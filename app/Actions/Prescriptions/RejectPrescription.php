<?php

namespace App\Actions\Prescriptions;

use App\Enums\Role;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LicenceGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Clinically reject a suspect or invalid prescription.
 *
 * Sets status to Rejected and logs suspect-prescription event (FR-4.12, AC-13).
 *
 * @uc UC-20
 *
 * @fr FR-4.3, FR-4.8, FR-4.12
 *
 * @br BR-03, BR-12
 *
 * @ac AC-13
 */
class RejectPrescription
{
    public function __construct(
        private readonly LicenceGate $licenceGate,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        Prescription $prescription,
        string $reason
    ): Prescription {
        // 1. Authorisation
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasPermission('prescriptions.verify')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_verify'));
        }

        // 2. Licence check
        if (! $this->licenceGate->allows($user, 'prescription.verify')) {
            AuditLogger::record(
                action: 'prescription.verify.refused',
                subject: $prescription,
                old: ['status' => $prescription->status],
                new: [
                    'user_id' => $user->id,
                    'reason' => 'unlicensed_or_disallowed_category',
                ],
                user: $user,
                description: "Prescription rejection refused for {$user->name}: invalid or disallowed licence"
            );

            throw new RuntimeException(__('prescriptions.verify_refused_licence'));
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('prescriptions.rejection_reason_required'));
        }

        if (! in_array($prescription->status, [Prescription::STATUS_PENDING_VERIFICATION, Prescription::STATUS_REGISTERED], true)) {
            throw new InvalidArgumentException(__('prescriptions.cannot_reject_status', ['status' => $prescription->status]));
        }

        return DB::transaction(function () use ($user, $prescription, $reason) {
            $oldStatus = $prescription->status;

            // 3. Update prescription record
            $prescription->rejected_by = $user->id;
            $prescription->rejected_at = now();
            $prescription->rejection_reason = $reason;
            $prescription->status = Prescription::STATUS_REJECTED;
            $prescription->save();

            // 4. Record status history
            $prescription->transitionStatus(
                Prescription::STATUS_REJECTED,
                $user,
                $reason
            );

            // 5. Audit trail: standard rejection audit
            AuditLogger::record(
                action: 'prescription.rejected',
                subject: $prescription,
                old: ['status' => $oldStatus],
                new: [
                    'status' => Prescription::STATUS_REJECTED,
                    'rejected_by' => $user->id,
                    'rejected_at' => $prescription->rejected_at->toIso8601String(),
                    'reason' => $reason,
                ],
                user: $user,
                description: "Prescription {$prescription->prescription_number} rejected by {$user->name}: {$reason}"
            );

            // 6. Suspect prescription audit event (FR-4.12)
            AuditLogger::record(
                action: 'prescription.suspect',
                subject: $prescription,
                old: ['status' => $oldStatus],
                new: [
                    'status' => Prescription::STATUS_REJECTED,
                    'prescriber_name' => $prescription->prescriber_name,
                    'prescriber_reg_no' => $prescription->prescriber_registration_no,
                    'reason' => $reason,
                    'logged_by' => $user->id,
                ],
                user: $user,
                description: "Suspect prescription event logged for {$prescription->prescription_number}: {$reason}"
            );

            return $prescription->fresh(['lines.product', 'customer', 'rejecter', 'statusHistory']);
        });
    }
}

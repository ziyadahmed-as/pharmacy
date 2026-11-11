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
 * Clinically verify a registered prescription.
 *
 * Allowed only if the user holds a valid, unexpired professional licence,
 * matching permitted licence categories if configured (BR-12, FR-4.3, OQ-09, AC-13).
 *
 * @uc UC-20
 *
 * @fr FR-4.3, FR-4.8
 *
 * @br BR-03, BR-12
 *
 * @ac AC-13
 */
class VerifyPrescription
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
        ?string $notes = null
    ): Prescription {
        // 1. Authorisation: SA cannot verify clinical prescriptions
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasPermission('prescriptions.verify')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_verify'));
        }

        // 2. Licence Check (STEP 1, STEP 3, E1, AC-13)
        // Must hold valid staff record with unexpired licence and allowed category
        if (! $this->licenceGate->allows($user, 'prescription.verify')) {
            AuditLogger::record(
                action: 'prescription.verify.refused',
                subject: $prescription,
                old: ['status' => $prescription->status],
                new: [
                    'user_id' => $user->id,
                    'has_staff' => $user->staff !== null,
                    'reason' => 'unlicensed_or_disallowed_category',
                ],
                user: $user,
                description: "Prescription verification refused for {$user->name}: invalid or disallowed licence"
            );

            throw new RuntimeException(__('prescriptions.verify_refused_licence'));
        }

        // 3. State check: only pending_verification or registered can be verified
        if (! in_array($prescription->status, [Prescription::STATUS_PENDING_VERIFICATION, Prescription::STATUS_REGISTERED], true)) {
            throw new InvalidArgumentException(__('prescriptions.cannot_verify_status', ['status' => $prescription->status]));
        }

        return DB::transaction(function () use ($user, $prescription, $notes) {
            $oldStatus = $prescription->status;

            // 4. Update prescription record
            $prescription->verified_by = $user->id;
            $prescription->verified_at = now();
            $prescription->status = Prescription::STATUS_VERIFIED;
            $prescription->save();

            // 5. Record status history
            $prescription->transitionStatus(
                Prescription::STATUS_VERIFIED,
                $user,
                $notes ?? 'Clinical verification completed'
            );

            // 6. Audit trail
            AuditLogger::record(
                action: 'prescription.verified',
                subject: $prescription,
                old: ['status' => $oldStatus],
                new: [
                    'status' => Prescription::STATUS_VERIFIED,
                    'verified_by' => $user->id,
                    'verified_at' => $prescription->verified_at->toIso8601String(),
                ],
                user: $user,
                description: "Prescription {$prescription->prescription_number} verified by {$user->name}"
            );

            return $prescription->fresh(['lines.product', 'customer', 'verifier', 'statusHistory']);
        });
    }
}


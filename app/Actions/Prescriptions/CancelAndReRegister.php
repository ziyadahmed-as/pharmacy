<?php

namespace App\Actions\Prescriptions;

use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cancel an existing prescription and register a corrected replacement.
 *
 * @uc UC-19
 *
 * @fr FR-4.11
 */
class CancelAndReRegister
{
    public function __construct(
        private readonly RegisterPrescription $register,
    ) {}

    /**
     * @param  array{name?: string, phone?: string, customer_id?: int|null}  $patientData
     * @param  array<int, array{product_id: int, dose: string, quantity: int}>  $lines
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        Prescription $original,
        string $cancellationReason,
        string $prescriberName,
        string $prescriberRegNo,
        string $prescriptionDate,
        array $patientData,
        array $lines,
        ?string $scanPath = null,
    ): Prescription {
        if (! $user->hasPermission('prescriptions.register')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_register'));
        }

        $cancellationReason = trim($cancellationReason);
        if ($cancellationReason === '') {
            throw new InvalidArgumentException(__('prescriptions.cancellation_reason_required'));
        }

        if (! $original->isActive()) {
            throw new InvalidArgumentException(__('prescriptions.cannot_cancel_inactive'));
        }

        return DB::transaction(function () use ($user, $original, $cancellationReason, $prescriberName, $prescriberRegNo, $prescriptionDate, $patientData, $lines, $scanPath) {
            // Register the new prescription first
            $newPrescription = $this->register->execute(
                $user,
                $prescriberName,
                $prescriberRegNo,
                $prescriptionDate,
                $patientData,
                $lines,
                $scanPath,
            );

            // Cancel the original and link to replacement
            $original->cancellation_reason = $cancellationReason;
            $original->replaced_by_id = $newPrescription->id;
            $original->save();

            $original->transitionStatus(Prescription::STATUS_CANCELLED, $user, "Cancelled and replaced by {$newPrescription->prescription_number}: {$cancellationReason}");

            AuditLogger::record(
                action: 'prescription.cancelled_and_reregistered',
                subject: $original,
                old: ['status' => 'active'],
                new: [
                    'cancelled_prescription' => $original->prescription_number,
                    'replacement_prescription' => $newPrescription->prescription_number,
                    'reason' => $cancellationReason,
                ],
                user: $user,
                description: "Prescription {$original->prescription_number} cancelled and replaced by {$newPrescription->prescription_number}",
            );

            return $newPrescription;
        });
    }
}

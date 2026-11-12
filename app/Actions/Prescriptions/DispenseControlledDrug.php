<?php

namespace App\Actions\Prescriptions;

use App\Actions\Sales\CompleteSale;
use App\Exceptions\NoOpenShiftException;
use App\Exceptions\PrescriptionNotDispensableException;
use App\Exceptions\QuantityExceedsPrescriptionException;
use App\Models\DispensingEvent;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShift;
use App\Services\FefoBatchResolver;
use App\Services\LicenceGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Dispense a controlled drug prescription line inside an open shift.
 *
 * Rules enforced:
 *  - Feature switch: pharmacy.handles_controlled_drugs must be true (OQ-01)
 *  - User must hold prescriptions.dispense permission (SA blocked)
 *  - LicenceGate: valid unexpired licence required for 'controlled.dispense' (BR-12, AC-13)
 *  - Open shift required
 *  - Prescription must be Verified or Partially Dispensed (E1, AC-03)
 *  - Prescription must not be expired for dispensing (E2, AC-12)
 *  - Product classification must be 'controlled'
 *  - Staff confirm patient and prescriber details
 *  - Quantity > remaining → reject unless licensed override reason given (BR-17, A2)
 *  - Repeat purchase within repeat_purchase_warning_days → warning (A1)
 *  - Atomic sale, dispensing event, stock movement, and controlled drug register entry
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3, FR-4.9
 *
 * @br BR-03, BR-11, BR-12, BR-17
 *
 * @ac AC-13, AC-34
 */
class DispenseControlledDrug
{
    public function __construct(
        private readonly CompleteSale $completeSale,
        private readonly LicenceGate $licenceGate,
    ) {}

    /**
     * @param  array<int, array{method: string, amount: int, reference?: string|null}>  $payments
     *
     * @throws AuthorizationException
     * @throws NoOpenShiftException
     * @throws PrescriptionNotDispensableException
     * @throws QuantityExceedsPrescriptionException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function execute(
        User $user,
        Prescription $prescription,
        int $lineId,
        int $quantity,
        array $payments,
        string $idempotencyKey,
        ?int $batchId = null,
        ?string $overrideReason = null,
        bool $repeatWarningAcknowledged = false,
        ?string $confirmedPatientName = null,
        ?string $confirmedPrescriberName = null
    ): Sale {
        // 0. Feature switch check (OQ-01)
        if (! (bool) setting('pharmacy.handles_controlled_drugs', true)) {
            throw new RuntimeException(__('controlled_drugs.module_disabled'));
        }

        // 1. Permission check — SA cannot dispense
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_dispense'));
        }

        if (! $user->can('prescriptions.dispense')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_dispense'));
        }

        // 2. LicenceGate: valid unexpired licence required for controlled dispensing (BR-12, AC-13)
        if (! $this->licenceGate->allows($user, 'controlled.dispense')) {
            throw new AuthorizationException(__('controlled_drugs.unauthorized_to_dispense_licence'));
        }

        // 3. Open shift check
        $shift = CurrentShift::requireForUser($user);
        if (! $shift->isOpen()) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        // 4. Prescription dispensability check (E1, AC-03)
        if (! $prescription->isDispensable()) {
            throw new PrescriptionNotDispensableException(
                __('prescriptions.not_dispensable', ['status' => $prescription->statusLabel()])
            );
        }

        // 5. Prescription expiry check (E2, AC-12)
        if ($prescription->isExpiredForDispensing()) {
            throw new PrescriptionNotDispensableException(__('prescriptions.dispensing_expired'));
        }

        // 6. Find line
        /** @var PrescriptionLine $line */
        $line = $prescription->lines()->where('id', $lineId)->firstOrFail();

        if ($line->isFullyDispensed()) {
            throw new PrescriptionNotDispensableException(
                __('prescriptions.line_fully_dispensed', ['product' => $line->product->brand])
            );
        }

        // 7. Verify product is controlled
        if ($line->product->classification !== 'controlled') {
            throw new InvalidArgumentException(
                __('controlled_drugs.not_controlled_product', ['product' => $line->product->brand])
            );
        }

        // 8. Staff confirm patient and prescriber details
        $patientName = trim((string) ($confirmedPatientName ?: $prescription->customer->name));
        $prescriberName = trim((string) ($confirmedPrescriberName ?: $prescription->prescriber_name));

        if ($patientName === '' || $prescriberName === '') {
            throw new InvalidArgumentException(__('controlled_drugs.patient_prescriber_required'));
        }

        $remaining = $line->remainingQuantity();

        // 9. Quantity guard (A2, BR-17)
        if ($quantity > $remaining) {
            if (empty($overrideReason)) {
                throw new QuantityExceedsPrescriptionException(
                    __('prescriptions.quantity_exceeds_prescribed', [
                        'requested' => $quantity,
                        'remaining' => $remaining,
                    ])
                );
            }
        }

        // 10. Repeat purchase warning (A1)
        if (! $repeatWarningAcknowledged) {
            $warningDays = (int) setting('prescriptions.repeat_purchase_warning_days', 0);
            if ($warningDays > 0) {
                $recentDispensing = DispensingEvent::query()
                    ->whereHas('prescriptionLine', function ($q) use ($line) {
                        $q->where('product_id', $line->product_id);
                    })
                    ->whereHas('prescriptionLine.prescription', function ($q) use ($prescription) {
                        $q->where('customer_id', $prescription->customer_id);
                    })
                    ->where('dispensed_at', '>=', now()->subDays($warningDays))
                    ->exists();

                if ($recentDispensing) {
                    throw new InvalidArgumentException(
                        __('prescriptions.repeat_purchase_warning', [
                            'days' => $warningDays,
                            'product' => $line->product->brand,
                        ])
                    );
                }
            }
        }

        // 11. FEFO batch resolution
        $product = $line->product;
        $allocations = FefoBatchResolver::resolve(
            product: $product,
            requestedQuantity: $quantity,
            overrideBatchId: $batchId,
            overrideReason: $overrideReason,
            actor: $user
        );

        $firstBatch = $allocations[0]['batch'];

        // 12. Build items array for CompleteSale
        $items = [];
        foreach ($allocations as $alloc) {
            $items[] = [
                'product_id' => $product->id,
                'quantity' => $alloc['quantity'],
                'batch_id' => $alloc['batch']->id,
                'override_reason' => $alloc['override_reason'],
            ];
        }

        // 13. Execute sale + dispensing event + stock movements in one atomic transaction
        return DB::transaction(function () use (
            $user, $prescription, $line, $quantity, $payments, $idempotencyKey,
            $items, $firstBatch, $overrideReason, $patientName, $prescriberName
        ) {
            $sale = $this->completeSale->execute(
                user: $user,
                items: $items,
                payments: $payments,
                idempotencyKey: $idempotencyKey,
                prescriptionId: $prescription->id,
                patientName: $patientName,
                prescriberName: $prescriberName
            );

            // Create dispensing event
            DispensingEvent::firstOrCreate(
                ['sale_id' => $sale->id, 'prescription_line_id' => $line->id],
                [
                    'batch_id' => $firstBatch->id,
                    'quantity' => $quantity,
                    'user_id' => $user->id,
                    'dispensed_at' => now(),
                ]
            );

            // Update prescription line
            $line->increment('quantity_dispensed', $quantity);
            $line->refresh();

            // Recompute prescription status
            $allLines = $prescription->lines()->get();
            $allFullyDispensed = $allLines->every(fn ($l) => $l->isFullyDispensed());

            $newStatus = $allFullyDispensed
                ? Prescription::STATUS_FULLY_DISPENSED
                : Prescription::STATUS_PARTIALLY_DISPENSED;

            if ($prescription->status !== $newStatus) {
                $prescription->transitionStatus(
                    to: $newStatus,
                    actor: $user,
                    notes: $overrideReason ? "Override: {$overrideReason}" : null
                );
            }

            // Audit
            AuditLogger::record(
                action: 'controlled_drug.dispensed',
                subject: $prescription,
                old: ['status' => $prescription->getOriginal('status')],
                new: [
                    'status' => $prescription->status,
                    'line_id' => $line->id,
                    'product' => $line->product->brand,
                    'quantity' => $quantity,
                    'patient' => $patientName,
                    'prescriber' => $prescriberName,
                    'sale_receipt_no' => $sale->receipt_no,
                ],
                metadata: [
                    'override_reason' => $overrideReason,
                    'batch_id' => $firstBatch->id,
                ],
                user: $user,
                description: "Controlled drug dispensed: {$quantity} of {$line->product->brand} to {$patientName} (Rx {$prescription->prescription_number})"
            );

            return $sale;
        });
    }
}

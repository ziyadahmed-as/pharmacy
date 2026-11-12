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

/**
 * Dispense one prescription line inside an open shift.
 *
 * Rules enforced:
 *  - User must hold `prescriptions.dispense` permission (not SA)
 *  - LicenceGate: valid unexpired licence required (BR-12, FR-10.3)
 *  - Open shift required
 *  - Prescription must be Verified or Partially Dispensed (E1, AC-03)
 *  - Prescription must not be expired for dispensing (E2)
 *  - Quantity > remaining → reject unless a licensed override reason given (A2, BR-17)
 *  - Repeat purchase within `prescriptions.repeat_purchase_warning_days` → exception (A1)
 *  - Controlled drug → stub exception (A3)
 *  - Idempotency handled by CompleteSale's idempotency key
 *
 * @uc UC-21
 *
 * @fr FR-4.1, FR-4.8, FR-4.9, FR-4.10, FR-3.4, FR-3.5, FR-2.4
 *
 * @br BR-03, BR-12, BR-17
 *
 * @ac AC-03, AC-12
 */
class DispensePrescriptionLine
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
    ): Sale {
        // 1. Permission check — SA cannot dispense
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_dispense'));
        }

        if (! $user->can('prescriptions.dispense')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_dispense'));
        }

        // 2. LicenceGate (BR-12, FR-10.3, AC-13)
        if (! $this->licenceGate->allows($user, 'prescription.dispense')) {
            throw new AuthorizationException(__('prescriptions.dispense_refused_licence'));
        }

        // 3. Open shift check
        $shift = CurrentShift::requireForUser($user);
        if (! $shift->isOpen()) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        // 4. Prescription dispensability (E1, AC-03)
        if (! $prescription->isDispensable()) {
            throw new PrescriptionNotDispensableException(
                __('prescriptions.not_dispensable', ['status' => $prescription->statusLabel()])
            );
        }

        // 5. Prescription expiry (E2, AC-12)
        if ($prescription->isExpiredForDispensing()) {
            throw new PrescriptionNotDispensableException(__('prescriptions.dispensing_expired'));
        }

        // 6. Find the line
        /** @var PrescriptionLine $line */
        $line = $prescription->lines()->where('id', $lineId)->firstOrFail();

        if ($line->isFullyDispensed()) {
            throw new PrescriptionNotDispensableException(
                __('prescriptions.line_fully_dispensed', ['product' => $line->product->brand])
            );
        }

        // 7. Controlled drug handover to UC-22
        if ($line->product->classification === 'controlled') {
            return app(DispenseControlledDrug::class)->execute(
                user: $user,
                prescription: $prescription,
                lineId: $lineId,
                quantity: $quantity,
                payments: $payments,
                idempotencyKey: $idempotencyKey,
                batchId: $batchId,
                overrideReason: $overrideReason,
                repeatWarningAcknowledged: $repeatWarningAcknowledged,
            );
        }

        $remaining = $line->remainingQuantity();

        // 8. Quantity guard (A2, BR-17)
        if ($quantity > $remaining) {
            if (empty($overrideReason)) {
                throw new QuantityExceedsPrescriptionException(
                    __('prescriptions.quantity_exceeds_prescribed', [
                        'requested' => $quantity,
                        'remaining' => $remaining,
                    ])
                );
            }

            // Override only allowed when user has a valid licence (already checked above)
        }

        // 9. Repeat purchase warning (A1)
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

        // 10. FEFO batch resolution
        $product = $line->product;
        $allocations = FefoBatchResolver::resolve(
            product: $product,
            requestedQuantity: $quantity,
            overrideBatchId: $batchId,
            overrideReason: $overrideReason,
            actor: $user
        );

        $firstBatch = $allocations[0]['batch'];

        // 11. Build items array for CompleteSale
        $items = [];
        foreach ($allocations as $alloc) {
            $items[] = [
                'product_id' => $product->id,
                'quantity' => $alloc['quantity'],
                'batch_id' => $alloc['batch']->id,
                'override_reason' => $alloc['override_reason'],
            ];
        }

        // 12. Execute sale + dispensing in one transaction
        return DB::transaction(function () use (
            $user, $prescription, $line, $quantity, $payments, $idempotencyKey,
            $items, $firstBatch, $overrideReason
        ) {
            // Create the sale (CompleteSale already handles idempotency internally)
            // We pass prescription_id via the extended signature
            $sale = $this->completeSale->execute(
                user: $user,
                items: $items,
                payments: $payments,
                idempotencyKey: $idempotencyKey,
                prescriptionId: $prescription->id,
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

            // Update prescription line quantity
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
                action: 'prescription.dispensed',
                subject: $prescription,
                old: ['status' => $prescription->getOriginal('status')],
                new: [
                    'status' => $prescription->status,
                    'line_id' => $line->id,
                    'quantity' => $quantity,
                    'sale_receipt_no' => $sale->receipt_no,
                ],
                metadata: ['override_reason' => $overrideReason],
                user: $user,
                description: "Dispensed {$quantity} of {$line->product->brand} on Rx {$prescription->prescription_number} → {$sale->receipt_no}"
            );

            return $sale;
        });
    }
}

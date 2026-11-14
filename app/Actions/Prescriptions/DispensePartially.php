<?php

namespace App\Actions\Prescriptions;

use App\Exceptions\PrescriptionNotDispensableException;
use App\Exceptions\QuantityExceedsPrescriptionException;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dispense a prescription line partially or as a refill.
 *
 * @uc UC-24 @fr FR-4.5, 4.8, 4.10; D-08
 */
class DispensePartially
{
    public function __construct(
        private readonly DispensePrescriptionLine $dispensePrescriptionLine,
    ) {}

    /**
     * @param  array<int, array{method: string, amount: int, reference?: string|null}>  $payments
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
        ?string $nextRefillDate = null,
        bool $isRefill = false,
    ): Sale {
        // STEP 3 / E1: Refuse a refill or partial dispense after prescription expiry
        if ($prescription->isExpiredForDispensing() || $prescription->status === Prescription::STATUS_EXPIRED) {
            throw new PrescriptionNotDispensableException(
                $isRefill
                    ? __('prescriptions.expired_refill_refused')
                    : __('prescriptions.dispensing_expired')
            );
        }

        // Find the line
        /** @var PrescriptionLine $line */
        $line = $prescription->lines()->where('id', $lineId)->firstOrFail();

        // Handle Refill initiation
        if ($isRefill) {
            if ($line->refills_allowed <= 0 || $line->refills_used >= $line->refills_allowed) {
                throw new PrescriptionNotDispensableException(__('prescriptions.no_refills_remaining'));
            }

            // If remaining quantity was 0 from previous fill, reset it for this refill cycle
            if ($line->remainingQuantity() === 0) {
                $line->update([
                    'remaining_quantity' => $line->quantity_prescribed,
                ]);
                $line->refresh();
            }
        }

        $remaining = $line->remainingQuantity();

        // STEP 3 / E1: Refuse a quantity above the remaining amount
        if ($quantity > $remaining) {
            throw new QuantityExceedsPrescriptionException(
                __('prescriptions.quantity_exceeds_prescribed', [
                    'requested' => $quantity,
                    'remaining' => $remaining,
                ])
            );
        }

        return DB::transaction(function () use (
            $user,
            $prescription,
            $line,
            $quantity,
            $payments,
            $idempotencyKey,
            $batchId,
            $overrideReason,
            $repeatWarningAcknowledged,
            $nextRefillDate,
            $isRefill
        ) {
            // Reuse UC-21 to execute dispensing, sale, FEFO batch allocation, stock deduction, and event creation
            $sale = $this->dispensePrescriptionLine->execute(
                user: $user,
                prescription: $prescription,
                lineId: $line->id,
                quantity: $quantity,
                payments: $payments,
                idempotencyKey: $idempotencyKey,
                batchId: $batchId,
                overrideReason: $overrideReason,
                repeatWarningAcknowledged: $repeatWarningAcknowledged,
            );

            $line->refresh();

            // Remaining quantity is already decremented by DispensePrescriptionLine
            $newRemaining = $line->remaining_quantity ?? max(0, $line->quantity_prescribed - $line->quantity_dispensed);

            // Calculate or format next refill date
            $calculatedRefillDate = $this->resolveNextRefillDate($line, $quantity, $nextRefillDate);

            // Update refill counts
            if ($isRefill) {
                $line->refills_used = $line->refills_used + 1;
            }

            // Set next refill date if there is remaining quantity or refills left
            $hasFutureDispenses = $newRemaining > 0 || ($line->refills_allowed > 0 && $line->refills_used < $line->refills_allowed);
            $line->next_refill_date = $hasFutureDispenses ? $calculatedRefillDate : null;
            $line->save();

            // Status stays Partially Dispensed until the full quantity is supplied across all lines and refills
            $prescription->refresh()->load('lines');
            $allLinesComplete = $prescription->lines->every(fn (PrescriptionLine $l) => $l->isFullyDispensed());

            $expectedStatus = $allLinesComplete
                ? Prescription::STATUS_FULLY_DISPENSED
                : Prescription::STATUS_PARTIALLY_DISPENSED;

            if ($prescription->status !== $expectedStatus) {
                $prescription->transitionStatus(
                    to: $expectedStatus,
                    actor: $user,
                    notes: $isRefill ? 'Refill dispensed' : 'Partial quantity dispensed'
                );
            }

            // Audit
            AuditLogger::record(
                action: $isRefill ? 'prescription.refilled' : 'prescription.partially_dispensed',
                subject: $prescription,
                old: ['status' => $prescription->getOriginal('status')],
                new: [
                    'status' => $prescription->status,
                    'line_id' => $line->id,
                    'quantity' => $quantity,
                    'remaining_quantity' => $line->remaining_quantity,
                    'refills_used' => $line->refills_used,
                    'next_refill_date' => $line->next_refill_date?->toDateString(),
                    'sale_receipt_no' => $sale->receipt_no,
                ],
                metadata: [
                    'is_refill' => $isRefill,
                    'batch_id' => $batchId,
                    'override_reason' => $overrideReason,
                ],
                user: $user,
                description: sprintf(
                    '%s %d of %s on Rx %s (remaining: %d, next refill: %s)',
                    $isRefill ? 'Refilled' : 'Partially dispensed',
                    $quantity,
                    $line->product->brand,
                    $prescription->prescription_number,
                    $line->remaining_quantity,
                    $line->next_refill_date?->toDateString() ?? 'none'
                )
            );

            return $sale;
        });
    }

    /**
     * Resolve or calculate next refill date.
     */
    protected function resolveNextRefillDate(PrescriptionLine $line, int $dispensedQty, ?string $explicitDate = null): string
    {
        if (! empty($explicitDate)) {
            return Carbon::parse($explicitDate)->toDateString();
        }

        $dose = strtolower((string) $line->dose);
        $dailyDoses = 1;

        if (str_contains($dose, 'four times') || str_contains($dose, 'qid') || str_contains($dose, '4 times') || str_contains($dose, '4x')) {
            $dailyDoses = 4;
        } elseif (str_contains($dose, 'three times') || str_contains($dose, 'tid') || str_contains($dose, '3 times') || str_contains($dose, '3x')) {
            $dailyDoses = 3;
        } elseif (str_contains($dose, 'twice') || str_contains($dose, 'bid') || str_contains($dose, '2 times') || str_contains($dose, '2x')) {
            $dailyDoses = 2;
        }

        $days = max(1, (int) ceil($dispensedQty / $dailyDoses));

        return now()->addDays($days)->toDateString();
    }
}

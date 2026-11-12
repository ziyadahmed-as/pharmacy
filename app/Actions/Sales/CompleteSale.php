<?php

namespace App\Actions\Sales;

use App\Exceptions\NoOpenShiftException;
use App\Exceptions\PaymentMismatchException;
use App\Exceptions\SellabilityException;
use App\Models\DailyClosing;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShift;
use App\Services\DocumentSequence;
use App\Services\FefoBatchResolver;
use App\Services\PriceResolver;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Complete an OTC or non-drug sale inside an open shift on a named till.
 *
 * @uc UC-13
 *
 * @fr FR-3.1, FR-3.2, FR-3.3, FR-3.4, FR-3.5, FR-3.11, FR-2.4
 *
 * @br BR-01, BR-02, BR-03, BR-04, BR-05, BR-20
 *
 * @ac AC-01, AC-02, AC-04, AC-20, AC-22, AC-31, AC-36
 */
class CompleteSale
{
    /**
     * Execute sale transaction atomically with FEFO stock deduction and receipt sequence.
     *
     * @param  User  $user  Cashier executing the sale
     * @param  array<int, array{product_id: int, quantity: int, unit_id?: int|null, batch_id?: int|null, override_reason?: string|null}>  $items
     * @param  array<int, array{method: string, amount: int, reference?: string|null}>  $payments
     * @param  int|null  $prescriptionId  When set, Rx/controlled products are allowed (UC-21)
     *
     * @throws AuthorizationException
     * @throws NoOpenShiftException
     * @throws PaymentMismatchException
     * @throws SellabilityException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        array $items,
        array $payments,
        string $idempotencyKey,
        ?int $prescriptionId = null,
        ?string $patientName = null,
        ?string $prescriberName = null
    ): Sale {
        // 1. Authorisation: SA cannot process sales
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('sales.unauthorized_to_sell'));
        }

        // 1.5. Day lock check (FR-3.10, AC-24)
        if (DailyClosing::isLockedForDate(now())) {
            throw new \RuntimeException(__('closing.day_is_locked_error'));
        }

        // 2. Open Shift Check
        $shift = CurrentShift::requireForUser($user);
        if (! $shift->isOpen()) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        if (empty($items)) {
            throw new InvalidArgumentException(__('sales.empty_cart_error'));
        }

        if (empty($payments)) {
            throw new InvalidArgumentException(__('sales.empty_payments_error'));
        }

        // 3. Atomically check idempotency and complete sale
        return DB::transaction(function () use ($user, $shift, $items, $payments, $idempotencyKey, $prescriptionId, $patientName, $prescriberName) {
            // E5: Check idempotency key under lock
            $existing = Sale::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load(['lines.product', 'lines.batch', 'lines.unit', 'payments', 'shift.till', 'user']);
            }

            $plannedLines = [];
            $saleTotalSantim = 0;

            // 4. Validate products, prices, FEFO and build planned lines
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // A3: Prescription / Controlled drug restriction
                if (in_array($product->classification, ['rx', 'controlled'], true) && $prescriptionId === null) {
                    throw new InvalidArgumentException(__('sales.prescription_required_error', ['product' => $product->brand]));
                }

                $unit = ! empty($item['unit_id']) ? ProductUnit::findOrFail($item['unit_id']) : null;
                $factor = $unit ? $unit->factor : 1;
                $quantityInUnit = max(1, (int) $item['quantity']);
                $quantityInBaseUnits = $quantityInUnit * $factor;

                // Price resolution
                $priceRecord = PriceResolver::at($product, $unit);
                if (! $priceRecord) {
                    throw new InvalidArgumentException(__('sales.no_price_configured', ['product' => $product->brand]));
                }

                $unitPriceSantim = (int) $priceRecord->price;
                $lineSubtotal = $quantityInUnit * $unitPriceSantim;
                $saleTotalSantim += $lineSubtotal;

                // Resolve FEFO batches (or manual override)
                $allocations = FefoBatchResolver::resolve(
                    product: $product,
                    requestedQuantity: $quantityInBaseUnits,
                    overrideBatchId: ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                    overrideReason: $item['override_reason'] ?? null,
                    actor: $user
                );

                // If split across batches, calculate proportional subtotals
                $totalAllocatedQty = array_sum(array_column($allocations, 'quantity'));
                foreach ($allocations as $idx => $alloc) {
                    $allocQty = $alloc['quantity'];
                    // Proportion of total subtotal for this split
                    $allocSubtotal = ($idx === count($allocations) - 1)
                        ? ($lineSubtotal - array_sum(array_column(array_slice($plannedLines, -count($allocations) + 1), 'subtotal')))
                        : (int) round(($lineSubtotal * $allocQty) / $totalAllocatedQty);

                    $plannedLines[] = [
                        'product_id' => $product->id,
                        'unit_id' => $unit?->id,
                        'batch_id' => $alloc['batch']->id,
                        'batch' => $alloc['batch'],
                        'quantity' => $allocQty,
                        'unit_price' => $unitPriceSantim,
                        'subtotal' => $allocSubtotal,
                        'is_fefo_override' => $alloc['is_override'],
                        'override_reason' => $alloc['override_reason'],
                    ];
                }
            }

            // 5. Payment reconciliation (E3, AC-22)
            $totalPaymentsSantim = 0;
            foreach ($payments as $payment) {
                $amount = (int) $payment['amount'];
                if ($amount <= 0) {
                    throw new InvalidArgumentException(__('sales.payment_amount_invalid'));
                }
                $totalPaymentsSantim += $amount;
            }

            if ($totalPaymentsSantim !== $saleTotalSantim) {
                throw new PaymentMismatchException(__('sales.payment_mismatch_error', [
                    'total' => number_format($saleTotalSantim / 100, 2),
                    'paid' => number_format($totalPaymentsSantim / 100, 2),
                ]));
            }

            // 6. Generate sequential gapless receipt number
            $receiptNo = DocumentSequence::next('sale', 'REC');

            // 7. Create Sale record
            $sale = Sale::create([
                'receipt_no' => $receiptNo,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'prescription_id' => $prescriptionId,
                'status' => 'completed',
                'total' => $saleTotalSantim,
                'idempotency_key' => $idempotencyKey,
                'reprint_count' => 0,
                'completed_at' => now(),
            ]);

            // 8. Create Sale Lines and post stock movements
            foreach ($plannedLines as $lineData) {
                SaleLine::create([
                    'sale_id' => $sale->id,
                    'product_id' => $lineData['product_id'],
                    'unit_id' => $lineData['unit_id'],
                    'batch_id' => $lineData['batch_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'subtotal' => $lineData['subtotal'],
                    'is_fefo_override' => $lineData['is_fefo_override'],
                    'override_reason' => $lineData['override_reason'],
                ]);

                // Post stock movement deduction (BR-04)
                StockMovementService::post(
                    batch: $lineData['batch'],
                    signedQuantity: -$lineData['quantity'],
                    type: 'sale',
                    source: $sale,
                    user: $user,
                    status: 'sellable',
                    location: null,
                    description: "Sale {$receiptNo}",
                    secondPerson: null,
                    patientName: $patientName,
                    prescriberName: $prescriberName
                );
            }

            // 9. Record Payments
            foreach ($payments as $paymentData) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $paymentData['method'],
                    'amount' => (int) $paymentData['amount'],
                    'reference' => ! empty($paymentData['reference']) ? trim($paymentData['reference']) : null,
                ]);
            }

            // 10. Audit log (BR-15)
            AuditLogger::record(
                action: 'sale.completed',
                subject: $sale,
                old: [],
                new: [
                    'receipt_no' => $sale->receipt_no,
                    'total' => $sale->total,
                    'shift_id' => $shift->id,
                    'lines_count' => count($plannedLines),
                    'payments_count' => count($payments),
                ],
                metadata: [
                    'total_etb' => $sale->totalEtb(),
                    'receipt_no' => $sale->receipt_no,
                ],
                user: $user,
                description: "Sale {$sale->receipt_no} completed for {$sale->totalEtb()} ETB"
            );

            return $sale->load(['lines.product', 'lines.batch', 'lines.unit', 'payments', 'shift.till', 'user']);
        });
    }
}

<?php

namespace App\Services;

use App\Exceptions\FefoOverrideException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SellabilityException;
use App\Models\Batch;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves batches for stock issue according to First-Expired, First-Out (FEFO).
 *
 * @uc UC-13
 *
 * @fr FR-3.2, FR-2.4
 *
 * @br BR-01, BR-02
 *
 * @ac AC-02
 */
class FefoBatchResolver
{
    /**
     * Retrieve all eligible, sellable batches for a product ordered by expiry ascending (FEFO).
     *
     * @return Collection<int, Batch>
     */
    public static function getEligibleBatches(Product $product): Collection
    {
        // 1. Recalled batches must not be eligible
        $recalledBatchIds = QuarantineRecord::where('status', QuarantineRecord::STATUS_OPEN)
            ->where('reason', QuarantineRecord::REASON_RECALLED)
            ->pluck('batch_id')
            ->all();

        return Batch::query()
            ->where('product_id', $product->id)
            ->where('expiry_date', '>', now())
            ->whereNotIn('id', $recalledBatchIds)
            ->whereHas('batchStocks', function ($q) {
                $q->where('status', 'sellable')
                    ->where('quantity', '>', 0);
            })
            ->with(['batchStocks' => function ($q) {
                $q->where('status', 'sellable');
            }])
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Resolve batch allocations for a given requested quantity in base units.
     * Splits across multiple batches if necessary under FEFO.
     * If an override batch is chosen, requires a non-empty reason and audits the override.
     *
     * @param  int  $requestedQuantity  In base units
     * @return array<int, array{batch: Batch, quantity: int, is_override: bool, override_reason: ?string}>
     *
     * @throws InsufficientStockException
     * @throws FefoOverrideException
     * @throws SellabilityException
     */
    public static function resolve(
        Product $product,
        int $requestedQuantity,
        ?int $overrideBatchId = null,
        ?string $overrideReason = null,
        ?User $actor = null
    ): array {
        $user = $actor ?? Auth::user();

        // 1. Manual Batch Override (A2)
        if ($overrideBatchId !== null) {
            $eligible = self::getEligibleBatches($product);
            $earliest = $eligible->first();

            $isDifferentFromEarliest = (! $earliest || $earliest->id !== $overrideBatchId);

            $trimmedReason = trim((string) $overrideReason);
            if ($isDifferentFromEarliest && $trimmedReason === '') {
                throw new FefoOverrideException(__('sales.fefo_override_reason_required'));
            }

            $batch = Batch::with(['batchStocks' => fn ($q) => $q->where('status', 'sellable')])
                ->where('product_id', $product->id)
                ->findOrFail($overrideBatchId);

            // Guard check
            SellabilityGuard::check($product, $batch, $requestedQuantity, $user, 'sell');

            $availableSellable = (int) $batch->batchStocks->sum('quantity');
            if ($availableSellable < $requestedQuantity) {
                throw new InsufficientStockException(__('sales.insufficient_batch_stock', [
                    'batch' => $batch->batch_no,
                    'available' => $availableSellable,
                    'requested' => $requestedQuantity,
                ]));
            }

            if ($isDifferentFromEarliest) {
                AuditLogger::record(
                    action: 'sale.fefo_override',
                    subject: $batch,
                    old: ['default_batch_id' => $earliest?->id, 'default_batch_no' => $earliest?->batch_no],
                    new: ['selected_batch_id' => $batch->id, 'selected_batch_no' => $batch->batch_no, 'reason' => $trimmedReason],
                    metadata: [
                        'product_id' => $product->id,
                        'brand' => $product->brand,
                        'quantity' => $requestedQuantity,
                        'reason' => $trimmedReason,
                    ],
                    user: $user,
                    description: "FEFO batch override for {$product->brand}: chosen {$batch->batch_no} instead of {$earliest?->batch_no} with reason: {$trimmedReason}"
                );
            }

            return [
                [
                    'batch' => $batch,
                    'quantity' => $requestedQuantity,
                    'is_override' => $isDifferentFromEarliest,
                    'override_reason' => $isDifferentFromEarliest ? $trimmedReason : null,
                ],
            ];
        }

        // 2. Default FEFO resolution
        $eligibleBatches = self::getEligibleBatches($product);
        $totalAvailable = 0;
        foreach ($eligibleBatches as $b) {
            $totalAvailable += (int) $b->batchStocks->sum('quantity');
        }

        if ($totalAvailable < $requestedQuantity) {
            throw new InsufficientStockException(__('sales.insufficient_product_stock', [
                'product' => $product->brand,
                'available' => $totalAvailable,
                'requested' => $requestedQuantity,
            ]));
        }

        $allocations = [];
        $remainingNeeded = $requestedQuantity;

        foreach ($eligibleBatches as $batch) {
            $batchSellable = (int) $batch->batchStocks->sum('quantity');
            if ($batchSellable <= 0) {
                continue;
            }

            $take = min($remainingNeeded, $batchSellable);

            // Re-verify with SellabilityGuard
            SellabilityGuard::check($product, $batch, $take, $user, 'sell');

            $allocations[] = [
                'batch' => $batch,
                'quantity' => $take,
                'is_override' => false,
                'override_reason' => null,
            ];

            $remainingNeeded -= $take;
            if ($remainingNeeded <= 0) {
                break;
            }
        }

        return $allocations;
    }
}

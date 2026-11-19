<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query object for Stock on Hand (UC-11 Step 1).
 * Supports search (name, generic, barcode), filters (low_stock, near_expiry, expired, quarantine).
 * Strictly hides unit cost and valuation from users without cost.view permission (BR-06).
 *
 * @uc UC-11 @fr FR-2.10, 7.2 @br BR-06 @ac AC-16
 */
class StockOnHandQuery
{
    /**
     * @param array{
     *     search?: ?string,
     *     filter?: ?string,
     *     category_id?: ?int,
     * } $filters
     */
    public function __construct(private array $filters = []) {}

    public function builder(?User $viewer = null): Builder
    {
        $canViewCost = $viewer !== null && $viewer->hasPermission('cost.view');
        $f = $this->filters;

        $batchColumns = $canViewCost
            ? ['id', 'product_id', 'batch_no', 'manufacturing_date', 'expiry_date', 'unit_cost', 'supplier_name', 'created_at']
            : ['id', 'product_id', 'batch_no', 'manufacturing_date', 'expiry_date', 'supplier_name', 'created_at'];

        $nearExpiryWindows = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        $nearExpiryDays = (int) max(is_array($nearExpiryWindows) ? $nearExpiryWindows : [90]);
        $today = Carbon::today()->format('Y-m-d');
        $nearExpiryThreshold = Carbon::today()->addDays($nearExpiryDays)->format('Y-m-d');

        $query = Product::query()
            ->with([
                'category',
                'batches' => function ($bQ) use ($batchColumns, $f) {
                    $bQ->select(array_merge($batchColumns, ['quantity_on_hand', 'quarantine_qty', 'status']))
                        ->when(! empty($f['location_id']), fn ($q) => $q->whereHas('stocks', fn ($s) => $s->where('location_id', $f['location_id'])))
                        ->with(['stocks' => function ($sQ) use ($f) {
                            $sQ->select(['id', 'batch_id', 'location_id', 'status', 'quantity'])
                                ->with('location')
                                ->when(! empty($f['location_id']), fn ($q) => $q->where('location_id', $f['location_id']));
                        }])
                        ->orderBy('expiry_date');
                },
            ]);

        // Search: name, generic name, barcode
        if (! empty($f['search'])) {
            $term = trim($f['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('brand', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        // Category filter
        if (! empty($f['category_id'])) {
            $query->where('category_id', $f['category_id']);
        }

        // Location filter
        if (! empty($f['location_id'])) {
            $query->whereHas('batches.stocks', fn ($sQ) => $sQ->where('location_id', $f['location_id'])->where('quantity', '>', 0));
        }

        // Status filters
        if (! empty($f['filter'])) {
            switch ($f['filter']) {
                case 'low_stock':
                    $query->where(function (Builder $q) use ($today) {
                        $q->whereRaw('(
                            SELECT COALESCE(SUM(blo.quantity_on_hand), 0)
                            FROM batches blo
                            WHERE blo.product_id  = products.id
                              AND blo.expiry_date > ?
                              AND blo.status       = "SELLABLE"
                        ) <= products.reorder_level', [$today]);
                    });
                    break;

                case 'near_expiry':
                    $query->whereHas('batches', function ($bQ) use ($today, $nearExpiryThreshold) {
                        $bQ->where('expiry_date', '>', $today)
                            ->where('expiry_date', '<=', $nearExpiryThreshold)
                            ->where('quantity_on_hand', '>', 0);    // SSOT
                    });
                    break;

                case 'expired':
                    $query->whereHas('batches', function ($bQ) use ($today) {
                        $bQ->where('expiry_date', '<=', $today)
                            ->where(function ($q) {
                                $q->where('quantity_on_hand', '>', 0)
                                  ->orWhere('quarantine_qty', '>', 0);
                            });
                    });
                    break;

                case 'quarantine':
                    $query->whereHas('batches', function ($bQ) {
                        $bQ->where('quarantine_qty', '>', 0);    // SSOT
                    });
                    break;
            }
        }

        // Nearest-expiry sort (UC-13, UC-11):
        //   1. Products with sellable stock first  (has_stock DESC)
        //   2. Among in-stock: earliest expiry first  (min_expiry ASC, NULLs last)
        //   3. Tiebreak by brand name  (brand ASC)
        //
        // The two correlated sub-queries are intentionally simple; they re-use the
        // same indexes (idx_batches_expiry_product, idx_bstocks_batch_status_qty)
        // that NearestExpiryQuery uses, so the planner produces an index-only scan.
        $today = Carbon::today()->toDateString();

        $minExpirySql = <<<'SQL'
            SELECT MIN(bne.expiry_date)
            FROM batches AS bne
            WHERE bne.product_id  = products.id
              AND bne.expiry_date > ?
              AND bne.status      = 'SELLABLE'
              AND bne.quantity_on_hand > 0
        SQL;

        $totalSellableSql = <<<'SQL'
            SELECT COALESCE(SUM(bnts.quantity_on_hand), 0)
            FROM batches AS bnts
            WHERE bnts.product_id  = products.id
              AND bnts.expiry_date > ?
              AND bnts.status      = 'SELLABLE'
              AND bnts.quantity_on_hand > 0
        SQL;

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $hasSqlExpr   = "({$totalSellableSql}) > 0";
        $orderRaw = "({$hasSqlExpr}) DESC, ({$minExpirySql}) ASC, products.brand ASC";

        return $query
            ->orderByRaw($orderRaw, [$today, $today]);
    }

    /**
     * Compute aggregated stock metrics for a product.
     *
     * @return array{
     *     total_sellable: int,
     *     total_quarantine: int,
     *     total_valuation: ?int,
     *     has_near_expiry: bool,
     *     has_expired: bool
     * }
     */
    public static function metrics(Product $product, bool $canViewCost): array
    {
        $totalSellable = 0;
        $totalQuarantine = 0;
        $totalValuation = $canViewCost ? 0 : null;

        $nearExpiryWindows = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        $nearExpiryDays = (int) max(is_array($nearExpiryWindows) ? $nearExpiryWindows : [90]);
        $today = Carbon::today()->format('Y-m-d');
        $nearExpiryThreshold = Carbon::today()->addDays($nearExpiryDays)->format('Y-m-d');

        $hasNearExpiry = false;
        $hasExpired = false;

        foreach ($product->batches as $batch) {
            $exp = $batch->expiry_date?->format('Y-m-d');

            $batchSellable   = (int) $batch->quantity_on_hand;   // SSOT
            $batchQuarantine = (int) $batch->quarantine_qty;     // SSOT
            $batchQty        = $batchSellable + $batchQuarantine;

            $totalSellable   += $batchSellable;
            $totalQuarantine += $batchQuarantine;

            if ($batchQty > 0 && $exp) {
                if ($exp <= $today) {
                    $hasExpired = true;
                } elseif ($exp <= $nearExpiryThreshold) {
                    $hasNearExpiry = true;
                }
            }

            if ($canViewCost && $batch->unit_cost !== null) {
                $totalValuation += (int) ($batchSellable * $batch->unit_cost);
            }
        }

        return [
            'total_sellable' => $totalSellable,
            'total_quarantine' => $totalQuarantine,
            'total_valuation' => $totalValuation,
            'has_near_expiry' => $hasNearExpiry,
            'has_expired' => $hasExpired,
        ];
    }
}

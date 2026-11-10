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

        $nearExpiryDays = (int) (setting('stock.near_expiry_days') ?? 90);
        $today = Carbon::today()->format('Y-m-d');
        $nearExpiryThreshold = Carbon::today()->addDays($nearExpiryDays)->format('Y-m-d');

        $query = Product::query()
            ->with([
                'category',
                'batches' => function ($bQ) use ($batchColumns) {
                    $bQ->select($batchColumns)
                        ->with(['stocks' => function ($sQ) {
                            $sQ->select(['id', 'batch_id', 'location', 'status', 'quantity']);
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

        // Status filters
        if (! empty($f['filter'])) {
            switch ($f['filter']) {
                case 'low_stock':
                    // Products with sellable stock <= reorder_level
                    $query->where(function (Builder $q) {
                        $q->whereHas('batches.stocks', function ($sQ) {
                            $sQ->where('status', 'sellable');
                        }, '<=', 0)
                            ->orWhereRaw('(SELECT COALESCE(SUM(bs.quantity), 0) FROM batch_stocks bs INNER JOIN batches b ON b.id = bs.batch_id WHERE b.product_id = products.id AND bs.status = "sellable") <= products.reorder_level');
                    });
                    break;

                case 'near_expiry':
                    // Batches expiring within the threshold but not yet expired
                    $query->whereHas('batches', function ($bQ) use ($today, $nearExpiryThreshold) {
                        $bQ->where('expiry_date', '>', $today)
                            ->where('expiry_date', '<=', $nearExpiryThreshold)
                            ->whereHas('stocks', fn ($sQ) => $sQ->where('quantity', '>', 0));
                    });
                    break;

                case 'expired':
                    // Batches that have reached or passed expiry
                    $query->whereHas('batches', function ($bQ) use ($today) {
                        $bQ->where('expiry_date', '<=', $today)
                            ->whereHas('stocks', fn ($sQ) => $sQ->where('quantity', '>', 0));
                    });
                    break;

                case 'quarantine':
                    // Has stock in quarantine
                    $query->whereHas('batches.stocks', function ($sQ) {
                        $sQ->where('status', 'quarantine')->where('quantity', '>', 0);
                    });
                    break;
            }
        }

        return $query->orderBy('brand');
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

        $nearExpiryDays = (int) (setting('stock.near_expiry_days') ?? 90);
        $today = Carbon::today()->format('Y-m-d');
        $nearExpiryThreshold = Carbon::today()->addDays($nearExpiryDays)->format('Y-m-d');

        $hasNearExpiry = false;
        $hasExpired = false;

        foreach ($product->batches as $batch) {
            $batchQty = 0;
            $exp = $batch->expiry_date?->format('Y-m-d');

            foreach ($batch->stocks as $stock) {
                if ($stock->status === 'sellable') {
                    $totalSellable += $stock->quantity;
                    $batchQty += $stock->quantity;
                } elseif ($stock->status === 'quarantine') {
                    $totalQuarantine += $stock->quantity;
                    $batchQty += $stock->quantity;
                }
            }

            if ($batchQty > 0 && $exp) {
                if ($exp <= $today) {
                    $hasExpired = true;
                } elseif ($exp <= $nearExpiryThreshold) {
                    $hasNearExpiry = true;
                }
            }

            if ($canViewCost && $batch->unit_cost !== null) {
                $totalValuation += (int) ($batchQty * $batch->unit_cost);
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

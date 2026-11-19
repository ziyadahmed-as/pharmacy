<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Returns products sorted by the expiry date of their earliest-expiring
 * SELLABLE, non-expired, non-recalled batch with quantity > 0.
 *
 * Sort order  : has_stock DESC → min_expiry ASC → brand ASC
 * Out-of-stock: products with no eligible stock sink to the bottom, greyed-out.
 *
 * All filtering, sorting and aggregation happen inside a single SQL statement so
 * the database can use idx_batches_expiry_product and idx_bstocks_batch_status_qty.
 * The Eloquent builder returned by builder() is paginate()-able.
 *
 * Cost fields (unit_cost, selling_price) are stripped for users without cost.view.
 *
 * @uc UC-13, UC-11
 * @fr FR-3.2, FR-2.10
 * @br BR-01, BR-02, BR-06
 * @nfr NFR-1 (< 1 s at 20 k products), NFR-6
 */
class NearestExpiryQuery
{
    public function __construct(private array $filters = []) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return an Eloquent Builder on `products` augmented with computed columns:
     *
     *   min_expiry       DATE|null  – earliest eligible batch expiry (NULL = no stock)
     *   total_sellable   int        – sum of sellable qty across eligible batches
     *   days_left        int|null   – days until min_expiry (NULL = no stock)
     *   has_stock        0|1        – 1 when total_sellable > 0
     *   current_price    int|null   – latest selling price in santim (NULL = not set)
     *                                 omitted from SELECT when viewer lacks cost.view
     *
     * Sorted: has_stock DESC, min_expiry ASC NULLS LAST, brand ASC.
     *
     * @param  User|null  $viewer  Pass null only in CLI/test contexts where no user is authenticated
     */
    public function builder(?User $viewer = null): Builder
    {
        $canViewCost = $viewer !== null && $viewer->hasPermission('cost.view');
        $f = $this->filters;

        // Sub-queries: only two — both bound correctly by Eloquent's selectSub.
        $minExpirySub     = $this->minExpirySub();
        $totalSellableSub = $this->totalSellableSub();
        $currentPriceSub  = $this->currentPriceSub();

        // Build the base Eloquent query on products, selecting the computed columns.
        // Only min_expiry and total_sellable are computed in SQL — has_stock and
        // days_left are derived in PHP from those two values (see toPosRow()).
        // This avoids duplicating sub-query SQL in selectRaw (which caused binding
        // count mismatches on SQLite: "column index out of range").
        $query = \App\Models\Product::query()
            ->where('products.is_active', true)
            ->addSelect('products.*')
            ->selectSub($minExpirySub,     'min_expiry')
            ->selectSub($totalSellableSub, 'total_sellable');

        // Add current_price only when the viewer can see costs.
        if ($canViewCost) {
            $query->selectSub($currentPriceSub, 'current_price');
        } else {
            // Always select a NULL placeholder so blade code can reference it safely.
            $query->selectRaw('NULL AS current_price');
        }

        // ---- Filters --------------------------------------------------------

        // Text search: brand, generic_name, barcode
        if (! empty($f['search'])) {
            $term = trim($f['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('products.brand', 'like', "%{$term}%")
                    ->orWhere('products.generic_name', 'like', "%{$term}%")
                    ->orWhere('products.barcode', $term);
            });
        }

        if (! empty($f['category_id'])) {
            $query->where('products.category_id', $f['category_id']);
        }

        if (! empty($f['classification'])) {
            $query->where('products.classification', $f['classification']);
        }

        // ---- Sort -----------------------------------------------------------
        // In-stock first:  (total_sellable > 0) DESC
        // Then nearest expiry first:  min_expiry ASC
        // Tiebreak on name:  brand ASC
        //
        // total_sellable is 0 (not NULL) when no stock — so (total_sellable > 0)
        // evaluates to 0 for out-of-stock, pushing them to the bottom.
        // min_expiry is NULL only for out-of-stock products, which are already
        // sorted to the bottom by the first key — so NULL handling is safe.
        $query->orderByRaw('(total_sellable > 0) DESC, min_expiry ASC, products.brand ASC');

        return $query;
    }

    /**
     * Convenience method: return a flat array of rows suitable for the POS
     * search-results dropdown.  Each row contains only the fields the POS blade
     * needs; never includes unit_cost or any buying-price field.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPosSearch(?User $viewer = null, int $limit = 10): array
    {
        return $this->builder($viewer)
            ->with(['latestPrice', 'units'])
            ->limit($limit)
            ->get()
            ->map(fn ($p) => $this->toPosRow($p))
            ->all();
    }

    // -------------------------------------------------------------------------
    // Row DTO helpers
    // -------------------------------------------------------------------------

    /**
     * Map a product (with computed columns from builder()) to the POS row array.
     * No cost field is ever included here — this method is called regardless of role.
     */
    public function toPosRow(\App\Models\Product $p): array
    {
        $totalSellable = (int) ($p->total_sellable ?? 0);
        $minExpiry     = $p->min_expiry;
        $daysLeft      = $this->daysLeftFromExpiry($minExpiry);
        $latestPrice   = $p->latestPrice;

        return [
            'id'              => $p->id,
            'brand'           => $p->brand,
            'generic_name'    => $p->generic_name,
            'strength'        => $p->strength,
            'dosage_form'     => $p->dosage_form,
            'classification'  => $p->classification,
            'barcode'         => $p->barcode,
            'thumbnail_url'   => ProductImageService::thumbnailUrl($p->thumbnail_path ?? null),
            'total_sellable'  => $totalSellable,
            'has_stock'       => $totalSellable > 0,
            'min_expiry'      => $minExpiry,
            'days_left'       => $daysLeft,
            'expiry_colour'   => $this->expiryColour($daysLeft),
            'price_santim'    => $latestPrice ? (int) $latestPrice->price : null,
            'price_etb'       => $latestPrice ? number_format($latestPrice->price / 100, 2) : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Colour coding (read thresholds from Settings — never hard-coded)
    // -------------------------------------------------------------------------

    /**
     * Map days_left to a Tailwind colour token.
     *
     * Thresholds come from inventory.near_expiry_alert_days (default [90,60,30]).
     * The list is sorted descending, e.g. [90, 60, 30]:
     *   > 90  → green
     *   61-90 → yellow
     *   31-60 → orange
     *   0-30  → red
     *   null  → slate (no stock)
     */
    public static function expiryColour(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'slate';
        }

        $windows = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        if (! is_array($windows) || empty($windows)) {
            $windows = [90, 60, 30];
        }
        rsort($windows);  // ensure descending: [90, 60, 30]

        // Map: > first window → green; <= each successive window → yellow/orange/red
        $colours = ['yellow', 'orange', 'red'];
        foreach ($windows as $i => $threshold) {
            if ($daysLeft > $threshold) {
                return 'green';
            }
            $colour = $colours[$i] ?? 'red';
            if ($i === count($windows) - 1 || $daysLeft > ($windows[$i + 1] ?? 0)) {
                return $colour;
            }
        }

        return 'red';
    }

    // -------------------------------------------------------------------------
    // Sub-query factories (all return query builder instances; no bindings lost)
    // -------------------------------------------------------------------------

    /** Cached per-request flag: does recall_batches table exist? */
    private static ?bool $recallTablesExist = null;

    private function recallTablesExist(): bool
    {
        if (self::$recallTablesExist === null) {
            self::$recallTablesExist = \Illuminate\Support\Facades\Schema::hasTable('recall_batches')
                && \Illuminate\Support\Facades\Schema::hasTable('recalls');
        }
        return self::$recallTablesExist;
    }

    /**
     * Sub-query: set of batch IDs that are eligible to sell.
     * Excludes: expired, recalled (via open QuarantineRecord or open RecallBatch).
     */
    private function eligibleBatchIdsSub(): QueryBuilder
    {
        $today = now()->toDateString();

        $recalledFromQr = DB::table('quarantine_records')
            ->select('batch_id')
            ->where('status', 'open')
            ->where('reason', 'recalled');

        $q = DB::table('batches')
            ->select('batches.id')
            ->where('batches.expiry_date', '>', $today)
            ->whereNotIn('batches.id', $recalledFromQr);

        if ($this->recallTablesExist()) {
            $recalledFromRb = DB::table('recall_batches')
                ->select('recall_batches.batch_id')
                ->join('recalls', 'recalls.id', '=', 'recall_batches.recall_id')
                ->where('recalls.status', 'open');
            $q->whereNotIn('batches.id', $recalledFromRb);
        }

        return $q;
    }

    /**
     * Sub-query: MIN(expiry_date) of eligible batches for the outer product.
     * Correlated on batches.product_id = products.id.
     * Reads quantity_on_hand (SSOT) instead of batch_stocks.
     */
    private function minExpirySub(): QueryBuilder
    {
        $today = now()->toDateString();

        $recalledFromQr = DB::table('quarantine_records')
            ->select('batch_id')->where('status', 'open')->where('reason', 'recalled');

        $q = DB::table('batches as b_exp')
            ->selectRaw('MIN(b_exp.expiry_date)')
            ->whereColumn('b_exp.product_id', 'products.id')
            ->where('b_exp.expiry_date', '>', $today)
            ->where('b_exp.status', 'SELLABLE')
            ->where('b_exp.quantity_on_hand', '>', 0)
            ->whereNotIn('b_exp.id', $recalledFromQr);

        if ($this->recallTablesExist()) {
            $q->whereNotIn('b_exp.id',
                DB::table('recall_batches')->select('recall_batches.batch_id')
                    ->join('recalls', 'recalls.id', '=', 'recall_batches.recall_id')
                    ->where('recalls.status', 'open'));
        }

        return $q;
    }

    /**
     * Sub-query: SUM(quantity_on_hand) across eligible batches for the outer product.
     * Reads quantity_on_hand (SSOT) instead of batch_stocks.
     */
    private function totalSellableSub(): QueryBuilder
    {
        $today = now()->toDateString();

        $recalledFromQr = DB::table('quarantine_records')
            ->select('batch_id')->where('status', 'open')->where('reason', 'recalled');

        $q = DB::table('batches as b_sum')
            ->selectRaw('COALESCE(SUM(b_sum.quantity_on_hand), 0)')
            ->whereColumn('b_sum.product_id', 'products.id')
            ->where('b_sum.expiry_date', '>', $today)
            ->where('b_sum.status', 'SELLABLE')
            ->where('b_sum.quantity_on_hand', '>', 0)
            ->whereNotIn('b_sum.id', $recalledFromQr);

        if ($this->recallTablesExist()) {
            $q->whereNotIn('b_sum.id',
                DB::table('recall_batches')->select('recall_batches.batch_id')
                    ->join('recalls', 'recalls.id', '=', 'recall_batches.recall_id')
                    ->where('recalls.status', 'open'));
        }

        return $q;
    }

    /**
     * Sub-query: most-recently effective selling price for the outer product.
     * Returns price in santim.
     */
    private function currentPriceSub(): QueryBuilder
    {
        return DB::table('product_prices as pp_price')
            ->selectRaw('pp_price.price')
            ->whereColumn('pp_price.product_id', 'products.id')
            ->whereNull('pp_price.unit_id')
            ->orderByDesc('pp_price.effective_from')
            ->orderByDesc('pp_price.id')
            ->limit(1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function daysLeftFromExpiry(?string $expiryDate): ?int
    {
        if ($expiryDate === null) {
            return null;
        }
        $diff = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($expiryDate)->startOfDay(), false);
        return (int) $diff;
    }
}

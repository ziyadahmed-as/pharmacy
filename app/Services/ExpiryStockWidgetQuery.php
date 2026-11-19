<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Powers the Expiry & Stock widget (UC-32, FR-7.7).
 *
 * Returns a paginated, sorted, filtered list of batches with:
 *   - product image thumbnail, name, strength, category, classification
 *   - batch_no, manufactured_date, expiry_date, days_left
 *   - qty_remaining (live from batch_stocks after all sales)
 *   - qty_sold in the chosen date window (from stock_movements type=sale)
 *   - selling_price (latest effective price)
 *   - batch status (SELLABLE / QUARANTINED / RECALLED / DEPLETED)
 *   - [OW/PM only] unit_cost, stock_value_at_cost
 *
 * Sort options (passed as $sortBy):
 *   nearest_expiry, furthest_expiry, longest_since_mfg, lowest_qty,
 *   highest_stock_value, highest_potential_loss, best_selling, name_az,
 *   recently_registered
 *
 * @uc UC-32 @fr FR-7.7 @br BR-06 @nfr NFR-1 (< 2 s), NFR-5 (mobile)
 */
class ExpiryStockWidgetQuery
{
    // -------------------------------------------------------------------------
    // Sort option constants
    // -------------------------------------------------------------------------
    public const SORT_NEAREST_EXPIRY       = 'nearest_expiry';
    public const SORT_FURTHEST_EXPIRY      = 'furthest_expiry';
    public const SORT_LONGEST_SINCE_MFG    = 'longest_since_mfg';
    public const SORT_LOWEST_QTY           = 'lowest_qty';
    public const SORT_HIGHEST_STOCK_VALUE  = 'highest_stock_value';
    public const SORT_HIGHEST_POTENTIAL_LOSS = 'highest_potential_loss';
    public const SORT_BEST_SELLING         = 'best_selling';
    public const SORT_NAME_AZ              = 'name_az';
    public const SORT_RECENTLY_REGISTERED  = 'recently_registered';

    public const SORT_OPTIONS = [
        self::SORT_NEAREST_EXPIRY,
        self::SORT_FURTHEST_EXPIRY,
        self::SORT_LONGEST_SINCE_MFG,
        self::SORT_LOWEST_QTY,
        self::SORT_HIGHEST_STOCK_VALUE,
        self::SORT_HIGHEST_POTENTIAL_LOSS,
        self::SORT_BEST_SELLING,
        self::SORT_NAME_AZ,
        self::SORT_RECENTLY_REGISTERED,
    ];

    /** Sort options that are only valid for OW/PM (require cost.view). */
    public const COST_SORTS = [
        self::SORT_HIGHEST_STOCK_VALUE,
        self::SORT_HIGHEST_POTENTIAL_LOSS,
    ];

    // -------------------------------------------------------------------------
    // Status filter constants
    // -------------------------------------------------------------------------
    public const STATUS_ALL         = 'all';
    public const STATUS_SELLABLE    = 'sellable';
    public const STATUS_EXPIRING_30 = 'expiring_30';
    public const STATUS_EXPIRING_60 = 'expiring_60';
    public const STATUS_EXPIRING_90 = 'expiring_90';
    public const STATUS_EXPIRED     = 'expired';
    public const STATUS_QUARANTINED = 'quarantined';
    public const STATUS_DEPLETED    = 'depleted';

    public function __construct(private array $filters = []) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run the widget query and return a paginated result.
     * Each item is a plain object with the columns listed in toRow().
     *
     * @param  User|null  $viewer  Null in CLI/tests only
     * @param  int        $perPage
     * @param  string     $pageName  Livewire pagination key
     */
    public function paginate(?User $viewer, int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        $canViewCost = $viewer !== null && $viewer->hasPermission('cost.view');
        $sortBy = $this->resolvedSort($canViewCost);

        return $this->buildQuery($canViewCost, $sortBy)
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Return all rows (no pagination) for export.
     * Never includes cost fields for PP.
     */
    public function forExport(?User $viewer): Collection
    {
        $canViewCost = $viewer !== null && $viewer->hasPermission('cost.view');
        $sortBy = $this->resolvedSort($canViewCost);

        return $this->buildQuery($canViewCost, $sortBy)->get();
    }

    /**
     * Summary card aggregates — cheap COUNT/SUM queries run separately.
     *
     * @return array{
     *   expiring_30: int,
     *   expiring_60: int,
     *   expiring_90: int,
     *   expired_and_quarantine: int,
     *   low_stock: int,
     *   sold_today: int,
     *   total_stock_value: int|null
     * }
     */
    public function summaryCards(?User $viewer): array
    {
        $canViewCost = $viewer !== null && $viewer->hasPermission('cost.view');
        $today       = now()->toDateString();
        $windows     = $this->nearExpiryWindows();

        // Sellable batch counts by expiry window — read from SSOT columns on batches
        $expiring30 = (int) DB::table('batches as b')
            ->where('b.status', 'SELLABLE')
            ->where('b.quantity_on_hand', '>', 0)
            ->where('b.expiry_date', '>', $today)
            ->where('b.expiry_date', '<=', now()->addDays(30)->toDateString())
            ->count();

        $expiring60 = (int) DB::table('batches as b')
            ->where('b.status', 'SELLABLE')
            ->where('b.quantity_on_hand', '>', 0)
            ->where('b.expiry_date', '>', $today)
            ->where('b.expiry_date', '<=', now()->addDays(60)->toDateString())
            ->count();

        $expiring90 = (int) DB::table('batches as b')
            ->where('b.status', 'SELLABLE')
            ->where('b.quantity_on_hand', '>', 0)
            ->where('b.expiry_date', '>', $today)
            ->where('b.expiry_date', '<=', now()->addDays(max($windows))->toDateString())
            ->count();

        $expiredAndQuarantine = (int) DB::table('batches as b')
            ->where(function ($q) use ($today) {
                $q->where('b.expiry_date', '<=', $today)
                  ->orWhere('b.quarantine_qty', '>', 0);
            })
            ->where(function ($q) {
                $q->where('b.quantity_on_hand', '>', 0)
                  ->orWhere('b.quarantine_qty', '>', 0);
            })
            ->count();

        $lowStock = (int) DB::table('products')
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->whereRaw('(
                SELECT COALESCE(SUM(blo.quantity_on_hand), 0)
                FROM batches blo
                WHERE blo.product_id  = products.id
                  AND blo.expiry_date > ?
                  AND blo.status      = "SELLABLE"
            ) <= products.reorder_level', [$today])
            ->count();

        $soldToday = (int) DB::table('stock_movements')
            ->where('type', 'sale')
            ->whereDate('created_at', $today)
            ->sum(DB::raw('ABS(quantity)'));

        $totalStockValue = null;
        if ($canViewCost) {
            $totalStockValue = (int) DB::table('batches as b')
                ->where('b.status', 'SELLABLE')
                ->where('b.quantity_on_hand', '>', 0)
                ->where('b.expiry_date', '>', $today)
                ->whereNotNull('b.unit_cost')
                ->selectRaw('COALESCE(SUM(b.quantity_on_hand * b.unit_cost), 0)')
                ->value(DB::raw('COALESCE(SUM(b.quantity_on_hand * b.unit_cost), 0)'));
        }

        return [
            'expiring_30'            => $expiring30,
            'expiring_60'            => $expiring60,
            'expiring_90'            => $expiring90,
            'expired_and_quarantine' => $expiredAndQuarantine,
            'low_stock'              => $lowStock,
            'sold_today'             => $soldToday,
            'total_stock_value'      => $totalStockValue,
        ];
    }

    // -------------------------------------------------------------------------
    // Core query builder
    // -------------------------------------------------------------------------

    private function buildQuery(bool $canViewCost, string $sortBy): \Illuminate\Database\Query\Builder
    {
        $today  = now()->toDateString();
        $f      = $this->filters;
        $windows = $this->nearExpiryWindows();

        // Date range for qty_sold
        $soldFrom = ! empty($f['sold_from']) ? $f['sold_from'] : null;
        $soldTo   = ! empty($f['sold_to'])   ? $f['sold_to']   : null;

        // Correlated sub-query: qty sold from this batch in the chosen window
        $qtySoldSub = DB::table('stock_movements as sm')
            ->selectRaw('COALESCE(SUM(ABS(sm.quantity)), 0)')
            ->whereColumn('sm.batch_id', 'b.id')
            ->where('sm.type', 'sale')
            ->when($soldFrom, fn ($q) => $q->whereDate('sm.created_at', '>=', $soldFrom))
            ->when($soldTo,   fn ($q) => $q->whereDate('sm.created_at', '<=', $soldTo));

        // Correlated sub-query: latest selling price for the product
        $priceSub = DB::table('product_prices as pp')
            ->selectRaw('pp.price')
            ->whereColumn('pp.product_id', 'p.id')
            ->whereNull('pp.unit_id')
            ->orderByDesc('pp.effective_from')
            ->orderByDesc('pp.id')
            ->limit(1);

        // Days left expression
        $daysLeftExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday(b.expiry_date) - julianday('now')) AS INTEGER)"
            : "DATEDIFF(b.expiry_date, CURDATE())";

        // Build SELECT list — cost columns only for OW/PM (BR-06)
        $select = [
            'b.id as batch_id',
            'b.product_id',
            'b.batch_no',
            'b.manufacturing_date',
            'b.expiry_date',
            DB::raw("({$daysLeftExpr}) as days_left"),
            'b.status as batch_status',
            'b.registered_at',
            'b.supplier_name',
            'p.brand',
            'p.generic_name',
            'p.strength',
            'p.dosage_form',
            'p.unit',
            'p.classification',
            'p.barcode',
            'p.thumbnail_path',
            'p.reorder_level',
            'cat.name as category_name',
            DB::raw('b.quantity_on_hand as qty_remaining'),       // SSOT
            DB::raw('b.quarantine_qty   as qty_quarantine'),       // SSOT
        ];

        if ($canViewCost) {
            $select[] = 'b.unit_cost';
            $select[] = DB::raw('b.quantity_on_hand * COALESCE(b.unit_cost, 0) as stock_value_at_cost');   // SSOT
        } else {
            $select[] = DB::raw('NULL as unit_cost');
            $select[] = DB::raw('NULL as stock_value_at_cost');
        }

        $query = DB::table('batches as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('p.is_active', true)
            ->select($select)
            ->selectSub($qtySoldSub, 'qty_sold')
            ->selectSub($priceSub,   'selling_price')
            ->groupBy(
                'b.id', 'b.product_id', 'b.batch_no', 'b.manufacturing_date',
                'b.expiry_date', 'b.status', 'b.unit_cost', 'b.registered_at',
                'b.supplier_name', 'b.quantity_on_hand', 'b.quarantine_qty',
                'p.brand', 'p.generic_name', 'p.strength',
                'p.dosage_form', 'p.unit', 'p.classification', 'p.barcode',
                'p.thumbnail_path', 'p.reorder_level', 'cat.name'
            );

        // ---- Filters --------------------------------------------------------

        if (! empty($f['search'])) {
            $term = trim($f['search']);
            $query->where(function ($q) use ($term) {
                $q->where('p.brand', 'like', "%{$term}%")
                  ->orWhere('p.generic_name', 'like', "%{$term}%")
                  ->orWhere('b.batch_no', 'like', "%{$term}%")
                  ->orWhere('p.barcode', $term);
            });
        }

        if (! empty($f['category_id'])) {
            $query->where('p.category_id', $f['category_id']);
        }

        if (! empty($f['classification'])) {
            $query->where('p.classification', $f['classification']);
        }

        $status = $f['status'] ?? self::STATUS_ALL;
        switch ($status) {
            case self::STATUS_SELLABLE:
                $query->where('b.expiry_date', '>', $today)
                      ->where('b.status', 'SELLABLE');
                break;
            case self::STATUS_EXPIRING_30:
                $query->where('b.expiry_date', '>', $today)
                      ->where('b.expiry_date', '<=', now()->addDays(30)->toDateString());
                break;
            case self::STATUS_EXPIRING_60:
                $query->where('b.expiry_date', '>', $today)
                      ->where('b.expiry_date', '<=', now()->addDays(60)->toDateString());
                break;
            case self::STATUS_EXPIRING_90:
                $query->where('b.expiry_date', '>', $today)
                      ->where('b.expiry_date', '<=', now()->addDays(max($windows))->toDateString());
                break;
            case self::STATUS_EXPIRED:
                $query->where('b.expiry_date', '<=', $today);
                break;
            case self::STATUS_QUARANTINED:
                $query->where('b.status', 'QUARANTINED');
                break;
            case self::STATUS_DEPLETED:
                $query->where('b.status', 'DEPLETED');
                break;
        }

        // ---- Sort -----------------------------------------------------------

        $this->applySort($query, $sortBy, $today, $windows, $canViewCost);

        return $query;
    }

    private function applySort(
        QueryBuilder $query,
        string $sortBy,
        string $today,
        array $windows,
        bool $canViewCost
    ): void {
        $driver = DB::connection()->getDriverName();

        switch ($sortBy) {
            case self::SORT_NEAREST_EXPIRY:
                $query->orderByRaw(
                    $driver === 'sqlite'
                        ? '(b.expiry_date > ?) DESC, b.expiry_date ASC, p.brand ASC'
                        : '(b.expiry_date > ?) DESC, b.expiry_date ASC, p.brand ASC',
                    [$today]
                );
                break;

            case self::SORT_FURTHEST_EXPIRY:
                $query->orderByRaw('b.expiry_date DESC, p.brand ASC');
                break;

            case self::SORT_LONGEST_SINCE_MFG:
                // NULL manufacturing_date sorts last
                $query->orderByRaw('b.manufacturing_date IS NULL ASC, b.manufacturing_date ASC, p.brand ASC');
                break;

            case self::SORT_LOWEST_QTY:
                $query->orderByRaw('b.quantity_on_hand ASC, p.brand ASC');   // SSOT
                break;

            case self::SORT_HIGHEST_STOCK_VALUE:
                if ($canViewCost) {
                    $query->orderByRaw('b.quantity_on_hand * COALESCE(b.unit_cost, 0) DESC, p.brand ASC');
                } else {
                    $query->orderBy('p.brand');
                }
                break;

            case self::SORT_HIGHEST_POTENTIAL_LOSS:
                if ($canViewCost) {
                    $alertWindow = max($windows);
                    $windowDate  = now()->addDays($alertWindow)->toDateString();
                    $query->orderByRaw(
                        'CASE WHEN b.expiry_date <= ? THEN b.quantity_on_hand * COALESCE(b.unit_cost, 0) ELSE 0 END DESC, p.brand ASC',
                        [$windowDate]
                    );
                } else {
                    $query->orderBy('p.brand');
                }
                break;

            case self::SORT_BEST_SELLING:
                $query->orderByRaw('qty_sold DESC, p.brand ASC');
                break;

            case self::SORT_NAME_AZ:
                $query->orderBy('p.brand')->orderBy('b.batch_no');
                break;

            case self::SORT_RECENTLY_REGISTERED:
                $query->orderByRaw('b.registered_at DESC, b.id DESC');
                break;

            default:
                $query->orderByRaw('b.expiry_date ASC, p.brand ASC');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Resolve the requested sort, downgrading cost-only sorts for PP. */
    private function resolvedSort(bool $canViewCost): string
    {
        $requested = $this->filters['sort_by'] ?? self::SORT_NEAREST_EXPIRY;

        if (! in_array($requested, self::SORT_OPTIONS, true)) {
            return self::SORT_NEAREST_EXPIRY;
        }

        // PP cannot use cost-based sorts — silently fall back to nearest_expiry.
        if (! $canViewCost && in_array($requested, self::COST_SORTS, true)) {
            return self::SORT_NEAREST_EXPIRY;
        }

        return $requested;
    }

    private function nearExpiryWindows(): array
    {
        $windows = setting('inventory.near_expiry_alert_days', [90, 60, 30]);

        return is_array($windows) ? $windows : [90, 60, 30];
    }
}

<?php

namespace App\Reports\Definitions\Inventory;

use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Stocktake variance report (UC-30/UC-14).
 * Audits physical counts vs expected system stock from stocktake counts.
 */
class StocktakeVarianceReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'stocktake_variance';
    }

    public function name(): string
    {
        return __('reports.stocktake_variance_name', [], 'en') ?: 'Stocktake Variance';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.stocktake_variance_desc', [], 'en') ?: 'Discrepancies between expected system inventory and physical stock counts.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_product', [], 'en') ?: 'Product',
            __('reports.col_batch_no', [], 'en') ?: 'Batch #',
            __('reports.col_expected_qty', [], 'en') ?: 'Expected Qty',
            __('reports.col_counted_qty', [], 'en') ?: 'Counted Qty',
            __('reports.col_variance_qty', [], 'en') ?: 'Variance Qty',
            __('reports.col_stocktake_ref', [], 'en') ?: 'Stocktake Ref',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        if (! Schema::hasTable('stocktakes') || ! Schema::hasTable('stocktake_items')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => [],
                'row_count' => 0,
            ];
        }

        $items = StocktakeItem::with(['product', 'batch', 'stocktake'])
            ->whereHas('stocktake', fn ($q) => $q->whereDate('created_at', '>=', $start)->whereDate('created_at', '<=', $end))
            ->whereColumn('counted_quantity', '!=', 'expected_quantity')
            ->get();

        $rows = [];
        $totalVariance = 0;

        foreach ($items as $it) {
            $expected = (int) ($it->expected_quantity ?? 0);
            $counted = (int) ($it->counted_quantity ?? 0);
            $diff = $counted - $expected;
            $totalVariance += abs($diff);

            $rows[] = [
                $it->product?->brand ?? '—',
                $it->batch?->batch_no ?? '—',
                $expected,
                $counted,
                ($diff > 0 ? '+' : '') . $diff,
                $it->stocktake?->reference ?? ('#' . $it->stocktake_id),
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'discrepant_items_count' => count($rows),
                'total_units_discrepancy' => $totalVariance,
            ],
            'row_count' => count($rows),
        ];
    }
}

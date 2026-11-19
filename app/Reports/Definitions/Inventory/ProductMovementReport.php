<?php

namespace App\Reports\Definitions\Inventory;

use App\Models\Product;
use App\Models\SaleLine;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Product movement report (Fast and slow movers).
 */
class ProductMovementReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'product_movement';
    }

    public function name(): string
    {
        return __('reports.product_movement_name', [], 'en') ?: 'Product Movement (Fast & Slow Movers)';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.product_movement_desc', [], 'en') ?: 'Inventory velocity analysis identifying fast-moving top sellers and slow-moving or dead stock.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'category_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_product', [], 'en') ?: 'Product',
            __('reports.col_category', [], 'en') ?: 'Category',
            __('reports.col_qty_sold', [], 'en') ?: 'Units Sold',
            __('reports.col_sales_revenue', [], 'en') ?: 'Total Revenue',
            __('reports.col_stock_on_hand', [], 'en') ?: 'Stock on Hand',
            __('reports.col_velocity', [], 'en') ?: 'Movement Velocity',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $products = Product::with(['category', 'batches.batchStocks' => fn ($q) => $q->where('status', 'sellable')])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->get();

        $rows = [];
        $totalUnits = 0;

        foreach ($products as $p) {
            $qtySold = (int) SaleLine::where('product_id', $p->id)
                ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereDate('completed_at', '>=', $start)->whereDate('completed_at', '<=', $end))
                ->sum('quantity');

            $rev = (int) SaleLine::where('product_id', $p->id)
                ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereDate('completed_at', '>=', $start)->whereDate('completed_at', '<=', $end))
                ->sum('subtotal');

            $stockOnHand = (int) $p->batches->sum('quantity_on_hand');   // SSOT
            $totalUnits += $qtySold;

            $velocity = 'Slow / Dormant';
            if ($qtySold > 50) {
                $velocity = 'Fast Mover';
            } elseif ($qtySold > 10) {
                $velocity = 'Moderate';
            }

            $rows[] = [
                $p->brand,
                $p->category?->name ?? 'Uncategorized',
                $qtySold,
                $this->formatMoney($rev),
                $stockOnHand,
                $velocity,
            ];
        }

        // Sort by units sold descending
        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'products_count' => count($rows),
                'total_units_sold' => $totalUnits,
            ],
            'row_count' => count($rows),
        ];
    }
}

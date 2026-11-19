<?php

namespace App\Reports\Definitions\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Stock valuation report (UC-30/UC-11).
 * Shows inventory quantities valued at unit cost (for authorized roles) and selling price.
 */
class StockValuationReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'stock_valuation';
    }

    public function name(): string
    {
        return __('reports.stock_valuation_name', [], 'en') ?: 'Stock Valuation';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.stock_valuation_desc', [], 'en') ?: 'Total value of sellable stock on hand at retail selling price and purchase cost.';
    }

    public function availableFilters(): array
    {
        return [
            'category_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_product', [], 'en') ?: 'Product',
            __('reports.col_category', [], 'en') ?: 'Category',
            __('reports.col_sellable_qty', [], 'en') ?: 'Sellable Qty',
            __('reports.col_selling_price', [], 'en') ?: 'Retail Value',
        ];

        if ($this->canViewCost($user)) {
            $headers[] = __('reports.col_cost_value', [], 'en') ?: 'Cost Value (COGS)';
            $headers[] = __('reports.col_potential_profit', [], 'en') ?: 'Potential Margin';
        }

        return $headers;
    }

    public function query(array $filters, User $user): array
    {
        $canCost = $this->canViewCost($user);

        $products = Product::with(['category', 'batches.batchStocks' => fn ($q) => $q->where('status', 'sellable')])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->where('is_active', true)
            ->get();

        $rows = [];
        $totalRetail = 0;
        $totalCost = 0;

        foreach ($products as $p) {
            $qty = 0;
            $prodCost = 0;
            $prodRetail = 0;

            foreach ($p->batches as $batch) {
                $bQty = (int) $batch->quantity_on_hand;   // SSOT
                $qty += $bQty;
                $cost = (int) ($batch->unit_cost ?? 0);
                $retail = (int) ($p->latestPrice?->selling_price ?? $batch->unit_cost ?? 0);

                $prodCost += $bQty * $cost;
                $prodRetail += $bQty * $retail;
            }

            if ($qty === 0) {
                continue;
            }

            $totalRetail += $prodRetail;
            $totalCost += $prodCost;

            $row = [
                $p->brand,
                $p->category?->name ?? 'Uncategorized',
                $qty,
                $this->formatMoney($prodRetail),
            ];

            if ($canCost) {
                $margin = $prodRetail - $prodCost;
                $row[] = $this->formatMoney($prodCost);
                $row[] = $this->formatMoney($margin);
            }

            $rows[] = $row;
        }

        $summary = [
            'products_stocked' => count($rows),
            'total_retail_value' => $this->formatMoney($totalRetail),
        ];

        if ($canCost) {
            $summary['total_cost_value'] = $this->formatMoney($totalCost);
            $summary['total_potential_profit'] = $this->formatMoney($totalRetail - $totalCost);
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

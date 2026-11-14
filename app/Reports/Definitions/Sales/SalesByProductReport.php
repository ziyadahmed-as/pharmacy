<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\SaleLine;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Sales report aggregated by product (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesByProductReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_by_product';
    }

    public function name(): string
    {
        return __('reports.sales_by_product_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_by_product_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Owner, PM, and Staff (staff gets their own sales)
        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'category_id' => true,
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_product'),
            __('reports.col_generic_name'),
            __('reports.col_category'),
            __('reports.col_units_sold'),
            __('reports.col_revenue'),
        ];

        if ($this->canViewCost($user)) {
            $headers[] = __('reports.col_cost_of_goods');
            $headers[] = __('reports.col_gross_profit');
        }

        return $headers;
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);
        $canCost = $this->canViewCost($user);

        $query = SaleLine::query()
            ->whereHas('sale', function ($q) use ($start, $end, $isStaff, $user) {
                $q->where('status', 'completed')
                    ->whereDate('completed_at', '>=', $start)
                    ->whereDate('completed_at', '<=', $end);

                if ($isStaff) {
                    $q->where('user_id', $user->id);
                }
            })
            ->with(['product.category', 'batch']);

        if (! empty($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('product', function ($q) use ($term) {
                $q->where('brand', 'like', $term)
                    ->orWhere('generic_name', 'like', $term)
                    ->orWhere('barcode', 'like', $term);
            });
        }

        $lines = $query->get();

        $grouped = [];
        foreach ($lines as $line) {
            $productId = $line->product_id;
            if (! isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'brand' => $line->product?->brand ?? 'Unknown',
                    'generic' => $line->product?->generic_name ?? '-',
                    'category' => $line->product?->category?->name ?? '-',
                    'qty' => 0,
                    'revenue' => 0,
                    'cost' => 0,
                ];
            }

            $grouped[$productId]['qty'] += $line->quantity;
            $grouped[$productId]['revenue'] += $line->subtotal;

            if ($canCost) {
                $unitCost = $line->batch?->unit_cost ?? 0;
                $grouped[$productId]['cost'] += ($line->quantity * $unitCost);
            }
        }

        uasort($grouped, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $rows = [];
        $totQty = 0;
        $totRev = 0;
        $totCost = 0;
        $totProfit = 0;

        foreach ($grouped as $item) {
            $profit = $item['revenue'] - $item['cost'];
            $totQty += $item['qty'];
            $totRev += $item['revenue'];
            $totCost += $item['cost'];
            $totProfit += $profit;

            $row = [
                $item['brand'],
                $item['generic'],
                $item['category'],
                (string) $item['qty'],
                $this->formatMoney($item['revenue']),
            ];

            if ($canCost) {
                $row[] = $this->formatMoney($item['cost']);
                $row[] = $this->formatMoney($profit);
            }

            $rows[] = $row;
        }

        $summary = [
            'total_units_sold' => $totQty,
            'total_revenue' => $this->formatMoney($totRev),
        ];

        if ($canCost) {
            $summary['total_cost'] = $this->formatMoney($totCost);
            $summary['total_gross_profit'] = $this->formatMoney($totProfit);
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

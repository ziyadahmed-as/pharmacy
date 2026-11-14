<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\SaleLine;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Sales report aggregated by product category (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesByCategoryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_by_category';
    }

    public function name(): string
    {
        return __('reports.sales_by_category_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_by_category_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_category'),
            __('reports.col_items_sold'),
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

        $lines = $query->get();

        $grouped = [];
        foreach ($lines as $line) {
            $categoryName = $line->product?->category?->name ?? __('reports.uncategorized');

            if (! isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'name' => $categoryName,
                    'qty' => 0,
                    'revenue' => 0,
                    'cost' => 0,
                ];
            }

            $grouped[$categoryName]['qty'] += $line->quantity;
            $grouped[$categoryName]['revenue'] += $line->subtotal;

            if ($canCost) {
                $unitCost = $line->batch?->unit_cost ?? 0;
                $grouped[$categoryName]['cost'] += ($line->quantity * $unitCost);
            }
        }

        uasort($grouped, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $rows = [];
        $totQty = 0;
        $totRev = 0;
        $totCost = 0;
        $totProfit = 0;

        foreach ($grouped as $cat) {
            $profit = $cat['revenue'] - $cat['cost'];
            $totQty += $cat['qty'];
            $totRev += $cat['revenue'];
            $totCost += $cat['cost'];
            $totProfit += $profit;

            $row = [
                $cat['name'],
                (string) $cat['qty'],
                $this->formatMoney($cat['revenue']),
            ];

            if ($canCost) {
                $row[] = $this->formatMoney($cat['cost']);
                $row[] = $this->formatMoney($profit);
            }

            $rows[] = $row;
        }

        $summary = [
            'total_items_sold' => $totQty,
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

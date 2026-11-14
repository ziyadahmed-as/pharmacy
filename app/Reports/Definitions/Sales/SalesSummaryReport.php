<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Carbon;

/**
 * Sales summary by day, week, month or year (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesSummaryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_summary';
    }

    public function name(): string
    {
        return __('reports.sales_summary_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_summary_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Owner, PM, and Staff (staff sees only their own sales / shift totals)
        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'group_by' => ['day', 'week', 'month', 'year'],
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_period'),
            __('reports.col_transactions'),
            __('reports.col_gross_sales'),
            __('reports.col_refunds'),
            __('reports.col_net_sales'),
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
        $groupBy = $filters['group_by'] ?? 'day';
        $isStaff = $this->isStaffOnly($user);
        $canCost = $this->canViewCost($user);

        // Base query for sales
        $salesQuery = Sale::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end);

        if ($isStaff) {
            $salesQuery->where('user_id', $user->id);
        }

        $sales = $salesQuery->with(['lines.batch'])->get();

        // Base query for refunds
        $refundsQuery = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end);

        if ($isStaff) {
            $refundsQuery->where('user_id', $user->id);
        }

        $refunds = $refundsQuery->get();

        // Grouping
        $buckets = [];

        foreach ($sales as $sale) {
            $completedAt = $sale->completed_at ?? $sale->created_at;
            $periodKey = $this->resolvePeriodKey($completedAt, $groupBy);

            if (! isset($buckets[$periodKey])) {
                $buckets[$periodKey] = [
                    'label' => $this->resolvePeriodLabel($completedAt, $groupBy),
                    'count' => 0,
                    'gross' => 0,
                    'refunds' => 0,
                    'cost' => 0,
                ];
            }

            $buckets[$periodKey]['count']++;
            $buckets[$periodKey]['gross'] += $sale->total;

            if ($canCost) {
                foreach ($sale->lines as $line) {
                    $unitCost = $line->batch?->unit_cost ?? 0;
                    $buckets[$periodKey]['cost'] += ($line->quantity * $unitCost);
                }
            }
        }

        foreach ($refunds as $refund) {
            $completedAt = $refund->completed_at ?? $refund->created_at;
            $periodKey = $this->resolvePeriodKey($completedAt, $groupBy);

            if (! isset($buckets[$periodKey])) {
                $buckets[$periodKey] = [
                    'label' => $this->resolvePeriodLabel($completedAt, $groupBy),
                    'count' => 0,
                    'gross' => 0,
                    'refunds' => 0,
                    'cost' => 0,
                ];
            }

            $buckets[$periodKey]['refunds'] += $refund->total_refund;
        }

        ksort($buckets);

        $rows = [];
        $totCount = 0;
        $totGross = 0;
        $totRefunds = 0;
        $totNet = 0;
        $totCost = 0;
        $totProfit = 0;

        foreach ($buckets as $b) {
            $net = $b['gross'] - $b['refunds'];
            $profit = $net - $b['cost'];

            $totCount += $b['count'];
            $totGross += $b['gross'];
            $totRefunds += $b['refunds'];
            $totNet += $net;
            $totCost += $b['cost'];
            $totProfit += $profit;

            $row = [
                $b['label'],
                (string) $b['count'],
                $this->formatMoney($b['gross']),
                $this->formatMoney($b['refunds']),
                $this->formatMoney($net),
            ];

            if ($canCost) {
                $row[] = $this->formatMoney($b['cost']);
                $row[] = $this->formatMoney($profit);
            }

            $rows[] = $row;
        }

        $summary = [
            'total_transactions' => $totCount,
            'total_gross_sales' => $this->formatMoney($totGross),
            'total_refunds' => $this->formatMoney($totRefunds),
            'total_net_sales' => $this->formatMoney($totNet),
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

    private function resolvePeriodKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'year' => $date->format('Y'),
            'month' => $date->format('Y-m'),
            'week' => $date->format('o-W'), // ISO-8601 year and week number
            default => $date->format('Y-m-d'),
        };
    }

    private function resolvePeriodLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'year' => $date->format('Y'),
            'month' => $date->format('F Y'),
            'week' => 'Week '.$date->format('W, Y'),
            default => $date->format('Y-m-d'),
        };
    }
}

<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Sales by staff member and shift totals (UC-30).
 * A1: Pharmacy staff see only their own shift/sales totals.
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesByStaffReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_by_staff';
    }

    public function name(): string
    {
        return __('reports.sales_by_staff_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_by_staff_desc');
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
            'user_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_staff_member'),
            __('reports.col_sales_count'),
            __('reports.col_gross_sales'),
            __('reports.col_refunds'),
            __('reports.col_net_sales'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);

        $salesQuery = Sale::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end)
            ->with('user');

        $refundsQuery = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end)
            ->with('user');

        // A1: staff restricted to own user ID
        if ($isStaff) {
            $salesQuery->where('user_id', $user->id);
            $refundsQuery->where('user_id', $user->id);
        } elseif (! empty($filters['user_id'])) {
            $salesQuery->where('user_id', $filters['user_id']);
            $refundsQuery->where('user_id', $filters['user_id']);
        }

        $sales = $salesQuery->get();
        $refunds = $refundsQuery->get();

        $staffMap = [];

        foreach ($sales as $s) {
            $uid = $s->user_id;
            $name = $s->user?->name ?? 'User #'.$uid;

            if (! isset($staffMap[$uid])) {
                $staffMap[$uid] = [
                    'name' => $name,
                    'sales_count' => 0,
                    'gross' => 0,
                    'refunds' => 0,
                ];
            }

            $staffMap[$uid]['sales_count']++;
            $staffMap[$uid]['gross'] += $s->total;
        }

        foreach ($refunds as $r) {
            $uid = $r->user_id;
            $name = $r->user?->name ?? 'User #'.$uid;

            if (! isset($staffMap[$uid])) {
                $staffMap[$uid] = [
                    'name' => $name,
                    'sales_count' => 0,
                    'gross' => 0,
                    'refunds' => 0,
                ];
            }

            $staffMap[$uid]['refunds'] += $r->total_refund;
        }

        uasort($staffMap, fn ($a, $b) => $b['gross'] <=> $a['gross']);

        $rows = [];
        $totCount = 0;
        $totGross = 0;
        $totRefunds = 0;
        $totNet = 0;

        foreach ($staffMap as $staff) {
            $net = $staff['gross'] - $staff['refunds'];
            $totCount += $staff['sales_count'];
            $totGross += $staff['gross'];
            $totRefunds += $staff['refunds'];
            $totNet += $net;

            $rows[] = [
                $staff['name'],
                (string) $staff['sales_count'],
                $this->formatMoney($staff['gross']),
                $this->formatMoney($staff['refunds']),
                $this->formatMoney($net),
            ];
        }

        $summary = [
            'total_sales_count' => $totCount,
            'total_gross_sales' => $this->formatMoney($totGross),
            'total_refunds' => $this->formatMoney($totRefunds),
            'total_net_sales' => $this->formatMoney($totNet),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


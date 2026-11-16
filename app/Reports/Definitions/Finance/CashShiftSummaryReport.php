<?php

namespace App\Reports\Definitions\Finance;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Cash and shift summary report.
 * Lists cash shifts, opening floats, sales totals, variances, and closing statuses.
 */
class CashShiftSummaryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'cash_shift_summary';
    }

    public function name(): string
    {
        return __('reports.cash_shift_summary_name', [], 'en') ?: 'Cash & Shift Summary';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function description(): string
    {
        return __('reports.cash_shift_summary_desc', [], 'en') ?: 'Cash till shifts, drawer floats, payment totals, cash expected vs counted and variances.';
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Owner, PM, and Staff (staff sees only their own shifts)
        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'till_id' => true,
            'user_id' => true,
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_shift_id', [], 'en') ?: 'Shift #',
            __('reports.col_till', [], 'en') ?: 'Till',
            __('reports.col_cashier', [], 'en') ?: 'Cashier',
            __('reports.col_opened_at', [], 'en') ?: 'Opened At',
            __('reports.col_closed_at', [], 'en') ?: 'Closed At',
            __('reports.col_opening_float', [], 'en') ?: 'Opening Float',
            __('reports.col_cash_sales', [], 'en') ?: 'Cash Sales',
            __('reports.col_expected_cash', [], 'en') ?: 'Expected Cash',
            __('reports.col_counted_cash', [], 'en') ?: 'Counted Cash',
            __('reports.col_variance', [], 'en') ?: 'Variance',
            __('reports.col_status', [], 'en') ?: 'Status',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);

        $query = Shift::with(['till', 'user'])
            ->whereDate('opened_at', '>=', $start)
            ->whereDate('opened_at', '<=', $end)
            ->when($isStaff, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $isStaff && ! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['till_id']), fn ($q) => $q->where('till_id', $filters['till_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('opened_at');

        $shifts = $query->get();

        $rows = [];
        $totalSales = 0;
        $totalVariance = 0;

        foreach ($shifts as $s) {
            $float = (int) ($s->opening_float ?? 0);
            $cashSales = (int) ($s->cash_sales_total ?? 0);
            $expected = (int) ($s->expected_cash ?? ($float + $cashSales));
            $counted = (int) ($s->counted_cash ?? 0);
            $variance = (int) ($s->variance ?? 0);

            $totalSales += $cashSales;
            $totalVariance += $variance;

            $rows[] = [
                '#' . $s->id,
                $s->till?->name ?? '—',
                $s->user?->name ?? $s->user?->username ?? '—',
                $s->opened_at ? $s->opened_at->format('Y-m-d H:i') : '—',
                $s->closed_at ? $s->closed_at->format('Y-m-d H:i') : 'Open',
                $this->formatMoney($float),
                $this->formatMoney($cashSales),
                $this->formatMoney($expected),
                $s->closed_at ? $this->formatMoney($counted) : '—',
                $s->closed_at ? $this->formatMoney($variance) : '—',
                ucfirst($s->status),
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'total_shifts' => count($shifts),
                'total_cash_sales' => $this->formatMoney($totalSales),
                'net_variance' => $this->formatMoney($totalVariance),
            ],
            'row_count' => count($rows),
        ];
    }
}

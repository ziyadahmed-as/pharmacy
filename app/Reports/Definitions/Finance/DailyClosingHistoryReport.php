<?php

namespace App\Reports\Definitions\Finance;

use App\Models\DailyClosing;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Daily closing history report (UC-30/UC-23).
 */
class DailyClosingHistoryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'daily_closing_history';
    }

    public function name(): string
    {
        return __('reports.daily_closing_history_name', [], 'en') ?: 'Daily Closing History';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function description(): string
    {
        return __('reports.daily_closing_history_desc', [], 'en') ?: 'History of end-of-day register locks, closing cash reconciliations and manager notes.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date', [], 'en') ?: 'Date',
            __('reports.col_status', [], 'en') ?: 'Status',
            __('reports.col_total_sales', [], 'en') ?: 'Total Sales',
            __('reports.col_total_refunds', [], 'en') ?: 'Total Refunds',
            __('reports.col_net_revenue', [], 'en') ?: 'Net Revenue',
            __('reports.col_cash_collected', [], 'en') ?: 'Cash Collected',
            __('reports.col_discrepancy', [], 'en') ?: 'Discrepancy',
            __('reports.col_closed_by', [], 'en') ?: 'Closed By',
            __('reports.col_closed_at', [], 'en') ?: 'Closed At',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $closings = DailyClosing::with('closer')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')
            ->get();

        $rows = [];
        $netTotal = 0;

        foreach ($closings as $c) {
            $sales = (int) ($c->total_sales ?? 0);
            $refunds = (int) ($c->total_refunds ?? 0);
            $net = $sales - $refunds;
            $cash = (int) ($c->total_cash_counted ?? 0);
            $diff = (int) ($c->variance ?? 0);

            $netTotal += $net;

            $rows[] = [
                $c->date ? $c->date->format('Y-m-d') : '—',
                ucfirst($c->status),
                $this->formatMoney($sales),
                $this->formatMoney($refunds),
                $this->formatMoney($net),
                $this->formatMoney($cash),
                $this->formatMoney($diff),
                $c->closer?->name ?? '—',
                $c->closed_at ? $c->closed_at->format('Y-m-d H:i') : 'Open',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'closed_days' => count($closings),
                'total_net_revenue' => $this->formatMoney($netTotal),
            ],
            'row_count' => count($rows),
        ];
    }
}

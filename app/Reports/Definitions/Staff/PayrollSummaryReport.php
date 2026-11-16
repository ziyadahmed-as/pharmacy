<?php

namespace App\Reports\Definitions\Staff;

use App\Models\PayrollRun;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll summary report (UC-30/UC-42).
 */
class PayrollSummaryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'payroll_summary';
    }

    public function name(): string
    {
        return __('reports.payroll_summary_name', [], 'en') ?: 'Payroll Summary';
    }

    public function category(): string
    {
        return 'staff';
    }

    public function description(): string
    {
        return __('reports.payroll_summary_desc', [], 'en') ?: 'Summary of monthly staff payroll runs, gross wages, deductions, allowances, and net pay.';
    }

    public function availableFilters(): array
    {
        return [
            'period' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_period', [], 'en') ?: 'Period',
            __('reports.col_staff_count', [], 'en') ?: 'Staff Count',
            __('reports.col_gross_total', [], 'en') ?: 'Gross Total',
            __('reports.col_deductions', [], 'en') ?: 'Total Deductions',
            __('reports.col_net_pay', [], 'en') ?: 'Net Pay',
            __('reports.col_status', [], 'en') ?: 'Status',
            __('reports.col_approved_by', [], 'en') ?: 'Approved By',
            __('reports.col_paid_at', [], 'en') ?: 'Paid At',
        ];
    }

    public function query(array $filters, User $user): array
    {
        if (! Schema::hasTable('payroll_runs')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => [],
                'row_count' => 0,
            ];
        }

        $runs = PayrollRun::with(['approver', 'items'])
            ->orderByDesc('period')
            ->get();

        $rows = [];
        $totalGross = 0;
        $totalNet = 0;

        foreach ($runs as $r) {
            $gross = (int) ($r->total_gross ?? $r->items?->sum('gross_salary') ?? 0);
            $net = (int) ($r->total_net ?? $r->items?->sum('net_salary') ?? 0);
            $deductions = $gross - $net;

            $totalGross += $gross;
            $totalNet += $net;

            $rows[] = [
                $r->period ?? '—',
                $r->items?->count() ?? 0,
                $this->formatMoney($gross),
                $this->formatMoney($deductions),
                $this->formatMoney($net),
                ucfirst($r->status ?? 'posted'),
                $r->approver?->name ?? '—',
                $r->paid_at ? $r->paid_at->format('Y-m-d') : '—',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'runs_count' => count($runs),
                'total_gross_disbursed' => $this->formatMoney($totalGross),
                'total_net_disbursed' => $this->formatMoney($totalNet),
            ],
            'row_count' => count($rows),
        ];
    }
}

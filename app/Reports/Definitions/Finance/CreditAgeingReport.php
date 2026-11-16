<?php

namespace App\Reports\Definitions\Finance;

use App\Models\CreditAccount;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Credit ageing report (UC-30/UC-26).
 * Customer receivables by ageing brackets: Current (0-30), 31-60, 61-90, 90+ days.
 */
class CreditAgeingReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'credit_ageing';
    }

    public function name(): string
    {
        return __('reports.credit_ageing_name', [], 'en') ?: 'Credit Receivables Ageing';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function description(): string
    {
        return __('reports.credit_ageing_desc', [], 'en') ?: 'Customer receivables breakdown by overdue ageing buckets (0-30, 31-60, 61-90, 90+ days).';
    }

    public function availableFilters(): array
    {
        return [
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_customer', [], 'en') ?: 'Customer / Organisation',
            __('reports.col_credit_limit', [], 'en') ?: 'Credit Limit',
            __('reports.col_balance', [], 'en') ?: 'Current Balance',
            __('reports.col_current_30', [], 'en') ?: '0 - 30 Days',
            __('reports.col_31_60', [], 'en') ?: '31 - 60 Days',
            __('reports.col_61_90', [], 'en') ?: '61 - 90 Days',
            __('reports.col_over_90', [], 'en') ?: 'Over 90 Days',
            __('reports.col_status', [], 'en') ?: 'Status',
        ];
    }

    public function query(array $filters, User $user): array
    {
        if (! Schema::hasTable('credit_accounts')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => ['total_outstanding' => $this->formatMoney(0)],
                'row_count' => 0,
            ];
        }

        $accounts = CreditAccount::with('customer')
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get();

        $rows = [];
        $totalBalance = 0;

        foreach ($accounts as $acc) {
            $bal = (int) $acc->current_balance;
            $limit = (int) $acc->credit_limit;
            $totalBalance += $bal;

            // Ageing bucket estimate based on days overdue
            $daysOverdue = $acc->days_overdue ?? 0;
            $b0_30 = $daysOverdue <= 30 ? $bal : 0;
            $b31_60 = ($daysOverdue > 30 && $daysOverdue <= 60) ? $bal : 0;
            $b61_90 = ($daysOverdue > 60 && $daysOverdue <= 90) ? $bal : 0;
            $b90plus = $daysOverdue > 90 ? $bal : 0;

            $rows[] = [
                $acc->customer?->name ?? 'Account #' . $acc->id,
                $this->formatMoney($limit),
                $this->formatMoney($bal),
                $this->formatMoney($b0_30),
                $this->formatMoney($b31_60),
                $this->formatMoney($b61_90),
                $this->formatMoney($b90plus),
                ucfirst($acc->status ?? 'Active'),
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'debtors_count' => count($accounts),
                'total_outstanding' => $this->formatMoney($totalBalance),
            ],
            'row_count' => count($rows),
        ];
    }
}

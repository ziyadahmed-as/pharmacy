<?php

namespace App\Reports\Definitions\Finance;

use App\Models\Expense;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Expenses by category report (UC-30/UC-28).
 */
class ExpensesByCategoryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'expenses_by_category';
    }

    public function name(): string
    {
        return __('reports.expenses_by_category_name', [], 'en') ?: 'Expenses by Category';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function description(): string
    {
        return __('reports.expenses_by_category_desc', [], 'en') ?: 'Breakdown of operational expenses grouped by category, showing approvals and totals.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'category_id' => true,
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date', [], 'en') ?: 'Date',
            __('reports.col_category', [], 'en') ?: 'Category',
            __('reports.col_description', [], 'en') ?: 'Description',
            __('reports.col_amount', [], 'en') ?: 'Amount',
            __('reports.col_status', [], 'en') ?: 'Status',
            __('reports.col_recorded_by', [], 'en') ?: 'Recorded By',
            __('reports.col_approved_by', [], 'en') ?: 'Approved By',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = Expense::with(['creator', 'approver', 'expenseCategory'])
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']), fn ($q) => $q->where('status', Expense::STATUS_POSTED))
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('expense_category_id', $filters['category_id']))
            ->orderByDesc('date');

        $expenses = $query->get();

        $rows = [];
        $totalAmount = 0;

        foreach ($expenses as $e) {
            $totalAmount += (int) $e->amount;
            $catName = $e->expenseCategory?->name ?? ucfirst($e->category);

            $rows[] = [
                $e->date ? $e->date->format('Y-m-d') : '—',
                $catName,
                $e->description,
                $this->formatMoney((int) $e->amount),
                ucfirst($e->status),
                $e->creator?->name ?? '—',
                $e->approver?->name ?? '—',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'count' => count($expenses),
                'total_expenses' => $this->formatMoney($totalAmount),
            ],
            'row_count' => count($rows),
        ];
    }
}

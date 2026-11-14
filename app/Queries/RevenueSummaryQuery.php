<?php

namespace App\Queries;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Carbon;

/**
 * Query object for gross sales, refunds, net sales, voids and recorded expenses (UC-29).
 *
 * @uc UC-29
 *
 * @fr FR-6.1, FR-6.6, FR-7.1
 *
 * @ac AC-24
 */
class RevenueSummaryQuery
{
    private string $startDate;

    private string $endDate;

    public function __construct(string $startDate, ?string $endDate = null)
    {
        $this->startDate = Carbon::parse($startDate)->toDateString();
        $this->endDate = Carbon::parse($endDate ?? $startDate)->toDateString();
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     gross_sales: int,
     *     gross_sales_count: int,
     *     refunds: int,
     *     refunds_count: int,
     *     net_sales: int,
     *     voids: int,
     *     voids_count: int,
     *     expenses: int,
     *     expenses_count: int,
     *     expenses_by_category: array<string, int>,
     *     operating_surplus: int
     * }
     */
    public function get(): array
    {
        // 1. Completed sales (Gross sales)
        $salesQuery = Sale::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $this->startDate)
            ->whereDate('completed_at', '<=', $this->endDate);

        $grossSales = (int) $salesQuery->sum('total');
        $grossSalesCount = (int) $salesQuery->count();

        // 2. Completed refunds (reduce revenue)
        $refundsQuery = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $this->startDate)
            ->whereDate('completed_at', '<=', $this->endDate);

        $refunds = (int) $refundsQuery->sum('total_refund');
        $refundsCount = (int) $refundsQuery->count();

        // 3. Net sales = Gross sales - Refunds
        $netSales = $grossSales - $refunds;

        // 4. Voids
        $voidsQuery = Sale::query()
            ->where('status', 'voided')
            ->whereDate('completed_at', '>=', $this->startDate)
            ->whereDate('completed_at', '<=', $this->endDate);

        $voids = (int) $voidsQuery->sum('total');
        $voidsCount = (int) $voidsQuery->count();

        // 5. Recorded operating expenses (UC-28)
        $expensesQuery = Expense::posted()->forPeriod($this->startDate, $this->endDate);
        $expenses = (int) $expensesQuery->sum('amount');
        $expensesCount = (int) $expensesQuery->count();

        // Expenses breakdown by category
        $expensesByCategory = [];
        foreach (Expense::CATEGORIES as $category) {
            $catTotal = (int) Expense::posted()
                ->forPeriod($this->startDate, $this->endDate)
                ->where('category', $category)
                ->sum('amount');
            $expensesByCategory[$category] = $catTotal;
        }

        // 6. Net Operating Surplus (Net sales - operating expenses)
        $operatingSurplus = $netSales - $expenses;

        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'gross_sales' => $grossSales,
            'gross_sales_count' => $grossSalesCount,
            'refunds' => $refunds,
            'refunds_count' => $refundsCount,
            'net_sales' => $netSales,
            'voids' => $voids,
            'voids_count' => $voidsCount,
            'expenses' => $expenses,
            'expenses_count' => $expensesCount,
            'expenses_by_category' => $expensesByCategory,
            'operating_surplus' => $operatingSurplus,
        ];
    }
}

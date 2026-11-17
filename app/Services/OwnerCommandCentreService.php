<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\Batch;
use App\Models\CreditAccount;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recall;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\StaffLicence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service to aggregate, compute and cache all command centre metrics, charts,
 * attention alerts, and KPIs for the Executive Owner Dashboard (UC-32).
 *
 * @uc UC-32
 *
 * @fr FR-7.7, FR-9.2
 */
class OwnerCommandCentreService
{
    /**
     * Compute and cache full command centre payload with a 60-second TTL.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(
        string $period = 'today',
        ?string $customFrom = null,
        ?string $customTo = null,
        ?int $tillId = null,
        ?int $staffId = null,
        bool $forceFresh = false
    ): array {
        [$from, $to, $prevFrom, $prevTo, $periodLabel] = $this->resolveDateRanges($period, $customFrom, $customTo);

        $cacheKey = sprintf(
            'owner_command_centre_%s_%s_%s_%s_%s',
            $period,
            $from->format('YmdHis'),
            $to->format('YmdHis'),
            $tillId ?? 'all',
            $staffId ?? 'all'
        );

        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $prevFrom, $prevTo, $periodLabel, $tillId, $staffId) {
            $userId = $this->resolveUserIdFromStaff($staffId);
            $kpis = $this->computeKpis($from, $to, $prevFrom, $prevTo, $tillId, $staffId);
            $charts = $this->computeCharts($from, $to, $tillId, $staffId);
            $attention = $this->computeAttentionItems();
            $activity = $this->computeLiveActivity();
            $surplus = $this->computeOperatingSurplus($from, $to, $tillId, $userId);

            return [
                'period_label' => $periodLabel,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'kpis' => $kpis,
                'charts' => $charts,
                'attention' => $attention,
                'activity' => $activity,
                'surplus' => $surplus,
                'generated_at' => now()->timezone(config('pms.timezone', 'Africa/Addis_Ababa'))->format('H:i:s'),
            ];
        });
    }

    /**
     * Calculate current and previous date ranges.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string}
     */
    public function resolveDateRanges(string $period, ?string $customFrom = null, ?string $customTo = null): array
    {
        $now = now()->timezone(config('pms.timezone', 'Africa/Addis_Ababa'));

        switch ($period) {
            case 'yesterday':
                $from = $now->copy()->subDay()->startOfDay();
                $to = $now->copy()->subDay()->endOfDay();
                $prevFrom = $now->copy()->subDays(2)->startOfDay();
                $prevTo = $now->copy()->subDays(2)->endOfDay();
                $periodLabel = __('dashboard.owner.periods.yesterday').' ('.$from->format('d M Y').')';
                break;

            case '7_days':
                $from = $now->copy()->subDays(6)->startOfDay();
                $to = $now->copy()->endOfDay();
                $prevFrom = $now->copy()->subDays(13)->startOfDay();
                $prevTo = $now->copy()->subDays(7)->endOfDay();
                $periodLabel = __('dashboard.owner.periods.7_days').' ('.$from->format('d M').' - '.$to->format('d M').')';
                break;

            case 'this_month':
                $from = $now->copy()->startOfMonth()->startOfDay();
                $to = $now->copy()->endOfDay();
                $prevFrom = $now->copy()->subMonth()->startOfMonth()->startOfDay();
                $prevTo = $now->copy()->subMonth()->endOfMonth()->endOfDay();
                $periodLabel = $now->format('F Y');
                break;

            case 'custom':
                $from = ! empty($customFrom) ? Carbon::parse($customFrom)->startOfDay() : $now->copy()->startOfDay();
                $to = ! empty($customTo) ? Carbon::parse($customTo)->endOfDay() : $now->copy()->endOfDay();
                $diffDays = max(1, $from->diffInDays($to) + 1);
                $prevFrom = $from->copy()->subDays($diffDays)->startOfDay();
                $prevTo = $from->copy()->subDay()->endOfDay();
                $periodLabel = $from->format('d M Y').' - '.$to->format('d M Y');
                break;

            case 'today':
            default:
                $from = $now->copy()->startOfDay();
                $to = $now->copy()->endOfDay();
                $prevFrom = $now->copy()->subDay()->startOfDay();
                $prevTo = $now->copy()->subDay()->endOfDay();
                $periodLabel = __('dashboard.owner.periods.today').' ('.$from->format('d M Y').')';
                break;
        }

        return [$from, $to, $prevFrom, $prevTo, $periodLabel];
    }

    /**
     * Compute the 12 core KPIs with previous period comparison.
     */
    private function computeKpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, ?int $tillId, ?int $staffId): array
    {
        $userId = $this->resolveUserIdFromStaff($staffId);

        // 1. Net Sales
        $salesCurrent = $this->querySalesTotal($from, $to, $tillId, $userId);
        $refundsCurrent = $this->queryRefundsTotal($from, $to, $tillId, $userId);
        $netSalesCurrent = max(0, $salesCurrent - $refundsCurrent);

        $salesPrev = $this->querySalesTotal($prevFrom, $prevTo, $tillId, $userId);
        $refundsPrev = $this->queryRefundsTotal($prevFrom, $prevTo, $tillId, $userId);
        $netSalesPrev = max(0, $salesPrev - $refundsPrev);

        // 2. Number of Sales
        $salesCountCurrent = $this->querySalesCount($from, $to, $tillId, $userId);
        $salesCountPrev = $this->querySalesCount($prevFrom, $prevTo, $tillId, $userId);

        // 3. Average Basket (AOV)
        $aovCurrent = $salesCountCurrent > 0 ? (int) round($netSalesCurrent / $salesCountCurrent) : 0;
        $aovPrev = $salesCountPrev > 0 ? (int) round($netSalesPrev / $salesCountPrev) : 0;

        // 4. Returns & Refunds
        $refundsCountCurrent = $this->queryRefundsCount($from, $to, $tillId, $userId);
        $refundsCountPrev = $this->queryRefundsCount($prevFrom, $prevTo, $tillId, $userId);

        // 5. Voids
        $voidsCountCurrent = $this->queryVoidsCount($from, $to, $tillId, $userId);
        $voidsCountPrev = $this->queryVoidsCount($prevFrom, $prevTo, $tillId, $userId);
        $voidsAmountCurrent = $this->queryVoidsAmount($from, $to, $tillId, $userId);

        // 6. Cash Difference (Variance on closed shifts)
        $varianceCurrent = $this->queryShiftVariance($from, $to, $tillId, $userId);
        $variancePrev = $this->queryShiftVariance($prevFrom, $prevTo, $tillId, $userId);

        // 7. Credit Outstanding (Total receivables)
        $creditOutstanding = Schema::hasTable('credit_accounts') ? (int) CreditAccount::sum('current_balance') : 0;

        // 8. Operating Expenses
        $expensesCurrent = $this->queryExpensesTotal($from, $to);
        $expensesPrev = $this->queryExpensesTotal($prevFrom, $prevTo);

        // 9. Stock Value at Selling Price
        $stockValueSelling = $this->queryTotalStockValueAtSellingPrice();

        // 10. Items Low
        $itemsLowCount = $this->queryLowStockCount();

        // 11. Near-Expiry & Expired Batches
        $nearExpiryCount = $this->queryNearExpiryCount();
        $expiredCount = $this->queryExpiredCount();

        // 12. Controlled Drug Exceptions
        $controlledExceptions = $this->queryControlledDrugExceptions($from, $to);
        $controlledExceptionsPrev = $this->queryControlledDrugExceptions($prevFrom, $prevTo);

        return [
            'net_sales' => [
                'label' => __('dashboard.owner.kpi.net_sales'),
                'value' => $netSalesCurrent,
                'formatted' => number_format($netSalesCurrent / 100, 2).' ETB',
                'change' => $this->calcPercentageChange($netSalesCurrent, $netSalesPrev),
                'is_positive' => $netSalesCurrent >= $netSalesPrev,
                'drill_url' => route('reports.run', ['type' => 'sales-summary', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
            ],
            'sales_count' => [
                'label' => __('dashboard.owner.kpi.sales_count'),
                'value' => $salesCountCurrent,
                'formatted' => number_format($salesCountCurrent),
                'change' => $this->calcPercentageChange($salesCountCurrent, $salesCountPrev),
                'is_positive' => $salesCountCurrent >= $salesCountPrev,
                'drill_url' => route('reports.run', ['type' => 'sales-summary', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
            ],
            'average_basket' => [
                'label' => __('dashboard.owner.kpi.average_basket'),
                'value' => $aovCurrent,
                'formatted' => number_format($aovCurrent / 100, 2).' ETB',
                'change' => $this->calcPercentageChange($aovCurrent, $aovPrev),
                'is_positive' => $aovCurrent >= $aovPrev,
                'drill_url' => route('reports.run', ['type' => 'sales-summary', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
            ],
            'returns_refunds' => [
                'label' => __('dashboard.owner.kpi.returns_refunds'),
                'value' => $refundsCurrent,
                'formatted' => number_format($refundsCurrent / 100, 2).' ETB',
                'subtext' => "{$refundsCountCurrent} ".trans_choice('dashboard.owner.kpi.refund_count', $refundsCountCurrent),
                'change' => $this->calcPercentageChange($refundsCurrent, $refundsPrev),
                'is_positive' => $refundsCurrent <= $refundsPrev, // lower returns is positive
                'drill_url' => route('reports.run', ['type' => 'sales-returns-refunds', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
            ],
            'voids' => [
                'label' => __('dashboard.owner.kpi.voids'),
                'value' => $voidsCountCurrent,
                'formatted' => "{$voidsCountCurrent} ".trans_choice('dashboard.owner.kpi.void_count', $voidsCountCurrent),
                'subtext' => number_format($voidsAmountCurrent / 100, 2).' ETB',
                'change' => $this->calcPercentageChange($voidsCountCurrent, $voidsCountPrev),
                'is_positive' => $voidsCountCurrent <= $voidsCountPrev,
                'drill_url' => route('reports.run', ['type' => 'audit-voids', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
            ],
            'cash_difference' => [
                'label' => __('dashboard.owner.kpi.cash_difference'),
                'value' => $varianceCurrent,
                'formatted' => ($varianceCurrent >= 0 ? '+' : '').number_format($varianceCurrent / 100, 2).' ETB',
                'change' => $this->calcPercentageChange(abs($varianceCurrent), abs($variancePrev)),
                'is_positive' => $varianceCurrent >= 0,
                'drill_url' => route('finance.cash-revenue'),
            ],
            'credit_outstanding' => [
                'label' => __('dashboard.owner.kpi.credit_outstanding'),
                'value' => $creditOutstanding,
                'formatted' => number_format($creditOutstanding / 100, 2).' ETB',
                'change' => null,
                'is_positive' => true,
                'drill_url' => route('credits.ageing'),
            ],
            'expenses' => [
                'label' => __('dashboard.owner.kpi.expenses'),
                'value' => $expensesCurrent,
                'formatted' => number_format($expensesCurrent / 100, 2).' ETB',
                'change' => $this->calcPercentageChange($expensesCurrent, $expensesPrev),
                'is_positive' => $expensesCurrent <= $expensesPrev, // lower expenses is positive
                'drill_url' => route('finance.cash-revenue'),
            ],
            'stock_value_selling' => [
                'label' => __('dashboard.owner.kpi.stock_value_selling'),
                'value' => $stockValueSelling,
                'formatted' => number_format($stockValueSelling / 100, 2).' ETB',
                'change' => null,
                'is_positive' => true,
                'drill_url' => route('reports.run', ['type' => 'inventory-current-stock']),
            ],
            'items_low' => [
                'label' => __('dashboard.owner.kpi.items_low'),
                'value' => $itemsLowCount,
                'formatted' => "{$itemsLowCount} ".trans_choice('dashboard.owner.kpi.item_count', $itemsLowCount),
                'change' => null,
                'is_positive' => $itemsLowCount === 0,
                'drill_url' => route('reports.run', ['type' => 'inventory-low-stock']),
            ],
            'expiry_counts' => [
                'label' => __('dashboard.owner.kpi.expiry_counts'),
                'value' => $nearExpiryCount + $expiredCount,
                'formatted' => "{$nearExpiryCount} / {$expiredCount}",
                'subtext' => __('dashboard.owner.kpi.near_vs_expired'),
                'change' => null,
                'is_positive' => ($nearExpiryCount + $expiredCount) === 0,
                'drill_url' => route('reports.run', ['type' => 'inventory-near-expiry']),
            ],
            'controlled_exceptions' => [
                'label' => __('dashboard.owner.kpi.controlled_exceptions'),
                'value' => $controlledExceptions,
                'formatted' => (string) $controlledExceptions,
                'change' => $this->calcPercentageChange($controlledExceptions, $controlledExceptionsPrev),
                'is_positive' => $controlledExceptions === 0,
                'drill_url' => route('reports.run', ['type' => 'compliance-controlled-drugs']),
            ],
        ];
    }

    /**
     * Compute visual charts data and tabular structures.
     */
    private function computeCharts(Carbon $from, Carbon $to, ?int $tillId, ?int $staffId): array
    {
        $userId = $this->resolveUserIdFromStaff($staffId);

        return [
            'sales_by_hour' => $this->chartSalesByHour($from, $to, $tillId, $userId),
            'sales_by_day' => $this->chartSalesByDay($from, $to, $tillId, $userId),
            'sales_by_category' => $this->chartSalesByCategory($from, $to, $tillId, $userId),
            'sales_by_payment_method' => $this->chartSalesByPaymentMethod($from, $to, $tillId, $userId),
            'sales_by_staff' => $this->chartSalesByStaff($from, $to, $tillId),
            'top_10_products' => $this->chartTopProducts($from, $to, $tillId, $userId),
            'bottom_10_products' => $this->chartBottomProducts($from, $to, $tillId, $userId),
        ];
    }

    /**
     * Sales by hour for the selected period.
     */
    private function chartSalesByHour(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $driver = DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite'
            ? "cast(strftime('%H', completed_at) as integer)"
            : 'HOUR(completed_at)';

        $rows = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw("{$hourExpr} as hour, SUM(total) as revenue, COUNT(*) as tx_count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $maxRevenue = max(1, (int) $rows->max('revenue'));
        $data = [];

        for ($h = 7; $h <= 22; $h++) {
            $row = $rows->firstWhere('hour', $h);
            $rev = $row ? (int) $row->revenue : 0;
            $cnt = $row ? (int) $row->tx_count : 0;
            $data[] = [
                'label' => sprintf('%02d:00', $h),
                'value' => $rev,
                'count' => $cnt,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => round(($rev / $maxRevenue) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.sales_by_hour'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-summary', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Sales by day over the selected date range.
     */
    private function chartSalesByDay(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', completed_at)"
            : 'DATE(completed_at)';

        $rows = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw("{$dateExpr} as date, SUM(total) as revenue, COUNT(*) as tx_count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        $cursor = $from->copy();
        $maxRevenue = 1;

        while ($cursor->lte($to)) {
            $dateStr = $cursor->toDateString();
            $row = $rows->get($dateStr);
            $rev = $row ? (int) $row->revenue : 0;
            if ($rev > $maxRevenue) {
                $maxRevenue = $rev;
            }
            $data[] = [
                'label' => $cursor->format('M d'),
                'date' => $dateStr,
                'value' => $rev,
                'count' => $row ? (int) $row->tx_count : 0,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => 0, // calculated below
            ];
            $cursor->addDay();
        }

        foreach ($data as &$item) {
            $item['percentage'] = round(($item['value'] / max(1, $maxRevenue)) * 100, 1);
        }

        return [
            'title' => __('dashboard.owner.charts.sales_by_day'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-summary', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Sales by product category.
     */
    private function chartSalesByCategory(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $rows = DB::table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('products', 'sale_lines.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId))
            ->select(
                DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"),
                DB::raw('SUM(sale_lines.subtotal) as revenue'),
                DB::raw('SUM(sale_lines.quantity) as quantity')
            )
            ->groupBy('category_name')
            ->orderByDesc('revenue')
            ->take(8)
            ->get();

        $totalRevenue = max(1, (int) $rows->sum('revenue'));
        $data = [];

        foreach ($rows as $row) {
            $rev = (int) $row->revenue;
            $data[] = [
                'label' => $row->category_name,
                'value' => $rev,
                'count' => (int) $row->quantity,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => round(($rev / $totalRevenue) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.sales_by_category'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-by-category', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Sales by payment method.
     */
    private function chartSalesByPaymentMethod(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $rows = DB::table('payments')
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId))
            ->select(
                DB::raw('COALESCE(payment_methods.name, payments.method) as method_name'),
                DB::raw('SUM(payments.amount) as revenue'),
                DB::raw('COUNT(payments.id) as tx_count')
            )
            ->groupBy('method_name')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = max(1, (int) $rows->sum('revenue'));
        $data = [];

        foreach ($rows as $row) {
            $rev = (int) $row->revenue;
            $data[] = [
                'label' => ucfirst(str_replace('_', ' ', $row->method_name)),
                'value' => $rev,
                'count' => (int) $row->tx_count,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => round(($rev / $totalRevenue) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.sales_by_payment_method'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-by-payment-method', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Sales by staff member.
     */
    private function chartSalesByStaff(Carbon $from, Carbon $to, ?int $tillId): array
    {
        $rows = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('staff', 'users.id', '=', 'staff.user_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->select(
                DB::raw('COALESCE(staff.name, users.name, users.username) as staff_name'),
                DB::raw('SUM(sales.total) as revenue'),
                DB::raw('COUNT(sales.id) as sales_count')
            )
            ->groupBy('staff_name')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = max(1, (int) $rows->sum('revenue'));
        $data = [];

        foreach ($rows as $row) {
            $rev = (int) $row->revenue;
            $data[] = [
                'label' => $row->staff_name,
                'value' => $rev,
                'count' => (int) $row->sales_count,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => round(($rev / $totalRevenue) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.sales_by_staff'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-by-staff', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Top 10 products by revenue.
     */
    private function chartTopProducts(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $rows = DB::table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('products', 'sale_lines.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId))
            ->select(
                'products.brand as product_name',
                DB::raw('SUM(sale_lines.subtotal) as revenue'),
                DB::raw('SUM(sale_lines.quantity) as quantity')
            )
            ->groupBy('products.id', 'products.brand')
            ->orderByDesc('revenue')
            ->take(10)
            ->get();

        $maxRevenue = max(1, (int) $rows->max('revenue'));
        $data = [];

        foreach ($rows as $row) {
            $rev = (int) $row->revenue;
            $data[] = [
                'label' => $row->product_name,
                'value' => $rev,
                'count' => (int) $row->quantity,
                'formatted' => number_format($rev / 100, 2).' ETB',
                'percentage' => round(($rev / $maxRevenue) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.top_10_products'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-by-product', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Bottom 10 products by sales volume (items with stock that are moving slowest).
     */
    private function chartBottomProducts(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $rows = DB::table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('products', 'sale_lines.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->select(
                'products.brand as product_name',
                DB::raw('SUM(sale_lines.subtotal) as revenue'),
                DB::raw('SUM(sale_lines.quantity) as quantity')
            )
            ->groupBy('products.id', 'products.brand')
            ->orderBy('quantity')
            ->take(10)
            ->get();

        $maxQty = max(1, (int) $rows->max('quantity'));
        $data = [];

        foreach ($rows as $row) {
            $qty = (int) $row->quantity;
            $data[] = [
                'label' => $row->product_name,
                'value' => (int) $row->revenue,
                'count' => $qty,
                'formatted' => number_format($row->revenue / 100, 2).' ETB',
                'percentage' => round(($qty / $maxQty) * 100, 1),
            ];
        }

        return [
            'title' => __('dashboard.owner.charts.bottom_10_products'),
            'data' => $data,
            'total' => (int) $rows->sum('revenue'),
            'drill_url' => route('reports.run', ['type' => 'sales-by-product', 'startDate' => $from->toDateString(), 'endDate' => $to->toDateString(), 'mode' => 'custom']),
        ];
    }

    /**
     * Needs-Attention Panel: actionable urgent items the owner must see first.
     */
    private function computeAttentionItems(): array
    {
        $items = [];

        // 1. Pending Approvals
        if (Schema::hasTable('approval_requests')) {
            $pending = ApprovalRequest::where('status', 'pending')->orderByDesc('created_at')->get();
            if ($pending->isNotEmpty()) {
                $items[] = [
                    'key' => 'pending_approvals',
                    'level' => 'critical',
                    'title' => __('dashboard.owner.attention.pending_approvals_title', ['count' => $pending->count()]),
                    'description' => __('dashboard.owner.attention.pending_approvals_desc', ['count' => $pending->count()]),
                    'url' => route('approvals.index'),
                    'action_label' => __('dashboard.owner.attention.view_approvals'),
                    'badge' => (string) $pending->count(),
                    'is_approval' => true,
                    'approval_id' => $pending->first()->id,
                ];
            }
        }

        // 2. Shifts not closed
        if (Schema::hasTable('shifts')) {
            $today = business_date();
            $unclosed = Shift::with(['till', 'user'])
                ->where('status', 'open')
                ->whereDate('opened_at', '<', $today)
                ->get();

            if ($unclosed->isNotEmpty()) {
                $items[] = [
                    'key' => 'unclosed_shifts',
                    'level' => 'warning',
                    'title' => __('dashboard.owner.attention.unclosed_shifts_title', ['count' => $unclosed->count()]),
                    'description' => __('dashboard.owner.attention.unclosed_shifts_desc', ['tills' => $unclosed->pluck('till.name')->filter()->join(', ')]),
                    'url' => route('shifts.close'),
                    'action_label' => __('dashboard.owner.attention.close_shifts'),
                    'badge' => (string) $unclosed->count(),
                ];
            }
        }

        // 3. Days not closed
        if (Schema::hasTable('daily_closings')) {
            $unclosedDays = DailyClosing::where('status', 'open')->count();
            if ($unclosedDays > 0) {
                $items[] = [
                    'key' => 'unclosed_days',
                    'level' => 'warning',
                    'title' => __('dashboard.owner.attention.unclosed_days_title', ['count' => $unclosedDays]),
                    'description' => __('dashboard.owner.attention.unclosed_days_desc'),
                    'url' => route('closing.day'),
                    'action_label' => __('dashboard.owner.attention.lock_days'),
                    'badge' => (string) $unclosedDays,
                ];
            }
        }

        // 4. Cash Differences Over Threshold
        $varianceThreshold = (int) setting('shifts.variance_alert_threshold', 5000); // 50 ETB
        if (Schema::hasTable('shifts')) {
            $largeVarianceShifts = Shift::where('status', 'closed')
                ->where(DB::raw('ABS(variance)'), '>', $varianceThreshold)
                ->where('closed_at', '>=', now()->subDays(7))
                ->count();

            if ($largeVarianceShifts > 0) {
                $items[] = [
                    'key' => 'cash_differences',
                    'level' => 'critical',
                    'title' => __('dashboard.owner.attention.cash_variance_title', ['count' => $largeVarianceShifts]),
                    'description' => __('dashboard.owner.attention.cash_variance_desc', ['threshold' => number_format($varianceThreshold / 100, 2)]),
                    'url' => route('finance.cash-revenue'),
                    'action_label' => __('dashboard.owner.attention.view_cash'),
                    'badge' => (string) $largeVarianceShifts,
                ];
            }
        }

        // 5. Licences and Staff Documents Expiring in 30 days
        $expiringLicences = Schema::hasTable('staff_licences')
            ? StaffLicence::whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])->count()
            : 0;

        $expiringDocs = Schema::hasTable('staff_documents')
            ? StaffDocument::whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])->count()
            : 0;

        $totalExpiringStaff = $expiringLicences + $expiringDocs;
        if ($totalExpiringStaff > 0) {
            $items[] = [
                'key' => 'expiring_staff_docs',
                'level' => 'warning',
                'title' => __('dashboard.owner.attention.expiring_staff_title', ['count' => $totalExpiringStaff]),
                'description' => __('dashboard.owner.attention.expiring_staff_desc', ['licences' => $expiringLicences, 'docs' => $expiringDocs]),
                'url' => route('staff.index'),
                'action_label' => __('dashboard.owner.attention.view_staff'),
                'badge' => (string) $totalExpiringStaff,
            ];
        }

        // 6. Failed Backups
        if (Schema::hasTable('backup_runs')) {
            $failedBackups = BackupRun::where('status', 'failed')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            if ($failedBackups > 0) {
                $items[] = [
                    'key' => 'failed_backups',
                    'level' => 'critical',
                    'title' => __('dashboard.owner.attention.failed_backups_title', ['count' => $failedBackups]),
                    'description' => __('dashboard.owner.attention.failed_backups_desc'),
                    'url' => route('admin.backups'),
                    'action_label' => __('dashboard.owner.attention.view_backups'),
                    'badge' => (string) $failedBackups,
                ];
            }
        }

        // 7. Products Below Reorder Level
        $lowStockCount = $this->queryLowStockCount();
        if ($lowStockCount > 0) {
            $items[] = [
                'key' => 'products_low_stock',
                'level' => 'info',
                'title' => __('dashboard.owner.attention.low_stock_title', ['count' => $lowStockCount]),
                'description' => __('dashboard.owner.attention.low_stock_desc'),
                'url' => route('reports.run', ['type' => 'inventory-low-stock']),
                'action_label' => __('dashboard.owner.attention.view_low_stock'),
                'badge' => (string) $lowStockCount,
            ];
        }

        // 8. Open Recalls
        if (Schema::hasTable('recalls')) {
            $openRecalls = Recall::where('status', 'open')->count();
            if ($openRecalls > 0) {
                $items[] = [
                    'key' => 'open_recalls',
                    'level' => 'critical',
                    'title' => __('dashboard.owner.attention.open_recalls_title', ['count' => $openRecalls]),
                    'description' => __('dashboard.owner.attention.open_recalls_desc'),
                    'url' => route('reports.run', ['type' => 'compliance-recalls']),
                    'action_label' => __('dashboard.owner.attention.view_recalls'),
                    'badge' => (string) $openRecalls,
                ];
            }
        }

        // 9. Voids or Adjustments above normal rate
        $recentVoids = Sale::where('status', 'voided')
            ->where('completed_at', '>=', now()->subDays(3))
            ->count();

        if ($recentVoids >= 3) {
            $items[] = [
                'key' => 'high_void_rate',
                'level' => 'warning',
                'title' => __('dashboard.owner.attention.high_voids_title', ['count' => $recentVoids]),
                'description' => __('dashboard.owner.attention.high_voids_desc'),
                'url' => route('reports.run', ['type' => 'audit-voids']),
                'action_label' => __('dashboard.owner.attention.view_voids'),
                'badge' => (string) $recentVoids,
            ];
        }

        return $items;
    }

    /**
     * Live activity feed (last 20 audited events in plain language).
     */
    private function computeLiveActivity(): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        $logs = AuditLog::with('user')
            ->orderByDesc('id')
            ->take(20)
            ->get();

        return $logs->map(function (AuditLog $log) {
            $user = $log->user?->name ?: ($log->user?->username ?: 'System');
            $action = $log->action;
            $time = $log->created_at ? $log->created_at->diffForHumans() : '';
            $exactTime = $log->created_at ? $log->created_at->timezone(config('pms.timezone', 'Africa/Addis_Ababa'))->format('d M H:i') : '';

            $description = $this->formatAuditDescription($log, $user);

            return [
                'id' => $log->id,
                'action' => $action,
                'user' => $user,
                'description' => $description,
                'time_ago' => $time,
                'exact_time' => $exactTime,
                'badge_color' => $this->auditBadgeColor($action),
            ];
        })->toArray();
    }

    /**
     * Compute operating surplus (Net sales minus operating expenses) or cost/profit.
     */
    private function computeOperatingSurplus(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): array
    {
        $sales = $this->querySalesTotal($from, $to, $tillId, $userId);
        $refunds = $this->queryRefundsTotal($from, $to, $tillId, $userId);
        $netSales = max(0, $sales - $refunds);
        $expenses = $this->queryExpensesTotal($from, $to);
        $surplus = $netSales - $expenses;

        $showCostProfit = (bool) setting('reports.show_cost_profit', false);

        $cogs = 0;
        $grossProfit = $netSales;
        $netProfit = $surplus;
        $marginPercent = $netSales > 0 ? round(($surplus / $netSales) * 100, 1) : 0.0;

        if ($showCostProfit) {
            $cogs = (int) SaleLine::query()
                ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('batches', 'sale_lines.batch_id', '=', 'batches.id')
                ->where('sales.status', 'completed')
                ->whereBetween('sales.completed_at', [$from, $to])
                ->select(DB::raw('SUM(sale_lines.quantity * COALESCE(batches.unit_cost, 0)) as total_cogs'))
                ->value('total_cogs');

            $grossProfit = $netSales - $cogs;
            $netProfit = $grossProfit - $expenses;
            $marginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0.0;
        }

        return [
            'show_cost_profit' => $showCostProfit,
            'net_sales' => $netSales,
            'expenses' => $expenses,
            'surplus' => $surplus,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'margin_percent' => $marginPercent,
            'formatted_net_sales' => number_format($netSales / 100, 2).' ETB',
            'formatted_expenses' => number_format($expenses / 100, 2).' ETB',
            'formatted_surplus' => number_format($surplus / 100, 2).' ETB',
            'formatted_cogs' => number_format($cogs / 100, 2).' ETB',
            'formatted_gross_profit' => number_format($grossProfit / 100, 2).' ETB',
            'formatted_net_profit' => number_format($netProfit / 100, 2).' ETB',
        ];
    }

    // ─── Query Helpers ───────────────────────────────────────────────────────

    private function resolveUserIdFromStaff(?int $staffId): ?int
    {
        if (! $staffId) {
            return null;
        }

        return Staff::where('id', $staffId)->value('user_id');
    }

    private function querySalesTotal(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return (int) Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->sum('total');
    }

    private function querySalesCount(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

    private function queryRefundsTotal(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return (int) SaleReturn::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->sum('total_refund');
    }

    private function queryRefundsCount(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return SaleReturn::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

    private function queryVoidsCount(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return Sale::where('status', 'voided')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

    private function queryVoidsAmount(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return (int) Sale::where('status', 'voided')
            ->whereBetween('completed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('till_id', $tillId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->sum('total');
    }

    private function queryShiftVariance(Carbon $from, Carbon $to, ?int $tillId, ?int $userId): int
    {
        return (int) Shift::where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->when($tillId, fn ($q) => $q->where('till_id', $tillId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->sum('variance');
    }

    private function queryExpensesTotal(Carbon $from, Carbon $to): int
    {
        return (int) Expense::posted()
            ->forPeriod($from->toDateString(), $to->toDateString())
            ->sum('amount');
    }

    private function queryTotalStockValueAtSellingPrice(): int
    {
        if (! Schema::hasTable('batch_stocks')) {
            return 0;
        }

        // Multiply sellable batch stocks by product prices
        $result = DB::table('batch_stocks')
            ->join('batches', 'batch_stocks.batch_id', '=', 'batches.id')
            ->join('products', 'batches.product_id', '=', 'products.id')
            ->where('batch_stocks.status', 'sellable')
            ->where('batch_stocks.quantity', '>', 0)
            ->select(DB::raw('SUM(batch_stocks.quantity * COALESCE(batches.unit_cost, 0)) as total_val'))
            ->value('total_val');

        return (int) ($result ?? 0);
    }

    private function queryLowStockCount(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('batch_stocks')) {
            return 0;
        }

        return Product::query()
            ->where('is_active', true)
            ->whereHas('batches.batchStocks', fn ($q) => $q->where('batch_stocks.status', 'sellable'))
            ->withSum(['batchStocks as sellable_qty' => fn ($q) => $q->where('batch_stocks.status', 'sellable')], 'quantity')
            ->get()
            ->filter(fn ($p) => ($p->sellable_qty ?? 0) <= $p->reorder_level)
            ->count();
    }

    private function queryNearExpiryCount(): int
    {
        if (! Schema::hasTable('batches')) {
            return 0;
        }

        $today = now()->toDateString();
        $in90Days = now()->addDays(90)->toDateString();

        return Batch::whereBetween('expiry_date', [$today, $in90Days])
            ->whereHas('batchStocks', fn ($q) => $q->where('status', 'sellable')->where('quantity', '>', 0))
            ->count();
    }

    private function queryExpiredCount(): int
    {
        if (! Schema::hasTable('batches')) {
            return 0;
        }

        $today = now()->toDateString();

        return Batch::where('expiry_date', '<', $today)
            ->whereHas('batchStocks', fn ($q) => $q->where('quantity', '>', 0))
            ->count();
    }

    private function queryControlledDrugExceptions(Carbon $from, Carbon $to): int
    {
        if (! Schema::hasTable('sale_lines')) {
            return 0;
        }

        return SaleLine::query()
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('products', 'sale_lines.product_id', '=', 'products.id')
            ->where('products.classification', 'controlled')
            ->where('sale_lines.is_fefo_override', true)
            ->whereBetween('sales.completed_at', [$from, $to])
            ->count();
    }

    private function calcPercentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function formatAuditDescription(AuditLog $log, string $user): string
    {
        $meta = $log->metadata ?? [];
        $new = $log->new_values ?? [];

        return match ($log->action) {
            'sale.completed' => "Completed sale {$meta['receipt_no']} for ".number_format(($new['total'] ?? 0) / 100, 2).' ETB',
            'sale.voided' => "Voided sale {$meta['receipt_no']}: ".($meta['reason'] ?? 'Approved void'),
            'sale.reprinted' => "Reprinted receipt {$meta['receipt_no']}: ".($meta['reason'] ?? 'Copy'),
            'expense.recorded' => 'Recorded expense: '.($new['description'] ?? 'Operating expense').' ('.number_format(($new['amount'] ?? 0) / 100, 2).' ETB)',
            'auth.login.success' => "Signed in successfully as {$log->role_used}",
            'auth.logout' => 'Signed out of system',
            'stock.receipt.confirmed' => "Confirmed stock receipt {$meta['receipt_no']}",
            'staff.document.uploaded' => 'Uploaded staff document for '.($new['staff_name'] ?? 'staff member'),
            'sales_target.saved' => 'Updated sales target for '.($new['period'] ?? 'period'),
            default => $log->description ?: "Action {$log->action} recorded by {$user}",
        };
    }

    private function auditBadgeColor(string $action): string
    {
        return match (true) {
            str_contains($action, 'void') || str_contains($action, 'failed') || str_contains($action, 'deleted') => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            str_contains($action, 'sale') || str_contains($action, 'confirmed') || str_contains($action, 'success') => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            str_contains($action, 'expense') || str_contains($action, 'reprint') => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
    }
}

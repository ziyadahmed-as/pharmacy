<?php

namespace App\Reports\Definitions\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Customer purchase history report (UC-30/UC-25).
 */
class CustomerPurchaseHistoryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'customer_purchase_history';
    }

    public function name(): string
    {
        return __('reports.customer_purchase_history_name', [], 'en') ?: 'Customer Purchase History';
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.customer_purchase_history_desc', [], 'en') ?: 'Sales, dispensed medications, totals, and return frequency by customer.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_customer', [], 'en') ?: 'Customer Name',
            __('reports.col_phone', [], 'en') ?: 'Phone',
            __('reports.col_total_purchases', [], 'en') ?: 'Total Purchases',
            __('reports.col_total_spent', [], 'en') ?: 'Total Spent',
            __('reports.col_last_visit', [], 'en') ?: 'Last Visit Date',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        if (! Schema::hasTable('customers')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => [],
                'row_count' => 0,
            ];
        }

        $customers = Customer::query()
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . $filters['search'] . '%';
                $q->where('name', 'like', $term)->orWhere('phone', 'like', $term);
            })
            ->get();

        $rows = [];
        $grandTotal = 0;

        foreach ($customers as $c) {
            $sales = Sale::where('customer_id', $c->id)
                ->where('status', 'completed')
                ->whereDate('completed_at', '>=', $start)
                ->whereDate('completed_at', '<=', $end)
                ->get();

            if ($sales->isEmpty() && ! empty($filters['date_range'])) {
                continue;
            }

            $spent = (int) $sales->sum('total');
            $grandTotal += $spent;
            $lastDate = $sales->max('completed_at');

            $rows[] = [
                $c->name,
                $c->phone ?? '—',
                $sales->count(),
                $this->formatMoney($spent),
                $lastDate ? \Carbon\Carbon::parse($lastDate)->format('Y-m-d') : '—',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'customers_count' => count($rows),
                'total_revenue' => $this->formatMoney($grandTotal),
            ],
            'row_count' => count($rows),
        ];
    }
}

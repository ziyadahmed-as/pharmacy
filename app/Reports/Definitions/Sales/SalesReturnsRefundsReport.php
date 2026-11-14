<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\SaleReturn;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Returns and refunds report (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesReturnsRefundsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_returns_refunds';
    }

    public function name(): string
    {
        return __('reports.sales_returns_refunds_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_returns_refunds_desc');
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
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_refund_no'),
            __('reports.col_receipt_no'),
            __('reports.col_date'),
            __('reports.col_processed_by'),
            __('reports.col_payment_method'),
            __('reports.col_refund_amount'),
            __('reports.col_reason'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);

        $query = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end)
            ->with(['sale', 'user'])
            ->orderByDesc('completed_at');

        if ($isStaff) {
            $query->where('user_id', $user->id);
        }

        $refunds = $query->get();

        $rows = [];
        $totalRefund = 0;

        foreach ($refunds as $r) {
            $totalRefund += $r->total_refund;
            $rows[] = [
                $r->refund_no,
                $r->sale?->receipt_no ?? '-',
                $this->formatDate($r->completed_at),
                $r->user?->name ?? 'User #'.$r->user_id,
                ucfirst(str_replace('_', ' ', $r->payment_method)),
                $this->formatMoney($r->total_refund),
                $r->reason ?? '-',
            ];
        }

        $summary = [
            'total_refunds_count' => count($rows),
            'total_refund_amount' => $this->formatMoney($totalRefund),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

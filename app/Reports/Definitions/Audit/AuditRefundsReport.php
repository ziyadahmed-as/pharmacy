<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\SaleReturn;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Refunds and returns audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-3.8 @ac AC-29
 */
class AuditRefundsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_refunds';
    }

    public function name(): string
    {
        return __('reports.audit_refunds_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_refunds_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
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
            __('reports.col_staff_member'),
            __('reports.col_payment_method'),
            __('reports.col_refund_amount'),
            __('reports.col_requires_approval'),
            __('reports.col_reason'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $refunds = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end)
            ->with(['sale', 'user'])
            ->orderByDesc('completed_at')
            ->get();

        $rows = [];
        $totRefund = 0;

        foreach ($refunds as $r) {
            $totRefund += $r->total_refund;

            $rows[] = [
                $r->refund_no,
                $r->sale?->receipt_no ?? '-',
                $this->formatDate($r->completed_at),
                $r->user?->name ?? 'User #'.$r->user_id,
                ucfirst(str_replace('_', ' ', $r->payment_method)),
                $this->formatMoney($r->total_refund),
                $r->requires_approval ? __('reports.yes') : __('reports.no'),
                $r->reason ?? '-',
            ];
        }

        $summary = [
            'total_refunds_count' => count($rows),
            'total_refund_amount' => $this->formatMoney($totRefund),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

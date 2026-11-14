<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Voided sales audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-3.7 @ac AC-29
 */
class AuditVoidsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_voids';
    }

    public function name(): string
    {
        return __('reports.audit_voids_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_voids_desc');
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
            __('reports.col_receipt_no'),
            __('reports.col_date'),
            __('reports.col_cashier'),
            __('reports.col_total_amount'),
            __('reports.col_lines_count'),
            __('reports.col_void_reason'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $sales = Sale::query()
            ->where('status', 'voided')
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end)
            ->with(['user', 'lines'])
            ->orderByDesc('completed_at')
            ->get();

        $rows = [];
        $totVoided = 0;

        foreach ($sales as $sale) {
            $totVoided += $sale->total;

            $rows[] = [
                $sale->receipt_no,
                $this->formatDate($sale->completed_at),
                $sale->user?->name ?? 'User #'.$sale->user_id,
                $this->formatMoney($sale->total),
                (string) $sale->lines->count(),
                $sale->void_reason ?? '-',
            ];
        }

        $summary = [
            'total_voided_sales' => count($rows),
            'total_voided_value' => $this->formatMoney($totVoided),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\AdjustmentRequest;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Stock adjustments audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-2.8 @ac AC-29
 */
class AuditAdjustmentsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_adjustments';
    }

    public function name(): string
    {
        return __('reports.audit_adjustments_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_adjustments_desc');
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
            'status' => ['pending', 'approved', 'rejected'],
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_request_id'),
            __('reports.col_date'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_reason_type'),
            __('reports.col_quantity'),
            __('reports.col_written_reason'),
            __('reports.col_requested_by'),
            __('reports.col_second_person'),
            __('reports.col_status'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = AdjustmentRequest::query()
            ->with(['batch.product', 'requester', 'secondPerson'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $adjustments = $query->get();

        $rows = [];
        $totQuantity = 0;

        foreach ($adjustments as $adj) {
            $totQuantity += $adj->quantity;

            $rows[] = [
                '#'.$adj->id,
                $this->formatDate($adj->created_at),
                $adj->batch?->product?->brand ?? 'Unknown',
                $adj->batch?->batch_no ?? '-',
                ucfirst(str_replace('_', ' ', $adj->reason_type)),
                (string) $adj->quantity,
                $adj->written_reason,
                $adj->requester?->name ?? 'User #'.$adj->requested_by,
                $adj->secondPerson?->name ?? '-',
                ucfirst($adj->status),
            ];
        }

        $summary = [
            'total_adjustment_requests' => count($rows),
            'total_quantity_adjusted' => $totQuantity,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\Disposal;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Disposals compliance audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-2.19 @ac AC-29
 */
class AuditDisposalsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_disposals';
    }

    public function name(): string
    {
        return __('reports.audit_disposals_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_disposals_desc');
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
            'reason' => Disposal::REASONS,
            'method' => Disposal::METHODS,
            'status' => Disposal::STATUSES,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_certificate_no'),
            __('reports.col_date'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_quantity'),
            __('reports.col_method'),
            __('reports.col_reason'),
            __('reports.col_performer'),
            __('reports.col_witness'),
            __('reports.col_approved_by'),
            __('reports.col_status'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = Disposal::query()
            ->with(['batch.product', 'performer', 'witness', 'approvedBy'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $disposals = $query->get();

        $rows = [];
        $totQuantity = 0;

        foreach ($disposals as $d) {
            $totQuantity += $d->quantity;

            $rows[] = [
                $d->certificate_no ?? '-',
                $this->formatDate($d->disposed_at ?? $d->created_at),
                $d->batch?->product?->brand ?? 'Unknown',
                $d->batch?->batch_no ?? '-',
                (string) $d->quantity,
                ucfirst(str_replace('_', ' ', $d->method)),
                ucfirst(str_replace('_', ' ', $d->reason)),
                $d->performer?->name ?? '-',
                $d->witness?->name ?? '-',
                $d->approvedBy?->name ?? '-',
                ucfirst($d->status),
            ];
        }

        $summary = [
            'total_disposal_audit_events' => count($rows),
            'total_quantity_disposed' => $totQuantity,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

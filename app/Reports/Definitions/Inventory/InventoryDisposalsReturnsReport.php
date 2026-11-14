<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\Disposal;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Disposals and supplier returns report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2, FR-2.19 @ac AC-29
 */
class InventoryDisposalsReturnsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_disposals_returns';
    }

    public function name(): string
    {
        return __('reports.inventory_disposals_returns_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_disposals_returns_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Owner and PM (management / compliance)
        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'method' => Disposal::METHODS,
            'reason' => Disposal::REASONS,
            'status' => Disposal::STATUSES,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date'),
            __('reports.col_certificate_no'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_quantity'),
            __('reports.col_reason'),
            __('reports.col_method'),
            __('reports.col_supplier_or_collector'),
            __('reports.col_status'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = Disposal::query()
            ->with(['batch.product', 'requestedBy', 'performer', 'witness'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $disposals = $query->get();

        $rows = [];
        $totQuantity = 0;

        foreach ($disposals as $d) {
            $totQuantity += $d->quantity;
            $party = $d->method === Disposal::METHOD_RETURN_TO_SUPPLIER
                ? ($d->supplier_name ?? '-')
                : ($d->collector_name ?? '-');

            $rows[] = [
                $this->formatDate($d->disposed_at ?? $d->created_at),
                $d->certificate_no ?? '-',
                $d->batch?->product?->brand ?? 'Unknown',
                $d->batch?->batch_no ?? '-',
                (string) $d->quantity,
                ucfirst(str_replace('_', ' ', $d->reason)),
                ucfirst(str_replace('_', ' ', $d->method)),
                $party,
                ucfirst($d->status),
            ];
        }

        $summary = [
            'total_disposal_records' => count($rows),
            'total_disposed_quantity' => $totQuantity,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

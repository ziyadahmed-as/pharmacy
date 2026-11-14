<?php

namespace App\Reports\Definitions\Compliance;

use App\Enums\Role;
use App\Models\Disposal;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Disposal compliance and loss report (UC-30).
 * Calculates financial loss by reason, product, and period (FR-2.19).
 *
 * @uc UC-30 @fr FR-7.4, FR-2.19 @ac AC-29
 */
class ComplianceDisposalsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'compliance_disposals';
    }

    public function name(): string
    {
        return __('reports.compliance_disposals_name');
    }

    public function category(): string
    {
        return 'compliance';
    }

    public function description(): string
    {
        return __('reports.compliance_disposals_desc');
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
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_date'),
            __('reports.col_certificate_no'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_quantity'),
            __('reports.col_reason'),
            __('reports.col_method'),
            __('reports.col_performer'),
            __('reports.col_witness'),
        ];

        if ($this->canViewCost($user)) {
            $headers[] = __('reports.col_unit_cost');
            $headers[] = __('reports.col_disposal_loss');
        }

        return $headers;
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $canCost = $this->canViewCost($user);

        $query = Disposal::query()
            ->with(['batch.product', 'performer', 'witness'])
            ->where('status', Disposal::STATUS_APPROVED)
            ->whereDate('disposed_at', '>=', $start)
            ->whereDate('disposed_at', '<=', $end)
            ->orderByDesc('disposed_at');

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        $disposals = $query->get();

        $rows = [];
        $totQuantity = 0;
        $totLoss = 0;
        $lossByReason = [];

        foreach ($disposals as $d) {
            $unitCost = $d->batch?->unit_cost ?? 0;
            $loss = $d->quantity * $unitCost;

            $totQuantity += $d->quantity;
            $totLoss += $loss;

            $lossByReason[$d->reason] = ($lossByReason[$d->reason] ?? 0) + $loss;

            $row = [
                $this->formatDate($d->disposed_at),
                $d->certificate_no ?? '-',
                $d->batch?->product?->brand ?? 'Unknown',
                $d->batch?->batch_no ?? '-',
                (string) $d->quantity,
                ucfirst(str_replace('_', ' ', $d->reason)),
                ucfirst(str_replace('_', ' ', $d->method)),
                $d->performer?->name ?? '-',
                $d->witness?->name ?? '-',
            ];

            if ($canCost) {
                $row[] = $this->formatMoney($unitCost);
                $row[] = $this->formatMoney($loss);
            }

            $rows[] = $row;
        }

        $summary = [
            'total_disposed_events' => count($rows),
            'total_disposed_units' => $totQuantity,
        ];

        if ($canCost) {
            $summary['total_disposal_loss'] = $this->formatMoney($totLoss);
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

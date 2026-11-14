<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\ProductPrice;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Selling price changes audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-1.5 @ac AC-29
 */
class AuditPriceChangesReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_price_changes';
    }

    public function name(): string
    {
        return __('reports.audit_price_changes_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_price_changes_desc');
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
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date'),
            __('reports.col_product'),
            __('reports.col_new_price'),
            __('reports.col_effective_from'),
            __('reports.col_changed_by'),
            __('reports.col_reason'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = ProductPrice::query()
            ->with(['product'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('product', fn ($p) => $p->where('brand', 'like', $term)->orWhere('generic_name', 'like', $term));
        }

        $prices = $query->get();

        $rows = [];
        foreach ($prices as $p) {
            $setter = User::find($p->set_by);

            $rows[] = [
                $this->formatDate($p->created_at),
                $p->product?->brand ?? 'Unknown',
                $this->formatMoney($p->price),
                $this->formatDate($p->effective_from),
                $setter?->name ?? 'User #'.$p->set_by,
                $p->reason ?? '-',
            ];
        }

        $summary = [
            'total_price_changes' => count($rows),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


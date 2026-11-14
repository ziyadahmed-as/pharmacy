<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Stock movements and ledger report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2 @ac AC-29
 */
class InventoryLedgerReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_ledger';
    }

    public function name(): string
    {
        return __('reports.inventory_ledger_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_ledger_desc');
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
            'type' => ['receipt', 'sale', 'adjustment', 'quarantine', 'disposal', 'return'],
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_movement_type'),
            __('reports.col_quantity'),
            __('reports.col_balance_after'),
            __('reports.col_user'),
            __('reports.col_description'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = StockMovement::query()
            ->with(['batch.product', 'user'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('batch', fn ($b) => $b->where('batch_no', 'like', $term))
                    ->orWhereHas('batch.product', fn ($p) => $p->where('brand', 'like', $term)->orWhere('generic_name', 'like', $term));
            });
        }

        $movements = $query->get();

        $rows = [];
        $netQuantity = 0;

        foreach ($movements as $m) {
            $netQuantity += $m->quantity;

            $rows[] = [
                $this->formatDate($m->created_at),
                $m->batch?->product?->brand ?? 'Unknown',
                $m->batch?->batch_no ?? '-',
                ucfirst($m->type),
                ($m->quantity > 0 ? '+'.$m->quantity : (string) $m->quantity),
                (string) $m->balance_after,
                $m->user?->name ?? 'User #'.$m->user_id,
                $m->description ?? '-',
            ];
        }

        $summary = [
            'total_movements' => count($rows),
            'net_quantity_change' => $netQuantity,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

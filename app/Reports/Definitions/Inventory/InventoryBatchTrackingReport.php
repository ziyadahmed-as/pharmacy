<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Batch tracking and traceability report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2 @ac AC-29
 */
class InventoryBatchTrackingReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_batch_tracking';
    }

    public function name(): string
    {
        return __('reports.inventory_batch_tracking_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_batch_tracking_desc');
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
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_batch_no'),
            __('reports.col_product'),
            __('reports.col_expiry_date'),
            __('reports.col_supplier'),
            __('reports.col_total_received'),
            __('reports.col_total_sold'),
            __('reports.col_current_balance'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        $query = Batch::query()
            ->with(['product', 'stocks', 'movements'])
            ->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('batch_no', 'like', $term)
                    ->orWhereHas('product', fn ($p) => $p->where('brand', 'like', $term)->orWhere('generic_name', 'like', $term));
            });
        }

        $batches = $query->limit(200)->get();

        $rows = [];
        $totBalance = 0;

        foreach ($batches as $batch) {
            $received = (int) $batch->movements->where('type', 'receipt')->sum('quantity');
            $sold = abs((int) $batch->movements->where('type', 'sale')->sum('quantity'));
            $balance = (int) $batch->stocks->sum('quantity');

            $totBalance += $balance;

            $rows[] = [
                $batch->batch_no,
                $batch->product?->brand ?? 'Unknown',
                $this->formatDate($batch->expiry_date, 'Y-m-d'),
                $batch->supplier_name ?? '-',
                (string) $received,
                (string) $sold,
                (string) $balance,
            ];
        }

        $summary = [
            'total_batches' => count($rows),
            'total_stock_balance' => $totBalance,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

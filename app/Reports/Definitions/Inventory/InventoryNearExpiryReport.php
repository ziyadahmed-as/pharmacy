<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Near-expiry stock report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2, FR-2.6 @ac AC-29
 */
class InventoryNearExpiryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_near_expiry';
    }

    public function name(): string
    {
        return __('reports.inventory_near_expiry_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_near_expiry_desc');
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
            'threshold_days' => [30, 60, 90, 180],
            'category_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_expiry_date'),
            __('reports.col_days_to_expiry'),
            __('reports.col_sellable_qty'),
            __('reports.col_supplier'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        $days = (int) ($filters['threshold_days'] ?? setting('inventory.expiry_alert_days', 90));
        $cutoffDate = now()->addDays($days)->toDateString();
        $today = now()->toDateString();

        $query = Batch::query()
            ->with(['product.category', 'stocks'])
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $cutoffDate)
            ->whereHas('stocks', fn ($q) => $q->where('status', 'sellable')->where('quantity', '>', 0))
            ->orderBy('expiry_date');

        if (! empty($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        $batches = $query->get();

        $rows = [];
        $totQuantity = 0;

        foreach ($batches as $batch) {
            $sellableQty = (int) $batch->stocks->where('status', 'sellable')->sum('quantity');
            $daysLeft = now()->diffInDays($batch->expiry_date, false);

            $totQuantity += $sellableQty;

            $rows[] = [
                $batch->product?->brand ?? 'Unknown',
                $batch->batch_no,
                $this->formatDate($batch->expiry_date, 'Y-m-d'),
                (string) max(0, $daysLeft),
                (string) $sellableQty,
                $batch->supplier_name ?? '-',
            ];
        }

        $summary = [
            'near_expiry_batches_count' => count($rows),
            'total_near_expiry_units' => $totQuantity,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use App\Services\ReorderListQuery;

/**
 * Low stock & reorder recommendations report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2, FR-2.7 @ac AC-29
 */
class InventoryLowStockReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_low_stock';
    }

    public function name(): string
    {
        return __('reports.inventory_low_stock_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_low_stock_desc');
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
            'category_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_product'),
            __('reports.col_generic_name'),
            __('reports.col_category'),
            __('reports.col_on_hand'),
            __('reports.col_reorder_level'),
            __('reports.col_suggested_reorder_qty'),
            __('reports.col_critical_shortage'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        $items = ReorderListQuery::get();

        if (! empty($filters['category_id'])) {
            $items = $items->filter(fn ($item) => $item['product']->category_id == $filters['category_id']);
        }

        $rows = [];
        $criticalCount = 0;

        foreach ($items as $item) {
            $product = $item['product'];
            if ($item['is_critical']) {
                $criticalCount++;
            }

            $rows[] = [
                $product->brand,
                $product->generic_name ?? '-',
                $product->category?->name ?? '-',
                (string) $item['on_hand'],
                (string) $item['reorder_level'],
                (string) $item['suggested_qty'],
                $item['is_critical'] ? __('reports.yes') : __('reports.no'),
            ];
        }

        $summary = [
            'total_items_to_reorder' => count($rows),
            'critical_shortages_count' => $criticalCount,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}

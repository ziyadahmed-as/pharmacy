<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\BatchStock;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Current stock on hand report (UC-30).
 * Unit cost and valuation hidden from non-authorized users (BR-06).
 *
 * @uc UC-30 @fr FR-7.2 @ac AC-29
 */
class InventoryCurrentStockReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_current_stock';
    }

    public function name(): string
    {
        return __('reports.inventory_current_stock_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_current_stock_desc');
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
            'status' => ['sellable', 'quarantine'],
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        $headers = [
            __('reports.col_product'),
            __('reports.col_generic_name'),
            __('reports.col_category'),
            __('reports.col_barcode'),
            __('reports.col_batch_no'),
            __('reports.col_expiry_date'),
            __('reports.col_location'),
            __('reports.col_status'),
            __('reports.col_quantity'),
        ];

        if ($this->canViewCost($user)) {
            $headers[] = __('reports.col_unit_cost');
            $headers[] = __('reports.col_valuation');
        }

        return $headers;
    }

    public function query(array $filters, User $user): array
    {
        $canCost = $this->canViewCost($user);

        $query = BatchStock::query()
            ->with(['batch.product.category'])
            ->where('quantity', '>', 0);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('batch.product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('batch', fn ($b) => $b->where('batch_no', 'like', $term))
                    ->orWhereHas('batch.product', function ($p) use ($term) {
                        $p->where('brand', 'like', $term)
                            ->orWhere('generic_name', 'like', $term)
                            ->orWhere('barcode', 'like', $term);
                    });
            });
        }

        $stocks = $query->get();

        $rows = [];
        $totQuantity = 0;
        $totValuation = 0;

        foreach ($stocks as $stock) {
            $batch = $stock->batch;
            $product = $batch?->product;

            $qty = $stock->quantity;
            $totQuantity += $qty;

            $unitCost = $batch?->unit_cost;
            $valuation = ($unitCost !== null && $stock->status === 'sellable') ? ($qty * $unitCost) : 0;
            $totValuation += $valuation;

            $row = [
                $product?->brand ?? 'Unknown',
                $product?->generic_name ?? '-',
                $product?->category?->name ?? '-',
                $product?->barcode ?? '-',
                $batch?->batch_no ?? '-',
                $this->formatDate($batch?->expiry_date, 'Y-m-d'),
                $stock->location ?? '-',
                $stock->status,
                (string) $qty,
            ];

            if ($canCost) {
                $row[] = $this->formatMoney($unitCost);
                $row[] = $this->formatMoney($valuation);
            }

            $rows[] = $row;
        }

        $summary = [
            'total_items_count' => count($rows),
            'total_stock_quantity' => $totQuantity,
        ];

        if ($canCost) {
            $summary['total_valuation'] = $this->formatMoney($totValuation);
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


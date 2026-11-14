<?php

namespace App\Reports\Definitions\Inventory;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Expired stock report (UC-30).
 *
 * @uc UC-30 @fr FR-7.2, FR-2.6 @ac AC-29
 */
class InventoryExpiredReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'inventory_expired';
    }

    public function name(): string
    {
        return __('reports.inventory_expired_name');
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function description(): string
    {
        return __('reports.inventory_expired_desc');
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
        $headers = [
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_expiry_date'),
            __('reports.col_days_expired'),
            __('reports.col_stock_status'),
            __('reports.col_quantity'),
        ];

        if ($this->canViewCost($user)) {
            $headers[] = __('reports.col_unit_cost');
            $headers[] = __('reports.col_expired_loss_value');
        }

        return $headers;
    }

    public function query(array $filters, User $user): array
    {
        $today = now()->toDateString();
        $canCost = $this->canViewCost($user);

        $query = Batch::query()
            ->with(['product.category', 'stocks'])
            ->whereDate('expiry_date', '<', $today)
            ->whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0))
            ->orderBy('expiry_date');

        if (! empty($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        $batches = $query->get();

        $rows = [];
        $totQuantity = 0;
        $totLoss = 0;

        foreach ($batches as $batch) {
            $daysExpired = now()->diffInDays($batch->expiry_date, true);
            $unitCost = $batch->unit_cost ?? 0;

            foreach ($batch->stocks as $stock) {
                if ($stock->quantity <= 0) {
                    continue;
                }

                $qty = $stock->quantity;
                $loss = $qty * $unitCost;

                $totQuantity += $qty;
                $totLoss += $loss;

                $row = [
                    $batch->product?->brand ?? 'Unknown',
                    $batch->batch_no,
                    $this->formatDate($batch->expiry_date, 'Y-m-d'),
                    (string) $daysExpired,
                    $stock->status,
                    (string) $qty,
                ];

                if ($canCost) {
                    $row[] = $this->formatMoney($unitCost);
                    $row[] = $this->formatMoney($loss);
                }

                $rows[] = $row;
            }
        }

        $summary = [
            'total_expired_records' => count($rows),
            'total_expired_units' => $totQuantity,
        ];

        if ($canCost) {
            $summary['total_expired_loss'] = $this->formatMoney($totLoss);
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}


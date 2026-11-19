<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating reorder recommendations (UC-05 Step 4).
 * Items at or below reorder level with suggested quantities (max minus on hand).
 *
 * @uc UC-05 @fr FR-2.7, D-06
 */
class ReorderListQuery
{
    /**
     * @return Collection<int, array{
     *     product: Product,
     *     on_hand: int,
     *     reorder_level: int,
     *     max_level: ?int,
     *     min_level: ?int,
     *     suggested_qty: int,
     *     preferred_supplier: string,
     *     is_critical: bool
     * }>
     */
    public static function get(): Collection
    {
        $products = Product::query()
            ->with(['category'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('reorder_level', '>', 0)
                    ->orWhere('min_level', '>', 0);
            })
            ->orderBy('brand')
            ->get();

        $results = collect();

        foreach ($products as $product) {
            // quantity_on_hand is the SSOT: sum across all non-expired SELLABLE batches.
            $sellableStock = (int) DB::table('batches')
                ->where('product_id', $product->id)
                ->where('expiry_date', '>', now()->toDateString())
                ->where('status', Batch::STATUS_SELLABLE)
                ->sum('quantity_on_hand');

            $reorderLevel = (int) ($product->reorder_level ?? 0);
            $minLevel = $product->min_level > 0 ? (int) $product->min_level : null;
            $maxLevel = $product->max_level > 0 ? (int) $product->max_level : null;

            if ($reorderLevel > 0 && $sellableStock <= $reorderLevel) {
                // Suggested quantity: maximum minus on hand
                $suggested = $maxLevel !== null && $maxLevel > $sellableStock
                    ? ($maxLevel - $sellableStock)
                    : max(1, ($reorderLevel * 2) - $sellableStock);

                $isCritical = $minLevel !== null && $minLevel > 0 && $sellableStock < $minLevel;

                $results->push([
                    'product' => $product,
                    'on_hand' => $sellableStock,
                    'reorder_level' => $reorderLevel,
                    'max_level' => $maxLevel,
                    'min_level' => $minLevel,
                    'suggested_qty' => $suggested,
                    'preferred_supplier' => $product->preferred_supplier_name ?: __('catalogue.no_preferred_supplier'),
                    'is_critical' => $isCritical,
                ]);
            }
        }

        return $results;
    }
}

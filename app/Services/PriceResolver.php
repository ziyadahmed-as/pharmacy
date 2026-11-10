<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use Carbon\CarbonInterface;

/**
 * Returns the latest price effective at a given time.
 *
 * @uc UC-02 @fr FR-1.5 @br BR-20 @ac AC-17
 */
class PriceResolver
{
    /**
     * Resolve selling price for a product and optional unit at a specific timestamp.
     */
    public static function at(Product $product, ?ProductUnit $unit = null, ?CarbonInterface $at = null): ?ProductPrice
    {
        $at = $at ?? now();

        $query = ProductPrice::where('product_id', $product->id)
            ->where('effective_from', '<=', $at);

        if ($unit) {
            $query->where('unit_id', $unit->id);
        } else {
            $query->whereNull('unit_id');
        }

        return $query->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}

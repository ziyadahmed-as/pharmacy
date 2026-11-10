<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PriceResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Set a selling price for a product.
 *
 * @uc UC-02 @fr FR-1.5 @br BR-20 @ac AC-16, AC-17
 */
class SetSellingPrice
{
    /**
     * @param Product $product
     * @param int $price Price in minor units (e.g. santim)
     * @param CarbonInterface $effectiveFrom
     * @param string $reason
     * @param ProductUnit|null $unit
     * @param User|null $user
     * @return ProductPrice
     * @throws ValidationException
     */
    public function execute(
        Product $product,
        int $price,
        CarbonInterface $effectiveFrom,
        string $reason,
        ?ProductUnit $unit = null,
        ?User $user = null
    ): ProductPrice {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('price.set')) {
            throw ValidationException::withMessages([
                'price' => __('catalogue.unauthorized_price_change'),
            ]);
        }

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'price' => __('catalogue.price_must_be_positive'),
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('catalogue.price_reason_required'),
            ]);
        }

        // Get old price at that effective moment
        $oldPrice = PriceResolver::at($product, $unit, $effectiveFrom);
        $oldAmount = $oldPrice ? $oldPrice->price : null;

        $newPriceRecord = ProductPrice::create([
            'product_id' => $product->id,
            'unit_id' => $unit?->id,
            'price' => $price,
            'effective_from' => $effectiveFrom,
            'reason' => $reason,
            'set_by' => $actor->id,
            'created_at' => now(),
        ]);

        AuditLogger::record(
            'price.changed',
            $product,
            ['price' => $oldAmount],
            ['price' => $price, 'effective_from' => $effectiveFrom->toIso8601String(), 'reason' => $reason, 'unit_id' => $unit?->id],
            ['product_id' => $product->id, 'brand' => $product->brand],
            $actor
        );

        return $newPriceRecord;
    }
}


<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Deactivates a product, keeping all history but blocking from future sale and receipt (A2).
 *
 * @uc UC-01 @fr FR-1.4
 */
class DeactivateProduct
{
    public function execute(Product $product, ?string $reason = null, ?User $user = null): Product
    {
        $actor = $user ?? Auth::user();

        // Staff cannot deactivate (E2); only PM and Owner
        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'product' => __('catalogue.unauthorized_deactivate'),
            ]);
        }

        if (! $product->is_active) {
            return $product;
        }

        $product->is_active = false;
        $product->save();

        AuditLogger::record(
            'catalogue.product.deactivated',
            $product,
            ['is_active' => true],
            ['is_active' => false, 'reason' => $reason],
            ['brand' => $product->brand],
            $actor
        );

        return $product;
    }
}

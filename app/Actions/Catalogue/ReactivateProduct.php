<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Reactivates a deactivated product.
 *
 * @uc UC-01 @fr FR-1.4
 */
class ReactivateProduct
{
    public function execute(Product $product, ?string $reason = null, ?User $user = null): Product
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'product' => __('catalogue.unauthorized_reactivate'),
            ]);
        }

        if ($product->is_active) {
            return $product;
        }

        $product->is_active = true;
        $product->save();

        AuditLogger::record(
            'catalogue.product.reactivated',
            $product,
            ['is_active' => false],
            ['is_active' => true, 'reason' => $reason],
            ['brand' => $product->brand],
            $actor
        );

        return $product;
    }
}

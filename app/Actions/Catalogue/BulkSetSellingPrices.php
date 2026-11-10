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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk set selling prices (used by importers / bulk updates).
 *
 * @uc UC-02 @fr FR-1.5 @br BR-20
 */
class BulkSetSellingPrices
{
    /**
     * @param array<int, array{product: Product, price: int, effective_from: CarbonInterface, reason: string, unit?: ProductUnit|null}> $items
     * @param User|null $user
     * @return array<int, ProductPrice>
     * @throws ValidationException
     */
    public function execute(array $items, ?User $user = null): array
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('price.set')) {
            throw ValidationException::withMessages([
                'price' => __('catalogue.unauthorized_price_change'),
            ]);
        }

        $results = [];

        DB::transaction(function () use ($items, $actor, &$results) {
            $setter = new SetSellingPrice();
            foreach ($items as $item) {
                $results[] = $setter->execute(
                    $item['product'],
                    $item['price'],
                    $item['effective_from'],
                    $item['reason'],
                    $item['unit'] ?? null,
                    $actor
                );
            }
        });

        return $results;
    }
}


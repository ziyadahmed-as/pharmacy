<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic or brand substitutes for a medicine (DDD 5.3).
 *
 * @property int $id
 * @property int $product_id
 * @property int $substitute_product_id
 * @property string|null $note
 * @property-read Product $product
 * @property-read Product $substitute
 *
 * @uc UC-13
 */
class ProductSubstitute extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'substitute_product_id');
    }

    /**
     * Suggest available substitutes with on-hand sellable stock.
     *
     * @return Collection<int, Product>
     */
    public static function suggest(Product|int $product): Collection
    {
        $id = $product instanceof Product ? $product->id : $product;

        $substituteIds = self::where('product_id', $id)
            ->pluck('substitute_product_id');

        return Product::query()
            ->whereIn('id', $substituteIds)
            ->where('is_active', true)
            ->with(['latestPrice', 'batchStocks'])
            ->get();
    }
}

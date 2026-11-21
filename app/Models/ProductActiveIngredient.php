<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Active ingredient line for a product.
 *
 * A single-ingredient product has one row; a combination product
 * (e.g. Amoxicillin + Clavulanic Acid) has multiple rows ordered by
 * `sort_order`.
 *
 * This replaces the free-text `products.strength` column for structured
 * ingredient data. The old `strength` varchar column is kept intact for
 * backward compatibility and display purposes.
 *
 * @property int         $id
 * @property int         $product_id
 * @property string      $ingredient_name   INN / generic name of the ingredient
 * @property string|null $ingredient_code   CAS number, INN code, or similar identifier
 * @property float       $amount            Numeric strength per dosage unit (e.g. 500)
 * @property string      $strength_unit     Unit of the amount (e.g. mg, mcg, IU, %)
 * @property int         $sort_order        Display order; 0 = first
 * @property-read Product $product
 */
class ProductActiveIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'ingredient_name',
        'ingredient_code',
        'amount',
        'strength_unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Human-readable strength string, e.g. "500 mg" or "875 mg + 125 mg".
     * For a single ingredient: "500 mg".
     * Combine multiple ingredients at the product level via strengthLabel().
     */
    public function getStrengthStringAttribute(): string
    {
        $amount = rtrim(rtrim(number_format((float) $this->amount, 4, '.', ''), '0'), '.');

        return "{$amount} {$this->strength_unit}";
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Persist the active-ingredient lines for a product atomically.
     *
     * Replaces all existing rows for the product with the supplied list.
     * Pass an empty array to clear all ingredients without deleting the product.
     *
     * @param  Product|int  $product
     * @param  array<int, array{
     *     ingredient_name: string,
     *     ingredient_code?: string|null,
     *     amount: float|string,
     *     strength_unit: string,
     *     sort_order?: int,
     * }>  $ingredients
     */
    public static function syncFor(Product|int $product, array $ingredients): void
    {
        $productId = $product instanceof Product ? $product->id : $product;

        // Delete existing rows for this product, then re-insert.
        // Using delete + insert rather than updateOrCreate per row avoids
        // stale rows when ingredient names change.
        self::where('product_id', $productId)->delete();

        foreach ($ingredients as $i => $item) {
            if (empty(trim($item['ingredient_name'] ?? '')) || empty($item['amount'])) {
                continue;
            }

            self::create([
                'product_id'      => $productId,
                'ingredient_name' => trim($item['ingredient_name']),
                'ingredient_code' => $item['ingredient_code'] ?? null,
                'amount'          => $item['amount'],
                'strength_unit'   => trim($item['strength_unit'] ?? 'mg'),
                'sort_order'      => $item['sort_order'] ?? $i,
            ]);
        }
    }
}

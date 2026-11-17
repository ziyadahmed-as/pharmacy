<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Product master record (DDD 5.3).
 *
 * @property int $id
 * @property string|null $product_code
 * @property string $brand
 * @property string $generic_name
 * @property string|null $strength
 * @property string|null $dosage_form
 * @property string|null $pack_size
 * @property string $unit
 * @property string|null $manufacturer
 * @property int|null $category_id
 * @property string|null $barcode
 * @property string|null $storage_condition
 * @property bool $is_cold_chain
 * @property string $classification
 * @property bool $loose_allowed
 * @property int $reorder_level
 * @property int $max_level
 * @property int $min_level
 * @property int|null $min_shelf_life_days
 * @property string|null $preferred_supplier_name
 * @property bool $is_active
 * @property string|null $image_path          Path on the 'private' disk; null until an image is uploaded.
 * @property int|null    $selling_price       Current selling price in minor units (santim). Null = not priced.
 * @property-read Category|null $category
 * @property-read DrugInformation|null $drugInformation
 * @property-read Collection<int, ProductUnit> $units
 * @property-read Collection<int, ProductPrice> $prices
 * @property-read Collection<int, Batch> $batches
 * @property-read Collection<int, BatchStock> $batchStocks
 * @property-read Collection<int, Product> $substitutes
 * @property-read ProductPrice|null $latestPrice
 *
 * @uc UC-01, UC-03
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'brand',
        'generic_name',
        'strength',
        'dosage_form',
        'pack_size',
        'unit',
        'manufacturer',
        'category_id',
        'barcode',
        'storage_condition',
        'is_cold_chain',
        'classification',
        'loose_allowed',
        'reorder_level',
        'max_level',
        'min_level',
        'min_shelf_life_days',
        'preferred_supplier_name',
        'is_active',
        'image_path',
        'selling_price',
        'thumbnail_path',
    ];

    protected function casts(): array
    {
        return [
            'is_cold_chain'    => 'boolean',
            'loose_allowed'    => 'boolean',
            'is_active'        => 'boolean',
            'reorder_level'    => 'integer',
            'max_level'        => 'integer',
            'min_level'        => 'integer',
            'min_shelf_life_days' => 'integer',
            'selling_price'    => 'integer',   // stored in minor units (santim)
        ];
    }

    // -------------------------------------------------------------------------
    // Stock helpers
    // -------------------------------------------------------------------------

    /**
     * Total available quantity = sum of sellable, non-expired batch quantities.
     *
     * Computed from child rows every time it is called; never stored separately
     * so it cannot drift out of sync with the ledger (FR-1.1).
     *
     * @fr FR-1.1
     */
    public function totalAvailableQuantity(): int
    {
        return (int) $this->batches()
            ->where('expiry_date', '>', now())
            ->whereIn('batches.status', ['SELLABLE'])
            ->withSum([
                'stocks as sellable_qty' => fn ($q) => $q->where('batch_stocks.status', 'sellable'),
            ], 'quantity')
            ->get()
            ->sum('sellable_qty');
    }

    // -------------------------------------------------------------------------
    // Cost / price visibility (BR-06)
    // -------------------------------------------------------------------------

    /**
     * Remove buying-price fields from the serialised array when the current
     * acting user holds only the PharmacyStaff role.
     *
     * Called automatically by toArray() / toJson() (Eloquent hook).
     * Server-side enforcement: even if a view mistakenly outputs $product,
     * unit_cost and selling_price will not be present for PP users.
     *
     * NOTE: selling_price here is the *denormalised* catalogue price set by OW/PM.
     * It differs from the per-sale line price which PS must see at the till.
     * The POS resolves price through PriceResolver, not this column.
     */
    protected function serializeForPs(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // If the user holds any role with cost.view the full model is returned.
        if ($user->hasPermission('cost.view')) {
            return false;
        }

        return true;  // PS: strip cost fields
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function drugInformation(): HasOne
    {
        return $this->hasOne(DrugInformation::class);
    }

    public function substitutes(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_substitutes',
            'product_id',
            'substitute_product_id'
        )->withPivot('note')->withTimestamps();
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function batchStocks(): HasManyThrough
    {
        return $this->hasManyThrough(BatchStock::class, Batch::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->latestOfMany('effective_from');
    }

    // -------------------------------------------------------------------------
    // Image accessor
    // -------------------------------------------------------------------------

    /**
     * Public thumbnail URL — returns the placeholder when no image is set.
     */
    public function thumbnailUrl(): string
    {
        return \App\Services\ProductImageService::thumbnailUrl($this->thumbnail_path ?? null);
    }

    // -------------------------------------------------------------------------
    // Serialisation — hide buying-price fields from PP (BR-06)
    // -------------------------------------------------------------------------

    /**
     * When converted to array/JSON, strip `selling_price` for users without
     * `cost.view`. The POS reads price through PriceResolver, not this column,
     * so PS can still sell; they just never see the catalogue cost/price column.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->serializeForPs()) {
            unset($array['selling_price']);
        }

        return $array;
    }
}

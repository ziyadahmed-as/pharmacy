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
    ];

    protected function casts(): array
    {
        return [
            'is_cold_chain' => 'boolean',
            'loose_allowed' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'integer',
            'max_level' => 'integer',
            'min_level' => 'integer',
            'min_shelf_life_days' => 'integer',
        ];
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
}

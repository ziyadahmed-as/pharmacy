<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
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
        'classification',
        'loose_allowed',
        'reorder_level',
        'max_level',
        'min_level',
        'preferred_supplier_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'loose_allowed' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'integer',
            'max_level' => 'integer',
            'min_level' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->latestOfMany('effective_from');
    }
}

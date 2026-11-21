<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Manufacturer master record.
 *
 * Replaces the free-text `products.manufacturer` column with a structured
 * entity. The old text column is kept intact; this model is linked via
 * `products.manufacturer_id` (nullable FK).
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $short_name
 * @property string|null $country
 * @property string|null $website
 * @property string|null $address
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property bool        $is_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'country',
        'website',
        'address',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Display label: short_name when available, otherwise full name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?? $this->name;
    }
}

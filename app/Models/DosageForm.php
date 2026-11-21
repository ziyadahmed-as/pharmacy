<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dosage form lookup (e.g. Tablet, Capsule, Syrup).
 *
 * Replaces the free-text `products.dosage_form` column with a managed list.
 * The old text column is kept intact; products link via `products.dosage_form_id`.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $abbreviation
 * @property string|null $route_hint
 * @property bool        $is_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class DosageForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
        'route_hint',
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
     * Display label: "Tablet (TAB)" when abbreviation is present, otherwise just name.
     */
    public function getLabelAttribute(): string
    {
        return $this->abbreviation
            ? "{$this->name} ({$this->abbreviation})"
            : $this->name;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Route of administration lookup (e.g. Oral, Intravenous, Topical).
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $abbreviation
 * @property string|null $description
 * @property bool        $is_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class RouteOfAdministration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
        'description',
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
        return $this->hasMany(Product::class, 'route_of_administration_id');
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
     * Display label: "Oral (PO)" when abbreviation is present, otherwise just name.
     */
    public function getLabelAttribute(): string
    {
        return $this->abbreviation
            ? "{$this->name} ({$this->abbreviation})"
            : $this->name;
    }
}

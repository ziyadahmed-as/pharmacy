<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Therapeutic class using the WHO ATC classification structure.
 *
 * Supports one level of parent–child nesting (ATC level 1 → level 2).
 * The existing `categories` table is kept separate — categories represent
 * business/dispensary organisation while therapeutic classes represent
 * clinical ATC groupings.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $atc_code    WHO ATC code (e.g. "J01", "N02A")
 * @property int|null    $parent_id
 * @property string|null $description
 * @property bool        $is_active
 * @property-read TherapeuticClass|null                            $parent
 * @property-read Collection<int, TherapeuticClass>               $children
 * @property-read Collection<int, Product>                        $products
 */
class TherapeuticClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'atc_code',
        'parent_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'therapeutic_class_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Only top-level (ATC level 1) classes. */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Full display label including ATC code when present.
     * e.g. "J01 — Antibacterials for Systemic Use"
     */
    public function getLabelAttribute(): string
    {
        return $this->atc_code
            ? "{$this->atc_code} — {$this->name}"
            : $this->name;
    }
}

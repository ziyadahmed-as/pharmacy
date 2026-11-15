<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $licence_number
 * @property string|null $facility
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Prescriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'licence_number',
        'facility',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function controlledDrugRegisterEntries(): HasMany
    {
        return $this->hasMany(ControlledDrugRegisterEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $clean = trim($term);
        if ($clean === '') {
            return $query;
        }

        $like = "%{$clean}%";

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('licence_number', 'like', $like)
                ->orWhere('facility', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    public function displayText(): string
    {
        return $this->licence_number
            ? "{$this->name} ({$this->licence_number})"
            : $this->name;
    }
}

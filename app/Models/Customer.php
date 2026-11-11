<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer profile with encrypted health records and audit trail.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $address
 * @property CarbonInterface|null $birth_date
 * @property bool $consent_given
 * @property CarbonInterface|null $consent_at
 * @property string|null $allergies (encrypted)
 * @property string|null $chronic_conditions (encrypted)
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, CustomerHealthView> $healthViews
 * @property-read Collection<int, Sale> $sales
 *
 * @uc UC-25
 *
 * @fr FR-5.1, FR-5.2, FR-5.6
 *
 * @ac AC-26
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'birth_date',
        'consent_given',
        'consent_at',
        'allergies',
        'chronic_conditions',
    ];

    protected function casts(): array
    {
        return [
            'consent_given' => 'boolean',
            'consent_at' => 'datetime',
            'birth_date' => 'date',
            'allergies' => 'encrypted',
            'chronic_conditions' => 'encrypted',
        ];
    }

    public function healthViews(): HasMany
    {
        return $this->hasMany(CustomerHealthView::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    public function hasConsent(): bool
    {
        return (bool) $this->consent_given;
    }
}

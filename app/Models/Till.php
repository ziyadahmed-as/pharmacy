<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property-read Collection<int, Shift> $shifts
 * @property-read Shift|null $activeShift
 */
class Till extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * All shifts for this till.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Current open shift on this till, if any.
     */
    public function activeShift(): HasOne
    {
        return $this->hasOne(Shift::class)->where('status', 'open');
    }

    /**
     * Check if this till currently has an open shift.
     */
    public function hasOpenShift(): bool
    {
        return $this->activeShift()->exists();
    }
}

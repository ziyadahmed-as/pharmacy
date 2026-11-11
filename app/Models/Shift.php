<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property int $till_id
 * @property int $user_id
 * @property CarbonInterface $opened_at
 * @property int $opening_float
 * @property string $status
 * @property CarbonInterface|null $closed_at
 * @property array<string, mixed>|null $expected_cash_per_payment_method
 * @property array<string, mixed>|null $counted_cash_per_payment_method
 * @property int|null $variance
 * @property string|null $variance_reason
 * @property-read Till $till
 * @property-read User $user
 */
class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'till_id',
        'user_id',
        'opened_at',
        'opening_float',
        'status',
        'closed_at',
        'expected_cash_per_payment_method',
        'counted_cash_per_payment_method',
        'variance',
        'variance_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'variance' => 'integer',
            'expected_cash_per_payment_method' => 'array',
            'counted_cash_per_payment_method' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Shift $shift) {
            if ($shift->getOriginal('status') === 'closed') {
                throw new RuntimeException(__('shifts.closed_shift_immutable'));
            }
        });

        static::deleting(function () {
            throw new RuntimeException(__('shifts.shifts_cannot_be_deleted'));
        });
    }

    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class);
    }

    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function openingFloatEtb(): float
    {
        return $this->opening_float / 100;
    }

    public function varianceEtb(): ?string
    {
        if ($this->variance === null) {
            return null;
        }

        return number_format($this->variance / 100, 2);
    }
}

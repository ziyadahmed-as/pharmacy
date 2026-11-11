<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Daily accounting closing record that locks sales, returns, voids and shifts for a date.
 *
 * @property int $id
 * @property string $date
 * @property string $status
 * @property array<string, int> $system_totals_by_method
 * @property array<string, int> $counted_totals_by_method
 * @property array<string, int> $variances_by_method
 * @property array<string, string>|null $variance_reasons
 * @property int $closed_by
 * @property int|null $verified_by
 * @property CarbonInterface|null $locked_at
 * @property string|null $notes
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read User $closer
 * @property-read User|null $verifier
 */
class DailyClosing extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'date',
        'status',
        'system_totals_by_method',
        'counted_totals_by_method',
        'variances_by_method',
        'variance_reasons',
        'closed_by',
        'verified_by',
        'locked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_totals_by_method' => 'array',
            'counted_totals_by_method' => 'array',
            'variances_by_method' => 'array',
            'variance_reasons' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (DailyClosing $closing) {
            if ($closing->getOriginal('status') === self::STATUS_LOCKED) {
                throw new RuntimeException(__('closing.locked_day_immutable'));
            }
        });

        static::deleting(function () {
            throw new RuntimeException(__('closing.daily_closings_cannot_be_deleted'));
        });
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public static function isLockedForDate(string|CarbonInterface $date): bool
    {
        $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return static::query()
            ->where('date', $dateStr)
            ->where('status', self::STATUS_LOCKED)
            ->exists();
    }
}

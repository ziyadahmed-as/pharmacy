<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Held sale transaction stored without reserving stock.
 *
 * @property int $id
 * @property int $user_id
 * @property int $shift_id
 * @property string|null $reference
 * @property array<string, mixed> $cart
 * @property int $total
 * @property string $status
 * @property Carbon $held_at
 * @property Carbon|null $resumed_at
 * @property Carbon|null $discarded_at
 * @property string|null $discard_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HeldSale extends Model
{
    use HasFactory;

    public const STATUS_HELD = 'held';

    public const STATUS_RESUMED = 'resumed';

    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'user_id',
        'shift_id',
        'reference',
        'cart',
        'total',
        'status',
        'held_at',
        'resumed_at',
        'discarded_at',
        'discard_reason',
    ];

    protected function casts(): array
    {
        return [
            'cart' => 'array',
            'total' => 'integer',
            'held_at' => 'datetime',
            'resumed_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isHeld(): bool
    {
        return $this->status === self::STATUS_HELD;
    }

    public function isResumed(): bool
    {
        return $this->status === self::STATUS_RESUMED;
    }

    public function isDiscarded(): bool
    {
        return $this->status === self::STATUS_DISCARDED;
    }

    public function totalEtb(): string
    {
        return number_format($this->total / 100, 2);
    }

    public function itemsCount(): int
    {
        return is_array($this->cart) ? count($this->cart) : 0;
    }

    /**
     * Scope to currently open held sales.
     */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HELD);
    }

    /**
     * Scope for a user: staff only see their own held sales (AC-23),
     * managers and owners can see all held sales.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['pharmacy_manager', 'owner'])) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}

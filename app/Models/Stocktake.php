<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stocktake session (UC-07).
 *
 * @property int $id
 * @property string $type  full|partial
 * @property string|null $scope_type  location|category
 * @property string|null $scope_value
 * @property string $status  draft|counting|review|approved|rejected
 * @property int $created_by
 * @property int|null $approved_by
 * @property \Carbon\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Stocktake extends Model
{
    public const TYPE_FULL = 'full';

    public const TYPE_PARTIAL = 'partial';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COUNTING = 'counting';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'scope_type',
        'scope_value',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class);
    }

    // ── Status helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCounting(): bool
    {
        return $this->status === self::STATUS_COUNTING;
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_COUNTING, self::STATUS_REVIEW], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_COUNTING, self::STATUS_REVIEW]);
    }

    // ── Computed helpers ─────────────────────────────────────────────────────

    public function totalVarianceItems(): int
    {
        return $this->lines()->whereNotNull('variance')->where('variance', '!=', 0)->count();
    }

    public function allLinesCounted(): bool
    {
        return $this->lines()->whereNull('counted_qty')->doesntExist();
    }
}


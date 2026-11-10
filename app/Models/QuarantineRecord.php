<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantineRecord extends Model
{
    use HasFactory;

    public const REASON_EXPIRED = 'expired';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_RECALLED = 'recalled';

    public const REASON_CONTAMINATED = 'contaminated';

    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_EXPIRED,
        self::REASON_DAMAGED,
        self::REASON_RECALLED,
        self::REASON_CONTAMINATED,
        self::REASON_OTHER,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_RELEASED = 'released';

    public const STATUS_DISPOSED = 'disposed';

    public const STATUS_RETURNED = 'returned';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_RELEASED,
        self::STATUS_DISPOSED,
        self::STATUS_RETURNED,
    ];

    protected $fillable = [
        'batch_id',
        'quantity',
        'reason',
        'status',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isRecalled(): bool
    {
        return $this->reason === self::REASON_RECALLED;
    }
}

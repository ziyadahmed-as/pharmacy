<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $refund_no
 * @property int $sale_id
 * @property int $user_id
 * @property int $shift_id
 * @property string $status
 * @property int $total_refund
 * @property string $payment_method
 * @property string|null $reason
 * @property bool $requires_approval
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Sale $sale
 * @property-read User $user
 * @property-read Shift $shift
 * @property-read Collection<int, SaleReturnLine> $lines
 */
class SaleReturn extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'refund_no',
        'sale_id',
        'user_id',
        'shift_id',
        'status',
        'total_refund',
        'payment_method',
        'reason',
        'requires_approval',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_refund' => 'integer',
            'requires_approval' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function totalRefundEtb(): string
    {
        return number_format($this->total_refund / 100, 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Adjustment request for damaged, lost, expired, or found stock.
 *
 * @property int $id
 * @property int $batch_id
 * @property string $reason_type
 * @property int $quantity
 * @property string $written_reason
 * @property string $status
 * @property int $requested_by
 * @property int|null $second_person_id
 * @property int|null $approval_request_id
 * @property bool $recount_done
 * @property int|null $recounted_by
 * @property Carbon|null $recounted_at
 * @property string|null $recount_notes
 * @property-read Batch $batch
 * @property-read User $requester
 * @property-read User|null $secondPerson
 * @property-read User|null $recounter
 * @property-read ApprovalRequest|null $approvalRequest
 *
 * @uc UC-06
 *
 * @fr FR-2.8
 *
 * @br BR-07, BR-14
 *
 * @ac AC-08
 */
class AdjustmentRequest extends Model
{
    use HasFactory;

    public const REASON_DAMAGE = 'damage';

    public const REASON_LOSS = 'loss';

    public const REASON_EXPIRY_WRITE_OFF = 'expiry_write_off';

    public const REASON_FOUND_STOCK = 'found_stock';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'batch_id',
        'reason_type',
        'quantity',
        'written_reason',
        'status',
        'requested_by',
        'second_person_id',
        'approval_request_id',
        'recount_done',
        'recounted_by',
        'recounted_at',
        'recount_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recount_done' => 'boolean',
            'recounted_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function secondPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_person_id');
    }

    public function recounter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recounted_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Value of the adjustment in minor units (santim) based on batch unit cost.
     */
    public function valueSantim(): int
    {
        return abs($this->quantity) * ($this->batch->unit_cost ?? 0);
    }

    /**
     * Value of the adjustment in ETB.
     */
    public function valueEtb(): float
    {
        return round($this->valueSantim() / 100, 2);
    }

    /**
     * BR-07: When the value is above inventory.adjustment_value_threshold
     * (or the threshold is "Not set" and policy says so), the request needs a recount before approval.
     */
    public function requiresRecount(): bool
    {
        $threshold = setting('inventory.adjustment_value_threshold');

        // If threshold is not configured, policy mandates a recount
        if ($threshold === null || $threshold === '' || $threshold === 'Not set') {
            return true;
        }

        $thresholdSantim = (int) round(((float) $threshold) * 100);

        return $this->valueSantim() > $thresholdSantim;
    }
}

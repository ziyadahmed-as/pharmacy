<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock disposal record (UC-09).
 *
 * @property int $id
 * @property int $batch_id
 * @property int $quarantine_record_id
 * @property int $quantity
 * @property string $reason
 * @property string $method
 * @property string|null $supplier_name
 * @property string|null $collector_name
 * @property string $status
 * @property int $requested_by
 * @property int|null $performer_id
 * @property int|null $witness_id
 * @property int|null $approval_request_id
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property string|null $certificate_no
 * @property string|null $evidence_path
 * @property string|null $notes
 * @property CarbonInterface|null $disposed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Batch $batch
 * @property-read QuarantineRecord $quarantineRecord
 * @property-read User $requestedBy
 * @property-read User|null $performer
 * @property-read User|null $witness
 * @property-read ApprovalRequest|null $approvalRequest
 * @property-read User|null $approvedBy
 * @property-read User|null $rejectedBy
 *
 * @uc UC-09
 *
 * @fr FR-2.16, FR-2.17, FR-2.18, FR-2.19, FR-2.20
 *
 * @br BR-07, BR-11
 *
 * @ac AC-10, AC-25
 */
class Disposal extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public const METHOD_RETURN_TO_SUPPLIER = 'return_to_supplier';

    public const METHOD_INCINERATION = 'incineration';

    public const METHOD_AUTHORISED_COLLECTOR = 'authorised_collector';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_RETURN_TO_SUPPLIER,
        self::METHOD_INCINERATION,
        self::METHOD_AUTHORISED_COLLECTOR,
        self::METHOD_OTHER,
    ];

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

    protected $fillable = [
        'batch_id',
        'quarantine_record_id',
        'quantity',
        'reason',
        'method',
        'supplier_name',
        'collector_name',
        'status',
        'requested_by',
        'performer_id',
        'witness_id',
        'approval_request_id',
        'approved_by',
        'rejected_by',
        'rejection_reason',
        'certificate_no',
        'evidence_path',
        'notes',
        'disposed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'disposed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function quarantineRecord(): BelongsTo
    {
        return $this->belongsTo(QuarantineRecord::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performer_id');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isReturnToSupplier(): bool
    {
        return $this->method === self::METHOD_RETURN_TO_SUPPLIER;
    }

    public function isControlled(): bool
    {
        return $this->batch->product->classification === 'controlled';
    }

    public function lossValue(): int
    {
        return $this->quantity * (int) ($this->batch->unit_cost ?? 0);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('disposal.status_pending'),
            self::STATUS_APPROVED => __('disposal.status_approved'),
            self::STATUS_REJECTED => __('disposal.status_rejected'),
            default => $this->status,
        };
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_RETURN_TO_SUPPLIER => __('disposal.method_return_to_supplier'),
            self::METHOD_INCINERATION => __('disposal.method_incineration'),
            self::METHOD_AUTHORISED_COLLECTOR => __('disposal.method_authorised_collector'),
            self::METHOD_OTHER => __('disposal.method_other'),
            default => $this->method,
        };
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_EXPIRED => __('disposal.reason_expired'),
            self::REASON_DAMAGED => __('disposal.reason_damaged'),
            self::REASON_RECALLED => __('disposal.reason_recalled'),
            self::REASON_CONTAMINATED => __('disposal.reason_contaminated'),
            self::REASON_OTHER => __('disposal.reason_other'),
            default => $this->reason,
        };
    }

    /**
     * Build the disposal loss report query (FR-2.20, STEP 5).
     * Expose in UC-30 later.
     */
    public static function lossReportQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $method = null,
        ?string $reason = null
    ): Builder {
        return static::query()
            ->with(['batch.product', 'approvedBy', 'performer', 'witness'])
            ->where('status', self::STATUS_APPROVED)
            ->when($startDate, fn (Builder $q) => $q->whereDate('disposed_at', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('disposed_at', '<=', $endDate))
            ->when($method, fn (Builder $q) => $q->where('method', $method))
            ->when($reason, fn (Builder $q) => $q->where('reason', $reason))
            ->orderByDesc('disposed_at');
    }

    /**
     * Generate unique certificate number.
     */
    public static function generateCertificateNo(int $id): string
    {
        return 'DISP-'.now()->format('Ymd').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Operating Expense record (UC-28).
 *
 * @property int $id
 * @property string $category
 * @property int $amount
 * @property CarbonInterface $date
 * @property string $description
 * @property string|null $proof_path
 * @property string $status
 * @property int $created_by
 * @property int|null $approved_by
 * @property int|null $approval_request_id
 * @property int|null $reversal_of_id
 * @property string|null $reversal_reason
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read User $creator
 * @property-read User|null $approver
 * @property-read ApprovalRequest|null $approvalRequest
 * @property-read Expense|null $reversedExpense
 * @property-read Collection<int, Expense> $reversals
 *
 * @uc UC-28
 *
 * @fr FR-6.2
 *
 * @ac AC-24
 */
class Expense extends Model
{
    use HasFactory;

    public const CATEGORY_RENT = 'rent';

    public const CATEGORY_SALARIES = 'salaries';

    public const CATEGORY_UTILITIES = 'utilities';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_RENT,
        self::CATEGORY_SALARIES,
        self::CATEGORY_UTILITIES,
        self::CATEGORY_TRANSPORT,
        self::CATEGORY_OTHER,
    ];

    public const STATUS_POSTED = 'posted';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_POSTED,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'category',
        'amount',
        'date',
        'description',
        'proof_path',
        'status',
        'created_by',
        'approved_by',
        'approval_request_id',
        'reversal_of_id',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('Expenses cannot be deleted. Corrections must be made via reversal entries.');
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function reversedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(Expense::class, 'reversal_of_id');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null || $this->amount < 0;
    }

    public function formattedAmount(): string
    {
        $prefix = $this->amount < 0 ? '-' : '';

        return $prefix.number_format(abs($this->amount) / 100, 2).' '.setting('profile.currency', 'ETB');
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_RENT => __('expense.category_rent'),
            self::CATEGORY_SALARIES => __('expense.category_salaries'),
            self::CATEGORY_UTILITIES => __('expense.category_utilities'),
            self::CATEGORY_TRANSPORT => __('expense.category_transport'),
            self::CATEGORY_OTHER => __('expense.category_other'),
            default => $this->category,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_POSTED => __('expense.status_posted'),
            self::STATUS_PENDING_APPROVAL => __('expense.status_pending_approval'),
            self::STATUS_REJECTED => __('expense.status_rejected'),
            default => $this->status,
        };
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate);
    }
}

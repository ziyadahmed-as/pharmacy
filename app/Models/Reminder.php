<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refill reminder for a customer prescription line.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $prescription_line_id
 * @property CarbonInterface $due_date
 * @property string $channel
 * @property string $status
 * @property CarbonInterface|null $sent_at
 * @property string|null $message_text
 * @property string|null $error_message
 * @property int $attempts
 * @property int|null $sent_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Customer $customer
 * @property-read PrescriptionLine $prescriptionLine
 * @property-read User|null $sentBy
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
class Reminder extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CALLED = 'called';

    protected $fillable = [
        'customer_id',
        'prescription_line_id',
        'due_date',
        'channel',
        'status',
        'sent_at',
        'message_text',
        'error_message',
        'attempts',
        'sent_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prescriptionLine(): BelongsTo
    {
        return $this->belongsTo(PrescriptionLine::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCalled(): bool
    {
        return $this->status === self::STATUS_CALLED;
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeDue(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        $date = $asOf ?? now()->toDateString();

        return $query->where('due_date', '<=', $date);
    }
}

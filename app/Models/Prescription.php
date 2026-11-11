<?php

namespace App\Models;

use App\Services\ActingRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prescription record from a registered prescriber.
 *
 * @property int $id
 * @property string $prescription_number
 * @property int $customer_id
 * @property string $prescriber_name
 * @property string $prescriber_registration_no
 * @property CarbonInterface $prescription_date
 * @property string $status
 * @property string|null $scan_path
 * @property int|null $replaced_by_id
 * @property string|null $cancellation_reason
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 * @property-read User $creator
 * @property-read Prescription|null $replacedBy
 * @property-read Collection<int, PrescriptionLine> $lines
 * @property-read Collection<int, PrescriptionStatusHistory> $statusHistory
 *
 * @uc UC-19
 *
 * @fr FR-4.2, FR-4.8, FR-4.11
 */
class Prescription extends Model
{
    use HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';

    public const STATUS_FULLY_DISPENSED = 'fully_dispensed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'prescription_number',
        'customer_id',
        'prescriber_name',
        'prescriber_registration_no',
        'prescription_date',
        'status',
        'scan_path',
        'replaced_by_id',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'prescription_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'replaced_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PrescriptionLine::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PrescriptionStatusHistory::class)->orderBy('created_at');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_REGISTERED,
            self::STATUS_PENDING_VERIFICATION,
            self::STATUS_VERIFIED,
            self::STATUS_PARTIALLY_DISPENSED,
        ]);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFullyDispensed(): bool
    {
        return $this->status === self::STATUS_FULLY_DISPENSED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_REGISTERED => __('prescriptions.status_registered'),
            self::STATUS_PENDING_VERIFICATION => __('prescriptions.status_pending_verification'),
            self::STATUS_VERIFIED => __('prescriptions.status_verified'),
            self::STATUS_PARTIALLY_DISPENSED => __('prescriptions.status_partially_dispensed'),
            self::STATUS_FULLY_DISPENSED => __('prescriptions.status_fully_dispensed'),
            self::STATUS_REJECTED => __('prescriptions.status_rejected'),
            self::STATUS_CANCELLED => __('prescriptions.status_cancelled'),
            self::STATUS_EXPIRED => __('prescriptions.status_expired'),
            default => $this->status,
        };
    }

    public function transitionStatus(string $to, User $actor, ?string $notes = null): void
    {
        $from = $this->status;
        $this->status = $to;
        $this->save();

        PrescriptionStatusHistory::create([
            'prescription_id' => $this->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor->id,
            'role_used' => ActingRole::for($actor)?->value,
            'notes' => $notes,
        ]);
    }
}

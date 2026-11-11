<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sale completed or voided inside an open shift on a named till.
 *
 * @property int $id
 * @property string $receipt_no
 * @property int $shift_id
 * @property int $user_id
 * @property int|null $prescription_id
 * @property string $status
 * @property int $total
 * @property string $idempotency_key
 * @property int $reprint_count
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no',
        'shift_id',
        'user_id',
        'customer_id',
        'prescription_id',
        'status',
        'total',
        'idempotency_key',
        'reprint_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'reprint_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isReprint(): bool
    {
        return $this->reprint_count > 0;
    }

    public function totalEtb(): string
    {
        return number_format($this->total / 100, 2);
    }
}

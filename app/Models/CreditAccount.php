<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Credit account linked to a customer with limit and running balance.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $credit_limit
 * @property int $balance
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @uc UC-26
 *
 * @fr FR-5.3, FR-6.4
 */
class CreditAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'credit_limit',
        'balance',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'balance' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function availableCredit(): int
    {
        return max(0, $this->credit_limit - $this->balance);
    }

    public function isOverLimit(): bool
    {
        return $this->balance > $this->credit_limit;
    }

    public function hasRoom(int $amount): bool
    {
        return ($this->balance + $amount) <= $this->credit_limit;
    }

    public function balanceEtb(): string
    {
        return number_format($this->balance / 100, 2);
    }

    public function limitEtb(): string
    {
        return number_format($this->credit_limit / 100, 2);
    }

    public function availableCreditEtb(): string
    {
        return number_format($this->availableCredit() / 100, 2);
    }
}

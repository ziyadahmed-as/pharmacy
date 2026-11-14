<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Credit transaction: a charge (sale on credit) or a payment (collection).
 *
 * @property int $id
 * @property int $credit_account_id
 * @property int|null $sale_id
 * @property int $amount
 * @property string $direction
 * @property string|null $method
 * @property string|null $receipt_no
 * @property string|null $reference
 * @property int $user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @uc UC-26
 *
 * @fr FR-5.3, FR-6.4
 */
class CreditPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_account_id',
        'sale_id',
        'amount',
        'direction',
        'method',
        'receipt_no',
        'reference',
        'user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountEtb(): string
    {
        return number_format($this->amount / 100, 2);
    }

    public function isCharge(): bool
    {
        return $this->direction === 'charge';
    }

    public function isPayment(): bool
    {
        return $this->direction === 'payment';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment tender recorded against a completed sale.
 *
 * @property int $id
 * @property int $sale_id
 * @property string $method
 * @property int $amount
 * @property string|null $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function amountEtb(): string
    {
        return number_format($this->amount / 100, 2);
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'cash' => __('sales.payment_method_cash'),
            'mobile_money' => __('sales.payment_method_mobile_money'),
            'telebirr' => __('sales.payment_method_telebirr'),
            'cbe_birr' => __('sales.payment_method_cbe_birr'),
            'card' => __('sales.payment_method_card'),
            'bank_transfer' => __('sales.payment_method_bank_transfer'),
            default => ucfirst(str_replace('_', ' ', $this->method)),
        };
    }
}

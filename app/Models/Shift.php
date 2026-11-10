<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $till_id
 * @property int $user_id
 * @property CarbonInterface $opened_at
 * @property int $opening_float
 * @property string $status
 * @property CarbonInterface|null $closed_at
 * @property array<string, mixed>|null $expected_cash_per_payment_method
 * @property array<string, mixed>|null $counted_cash_per_payment_method
 * @property int|null $variance
 * @property string|null $variance_reason
 * @property-read Till $till
 * @property-read User $user
 */
class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'till_id',
        'user_id',
        'opened_at',
        'opening_float',
        'status',
        'closed_at',
        'expected_cash_per_payment_method',
        'counted_cash_per_payment_method',
        'variance',
        'variance_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'variance' => 'integer',
            'expected_cash_per_payment_method' => 'array',
            'counted_cash_per_payment_method' => 'array',
        ];
    }

    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function openingFloatEtb(): float
    {
        return $this->opening_float / 100;
    }
}

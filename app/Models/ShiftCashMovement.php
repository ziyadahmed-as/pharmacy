<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shift_id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property string $reason
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Shift $shift
 * @property-read User $user
 */
class ShiftCashMovement extends Model
{
    use HasFactory;

    public const TYPE_CASH_IN = 'cash_in';

    public const TYPE_CASH_OUT = 'cash_out';

    protected $fillable = [
        'shift_id',
        'user_id',
        'type',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
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

    public function isCashIn(): bool
    {
        return $this->type === self::TYPE_CASH_IN;
    }

    public function isCashOut(): bool
    {
        return $this->type === self::TYPE_CASH_OUT;
    }

    public function amountEtb(): string
    {
        return number_format($this->amount / 100, 2);
    }
}


<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment method master record.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_cash
 * @property bool $is_active
 * @property bool $requires_reference
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_cash',
        'is_active',
        'requires_reference',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
            'requires_reference' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Access log for customer sensitive health fields (allergies, chronic conditions).
 *
 * @property int $id
 * @property int $customer_id
 * @property int $user_id
 * @property string|null $role_used
 * @property CarbonInterface $viewed_at
 * @property string|null $ip_address
 * @property CarbonInterface $created_at
 * @property-read Customer $customer
 * @property-read User $user
 *
 * @uc UC-25
 *
 * @fr FR-5.6
 *
 * @ac AC-26
 */
class CustomerHealthView extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'user_id',
        'role_used',
        'viewed_at',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

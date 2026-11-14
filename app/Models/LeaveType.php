<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Leave Type (UC-41).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $days_per_year
 * @property bool $is_paid
 * @property bool $requires_approval
 *
 * @uc UC-41 @fr FR-10.5, FR-10.6
 */
class LeaveType extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'days_per_year' => 'integer',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}

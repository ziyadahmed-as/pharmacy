<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leave Balance per staff member and leave type for a given calendar year (UC-41).
 *
 * @property int $id
 * @property int $staff_id
 * @property int $leave_type_id
 * @property int $year
 * @property int $entitled_days
 * @property int $used_days
 * @property-read int $remaining_days
 *
 * @uc UC-41 @fr FR-10.6
 */
class LeaveBalance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'integer',
            'used_days' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getRemainingDaysAttribute(): int
    {
        return max(0, $this->entitled_days - $this->used_days);
    }
}

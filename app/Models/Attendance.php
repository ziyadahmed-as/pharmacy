<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance Record for a staff member on a specific date (UC-41).
 *
 * @property int $id
 * @property int $staff_id
 * @property CarbonInterface $date
 * @property CarbonInterface|null $clock_in
 * @property CarbonInterface|null $clock_out
 * @property int $lateness_minutes
 * @property bool $is_absent
 * @property string|null $absence_reason
 * @property int|null $roster_entry_id
 * @property string|null $notes
 *
 * @uc UC-41 @fr FR-10.5
 */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'lateness_minutes' => 'integer',
            'is_absent' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class);
    }

    public function getHoursWorkedAttribute(): float
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return 0.0;
        }

        $minutes = $this->clock_in->diffInMinutes($this->clock_out);

        return round($minutes / 60, 2);
    }
}

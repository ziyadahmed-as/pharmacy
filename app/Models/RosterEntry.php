<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Roster entry (UC-40). Staff shift assignment on a date and till.
 *
 * @property int $id
 * @property int $staff_id
 * @property CarbonImmutable|Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property int|null $till_id
 * @property string|null $note
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @uc UC-40 @fr FR-10.4
 */
class RosterEntry extends Model
{
    use HasFactory;

    protected $table = 'roster_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function getTimeRangeAttribute(): string
    {
        return sprintf('%s - %s', $this->formatted_start_time, $this->formatted_end_time);
    }
}

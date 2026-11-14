<?php

namespace App\Actions\Staff\Attendance;

use App\Models\Attendance;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Clock out a staff member for work (UC-41).
 *
 * @uc UC-41 @fr FR-10.5
 */
class ClockOut
{
    /**
     * @throws ValidationException
     */
    public function execute(Staff $staff, User $actor, ?CarbonInterface $time = null, ?string $notes = null): Attendance
    {
        $clockOutTime = $time ? Carbon::instance($time) : now();
        $date = $clockOutTime->toDateString();

        $attendance = Attendance::where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        if (! $attendance || $attendance->clock_in === null) {
            throw ValidationException::withMessages([
                'attendance' => __('attendance.not_clocked_in'),
            ]);
        }

        if ($attendance->clock_out !== null) {
            throw ValidationException::withMessages([
                'attendance' => __('attendance.already_clocked_out', ['time' => $attendance->clock_out->format('H:i')]),
            ]);
        }

        $attendance->clock_out = $clockOutTime;
        if ($notes) {
            $attendance->notes = trim(($attendance->notes ? $attendance->notes."\n" : '').$notes);
        }
        $attendance->save();

        AuditLogger::record(
            action: 'staff.attendance.clock_out',
            subject: $attendance,
            old: [],
            new: [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'clock_out' => $clockOutTime->toDateTimeString(),
                'hours_worked' => $attendance->hours_worked,
            ],
            user: $actor,
            description: "Staff {$staff->name} clocked out at {$clockOutTime->format('H:i')}"
        );

        return $attendance;
    }
}

<?php

namespace App\Actions\Staff\Attendance;

use App\Models\Attendance;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Clock in a staff member for work and compute lateness against the roster (UC-41).
 *
 * @uc UC-41 @fr FR-10.5
 */
class ClockIn
{
    /**
     * @throws ValidationException
     */
    public function execute(Staff $staff, User $actor, ?CarbonInterface $time = null, ?string $notes = null): Attendance
    {
        $clockInTime = $time ? Carbon::instance($time) : now();
        $date = business_date($clockInTime);

        $existing = Attendance::where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        if ($existing && $existing->clock_in !== null) {
            throw ValidationException::withMessages([
                'attendance' => __('attendance.already_clocked_in', ['time' => $existing->clock_in->format('H:i')]),
            ]);
        }

        // Check scheduled roster entry for today
        $rosterEntry = RosterEntry::where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        $latenessMinutes = 0;
        if ($rosterEntry && ! empty($rosterEntry->start_time)) {
            $scheduledStart = Carbon::parse($date.' '.$rosterEntry->start_time);
            if ($clockInTime->greaterThan($scheduledStart)) {
                $latenessMinutes = (int) $scheduledStart->diffInMinutes($clockInTime);
            }
        }

        $attendance = $existing ?? new Attendance([
            'staff_id' => $staff->id,
            'date' => $date,
        ]);

        $attendance->clock_in = $clockInTime;
        $attendance->lateness_minutes = $latenessMinutes;
        $attendance->is_absent = false;
        $attendance->absence_reason = null;
        $attendance->roster_entry_id = $rosterEntry?->id;
        if ($notes) {
            $attendance->notes = $notes;
        }
        $attendance->save();

        AuditLogger::record(
            action: 'staff.attendance.clock_in',
            subject: $attendance,
            old: [],
            new: [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'clock_in' => $clockInTime->toDateTimeString(),
                'lateness_minutes' => $latenessMinutes,
                'roster_entry_id' => $rosterEntry?->id,
            ],
            user: $actor,
            description: "Staff {$staff->name} clocked in at {$clockInTime->format('H:i')}".($latenessMinutes > 0 ? " ({$latenessMinutes} min late)" : '')
        );

        return $attendance;
    }
}

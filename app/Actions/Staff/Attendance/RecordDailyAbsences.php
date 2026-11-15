<?php

namespace App\Actions\Staff\Attendance;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;

/**
 * Flags absences for scheduled staff who did not clock in on a given date (UC-41).
 *
 * @uc UC-41 @fr FR-10.5
 */
class RecordDailyAbsences
{
    /**
     * @return array{total_scheduled: int, attended: int, absences_recorded: int, on_leave: int}
     */
    public function execute(?CarbonInterface $targetDate = null): array
    {
        $date = business_date($targetDate);

        $rosterEntries = RosterEntry::with('staff')
            ->whereDate('date', $date)
            ->get();

        $totalScheduled = $rosterEntries->count();
        $attended = 0;
        $absencesRecorded = 0;
        $onLeave = 0;

        foreach ($rosterEntries as $entry) {
            $attendance = Attendance::where('staff_id', $entry->staff_id)
                ->whereDate('date', $date)
                ->first();

            if ($attendance && $attendance->clock_in !== null) {
                $attended++;

                continue;
            }

            // Check if staff has approved leave on this date
            $isOnLeave = LeaveRequest::where('staff_id', $entry->staff_id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            $reason = $isOnLeave ? 'on_leave' : 'unexcused';
            if ($isOnLeave) {
                $onLeave++;
            } else {
                $absencesRecorded++;
            }

            $record = $attendance ?? new Attendance([
                'staff_id' => $entry->staff_id,
                'date' => $date,
            ]);

            $record->is_absent = true;
            $record->absence_reason = $reason;
            $record->lateness_minutes = 0;
            $record->roster_entry_id = $entry->id;
            $record->save();
        }

        AuditLogger::record(
            action: 'staff.attendance.absences_checked',
            subject: null,
            old: [],
            new: [
                'date' => $date,
                'total_scheduled' => $totalScheduled,
                'attended' => $attended,
                'absences_recorded' => $absencesRecorded,
                'on_leave' => $onLeave,
            ],
            user: null,
            description: "Checked attendance for {$date}: {$attended} attended, {$absencesRecorded} unexcused absent, {$onLeave} on leave"
        );

        return [
            'total_scheduled' => $totalScheduled,
            'attended' => $attended,
            'absences_recorded' => $absencesRecorded,
            'on_leave' => $onLeave,
        ];
    }
}

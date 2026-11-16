<?php

namespace App\Reports\Definitions\Staff;

use App\Models\AttendanceRecord;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Roster vs attendance report (UC-30/UC-40).
 * Compares scheduled roster shifts with actual biometric/manual clock-ins.
 */
class RosterVsAttendanceReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'roster_vs_attendance';
    }

    public function name(): string
    {
        return __('reports.roster_vs_attendance_name', [], 'en') ?: 'Roster vs Attendance';
    }

    public function category(): string
    {
        return 'staff';
    }

    public function description(): string
    {
        return __('reports.roster_vs_attendance_desc', [], 'en') ?: 'Comparison between planned staff shift rosters and actual clock-in attendance.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'user_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date', [], 'en') ?: 'Date',
            __('reports.col_staff_name', [], 'en') ?: 'Staff Member',
            __('reports.col_scheduled_shift', [], 'en') ?: 'Scheduled Shift',
            __('reports.col_actual_status', [], 'en') ?: 'Actual Attendance',
            __('reports.col_hours_scheduled', [], 'en') ?: 'Sched. Hours',
            __('reports.col_hours_worked', [], 'en') ?: 'Actual Hours',
            __('reports.col_punctuality', [], 'en') ?: 'Punctuality',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        if (! Schema::hasTable('roster_entries')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => [],
                'row_count' => 0,
            ];
        }

        $entries = RosterEntry::with('staff.user')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->when(! empty($filters['user_id']), fn ($q) => $q->whereHas('staff', fn ($s) => $s->where('user_id', $filters['user_id'])))
            ->orderBy('date')
            ->get();

        $rows = [];

        foreach ($entries as $r) {
            $staff = $r->staff;
            $att = Schema::hasTable('attendance_records')
                ? AttendanceRecord::where('staff_id', $r->staff_id)->whereDate('date', $r->date)->first()
                : null;

            $status = $att ? ucfirst($att->status) : 'No Record';
            $actualHours = $att ? round(($att->minutes_worked ?? 0) / 60, 1) . ' hrs' : '0 hrs';
            $schedHours = ($r->shift_hours ?? 8) . ' hrs';
            $punctual = $att ? ($att->is_late ? 'Late (' . ($att->late_minutes ?? 0) . 'm)' : 'On Time') : '—';

            $rows[] = [
                $r->date ? \Carbon\Carbon::parse($r->date)->format('Y-m-d') : '—',
                $staff?->name ?? '—',
                $r->shift_name ?? 'Regular Shift',
                $status,
                $schedHours,
                $actualHours,
                $punctual,
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'scheduled_shifts' => count($entries),
            ],
            'row_count' => count($rows),
        ];
    }
}

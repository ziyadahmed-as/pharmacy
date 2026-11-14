<?php

namespace App\Reports\Definitions\Staff;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Monthly attendance, lateness and absence report (UC-41).
 *
 * @uc UC-41 @fr FR-10.5, FR-10.6 @br BR-13
 */
class MonthlyAttendanceReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'staff_monthly_attendance';
    }

    public function name(): string
    {
        return __('reports.staff_monthly_attendance_name');
    }

    public function category(): string
    {
        return 'staff';
    }

    public function description(): string
    {
        return __('reports.staff_monthly_attendance_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'staff_id' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_staff_member'),
            __('reports.col_scheduled_days'),
            __('reports.col_attended_days'),
            __('reports.col_hours_worked'),
            __('reports.col_times_late'),
            __('reports.col_lateness_minutes'),
            __('reports.col_absent_days'),
            __('reports.col_leave_days'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$from, $to] = $this->parseDateRange($filters);

        $staffQuery = Staff::query()->orderBy('name');

        // Staff can only view their own record (BR-13)
        if (! $user->hasAnyRole([Role::Owner, Role::PharmacyManager])) {
            $staffQuery->where('user_id', $user->id);
        } elseif (! empty($filters['staff_id'])) {
            $staffQuery->where('id', $filters['staff_id']);
        }

        $staffList = $staffQuery->get();

        $rows = [];
        $totalScheduled = 0;
        $totalAttended = 0;
        $totalHours = 0.0;
        $totalLateness = 0;
        $totalTimesLate = 0;
        $totalAbsences = 0;
        $totalLeave = 0;

        foreach ($staffList as $staff) {
            // 1. Scheduled days from roster
            $scheduledDays = RosterEntry::where('staff_id', $staff->id)
                ->whereBetween('date', [$from, $to])
                ->count();

            // 2. Attendance records in range
            $attendances = Attendance::where('staff_id', $staff->id)
                ->whereBetween('date', [$from, $to])
                ->get();

            $attendedDays = $attendances->whereNotNull('clock_in')->count();

            $hoursWorked = 0.0;
            $latenessMinutes = 0;
            $timesLate = 0;
            $unexcusedAbsences = 0;
            $leaveDays = 0;

            foreach ($attendances as $att) {
                if ($att->clock_in && $att->clock_out) {
                    $hoursWorked += $att->hours_worked;
                }
                if ($att->lateness_minutes > 0) {
                    $latenessMinutes += $att->lateness_minutes;
                    $timesLate++;
                }
                if ($att->is_absent) {
                    if ($att->absence_reason === 'on_leave') {
                        $leaveDays++;
                    } else {
                        $unexcusedAbsences++;
                    }
                }
            }

            $totalScheduled += $scheduledDays;
            $totalAttended += $attendedDays;
            $totalHours += $hoursWorked;
            $totalLateness += $latenessMinutes;
            $totalTimesLate += $timesLate;
            $totalAbsences += $unexcusedAbsences;
            $totalLeave += $leaveDays;

            $rows[] = [
                $staff->name,
                $scheduledDays,
                $attendedDays,
                number_format($hoursWorked, 1),
                $timesLate,
                $latenessMinutes,
                $unexcusedAbsences,
                $leaveDays,
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                __('reports.summary_total_scheduled') => $totalScheduled,
                __('reports.summary_total_attended') => $totalAttended,
                __('reports.summary_total_hours') => number_format($totalHours, 1),
                __('reports.summary_total_lateness') => "{$totalLateness} min ({$totalTimesLate} times)",
                __('reports.summary_total_absences') => $totalAbsences,
                __('reports.summary_total_leave') => $totalLeave,
            ],
            'row_count' => count($rows),
        ];
    }
}

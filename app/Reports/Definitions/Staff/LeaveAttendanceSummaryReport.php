<?php

namespace App\Reports\Definitions\Staff;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Leave and attendance summary report (UC-30/UC-40/UC-41).
 */
class LeaveAttendanceSummaryReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'leave_attendance_summary';
    }

    public function name(): string
    {
        return __('reports.leave_attendance_summary_name', [], 'en') ?: 'Leave & Attendance Summary';
    }

    public function category(): string
    {
        return 'staff';
    }

    public function description(): string
    {
        return __('reports.leave_attendance_summary_desc', [], 'en') ?: 'Summary of staff attendance hours, late arrivals, absences, and approved leaves.';
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
            __('reports.col_staff_name', [], 'en') ?: 'Staff Member',
            __('reports.col_days_present', [], 'en') ?: 'Days Present',
            __('reports.col_late_count', [], 'en') ?: 'Late Arrivals',
            __('reports.col_total_hours', [], 'en') ?: 'Total Hours Worked',
            __('reports.col_approved_leaves', [], 'en') ?: 'Approved Leave Days',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $staffList = Staff::with('user')
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->get();

        $rows = [];
        $totalHoursAll = 0;

        foreach ($staffList as $st) {
            $daysPresent = 0;
            $lateCount = 0;
            $totalMinutes = 0;

            if (Schema::hasTable('attendance_records')) {
                $att = AttendanceRecord::where('staff_id', $st->id)
                    ->whereDate('date', '>=', $start)
                    ->whereDate('date', '<=', $end)
                    ->get();

                $daysPresent = $att->where('status', 'present')->count();
                $lateCount = $att->where('is_late', true)->count();
                $totalMinutes = (int) $att->sum('minutes_worked');
            }

            $leaveDays = 0;
            if (Schema::hasTable('leave_requests')) {
                $leaveDays = LeaveRequest::where('staff_id', $st->id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start)
                    ->count();
            }

            $hours = round($totalMinutes / 60, 1);
            $totalHoursAll += $hours;

            $rows[] = [
                $st->name ?? $st->user?->name ?? '—',
                $daysPresent,
                $lateCount,
                $hours . ' hrs',
                $leaveDays . ' days',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'staff_count' => count($staffList),
                'total_hours' => $totalHoursAll . ' hrs',
            ],
            'row_count' => count($rows),
        ];
    }
}

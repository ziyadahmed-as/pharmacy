<?php

namespace App\Actions\Staff\Leave;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Submits a staff leave request and triggers UC-34 manager approval (UC-41).
 *
 * @uc UC-41 @fr FR-10.6
 */
class SubmitLeaveRequest
{
    public function __construct(protected ApprovalService $approvalService) {}

    /**
     * @throws ValidationException
     */
    public function execute(
        Staff $staff,
        User $actor,
        LeaveType $leaveType,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        ?string $reason = null
    ): LeaveRequest {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'end_date' => __('attendance.leave_end_before_start'),
            ]);
        }

        $days = (int) $start->diffInDays($end) + 1;
        $year = $start->year;

        // Find or initialize balance for this year
        $balance = LeaveBalance::firstOrCreate(
            ['staff_id' => $staff->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
            ['entitled_days' => $leaveType->days_per_year, 'used_days' => 0]
        );

        $remainingDays = $balance->remaining_days;
        $isOverBalance = ($days > $remainingDays);

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $staff->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $reason,
            'status' => LeaveRequest::STATUS_PENDING,
            'is_over_balance' => $isOverBalance,
        ]);

        $summary = sprintf(
            '%s: %d day(s) %s (%s to %s)%s',
            $staff->name,
            $days,
            $leaveType->name,
            $start->format('d M'),
            $end->format('d M'),
            $isOverBalance ? " [OVER-BALANCE: {$remainingDays} days available]" : ''
        );

        $approvalReq = $this->approvalService->request(
            type: 'staff.leave',
            initiator: $actor,
            payload: [
                'leave_request_id' => $leaveRequest->id,
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'leave_type_id' => $leaveType->id,
                'leave_type_name' => $leaveType->name,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'is_over_balance' => $isOverBalance,
                'remaining_days' => $remainingDays,
            ],
            summary: $summary,
            subject: $leaveRequest,
            concerns: $staff->user,
            comment: $reason
        );

        $leaveRequest->update(['approval_request_id' => $approvalReq->id]);

        AuditLogger::record(
            action: 'staff.leave.requested',
            subject: $leaveRequest,
            old: [],
            new: [
                'staff_id' => $staff->id,
                'days' => $days,
                'leave_type' => $leaveType->name,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_over_balance' => $isOverBalance,
                'approval_request_id' => $approvalReq->id,
            ],
            user: $actor,
            description: $summary
        );

        return $leaveRequest;
    }
}

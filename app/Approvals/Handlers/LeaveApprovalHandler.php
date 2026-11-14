<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Handles approval decisions for staff leave requests (UC-41).
 *
 * @uc UC-41 @fr FR-10.6
 */
class LeaveApprovalHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'staff.leave';
    }

    /**
     * Pharmacy Manager and Owner can approve leave requests.
     * Initiator cannot approve their own request (enforced by UC-34).
     *
     * @param  array<string, mixed>  $payload
     * @return list<Role>
     */
    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return [Role::PharmacyManager, Role::Owner];
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $leaveRequestId = $request->payload['leave_request_id'] ?? null;
        if (! $leaveRequestId) {
            return;
        }

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

        if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
            return;
        }

        $oldStatus = $leaveRequest->status;

        $leaveRequest->forceFill([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'decided_at' => now(),
        ])->save();

        // Deduct from leave balance for that year
        $year = Carbon::parse($leaveRequest->start_date)->year;
        $balance = LeaveBalance::where('staff_id', $leaveRequest->staff_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $year)
            ->first();

        if ($balance) {
            $balance->increment('used_days', $leaveRequest->days);
        }

        // If today is within leave dates, set staff status to ON_LEAVE
        $today = now()->toDateString();
        if ($today >= $leaveRequest->start_date->toDateString() && $today <= $leaveRequest->end_date->toDateString()) {
            $leaveRequest->staff->update(['status' => Staff::ON_LEAVE]);
        }

        AuditLogger::record(
            action: 'staff.leave.approved',
            subject: $leaveRequest,
            old: ['status' => $oldStatus],
            new: [
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'used_days' => $balance?->used_days,
            ],
            user: $approver,
            description: "Leave request approved for {$leaveRequest->staff->name} ({$leaveRequest->days} days)"
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $leaveRequestId = $request->payload['leave_request_id'] ?? null;
        if (! $leaveRequestId) {
            return;
        }

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

        $oldStatus = $leaveRequest->status;

        $leaveRequest->forceFill([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'decided_at' => now(),
            'rejection_reason' => $request->decision_comment,
        ])->save();

        AuditLogger::record(
            action: 'staff.leave.rejected',
            subject: $leaveRequest,
            old: ['status' => $oldStatus],
            new: [
                'status' => LeaveRequest::STATUS_REJECTED,
                'approved_by' => $approver->id,
                'rejection_reason' => $request->decision_comment,
            ],
            user: $approver,
            description: "Leave request rejected for {$leaveRequest->staff->name}: {$request->decision_comment}"
        );
    }
}

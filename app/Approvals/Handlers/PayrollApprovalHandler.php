<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Handles Owner approval decisions for monthly payroll runs (UC-42).
 *
 * @uc UC-42 @fr FR-10.7
 */
class PayrollApprovalHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'staff.payroll';
    }

    /**
     * Only the Owner can approve payroll runs submitted by the PM.
     *
     * @param  array<string, mixed>  $payload
     * @return list<Role>
     */
    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return [Role::Owner];
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $payrollRunId = $request->payload['payroll_run_id'] ?? null;
        if (! $payrollRunId) {
            return;
        }

        /** @var PayrollRun $payrollRun */
        $payrollRun = PayrollRun::findOrFail($payrollRunId);

        $oldStatus = $payrollRun->status;

        $payrollRun->forceFill([
            'status' => PayrollRun::STATUS_APPROVED,
            'approved_by' => $approver->id,
        ])->save();

        AuditLogger::record(
            action: 'payroll.approved',
            subject: $payrollRun,
            old: ['status' => $oldStatus],
            new: ['status' => PayrollRun::STATUS_APPROVED],
            user: $approver,
            description: "Owner approved payroll run for {$payrollRun->month} (Net: ETB {$payrollRun->total_net})"
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $payrollRunId = $request->payload['payroll_run_id'] ?? null;
        if (! $payrollRunId) {
            return;
        }

        /** @var PayrollRun $payrollRun */
        $payrollRun = PayrollRun::findOrFail($payrollRunId);

        $oldStatus = $payrollRun->status;

        $payrollRun->forceFill([
            'status' => PayrollRun::STATUS_REJECTED,
            'approved_by' => $approver->id,
        ])->save();

        AuditLogger::record(
            action: 'payroll.rejected',
            subject: $payrollRun,
            old: ['status' => $oldStatus],
            new: ['status' => PayrollRun::STATUS_REJECTED],
            user: $approver,
            description: "Payroll run for {$payrollRun->month} rejected by Owner: {$request->decision_comment}"
        );
    }
}


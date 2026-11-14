<?php

namespace App\Actions\Staff\Payroll;

use App\Enums\Role;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Submits payroll run for Owner approval (if PM prepared it) or auto-approves if Owner confirms.
 *
 * @uc UC-42 @fr FR-10.7
 */
class SubmitPayrollApproval
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, PayrollRun $payrollRun): PayrollRun
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('payroll.manage')) {
            throw new AuthorizationException(__('payroll.unauthorized'));
        }

        if ($payrollRun->status !== PayrollRun::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'payroll' => __('payroll.must_be_draft'),
            ]);
        }

        // If prepared by Owner directly, Owner can approve directly
        if ($user->hasRole(Role::Owner)) {
            $payrollRun->update([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_by' => $user->id,
            ]);

            AuditLogger::record(
                action: 'payroll.approved',
                subject: $payrollRun,
                old: ['status' => PayrollRun::STATUS_DRAFT],
                new: ['status' => PayrollRun::STATUS_APPROVED],
                user: $user,
                description: "Owner directly approved payroll run for {$payrollRun->month}"
            );

            return $payrollRun->fresh();
        }

        // PM submits to Owner for approval via UC-34
        $summary = sprintf(
            'Payroll for %s: %d staff, Total Net: ETB %s',
            $payrollRun->month,
            $payrollRun->lines()->count(),
            number_format($payrollRun->total_net, 2)
        );

        $approvalReq = $this->approvalService->request(
            type: 'staff.payroll',
            initiator: $user,
            payload: [
                'payroll_run_id' => $payrollRun->id,
                'month' => $payrollRun->month,
                'total_gross' => $payrollRun->total_gross,
                'total_net' => $payrollRun->total_net,
                'staff_count' => $payrollRun->lines()->count(),
            ],
            summary: $summary,
            subject: $payrollRun,
            comment: $payrollRun->notes
        );

        $payrollRun->update([
            'status' => PayrollRun::STATUS_PENDING_APPROVAL,
            'approval_request_id' => $approvalReq->id,
        ]);

        AuditLogger::record(
            action: 'payroll.approval_requested',
            subject: $payrollRun,
            old: ['status' => PayrollRun::STATUS_DRAFT],
            new: ['status' => PayrollRun::STATUS_PENDING_APPROVAL],
            user: $user,
            description: "Submitted payroll run for {$payrollRun->month} to Owner approval"
        );

        return $payrollRun->fresh();
    }
}


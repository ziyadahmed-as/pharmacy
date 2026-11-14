<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Handles approval decisions for operating expenses exceeding threshold (UC-28).
 *
 * @uc UC-28
 *
 * @fr FR-6.2
 *
 * @oq OQ-06
 */
class ExpenseApprovalHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'finance.expense';
    }

    /**
     * Only the Owner approves expenses raised by the Pharmacy Manager.
     * The initiator cannot approve their own entry.
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
        $expenseId = $request->payload['expense_id'] ?? null;
        if (! $expenseId) {
            return;
        }

        /** @var Expense $expense */
        $expense = Expense::findOrFail($expenseId);

        if ($expense->isPosted()) {
            return;
        }

        $expense->forceFill([
            'status' => Expense::STATUS_POSTED,
            'approved_by' => $approver->id,
        ])->save();

        AuditLogger::record(
            action: 'expense.approved',
            subject: $expense,
            old: ['status' => Expense::STATUS_PENDING_APPROVAL],
            new: ['status' => Expense::STATUS_POSTED, 'approved_by' => $approver->id],
            user: $approver,
            description: $request->decision_comment
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $expenseId = $request->payload['expense_id'] ?? null;
        if (! $expenseId) {
            return;
        }

        /** @var Expense|null $expense */
        $expense = Expense::find($expenseId);

        if ($expense && $expense->isPendingApproval()) {
            $expense->forceFill([
                'status' => Expense::STATUS_REJECTED,
                'approved_by' => $approver->id,
            ])->save();

            AuditLogger::record(
                action: 'expense.rejected',
                subject: $expense,
                old: ['status' => Expense::STATUS_PENDING_APPROVAL],
                new: ['status' => Expense::STATUS_REJECTED, 'approved_by' => $approver->id],
                user: $approver,
                description: $request->decision_comment
            );
        }
    }
}

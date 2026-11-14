<?php

namespace App\Actions\Finance;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Action to record and reverse operating expenses (UC-28).
 *
 * @uc UC-28
 *
 * @fr FR-6.2
 *
 * @oq OQ-06
 */
class RecordExpense
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    /**
     * @param  array{
     *     category: string,
     *     amount: int,
     *     date?: string|null,
     *     description: string,
     *     proof_path?: string|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): Expense
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('finance.expense.record')) {
            throw new AuthorizationException(__('expense.unauthorized'));
        }

        $category = $data['category'] ?? null;
        if (! $category || ! in_array($category, Expense::CATEGORIES, true)) {
            throw ValidationException::withMessages([
                'category' => __('expense.category_required'),
            ]);
        }

        $amount = $data['amount'] ?? null;
        if ($amount === null || (int) $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('expense.amount_required'),
            ]);
        }
        $amount = (int) $amount;

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => __('expense.description_required'),
            ]);
        }

        $date = ! empty($data['date'])
            ? Carbon::parse($data['date'])->toDateString()
            : now()->toDateString();

        $proofPath = ! empty($data['proof_path']) ? (string) $data['proof_path'] : null;

        // Approval determination (FR-6.2, OQ-06)
        // Owner entries are posted immediately.
        // PM entries: if limit is "Not set" (null) or amount > limit, requires Owner approval.
        $needsApproval = false;
        if ($user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
            $limit = setting('finance.expense_approval_limit');
            if ($limit === null || $limit === '') {
                $needsApproval = true;
            } else {
                $needsApproval = $amount > (int) $limit;
            }
        }

        $status = $needsApproval ? Expense::STATUS_PENDING_APPROVAL : Expense::STATUS_POSTED;
        $approvedBy = $needsApproval ? null : $user->id;

        return DB::transaction(function () use ($user, $category, $amount, $date, $description, $proofPath, $status, $approvedBy, $needsApproval) {
            /** @var Expense $expense */
            $expense = Expense::create([
                'category' => $category,
                'amount' => $amount,
                'date' => $date,
                'description' => $description,
                'proof_path' => $proofPath,
                'status' => $status,
                'created_by' => $user->id,
                'approved_by' => $approvedBy,
            ]);

            if ($needsApproval) {
                $approvalRequest = $this->approvalService->request(
                    type: 'finance.expense',
                    initiator: $user,
                    payload: [
                        'expense_id' => $expense->id,
                        'category' => $expense->category,
                        'amount' => $expense->amount,
                        'formatted_amount' => $expense->formattedAmount(),
                        'date' => $expense->date->toDateString(),
                        'description' => $expense->description,
                    ],
                    summary: sprintf(
                        'Operating expense (%s): %s - %s',
                        $expense->categoryLabel(),
                        $expense->formattedAmount(),
                        $expense->description
                    ),
                    subject: $expense,
                    comment: $expense->description
                );

                $expense->forceFill(['approval_request_id' => $approvalRequest->id])->save();
            }

            AuditLogger::record(
                action: 'expense.recorded',
                subject: $expense,
                old: [],
                new: [
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'date' => $expense->date->toDateString(),
                    'description' => $expense->description,
                    'status' => $expense->status,
                ],
                user: $user,
                description: $expense->description
            );

            return $expense;
        });
    }

    /**
     * Corrections to expenses are done by creating a reversing entry with a reason (STEP 3).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reverse(User $user, Expense $expense, string $reason): Expense
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('finance.expense.record')) {
            throw new AuthorizationException(__('expense.unauthorized'));
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => __('expense.reason_required'),
            ]);
        }

        if (! $expense->isPosted() || $expense->isReversal() || $expense->reversals()->exists()) {
            throw new RuntimeException(__('expense.cannot_reverse'));
        }

        return DB::transaction(function () use ($user, $expense, $trimmedReason) {
            /** @var Expense $reversal */
            $reversal = Expense::create([
                'category' => $expense->category,
                'amount' => -$expense->amount,
                'date' => now()->toDateString(),
                'description' => 'Reversal: '.$expense->description,
                'proof_path' => null,
                'status' => Expense::STATUS_POSTED,
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'reversal_of_id' => $expense->id,
                'reversal_reason' => $trimmedReason,
            ]);

            AuditLogger::record(
                action: 'expense.recorded',
                subject: $reversal,
                old: [],
                new: [
                    'category' => $reversal->category,
                    'amount' => $reversal->amount,
                    'reversal_of_id' => $expense->id,
                    'reversal_reason' => $trimmedReason,
                    'status' => Expense::STATUS_POSTED,
                ],
                user: $user,
                description: 'Reversal: '.$trimmedReason
            );

            AuditLogger::record(
                action: 'expense.reversed',
                subject: $expense,
                old: ['status' => Expense::STATUS_POSTED],
                new: ['reversal_id' => $reversal->id, 'reason' => $trimmedReason],
                user: $user,
                description: $trimmedReason
            );

            return $reversal;
        });
    }
}

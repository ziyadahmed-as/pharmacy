<?php

namespace App\Actions\Staff\Payroll;

use App\Actions\Finance\RecordExpense;
use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records payment for an approved payroll run:
 *  - Marks salary advances as recovered.
 *  - Posts salary expense to UC-28 (category: salaries) once.
 *  - Marks run as paid.
 *
 * @uc UC-42 @fr FR-10.7 @br BR-13
 */
class RecordPayrollPayment
{
    public function __construct(
        protected RecordExpense $recordExpense
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $payer, PayrollRun $payrollRun, ?string $paymentNotes = null): PayrollRun
    {
        if ($payer->isSystemAdmin() || ! $payer->hasPermission('payroll.manage')) {
            throw new AuthorizationException(__('payroll.unauthorized'));
        }

        if ($payrollRun->status !== PayrollRun::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'payroll' => __('payroll.must_be_approved_to_pay'),
            ]);
        }

        if ($payrollRun->expense_id !== null) {
            throw ValidationException::withMessages([
                'payroll' => __('payroll.already_paid'),
            ]);
        }

        return DB::transaction(function () use ($payer, $payrollRun, $paymentNotes) {
            // 1. Post expense to UC-28
            $description = "Monthly payroll payment for {$payrollRun->month} (".$payrollRun->lines()->count().' staff)';
            if ($paymentNotes) {
                $description .= ' - '.$paymentNotes;
            }

            $expense = $this->recordExpense->execute($payer, [
                'category' => Expense::CATEGORY_SALARIES,
                'amount' => $payrollRun->total_net,
                'date' => now()->toDateString(),
                'description' => $description,
            ]);

            // 2. Mark deducted advances as recovered
            $staffIds = $payrollRun->lines()->pluck('staff_id');
            SalaryAdvance::whereIn('staff_id', $staffIds)
                ->where('status', SalaryAdvance::STATUS_APPROVED)
                ->where(function ($q) use ($payrollRun) {
                    $q->whereNull('repayment_month')
                        ->orWhere('repayment_month', '<=', $payrollRun->month);
                })
                ->update([
                    'status' => SalaryAdvance::STATUS_RECOVERED,
                    'payroll_run_id' => $payrollRun->id,
                ]);

            // 3. Mark payroll run paid
            $payrollRun->update([
                'status' => PayrollRun::STATUS_PAID,
                'paid_by' => $payer->id,
                'paid_at' => now(),
                'expense_id' => $expense->id,
            ]);

            AuditLogger::record(
                action: 'payroll.paid',
                subject: $payrollRun,
                old: ['status' => PayrollRun::STATUS_APPROVED],
                new: [
                    'status' => PayrollRun::STATUS_PAID,
                    'expense_id' => $expense->id,
                    'total_paid' => $payrollRun->total_net,
                ],
                user: $payer,
                description: "Recorded payment for {$payrollRun->month} payroll and posted Expense #{$expense->id}"
            );

            return $payrollRun->fresh(['lines.staff', 'expense']);
        });
    }
}

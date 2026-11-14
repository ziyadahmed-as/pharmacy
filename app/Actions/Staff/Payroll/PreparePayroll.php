<?php

namespace App\Actions\Staff\Payroll;

use App\Models\Attendance;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\RosterEntry;
use App\Models\SalaryAdvance;
use App\Models\Staff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prepares the payroll draft for a month.
 *
 * Pulls base salary, allowances, overtime hours from attendance vs roster,
 * approved salary advances, and calculates gross and net per staff member.
 *
 * @uc UC-42 @fr FR-10.7 @br BR-13
 */
class PreparePayroll
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    /**
     * @param  array<int, array{deductions?: int}>  $customDeductions  staff_id => ['deductions' => x]
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $preparer, string $month, array $customDeductions = [], ?string $notes = null): PayrollRun
    {
        if ($preparer->isSystemAdmin() || ! $preparer->hasPermission('payroll.manage')) {
            throw new AuthorizationException(__('payroll.unauthorized'));
        }

        // Validate format YYYY-MM
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                'month' => __('payroll.invalid_month_format'),
            ]);
        }

        $startOfMonth = Carbon::parse($month.'-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Check if payroll run already exists for this month
        $existing = PayrollRun::where('month', $month)->first();
        if ($existing && in_array($existing->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID], true)) {
            throw ValidationException::withMessages([
                'month' => __('payroll.already_processed', ['month' => $month]),
            ]);
        }

        return DB::transaction(function () use ($preparer, $month, $startOfMonth, $endOfMonth, $customDeductions, $notes, $existing) {
            $payrollRun = $existing ?? new PayrollRun(['month' => $month]);
            $payrollRun->status = PayrollRun::STATUS_DRAFT;
            $payrollRun->prepared_by = $preparer->id;
            $payrollRun->notes = $notes;
            $payrollRun->save();

            // Clear old lines if updating draft
            $payrollRun->lines()->delete();

            // Active staff
            $staffMembers = Staff::query()
                ->with(['salaryProfile'])
                ->where('status', '!=', Staff::ENDED)
                ->get();

            $totalGross = 0;
            $totalAllowances = 0;
            $totalOvertime = 0;
            $totalDeductions = 0;
            $totalAdvances = 0;
            $totalNet = 0;

            foreach ($staffMembers as $staff) {
                $profile = $staff->salaryProfile;
                $baseSalary = $profile?->base_salary ?? 0;
                $allowances = $profile?->allowances ?? 0;
                $hourlyRate = $profile?->hourly_rate ?? 0;
                $paymentMethod = $profile?->payment_method ?? 'bank_transfer';

                // Calculate overtime: hours worked beyond scheduled hours in month
                $attendances = Attendance::where('staff_id', $staff->id)
                    ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->whereNotNull('clock_in')
                    ->whereNotNull('clock_out')
                    ->get();

                $overtimeHours = 0.0;
                foreach ($attendances as $att) {
                    $worked = (float) $att->hours_worked;
                    $scheduledHours = 8.0; // default standard day

                    if ($att->roster_entry_id) {
                        $roster = RosterEntry::find($att->roster_entry_id);
                        if ($roster && $roster->start_time && $roster->end_time) {
                            $schedStart = Carbon::parse($att->date->toDateString().' '.$roster->start_time);
                            $schedEnd = Carbon::parse($att->date->toDateString().' '.$roster->end_time);
                            $diff = $schedStart->diffInMinutes($schedEnd) / 60.0;
                            if ($diff > 0) {
                                $scheduledHours = $diff;
                            }
                        }
                    }

                    if ($worked > $scheduledHours) {
                        $overtimeHours += ($worked - $scheduledHours);
                    }
                }

                $overtimeHours = round($overtimeHours, 2);
                $overtimeAmount = (int) round($overtimeHours * $hourlyRate);

                // Fetch pending/approved advances for this staff scheduled for or prior to this month
                $advances = SalaryAdvance::where('staff_id', $staff->id)
                    ->where('status', SalaryAdvance::STATUS_APPROVED)
                    ->where(function ($q) use ($month) {
                        $q->whereNull('repayment_month')
                            ->orWhere('repayment_month', '<=', $month);
                    })
                    ->sum('amount');

                $deductions = (int) ($customDeductions[$staff->id]['deductions'] ?? 0);

                $gross = $baseSalary + $allowances + $overtimeAmount;
                $net = max(0, $gross - $deductions - $advances);

                PayrollLine::create([
                    'payroll_run_id' => $payrollRun->id,
                    'staff_id' => $staff->id,
                    'base_salary' => $baseSalary,
                    'allowances' => $allowances,
                    'overtime_hours' => $overtimeHours,
                    'overtime_amount' => $overtimeAmount,
                    'gross' => $gross,
                    'deductions' => $deductions,
                    'advances' => $advances,
                    'net' => $net,
                    'payment_method' => $paymentMethod,
                ]);

                $totalGross += $gross;
                $totalAllowances += $allowances;
                $totalOvertime += $overtimeAmount;
                $totalDeductions += $deductions;
                $totalAdvances += $advances;
                $totalNet += $net;
            }

            $payrollRun->update([
                'total_gross' => $totalGross,
                'total_allowances' => $totalAllowances,
                'total_overtime' => $totalOvertime,
                'total_deductions' => $totalDeductions,
                'total_advances' => $totalAdvances,
                'total_net' => $totalNet,
            ]);

            AuditLogger::record(
                action: 'payroll.prepared',
                subject: $payrollRun,
                old: [],
                new: [
                    'month' => $month,
                    'total_gross' => $totalGross,
                    'total_net' => $totalNet,
                    'lines_count' => $staffMembers->count(),
                ],
                user: $preparer,
                description: "Prepared payroll draft for {$month} (Net: ETB {$totalNet})"
            );

            return $payrollRun->fresh(['lines.staff']);
        });
    }
}

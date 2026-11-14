<?php

namespace App\Services;

use App\Models\PayrollLine;

/**
 * Generates single staff payslip PDF using standard PDF stream.
 *
 * @uc UC-42 @fr FR-10.7
 */
class PayslipPdf
{
    public static function generate(PayrollLine $line): string
    {
        $staff = $line->staff;
        $run = $line->payrollRun;

        $title = 'PAYSLIP - '.$run->month.' - '.$staff->name;

        $rows = [
            ['Description', 'Details / Amount (ETB)'],
            ['Employee Name', $staff->name],
            ['Employee Title', $staff->title ?? 'Staff Member'],
            ['Payment Month', $run->month],
            ['Payment Method', ucfirst(str_replace('_', ' ', $line->payment_method))],
            ['Status', ucfirst($run->status)],
            ['------------------', '------------------'],
            ['Base Salary', number_format($line->base_salary, 2)],
            ['Allowances', number_format($line->allowances, 2)],
            ['Overtime Hours', number_format($line->overtime_hours, 1).' hrs'],
            ['Overtime Pay', number_format($line->overtime_amount, 2)],
            ['Gross Earnings', number_format($line->gross, 2)],
            ['------------------', '------------------'],
            ['Deductions', '-'.number_format($line->deductions, 2)],
            ['Salary Advances', '-'.number_format($line->advances, 2)],
            ['------------------', '------------------'],
            ['NET PAY', 'ETB '.number_format($line->net, 2)],
        ];

        return SimplePdf::table($title, $rows);
    }
}

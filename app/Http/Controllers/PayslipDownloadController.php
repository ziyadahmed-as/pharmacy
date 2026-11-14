<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\PayrollLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayslipPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to download staff payslip PDF.
 *
 * Staff can view own payslip only; Owner/PM/Accountant can view any payslip.
 * SA has no access (BR-13).
 *
 * @uc UC-42 @fr FR-10.7 @br BR-13
 */
class PayslipDownloadController extends Controller
{
    public function __invoke(Request $request, PayrollLine $payrollLine): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user || $user->isSystemAdmin() || ! $user->canAccessBusinessData(), 403);

        $line = $payrollLine->load(['staff.user', 'payrollRun']);
        $staff = $line->staff;

        $canViewAll = $user->hasAnyRole([Role::Owner, Role::PharmacyManager]) || $user->hasPermission('payroll.manage');
        $isOwn = $staff->user_id === $user->id;

        abort_unless($canViewAll || $isOwn, 403);

        $pdf = PayslipPdf::generate($line);
        $filename = "payslip-{$line->payrollRun->month}-{$staff->id}.pdf";

        AuditLogger::record(
            action: 'payroll.payslip.downloaded',
            subject: $line,
            old: [],
            new: ['payroll_line_id' => $line->id, 'staff_id' => $staff->id, 'month' => $line->payrollRun->month],
            user: $user,
            description: "Downloaded payslip for {$staff->name} ({$line->payrollRun->month})"
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

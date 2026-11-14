<?php

use App\Actions\Staff\Payroll\PreparePayroll;
use App\Actions\Staff\Payroll\RecordPayrollPayment;
use App\Actions\Staff\Payroll\SubmitPayrollApproval;
use App\Livewire\Finance\Payroll\Index as PayrollIndex;
use App\Models\ApprovalRequest;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\RosterEntry;
use App\Models\SalaryAdvance;
use App\Models\SalaryProfile;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
});

it('calculates totals per staff member correctly including allowances and overtime', function () {
    $pm = pm();
    $staff = makeStaff(['name' => 'Kenenisa Bekele']);

    // Set salary profile: base 10,000, allowances 2,000, hourly 100
    SalaryProfile::create([
        'staff_id' => $staff->id,
        'base_salary' => 10000,
        'allowances' => 2000,
        'hourly_rate' => 100,
        'payment_method' => 'bank_transfer',
    ]);

    // Roster 8 hours, Attendance worked 10 hours => 2 hours overtime
    $month = '2026-11';
    $date = '2026-11-05';
    $roster = RosterEntry::create([
        'staff_id' => $staff->id,
        'date' => $date,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'created_by' => $pm->id,
    ]);

    Attendance::create([
        'staff_id' => $staff->id,
        'date' => $date,
        'clock_in' => Carbon::parse($date.' 08:00:00'),
        'clock_out' => Carbon::parse($date.' 18:00:00'), // 10h => 2h OT
        'roster_entry_id' => $roster->id,
        'is_absent' => false,
    ]);

    $action = app(PreparePayroll::class);
    $run = $action->execute($pm, $month);

    expect($run)->toBeInstanceOf(PayrollRun::class)
        ->and($run->status)->toBe(PayrollRun::STATUS_DRAFT)
        ->and($run->lines)->toHaveCount(1);

    $line = $run->lines->first();
    expect($line->base_salary)->toBe(10000)
        ->and($line->allowances)->toBe(2000)
        ->and((float) $line->overtime_hours)->toEqualWithDelta(2.0, 0.01)
        ->and($line->overtime_amount)->toBe(200)
        ->and($line->gross)->toBe(12200)
        ->and($line->net)->toBe(12200);
});

it('deducts approved salary advances from the net pay', function () {
    $pm = pm();
    $staff = makeStaff(['name' => 'Tirunesh Dibaba']);

    SalaryProfile::create([
        'staff_id' => $staff->id,
        'base_salary' => 15000,
        'allowances' => 1000,
        'hourly_rate' => 100,
    ]);

    // Advance 3,000 approved for repayment in 2026-11
    SalaryAdvance::create([
        'staff_id' => $staff->id,
        'amount' => 3000,
        'request_date' => '2026-10-25',
        'status' => SalaryAdvance::STATUS_APPROVED,
        'repayment_month' => '2026-11',
    ]);

    $action = app(PreparePayroll::class);
    $run = $action->execute($pm, '2026-11');

    $line = $run->lines->first();
    expect($line->gross)->toBe(16000)
        ->and($line->advances)->toBe(3000)
        ->and($line->net)->toBe(13000)
        ->and($run->total_net)->toBe(13000);
});

it('requires Owner approval when PM prepares payroll', function () {
    $pm = pm();
    $owner = owner();
    $staff = makeStaff(['name' => 'Gelete Burka']);

    SalaryProfile::create([
        'staff_id' => $staff->id,
        'base_salary' => 8000,
    ]);

    $prepare = app(PreparePayroll::class);
    $run = $prepare->execute($pm, '2026-11');

    $submit = app(SubmitPayrollApproval::class);
    $submittedRun = $submit->execute($pm, $run);

    expect($submittedRun->status)->toBe(PayrollRun::STATUS_PENDING_APPROVAL)
        ->and($submittedRun->approval_request_id)->not->toBeNull();

    $approval = ApprovalRequest::find($submittedRun->approval_request_id);
    expect($approval)->not->toBeNull()
        ->and($approval->type)->toBe('staff.payroll');

    // Owner approves with reauth
    actAs($owner);
    reauth();

    app(ApprovalService::class)->approve($approval, $owner, 'Approved by Owner');

    $submittedRun->refresh();
    expect($submittedRun->status)->toBe(PayrollRun::STATUS_APPROVED)
        ->and($submittedRun->approved_by)->toBe($owner->id);
});

it('records payment, marks advances recovered, and posts salary expense to UC-28 once', function () {
    $owner = owner();
    $staff = makeStaff(['name' => 'Sileshi Sihine']);

    SalaryProfile::create([
        'staff_id' => $staff->id,
        'base_salary' => 20000,
    ]);

    $advance = SalaryAdvance::create([
        'staff_id' => $staff->id,
        'amount' => 5000,
        'request_date' => '2026-10-15',
        'status' => SalaryAdvance::STATUS_APPROVED,
        'repayment_month' => '2026-11',
    ]);

    $prepare = app(PreparePayroll::class);
    $run = $prepare->execute($owner, '2026-11');

    // Directly approve (Owner prepared)
    $submit = app(SubmitPayrollApproval::class);
    $submit->execute($owner, $run);

    $pay = app(RecordPayrollPayment::class);
    $paidRun = $pay->execute($owner, $run);

    expect($paidRun->status)->toBe(PayrollRun::STATUS_PAID)
        ->and($paidRun->expense_id)->not->toBeNull()
        ->and($paidRun->paid_by)->toBe($owner->id);

    // Verify Expense posted to UC-28
    $expense = Expense::find($paidRun->expense_id);
    expect($expense)->not->toBeNull()
        ->and($expense->category)->toBe(Expense::CATEGORY_SALARIES)
        ->and($expense->amount)->toBe(15000)
        ->and($expense->status)->toBe(Expense::STATUS_POSTED);

    // Advance marked as recovered
    $advance->refresh();
    expect($advance->status)->toBe(SalaryAdvance::STATUS_RECOVERED)
        ->and($advance->payroll_run_id)->toBe($paidRun->id);

    // Cannot pay twice
    expect(fn () => $pay->execute($owner, $paidRun))
        ->toThrow(ValidationException::class);
});

it('allows staff to download only their own payslip while manager can download any', function () {
    $staffUser1 = ps();
    $staff1 = makeStaff(['name' => 'Staff Alice', 'user_id' => $staffUser1->id]);

    $staffUser2 = ps();
    $staff2 = makeStaff(['name' => 'Staff Bob', 'user_id' => $staffUser2->id]);

    SalaryProfile::create(['staff_id' => $staff1->id, 'base_salary' => 9000]);
    SalaryProfile::create(['staff_id' => $staff2->id, 'base_salary' => 9500]);

    $owner = owner();
    $prepare = app(PreparePayroll::class);
    $run = $prepare->execute($owner, '2026-11');
    app(SubmitPayrollApproval::class)->execute($owner, $run);

    $line1 = $run->lines->firstWhere('staff_id', $staff1->id);
    $line2 = $run->lines->firstWhere('staff_id', $staff2->id);

    // Staff 1 can download own payslip
    actAs($staffUser1);
    $response1 = $this->get(route('payroll.payslip', $line1->id));
    $response1->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Staff 1 CANNOT download Staff 2 payslip
    $response2 = $this->get(route('payroll.payslip', $line2->id));
    $response2->assertForbidden();

    // Owner can download both
    actAs($owner);
    $this->get(route('payroll.payslip', $line1->id))->assertOk();
    $this->get(route('payroll.payslip', $line2->id))->assertOk();
});

it('strictly denies pay data access to System Administrator and unauthorized roles', function () {
    $admin = sa();
    actAs($admin);

    $this->get(route('payroll.index'))->assertForbidden();

    $pm = pm();
    $staff = makeStaff(['name' => 'Secret Staff']);
    SalaryProfile::create(['staff_id' => $staff->id, 'base_salary' => 12000]);
    $run = app(PreparePayroll::class)->execute($pm, '2026-11');
    $line = $run->lines->first();

    $this->get(route('payroll.payslip', $line->id))->assertForbidden();

    // System Admin cannot prepare payroll directly
    expect(fn () => app(PreparePayroll::class)->execute($admin, '2026-12'))
        ->toThrow(AuthorizationException::class);
});

it('renders livewire index showing own payslips to staff and full management to manager', function () {
    $staffUser = ps();
    $staff = makeStaff(['name' => 'Test Staff', 'user_id' => $staffUser->id]);
    SalaryProfile::create(['staff_id' => $staff->id, 'base_salary' => 11000]);

    $owner = owner();
    $run = app(PreparePayroll::class)->execute($owner, '2026-11');
    app(SubmitPayrollApproval::class)->execute($owner, $run);

    // Staff sees own payslips
    actAs($staffUser);
    Livewire::test(PayrollIndex::class)
        ->assertSee('My Payslips')
        ->assertSee('2026-11')
        ->assertDontSee('Prepare Payroll');

    // Manager sees payroll management
    $pm = pm();
    actAs($pm);
    Livewire::test(PayrollIndex::class)
        ->assertSee('Prepare Payroll')
        ->assertSee('2026-11');
});

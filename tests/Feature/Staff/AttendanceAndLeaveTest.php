<?php

use App\Actions\Staff\Attendance\ClockIn;
use App\Actions\Staff\Attendance\ClockOut;
use App\Actions\Staff\Attendance\RecordDailyAbsences;
use App\Actions\Staff\Leave\SubmitLeaveRequest;
use App\Livewire\Staff\Attendance\Index as AttendanceIndex;
use App\Models\ApprovalRequest;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Reports\Definitions\Staff\MonthlyAttendanceReport;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    $this->artisan('db:seed', ['--class' => 'LeaveTypeSeeder']);
});

it('calculates lateness against roster entry on clock in', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Abebe Bikila', 'user_id' => $user->id]);

    $today = now()->toDateString();
    RosterEntry::create([
        'staff_id' => $staff->id,
        'date' => $today,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'created_by' => $user->id,
    ]);

    $clockInTime = Carbon::parse($today.' 08:35:00');

    $action = app(ClockIn::class);
    $attendance = $action->execute($staff, $user, $clockInTime);

    expect($attendance)->toBeInstanceOf(Attendance::class)
        ->and($attendance->lateness_minutes)->toBe(35)
        ->and($attendance->is_absent)->toBeFalse();
});

it('prevents clocking in twice on the same day', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Sara Desta', 'user_id' => $user->id]);

    $action = app(ClockIn::class);
    $action->execute($staff, $user, now());

    expect(fn () => $action->execute($staff, $user, now()))
        ->toThrow(ValidationException::class);
});

it('calculates hours worked when clocking out', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Dawit Kebede', 'user_id' => $user->id]);

    $date = now()->toDateString();
    $inTime = Carbon::parse($date.' 08:00:00');
    $outTime = Carbon::parse($date.' 16:30:00');

    $clockIn = app(ClockIn::class);
    $clockIn->execute($staff, $user, $inTime);

    $clockOut = app(ClockOut::class);
    $attendance = $clockOut->execute($staff, $user, $outTime);

    expect($attendance->clock_out)->not->toBeNull()
        ->and($attendance->hours_worked)->toEqualWithDelta(8.5, 0.01);
});

it('prevents clocking out without clocking in first or twice', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Almaz Ayana', 'user_id' => $user->id]);

    $clockOut = app(ClockOut::class);

    expect(fn () => $clockOut->execute($staff, $user, now()))
        ->toThrow(ValidationException::class);

    $clockIn = app(ClockIn::class);
    $clockIn->execute($staff, $user, now());
    $clockOut->execute($staff, $user, now());

    expect(fn () => $clockOut->execute($staff, $user, now()))
        ->toThrow(ValidationException::class);
});

it('records unexcused absence for scheduled staff who did not clock in', function () {
    $creator = pm();
    $staff = makeStaff(['name' => 'Chala Tola']);
    $date = '2026-11-10';

    RosterEntry::create([
        'staff_id' => $staff->id,
        'date' => $date,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'created_by' => $creator->id,
    ]);

    $action = app(RecordDailyAbsences::class);
    $res = $action->execute(Carbon::parse($date));

    expect($res['absences_recorded'])->toBe(1)
        ->and($res['on_leave'])->toBe(0);

    $att = Attendance::where('staff_id', $staff->id)->whereDate('date', $date)->first();
    expect($att)->not->toBeNull()
        ->and($att->is_absent)->toBeTrue()
        ->and($att->absence_reason)->toBe('unexcused');
});

it('flags staff on approved leave as on_leave rather than unexcused', function () {
    $creator = pm();
    $staff = makeStaff(['name' => 'Hanna Tesfaye']);
    $date = '2026-11-12';
    $leaveType = LeaveType::where('code', 'annual')->firstOrFail();

    LeaveRequest::create([
        'staff_id' => $staff->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-15',
        'days' => 6,
        'status' => LeaveRequest::STATUS_APPROVED,
        'is_over_balance' => false,
    ]);

    RosterEntry::create([
        'staff_id' => $staff->id,
        'date' => $date,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'created_by' => $creator->id,
    ]);

    $action = app(RecordDailyAbsences::class);
    $res = $action->execute(Carbon::parse($date));

    expect($res['on_leave'])->toBe(1)
        ->and($res['absences_recorded'])->toBe(0);

    $att = Attendance::where('staff_id', $staff->id)->whereDate('date', $date)->first();
    expect($att->is_absent)->toBeTrue()
        ->and($att->absence_reason)->toBe('on_leave');
});

it('submits leave request and triggers approval workflow', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Tigist Assefa', 'user_id' => $user->id]);
    $leaveType = LeaveType::where('code', 'annual')->firstOrFail();

    $action = app(SubmitLeaveRequest::class);
    $leaveRequest = $action->execute($staff, $user, $leaveType, '2026-12-01', '2026-12-03', 'Family trip');

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING)
        ->and($leaveRequest->days)->toBe(3)
        ->and($leaveRequest->is_over_balance)->toBeFalse()
        ->and($leaveRequest->approval_request_id)->not->toBeNull();

    $approval = ApprovalRequest::find($leaveRequest->approval_request_id);
    expect($approval)->not->toBeNull()
        ->and($approval->type)->toBe('staff.leave')
        ->and($approval->initiator_id)->toBe($user->id);
});

it('flags over-balance leave request to the manager (E1)', function () {
    $user = ps();
    $staff = makeStaff(['name' => 'Kenenisa Bekele', 'user_id' => $user->id]);
    $leaveType = LeaveType::where('code', 'annual')->firstOrFail(); // default 16 days

    // Submit 20 days
    $action = app(SubmitLeaveRequest::class);
    $leaveRequest = $action->execute($staff, $user, $leaveType, '2026-12-01', '2026-12-20', 'Long vacation');

    expect($leaveRequest->is_over_balance)->toBeTrue();

    $approval = ApprovalRequest::find($leaveRequest->approval_request_id);
    expect($approval->summary)->toContain('[OVER-BALANCE');
});

it('updates leave balance and increments used_days on approval', function () {
    $staffUser = ps();
    $staff = makeStaff(['name' => 'Derartu Tulu', 'user_id' => $staffUser->id]);
    $manager = pm();
    $leaveType = LeaveType::where('code', 'annual')->firstOrFail();

    $action = app(SubmitLeaveRequest::class);
    $leaveRequest = $action->execute($staff, $staffUser, $leaveType, '2026-12-01', '2026-12-05');

    $approval = ApprovalRequest::find($leaveRequest->approval_request_id);

    actAs($manager);
    reauth();

    app(ApprovalService::class)->approve($approval, $manager, 'Approved by PM');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($leaveRequest->approved_by)->toBe($manager->id);

    $balance = LeaveBalance::where('staff_id', $staff->id)
        ->where('leave_type_id', $leaveType->id)
        ->where('year', 2026)
        ->first();

    expect($balance)->not->toBeNull()
        ->and($balance->used_days)->toBe(5)
        ->and($balance->remaining_days)->toBe($leaveType->days_per_year - 5);
});

it('does not alter balance when leave request is rejected', function () {
    $staffUser = ps();
    $staff = makeStaff(['name' => 'Haile Gebrselassie', 'user_id' => $staffUser->id]);
    $manager = pm();
    $leaveType = LeaveType::where('code', 'annual')->firstOrFail();

    $action = app(SubmitLeaveRequest::class);
    $leaveRequest = $action->execute($staff, $staffUser, $leaveType, '2026-12-01', '2026-12-02');

    $approval = ApprovalRequest::find($leaveRequest->approval_request_id);

    actAs($manager);
    reauth();

    app(ApprovalService::class)->reject($approval, $manager, 'Staffing constraint');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_REJECTED)
        ->and($leaveRequest->rejection_reason)->toBe('Staffing constraint');

    $balance = LeaveBalance::where('staff_id', $staff->id)
        ->where('leave_type_id', $leaveType->id)
        ->where('year', 2026)
        ->first();

    expect($balance->used_days)->toBe(0);
});

it('enforces own-data visibility for staff while manager sees all', function () {
    $staffUser1 = ps();
    $staff1 = makeStaff(['name' => 'Staff One', 'user_id' => $staffUser1->id]);

    $staffUser2 = ps();
    $staff2 = makeStaff(['name' => 'Staff Two', 'user_id' => $staffUser2->id]);

    $today = now()->toDateString();
    Attendance::create([
        'staff_id' => $staff1->id,
        'date' => $today,
        'clock_in' => now()->subHours(4),
        'clock_out' => now(),
    ]);

    Attendance::create([
        'staff_id' => $staff2->id,
        'date' => $today,
        'clock_in' => now()->subHours(2),
    ]);

    // Staff 1 sees only own attendance
    actAs($staffUser1);
    Livewire::test(AttendanceIndex::class)
        ->assertSee('Staff One')
        ->assertDontSee('Staff Two');

    // Manager sees both
    $manager = pm();
    actAs($manager);
    Livewire::test(AttendanceIndex::class)
        ->assertSee('Staff One')
        ->assertSee('Staff Two');
});

it('computes monthly attendance report totals accurately', function () {
    $staff = makeStaff(['name' => 'Report Staff']);
    $manager = pm();
    $month = now()->format('Y-m');

    // Create 1 attended, 1 late, 1 absent
    Attendance::create([
        'staff_id' => $staff->id,
        'date' => now()->startOfMonth()->toDateString(),
        'clock_in' => now()->startOfMonth()->setHour(8),
        'clock_out' => now()->startOfMonth()->setHour(16),
        'lateness_minutes' => 0,
        'is_absent' => false,
    ]);

    Attendance::create([
        'staff_id' => $staff->id,
        'date' => now()->startOfMonth()->addDay()->toDateString(),
        'clock_in' => now()->startOfMonth()->addDay()->setHour(8)->setMinute(20),
        'clock_out' => now()->startOfMonth()->addDay()->setHour(16),
        'lateness_minutes' => 20,
        'is_absent' => false,
    ]);

    Attendance::create([
        'staff_id' => $staff->id,
        'date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'is_absent' => true,
        'absence_reason' => 'unexcused',
    ]);

    $report = new MonthlyAttendanceReport;
    $result = $report->query([
        'from_date' => now()->startOfMonth()->toDateString(),
        'to_date' => now()->endOfMonth()->toDateString(),
    ], $manager);

    expect($result['rows'])->not->toBeEmpty();
    $row = collect($result['rows'])->firstWhere(0, 'Report Staff');
    expect($row)->not->toBeNull()
        ->and($row[2])->toBe(2)
        ->and($row[4])->toBe(1)
        ->and($row[5])->toBe(20)
        ->and($row[6])->toBe(1);
});

it('denies system administrator from accessing attendance and leave routes', function () {
    $admin = sa();
    actAs($admin);

    $this->get(route('attendance.index'))->assertForbidden();
    $this->get(route('attendance.leave'))->assertForbidden();
});

it('runs the record-daily-absences console command successfully', function () {
    $creator = pm();
    $staff = makeStaff(['name' => 'Command Staff']);
    $date = '2026-11-13';

    RosterEntry::create([
        'staff_id' => $staff->id,
        'date' => $date,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'created_by' => $creator->id,
    ]);

    $this->artisan('pms:record-daily-absences', ['--date' => $date])
        ->assertSuccessful();

    $att = Attendance::where('staff_id', $staff->id)->whereDate('date', $date)->first();
    expect($att)->not->toBeNull()
        ->and($att->is_absent)->toBeTrue();
});

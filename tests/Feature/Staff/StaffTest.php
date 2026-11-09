<?php

use App\Actions\Staff\AddOrRenewLicence;
use App\Actions\Staff\EndEmployment;
use App\Actions\Staff\SaveStaffRecord;
use App\Livewire\Staff\Edit;
use App\Livewire\Staff\Index;
use App\Livewire\Staff\Show;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Services\LicenceGate;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** UC-39 Manage staff record and licence. FR-10.1..10.3, 9.1; BR-12, BR-13; AC-13, AC-32. */
beforeEach(fn () => seedRoles());

it('lets the PM and the Owner create and edit a staff record', function () {
    foreach ([pm(), owner()] as $manager) {
        actAs($manager);
        Livewire::test(Edit::class)->set('form.name', 'Hana Bekele')->set('form.title', 'Pharmacist')->set('form.id_number', 'ET-12345')->call('save')->assertHasNoErrors();
    }
    $staff = Staff::where('name', 'Hana Bekele')->first();
    actAs(pm());
    Livewire::test(Edit::class, ['staff' => $staff])->set('form.title', 'Senior pharmacist')->call('save')->assertHasNoErrors();

    expect($staff->refresh()->title)->toBe('Senior pharmacist')->and($staff->id_number)->toBe('ET-12345'); // blank keeps the stored number
    expect(AuditLog::where('action', 'staff.staff.created')->count())->toBeGreaterThanOrEqual(2);
    expect(AuditLog::where('action', 'staff.staff.updated')->exists())->toBeTrue();
});

it('validates the staff form', function () {
    actAs(pm());
    Livewire::test(Edit::class)->set('form.name', '')->call('save')->assertHasErrors(['form.name']);
});

it('encrypts the ID number in the database and masks it in the audit trail', function () {
    $staff = makeStaff(['id_number' => 'SECRET-ID-777']);

    expect(DB::table('staff')->where('id', $staff->id)->value('id_number'))->not->toContain('SECRET-ID-777');
    expect($staff->refresh()->id_number)->toBe('SECRET-ID-777');
    $log = AuditLog::where('action', 'staff.staff.created')->latest('id')->first();
    expect(json_encode($log->new_values))->not->toContain('SECRET-ID-777')->and($log->new_values['id_number'])->toBe('[masked]');
});

it('shows a pharmacy_staff user only their own record, without the ID number (BR-13)', function () {
    $me = ps();
    $mine = makeStaff(['user_id' => $me->id, 'name' => 'My Own Record', 'id_number' => 'MINE-999']);
    $other = makeStaff(['name' => 'Someone Else']);
    actAs($me);

    Livewire::test(Index::class)->assertSee('My Own Record')->assertDontSee('Someone Else');
    $this->get(route('staff.show', $mine))->assertOk()->assertDontSee('MINE-999');
    $this->get(route('staff.show', $other))->assertForbidden();
    $this->get(route('staff.create'))->assertForbidden();
    $this->get(route('staff.edit', $mine))->assertForbidden();
});

it('lets the manager see the ID number', function () {
    $staff = makeStaff(['id_number' => 'VISIBLE-42']);
    actAs(owner());

    $this->get(route('staff.show', $staff))->assertOk()->assertSee('VISIBLE-42');
});

it('denies the System Administrator every staff screen, also by URL', function () {
    $staff = makeStaff();
    actAs(sa());

    foreach ([route('staff.index'), route('staff.create'), route('staff.show', $staff), route('staff.edit', $staff), route('staff.photo', $staff)] as $url) {
        $this->get($url)->assertForbidden();
    }
    expect(fn () => app(SaveStaffRecord::class)->execute(auth()->user(), ['name' => 'x', 'status' => 'active']))->toThrow(AuthorizationException::class);
});

it('checks the licence validity cases', function () {
    $staff = makeStaff();
    expect($staff->hasValidLicence())->toBeFalse(); // none

    $add = fn (string $expires) => app(AddOrRenewLicence::class)->execute(pm(), $staff, ['number' => 'L-'.$expires, 'issuing_body' => 'FMHACA', 'expires_at' => $expires]);

    $add(now()->subDay()->toDateString());
    expect($staff->hasValidLicence())->toBeFalse(); // expired yesterday

    $add(now()->timezone(config('pms.timezone'))->toDateString());
    expect($staff->hasValidLicence())->toBeTrue(); // expires today is still valid today
    expect($staff->hasValidLicence(now()->addDay()))->toBeFalse();

    $add(now()->addYear()->toDateString()); // renewed
    expect($staff->hasValidLicence(now()->addDays(30)))->toBeTrue();
    expect($staff->licences()->count())->toBe(3); // history kept
});

it('LicenceGate reads the licence, not the role, and honours the category setting (BR-12)', function () {
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);
    $gate = app(LicenceGate::class);

    expect($gate->allows($user, 'prescription.dispense'))->toBeFalse(); // no licence

    app(AddOrRenewLicence::class)->execute(pm(), $staff, ['number' => 'L1', 'issuing_body' => 'FMHACA', 'category' => 'Technician', 'expires_at' => now()->addYear()->toDateString()]);
    expect($gate->allows($user->refresh(), 'prescription.dispense'))->toBeTrue(); // empty category setting = any valid licence

    app(SettingsRepository::class)->put('prescriptions.licence_categories_allowed', ['Pharmacist']);
    expect($gate->allows($user->refresh(), 'prescription.dispense'))->toBeFalse();

    app(AddOrRenewLicence::class)->execute(pm(), $staff, ['number' => 'L2', 'issuing_body' => 'FMHACA', 'category' => 'pharmacist', 'expires_at' => now()->addYear()->toDateString()]);
    expect($gate->allows($user->refresh(), 'prescription.dispense'))->toBeTrue();

    $staff->forceFill(['status' => Staff::ENDED])->save();
    expect($gate->allows($user->refresh(), 'prescription.dispense'))->toBeFalse();
    expect($gate->allows(pm(), 'prescription.dispense'))->toBeFalse(); // a manager role alone is not a licence
});

it('validates a licence and requires manage permission', function () {
    $staff = makeStaff();
    actAs(pm());
    Livewire::test(Show::class, ['staff' => $staff])->set('lic_number', '')->call('addLicence')->assertHasErrors(['lic_number', 'lic_body', 'lic_expires']);

    expect(fn () => app(AddOrRenewLicence::class)->execute(ps(), $staff, ['number' => 'x', 'issuing_body' => 'y', 'expires_at' => '2030-01-01']))->toThrow(AuthorizationException::class);
});

it('ends employment: status Ended, linked user deactivated and sessions ended (AC-32)', function () {
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    app(EndEmployment::class)->execute(pm(), $staff, null, 'resigned');

    expect($staff->refresh()->status)->toBe('ended')->and($staff->end_date)->not->toBeNull();
    expect($user->refresh()->is_active)->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(AuditLog::where('action', 'staff.staff.employment_ended')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'user.account.deactivated')->exists())->toBeTrue();
    expect(Staff::count())->toBe(1); // never deleted

    // an ended record cannot be edited
    expect(fn () => app(SaveStaffRecord::class)->execute(pm(), ['name' => 'x', 'status' => 'active'], $staff))->toThrow(ValidationException::class);
});

it('creates licence alerts once per licence and threshold (idempotent)', function () {
    $mgr = pm();
    $own = owner();
    $soon = makeStaff(['name' => 'Soon Expiring']);
    $gone = makeStaff(['name' => 'Already Expired']);
    $fine = makeStaff(['name' => 'Fine Person']);
    $lic = fn ($s, $d) => app(AddOrRenewLicence::class)->execute($mgr, $s, ['number' => 'L', 'issuing_body' => 'B', 'expires_at' => $d]);
    $lic($soon, now()->addDays(20)->toDateString());
    $lic($gone, now()->subDays(3)->toDateString());
    $lic($fine, now()->addDays(300)->toDateString());

    $this->artisan('staff:licence-alerts')->assertSuccessful();
    $first = DB::table('notifications')->count();
    expect($first)->toBe(4); // 2 licences x (PM + Owner)

    $this->artisan('staff:licence-alerts')->assertSuccessful();
    expect(DB::table('notifications')->count())->toBe($first);

    $body = DB::table('notifications')->pluck('data')->join(' ');
    expect($body)->toContain('Soon Expiring')->toContain('Already Expired')->not->toContain('Fine Person');
});

it('flags an expired licence on the list (E1) and filters expiring ones', function () {
    $staff = makeStaff(['name' => 'Expired Person']);
    app(AddOrRenewLicence::class)->execute(pm(), $staff, ['number' => 'L', 'issuing_body' => 'B', 'expires_at' => now()->subDay()->toDateString()]);
    makeStaff(['name' => 'No Licence Person']);
    actAs(pm());

    Livewire::test(Index::class)->assertSee('Expired')->set('expiringOnly', true)->assertSee('Expired Person')->assertDontSee('No Licence Person');
});

it('never offers the photo of a staff record to someone who may not view it', function () {
    Storage::fake('local');
    Storage::disk('local')->put('staff-photos/p.png', 'x');
    $staff = makeStaff(['photo_path' => 'staff-photos/p.png']);

    actAs(ps());
    $this->get(route('staff.photo', $staff))->assertForbidden();
    actAs(pm());
    $this->get(route('staff.photo', $staff))->assertOk();
});

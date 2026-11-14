<?php

use App\Actions\Staff\DeleteRosterEntry;
use App\Actions\Staff\SaveRosterEntry;
use App\Livewire\Dashboard;
use App\Livewire\Staff\Roster\Index;
use App\Livewire\Staff\Roster\OnDutyToday;
use App\Models\AuditLog;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\Till;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * UC-40 Manage roster and shifts.
 *
 * @uc UC-40 @fr FR-10.4
 */
beforeEach(function () {
    seedRoles();
});

it('lets the PM and Owner save a roster shift assignment', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Abebe Bikila']);
    $till = Till::create(['name' => 'Till 1', 'is_active' => true]);

    actAs($manager);

    $action = app(SaveRosterEntry::class);
    $entry = $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'till_id' => $till->id,
        'note' => 'Morning cash shift',
    ]);

    expect($entry)->toBeInstanceOf(RosterEntry::class)
        ->and($entry->staff_id)->toBe($staff->id)
        ->and($entry->date->toDateString())->toBe('2026-11-16')
        ->and($entry->formatted_start_time)->toBe('08:00')
        ->and($entry->formatted_end_time)->toBe('16:00')
        ->and($entry->till_id)->toBe($till->id)
        ->and($entry->note)->toBe('Morning cash shift');

    // Audit log written
    expect(AuditLog::where('action', 'roster.saved')->where('auditable_id', (string) $entry->id)->exists())->toBeTrue();
});

it('refuses a second cash shift on the same till at overlapping times (E1)', function () {
    $manager = pm();
    $staff1 = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Staff One']);
    $staff2 = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Staff Two']);
    $till = Till::create(['name' => 'Till 1', 'is_active' => true]);

    actAs($manager);
    $action = app(SaveRosterEntry::class);

    // Initial shift: 08:00 - 14:00 on Till 1
    $action->execute($manager, [
        'staff_id' => $staff1->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '14:00',
        'till_id' => $till->id,
    ]);

    // Attempt overlapping shift on same till: 12:00 - 18:00 (overlaps between 12:00 and 14:00)
    expect(fn () => $action->execute($manager, [
        'staff_id' => $staff2->id,
        'date' => '2026-11-16',
        'start_time' => '12:00',
        'end_time' => '18:00',
        'till_id' => $till->id,
    ]))->toThrow(ValidationException::class);

    // Contiguous shift: 14:00 - 20:00 on same till does NOT overlap and must succeed
    $shift2 = $action->execute($manager, [
        'staff_id' => $staff2->id,
        'date' => '2026-11-16',
        'start_time' => '14:00',
        'end_time' => '20:00',
        'till_id' => $till->id,
    ]);

    expect($shift2)->toBeInstanceOf(RosterEntry::class);
});

it('warns when a staff member is scheduled twice at once', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Busy Staff']);
    $till1 = Till::create(['name' => 'Till 1', 'is_active' => true]);
    $till2 = Till::create(['name' => 'Till 2', 'is_active' => true]);

    actAs($manager);
    $action = app(SaveRosterEntry::class);

    // First shift: 09:00 - 17:00
    $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'till_id' => $till1->id,
    ]);

    // Overlapping shift for same staff without allow_staff_overlap throws ValidationException
    expect(fn () => $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '13:00',
        'end_time' => '21:00',
        'till_id' => $till2->id,
        'allow_staff_overlap' => false,
    ]))->toThrow(ValidationException::class);

    // With allow_staff_overlap confirmed, it succeeds
    $second = $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '13:00',
        'end_time' => '21:00',
        'till_id' => $till2->id,
        'allow_staff_overlap' => true,
    ]);

    expect($second)->toBeInstanceOf(RosterEntry::class);
});

it('cannot assign ended or on-leave staff', function () {
    $manager = pm();
    $endedStaff = makeStaff(['status' => Staff::ENDED, 'name' => 'Ended Staff']);
    $onLeaveStaff = makeStaff(['status' => Staff::ON_LEAVE, 'name' => 'On Leave Staff']);

    actAs($manager);
    $action = app(SaveRosterEntry::class);

    // Attempt assigning ended staff
    expect(fn () => $action->execute($manager, [
        'staff_id' => $endedStaff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]))->toThrow(ValidationException::class);

    // Attempt assigning on-leave staff
    expect(fn () => $action->execute($manager, [
        'staff_id' => $onLeaveStaff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]))->toThrow(ValidationException::class);
});

it('allows PM and Owner to edit an existing roster entry', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Abebe']);
    $till1 = Till::create(['name' => 'Till 1', 'is_active' => true]);
    $till2 = Till::create(['name' => 'Till 2', 'is_active' => true]);

    actAs($manager);
    $action = app(SaveRosterEntry::class);

    $entry = $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'till_id' => $till1->id,
    ]);

    // PM updates shift to Till 2 with new hours
    $updated = $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'till_id' => $till2->id,
        'note' => 'Shift moved to Till 2',
    ], $entry);

    expect($updated->formatted_start_time)->toBe('09:00')
        ->and($updated->formatted_end_time)->toBe('17:00')
        ->and($updated->till_id)->toBe($till2->id)
        ->and($updated->note)->toBe('Shift moved to Till 2');

    expect(AuditLog::where('action', 'roster.updated')->where('auditable_id', (string) $entry->id)->exists())->toBeTrue();
});

it('allows PM to delete a roster entry with audit trail', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Abebe']);
    actAs($manager);

    $entry = app(SaveRosterEntry::class)->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    app(DeleteRosterEntry::class)->execute($manager, $entry);

    expect(RosterEntry::find($entry->id))->toBeNull();
    expect(AuditLog::where('action', 'roster.deleted')->where('auditable_id', (string) $entry->id)->exists())->toBeTrue();
});

it('lets staff view only their own shifts in read-only mode', function () {
    $userMe = ps();
    $myStaff = makeStaff(['user_id' => $userMe->id, 'name' => 'My Pharmacist Profile', 'status' => Staff::ACTIVE]);

    $otherUser = ps();
    $otherStaff = makeStaff(['user_id' => $otherUser->id, 'name' => 'Other Staff Member', 'status' => Staff::ACTIVE]);

    $manager = pm();
    actAs($manager);
    $action = app(SaveRosterEntry::class);

    // Schedule both staff members
    $action->execute($manager, [
        'staff_id' => $myStaff->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);
    $action->execute($manager, [
        'staff_id' => $otherStaff->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    // Act as pharmacy staff
    actAs($userMe);

    // Visit Livewire roster component
    Livewire::test(Index::class)
        ->assertSee('My Pharmacist Profile')
        ->assertDontSee('Other Staff Member')
        ->assertDontSee(__('roster.add_shift'));

    // Staff cannot call create or edit modals
    Livewire::test(Index::class)->call('openCreateModal')->assertForbidden();
});

it('supports repeating shift assignment for a week or month', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Weekly Staff']);
    actAs($manager);

    $action = app(SaveRosterEntry::class);
    $entries = $action->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16', // Monday
        'repeat_until' => '2026-11-20', // Friday
        'repeat_days' => [1, 2, 3, 4, 5], // Mon-Fri
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    expect($entries)->toHaveCount(5);
    expect(RosterEntry::where('staff_id', $staff->id)->count())->toBe(5);
});

it('displays on duty today widget on the dashboard for business users', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE, 'name' => 'Today Hero Staff']);
    $till = Till::create(['name' => 'Till Express', 'is_active' => true]);

    actAs($manager);
    app(SaveRosterEntry::class)->execute($manager, [
        'staff_id' => $staff->id,
        'date' => today()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '16:00',
        'till_id' => $till->id,
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee(__('roster.on_duty_today'))
        ->assertSee('Today Hero Staff')
        ->assertSee('Till Express')
        ->assertSee('08:00')
        ->assertSee('16:00');

    Livewire::test(OnDutyToday::class)
        ->assertSee('Today Hero Staff')
        ->assertSee('Till Express');
});

it('denies the System Administrator access to roster screens and actions', function () {
    $admin = sa();
    actAs($admin);

    $this->get(route('roster.index'))->assertForbidden();

    Livewire::test(Index::class)->assertForbidden();

    $staff = makeStaff(['status' => Staff::ACTIVE]);
    expect(fn () => app(SaveRosterEntry::class)->execute($admin, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]))->toThrow(AuthorizationException::class);
});

it('validates start and end time constraints', function () {
    $manager = pm();
    $staff = makeStaff(['status' => Staff::ACTIVE]);
    actAs($manager);

    // End time before start time
    expect(fn () => app(SaveRosterEntry::class)->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '16:00',
        'end_time' => '08:00',
    ]))->toThrow(ValidationException::class);

    // End time equal to start time
    expect(fn () => app(SaveRosterEntry::class)->execute($manager, [
        'staff_id' => $staff->id,
        'date' => '2026-11-16',
        'start_time' => '12:00',
        'end_time' => '12:00',
    ]))->toThrow(ValidationException::class);
});

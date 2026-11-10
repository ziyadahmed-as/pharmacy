<?php

use App\Actions\Shifts\OpenShift;
use App\Exceptions\NoOpenShiftException;
use App\Exceptions\ShiftAlreadyOpenException;
use App\Exceptions\TillInactiveException;
use App\Exceptions\UserAlreadyHasOpenShiftException;
use App\Livewire\Shifts\Open;
use App\Livewire\Tills\Index;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\Till;
use App\Services\CurrentShift;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * UC-12 Open shift.
 * FR-3.9, FR-10.4; BR-01; AC-24.
 */
beforeEach(fn () => seedRoles());

it('opens a shift recording user, till, time and float (AC-24)', function () {
    $user = ps();
    $till = Till::factory()->create(['name' => 'Counter 1', 'is_active' => true]);

    $shift = app(OpenShift::class)->execute(
        user: $user,
        till: $till,
        openingFloatSantim: 25000 // 250.00 ETB
    );

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->till_id)->toBe($till->id)
        ->and($shift->user_id)->toBe($user->id)
        ->and($shift->status)->toBe('open')
        ->and($shift->opening_float)->toBe(25000)
        ->and($shift->opened_at)->not->toBeNull()
        ->and($shift->isOpen())->toBeTrue();

    expect(CurrentShift::forUser($user)?->id)->toBe($shift->id);
    expect(CurrentShift::forTill($till)?->id)->toBe($shift->id);
});

it('records an audit log entry shift.opened with till, user and opening float', function () {
    $user = ps();
    $till = Till::factory()->create(['name' => 'Counter 2']);

    $shift = app(OpenShift::class)->execute(
        user: $user,
        till: $till,
        openingFloatSantim: 50000
    );

    $log = AuditLog::where('action', 'shift.opened')
        ->where('auditable_type', Shift::class)
        ->where('auditable_id', $shift->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->new_values['till_id'])->toBe($till->id)
        ->and($log->new_values['till_name'])->toBe('Counter 2')
        ->and($log->new_values['opening_float'])->toBe(50000);
});

it('refuses a second open shift on the same till with a clear message (AC-24, E1)', function () {
    $staff1 = ps();
    $staff2 = ps();
    $till = Till::factory()->create(['name' => 'Counter 3']);

    app(OpenShift::class)->execute($staff1, $till, 10000);

    expect(fn () => app(OpenShift::class)->execute($staff2, $till, 10000))
        ->toThrow(ShiftAlreadyOpenException::class, __('shifts.till_already_has_open_shift', ['till' => 'Counter 3']));
});

it('refuses a second open shift for the same user (E1)', function () {
    $user = ps();
    $till1 = Till::factory()->create(['name' => 'Counter 1']);
    $till2 = Till::factory()->create(['name' => 'Counter 2']);

    app(OpenShift::class)->execute($user, $till1, 10000);

    expect(fn () => app(OpenShift::class)->execute($user, $till2, 10000))
        ->toThrow(UserAlreadyHasOpenShiftException::class, __('shifts.user_already_has_open_shift'));
});

it('refuses opening a shift on an inactive till', function () {
    $user = ps();
    $till = Till::factory()->create(['name' => 'Counter Inactive', 'is_active' => false]);

    expect(fn () => app(OpenShift::class)->execute($user, $till, 10000))
        ->toThrow(TillInactiveException::class);
});

it('rejects a negative opening float', function () {
    $user = ps();
    $till = Till::factory()->create();

    expect(fn () => app(OpenShift::class)->execute($user, $till, -500))
        ->toThrow(InvalidArgumentException::class);
});

it('denies users without shifts.open permission (e.g. System Admin)', function () {
    $admin = sa();
    $till = Till::factory()->create();

    expect(fn () => app(OpenShift::class)->execute($admin, $till, 10000))
        ->toThrow(AuthorizationException::class);
});

it('CurrentShift helper throws NoOpenShiftException when no shift is open', function () {
    $user = ps();

    expect(CurrentShift::forUser($user))->toBeNull();
    expect(CurrentShift::hasOpenShift($user))->toBeFalse();

    expect(fn () => CurrentShift::requireForUser($user))
        ->toThrow(NoOpenShiftException::class, __('shifts.no_open_shift_error'));
});

it('runs Shifts\\Open Livewire component to open a shift', function () {
    $user = ps();
    $till = Till::factory()->create(['name' => 'Express Till', 'is_active' => true]);

    actAs($user);

    Livewire::test(Open::class)
        ->assertSee('Express Till')
        ->set('selectedTillId', $till->id)
        ->set('openingFloat', '150.50')
        ->call('openShift')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $shift = Shift::where('till_id', $till->id)->first();
    expect($shift)->not->toBeNull()
        ->and($shift->opening_float)->toBe(15050)
        ->and($shift->user_id)->toBe($user->id);
});

it('shows occupied status in till picker and sets errorMessage when attempting to open occupied till', function () {
    $user1 = ps();
    $user2 = ps();
    $till = Till::factory()->create(['name' => 'Occupied Till']);

    app(OpenShift::class)->execute($user1, $till, 10000);

    actAs($user2);

    Livewire::test(Open::class)
        ->assertSee('Occupied Till')
        ->assertSee(__('shifts.occupied'))
        ->set('selectedTillId', $till->id)
        ->set('openingFloat', '100')
        ->call('openShift')
        ->assertSee(__('shifts.till_already_has_open_shift', ['till' => 'Occupied Till']));
});

it('lets the PM view and create tills', function () {
    $manager = pm();
    actAs($manager);

    Livewire::test(Index::class)
        ->set('newTillName', 'Drive-thru Counter')
        ->call('createTill')
        ->assertHasNoErrors();

    expect(Till::where('name', 'Drive-thru Counter')->exists())->toBeTrue();
});

it('lets the PM toggle till active status but blocks deactivating an occupied till', function () {
    $manager = pm();
    $staff = ps();
    $till = Till::factory()->create(['name' => 'Counter Toggle', 'is_active' => true]);

    actAs($manager);

    // Can toggle inactive when free
    Livewire::test(Index::class)
        ->call('toggleActive', $till->id)
        ->assertHasNoErrors();

    expect($till->refresh()->is_active)->toBeFalse();

    // Toggle back active
    Livewire::test(Index::class)
        ->call('toggleActive', $till->id)
        ->assertHasNoErrors();

    expect($till->refresh()->is_active)->toBeTrue();

    // Now open shift on till
    app(OpenShift::class)->execute($staff, $till, 10000);

    // Attempt to toggle inactive while open
    Livewire::test(Index::class)
        ->call('toggleActive', $till->id)
        ->assertSee(__('shifts.cannot_deactivate_till_with_open_shift'));

    expect($till->refresh()->is_active)->toBeTrue();
});

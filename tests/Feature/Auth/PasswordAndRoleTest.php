<?php

use App\Enums\Role;
use App\Livewire\Auth\ChooseActingRole;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

beforeEach(fn () => seedRoles());

it('sends a user who must change the password to the forced change page', function () {
    $user = actAs(ps(['must_change_password' => true]));

    $this->get('/dashboard')->assertRedirect(route('password.force-change'));
    $this->get('/staff')->assertRedirect(route('password.force-change'));
});

it('rejects a 7-character password and accepts 8', function () {
    $user = actAs(ps(['must_change_password' => true, 'password' => 'Old-Password-1']));

    Livewire::test(ForcePasswordChange::class)->set('current_password', 'Old-Password-1')->set('password', 'Abc1234')->set('password_confirmation', 'Abc1234')
        ->call('updatePassword')->assertHasErrors('password');

    Livewire::test(ForcePasswordChange::class)->set('current_password', 'Old-Password-1')->set('password', 'Abc12345')->set('password_confirmation', 'Abc12345')
        ->call('updatePassword')->assertHasNoErrors()->assertRedirect(route('dashboard'));

    expect($user->refresh()->must_change_password)->toBeFalse()->and(Hash::check('Abc12345', $user->password))->toBeTrue();
    expect(AuditLog::where('action', 'auth.password.changed')->exists())->toBeTrue();
});

it('does not allow reusing any of the last 5 passwords but allows the 6th back', function () {
    $user = actAs(ps(['must_change_password' => true, 'password' => 'Start-Pass-0']));
    $current = 'Start-Pass-0';

    foreach (range(1, 5) as $i) {
        $new = "Pass-Number-{$i}";
        Livewire::test(ForcePasswordChange::class)->set('current_password', $current)->set('password', $new)->set('password_confirmation', $new)->call('updatePassword')->assertHasNoErrors();
        $user->refresh()->forceFill(['must_change_password' => true])->save();
        $current = $new;
    }

    // Pass-Number-3 is within the last 5 (current + 4 archived) -> refused
    Livewire::test(ForcePasswordChange::class)->set('current_password', $current)->set('password', 'Pass-Number-3')->set('password_confirmation', 'Pass-Number-3')
        ->call('updatePassword')->assertHasErrors('password');

    // and the current password itself cannot be "changed" to the same value
    Livewire::test(ForcePasswordChange::class)->set('current_password', $current)->set('password', $current)->set('password_confirmation', $current)
        ->call('updatePassword')->assertHasErrors('password');

    // the original (6 passwords ago) is free again
    Livewire::test(ForcePasswordChange::class)->set('current_password', $current)->set('password', 'Start-Pass-0')->set('password_confirmation', 'Start-Pass-0')
        ->call('updatePassword')->assertHasNoErrors();
});

it('rejects a wrong current password on the forced change page', function () {
    actAs(ps(['must_change_password' => true, 'password' => 'Old-Password-1']));

    Livewire::test(ForcePasswordChange::class)->set('current_password', 'wrong')->set('password', 'Abc12345')->set('password_confirmation', 'Abc12345')
        ->call('updatePassword')->assertHasErrors('current_password');
});

it('shows each role only its own menu items', function () {
    $matrix = [
        'sa' => [sa(), ['Users' => true, 'Audit' => true, 'Staff' => false, 'Approvals' => true, 'Settings' => true]],
        'owner' => [owner(), ['Users' => true, 'Audit' => true, 'Staff' => true, 'Approvals' => true, 'Settings' => true]],
        'pm' => [pm(), ['Users' => true, 'Audit' => true, 'Staff' => true, 'Approvals' => true, 'Settings' => true]],
        'ps' => [ps(), ['Users' => false, 'Audit' => false, 'Staff' => true, 'Approvals' => true, 'Settings' => false]],
    ];
    $labels = ['Users' => 'User accounts', 'Audit' => 'Audit log', 'Staff' => 'Staff records', 'Approvals' => 'Approvals', 'Settings' => 'Settings'];
    $hrefs = ['Users' => '/users', 'Audit' => '/audit', 'Staff' => '/staff', 'Approvals' => '/approvals', 'Settings' => '/settings'];

    foreach ($matrix as $name => [$user, $expect]) {
        actAs($user);
        $html = $this->get('/dashboard')->assertOk()->getContent();
        foreach ($expect as $item => $visible) {
            $has = str_contains($html, 'href="'.url($hrefs[$item]).'"');
            expect($has)->toBe($visible, "{$name} / {$item}");
        }
    }
});

it('makes a multi-role user choose an acting role and records it as role_used', function () {
    $user = makeUser([Role::PharmacyManager, Role::PharmacyStaff], ['password' => 'Correct-Horse-1', 'username' => 'both']);

    Livewire::test(Login::class)->set('username', 'both')->set('password', 'Correct-Horse-1')->call('login')->assertRedirect(route('acting-role'));

    $this->get('/dashboard')->assertRedirect(route('acting-role'));

    Livewire::test(ChooseActingRole::class)->call('choose', 'pharmacy_staff')->assertRedirect(route('dashboard'));
    AuditLogger::record('test.role.used');
    expect(AuditLog::where('action', 'test.role.used')->first()->role_used)->toBe('pharmacy_staff');

    Livewire::test(ChooseActingRole::class)->call('choose', 'pharmacy_manager');
    AuditLogger::record('test.role.used2');
    expect(AuditLog::where('action', 'test.role.used2')->first()->role_used)->toBe('pharmacy_manager');
});

it('refuses to act under a role the user does not hold', function () {
    actAs(ps());
    Livewire::test(ChooseActingRole::class)->call('choose', 'owner')->assertNoRedirect();
    expect(session('auth.acting_role'))->toBe('pharmacy_staff');
});

it('switches the language and remembers it for the user', function () {
    $user = actAs(ps());

    $this->get('/locale/am')->assertRedirect();
    expect($user->refresh()->locale)->toBe('am');

    $this->get('/dashboard')->assertOk()->assertSee('ዳሽቦርድ');
    $this->get('/locale/xx')->assertNotFound();
});

it('has a translation in both languages for every key used', function () {
    $missing = [];
    $files = collect(File::allFiles(base_path('app')))->merge(File::allFiles(resource_path('views')));
    foreach ($files as $f) {
        preg_match_all("/__\('([a-z_]+\.[a-z0-9_.]*[a-z0-9_])'/", $f->getContents(), $m);
        foreach ($m[1] as $key) {
            foreach (['en', 'am'] as $loc) {
                if (! Lang::has($key, $loc, false)) {
                    $missing[] = "{$loc}:{$key}";
                }
            }
        }
    }
    expect(array_values(array_unique($missing)))->toBe([]);
});

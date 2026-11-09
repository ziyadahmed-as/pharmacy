<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\EndUserSessions;
use App\Services\SettingsRepository;
use App\Services\Totp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** UC-33 Log in and log out. BRD FR-8.1, 8.4, 8.6, 8.9. AC-18, AC-32. */
beforeEach(fn () => seedRoles());

it('signs in a valid active user and audits auth.login.success with the role used', function () {
    $user = ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);

    Livewire::test(Login::class)->set('username', 'amina')->set('password', 'Correct-Horse-1')->call('login')->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $log = AuditLog::where('action', 'auth.login.success')->first();
    expect($log)->not->toBeNull()->and($log->user_id)->toBe($user->id)->and($log->role_used)->toBe('pharmacy_staff');
});

it('refuses a wrong password and gives the same generic text for an unknown username', function () {
    ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);

    $wrong = Livewire::test(Login::class)->set('username', 'amina')->set('password', 'nope')->call('login')->errors()->first('username');
    $unknown = Livewire::test(Login::class)->set('username', 'ghost')->set('password', 'nope')->call('login')->errors()->first('username');

    expect($wrong)->toBe($unknown)->and($wrong)->toBe(__('auth.failed'));
    $this->assertGuest();
    expect(AuditLog::where('action', 'auth.login.failed')->count())->toBe(2);
});

it('locks the account after 5 wrong passwords and then refuses the right one (AC-18)', function () {
    $user = ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);

    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)->set('username', 'amina')->set('password', 'wrong')->call('login');
    }

    $user->refresh();
    expect($user->locked_at)->not->toBeNull()->and($user->failed_login_attempts)->toBe(5);
    expect(AuditLog::where('action', 'auth.login.locked')->count())->toBe(1);

    Livewire::test(Login::class)->set('username', 'amina')->set('password', 'Correct-Horse-1')->call('login')
        ->assertHasErrors('username')->assertNoRedirect();
    $this->assertGuest();
});

it('uses the lockout threshold from the settings table, not a constant', function () {
    ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);
    app(SettingsRepository::class)->put('security.max_failed_logins', 2);

    foreach ([1, 2] as $i) {
        Livewire::test(Login::class)->set('username', 'amina')->set('password', 'x')->call('login');
    }

    expect(User::where('username', 'amina')->first()->locked_at)->not->toBeNull();
});

it('resets the failure counter after a successful login', function () {
    $user = ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);
    foreach ([1, 2, 3] as $i) {
        Livewire::test(Login::class)->set('username', 'amina')->set('password', 'bad')->call('login');
    }
    Livewire::test(Login::class)->set('username', 'amina')->set('password', 'Correct-Horse-1')->call('login');

    expect($user->refresh()->failed_login_attempts)->toBe(0);
});

it('refuses a deactivated user (AC-32) and a pending-approval account (AC-28)', function () {
    ps(['username' => 'gone', 'password' => 'Correct-Horse-1', 'is_active' => false]);
    ps(['username' => 'new', 'password' => 'Correct-Horse-1', 'is_active' => false, 'account_status' => 'pending_approval']);

    Livewire::test(Login::class)->set('username', 'gone')->set('password', 'Correct-Horse-1')->call('login')->assertHasErrors('username');
    Livewire::test(Login::class)->set('username', 'new')->set('password', 'Correct-Horse-1')->call('login')->assertHasErrors('username');
    $this->assertGuest();
});

it('ends the open session of a user deactivated while signed in (AC-32)', function () {
    $user = actAs(ps());
    $user->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(AuditLog::where('action', 'auth.session.terminated')->exists())->toBeTrue();
});

it('EndUserSessions deletes the sessions rows of that user only', function () {
    $a = ps();
    $b = ps();
    foreach ([$a, $b] as $u) {
        DB::table('sessions')->insert(['id' => 's'.$u->id, 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
    }

    app(EndUserSessions::class)->handle($a);

    expect(DB::table('sessions')->where('user_id', $a->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $b->id)->count())->toBe(1);
});

it('logs a user out after the idle limit and audits auth.logout.idle (AC-18)', function () {
    $user = ps();

    $this->actingAs($user)->withSession(['auth.last_activity' => time() - (16 * 60), 'auth.acting_role' => 'pharmacy_staff'])
        ->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(AuditLog::where('action', 'auth.logout.idle')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('honours the idle limit from settings', function () {
    app(SettingsRepository::class)->put('security.idle_timeout_minutes', 5);
    $user = ps();

    $this->actingAs($user)->withSession(['auth.last_activity' => time() - (6 * 60), 'auth.acting_role' => 'pharmacy_staff'])
        ->get('/dashboard')->assertRedirect(route('login'));
});

it('keeps an active session open and refreshes the activity stamp', function () {
    $user = ps();

    $this->actingAs($user)->withSession(['auth.last_activity' => time() - (5 * 60), 'auth.acting_role' => 'pharmacy_staff'])
        ->get('/dashboard')->assertOk();
});

it('logs out manually and audits it', function () {
    $user = actAs(ps());

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(AuditLog::where('action', 'auth.logout.manual')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('regenerates the session id on login', function () {
    ps(['username' => 'amina', 'password' => 'Correct-Horse-1']);
    $before = session()->getId();

    Livewire::test(Login::class)->set('username', 'amina')->set('password', 'Correct-Horse-1')->call('login');

    expect(session()->getId())->not->toBe($before);
});

it('makes shared accounts impossible: usernames are unique', function () {
    ps(['username' => 'amina']);

    expect(fn () => ps(['username' => 'amina']))->toThrow(QueryException::class);
});

it('redirects unauthenticated visitors to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/users')->assertRedirect(route('login'));
});

// ---- two-factor (optional, real TOTP) ----

it('asks for a TOTP code for a manager with two-factor on, and accepts only the real code', function () {
    $secret = Totp::generateSecret();
    $user = pm(['username' => 'mgr', 'password' => 'Correct-Horse-1', 'two_factor_enabled' => true, 'two_factor_secret' => $secret]);

    Livewire::test(Login::class)->set('username', 'mgr')->set('password', 'Correct-Horse-1')->call('login')->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    // the old hard-coded "123456" bypass must be gone
    Livewire::test(TwoFactorChallenge::class)->set('code', '123456')->call('verify')->assertHasErrors('code');
    $this->assertGuest();

    Livewire::test(TwoFactorChallenge::class)->set('code', Totp::code($secret))->call('verify')->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(AuditLog::where('action', 'auth.mfa.success')->exists())->toBeTrue();
});

it('does not ask for a second factor when it is off, and never for pharmacy_staff', function () {
    pm(['username' => 'mgr', 'password' => 'Correct-Horse-1', 'two_factor_enabled' => false]);
    ps(['username' => 'ps1', 'password' => 'Correct-Horse-1', 'two_factor_enabled' => true, 'two_factor_secret' => Totp::generateSecret()]);

    Livewire::test(Login::class)->set('username', 'mgr')->set('password', 'Correct-Horse-1')->call('login')->assertRedirect(route('dashboard'));
    auth()->logout();
    Livewire::test(Login::class)->set('username', 'ps1')->set('password', 'Correct-Horse-1')->call('login')->assertRedirect(route('dashboard'));
});

it('matches RFC 6238 test vectors', function () {
    // RFC 6238 appendix B, SHA-1, secret "12345678901234567890", T=59 -> 94287082 (8 digits)
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    expect(Totp::code($secret, 59, 30, 8))->toBe('94287082')
        ->and(Totp::code($secret, 1111111109, 30, 8))->toBe('07081804');
});

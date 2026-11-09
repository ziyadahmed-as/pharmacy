<?php

use App\Actions\Users\ResetPassword;
use App\Actions\Users\UnlockAccount;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Users\Index;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** UC-36 Reset password / unlock account. FR-8.6, 8.10; AC-18. */
beforeEach(fn () => seedRoles());

it('unlocks a locked account after identity confirmation and a note', function () {
    $admin = sa();
    $target = ps(['locked_at' => now(), 'failed_login_attempts' => 5]);

    app(UnlockAccount::class)->execute($admin, $target, true, 'Phoned the owner, confirmed by voice');

    expect($target->refresh()->locked_at)->toBeNull()->and($target->failed_login_attempts)->toBe(0);
    $log = AuditLog::where('action', 'user.account.unlocked')->first();
    expect($log->user_id)->toBe($admin->id)->and($log->metadata['note'])->toContain('confirmed by voice')->and($log->auditable_id)->toBe((string) $target->id);
});

it('requires the identity checkbox and a note (E1)', function () {
    $target = ps(['locked_at' => now()]);
    expect(fn () => app(UnlockAccount::class)->execute(sa(), $target, false, 'note'))->toThrow(ValidationException::class);
    expect(fn () => app(UnlockAccount::class)->execute(sa(), $target, true, '  '))->toThrow(ValidationException::class);
    expect(fn () => app(ResetPassword::class)->execute(sa(), $target, false, 'note'))->toThrow(ValidationException::class);
    expect($target->refresh()->locked_at)->not->toBeNull();
});

it('resets the password: one-time temporary value, forced change, history, sessions ended, notifications', function () {
    $admin = sa();
    $mgr = pm();
    $own = owner();
    $target = ps(['password' => 'Old-Password-1', 'locked_at' => now(), 'failed_login_attempts' => 5]);
    DB::table('sessions')->insert(['id' => 'r', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    $oldHash = $target->password;

    $result = app(ResetPassword::class)->execute($admin, $target, true, 'Verified with ID card');
    $temp = $result['temporary_password'];

    $target->refresh();
    expect(Hash::check($temp, $target->password))->toBeTrue()->and($target->must_change_password)->toBeTrue()->and($target->locked_at)->toBeNull();
    expect(DB::table('password_histories')->where('user_id', $target->id)->where('password_hash', $oldHash)->exists())->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
    expect(DB::table('notifications')->whereIn('notifiable_id', [$mgr->id, $own->id])->where('type', 'user.password.reset')->count())->toBe(2);

    foreach (['audit_logs', 'notifications', 'users'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain($temp);
    }
    $log = AuditLog::where('action', 'user.password.reset')->first();
    expect($log->user_id)->toBe($admin->id)->and($log->metadata['note'])->toBe('Verified with ID card');
});

it('stops the user reusing the old password after a reset (history)', function () {
    $target = ps(['password' => 'Old-Password-1', 'must_change_password' => false]);
    $temp = app(ResetPassword::class)->execute(sa(), $target, true, 'ok')['temporary_password'];

    actAs($target->refresh());
    Livewire::test(ForcePasswordChange::class)->set('current_password', $temp)->set('password', 'Old-Password-1')->set('password_confirmation', 'Old-Password-1')->call('updatePassword')->assertHasErrors('password');
});

it('blocks a self reset and a self unlock', function () {
    $admin = sa(['locked_at' => now()]);
    expect(fn () => app(ResetPassword::class)->execute($admin, $admin, true, 'me'))->toThrow(ValidationException::class);
    expect(fn () => app(UnlockAccount::class)->execute($admin, $admin, true, 'me'))->toThrow(ValidationException::class);
});

it('throttles to 10 resets per hour per SA', function () {
    $admin = sa();
    foreach (range(1, 10) as $i) {
        app(ResetPassword::class)->execute($admin, ps(), true, 'ok');
    }
    expect(fn () => app(ResetPassword::class)->execute($admin, ps(), true, 'ok'))->toThrow(ValidationException::class);
});

it('refuses a deactivated or pending account for reset', function () {
    expect(fn () => app(ResetPassword::class)->execute(sa(), ps(['is_active' => false]), true, 'ok'))->toThrow(ValidationException::class);
});

it('is reserved to the SA: PM, Owner and staff are denied, also through the screen', function () {
    $target = ps(['locked_at' => now()]);
    foreach ([pm(), owner(), ps()] as $user) {
        expect(fn () => app(UnlockAccount::class)->execute($user, $target, true, 'x'))->toThrow(AuthorizationException::class);
    }
    actAs(pm());
    Livewire::test(Index::class)->call('open', 'reset', $target->id)->assertForbidden();
});

it('runs reset and unlock through the screen and shows the password once', function () {
    $target = ps(['locked_at' => now()]);
    actAs(sa());

    $c = Livewire::test(Index::class)->call('open', 'reset', $target->id)->set('identityVerified', true)->set('note', 'ID card')->call('resetPassword');
    expect($c->get('revealedPassword'))->toBeString();
    $c->call('closeModal');
    expect($c->get('revealedPassword'))->toBeNull();

    $locked = ps(['locked_at' => now()]);
    Livewire::test(Index::class)->call('open', 'unlock', $locked->id)->set('identityVerified', true)->set('note', 'ID')->call('unlock')->assertHasNoErrors();
    expect($locked->refresh()->locked_at)->toBeNull();
});

it('break-glass: pms:reset-sa unlocks a locked SA from the server and audits it', function () {
    $admin = sa(['username' => 'sysadmin', 'locked_at' => now(), 'failed_login_attempts' => 5]);

    $this->artisan('pms:reset-sa', ['username' => 'sysadmin', '--note' => 'locked out'])->assertSuccessful();

    expect($admin->refresh()->locked_at)->toBeNull()->and($admin->must_change_password)->toBeTrue();
    expect(AuditLog::where('action', 'user.password.reset_cli')->exists())->toBeTrue();
    $this->artisan('pms:reset-sa', ['username' => 'nobody'])->assertFailed();
    $this->artisan('pms:reset-sa', ['username' => ps()->username])->assertFailed(); // only SAs
});

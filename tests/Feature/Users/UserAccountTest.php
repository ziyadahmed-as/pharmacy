<?php

use App\Actions\Users\CreateUserAccount;
use App\Actions\Users\RequestUserChange;
use App\Exceptions\ApprovalException;
use App\Livewire\Auth\Login;
use App\Livewire\Users\Index;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** UC-35 Manage user account (create, roles, deactivate, reactivate). FR-8.1, 8.2, 8.5, 8.8, 8.9; AC-28, AC-32. */
beforeEach(fn () => seedRoles());

function decide(ApprovalRequest $r, User $approver, bool $approve = true): void
{
    actAs($approver);
    reauth();
    $approve ? app(ApprovalService::class)->approve($r, $approver) : app(ApprovalService::class)->reject($r, $approver, 'no');
}

it('creates a pending account that cannot sign in until approved (AC-28)', function () {
    $admin = sa();
    $staff = makeStaff(['name' => 'Selam Tadesse']);
    $request = app(CreateUserAccount::class)->execute($admin, $staff, 'selam', ['pharmacy_staff']);

    $user = User::where('username', 'selam')->first();
    expect($user->status)->toBe('pending_approval')->and($user->canSignIn())->toBeFalse()->and($request->status)->toBe('pending')->and($request->eligible_roles)->toBe(['pharmacy_manager']);
    expect($staff->refresh()->user_id)->toBeNull();
    $this->post('/logout'); // no-op
    Livewire::test(Login::class)->set('username', 'selam')->set('password', 'anything')->call('login')->assertHasErrors('username');
});

it('activates on approval, links the staff, and hands over the password once, never stored in clear', function () {
    $admin = sa();
    $staff = makeStaff();
    $request = app(CreateUserAccount::class)->execute($admin, $staff, 'selam', ['pharmacy_staff']);

    decide($request, pm());

    $user = User::where('username', 'selam')->first();
    expect($user->is_active)->toBeTrue()->and($user->must_change_password)->toBeTrue()->and($staff->refresh()->user_id)->toBe($user->id);
    expect($user->roles->pluck('slug')->all())->toBe(['pharmacy_staff']);

    $reveal = DB::table('credential_reveals')->first();
    expect($reveal)->not->toBeNull();

    actAs($admin);
    $c = Livewire::test(Index::class)->call('revealCredential', $reveal->id);
    $password = $c->get('revealedPassword');
    expect($password)->toBeString()->and(strlen($password))->toBe(14);
    $c->assertSee($password);

    // the clear value is nowhere in the database, and a second reveal fails
    foreach (['users', 'credential_reveals', 'audit_logs', 'approval_requests', 'notifications', 'password_histories'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain($password, $table);
    }
    expect(DB::table('credential_reveals')->count())->toBe(0);
    Livewire::test(Index::class)->call('revealCredential', $reveal->id)->assertHasErrors('reveal');

    // and the new user can sign in with it, then must change it
    auth()->logout();
    Livewire::test(Login::class)->set('username', 'selam')->set('password', $password)->call('login')->assertRedirect(route('password.force-change'));
});

it('only the initiating SA can reveal the password', function () {
    $request = app(CreateUserAccount::class)->execute(sa(), makeStaff(), 'selam', ['pharmacy_staff']);
    decide($request, pm());
    $rid = DB::table('credential_reveals')->value('id');

    actAs(sa());
    Livewire::test(Index::class)->call('revealCredential', $rid)->assertHasErrors('reveal');
});

it('frees the username and keeps the staff unlinked when the request is rejected', function () {
    $staff = makeStaff();
    $request = app(CreateUserAccount::class)->execute(sa(), $staff, 'selam', ['pharmacy_staff']);
    decide($request, pm(), false);

    expect($staff->refresh()->user_id)->toBeNull()->and(User::where('username', 'selam')->exists())->toBeFalse();
    app(CreateUserAccount::class)->execute(sa(), $staff, 'selam', ['pharmacy_staff']); // can be asked again
    expect(User::where('username', 'selam')->count())->toBe(1);
});

it('validates the create form: unique username, one account per staff, ended staff, roles', function () {
    $admin = sa();
    ps(['username' => 'taken']);
    $create = fn ($staff, $name, $roles) => fn () => app(CreateUserAccount::class)->execute($admin, $staff, $name, $roles);

    expect($create(makeStaff(), 'taken', ['pharmacy_staff']))->toThrow(ValidationException::class);
    expect($create(makeStaff(), 'ab', ['pharmacy_staff']))->toThrow(ValidationException::class);
    expect($create(makeStaff(), 'ok name', ['pharmacy_staff']))->toThrow(ValidationException::class);
    expect($create(makeStaff(), 'fine', []))->toThrow(ValidationException::class);
    expect($create(makeStaff(), 'fine', ['hacker']))->toThrow(ValidationException::class);
    expect($create(makeStaff()->forceFill(['status' => 'ended']), 'fine', ['pharmacy_staff']))->toThrow(ValidationException::class);
    expect($create(makeStaff(['user_id' => ps()->id]), 'fine', ['pharmacy_staff']))->toThrow(ValidationException::class);
});

it('never lets the SA role be combined with another role (C-4), at creation and on change', function () {
    $admin = sa();
    expect(fn () => app(CreateUserAccount::class)->execute($admin, makeStaff(), 'combo', ['system_admin', 'owner']))->toThrow(ValidationException::class, __('users.sa_cannot_combine'));
    expect(fn () => app(RequestUserChange::class)->changeRoles($admin, ps(), ['system_admin', 'pharmacy_staff']))->toThrow(ValidationException::class);
    expect(User::where('username', 'combo')->exists())->toBeFalse();
});

it('only the SA may manage accounts; PM and Owner cannot, and see the list read-only', function () {
    $target = ps();
    foreach ([pm(), owner(), ps()] as $user) {
        expect(fn () => app(RequestUserChange::class)->deactivate($user, $target, 'x'))->toThrow(AuthorizationException::class);
    }
    actAs(pm());
    $this->get('/users')->assertOk()->assertDontSee(__('users.create'));
    actAs(ps());
    $this->get('/users')->assertForbidden();
});

it('changes roles only after approval and ends the sessions of that user', function () {
    $target = ps();
    DB::table('sessions')->insert(['id' => 'q', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    $request = app(RequestUserChange::class)->changeRoles(sa(), $target, ['pharmacy_manager'], 'promotion');

    expect($target->refresh()->hasRole('pharmacy_manager'))->toBeFalse();
    expect($request->eligible_roles)->toBe(['owner']); // the change concerns a PM role, so the Owner decides
    decide($request, owner());

    expect($target->refresh()->roles->pluck('slug')->all())->toBe(['pharmacy_manager']);
    expect(DB::table('role_assignment_history')->where('user_id', $target->id)->count())->toBe(1);
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
    expect(AuditLog::where('action', 'user.roles.updated')->exists())->toBeTrue();
});

it('sends a change about a PM to the Owner, and about the Owner to a PM', function () {
    $about = pm();
    expect(app(RequestUserChange::class)->deactivate(sa(), $about, 'left')->eligible_roles)->toBe(['owner']);
    $o = owner();
    expect(app(RequestUserChange::class)->deactivate(sa(), $o, 'left')->eligible_roles)->toBe(['pharmacy_manager']);

    // the PM concerned cannot approve a request about themselves, even if eligible
    $req = app(RequestUserChange::class)->changeRoles(sa(), $about, ['owner']);
    expect($req->eligible_roles)->toContain('pharmacy_manager');
    actAs($about);
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($req, $about))->toThrow(ApprovalException::class);
});

it('deactivates after approval, ends the sessions at once, and keeps the history (AC-32)', function () {
    $target = ps();
    DB::table('sessions')->insert(['id' => 'z', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    $request = app(RequestUserChange::class)->deactivate(sa(), $target, 'misconduct');
    expect($target->refresh()->is_active)->toBeTrue(); // not yet

    decide($request, pm());

    expect($target->refresh()->is_active)->toBeFalse()->and(User::whereKey($target->id)->exists())->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
    actAs($target);
    $this->get('/dashboard')->assertRedirect(route('login'));
    expect(AuditLog::where('action', 'user.account.deactivated')->exists())->toBeTrue();
});

it('reactivates after approval and requires a reason', function () {
    $target = ps(['is_active' => false]);
    expect(fn () => app(RequestUserChange::class)->reactivate(sa(), $target, ''))->toThrow(ValidationException::class);

    decide(app(RequestUserChange::class)->reactivate(sa(), $target, 'back'), pm());
    expect($target->refresh()->is_active)->toBeTrue();
});

it('does not let the SA deactivate their own account', function () {
    $admin = sa();
    expect(fn () => app(RequestUserChange::class)->deactivate($admin, $admin, 'x'))->toThrow(ValidationException::class);
});

it('runs the create wizard through the screen', function () {
    $staff = makeStaff(['name' => 'Wizard Person']);
    actAs(sa());

    Livewire::test(Index::class)->call('open', 'create')->set('staffId', $staff->id)->set('username', 'wizard')->set('roles', ['pharmacy_staff'])->call('createAccount')->assertHasNoErrors();

    expect(User::where('username', 'wizard')->value('account_status'))->toBe('pending_approval');
});

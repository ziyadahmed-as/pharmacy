<?php

use App\Enums\UserRole;
use App\Livewire\Admin\AccountRecoveryDashboard;
use App\Livewire\Admin\PerformAccountRecovery;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\IdentityVerification;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(
        ['slug' => UserRole::SYSTEM_ADMINISTRATOR->value],
        ['name' => 'System Administrator']
    );

    $this->staffRole = Role::firstOrCreate(
        ['slug' => UserRole::PHARMACY_STAFF->value],
        ['name' => 'Pharmacy Staff']
    );

    $this->admin = User::factory()->create([
        'username' => 'sysadmin_user',
    ]);
    $this->admin->roles()->attach($this->adminRole);

    $this->staff = User::factory()->create([
        'username' => 'staff_user_1',
        'password' => Hash::make('OldPassword123!'),
        'is_active' => true,
    ]);
    $this->staff->roles()->attach($this->staffRole);
});

test('SA can view account recovery dashboard', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.account-recovery'));
    $response->assertStatus(200);
});

test('Non-admin users cannot access account recovery dashboard', function () {
    $this->actingAs($this->staff);

    $response = $this->get(route('admin.account-recovery'));
    $response->assertStatus(403);
});

test('Identity verification can be recorded successfully', function () {
    $service = app(AccountRecoveryService::class);

    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'in_person',
        outcome: 'verified',
        notes: 'Verified via staff ID badge at main desk'
    );

    expect($verification->isVerified())->toBeTrue();
    expect($verification->user_id)->toBe($this->staff->id);
    expect($verification->admin_id)->toBe($this->admin->id);

    $this->assertDatabaseHas('identity_verifications', [
        'user_id' => $this->staff->id,
        'admin_id' => $this->admin->id,
        'outcome' => 'verified',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'identity_verification_success',
        'user_id' => $this->admin->id,
    ]);
});

test('Failed identity verification refuses operation and logs security audit event', function () {
    $service = app(AccountRecoveryService::class);

    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'registered_phone',
        outcome: 'failed',
        notes: 'Caller failed voice verification challenge'
    );

    expect($verification->isVerified())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'identity_verification_failed',
        'user_id' => $this->admin->id,
    ]);

    // Attempting unlock with failed verification throws exception
    expect(fn () => $service->unlockAccount($this->staff, $this->admin, $verification))
        ->toThrow(RuntimeException::class, 'Cannot unlock account without confirmed identity verification.');
});

test('Admin can unlock a locked account after identity verification', function () {
    $this->staff->update([
        'locked_at' => now(),
        'failed_login_attempts' => 5,
    ]);

    expect($this->staff->isLocked())->toBeTrue();

    $service = app(AccountRecoveryService::class);
    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'in_person',
        outcome: 'verified'
    );

    $unlockedUser = $service->unlockAccount($this->staff, $this->admin, $verification);

    expect($unlockedUser->isLocked())->toBeFalse();
    expect($unlockedUser->failed_login_attempts)->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'account_unlocked_by_admin',
        'user_id' => $this->admin->id,
    ]);

    // Staff can authenticate with original password
    Livewire::test(Login::class)
        ->set('username', 'staff_user_1')
        ->set('password', 'OldPassword123!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));
});

test('Admin cannot unlock an active or deactivated account', function () {
    $service = app(AccountRecoveryService::class);
    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'in_person',
        outcome: 'verified'
    );

    // Active account
    expect(fn () => $service->unlockAccount($this->staff, $this->admin, $verification))
        ->toThrow(RuntimeException::class, "Account staff_user_1 with status 'active' cannot be unlocked.");

    // Deactivated account
    $this->staff->update(['is_active' => false]);
    expect(fn () => $service->unlockAccount($this->staff, $this->admin, $verification))
        ->toThrow(RuntimeException::class, "Account staff_user_1 with status 'deactivated' cannot be unlocked.");
});

test('Admin can reset password, which forces password change on next login', function () {
    $service = app(AccountRecoveryService::class);
    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'in_person',
        outcome: 'verified'
    );

    $result = $service->resetPassword($this->staff, $this->admin, $verification);

    $tempPassword = $result['temporary_password'];
    expect($tempPassword)->toBeString()->not->toBeEmpty();

    $freshStaff = $this->staff->fresh();
    expect($freshStaff->must_change_password)->toBeTrue();
    expect($freshStaff->password_reset_at)->not->toBeNull();

    // Verify temp password works
    expect(Hash::check($tempPassword, $freshStaff->password))->toBeTrue();

    // Plaintext password is NEVER in audit log
    $audit = AuditLog::where('action', 'password_reset_by_admin')->latest()->first();
    expect($audit)->not->toBeNull();
    expect(json_encode($audit->metadata))->not->toContain($tempPassword);

    // Logging in with temp password redirects to forced password change
    Livewire::test(Login::class)
        ->set('username', 'staff_user_1')
        ->set('password', $tempPassword)
        ->call('login')
        ->assertRedirect(route('password.force-change'));
});

test('Admin can perform atomic combined reset and unlock', function () {
    $this->staff->update([
        'locked_at' => now(),
        'failed_login_attempts' => 5,
    ]);

    $service = app(AccountRecoveryService::class);
    $verification = $service->recordIdentityVerification(
        targetUser: $this->staff,
        adminUser: $this->admin,
        method: 'in_person',
        outcome: 'verified'
    );

    $result = $service->resetPasswordAndUnlock($this->staff, $this->admin, $verification);
    $tempPassword = $result['temporary_password'];

    $fresh = $this->staff->fresh();
    expect($fresh->isLocked())->toBeFalse();
    expect($fresh->failed_login_attempts)->toBe(0);
    expect($fresh->must_change_password)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'account_reset_and_unlocked_by_admin',
        'user_id' => $this->admin->id,
    ]);
});

test('Livewire recovery wizard handles verification failure and stops operation', function () {
    $this->actingAs($this->admin);

    Livewire::test(PerformAccountRecovery::class, ['userId' => $this->staff->id])
        ->assertSet('step', 2)
        ->set('verification_method', 'in_person')
        ->set('verification_outcome', 'failed')
        ->call('submitIdentityVerification')
        ->assertRedirect(route('admin.account-recovery'));

    expect($this->staff->fresh()->is_active)->toBeTrue();
    expect($this->staff->fresh()->must_change_password)->toBeFalse();
});


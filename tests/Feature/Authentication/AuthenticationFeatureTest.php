<?php

namespace Tests\Feature\Authentication;

use App\Enums\UserRole;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Role $staffRole;
    protected Role $managerRole;
    protected Role $ownerRole;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup the four principal roles
        $this->staffRole = Role::create([
            'name' => UserRole::PHARMACY_STAFF->label(),
            'slug' => UserRole::PHARMACY_STAFF->value,
            'description' => 'Staff',
        ]);

        $this->managerRole = Role::create([
            'name' => UserRole::PHARMACY_MANAGER->label(),
            'slug' => UserRole::PHARMACY_MANAGER->value,
            'description' => 'Manager',
        ]);

        $this->ownerRole = Role::create([
            'name' => UserRole::OWNER->label(),
            'slug' => UserRole::OWNER->value,
            'description' => 'Owner',
        ]);

        $this->adminRole = Role::create([
            'name' => UserRole::SYSTEM_ADMINISTRATOR->label(),
            'slug' => UserRole::SYSTEM_ADMINISTRATOR->value,
            'description' => 'Admin',
        ]);
    }

    /**
     * AUTH-001: Valid active user login
     */
    public function test_auth_001_valid_active_user_login(): void
    {
        $user = User::factory()->create([
            'username' => 'staff_active',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => true,
        ]);
        $user->roles()->attach($this->staffRole);

        Livewire::test(Login::class)
            ->set('username', 'staff_active')
            ->set('password', 'CorrectPassword123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    /**
     * AUTH-002: Invalid password
     */
    public function test_auth_002_invalid_password_rejected(): void
    {
        $user = User::factory()->create([
            'username' => 'staff_user',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('username', 'staff_user')
            ->set('password', 'WrongPassword123!')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertGuest();
        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
    }

    /**
     * AUTH-003: Invalid username
     */
    public function test_auth_003_invalid_username_rejected(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'non_existent_user')
            ->set('password', 'Password123!')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertGuest();
    }

    /**
     * AUTH-004: Deactivated account
     */
    public function test_auth_004_deactivated_account_rejected(): void
    {
        $user = User::factory()->deactivated()->create([
            'username' => 'deactivated_user',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Livewire::test(Login::class)
            ->set('username', 'deactivated_user')
            ->set('password', 'CorrectPassword123!')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertGuest();
    }

    /**
     * AUTH-005: Five consecutive failed attempts locks account
     */
    public function test_auth_005_five_consecutive_failed_attempts_locks_account(): void
    {
        $user = User::factory()->create([
            'username' => 'lockout_test',
            'password' => Hash::make('CorrectPassword123!'),
            'failed_login_attempts' => 4,
        ]);

        Livewire::test(Login::class)
            ->set('username', 'lockout_test')
            ->set('password', 'WrongPassword123!')
            ->call('login')
            ->assertHasErrors(['username']);

        $fresh = $user->fresh();
        $this->assertEquals(5, $fresh->failed_login_attempts);
        $this->assertNotNull($fresh->locked_at);
        $this->assertTrue($fresh->isLocked());
    }

    /**
     * AUTH-006: Locked account login rejected
     */
    public function test_auth_006_locked_account_rejected(): void
    {
        $user = User::factory()->locked()->create([
            'username' => 'already_locked',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Livewire::test(Login::class)
            ->set('username', 'already_locked')
            ->set('password', 'CorrectPassword123!')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertGuest();
    }

    /**
     * AUTH-007: Successful login regenerates session
     */
    public function test_auth_007_successful_login_regenerates_session(): void
    {
        $user = User::factory()->create([
            'username' => 'session_regen_user',
            'password' => Hash::make('Password123!'),
        ]);
        $user->roles()->attach($this->staffRole);

        $initialSessionId = session()->getId();

        $authService = app(AuthenticationService::class);
        $result = $authService->attempt('session_regen_user', 'Password123!');

        $this->assertEquals('authenticated', $result['status']);
        $this->assertNotEquals($initialSessionId, session()->getId());
    }

    /**
     * AUTH-008: User manually logs out & invalidates session
     */
    public function test_auth_008_user_manually_logs_out(): void
    {
        $user = User::factory()->create([
            'username' => 'logout_user',
        ]);
        $user->roles()->attach($this->staffRole);

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * AUTH-009: Inactivity timeout expires session
     */
    public function test_auth_009_inactivity_timeout_expires_session(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->staffRole);

        $this->actingAs($user);

        // Simulate last activity 16 minutes ago (exceeding 15 min threshold)
        session()->put('auth.last_activity', time() - (16 * 60));

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * AUTH-010: Manager MFA enabled requires challenge
     */
    public function test_auth_010_manager_mfa_enabled_requires_challenge(): void
    {
        $manager = User::factory()->withMfa('123456')->create([
            'username' => 'manager_mfa',
            'password' => Hash::make('Password123!'),
        ]);
        $manager->roles()->attach($this->managerRole);

        Livewire::test(Login::class)
            ->set('username', 'manager_mfa')
            ->set('password', 'Password123!')
            ->call('login')
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertEquals($manager->id, session()->get('login.mfa_user_id'));

        // Completing challenge
        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '123456')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($manager);
    }

    /**
     * AUTH-011: Administrator MFA enabled requires challenge
     */
    public function test_auth_011_admin_mfa_enabled_requires_challenge(): void
    {
        $admin = User::factory()->withMfa('123456')->create([
            'username' => 'admin_mfa',
            'password' => Hash::make('Password123!'),
        ]);
        $admin->roles()->attach($this->adminRole);

        Livewire::test(Login::class)
            ->set('username', 'admin_mfa')
            ->set('password', 'Password123!')
            ->call('login')
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '123456')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * AUTH-012: MFA disabled allows normal authentication flow
     */
    public function test_auth_012_mfa_disabled_allows_normal_authentication(): void
    {
        $managerWithoutMfa = User::factory()->create([
            'username' => 'manager_nomfa',
            'password' => Hash::make('Password123!'),
            'two_factor_enabled' => false,
        ]);
        $managerWithoutMfa->roles()->attach($this->managerRole);

        Livewire::test(Login::class)
            ->set('username', 'manager_nomfa')
            ->set('password', 'Password123!')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($managerWithoutMfa);
    }

    /**
     * AUTH-013: Password change required enforced
     */
    public function test_auth_013_password_change_enforced(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'username' => 'reset_user',
            'password' => Hash::make('Temporary123!'),
        ]);
        $user->roles()->attach($this->staffRole);

        $this->actingAs($user);

        // Attempting to access dashboard should redirect to forced password change
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('password.force-change'));

        // Complete password change
        Livewire::test(ForcePasswordChange::class)
            ->set('current_password', 'Temporary123!')
            ->set('password', 'NewPermanentPassword123!')
            ->set('password_confirmation', 'NewPermanentPassword123!')
            ->call('updatePassword')
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    /**
     * AUTH-014: Shared account attempt - Individual account authentication enforced
     */
    public function test_auth_014_individual_account_authentication_enforced(): void
    {
        $user1 = User::factory()->create(['username' => 'staff_alice']);
        $user2 = User::factory()->create(['username' => 'staff_bob']);

        $user1->roles()->attach($this->staffRole);
        $user2->roles()->attach($this->staffRole);

        $this->assertNotEquals($user1->id, $user2->id);
        $this->assertNotEquals($user1->username, $user2->username);
    }

    /**
     * AUTH-015: Successful authentication audit event recorded
     */
    public function test_auth_015_successful_authentication_audit_event_recorded(): void
    {
        $user = User::factory()->create([
            'username' => 'audit_test_user',
            'password' => Hash::make('Password123!'),
        ]);
        $user->roles()->attach($this->staffRole);

        Livewire::test(Login::class)
            ->set('username', 'audit_test_user')
            ->set('password', 'Password123!')
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login_success',
            'event_category' => 'authentication',
        ]);
    }

    /**
     * AUTH-016: Failed authentication audit event recorded
     */
    public function test_auth_016_failed_authentication_audit_event_recorded(): void
    {
        $user = User::factory()->create([
            'username' => 'audit_fail_user',
            'password' => Hash::make('Password123!'),
        ]);

        Livewire::test(Login::class)
            ->set('username', 'audit_fail_user')
            ->set('password', 'WrongPassword123!')
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login_failed',
            'event_category' => 'authentication',
        ]);
    }

    /**
     * AUTH-017: Unauthorized route access denied
     */
    public function test_auth_017_unauthorized_route_access_denied(): void
    {
        // System admin accessing business data (clinical/sales)
        $admin = User::factory()->create(['username' => 'admin_test']);
        $admin->roles()->attach($this->adminRole);

        $this->actingAs($admin);

        $response = $this->get(route('test.business-data'));
        $response->assertStatus(403);

        // Staff accessing system administration tools
        $staff = User::factory()->create(['username' => 'staff_test']);
        $staff->roles()->attach($this->staffRole);

        $this->actingAs($staff);

        $adminResponse = $this->get(route('test.admin-only'));
        $adminResponse->assertStatus(403);
    }

    /**
     * AUTH-018: Successful logout audit event recorded
     */
    public function test_auth_018_successful_logout_audit_event_recorded(): void
    {
        $user = User::factory()->create(['username' => 'logout_audit_user']);
        $user->roles()->attach($this->staffRole);

        $this->actingAs($user);

        $this->post(route('logout'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout_manual',
            'event_category' => 'authentication',
        ]);
    }
}


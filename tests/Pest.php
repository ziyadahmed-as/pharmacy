<?php

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\ActingRole;
use App\Services\ReAuthentication;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Arch');
pest()->extend(TestCase::class)->in('Unit');

/** Tests depend on seeded roles only (AGENTS.md section 10). */
function seedRoles(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/** Make an active user holding the given roles. */
function makeUser(RoleEnum|array $roles = RoleEnum::PharmacyStaff, array $attributes = []): User
{
    if (Role::count() === 0) {
        seedRoles();
    }
    $user = User::factory()->create($attributes);
    foreach (is_array($roles) ? $roles : [$roles] as $role) {
        $user->roles()->attach(Role::where('slug', $role->value)->value('id'));
    }

    return $user->refresh();
}

function sa(array $a = []): User
{
    return makeUser(RoleEnum::SystemAdmin, $a);
}
function owner(array $a = []): User
{
    return makeUser(RoleEnum::Owner, $a);
}
function pm(array $a = []): User
{
    return makeUser(RoleEnum::PharmacyManager, $a);
}
function ps(array $a = []): User
{
    return makeUser(RoleEnum::PharmacyStaff, $a);
}

/** Sign in as the user with a chosen acting role (as the UI would). */
function actAs(User $user, ?RoleEnum $role = null): User
{
    test()->actingAs($user);
    $roles = $user->roleEnums();
    session()->put(ActingRole::SESSION_KEY, ($role ?? $roles[0])->value);
    session()->put('auth.last_activity', time());

    return $user;
}

/** Grant the password re-entry the approval engine demands (what the modal does). */
function reauth(string $purpose = 'approval.decide'): void
{
    ReAuthentication::grant($purpose);
}

function makeStaff(array $attributes = []): Staff
{
    return Staff::factory()->create($attributes);
}

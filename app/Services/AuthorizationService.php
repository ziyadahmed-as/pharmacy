<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Enums\UserRole;

class AuthorizationService
{
    /**
     * Determine if a user has a specific role.
     */
    public function userHasRole(User $user, UserRole|string $role): bool
    {
        return $user->hasRole($role);
    }

    /**
     * Determine if a user has any of the given roles.
     */
    public function userHasAnyRole(User $user, array $roles): bool
    {
        return $user->hasAnyRole($roles);
    }

    /**
     * Determine if a user can access business data (patient, prescriptions, sales, financial).
     * System Administrator is explicitly excluded.
     */
    public function canAccessBusinessData(User $user): bool
    {
        return $user->canAccessBusinessData();
    }

    /**
     * Determine if a user can access technical system settings / administration.
     */
    public function canManageSystem(User $user): bool
    {
        return $user->hasRole(UserRole::SYSTEM_ADMINISTRATOR);
    }
}

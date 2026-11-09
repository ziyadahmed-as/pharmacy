<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;

/**
 * The role a user acts under for this session (UC-33 step 7).
 * Stored in the session; every audit entry records it as `role_used`.
 */
final class ActingRole
{
    public const SESSION_KEY = 'auth.acting_role';

    public static function for(User $user): ?Role
    {
        $roles = $user->roleEnums();

        if ($roles === []) {
            return null;
        }

        $chosen = null;
        try {
            $chosen = session()->get(self::SESSION_KEY);
        } catch (\Throwable) {
        }

        if ($chosen) {
            foreach ($roles as $r) {
                if ($r->value === $chosen) {
                    return $r;
                }
            }
        }

        return $roles[0];
    }

    /** True when the user holds several roles and has not picked one yet. */
    public static function needsSelection(User $user): bool
    {
        return count($user->roleEnums()) > 1 && ! session()->has(self::SESSION_KEY);
    }

    public static function choose(User $user, string $role): bool
    {
        foreach ($user->roleEnums() as $r) {
            if ($r->value === $role) {
                session()->put(self::SESSION_KEY, $role);

                return true;
            }
        }

        return false;
    }
}

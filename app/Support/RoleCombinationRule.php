<?php

namespace App\Support;

use App\Enums\Role;

/**
 * C-4 / BRD FR-8.8: the system administrator can never hold another role.
 * No exception path exists, on purpose.
 */
final class RoleCombinationRule
{
    /** @param  iterable<Role|string>  $roles */
    public static function allows(iterable $roles): bool
    {
        $values = [];
        foreach ($roles as $r) {
            $values[] = $r instanceof Role ? $r->value : (string) $r;
        }
        $values = array_values(array_unique($values));

        if ($values === []) {
            return false; // an account always has at least one role
        }

        return ! (in_array(Role::SystemAdmin->value, $values, true) && count($values) > 1);
    }

    /** @param  iterable<Role|string>  $roles */
    public static function message(iterable $roles): ?string
    {
        return self::allows($roles) ? null : __('users.sa_cannot_combine');
    }
}

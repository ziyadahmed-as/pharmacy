<?php

namespace App\Policies;

use App\Models\RosterEntry;
use App\Models\User;

/**
 * Roster policy (UC-40).
 * Owner and Pharmacy Manager manage roster entries.
 * Pharmacy Staff can only view their own shifts.
 * System Admin has no access.
 *
 * @uc UC-40 @fr FR-10.4
 */
class RosterEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.manage') || $user->hasPermission('staff.view.own');
    }

    public function view(User $user, RosterEntry $entry): bool
    {
        if ($user->hasPermission('staff.manage')) {
            return true;
        }

        return $user->hasPermission('staff.view.own') && $entry->staff_id === $user->staff?->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function update(User $user, RosterEntry $entry): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function delete(User $user, RosterEntry $entry): bool
    {
        return $user->hasPermission('staff.manage');
    }
}

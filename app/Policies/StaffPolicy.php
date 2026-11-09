<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

/**
 * Staff personal data: owner and pharmacy_manager see everything (BR-13);
 * pharmacy_staff see only their own record without id_number; the SA sees none.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.manage') || $user->hasPermission('staff.view.own');
    }

    public function view(User $user, Staff $staff): bool
    {
        if ($user->hasPermission('staff.manage')) {
            return true;
        }

        return $user->hasPermission('staff.view.own') && $staff->user_id === $user->id;
    }

    public function viewSensitive(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('staff.manage');
    }
}

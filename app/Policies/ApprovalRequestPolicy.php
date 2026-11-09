<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

/** Eligible role, not the initiator, not the person concerned, pending, not expired. */
class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('approvals.request');
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        if ($request->initiator_id === $user->id) {
            return true;
        }
        if (! $user->hasPermission('approvals.process')) {
            return false;
        }
        foreach ($user->roleEnums() as $role) {
            if (in_array($role->value, $request->eligible_roles ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function decide(User $user, ApprovalRequest $request): bool
    {
        if (! $user->hasPermission('approvals.process') || ! $request->isPending() || $request->isExpired()) {
            return false;
        }
        if ($request->initiator_id === $user->id || $request->concerns_user_id === $user->id) {
            return false;
        }
        foreach ($user->roleEnums() as $role) {
            if (in_array($role->value, $request->eligible_roles ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function cancel(User $user, ApprovalRequest $request): bool
    {
        return $request->initiator_id === $user->id && $request->isPending();
    }
}

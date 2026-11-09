<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Support\RoleCombinationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * ChangeUserRoles (A1), DeactivateUser and ReactivateUser (A2): each one is only a
 * request; the change happens when the approval handler runs.
 *
 * @uc UC-35 @fr FR-8.2,FR-8.5,FR-8.9 @br BR-14 @ac AC-28,AC-32
 */
class RequestUserChange
{
    public function __construct(private ApprovalService $approvals) {}

    /** @param  list<string>  $roles */
    public function changeRoles(User $sa, User $target, array $roles, ?string $reason = null): ApprovalRequest
    {
        Gate::forUser($sa)->authorize('users.manage');
        validator(['roles' => $roles], ['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['in:'.implode(',', Role::values())]])->validate();

        if (! RoleCombinationRule::allows($roles)) {
            throw ValidationException::withMessages(['roles' => RoleCombinationRule::message($roles)]);
        }
        $old = $target->roles()->pluck('slug')->sort()->values()->all();

        return $this->approvals->request(
            'user.roles.update', $sa,
            ['user_id' => $target->id, 'old_roles' => $old, 'new_roles' => array_values($roles), 'reason' => $reason],
            __('users.summary.roles', ['username' => $target->username]), $target, $target, $reason
        );
    }

    public function deactivate(User $sa, User $target, string $reason): ApprovalRequest
    {
        Gate::forUser($sa)->authorize('users.manage');
        $this->requireReason($reason);
        if ($target->id === $sa->id) {
            throw ValidationException::withMessages(['user' => __('users.error.self')]);
        }

        return $this->approvals->request('user.deactivate', $sa, ['user_id' => $target->id, 'reason' => $reason],
            __('users.summary.deactivate', ['username' => $target->username]), $target, $target, $reason);
    }

    public function reactivate(User $sa, User $target, string $reason): ApprovalRequest
    {
        Gate::forUser($sa)->authorize('users.manage');
        $this->requireReason($reason);

        return $this->approvals->request('user.reactivate', $sa, ['user_id' => $target->id, 'reason' => $reason],
            __('users.summary.reactivate', ['username' => $target->username]), $target, $target, $reason);
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('users.error.reason')]);
        }
    }
}

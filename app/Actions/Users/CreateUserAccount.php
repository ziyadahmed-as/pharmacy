<?php

namespace App\Actions\Users;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use App\Support\RoleCombinationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SA picks an active staff record without a user, sets username and roles. The account
 * is created `pending_approval` and cannot sign in until approved (AC-28).
 *
 * @uc UC-35 @fr FR-8.1,FR-8.2,FR-8.8 @br BR-14 @ac AC-28
 */
class CreateUserAccount
{
    public function __construct(private ApprovalService $approvals) {}

    /**
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    public function execute(User $sa, Staff $staff, string $username, array $roles): ApprovalRequest
    {
        Gate::forUser($sa)->authorize('users.manage');

        $username = trim($username);
        $v = validator(['username' => $username, 'roles' => $roles], [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:'.implode(',', \App\Enums\Role::values())],
        ]);
        $v->validate();

        if (! RoleCombinationRule::allows($roles)) {
            throw ValidationException::withMessages(['roles' => RoleCombinationRule::message($roles)]);
        }
        if ($staff->status === Staff::ENDED) {
            throw ValidationException::withMessages(['staff' => __('users.error.staff_ended')]);
        }
        if ($staff->user_id) {
            throw ValidationException::withMessages(['staff' => __('users.error.staff_has_user')]);
        }

        return DB::transaction(function () use ($sa, $staff, $username, $roles) {
            $user = User::create([
                'name' => $staff->name,
                'username' => $username,
                'password' => Str::random(40),               // unusable until approval sets the real one
                'is_active' => false,
                'account_status' => User::STATUS_PENDING,
                'must_change_password' => true,
            ]);
            $user->roles()->sync(Role::whereIn('slug', $roles)->pluck('id'));
            $user->unsetRelation('roles');

            AuditLogger::record('user.account.requested', $user, [], ['username' => $username, 'roles' => $roles, 'staff_id' => $staff->id], [], $sa);

            return $this->approvals->request(
                'user.create',
                $sa,
                ['user_id' => $user->id, 'staff_id' => $staff->id, 'roles' => array_values($roles)],
                __('users.summary.create', ['username' => $username, 'name' => $staff->name]),
                $user,
                $user,
            );
        });
    }
}

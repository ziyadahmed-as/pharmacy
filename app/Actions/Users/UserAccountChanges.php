<?php

namespace App\Actions\Users;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EndUserSessions;
use Illuminate\Support\Facades\DB;

/** Shared low-level writes used by the UC-35 approval handlers (run only after approval). */
class UserAccountChanges
{
    public function __construct(private EndUserSessions $endSessions) {}

    /** @param  list<string>  $roles */
    public function syncRoles(User $user, array $roles, ?User $actor, ?User $approver, ?string $reason = null): void
    {
        $old = $user->roles()->pluck('slug')->sort()->values()->all();
        $new = collect($roles)->unique()->sort()->values()->all();
        $ids = Role::whereIn('slug', $new)->pluck('id')->all();
        $user->roles()->sync($ids);
        $user->unsetRelation('roles');

        DB::table('role_assignment_history')->insert([
            'user_id' => $user->id,
            'old_roles' => json_encode($old),
            'new_roles' => json_encode($new),
            'actor_id' => $actor?->id,
            'approver_id' => $approver?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        if ($old !== $new) {
            AuditLogger::record('user.roles.updated', $user, ['roles' => $old], ['roles' => $new], ['reason' => $reason, 'approver_id' => $approver?->id]);
        }
    }

    public function deactivate(User $user, ?string $reason, ?User $approver): void
    {
        $user->forceFill(['is_active' => false])->save();
        ($this->endSessions)($user);
        AuditLogger::record('user.account.deactivated', $user, ['is_active' => true], ['is_active' => false], ['reason' => $reason, 'approver_id' => $approver?->id]);
    }

    public function reactivate(User $user, ?string $reason, ?User $approver): void
    {
        $user->forceFill(['is_active' => true, 'account_status' => User::STATUS_ACTIVE, 'locked_at' => null, 'failed_login_attempts' => 0])->save();
        AuditLogger::record('user.account.reactivated', $user, ['is_active' => false], ['is_active' => true], ['reason' => $reason, 'approver_id' => $approver?->id]);
    }

    public static function roleSlugs(array $roles): array
    {
        return array_map(fn ($r) => $r instanceof RoleEnum ? $r->value : (string) $r, $roles);
    }
}

<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * SA clears a lockout after confirming the person's identity (E1: confirmation and note required).
 *
 * @uc UC-36 @fr FR-8.6,FR-8.10 @ac AC-18
 * Audit: user.account.unlocked (actor, target, note)
 */
class UnlockAccount
{
    public function execute(User $sa, User $target, bool $identityVerified, string $note): User
    {
        Gate::forUser($sa)->authorize('users.recover');
        ResetPassword::guard($sa, $target, $identityVerified, $note);

        if (! $target->isLocked()) {
            throw ValidationException::withMessages(['target' => __('users.error.not_locked')]);
        }

        DB::transaction(function () use ($sa, $target, $note) {
            $old = ['locked_at' => (string) $target->locked_at, 'failed_login_attempts' => $target->failed_login_attempts];
            $target->forceFill(['locked_at' => null, 'failed_login_attempts' => 0])->save();
            AuditLogger::record('user.account.unlocked', $target, $old, ['locked_at' => null, 'failed_login_attempts' => 0], [
                'identity_verified' => true, 'note' => $note, 'target_username' => $target->username,
            ], $sa);
        });

        return $target->refresh();
    }
}

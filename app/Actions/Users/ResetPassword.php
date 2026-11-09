<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EndUserSessions;
use App\Services\Notifier;
use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SA resets a password: random temporary password shown once (returned, never stored
 * or logged in plain text), must_change_password, old hash into history, sessions ended,
 * Owner and PM notified, at most 10 resets per hour per SA.
 *
 * @uc UC-36 @fr FR-8.6,FR-8.10 @ac AC-18
 * Audit: user.password.reset (actor, target, note; never the password)
 */
class ResetPassword
{
    public const MAX_PER_HOUR = 10;

    public function __construct(private EndUserSessions $endSessions) {}

    /** @return array{user: User, temporary_password: string} */
    public function execute(User $sa, User $target, bool $identityVerified, string $note, bool $alsoUnlock = true): array
    {
        Gate::forUser($sa)->authorize('users.recover');
        self::guard($sa, $target, $identityVerified, $note);

        $key = 'pw-reset:'.$sa->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages(['target' => __('users.error.reset_throttle')]);
        }
        if (! $target->isEligibleForPasswordReset()) {
            throw ValidationException::withMessages(['target' => __('users.error.not_resettable')]);
        }
        RateLimiter::hit($key, 3600);

        $temporary = Str::password(14, symbols: false);

        DB::transaction(function () use ($sa, $target, $note, $temporary, $alsoUnlock) {
            PasswordPolicy::set($target, $temporary, mustChange: true);
            $target->forceFill(['password_reset_at' => now()] + ($alsoUnlock ? ['locked_at' => null, 'failed_login_attempts' => 0] : []))->save();
            ($this->endSessions)($target);

            AuditLogger::record('user.password.reset', $target, [], [], [
                'identity_verified' => true, 'note' => $note, 'target_username' => $target->username, 'unlocked' => $alsoUnlock,
            ], $sa);
        });

        Notifier::toRoles([Role::Owner, Role::PharmacyManager], 'user.password.reset', [
            'message_key' => 'users.notify.password_reset',
            'message_params' => ['user' => $target->username, 'admin' => $sa->username],
        ]);

        return ['user' => $target->refresh(), 'temporary_password' => $temporary];
    }

    /** Shared guard for UC-36 actions. @throws ValidationException */
    public static function guard(User $sa, User $target, bool $identityVerified, string $note): void
    {
        if (! $identityVerified) {
            throw ValidationException::withMessages(['identityVerified' => __('users.error.identity_required')]);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('users.error.note_required')]);
        }
        if ($sa->id === $target->id) {
            throw ValidationException::withMessages(['target' => __('users.error.self_recovery')]);
        }
    }
}

<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authenticates by username and password. Refuses deactivated, pending and locked
 * users, counts failures and locks at the configured threshold. The error text never
 * reveals whether the username exists.
 *
 * @uc UC-33 @fr FR-8.1,FR-8.4,FR-8.6 @ac AC-18,AC-32
 * Audit: auth.login.success|failed|locked (success is written by CompleteLogin)
 */
class LogIn
{
    public function __construct(private CompleteLogin $complete) {}

    /**
     * @return array{status: 'authenticated'|'mfa_required', user: User, redirect: string}
     *
     * @throws ValidationException
     */
    public function execute(string $username, string $password, bool $remember = false): array
    {
        $generic = ValidationException::withMessages(['username' => __('auth.failed')]);

        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (! $user) {
            AuditLogger::record('auth.login.failed', null, [], [], ['attempted_username' => $username, 'reason' => 'unknown_user']);
            // Burn comparable time so the response does not betray existence.
            Hash::check($password, '$2y$12$pxkzfeKixJKnM26EDJmDB.Xtv2qzl.ZNtS4pkJ44ufz1eGZks8L3q');
            throw $generic;
        }

        if ($user->account_status === User::STATUS_PENDING) {
            AuditLogger::record('auth.login.failed', $user, [], [], ['reason' => 'pending_approval'], $user);
            throw $generic;
        }

        if (! $user->is_active) {
            AuditLogger::record('auth.login.failed', $user, [], [], ['reason' => 'deactivated'], $user);
            throw ValidationException::withMessages(['username' => __('auth.deactivated')]);
        }

        if ($user->isLocked()) {
            AuditLogger::record('auth.login.failed', $user, [], [], ['reason' => 'locked'], $user);
            throw ValidationException::withMessages(['username' => __('auth.locked')]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->recordFailure($user);
            throw $generic;
        }

        $user->forceFill(['failed_login_attempts' => 0])->save();

        if (config('pms.two_factor_enabled', true) && $user->requiresMfa()) {
            session()->put('login.mfa_user_id', $user->id);
            session()->put('login.remember', $remember);
            AuditLogger::record('auth.mfa.prompted', $user, [], [], [], $user);

            return ['status' => 'mfa_required', 'user' => $user, 'redirect' => route('two-factor.login')];
        }

        return $this->complete->execute($user, $remember);
    }

    private function recordFailure(User $user): void
    {
        $threshold = max(1, (int) setting('security.max_failed_logins', 5));

        DB::transaction(function () use ($user, $threshold) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            $count = $fresh->failed_login_attempts + 1;
            $lock = $count >= $threshold;

            $fresh->forceFill([
                'failed_login_attempts' => $count,
                'locked_at' => $lock ? now() : $fresh->locked_at,
            ])->save();

            AuditLogger::record(
                $lock ? 'auth.login.locked' : 'auth.login.failed',
                $fresh,
                [],
                [],
                ['reason' => 'bad_password', 'failed_attempts' => $count, 'threshold' => $threshold],
                $fresh
            );
        });
    }
}

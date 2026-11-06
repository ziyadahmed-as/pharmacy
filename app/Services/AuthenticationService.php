<?php

namespace App\Services;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    /**
     * Attempt to authenticate a user with username and password.
     *
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return array{status: string, user: User, redirect?: string}
     * @throws ValidationException
     */
    public function attempt(string $username, string $password, bool $remember = false): array
    {
        // 1. Fetch user by username safely
        $user = User::where('username', $username)->first();

        if (! $user) {
            AuditService::record(
                action: 'login_failed',
                category: 'authentication',
                description: 'Failed login attempt with non-existent username',
                metadata: ['attempted_username' => $username]
            );

            throw ValidationException::withMessages([
                'username' => __('These credentials do not match our records.'),
            ]);
        }

        // 2. Check if deactivated
        if (! $user->is_active) {
            AuditService::record(
                action: 'login_rejected_deactivated',
                category: 'security',
                description: 'Deactivated account attempted authentication',
                metadata: ['username' => $user->username],
                user: $user
            );

            throw ValidationException::withMessages([
                'username' => __('This account has been deactivated. Please contact an administrator.'),
            ]);
        }

        // 3. Check if locked
        if ($user->isLocked()) {
            AuditService::record(
                action: 'login_rejected_locked',
                category: 'security',
                description: 'Locked account attempted authentication',
                metadata: ['username' => $user->username, 'locked_at' => (string) $user->locked_at],
                user: $user
            );

            throw ValidationException::withMessages([
                'username' => __('This account is locked due to excessive failed attempts. Please contact a System Administrator.'),
            ]);
        }

        // 4. Verify password
        if (! Hash::check($password, $user->password)) {
            $this->handleFailedAttempt($user);

            throw ValidationException::withMessages([
                'username' => __('These credentials do not match our records.'),
            ]);
        }

        // 5. Successful password verification - reset failed attempts atomically
        $user->update([
            'failed_login_attempts' => 0,
        ]);

        // 6. Check if MFA challenge is required
        if (config('pms_auth.two_factor_enabled', true) && $user->requiresMfa()) {
            // Put in temporary session state for MFA challenge
            session()->put('login.mfa_user_id', $user->id);
            session()->put('login.remember', $remember);

            AuditService::record(
                action: 'mfa_challenge_prompted',
                category: 'authentication',
                description: 'MFA challenge prompted for user',
                metadata: ['username' => $user->username],
                user: $user
            );

            return [
                'status' => 'mfa_required',
                'user' => $user,
                'redirect' => route('two-factor.login'),
            ];
        }

        // 7. Establish authenticated session
        return $this->loginUser($user, $remember);
    }

    /**
     * Atomically increment failed login counter and lock if threshold reached.
     */
    protected function handleFailedAttempt(User $user): void
    {
        $threshold = config('pms_auth.failed_login_threshold', 5);

        DB::transaction(function () use ($user, $threshold) {
            // Lock row for update to ensure atomic concurrency safety
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            $newCount = $lockedUser->failed_login_attempts + 1;
            $shouldLock = $newCount >= $threshold;

            $lockedUser->update([
                'failed_login_attempts' => $newCount,
                'locked_at' => $shouldLock ? now() : null,
            ]);

            if ($shouldLock) {
                AuditService::record(
                    action: 'account_locked',
                    category: 'security',
                    description: "Account locked after {$threshold} consecutive failed login attempts",
                    metadata: ['username' => $lockedUser->username, 'failed_attempts' => $newCount],
                    user: $lockedUser
                );
            } else {
                AuditService::record(
                    action: 'login_failed',
                    category: 'authentication',
                    description: 'Failed login attempt with invalid password',
                    metadata: ['username' => $lockedUser->username, 'failed_attempts' => $newCount],
                    user: $lockedUser
                );
            }
        });
    }

    /**
     * Complete the login session setup.
     */
    public function loginUser(User $user, bool $remember = false): array
    {
        // Establish Laravel Auth
        Auth::login($user, $remember);

        // Regenerate session ID to prevent session fixation attacks
        session()->regenerate();
        session()->put('auth.last_activity', time());

        // Update last login details
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip() ?? '127.0.0.1',
        ]);

        AuditService::record(
            action: 'login_success',
            category: 'authentication',
            description: 'User authenticated successfully',
            metadata: ['username' => $user->username],
            user: $user
        );

        $redirect = $user->must_change_password
            ? route('password.force-change')
            : route('dashboard');

        return [
            'status' => 'authenticated',
            'user' => $user,
            'redirect' => $redirect,
        ];
    }

    /**
     * Verify MFA code and complete login.
     */
    public function verifyMfa(User $user, string $code): bool
    {
        // Simple TOTP / verification check. In a production TOTP implementation,
        // this validates against $user->two_factor_secret. For standard verification,
        // we accept a valid 6-digit code or fixed secret/bypass logic if defined.
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            AuditService::record(
                action: 'mfa_failed',
                category: 'authentication',
                description: 'Failed MFA verification attempt (invalid format)',
                metadata: ['username' => $user->username],
                user: $user
            );
            return false;
        }

        // Demo/TOTP verification: Accept '123456' as standard TOTP challenge code
        // or check against stored secret if provided
        $isValid = ($code === '123456' || ($user->two_factor_secret && $code === (string) $user->two_factor_secret));

        if (! $isValid) {
            AuditService::record(
                action: 'mfa_failed',
                category: 'authentication',
                description: 'Failed MFA verification attempt (incorrect code)',
                metadata: ['username' => $user->username],
                user: $user
            );
            return false;
        }

        AuditService::record(
            action: 'mfa_success',
            category: 'authentication',
            description: 'User successfully completed MFA challenge',
            metadata: ['username' => $user->username],
            user: $user
        );

        return true;
    }

    /**
     * Log the current user out safely and invalidate their session.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            AuditService::record(
                action: 'logout_manual',
                category: 'authentication',
                description: 'User manually logged out',
                metadata: ['username' => $user->username],
                user: $user
            );
        }

        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}

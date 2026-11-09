<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Totp;
use Illuminate\Support\Facades\RateLimiter;

/** Checks a real TOTP code (RFC 6238), throttled to 5 tries per minute per user. */
class VerifyTwoFactor
{
    public function execute(User $user, string $code): bool
    {
        $key = 'mfa:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            AuditLogger::record('auth.mfa.throttled', $user, [], [], [], $user);

            return false;
        }

        if (! $user->two_factor_secret || ! Totp::verify($user->two_factor_secret, $code)) {
            RateLimiter::hit($key, 60);
            AuditLogger::record('auth.mfa.failed', $user, [], [], [], $user);

            return false;
        }

        RateLimiter::clear($key);
        AuditLogger::record('auth.mfa.success', $user, [], [], [], $user);

        return true;
    }
}

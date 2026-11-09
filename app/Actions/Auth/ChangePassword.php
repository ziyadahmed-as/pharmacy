<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Forced or voluntary password change: minimum length, last N not reusable (A1).
 *
 * @uc UC-33 @fr FR-8.6 @ac AC-18
 */
class ChangePassword
{
    /** @throws ValidationException */
    public function execute(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_wrong')]);
        }

        $violations = PasswordPolicy::violations($user, $new);
        if ($violations !== []) {
            throw ValidationException::withMessages(['password' => $violations]);
        }

        $wasForced = $user->must_change_password;
        PasswordPolicy::set($user, $new, mustChange: false);

        AuditLogger::record('auth.password.changed', $user, [], [], ['forced' => $wasForced], $user);
    }
}

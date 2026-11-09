<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\ActingRole;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/** Establishes the session after password (and optional second factor) succeeded. */
class CompleteLogin
{
    /** @return array{status: 'authenticated', user: User, redirect: string} */
    public function execute(User $user, bool $remember = false): array
    {
        Auth::login($user, $remember);
        session()->regenerate();
        session()->put('auth.last_activity', time());

        // A single-role user acts under that role; several roles must choose (step 7).
        session()->forget(ActingRole::SESSION_KEY);
        $roles = $user->roleEnums();
        if (count($roles) === 1) {
            session()->put(ActingRole::SESSION_KEY, $roles[0]->value);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip() ?? '127.0.0.1',
        ])->save();

        AuditLogger::record('auth.login.success', $user, [], [], [], $user);

        $redirect = match (true) {
            $user->must_change_password => route('password.force-change'),
            ActingRole::needsSelection($user) => route('acting-role'),
            default => route('dashboard'),
        };

        return ['status' => 'authenticated', 'user' => $user, 'redirect' => $redirect];
    }
}

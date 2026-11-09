<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ends the session of a user who was deactivated, locked or made pending (AC-32). */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (! $user->canSignIn() || $user->isLocked())) {
            AuditLogger::record(
                'auth.session.terminated',
                $user,
                [],
                [],
                ['reason' => ! $user->is_active ? 'deactivated' : ($user->isLocked() ? 'locked' : 'pending')],
                $user
            );

            $message = ! $user->is_active ? __('auth.deactivated') : __('auth.locked');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => $message]);
        }

        return $next($request);
    }
}

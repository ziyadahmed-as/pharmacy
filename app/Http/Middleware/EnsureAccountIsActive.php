<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            if (! $user->is_active) {
                AuditService::record(
                    action: 'session_terminated_deactivated',
                    category: 'security',
                    description: 'Terminated active session for deactivated user',
                    metadata: ['username' => $user->username],
                    user: $user
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'username' => 'Your account has been deactivated.',
                ]);
            }

            if ($user->isLocked()) {
                AuditService::record(
                    action: 'session_terminated_locked',
                    category: 'security',
                    description: 'Terminated active session for locked user',
                    metadata: ['username' => $user->username],
                    user: $user
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'username' => 'Your account is locked. Please contact a System Administrator.',
                ]);
            }
        }

        return $next($request);
    }
}


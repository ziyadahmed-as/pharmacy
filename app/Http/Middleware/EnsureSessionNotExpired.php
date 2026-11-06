<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionNotExpired
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = config('pms_auth.session_inactivity_timeout', 15);
            $maxIdleSeconds = $timeoutMinutes * 60;
            $lastActivity = $request->session()->get('auth.last_activity');

            if ($lastActivity && (time() - $lastActivity > $maxIdleSeconds)) {
                $user = Auth::user();

                AuditService::record(
                    action: 'session_expired_inactivity',
                    category: 'authentication',
                    description: "User session expired due to {$timeoutMinutes} minutes of inactivity",
                    metadata: [
                        'username' => $user?->username,
                        'idle_seconds' => time() - $lastActivity,
                    ],
                    user: $user
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'username' => 'Your session has expired due to inactivity. Please log in again.',
                ]);
            }

            // Update last activity timestamp
            $request->session()->put('auth.last_activity', time());
        }

        return $next($request);
    }
}


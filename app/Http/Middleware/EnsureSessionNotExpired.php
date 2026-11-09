<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LogOut;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side idle logout (UC-33 step 4, AC-18). Limit from setting
 * security.idle_timeout_minutes. Logs auth.logout.idle.
 */
class EnsureSessionNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $limit = max(1, (int) setting('security.idle_timeout_minutes', 15)) * 60;
            $last = $request->session()->get('auth.last_activity');

            if ($last && (time() - $last) > $limit) {
                app(LogOut::class)->execute('idle');

                if ($request->expectsJson() || $request->header('X-Livewire')) {
                    abort(419, __('auth.idle_logout'));
                }

                return redirect()->route('login')->withErrors(['username' => __('auth.idle_logout')]);
            }

            $request->session()->put('auth.last_activity', time());
        }

        return $next($request);
    }
}

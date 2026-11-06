<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password) {
            // Allow access to password force-change routes and logout
            if (! $request->routeIs('password.force-change') && ! $request->routeIs('logout')) {
                return redirect()->route('password.force-change');
            }
        }

        return $next($request);
    }
}

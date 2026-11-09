<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.force-change', 'logout', 'locale.switch')) {
            return redirect()->route('password.force-change');
        }

        return $next($request);
    }
}

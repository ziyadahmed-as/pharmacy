<?php

namespace App\Http\Middleware;

use App\Services\ActingRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A user with several roles must pick the acting role for the session (UC-33 step 7). */
class EnsureActingRoleChosen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ActingRole::needsSelection($user) && ! $request->routeIs('acting-role', 'logout', 'locale.switch')) {
            return redirect()->route('acting-role');
        }

        return $next($request);
    }
}

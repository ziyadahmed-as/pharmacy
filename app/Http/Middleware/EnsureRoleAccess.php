<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    /** @param  string  ...$roles allowed role slugs */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($roles !== [] && ! $user->hasAnyRole($roles)) {
            AuditLogger::record('auth.access.denied', null, [], [], [
                'required_roles' => $roles,
                'url' => $request->fullUrl(),
            ]);

            abort(403, __('auth.role_denied'));
        }

        return $next($request);
    }
}

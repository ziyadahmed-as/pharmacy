<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles Allowed role slugs
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (! $user->hasAnyRole($roles)) {
            AuditService::record(
                action: 'unauthorized_role_access_attempt',
                category: 'authorization',
                description: 'User attempted to access route without required role',
                metadata: [
                    'username' => $user->username,
                    'required_roles' => $roles,
                    'attempted_url' => $request->fullUrl(),
                ],
                user: $user
            );

            abort(403, 'Unauthorized. Your assigned role does not grant access to this section.');
        }

        return $next($request);
    }
}

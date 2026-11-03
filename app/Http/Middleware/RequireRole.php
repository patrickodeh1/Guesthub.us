<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active' || ! $user->is_active) {
            abort(403, 'Your account is inactive. Please contact the system owner.');
        }

        // Fail closed: a bare `role` (no roles listed) used to let every logged-in user through.
        abort_if(empty($roles), 500, 'The role middleware must be given at least one role.');

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}

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

        if (! $user || $user->status !== 'active') {
            abort(403, 'Your account is inactive. Please contact the system owner.');
        }

        if (! empty($roles) && ! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}

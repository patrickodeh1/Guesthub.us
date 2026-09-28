<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            // Check if must_change_password flag is true or if their password is literally 'password'
            if ($user->must_change_password || \Illuminate\Support\Facades\Hash::check('password', $user->password)) {
                // Allow them to visit the forced change form, submit it, or log out
                if (!$request->routeIs('password.force-change', 'password.force-change.store', 'logout')) {
                    return redirect()->route('password.force-change');
                }
            }
        }

        return $next($request);
    }
}

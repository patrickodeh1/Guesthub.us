<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $property = $request->route('property');
        $id = $property instanceof Property ? $property->id : $property;

        if ($user && $id && ! $user->hasRole('admin')
            && ! Property::visibleTo($user)->whereKey($id)->exists()) {
            abort(403, 'You do not have access to this property.');
        }

        return $next($request);
    }
}

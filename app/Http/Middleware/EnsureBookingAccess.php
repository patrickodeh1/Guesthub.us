<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Support\BookingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any route with a {booking} parameter unless that booking belongs to a
 * property the signed-in user may access. Applied to the whole /admin group, so
 * new {booking} routes are protected automatically.
 */
class EnsureBookingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $booking = $request->route('booking');
        $id = $booking instanceof Booking ? $booking->getKey() : $booking;

        if ($user && $id && ! BookingAccess::query($user)->whereKey($id)->exists()) {
            abort(403, 'You do not have access to this guest.');
        }

        return $next($request);
    }
}

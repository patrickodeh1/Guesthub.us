<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for "which bookings (guests) may this user see".
 * Admin: all. Everyone else: only bookings on properties Property::visibleTo() allows.
 */
class BookingAccess
{
    public static function query(User $user): Builder
    {
        $query = Booking::query();

        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->whereIn('property_id', Property::visibleTo($user)->select('properties.id'));
    }
}

<?php
namespace App\Services;

use App\Models\Booking;
use App\Models\GuestSession;
use Illuminate\Support\Str;

class GuestSessionService
{
    public const COOKIE_NAME = 'guesthub_session';

    public static function refreshCookie(Booking $booking): void
    {
        $token = Str::random(64);
        GuestSession::updateOrCreate(
            ['booking_id' => $booking->id],
            ['token_hash' => hash('sha256', $token), 'expires_at' => self::expiryFor($booking)]
        );
        $expiryMinutes = now()->diffInMinutes(self::expiryFor($booking));
        \Illuminate\Support\Facades\Cookie::queue(
            self::COOKIE_NAME,
            $token,
            $expiryMinutes,
            null,
            null,
            true,
            true
        );
    }


    public static function resolve(?string $token): ?Booking
    {
        if (! $token) {
            return null;
        }
        $session = GuestSession::where('token_hash', hash('sha256', $token))->first();
        if (! $session || $session->isExpired()) {
            return null;
        }
        return $session->booking;
    }

    public static function expiryFor(Booking $booking): \Carbon\Carbon
    {
        $checkout = $booking->check_out_date ?? now();
        return \Carbon\Carbon::parse($checkout)->addDays(3);
    }
}

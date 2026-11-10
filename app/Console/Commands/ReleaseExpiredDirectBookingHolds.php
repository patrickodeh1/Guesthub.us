<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\DirectBookingService;
use Illuminate\Console\Command;

class ReleaseExpiredDirectBookingHolds extends Command
{
    protected $signature = 'directbookings:release-expired-holds';

    protected $description = 'Expire direct-booking holds and send halfway reminders.';

    public function handle(DirectBookingService $bookings): int
    {
        $expired = 0;
        Booking::query()
            ->where('status', Booking::STATUS_PENDING_PAYMENT)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->chunkById(100, function ($holds) use ($bookings, &$expired) {
                foreach ($holds as $booking) {
                    if ($bookings->expireHold($booking)) {
                        $expired++;
                    }
                }
            });

        $reminded = 0;
        Booking::query()
            ->where('status', Booking::STATUS_PENDING_PAYMENT)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '>', now())
            ->whereNull('direct_booking_reminded_at')
            ->chunkById(100, function ($holds) use ($bookings, &$reminded) {
                foreach ($holds as $booking) {
                    if ($bookings->sendHoldReminder($booking)) {
                        $reminded++;
                    }
                }
            });

        $this->info("Expired {$expired} direct-booking holds and sent {$reminded} reminders.");

        return self::SUCCESS;
    }
}

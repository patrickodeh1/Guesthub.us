<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class SendCheckoutReminders extends Command
{
    protected $signature = 'bookings:send-checkout-reminders';

    protected $description = 'Sends the "check-out is available tomorrow" guest alert once per booking on the day before check-out';

    public function handle(): int
    {
        // "Tomorrow" in the host's local timezone, compared as a date string.
        $tomorrow = now()->setTimezone(config('app.display_timezone'))->addDay()->toDateString();

        $bookings = Booking::query()
            ->whereDate('check_out_date', $tomorrow)
            ->whereNull('checkout_reminder_sent_at')
            ->whereNotIn('status', ['cancelled', 'checked_out'])
            ->with('property')
            ->get();

        foreach ($bookings as $booking) {
            GuestAlertService::send('checkout_reminder', $booking);
            $booking->update(['checkout_reminder_sent_at' => now()]);
        }

        $this->info("Sent checkout reminders for {$bookings->count()} booking(s).");

        return self::SUCCESS;
    }
}

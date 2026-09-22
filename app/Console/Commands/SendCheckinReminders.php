<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class SendCheckinReminders extends Command
{
    protected $signature = 'bookings:send-checkin-reminders';

    protected $description = 'Sends the "time to check in" guest alert (task 30) once per booking on its check-in day';

    public function handle(): int
    {
        $bookings = Booking::query()
            ->whereDate('check_in_date', now()->toDateString())
            ->whereNull('checkin_reminder_sent_at')
            ->whereIn('status', ['guest_approved'])
            ->with('property')
            ->get();

        foreach ($bookings as $booking) {
            GuestAlertService::send('time_to_check_in', $booking);
            $booking->update(['checkin_reminder_sent_at' => now()]);
        }

        $this->info("Sent check-in reminders for {$bookings->count()} booking(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class RemindIncompleteCheckins extends Command
{
    protected $signature = 'bookings:remind-incomplete-checkins';

    protected $description = 'Remind guests every thirty minutes after GPS verification until they confirm arrival';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(30);

        $bookings = Booking::query()
            ->whereDate('check_in_date', now()->toDateString())
            ->whereNotNull('checkin_instructions_started_at')
            ->where('checkin_instructions_started_at', '<=', $cutoff)
            ->whereNull('checked_in_at')
            ->where(function ($query) {
                $query->whereNull('manually_checked_in')
                    ->orWhere('manually_checked_in', false);
            })
            ->where('status', '<>', 'currently_hosting')
            ->whereNotIn('status', ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show', 'checked_out'])
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_incomplete_checkin_reminder_at')
                    ->orWhere('last_incomplete_checkin_reminder_at', '<=', $cutoff);
            })
            ->with('property')
            ->get();

        foreach ($bookings as $booking) {
            GuestAlertService::send('incomplete_checkin_reminder', $booking);
            $booking->update(['last_incomplete_checkin_reminder_at' => now()]);
        }

        $this->info("Processed {$bookings->count()} incomplete check-in reminder(s).");

        return self::SUCCESS;
    }
}

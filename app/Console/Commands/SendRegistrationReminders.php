<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class SendRegistrationReminders extends Command
{
    protected $signature = 'bookings:send-registration-reminders';

    protected $description = 'Daily nudge to guests who have not finished registration or still have something pending';

    public function handle(): int
    {
        $today = now()->setTimezone(config('app.display_timezone'))->toDateString();

        $bookings = Booking::query()
            ->notArchived()
            ->whereNull('cancelled_at')
            ->whereDate('check_in_date', '>=', $today)
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['guest_approved', 'checked_in', 'checked_out', 'cancelled']))
            ->where(fn ($q) => $q->whereNull('last_registration_reminder_at')->orWhere('last_registration_reminder_at', '<=', now()->subHours(20)))
            ->with('property')
            ->get();

        foreach ($bookings as $booking) {
            $pn = $booking->property?->guest_display_name ?? 'your stay';
            $pending = filled($booking->registration_notified_at)
                ? "we still need something from you before check-in at {$pn}. Open your link to see what's pending:"
                : "this is a friendly reminder to finish your registration for {$pn} so we can get everything ready for your stay.";

            GuestAlertService::send('registration_reminder', $booking, ['pending_text' => $pending]);
            Booking::whereKey($booking->id)->update(['last_registration_reminder_at' => now()]);
        }

        $this->info("Registration reminders processed for {$bookings->count()} booking(s).");

        return self::SUCCESS;
    }
}

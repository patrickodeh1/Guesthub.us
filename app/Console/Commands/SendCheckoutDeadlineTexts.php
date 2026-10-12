<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class SendCheckoutDeadlineTexts extends Command
{
    protected $signature = 'bookings:send-checkout-deadline-texts {--dry-run : List what would be sent, send and mark nothing}';

    protected $description = 'Texts guests one hour before check-out, and again an hour after if they have not checked out';

    public function handle(): int
    {
        $today = now()->setTimezone(config('app.display_timezone'));
        $dry = (bool) $this->option('dry-run');

        $bookings = Booking::query()
            ->whereNull('checked_out_at')
            ->whereIn('status', ['guest_approved', 'currently_hosting'])
            ->whereDate('check_out_date', '>=', $today->copy()->subDay()->toDateString())
            ->whereDate('check_out_date', '<=', $today->copy()->addDay()->toDateString())
            ->where(fn ($q) => $q->whereNull('checkout_soon_sent_at')->orWhereNull('checkout_overdue_sent_at'))
            ->with('property')
            ->get();

        $soon = 0;
        $overdue = 0;
        foreach ($bookings as $b) {
            if ($b->isCancelled()) {
                continue;
            }

            // From 60 minutes before check-out until check-out time.
            if (! $b->checkout_soon_sent_at && $b->isPastCheckoutGracePeriod(-60) && ! $b->isPastCheckoutGracePeriod(0)) {
                $this->line(($dry ? '[dry] ' : '')."soon: booking {$b->id} {$b->guest_name}");
                if (! $dry) {
                    $b->forceFill(['checkout_soon_sent_at' => now()])->saveQuietly();
                    GuestAlertService::send('checkout_soon', $b);
                }
                $soon++;
            }

            // From 60 minutes after check-out until the 90-minute auto-checkout.
            if (! $b->checkout_overdue_sent_at && $b->isPastCheckoutGracePeriod(60) && ! $b->isPastCheckoutGracePeriod(90)) {
                $this->line(($dry ? '[dry] ' : '')."overdue: booking {$b->id} {$b->guest_name}");
                if (! $dry) {
                    $b->forceFill(['checkout_overdue_sent_at' => now()])->saveQuietly();
                    GuestAlertService::send('checkout_overdue', $b);
                }
                $overdue++;
            }
        }

        $this->info(($dry ? '[dry run] ' : '')."Check-out texts: {$soon} one-hour-before, {$overdue} one-hour-after.");

        return self::SUCCESS;
    }
}

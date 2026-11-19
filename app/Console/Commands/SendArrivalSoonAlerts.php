<?php

namespace App\Console\Commands;

use App\Helpers\TimezoneHelper;
use App\Models\Booking;
use App\Services\GuestAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendArrivalSoonAlerts extends Command
{
    /** Minutes before check-in (property time) at which the heads-up is sent. */
    private const LEAD_MINUTES = 70;

    protected $signature = 'bookings:send-arrival-soon';

    protected $description = 'Admin heads-up when an approved guest is about an hour from their check-in time';

    public function handle(): int
    {
        $fallback = config('app.display_timezone') ?: config('app.timezone');

        // "Today" differs by timezone, so take a 3-day window and decide per
        // property below using that property's own clock.
        $bookings = Booking::query()
            ->notArchived()
            ->whereNull('cancelled_at')
            ->whereDate('check_in_date', '>=', now($fallback)->subDay()->toDateString())
            ->whereDate('check_in_date', '<=', now($fallback)->addDay()->toDateString())
            ->whereNull('arrival_alert_sent_at')
            ->where('status', 'guest_approved')
            ->with('property')
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $tz = TimezoneHelper::isValid($booking->property?->timezone) ? $booking->property->timezone : $fallback;

            try {
                $at = Carbon::parse($booking->check_in_date->toDateString().' '.$booking->effectiveCheckinTimeFormatted(), $tz);
            } catch (\Throwable $e) {
                continue;
            }

            $mins = (int) round(now()->diffInMinutes($at, false));
            if ($mins < 0 || $mins > self::LEAD_MINUTES) {
                continue;
            }

            $arrivesIn = $mins >= 60 ? intdiv($mins, 60).'h '.($mins % 60).'m' : $mins.' min';

            GuestAlertService::send('arrival_soon', $booking, ['arrives_in' => $arrivesIn]);
            Booking::whereKey($booking->id)->update(['arrival_alert_sent_at' => now()]);
            $sent++;
        }

        $this->info("Arrival-soon alerts sent: {$sent}.");

        return self::SUCCESS;
    }
}

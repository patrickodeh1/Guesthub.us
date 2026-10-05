<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\GuestAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendArrivalSoonAlerts extends Command
{
    protected $signature = 'bookings:send-arrival-soon';

    protected $description = 'Admin heads-up when an approved guest is about an hour from their check-in time';

    public function handle(): int
    {
        $tz = config('app.display_timezone');

        $bookings = Booking::query()
            ->notArchived()
            ->whereNull('cancelled_at')
            ->whereDate('check_in_date', now($tz)->toDateString())
            ->whereNull('arrival_alert_sent_at')
            ->where('status', 'guest_approved')
            ->with('property')
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            try {
                $at = Carbon::parse($booking->check_in_date->toDateString().' '.$booking->effectiveCheckinTimeFormatted(), $booking->property?->timezone ?: $tz);
            } catch (\Throwable $e) {
                continue;
            }

            $mins = now()->diffInMinutes($at, false);
            if ($mins < 0 || $mins > 70) {
                continue;
            }

            GuestAlertService::send('arrival_soon', $booking);
            Booking::whereKey($booking->id)->update(['arrival_alert_sent_at' => now()]);
            $sent++;
        }

        $this->info("Arrival-soon alerts sent: {$sent}.");

        return self::SUCCESS;
    }
}

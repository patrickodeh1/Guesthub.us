<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Task 23: auto-checkout bookings 30 minutes after checkout time if the guest
// never pressed "All Done". Runs frequently so the 30-minute grace period is
// honored reasonably precisely without needing a real-time queue.
Schedule::command('bookings:auto-checkout')->everyFiveMinutes();

// Archives bookings past their checkout time. Previously ran as a side
// effect of loading the admin guest list or dashboard (full-table scan on
// every page view) - moved to the scheduler for the same reasons task 23's
// auto-checkout was: predictable cadence, no page-load cost.
Schedule::command('bookings:archive-overdue')->everyFiveMinutes();

// Task 30: "time to check in" alert, sent once per booking on its check-in
// day. Runs early morning so guests get it well before typical check-in
// times; the whereNull(checkin_reminder_sent_at) guard makes re-runs safe.
Schedule::command('bookings:send-checkin-reminders')->dailyAt('08:00');

// "Check-out is available tomorrow" alert, sent once per booking the evening
// before check-out (task 30). The whereNull(checkout_reminder_sent_at) guard
// makes re-runs safe.
Schedule::command('bookings:send-checkout-reminders')->dailyAt('18:00');

// Polls the active PMS provider (Channex now, NextPax later — see
// App\Services\Pms) for new/changed bookings. Cadence follows Channex's own
// recommended poll interval; webhooks (routes/web.php) supplement this for
// faster updates, this is the reliable backbone per their docs.
Schedule::command('pms:sync')->everyMinute()->when(
    fn () => now()->minute % max(1, (int) config('pms.poll_interval_minutes')) === 0
);

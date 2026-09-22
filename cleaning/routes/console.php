<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Automatically delete old session photos to prevent server space exhaustion
Schedule::command('photos:prune-old --days=14')->dailyAt('02:00');

// Send Pre-Arrival Training reminders (24h and Same Day)
Schedule::command('training:send-reminders')->hourly();

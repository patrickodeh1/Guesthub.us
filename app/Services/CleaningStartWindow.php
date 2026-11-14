<?php

namespace App\Services;

use App\Models\CleaningSession;
use Illuminate\Support\Carbon;

class CleaningStartWindow
{
    public const DEFAULT_MINUTES = 120;
    public const LATE_GRACE_HOURS = 2;

    public function minutesBefore(CleaningSession $session): int
    {
        $minutes = (int) ($session->property?->cleaner_start_window_minutes ?? self::DEFAULT_MINUTES);

        return max(0, $minutes);
    }

    public function scheduledAt(CleaningSession $session): ?Carbon
    {
        $rawDate = $session->getRawOriginal('scheduled_date');
        $rawTime = $session->getRawOriginal('scheduled_time');

        if (! $rawDate || ! $rawTime) {
            return null;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');

        // These columns describe a property-local wall-clock appointment.
        // Extract the calendar date and clock time independently so a datetime
        // cast or UTC serialization cannot shift either value across timezones.
        $date = Carbon::parse((string) $rawDate)->format('Y-m-d');
        $time = Carbon::parse((string) $rawTime)->format('H:i:s');

        return Carbon::createFromFormat('!Y-m-d H:i:s', $date.' '.$time, $timezone);
    }

    public function opensAt(CleaningSession $session): ?Carbon
    {
        $scheduled = $this->scheduledAt($session);

        return $scheduled?->copy()->subMinutes($this->minutesBefore($session));
    }

    public function closesAt(CleaningSession $session): ?Carbon
    {
        return $this->scheduledAt($session)?->addHours(self::LATE_GRACE_HOURS);
    }

    public function isTooEarly(CleaningSession $session): bool
    {
        $opensAt = $this->opensAt($session);
        if (! $opensAt) {
            return false;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');

        return Carbon::now($timezone)->lt($opensAt);
    }

    public function canStart(CleaningSession $session): bool
    {
        $opensAt = $this->opensAt($session);
        $closesAt = $this->closesAt($session);
        if (! $opensAt || ! $closesAt) {
            return true;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');
        $now = Carbon::now($timezone);

        return $now->greaterThanOrEqualTo($opensAt) && $now->lessThanOrEqualTo($closesAt);
    }
}

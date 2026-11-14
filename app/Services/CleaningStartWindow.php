<?php

namespace App\Services;

use App\Models\CleaningSession;
use Carbon\Carbon;

class CleaningStartWindow
{
    public const DEFAULT_MINUTES = 120;
    public const MAX_MINUTES = 240;
    public const LATE_GRACE_HOURS = 2;

    public function minutesBefore(CleaningSession $session): int
    {
        $minutes = (int) ($session->property?->cleaner_start_window_minutes ?? self::DEFAULT_MINUTES);

        return max(0, min(self::MAX_MINUTES, $minutes));
    }

    public function scheduledAt(CleaningSession $session): ?Carbon
    {
        if (! $session->scheduled_date || ! $session->scheduled_time) {
            return null;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');
        $time = Carbon::parse((string) $session->getRawOriginal('scheduled_time'), $timezone);

        return Carbon::parse($session->scheduled_date->toDateString(), $timezone)
            ->setTime($time->hour, $time->minute, $time->second);
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

        return now($timezone)->lt($opensAt);
    }

    public function canStart(CleaningSession $session): bool
    {
        $opensAt = $this->opensAt($session);
        $closesAt = $this->closesAt($session);
        if (! $opensAt || ! $closesAt) {
            return true;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');
        $now = now($timezone);

        return $now->betweenIncluded($opensAt, $closesAt);
    }
}

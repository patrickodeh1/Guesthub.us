<?php

namespace App\Services;

use App\Models\CleaningSession;
use Illuminate\Support\Carbon;

class CleaningStartWindow
{
    public const DEFAULT_MINUTES_BEFORE = 120;
    public const DEFAULT_MINUTES_AFTER = 120;

    public function minutesBefore(CleaningSession $session): int
    {
        return max(0, (int) ($session->property?->cleaner_start_window_minutes ?? self::DEFAULT_MINUTES_BEFORE));
    }

    public function minutesAfter(CleaningSession $session): int
    {
        return max(0, (int) ($session->property?->cleaner_end_window_minutes ?? self::DEFAULT_MINUTES_AFTER));
    }

    public function isEnabled(CleaningSession $session): bool
    {
        return (bool) ($session->property?->cleaner_start_window_enabled ?? true);
    }

    public function scheduledAt(CleaningSession $session): ?Carbon
    {
        $rawDate = $session->getRawOriginal('scheduled_date');
        $rawTime = $session->getRawOriginal('scheduled_time');
        if (! $rawDate || ! $rawTime) {
            return null;
        }

        $timezone = $session->property?->timezone ?: config('app.timezone');
        $date = Carbon::parse((string) $rawDate)->format('Y-m-d');
        $time = Carbon::parse((string) $rawTime)->format('H:i:s');

        return Carbon::createFromFormat('!Y-m-d H:i:s', $date.' '.$time, $timezone);
    }

    public function opensAt(CleaningSession $session): ?Carbon
    {
        return $this->scheduledAt($session)?->subMinutes($this->minutesBefore($session));
    }

    public function closesAt(CleaningSession $session): ?Carbon
    {
        return $this->scheduledAt($session)?->addMinutes($this->minutesAfter($session));
    }

    public function isTooEarly(CleaningSession $session): bool
    {
        if (! $this->isEnabled($session)) {
            return false;
        }
        $opensAt = $this->opensAt($session);
        if (! $opensAt) {
            return false;
        }
        $timezone = $session->property?->timezone ?: config('app.timezone');

        return Carbon::now($timezone)->lt($opensAt);
    }

    public function isTooLate(CleaningSession $session): bool
    {
        if (! $this->isEnabled($session)) {
            return false;
        }
        $closesAt = $this->closesAt($session);
        if (! $closesAt) {
            return false;
        }
        $timezone = $session->property?->timezone ?: config('app.timezone');

        return Carbon::now($timezone)->gt($closesAt);
    }

    public function canStart(CleaningSession $session): bool
    {
        $scheduledAt = $this->scheduledAt($session);
        if (! $scheduledAt) {
            return true;
        }
        $now = Carbon::now($session->property?->timezone ?: config('app.timezone'));

        if (! $this->isEnabled($session)) { return true; }

        if ($now->lt($scheduledAt->copy()->subMinutes($this->minutesBefore($session)))) {
            return false;
        }
        if ($now->gt($scheduledAt->copy()->addMinutes($this->minutesAfter($session)))) {
            return false;
        }

        return true;
    }
}

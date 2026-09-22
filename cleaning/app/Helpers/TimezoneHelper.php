<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeInterface;
use App\Models\Property;

/**
 * Centralised timestamp formatting for property-local display.
 *
 * All database timestamps in this app are stored in the application timezone
 * (America/New_York per config/app.php).  This helper converts them to the
 * property's IANA timezone and applies a consistent 12-hour display format.
 *
 * Usage:
 *   TimezoneHelper::format($session->started_at, $session->property);
 *   TimezoneHelper::toLocal($carbon, $property);
 */
class TimezoneHelper
{
    /** Default 12-hour display format used everywhere a time is shown to a human. */
    public const DATETIME = 'M j, Y g:i A';
    public const TIME_ONLY = 'g:i A';
    public const DATE_ONLY = 'M j, Y';
    public const PHOTO_STAMP = 'm/d/Y g:i:s A T';
    public const REPORT_TIMESTAMP = 'm/d/Y - g:i A (T)';

    /**
     * Convert a timestamp to the property's local timezone.
     *
     * Returns null if $time is null, making it safe to call on optional timestamps.
     */
    public static function toLocal(DateTimeInterface|Carbon|string|null $time, Property|string|null $timezoneSource = null): ?Carbon
    {
        if ($time === null) {
            return null;
        }

        $tz = self::resolveTimezone($timezoneSource);
        $carbon = $time instanceof Carbon ? $time->copy() : Carbon::parse($time);

        return $carbon->setTimezone($tz);
    }

    /**
     * One-stop display formatter: converts to property-local time and formats as
     * a 12-hour string.
     *
     * Returns $default when $time is null (avoids the need for `?? '--'` in views).
     */
    public static function format(
        DateTimeInterface|Carbon|string|null $time,
        Property|string|null $timezoneSource = null,
        string $format = self::DATETIME,
        string $default = '--'
    ): string {
        $local = self::toLocal($time, $timezoneSource);

        return $local ? $local->format($format) : $default;
    }

    /**
     * Resolve a timezone string from various input types.
     */
    private static function resolveTimezone(Property|string|null $source): string
    {
        if ($source instanceof Property) {
            return $source->timezone ?: config('app.timezone', 'America/New_York');
        }

        if (is_string($source) && $source !== '') {
            return $source;
        }

        return config('app.timezone', 'America/New_York');
    }
}

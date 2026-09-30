<?php

namespace App\Helpers;

use App\Models\Property;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Centralised timestamp formatting for property-local display.
 */
class TimezoneHelper
{
    public const DATETIME = 'M j, Y g:i A';
    public const TIME_ONLY = 'g:i A';
    public const DATE_ONLY = 'M j, Y';
    public const PHOTO_STAMP = 'm/d/Y g:i:s A T';
    public const REPORT_TIMESTAMP = 'm/d/Y - g:i A (T)';

    public static function toLocal(DateTimeInterface|Carbon|string|null $time, Property|string|null $timezoneSource = null): ?Carbon
    {
        if ($time === null) {
            return null;
        }

        $timezone = self::resolveTimezone($timezoneSource);
        $carbon = $time instanceof Carbon ? $time->copy() : Carbon::parse($time);

        return $carbon->setTimezone($timezone);
    }

    public static function format(
        DateTimeInterface|Carbon|string|null $time,
        Property|string|null $timezoneSource = null,
        string $format = self::DATETIME,
        string $default = '--'
    ): string {
        $local = self::toLocal($time, $timezoneSource);

        return $local ? $local->format($format) : $default;
    }

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

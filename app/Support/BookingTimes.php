<?php

namespace App\Support;

use Carbon\Carbon;

class BookingTimes
{
    /** A guest's requested time counts only when its status is approved; otherwise null (use the property's standard time). */
    public static function approved(?string $status, ?string $value): ?string
    {
        if (strtolower(trim((string) $status)) !== 'approved') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Approved guest time, else the property's standard time, formatted "g:i A" (null if neither exists). */
    public static function label(?string $status, ?string $preference, ?string $standard): ?string
    {
        $value = self::approved($status, $preference) ?? trim((string) $standard);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }
}

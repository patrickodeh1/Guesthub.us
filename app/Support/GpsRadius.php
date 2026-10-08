<?php

namespace App\Support;

use App\Models\Setting;

class GpsRadius
{
    public static function base(): float
    {
        return (float) (int) Setting::getValue('gps_radius_meters', 150);
    }

    /** Base radius plus the device-reported accuracy, capped (same rule as the guest GPS check). */
    public static function effective($accuracy = null): float
    {
        $cap = (int) Setting::getValue('gps_accuracy_bonus_cap_meters', 100);
        $bonus = min(max((float) ($accuracy ?? 0), 0), $cap);
        return self::base() + $bonus;
    }
}

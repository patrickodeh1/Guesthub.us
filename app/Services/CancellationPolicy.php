<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;

class CancellationPolicy
{
    public const DEFAULTS = [
        'flexible' => ['full_days' => 1, 'partial_days' => 0, 'partial_percent' => 0],
        'moderate' => ['full_days' => 5, 'partial_days' => 0, 'partial_percent' => 0],
        'strict' => ['full_days' => 14, 'partial_days' => 7, 'partial_percent' => 50],
    ];

    public static function rules(string $policy): array
    {
        $policy = array_key_exists($policy, self::DEFAULTS) ? $policy : 'moderate';
        $stored = json_decode((string) Setting::getValue('cancellation_policy_'.$policy, ''), true);
        $rules = array_merge(self::DEFAULTS[$policy], is_array($stored) ? $stored : []);

        return [
            'full_days' => max(0, min(365, (int) $rules['full_days'])),
            'partial_days' => max(0, min(365, (int) $rules['partial_days'])),
            'partial_percent' => max(0, min(100, (int) $rules['partial_percent'])),
        ];
    }

    /** Refund percentage (0-100) for a cancellation made at $now for a stay starting at $checkInMoment. */
    public static function refundPercent(string $policy, CarbonInterface $checkInMoment, CarbonInterface $now): int
    {
        $rules = self::rules($policy);
        $daysBefore = $now->diffInMinutes($checkInMoment, false) / 1440;
        if ($daysBefore < 0) {
            return 0;
        }
        if ($daysBefore >= $rules['full_days']) {
            return 100;
        }
        if ($rules['partial_days'] > 0 && $rules['partial_percent'] > 0 && $daysBefore >= $rules['partial_days']) {
            return $rules['partial_percent'];
        }

        return 0;
    }

    public static function summary(string $policy): string
    {
        $r = self::rules($policy);
        $days = fn (int $n) => $n === 1 ? '1 day' : $n.' days';
        $text = 'Full refund if cancelled '.$days($r['full_days']).' or more before check-in';
        if ($r['partial_days'] > 0 && $r['partial_percent'] > 0) {
            $text .= '; '.$r['partial_percent'].'% refund if cancelled '.$days($r['partial_days']).' or more before';
        }

        return $text.'; no refund after that.';
    }
}

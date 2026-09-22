<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AssignmentGroupingService
{
    /**
     * Group assignments/sessions by relative date and sort the groups.
     *
     * @param Collection|\Illuminate\Database\Eloquent\Collection|array $assignments
     * @return array
     */
    public static function groupAssignmentsByDate($assignments, bool $upcoming = false): array
    {
        $grouped = [];

        foreach ($assignments as $assignment) {
            $date = $assignment->scheduled_date;

            if ($date) {
                if (!$date instanceof Carbon) {
                    $date = Carbon::parse($date);
                }
                $dateKey = $date->toDateString();
            } else {
                $dateKey = 'No Date Assigned';
            }

            $grouped[$dateKey][] = $assignment;
        }

        // Sort the grouped array by date keys
        uksort($grouped, function ($a, $b) use ($upcoming) {
            if ($a === 'No Date Assigned') return 1;
            if ($b === 'No Date Assigned') return -1;

            if ($upcoming) {
                // Ascending chronological order (Tomorrow -> In 2 days)
                return $a <=> $b;
            } else {
                // Descending order (Today -> Yesterday -> 2 days ago)
                return $b <=> $a;
            }
        });

        // Map keys to their human-friendly labels
        $labeledGrouped = [];
        foreach ($grouped as $dateKey => $sessions) {
            if ($dateKey === 'No Date Assigned') {
                $label = 'No Date Assigned';
            } else {
                $label = self::getDateLabel(Carbon::parse($dateKey));
            }
            $labeledGrouped[$label] = $sessions;
        }

        return $labeledGrouped;
    }

    /**
     * Get relative or absolute date label.
     *
     * @param Carbon $date
     * @return string
     */
    public static function getDateLabel(Carbon $date): string
    {
        $today = Carbon::today();
        $targetDate = $date->copy()->startOfDay();
        
        $diff = (int) $today->diffInDays($targetDate, false);

        if ($diff === 0) {
            return 'Today';
        } elseif ($diff === 1) {
            return 'Tomorrow';
        } elseif ($diff === -1) {
            return 'Yesterday';
        } elseif ($diff > 1 && $diff <= 3) {
            return "In {$diff} days";
        } elseif ($diff < -1 && $diff >= -3) {
            $absDiff = abs($diff);
            return "{$absDiff} days ago";
        } else {
            // DayName, Month DD, YYYY
            return $targetDate->format('l, F j, Y');
        }
    }

    /**
     * Get sort priority value for a date label.
     * Order should be:
     * - Today (0)
     * - Tomorrow (1)
     * - In 2 days (2)
     * - In 3 days (3)
     * - Future dates chronologically (4 onwards)
     * - Yesterday (100001)
     * - 2 days ago (100002)
     * - 3 days ago (100003)
     * - Past dates older than 3 days (100004 onwards)
     * - No Date Assigned (999999)
     *
     * @param string $label
     * @return int
     */
    public static function getDateSortKey(string $label): int
    {
        $today = Carbon::today();

        if ($label === 'Today') {
            return 0;
        }
        if ($label === 'Tomorrow') {
            return 1;
        }
        if ($label === 'Yesterday') {
            return 100001;
        }
        if ($label === 'No Date Assigned') {
            return 999999;
        }

        // Matches "In X days"
        if (preg_match('/^In (\d+) days$/', $label, $matches)) {
            return (int) $matches[1];
        }

        // Matches "X days ago"
        if (preg_match('/^(\d+) days ago$/', $label, $matches)) {
            return 100000 + (int) $matches[1];
        }

        // Try parsing the date label
        try {
            $date = Carbon::parse($label)->startOfDay();
            $diff = $today->diffInDays($date, false);
            if ($diff > 0) {
                return $diff;
            } else {
                return 100000 + abs($diff);
            }
        } catch (\Exception $e) {
            return 999999;
        }
    }
}

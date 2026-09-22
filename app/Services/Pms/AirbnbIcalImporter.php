<?php

namespace App\Services\Pms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject\Reader;

/**
 * One-time/on-demand importer for a property's Airbnb iCal export URL.
 * Airbnb's iCal feed is read-only and availability-only -- no rates, no
 * ongoing push capability -- so this is deliberately a manual, button-
 * triggered fetch (see Admin\PropertyAvailabilityController::importIcal),
 * never a scheduled job. It exists purely to bootstrap/reconcile Guesthub's
 * own PropertyAvailability ledger with whatever Airbnb currently has
 * blocked, since Airbnb locks manual calendar edits once a channel manager
 * (Channex) mapping is active and there is otherwise no way to see Airbnb's
 * current state from outside Airbnb itself.
 */
class AirbnbIcalImporter
{
    /**
     * Fetches and parses the given iCal URL, returning a flat list of
     * blocked date ranges. Each VEVENT's DTEND is exclusive per the iCal
     * spec (matches Airbnb's own checkout-day convention), so the returned
     * range's 'to' is already the correct exclusive boundary -- callers
     * should treat date < to as blocked, not <=.
     *
     * @return array<int, array{from: string, to: string}> each date as Y-m-d
     * @throws \RuntimeException on fetch failure or unparseable content
     */
    public function fetchBlockedRanges(string $icalUrl): array
    {
        // Airbnb appears to rate-limit/throttle requests carrying Guzzle's
        // default User-Agent more aggressively than normal browser/curl
        // traffic (observed: identical URL succeeds via curl, 429s via the
        // default Laravel HTTP client) -- sending a standard browser UA
        // avoids that.
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'])
            ->get($icalUrl);

        if (! $response->successful()) {
            Log::warning('AirbnbIcalImporter fetch failed', [
                'url' => $icalUrl,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException("Could not fetch the iCal feed (HTTP {$response->status()}). Double-check the URL.");
        }

        try {
            $calendar = Reader::read($response->body());
        } catch (\Throwable $e) {
            Log::warning('AirbnbIcalImporter parse failed', [
                'url' => $icalUrl,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('The URL did not return a valid iCal calendar.');
        }

        $ranges = [];

        foreach ($calendar->VEVENT ?? [] as $event) {
            $start = $event->DTSTART ?? null;
            $end = $event->DTEND ?? null;

            if (! $start || ! $end) {
                continue;
            }

            $ranges[] = [
                'from' => $start->getDateTime()->format('Y-m-d'),
                'to' => $end->getDateTime()->format('Y-m-d'),
            ];
        }

        return $ranges;
    }

    /**
     * Expands the blocked ranges from fetchBlockedRanges() into a flat set
     * of individual blocked date strings (Y-m-d), for easy diffing against
     * PropertyAvailability rows. $to is treated as exclusive (checkout day
     * itself is not blocked), matching iCal/Airbnb convention.
     *
     * @param array<int, array{from: string, to: string}> $ranges
     * @return array<int, string>
     */
    public function expandToDateList(array $ranges): array
    {
        $dates = [];

        foreach ($ranges as $range) {
            $cursor = new \DateTimeImmutable($range['from']);
            $end = new \DateTimeImmutable($range['to']);

            while ($cursor < $end) {
                $dates[] = $cursor->format('Y-m-d');
                $cursor = $cursor->modify('+1 day');
            }
        }

        return array_values(array_unique($dates));
    }
}

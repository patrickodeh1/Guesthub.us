<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class IcalExportService
{
    private const CACHE_TTL_SECONDS = 60;

    public function export(Property $property, ?string $for = null): string
    {
        $events = Cache::remember(
            self::cacheKey((int) $property->id),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn () => $this->eventsFor($property),
        );

        if ($for !== null) {
            $events = array_values(array_filter(
                $events,
                fn (array $event) => $event['origin'] === null || $event['origin'] !== $for,
            ));
        }

        usort($events, fn (array $a, array $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return $this->serialize($events);
    }

    public function invalidate(int $propertyId): void
    {
        Cache::forget(self::cacheKey($propertyId));
    }

    public static function cacheKey(int $propertyId): string
    {
        return "ical-export:property:{$propertyId}";
    }

    /**
     * @return array<int, array{uid:string,start:string,end:string,origin:?string}>
     */
    private function eventsFor(Property $property): array
    {
        $today = now()->toDateString();
        $events = [];

        Booking::query()
            ->where('property_id', $property->id)
            ->holdingNights()
            ->whereNotNull('check_in_date')
            ->whereNotNull('check_out_date')
            ->whereDate('check_out_date', '>', $today)
            ->get(['id', 'booking_platform', 'source', 'check_in_date', 'check_out_date'])
            ->each(function (Booking $booking) use (&$events): void {
                $events[] = [
                    'uid' => substr(hash_hmac('sha256', 'booking:'.$booking->id, (string) config('app.key')), 0, 32).'@guesthub.us',
                    'start' => $booking->check_in_date->toDateString(),
                    'end' => $booking->check_out_date->toDateString(),
                    'origin' => $this->origin($booking),
                ];
            });

        $manualDates = PropertyAvailability::query()
            ->where('property_id', $property->id)
            ->whereDate('date', '>=', $today)
            ->where('is_available', false)
            ->where('source', 'manual')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'booked'))
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        foreach ($this->dateRanges($manualDates) as [$start, $end]) {
            $events[] = [
                'uid' => substr(hash_hmac('sha256', "manual:{$property->id}:{$start}:{$end}", (string) config('app.key')), 0, 32).'@guesthub.us',
                'start' => $start,
                'end' => $end,
                'origin' => null,
            ];
        }

        return $events;
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<int, array{0:string,1:string}>
     */
    private function dateRanges(array $dates): array
    {
        $ranges = [];
        $start = null;
        $last = null;

        foreach ($dates as $date) {
            if ($start === null) {
                $start = $date;
                $last = $date;

                continue;
            }

            if (Carbon::parse($last)->addDay()->toDateString() === $date) {
                $last = $date;

                continue;
            }

            $ranges[] = [$start, Carbon::parse($last)->addDay()->toDateString()];
            $start = $date;
            $last = $date;
        }

        if ($start !== null && $last !== null) {
            $ranges[] = [$start, Carbon::parse($last)->addDay()->toDateString()];
        }

        return $ranges;
    }

    private function origin(Booking $booking): string
    {
        $platform = strtolower(trim((string) ($booking->booking_platform ?: $booking->source)));
        if ($booking->isDirectBooking() || (! $booking->lease_offer_id && ! $booking->lease_application_id && ($booking->source === 'direct' || str_contains($platform, 'direct') || str_contains($platform, 'guesthub')))) {
            return 'direct';
        }
        if (str_contains($platform, 'airbnb')) {
            return 'airbnb';
        }
        if (str_contains($platform, 'vrbo') || str_contains($platform, 'homeaway')) {
            return 'vrbo';
        }

        return 'other';
    }

    /**
     * @param  array<int, array{uid:string,start:string,end:string,origin:?string}>  $events
     */
    private function serialize(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GuestHub//Calendar Export//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:GuestHub Availability',
        ];
        $stamp = now('UTC')->format('Ymd\THis\Z');

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$event['uid'];
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.str_replace('-', '', $event['start']);
            $lines[] = 'DTEND;VALUE=DATE:'.str_replace('-', '', $event['end']);
            $lines[] = 'SUMMARY:Reserved';
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }
}

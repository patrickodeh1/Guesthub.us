<?php

namespace App\Services\Pms;

use App\Models\ChannexOutbox;
use App\Models\Property;
use App\Models\PropertyAvailability;
use Illuminate\Support\Facades\DB;

/**
 * The single place that changes per-date availability/rates/restrictions.
 * Writes the date rows and marks them dirty in channex_outbox in one
 * transaction. Used by the admin editor, booking hooks and the iCal seed.
 * Explicit on purpose: upsert() and PMS imports skip Eloquent model events.
 */
class AvailabilityLedger
{
    public const RESTRICTION_FIELDS = [
        'rate', 'min_stay_arrival', 'min_stay_through', 'max_stay',
        'stop_sell', 'closed_to_arrival', 'closed_to_departure',
    ];

    /**
     * @param  array<int,string>  $dates   'Y-m-d' strings
     * @param  array<string,mixed>  $values  any of is_available, status, RESTRICTION_FIELDS (null clears a value)
     */
    public function setDates(Property $property, array $dates, array $values, string $source = 'manual'): void
    {
        $dates = array_values(array_unique($dates));
        if ($dates === []) {
            return;
        }

        $allowed = array_merge(['is_available', 'status'], self::RESTRICTION_FIELDS);
        $values = array_intersect_key($values, array_flip($allowed));
        if ($values === []) {
            return;
        }

        if (isset($values['status'])) {
            $values['is_available'] = $values['status'] === 'available';
        } elseif (array_key_exists('is_available', $values)) {
            $values['status'] = $values['is_available'] ? 'available' : 'blocked';
        }

        $now = now();

        // Rate/restriction writes from PriceLabs or the rate editor record who owns them.
        $rateOwner = (in_array($source, ['pricelabs', 'manual'], true)
            && array_intersect(array_keys($values), self::RESTRICTION_FIELDS))
            ? ['rates_source' => $source] : [];

        DB::transaction(function () use ($property, $dates, $values, $source, $now, $rateOwner) {
            $existing = $property->availabilities()
                ->whereIn('date', $dates)
                ->pluck('date')
                ->map(fn ($d) => substr((string) $d, 0, 10))
                ->all();

            if ($existing) {
                $property->availabilities()
                    ->whereIn('date', $existing)
                    ->update($values + $rateOwner + (array_key_exists('is_available', $values) ? ['source' => $source] : []) + ['updated_at' => $now]);
            }

            $missing = array_values(array_diff($dates, $existing));
            if ($missing) {
                $defaults = ['is_available' => true, 'status' => 'available'];
                $rows = array_map(fn ($d) => array_merge($defaults, $values, $rateOwner, [
                    'property_id' => $property->id,
                    'date' => $d,
                    'source' => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]), $missing);

                foreach (array_chunk($rows, 500) as $chunk) {
                    PropertyAvailability::insert($chunk);
                }
            }

            $this->mark($property, $dates, $values);
        });
    }

    /**
     * Queue dates for sending. Availability is only queued once the property
     * has been seeded (the seed button stays the gate for the first push);
     * restrictions only when Guesthub owns the rates for this property.
     */
    public function mark(Property $property, array $dates, array $values): void
    {
        $kinds = [];

        if (array_key_exists('is_available', $values)
            && $property->channex_room_type_id
            && $property->channex_availability_seeded_at) {
            $kinds[] = 'availability';
        }

        if (array_intersect(array_keys($values), self::RESTRICTION_FIELDS)
            && $property->rate_source === 'guesthub'
            && $property->channex_room_type_id
            && $property->channex_rate_plan_id) {
            $kinds[] = 'restrictions';
        }

        if (! $kinds) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($kinds as $kind) {
            foreach ($dates as $d) {
                $rows[] = [
                    'property_id' => $property->id,
                    'kind' => $kind,
                    'date' => $d,
                    'attempts' => 0,
                    'next_attempt_at' => null,
                    'last_error' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ChannexOutbox::upsert($chunk, ['property_id', 'kind', 'date'], ['attempts', 'next_attempt_at', 'last_error', 'updated_at']);
        }
    }

    /** Recompute nights for a booking (and its previous stay, if it moved). */
    public function syncBooking(\App\Models\Booking $booking, ?array $old = null): void
    {
        if ($old) {
            $this->syncStay($old);
        }
        $this->syncStay([
            'property_id' => $booking->property_id,
            'from' => $booking->check_in_date?->toDateString(),
            'to' => $booking->check_out_date?->toDateString(),
        ]);
    }

    /** @param array{property_id:?int,from:?string,to:?string} $stay */
    public function syncStay(array $stay): void
    {
        if (empty($stay['property_id']) || empty($stay['from']) || empty($stay['to'])) {
            return;
        }
        $this->syncNights((int) $stay['property_id'], $stay['from'], $stay['to']);
    }

    /**
     * Recompute nights in [from, to) (to = check-out day, stays open).
     * Closed if any non-cancelled booking covers the night. Reopened only if
     * it was closed by this mechanism (status booked, source booking) and
     * nothing covers it any more. Manual blocks and iCal rows are untouched.
     */
    public function syncNights(int $propertyId, string $from, string $to): void
    {
        $property = Property::find($propertyId);
        if (! $property) {
            return;
        }

        $start = \Carbon\Carbon::parse($from)->startOfDay();
        $end = \Carbon\Carbon::parse($to)->startOfDay();
        if ($end->lte($start) || $start->diffInDays($end) > 800) {
            return;
        }

        $covered = [];
        \App\Models\Booking::query()
            ->where('property_id', $propertyId)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
            ->whereDate('check_in_date', '<', $end->toDateString())
            ->whereDate('check_out_date', '>', $start->toDateString())
            ->get(['id', 'check_in_date', 'check_out_date'])
            ->each(function ($b) use (&$covered, $start, $end) {
                $n = $b->check_in_date->copy()->startOfDay()->max($start)->copy();
                $last = $b->check_out_date->copy()->startOfDay()->min($end)->copy();
                for (; $n->lt($last); $n->addDay()) {
                    $covered[$n->format('Y-m-d')] = true;
                }
            });

        $rows = $property->availabilities()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $toBook = [];
        $toRelease = [];
        for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $row = $rows->get($key);

            if (isset($covered[$key])) {
                if (! $row || $row->is_available) {
                    $toBook[] = $key;
                }
            } elseif ($row && $row->status === 'booked' && $row->source === 'booking') {
                $toRelease[] = $key;
            }
        }

        $this->setDates($property, $toBook, ['status' => 'booked'], 'booking');
        $this->setDates($property, $toRelease, ['status' => 'available'], 'booking');
    }
}

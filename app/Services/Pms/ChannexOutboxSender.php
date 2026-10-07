<?php

namespace App\Services\Pms;

use App\Models\ChannexOutbox;
use App\Models\Property;
use Carbon\Carbon;

/**
 * Sends dirty dates from channex_outbox to Channex. One call per kind
 * (availability / restrictions) per property per run, with consecutive
 * identical dates merged into ranges. Capped per run to stay well under
 * Channex's ARI rate limit; leftovers go out on the next run.
 */
class ChannexOutboxSender
{
    private const MAX_CALLS_PER_RUN = 15;
    private const MAX_RANGES_PER_CALL = 1000;
    private const MAX_ATTEMPTS = 10;

    public function __construct(private PmsProviderInterface $provider)
    {
    }

    /** @return array{calls:int,sent:int,failed:int} */
    public function flush(?int $propertyId = null): array
    {
        $stats = ['calls' => 0, 'sent' => 0, 'failed' => 0];

        if (! method_exists($this->provider, 'pushAvailabilityRanges')) {
            return $stats;
        }

        $cutoff = now();

        $propertyIds = $this->due(ChannexOutbox::query(), $cutoff)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->distinct()
            ->pluck('property_id');

        foreach ($propertyIds as $pid) {
            $property = Property::find($pid);
            if (! $property) {
                continue;
            }
            foreach (['availability', 'restrictions'] as $kind) {
                if ($stats['calls'] >= self::MAX_CALLS_PER_RUN) {
                    return $stats;
                }
                $this->flushKind($property, $kind, $cutoff, $stats);
            }
        }

        return $stats;
    }

    private function due($query, Carbon $cutoff)
    {
        return $query
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $cutoff))
            ->where('updated_at', '<=', $cutoff);
    }

    private function flushKind(Property $property, string $kind, Carbon $cutoff, array &$stats): void
    {
        $rows = $this->due(ChannexOutbox::query(), $cutoff)
            ->where('property_id', $property->id)
            ->where('kind', $kind)
            ->orderBy('date')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        if ($kind === 'restrictions' && $property->rate_source !== 'guesthub') {
            // Rates are owned elsewhere (e.g. PriceLabs): never send them.
            ChannexOutbox::whereIn('id', $rows->pluck('id'))->delete();
            return;
        }

        if (! $property->channex_property_id || ! $property->channex_room_type_id) {
            // Not connected to Channex at all: nothing to send, and Full sync
            // reads the ledger, so there is nothing to keep.
            ChannexOutbox::whereIn('id', $rows->pluck('id'))->delete();
            return;
        }

        $ready = $property->channex_property_id
            && $property->channex_room_type_id
            && ($kind === 'availability' ? (bool) $property->channex_availability_seeded_at : (bool) $property->channex_rate_plan_id);

        if (! $ready) {
            ChannexOutbox::whereIn('id', $rows->pluck('id'))->update([
                'next_attempt_at' => now()->addHour(),
                'last_error' => 'Property is not fully mapped or seeded yet.',
            ]);
            return;
        }

        $dates = $rows->map(fn ($r) => $r->date->format('Y-m-d'))->all();
        $ledger = $property->availabilities()->whereIn('date', $dates)->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        // Dates whose ledger row no longer exists: nothing to send.
        $orphanIds = $rows->filter(fn ($r) => ! $ledger->has($r->date->format('Y-m-d')))->pluck('id');
        if ($orphanIds->isNotEmpty()) {
            ChannexOutbox::whereIn('id', $orphanIds)->delete();
        }

        $payloads = [];
        foreach ($ledger as $date => $row) {
            $payload = $kind === 'availability'
                ? ['availability' => $row->is_available ? 1 : 0]
                : $this->restrictionPayload($row);
            if ($payload !== []) {
                $payloads[$date] = $payload;
            }
        }

        if ($payloads === []) {
            ChannexOutbox::whereIn('id', $rows->pluck('id'))->delete();
            return;
        }

        ksort($payloads);
        $ranges = array_slice($this->mergeRanges($payloads), 0, self::MAX_RANGES_PER_CALL);
        $covered = array_merge(...array_column($ranges, 'dates'));

        $ok = $kind === 'availability'
            ? $this->provider->pushAvailabilityRanges(
                $property->channex_property_id,
                $property->channex_room_type_id,
                array_map(fn ($r) => [$r['from'], $r['to'], $r['data']['availability']], $ranges)
            )
            : $this->provider->pushRestrictions(
                $property->channex_property_id,
                $property->channex_rate_plan_id,
                array_map(fn ($r) => [$r['from'], $r['to'], $r['data']], $ranges)
            );

        $stats['calls']++;

        $coveredIds = $rows->filter(fn ($r) => in_array($r->date->format('Y-m-d'), $covered, true))->pluck('id');

        if ($ok) {
            $stats['sent'] += count($covered);
            ChannexOutbox::whereIn('id', $coveredIds)->where('updated_at', '<=', $cutoff)->delete();
        } else {
            $stats['failed'] += count($covered);
            ChannexOutbox::whereIn('id', $coveredIds)->update([
                'attempts' => \DB::raw('attempts + 1'),
                'next_attempt_at' => now()->addMinutes(min(60, 2 ** min(6, (int) $rows->whereIn('id', $coveredIds)->max('attempts') + 1))),
                'last_error' => 'Channex rejected the update; see laravel.log.',
            ]);
        }
    }

    private function restrictionPayload($row): array
    {
        $p = [];
        if ($row->rate !== null) {
            $p['rate'] = number_format((float) $row->rate, 2, '.', '');
        }
        foreach (['min_stay_arrival', 'min_stay_through', 'max_stay'] as $f) {
            if ($row->{$f} !== null) {
                $p[$f] = (int) $row->{$f};
            }
        }
        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $f) {
            if ($row->{$f} !== null) {
                $p[$f] = (bool) $row->{$f};
            }
        }
        return $p;
    }

    /** @return array<int,array{from:string,to:string,data:array,dates:array<int,string>}> */
    private function mergeRanges(array $payloads): array
    {
        $ranges = [];
        $cur = null;

        foreach ($payloads as $date => $data) {
            $extends = $cur
                && $cur['data'] === $data
                && Carbon::parse($cur['to'])->addDay()->format('Y-m-d') === $date;

            if ($extends) {
                $cur['to'] = $date;
                $cur['dates'][] = $date;
            } else {
                if ($cur) {
                    $ranges[] = $cur;
                }
                $cur = ['from' => $date, 'to' => $date, 'data' => $data, 'dates' => [$date]];
            }
        }

        if ($cur) {
            $ranges[] = $cur;
        }

        return $ranges;
    }

    /**
     * Full sync: sends the next $days days of availability and of rates and
     * restrictions straight to Channex, one call per kind, then clears the
     * matching outbox rows. Rates are only sent for rate_source = guesthub.
     * Nothing here reads booking fees or any guest charge.
     *
     * @return array{calls:int,availability_dates:int,restriction_dates:int,failed:bool,messages:array<int,string>}
     */
    public function fullSync(Property $property, int $days = 500): array
    {
        $r = ['calls' => 0, 'availability_dates' => 0, 'restriction_dates' => 0, 'failed' => false, 'messages' => []];

        if (! method_exists($this->provider, 'pushAvailabilityRanges')) {
            $r['failed'] = true;
            $r['messages'][] = 'The current PMS provider cannot send date ranges.';
            return $r;
        }

        if (! $property->channex_property_id || ! $property->channex_room_type_id) {
            $r['failed'] = true;
            $r['messages'][] = 'Set the Channex property ID and map the room type first.';
            return $r;
        }

        $start = now()->startOfDay();
        $end = $start->copy()->addDays($days - 1);
        $startedAt = now();

        $rows = $property->availabilities()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->get();

        // ---- availability ----
        if (! $property->channex_availability_seeded_at) {
            $r['messages'][] = 'Availability skipped: seed the property with Push to Channex first.';
        } else {
            $payloads = [];
            foreach ($rows as $row) {
                $payloads[$row->date->format('Y-m-d')] = ['availability' => $row->is_available ? 1 : 0];
            }

            if ($payloads === []) {
                $r['messages'][] = 'Availability skipped: no dates in the next ' . $days . ' days.';
            } else {
                $ranges = $this->mergeRanges($payloads);
                $ok = $this->provider->pushAvailabilityRanges(
                    $property->channex_property_id,
                    $property->channex_room_type_id,
                    array_map(fn ($x) => [$x['from'], $x['to'], $x['data']['availability']], $ranges)
                );
                $r['calls']++;

                if ($ok) {
                    $r['availability_dates'] = count($payloads);
                    ChannexOutbox::where('property_id', $property->id)->where('kind', 'availability')
                        ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                        ->where('updated_at', '<=', $startedAt)->delete();
                } else {
                    $r['failed'] = true;
                    $r['messages'][] = 'Channex rejected the availability update; see laravel.log.';
                }
            }
        }

        // ---- rates and restrictions ----
        if (! $property->channex_rate_plan_id) {
            $r['messages'][] = 'Rates skipped: no rate plan is chosen.';
        } else {
            $payloads = [];
            foreach ($rows as $row) {
                $p = $this->restrictionPayload($row);
                if ($p !== []) {
                    $payloads[$row->date->format('Y-m-d')] = $p;
                }
            }

            if ($payloads === []) {
                $r['messages'][] = 'Rates skipped: no rates or restrictions are set for the next ' . $days . ' days.';
            } else {
                $ranges = $this->mergeRanges($payloads);
                $ok = $this->provider->pushRestrictions(
                    $property->channex_property_id,
                    $property->channex_rate_plan_id,
                    array_map(fn ($x) => [$x['from'], $x['to'], $x['data']], $ranges)
                );
                $r['calls']++;

                if ($ok) {
                    $r['restriction_dates'] = count($payloads);
                    ChannexOutbox::where('property_id', $property->id)->where('kind', 'restrictions')
                        ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                        ->where('updated_at', '<=', $startedAt)->delete();
                } else {
                    $r['failed'] = true;
                    $r['messages'][] = 'Channex rejected the rates update; see laravel.log.';
                }
            }
        }

        return $r;
    }
}

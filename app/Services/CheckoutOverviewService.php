<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CleaningSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the unified Dashboard checkout board: one row per property per checkout
 * day, with the next arrival, the cleaning job and whether it is covered.
 *
 * Source of truth is the Booking (Channex). A cleaning session is matched to a
 * checkout by property + scheduled_date. No network calls happen here.
 * Assumes one Property = one rentable unit, so two checkouts on the same
 * property and day are flagged as overlapping bookings.
 */
class CheckoutOverviewService
{
    /** "Later This Week" = day after tomorrow through today + 6 (a rolling week). */
    private const WINDOW_DAYS = 6;

    /** Bookings in these statuses never need a cleaning. Harmless if a status is unused. */
    private const EXCLUDED_STATUSES = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];

    public function get(User $user): array
    {
        $tz = config('app.display_timezone');
        $today = Carbon::now($tz)->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $laterStart = $today->copy()->addDays(2);
        $windowEnd = $today->copy()->addDays(self::WINDOW_DAYS);

        $dates = [
            'today' => $today,
            'tomorrow' => $tomorrow,
            'later_start' => $laterStart,
            'later_end' => $windowEnd,
        ];

        $bookings = $this->active(Booking::with('property'))
            ->whereHas('property', fn ($q) => $q->visibleTo($user))
            ->whereDate('check_out_date', '>=', $today->toDateString())
            ->whereDate('check_out_date', '<=', $windowEnd->toDateString())
            ->orderBy('check_out_date')
            ->get();

        if ($bookings->isEmpty()) {
            return [
                'dates' => $dates,
                'groups' => ['today' => collect(), 'tomorrow' => collect(), 'later' => collect()],
                'summary' => ['total' => 0, 'uncovered' => 0, 'same_day' => 0, 'today_uncovered' => 0, 'overlap' => 0],
            ];
        }

        $propertyIds = $bookings->pluck('property_id')->unique()->values();

        $cleanerOptions = DB::table('property_user')
            ->join('users', 'users.id', '=', 'property_user.user_id')
            ->whereIn('property_user.property_id', $propertyIds)
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get(['property_user.property_id', 'users.id', 'users.name'])
            ->groupBy('property_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])->values());

        $sessions = CleaningSession::with('housekeeper:id,name')
            ->whereIn('property_id', $propertyIds)
            ->whereDate('scheduled_date', '>=', $today->toDateString())
            ->whereDate('scheduled_date', '<=', $windowEnd->toDateString())
            ->get()
            ->groupBy(fn (CleaningSession $s) => $s->property_id.'|'.$s->scheduled_date->toDateString());

        $arrivals = $this->active(Booking::query())
            ->whereIn('property_id', $propertyIds)
            ->whereDate('check_in_date', '>=', $today->toDateString())
            ->orderBy('check_in_date')
            ->get()
            ->groupBy('property_id');

        // One row per property per checkout day (that is also how a cleaning job is matched).
        $rows = $bookings
            ->groupBy(fn (Booking $b) => $b->property_id.'|'.$b->check_out_date->toDateString())
            ->map(function ($group, $key) use ($sessions, $arrivals, $today, $tomorrow, $cleanerOptions) {
                $primary = $group->first();
                $outDate = $primary->check_out_date->toDateString();
                $ids = $group->pluck('id');

                $next = ($arrivals->get($primary->property_id) ?? collect())
                    ->first(fn (Booking $a) => ! $ids->contains($a->id) && $a->check_in_date->toDateString() >= $outDate);

                // If several sessions exist for that day, prefer one that has a cleaner.
                $session = $sessions->get($key)
                    ?->sortByDesc(fn (CleaningSession $s) => $s->housekeeper_id ? 1 : 0)
                    ->first();

                $sameDay = $next && $next->check_in_date->toDateString() === $outDate;

                return [
                    'booking' => $primary,
                    'guest_names' => $group->pluck('guest_name')->all(),
                    'overlap' => $group->count() > 1,
                    'property' => $primary->property,
                    'date' => $primary->check_out_date,
                    'bucket' => $outDate === $today->toDateString() ? 'today'
                        : ($outDate === $tomorrow->toDateString() ? 'tomorrow' : 'later'),
                    'checkout_time' => $this->timeLabel(\App\Support\BookingTimes::approved($primary->checkout_time_status, $primary->checkout_time_preference))
                        ?? $this->timeLabel($primary->property?->checkout_time),
                    'next' => $next,
                    'next_time' => $next
                        ? ($this->timeLabel(\App\Support\BookingTimes::approved($next->checkin_time_status, $next->checkin_time_preference))
                            ?? $this->timeLabel($primary->property?->checkin_time))
                        : null,
                    'same_day' => $sameDay,
                    'session' => $session,
                    'cleaner' => $session?->housekeeper?->name,
                    'covered' => (bool) ($session && $session->housekeeper_id),
                    'cleaner_options' => $cleanerOptions->get($primary->property_id, collect()),
                ];
            })
            ->values();

        $sorted = fn ($collection) => $collection
            ->sortBy(fn ($r) => $r['date']->toDateString().'|'.($r['covered'] ? '1' : '0').'|'.($r['same_day'] ? '0' : '1').'|'.$r['property']?->name)
            ->values();

        return [
            'dates' => $dates,
            'groups' => [
                'today' => $sorted($rows->where('bucket', 'today')),
                'tomorrow' => $sorted($rows->where('bucket', 'tomorrow')),
                'later' => $sorted($rows->where('bucket', 'later')),
            ],
            'summary' => [
                'total' => $rows->count(),
                'uncovered' => $rows->where('covered', false)->count(),
                'same_day' => $rows->where('same_day', true)->count(),
                'today_uncovered' => $rows->where('bucket', 'today')->where('covered', false)->count(),
                'overlap' => $rows->where('overlap', true)->count(),
            ],
        ];
    }

    /** Bookings that can still need a cleaning: not archived, not cancelled/declined. */
    private function active(Builder $query): Builder
    {
        return $query
            ->notArchived()
            ->whereNull('cancelled_at')
            ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', self::EXCLUDED_STATUSES));
    }

    /** Turn a stored time preference into "g:i A", or pass through free text. */
    private function timeLabel(?string $value): ?string
    {
        $value = trim((string) $value);
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

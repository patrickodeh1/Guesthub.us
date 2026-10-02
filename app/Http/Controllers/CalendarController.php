<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use App\Models\Booking;
use App\Models\CleaningSession;


use App\Models\User;
use App\Models\Property;
use App\Models\PropertyCheckout;
use App\Models\Room;
use App\Services\ICalService;

class CalendarController extends Controller
{
    private $icalService;

    public function __construct(ICalService $icalService) {
        $this->icalService = $icalService;
    }

    /**
     * Determine acting role per request.
     * If user has admin, admin wins unless ?as=owner given and user also has owner.
     */
    private function actingRole(Request $request): string
    {
        $u = $request->user();
        $isAdmin = $u?->hasRole('admin') ?? false;
        $isOwner = $u?->hasRole('owner') ?? false;
        $isCompany = $u?->hasRole('company') ?? false;
        $isHK    = $u?->hasRole('housekeeper') ?? false;

        if ($isAdmin) {
            if (($isOwner || $isCompany) && $request->query('as') === 'owner') return 'owner';
            return 'admin';
        }
        if ($isOwner || $isCompany) return 'owner';
        if ($isHK)    return 'housekeeper';

        // Final fallback: if no role is found, treat as owner to avoid 403 during demo/setup
        return 'owner';
    }

    public function index(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // Determine user's timezone from their properties or default to US Eastern
        $userTz = 'America/Chicago'; // Default for FlipStatus (Chicago/Cleveland)
        if ($u) {
            $propTz = Property::query()->active()
                ->when($acting === 'housekeeper', function($q) use ($u) {
                    $q->whereIn('id', function($sub) use ($u) {
                        $sub->select('property_id')->from('cleaning_sessions')->where('housekeeper_id', $u->id);
                    });
                })
                ->when($acting === 'owner', fn($q) => $q->where('owner_id', $u->id))
                ->whereNotNull('timezone')
                ->where('timezone', '!=', '')
                ->value('timezone');

            if ($propTz) {
                $userTz = $propTz;
            }
        }

        $today = now($userTz)->toDateString();

        // Month selection (YYYY-MM), defaults to current month
        $monthParam = (string) $request->query('month', now($userTz)->format('Y-m'));
        $monthStart = Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth();
        $monthEnd   = (clone $monthStart)->endOfMonth();

        // Grid boundaries (full weeks) - Start on Sunday
        $gridStart = (clone $monthStart)->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = (clone $monthEnd)->endOfWeek(Carbon::SUNDAY);

        // Day selected (for sidebar/list)
        $selectedDay = $request->query('day');
        $selectedDay = $selectedDay ? Carbon::parse($selectedDay)->toDateString() : null;

        // Filters (all optional, read from the query string)
        $fProperty    = (int) $request->query('property') ?: null;
        $fGuest       = trim((string) $request->query('guest', ''));
        $fCleaner     = (int) $request->query('cleaner') ?: null;
        $fCleaning    = trim((string) $request->query('cleaning_status', ''));
        $fReservation = trim((string) $request->query('reservation_status', ''));
        $fCoverage    = in_array($request->query('coverage'), ['covered', 'needs'], true) ? $request->query('coverage') : '';
        $hasStatusCol = \Illuminate\Support\Facades\Schema::hasColumn('cleaning_sessions', 'status');

        // Base query scoped by acting role
        $q = CleaningSession::query()
            ->with(['property:id,name,owner_id', 'housekeeper:id,name'])
            ->whereBetween('scheduled_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

        // Helpers to get visible property IDs
        $visiblePropIds = null;

        if ($acting === 'housekeeper') {
            $q->where('housekeeper_id', $u->id);
        } elseif ($acting === 'owner') {
             if ($u->hasRole('company')) {
                  // Company Logic
                  $companyPropIds = Property::active()->where(function($qq) use ($u) {
                      $qq->where('owner_id', $u->id)
                        ->orWhereIn('owner_id', function($sub) use ($u) {
                            $sub->select('id')->from('users')->where('owner_id', $u->id);
                        })
                        ->orWhereIn('owner_id', function($sub) use ($u) {
                            $sub->select('owner_id')->from('housekeeper_owner')->where('housekeeper_id', $u->id);
                        })
                        ->orWhereHas('users', function($sub2) use ($u) {
                            $sub2->where('users.id', $u->id);
                        });
                  })->pluck('id');

                  $q->whereIn('property_id', $companyPropIds);
                  $visiblePropIds = $companyPropIds;
             } else {
                  $q->whereHas('property', fn($p) => $p->where('owner_id', $u->id));
                  $visiblePropIds = Property::active()->where('owner_id', $u->id)->pluck('id');
             }
        } // admin -> no scope

        $cleanerFilterOptions = (clone $q)->whereNotNull('housekeeper_id')->get()
            ->pluck('housekeeper.name', 'housekeeper_id')->filter()->sort()->all();
        $cleaningStatusOptions = $hasStatusCol
            ? (clone $q)->get()->pluck('status')->filter()->unique()->sort()->values()->all()
            : [];

        if ($fProperty) { $q->where('property_id', $fProperty); }
        if ($fCleaner && $acting !== 'housekeeper') { $q->where('housekeeper_id', $fCleaner); }
        if ($fCleaning !== '' && $hasStatusCol) { $q->where('status', $fCleaning); }
        if ($fCoverage === 'covered') { $q->whereNotNull('housekeeper_id'); }
        if ($fCoverage === 'needs') { $q->whereNull('housekeeper_id'); }

        $sessions = $q->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        // Group sessions by date for calendar dots/counts
        $byDate = $sessions->groupBy(fn($s) => Carbon::parse($s->scheduled_date)->toDateString());

        // --- Checkouts come from Guest Hub bookings (no network calls) ---
        $unscheduledByDate = collect();

        if ($acting !== 'housekeeper') {
            $bookingQuery = Booking::query()
                ->notArchived()
                ->whereNull('cancelled_at')
                ->with('property:id,name')
                ->whereBetween('check_out_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

            if (!is_null($visiblePropIds)) {
                $bookingQuery->whereIn('property_id', $visiblePropIds);
            }

            if ($fProperty) { $bookingQuery->where('property_id', $fProperty); }
            if ($fGuest !== '') { $bookingQuery->where('guest_name', 'like', '%' . $fGuest . '%'); }
            if ($fReservation !== '') { $bookingQuery->where('status', $fReservation); }

            $checkouts = $bookingQuery->get();

            $covered = CleaningSession::query()
                ->whereIn('property_id', $checkouts->pluck('property_id')->unique())
                ->whereBetween('scheduled_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                ->get(['property_id', 'scheduled_date'])
                ->map(fn ($s) => $s->property_id . '|' . Carbon::parse($s->scheduled_date)->toDateString())
                ->flip();

            foreach ($checkouts as $b) {
                $d = Carbon::parse($b->check_out_date)->toDateString();
                if ($covered->has($b->property_id . '|' . $d)) continue;
                $unscheduledByDate->push([
                    'id' => null,
                    'booking_id' => $b->getKey(),
                    'date' => $d,
                    'property_id' => $b->property_id,
                    'property_name' => $b->property?->name ?? 'Unknown Property',
                    'guest_name' => $b->guest_name ?: 'Guest',
                    'source' => $b->source,
                    'type' => 'unscheduled',
                ]);
            }
        }

        $unscheduledByDate = $unscheduledByDate->groupBy('date');
        if ($fCleaner || $fCleaning !== '' || $fCoverage === 'covered') {
            $unscheduledByDate = collect();
        }


        // --- Guest Hub bookings on the grid (local records only; no network calls) ---
        $bookingMarks = [];
        $dayBookings = collect();
        $conflictIds = [];

        if ($acting !== 'housekeeper') {
            $bq = Booking::query()
                ->notArchived()
                ->whereNull('cancelled_at')
                ->with('property:id,name,checkin_time,checkout_time')
                ->whereDate('check_in_date', '<=', $gridEnd->toDateString())
                ->when($fProperty, fn ($bq) => $bq->where('property_id', $fProperty))
                ->when($fGuest !== '', fn ($bq) => $bq->where('guest_name', 'like', '%' . $fGuest . '%'))
                ->when($fReservation !== '', fn ($bq) => $bq->where('status', $fReservation))
                ->whereDate('check_out_date', '>=', $gridStart->toDateString());
            if (!is_null($visiblePropIds)) {
                $bq->whereIn('property_id', $visiblePropIds);
            }
            $gridBookings = $bq->get();

            // Overlapping stays on the same property (one property = one unit)
            foreach ($gridBookings->groupBy('property_id') as $group) {
                foreach ($group as $a) {
                    foreach ($group as $b) {
                        if ($a->getKey() < $b->getKey()
                            && $a->check_in_date->lt($b->check_out_date)
                            && $a->check_out_date->gt($b->check_in_date)) {
                            $conflictIds[$a->getKey()] = true;
                            $conflictIds[$b->getKey()] = true;
                        }
                    }
                }
            }

            // Back-to-back: one stay checks out the same day another checks in on the same property
            $turnoverIds = [];
            $turnoverDates = [];
            foreach ($gridBookings->groupBy('property_id') as $group) {
                foreach ($group as $a) {
                    foreach ($group as $b) {
                        if ($a->getKey() !== $b->getKey()
                            && $a->check_out_date->toDateString() === $b->check_in_date->toDateString()) {
                            $turnoverIds[$a->getKey()] = true;
                            $turnoverIds[$b->getKey()] = true;
                            $turnoverDates[$a->property_id . '|' . $a->check_out_date->toDateString()] = true;
                        }
                    }
                }
            }

            foreach ($gridBookings as $gb) {
                $in = $gb->check_in_date->copy()->startOfDay();
                $out = $gb->check_out_date->copy()->startOfDay();
                $isConflict = isset($conflictIds[$gb->getKey()]);
                foreach (CarbonPeriod::create($in, '1 day', $out) as $date) {
                    $d = $date->toDateString();
                    if ($d < $gridStart->toDateString() || $d > $gridEnd->toDateString()) {
                        continue;
                    }
                    $bookingMarks[$d] ??= ['in' => 0, 'out' => 0, 'stay' => 0, 'conflict' => false, 'b2b' => false];
                    $key = $d === $in->toDateString() ? 'in' : ($d === $out->toDateString() ? 'out' : 'stay');
                    $bookingMarks[$d][$key]++;
                    if ($key !== 'stay' && isset($turnoverIds[$gb->getKey()]) && isset($turnoverDates[$gb->property_id . '|' . $d])) {
                        $bookingMarks[$d]['b2b'] = true;
                    }
                    if ($isConflict) {
                        $bookingMarks[$d]['conflict'] = true;
                    }
                }
            }

            if ($selectedDay) {
                $dayBookings = $gridBookings
                    ->filter(fn ($gb) => $gb->check_in_date->toDateString() <= $selectedDay
                        && $gb->check_out_date->toDateString() >= $selectedDay)
                    ->map(fn ($gb) => [
                        'booking' => $gb,
                        'role' => $gb->check_in_date->toDateString() === $selectedDay
                            ? 'arriving'
                            : ($gb->check_out_date->toDateString() === $selectedDay ? 'departing' : 'staying'),
                        'conflict' => isset($conflictIds[$gb->getKey()]),
                        'turnover' => isset($turnoverIds[$gb->getKey()]),
                        'in_time' => \App\Support\BookingTimes::label($gb->checkin_time_status, $gb->checkin_time_preference, $gb->property?->checkin_time),
                        'out_time' => \App\Support\BookingTimes::label($gb->checkout_time_status, $gb->checkout_time_preference, $gb->property?->checkout_time),
                    ])->values();
            }
        }

        // --- Availability from the imported iCal dates (read-only; local rows, no network calls) ---
        $blockedByDate = [];
        $dayBlocked = [];
        $availableNights = [];
        $availabilityAsOf = null;
        $availabilityMonth = $monthStart->format('F');

        if ($acting !== 'housekeeper') {
            $occupied = [];
            foreach ($gridBookings as $gb) {
                $lastNight = $gb->check_out_date->copy()->subDay()->startOfDay();
                foreach (CarbonPeriod::create($gb->check_in_date->copy()->startOfDay(), '1 day', $lastNight) as $night) {
                    $occupied[$gb->property_id][$night->toDateString()] = true;
                }
            }

            $avRows = \App\Models\PropertyAvailability::query()
                ->whereDate('date', '>=', $gridStart->toDateString())
                ->whereDate('date', '<=', $gridEnd->toDateString())
                ->when(!is_null($visiblePropIds), fn ($q) => $q->whereIn('property_id', $visiblePropIds))
                ->get(['property_id', 'date', 'is_available', 'updated_at']);

            $blockedProps = [];
            $openNights = [];
            foreach ($avRows as $row) {
                $d = \Carbon\Carbon::parse($row->date)->toDateString();
                $pid = $row->property_id;
                if ($row->is_available) {
                    if ($d >= $monthStart->toDateString() && $d <= $monthEnd->toDateString() && empty($occupied[$pid][$d])) {
                        $openNights[$pid] = ($openNights[$pid] ?? 0) + 1;
                    }
                } elseif (empty($occupied[$pid][$d])) {
                    $blockedProps[$d][] = $pid;
                    $blockedByDate[$d] = ($blockedByDate[$d] ?? 0) + 1;
                }
            }

            $propNames = \App\Models\Property::query()
                ->whereIn('id', $avRows->pluck('property_id')->unique()->values()->all())
                ->pluck('name', 'id');

            if ($selectedDay && !empty($blockedProps[$selectedDay])) {
                $dayBlocked = collect($blockedProps[$selectedDay])->map(fn ($id) => $propNames[$id] ?? ('Property ' . $id))->sort()->values()->all();
            }

            $availableNights = collect($openNights)
                ->map(fn ($n, $id) => ['name' => $propNames[$id] ?? ('Property ' . $id), 'nights' => $n])
                ->sortBy('name')->values()->all();

            $availabilityAsOf = $avRows->max('updated_at');
        }

        // Optional: a list for the selected day, sorted by time (earliest to latest)
        $daySessions = $selectedDay ? ($byDate[$selectedDay] ?? collect()) : collect();
        $dayUnscheduled = $selectedDay ? ($unscheduledByDate[$selectedDay] ?? collect()) : collect();

        // Sort day sessions by scheduled_time (earliest to latest)
        if ($daySessions->isNotEmpty()) {
            $daySessions = $daySessions->sortBy(function($session) {
                return $session->scheduled_time ? $session->scheduled_time->format('H:i:s') : '23:59:59';
            })->values();
        }

        // --- Week / Day views (agenda built from the same data and filters as the month grid) ---
        $viewMode = in_array($request->query('view'), ['week', 'day'], true) ? $request->query('view') : 'month';
        $anchor = $selectedDay
            ? Carbon::parse($selectedDay)->startOfDay()
            : (($today >= $monthStart->toDateString() && $today <= $monthEnd->toDateString()) ? Carbon::parse($today)->startOfDay() : $monthStart->copy()->startOfDay());
        if ($anchor->lt($gridStart) || $anchor->gt($gridEnd)) {
            $anchor = $monthStart->copy()->startOfDay();
        }
        $rangeStart = $viewMode === 'week' ? $anchor->copy()->startOfWeek(Carbon::SUNDAY) : $anchor->copy();
        $rangeEnd = $viewMode === 'week' ? $anchor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay() : $anchor->copy();
        $rangeLabel = $viewMode === 'week'
            ? $rangeStart->format('M j') . ' to ' . $rangeEnd->format('M j, Y')
            : $anchor->format('l, M j, Y');

        $cleanQuery = collect($request->query())
            ->reject(fn ($val, $key) => str_contains((string) $key, 'amp;') || $key === 'page')
            ->all();
        $navParams = fn (Carbon $dt) => array_merge($cleanQuery, ['month' => $dt->format('Y-m'), 'day' => $dt->toDateString()]);
        $step = $viewMode === 'week' ? 7 : 1;
        $prevUrl = $viewMode === 'month'
            ? route('calendar.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')] + $cleanQuery)
            : route('calendar.index', $navParams($anchor->copy()->subDays($step)));
        $nextUrl = $viewMode === 'month'
            ? route('calendar.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')] + $cleanQuery)
            : route('calendar.index', $navParams($anchor->copy()->addDays($step)));
        $todayUrl = route('calendar.index', array_filter(['view' => $viewMode === 'month' ? null : $viewMode, 'as' => $request->query('as')]));

        $viewLinks = [];
        foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $k => $lbl) {
            $dayParam = ($k === 'month' && !$selectedDay) ? [] : ['day' => $anchor->toDateString()];
            $viewLinks[$k] = [
                'label' => $lbl,
                'url' => route('calendar.index', array_merge(
                    collect($cleanQuery)->except(['view', 'day'])->all(),
                    ['month' => $anchor->format('Y-m')],
                    $dayParam,
                    $k === 'month' ? [] : ['view' => $k]
                )),
            ];
        }

        $agenda = [];
        if ($viewMode !== 'month') {
            $bookingsPool = ($acting !== 'housekeeper' && isset($gridBookings)) ? $gridBookings : collect();
            $kindOrder = ['out' => 0, 'in' => 1, 'stay' => 2];
            foreach (CarbonPeriod::create($rangeStart, '1 day', $rangeEnd) as $date) {
                $d = $date->toDateString();

                $sess = ($byDate[$d] ?? collect())
                    ->sortBy(fn ($s) => $s->scheduled_time ? $s->scheduled_time->format('H:i:s') : '23:59:59')
                    ->values()
                    ->map(fn ($s) => [
                        'property' => $s->property?->name ?? 'Property',
                        'cleaner' => $s->housekeeper?->name,
                        'time' => $s->scheduled_time ? $s->scheduled_time->format('g:i A') : null,
                        'status' => $hasStatusCol ? $s->status : null,
                    ])->all();

                $bk = [];
                foreach ($bookingsPool as $gb) {
                    $in = $gb->check_in_date->toDateString();
                    $out = $gb->check_out_date->toDateString();
                    if ($d < $in || $d > $out) { continue; }
                    $kind = $d === $in ? 'in' : ($d === $out ? 'out' : 'stay');
                    $bk[] = [
                        'id' => $gb->getKey(),
                        'guest' => $gb->guest_name ?: 'Guest',
                        'property' => $gb->property?->name ?? 'Property',
                        'kind' => $kind,
                        'time' => $kind === 'in'
                            ? \App\Support\BookingTimes::label($gb->checkin_time_status, $gb->checkin_time_preference, $gb->property?->checkin_time)
                            : ($kind === 'out' ? \App\Support\BookingTimes::label($gb->checkout_time_status, $gb->checkout_time_preference, $gb->property?->checkout_time) : null),
                        'conflict' => isset($conflictIds[$gb->getKey()]),
                        'turnover' => $kind !== 'stay' && isset($turnoverDates[$gb->property_id . '|' . $d]),
                    ];
                }
                usort($bk, fn ($a, $b) => [$kindOrder[$a['kind']], $a['property']] <=> [$kindOrder[$b['kind']], $b['property']]);

                $agenda[] = [
                    'date' => $d,
                    'label' => $date->format('D, M j'),
                    'isToday' => $d === $today,
                    'sessions' => $sess,
                    'pending' => ($unscheduledByDate[$d] ?? collect())->values()->all(),
                    'bookings' => $bk,
                    'blocked' => $blockedByDate[$d] ?? 0,
                ];
            }
        }

        // Build day cells
        $days = [];
        foreach (CarbonPeriod::create($gridStart, '1 day', $gridEnd) as $date) {
            $d = $date->toDateString();
            $days[] = [
                'date'          => $d,
                'isToday'       => $d === $today,
                'inMonth'       => $date->betweenIncluded($monthStart, $monthEnd),
                'sessionCount'  => ($byDate[$d] ?? collect())->count(),
                'unscheduledCount' => ($unscheduledByDate[$d] ?? collect())->count(),
                'marks'         => $bookingMarks[$d] ?? null,
                'blocked'       => $blockedByDate[$d] ?? 0,
            ];
        }

        // Prev/next month params
        $prevMonth = (clone $monthStart)->subMonth()->format('Y-m');
        $nextMonth = (clone $monthStart)->addMonth()->format('Y-m');

        // Cleaners eligible per property for the assign drawer (same rule as the dashboard)
        $cleanerOptions = [];

        // Properties the user can create a job for (same visibility rule as the assign endpoint)
        $pickerProperties = [];
        if ($u && $acting !== 'housekeeper' && $u->hasAnyRole(['admin', 'owner', 'company'])) {
            $pickerProperties = Property::query()->active()->visibleTo($u)->orderBy('name')
                ->get(['id', 'name', 'owner_id'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'has_owner' => (bool) $p->owner_id])
                ->all();
        }
        if ($acting !== 'housekeeper') {
            $assignPropIds = collect($dayUnscheduled)->pluck('property_id')
                ->merge(collect($daySessions)->pluck('property_id'))
                ->merge(collect($pickerProperties)->pluck('id'))
                ->unique()->values();
            if ($assignPropIds->isNotEmpty()) {
                $cleanerOptions = \Illuminate\Support\Facades\DB::table('property_user')
                    ->join('users', 'users.id', '=', 'property_user.user_id')
                    ->whereIn('property_user.property_id', $assignPropIds)
                    ->where('users.is_active', true)
                    ->orderBy('users.name')
                    ->get(['property_user.property_id', 'users.id', 'users.name'])
                    ->groupBy('property_id')
                    ->map(fn ($rows) => $rows->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all())
                    ->all();
            }
        }

        return view('calendar.index', [
            'acting'      => $acting,
            'monthStart'  => $monthStart,
            'prevMonth'   => $prevMonth,
            'nextMonth'   => $nextMonth,
            'days'        => $days,
            'selectedDay' => $selectedDay,
            'daySessions' => $daySessions,
            'dayUnscheduled' => $dayUnscheduled,
            'dayBookings' => $dayBookings,
            'dayBlocked' => $dayBlocked,
            'viewMode' => $viewMode,
            'rangeLabel' => $rangeLabel,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
            'todayUrl' => $todayUrl,
            'viewLinks' => $viewLinks,
            'agenda' => $agenda,
            'cleanerFilterOptions' => $cleanerFilterOptions,
            'cleaningStatusOptions' => $cleaningStatusOptions,
            'reservationStatusOptions' => \App\Models\Booking::query()->notArchived()->whereNull('cancelled_at')->whereNotNull('status')->distinct()->pluck('status')->sort()->values()->all(),
            'availableNights' => $availableNights,
            'availabilityAsOf' => $availabilityAsOf,
            'availabilityMonth' => $availabilityMonth,
            'cleanerOptions' => $cleanerOptions,
            'pickerProperties' => $pickerProperties,
        ]);
    }
}

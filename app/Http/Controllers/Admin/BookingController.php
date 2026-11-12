<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Support\PhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    use \App\Traits\BuildsGuestPortalData;
    // Re-verified 2026-08-02: parking_needed save logic confirmed correct on create + update

    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');
        $hasSearch = filled($request->search);

        $baseQuery = fn () => $this->visible()->with('property')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('guest_name', 'like', "%{$search}%")
                ->orWhere('booking_id', $search)
                ->orWhere('reservation_id', $search)
                ->orWhere('email', $search)
            ))
            ->when($request->status, fn ($query, $status) => $status === 'pending_check_in'
                ? $query->where('status', 'guest_approved')->whereDate('check_in_date', '<=', today())
                : $query->where('status', $status))
            ->when($request->property_id, fn ($query, $pid) => $query->where('property_id', $pid))
            ->when(! $hasSearch, fn ($query) => $showArchived ? $query->archived() : $query->notArchived());

        // "Today" is a single merged section, not separate Priority/Today
        // cards -- guests checking in today with incomplete steps come
        // first, then other today check-ins, then today's checkouts, all
        // ordered purely by Booking::weekCardSortTier() (tiers 1-3 cover
        // exactly this). Splitting these into separate cards was the bug:
        // the client asked for one "Today" section with internal priority
        // ordering, not multiple labeled sections.
        $today = $this->todayTabBookings();
        $this->attachLinkedCleanersToBookings($today);
        $todayTotal = $today->count();
        $todayIds = $today->pluck('id');
        $today = $today->take(5)->values();

        // "This Week" covers everything else in the operational window
        // (currently hosting, upcoming within 6 days) -- tiers 4-6 of the
        // same weekCardSortTier() scale, so the two sections use one
        // consistent source of truth for ordering. Guests who have already
        // checked out are deliberately excluded: they only appear in
        // "Today" on the day they check out, then are archived.
        $thisWeekAll = $this->visible()->with('property')
            ->notArchived()
            ->whereNotIn('id', $todayIds)
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '<=', today()->addDays(6))
            ->get()
            ->sort(function ($a, $b) {
                $tierCompare = $a->weekCardSortTier() <=> $b->weekCardSortTier();

                return $tierCompare !== 0 ? $tierCompare : $a->check_in_date->timestamp <=> $b->check_in_date->timestamp;
            })
            ->values();
        $thisWeekTotal = $thisWeekAll->count();
        $this->attachLinkedCleanersToBookings($thisWeekAll);
        $thisWeek = $thisWeekAll->take(5)->values();

        // "Upcoming" card: all future stays after this week's window,
        // capped to an initial batch of 5 with a "Show More" AJAX endpoint
        // that loads 5 more at a time.
        $upcomingLimit = 5;
        $upcomingBaseQuery = fn () => $this->visible()->with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', today()->addDays(6));

        $upcoming = ($upcomingBaseQuery)()->orderBy('check_in_date')->limit($upcomingLimit)->get();
        $this->attachLinkedCleanersToBookings($upcoming);
        $upcomingTotal = ($upcomingBaseQuery)()->count();

        $thisWeekIds = $thisWeekAll->pluck('id')->merge($todayIds);
        $bookings = ($baseQuery)()
            ->when($thisWeekIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $thisWeekIds))
            ->when($showArchived && ! $hasSearch,
                fn ($q) => $q->orderByDesc('archived_at'),
                fn ($q) => $q->orderBy('check_in_date'))
            ->paginate(15)
            ->withQueryString();
        $this->attachLinkedCleanersToBookings($bookings);

        $properties = Property::visibleTo(auth()->user())->orderBy('name')->get();

        $stats = [
            'total_guests'     => $this->visible()->notArchived()->count(),
            'todays_arrivals'  => $this->visible()->notArchived()->whereDate('check_in_date', today())->count(),
            'waiting_approval' => $this->visible()->notArchived()->where('status', 'pre_checkin_complete')->whereNull('approved_at')->count(),
            'checked_in'       => $this->visible()->notArchived()
                ->where(fn ($q) => $q->where('manually_checked_in', true)->orWhere('status', 'currently_hosting'))
                ->whereNull('checked_out_at')
                ->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'today', 'todayTotal', 'thisWeek', 'thisWeekTotal', 'upcoming', 'upcomingTotal', 'upcomingLimit', 'properties', 'showArchived', 'stats'));
    }

    /**
     * Single source of truth for the guest list "Today" tab, used by
     * index() and both Show More endpoints so paging never drifts.
     * Includes check-ins/check-outs today plus every guest whose stay
     * covers today (checked in or not), ordered by todayTabSortKey().
     */
    private function todayTabBookings()
    {
        return $this->visible()->with('property')
            ->notArchived()
            ->where(fn ($q) => $q
                ->whereDate('check_in_date', today())
                ->orWhereDate('check_out_date', today())
                ->orWhere(fn ($s) => $s
                    ->whereDate('check_in_date', '<=', today())
                    ->whereDate('check_out_date', '>', today())
                    ->whereNull('checked_out_at')))
            ->get()
            ->sortBy(fn ($b) => $b->todayTabSortKey())
            ->values();
    }

    public function todayMore(Request $request)
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(12, max(1, (int) $request->query('limit', 5)));

        // Must match index()'s Today ordering exactly (sorted by
        // weekCardSortTier() in PHP, not a DB-level orderBy) so "Show More"
        // continues in the same priority order as the initial page load
        // rather than falling back to plain check_in_date ordering.
        $bookings = $this->todayTabBookings();
        $this->attachLinkedCleanersToBookings($bookings);

        $total = $bookings->count();
        $batch = $bookings->slice($offset, $limit);
        $this->attachLinkedCleanersToBookings($batch);

        return response()->json([
            'html' => $batch->map(fn ($booking) => view('admin.bookings.partials.week-guest-row', ['booking' => $booking, 'context' => 'today'])->render())->implode(''),
            'next_offset' => $offset + $batch->count(),
            'has_more' => ($offset + $batch->count()) < $total,
        ]);
    }

    public function thisWeekMore(Request $request)
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(12, max(1, (int) $request->query('limit', 5)));

        $bookings = $this->visible()->with('property')
            ->notArchived()
            ->whereNotIn('id', $this->todayTabBookings()->pluck('id'))
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '<=', today()->addDays(6))
            ->get()
            ->sort(function ($a, $b) {
                $tierCompare = $a->weekCardSortTier() <=> $b->weekCardSortTier();

                return $tierCompare !== 0 ? $tierCompare : $a->check_in_date->timestamp <=> $b->check_in_date->timestamp;
            })
            ->values();

        $this->attachLinkedCleanersToBookings($bookings);
        $total = $bookings->count();
        $batch = $bookings->slice($offset, $limit);
        $this->attachLinkedCleanersToBookings($batch);
        $html = $batch->map(fn ($booking) => view('admin.bookings.partials.week-guest-row', [
            'booking' => $booking,
            'context' => 'this-week',
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'next_offset' => $offset + $batch->count(),
            'has_more' => ($offset + $batch->count()) < $total,
        ]);
    }

    /**
     * Live search dropdown for the guest search bar (task 7): returns a
     * small JSON list of matches as the admin types, instead of requiring
     * a form submit to filter the table below.
     */
    public function searchLive(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $results = $this->visible()->with('property')
            ->where(fn ($q) => $q
                ->where('guest_name', 'like', "%{$search}%")
                ->orWhere('booking_id', $search)
                ->orWhere('reservation_id', $search)
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('check_in_date', 'desc')
            ->limit(8)
            ->get();

        $cleanerNames = $this->bookingCleanerNames($results);

        $results = $results->map(fn ($b) => [
            'id'       => $b->id,
            'name'     => $b->guest_name,
            'property' => $b->property?->name,
            'stay'     => $b->stayRangeLabel(),
            'status'   => $b->statusLabel(),
            'cleaner'  => $cleanerNames[$b->id] ?? null,
            'url'      => route('admin.guests.show', $b),
        ]);

        return response()->json($results);
    }

    /**
     * Batch-loads additional "Upcoming" rows for the Show More link,
     * rendered server-side with the same row partial used on initial page load.
     */
    public function upcomingMore(Request $request)
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $limit  = min(12, max(1, (int) $request->query('limit', 5)));

        $bookings = $this->visible()->with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', today()->addDays(6))
            ->orderBy('check_in_date')
            ->skip($offset)
            ->take($limit)
            ->get();

        $this->attachLinkedCleanersToBookings($bookings);

        $total = $this->visible()->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', today()->addDays(6))
            ->count();

        $html = $bookings->map(fn ($booking) => view('admin.bookings.partials.week-guest-row', [
            'booking' => $booking,
            'context' => 'upcoming',
        ])->render())->implode('');

        return response()->json([
            'html'       => $html,
            'next_offset' => $offset + $bookings->count(),
            'has_more'   => ($offset + $bookings->count()) < $total,
        ]);
    }

    public function create()
    {
        return view('admin.bookings.form', [
            'booking'          => new Booking(),
            'properties'       => Property::visibleTo(auth()->user())->where('active', true)->orderBy('name')->get(),
            'instructionSteps' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data               = $this->validated($request);
        $data['phone'] = PhoneFormatter::normalizeForStorage($data['phone'] ?? null, $request->input('phone_country_code', '+1'));
        $this->enforcePreCheckinCap($data);
        $data['booking_id'] = ($data['booking_id'] ?? null) ?: 'BK-'.strtoupper(Str::random(8));
        $data['token']      = Str::random(40);
        $data['photo_id_received'] = $request->boolean('photo_id_received');
        if (($data['status'] ?? null) === 'pre_checkin_complete') {
            $data['photo_id_received'] = true;
        }
        if ($data['photo_id_received'] && empty($data['approved_at'])) {
            $data['approved_at'] = now();
        }
        $cleanerId = $data['housekeeper_id'] ?? null;
        unset($data['housekeeper_id']); // not a bookings column

        // Hold back the "needs a cleaner" text when a cleaner was picked on the form.
        \App\Services\CleaningFlowService::$suppressNeedsCleaner = (bool) $cleanerId;
        try {
            $booking = \App\Services\BookingConflictService::withPropertyLock((int) $data['property_id'], function () use ($data) {
                if (($data['status'] ?? null) !== 'cancelled') {
                    \App\Services\BookingConflictService::assertFree((int) $data['property_id'], $data['check_in_date'], $data['check_out_date']);
                }

                return Booking::create($data);
            });
        } finally {
            \App\Services\CleaningFlowService::$suppressNeedsCleaner = false;
        }
        $booking->recalculateParkingCharge();

        app(\App\Services\Pms\AvailabilityLedger::class)->syncBooking($booking);

        ActivityLogService::admin('booking_created', "Guest booking created for {$booking->guest_name} ({$booking->booking_id}).", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
            'metadata'     => [
                'guest_name'    => $booking->guest_name,
                'check_in_date' => $booking->check_in_date,
                'property_id'   => $booking->property_id,
            ],
        ]);

        $notice = 'Guest booking created successfully.';
        if ($cleanerId) {
            try {
                $session = \App\Models\CleaningSession::where('booking_id', $booking->id)
                    ->where('status', '<>', 'cancelled')->first();
                if (! $session) {
                    throw new \RuntimeException('No cleaning was created for this checkout date.');
                }
                app(\App\Services\CleaningJobService::class)->quickAssign(auth()->user(), [
                    'property_id'    => $booking->property_id,
                    'housekeeper_id' => (int) $cleanerId,
                    'scheduled_date' => $booking->check_out_date->toDateString(),
                    'scheduled_time' => $session->scheduled_time?->format('H:i'),
                ], $session, false);
                $notice .= ' Cleaner assigned.';
            } catch (\Illuminate\Validation\ValidationException $e) {
                $notice .= ' The cleaner was NOT assigned: '.collect($e->errors())->flatten()->first();
            } catch (\Throwable $e) {
                report($e);
                $notice .= ' The cleaner was NOT assigned: '.$e->getMessage().' Use Actions > Assign cleaner.';
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $booking->id, 'message' => $notice]);
        }

        return redirect()->route('admin.guests.show', $booking)->with('success', $notice);
    }

    public function show(Booking $booking)
    {
        $booking->load(['property', 'leaseOffer.application.charges', 'leaseOffer.installments.charges', 'charges']);

        $guestLogs = \App\Models\ActivityLog::where('booking_id', $booking->id)
            ->orWhere(fn ($q) => $q
                ->where('subject_type', Booking::class)
                ->where('subject_id', $booking->id)
            )
            ->latest()
            ->take(25)
            ->get();

        // Needed by the inline Guest Details edit panel's Property dropdown
        // (task: merged edit/view page) -- same source list the old
        // separate edit page used.
        $properties = Property::visibleTo(auth()->user())->where('active', true)->orderBy('name')->get();

        $cleanings = $booking->linkedCleanings()->with('housekeeper:id,name')->orderByDesc('scheduled_date')->get();

        return view('admin.bookings.show', compact('booking', 'guestLogs', 'properties', 'cleanings'));
    }

    public function update(Request $request, Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $oldStatus = $booking->status;
        $data = $this->validated($request, $booking);
        $data['phone'] = PhoneFormatter::normalizeForStorage($data['phone'] ?? null, $request->input('phone_country_code', '+1'));
        if ($request->has('parking_needed')) {
            $data['parking_needed'] = $request->boolean('parking_needed');
        }
        $this->enforcePreCheckinCap(array_merge($booking->only(['incidentals_charge', 'parking_needed', 'early_checkin_tier', 'early_checkin_charge_override', 'early_checkin_billing_mode']), $data));
        $data['photo_id_received'] = $request->boolean('photo_id_received');
        if (($data['status'] ?? null) === 'pre_checkin_complete') {
            $data['photo_id_received'] = true;
        }
        if ($data['photo_id_received'] && empty($booking->approved_at) && empty($data['approved_at'])) {
            $data['approved_at'] = now();
        }
        $timesChanged = false;
        foreach ([
            'checkin_time_preference' => 'checkin_time_status',
            'checkout_time_preference' => 'checkout_time_status',
        ] as $timeField => $statusField) {
            if (array_key_exists($timeField, $data) && $data[$timeField] !== $booking->{$timeField}) {
                $data[$statusField] = filled($data[$timeField]) ? 'approved' : null;
                $timesChanged = true;
            }
        }
        $oldStay = ['property_id' => $booking->property_id, 'from' => $booking->check_in_date?->toDateString(), 'to' => $booking->check_out_date?->toDateString()];
        // Only re-check when property/dates actually changed, so editing a note on a
        // booking that already overlaps (legacy data) is never blocked.
        $stayChanged = (int) $data['property_id'] !== (int) $booking->property_id
            || \Carbon\Carbon::parse($data['check_in_date'])->toDateString() !== $booking->check_in_date?->toDateString()
            || \Carbon\Carbon::parse($data['check_out_date'])->toDateString() !== $booking->check_out_date?->toDateString();
        \App\Services\BookingConflictService::withPropertyLock((int) $data['property_id'], function () use ($booking, $data, $stayChanged) {
            if ($stayChanged && ($data['status'] ?? $booking->status) !== 'cancelled') {
                \App\Services\BookingConflictService::assertFree((int) $data['property_id'], $data['check_in_date'], $data['check_out_date'], $booking->id);
            }
            $booking->update($data);
        });
        app(\App\Services\Pms\AvailabilityLedger::class)->syncBooking($booking, $oldStay);
        $booking->recalculateParkingCharge();
        if ($timesChanged) {
            $booking->syncFeesFromApprovedTimes();
        }

        ActivityLogService::admin('booking_updated', auth()->user()->name." updated booking for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'metadata'     => ['old_status' => $oldStatus, 'new_status' => $booking->status],
        ]);

        return redirect()->route('admin.guests.show', $booking)->with('success', 'Booking updated.');
    }

    /**
     * Dedicated endpoint for the "Guest Details" ledger editor (parking,
     * incidentals hold, early check-in, late checkout -- amounts and the
     * fields that drive them). Kept entirely separate from update() above
     * so this form and the identity-fields form on the same page never
     * step on each other's fields -- each only ever submits/validates its
     * own set, so neither can null out data the other owns.
     */
    public function updateLedger(Request $request, Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $oldEarlyCheckinTier = $booking->early_checkin_tier;
        $oldLateCheckoutType = $booking->late_checkout_type;
        $oldLateCheckoutCharge = $booking->effectiveLateCheckoutCharge();
        $oldIncidentalsCharge = (float) ($booking->effectiveIncidentalsCharge() ?? 0);

        $data = $request->validate([
            // parking_needed intentionally excluded: it's set by the guest
            // during their own check-in flow, read-only for admin here.
            // Leaving it out of validated() means updateLedger() never
            // touches this column, so it can't be nulled out just because
            // this form doesn't submit it.
            'parking_charge_override'        => ['nullable', 'numeric', 'min:0'],
            'incidentals_charge'             => ['nullable', 'numeric', 'min:0'],
            'additional_deposit'             => ['nullable', 'numeric', 'min:0'],
            'early_checkin_tier'             => ['nullable', 'in:8am_12pm,12pm_2pm,2pm_4pm,8am,12pm'],
            'early_checkin_charge_override'  => ['nullable', 'numeric', 'min:0'],
            'early_checkin_billing_mode'     => ['nullable', 'in:charge,deduct_from_hold'],
            'late_checkout_type'             => ['nullable', 'in:authorized,unauthorized'],
            'late_checkout_hours'            => ['nullable', 'numeric', 'min:0'],
            'late_checkout_actual_time'      => ['nullable', 'date'],
            'late_checkout_charge_override'  => ['nullable', 'numeric', 'min:0'],
        ]);

        // Bookings from before the additional-deposit ledger keep their original behavior untouched.
        if (! $booking->usesAdditionalDeposit()) {
            unset($data['additional_deposit']);
        }

        $this->enforcePreCheckinCap(array_merge(
            $booking->only(['property_id', 'check_in_date', 'check_out_date', 'parking_needed', 'early_checkin_billing_mode']),
            $data
        ));

        $booking->update($data);
        // recalculateParkingCharge() is safe to leave here -- it only
        // recomputes based on $booking->parking_needed (untouched) and the
        // stay dates (also untouched by this form), so it doesn't need
        // parking_needed to have come from this request.
        $booking->recalculateParkingCharge();

        // Newly granted (not just re-saved unchanged) early check-in tier:
        // let the guest know via their existing portal link so they can pay
        // for it -- they won't otherwise know to check back, since granting
        // it is an admin-initiated action.
        $notifyReservationUpdated = false;
        if ($booking->early_checkin_tier && $booking->early_checkin_tier !== $oldEarlyCheckinTier) {
            $notifyReservationUpdated = true;
        }

        // Same idea for late checkout: only fire when it's newly marked
        // authorized (not on every ledger re-save), same guard shape as
        // early_checkin_tier above.
        if ($booking->late_checkout_type === 'authorized' && $oldLateCheckoutType !== 'authorized') {
            $notifyReservationUpdated = true;
        }

        if ($notifyReservationUpdated) {
            \App\Services\GuestAlertService::send('reservation_updated', $booking->fresh());
        }

        // Same idea for late checkout / incidentals, but only once the guest
        // has actually checked out -- these are almost always entered by
        // admin after the stay, and the guest can't see the payment screen
        // (post_checkout state) until then anyway.
        if ($booking->status === 'checked_out') {
            $newLateCheckoutCharge = $booking->effectiveLateCheckoutCharge();
            $newIncidentalsCharge = (float) ($booking->effectiveIncidentalsCharge() ?? 0);

            if (($newLateCheckoutCharge ?? 0) > ($oldLateCheckoutCharge ?? 0) || $newIncidentalsCharge > $oldIncidentalsCharge) {
                \App\Services\GuestAlertService::send('post_checkout_balance_due', $booking);
            }
        }

        ActivityLogService::admin('booking_ledger_updated', auth()->user()->name." updated the charge ledger for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'info',
        ]);

        return redirect()->route('admin.guests.show', $booking)->with('success', 'Ledger updated.');
    }

    /** Toggle: apply (or undo) the unauthorized late checkout fee against the incidentals hold. */
    public function deductLateCheckout(Booking $booking)
    {
        abort_unless($booking->checked_out_at && $booking->unauthorizedLateCheckoutCharge() > 0, 422, 'No unauthorized late checkout fee to deduct.');
        $booking->update(['late_checkout_deducted_at' => $booking->late_checkout_deducted_at ? null : now()]);

        return back()->with('success', $booking->late_checkout_deducted_at ? 'Late checkout fee deducted from the hold.' : 'Deduction removed.');
    }

    public function updateWelcomeMessage(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'welcome_message' => ['nullable', 'string'],
        ]);

        $booking->update($data);

        ActivityLogService::admin('booking_welcome_message_updated', auth()->user()->name." updated the welcome message for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
        ]);

        return back()->with('success', 'Welcome message saved.');
    }

    public function destroy(Booking $booking)
    {
        $name = $booking->guest_name;

        ActivityLogService::admin('booking_deleted', auth()->user()->name." deleted booking for {$name} ({$booking->booking_id}).", 'guests', [
            'severity' => 'warning',
            'metadata' => ['booking_id' => $booking->booking_id, 'guest_name' => $name],
        ]);

        $stay = ['property_id' => $booking->property_id, 'from' => $booking->check_in_date?->toDateString(), 'to' => $booking->check_out_date?->toDateString()];
        $booking->delete();
        app(\App\Services\Pms\AvailabilityLedger::class)->syncStay($stay);

        return redirect()->route('admin.guests.index')->with('success', "Booking for {$name} deleted.");
    }

    public function archive(Booking $booking)
    {
        $booking->update(['archived_at' => now()]);
        ActivityLogService::admin('booking_archived', auth()->user()->name." archived booking for {$booking->guest_name} ({$booking->booking_id}).", 'guests', [
            'severity' => 'info',
            'metadata' => ['booking_id' => $booking->booking_id, 'guest_name' => $booking->guest_name],
        ]);
        return back()->with('success', "Booking for {$booking->guest_name} archived.");
    }
    public function unarchive(Booking $booking)
    {
        $booking->update(['archived_at' => null]);
        ActivityLogService::admin('booking_unarchived', auth()->user()->name." unarchived booking for {$booking->guest_name} ({$booking->booking_id}).", 'guests', [
            'severity' => 'info',
            'metadata' => ['booking_id' => $booking->booking_id, 'guest_name' => $booking->guest_name],
        ]);
        return back()->with('success', "Booking for {$booking->guest_name} restored from archive.");
    }
    public function overrideGps(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update(['gps_verified' => true]);
        ActivityLogService::security('gps_override', auth()->user()->name." overrode GPS verification for {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
            'metadata'     => ['guest_name' => $booking->guest_name, 'override_by' => auth()->user()->name],
        ]);
        return back()->with('success', 'GPS verification overridden for guest.');
    }

    public function overrideCheckin(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'manually_checked_in' => true,
            'checked_in_at'       => now(),
            'checked_out_at'      => null,
            'status'              => 'currently_hosting',
        ]);

        ActivityLogService::security('manual_checkin_override', auth()->user()->name." manually checked in {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
            'metadata'     => ['guest_name' => $booking->guest_name, 'override_by' => auth()->user()->name],
        ]);

        return back()->with('success', 'Guest manually marked as checked in.');
    }

    public function overrideCheckout(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'checked_out_at' => now(),
            'status'         => 'checked_out',
        ]);

        ActivityLogService::security('manual_checkout_override', auth()->user()->name." manually checked out {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
            'metadata'     => ['guest_name' => $booking->guest_name, 'override_by' => auth()->user()->name],
        ]);

        return back()->with('success', 'Guest manually marked as checked out.');
    }

    public function markIdReceived(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'photo_id_received' => true,
            'status' => $booking->status === 'pending' ? 'pre_checkin_complete' : $booking->status,
            'approved_at' => $booking->approved_at ?: now(),
        ]);

        ActivityLogService::admin('photo_id_marked', auth()->user()->name." marked photo ID as received for {$booking->guest_name}.", 'photo_id', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', 'Photo ID marked as received.');
    }

    public function bypassVehicleInfo(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'vehicle_info_bypassed_at' => now(),
        ]);
        ActivityLogService::admin('vehicle_info_bypassed', auth()->user()->name." bypassed vehicle info for {$booking->guest_name}.", 'vehicle_info', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);
        return back()->with('success', 'Vehicle info requirement bypassed.');
    }

    public function approveBooking(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'approved_at' => now(),
            'decline_reason' => null,
        ]);

        ActivityLogService::admin('booking_approved', auth()->user()->name." approved {$booking->guest_name} for check-in.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', 'Guest approved for check-in.');
    }

    public function markBackgroundCheckComplete(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        if (! $booking->isApproved()) {
            return back()->with('error', 'Photo ID must be approved before marking the background check complete.');
        }

        $booking->update([
            'background_check_completed_at' => now(),
            'status' => 'awaiting_deposit',
        ]);
        \App\Services\GuestAlertService::send('background_check_complete', $booking, ['result' => 'approved']);

        ActivityLogService::admin('background_check_completed', auth()->user()->name." marked background check complete for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', 'Background check marked complete, guest is now awaiting deposit.');
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,pre_checkin_complete,awaiting_deposit,guest_approved,currently_hosting,checked_out,cancelled'],
            'refund_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($data['status'] === 'cancelled' && $booking->source === 'direct' && ! $booking->isCancelled()) {
            try {
                $refund = app(\App\Services\DirectBookingService::class)->cancel($booking, false, 'Cancelled by staff.');
            } catch (\Stripe\Exception\ApiErrorException $e) {
                \Illuminate\Support\Facades\Log::warning('Direct booking cancel: Stripe refund failed', ['booking' => $booking->booking_id, 'message' => $e->getMessage()]);

                return back()->with('error', 'The refund could not be sent to Stripe, so the booking was NOT cancelled. Please try again in a moment.');
            }
            ActivityLogService::admin('direct_booking_cancelled', auth()->user()->name." cancelled direct booking {$booking->booking_id}.", 'guests', [
                'subject_type' => Booking::class,
                'subject_id'   => $booking->id,
                'booking_id'   => $booking->id,
                'property_id'  => $booking->property_id,
                'severity'     => 'warning',
            ]);

            return back()->with('success', 'Direct booking cancelled. Use the Refund panel on this booking to refund the guest.');
        }

        $wasCancelled = $booking->isCancelled();
        $goingCancelled = $data['status'] === 'cancelled';

        // Any status change on a cancelled booking is blocked EXCEPT
        // reversing the cancellation itself (picking a different status) --
        // that's the one deliberate way to undo an admin/guest mistake.
        if ($wasCancelled && $goingCancelled) {
            $this->guardNotCancelled($booking);
        }

        $updates = ['status' => $data['status']];

        if (! $wasCancelled && $goingCancelled) {
            // Admin-triggered cancellation: mirror the OTA cancellation path
            // in BookingImportService exactly, just with cancelled_by_guest
            // false since this one came from the admin panel, not the guest.
            $feeApplies = $booking->cancellationFeeAppliesForDate();

            $updates['cancelled_at'] = now();
            $updates['cancelled_by_guest'] = false;
            $updates['cancellation_fee_applies'] = $feeApplies;
            $updates['archived_at'] = $feeApplies ? null : now();
        } elseif ($wasCancelled && ! $goingCancelled) {
            // Reversing a cancellation: clear every cancellation-related
            // field and make sure the booking isn't left archived.
            $updates['cancelled_at'] = null;
            $updates['cancelled_by_guest'] = false;
            $updates['cancellation_fee_applies'] = false;
            $updates['archived_at'] = null;
        }

        $reviving = $wasCancelled && ! $goingCancelled;
        \App\Services\BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking, $updates, $reviving) {
            if ($reviving) {
                \App\Services\BookingConflictService::assertFree((int) $booking->property_id, $booking->check_in_date, $booking->check_out_date, $booking->id, 'status');
            }
            $booking->update($updates);
        });
        app(\App\Services\Pms\AvailabilityLedger::class)->syncBooking($booking);

        ActivityLogService::admin('status_manually_changed', auth()->user()->name." manually set status to \"".str($data['status'])->replace('_', ' ')->title()."\" for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
        ]);

        return back()->with('success', 'Status updated.');
    }

    public function markDepositVerified(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        if (! $booking->isBackgroundCheckComplete()) {
            return back()->with('error', 'Background check must be completed before verifying the deposit.');
        }

        $booking->update([
            'deposit_verified_at' => now(),
            'status' => 'guest_approved',
        ]);
        \App\Services\GuestAlertService::send('fully_approved', $booking);

        ActivityLogService::admin('deposit_verified', auth()->user()->name." verified the deposit for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', 'Deposit verified. Guest is now approved.');
    }

    /**
     * "The unit is ready and the guest may check in." Until this is set, a
     * fully pre-checked-in guest is held on the "unit isn't quite ready yet"
     * screen instead of seeing their arrival details.
     */
    public function approveCheckin(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update(['checkin_approved_at' => now()]);

        \App\Services\CleaningFlowService::sendUnitReady($booking);

        ActivityLogService::admin('checkin_approved', auth()->user()->name." marked the unit ready and approved check-in for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', 'Unit ready — guest approved to check in.');
    }

    /**
     * Approve a single side of the guest's ID (front or back) independently.
     * Overall booking approval (approved_at) is only set once every side on
     * file is approved.
     */
    /**
     * Approve or deny a guest's requested non-standard check-in/check-out
     * time (task 0). Approving auto-selects the early check-in window or
     * authorized late checkout hours via syncFeesFromApprovedTimes(); admin
     * can still adjust them in the ledger. The guest is notified of the
     * decision either way, via the checkin_time_approved/denied or
     * checkout_time_approved/denied alert events.
     */
    public function updateTimePreferenceStatus(Request $request, Booking $booking, string $type)
    {
        $this->guardNotCancelled($booking);

        abort_unless(in_array($type, ['checkin', 'checkout'], true), 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
        ]);

        $statusField = $type === 'checkin' ? 'checkin_time_status' : 'checkout_time_status';

        if (($data['decision'] ?? null) === 'approved') {
            $pref = $type === 'checkin' ? $booking->checkin_time_preference : $booking->checkout_time_preference;
            $dead = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];
            $live = fn ($q) => $q->whereNull('cancelled_at')
                ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', $dead));
            $min = \App\Services\CleaningJobService::MIN_TURNOVER_MINUTES;

            if ($pref && $type === 'checkout') {
                $date = \Carbon\Carbon::parse($booking->check_out_date)->toDateString();
                $out = \Carbon\Carbon::parse($date.' '.$pref);
                $next = Booking::where('property_id', $booking->property_id)->whereDate('check_in_date', $date)
                    ->where('id', '<>', $booking->id)->where($live)->first();
                if ($next) {
                    $arrive = \Carbon\Carbon::parse($date.' '.$next->effectiveCheckinTime());
                    if ($out->diffInMinutes($arrive, false) < $min) {
                        $msg = 'Cannot approve: the next guest arrives at '.$arrive->format('g:i A').', which leaves less than '.($min / 60).' hours after a '.$out->format('g:i A').' checkout. Deny it to keep the standard time.';
                        return back()->withErrors(['time' => $msg])->with('error', $msg);
                    }
                }
            }

            if ($pref && $type === 'checkin') {
                $date = \Carbon\Carbon::parse($booking->check_in_date)->toDateString();
                $arrive = \Carbon\Carbon::parse($date.' '.$pref);
                $prev = Booking::where('property_id', $booking->property_id)->whereDate('check_out_date', $date)
                    ->where('id', '<>', $booking->id)->where($live)->first();
                if ($prev) {
                    $leave = \Carbon\Carbon::parse($date.' '.$prev->effectiveCheckoutTime());
                    if ($leave->diffInMinutes($arrive, false) < $min) {
                        $msg = 'Cannot approve: the previous guest checks out at '.$leave->format('g:i A').', which leaves less than '.($min / 60).' hours before a '.$arrive->format('g:i A').' check-in. Deny it to keep the standard time.';
                        return back()->withErrors(['time' => $msg])->with('error', $msg);
                    }
                }
            }
        }

        $booking->update([
            $statusField => $data['decision'],
        ]);

        $booking->refresh();

        // Approval auto-selects the early check-in window / authorized late
        // checkout hours; denial clears them. The alert below already tells
        // the guest, so don't send a second one from the sync.
        $booking->syncFeesFromApprovedTimes(notify: false);

        $requestedTime = $type === 'checkin'
            ? $booking->checkinTimePreferenceFormatted()
            : $booking->checkoutTimePreferenceFormatted();

        $event = $type.'_time_'.$data['decision'];

        \App\Services\GuestAlertService::send('reservation_updated', $booking);

        $label = $type === 'checkin' ? 'check-in' : 'check-out';

        ActivityLogService::admin(
            'time_preference_'.$data['decision'],
            auth()->user()->name." {$data['decision']} {$booking->guest_name}'s requested {$label} time.",
            'guests',
            [
                'subject_type' => Booking::class,
                'subject_id'   => $booking->id,
                'booking_id'   => $booking->id,
                'property_id'  => $booking->property_id,
                'severity'     => $data['decision'] === 'approved' ? 'success' : 'warning',
            ]
        );

        return back()->with('success', ucfirst($label)." time request {$data['decision']}.");
    }

    public function approveIdSide(Request $request, Booking $booking, string $side)
    {
        $this->guardNotCancelled($booking);

        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $field = $side === 'back' ? 'photo_id_back_approved_at' : 'photo_id_front_approved_at';
        $reasonField = $side === 'back' ? 'photo_id_back_declined_reason' : 'photo_id_front_declined_reason';

        $booking->update([
            $field => now(),
            $reasonField => null,
        ]);

        if ($booking->fresh()->isIdFullyApproved()) {
            $booking->update([
                'approved_at' => now(),
                'decline_reason' => null,
                'photo_id_received' => true,
            ]);
        }

        ActivityLogService::admin('id_side_approved', auth()->user()->name." approved the {$side} of {$booking->guest_name}'s ID.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'success',
        ]);

        return back()->with('success', ucfirst($side).' of ID approved.');
    }

    /**
     * Decline a single side of the guest's ID (front or back) independently.
     * The declined side's uploaded photo is cleared so the guest is forced to
     * re-upload only that side; the other side (if already approved) is left
     * untouched. Triggers an email + SMS to the guest with the reason.
     */
    public function declineIdSide(Request $request, Booking $booking, string $side)
    {
        $this->guardNotCancelled($booking);

        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $data = $request->validate([
            'decline_reason' => ['required', 'string', 'max:1000'],
        ]);

        $pathField = $side === 'back' ? 'photo_id_back_path' : 'photo_id_path';
        $approvedField = $side === 'back' ? 'photo_id_back_approved_at' : 'photo_id_front_approved_at';
        $reasonField = $side === 'back' ? 'photo_id_back_declined_reason' : 'photo_id_front_declined_reason';

        $booking->update([
            $pathField => null,
            $approvedField => null,
            $reasonField => $data['decline_reason'],
            'approved_at' => null,
            'decline_reason' => $data['decline_reason'],
            'photo_id_received' => false,
            'status' => 'pending',
        ]);

        ActivityLogService::admin('id_side_declined', auth()->user()->name." declined the {$side} of {$booking->guest_name}'s ID: {$data['decline_reason']}", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
        ]);

        $sideLabel = $side === 'back' ? 'back' : 'front';

        \App\Services\GuestAlertService::send('photo_id_declined', $booking, [
            'id_side' => $sideLabel,
            'decline_reason' => $data['decline_reason'],
        ]);

        return back()->with('success', ucfirst($side).' of ID declined. Guest has been notified and asked to re-upload.');
    }

    public function blockAccess(Request $request, Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $data = $request->validate([
            'access_blocked_reason' => ['required', 'string', 'max:1000'],
        ]);

        $booking->update([
            'access_blocked_at' => now(),
            'access_blocked_reason' => $data['access_blocked_reason'],
        ]);

        ActivityLogService::admin('booking_access_blocked', auth()->user()->name." blocked access for {$booking->guest_name}: {$data['access_blocked_reason']}", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'warning',
        ]);

        return back()->with('success', 'Guest access blocked.');
    }

    public function unblockAccess(Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $booking->update([
            'access_blocked_at' => null,
            'access_blocked_reason' => null,
        ]);

        ActivityLogService::admin('booking_access_unblocked', auth()->user()->name." restored access for {$booking->guest_name}", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'info',
        ]);

        return back()->with('success', 'Guest access restored.');
    }

    public function photoId(Booking $booking)
    {
        abort_unless($booking->photo_id_path && Storage::disk('local')->exists($booking->photo_id_path), 404);

        ActivityLogService::security('photo_id_viewed', auth()->user()->name." viewed photo ID for {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'security',
            'metadata'     => ['accessed_by' => auth()->user()->name],
        ]);

        $ext = pathinfo($booking->photo_id_path, PATHINFO_EXTENSION) ?: 'jpg';

        return response()->download(
            \Storage::path($booking->photo_id_path),
            $booking->booking_id.'-photo-id.'.$ext
        );
    }

    public function photoIdView(Booking $booking)
    {
        abort_unless($booking->photo_id_path && Storage::disk('local')->exists($booking->photo_id_path), 404);

        return response()->file(\Storage::path($booking->photo_id_path));
    }

    public function photoIdBack(Booking $booking)
    {
        abort_unless($booking->photo_id_back_path && Storage::disk('local')->exists($booking->photo_id_back_path), 404);
        ActivityLogService::security('photo_id_back_viewed', auth()->user()->name." viewed back of photo ID for {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'security',
            'metadata'     => ['accessed_by' => auth()->user()->name],
        ]);

        $ext = pathinfo($booking->photo_id_back_path, PATHINFO_EXTENSION) ?: 'jpg';

        return response()->download(
            \Storage::path($booking->photo_id_back_path),
            $booking->booking_id.'-photo-id-back.'.$ext
        );
    }

    public function photoIdBackView(Booking $booking)
    {
        abort_unless($booking->photo_id_back_path && Storage::disk('local')->exists($booking->photo_id_back_path), 404);

        return response()->file(\Storage::path($booking->photo_id_back_path));
    }

    public function licensePlate(Booking $booking)
    {
        abort_unless($booking->license_plate_photo_path && Storage::disk('local')->exists($booking->license_plate_photo_path), 404);

        ActivityLogService::security('license_plate_photo_viewed', auth()->user()->name." viewed license plate photo for {$booking->guest_name} ({$booking->booking_id}).", [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'severity'     => 'security',
            'metadata'     => ['accessed_by' => auth()->user()->name],
        ]);

        $ext = pathinfo($booking->license_plate_photo_path, PATHINFO_EXTENSION) ?: 'jpg';

        return response()->download(
            \Storage::path($booking->license_plate_photo_path),
            $booking->booking_id.'-license-plate.'.$ext
        );
    }

    public function licensePlateView(Booking $booking)
    {
        abort_unless($booking->license_plate_photo_path && Storage::disk('local')->exists($booking->license_plate_photo_path), 404);

        return response()->file(\Storage::path($booking->license_plate_photo_path));
    }

    public function rentalAgreement(\App\Models\Booking $booking)
    {
        return response(app(\App\Services\RentalAgreementService::class)->renderHtml($booking));
    }

    /** Bookings the signed-in user may see (admin: all; others: only on properties visible to them). */
    private function visible(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        abort_unless($user, 403);

        return \App\Support\BookingAccess::query($user);
    }

    /**
     * Efficiently resolve the most recent linked cleaner for a page batch of bookings
     * without issuing one query per row. Matches Booking::linkedCleanings() semantics.
     */
    private function bookingCleanerNames(\Illuminate\Support\Collection $bookings): array
    {
        if ($bookings->isEmpty()) {
            return [];
        }

        $bookingIds = $bookings->pluck('id')->filter()->all();
        if ($bookingIds === []) {
            return [];
        }

        $propertyDateMatches = $bookings
            ->filter(fn ($booking) => $booking->property_id && $booking->check_out_date)
            ->mapWithKeys(fn ($booking) => [$booking->id => [
                'property_id' => $booking->property_id,
                'date' => $booking->check_out_date->toDateString(),
            ]])
            ->all();

        $sessions = \App\Models\CleaningSession::query()
            ->where(function ($query) use ($bookingIds, $propertyDateMatches) {
                if ($bookingIds !== []) {
                    $query->whereIn('booking_id', $bookingIds);
                }

                if ($propertyDateMatches !== []) {
                    $query->orWhere(function ($matchQuery) use ($propertyDateMatches) {
                        $matchQuery->whereNull('booking_id')->where(function ($dateQuery) use ($propertyDateMatches) {
                            foreach ($propertyDateMatches as $match) {
                                $dateQuery->orWhere(function ($inner) use ($match) {
                                    $inner->where('property_id', $match['property_id'])
                                        ->whereDate('scheduled_date', $match['date']);
                                });
                            }
                        });
                    });
                }
            })
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->with('housekeeper:id,name')
            ->get();

        $cleanerNames = [];
        foreach ($sessions as $session) {
            if ($session->booking_id) {
                $cleanerNames[$session->booking_id] = $session->housekeeper?->name;
                continue;
            }

            foreach ($propertyDateMatches as $bookingId => $match) {
                if (isset($cleanerNames[$bookingId])) {
                    continue;
                }

                if ((int) $session->property_id === (int) $match['property_id']
                    && $session->scheduled_date
                    && \Illuminate\Support\Carbon::parse($session->scheduled_date)->toDateString() === $match['date']) {
                    $cleanerNames[$bookingId] = $session->housekeeper?->name;
                    break;
                }
            }
        }

        return $cleanerNames;
    }

    private function attachLinkedCleanersToBookings($bookings): void
    {
        if ($bookings instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $bookings = $bookings->getCollection();
        }

        if ($bookings instanceof \Illuminate\Support\Collection && $bookings->isNotEmpty()) {
            $cleanerNames = $this->bookingCleanerNames($bookings);

            foreach ($bookings as $booking) {
                $booking->setRelation('linkedCleanings', collect());
                $cleanerName = $cleanerNames[$booking->id] ?? null;
                if ($cleanerName) {
                    $booking->setRelation('linkedCleanings', collect([
                        (object) [
                            'scheduled_date' => $booking->check_out_date?->toDateString(),
                            'housekeeper' => (object) ['name' => $cleanerName],
                        ],
                    ]));
                }
            }
        }
    }

    private function validated(Request $request, ?Booking $booking = null): array
    {
        return $request->validate([
            'booking_id'     => ['nullable', 'string', 'max:255', 'unique:bookings,booking_id,'.($booking?->id ?? 'NULL')],
            'reservation_id' => ['required', 'string', 'max:255', 'unique:bookings,reservation_id,'.($booking?->id ?? 'NULL')],
            'booking_platform' => ['nullable', 'string', 'max:100'],
            'guest_name'     => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:255'],
            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'email'          => ['nullable', 'email', 'max:255'],
            'check_in_date'  => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after_or_equal:check_in_date'],
            'property_id'    => ['required', 'exists:properties,id', function ($attribute, $value, $fail) {
                if (! Property::visibleTo(auth()->user())->whereKey($value)->exists()) {
                    $fail('You do not have access to that property.');
                }
            }],
            'id_type'        => ['required', 'in:state_id,passport'],
            // Parking charges, incidentals_charge, early_checkin_tier, and
            // late-checkout billing fields are managed in the Guest Details
            // ledger editor. parking_needed itself is editable here.
            'parking_needed' => ['nullable', 'boolean'],
            'photo_id_received' => ['nullable', 'boolean'],
            'checkin_time_preference'  => ['nullable', 'date_format:H:i'],
            'checkout_time_preference' => ['nullable', 'date_format:H:i'],
            'status'         => ['required', 'in:pending,pre_checkin_complete,awaiting_deposit,guest_approved,currently_hosting,checked_out,cancelled'],
            'notes'          => ['nullable', 'string'],
            'housekeeper_id' => ['nullable', 'exists:users,id'],
        ]);
    }

    /**
     * Keep the admin-entered incidentals charge and the combined pre-check-in
     * subtotal within the property's cap, falling back to the global cap.
     * Processing fees are intentionally excluded because the cap represents
     * the charge subtotal and the fee is applied on top.
     */
    private function enforcePreCheckinCap(array $data): void
    {
        $property = Property::findOrFail($data['property_id']);
        $capCents = $property->deposit_cap_cents !== null
            ? (int) $property->deposit_cap_cents
            : (int) Setting::getValue('default_deposit_cap_cents', 0);

        if ($capCents <= 0) {
            return;
        }

        $holdField = array_key_exists('additional_deposit', $data) ? 'additional_deposit' : 'incidentals_charge';
        $holdAmount = (float) ($data['incidentals_charge'] ?? 0) + (float) ($data['additional_deposit'] ?? 0);
        $incidentalsCents = (int) round($holdAmount * 100);
        if ($incidentalsCents > $capCents) {
            throw ValidationException::withMessages([
                $holdField => ($holdField === 'additional_deposit' ? 'Additional deposit' : 'Incidentals').' cannot exceed the deposit threshold of $'.number_format($capCents / 100, 2).'.',
            ]);
        }

        $booking = new Booking($data);
        $booking->setRelation('property', $property);
        $booking->parking_needed = (bool) ($data['parking_needed'] ?? false);
        $booking->check_in_date = $data['check_in_date'];
        $booking->check_out_date = $data['check_out_date'];
        $parkingCharge = $booking->parking_charge_override !== null
            ? (float) $booking->parking_charge_override
            : ($booking->calculateParkingCharge() ?? 0);

        $subtotalCents = (int) round((
            $parkingCharge
            + $holdAmount
            + ($booking->earlyCheckinIsDeductedFromHold() ? 0 : ($booking->effectiveEarlyCheckinCharge() ?? 0))
        ) * 100);

        if ($subtotalCents > $capCents) {
            throw ValidationException::withMessages([
                $holdField => 'Additional deposit/incidentals, parking, and early check-in total cannot exceed the deposit threshold of $'.number_format($capCents / 100, 2).'.',
            ]);
        }
    }

    /**
     * A cancelled reservation (whether cancelled by the guest via the OTA
     * or manually by admin) is locked: no edits, approvals, or check-in
     * actions, whether from the UI or a direct request. The one exception
     * is reversing the cancellation itself via updateStatus().
     */
    private function guardNotCancelled(Booking $booking): void
    {
        abort_if($booking->isCancelled(), 403, 'This reservation is cancelled and is locked.');
    }
}

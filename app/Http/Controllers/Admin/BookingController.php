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

        $baseQuery = fn () => Booking::with('property')
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
        $today = Booking::with('property')
            ->notArchived()
            ->where(fn ($q) => $q
                ->whereDate('check_in_date', today())
                ->orWhereDate('check_out_date', today()))
            ->get()
            ->sortBy(fn ($b) => $b->weekCardSortTier())
            ->values();
        $todayTotal = $today->count();
        $todayIds = $today->pluck('id');
        $today = $today->take(5)->values();

        // "This Week" covers everything else in the operational window
        // (currently hosting, upcoming within 6 days) -- tiers 4-6 of the
        // same weekCardSortTier() scale, so the two sections use one
        // consistent source of truth for ordering. Guests who have already
        // checked out are deliberately excluded: they only appear in
        // "Today" on the day they check out, then are archived.
        $thisWeekAll = Booking::with('property')
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
        $thisWeek = $thisWeekAll->take(5)->values();

        // "Upcoming" card: all future stays after this week's window,
        // capped to an initial batch of 5 with a "Show More" AJAX endpoint
        // that loads 5 more at a time.
        $upcomingLimit = 5;
        $upcomingBaseQuery = fn () => Booking::with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', today()->addDays(6));

        $upcoming = ($upcomingBaseQuery)()->orderBy('check_in_date')->limit($upcomingLimit)->get();
        $upcomingTotal = ($upcomingBaseQuery)()->count();

        $thisWeekIds = $thisWeekAll->pluck('id')->merge($todayIds);
        $bookings = ($baseQuery)()
            ->when($thisWeekIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $thisWeekIds))
            ->when($showArchived && ! $hasSearch,
                fn ($q) => $q->orderByDesc('archived_at'),
                fn ($q) => $q->orderBy('check_in_date'))
            ->paginate(15)
            ->withQueryString();

        $properties = Property::orderBy('name')->get();

        $stats = [
            'total_guests'     => Booking::notArchived()->count(),
            'todays_arrivals'  => Booking::notArchived()->whereDate('check_in_date', today())->count(),
            'waiting_approval' => Booking::notArchived()->where('status', 'pre_checkin_complete')->whereNull('approved_at')->count(),
            'checked_in'       => Booking::notArchived()
                ->where(fn ($q) => $q->where('manually_checked_in', true)->orWhere('status', 'currently_hosting'))
                ->whereNull('checked_out_at')
                ->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'today', 'todayTotal', 'thisWeek', 'thisWeekTotal', 'upcoming', 'upcomingTotal', 'upcomingLimit', 'properties', 'showArchived', 'stats'));
    }

    public function todayMore(Request $request)
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(12, max(1, (int) $request->query('limit', 5)));

        // Must match index()'s Today ordering exactly (sorted by
        // weekCardSortTier() in PHP, not a DB-level orderBy) so "Show More"
        // continues in the same priority order as the initial page load
        // rather than falling back to plain check_in_date ordering.
        $bookings = Booking::with('property')
            ->notArchived()
            ->where(fn ($q) => $q->whereDate('check_in_date', today())->orWhereDate('check_out_date', today()))
            ->get()
            ->sortBy(fn ($b) => $b->weekCardSortTier())
            ->values();

        $total = $bookings->count();
        $batch = $bookings->slice($offset, $limit);

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

        $bookings = Booking::with('property')
            ->notArchived()
            ->whereNotIn('id', Booking::notArchived()
                ->where(fn ($q) => $q->whereDate('check_in_date', today())->orWhereDate('check_out_date', today()))
                ->pluck('id'))
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '<=', today()->addDays(6))
            ->get()
            ->sort(function ($a, $b) {
                $tierCompare = $a->weekCardSortTier() <=> $b->weekCardSortTier();

                return $tierCompare !== 0 ? $tierCompare : $a->check_in_date->timestamp <=> $b->check_in_date->timestamp;
            })
            ->values();

        $total = $bookings->count();
        $batch = $bookings->slice($offset, $limit);
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

        $results = Booking::with('property')
            ->where(fn ($q) => $q
                ->where('guest_name', 'like', "%{$search}%")
                ->orWhere('booking_id', $search)
                ->orWhere('reservation_id', $search)
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('check_in_date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn ($b) => [
                'id'       => $b->id,
                'name'     => $b->guest_name,
                'property' => $b->property?->name,
                'stay'     => $b->stayRangeLabel(),
                'status'   => $b->statusLabel(),
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

        $bookings = Booking::with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', today()->addDays(6))
            ->orderBy('check_in_date')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = Booking::notArchived()
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
            'properties'       => Property::where('active', true)->orderBy('name')->get(),
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
        $booking            = Booking::create($data);
        $booking->recalculateParkingCharge();

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

        return redirect()->route('admin.guests.show', $booking)->with('success', 'Guest booking created successfully.');
    }

    public function show(Booking $booking)
    {
        $booking->load('property');

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
        $properties = Property::where('active', true)->orderBy('name')->get();

        return view('admin.bookings.show', compact('booking', 'guestLogs', 'properties'));
    }

    public function preview(Booking $booking, string $state)
    {
        abort_unless(in_array($state, ['identity', 'waiting', 'arrival', 'guide', 'checkout'], true), 404);
        $booking->load(['property.categories', 'property.amenities']);

        ActivityLogService::admin('booking_previewed', auth()->user()->name." previewed guest page (state: {$state}) for {$booking->guest_name}.", 'guests', [
            'subject_type' => Booking::class,
            'subject_id'   => $booking->id,
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'metadata'     => ['preview_state' => $state],
        ]);

        $checkinSteps = \App\Models\InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'checkin')->where('active', true)
            ->where($booking->parking_needed ? fn($q) => $q->where('visibility', '!=', 'non_parkers_only') : fn($q) => $q->where('visibility', '!=', 'parkers_only'))
            ->orderBy('sort_order')->get()
            ->map(fn($s) => ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl()])
            ->values()->toArray();
        $parkingSteps = $booking->parking_needed ? \App\Models\InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'parking')->where('active', true)->orderBy('sort_order')->get()
            ->map(fn($s) => ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl()])
            ->values()->toArray() : [];
        $checkoutSteps = \App\Models\InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'checkout')->where('active', true)
            ->where($booking->parking_needed ? fn($q) => $q->where('visibility', '!=', 'non_parkers_only') : fn($q) => $q->where('visibility', '!=', 'parkers_only'))
            ->orderBy('sort_order')->get()
            ->map(fn($s) => ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl()])
            ->values()->toArray();

        return view('guest.show', [
            'booking'        => $booking,
            'property'       => $booking->property,
            'state'          => $state,
            'categories'     => $booking->property->categories->filter(fn ($c) => $c->active && $c->pivot->active)->values(),
            'locks'          => $this->resolveLocks($booking),
            'gpsRadius'      => (int) Setting::getValue('gps_radius_meters', 150),
            'previewMode'    => true,
            'welcomeMessage' => $booking->welcome_message ?: Setting::getValue('default_intro', 'We are glad to have you. Please complete the following details prior to check-in.'),
            'gpsVerifyMessage' => Setting::getValue('gps_verify_message', "It's Go Time!"),
            'checkinSteps'   => $checkinSteps,
            'parkingSteps'   => $parkingSteps,
            'checkoutSteps'  => $checkoutSteps,
            'checkinTimeOptions' => $this->checkinTimeOptions(),
            'checkoutTimeOptions' => $this->checkoutTimeOptions(),
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $this->guardNotCancelled($booking);

        $oldStatus = $booking->status;
        $data = $this->validated($request, $booking);
        $data['phone'] = PhoneFormatter::normalizeForStorage($data['phone'] ?? null, $request->input('phone_country_code', '+1'));
        $this->enforcePreCheckinCap(array_merge($booking->only(['incidentals_charge', 'parking_needed', 'early_checkin_tier', 'early_checkin_charge_override', 'early_checkin_billing_mode']), $data));
        $data['photo_id_received'] = $request->boolean('photo_id_received');
        if (($data['status'] ?? null) === 'pre_checkin_complete') {
            $data['photo_id_received'] = true;
        }
        if ($data['photo_id_received'] && empty($booking->approved_at) && empty($data['approved_at'])) {
            $data['approved_at'] = now();
        }
        foreach ([
            'checkin_time_preference' => 'checkin_time_status',
            'checkout_time_preference' => 'checkout_time_status',
        ] as $timeField => $statusField) {
            if (array_key_exists($timeField, $data) && $data[$timeField] !== $booking->{$timeField}) {
                $data[$statusField] = filled($data[$timeField]) ? 'approved' : null;
            }
        }
        $booking->update($data);
        $booking->recalculateParkingCharge();

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
            'early_checkin_tier'             => ['nullable', 'in:8am_12pm,12pm_2pm,2pm_4pm,8am,12pm'],
            'early_checkin_charge_override'  => ['nullable', 'numeric', 'min:0'],
            'early_checkin_billing_mode'     => ['nullable', 'in:charge,deduct_from_hold'],
            'late_checkout_type'             => ['nullable', 'in:authorized,unauthorized'],
            'late_checkout_hours'            => ['nullable', 'numeric', 'min:0'],
            'late_checkout_actual_time'      => ['nullable', 'date'],
            'late_checkout_charge_override'  => ['nullable', 'numeric', 'min:0'],
        ]);

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
        if ($booking->early_checkin_tier && $booking->early_checkin_tier !== $oldEarlyCheckinTier) {
            \App\Services\GuestAlertService::send('early_checkin_granted', $booking);
        }

        // Same idea for late checkout: only fire when it's newly marked
        // authorized (not on every ledger re-save), same guard shape as
        // early_checkin_tier above.
        if ($booking->late_checkout_type === 'authorized' && $oldLateCheckoutType !== 'authorized') {
            \App\Services\GuestAlertService::send('late_checkout_granted', $booking);
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

        $booking->delete();

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
        \App\Services\GuestAlertService::send('background_check_complete', $booking);

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
        ]);

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

        $booking->update($updates);

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

        \App\Services\GuestAlertService::send('checkin_ready', $booking);

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
     * time (task 0). Approving does not itself set a charge, admin still
     * fills in the task 26 billing fields (early_checkin_tier /
     * late_checkout_type etc.) as needed. The guest is notified of the
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

        $booking->update([
            $statusField => $data['decision'],
        ]);

        $booking->refresh();

        $requestedTime = $type === 'checkin'
            ? $booking->checkinTimePreferenceFormatted()
            : $booking->checkoutTimePreferenceFormatted();

        $event = $type.'_time_'.$data['decision'];

        \App\Services\GuestAlertService::send($event, $booking, [
            'requested_time' => $requestedTime,
        ]);

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
            'property_id'    => ['required', 'exists:properties,id'],
            'id_type'        => ['required', 'in:state_id,passport'],
            // parking_needed / parking_charge_override / incidentals_charge /
            // early_checkin_tier / late_checkout_type / late_checkout_hours /
            // late_checkout_actual_time intentionally removed: none of these
            // are submitted by the merged guest details editor on the show
            // page (task: "merge edit and view page into one clean UI" --
            // these charge-driving fields are excluded there since they're
            // already represented in the Guest Details card and get their
            // own editor with the ledger work). Leaving these keys out of
            // validated() entirely means store()/update() never include them
            // in $data, so existing values are left untouched rather than
            // nulled out when the form that used to submit them no longer
            // does.
            'photo_id_received' => ['nullable', 'boolean'],
            'checkin_time_preference'  => ['nullable', 'date_format:H:i'],
            'checkout_time_preference' => ['nullable', 'date_format:H:i'],
            'status'         => ['required', 'in:pending,pre_checkin_complete,awaiting_deposit,guest_approved,currently_hosting,checked_out,cancelled'],
            'notes'          => ['nullable', 'string'],
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

        $incidentalsCents = (int) round(((float) ($data['incidentals_charge'] ?? 0)) * 100);
        if ($incidentalsCents > $capCents) {
            throw ValidationException::withMessages([
                'incidentals_charge' => 'Incidentals cannot exceed the deposit threshold of $'.number_format($capCents / 100, 2).'.',
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
            + ((float) ($data['incidentals_charge'] ?? 0))
            + ($booking->earlyCheckinIsDeductedFromHold() ? 0 : ($booking->effectiveEarlyCheckinCharge() ?? 0))
        ) * 100);

        if ($subtotalCents > $capCents) {
            throw ValidationException::withMessages([
                'incidentals_charge' => 'Incidentals, parking, and early check-in total cannot exceed the deposit threshold of $'.number_format($capCents / 100, 2).'.',
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

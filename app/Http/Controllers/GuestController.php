<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Category;
use App\Models\PropertyLock;
use App\Models\InstructionStep;
use App\Models\CategoryPage;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Services\SeamService;
use App\Services\SmsConsentService;
use App\Services\SmsNotificationService;
use App\Services\RentalAgreementService;
use App\Services\IdDocumentExtractor;
use App\Support\PhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuestController extends Controller
{
    public function show(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        return $this->renderPortal($booking);
    }

    private function renderPortal(Booking $booking)
    {
        $booking->load(['property.categories', 'property.amenities', 'property.instructionSteps']);

        // Auto-close a stay the guest never manually checked out of, once
        // their checkout time has passed and the door is reported locked.
        $this->autoCheckoutIfDue($booking);

        $state = $this->state($booking);

        ActivityLogService::guest('portal_viewed', "Guest {$booking->guest_name} viewed the portal (state: {$state}).", 'guest_portal', [
            'booking_id'   => $booking->id,
            'property_id'  => $booking->property_id,
            'actor_name'   => $booking->guest_name,
            'actor_email'  => $booking->email,
            'metadata'     => ['state' => $state, 'booking_ref' => $booking->booking_id],
        ]);

        // Conditional notices (Admin > Guest Notices): which phase are we in,
        // and what pop-ups / wizard steps apply right now.
        $noticePhase = ! $booking->isMarkedCheckedIn()
            ? 'checkin'
            : ($booking->isCheckoutDay() ? 'checkout' : 'guide');

        $checkinSteps = ($state === 'guide' && ! $booking->instructionsCompleted()) ? $this->checkinSteps($booking) : [];
        if ($noticePhase === 'checkin') {
            $checkinSteps = collect($checkinSteps)->concat(\App\Services\GuestNoticeService::steps($booking, 'checkin'))->values()->all();
        } else {
            $checkinSteps = collect($checkinSteps)->values()->all();
        }

        $checkoutSteps = collect($this->checkoutSteps($booking));
        if ($noticePhase === 'checkout') {
            $checkoutSteps = $checkoutSteps->concat(\App\Services\GuestNoticeService::steps($booking, 'checkout'));
        }
        $checkoutSteps = $checkoutSteps->values()->all();

        return view('guest.show', [
            'booking'       => $booking,
            'property'      => $booking->property,
            'state'         => $state,
            'categories'    => $this->availableCategories($booking),
            'locks'         => $this->resolveLocks($booking),
            'gpsRadius'     => (int) Setting::getValue('gps_radius_meters', 150),
            'gpsVerifyMessage' => Setting::getValue('gps_verify_message', "It's Go Time!"),
            'backgroundCheckStepName' => Setting::getValue('background_check_step_name', 'Background Check'),
            'backgroundCheckStepInstructions' => Setting::getValue('background_check_step_instructions', 'Please be on the lookout for an email from Airbnb so that you can submit the required hold for incidentals. This hold is refunded after checkout.'),
            'checkinSteps'  => $checkinSteps,
            'checkoutSteps' => $checkoutSteps,
            'parkingSteps'  => ($state === 'guide' && ! $booking->instructionsCompleted()) ? $this->parkingSteps($booking) : [],
            'guestNoticePopups' => \App\Services\GuestNoticeService::popups($booking, $noticePhase),
            'checkinTimeOptions' => $this->checkinTimeOptions(),
            'checkoutTimeOptions' => $this->checkoutTimeOptions(),
            // task: vehicle info is never required at Step 1 -- prompt for
            // it later in-flow instead, once it's actually needed (~1 day
            // out, or later in-flow for same-day bookings).
            'needsVehicleInfoPrompt' => $booking->needsVehicleInfoPrompt(),
        ]);
    }

    public function checkinByReservation(Request $request)
    {
        $rid = $request->query('RID');
        abort_unless($rid, 404);

        $booking = Booking::where('reservation_id', $rid)->firstOrFail();

        return $this->renderPortal($booking);
    }

    public function verifyReservationLogin(Request $request)
    {
        $rid = $request->input('RID');
        $booking = Booking::where('reservation_id', $rid)->firstOrFail();

        $data = $request->validate([
            'phone' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $emailMatch = strtolower(trim($data['email'])) === strtolower(trim($booking->email));
        $phoneMatch = PhoneFormatter::normalizeForStorage($data['phone']) === PhoneFormatter::normalizeForStorage($booking->phone);

        if (! $emailMatch || ! $phoneMatch) {
            ActivityLogService::security('guest_login_failed', "Failed RID login attempt for reservation: {$rid}.", [
                'actor_type' => 'guest',
                'severity'   => 'warning',
                'metadata'   => ['reservation_id' => $rid],
            ]);
            return back()->withInput()->with('error', 'Phone and email do not match our records.');
        }

        $booking->update(['guest_authenticated_at' => now()]);
        \App\Services\GuestSessionService::refreshCookie($booking);

        ActivityLogService::guest('guest_login_verified', "Guest {$booking->guest_name} logged in via reservation ID.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
        ]);

        return redirect()->route('checkin.rid', ['RID' => $rid]);
    }

    public function login(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        // Effective parking answer after this request: the newly-submitted
        // value if present, otherwise whatever's already on the booking.
        // Vehicle info is only required once parking is confirmed "yes" —
        // task 34.
        $parkingAnswer = $request->filled('parking_needed')
            ? filter_var($request->input('parking_needed'), FILTER_VALIDATE_BOOLEAN)
            : $booking->parking_needed;

        // Terms/Privacy acceptance and SMS consent are collected at Step 1
        // (login). Rental contract signing is NOT — it requires the ID to
        // already be uploaded and scanned so the typed name can be checked
        // against it, so it's handled later by signRentalAgreement(), once
        // ID capture (Step 2) has run.
        $requiresTermsAcceptance = ! $booking->terms_accepted_at;

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:255'],
            'parking_needed' => [is_null($booking->parking_needed) ? 'required' : 'nullable'],
            'checkin_time_preference' => ['required', 'string'],
            'checkout_time_preference' => ['nullable', 'string'],
            // Never required at Step 1 -- task: don't block the initial guest
            // flow on vehicle info. Actually prompted later, in the waiting
            // area, via Booking::needsVehicleInfoPrompt() (~1 day before
            // arrival, or later in-flow for same-day bookings).
            'vehicle_make_model' => ['nullable', 'string', 'max:255'],
            'license_plate_photo' => ['nullable', 'image', 'max:8192'],
            'terms_accepted' => [$requiresTermsAcceptance ? 'accepted' : 'nullable'],
            'sms_consent' => ['nullable', 'boolean'],
        ]);

        $newCheckinPreference = $data['checkin_time_preference'];
        $newCheckoutPreference = $data['checkout_time_preference'] ?? null;

        $updates = [
            'guest_name' => $data['guest_name'],
            'phone' => PhoneFormatter::normalizeForStorage($data['phone'], $data['phone_country_code'] ?? '+1'),
            'email' => $data['email'],
            'parking_needed' => array_key_exists('parking_needed', $data) && $data['parking_needed'] !== null
                ? filter_var($data['parking_needed'], FILTER_VALIDATE_BOOLEAN)
                : $booking->parking_needed,
            'checkin_time_preference' => $newCheckinPreference,
            'checkout_time_preference' => $newCheckoutPreference,
            'guest_authenticated_at' => now(),
        ];

        // Only early check-in and late checkout require approval. A later
        // check-in or earlier checkout is within the property's normal
        // operating window and can be accepted without admin review.
        if ($newCheckinPreference !== $booking->checkin_time_preference) {
            $updates['checkin_time_status'] = $booking->requiresCheckinTimeApproval($newCheckinPreference)
                ? 'pending'
                : null;
        }

        if ($newCheckoutPreference !== $booking->checkout_time_preference) {
            $updates['checkout_time_status'] = $booking->requiresCheckoutTimeApproval($newCheckoutPreference)
                ? 'pending'
                : null;
        }

        if ($parkingAnswer) {
            $updates['vehicle_make_model'] = $data['vehicle_make_model'] ?? $booking->vehicle_make_model;
        }

        if ($request->hasFile('license_plate_photo')) {
            $updates['license_plate_photo_path'] = $request->file('license_plate_photo')->store('license-plates');
        }

        if ($requiresTermsAcceptance) {
            $updates['terms_accepted_at'] = now();
            $updates['terms_accepted_version'] = \App\Models\Setting::getValue('terms_version', '1');
        }

        $booking->update($updates);

        if ($request->boolean('sms_consent')) {
            SmsConsentService::recordOptIn($booking, $booking->phone, [
                'disclosure_text' => Setting::getValue('legal_sms_consent_content', ''),
                'disclosure_version' => Setting::getValue('sms_consent_version', '1'),
                'terms_version' => Setting::getValue('terms_version', '1'),
                'privacy_version' => Setting::getValue('privacy_policy_version', '1'),
                'page_url' => url()->current(),
                'opt_in_method' => 'guest_portal',
            ]);

            $hostName = $booking->property?->name ?? 'Guest Hub';
            SmsNotificationService::guestAlert(
                $booking->phone,
                'Guest Hub Guest Alerts: You are subscribed to non-marketing guest messages for '.$hostName.'. Msg frequency varies, up to 20/month. Msg & data rates may apply. Reply HELP for help or STOP to cancel.'
            );
        }

        $booking->recalculateParkingCharge();

        \App\Services\GuestSessionService::refreshCookie($booking);

        ActivityLogService::guest('guest_login_verified', "Guest {$booking->guest_name} completed login step.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
        ]);

        return response()->json(['ok' => true]);
    }

    public function verifyLogin(string $bookingId, string $token, \Illuminate\Http\Request $request)
    {
        $booking = Booking::where('booking_id', $bookingId)->first();
        if (! $booking) {
            abort(404);
        }
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);
        $emailMatch = strtolower(trim($data['email'])) === strtolower(trim($booking->email));
        $phoneMatch = PhoneFormatter::normalizeForStorage($data['phone']) === PhoneFormatter::normalizeForStorage($booking->phone);
        if (! $emailMatch || ! $phoneMatch) {
            ActivityLogService::security('guest_login_failed', "Failed guest login attempt for booking: {$bookingId}.", [
                'actor_type' => 'guest',
                'severity'   => 'warning',
                'metadata'   => ['booking_id' => $bookingId],
            ]);
            return back()->withInput()->with('error', 'Phone and email do not match our records.');
        }
        \App\Services\GuestSessionService::refreshCookie($booking);
        ActivityLogService::guest('guest_login_verified', "Guest {$booking->guest_name} verified login.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
        ]);
        return redirect()->route('guest.show', [$bookingId, $token]);
    }
    public function confirmCheckin(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        if (! $booking->isMarkedCheckedIn()) {
            $booking->update([
                'status'        => 'currently_hosting',
                'checked_in_at' => now(),
            ]);
            \App\Services\GuestAlertService::send('checkin_completed', $booking);
            ActivityLogService::guest('guest_confirmed_checkin', "Guest {$booking->guest_name} confirmed check-in.", 'check', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'actor_email' => $booking->email,
                'severity'    => 'success',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function confirmCheckout(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        $this->completeCheckout($booking);

        return response()->json(['ok' => true]);
    }

    /**
     * Mark a booking checked out exactly once, firing the checkout alert and
     * activity log. Shared by the guest's manual "I'm checked out" action and
     * the automatic checkout that runs when the guest locks the door on
     * checkout day / their checkout time has passed.
     */
    private function completeCheckout(Booking $booking, string $source = 'guest_confirmed_checkout'): void
    {
        $booking->completeCheckout($source);
    }

    /**
     * Safety net for the "guests never press I'm checked out" problem: once a
     * checked-in guest is past their effective checkout time on checkout day
     * AND the door is reported locked, close the stay automatically instead of
     * waiting for a press that never comes. Run on every portal render and by
     * the bookings:auto-checkout scheduled command.
     */
    public function autoCheckoutIfDue(Booking $booking): bool
    {
        if ($booking->checked_out_at || ! $booking->isMarkedCheckedIn() || $booking->isCancelled()) {
            return false;
        }

        // isPastCheckoutTime() already respects the property's timezone and
        // returns false before checkout day, so it covers "checkout day and
        // past the deadline" without a separate UTC-vs-local day comparison.
        if (! $booking->isPastCheckoutTime()) {
            return false;
        }

        $locks = $booking->property->locks;

        // If the property has locks, require the door to actually be locked
        // (don't boot a guest who's still inside). Properties without a lock
        // fall back to time alone.
        if ($locks->isNotEmpty() && ! $locks->contains(fn ($lock) => $lock->last_known_locked === true)) {
            return false;
        }

        $this->completeCheckout($booking, 'guest_auto_checkout');

        return true;
    }

    public function createDepositIntent(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $service = app(\App\Services\Payments\PaymentService::class);

        if (! $service->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Payments are not available right now. Please contact us.'], 503);
        }

        if ($booking->isDepositCaptured() || $booking->deposit_verified_at) {
            return response()->json(['ok' => false, 'error' => 'Deposit already paid.'], 422);
        }

        $amountCents = $booking->calculatePreCheckinChargeCents();

        if ($amountCents <= 0) {
            return response()->json(['ok' => false, 'error' => 'No deposit is configured for this stay.'], 422);
        }

        // Avoid creating a duplicate PaymentIntent on page reload/refresh —
        // reuse the existing pending one if there is one, re-fetching its
        // client_secret from Stripe so the guest can still complete payment.
        $existing = $booking->charges()->where('type', \App\Models\Charge::TYPE_DEPOSIT)->where('status', \App\Models\Charge::STATUS_PENDING)->latest()->first();
        if ($existing) {
            return response()->json([
                'ok' => true,
                'client_secret' => $service->retrieveClientSecret($existing->stripe_payment_intent_id),
                'publishable_key' => config('services.stripe.key'),
                'amount_cents' => $existing->amount_cents,
            ]);
        }

        $result = $service->createPendingIntent(
            $booking,
            \App\Models\Charge::TYPE_DEPOSIT,
            $amountCents,
            'precheckin_approval',
            $booking->preCheckinChargeBreakdown()
        );

        return response()->json([
            'ok' => true,
            'client_secret' => $result['client_secret'],
            'publishable_key' => config('services.stripe.key'),
            'amount_cents' => $amountCents,
        ]);
    }

    public function confirmDepositPayment(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $request->validate(['payment_intent_id' => ['required', 'string']]);

        $charge = app(\App\Services\Payments\PaymentService::class)->finalize($request->input('payment_intent_id'));

        if (! $charge || $charge->booking_id !== $booking->id) {
            return response()->json(['ok' => false, 'error' => 'Payment not found.'], 404);
        }

        if ($charge->status !== \App\Models\Charge::STATUS_SUCCESS) {
            return response()->json(['ok' => false, 'error' => 'Payment was not successful. Please try again.'], 422);
        }

        ActivityLogService::guest('deposit_paid_online', "Guest {$booking->guest_name} paid the incidentals deposit online.", 'check', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
            'metadata'    => ['amount_cents' => $charge->amount_cents, 'charge_id' => $charge->id],
        ]);

        // A successful card payment is verified immediately so the guest
        // advances to the next step without waiting for an admin to mark the
        // deposit received. The background check is already complete by the
        // time a guest reaches this screen.
        if ($booking->isBackgroundCheckComplete() && ! $booking->isDepositVerified()) {
            $booking->update([
                'deposit_verified_at' => now(),
                'status'              => 'guest_approved',
            ]);
        }

        \App\Services\GuestAlertService::send('deposit_paid', $booking);

        return response()->json(['ok' => true, 'message' => 'Deposit payment of $'.number_format($charge->amountDollars(), 2).' received.']);
    }

    /**
     * The guest chose to pay the incidentals hold on their booking platform.
     * No webhook confirms an off-platform payment, so we just record the
     * choice: the portal then shows "pending approval" (never the amount) and
     * keeps them there on every revisit until the admin verifies the deposit.
     */
    public function selectPlatformPayment(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        if (! $booking->platformPaymentSelected()) {
            $booking->update(['platform_payment_selected_at' => now()]);

            ActivityLogService::guest('platform_payment_selected', "Guest {$booking->guest_name} chose to pay the incidentals hold on {$booking->platformLabel()}.", 'check', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'actor_email' => $booking->email,
                'severity'    => 'success',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Generic charge-intent creation, reusable for any charge type beyond
     * the deposit (parking, early check-in, the portion of late
     * checkout/incidentals not covered by the deposit). $type must be one
     * of Charge::TYPE_* and have a known, positive amount already computed
     * on the booking — this endpoint never accepts an arbitrary
     * client-supplied amount.
     */
    public function createChargeIntent(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $service = app(\App\Services\Payments\PaymentService::class);

        if (! $service->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Payments are not available right now. Please contact us.'], 503);
        }

        $type = $request->validate(['type' => ['required', 'string', 'in:parking,early_checkin,late_checkout,incidentals']])['type'];

        $subtotalCents = match ($type) {
            // Parking/early check-in/incidentals all use the *unbilled*
            // remainder — if it was already paid as part of the combined
            // pre-checkin (deposit) charge, this is $0 and nothing is
            // charged again. Late checkout has no combined-charge
            // counterpart, so it always uses the full current amount.
            \App\Models\Charge::TYPE_PARKING => $booking->unbilledParkingCents(),
            \App\Models\Charge::TYPE_EARLY_CHECKIN => $booking->unbilledEarlyCheckinCents(),
            \App\Models\Charge::TYPE_LATE_CHECKOUT => (int) round(($booking->lateCheckoutCharge() ?? 0) * 100),
            \App\Models\Charge::TYPE_INCIDENTALS => $booking->unbilledIncidentalsCents(),
            default => 0,
        };

        if ($subtotalCents <= 0) {
            return response()->json(['ok' => false, 'error' => 'Nothing is currently due for this.'], 422);
        }

        // Same % processing fee as the combined pre-checkin charge, applied
        // to this individual charge's subtotal so the fee is consistent
        // whether the guest pays grouped or one item at a time.
        $amountCents = $booking->applyProcessingFeeCents($subtotalCents);

        // Avoid creating a duplicate outstanding intent for the same type —
        // reuse the existing pending one if there is one already.
        $existing = $booking->charges()->where('type', $type)->where('status', \App\Models\Charge::STATUS_PENDING)->latest()->first();
        if ($existing) {
            $clientSecret = $service->retrieveClientSecret($existing->stripe_payment_intent_id);

            return response()->json([
                'ok' => true,
                'client_secret' => $clientSecret,
                'publishable_key' => config('services.stripe.key'),
                'existing_charge_id' => $existing->id,
                'amount_cents' => $existing->amount_cents,
            ]);
        }

        $result = $service->createPendingIntent($booking, $type, $amountCents, 'guest_initiated');

        return response()->json([
            'ok' => true,
            'client_secret' => $result['client_secret'],
            'publishable_key' => config('services.stripe.key'),
            'amount_cents' => $amountCents,
        ]);
    }

    public function confirmChargePayment(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $request->validate(['payment_intent_id' => ['required', 'string']]);

        $charge = app(\App\Services\Payments\PaymentService::class)->finalize($request->input('payment_intent_id'));

        if (! $charge || $charge->booking_id !== $booking->id) {
            return response()->json(['ok' => false, 'error' => 'Payment not found.'], 404);
        }

        if ($charge->status !== \App\Models\Charge::STATUS_SUCCESS) {
            return response()->json(['ok' => false, 'error' => 'Payment was not successful. Please try again.'], 422);
        }

        ActivityLogService::guest('charge_paid_online', "Guest {$booking->guest_name} paid a {$charge->type} charge online.", 'check', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
            'metadata'    => ['type' => $charge->type, 'amount_cents' => $charge->amount_cents, 'charge_id' => $charge->id],
        ]);

        return response()->json([
            'ok' => true,
            'message' => ucfirst(str_replace('_', ' ', $charge->type)).' payment of $'.number_format($charge->amountDollars(), 2).' received.',
        ]);
    }

    public function submitIdentity(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        // Each side is required only if that specific side is missing (never uploaded,
        // or cleared out by an admin decline) and the booking isn't already marked
        // photo_id_received (e.g. captured outside the guest flow) — a decline on one
        // side should never force re-upload of an already-approved other side.
        $frontRequired = ! $booking->photo_id_received && blank($booking->photo_id_path);
        $backRequired = ! $booking->photo_id_received && blank($booking->photo_id_back_path) && $booking->id_type !== 'passport';

        $data = $request->validate([
            'photo_id' => [$frontRequired ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'photo_id_back' => [$backRequired ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $advancedStatuses = ['guest_approved', 'awaiting_deposit', 'currently_hosting', 'checked_out'];
        $updates = [
            'status'       => in_array($booking->status, $advancedStatuses, true) ? $booking->status : 'pre_checkin_complete',
            'identity_confirmed_at' => now(),
            'decline_reason' => null,
            'photo_id_received' => true,
        ];



        $archiveFolder = 'photo-ids-archive/'.$booking->booking_id.'-'.\Illuminate\Support\Str::slug($booking->guest_name);

        if ($request->hasFile('photo_id')) {
            if ($booking->photo_id_path && \Storage::disk('local')->exists($booking->photo_id_path)) {
                \Storage::disk('local')->move(
                    $booking->photo_id_path,
                    $archiveFolder.'/'.now()->format('Ymd-His').'-front-'.basename($booking->photo_id_path)
                );
            }
            $updates['photo_id_path'] = $request->file('photo_id')->store('photo-ids');
            $updates['photo_id_front_declined_reason'] = null;
        }
        if ($request->hasFile('photo_id_back')) {
            if ($booking->photo_id_back_path && \Storage::disk('local')->exists($booking->photo_id_back_path)) {
                \Storage::disk('local')->move(
                    $booking->photo_id_back_path,
                    $archiveFolder.'/'.now()->format('Ymd-His').'-back-'.basename($booking->photo_id_back_path)
                );
            }
            $updates['photo_id_back_path'] = $request->file('photo_id_back')->store('photo-ids');
            $updates['photo_id_back_declined_reason'] = null;
        }

        // Durable "registration completed" flag so a rejected/re-uploaded ID
        // (which resets status to pending) never re-sends the registration
        // alert — only one registration notification per guest.
        $wasRegistrationNotified = filled($booking->registration_notified_at);

        $booking->update($updates);

        // Scan the front of the ID (the side names/DOB/expiry are always
        // printed on, for both passports and licenses) and record whether
        // it matches what the guest typed, is expired, or needs manual
        // review. This gates the rental agreement signature — see
        // signRentalAgreement() — the guest can no longer sign before the
        // ID they uploaded has actually been checked.
        if (isset($updates['photo_id_path'])) {
            $this->scanUploadedId($booking, $updates['photo_id_path']);
        }

        $frontUploaded = $request->hasFile('photo_id') && $booking->photo_id_path;
        $backUploaded = $request->hasFile('photo_id_back') && $booking->photo_id_back_path;

        if (! $wasRegistrationNotified) {
            \App\Services\GuestAlertService::send('registration_received', $booking);
            $booking->update(['registration_notified_at' => now()]);
        } elseif ($frontUploaded || $backUploaded) {
            \App\Services\GuestAlertService::send('photo_id_uploaded', $booking);
        }

        ActivityLogService::guest('photo_id_uploaded', "Guest {$booking->guest_name} submitted photo ID and pre-arrival details.", 'photo_id', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
            'metadata'    => ['email' => $booking->email, 'booking_ref' => $booking->booking_id],
        ]);

        $booking->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id_scan' => [
                    'status' => $booking->id_scan_status,
                    'passed' => $booking->idScanPassed(),
                    'blocking_reason' => $booking->idScanBlockingReason(),
                    'name' => $booking->id_name,
                ],
            ]);
        }

        return back()
            ->with('success', 'All complete. Your arrival information has been received securely.');
    }

    /**
     * Runs OCR on the just-uploaded front ID photo and records the
     * extracted name/DOB/expiry plus a scan status:
     *   - expired          → id_expiry_date is in the past. Instant reject.
     *   - name_mismatch     → extracted name clearly doesn't match the
     *                        guest's typed name. Instant reject.
     *   - matched          → extracted name matches, ID not expired.
     *   - manual_review    → OCR couldn't confidently read a needed field
     *                        (bad photo, unfamiliar layout, etc.) — never
     *                        auto-rejected on our own low confidence, an
     *                        admin resolves it instead.
     */
    private function scanUploadedId(Booking $booking, string $storagePath): void
    {
        $result = app(IdDocumentExtractor::class)->extract($storagePath);

        $status = 'manual_review';

        if ($result->isExpired()) {
            $status = 'expired';
        } elseif ($result->hasUsableName()) {
            $status = $this->normalizePersonName($result->name) === $this->normalizePersonName($booking->guest_name)
                ? 'matched'
                : 'name_mismatch';
        }

        $booking->update([
            'id_name' => $result->name,
            'id_date_of_birth' => $result->dateOfBirth,
            'id_age' => $result->age(),
            'id_expiry_date' => $result->expiryDate,
            'id_scan_status' => $status,
            'id_scanned_at' => now(),
        ]);

        ActivityLogService::guest('id_scanned', "ID scan for {$booking->guest_name} completed with status: {$status}.", 'photo_id', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'severity'    => in_array($status, ['expired', 'name_mismatch'], true) ? 'warning' : 'info',
            'metadata'    => ['id_scan_status' => $status, 'extracted_name' => $result->name],
        ]);
    }

    /**
     * Signs the rental agreement. Split out from login() (Step 1) because
     * the typed name can only be checked against the government ID once
     * the ID has actually been uploaded and scanned (Step 2) — signing is
     * blocked entirely until id_scan_status is 'matched' or
     * 'manual_review' (an admin will resolve 'manual_review' cases; the
     * guest isn't blocked by our own OCR limitations, only by a genuine
     * expired ID or name mismatch).
     */
    public function signRentalAgreement(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        if ($booking->isCancelled()) {
            abort(403, 'This booking has been cancelled.');
        }

        if ($booking->contract_accepted_at) {
            return $request->expectsJson() ? response()->json(['ok' => true]) : back();
        }

        if (! $booking->photo_id_received || blank($booking->id_scan_status)) {
            throw ValidationException::withMessages([
                'contract_signed_name' => 'Please upload your government ID before signing the rental agreement.',
            ]);
        }

        if (! $booking->idScanPassed()) {
            throw ValidationException::withMessages([
                'contract_signed_name' => $booking->idScanBlockingReason() ?? 'We were unable to verify your ID. Please contact us for help.',
            ]);
        }

        $data = $request->validate([
            'contract_signed_name' => ['required', 'string', 'max:255'],
            'contract_signed_device_id' => ['nullable', 'string', 'max:64'],
        ]);

        // Prefer the name actually read off the ID as the source of truth
        // once we have one (manual_review cases may lack a usable
        // extracted name, so fall back to the typed guest_name then).
        $referenceName = $booking->id_name ?: $booking->guest_name;

        if ($this->normalizePersonName($data['contract_signed_name']) !== $this->normalizePersonName($referenceName)) {
            throw ValidationException::withMessages([
                'contract_signed_name' => 'This must match your name exactly as it appears on your government ID.',
            ]);
        }

        $booking->update([
            'contract_accepted_at' => now(),
            'contract_version' => Setting::getValue('legal_rental_contract_version', '1'),
            'contract_signed_name' => $data['contract_signed_name'],
            'contract_signed_ip' => $request->ip(),
            'contract_signed_user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'contract_signed_device_id' => $data['contract_signed_device_id'] ?? null,
        ]);

        ActivityLogService::guest('rental_agreement_signed', "Guest {$booking->guest_name} signed the rental agreement.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'severity'    => 'success',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Rental agreement signed.');
    }

    public function submitVehicleInfo(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        $upload = $request->file('license_plate_photo');
        if (! $upload || ! $upload->isValid()) {
            throw ValidationException::withMessages([
                'license_plate_photo' => match ($upload?->getError()) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The license plate photo is too large for this upload. Please choose a smaller image.',
                    UPLOAD_ERR_PARTIAL => 'The license plate photo upload was interrupted. Please try again.',
                    UPLOAD_ERR_NO_FILE => 'Please select a license plate photo.',
                    default => 'The license plate photo could not be uploaded. Please try again.',
                },
            ]);
        }

        $request->validate([
            'license_plate_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
        ]);

        $updates = [];

        $storedPath = $upload->store('license-plates');
        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'license_plate_photo' => 'The license plate photo could not be saved. Please try again.',
            ]);
        }
        $updates['license_plate_photo_path'] = $storedPath;

        $booking->update($updates);

        ActivityLogService::guest('vehicle_info_submitted', "Guest {$booking->guest_name} submitted vehicle information.", 'vehicle_info', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
            'metadata'    => ['email' => $booking->email, 'booking_ref' => $booking->booking_id],
        ]);

        return back()->with('success', 'Thanks — your vehicle information has been received.');
    }

    public function parking(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $data    = $request->validate(['parking_needed' => ['required', 'boolean']]);
        $booking->update($data);
        $booking->recalculateParkingCharge();

        ActivityLogService::guest('parking_answered', "Guest {$booking->guest_name} answered parking question: ".($data['parking_needed'] ? 'Yes' : 'No').".", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'metadata'    => ['parking_needed' => $data['parking_needed']],
        ]);

        return back()->with('success', 'Parking preference saved.');
    }

    /**
     * Records that the guest read and accepted the arrival-day disclaimer, so
     * the address/arrival details are revealed and it doesn't show again.
     */
    public function agreeToArrival(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        $booking->update(['checkin_disclaimer_agreed_at' => now()]);

        ActivityLogService::guest('arrival_disclaimer_agreed', "Guest {$booking->guest_name} agreed to the arrival instructions disclaimer.", 'check', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
        ]);

        return back();
    }

    public function rentalAgreement(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        abort_unless(filled($booking->contract_accepted_at), 404);

        return view('agreements.rental-agreement', array_merge(
            app(RentalAgreementService::class)->viewData($booking),
            ['pdfMode' => false]
        ));
    }

    public function rentalAgreementPdf(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        abort_unless(filled($booking->contract_accepted_at), 404);

        $service = app(RentalAgreementService::class);

        // No PDF engine installed yet -- fall back to the printable page (the
        // guest can still use the browser's "Save as PDF").
        if (! $service->isPdfAvailable()) {
            return redirect()->route('guest.rental-agreement', [$booking->booking_id, $booking->token]);
        }

        return response($service->renderPdf($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rental-agreement-'.$booking->booking_id.'.pdf"',
        ]);
    }

    public function verifyGps(Request $request, string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        $data    = $request->validate([
            'latitude'  => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $property = $booking->property;
        abort_if(! $property->latitude || ! $property->longitude, 422, 'Property GPS coordinates are not configured.');

        $distance = $this->distanceMeters(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $property->latitude,
            (float) $property->longitude
        );
        $radius = (int) Setting::getValue('gps_radius_meters', 150);

        // Browser-reported GPS accuracy is a margin of error, not a guarantee.
        // A guest genuinely on-site can still get a low-precision fix (e.g. indoors),
        // so extend the effective radius by the reported accuracy, capped so a wildly
        // inaccurate/spoofed reading can't be used to pass verification from far away.
        $maxAccuracyBonus = (int) Setting::getValue('gps_accuracy_bonus_cap_meters', 100);
        $accuracy          = isset($data['accuracy']) ? (float) $data['accuracy'] : 0.0;
        $accuracyBonus     = min(max($accuracy, 0), $maxAccuracyBonus);
        $effectiveRadius   = $radius + $accuracyBonus;

        if ($distance > $effectiveRadius) {
            ActivityLogService::guest('gps_failed', "Guest {$booking->guest_name} GPS verification failed (distance: ".round($distance)."m, radius: {$radius}m, accuracy: ".round($accuracy)."m, effective radius: ".round($effectiveRadius)."m).", 'gps', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'actor_email' => $booking->email,
                'severity'    => 'warning',
                'metadata'    => [
                    'submitted_lat'    => $data['latitude'],
                    'submitted_lon'    => $data['longitude'],
                    'distance_meters'  => round($distance),
                    'radius_meters'    => $radius,
                    'accuracy_meters'  => round($accuracy),
                    'effective_radius' => round($effectiveRadius),
                ],
            ]);

            return response()->json([
                'ok'       => false,
                'message'  => 'Your location appears to be outside the verification radius. Please contact guest services for manual approval.',
                'distance' => round($distance),
            ], 422);
        }

        $booking->update([
            'gps_verified'  => true,
        ]);

        ActivityLogService::guest('gps_verified', "Guest {$booking->guest_name} GPS verified and checked in (distance: ".round($distance)."m, accuracy: ".round($accuracy)."m).", 'gps', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'severity'    => 'success',
            'metadata'    => [
                'submitted_lat'    => $data['latitude'],
                'submitted_lon'    => $data['longitude'],
                'distance_meters'  => round($distance),
                'radius_meters'    => $radius,
                'accuracy_meters'  => round($accuracy),
                'effective_radius' => round($effectiveRadius),
            ],
        ]);

        return response()->json(['ok' => true, 'message' => 'Location verified. Your check-in details are unlocked.']);
    }

    public function gpsStatus(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);
        return response()->json(['gps_verified' => (bool) $booking->gps_verified]);
    }
    public function idStatus(string $bookingId, string $token)
    {
        $booking = $this->booking($bookingId, $token);

        // Also gives the guest's browser a hook to auto-close the stay if the
        // door is locked and their checkout time passed while the page sat open.
        $this->autoCheckoutIfDue($booking);

        return response()->json([
            'id_approved' => (bool) ($booking->photo_id_received && $booking->isApproved()),
            'id_rejected' => $booking->hasPendingIdRejection(),
            'background_check_complete' => $booking->isBackgroundCheckComplete(),
            'deposit_verified' => $booking->isDepositVerified(),
            'checkin_approved' => $booking->isCheckinApproved(),
            'vehicle_info_bypassed' => (bool) $booking->vehicle_info_bypassed_at,
            'checked_out' => (bool) $booking->checked_out_at,
        ]);
    }
    public function category(string $bookingId, string $token, Category $category)
    {
        $booking = $this->booking($bookingId, $token);
        $booking->load(['property.categories', 'property.amenities']);
        $state = $this->state($booking);
        if (! in_array($state, ['checkout_notice', 'checkout_available', 'guide'], true)) {
            return redirect()->route('guest.show', [$booking->booking_id, $booking->token]);
        }

        $categories = $this->availableCategories($booking);
        abort_unless($categories->contains('id', $category->id), 404);
        $category = $categories->firstWhere('id', $category->id);

        $page = CategoryPage::where('property_id', $booking->property_id)
            ->where('category_id', $category->id)
            ->where('active', true)
            ->first()?->resolvedPage();
        $locks = $category->action === 'door_lock'
            ? $this->resolveLocks($booking)
            : collect();
        // TicketmasterService removed -- the client explicitly does not want
        // ticketed events shown (see project notes, Sept 2026). Local events
        // is intentionally empty pending a curated/CitySpark-based free
        // events replacement.
        $localEvents = collect();
        $eventsTotal = 0;
        $eventsHasMore = false;

        ActivityLogService::guest('category_viewed', "Guest {$booking->guest_name} viewed category: {$category->title}.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'actor_email' => $booking->email,
            'metadata'    => ['category' => $category->title, 'category_id' => $category->id],
        ]);

        return view('guest.category', compact('booking', 'category', 'page', 'categories', 'locks', 'localEvents', 'eventsTotal', 'eventsHasMore', 'state'));
    }

    public function moreEvents(Request $request, string $bookingId, string $token, Category $category)
    {
        $booking = $this->booking($bookingId, $token);
        $state = $this->state($booking);
        if (! in_array($state, ['checkout_notice', 'checkout_available', 'guide'], true)) {
            abort(403);
        }
        $categories = $this->availableCategories($booking);
        abort_unless($categories->contains('id', $category->id), 404);
        $category = $categories->firstWhere('id', $category->id);
        abort_unless($category->action === 'local_events', 404);

        $page = max(0, (int) $request->query('page', 0));

        // TicketmasterService removed -- see note in show(). No pagination
        // needed until a real events source replaces it.
        $eventsResult = ['events' => [], 'totalElements' => 0, 'hasMore' => false];

        return response()->json($eventsResult);
    }

    /**
     * Enforced on every unlock/lock attempt (not just once at arrival, per
     * the existing gps_verified flow). Closes the gap where a leaked link
     * could operate the door from anywhere, indefinitely: requires both an
     * active stay window and a live, in-range GPS reading submitted with
     * this specific request. Returns an error JsonResponse if blocked, or
     * null if the action may proceed.
     */
    private function assertLockActionAllowed(Request $request, Booking $booking): ?\Illuminate\Http\JsonResponse
    {
        $today = now()->toDateString();
        $checkIn = optional($booking->check_in_date)->toDateString();
        $checkOut = optional($booking->check_out_date)->toDateString();

        if (($checkIn && $today < $checkIn) || ($checkOut && $today > $checkOut)) {
            ActivityLogService::guest('door_action_blocked_stay_window', "Blocked door action for {$booking->guest_name}: outside active stay dates.", 'guest_portal', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'severity'    => 'warning',
            ]);
            return response()->json(['ok' => false, 'error' => 'Door access is only available during your stay dates.'], 403);
        }

        $property = $booking->property;
        if (! $property->latitude || ! $property->longitude) {
            // No coordinates configured for this property — can't
            // location-gate, fall back to the stay-window check alone
            // rather than blocking every guest due to missing admin setup.
            return null;
        }

        $data = $request->validate([
            'latitude'  => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $distance = $this->distanceMeters(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $property->latitude,
            (float) $property->longitude
        );
        $radius = (int) Setting::getValue('gps_radius_meters', 150);

        if ($distance > $radius) {
            ActivityLogService::guest('door_action_blocked_gps', "Blocked door action for {$booking->guest_name}: outside property radius (distance: ".round($distance)."m, radius: {$radius}m).", 'guest_portal', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'severity'    => 'warning',
                'metadata'    => ['distance_meters' => round($distance), 'radius_meters' => $radius],
            ]);
            return response()->json(['ok' => false, 'error' => 'You must be at the property to lock or unlock the door.'], 403);
        }

        return null;
    }

    public function unlockDoor(Request $request, string $bookingId, string $token, PropertyLock $lock)
    {
        $booking = $this->booking($bookingId, $token);
        abort_unless($lock->property_id === $booking->property_id, 404);

        if ($blocked = $this->assertLockActionAllowed($request, $booking)) {
            return $blocked;
        }
        try {
            $attempt = app(SeamService::class)->unlock($lock->seam_device_id);
        } catch (\Throwable $e) {
            ActivityLogService::guest('door_unlock_failed', "Guest {$booking->guest_name} failed to send unlock command for {$lock->label}: {$e->getMessage()}", 'guest_portal', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'severity'    => 'warning',
                'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id],
            ]);
            return response()->json(['ok' => false, 'error' => 'Could not reach the door. Please try again in a moment.'], 502);
        }
        if (! empty($attempt['action_attempt_id'])) {
            \Illuminate\Support\Facades\Cache::put('seam_attempt_lock:'.$attempt['action_attempt_id'], ['lock_id' => $lock->id, 'guest_name' => $booking->guest_name, 'booking_id' => $booking->id], now()->addMinutes(10));
        }
        ActivityLogService::guest('door_unlock_attempted', "Guest {$booking->guest_name} sent unlock command for {$lock->label}.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id, 'action_attempt_id' => $attempt['action_attempt_id'] ?? null],
        ]);
        return response()->json(['ok' => true, 'status' => 'pending', 'action_attempt_id' => $attempt['action_attempt_id'] ?? null]);
    }
    public function lockStatus(string $bookingId, string $token, PropertyLock $lock)
    {
        $booking = $this->booking($bookingId, $token);
        abort_unless($lock->property_id === $booking->property_id, 404);

        // Ask Seam for the live state rather than trusting the cached
        // last_known_locked, which only webhooks refresh. This is what makes
        // the confirmation poll accurate (and reliable for August locks whose
        // webhook reporting was lagging), and it persists what we learn so the
        // dashboard and auto-checkout see the same truth.
        $locked = $this->lockStatusFor($booking, $lock);

        if ($locked !== null && $lock->last_known_locked !== $locked) {
            $lock->update(['last_known_locked' => $locked, 'last_status_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'locked' => $locked,
            'updated_at' => optional($lock->last_status_at)->toIso8601String(),
        ]);
    }
    public function lockDoor(Request $request, string $bookingId, string $token, PropertyLock $lock)
    {
        $booking = $this->booking($bookingId, $token);
        abort_unless($lock->property_id === $booking->property_id, 404);

        if ($blocked = $this->assertLockActionAllowed($request, $booking)) {
            return $blocked;
        }
        try {
            $attempt = app(SeamService::class)->lock($lock->seam_device_id);
        } catch (\Throwable $e) {
            ActivityLogService::guest('door_lock_failed', "Guest {$booking->guest_name} failed to send lock command for {$lock->label}: {$e->getMessage()}", 'guest_portal', [
                'booking_id'  => $booking->id,
                'property_id' => $booking->property_id,
                'actor_name'  => $booking->guest_name,
                'severity'    => 'warning',
                'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id],
            ]);
            return response()->json(['ok' => false, 'error' => 'Could not reach the door. Please try again in a moment.'], 502);
        }
        if (! empty($attempt['action_attempt_id'])) {
            \Illuminate\Support\Facades\Cache::put('seam_attempt_lock:'.$attempt['action_attempt_id'], ['lock_id' => $lock->id, 'guest_name' => $booking->guest_name, 'booking_id' => $booking->id], now()->addMinutes(10));
        }
        ActivityLogService::guest('door_lock_attempted', "Guest {$booking->guest_name} sent lock command for {$lock->label}.", 'guest_portal', [
            'booking_id'  => $booking->id,
            'property_id' => $booking->property_id,
            'actor_name'  => $booking->guest_name,
            'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id, 'action_attempt_id' => $attempt['action_attempt_id'] ?? null],
        ]);
        return response()->json(['ok' => true, 'status' => 'pending', 'action_attempt_id' => $attempt['action_attempt_id'] ?? null]);
    }


    private array $lockStatusCache = [];

    private function resolveLocks(Booking $booking)
    {
        return $booking->property->locks->map(fn ($lock) => [
            'lock'   => $lock,
            'status' => $this->lockStatusFor($booking, $lock),
        ]);
    }

    private function lockStatusFor(Booking $booking, ?PropertyLock $lock = null): ?bool
    {
        $lock = $lock ?: $booking->property->locks()->first();
        if (! $lock) {
            return null;
        }
        if (array_key_exists($lock->id, $this->lockStatusCache)) {
            return $this->lockStatusCache[$lock->id];
        }

        // Tiny cache so the guest's 1-second confirmation poll doesn't hammer
        // the Seam API, while still being fresh enough to catch a lock action.
        $status = \Illuminate\Support\Facades\Cache::remember(
            'seam_lock_status:'.$lock->id,
            now()->addSeconds(2),
            function () use ($lock) {
                try {
                    return app(SeamService::class)->getLockStatus($lock->seam_device_id);
                } catch (\Throwable $e) {
                    return null;
                }
            }
        );

        return $this->lockStatusCache[$lock->id] = $status;
    }
    private function booking(string $bookingId, string $token): Booking
    {
        $booking = Booking::with('property')
            ->where('booking_id', $bookingId)
            ->where('token', $token)
            ->first();
        if ($booking) {
            \App\Services\GuestSessionService::refreshCookie($booking);
            return $booking;
        }
        $cookieToken = request()->cookie(\App\Services\GuestSessionService::COOKIE_NAME);
        $sessionBooking = \App\Services\GuestSessionService::resolve($cookieToken);
        if ($sessionBooking && $sessionBooking->booking_id === $bookingId) {
            return $sessionBooking;
        }
        $fallbackBooking = Booking::with('property')->where('booking_id', $bookingId)->first();
        if (! $fallbackBooking) {
            ActivityLogService::security('invalid_token_access', "Invalid guest token access attempt for booking: {$bookingId}.", [
                'actor_type' => 'guest',
                'severity'   => 'warning',
                'metadata'   => ['booking_id' => $bookingId],
            ]);
            abort(404);
        }
        ActivityLogService::security('invalid_token_access', "Token mismatch for booking: {$bookingId}, guest routed to login.", [
            'actor_type' => 'guest',
            'severity'   => 'warning',
            'metadata'   => ['booking_id' => $bookingId],
        ]);
        return $fallbackBooking;
    }

    private function state(Booking $booking): string
    {
        if ($booking->isCancelled()) {
            return 'cancelled';
        }

        if ($booking->access_blocked_at) {
            return 'access_blocked';
        }

        if (! $booking->isCheckedIn()) {

        if (! $booking->isIdentityComplete()) {
            return 'identity';
        }

        if (! $booking->photo_id_received) {
            return 'identity';
        }

        if ($booking->needsIdApproval()) {
            return 'identity';
        }

        if (! $booking->isBackgroundCheckComplete()) {
            return 'identity';
        }
        }

        if (in_array($booking->status, ['pre_checkin_complete', 'awaiting_deposit', 'deposit_paid'], true) && ! $booking->deposit_verified_at) {
            return 'awaiting_deposit';
        }

        if ($booking->status === 'checked_out') {
            return $booking->isPastCheckoutDay() ? 'post_checkout' : 'checkout_locked';
        }

        if ($booking->isPastCheckoutDay()) {
            return 'post_checkout';
        }

        // Blocked by default: hold the guest on a "unit isn't quite ready yet"
        // screen until the host marks the unit ready and approves check-in.
        // Guests already let in (manual check-in or a successful GPS verify)
        // skip this so nobody already inside gets locked back out.
        if (! $booking->isCheckinApproved() && ! $booking->isMarkedCheckedIn() && ! $booking->gps_verified) {
            return 'unit_not_ready';
        }

        if (! $booking->isCheckinDay() && $booking->needsVehicleInfoPrompt()) {
            return 'vehicle_info';
        }
        if (! $booking->isCheckinDay()) {
            return 'waiting';
        }

        if ($booking->needsVehicleInfoPrompt()) {
            return 'vehicle_info';
        }

        if (! $booking->gps_verified) {
            return 'arrival';
        }

        if (! $booking->isCheckedIn()) {
            return 'guide';
        }

        if ($booking->isCheckoutDay()) {
            return $booking->isPastCheckoutTime() ? 'checkout_locked' : 'checkout_available';
        }

        if ($booking->isCheckoutDayBeforeNoon()) {
            return 'checkout_notice';
        }

        return 'guide';
    }

    private function checkinSteps(Booking $booking): array
    {
        $primaryLock = $booking->property->locks()->first();

        return InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'checkin')
            ->where('active', true)
            ->where($booking->parking_needed ? fn($q) => $q->where('visibility', '!=', 'non_parkers_only') : fn($q) => $q->where('visibility', '!=', 'parkers_only'))
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(function ($s) use ($booking, $primaryLock) {
                $isLock = ($s->action ?? 'content') === 'door_lock';
                return ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl(), 'images' => $s->images->map(fn($img) => $img->imageUrl())->values()->toArray(), 'action' => $s->action ?? 'content', 'lock_status' => $isLock ? $this->lockStatusFor($booking, $primaryLock) : null, 'lock_id' => $isLock && $primaryLock ? $primaryLock->id : null];
            })
            ->values()
            ->toArray();
    }

    private function parkingSteps(Booking $booking): array
    {
        if (!$booking->parking_needed) return [];

        $primaryLock = $booking->property->locks()->first();

        return InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'parking')
            ->where('active', true)
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(function ($s) use ($booking, $primaryLock) {
                $isLock = ($s->action ?? 'content') === 'door_lock';
                return ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl(), 'images' => $s->images->map(fn($img) => $img->imageUrl())->values()->toArray(), 'action' => $s->action ?? 'content', 'lock_status' => $isLock ? $this->lockStatusFor($booking, $primaryLock) : null, 'lock_id' => $isLock && $primaryLock ? $primaryLock->id : null];
            })
            ->values()
            ->toArray();
    }

    private function checkoutSteps(Booking $booking): array
    {
        $primaryLock = $booking->property->locks()->first();

        return InstructionStep::where('property_id', $booking->property_id)
            ->where('type', 'checkout')
            ->where('active', true)
            ->where($booking->parking_needed ? fn($q) => $q->where('visibility', '!=', 'non_parkers_only') : fn($q) => $q->where('visibility', '!=', 'parkers_only'))
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(function ($s) use ($booking, $primaryLock) {
                $isLock = ($s->action ?? 'content') === 'door_lock';
                return ['title' => $s->title, 'content' => $s->renderContent($booking), 'image' => $s->imageUrl(), 'images' => $s->images->map(fn($img) => $img->imageUrl())->values()->toArray(), 'action' => $s->action ?? 'content', 'lock_status' => $isLock ? $this->lockStatusFor($booking, $primaryLock) : null, 'lock_id' => $isLock && $primaryLock ? $primaryLock->id : null];
            })
            ->values()
            ->toArray();
    }

    private function checkinTimeOptions(): array
    {
        $hours = array_merge(range(8, 23), [0]);


        $options = [];
        foreach ($hours as $hour) {
            $value = sprintf('%02d:00', $hour);
            $label = \Carbon\Carbon::createFromTime($hour, 0)->format('g:i A');
            $options[$value] = $label;
        }
        return $options;
    }

    private function checkoutTimeOptions(): array
    {
        $hours = range(7, 14); // 7:00 AM through 2:00 PM only


        $options = [];
        foreach ($hours as $hour) {
            $value = sprintf('%02d:00', $hour);
            $label = \Carbon\Carbon::createFromTime($hour, 0)->format('g:i A');
            $options[$value] = $label;
        }
        return $options;
    }
    private function availableCategories(Booking $booking)
    {
        return $booking->property->categories
            ->filter(fn ($c) => $c->active && $c->pivot->active)
            ->values();
    }

    /**
     * Normalize a person's name for comparison: trim, collapse internal
     * whitespace, and lowercase, so "James  Smith" and "james smith" match
     * (but "Jim" and "James" still don't).
     */
    private function normalizePersonName(?string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);
        $a     = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

<?php

namespace App\Models;

use App\Support\PhoneFormatter;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'reservation_id', 'source', 'booking_platform', 'channex_booking_id', 'guest_name', 'phone', 'email', 'check_in_date', 'check_out_date',
        'property_id', 'id_type', 'token', 'photo_id_path', 'photo_id_back_path', 'photo_id_received', 'parking_needed', 'early_checkin_tier', 'checkin_time_preference', 'checkout_time_preference', 'checkin_time_status', 'checkout_time_status', 'gps_verified', 'guest_authenticated_at', 'checkin_disclaimer_agreed_at',
        'manually_checked_in', 'checked_in_at', 'checked_out_at', 'late_checkout_type', 'late_checkout_hours', 'late_checkout_actual_time', 'gps_overridden', 'status', 'cancelled_at', 'cancelled_by_guest', 'cancellation_fee_applies', 'notes', 'welcome_message', 'identity_confirmed_at', 'registration_notified_at',
        'approved_at', 'decline_reason', 'archived_at', 'background_check_completed_at', 'deposit_verified_at', 'platform_payment_selected_at', 'checkin_approved_at',
        'contract_version', 'contract_accepted_at', 'contract_signed_name', 'contract_signed_ip', 'contract_signed_user_agent', 'contract_signed_device_id',
        'sms_consent_at', 'sms_consent_version', 'sms_consent_opted_in',
        'terms_accepted_at', 'terms_accepted_version',
        'deposit_payment_status', 'deposit_stripe_payment_intent_id', 'deposit_amount_cents',
        'incidentals_billed_cents', 'parking_billed_cents', 'early_checkin_billed_cents',
        'access_blocked_at', 'access_blocked_reason',
        'photo_id_front_approved_at', 'photo_id_front_declined_reason',
        'photo_id_back_approved_at', 'photo_id_back_declined_reason',
        'parking_charge', 'parking_charge_override', 'incidentals_charge', 'checkin_reminder_sent_at', 'checkout_reminder_sent_at',
        'early_checkin_charge_override', 'early_checkin_billing_mode', 'late_checkout_charge_override', 'ledger_published_at',
        'vehicle_make_model', 'license_plate_photo_path', 'vehicle_info_bypassed_at',
        'id_date_of_birth', 'id_age', 'id_expiry_date', 'id_number', 'id_name', 'id_scan_status', 'id_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'parking_needed' => 'boolean',
            'photo_id_received' => 'boolean',
            'gps_verified' => 'boolean',
            'manually_checked_in' => 'boolean',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'late_checkout_hours' => 'decimal:2',
            'late_checkout_actual_time' => 'datetime',
            'guest_authenticated_at' => 'datetime',
            'checkin_disclaimer_agreed_at' => 'datetime',
            'approved_at' => 'datetime',
            'identity_confirmed_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by_guest' => 'boolean',
            'cancellation_fee_applies' => 'boolean',
            'background_check_completed_at' => 'datetime',
            'deposit_verified_at' => 'datetime',
            'platform_payment_selected_at' => 'datetime',
            'registration_notified_at' => 'datetime',
            'checkin_approved_at' => 'datetime',
            'contract_accepted_at' => 'datetime',
            'sms_consent_at' => 'datetime',
            'sms_consent_opted_in' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'deposit_amount_cents' => 'integer',
            'incidentals_billed_cents' => 'integer',
            'parking_billed_cents' => 'integer',
            'early_checkin_billed_cents' => 'integer',
            'access_blocked_at' => 'datetime',
            'photo_id_front_approved_at' => 'datetime',
            'photo_id_back_approved_at' => 'datetime',
            'parking_charge' => 'decimal:2',
            'parking_charge_override' => 'decimal:2',
            'incidentals_charge' => 'decimal:2',
            'early_checkin_charge_override' => 'decimal:2',
            'late_checkout_charge_override' => 'decimal:2',
            'ledger_published_at' => 'datetime',
            'checkin_reminder_sent_at' => 'datetime',
            'checkout_reminder_sent_at' => 'datetime',
            'id_date_of_birth' => 'date',
            'id_age' => 'integer',
            'id_expiry_date' => 'date',
            'id_scanned_at' => 'datetime',
            'vehicle_info_bypassed_at' => 'datetime',
                    ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function charges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * True once a Stripe deposit charge exists (captured or failed) for
     * this booking. Entirely separate from isDepositVerified() / the legacy
     * manual deposit_verified_at flow, which is untouched by this.
     */
    public function hasDepositCharge(): bool
    {
        return filled($this->deposit_payment_status);
    }

    public function isDepositCaptured(): bool
    {
        return $this->deposit_payment_status === 'success';
    }

    /**
     * The incidentals amount actually in effect for this booking: a
     * per-booking amount set manually takes priority, otherwise falls back
     * to the property's required_incidentals_hold_amount default. Returns
     * null if neither is set, so callers can distinguish "no hold" from
     * "$0 hold" the same way effectiveParkingCharge() does.
     */
    public function effectiveIncidentalsCharge(): ?float
    {
        if ($this->incidentals_charge !== null) {
            return (float) $this->incidentals_charge;
        }

        return $this->property?->required_incidentals_hold_amount !== null
            ? (float) $this->property->required_incidentals_hold_amount
            : null;
    }

    /**
     * The pre-checkin combined charge: parking + incidentals + early
     * check-in (if already granted at this point), capped at the
     * property's flat-dollar ceiling (falling back to the global default),
     * then a global % processing fee added on top. This is what's actually
     * charged at the "after ID upload" step — replaces the old flat
     * admin-set deposit amount. If early check-in is granted *after* this
     * has already been paid, it's billed separately (see the standalone
     * early-check-in guest charge card) rather than reopening this total.
     */
    /**
     * Human-readable breakdown of calculatePreCheckinChargeCents(), for
     * admin visibility on the Payments page (a bare "$187.50" line item
     * would otherwise be meaningless once this is a combined charge).
     */
    public function preCheckinChargeBreakdown(): string
    {
        $parts = [];
        if (($parking = $this->effectiveParkingCharge()) > 0) {
            $parts[] = 'parking $' . number_format($parking, 2);
        }
        if (($this->effectiveIncidentalsCharge() ?? 0) > 0) {
            $parts[] = 'incidentals $' . number_format($this->effectiveIncidentalsCharge(), 2);
        }
        if (($earlyCheckin = $this->effectiveEarlyCheckinCharge()) > 0 && ! $this->earlyCheckinIsDeductedFromHold()) {
            $parts[] = 'early check-in $' . number_format($earlyCheckin, 2);
        }

        $capCents = $this->property && $this->property->deposit_cap_cents !== null
            ? $this->property->deposit_cap_cents
            : (int) \App\Models\Setting::getValue('default_deposit_cap_cents', 0);
        $feePercent = (float) \App\Models\Setting::getValue('processing_fee_percent', 0);

        $summary = $parts ? implode(' + ', $parts) : 'no parking/incidentals/early check-in';
        if ($capCents > 0) {
            $summary .= ', capped at $' . number_format($capCents / 100, 2);
        }
        if ($feePercent > 0) {
            $summary .= ", plus {$feePercent}% processing fee";
        }

        return $summary;
    }

    public function calculatePreCheckinChargeCents(): int
    {
        $parkingCents = (int) round(($this->effectiveParkingCharge() ?? 0) * 100);
        $incidentalsCents = (int) round(($this->effectiveIncidentalsCharge() ?? 0) * 100);
        $earlyCheckinCents = $this->earlyCheckinIsDeductedFromHold()
            ? 0
            : (int) round(($this->effectiveEarlyCheckinCharge() ?? 0) * 100);

        $capCents = $this->property && $this->property->deposit_cap_cents !== null
            ? $this->property->deposit_cap_cents
            : (int) \App\Models\Setting::getValue('default_deposit_cap_cents', 0);

        $subtotal = $parkingCents + $incidentalsCents + $earlyCheckinCents;
        if ($capCents > 0) {
            $subtotal = min($subtotal, $capCents);
        }

        return $this->applyProcessingFeeCents($subtotal);
    }

    /**
     * Adds the global % processing fee on top of a subtotal, in cents.
     * Shared by the combined pre-checkin charge and every standalone
     * charge type (parking, early check-in, late checkout, incidentals)
     * so the fee is consistently the same % of whatever is actually being
     * charged in that request, grouped or individual.
     */
    public function applyProcessingFeeCents(int $subtotalCents): int
    {
        $feePercent = (float) \App\Models\Setting::getValue('processing_fee_percent', 0);
        $fee = (int) round($subtotalCents * ($feePercent / 100));

        return $subtotalCents + $fee;
    }

    /**
     * incidentals_charge minus whatever's already been billed (via the
     * combined pre-checkin charge or a prior standalone incidentals
     * charge). Never negative — if admin lowers incidentals_charge after
     * some was already billed, this is just 0, not a refund trigger.
     */
    public function unbilledIncidentalsCents(): int
    {
        $currentCents = (int) round(($this->incidentals_charge ?? 0) * 100);

        return max(0, $currentCents - ($this->incidentals_billed_cents ?? 0));
    }

    /**
     * effectiveParkingCharge() minus whatever's already been billed (via
     * the combined pre-checkin charge or a prior standalone parking
     * charge). Same idea as unbilledIncidentalsCents() — prevents the
     * standalone "pay now" card from re-charging parking that was already
     * paid as part of the deposit.
     */
    public function unbilledParkingCents(): int
    {
        $currentCents = (int) round(($this->effectiveParkingCharge() ?? 0) * 100);

        return max(0, $currentCents - ($this->parking_billed_cents ?? 0));
    }

    /**
     * effectiveEarlyCheckinCharge() minus whatever's already been billed
     * (via the combined pre-checkin charge or a prior standalone early
     * check-in charge). Same idea as unbilledIncidentalsCents().
     */
    public function unbilledEarlyCheckinCents(): int
    {
        $currentCents = (int) round(($this->effectiveEarlyCheckinCharge() ?? 0) * 100);

        return max(0, $currentCents - ($this->early_checkin_billed_cents ?? 0));
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }
    public function isArchived(): bool
    {
        return filled($this->archived_at);
    }

    public function localTimestamp(?CarbonInterface $timestamp): ?Carbon
    {
        return $timestamp?->copy()->setTimezone($this->property?->timezone ?? config('app.display_timezone'));
    }

    public function isIdentityComplete(): bool
    {
        return filled($this->identity_confirmed_at);
    }

    public function hasAgreedToArrivalDisclaimer(): bool
    {
        return filled($this->checkin_disclaimer_agreed_at);
    }

    public function isCheckedIn(): bool
    {
        return !is_null($this->checked_in_at);
    }

    public function isApproved(): bool
    {
        return filled($this->approved_at);
    }

    public function isBackgroundCheckComplete(): bool
    {
        return filled($this->background_check_completed_at);
    }

    public function isDepositVerified(): bool
    {
        return filled($this->deposit_verified_at);
    }

    /**
     * The guest chose to pay the incidentals hold on their booking platform
     * (Airbnb/VRBO/etc.) rather than by card. There is no webhook to confirm
     * an off-platform payment, so this flag lets the portal move them past the
     * payment screen to "pending approval" and keep them there on revisit.
     */
    public function platformPaymentSelected(): bool
    {
        return filled($this->platform_payment_selected_at);
    }

    /**
     * The host has confirmed the unit is ready and approved this guest to
     * check in. Until then, a pre-checked-in guest is held on the "unit isn't
     * quite ready yet" screen.
     */
    public function isCheckinApproved(): bool
    {
        return filled($this->checkin_approved_at);
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCancelledByGuest(): bool
    {
        return $this->isCancelled() && (bool) $this->cancelled_by_guest;
    }

    /**
     * Whether a cancellation as of today falls inside the 30-day window
     * before this booking's arrival (or after the stay was due to start),
     * where we're still owed money and the booking must stay unarchived
     * and read-only. Shared by the OTA cancellation import path
     * (BookingImportService) and admin-triggered cancellation
     * (BookingController::updateStatus) so both use the identical rule.
     */
    public function cancellationFeeAppliesForDate($checkInDate = null): bool
    {
        $date = $checkInDate ?? $this->check_in_date;

        if (! $date) {
            return false;
        }

        $today = now()->setTimezone(config('app.display_timezone'))->startOfDay();

        return \Carbon\Carbon::parse($date)->startOfDay()->lte($today->copy()->addDays(30));
    }

    /**
     * A cancelled booking inside the 30-day pre-arrival window: we're owed
     * money, so it stays unarchived and read-only rather than being filed away.
     */
    public function cancellationFeeApplies(): bool
    {
        return $this->isCancelled() && (bool) $this->cancellation_fee_applies;
    }

    /**
     * Cancelled bookings are locked: no editing, approvals, GPS override, etc.
     */
    public function isReadOnly(): bool
    {
        return $this->isCancelled();
    }

    public function needsIdApproval(): bool
    {
        return filled($this->photo_id_path) && ! $this->isApproved();
    }

    /**
     * True once the uploaded ID has been scanned (task: OCR name/DOB/expiry
     * verification) AND the result is one the guest is allowed to proceed
     * on: the extracted name matched what they typed, or the scan couldn't
     * confidently read a field and was routed to manual admin review
     * (never automatically blocked on our own low confidence). Expired
     * documents and clear name mismatches are excluded — those must be
     * resolved (re-upload or admin override) before the rental agreement
     * can be signed.
     */
    public function idScanPassed(): bool
    {
        return in_array($this->id_scan_status, ['matched', 'manual_review'], true);
    }

    public function idScanBlockingReason(): ?string
    {
        return match ($this->id_scan_status) {
            'expired' => 'Your ID appears to be expired. Please upload a currently valid government ID to continue.',
            'name_mismatch' => 'The name on your ID doesn\'t match the name you entered. Please double-check your name or re-upload a clearer photo of your ID.',
            default => null,
        };
    }

    public function isFrontIdApproved(): bool
    {
        return filled($this->photo_id_front_approved_at);
    }

    public function isBackIdApproved(): bool
    {
        return filled($this->photo_id_back_approved_at);
    }

    /**
     * True once every side of ID the guest is expected to provide is approved.
     * Back side is only required if the booking has a back-side path/requirement
     * on record (some ID types are front-only).
     */
    public function isIdFullyApproved(): bool
    {
        $frontOk = $this->isFrontIdApproved();
        $backRequired = filled($this->photo_id_back_path) || filled($this->photo_id_back_declined_reason);
        $backOk = ! $backRequired || $this->isBackIdApproved();

        return $frontOk && $backOk;
    }

    public function hasPendingIdRejection(): bool
    {
        return filled($this->photo_id_front_declined_reason) || filled($this->photo_id_back_declined_reason);
    }

    public function dateRangeOnly(): string
    {
        $in = $this->check_in_date;
        $out = $this->check_out_date;

        if ($in->format('Y-m') === $out->format('Y-m')) {
            return $in->format('M j').'-'.$out->format('j');
        }

        if ($in->format('Y') === $out->format('Y')) {
            return $in->format('M j').' - '.$out->format('M j');
        }

        return $in->format('M j, Y').' - '.$out->format('M j, Y');
    }

    public function stayRangeLabel(): string
    {
        return $this->dateRangeOnly().' '.$this->nightsLabel();
    }

    /**
     * Number of nights for this stay, based on check_in_date/check_out_date.
     * Returns null if either date is missing (task 27).
     */
    public function nightsCount(): ?int
    {
        if (!$this->check_in_date || !$this->check_out_date) {
            return null;
        }

        return (int) $this->check_in_date->diffInDays($this->check_out_date);
    }

    /**
     * "(X night)" / "(X nights)" bracketed label for display next to dates,
     * per the client's request to show nights count next to dates everywhere
     * (task 27). Returns an empty string if nights can't be determined.
     */
    public function nightsLabel(): string
    {
        $nights = $this->nightsCount();

        if ($nights === null) {
            return '';
        }

        return '('.$nights.' '.Str::plural('night', $nights).')';
    }

    /**
     * Calculate the parking charge for this stay by summing the property's
     * per-weekday parking rate across each night of the stay (task 20/25).
     * Nights with no configured rate for that weekday are skipped (treated as $0),
     * rather than blocking the whole calculation, since the client fills in
     * rates per property over time.
     * Returns null if parking isn't needed, or if there's no check-in/check-out
     * date pair to calculate nights from.
     */
    public function calculateParkingCharge(): ?float
    {
        if (!$this->parking_needed) {
            return null;
        }

        if (!$this->check_in_date || !$this->check_out_date || !$this->property) {
            return null;
        }

        $total = 0.0;
        $night = $this->check_in_date->copy();

        while ($night->lt($this->check_out_date)) {
            $total += $this->property->parkingRateForDay($night) ?? 0.0;
            $night->addDay();
        }

        return round($total, 2);
    }

    /**
     * Recalculate and persist the auto-calculated parking_charge field.
     * Does not touch parking_charge_override — that's set independently by an admin.
     */
    public function recalculateParkingCharge(): void
    {
        $this->parking_charge = $this->calculateParkingCharge();
        $this->save();
    }

    /**
     * The charge actually used for billing: admin override wins if set,
     * otherwise fall back to the auto-calculated amount.
     */
    public function effectiveParkingCharge(): ?float
    {
        if ($this->parking_charge_override !== null) {
            return (float) $this->parking_charge_override;
        }

        return $this->parking_charge !== null ? (float) $this->parking_charge : null;
    }

    /**
     * The charge for a granted early check-in exception, looked up flat
     * from the property's rate for whichever time-window tier was granted
     * (task 26, updated per client clarification to three explicit
     * windows: 8am-12pm, 12pm-2pm, 2pm-4pm — each a distinct flat charge,
     * different per property). Returns null if no tier was granted or the
     * property hasn't set that window's rate yet.
     */
    public function earlyCheckinCharge(): ?float
    {
        if (!$this->early_checkin_tier || !$this->property) {
            return null;
        }

        $rate = match ($this->early_checkin_tier) {
            '8am_12pm', '8am' => $this->property->early_checkin_rate_8am_12pm ?? $this->property->early_checkin_rate_8am,
            '12pm_2pm', '12pm' => $this->property->early_checkin_rate_12pm_2pm ?? $this->property->early_checkin_rate_12pm,
            '2pm_4pm' => $this->property->early_checkin_rate_2pm_4pm,
            default => null,
        };

        return $rate !== null ? (float) $rate : null;
    }

    /**
     * The early check-in charge actually in effect: an admin override on
     * the ledger takes priority over the property/tier-derived rate. Same
     * override pattern as effectiveParkingCharge().
     */
    public function effectiveEarlyCheckinCharge(): ?float
    {
        if ($this->early_checkin_charge_override !== null) {
            return (float) $this->early_checkin_charge_override;
        }

        return $this->earlyCheckinCharge();
    }

    /**
     * Whether this booking's early check-in is settled by deducting from the
     * incidentals hold at checkout (like late checkout) instead of being
     * billed to the guest upfront. null/default ("charge") keeps the old
     * behavior where early check-in is part of the pre-check-in charge.
     */
    public function earlyCheckinIsDeductedFromHold(): bool
    {
        return $this->early_checkin_billing_mode === 'deduct_from_hold';
    }

    /**
     * The property's standard checkout instant for this booking's checkout
     * day, respecting the guest's chosen checkout time preference if set.
     * Used only for the unauthorized late-checkout hour calculation below —
     * deliberately independent of checked_out_at and the auto-checkout
     * scheduled command (task 23), so the two features never interact.
     */
    protected function standardCheckoutInstant(): ?CarbonInterface
    {
        if (!$this->check_out_date || !$this->property) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $this->effectiveCheckoutTime()));

        return $this->check_out_date->copy()->setTime($hour, $minute);
    }

    /**
     * Hours late for an unauthorized late checkout, computed from the
     * admin-entered actual checkout time vs. the standard checkout time.
     * This is intentionally separate from checked_out_at (which may be set
     * by the auto-checkout command and doesn't reflect when the guest
     * actually left) — task 26 explicitly calls for a manually recorded
     * actual time for the unauthorized case.
     */
    public function lateCheckoutHoursUnauthorized(): ?float
    {
        if ($this->late_checkout_type !== 'unauthorized' || !$this->late_checkout_actual_time) {
            return null;
        }

        $standard = $this->standardCheckoutInstant();
        if (!$standard) {
            return null;
        }

        $minutesLate = $standard->diffInMinutes($this->late_checkout_actual_time, false);

        return $minutesLate > 0 ? round($minutesLate / 60, 2) : 0.0;
    }

    /**
     * The late-checkout charge, either authorized (admin-entered hours ×
     * property's authorized hourly rate) or unauthorized (hours computed
     * from the admin-entered actual checkout time × unauthorized hourly
     * rate). Returns null if no late-checkout type is set, or the needed
     * rate/hours aren't available yet.
     */
    /**
     * The late-checkout charge, billed per half-hour (30 minute) block —
     * per client clarification, not a raw hourly multiplication. Any
     * partial block is rounded up (a guest 10 minutes into a new block
     * still owes for that whole block). Authorized uses the
     * admin-entered hours × the property's authorized per-30-min rate;
     * unauthorized uses hours computed from the admin-entered actual
     * checkout time × the (higher) unauthorized per-30-min rate. Returns
     * null if no late-checkout type is set, or the needed rate/hours
     * aren't available yet.
     */
    public function lateCheckoutCharge(): ?float
    {
        if (!$this->late_checkout_type || !$this->property) {
            return null;
        }

        if ($this->late_checkout_type === 'authorized') {
            $rate = $this->property->late_checkout_rate_authorized_per_30min ?? $this->property->late_checkout_rate_authorized_hourly;
            if ($this->late_checkout_hours === null || $rate === null) {
                return null;
            }

            return $this->chargeForHoursInHalfHourBlocks((float) $this->late_checkout_hours, (float) $rate);
        }

        if ($this->late_checkout_type === 'unauthorized') {
            $hours = $this->lateCheckoutHoursUnauthorized();
            $rate = $this->property->late_checkout_rate_unauthorized_per_30min ?? $this->property->late_checkout_rate_unauthorized_hourly;
            if ($hours === null || $rate === null) {
                return null;
            }

            return $this->chargeForHoursInHalfHourBlocks($hours, (float) $rate);
        }

        return null;
    }

    /**
     * The late-checkout charge actually in effect: an admin override on
     * the ledger takes priority over the computed rate. Same override
     * pattern as effectiveParkingCharge() / effectiveEarlyCheckinCharge().
     */
    public function effectiveLateCheckoutCharge(): ?float
    {
        if ($this->late_checkout_charge_override !== null) {
            return (float) $this->late_checkout_charge_override;
        }

        return $this->lateCheckoutCharge();
    }

    /**
     * Whether the ledger has ever been published to the guest. Admin can
     * freely edit parking/incidentals/early-check-in/late-checkout amounts
     * without the guest seeing anything change until this is (re)published.
     */
    public function isLedgerPublished(): bool
    {
        return filled($this->ledger_published_at);
    }

    /**
     * Total that gets deducted from the incidentals hold at checkout: late
     * checkout plus, when configured, early check-in. This is the amount
     * the guest is not refunded.
     */
    public function holdDeductions(): float
    {
        $deduction = $this->effectiveLateCheckoutCharge() ?? 0;

        if ($this->earlyCheckinIsDeductedFromHold()) {
            $deduction += $this->effectiveEarlyCheckinCharge() ?? 0;
        }

        return (float) $deduction;
    }

    /**
     * What's actually left of the incidentals hold once the late checkout
     * (and, when configured, early check-in) deductions are taken out of it
     * at checkout (task: "we're not gonna charge them extra we'll just
     * deduct that from the incidentals hold after check out"). Floored at
     * 0 -- if the deductions exceed the hold, refunding stops there; it's
     * on the admin to raise the incidentals hold ahead of time if a large
     * deduction is expected (see ledgerDeductionsExceedHold()).
     */
    public function estimatedIncidentalsRefund(): float
    {
        $hold = $this->effectiveIncidentalsCharge() ?? 0;

        return max(0.0, $hold - $this->holdDeductions());
    }

    /**
     * True when the hold deductions (late checkout, plus early check-in if
     * in deduct-from-hold mode) would exceed the current incidentals hold —
     * the exact scenario the client described: "if they want ... a late
     * checkout and that would exceed the amount of incidentals hold, we
     * might change the incidentals hold". Surfaced as a warning banner on
     * the ledger so admin catches it before checkout, not after.
     */
    public function ledgerDeductionsExceedHold(): bool
    {
        $hold = $this->effectiveIncidentalsCharge() ?? 0;

        return $this->holdDeductions() > $hold;
    }

    /**
     * What the guest actually ends up paying net of the refund: the
     * pre-check-in charge minus the estimated refund from the incidentals
     * hold. Equivalently parking + early check-in (if charged) + late
     * checkout + processing fee — i.e. the non-refundable portion admin
     * ultimately keeps. This is the "at a glance" figure for the ledger.
     */
    public function netChargeCents(): int
    {
        return max(0, $this->calculatePreCheckinChargeCents() - (int) round($this->estimatedIncidentalsRefund() * 100));
    }

    /**
     * Converts a number of hours into whole half-hour blocks (rounding any
     * partial block up) and multiplies by the given per-block rate.
     */
    private function chargeForHoursInHalfHourBlocks(float $hours, float $ratePer30Min): float
    {
        $blocks = (int) ceil(($hours * 60) / 30);

        return round($blocks * $ratePer30Min, 2);
    }

    public function instructionsCompleted(): bool
    {
        return $this->manually_checked_in || $this->status === 'currently_hosting';
    }

    public function isMarkedCheckedIn(): bool
    {
        return $this->isCheckedIn() || $this->manually_checked_in || $this->status === 'currently_hosting';
    }

    public function isPriorityGuest(): bool
    {
        return $this->needsIdApproval()
            || ($this->status === 'pre_checkin_complete' && ! $this->isApproved())
            || $this->status === 'awaiting_deposit'
            || ($this->isApproved() && ! $this->isBackgroundCheckComplete())
            || ($this->isBackgroundCheckComplete() && ! $this->isDepositVerified())
            || ($this->isCheckinDay() && in_array($this->status, ['guest_approved', 'currently_hosting'], true) && ! $this->gps_verified)
            || $this->checkin_time_status === 'pending'
            || $this->checkout_time_status === 'pending'
            || $this->id_scan_status === 'manual_review';
    }

    /**
     * Short label explaining why a booking showed up in the dashboard's
     * "Needs Attention" list, checked in the same priority order as
     * isPriorityGuest() so the most urgent reason is always shown first.
     */
    public function priorityReason(): ?string
    {
        return match (true) {
            $this->needsIdApproval() => 'ID needs approval',
            $this->id_scan_status === 'manual_review' => 'ID scan needs manual review',
            $this->status === 'pre_checkin_complete' && ! $this->isApproved() => 'Awaiting approval',
            $this->status === 'awaiting_deposit' => 'Awaiting deposit',
            $this->isApproved() && ! $this->isBackgroundCheckComplete() => 'Background check pending',
            $this->isBackgroundCheckComplete() && ! $this->isDepositVerified() => 'Deposit not verified',
            $this->checkin_time_status === 'pending' => 'Early check-in request pending',
            $this->checkout_time_status === 'pending' => 'Late checkout request pending',
            $this->isCheckinDay() && in_array($this->status, ['guest_approved', 'currently_hosting'], true) && ! $this->gps_verified => 'Not yet marked arrived',
            default => null,
        };
    }

    public function isCheckinDay(?CarbonInterface $date = null): bool
    {
        $date ??= now();

        return $date->toDateString() >= $this->check_in_date->toDateString();
    }

    public function daysUntilCheckIn(?CarbonInterface $date = null): int
    {
        $date ??= now();

        return (int) $date->copy()->startOfDay()->diffInDays($this->check_in_date->copy()->startOfDay(), false);
    }

    public function daysUntilCheckOut(?CarbonInterface $date = null): int
    {
        $date ??= now();

        return (int) $date->copy()->startOfDay()->diffInDays($this->check_out_date->copy()->startOfDay(), false);
    }

    /**
     * "in 3 days" / "tomorrow" / "today" phrasing for a future day count.
     * Centralized so every countdown label (dashboard + guests list) uses
     * the same wording instead of "in 1 day".
     */
    private function relativeDaysPhrase(int $daysUntil): string
    {
        return match (true) {
            $daysUntil <= 0 => 'today',
            $daysUntil === 1 => 'tomorrow',
            default => 'in '.$daysUntil.' '.Str::plural('day', $daysUntil),
        };
    }

    /**
     * Same as relativeDaysPhrase() but with no leading "in", for phrases
     * whose verb already ends in "in" -- "Checks in tomorrow" / "Checks in
     * 3 days" instead of "Checks in in 3 days".
     */
    private function relativeDaysPhraseBare(int $daysUntil): string
    {
        return match (true) {
            $daysUntil <= 0 => 'today',
            $daysUntil === 1 => 'tomorrow',
            default => $daysUntil.' '.Str::plural('day', $daysUntil),
        };
    }

    /**
     * "1 day ago" / "yesterday" phrasing for a past day count ($daysAgo is
     * always >= 1 at call sites).
     */
    private function relativeDaysAgoPhrase(int $daysAgo): string
    {
        return $daysAgo === 1 ? 'yesterday' : $daysAgo.' '.Str::plural('day', $daysAgo).' ago';
    }

    /**
     * Dynamic status line for the admin "This Week" guest card (task 8):
     * checked-in/checking-in-today guests show a countdown to checkout,
     * recently checked-out guests show how long ago they left, everyone
     * else falls back to the plain nights-of-stay label.
     */
    public function weekCardDynamicLabel(): string
    {
        if ($this->checked_out_at) {
            return 'Checked out';
        }

        if ($this->isMarkedCheckedIn() && ! $this->checked_out_at) {
            $daysLeft = $this->daysUntilCheckOut();

            return 'Checks out '.$this->relativeDaysPhrase($daysLeft);
        }

        return $this->nightsLabel();
    }

    public function weekCardArrivalLabel(): string
    {
        if ($this->isMarkedCheckedIn() || $this->checked_out_at) {
            return '';
        }

        $daysUntil = $this->daysUntilCheckIn();

        if ($daysUntil < 0) {
            return 'Check-in was '.$this->relativeDaysAgoPhrase(abs($daysUntil));
        }

        return 'Checks in '.$this->relativeDaysPhraseBare($daysUntil);
    }

    /**
     * Countdown label for the admin "Next Week" guest card (task 9),
     * e.g. "Checks in 5 days".
     */
    public function arrivalCountdownLabel(): string
    {
        return 'Checks in '.$this->relativeDaysPhraseBare($this->daysUntilCheckIn());
    }

    public function checkInCountdownLabel(): string
    {
        $daysUntil = $this->daysUntilCheckIn();

        if ($daysUntil < 0) {
            return 'Check-in was '.$this->relativeDaysAgoPhrase(abs($daysUntil));
        }

        return 'Checks in '.$this->relativeDaysPhraseBare($daysUntil);
    }

    /**
     * One-line label for the admin dashboard's per-property list: arriving
     * today / checking out today / staying until / arriving later, followed by
     * the property's check-in (or check-out) time. $todayDate is the display
     * timezone's Y-m-d, compared as a plain string so a UTC server can't push
     * a "tomorrow" arrival into "today".
     */
    public function dashboardArrivalLine(string $todayDate): string
    {
        $checkIn = $this->check_in_date?->toDateString();
        $checkOut = $this->check_out_date?->toDateString();

        if ($checkIn === $todayDate) {
            return 'Arriving today &middot; Check-in '.$this->effectiveCheckinTimeFormatted();
        }

        if ($checkOut === $todayDate) {
            return 'Checking out today &middot; Check-out '.$this->effectiveCheckoutTimeFormatted();
        }

        if ($this->isMarkedCheckedIn() && ! $this->checked_out_at) {
            return 'Staying until '.$this->check_out_date->format('M j');
        }

        $days = (int) \Carbon\Carbon::parse($todayDate)->startOfDay()
            ->diffInDays($this->check_in_date->startOfDay(), false);

        $when = match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => 'in '.$days.' days',
        };

        return 'Arriving '.$when.' &middot; Check-in '.$this->effectiveCheckinTimeFormatted();
    }

    /**
     * Sort today's operational work ahead of the rest of the week:
     * priority check-ins, other check-ins, check-outs, active stays, then
     * recently checked-out and upcoming bookings.
     */
    public function weekCardSortTier(): int
    {
        // Cancelled reservations sit at the bottom of the operational lists.
        if ($this->isCancelled()) {
            return 9;
        }

        // Compare plain date strings against the host's local day instead of
        // Carbon's isToday(), which uses the UTC app timezone and can push a
        // "tomorrow" arrival into "today".
        $today = now()->setTimezone(config('app.display_timezone'))->toDateString();
        $isTodayCheckIn = $this->check_in_date->toDateString() === $today;
        $isTodayCheckOut = $this->check_out_date->toDateString() === $today;

        if ($isTodayCheckIn && ! $this->isMarkedCheckedIn() && $this->isPriorityGuest()) {
            return 1;
        }

        if ($isTodayCheckIn && ! $this->isMarkedCheckedIn()) {
            return 2;
        }

        if ($isTodayCheckOut) {
            return 3;
        }

        if ($this->isMarkedCheckedIn() && ! $this->checked_out_at) {
            return 4;
        }

        if ($this->checked_out_at) {
            return 5;
        }

        return 6;
    }

    public function isSameDayBooking(): bool
    {
        return $this->created_at->toDateString() === $this->check_in_date->toDateString();
    }

    public function needsVehicleInfoPrompt(): bool
    {
        if (! $this->parking_needed) {
            return false;
        }

        if (! ($this->property?->requires_vehicle_photo ?? true)) {
            return false;
        }

        if ($this->vehicle_info_bypassed_at) {
            return false;
        }

        if ($this->license_plate_photo_path) {
            return false;
        }

        if ($this->isSameDayBooking()) {
            return true;
        }

        return $this->daysUntilCheckIn() <= 1;
    }

    /**
     * The property's standard check-in time. Falls back to '16:00' in code
     * only (no DB default, no global Setting) when the property doesn't
     * have one configured.
     */
    public function standardCheckinTime(): string
    {
        return $this->property?->checkin_time ?: '16:00';
    }

    public function standardCheckinTimeFormatted(): string
    {
        return $this->safeFormatTime($this->standardCheckinTime());
    }

    /**
     * The property's standard check-out time. Falls back to '10:00' in code
     * only — never reached for any existing property, since those already
     * have a real stored value from the original column default.
     */
    public function standardCheckoutTime(): string
    {
        return $this->property?->checkout_time ?: '10:00';
    }

    public function standardCheckoutTimeFormatted(): string
    {
        return $this->safeFormatTime($this->standardCheckoutTime());
    }

    public function requiresCheckinTimeApproval(?string $requestedTime = null): bool
    {
        return $this->isTimeBefore($requestedTime, $this->standardCheckinTime());
    }

    public function requiresCheckoutTimeApproval(?string $requestedTime = null): bool
    {
        return $this->isTimeAfter($requestedTime, $this->standardCheckoutTime());
    }

    private function isTimeBefore(?string $requestedTime, string $standardTime): bool
    {
        if (! filled($requestedTime)) {
            return false;
        }

        return $this->timeMinutes($requestedTime) < $this->timeMinutes($standardTime);
    }

    private function isTimeAfter(?string $requestedTime, string $standardTime): bool
    {
        if (! filled($requestedTime)) {
            return false;
        }

        return $this->timeMinutes($requestedTime) > $this->timeMinutes($standardTime);
    }

    private function timeMinutes(string $value): int
    {
        $time = $this->safeParseTime($value);

        return ($time->hour * 60) + $time->minute;
    }

    /**
     * The check-in time actually in effect for this booking. A guest's
     * requested preference is only honored once admin-approved; otherwise
     * this falls back to the property's standard time. A preference request
     * is a *request*, not an automatic override — approval may carry a
     * charge (see early_checkin_tier / task 26 billing).
     */
    public function effectiveCheckinTime(): string
    {
        if ($this->checkin_time_preference && $this->checkin_time_status === 'approved') {
            return $this->checkin_time_preference;
        }

        return $this->standardCheckinTime();
    }

    public function effectiveCheckinTimeFormatted(): string
    {
        return $this->safeFormatTime($this->effectiveCheckinTime());
    }

    public function checkinTimePreferenceFormatted(): ?string
    {
        return $this->checkin_time_preference ? $this->safeFormatTime($this->checkin_time_preference) : null;
    }

    public function checkoutTimePreferenceFormatted(): ?string
    {
        return $this->checkout_time_preference ? $this->safeFormatTime($this->checkout_time_preference) : null;
    }

    public function addressAvailableAtFormatted(): string
    {
        return $this->safeFormatTime($this->effectiveCheckinTime(), subHour: true);
    }

    private function safeParseTime(string $value): \Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromFormat('H:i', trim($value));
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::parse(trim($value));
            } catch (\Exception $e) {
                return \Carbon\Carbon::createFromFormat('H:i', '15:00');
            }
        }
    }
    private function safeFormatTime(string $value, bool $subHour = false): string
    {
        $time = null;

        try {
            $time = \Carbon\Carbon::createFromFormat('H:i', trim($value));
        } catch (\Exception $e) {
            try {
                $time = \Carbon\Carbon::parse(trim($value));
            } catch (\Exception $e) {
                $time = \Carbon\Carbon::createFromFormat('H:i', '15:00');
            }
        }

        if ($subHour) {
            $time = $time->subHour();
        }

        return $time->format('g:i A');
    }

    public function canViewAddress(?CarbonInterface $now = null): bool
    {
        $timezone = $this->property?->timezone ?? 'America/New_York';
        $now = ($now ?? now())->setTimezone($timezone);

        $checkinDate = $this->check_in_date->toDateString();

        if ($now->toDateString() < $checkinDate) return false;
        if ($now->toDateString() > $checkinDate) return true;

        $parsedTime = $this->safeParseTime($this->effectiveCheckinTime());
        $threshold = \Carbon\Carbon::parse($checkinDate, $timezone)->setTime($parsedTime->hour, $parsedTime->minute)->subHour();

        return $now->greaterThanOrEqualTo($threshold);
    }

    public function isCheckoutDay(?CarbonInterface $date = null): bool
    {
        $date ??= now();

        return $date->toDateString() >= $this->check_out_date->toDateString();
    }

    public function isPastCheckoutDay(?CarbonInterface $now = null): bool
    {
        $timezone = $this->property?->timezone ?? 'America/New_York';
        $now = ($now ?? now())->setTimezone($timezone);

        return $now->toDateString() > $this->check_out_date->toDateString();
    }

    public function isCheckoutDayBeforeNoon(?CarbonInterface $now = null): bool
    {
        $timezone = $this->property?->timezone ?? 'America/New_York';
        $now = ($now ?? now())->setTimezone($timezone);

        if ($now->toDateString() !== $this->check_out_date->copy()->subDay()->toDateString()) {
            return false;
        }

        return $now->hour >= 12;
    }

    /**
     * The check-out time actually in effect for this booking. Same approval
     * gate as effectiveCheckinTime() — a guest's requested preference only
     * applies once admin-approved, otherwise falls back to the property's
     * standard checkout time.
     */
    public function effectiveCheckoutTime(): string
    {
        if ($this->checkout_time_preference && $this->checkout_time_status === 'approved') {
            return $this->checkout_time_preference;
        }

        return $this->standardCheckoutTime();
    }

    public function effectiveCheckoutTimeFormatted(): string
    {
        return $this->safeFormatTime($this->effectiveCheckoutTime());
    }

    public function isPastCheckoutTime(?CarbonInterface $now = null): bool
    {
        $timezone = $this->property?->timezone ?? 'America/New_York';
        $now = ($now ?? now())->setTimezone($timezone);

        if ($now->toDateString() < $this->check_out_date->toDateString()) return false;
        if ($now->toDateString() > $this->check_out_date->toDateString()) return true;

        [$hour, $minute] = array_map('intval', explode(':', $this->effectiveCheckoutTime()));

        return $now->hour > $hour || ($now->hour === $hour && $now->minute >= $minute);
    }

    public function isPastCheckoutGracePeriod(int $graceMinutes = 30, ?CarbonInterface $now = null): bool
    {
        $timezone = $this->property?->timezone ?? 'America/New_York';
        $now = ($now ?? now())->setTimezone($timezone);

        if ($now->toDateString() < $this->check_out_date->toDateString()) return false;
        if ($now->toDateString() > $this->check_out_date->toDateString()) return true;

        [$hour, $minute] = array_map('intval', explode(':', $this->effectiveCheckoutTime()));
        $threshold = $now->copy()->setTime($hour, $minute)->addMinutes($graceMinutes);

        return $now->greaterThanOrEqualTo($threshold);
    }

    public static function archiveOverdue(): int
    {
        $count = 0;
        static::notArchived()->chunkById(100, function ($bookings) use (&$count) {
            foreach ($bookings as $booking) {
                // Guest cancellations are archived (or kept) at the moment of
                // cancellation, not by this checkout-date sweep.
                if ($booking->isCancelled() || ! $booking->isPastCheckoutTime()) {
                    continue;
                }
                $updates = ['archived_at' => now()];
                $booking->update($updates);
                $count++;
            }
        });
        return $count;
    }

    /**
     * Mark this booking checked out exactly once, firing the guest alert and
     * an activity log entry. Shared by the guest's manual action and both
     * automatic checkout paths (the grace-period overdue sweep and the
     * lock-based auto-close in GuestController).
     */
    public function completeCheckout(string $source = 'guest_confirmed_checkout'): bool
    {
        if ($this->checked_out_at) {
            return false;
        }

        $this->update([
            'status'         => 'checked_out',
            'checked_out_at' => now(),
        ]);

        \App\Services\GuestAlertService::send('checkout_completed', $this);

        \App\Services\ActivityLogService::guest($source, "Guest {$this->guest_name} checked out ({$source}).", 'check', [
            'booking_id'  => $this->id,
            'property_id' => $this->property_id,
            'actor_name'  => $this->guest_name,
            'actor_email' => $this->email,
            'severity'    => 'success',
        ]);

        return true;
    }

    public static function autoCheckoutOverdue(int $graceMinutes = 30): int
    {
        $count = 0;
        static::where('status', '!=', 'checked_out')->chunkById(100, function ($bookings) use (&$count, $graceMinutes) {
            foreach ($bookings as $booking) {
                if ($booking->isCancelled() || ! $booking->isPastCheckoutGracePeriod($graceMinutes)) {
                    continue;
                }
                if ($booking->completeCheckout('guest_auto_checkout_overdue')) {
                    $count++;
                }
            }
        });
        return $count;
    }

    public function publicUrl(): string
    {
        if ($this->reservation_id) {
            return route('checkin.rid', ['RID' => $this->reservation_id]);
        }
        return route('guest.show', [$this->booking_id, $this->token]);
    }

    public function effectiveStatus(): string
    {
        if ($this->status === 'guest_approved' && $this->isCheckinDay()) {
            return 'pending_check_in';
        }
        return $this->status ?: 'pending';
    }

    public function statusLabel(): string
    {
        if ($this->isCancelledByGuest()) {
            return 'Cancelled by Guest';
        }

        return str($this->effectiveStatus())->replace('_', ' ')->title()->toString();
    }

    /**
     * The booking platform to name in the guest "pay on …" flow. Uses the
     * OTA recorded from the channel manager (or set by hand) and falls back
     * to "Airbnb" when nothing is on file, so bookings created before this
     * field existed keep their exact previous guest experience.
     */
    public function platformLabel(): string
    {
        $platform = trim((string) ($this->booking_platform ?? ''));

        if ($platform === '') {
            return 'Airbnb';
        }

        return match (strtolower($platform)) {
            'airbnb' => 'Airbnb',
            'vrbo', 'homeaway' => 'Vrbo',
            'booking.com', 'booking' => 'Booking.com',
            'expedia' => 'Expedia',
            default => $platform,
        };
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        return PhoneFormatter::format($this->phone);
    }
}

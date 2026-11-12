<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\LeaseApplication;
use App\Models\LeaseInstallment;
use App\Models\LeaseOffer;
use App\Models\HostPaymentMethod;
use App\Services\Pms\AvailabilityLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaseOfferService
{
    public function create(LeaseApplication $application, array $data, int $hostId): LeaseOffer
    {
        abort_unless((int) $application->property?->owner_id === $hostId || auth()->user()?->hasRole('admin'), 403);
        abort_unless($application->status === 'approved' && $application->fee_status === 'paid', 409, 'The application must be approved and its application fee settled before an offer can be sent.');
        abort_if($application->offers()->whereIn('status', [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED])->exists(), 409, 'An active offer already exists.');

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $months = (int) $data['term_months'];
        $rent = (int) round((float) $data['monthly_rent'] * 100);
        $deposit = (int) round((float) $data['deposit'] * 100);
        $fees = (int) round((float) ($data['fees'] ?? 0) * 100);
        $initial = (int) round((float) $data['initial_due'] * 100);
        $totalRent = $rent * $months;
        $total = $totalRent + $deposit + $fees;
        if ($rent < 1 || $initial < ($deposit + $fees + $rent) || $initial > $total) {
            throw ValidationException::withMessages(['initial_due' => 'The initial amount must cover the security deposit, fees and first month of rent, and cannot exceed the lease total.']);
        }
        if ($end->lte($start) || $start->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['start_date' => 'Choose a valid future rental period.']);
        }

        $method = $data['payment_method'];
        $paymentMethod = null;
        if ($method === 'manual') {
            $paymentMethod = HostPaymentMethod::query()->whereKey($data['host_payment_method_id'] ?? 0)
                ->where('owner_id', $application->property->owner_id)->where('enabled', true)->first();
            if (! $paymentMethod) {
                throw ValidationException::withMessages(['host_payment_method_id' => 'Choose an enabled payment method belonging to this property owner.']);
            }
        } else {
            $owner = $application->property->owner;
            abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && app(\App\Services\Payments\PaymentService::class)->isConfigured(), 422, 'Card payments are not available for this host.');
        }

        $acceptBy = Carbon::parse($data['accept_by']);
        abort_if($acceptBy->lte(now()) || $acceptBy->gt(now()->addDays(30)), 422, 'The acceptance deadline must be in the next 30 days.');

        return BookingConflictService::withPropertyLock((int) $application->property_id, function () use ($application, $data, $start, $end, $months, $rent, $deposit, $fees, $initial, $total, $method, $paymentMethod, $acceptBy): LeaseOffer {
            BookingConflictService::assertFree((int) $application->property_id, $start, $end);
            $application->refresh();
            abort_unless($application->status === 'approved', 409);

            $bookingRef = 'LS-'.strtoupper(Str::random(8));
            $booking = Booking::create([
                'booking_id' => $bookingRef,
                'reservation_id' => $bookingRef,
                'source' => 'direct',
                'booking_platform' => 'Direct',
                'guest_name' => $application->applicant_name,
                'phone' => $application->applicant_phone,
                'email' => $application->applicant_email,
                'property_id' => $application->property_id,
                'token' => Str::random(48),
                'check_in_date' => $start->toDateString(),
                'check_out_date' => $end->toDateString(),
                'status' => Booking::STATUS_PENDING_PAYMENT,
                'guests_count' => max(1, (int) $application->occupants),
                'payment_method' => $method,
                'host_payment_method_id' => $paymentMethod?->id,
                'accommodation_cents' => $rent * $months,
                'cleaning_fee_cents' => $fees,
                'tax_cents' => 0,
                'processing_fee_cents' => 0,
                'total_cents' => $total,
                'hold_expires_at' => $acceptBy,
                'lease_application_id' => $application->id,
            ]);

            $offer = LeaseOffer::create([
                'lease_application_id' => $application->id,
                'property_id' => $application->property_id,
                'booking_id' => $booking->id,
                'status' => LeaseOffer::STATUS_SENT,
                'token' => Str::random(64),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'monthly_rent_cents' => $rent,
                'deposit_cents' => $deposit,
                'fees' => [['label' => 'Additional fees', 'amount_cents' => $fees]],
                'total_cents' => $total,
                'initial_due_cents' => $initial,
                'payment_method' => $method,
                'host_payment_method_id' => $paymentMethod?->id,
                'accept_by' => $acceptBy,
                'contract_html' => app(LeaseAgreementService::class)->templateFor($application->property),
                'contract_version' => 'lease-offer-v1',
                'host_signed_name' => auth()->user()?->name ?: $application->property->owner?->name,
                'host_signed_at' => now(),
                'host_signed_ip' => request()->ip(),
                'host_signed_user_agent' => mb_substr((string) request()->userAgent(), 0, 1000),
                'sent_at' => now(),
                'term_months' => $months,
            ]);

            $booking->update(['lease_offer_id' => $offer->id]);
            $offer->installments()->create([
                'kind' => LeaseInstallment::KIND_DEPOSIT, 'label' => 'Security deposit',
                'due_date' => $start->toDateString(), 'amount_cents' => $deposit,
                'required_at_signing' => $deposit > 0, 'sort_order' => 1,
            ]);
            $offer->installments()->create([
                'kind' => LeaseInstallment::KIND_FEE, 'label' => 'Additional fees',
                'due_date' => $start->toDateString(), 'amount_cents' => $fees,
                'required_at_signing' => $fees > 0, 'sort_order' => 2,
            ]);
            $firstRent = min($rent, $initial);
            for ($month = 0; $month < $months; $month++) {
                $due = $start->copy()->addMonthsNoOverflow($month);
                $offer->installments()->create([
                    'kind' => LeaseInstallment::KIND_RENT,
                    'label' => 'Rent · '.$due->format('F Y'),
                    'due_date' => $due->toDateString(),
                    'amount_cents' => $rent,
                    'required_at_signing' => $month === 0 && ($deposit + $fees + $rent) <= $initial,
                    'sort_order' => 10 + $month,
                ]);
            }
            $required = $offer->installments()->whereIn('kind', ['deposit', 'fee'])->sum('amount_cents');
            $remainingInitial = max(0, $initial - $required);
            if ($remainingInitial > 0) {
                $first = $offer->installments()->where('kind', 'rent')->orderBy('sort_order')->first();
                if ($first) {
                    $first->update(['required_at_signing' => true]);
                }
            }
            app(AvailabilityLedger::class)->syncBooking($booking);
            return $offer->load(['installments', 'booking', 'application']);
        });
    }

    public function signGuest(LeaseOffer $offer, string $name, string $ip, string $agent): void
    {
        abort_unless($offer->status === LeaseOffer::STATUS_SENT && $offer->accept_by?->isFuture(), 409, 'This offer is no longer available.');
        abort_unless(mb_strtolower(trim($name)) === mb_strtolower(trim((string) $offer->application->applicant_name)), 422, 'Enter your full name exactly as it appears on your application.');
        $offer->update([
            'guest_signed_name' => trim($name), 'guest_signed_at' => now(),
            'guest_signed_ip' => $ip, 'guest_signed_user_agent' => mb_substr($agent, 0, 1000),
            'status' => LeaseOffer::STATUS_SIGNED,
        ]);
        $this->confirmIfReady($offer);
    }

    public function markInstallmentPaid(LeaseInstallment $installment, int $hostId): void
    {
        $offer = $installment->offer()->with('booking')->firstOrFail();
        abort_unless((int) $offer->property->owner_id === $hostId || auth()->user()?->hasRole('admin'), 403);
        abort_unless($offer->status === LeaseOffer::STATUS_SIGNED && $offer->payment_method === 'manual', 409);
        abort_unless($installment->status === LeaseInstallment::STATUS_PENDING, 409);
        $installment->update(['status' => LeaseInstallment::STATUS_PAID, 'method' => 'manual', 'paid_at' => now(), 'received_by' => $hostId]);
        $this->confirmIfReady($offer);
    }

    public function confirmIfReady(LeaseOffer $offer): bool
    {
        $offer->loadMissing(['installments', 'booking']);
        if (! $offer->isFullySigned() || $offer->status === LeaseOffer::STATUS_CONFIRMED) return false;
        $required = $offer->installments->where('required_at_signing', true);
        if ($required->isEmpty() || $required->contains(fn ($i) => ! $i->isSettled())) return false;

        $confirmed = BookingConflictService::withPropertyLock((int) $offer->property_id, function () use ($offer): bool {
            $locked = LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== LeaseOffer::STATUS_SIGNED || ! $locked->isFullySigned() || $locked->accept_by?->isPast()) return false;
            BookingConflictService::assertFree((int) $locked->property_id, $locked->start_date, $locked->end_date, (int) $locked->booking_id);
            $booking = Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();
            $booking->update(['status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null]);
            $locked->update(['status' => LeaseOffer::STATUS_CONFIRMED, 'confirmed_at' => now()]);
            return true;
        });
        if ($confirmed) {
            $booking = $offer->booking->fresh(['property']);
            app(AvailabilityLedger::class)->syncBooking($booking);
            try {
                \Illuminate\Support\Facades\Mail::to($booking->email)->send(new \App\Mail\DirectBookingMail(
                    'Your rental is confirmed',
                    'Your lease agreement has been signed and the required initial payment has been received. Your guest portal is ready.',
                    route('guest.show', [$booking->booking_id, $booking->token]),
                    'Open guest portal',
                ));
            } catch (\Throwable $e) { report($e); }
        }
        return $confirmed;
    }
}

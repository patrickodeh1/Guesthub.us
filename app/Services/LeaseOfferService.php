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
        if ($rent < 1 || $initial !== ($deposit + $fees + $rent) || $initial > $total) {
            throw ValidationException::withMessages(['initial_due' => 'The initial amount must equal the security deposit, fees and first month of rent.']);
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
            $offer->installments()->where('kind', 'rent')->orderBy('sort_order')->first()?->update(['required_at_signing' => true]);
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
        abort_unless(in_array($offer->status, [LeaseOffer::STATUS_SIGNED, LeaseOffer::STATUS_CONFIRMED], true) && $offer->payment_method === 'manual', 409);
        abort_unless($installment->status === LeaseInstallment::STATUS_PENDING, 409);
        $installment->update(['status' => LeaseInstallment::STATUS_PAID, 'method' => 'manual', 'paid_at' => now(), 'received_by' => $hostId]);
        $this->confirmIfReady($offer);
    }


    public function cancel(LeaseOffer $offer, int $hostId, string $reason = ''): void
    {
        abort_unless((int) $offer->property->owner_id === $hostId || auth()->user()?->hasRole('admin'), 403);
        abort_unless(in_array($offer->status, [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED, LeaseOffer::STATUS_CONFIRMED], true), 409);
        DB::transaction(function () use ($offer, $reason): void {
            $locked = LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED, LeaseOffer::STATUS_CONFIRMED], true), 409);
            $locked->update(['status' => LeaseOffer::STATUS_WITHDRAWN, 'withdrawn_at' => now(), 'declined_reason' => trim($reason) ?: 'Withdrawn by host']);
            $locked->installments()->where('status', LeaseInstallment::STATUS_PENDING)->update(['status' => LeaseInstallment::STATUS_WAIVED]);
            $booking = Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->first();
            if ($booking) $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'hold_expires_at' => null]);
        });
        if ($offer->booking) app(AvailabilityLedger::class)->syncBooking($offer->booking->fresh());
    }

    public function renderContract(LeaseOffer $offer): string
    {
        $schedule = '<table><tbody>';
        foreach ($offer->installments as $item) {
            $schedule .= '<tr><td>'.e($item->label).' · '.e($item->due_date->format('M j, Y')).'</td><td>LeaseOffer $offer): bool
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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
.number_format($item->amount_cents / 100, 2).'</td></tr>';
        }
        $schedule .= '</tbody></table>';
        $values = [
            '[[applicant_name]]' => e($offer->application->applicant_name),
            '[[host_name]]' => e($offer->property->owner?->host_name ?: $offer->property->owner?->name),
            '[[property_name]]' => e($offer->property->name),
            '[[property_address]]' => e((string) ($offer->property->address ?? '')),
            '[[start_date]]' => e($offer->start_date->format('M j, Y')),
            '[[end_date]]' => e($offer->end_date->format('M j, Y')),
            '[[nights]]' => (string) $offer->nights(),
            '[[monthly_rent]]' => 'LeaseOffer $offer): bool
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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
.number_format($offer->monthly_rent_cents / 100, 2),
            '[[deposit]]' => 'LeaseOffer $offer): bool
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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
.number_format($offer->deposit_cents / 100, 2),
            '[[total]]' => 'LeaseOffer $offer): bool
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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
.number_format($offer->total_cents / 100, 2),
            '[[initial_due]]' => 'LeaseOffer $offer): bool
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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
.number_format($offer->initial_due_cents / 100, 2),
            '[[payment_schedule]]' => $schedule,
            '[[host_signed_name]]' => e((string) $offer->host_signed_name),
            '[[host_signed_at]]' => e($offer->host_signed_at?->format('M j, Y')),
            '[[tenant_signed_name]]' => e((string) $offer->guest_signed_name),
            '[[tenant_signed_at]]' => e($offer->guest_signed_at?->format('M j, Y')),
            '[[tenant_signed_ip]]' => e((string) $offer->guest_signed_ip),
            '[[agreement_version]]' => e((string) $offer->contract_version),
        ];
        return strtr((string) $offer->contract_html, $values);
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
            $booking->update([
                'status' => 'pending', 'payment_confirmed_at' => now(), 'hold_expires_at' => null,
                'contract_snapshot' => $this->renderContract($locked), 'contract_version' => $locked->contract_version,
                'contract_accepted_at' => $locked->guest_signed_at, 'contract_signed_name' => $locked->guest_signed_name,
                'contract_signed_ip' => $locked->guest_signed_ip, 'contract_signed_user_agent' => $locked->guest_signed_user_agent,
            ]);
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

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\HostPaymentMethod;
use App\Models\PropertyListing;
use App\Services\Payments\PaymentService;
use App\Services\Pms\AvailabilityLedger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DirectBookingService
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly PaymentService $payments,
        private readonly DirectBookingCancellationPolicy $cancellationPolicy,
    ) {}

    public function start(PropertyListing $listing, array $data): Booking
    {
        $listing = PropertyListing::query()
            ->whereKey($listing->getKey())
            ->whereNotNull('published_at')
            ->with(['property.owner', 'property.owner.hostPaymentMethods'])
            ->firstOrFail();
        $property = $listing->property;
        $owner = $property?->owner;
        abort_unless($property?->active && $owner && FeatureGate::enabled($owner, 'direct_booking_site'), 404);

        $paymentMethodId = null;
        if ($data['payment_method'] === 'card') {
            abort_unless(filled($owner->stripe_account_id) && $owner->stripe_charges_enabled, 422, 'Card payment is not available for this host.');
            abort_unless($this->payments->isConfigured(), 503, 'Card payment is temporarily unavailable.');
        } else {
            abort_unless(FeatureGate::enabled($owner, 'manual_payments'), 422, 'Manual payment is not available for this host.');
            $method = HostPaymentMethod::query()
                ->whereKey($data['host_payment_method_id'] ?? null)
                ->where('owner_id', $owner->id)
                ->where('enabled', true)
                ->first();
            if (! $method) {
                throw ValidationException::withMessages(['host_payment_method_id' => 'Choose an available host payment method.']);
            }
            $paymentMethodId = $method->id;
        }

        $requestBooking = $listing->booking_mode === 'request';
        $holdHours = $requestBooking || $data['payment_method'] === 'manual' ? 24 : 0.25;
        $quote = null;
        $booking = BookingConflictService::withPropertyLock((int) $property->id, function () use (
            $listing,
            $property,
            $data,
            $paymentMethodId,
            $requestBooking,
            $holdHours,
            &$quote,
        ) {
            $freshListing = PropertyListing::query()
                ->whereKey($listing->id)
                ->whereNotNull('published_at')
                ->with('property.owner')
                ->firstOrFail();
            abort_unless(
                $freshListing->property->active
                    && $freshListing->property->owner
                    && FeatureGate::enabled($freshListing->property->owner, 'direct_booking_site'),
                404,
            );
            $quote = $this->quotes->quote(
                $freshListing,
                $data['check_in'],
                $data['check_out'],
                (int) $data['guests'],
                $data['payment_method'] === 'card' ? 'card' : 'manual',
            );
            try {
                BookingConflictService::assertFree(
                    (int) $property->id,
                    $data['check_in'],
                    $data['check_out'],
                );
            } catch (ValidationException) {
                throw ValidationException::withMessages(['check_in' => 'These dates are no longer available. Please choose another stay.']);
            }

            $nightly = (int) $quote['subtotal_cents'];
            $cleaning = $this->lineAmount($quote, 'Cleaning fee');
            $tax = $this->lineAmount($quote, 'Estimated tax');
            $processing = $this->lineAmount($quote, 'Card processing fee');
            $reference = 'DB-'.strtoupper(Str::random(8));

            return Booking::create([
                'booking_id' => $reference,
                'reservation_id' => $reference,
                'source' => 'direct',
                'booking_platform' => 'Direct',
                'guest_name' => $data['guest_name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'property_id' => $property->id,
                'token' => Str::random(40),
                'check_in_date' => $data['check_in'],
                'check_out_date' => $data['check_out'],
                'status' => Booking::STATUS_PENDING_PAYMENT,
                'guests_count' => (int) $data['guests'],
                'payment_method' => $data['payment_method'],
                'direct_booking_request' => $requestBooking,
                'host_payment_method_id' => $paymentMethodId,
                'accommodation_cents' => $nightly,
                'cleaning_fee_cents' => $cleaning,
                'tax_cents' => $tax,
                'processing_fee_cents' => $processing,
                'total_cents' => (int) $quote['total_cents'],
                'hold_expires_at' => now()->addSeconds((int) ($holdHours * 3600)),
                'terms_accepted_at' => now(),
                'terms_accepted_version' => 'direct-booking-v1',
            ]);
        });

        $booking->refresh();
        app(AvailabilityLedger::class)->syncBooking($booking);
        $this->notifyStarted($booking->fresh(['property.owner', 'hostPaymentMethod']), $requestBooking);

        return $booking->fresh(['property.owner', 'hostPaymentMethod']);
    }

    public function confirmCardPayment(Charge $charge): void
    {
        $booking = $charge->booking;
        if (! $booking || $charge->type !== Charge::TYPE_ACCOMMODATION) {
            return;
        }

        $confirmed = BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking): bool {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if (! $locked || $locked->payment_confirmed_at) {
                return false;
            }
            if ($locked->status !== Booking::STATUS_PENDING_PAYMENT
                || ($locked->direct_booking_request && ! $locked->direct_booking_approved_at)) {
                return false;
            }
            if ($locked->hold_expires_at && $locked->hold_expires_at->isPast()) {
                $locked->update(['status' => 'expired', 'cancelled_at' => now(), 'hold_expires_at' => null]);

                return false;
            }

            $locked->update([
                'status' => 'pending',
                'payment_confirmed_at' => now(),
                'hold_expires_at' => null,
            ]);

            return true;
        });

        if ($confirmed) {
            $this->sendTemplate($booking->fresh('property'), 'guest_card_paid', 'View your booking');

            return;
        }

        $booking->refresh();
        if (! $booking->payment_confirmed_at && in_array($booking->status, ['expired', 'cancelled', 'canceled'], true)
            && (int) $charge->refunded_cents === 0) {
            app(AvailabilityLedger::class)->syncBooking($booking);
            $this->payments->refund($charge, (int) $charge->amount_cents);
        }
    }

    public function markManualPaymentReceived(Booking $booking): void
    {
        abort_unless($booking->source === 'direct' && $booking->payment_method === 'manual'
            && (! $booking->direct_booking_request || $booking->direct_booking_approved_at), 404);
        if ($booking->status !== Booking::STATUS_PENDING_PAYMENT || ($booking->hold_expires_at && $booking->hold_expires_at->isPast())) {
            throw ValidationException::withMessages(['booking' => 'This manual-payment hold is no longer active.']);
        }

        BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Booking::STATUS_PENDING_PAYMENT
                || ($locked->direct_booking_request && ! $locked->direct_booking_approved_at)
                || ($locked->hold_expires_at && $locked->hold_expires_at->isPast())) {
                throw ValidationException::withMessages(['booking' => 'This manual-payment hold is no longer active.']);
            }
            $locked->update([
                'status' => 'pending',
                'payment_confirmed_at' => now(),
                'hold_expires_at' => null,
            ]);
        });

        $this->sendTemplate($booking->fresh('property'), 'guest_manual_confirmed', 'View your booking');
    }

    public function approveRequest(Booking $booking): void
    {
        abort_unless($booking->source === 'direct' && $booking->direct_booking_request
            && ! $booking->direct_booking_approved_at && $booking->status === Booking::STATUS_PENDING_PAYMENT, 404);
        BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! $locked->direct_booking_request || $locked->direct_booking_approved_at
                || $locked->status !== Booking::STATUS_PENDING_PAYMENT || ($locked->hold_expires_at && $locked->hold_expires_at->isPast())) {
                throw ValidationException::withMessages(['booking' => 'This booking request has expired or is no longer awaiting approval.']);
            }
            BookingConflictService::assertFree(
                (int) $locked->property_id,
                $locked->check_in_date,
                $locked->check_out_date,
                $locked->id,
            );
            $locked->update([
                'status' => Booking::STATUS_PENDING_PAYMENT,
                'direct_booking_approved_at' => now(),
                'hold_expires_at' => now()->addHours(24),
                'direct_booking_reminded_at' => null,
            ]);
        });

        $booking->refresh()->load('property');
        $this->notifyGuest(
            $booking,
            'Booking request approved',
            'Your host approved your stay. Complete payment within 24 hours to confirm the booking.',
            'Complete payment',
            route('public.checkout.pay', [$booking->booking_id, $booking->token]),
        );
    }

    public function declineRequest(Booking $booking, string $reason): void
    {
        abort_unless($booking->source === 'direct' && $booking->direct_booking_request
            && ! $booking->direct_booking_approved_at && $booking->status === Booking::STATUS_PENDING_PAYMENT, 404);
        BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking, $reason): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->direct_booking_request && ! $locked->direct_booking_approved_at
                && $locked->status === Booking::STATUS_PENDING_PAYMENT, 409);
            $locked->update([
                'status' => 'declined',
                'decline_reason' => $reason,
                'cancelled_at' => now(),
                'hold_expires_at' => null,
            ]);
        });

        $booking->refresh();
        app(AvailabilityLedger::class)->syncBooking($booking);
        $this->notifyGuest($booking->fresh('property'), 'Booking request update', 'The host could not accept this booking request. '.$reason, 'Browse other stays');
    }

    public function expireHold(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_PENDING_PAYMENT
            || ! $booking->hold_expires_at || $booking->hold_expires_at->isFuture()) {
            return false;
        }

        foreach ($booking->charges()->where('status', Charge::STATUS_PENDING)->get() as $charge) {
            try {
                $this->payments->cancelPendingIntent($charge);
            } catch (Throwable $exception) {
                report($exception);
                $charge->refresh();
                if ($charge->status !== Charge::STATUS_SUCCESS) {
                    return false;
                }
                $this->payments->finalize((string) $charge->stripe_payment_intent_id);

                return false;
            }
        }

        $expired = BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking): bool {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== Booking::STATUS_PENDING_PAYMENT
                || ! $locked->hold_expires_at || $locked->hold_expires_at->isFuture()) {
                return false;
            }
            $locked->update(['status' => 'expired', 'cancelled_at' => now(), 'hold_expires_at' => null]);

            return true;
        });

        if ($expired) {
            $booking->refresh();
            app(AvailabilityLedger::class)->syncBooking($booking);
            $this->notifyGuest($booking->fresh('property'), 'Booking hold expired', 'Your booking hold expired before payment was completed. You can choose another available stay.', 'Browse other stays');
        }

        return $expired;
    }

    public function sendHoldReminder(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_PENDING_PAYMENT
            || ! $booking->hold_expires_at || $booking->direct_booking_reminded_at
            || ($booking->direct_booking_request && ! $booking->direct_booking_approved_at)
            || $booking->created_at->addSeconds((int) $booking->created_at->diffInSeconds($booking->hold_expires_at) / 2)->isFuture()) {
            return false;
        }

        $updated = Booking::query()
            ->whereKey($booking->id)
            ->where('status', Booking::STATUS_PENDING_PAYMENT)
            ->whereNull('direct_booking_reminded_at')
            ->update(['direct_booking_reminded_at' => now()]);
        if (! $updated) {
            return false;
        }

        $this->notifyGuest($booking->fresh('property'), 'Booking hold reminder', 'Your stay is on hold temporarily. Complete the next step before the hold expires.', 'Continue booking');

        return true;
    }

    public function cancel(Booking $booking, bool $guestCancellation, ?string $reason = null, ?int $refundPercent = null): int
    {
        abort_unless($booking->source === 'direct', 404);
        if (in_array($booking->status, ['cancelled', 'canceled', 'declined', 'expired'], true)) {
            throw ValidationException::withMessages(['booking' => 'This booking is already closed.']);
        }

        $refundCents = 0;
        BookingConflictService::withPropertyLock((int) $booking->property_id, function () use ($booking, $guestCancellation, $reason, $refundPercent, &$refundCents): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->source === 'direct' && ! in_array($locked->status, ['cancelled', 'canceled', 'declined', 'expired'], true), 409);

            $paidCharge = $locked->charges()
                ->where('type', Charge::TYPE_ACCOMMODATION)
                ->whereIn('status', [Charge::STATUS_SUCCESS, Charge::STATUS_PARTIALLY_CAPTURED])
                ->latest()
                ->first();
            if ($paidCharge) {
                $refundCents = $this->cancellationPolicy->refundableCents($locked, $paidCharge, $guestCancellation ? null : ($refundPercent ?? 100));
                if ($refundCents > 0) {
                    $this->payments->refund($paidCharge, $refundCents);
                }
            }

            foreach ($locked->charges()->where('status', Charge::STATUS_PENDING)->get() as $pendingCharge) {
                $this->payments->cancelPendingIntent($pendingCharge);
            }
            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_guest' => $guestCancellation,
                'decline_reason' => $reason,
                'hold_expires_at' => null,
                'archived_at' => now(),
            ]);
        });

        $booking->refresh()->load('property');
        app(AvailabilityLedger::class)->syncBooking($booking);
        $message = $refundCents > 0
            ? 'Your booking was cancelled. A refund of $'.number_format($refundCents / 100, 2).' was initiated.'
            : ($booking->payment_method === 'manual' && $booking->payment_confirmed_at
                ? 'Your booking was cancelled. Please contact the host to arrange any manual-payment refund.'
                : 'Your booking was cancelled.');
        $this->refundText = $refundCents > 0
            ? 'A refund of $'.number_format($refundCents / 100, 2).' was initiated.'
            : ($booking->payment_method === 'manual' && $booking->payment_confirmed_at
                ? 'Please contact the host to arrange any manual-payment refund.'
                : '');
        $this->notifyGuest($booking, 'Booking cancelled', $message, 'Browse other stays');
        $this->refundText = '';

        return $refundCents;
    }

    private function lineAmount(array $quote, string $label): int
    {
        foreach ($quote['line_items'] as $item) {
            if ($item['label'] === $label) {
                return (int) $item['amount_cents'];
            }
        }

        return 0;
    }

    private function notifyStarted(Booking $booking, bool $requestBooking): void
    {
        $owner = $booking->property?->owner;
        $hostMsg = \App\Services\DirectBookingMessages::render($requestBooking ? 'host_new_request' : 'host_new_booking', $booking);
        if ($owner?->email && $hostMsg) {
            EmailNotificationService::sendDirectBooking(
                $booking,
                $owner->email,
                $hostMsg['subject'],
                $hostMsg['message'],
                route('admin.guests.show', $booking),
                $requestBooking ? 'Review request' : 'Review booking',
            );
        }

        $this->notifyGuest(
            $booking,
            $requestBooking ? 'Booking request received' : ($booking->payment_method === 'manual' ? 'Manual payment instructions' : 'Complete your booking'),
            $requestBooking
                ? 'Your booking request was sent to the host. You will receive a message after the host reviews it.'
                : ($booking->payment_method === 'manual'
                    ? $this->manualInstructions($booking)
                    : 'Your dates are held temporarily. Complete card payment to confirm your stay.'),
            $requestBooking ? 'View booking request' : ($booking->payment_method === 'manual' ? 'View payment instructions' : 'Complete payment'),
            route('public.checkout.pay', [$booking->booking_id, $booking->token]),
        );
    }

    private function manualInstructions(Booking $booking): string
    {
        $method = $booking->hostPaymentMethod;

        return trim(($method?->label ?: 'Manual payment').'. '
            .($method?->handle ? 'Payment reference/account: '.$method->handle.'. ' : '')
            .($method?->instructions ?: 'Follow the host instructions and use booking reference '.$booking->booking_id.'.')
            .' Your dates are held for 24 hours; the host must confirm receipt before the booking is final.');
    }

    public function notifyHost(Booking $booking, bool $reminder = false): void
    {
        try {
            $booking->loadMissing(['property.owner', 'hostPaymentMethod']);
            $email = $booking->property?->owner?->email;
            if (! $email) {
                return;
            }
            $amount = '$'.number_format(((int) $booking->total_cents) / 100, 2);
            $dates = $booking->check_in_date->format('M j').' to '.$booking->check_out_date->format('M j, Y');
            $url = url('/admin/guests');
            foreach (['admin.guests.show', 'admin.bookings.show'] as $name) {
                if (\Illuminate\Support\Facades\Route::has($name)) {
                    $url = route($name, $booking);
                    break;
                }
            }
            if ($booking->direct_booking_request && ! $booking->direct_booking_approved_at) {
                $subject = 'New booking request to review';
                $message = "{$booking->guest_name} requested {$booking->property->name} ({$dates}). Open the booking to approve or decline.";
                $action = 'Review request';
            } else {
                $method = $booking->hostPaymentMethod?->label ?: 'your payment method';
                $subject = $reminder ? 'Payment reminder: booking hold ends soon' : 'New booking waiting for your payment confirmation';
                $message = "{$booking->guest_name} booked {$booking->property->name} ({$dates}). Check {$method} for {$amount} with reference {$booking->booking_id}, then mark the payment received. "
                    .'The dates are held until '.($booking->hold_expires_at?->format('M j, g:i A') ?? 'the hold expires').' and are released if the payment is not confirmed.';
                $action = 'Confirm payment';
            }
            if ($reminder) {
                $tpl = \App\Services\DirectBookingMessages::render('host_payment_reminder', $booking, ['{message}' => $message]);
                if ($tpl === null) {
                    return;
                }
                $subject = $tpl['subject'];
                $message = $tpl['message'];
            }
            EmailNotificationService::sendDirectBooking($booking, $email, $subject, $message, $url, $action);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Host notification failed', ['booking' => $booking->booking_id, 'message' => $e->getMessage()]);
        }
    }

    private function sendTemplate(Booking $booking, string $key, string $action, array $vars = []): void
    {
        $m = \App\Services\DirectBookingMessages::render($key, $booking, $vars);
        if ($m) {
            $this->notifyGuest($booking, $m['subject'], $m['message'], $action, null, true);
        }
    }

    private function helpLine(): string
    {
        return ' Need to cancel or change your booking? Open your guest portal and use Call or Contact, and our team will help.';
    }

    private string $refundText = '';

    private const TEMPLATE_BY_SUBJECT = [
        'Complete your booking' => 'guest_started_card',
        'Manual payment instructions' => 'guest_started_manual',
        'Booking request received' => 'guest_request_received',
        'Booking request approved' => 'guest_request_approved',
        'Booking request update' => 'guest_request_declined',
        'Booking hold reminder' => 'guest_hold_reminder',
        'Booking hold expired' => 'guest_hold_expired',
        'Booking cancelled' => 'guest_cancelled',
    ];

    private function notifyGuest(Booking $booking, string $subject, string $message, string $action, ?string $url = null, bool $raw = false): void
    {
        if (! $booking->email) {
            return;
        }
        if (! $raw && isset(self::TEMPLATE_BY_SUBJECT[$subject])) {
            $booking->loadMissing('hostPaymentMethod');
            $pm = $booking->hostPaymentMethod;
            $rendered = \App\Services\DirectBookingMessages::render(self::TEMPLATE_BY_SUBJECT[$subject], $booking, [
                '{message}' => $message,
                '{instructions}' => trim(($pm?->handle ? 'Payment reference/account: '.$pm->handle.'. ' : '').($pm?->instructions ?: 'Use booking reference '.$booking->booking_id.'.')),
                '{reason}' => (string) $booking->decline_reason,
                '{refund_text}' => $this->refundText,
            ]);
            if (! $rendered) {
                return;
            }
            $subject = $rendered['subject'];
            $message = $rendered['message'];
        }
        EmailNotificationService::sendDirectBooking(
            $booking,
            $booking->email,
            $subject,
            $message,
            $url ?: route('guest.show', [$booking->booking_id, $booking->token]),
            $action,
        );
    }
}

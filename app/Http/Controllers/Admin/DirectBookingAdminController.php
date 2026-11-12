<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\DirectBookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DirectBookingAdminController extends Controller
{
    public function markPaymentReceived(Request $request, Booking $booking, DirectBookingService $bookings, \App\Services\PaymentReceiptService $receipts)
    {
        abort_unless($request->user()->hasRole('admin') || (int) $booking->property()->value('owner_id') === (int) $request->user()->id, 403);
        $request->validate(['receipt' => $receipts->rules()]);
        if (! $booking->guest_payment_reported_at
            || ! $receipts->forPayable($booking)->contains('role', 'guest')) {
            throw ValidationException::withMessages([
                'receipt' => 'Wait until the guest reports payment and uploads their receipt before confirming.',
            ]);
        }
        $receipts->store($request->file('receipt'), $booking, 'host', $request->user()->id);
        $bookings->markManualPaymentReceived($booking);

        return back()->with('success', 'Manual payment recorded; the stay is confirmed.');
    }

    public function approve(Booking $booking, DirectBookingService $bookings)
    {
        $bookings->approveRequest($booking);

        return back()->with('success', 'Booking request approved. The guest has been emailed a payment link.');
    }

    public function decline(Request $request, Booking $booking, DirectBookingService $bookings)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bookings->declineRequest($booking, $data['reason']);

        return back()->with('success', 'Booking request declined and the guest has been notified.');
    }

    private function notifyRefund(Booking $booking, int $cents, ?int $remainingCents): void
    {
        try {
            if (! $booking->email) {
                return;
            }
            $money = fn ($c) => '$'.number_format($c / 100, 2);
            $tpl = \App\Services\DirectBookingMessages::renderGeneric('guest_refund_issued', [
                'guest_name' => $booking->guest_name,
                'property_name' => $booking->property?->name,
                'booking_ref' => $booking->booking_id,
                'refund_amount' => $money($cents),
                'remaining_amount' => $remainingCents === null ? 'n/a' : $money($remainingCents),
            ]);
            if (! $tpl) {
                return;
            }
            \App\Services\EmailNotificationService::sendDirectBooking(
                $booking, $booking->email, $tpl['subject'], $tpl['message'],
                route('guest.show', [$booking->booking_id, $booking->token]), 'View your booking'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Refund email failed', ['booking' => $booking->booking_id, 'message' => $e->getMessage()]);
        }
    }

    public function resolveRefund(Request $request, Booking $booking)
    {
        abort_unless($request->user()->hasRole('admin') || (int) $booking->property()->value('owner_id') === (int) $request->user()->id, 403);
        abort_unless($booking->source === 'direct' && $booking->isCancelled(), 409);
        $booking->forceFill(['refund_status' => 'resolved', 'archived_at' => now()])->save();

        return back()->with('success', 'Refund closed and booking archived.');
    }

    public function refund(Request $request, Booking $booking)
    {
        abort_unless($request->user()->hasRole('admin') || (int) $booking->property()->value('owner_id') === (int) $request->user()->id, 403);
        abort_unless($booking->source === 'direct' && $booking->isCancelled(), 409);
        $data = $request->validate([
            'amount' => ['required', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'note' => ['nullable', 'string', 'max:500'],
            'manual' => ['nullable', 'boolean'],
        ]);
        $cents = (int) round(((float) $data['amount']) * 100);
        if ($cents < 1) {
            return back()->with('error', 'Enter an amount greater than zero.');
        }
        $who = $request->user()->name;
        $log = fn (string $text) => \App\Services\ActivityLogService::admin('direct_booking_refund', $text, 'guests', [
            'subject_type' => Booking::class, 'subject_id' => $booking->id, 'booking_id' => $booking->id,
            'property_id' => $booking->property_id, 'severity' => 'warning',
        ]);
        $note = filled($data['note'] ?? null) ? ' Note: '.$data['note'] : '';

        if ($booking->payment_method === 'manual') {
            if ($cents > (int) $booking->total_cents) {
                return back()->with('error', 'That is more than the booking total.');
            }
            $log("{$who} recorded a manual refund of $".number_format($cents / 100, 2)." for {$booking->booking_id}.".$note);

            $this->notifyRefund($booking, $cents, null);

            return back()->with('success', 'Manual refund recorded and the guest was emailed. Remember to send the money yourself.');
        }

        $charge = $booking->charges()->where('type', \App\Models\Charge::TYPE_ACCOMMODATION)
            ->whereIn('status', [\App\Models\Charge::STATUS_SUCCESS, \App\Models\Charge::STATUS_PARTIALLY_CAPTURED])->latest()->first();
        if (! $charge) {
            return back()->with('error', 'There is nothing left to refund on this booking.');
        }
        $remaining = $charge->amount_cents - (int) $charge->refunded_cents;
        if ($cents > $remaining) {
            return back()->with('error', 'The most you can refund is $'.number_format($remaining / 100, 2).'.');
        }
        try {
            app(\App\Services\Payments\PaymentService::class)->refund($charge, $cents);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Direct booking refund failed', ['booking' => $booking->booking_id, 'message' => $e->getMessage()]);

            return back()->with('error', 'The refund could not be completed, so nothing was sent. Please try again in a moment.');
        }
        $log("{$who} refunded $".number_format($cents / 100, 2)." to the guest for {$booking->booking_id}.".$note);

        $fresh = $charge->fresh();
        $this->notifyRefund($booking, $cents, max(0, $fresh->amount_cents - (int) $fresh->refunded_cents));
        if ($charge->fresh()->refunded_cents >= $charge->amount_cents) {
            $booking->forceFill(['refund_status' => 'resolved', 'archived_at' => now()])->save();
        }

        return back()->with('success', 'Refund of $'.number_format($cents / 100, 2).' sent.');
    }

    public function cancel(Request $request, Booking $booking, DirectBookingService $bookings)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $refund = $bookings->cancel($booking, false, $data['reason'] ?? 'Cancelled by host.');

        return back()->with('success', 'Booking cancelled. Use the Refund panel to refund the guest.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\DirectBookingService;
use Illuminate\Http\Request;

class DirectBookingAdminController extends Controller
{
    public function markPaymentReceived(Booking $booking, DirectBookingService $bookings)
    {
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

    public function cancel(Request $request, Booking $booking, DirectBookingService $bookings)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $refund = $bookings->cancel($booking, false, $data['reason'] ?? 'Cancelled by host.');

        return back()->with('success', $refund > 0
            ? 'Booking cancelled and full card refund initiated.'
            : 'Booking cancelled.');
    }
}

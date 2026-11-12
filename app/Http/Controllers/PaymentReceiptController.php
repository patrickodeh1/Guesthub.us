<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptService;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function guestReport(Request $request, string $bookingId, string $token, PaymentReceiptService $receipts)
    {
        $booking = $this->guestBooking($bookingId, $token);
        $request->validate(['receipt' => $receipts->rules()]);
        $receipts->guestReport($booking, $request->file('receipt'));

        return redirect()->route('public.checkout.pay', [$booking->booking_id, $booking->token])
            ->with('status', 'Your payment is being confirmed. We will let you know when it is confirmed.');
    }

    public function guestShow(string $bookingId, string $token, PaymentReceipt $receipt, PaymentReceiptService $receipts)
    {
        $booking = $this->guestBooking($bookingId, $token);
        abort_unless($receipt->payable_type === $booking->getMorphClass() && (int) $receipt->payable_id === (int) $booking->id, 404);

        return $receipts->stream($receipt);
    }

    private function guestBooking(string $bookingId, string $token): Booking
    {
        return Booking::query()
            ->where('booking_id', $bookingId)
            ->where('token', $token)
            ->where('source', 'direct')
            ->whereNull('lease_offer_id')
            ->whereNull('lease_application_id')
            ->firstOrFail();
    }
}

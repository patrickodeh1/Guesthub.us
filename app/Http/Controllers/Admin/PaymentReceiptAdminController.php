<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptService;
use Illuminate\Http\Request;

class PaymentReceiptAdminController extends Controller
{
    public function show(Request $request, Booking $booking, PaymentReceipt $receipt, PaymentReceiptService $receipts)
    {
        abort_unless($request->user()->hasRole('admin') || (int) $booking->property()->value('owner_id') === (int) $request->user()->id, 403);
        abort_unless($receipt->payable_type === $booking->getMorphClass() && (int) $receipt->payable_id === (int) $booking->id, 404);

        return $receipts->stream($receipt);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $charges = Charge::query()
            ->with(['booking:id,booking_id,guest_name,property_id', 'booking.property:id,name'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('booking', fn ($inner) => $inner
                ->where('guest_name', 'like', "%{$s}%")
                ->orWhere('booking_id', 'like', "%{$s}%")
            ))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $totals = [
            'success_cents' => Charge::where('status', Charge::STATUS_SUCCESS)->sum('amount_cents'),
            'pending_cents' => Charge::where('status', Charge::STATUS_PENDING)->sum('amount_cents'),
            'failed_count' => Charge::where('status', Charge::STATUS_FAILED)->count(),
        ];

        return view('admin.payments.index', [
            'charges' => $charges,
            'totals' => $totals,
            'types' => [
                Charge::TYPE_DEPOSIT => 'Pre-checkin (parking/incidentals/early check-in)',
                Charge::TYPE_PARKING => 'Parking (standalone)',
                Charge::TYPE_INCIDENTALS => 'Incidentals (standalone)',
                Charge::TYPE_EARLY_CHECKIN => 'Early check-in (standalone)',
                Charge::TYPE_LATE_CHECKOUT => 'Late checkout',
            ],
            'statuses' => [
                Charge::STATUS_PENDING => 'Pending',
                Charge::STATUS_SUCCESS => 'Success',
                Charge::STATUS_FAILED => 'Failed',
            ],
            'stripeConfigured' => filled(config('services.stripe.secret')),
        ]);
    }
}

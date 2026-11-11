<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\DirectBookingService;
use Illuminate\Console\Command;

class RemindHostsOfPendingPayments extends Command
{
    protected $signature = 'direct-bookings:remind-hosts';

    protected $description = 'Email hosts about unconfirmed payments whose hold expires within 6 hours.';

    public function handle(DirectBookingService $service): int
    {
        $count = 0;
        Booking::query()
            ->where('source', 'direct')
            ->where('status', Booking::STATUS_PENDING_PAYMENT)
            ->where('payment_method', 'manual')
            ->whereNull('payment_confirmed_at')
            ->whereBetween('hold_expires_at', [now(), now()->addHours(6)])
            ->where(fn ($q) => $q->where('direct_booking_request', false)->orWhereNotNull('direct_booking_approved_at'))
            ->with(['property.owner', 'hostPaymentMethod'])
            ->each(function (Booking $booking) use ($service, &$count): void {
                $service->notifyHost($booking, true);
                $count++;
            });
        $this->info("Checked {$count} bookings.");

        return self::SUCCESS;
    }
}

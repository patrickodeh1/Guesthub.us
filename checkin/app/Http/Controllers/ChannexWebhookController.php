<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\Pms\BookingImportService;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannexWebhookController extends Controller
{
    public function handle(Request $request, PmsProviderInterface $provider, BookingImportService $importer)
    {
        $secret = config('services.channex.webhook_secret');

        if ($secret) {
            // Channex has no built-in HMAC signing. Per their docs, auth is
            // via a custom shared-secret header you configure on the
            // webhook itself (headers: {"X-Channex-Webhook-Secret": "..."})
            // and we just compare it exactly on receipt.
            $provided = $request->header('X-Channex-Webhook-Secret');

            if (! $provided || ! hash_equals($secret, $provided)) {
                ActivityLogService::security('channex_webhook_invalid_signature', 'Rejected a Channex webhook with an invalid or missing secret header.', [
                    'severity' => 'warning',
                ]);
                return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->json()->all();
        Log::info('Channex webhook received', ['payload' => $payload]);

        $pmsBooking = $provider->handleWebhookPayload($payload);

        if (! $pmsBooking) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $booking = $importer->import($pmsBooking);

        if ($booking) {
            // Channex acknowledges Booking Revisions, not Bookings — must
            // use revisionId here, not externalBookingId, or the ack call
            // 404s against the real API.
            $provider->acknowledgeBooking($pmsBooking->revisionId ?? $pmsBooking->externalBookingId);
        }

        return response()->json(['ok' => true]);
    }
}

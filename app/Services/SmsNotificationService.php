<?php
namespace App\Services;
use App\Models\Booking;
use App\Support\PhoneFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    /**
     * Telnyx Messaging API endpoint.
     */
    private const TELNYX_MESSAGES_URL = 'https://api.telnyx.com/v2/messages';

    /**
     * Send an SMS to an arbitrary number (e.g. the guest's phone), as opposed to
     * the fixed admin-notify number used by the existing admin-facing alerts.
     */
    protected static function sendTo(?string $to, string $message, string $context = 'guest', bool $requireConsent = true): void
    {
        $to = PhoneFormatter::toTelUri($to);

        if ($requireConsent && ($context === 'guest' || $context === 'guest_alert')) {
            $phone = preg_replace('/\D+/', '', (string) $to);
            if (! $phone || ! \App\Services\SmsConsentService::canSendTo($phone)) {
                Log::warning("SMS notification skipped ({$context}): no active SMS consent for recipient.");
                return;
            }
        }

        $apiKey = config('services.telnyx.api_key');
        $from = PhoneFormatter::toTelUri(config('services.telnyx.from_number'));

        if (! $apiKey || ! $from || ! $to) {
            Log::warning("SMS notification skipped ({$context}): Telnyx not fully configured or recipient missing.");
            return;
        }

        // Telnyx requires the 'from' number in strict E.164 format (e.g.
        // "+15555550199"). A number that's present but malformed (missing
        // the leading '+', wrong digit count, non-numeric characters) isn't
        // caught by the blank check above and instead reaches the API,
        // which rejects the whole request with error 10004 "Invalid source
        // number" — regardless of how well-formed the recipient number is.
        // Catch that here with a clear, actionable log message instead.
        if (! preg_match('/^\+[1-9]\d{9,14}$/', $from)) {
            Log::error("SMS notification skipped ({$context}): TELNYX_FROM_NUMBER (\"{$from}\") is not a valid E.164 number. It must be in the form +1XXXXXXXXXX (with the leading '+', country code, and no spaces/dashes).");
            return;
        }

        $payload = [
            'from' => $from,
            'to' => $to,
            'text' => $message,
        ];

        if ($profileId = config('services.telnyx.messaging_profile_id')) {
            $payload['messaging_profile_id'] = $profileId;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post(self::TELNYX_MESSAGES_URL, $payload);

            if (! $response->successful()) {
                Log::error("SMS notification failed ({$context}) to {$to}: HTTP {$response->status()} ".$response->body());
                return;
            }

            Log::info("SMS notification sent ({$context}) to {$to}.");
        } catch (\Throwable $e) {
            Log::error("SMS notification failed ({$context}): ".$e->getMessage());
        }
    }

    /**
     * Text the GUEST (not the admin) that a side of their ID was declined.
     */
    public static function photoIdDeclinedToGuest(Booking $booking, string $side, string $reason): void
    {
        $sideLabel = $side === 'back' ? 'back' : 'front';
        self::sendTo(
            $booking->phone,
            "GuestHub: The {$sideLabel} of your ID was not approved. Reason: {$reason}. Please log back in to re-upload it.",
            'guest'
        );
    }

    /**
     * Send an already-rendered lifecycle alert message (task 30) to an
     * arbitrary number — the guest's phone, or the admin's notify number.
     */
    public static function guestAlert(string $to, string $message, bool $requireConsent = true): void
    {
        self::sendTo($to, $message, 'guest_alert', $requireConsent);
    }
}

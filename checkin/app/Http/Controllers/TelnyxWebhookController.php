<?php

namespace App\Http\Controllers;

use App\Services\SmsConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound Telnyx messaging webhooks (STOP / HELP keywords) and
 * updates guest SMS consent accordingly.
 *
 * Telnyx signs webhooks with an Ed25519 key: header `telnyx-signature-ed25519`
 * (base64 signature over "{timestamp}|{raw body}") plus `telnyx-timestamp`.
 * The matching public key is configured as TELNYX_PUBLIC_KEY. When no public
 * key is set (local/dev) verification is skipped rather than rejecting
 * everything.
 */
class TelnyxWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Telnyx webhook rejected: invalid or missing signature.');
            return response()->json(['ok' => false], 403);
        }

        $eventType = $request->input('data.event_type');

        // Only inbound messages carry STOP/HELP keywords; acknowledge every
        // other event so Telnyx doesn't keep retrying.
        if ($eventType && $eventType !== 'message.received') {
            return response()->json(['ok' => true]);
        }

        $payload = $request->input('data.payload', []);
        $phone = $payload['from']['phone_number'] ?? null;
        $body = (string) ($payload['text'] ?? '');

        if (! $phone) {
            return response()->json(['ok' => false], 400);
        }

        SmsConsentService::handleInboundKeyword($phone, $body);

        return response()->json(['ok' => true]);
    }

    protected function hasValidSignature(Request $request): bool
    {
        $publicKey = (string) config('services.telnyx.public_key');

        if ($publicKey === '') {
            return true;
        }

        $signature = (string) $request->header('telnyx-signature-ed25519', '');
        $timestamp = (string) $request->header('telnyx-timestamp', '');

        if ($signature === '' || $timestamp === '') {
            return false;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            Log::warning('Telnyx webhook signature could not be verified: the sodium PHP extension is not available.');
            return false;
        }

        $signedPayload = $timestamp.'|'.$request->getContent();
        $decodedSignature = base64_decode($signature, true);
        $decodedKey = base64_decode($publicKey, true);

        if ($decodedSignature === false
            || $decodedKey === false
            || strlen($decodedKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($decodedSignature, $signedPayload, $decodedKey);
    }
}

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\SmsConsentEvent;
use Illuminate\Support\Str;

class SmsConsentService
{
    public static function canSendTo(?string $phone): bool
    {
        $phone = self::normalizePhone($phone);

        if (! $phone) {
            return false;
        }

        $event = SmsConsentEvent::where('phone', $phone)
            ->latest('occurred_at')
            ->first();

        return $event && $event->event_type === 'opt_in';
    }

    public static function recordOptIn(Booking $booking, ?string $phone, array $context = []): void
    {
        $phone = self::normalizePhone($phone ?? $booking->phone);

        if (! $phone) {
            return;
        }

        $now = now();
        $disclosure = trim((string) ($context['disclosure_text'] ?? Setting::getValue('legal_sms_consent_content', '')));
        $version = (string) ($context['disclosure_version'] ?? Setting::getValue('sms_consent_version', '1'));
        $termsVersion = (string) ($context['terms_version'] ?? Setting::getValue('rental_contract_version', '1'));
        $privacyVersion = (string) ($context['privacy_version'] ?? Setting::getValue('privacy_policy_version', '1'));

        SmsConsentEvent::create([
            'booking_id' => $booking->id,
            'phone' => $phone,
            'event_type' => 'opt_in',
            'checkbox_status' => true,
            'disclosure_text' => $disclosure ?: null,
            'disclosure_version' => $version ?: null,
            'terms_version' => $termsVersion ?: null,
            'privacy_version' => $privacyVersion ?: null,
            'page_url' => $context['page_url'] ?? request()->fullUrl() ?? null,
            'guest_name' => $booking->guest_name,
            'property_id' => $booking->property_id,
            'host_name' => $booking->property?->host_name ?? null,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'opt_in_method' => $context['opt_in_method'] ?? 'guest_portal',
            'occurred_at' => $now,
        ]);

        $booking->forceFill([
            'sms_consent_at' => $now,
            'sms_consent_version' => $version,
            'sms_consent_opted_in' => true,
        ])->save();

        SmsNotificationService::guestAlert(
            $phone,
            'Guest Hub Guest Alerts: You are now opted in to receive reservation and access-related text updates from Guest Hub. Msg & data rates may apply. Reply STOP to cancel, HELP for help.',
            false
        );
    }

    public static function recordOptOut(Booking $booking, ?string $phone = null): void
    {
        $phone = self::normalizePhone($phone ?? $booking->phone);

        if (! $phone) {
            return;
        }

        $now = now();

        SmsConsentEvent::create([
            'booking_id' => $booking->id,
            'phone' => $phone,
            'event_type' => 'opt_out',
            'checkbox_status' => false,
            'disclosure_text' => null,
            'page_url' => request()->fullUrl() ?? null,
            'guest_name' => $booking->guest_name,
            'property_id' => $booking->property_id,
            'host_name' => $booking->property?->host_name ?? null,
            'ip_address' => request()->ip(),
            'opt_in_method' => 'telnyx_stop',
            'occurred_at' => $now,
        ]);

        $booking->forceFill([
            'sms_consent_opted_in' => false,
            'sms_consent_at' => $booking->sms_consent_at ?? $now,
        ])->save();
    }

    public static function handleInboundKeyword(?string $phone, string $body): void
    {
        $phone = self::normalizePhone($phone);
        if (! $phone) {
            return;
        }

        $normalized = strtoupper(trim($body));
        $booking = Booking::where('phone', 'like', '%'.self::normalizePhone($phone).'%')->first();

        if ($normalized === 'STOP' || $normalized === 'STOPALL' || $normalized === 'UNSUBSCRIBE') {
            if ($booking) {
                self::recordOptOut($booking, $phone);
            }
            SmsNotificationService::guestAlert($phone, 'Guest Hub Guest Alerts: You have opted out and will receive no further Guest Hub Guest Alerts. For help, email needhelp@guesthub.us.', false);
            return;
        }

        if ($normalized === 'HELP') {
            SmsNotificationService::guestAlert($phone, 'Guest Hub Guest Alerts: For help, visit guesthub.us or email needhelp@guesthub.us. Reply STOP to cancel. Msg & data rates may apply.', false);
        }
    }

    /**
     * Normalizes to the last 10 digits of the number. Telnyx sends inbound
     * webhooks in E.164 (+1XXXXXXXXXX); guest-entered phone numbers are
     * often stored without the country code. Comparing on the last 10
     * digits means an opt-in recorded as "5551234567" and a STOP received
     * as "+15551234567" resolve to the same consent record, instead of
     * silently creating two unrelated rows.
     */
    public static function normalizePhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (! $digits) {
            return '';
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}

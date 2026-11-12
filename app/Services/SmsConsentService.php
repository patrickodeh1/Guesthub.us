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

    /** Opt-in captured on the lease application form, before any booking exists. */
    public static function recordLeaseOptIn(\App\Models\LeaseApplication $application, array $context = []): void
    {
        $phone = self::normalizePhone($context['phone'] ?? $application->applicant_phone);

        if (! $phone) {
            return;
        }

        $application->loadMissing('property');
        $now = now();
        $version = (string) ($context['disclosure_version'] ?? Setting::getValue('sms_consent_version', '1'));

        SmsConsentEvent::create([
            'booking_id' => null,
            'lease_application_id' => $application->id,
            'phone' => $phone,
            'event_type' => 'opt_in',
            'checkbox_status' => true,
            'disclosure_text' => trim((string) ($context['disclosure_text'] ?? '')) ?: null,
            'disclosure_version' => $version ?: null,
            'terms_version' => (string) Setting::getValue('rental_contract_version', '1') ?: null,
            'privacy_version' => (string) Setting::getValue('privacy_policy_version', '1') ?: null,
            'page_url' => $context['page_url'] ?? null,
            'guest_name' => $application->applicant_name,
            'property_id' => $application->property_id,
            'host_name' => $application->property?->host_name ?? null,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'opt_in_method' => $context['opt_in_method'] ?? 'lease_application',
            'occurred_at' => $now,
        ]);

        $application->forceFill(['sms_consent_at' => $now])->save();

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

    /** STOP from a number with no booking (a lease applicant): copy their latest consent row as an opt-out. */
    public static function recordPhoneOptOut(string $phone): void
    {
        $last = SmsConsentEvent::where('phone', $phone)->latest('occurred_at')->first();

        if (! $last || $last->event_type === 'opt_out') {
            return;
        }

        SmsConsentEvent::create([
            'booking_id' => $last->booking_id,
            'lease_application_id' => $last->lease_application_id,
            'phone' => $phone,
            'event_type' => 'opt_out',
            'checkbox_status' => false,
            'disclosure_text' => null,
            'page_url' => null,
            'guest_name' => $last->guest_name,
            'property_id' => $last->property_id,
            'host_name' => $last->host_name,
            'ip_address' => request()->ip(),
            'opt_in_method' => 'telnyx_stop',
            'occurred_at' => now(),
        ]);
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
            } else {
                self::recordPhoneOptOut($phone);
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

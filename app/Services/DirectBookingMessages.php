<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

class DirectBookingMessages
{
    public const HELP = ' Need to cancel or change your booking? Open your guest portal and use Call or Contact, and our team will help.';

    /** Placeholders: {guest_name} {booking_id} {property} {dates} {amount} {method} {hold_until} {guests} {message} */
    public const EVENTS = [
        'guest_started_card' => ['label' => 'Guest: complete your booking (card)', 'subject' => 'Complete your booking', 'message' => 'Hi {guest_name}, your dates at {property} ({dates}) are held temporarily. Complete card payment of {amount} to confirm your stay.'],
        'guest_started_manual' => ['label' => 'Guest: payment instructions', 'subject' => 'Payment instructions', 'message' => 'Hi {guest_name}, to confirm your stay at {property} ({dates}), pay {amount} using {method}. {instructions} Your dates are held until {hold_until}; the host must confirm receipt before the booking is final.'],
        'guest_request_received' => ['label' => 'Guest: booking request received', 'subject' => 'Booking request received', 'message' => 'Hi {guest_name}, your booking request for {property} ({dates}) was sent to the host. You will receive a message after the host reviews it.'],
        'guest_request_approved' => ['label' => 'Guest: request approved', 'subject' => 'Booking request approved', 'message' => 'Hi {guest_name}, your host approved your stay at {property} ({dates}). Complete payment within 24 hours to confirm the booking.'],
        'guest_request_declined' => ['label' => 'Guest: request declined', 'subject' => 'Booking request update', 'message' => 'Hi {guest_name}, the host could not accept your booking request for {property}. {reason}'],
        'guest_card_paid' => ['label' => 'Guest: card payment received', 'subject' => 'Booking confirmed', 'message' => 'Hi {guest_name}, your card payment of {amount} was received and your stay at {property} ({dates}) is confirmed.'.self::HELP],
        'guest_manual_confirmed' => ['label' => 'Guest: host confirmed your payment', 'subject' => 'Booking confirmed', 'message' => 'Hi {guest_name}, your host confirmed receipt of your payment. Your stay at {property} ({dates}) is confirmed.'.self::HELP],
        'guest_hold_reminder' => ['label' => 'Guest: hold reminder', 'subject' => 'Booking hold reminder', 'message' => 'Hi {guest_name}, your stay at {property} is on hold temporarily. Complete the next step before the hold expires.'],
        'guest_hold_expired' => ['label' => 'Guest: hold expired', 'subject' => 'Booking hold expired', 'message' => 'Hi {guest_name}, your booking hold expired before payment was completed. You can choose another available stay.'],
        'guest_cancelled' => ['label' => 'Guest: booking cancelled', 'subject' => 'Booking cancelled', 'message' => 'Hi {guest_name}, your booking {booking_id} at {property} ({dates}) was cancelled. {refund_text}'],
        'host_new_booking' => ['label' => 'Host: new direct booking', 'subject' => 'New direct booking', 'message' => '{guest_name} requested {dates} for {guests} guests at {property}. Reference: {booking_id}.'],
        'host_new_request' => ['label' => 'Host: new booking request', 'subject' => 'New booking request', 'message' => '{guest_name} requested {dates} for {guests} guests at {property}. Reference: {booking_id}. Open the booking to approve or decline.'],
        'host_payment_reminder' => ['label' => 'Host: payment confirmation reminder', 'subject' => 'Payment reminder: booking hold ends soon', 'message' => '{guest_name} booked {property} ({dates}). Check {method} for {amount} with reference {booking_id}, then mark the payment received. The dates are held until {hold_until} and are released if the payment is not confirmed.'],
        'guest_lease_received' => ['label' => 'Guest: lease application received', 'subject' => 'Lease application received', 'message' => 'Hi {applicant_name}, your application for {property} has been received. The host will review it and contact you with any updates.'],
        'host_lease_new' => ['label' => 'Host: new lease application', 'subject' => 'New lease application', 'message' => '{applicant_name} submitted a lease application for {property}. Open it to review the details.'],
        'guest_lease_status' => ['label' => 'Guest: lease application status updated', 'subject' => 'Lease application status updated', 'message' => 'Hi {applicant_name}, the host updated your lease application for {property}. New status: {status}.'],
        'guest_lease_fee_paid' => ['label' => 'Guest: application fee received', 'subject' => 'Lease application fee received', 'message' => 'Hi {applicant_name}, your application fee of {fee_amount} for {property} has been received. {refund_text}'],
        'staff_contact_form' => ['label' => 'Staff: contact form submission', 'subject' => 'New contact form submission', 'message' => "Contact form submission\n\nName: {sender_name}\nEmail: {sender_email}\nPhone: {sender_phone}\nSubject: {sender_subject}\nMessage:\n{contact_message}"],
        'staff_privacy_request' => ['label' => 'Staff: privacy request received', 'subject' => 'Privacy request received', 'message' => "Privacy request received\n\nName: {sender_name}\nEmail: {sender_email}\nPhone: {sender_phone}\nRequest type: {request_type}\nDetails:\n{details}"],
        'guest_early_access' => ['label' => 'Guest: early access request received', 'subject' => 'We received your request for early access', 'message' => "Hi {lead_name},\n\nWe've received your request for early access to {site_name}. We'll be in touch soon with next steps.\n\nThanks,\n{site_name}"],
        'staff_early_access' => ['label' => 'Staff: new early access signup', 'subject' => 'New early access signup: {lead_name}', 'message' => "New early access signup\n\nName: {lead_name}\nEmail: {lead_email}\nPhone: {lead_phone}\nRole: {lead_role}\nMessage: {lead_message}"],
        'staff_contact_sms' => ['label' => 'Staff: contact form text message', 'subject' => 'SMS (no subject)', 'message' => "GuestHub contact: {sender_name} ({sender_email})\nSubject: {sender_subject}\nMessage: {contact_message}"],
        'staff_session_started' => ['label' => 'Staff: cleaning session started', 'subject' => 'Cleaning Started – {property_name}', 'message' => 'A cleaning session has begun at {property_name}.'],
        'staff_session_completed' => ['label' => 'Staff: cleaning session completed', 'subject' => 'Cleaning Completed – {property_name}', 'message' => 'A cleaning session has been completed at {property_name}. Status: {status_text}.'],
    ];

    public static function config(): array
    {
        $saved = json_decode((string) Setting::getValue('direct_booking_messages', ''), true);
        $out = [];
        foreach (self::EVENTS as $key => $meta) {
            $row = is_array($saved) ? ($saved[$key] ?? []) : [];
            $out[$key] = [
                'enabled' => (bool) ($row['enabled'] ?? true),
                'subject' => filled($row['subject'] ?? null) ? $row['subject'] : $meta['subject'],
                'message' => filled($row['message'] ?? null) ? $row['message'] : $meta['message'],
            ];
        }

        return $out;
    }

    public static function put(array $rows): void
    {
        $clean = [];
        foreach (self::EVENTS as $key => $meta) {
            $r = $rows[$key] ?? [];
            $clean[$key] = [
                'enabled' => (bool) ($r['enabled'] ?? false),
                'subject' => mb_substr((string) ($r['subject'] ?? ''), 0, 200),
                'message' => mb_substr((string) ($r['message'] ?? ''), 0, 2000),
            ];
        }
        Setting::putValue('direct_booking_messages', json_encode($clean));
    }

    /** @return array{subject:string,message:string}|null null when switched off */
    public static function renderGeneric(string $key, array $vars): ?array
    {
        $cfg = self::config()[$key] ?? null;
        if (! $cfg || ! $cfg['enabled']) {
            return null;
        }
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }

        return ['subject' => strtr($cfg['subject'], $map), 'message' => strtr($cfg['message'], $map)];
    }

    /** @return array{subject:string,message:string}|null null when switched off */
    public static function renderLease(string $key, \App\Models\LeaseApplication $application, array $extra = []): ?array
    {
        $cfg = self::config()[$key] ?? null;
        if (! $cfg || ! $cfg['enabled']) {
            return null;
        }
        $application->loadMissing('listing');
        $cents = (int) ($application->total_fee_cents ?: ($application->listing?->lease_application_fee_cents ?? 0));
        $refund = match ($application->listing?->lease_fee_refund_policy) {
            'non_refundable' => 'The application fee is non-refundable.',
            'refundable_if_declined' => 'The application fee is refunded if the host declines your application.',
            default => 'Please contact the host if you have questions about refunds.',
        };
        $vars = $extra + [
            '{applicant_name}' => (string) $application->applicant_name,
            '{application_id}' => (string) $application->id,
            '{property}' => (string) ($application->listing?->public_title ?? ''),
            '{fee_amount}' => '$'.number_format($cents / 100, 2),
            '{status}' => str_replace('_', ' ', (string) $application->status),
            '{refund_text}' => $refund,
        ];

        return ['subject' => strtr($cfg['subject'], $vars), 'message' => strtr($cfg['message'], $vars)];
    }

    /** @return array{subject:string,message:string}|null null when switched off */
    public static function render(string $key, Booking $booking, array $extra = []): ?array
    {
        $cfg = self::config()[$key] ?? null;
        if (! $cfg || ! $cfg['enabled']) {
            return null;
        }
        $booking->loadMissing(['property', 'hostPaymentMethod']);
        $vars = $extra + [
            '{guest_name}' => $booking->guest_name,
            '{booking_id}' => $booking->booking_id,
            '{property}' => $booking->property?->name ?? '',
            '{dates}' => $booking->check_in_date->format('M j').' to '.$booking->check_out_date->format('M j, Y'),
            '{amount}' => '$'.number_format(((int) $booking->total_cents) / 100, 2),
            '{method}' => $booking->hostPaymentMethod?->label ?: 'card',
            '{hold_until}' => $booking->hold_expires_at?->format('M j, g:i A') ?? '',
            '{guests}' => (string) $booking->guests_count,
            '{message}' => '',
            '{instructions}' => '',
            '{reason}' => '',
            '{refund_text}' => '',
        ];

        return ['subject' => strtr($cfg['subject'], $vars), 'message' => strtr($cfg['message'], $vars)];
    }
}

<?php

namespace App\Services;

use App\Mail\GuestAlertMail;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the guest lifecycle alerts with globally customizable message
 * templates and per-event, per-role toggles for who receives each one and
 * over which channel(s).
 *
 * Each event has a separate "guest" wording and "staff" wording, since the
 * guest-facing copy ("Hi {guest_first_name}, you're checked in!") reads wrong when
 * forwarded to staff, who need it phrased as a notice about the guest
 * instead. Recipients are: the guest themselves, the Contact desk (Settings
 * > General contact_phone/contact_email), and each staff role (owner,
 * manager, staff, viewer) individually, pulled from that role's own users.
 * All enabled recipients across every source are normalized and deduped
 * before sending so the same phone/email is never messaged twice for one
 * event.
 *
 * All settings live under a single Setting key ('guest_alerts_config') as
 * one JSON blob, since this is one coherent settings unit rather than
 * several independent values.
 */
class GuestAlertService
{
    public const EVENTS = [
        'registration_received' => [
            'label' => 'Registration completed',
            'default_guest_message' => "Hi {guest_first_name}, thanks for registering for {property_name}. We're reviewing your details now and will let you know as soon as the next step is ready.",
            'default_staff_message' => 'New registration submitted for {property_name} by {guest_name}. Review it in the admin panel.',
        ],
        'background_check_complete' => [
            'label' => 'Background check complete',
            'default_guest_message' => "Hi {guest_first_name}, your {step_name} for {property_name} is complete. Your incidentals hold is now ready. Visit your check-in link to see the amount and complete payment.",
            'default_staff_message' => "{guest_name}'s {step_name} for {property_name} came back complete.",
        ],
        'fully_approved' => [
            'label' => 'Fully approved',
            'default_guest_message' => "Hi {guest_first_name}, you're fully approved for {property_name}. Check-in: {check_in_time} on {check_in_date}. Check-out: {check_out_time} on {check_out_date}. Parking: {parking_status}.",
            'default_staff_message' => '{guest_name} was just marked fully approved for {property_name}. Check-in {check_in_date} at {check_in_time}, check-out {check_out_date} at {check_out_time}. Parking: {parking_status}.',
        ],
        'time_to_check_in' => [
            'label' => 'Time to check in',
            'default_guest_message' => "Hi {guest_first_name}, today's the day. Check-in at {property_name} opens at {check_in_time}.",
            'default_staff_message' => '{guest_name} is due to check in today at {property_name}, opening at {check_in_time}. Make sure the unit is ready.',
        ],
        'checkin_ready' => [
            'label' => 'Unit ready to check in',
            'default_guest_message' => "Hi {guest_first_name}, your unit at {property_name} is ready. Visit your check-in link to complete check-in and unlock your guide.",
            'default_staff_message' => "{guest_name}'s unit at {property_name} is ready and they have been approved to check in.",
        ],
        'checkin_completed' => [
            'label' => 'Check-in completed',
            'default_guest_message' => 'Hi {guest_first_name}, your check-in at {property_name} is complete. Enjoy your stay.',
            'default_staff_message' => 'Check-in has been completed and {guest_name} is successfully checked into {property_name}.',
        ],
        'checkout_reminder' => [
            'label' => 'Check-out reminder',
            'default_guest_message' => 'Hi {guest_first_name}, check-out for {property_name} is available tomorrow at {check_out_time}. Please wait until you are fully ready before starting check-out.',
            'default_staff_message' => 'Reminder: {guest_name} checks out of {property_name} tomorrow at {check_out_time}.',
        ],
        'checkout_completed' => [
            'label' => 'Check-out completed',
            'default_guest_message' => 'Hi {guest_first_name}, thanks for staying at {property_name}. You are now checked out. Safe travels.',
            'default_staff_message' => '{guest_name} has just checked out of {property_name}. The unit is ready for turnover.',
        ],
        'deposit_paid' => [
            'label' => 'Deposit paid',
            'default_guest_message' => 'Hi {guest_first_name}, your incidentals deposit for {property_name} has been received. Thanks.',
            'default_staff_message' => '{guest_name} paid their incidentals deposit online for {property_name}.',
        ],
        'early_checkin_granted' => [
            'label' => 'Early check-in granted',
            'default_guest_message' => 'Hi {guest_first_name}, your early check-in request for {property_name} has been approved. Please visit your check-in link to complete payment.',
            'default_staff_message' => 'Early check-in was granted for {guest_name} at {property_name}.',
        ],
        'late_checkout_granted' => [
            'label' => 'Late checkout granted',
            'default_guest_message' => 'Hi {guest_first_name}, your late checkout request for {property_name} has been approved.',
            'default_staff_message' => 'Late checkout was granted for {guest_name} at {property_name}.',
        ],
        'checkin_time_approved' => [
            'label' => 'Requested check-in time approved',
            'default_guest_message' => 'Hi {guest_first_name}, your requested check-in time of {requested_time} for {property_name} has been approved.',
            'default_staff_message' => "{guest_name}'s requested check-in time of {requested_time} for {property_name} was approved.",
        ],
        'checkin_time_denied' => [
            'label' => 'Requested check-in time denied',
            'default_guest_message' => 'Hi {guest_first_name}, we are not able to accommodate your requested check-in time of {requested_time} for {property_name}. Standard check-in remains {check_in_time}.',
            'default_staff_message' => "{guest_name}'s requested check-in time of {requested_time} for {property_name} was denied.",
        ],
        'checkout_time_approved' => [
            'label' => 'Requested check-out time approved',
            'default_guest_message' => 'Hi {guest_first_name}, your requested check-out time of {requested_time} for {property_name} has been approved.',
            'default_staff_message' => "{guest_name}'s requested check-out time of {requested_time} for {property_name} was approved.",
        ],
        'checkout_time_denied' => [
            'label' => 'Requested check-out time denied',
            'default_guest_message' => 'Hi {guest_first_name}, we are not able to accommodate your requested check-out time of {requested_time} for {property_name}. Standard check-out remains {check_out_time}.',
            'default_staff_message' => "{guest_name}'s requested check-out time of {requested_time} for {property_name} was denied.",
        ],
        'post_checkout_balance_due' => [
            'label' => 'Post-checkout charge recorded',
            'default_guest_message' => 'Thanks for your stay at {property_name}, {guest_first_name}. We hope you had a great time.',
            'default_staff_message' => 'A post-checkout charge (late checkout / incidentals) was recorded for {guest_name} at {property_name} -- refund handled offline.',
        ],
        'pms_booking_received' => [
            'label' => 'New channel booking received',
            'default_guest_message' => '', // guest not notified for this event; see defaultToggleOverrides()
            'default_staff_message' => 'New booking for {guest_name} at {property_name} ({check_in_date} to {check_out_date}) came in via the channel manager. Review it in the admin panel.',
        ],
        'pms_booking_cancelled' => [
            'label' => 'Channel booking cancelled',
            'default_guest_message' => '', // guest not notified for this event; see defaultToggleOverrides()
            'default_staff_message' => 'The booking for {guest_name} at {property_name} ({check_in_date} to {check_out_date}) was cancelled on the channel manager.',
        ],
        'pms_booking_updated' => [
            'label' => 'Channel booking updated',
            'default_guest_message' => '', // guest not notified for this event; see defaultToggleOverrides()
            'default_staff_message' => 'The booking for {guest_name} at {property_name} was updated via the channel manager -- now {check_in_date} to {check_out_date}. Review it in the admin panel.',
        ],
        'photo_id_uploaded' => [
            'label' => 'Photo ID uploaded',
            'default_guest_message' => 'Hi {guest_first_name}, your photo ID for {property_name} was received and is being reviewed. No action needed for now.',
            'default_staff_message' => '{guest_name} uploaded a photo ID for {property_name}. Please log in and review it.',
        ],
        'photo_id_declined' => [
            'label' => 'Photo ID declined',
            'default_guest_message' => 'Hi {guest_first_name}, the {id_side} of your ID for {property_name} was not approved. Reason: {decline_reason}. Please log back in to re-upload it.',
            'default_staff_message' => 'The {id_side} of {guest_name}\'s ID for {property_name} was declined. Reason: {decline_reason}. Guest has been asked to re-upload.',
        ],
        'photo_id_expired' => [
            'label' => 'Photo ID expired',
            'default_guest_message' => 'Hi {guest_first_name}, the ID you uploaded for {property_name} appears to be expired. Please log back in and upload a current, unexpired government ID so we can finish your check-in.',
            'default_staff_message' => 'The ID {guest_name} uploaded for {property_name} appears to be expired. They have been asked to re-upload a valid ID.',
        ],
    ];

    /**
     * Roles that can be individually toggled as alert recipients, in
     * addition to the guest and the Contact desk.
     */
    public const STAFF_ROLES = ['owner', 'manager', 'staff', 'viewer'];

    /**
     * All recipient sources a toggle can exist for: the guest, the Contact
     * desk, and each staff role.
     */
    public const RECIPIENT_SOURCES = ['guest', 'contact', 'owner', 'manager', 'staff', 'viewer'];

    /**
     * Default toggle state for an event when nothing has been configured
     * yet. Guest, Contact desk, and Owner are on by default so a fresh
     * install notifies the guest and the primary staff contact out of the
     * box; Manager/Staff/Viewer are opt-in since not every install wants
     * every role paged for every event.
     */
    public static function defaultToggles(): array
    {
        $toggles = [];

        foreach (self::RECIPIENT_SOURCES as $source) {
            $onByDefault = in_array($source, ['guest', 'contact', 'owner'], true);
            $toggles["{$source}_sms"] = $onByDefault;
            $toggles["{$source}_email"] = $onByDefault;
        }

        return $toggles;
    }

    /**
     * Per-event overrides of defaultToggles(), for events that shouldn't use
     * the global default. Photo ID uploads are primarily a staff review
     * step, so guest notifications default off while staff stay on.
     */
    public static function defaultToggleOverrides(): array
    {
        return [
            'pms_booking_received' => [
                'guest_sms' => false,
                'guest_email' => false,
                'contact_sms' => true,
                'contact_email' => true,
                'owner_sms' => true,
                'owner_email' => true,
            ],
            'pms_booking_cancelled' => [
                'guest_sms' => false,
                'guest_email' => false,
                'contact_sms' => true,
                'contact_email' => true,
                'owner_sms' => true,
                'owner_email' => true,
            ],
            'pms_booking_updated' => [
                'guest_sms' => false,
                'guest_email' => false,
                'contact_sms' => true,
                'contact_email' => true,
                'owner_sms' => true,
                'owner_email' => true,
            ],
            'photo_id_uploaded' => [
                'guest_sms' => false,
                'guest_email' => false,
                'contact_sms' => true,
                'contact_email' => true,
                'owner_sms' => true,
                'owner_email' => true,
            ],
            'photo_id_declined' => [
                'guest_sms' => true,
                'guest_email' => true,
                'contact_sms' => false,
                'contact_email' => false,
                'owner_sms' => false,
                'owner_email' => false,
            ],
        ];
    }

    public static function config(): array
    {
        $stored = json_decode(Setting::getValue('guest_alerts_config', '') ?: '', true) ?: [];
        $config = [];
        $overrides = self::defaultToggleOverrides();

        foreach (self::EVENTS as $key => $meta) {
            $row = self::migrateLegacyRow($stored[$key] ?? []);

            $config[$key] = array_merge(
                [
                    'guest_message' => $meta['default_guest_message'],
                    'staff_message' => $meta['default_staff_message'],
                ],
                self::defaultToggles(),
                $overrides[$key] ?? [],
                $row
            );
        }

        return $config;
    }

    /**
     * Map an older stored row (single "message" shared by guest and staff,
     * and "admin_sms"/"admin_email" instead of per-role toggles) onto the
     * current shape. Only guest_message is carried over from the old
     * "message" field, since that's what an admin actually saw and
     * customized in the old single-textarea form; staff_message is left
     * unset here so config() falls back to the new, distinct staff default
     * rather than duplicating the guest wording onto staff.
     */
    protected static function migrateLegacyRow(array $row): array
    {
        if (isset($row['message']) && ! isset($row['guest_message'])) {
            $row['guest_message'] = $row['message'];
        }
        unset($row['message']);

        if (isset($row['admin_sms']) && ! isset($row['contact_sms'])) {
            $row['contact_sms'] = $row['admin_sms'];
        }
        if (isset($row['admin_email']) && ! isset($row['contact_email'])) {
            $row['contact_email'] = $row['admin_email'];
        }
        unset($row['admin_sms'], $row['admin_email']);

        return $row;
    }

    /**
     * Event labels for display, with the "background check" step's name
     * substituted in wherever it's admin-customizable.
     */
    public static function labels(): array
    {
        $stepName = Setting::getValue('background_check_step_name', 'Background Check');
        $labels = [];

        foreach (self::EVENTS as $key => $meta) {
            $labels[$key] = $key === 'background_check_complete'
                ? "{$stepName} complete"
                : $meta['label'];
        }

        return $labels;
    }

    public static function putConfig(array $config): void
    {
        $clean = [];

        foreach (self::EVENTS as $key => $meta) {
            $row = $config[$key] ?? [];
            $clean[$key] = [
                'guest_message' => trim((string) ($row['guest_message'] ?? $meta['default_guest_message'])) ?: $meta['default_guest_message'],
                'staff_message' => trim((string) ($row['staff_message'] ?? $meta['default_staff_message'])) ?: $meta['default_staff_message'],
            ];

            foreach (self::RECIPIENT_SOURCES as $source) {
                $clean[$key]["{$source}_sms"] = (bool) ($row["{$source}_sms"] ?? false);
                $clean[$key]["{$source}_email"] = (bool) ($row["{$source}_email"] ?? false);
            }
        }

        Setting::putValue('guest_alerts_config', json_encode($clean));
    }

    /**
     * Send the alert for a given lifecycle event, per the current settings.
     * Safe to call even if the event key is unknown (no-op) so call sites
     * never need to guard against typos causing a hard failure.
     *
     * $extraTokens lets a call site fill in event-specific placeholders that
     * aren't derived from the booking itself, e.g. {id_side} and
     * {decline_reason} for photo_id_declined.
     */
    public static function send(string $event, Booking $booking, array $extraTokens = []): void
    {
        if (! array_key_exists($event, self::EVENTS)) {
            return;
        }

        $label = self::labels()[$event];
        $row = self::config()[$event];
        $appName = (string) config('app.name');

        // The email already brands itself (header + footer), so a leading
        // app-name prefix on the stored wording is redundant there. SMS keeps
        // it, since a text only shows a number with no sender display name.
        $guestMessage = self::stripSenderPrefix(self::render($row['guest_message'], $booking, $extraTokens), $appName);
        $staffMessage = self::stripSenderPrefix(self::render($row['staff_message'], $booking, $extraTokens), $appName);

        // Subject identifies the guest, never the app (the sender already
        // shows the app name).
        $subject = trim($booking->guest_name.' '.$label);

        if ($row['guest_sms'] && $booking->phone) {
            SmsNotificationService::guestAlert($booking->phone, self::withSenderPrefix($guestMessage, $appName), true);
        }

        if ($row['guest_email']) {
            if ($booking->email) {
                try {
                    Mail::to($booking->email)->send(new GuestAlertMail($label, $guestMessage, $subject, $booking->property?->name));
                    Log::info("Guest alert email sent (guest, {$event}) to {$booking->email}.");
                } catch (\Throwable $e) {
                    Log::error("Guest alert email failed (guest, {$event}): ".$e->getMessage());
                }
            } else {
                Log::warning("Guest alert guest email skipped ({$event}): guest_email is enabled but booking {$booking->booking_id} has no email on file.");
            }
        }

        [$staffPhones, $staffEmails] = self::staffRecipients($row);

        foreach ($staffPhones as $phone) {
            SmsNotificationService::guestAlert($phone, self::withSenderPrefix($staffMessage, $appName), false);
        }

        if (! empty($staffEmails)) {
            foreach ($staffEmails as $recipient) {
                try {
                    Mail::to($recipient)->send(new GuestAlertMail($label, $staffMessage, $subject));
                    Log::info("Guest alert email sent (staff, {$event}) to {$recipient}.");
                } catch (\Throwable $e) {
                    Log::error("Guest alert email failed (staff, {$event}, {$recipient}): ".$e->getMessage());
                }
            }
        } elseif (self::anyStaffEmailEnabled($row)) {
            Log::warning("Guest alert staff email skipped ({$event}): a staff email toggle is enabled but none of the enabled recipients have an email on file.");
        }
    }

    /**
     * Resolve the deduped set of staff phone numbers and emails to notify
     * for this event, drawing from the Contact desk (Settings > General)
     * and each individually-toggled role's own users.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected static function staffRecipients(array $row): array
    {
        $phones = collect();
        $emails = collect();

        if ($row['contact_sms'] ?? false) {
            // The .env Telnyx admin number is kept as a legacy fallback
            // recipient alongside the Contact desk toggle, for installs that
            // never moved their admin number into Settings.
            $phones->push(self::normalizePhoneForSms(Setting::getValue('contact_phone')));
            $phones->push(config('services.telnyx.admin_notify_number'));
        }
        if ($row['contact_email'] ?? false) {
            $emails->push(Setting::getValue('contact_email'));
        }

        foreach (self::STAFF_ROLES as $role) {
            $smsOn = $row["{$role}_sms"] ?? false;
            $emailOn = $row["{$role}_email"] ?? false;

            if (! $smsOn && ! $emailOn) {
                continue;
            }

            $users = User::where('role', $role)->get(['phone', 'email']);

            if ($smsOn) {
                foreach ($users as $user) {
                    $phones->push(self::normalizePhoneForSms($user->phone ?? null));
                }
            }
            if ($emailOn) {
                foreach ($users as $user) {
                    $emails->push($user->email);
                }
            }
        }

        return [
            $phones->filter()->unique()->values()->all(),
            $emails->filter()->unique()->values()->all(),
        ];
    }

    protected static function anyStaffEmailEnabled(array $row): bool
    {
        if ($row['contact_email'] ?? false) {
            return true;
        }

        foreach (self::STAFF_ROLES as $role) {
            if ($row["{$role}_email"] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip formatting characters (spaces, dashes, parens) from a phone number
     * pulled from Settings/users so it's in a Telnyx-friendly format. Returns
     * null if the input is empty so callers can skip sending cleanly.
     */
    protected static function normalizePhoneForSms(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $number);

        return $normalized ?: null;
    }

    /**
     * Remove a leading app-name prefix (e.g. "Guest Hub: " or "Guest Hub
     * alert: ") from a stored message so the email doesn't repeat the brand
     * its own header/footer already show. Tolerant of the name being written
     * with or without a space ("GuestHub" vs "Guest Hub").
     */
    protected static function stripSenderPrefix(string $message, string $appName): string
    {
        $appName = trim($appName);

        if ($appName === '') {
            return $message;
        }

        $name = str_replace(' ', '\s*', preg_quote($appName, '/'));
        $pattern = '/^\s*'.$name.'(?:\s+alert)?\s*[:\-\x{2013}\x{2014}]\s*/iu';

        return (string) preg_replace($pattern, '', $message, 1);
    }

    /**
     * Prefix a rendered message with the app name for SMS, where there is no
     * sender display name to identify us. Applied after stripSenderPrefix()
     * so it never doubles up.
     */
    protected static function withSenderPrefix(string $message, string $appName): string
    {
        $appName = trim($appName);

        return $appName !== '' ? "{$appName}: {$message}" : $message;
    }

    protected static function render(string $template, Booking $booking, array $extraTokens = []): string
    {
        $parkingStatus = is_null($booking->parking_needed)
            ? 'not yet specified'
            : ($booking->parking_needed ? 'confirmed' : 'not needed');

        $tokens = [
            '{guest_name}' => $booking->guest_name,
            '{guest_first_name}' => trim(explode(' ', trim($booking->guest_name))[0] ?? '') ?: $booking->guest_name,
            '{property_name}' => $booking->property?->name ?? 'your property',
            '{check_in_date}' => $booking->check_in_date?->format('M j, Y') ?? '',
            '{check_out_date}' => $booking->check_out_date?->format('M j, Y') ?? '',
            '{check_in_time}' => $booking->effectiveCheckinTimeFormatted(),
            '{check_out_time}' => $booking->effectiveCheckoutTimeFormatted(),
            '{parking_status}' => $parkingStatus,
            '{step_name}' => Setting::getValue('background_check_step_name', 'Background Check'),
        ];

        foreach ($extraTokens as $key => $value) {
            $tokens['{'.$key.'}'] = $value;
        }

        return strtr($template, $tokens);
    }
}

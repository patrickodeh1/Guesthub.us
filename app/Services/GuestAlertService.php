<?php

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Mail\GuestAlertMail;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Carbon\Carbon;
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
        'vehicle_plate_submitted' => [
            'label' => 'Vehicle photo submitted',
            'default_guest_message' => '',
            'default_staff_message' => '{guest_name} submitted a vehicle photo for {property_name}. Plate: {license_plate} {license_plate_state}. Review it: {admin_link}',
        ],
        'registration_received' => [
            'label' => 'Registration completed',
            'default_guest_message' => "Hi {guest_first_name}, thanks for registering for {property_name}. We're reviewing your details now and will let you know as soon as the next step is ready.",
            'default_staff_message' => 'New registration submitted for {property_name} by {guest_name}. Review it: {admin_link}',
        ],
        'background_check_complete' => [
            'label' => 'Background check complete',
            'default_guest_message' => "Hi {guest_first_name}, your {step_name} for {property_name} is complete. Your incidentals hold is now ready. Visit your check-in link to see the amount and complete payment: {guest_link}",
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
            'default_guest_message' => "Hi {guest_first_name}, your unit at {property_name} is ready. Visit your check-in link to complete check-in and unlock your guide: {guest_link}",
            'default_staff_message' => "{guest_name}'s unit at {property_name} is ready and they have been approved to check in.",
        ],
        'checkin_completed' => [
            'label' => 'Check-in completed',
            'default_guest_message' => 'Hi {guest_first_name}, your check-in at {property_name} is complete. Enjoy your stay.',
            'default_staff_message' => 'Check-in has been completed and {guest_name} is successfully checked into {property_name}.',
        ],
        'checkout_soon' => [
            'label' => 'Check-out in one hour',
            'default_guest_message' => "Hi {guest_first_name}, check-out from {property_name} is at {check_out_time}, about an hour from now. Please be sure to fully check out here: {guest_link}. Late check-out fees apply after that time.",
            'default_staff_message' => 'Reminder: {guest_name} checks out of {property_name} in about an hour, at {check_out_time}.',
        ],
        'checkout_overdue' => [
            'label' => 'Check-out time passed',
            'default_guest_message' => "Hi {guest_first_name}, check-out from {property_name} was at {check_out_time}. Late check-out fees now apply. If you have already left, please confirm your check-out here: {guest_link}",
            'default_staff_message' => '{guest_name} has not checked out of {property_name}. The check-out time was {check_out_time} and late check-out fees now apply.',
        ],
        'checkout_reminder' => [
            'label' => 'Check-out reminder',
            'default_guest_message' => "Hi {guest_first_name}, a quick reminder that check-out from {property_name} is tomorrow, {check_out_date}, at {check_out_time}. When you're ready to leave, you can complete check-out here: {guest_link}",
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
            'label' => 'Early check-in approved (fee/payment step)',
            'default_guest_message' => 'Hi {guest_first_name}, your early check-in request for {property_name} has been approved. Please visit your check-in link to complete payment: {guest_link}',
            'default_staff_message' => 'Early check-in was granted for {guest_name} at {property_name}.',
        ],
        'late_checkout_granted' => [
            'label' => 'Late check-out approved (fee/payment step)',
            'default_guest_message' => 'Hi {guest_first_name}, your late checkout request for {property_name} has been approved.',
            'default_staff_message' => 'Late checkout was granted for {guest_name} at {property_name}.',
        ],
        'checkin_time_approved' => [
            'label' => 'Requested arrival time approved',
            'default_guest_message' => 'Hi {guest_first_name}, your requested check-in time of {requested_time} for {property_name} has been approved.',
            'default_staff_message' => "{guest_name}'s requested check-in time of {requested_time} for {property_name} was approved.",
        ],
        'checkin_time_denied' => [
            'label' => 'Requested check-in time denied',
            'default_guest_message' => 'Hi {guest_first_name}, we are not able to accommodate your requested check-in time of {requested_time} for {property_name}. Standard check-in remains {check_in_time}.',
            'default_staff_message' => "{guest_name}'s requested check-in time of {requested_time} for {property_name} was denied.",
        ],
        'checkout_time_approved' => [
            'label' => 'Requested departure time approved',
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
        'cleaning_complete' => [
            'label' => 'Cleaning complete (same-day check-in guest)',
            'default_guest_message' => 'Hi {guest_first_name}, the cleaning at {property_name} is complete. We are doing a final check and will message you as soon as your unit is ready for check-in.',
            'default_staff_message' => '',
        ],
        'reservation_updated' => [
            'label' => 'Reservation details updated (times)',
            'default_guest_message' => 'Your reservation details have been updated. Check in {check_in_time} {check_in_date}. Check out {check_out_time} {check_out_date}.',
            'default_staff_message' => '',
        ],
        'photo_id_resubmitted' => [
            'label' => 'Photo ID re-uploaded (2nd+ attempt)',
            'default_guest_message' => '',
            'default_staff_message' => '{guest_name} re-uploaded their ID for {property_internal_name}. Review it: {admin_link}',
        ],
        'registration_reminder' => [
            'label' => 'Daily registration reminder',
            'default_guest_message' => 'Hi {guest_first_name}, {pending_text} {guest_link}',
            'default_staff_message' => '',
        ],
        'arrival_soon' => [
            'label' => 'Guest arriving within the hour',
            'default_guest_message' => '',
            'default_staff_message' => '{guest_name} is due to arrive at {property_internal_name} around {check_in_time} (in about {arrives_in}). Be on standby.',
        ],
        'photo_id_uploaded' => [
            'label' => 'Photo ID uploaded',
            'default_guest_message' => 'Hi {guest_first_name}, your photo ID for {property_name} was received and is being reviewed. No action needed for now.',
            'default_staff_message' => '{guest_name} uploaded a photo ID for {property_name}. Review it: {admin_link}',
        ],
        'photo_id_declined' => [
            'label' => 'Photo ID declined',
            'default_guest_message' => 'Hi {guest_first_name}, the {id_side} of your ID for {property_name} was not approved. Reason: {decline_reason}. Please re-upload it here: {guest_link}',
            'default_staff_message' => 'The {id_side} of {guest_name}\'s ID for {property_name} was declined. Reason: {decline_reason}. Guest has been asked to re-upload.',
        ],
        'photo_id_expired' => [
            'label' => 'Photo ID expired',
            'default_guest_message' => 'Hi {guest_first_name}, the ID you uploaded for {property_name} appears to be expired. Please upload a current, unexpired government ID so we can finish your check-in: {guest_link}',
            'default_staff_message' => 'The ID {guest_name} uploaded for {property_name} appears to be expired. They have been asked to re-upload a valid ID.',
        ],
    ];

    /**
     * Roles that can be individually toggled as alert recipients, in
     * addition to the guest and the Contact desk.
     */
    public const STAFF_ROLES = ['admin', 'company', 'owner', 'manager', 'housekeeper'];

    /**
     * All recipient sources a toggle can exist for: the guest, the Contact
     * desk, and each staff role.
     */
    public const RECIPIENT_SOURCES = ['guest', 'contact', 'admin', 'company', 'owner', 'manager', 'housekeeper'];

    /**
     * Default toggle state for an event when nothing has been configured
     * yet. Guest, Contact desk, Admin, and Owner are on by default so a fresh
     * install notifies the guest and primary staff contacts out of the box;
     * other roles are opt-in since not every install wants every role paged
     * for every event.
     */
    public static function defaultToggles(): array
    {
        $toggles = [];

        foreach (self::RECIPIENT_SOURCES as $source) {
            $onByDefault = in_array($source, ['guest', 'contact', 'admin', 'owner'], true);
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
            'vehicle_plate_submitted' => [
                'guest_sms' => false,
                'guest_email' => false,
                'contact_sms' => true,
                'contact_email' => true,
                'owner_sms' => false,
                'owner_email' => false,
            ],
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

    public static function config($ownerId = null): array
    {
        $stored = json_decode(Setting::getValue('guest_alerts_config', '') ?: '', true) ?: [];
        $own = $ownerId ? (json_decode(Setting::getValue(self::settingKey($ownerId), '') ?: '', true) ?: []) : [];
        $config = [];
        $overrides = self::defaultToggleOverrides();

        foreach (self::EVENTS as $key => $meta) {
            $row = self::migrateLegacyRow($own[$key] ?? ($stored[$key] ?? []));

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

        // Only convert genuinely old rows (admin_* present, contact_* absent).
        // Current-format rows keep their saved admin_* values.
        if (isset($row['admin_sms']) && ! isset($row['contact_sms'])) {
            $row['contact_sms'] = $row['admin_sms'];
            unset($row['admin_sms']);
        }
        if (isset($row['admin_email']) && ! isset($row['contact_email'])) {
            $row['contact_email'] = $row['admin_email'];
            unset($row['admin_email']);
        }

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

    public static function settingKey($ownerId = null): string
    {
        $ownerId = $ownerId ? (int) $ownerId : 0;

        return $ownerId ? "guest_alerts_config_o{$ownerId}" : 'guest_alerts_config';
    }

    public static function putConfig(array $config, $ownerId = null): void
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

        if ($ownerId) {
            // Owners store only events that differ from the global config, so they keep inheriting the rest.
            $pick = function (array $r): array {
                $o = ['guest_message' => trim((string) ($r['guest_message'] ?? '')), 'staff_message' => trim((string) ($r['staff_message'] ?? ''))];
                foreach (self::RECIPIENT_SOURCES as $s) {
                    $o["{$s}_sms"] = (bool) ($r["{$s}_sms"] ?? false);
                    $o["{$s}_email"] = (bool) ($r["{$s}_email"] ?? false);
                }

                return $o;
            };
            $global = self::config();
            $store = [];
            foreach ($clean as $k => $r) {
                if ($pick($r) != $pick($global[$k] ?? [])) {
                    $store[$k] = $r;
                }
            }
            Setting::putValue(self::settingKey($ownerId), json_encode($store));

            return;
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
        $ownerId = $booking->property?->owner_id;
        $row = self::config($ownerId)[$event];
        $appName = Branding::siteName();

        // The email already brands itself (header + footer), so a leading
        // app-name prefix on the stored wording is redundant there. SMS keeps
        // it, since a text only shows a number with no sender display name.
        $guestMessage = self::stripSenderPrefix(self::render($row['guest_message'], $booking, $extraTokens), $appName);
        $staffMessage = self::stripSenderPrefix(self::render($row['staff_message'], $booking, $extraTokens, true), $appName);

        // Subject identifies the guest, never the app (the sender already
        // shows the app name).
        $subject = trim($booking->guest_name.' '.$label);

        if (trim($guestMessage) !== '' && $row['guest_sms'] && $booking->phone) {
            SmsNotificationService::guestAlert($booking->phone, self::withSenderPrefix($guestMessage, $appName), true);
        }

        if (trim($guestMessage) !== '' && $row['guest_email']) {
            if ($booking->email) {
                try {
                    Mail::to($booking->email)->send(new GuestAlertMail($label, $guestMessage, $subject, $booking->property?->guest_display_name));
                    Log::info("Guest alert email sent (guest, {$event}) to {$booking->email}.");
                } catch (\Throwable $e) {
                    Log::error("Guest alert email failed (guest, {$event}): ".$e->getMessage());
                }
            } else {
                Log::warning("Guest alert guest email skipped ({$event}): guest_email is enabled but booking {$booking->booking_id} has no email on file.");
            }
        }

        [$staffPhones, $staffEmails] = trim($staffMessage) !== '' ? self::staffRecipients($row, $booking->property) : [[], []];

        $staffTz = self::staffTimezones();

        foreach ($staffPhones as $phone) {
            $msg = self::stripSenderPrefix(self::render($row['staff_message'], $booking, $extraTokens, true, $staffTz['phones'][$phone] ?? null), $appName);
            SmsNotificationService::guestAlert($phone, self::withSenderPrefix($msg, $appName), false);
        }

        if (! empty($staffEmails)) {
            foreach ($staffEmails as $recipient) {
                try {
                    $emailMessage = self::stripSenderPrefix(self::render($row['staff_message'], $booking, $extraTokens, true, $staffTz['emails'][strtolower((string) $recipient)] ?? null), $appName);
                    Mail::to($recipient)->send(new GuestAlertMail($label, $emailMessage, $subject));
                    Log::info("Guest alert email sent (staff, {$event}) to {$recipient}.");
                } catch (\Throwable $e) {
                    Log::error("Guest alert email failed (staff, {$event}, {$recipient}): ".$e->getMessage());
                }
            }
        } elseif (trim($staffMessage) !== '' && self::anyStaffEmailEnabled($row)) {
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
    protected static function staffRecipients(array $row, ?\App\Models\Property $property = null): array
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

            if (! \Spatie\Permission\Models\Role::where('name', $role)->exists()) {
                continue; // role not created on this install
            }

            $users = self::usersForRole($role, $property);

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

    /** Only the people linked to this property's account, never every user with the role. */
    protected static function usersForRole(string $role, ?\App\Models\Property $property)
    {
        if (! $property || $role === 'admin') {
            return User::role($role)->get(['phone', 'email']);
        }

        $ownerId = (int) $property->owner_id;
        $db = \Illuminate\Support\Facades\DB::class;
        $linked = fn () => $db::table('housekeeper_owner')->where('owner_id', $ownerId)->pluck('housekeeper_id');

        if ($role === 'owner') {
            return $ownerId ? User::role('owner')->whereKey($ownerId)->get(['phone', 'email']) : collect();
        }
        if ($role === 'company') {
            if (! $ownerId) {
                return collect();
            }
            $parent = User::query()->whereKey($ownerId)->value('owner_id');
            $ids = collect([$ownerId, $parent])->merge($linked())->filter()->unique()->values();

            return User::role('company')->whereIn('id', $ids)->get(['phone', 'email']);
        }
        if ($role === 'manager') {
            $ids = $db::table('property_user')->where('property_id', $property->id)->pluck('user_id');

            return User::role('manager')->whereIn('id', $ids)->get(['phone', 'email']);
        }
        if (! $ownerId) {
            return collect();
        }
        $ids = $linked()->merge(User::query()->where('owner_id', $ownerId)->pluck('id'))->unique()->values();

        return User::role($role)->whereIn('id', $ids)->get(['phone', 'email']);
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

        return $message; // sender prefix removed on request
    }

    protected static function guestLink(Booking $booking): string
    {
        try {
            if (\Illuminate\Support\Facades\Route::has('guest.show')) {
                return $booking->publicUrl();
            }
        } catch (\Throwable $e) {
            Log::warning('guestLink failed: '.$e->getMessage());
        }
        return url('/');
    }

    protected static function adminLink(Booking $booking): string
    {
        try {
            if (\Illuminate\Support\Facades\Route::has('admin.guests.show')) {
                return route('admin.guests.show', $booking);
            }
        } catch (\Throwable $e) {
            Log::warning('adminLink failed: '.$e->getMessage());
        }
        return url('/');
    }

    /**
     * Saved timezones of staff users, keyed by normalized phone and by
     * lowercase email, so each staff message can show the recipient's own time.
     *
     * @return array{phones: array<string, string>, emails: array<string, string>}
     */
    protected static function staffTimezones(): array
    {
        $phones = [];
        $emails = [];

        try {
            User::query()->whereNotNull('timezone')->where('timezone', '!=', '')
                ->get(['phone', 'email', 'timezone'])
                ->each(function ($u) use (&$phones, &$emails) {
                    if (! TimezoneHelper::isValid($u->timezone)) {
                        return;
                    }
                    if ($p = self::normalizePhoneForSms($u->phone)) {
                        $phones[$p] = $u->timezone;
                    }
                    if ($u->email) {
                        $emails[strtolower($u->email)] = $u->timezone;
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('staffTimezones failed: '.$e->getMessage());
        }

        return ['phones' => $phones, 'emails' => $emails];
    }

    /** Property-time label for staff, plus the recipient's own time when it differs. */
    protected static function staffTime(Booking $booking, string $which, ?string $viewerTz): string
    {
        $plain = $which === 'in' ? $booking->effectiveCheckinTimeFormatted() : $booking->effectiveCheckoutTimeFormatted();

        try {
            $date = $which === 'in' ? $booking->check_in_date : $booking->check_out_date;
            if (! $date) {
                return $plain;
            }

            $time = $which === 'in' ? $booking->effectiveCheckinTime() : $booking->effectiveCheckoutTime();
            $tz = $booking->property?->timezone;
            if (! TimezoneHelper::isValid($tz)) {
                $tz = config('app.display_timezone') ?: config('app.timezone');
            }

            return TimezoneHelper::dualTime(Carbon::parse($date->toDateString().' '.$time, $tz), $viewerTz);
        } catch (\Throwable $e) {
            return $plain;
        }
    }

    protected static function render(string $template, Booking $booking, array $extraTokens = [], bool $staff = false, ?string $viewerTz = null): string
    {
        $parkingStatus = is_null($booking->parking_needed)
            ? 'not yet specified'
            : ($booking->parking_needed ? 'confirmed' : 'not needed');

        $tokens = [
            '{guest_name}' => $booking->guest_name,
            '{guest_first_name}' => trim(explode(' ', trim($booking->guest_name))[0] ?? '') ?: $booking->guest_name,
            '{property_name}' => $booking->property?->guest_display_name ?? 'your property',
            '{property_guest_name}' => $booking->property?->guest_display_name ?? 'your property',
            '{property_internal_name}' => $booking->property?->internal_display_name ?? 'the property',
            '{guest_link}' => self::guestLink($booking),
            '{admin_link}' => self::adminLink($booking),
            '{check_in_date}' => $booking->check_in_date?->format('M j, Y') ?? '',
            '{check_out_date}' => $booking->check_out_date?->format('M j, Y') ?? '',
            '{check_in_time}' => $staff ? self::staffTime($booking, 'in', $viewerTz) : $booking->effectiveCheckinTimeFormatted(),
            '{check_out_time}' => $staff ? self::staffTime($booking, 'out', $viewerTz) : $booking->effectiveCheckoutTimeFormatted(),
            '{parking_status}' => $parkingStatus,
            '{step_name}' => Setting::getValue('background_check_step_name', 'Background Check'),
            '{license_plate}' => $booking->license_plate ?: 'not read',
            '{license_plate_state}' => $booking->license_plate_state ?: '',
        ];

        foreach ($extraTokens as $key => $value) {
            $tokens['{'.$key.'}'] = $value;
        }

        return strtr($template, $tokens);
    }
}

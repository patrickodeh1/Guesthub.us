<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CleaningSession;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Connects Guest Hub bookings to cleaning sessions: auto-creates the checkout
 * clean, tags it to the guest, texts the cleaner and handles YES/NO replies,
 * and triggers the "unit ready" guest alert when a same-day clean finishes.
 * Every public method is exception-safe so a failure never breaks the caller.
 */
class CleaningFlowService
{
    private const DEAD_STATUSES = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];

    /** One checkout clean per booking, on the checkout date and time. */
    public static function syncForBooking(Booking $booking): ?CleaningSession
    {
        try {
            $property = $booking->property;
            if (! $property || ! $booking->check_out_date) {
                return null;
            }

            $session = CleaningSession::where('booking_id', $booking->id)->first();

            if ($booking->cancelled_at || in_array($booking->status, self::DEAD_STATUSES, true)) {
                if ($session && ! $session->started_at && $session->status !== 'completed') {
                    $session->update(['status' => 'cancelled']);
                }
                return $session;
            }

            $date = $booking->check_out_date->toDateString();
            if ($date < now(config('app.display_timezone'))->toDateString()) {
                return $session; // past checkout, leave history alone
            }

            $time = self::timeOrNull($booking->effectiveCheckoutTimeFormatted());

            if (! $session) {
                // Adopt a manually created clean for the same property/day instead of duplicating it.
                $manual = CleaningSession::where('property_id', $property->id)
                    ->whereDate('scheduled_date', $date)
                    ->whereNull('booking_id')->where('no_guest', false)
                    ->where('status', '<>', 'cancelled')->first();
                if ($manual) {
                    $manual->update(['booking_id' => $booking->id]);
                    return $manual;
                }

                if (! $property->owner_id) {
                    Log::warning("CleaningFlow: property {$property->id} has no owner, checkout session not created.");
                    return null;
                }

                return CleaningSession::create([
                    'property_id' => $property->id,
                    'owner_id' => $property->owner_id,
                    'housekeeper_id' => null,
                    'scheduled_date' => $date,
                    'scheduled_time' => $time,
                    'status' => 'pending',
                    'booking_id' => $booking->id,
                    'assignment_status' => 'unassigned',
                ]);
            }

            if (! $session->started_at && $session->status !== 'completed') {
                $dateMoved = $session->scheduled_date?->toDateString() !== $date;
                if ($dateMoved || $session->assignment_status === 'unassigned') {
                    $session->update(['scheduled_date' => $date, 'scheduled_time' => $time]);
                }
            }

            return $session;
        } catch (\Throwable $e) {
            Log::error('CleaningFlow sync failed: '.$e->getMessage());
            return null;
        }
    }

    /** Tag a manually created session to the departing (or current) guest. */
    public static function autoTag(CleaningSession $session): void
    {
        try {
            if ($session->booking_id || $session->no_guest) {
                return;
            }
            $date = $session->scheduled_date?->toDateString();
            if (! $date) {
                return;
            }
            $base = Booking::where('property_id', $session->property_id)->whereNull('cancelled_at')
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::DEAD_STATUSES));

            $booking = (clone $base)->whereDate('check_out_date', $date)->first()
                ?? (clone $base)->whereDate('check_in_date', '<=', $date)->whereDate('check_out_date', '>=', $date)->first();

            if ($booking) {
                $session->forceFill(['booking_id' => $booking->id])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::error('CleaningFlow autoTag failed: '.$e->getMessage());
        }
    }

    /** Text the cleaner their assignment and wait for YES/NO. */
    public static function notifyAssigned(CleaningSession $session): void
    {
        try {
            $user = $session->housekeeper;
            if (! $user) {
                return;
            }
            $phone = self::phoneFor($user);
            if (! $phone) {
                Log::info("CleaningFlow: cleaner {$user->id} has no phone, assignment text skipped.");
                return;
            }

            $p = $session->property;
            $when = $session->scheduled_date->format('D, M j').($session->scheduled_time ? ' at '.$session->scheduled_time->format('g:i A') : '');
            $msg = "New cleaning: {$p->internal_display_name} on {$when}. Reply YES to confirm or NO to decline.";

            $result = SmsNotificationService::deliver($phone, $msg, 'cleaning');
            NotificationLog::create([
                'property_id' => $session->property_id,
                'user_id' => $user->id,
                'cleaning_session_id' => $session->id,
                'notification_type' => 'cleaning_assignment',
                'recipient_phone' => $phone,
                'message_content' => $msg,
                'delivery_status' => $result['status'],
                'error_message' => $result['error'],
                'sent_at' => now(),
            ]);

            $session->forceFill(['assignment_status' => 'pending_confirmation', 'cleaner_notified_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('CleaningFlow notifyAssigned failed: '.$e->getMessage());
        }
    }

    /** Inbound SMS from a cleaner. Returns true when the message was a YES/NO we handled. */
    public static function handleCleanerReply(?string $phone, string $body): bool
    {
        try {
            $answer = strtoupper(preg_replace('/[^A-Za-z]/', '', $body));
            if (! in_array($answer, ['YES', 'Y', 'NO', 'N'], true)) {
                return false;
            }
            $digits = SmsConsentService::normalizePhone($phone);
            if ($digits === '') {
                return false;
            }

            $cols = array_values(array_filter(['phone', 'phone_number'], fn ($c) => Schema::hasColumn('users', $c)));
            $user = User::query()->get(array_merge(['id', 'name'], $cols))->first(
                fn ($u) => in_array($digits, array_map(fn ($c) => SmsConsentService::normalizePhone($u->{$c}), $cols), true)
            );
            if (! $user) {
                return false;
            }

            $session = CleaningSession::with('property')
                ->where('housekeeper_id', $user->id)
                ->where('assignment_status', 'pending_confirmation')
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->orderBy('scheduled_date')->first();
            if (! $session) {
                return false;
            }

            $name = $session->property->internal_display_name;
            $day = $session->scheduled_date->format('D, M j');

            if (in_array($answer, ['YES', 'Y'], true)) {
                $session->forceFill(['assignment_status' => 'confirmed'])->saveQuietly();
                SmsNotificationService::deliver($phone, "Confirmed: {$name} on {$day}. Thank you!", 'cleaning');
                return true;
            }

            $session->forceFill(['housekeeper_id' => null, 'assignment_status' => 'unassigned'])->saveQuietly();
            SmsNotificationService::deliver($phone, "Got it, you are off the cleaning at {$name} on {$day}.", 'cleaning');
            self::alertAdmins("{$user->name} declined the cleaning at {$name} on {$day}. It still needs a cleaner.");
            return true;
        } catch (\Throwable $e) {
            Log::error('CleaningFlow reply failed: '.$e->getMessage());
            return false;
        }
    }

    /** Cleaner finished: tell a guest arriving today that the unit is ready. */
    public static function onCleaningCompleted(CleaningSession $session): void
    {
        try {
            $today = now(config('app.display_timezone'))->toDateString();
            $booking = Booking::where('property_id', $session->property_id)
                ->whereDate('check_in_date', $today)->whereNull('cancelled_at')
                ->where('status', 'guest_approved')->first();
            if ($booking) {
                self::sendUnitReady($booking);
            }
        } catch (\Throwable $e) {
            Log::error('CleaningFlow onCleaningCompleted failed: '.$e->getMessage());
        }
    }

    /** The one place the "unit ready" guest alert is sent, so it never goes out twice. */
    public static function sendUnitReady(Booking $booking): void
    {
        if ($booking->unit_ready_sent_at) {
            return;
        }
        GuestAlertService::send('checkin_ready', $booking);
        $booking->forceFill(['unit_ready_sent_at' => now()])->saveQuietly();
    }

    private static function alertAdmins(string $message): void
    {
        $numbers = collect([Setting::getValue('contact_phone'), config('services.telnyx.admin_notify_number')])
            ->filter()->unique(fn ($n) => SmsConsentService::normalizePhone($n));
        foreach ($numbers as $n) {
            SmsNotificationService::deliver($n, $message, 'staff');
        }
    }

    private static function phoneFor(User $user): ?string
    {
        return $user->phone_number ?: ($user->phone ?: null);
    }

    private static function timeOrNull(?string $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

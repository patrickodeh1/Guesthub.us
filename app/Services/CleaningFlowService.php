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
                $newTime = $time ? \Carbon\Carbon::parse($time)->format('H:i') : null;
                $oldTime = $session->scheduled_time?->format('H:i');
                $changed = $session->scheduled_date?->toDateString() !== $date || $oldTime !== $newTime;

                if ($changed && ! $session->schedule_overridden) {
                    $session->update(['scheduled_date' => $date, 'scheduled_time' => $time]);
                    if ($session->housekeeper_id) {
                        self::notifyAssigned($session->fresh(), true);
                    }
                } elseif ($changed && $session->schedule_overridden && $session->housekeeper_id && $newTime) {
                    $cleanStart = $session->scheduled_date->copy()->setTimeFromTimeString($oldTime ?? '00:00');
                    $checkout = \Carbon\Carbon::parse("{$date} {$newTime}");
                    $key = 'checkout_moved_alert_'.$session->id.'_'.$date.'_'.$newTime;
                    if ($cleanStart->lt($checkout) && \Illuminate\Support\Facades\Cache::add($key, 1, now()->addDay())) {
                        self::alertAdmins("Checkout moved to {$checkout->format('D, M j g:i A')} but the cleaning at {$property->internal_display_name} is still set for {$cleanStart->format('D, M j g:i A')}. Please review.");
                    }
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
    public static function notifyAssigned(CleaningSession $session, bool $changed = false): void
    {
        try {
            $user = $session->housekeeper;
            if (! $user) {
                return;
            }
            $phone = self::phoneFor($user);
            if (! $phone) {
                Log::info("CleaningFlow: cleaner {$user->id} has no phone, assignment text skipped.");
                $session->forceFill(['assignment_status' => 'pending_confirmation'])->saveQuietly();
                self::alertAdmins("{$user->name} has no phone number on file, so they could not be texted the cleaning at {$session->property->internal_display_name}. Add a phone number, or confirm the job manually.");
                return;
            }

            $p = $session->property;
            $when = $session->scheduled_date->format('D, M j').($session->scheduled_time ? ' at '.$session->scheduled_time->format('g:i A') : '');
            $label = $changed ? 'Updated cleaning' : 'New cleaning';
            $msg = "{$label}: {$p->internal_display_name} on {$when}. Reply YES to confirm or NO to decline (job {$session->id}).";

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

            if (! empty($result['error'])) {
                self::alertAdmins("The assignment text to {$user->name} for {$p->internal_display_name} failed to send. Confirm the job manually if they accept another way.");
            }
            $session->forceFill(['assignment_status' => 'pending_confirmation', 'cleaner_notified_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('CleaningFlow notifyAssigned failed: '.$e->getMessage());
        }
    }

    /** Inbound SMS from a cleaner. Returns true when we answered it. */
    public static function handleCleanerReply(?string $phone, string $body): bool
    {
        try {
            $text = trim($body);
            $m = [];
            $isAnswer = (bool) preg_match('/^[^A-Za-z0-9]*(yes|y|no|n)[^A-Za-z0-9]*(\d+)?[^A-Za-z0-9]*$/i', $text, $m);
            $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $text));
            if (! $isAnswer && in_array($word, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'HELP', 'INFO', 'START', 'UNSTOP'], true)) {
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

            $pending = CleaningSession::with('property')
                ->where('housekeeper_id', $user->id)
                ->where('assignment_status', 'pending_confirmation')
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->orderBy('scheduled_date')->get();
            if ($pending->isEmpty()) {
                return false;
            }

            if (! $isAnswer) {
                SmsNotificationService::deliver($phone, 'Please reply YES to confirm or NO to decline your cleaning assignment.', 'cleaning');
                return true;
            }

            $yes = in_array(strtoupper($m[1]), ['YES', 'Y'], true);
            $code = (isset($m[2]) && $m[2] !== '') ? (int) $m[2] : null;

            if ($code !== null) {
                $session = $pending->firstWhere('id', $code);
                if (! $session) {
                    SmsNotificationService::deliver($phone, "There is no pending cleaning with job {$code}.", 'cleaning');
                    return true;
                }
            } elseif ($pending->count() > 1) {
                $list = $pending->take(5)->map(fn ($x) => "job {$x->id}: ".$x->property->internal_display_name.' '.$x->scheduled_date->format('M j'))->implode('; ');
                SmsNotificationService::deliver($phone, "You have {$pending->count()} pending cleanings. Reply with the job number, for example YES {$pending->first()->id}. {$list}", 'cleaning');
                return true;
            } else {
                $session = $pending->first();
            }

            $name = $session->property->internal_display_name;
            $day = $session->scheduled_date->format('D, M j');

            if ($yes) {
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

    /** Alert the admins once per job when a cleaner has not answered within $hours. Returns how many alerts went out. */
    public static function alertUnconfirmed(int $hours = 2): int
    {
        $sent = 0;
        $sessions = CleaningSession::with(['property', 'housekeeper'])
            ->where('assignment_status', 'pending_confirmation')
            ->whereNotNull('housekeeper_id')
            ->whereNotNull('cleaner_notified_at')
            ->where('cleaner_notified_at', '<=', now()->subHours($hours))
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->get();
        foreach ($sessions as $x) {
            $key = 'cleaning_unconfirmed_alert_'.$x->id.'_'.$x->cleaner_notified_at->timestamp;
            if (! \Illuminate\Support\Facades\Cache::add($key, 1, now()->addDays(3))) {
                continue;
            }
            self::alertAdmins(($x->housekeeper?->name ?? 'A cleaner').' has not replied to the cleaning at '.$x->property->internal_display_name.' on '.$x->scheduled_date->format('D, M j').". It is still waiting for YES/NO.");
            $sent++;
        }
        return $sent;
    }

    /** Cleaner finished: tell a guest arriving today that the unit is ready. */
    public static function onCleaningCompleted(CleaningSession $session): void
    {
        try {
            $today = now(config('app.display_timezone'))->toDateString();
            $booking = Booking::where('property_id', $session->property_id)
                ->whereDate('check_in_date', $today)->whereNull('cancelled_at')
                ->where('status', 'guest_approved')->first();
            // Heads-up only: no link, because the guest's pages stay locked until the admin marks the unit ready.
            if ($booking && \Illuminate\Support\Facades\Cache::add('cleaning_complete_guest_'.$booking->id, 1, now()->addDays(2))) {
                GuestAlertService::send('cleaning_complete', $booking);
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

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
                    $oldWhen = $session->scheduled_date ? self::whenLabel($session->scheduled_date, $session->scheduled_time) : null;
                    $session->update(['scheduled_date' => $date, 'scheduled_time' => $time]);
                    if ($session->housekeeper_id) {
                        self::notifyAssigned($session->fresh(), true, $oldWhen);
                    }
                } elseif ($changed && $session->schedule_overridden && $session->housekeeper_id && $newTime) {
                    $cleanStart = $session->scheduled_date->copy()->setTimeFromTimeString($oldTime ?? '00:00');
                    $checkout = \Carbon\Carbon::parse("{$date} {$newTime}");
                    $key = 'checkout_moved_alert_'.$session->id.'_'.$date.'_'.$newTime;
                    if ($cleanStart->lt($checkout) && \Illuminate\Support\Facades\Cache::add($key, 1, now()->addDay())) {
                        // Checkout moved but the cleaning time was set by hand: no alert needed.
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
    public static function notifyAssigned(CleaningSession $session, bool $changed = false, ?string $oldWhen = null): void
    {
        try {
            $user = $session->housekeeper;
            if (! $user) {
                return;
            }
            if (! Setting::get('notify_assignments', true)
                || ! CleaningMessages::enabled($changed && $oldWhen ? 'assignment_moved' : ($changed ? 'assignment_updated' : 'assignment_new'))) {
                return;
            }
            $phone = self::phoneFor($user);
            if (! $phone) {
                Log::info("CleaningFlow: cleaner {$user->id} has no phone, assignment text skipped.");
                $session->forceFill(['assignment_status' => 'pending_confirmation'])->saveQuietly();
                self::alertAdmins('admin_no_phone', ['cleaner' => $user->name, 'property_name' => $session->property->internal_display_name]);
                return;
            }

            $p = $session->property;
            $when = self::whenLabel($session->scheduled_date, $session->scheduled_time);
            $msg = CleaningMessages::render(
                $changed && $oldWhen ? 'assignment_moved' : ($changed ? 'assignment_updated' : 'assignment_new'),
                ['property_name' => $p->internal_display_name, 'when' => $when, 'old_when' => $oldWhen ?? '']
            );

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
                self::alertAdmins('admin_send_failed', ['cleaner' => $user->name, 'property_name' => $p->internal_display_name, 'when' => $when]);
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
            Log::info('SMS-LOG in RECEIVED from '.SmsNotificationService::maskPhone($phone).': "'.mb_substr($text, 0, 40).'"');
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
            $users = User::query()->get(array_merge(['id', 'name'], $cols))->filter(
                fn ($u) => in_array($digits, array_map(fn ($c) => SmsConsentService::normalizePhone($u->{$c}), $cols), true)
            );
            if ($users->isEmpty()) {
                Log::info('SMS-LOG in IGNORED: no user has the number '.SmsNotificationService::maskPhone($phone).' (check phone_number on the cleaner).');
                return false;
            }

            // Several users can share one phone number: look at all of their pending cleanings.
            $pending = CleaningSession::with('property')
                ->whereIn('housekeeper_id', $users->pluck('id'))
                ->where('assignment_status', 'pending_confirmation')
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->orderBy('scheduled_date')->get();
            if ($pending->isEmpty()) {
                Log::info('SMS-LOG in IGNORED: no cleaning waiting for confirmation for '.$users->pluck('name')->implode(', ').'.');
                return false;
            }
            $user = $users->firstWhere('id', $pending->first()->housekeeper_id) ?? $users->first();

            if (! $isAnswer) {
                if (CleaningMessages::enabled('cleaner_reply_help')) {
                    SmsNotificationService::deliver($phone, CleaningMessages::render('cleaner_reply_help'), 'cleaning');
                }
                return true;
            }

            $yes = in_array(strtoupper($m[1]), ['YES', 'Y'], true);
            // Job numbers are not used: Y/N answers the oldest pending cleaning.
            $session = $pending->first();

            $name = $session->property->internal_display_name;
            $day = self::whenLabel($session->scheduled_date, $session->scheduled_time);

            if ($yes) {
                $session->forceFill(['assignment_status' => 'confirmed'])->saveQuietly();
                Log::info("SMS-LOG in CONFIRMED: {$user->name} accepted cleaning session {$session->id}.");
                self::alertAdmins('admin_accepted', ['cleaner' => $user->name, 'property_name' => $name, 'when' => $day]);
                if (CleaningMessages::enabled('cleaner_confirmed')) {
                    SmsNotificationService::deliver($phone, CleaningMessages::render('cleaner_confirmed', ['property_name' => $name, 'when' => $day]), 'cleaning');
                }
                return true;
            }

            $session->forceFill(['assignment_status' => 'declined'])->saveQuietly();
            Log::info("SMS-LOG in DECLINED: {$user->name} declined cleaning session {$session->id}.");
            if (CleaningMessages::enabled('cleaner_declined')) {
                SmsNotificationService::deliver($phone, CleaningMessages::render('cleaner_declined', ['property_name' => $name, 'when' => $day]), 'cleaning');
            }
            self::alertAdmins('admin_declined', ['cleaner' => $user->name, 'property_name' => $name, 'when' => $day]);
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
            self::alertAdmins('admin_unconfirmed', ['cleaner' => $x->housekeeper?->name ?? 'Cleaner', 'property_name' => $x->property->internal_display_name, 'when' => self::whenLabel($x->scheduled_date, $x->scheduled_time)]);
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

    private static function alertAdmins(string $key, array $vars = []): void
    {
        if (! CleaningMessages::enabled($key)) {
            return;
        }
        $message = CleaningMessages::render($key, $vars);
        $numbers = collect([Setting::getValue('contact_phone'), config('services.telnyx.admin_notify_number')])
            ->filter()->unique(fn ($n) => SmsConsentService::normalizePhone($n));
        foreach ($numbers as $n) {
            SmsNotificationService::deliver($n, $message, 'staff');
        }
    }

    private static function whenLabel($date, $time = null): string
    {
        return $date->format('D, M j').($time ? ' at '.$time->format('g:i A') : '');
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

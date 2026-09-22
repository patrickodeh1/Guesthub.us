<?php

namespace App\Services;

use App\Mail\SessionCompletedMail;
use App\Mail\SessionStartedMail;
use App\Models\CleaningSession;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Centralized email notification service for session lifecycle events.
 *
 * Mirrors the existing SmsNotificationService pattern.
 * - Queries admin users via Spatie roles.
 * - Idempotent: checks NotificationLog per (session, type, recipient).
 * - Never throws — failures are logged but never block session operations.
 */
class EmailNotificationService
{
    /**
     * Send "Session Started" email to all admin users.
     */
    public static function sendSessionStarted(CleaningSession $session): void
    {
        try {
            $admins = self::getAdminRecipients();

            foreach ($admins as $admin) {
                if (self::alreadySent($session, 'email_session_started', $admin)) {
                    continue;
                }

                self::sendAndLog(
                    session: $session,
                    admin: $admin,
                    mailable: new SessionStartedMail($session),
                    notificationType: 'email_session_started',
                );
            }
        } catch (Throwable $e) {
            Log::warning('EmailNotificationService::sendSessionStarted failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send "Session Completed" email to all admin users.
     */
    public static function sendSessionCompleted(CleaningSession $session): void
    {
        try {
            $admins = self::getAdminRecipients();

            foreach ($admins as $admin) {
                if (self::alreadySent($session, 'email_session_completed', $admin)) {
                    continue;
                }

                self::sendAndLog(
                    session: $session,
                    admin: $admin,
                    mailable: new SessionCompletedMail($session),
                    notificationType: 'email_session_completed',
                );
            }
        } catch (Throwable $e) {
            Log::warning('EmailNotificationService::sendSessionCompleted failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all active admin users with a valid email address.
     */
    private static function getAdminRecipients(): \Illuminate\Database\Eloquent\Collection
    {
        return User::role('admin')
            ->active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();
    }

    /**
     * Check if this notification was already sent (idempotency guard).
     * Each admin receives at most one notification per session per event.
     */
    private static function alreadySent(CleaningSession $session, string $type, User $admin): bool
    {
        return NotificationLog::where('cleaning_session_id', $session->id)
            ->where('notification_type', $type)
            ->where('recipient_email', $admin->email)
            ->exists();
    }

    /**
     * Attempt to send the email and log the result.
     */
    private static function sendAndLog(
        CleaningSession $session,
        User $admin,
        \Illuminate\Mail\Mailable $mailable,
        string $notificationType,
    ): void {
        $logData = [
            'property_id' => $session->property_id,
            'user_id' => $admin->id,
            'cleaning_session_id' => $session->id,
            'notification_type' => $notificationType,
            'recipient_phone' => '', // not applicable for email
            'recipient_email' => $admin->email,
            'message_content' => "Email: {$notificationType} for session #{$session->id}",
        ];

        try {
            Mail::to($admin->email)->send($mailable);

            NotificationLog::create(array_merge($logData, [
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]));
        } catch (Throwable $e) {
            Log::warning("EmailNotificationService: Failed to send {$notificationType}", [
                'session_id' => $session->id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create(array_merge($logData, [
                'delivery_status' => 'failed',
                'error_message' => \Illuminate\Support\Str::limit($e->getMessage(), 500),
            ]));
        }
    }
}

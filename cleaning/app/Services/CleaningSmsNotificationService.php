<?php

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Models\CleaningSession;
use App\Models\NotificationLog;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class CleaningSmsNotificationService
{
    /**
     * Send "Cleaning Started" notification.
     */
    public static function sendCleaningStarted(CleaningSession $session): void
    {
        $property = $session->property;
        
        $globalSetting = \App\Models\Setting::get('notify_cleaning_started_global', '1');
        if ($globalSetting === '0' || !$property->notify_cleaning_started) {
            return;
        }

        if (self::alreadySent($session, 'started')) {
            return;
        }

        $housekeeper = $session->housekeeper;
        $message = "Cleaning has started at {$property->name}.\n"
            . "Cleaner: " . ($housekeeper->name ?? 'N/A') . "\n"
            . "Start Time: " . TimezoneHelper::format(now(), $property) . "\n"
            . "Job ID: #{$session->id}";

        self::dispatch($session, 'started', $message);
    }

    /**
     * Send "Cleaning Finished" notification.
     */
    public static function sendCleaningFinished(CleaningSession $session): void
    {
        $property = $session->property;

        $globalSetting = \App\Models\Setting::get('notify_cleaning_finished_global', '1');
        if ($globalSetting === '0' || !$property->notify_cleaning_finished) {
            return;
        }

        if (self::alreadySent($session, 'finished')) {
            return;
        }

        $housekeeper = $session->housekeeper;
        $message = "Cleaning has been completed at {$property->name}.\n"
            . "Cleaner: " . ($housekeeper->name ?? 'N/A') . "\n"
            . "Completion Time: " . TimezoneHelper::format(now(), $property) . "\n"
            . "Job ID: #{$session->id}";

        self::dispatch($session, 'finished', $message);
    }

    /**
     * Send "Photo Started" notification (first photo only).
     */
    public static function sendPhotoStarted(CleaningSession $session): void
    {
        $property = $session->property;

        $globalSetting = \App\Models\Setting::get('notify_photo_started_global', '1');
        if ($globalSetting === '0' || !$property->notify_photo_started) {
            return;
        }

        if (self::alreadySent($session, 'photo')) {
            return;
        }

        $housekeeper = $session->housekeeper;
        $message = "Property photos are now being captured for {$property->name}.\n"
            . "Cleaner: " . ($housekeeper->name ?? 'N/A') . "\n"
            . "Time: " . TimezoneHelper::format(now(), $property);

        self::dispatch($session, 'photo', $message);
    }

    /**
     * Send "Task Note" notification.
     */
    public static function sendTaskNote(CleaningSession $session, Task $task, string $noteContent): void
    {
        $property = $session->property;

        $globalSetting = \App\Models\Setting::get('notify_task_notes_global', '1');
        if ($globalSetting === '0' || !$property->notify_task_notes) {
            return;
        }

        $housekeeper = $session->housekeeper;
        $message = "Task Note Added\n\n"
            . "Property: {$property->name}\n"
            . "Task: {$task->name}\n"
            . "Cleaner: " . ($housekeeper->name ?? 'N/A') . "\n\n"
            . "Note:\n'{$noteContent}'\n\n"
            . "Time: " . TimezoneHelper::format(now(), $property);

        self::dispatch($session, 'note', $message);
    }

    /**
     * Check if a notification of this type was already sent for this session.
     */
    private static function alreadySent(CleaningSession $session, string $type, ?int $userId = null): bool
    {
        $query = NotificationLog::where('cleaning_session_id', $session->id)
            ->where('notification_type', $type)
            ->where('delivery_status', 'sent');
            
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->exists();
    }
    
    /**
     * Check if a notification of this type was sent recently to a user (cooldown).
     */
    private static function alreadySentRecently(CleaningSession $session, string $type, int $userId, int $hours = 1): bool
    {
        return NotificationLog::where('cleaning_session_id', $session->id)
            ->where('notification_type', $type)
            ->where('delivery_status', 'sent')
            ->where('user_id', $userId)
            ->where('sent_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * Dispatch the SMS to all active recipients.
     */
    private static function dispatch(CleaningSession $session, string $type, string $message): void
    {
        $property = $session->property;
        $recipients = $property->notificationRecipients()->where('active', true)->get();

        if ($recipients->isEmpty()) {
            Log::info("[SMS] No active recipients for property {$property->id} ({$property->name}). Skipping {$type} notification.");
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Log::channel('stack')->info("[SMS SENT] To: {$recipient->phone_number} ({$recipient->recipient_name})\n{$message}");

                NotificationLog::create([
                    'property_id' => $property->id,
                    'cleaning_session_id' => $session->id,
                    'notification_type' => $type,
                    'recipient_phone' => $recipient->phone_number,
                    'message_content' => $message,
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error("[SMS FAILED] To: {$recipient->phone_number} - Error: {$e->getMessage()}");

                NotificationLog::create([
                    'property_id' => $property->id,
                    'cleaning_session_id' => $session->id,
                    'notification_type' => $type,
                    'recipient_phone' => $recipient->phone_number,
                    'message_content' => $message,
                    'delivery_status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'sent_at' => now(),
                ]);
            }
        }
    }

    /**
     * Dispatch an SMS directly to a specific user (e.g., housekeeper).
     */
    private static function dispatchToCleaner(CleaningSession $session, string $type, string $message): void
    {
        $housekeeper = $session->housekeeper;
        if (!$housekeeper || empty($housekeeper->phone_number)) {
            Log::info("[SMS] No phone number for housekeeper {$session->housekeeper_id}. Skipping {$type} notification.");
            return;
        }

        // Optional: Check notification preferences if they exist on User model.
        // Assuming no preference opt-out exists based on inspection, we proceed.
        // If a quiet hours preference was added, it would be evaluated here.

        if ($type === 'training_blocked') {
            if (self::alreadySentRecently($session, $type, $housekeeper->id, 1)) {
                return; // Prevent duplicate within 1 hour for block
            }
        } else {
            if (self::alreadySent($session, $type, $housekeeper->id)) {
                return; // Prevent duplicate for this user for assigned/24h/sameday
            }
        }

        try {
            Log::channel('stack')->info("[SMS SENT] To Cleaner: {$housekeeper->phone_number} ({$housekeeper->name})\n{$message}");

            NotificationLog::create([
                'property_id' => $session->property_id,
                'user_id' => $housekeeper->id,
                'cleaning_session_id' => $session->id,
                'notification_type' => $type,
                'recipient_phone' => $housekeeper->phone_number,
                'message_content' => $message,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("[SMS FAILED] To Cleaner: {$housekeeper->phone_number} - Error: {$e->getMessage()}");

            NotificationLog::create([
                'property_id' => $session->property_id,
                'user_id' => $housekeeper->id,
                'cleaning_session_id' => $session->id,
                'notification_type' => $type,
                'recipient_phone' => $housekeeper->phone_number,
                'message_content' => $message,
                'delivery_status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Send "Training Assigned" notification to the housekeeper.
     */
    public static function sendTrainingAssigned(CleaningSession $session, int $videosCount, int $tasksCount, int $estMinutes): void
    {
        $property = $session->property;
        $date = TimezoneHelper::format($session->scheduled_date, $property, 'M j, Y');
        
        $message = "Pre-Arrival Training Assigned\n\n"
            . "Property: {$property->name}\n"
            . "Date: {$date}\n\n"
            . "You have required training to complete before this assignment.\n"
            . "Videos: {$videosCount}\n"
            . "Tasks: {$tasksCount}\n"
            . "Est. Time: {$estMinutes}m\n\n"
            . "Complete Training:\n" . route('training.index');

        self::dispatchToCleaner($session, 'training_assigned', $message);
    }

    /**
     * Send "Training Reminder" (24h or Same Day) to the housekeeper.
     */
    public static function sendTrainingReminder(CleaningSession $session, string $timing, int $remainingItems, int $estMinutes): void
    {
        $property = $session->property;
        $date = TimezoneHelper::format($session->scheduled_date, $property, 'M j, Y');
        
        $timingLabel = $timing === '24h' ? 'Starts in 24 hours' : 'Starts Today';
        
        $message = "Training Reminder: {$timingLabel}\n\n"
            . "Property: {$property->name}\n"
            . "Date: {$date}\n\n"
            . "You still have {$remainingItems} required training item(s) to complete before you can start.\n"
            . "Est. Time: {$estMinutes}m\n\n"
            . "Complete Training:\n" . route('training.index');

        self::dispatchToCleaner($session, "training_reminder_{$timing}", $message);
    }

    /**
     * Send "Session Blocked" reminder to the housekeeper.
     */
    public static function sendTrainingBlockedReminder(CleaningSession $session, int $remainingItems): void
    {
        $property = $session->property;
        
        $message = "Session Start Blocked\n\n"
            . "Property: {$property->name}\n\n"
            . "You attempted to start this session but still have {$remainingItems} mandatory training item(s) to complete.\n\n"
            . "Complete Training:\n" . route('training.index');

        self::dispatchToCleaner($session, 'training_blocked', $message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\Property;
use App\Models\PropertyNotificationRecipient;
use Illuminate\Http\Request;

class PropertyNotificationSettingsController extends Controller
{
    /**
     * Update notification settings for a property.
     */
    public function updateSettings(Request $request, Property $property)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        $validated = $request->validate([
            'notify_cleaning_started' => 'boolean',
            'notify_cleaning_finished' => 'boolean',
            'notify_photo_started' => 'boolean',
            'notify_task_notes' => 'boolean',
        ]);

        $property->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Notification settings updated.']);
        }

        return back()->with('success', 'Notification settings updated.');
    }

    /**
     * Add a notification recipient.
     */
    public function addRecipient(Request $request, Property $property)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        $recipient = $property->notificationRecipients()->create([
            'phone_number' => $validated['phone_number'],
            'recipient_name' => $validated['recipient_name'] ?? null,
            'active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'recipient' => $recipient]);
        }

        return back()->with('success', 'Recipient added.');
    }

    /**
     * Update a notification recipient.
     */
    public function updateRecipient(Request $request, Property $property, PropertyNotificationRecipient $recipient)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        abort_unless($recipient->property_id === $property->id, 404);

        $validated = $request->validate([
            'phone_number' => 'sometimes|required|string|max:20',
            'recipient_name' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        $recipient->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'recipient' => $recipient->fresh()]);
        }

        return back()->with('success', 'Recipient updated.');
    }

    /**
     * Delete a notification recipient.
     */
    public function deleteRecipient(Request $request, Property $property, PropertyNotificationRecipient $recipient)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        abort_unless($recipient->property_id === $property->id, 404);

        $recipient->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Recipient removed.']);
        }

        return back()->with('success', 'Recipient removed.');
    }

    /**
     * Get notification history for a property.
     */
    public function history(Request $request, Property $property)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        $logs = $property->notificationLogs()
            ->latest()
            ->paginate(20);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'logs' => $logs]);
        }

        return back();
    }

    /**
     * Resend a failed notification.
     */
    public function resend(Request $request, Property $property, NotificationLog $log)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        abort_unless($log->property_id === $property->id, 404);

        try {
            \Illuminate\Support\Facades\Log::channel('stack')->info("[SMS RESENT] To: {$log->recipient_phone}\n{$log->message_content}");

            $newLog = NotificationLog::create([
                'property_id' => $property->id,
                'cleaning_session_id' => $log->cleaning_session_id,
                'notification_type' => $log->notification_type,
                'recipient_phone' => $log->recipient_phone,
                'message_content' => $log->message_content,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Notification resent.', 'log' => $newLog]);
            }

            return back()->with('success', 'Notification resent.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Resend failed: ' . $e->getMessage()], 500);
            }
            return back()->withErrors(['resend' => 'Resend failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get recipients and settings (JSON API for the frontend).
     */
    public function getSettings(Property $property)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'settings' => [
                'notify_cleaning_started' => (bool) $property->notify_cleaning_started,
                'notify_cleaning_finished' => (bool) $property->notify_cleaning_finished,
                'notify_photo_started' => (bool) $property->notify_photo_started,
                'notify_task_notes' => (bool) $property->notify_task_notes,
            ],
            'recipients' => $property->notificationRecipients()->get(),
        ]);
    }
}

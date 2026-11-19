<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\CleaningMessages;
use App\Services\DirectBookingMessages;
use App\Services\GuestAlertService;
use App\Support\NotificationScope;
use Illuminate\Http\Request;

/**
 * Guest lifecycle alert templates, cleaner texts and per-event, per-role send toggles.
 * Stored per property owner; admins also edit the global defaults that owners inherit.
 */
class NotificationSettingsController extends Controller
{
    private const GLOBAL_SWITCHES = [
        'notify_cleaning_started_global',
        'notify_cleaning_finished_global',
        'notify_photo_started_global',
        'notify_task_notes_global',
        'notify_assignments',
        'notify_session_started',
        'notify_session_completed',
    ];

    public function edit(Request $request)
    {
        $scope = NotificationScope::resolve($request, $request->user());
        $ownerId = $scope['ownerId'];

        return view('admin.notifications.index', [
            'alertEvents' => GuestAlertService::EVENTS,
            'alertLabels' => GuestAlertService::labels(),
            'alertConfig' => GuestAlertService::config($ownerId),
            'staffRoles' => GuestAlertService::STAFF_ROLES,
            'cleaningEvents' => CleaningMessages::EVENTS,
            'cleaningConfig' => CleaningMessages::config($ownerId),
            'directEvents' => DirectBookingMessages::EVENTS,
            'directConfig' => DirectBookingMessages::config(),
            'scopeOwnerId' => $ownerId,
            'scopeOptions' => $scope['options'],
            'scopeIsAdmin' => $scope['isAdmin'],
            'isGlobalScope' => $ownerId === null,
        ]);
    }

    public function update(Request $request)
    {
        $scope = NotificationScope::resolve($request, $request->user());
        $ownerId = $scope['ownerId'];
        $isGlobal = $ownerId === null;

        $rules = [
            'alerts' => ['required', 'array'],
            'alerts.*.guest_message' => ['nullable', 'string', 'max:1000'],
            'alerts.*.staff_message' => ['nullable', 'string', 'max:1000'],
            'alerts.*._complete' => ['nullable', 'boolean'],
            'cleaning' => ['nullable', 'array'],
            'cleaning.*.message' => ['nullable', 'string', 'max:1000'],
            'cleaning.*.enabled' => ['nullable', 'boolean'],
            'cleaning.*._complete' => ['nullable', 'boolean'],
            'direct' => ['nullable', 'array'],
            'direct.*.enabled' => ['nullable', 'boolean'],
            'direct.*.subject' => ['nullable', 'string', 'max:200'],
            'direct.*.message' => ['nullable', 'string', 'max:2000'],
        ];
        foreach (self::GLOBAL_SWITCHES as $key) {
            $rules[$key] = ['nullable', 'boolean'];
        }
        foreach (GuestAlertService::RECIPIENT_SOURCES as $source) {
            $rules["alerts.*.{$source}_sms"] = ['nullable', 'boolean'];
            $rules["alerts.*.{$source}_email"] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        // Platform-wide switches and system emails are admin/global only.
        if ($isGlobal) {
            foreach (self::GLOBAL_SWITCHES as $key) {
                \App\Models\Setting::putValue($key, (bool) ($data[$key] ?? false));
            }
        }

        $config = [];
        $existing = GuestAlertService::config($ownerId);
        $skipped = [];
        foreach (GuestAlertService::EVENTS as $key => $meta) {
            $row = $data['alerts'][$key] ?? null;

            // Row missing or cut off (PHP max_input_vars): keep what is saved instead of zeroing it.
            if (! is_array($row) || empty($row['_complete'])) {
                $config[$key] = $existing[$key];
                $skipped[] = $key;
                continue;
            }
            $config[$key] = [
                'guest_message' => $row['guest_message'] ?? $meta['default_guest_message'],
                'staff_message' => $row['staff_message'] ?? $meta['default_staff_message'],
            ];

            foreach (GuestAlertService::RECIPIENT_SOURCES as $source) {
                $config[$key]["{$source}_sms"] = (bool) ($row["{$source}_sms"] ?? false);
                $config[$key]["{$source}_email"] = (bool) ($row["{$source}_email"] ?? false);
            }
        }

        GuestAlertService::putConfig($config, $ownerId);
        CleaningMessages::put($data['cleaning'] ?? [], $ownerId);
        if ($isGlobal && $request->has('direct')) {
            DirectBookingMessages::put($data['direct'] ?? []);
        }

        $who = $isGlobal ? 'global defaults' : 'account #'.$ownerId;
        ActivityLog::record('guest_alerts_updated', "Notification messages and send preferences were updated ({$who}).", 'settings');

        return back()->with('success', 'Notification settings saved.'.($skipped
            ? ' WARNING: '.count($skipped).' events were not received and kept their old values ('.implode(', ', $skipped).'). Raise max_input_vars on the server.'
            : ''));
    }

    public function updateDirect(Request $request)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'direct' => ['nullable', 'array'],
            'direct.*.enabled' => ['nullable', 'boolean'],
            'direct.*.subject' => ['nullable', 'string', 'max:200'],
            'direct.*.message' => ['nullable', 'string', 'max:2000'],
        ]);
        DirectBookingMessages::put($data['direct'] ?? []);
        ActivityLog::record('direct_booking_emails_updated', 'Direct booking email templates were updated.', 'settings');

        return back()->with('success', 'Direct booking emails saved.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\GuestAlertService;
use Illuminate\Http\Request;

/**
 * Guest lifecycle alert templates and per-event, per-role send toggles.
 * Split out from the general SettingsController since this is a large,
 * structured section in its own right, on its own page in the sidebar.
 */
class NotificationSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.notifications.index', [
            'alertEvents' => GuestAlertService::EVENTS,
            'alertLabels' => GuestAlertService::labels(),
            'alertConfig' => GuestAlertService::config(),
            'staffRoles' => GuestAlertService::STAFF_ROLES,
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'alerts' => ['required', 'array'],
            'alerts.*.guest_message' => ['nullable', 'string', 'max:1000'],
            'alerts.*.staff_message' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ([
            'notify_cleaning_started_global',
            'notify_cleaning_finished_global',
            'notify_photo_started_global',
            'notify_task_notes_global',
            'notify_assignments',
            'notify_session_started',
            'notify_session_completed',
        ] as $key) {
            $rules[$key] = ['nullable', 'boolean'];
        }

        foreach (GuestAlertService::RECIPIENT_SOURCES as $source) {
            $rules["alerts.*.{$source}_sms"] = ['nullable', 'boolean'];
            $rules["alerts.*.{$source}_email"] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        foreach ([
            'notify_cleaning_started_global',
            'notify_cleaning_finished_global',
            'notify_photo_started_global',
            'notify_task_notes_global',
            'notify_assignments',
            'notify_session_started',
            'notify_session_completed',
        ] as $key) {
            \App\Models\Setting::putValue($key, (bool) ($data[$key] ?? false));
        }

        $config = [];
        foreach (GuestAlertService::EVENTS as $key => $meta) {
            $row = $data['alerts'][$key] ?? [];
            $config[$key] = [
                'guest_message' => $row['guest_message'] ?? $meta['default_guest_message'],
                'staff_message' => $row['staff_message'] ?? $meta['default_staff_message'],
            ];

            foreach (GuestAlertService::RECIPIENT_SOURCES as $source) {
                $config[$key]["{$source}_sms"] = (bool) ($row["{$source}_sms"] ?? false);
                $config[$key]["{$source}_email"] = (bool) ($row["{$source}_email"] ?? false);
            }
        }

        GuestAlertService::putConfig($config);

        ActivityLog::record('guest_alerts_updated', 'Guest lifecycle alert messages and send preferences were updated.', 'settings');

        return back()->with('success', 'Notification settings saved.');
    }
}

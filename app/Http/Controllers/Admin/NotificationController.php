<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Property;
use Illuminate\Http\Request;

class NotificationController extends Controller
{

    /**
     * Communications > Notifications: notifications that were sent, their delivery
     * status, who received them and the related property / cleaning job.
     * (Rules and templates live under Settings > Notification Settings.)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = NotificationLog::with(['property', 'user', 'cleaningSession'])->latest('id');

        // Admins see everything; everyone else only their visible properties.
        if (! $user->hasRole('admin')) {
            $query->whereIn('property_id', Property::visibleTo($user)->pluck('id'));
        }

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('delivery_status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('notification_type', $request->string('type')))
            ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->integer('property_id')));

        $logs = $query->paginate(25)->withQueryString();

        $statuses = NotificationLog::query()->whereNotNull('delivery_status')->distinct()->orderBy('delivery_status')->pluck('delivery_status');
        $types = NotificationLog::query()->whereNotNull('notification_type')->distinct()->orderBy('notification_type')->pluck('notification_type');
        $properties = Property::visibleTo($user)->orderBy('name')->get(['id', 'name']);

        return view('admin.notifications.history', compact('logs', 'statuses', 'types', 'properties'));
    }

    /**
     * Dismiss (mark as read) a single notification item, e.g.
     * "pending_id:12" or "checkin_today:45".
     */
    public function dismiss(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:100',
        ]);

        $request->user()->dismissNotification($request->key);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /**
     * Mark every currently-visible notification key as read at once.
     */
    public function markAllRead(Request $request)
    {
        $request->validate([
            'keys'   => 'array',
            'keys.*' => 'string|max:100',
        ]);

        $request->user()->dismissNotifications($request->keys ?? []);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}

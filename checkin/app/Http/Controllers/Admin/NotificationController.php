<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
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

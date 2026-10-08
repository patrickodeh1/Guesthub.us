<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class SessionLockboxController extends Controller
{
    public function update(Request $request, CleaningSession $session)
    {
        $user = $request->user();
        abort_unless($user && (int) $session->housekeeper_id === (int) $user->id, 403);

        if ($session->status !== 'in_progress') {
            if ($request->expectsJson()) { return response()->json(['ok' => false, 'message' => 'The lockbox code can only be changed while your cleaning is in progress.'], 403); }
            return back()->with('error', 'The lockbox code can only be changed while your cleaning is in progress.');
        }

        $data = $request->validate(['lockbox_code' => ['required', 'string', 'max:255']]);

        $property = $session->property;
        $property->forceFill(['lockbox_code' => trim($data['lockbox_code'])])->save();

        try {
            // The code itself is never written to the log.
            ActivityLogService::admin('lockbox_changed', "{$user->name} changed the lockbox code for {$property->name} during a cleaning.", 'properties', [
                'subject_type' => \App\Models\Property::class,
                'subject_id'   => $property->id,
                'severity'     => 'warning',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->expectsJson()) { return response()->json(['ok' => true]); }
        return back()->with('success', 'Lockbox code updated.');
    }
}

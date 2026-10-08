<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Models\PropertyLock;
use App\Services\ActivityLogService;
use App\Services\GpsService;
use App\Services\SeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionDoorController extends Controller
{
    public function unlock(Request $request, CleaningSession $session, PropertyLock $lock)
    {
        return $this->act($request, $session, $lock, 'unlock');
    }

    public function lock(Request $request, CleaningSession $session, PropertyLock $lock)
    {
        return $this->act($request, $session, $lock, 'lock');
    }

    public function status(Request $request, CleaningSession $session, PropertyLock $lock)
    {
        if ($blocked = $this->guard($request, $session, $lock)) {
            return $blocked;
        }
        try {
            $locked = app(SeamService::class)->getLockStatus($lock->seam_device_id);
        } catch (\Throwable $e) {
            report($e);
            $locked = null;
        }
        if ($locked !== null && $lock->last_known_locked !== $locked) {
            $lock->update(['last_known_locked' => $locked, 'last_status_at' => now()]);
        }
        return response()->json(['ok' => $locked !== null, 'locked' => $locked]);
    }

    private function guard(Request $request, CleaningSession $session, PropertyLock $lock): ?JsonResponse
    {
        $user = $request->user();
        abort_unless($lock->property_id === $session->property_id, 404);

        if (! $user || (int) $session->housekeeper_id !== (int) $user->id) {
            return response()->json(['ok' => false, 'error' => 'This assignment is not yours.'], 403);
        }
        if (! in_array($session->status, ['pending', 'in_progress'], true)
            || ($session->status === 'pending' && ! \App\Services\CleanerAccessSteps::locationVerified($session))) {
            return response()->json(['ok' => false, 'error' => 'Door access opens once you have verified your location, and ends when your cleaning is complete.'], 403);
        }
        return null;
    }

    private function act(Request $request, CleaningSession $session, PropertyLock $lock, string $action): JsonResponse
    {
        if ($blocked = $this->guard($request, $session, $lock)) {
            return $blocked;
        }

        $property = $session->property;
        if (! $session->gps_override_enabled && $property->latitude !== null && $property->longitude !== null) {
            $v = validator($request->all(), ['latitude' => ['required', 'numeric'], 'longitude' => ['required', 'numeric']]);
            if ($v->fails()) {
                return response()->json(['ok' => false, 'error' => 'Your location is required to lock or unlock the door.'], 422);
            }
            $distance = GpsService::distanceMeters(
                (float) $request->input('latitude'), (float) $request->input('longitude'),
                (float) $property->latitude, (float) $property->longitude
            );
            $radius = \App\Support\GpsRadius::base();
            if ($distance > $radius) {
                return response()->json(['ok' => false, 'error' => 'You must be at the property to use the door.'], 403);
            }
        }

        $user = $request->user();
        try {
            $attempt = $action === 'unlock'
                ? app(SeamService::class)->unlock($lock->seam_device_id)
                : app(SeamService::class)->lock($lock->seam_device_id);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'error' => 'Could not reach the door. Please try again in a moment.'], 502);
        }

        try {
            ActivityLogService::admin("cleaner_door_{$action}", "{$user->name} sent a {$action} command for {$lock->label} during a cleaning at {$property->name}.", 'properties', [
                'subject_type' => \App\Models\CleaningSession::class,
                'subject_id'   => $session->id,
                'metadata'     => ['lock_id' => $lock->id, 'action_attempt_id' => $attempt['action_attempt_id'] ?? null],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true, 'status' => 'pending', 'action_attempt_id' => $attempt['action_attempt_id'] ?? null]);
    }
}

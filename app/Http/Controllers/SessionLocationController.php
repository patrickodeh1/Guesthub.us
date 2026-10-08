<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Services\GpsService;
use Illuminate\Http\Request;

class SessionLocationController extends Controller
{
    public function verify(Request $request, CleaningSession $session)
    {
        $user = $request->user();
        if (! $user || (int) $session->housekeeper_id !== (int) $user->id) {
            return response()->json(['ok' => false, 'message' => 'This assignment is not yours.'], 403);
        }
        if ($session->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'This session has already started or ended.'], 403);
        }
        if ($session->gps_override_enabled) {
            return response()->json(['ok' => true]);
        }

        $property = $session->property;
        if ($property->latitude !== null && $property->longitude !== null) {
            $data = $request->validate([
                'latitude'  => ['required', 'numeric'],
                'longitude' => ['required', 'numeric'],
                'accuracy'  => ['nullable', 'numeric', 'min:0'],
            ]);
            $distance = GpsService::distanceMeters(
                (float) $data['latitude'], (float) $data['longitude'],
                (float) $property->latitude, (float) $property->longitude
            );
            $radius = \App\Support\GpsRadius::effective($data['accuracy'] ?? null);
            if ($distance > $radius) {
                return response()->json(['ok' => false, 'message' => 'You are about '.round($distance).'m away. You must be at the property.'], 422);
            }
        }

        $session->forceFill(['gps_confirmed_at' => now()])->saveQuietly();
        return response()->json(['ok' => true]);
    }
}

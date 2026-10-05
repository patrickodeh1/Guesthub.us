<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CleaningSession;
use App\Models\Property;
use Illuminate\Http\Request;

/** Reassign a cleaning session to a different guest, or mark it "no guest". */
class CleaningSessionGuestController extends Controller
{
    public function update(Request $request, CleaningSession $cleaningSession)
    {
        abort_unless(Property::query()->visibleTo($request->user())->whereKey($cleaningSession->property_id)->exists(), 403);

        $data = $request->validate([
            'booking_id' => ['nullable', 'integer'],
            'no_guest' => ['nullable', 'boolean'],
        ]);

        $none = (bool) ($data['no_guest'] ?? false) || empty($data['booking_id']);
        if (! $none) {
            abort_unless(Booking::whereKey($data['booking_id'])->where('property_id', $cleaningSession->property_id)->exists(), 422);
        }

        $cleaningSession->update(['booking_id' => $none ? null : $data['booking_id'], 'no_guest' => $none]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Guest tag updated.');
    }
}

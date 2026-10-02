<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyRoomAttachController extends Controller
{
    public function store(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can attach rooms to properties.');

        $data = $request->validate([
            'room_ids'   => ['array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'room_names' => ['array'],
            'room_names.*' => ['string', 'max:255'],
        ]);

        $roomIds = array_values(array_unique($data['room_ids'] ?? []));
        $roomNames = array_values(array_filter($data['room_names'] ?? [], fn ($v) => trim($v) !== ''));

        DB::transaction(function () use ($property, $roomIds, $roomNames) {
            $attached = $property->rooms()->pluck('rooms.id')->all();

            // Unchecked rooms are detached only. Nothing is deleted.
            $toDetach = array_diff($attached, $roomIds);
            if (! empty($toDetach)) {
                $property->rooms()->detach($toDetach);
            }

            // Only rooms that are not already attached get cloned.
            $toClone = array_diff($roomIds, $attached);
            $nextSort = (int) $property->rooms()->max('property_room.sort_order');
            if (! empty($toClone)) {
                $templates = Room::whereIn('id', $toClone)->get()->keyBy('id');
                foreach ($toClone as $id) {
                    if ($template = $templates->get($id)) {
                        $template->cloneForProperty($property, ++$nextSort);
                    }
                }
            }

            foreach ($roomNames as $name) {
                $room = Room::create([
                    'name' => trim($name),
                    'is_default' => false,
                    'min_photos' => 2,
                ]);
                $property->rooms()->attach($room->id, ['sort_order' => ++$nextSort]);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Rooms updated.']);
        }

        return back()->with('ok', 'Rooms updated.');
    }
}

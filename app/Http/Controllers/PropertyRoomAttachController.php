<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Support\OrgScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyRoomAttachController extends Controller
{
    public function store(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'Only administrators, owners, companies and managers can attach rooms to properties.');
        abort_unless(Property::visibleTo($request->user())->whereKey($property->id)->exists(), 403, 'You do not have access to this property.');

        $data = $request->validate([
            'room_ids'   => ['array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'room_names' => ['array'],
            'room_names.*' => ['string', 'max:255'],
        ]);

        $roomIds = array_values(array_unique($data['room_ids'] ?? []));
        $roomNames = array_values(array_filter($data['room_names'] ?? [], fn ($v) => trim($v) !== ''));

        if (! $request->user()->hasRole('admin') && $roomIds !== []) {
            $visiblePropertyIds = Property::visibleTo($request->user())->select('properties.id');
            $allowedRoomIds = Room::query()
                ->whereIn('rooms.id', $roomIds)
                ->where(function ($query) use ($visiblePropertyIds, $request) {
                    $query->where('rooms.is_default', true)
                        ->orWhereIn('rooms.created_by', OrgScope::userIds($request->user()))
                        ->orWhere(fn ($propertyRooms) => $propertyRooms
                            ->whereHas('properties')
                            ->whereDoesntHave('properties', fn ($linkedProperties) => $linkedProperties
                                ->whereNotIn('properties.id', $visiblePropertyIds)));
                })
                ->pluck('rooms.id')
                ->map(fn ($id) => (int) $id)
                ->all();
            abort_if(array_diff(array_map('intval', $roomIds), $allowedRoomIds) !== [], 403, 'You do not have access to one or more rooms.');
        }

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
                $cloneable = $toClone;
                $templates = Room::whereIn('id', $cloneable)->get()->keyBy('id');
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

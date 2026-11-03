<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Support\OrgScope;
use Illuminate\Http\Request;

/**
 * Controller to handle ordering of tasks within a room.
 * This allows the user to specify the order of tasks in a room.
 */
class RoomTaskOrderController extends Controller
{
    public function update(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'Only administrators, owners, companies and managers can reorder tasks.');
        abort_unless(Property::visibleTo($request->user())->whereKey($property->id)->exists(), 403, 'You do not have access to this property.');
        abort_unless($room->properties()->whereKey($property->id)->exists(), 403, 'That room does not belong to this property.');

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct', 'exists:tasks,id'],
        ]);
        $this->authorizeOrder($room, $data['order']);

        foreach ($data['order'] as $index => $taskId) {
            $room->tasks()->updateExistingPivot($taskId, ['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * PATCH /rooms/{room}/tasks
     * Handle task ordering for standalone rooms (not under properties)
     */
    public function updateForRoom(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'Only administrators, owners, companies and managers can reorder tasks.');
        $user = $request->user();
        $canAccessRoom = $user->hasRole('admin') || (! $room->is_default && (
            $room->properties()->whereIn('properties.id', Property::visibleTo($user)->select('properties.id'))->exists()
            || in_array((int) ($room->created_by ?? 0), OrgScope::userIds($user), true)
        ));
        abort_unless($canAccessRoom, 403, 'You do not have access to this room.');

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct', 'exists:tasks,id'],
        ]);
        $this->authorizeOrder($room, $data['order']);

        foreach ($data['order'] as $index => $taskId) {
            $room->tasks()->updateExistingPivot($taskId, ['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function authorizeOrder(Room $room, array $taskIds): void
    {
        $attachedIds = $room->tasks()->pluck('tasks.id')->map(fn ($id) => (int) $id)->all();
        $requestedIds = array_map('intval', $taskIds);

        abort_if(array_diff($requestedIds, $attachedIds) !== [], 403, 'The order contains a task that is not attached to this room.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyRoomOrderController extends Controller
{
    public function update(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'Only administrators, owners, companies and managers can reorder rooms.');
        abort_unless(Property::visibleTo($request->user())->whereKey($property->id)->exists(), 403, 'You do not have access to this property.');

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct', 'exists:rooms,id'],
        ]);
        $attachedIds = $property->rooms()->pluck('rooms.id')->map(fn ($id) => (int) $id)->all();
        $requestedIds = array_map('intval', $data['order']);
        abort_if(array_diff($requestedIds, $attachedIds) !== [], 403, 'The order contains a room that is not attached to this property.');

        foreach ($data['order'] as $i => $roomId) {
            $property->rooms()->updateExistingPivot($roomId, ['sort_order' => $i + 1]);
        }

        return response()->json(['status' => 'ok']);
    }
}

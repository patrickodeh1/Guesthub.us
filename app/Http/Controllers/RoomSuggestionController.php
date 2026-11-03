<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Support\OrgScope;
use Illuminate\Http\Request;

class RoomSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $user = $request->user();
        $query = Room::query()->where('name', 'like', "%$q%");

        if (! $user->hasRole('admin')) {
            $visible = Property::visibleTo($user)->select('properties.id');
            $query->where(function ($roomQuery) use ($visible, $user) {
                $roomQuery->where('rooms.is_default', true)
                    ->orWhereIn('rooms.created_by', OrgScope::userIds($user))
                    ->orWhereHas('properties', fn ($propertyQuery) => $propertyQuery->whereIn('properties.id', $visible));
            });
        }

        $rooms = $query->orderBy('name')->limit(8)->get(['id', 'name', 'is_default']);

        return response()->json($rooms);
    }
}

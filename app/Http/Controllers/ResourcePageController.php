<?php

namespace App\Http\Controllers;

use App\Models\InstructionalVideo;
use App\Models\Property;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourcePageController extends Controller
{
    /**
     * Get accessible property IDs for the authenticated user.
     */
    private function getAccessiblePropertyIds(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return Property::active()->pluck('id');
        }

        if ($user->hasRole('company')) {
            // Company sees own properties + properties of owners they manage
            $managedOwnerIds = DB::table('housekeeper_owner')
                ->where('owner_id', $user->id)
                ->pluck('housekeeper_id')
                ->toArray();

            // Also include owners belonging to this company
            $childOwnerIds = \App\Models\User::where('owner_id', $user->id)->pluck('id')->toArray();

            $ownerIds = array_unique(array_merge([$user->id], $managedOwnerIds, $childOwnerIds));

            return Property::active()
                ->whereIn('owner_id', $ownerIds)
                ->pluck('id');
        }

        if ($user->hasRole('owner')) {
            return Property::active()
                ->where('owner_id', $user->id)
                ->pluck('id');
        }

        // Housekeeper: properties they are directly assigned to or have sessions for
        $assignedIds = DB::table('property_user')
            ->where('user_id', $user->id)
            ->pluck('property_id')
            ->toArray();

        $sessionIds = DB::table('cleaning_sessions')
            ->where('housekeeper_id', $user->id)
            ->pluck('property_id')
            ->toArray();

        $allIds = array_unique(array_merge($assignedIds, $sessionIds));

        return Property::active()
            ->whereIn('id', $allIds)
            ->pluck('id');
    }

    /**
     * Photos page — staging images (task media of type 'image') grouped by property.
     */
    public function photos(Request $request)
    {
        $user = $request->user();
        $propertyIds = $this->getAccessiblePropertyIds($request);
        $selectedPropertyId = $request->query('property');

        // Get properties for the filter dropdown
        $properties = Property::active()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // If a specific property is selected, filter to just that one
        $filteredIds = $selectedPropertyId
            ? $propertyIds->intersect([(int) $selectedPropertyId])
            : $propertyIds;

        // Get all tasks with image media, grouped by property via rooms
        $roomTaskPhotos = DB::table('task_media')
            ->join('tasks', 'tasks.id', '=', 'task_media.task_id')
            ->join('room_task', 'room_task.task_id', '=', 'tasks.id')
            ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
            ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
            ->join('properties', 'properties.id', '=', 'property_room.property_id')
            ->where('task_media.type', 'image')
            ->whereIn('properties.id', $filteredIds)
            ->select(
                'properties.id as property_id',
                'properties.name as property_name',
                'rooms.id as room_id',
                'rooms.name as room_name',
                'tasks.id as task_id',
                'tasks.name as task_name',
                'task_media.id as media_id',
                'task_media.url as media_url',
                'task_media.thumbnail as media_thumbnail',
                'task_media.caption as media_caption'
            )
            ->orderBy('properties.name')
            ->orderBy('rooms.name')
            ->orderBy('tasks.name')
            ->get();

        // Also get property-level task photos
        $propertyTaskPhotos = DB::table('task_media')
            ->join('tasks', 'tasks.id', '=', 'task_media.task_id')
            ->join('property_tasks', 'property_tasks.task_id', '=', 'tasks.id')
            ->join('properties', 'properties.id', '=', 'property_tasks.property_id')
            ->where('task_media.type', 'image')
            ->whereIn('properties.id', $filteredIds)
            ->select(
                'properties.id as property_id',
                'properties.name as property_name',
                DB::raw('NULL as room_id'),
                DB::raw("'Property-Level' as room_name"),
                'tasks.id as task_id',
                'tasks.name as task_name',
                'task_media.id as media_id',
                'task_media.url as media_url',
                'task_media.thumbnail as media_thumbnail',
                'task_media.caption as media_caption'
            )
            ->orderBy('properties.name')
            ->orderBy('tasks.name')
            ->get();

        $allPhotos = $roomTaskPhotos->concat($propertyTaskPhotos);

        // Resolve URLs through the TaskMedia model accessor pattern
        $allPhotos = $allPhotos->map(function ($photo) {
            $photo->resolved_url = $this->resolveMediaUrl($photo->media_url);
            $photo->resolved_thumbnail = $photo->media_thumbnail
                ? $this->resolveMediaUrl($photo->media_thumbnail)
                : $photo->resolved_url;
            return $photo;
        });

        // Group by property
        $grouped = $allPhotos->groupBy('property_name');

        return view('resources.photos', compact('grouped', 'properties', 'selectedPropertyId'));
    }

    /**
     * Videos page — instructional videos the user has access to.
     */
    public function videos(Request $request)
    {
        $user = $request->user();
        $propertyIds = $this->getAccessiblePropertyIds($request);
        $selectedPropertyId = $request->query('property');
        $selectedCategory = $request->query('category', '');

        // Get properties for the filter dropdown
        $properties = Property::active()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Build video query
        $query = InstructionalVideo::published()
            ->with('properties')
            ->whereHas('properties', function ($q) use ($propertyIds, $selectedPropertyId) {
                $q->whereIn('properties.id', $propertyIds);
                if ($selectedPropertyId) {
                    $q->where('properties.id', (int) $selectedPropertyId);
                }
            });

        if ($selectedCategory) {
            $query->where('category', $selectedCategory);
        }

        $videos = $query->latest()->get();

        $categories = ['general', 'room-specific', 'equipment', 'safety', 'process'];

        return view('resources.videos', compact('videos', 'properties', 'categories', 'selectedPropertyId', 'selectedCategory'));
    }

    /**
     * Guides page — text instructions grouped by property.
     */
    public function guides(Request $request)
    {
        $user = $request->user();
        $propertyIds = $this->getAccessiblePropertyIds($request);
        $selectedPropertyId = $request->query('property');

        // Get properties for the filter dropdown
        $properties = Property::active()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // If a specific property is selected, filter to just that one
        $filteredIds = $selectedPropertyId
            ? $propertyIds->intersect([(int) $selectedPropertyId])
            : $propertyIds;

        // Get room-level task instructions
        $roomInstructions = DB::table('room_task')
            ->join('tasks', 'tasks.id', '=', 'room_task.task_id')
            ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
            ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
            ->join('properties', 'properties.id', '=', 'property_room.property_id')
            ->whereIn('properties.id', $filteredIds)
            ->where(function ($q) {
                $q->whereNotNull('room_task.instructions')
                  ->where('room_task.instructions', '!=', '')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('tasks.instructions')
                         ->where('tasks.instructions', '!=', '');
                  });
            })
            ->select(
                'properties.id as property_id',
                'properties.name as property_name',
                'rooms.id as room_id',
                'rooms.name as room_name',
                'tasks.id as task_id',
                'tasks.name as task_name',
                'tasks.type as task_type',
                DB::raw('COALESCE(NULLIF(room_task.instructions, \'\'), tasks.instructions) as instructions')
            )
            ->orderBy('properties.name')
            ->orderBy('rooms.name')
            ->orderBy('tasks.name')
            ->get();

        // Get property-level task instructions
        $propertyInstructions = DB::table('property_tasks')
            ->join('tasks', 'tasks.id', '=', 'property_tasks.task_id')
            ->join('properties', 'properties.id', '=', 'property_tasks.property_id')
            ->whereIn('properties.id', $filteredIds)
            ->where(function ($q) {
                $q->whereNotNull('property_tasks.instructions')
                  ->where('property_tasks.instructions', '!=', '')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('tasks.instructions')
                         ->where('tasks.instructions', '!=', '');
                  });
            })
            ->select(
                'properties.id as property_id',
                'properties.name as property_name',
                DB::raw('NULL as room_id'),
                DB::raw("'Property-Level' as room_name"),
                'tasks.id as task_id',
                'tasks.name as task_name',
                'tasks.type as task_type',
                DB::raw('COALESCE(NULLIF(property_tasks.instructions, \'\'), tasks.instructions) as instructions')
            )
            ->orderBy('properties.name')
            ->orderBy('tasks.name')
            ->get();

        $allGuides = $roomInstructions->concat($propertyInstructions);

        // Group by property, then by room
        $grouped = $allGuides->groupBy('property_name')->map(function ($items) {
            return $items->groupBy('room_name');
        });

        return view('resources.guides', compact('grouped', 'properties', 'selectedPropertyId'));
    }

    /**
     * Resolve a media URL using the same pattern as TaskMedia model.
     */
    private function resolveMediaUrl(?string $value): ?string
    {
        if (!$value) return null;

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $path = str_replace('\\', '/', trim($value));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return url('file/' . $path);
    }
}

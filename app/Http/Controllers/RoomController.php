<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{

    public function index(Request $request)
    {
        // Filter to show ONLY global template rooms or unattached standalone rooms.
        // We hide property-specific cloned rooms to prevent the admin list from being flooded.
        $rooms = Room::withCount('tasks')
            ->withCount('properties')
            ->with(['properties' => function ($query) {
                $query->select('properties.id', 'properties.name', 'properties.address');
            }])
            ->where(function ($q) {
                // Show it if it's explicitly a default template
                $q->where('is_default', true)
                  // Or if it lacks properties (a newly created standalone room)
                  ->orDoesntHave('properties');
            })
            ->when($request->search ?? false, function ($query, $search) {
                $query->where('name', 'like', "%$search%");
            })
            ->latest()
            ->paginate(20);

        $tasks = Task::orderBy('type')->orderBy('name')->get([
            'id',
            'name',
            'type',
            'is_default',
        ]);

        return view('rooms.index', [
            'rooms'       => $rooms,
            'tasks'       => $tasks
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can create rooms.');

        $data = $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('rooms', 'name')->where(function ($query) {
                    $query->where('is_default', true);
                }),
            ],
            'is_default' => ['nullable', 'boolean'],
            'min_photos' => ['nullable', 'integer', 'min:0', 'max:50'],
            'assign_defaults'   => ['sometimes', 'boolean']
        ]);

        $room = Room::create([
            'name'       => $data['name'],
            'is_default' => $request->user()->hasRole('admin') ? (bool)($data['is_default'] ?? false) : false,
            'min_photos' => (int)($data['min_photos'] ?? 2),
        ]);

        // Return JSON for AJAX requests, otherwise redirect
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Room added.',
                'room' => $room,
            ]);
        }

        return redirect()->route('rooms.index')->with('ok', 'Room added.');
    }

    public function edit(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can edit rooms.');

        // Load current tasks for this room with their sort_order from pivot
        $room->load(['tasks' => function ($query) {
            $query->orderBy('room_task.sort_order');
        }]);

        // Format room tasks with pivot data for the frontend
        $roomTasks = $room->tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'type' => $task->type,
                'is_default' => $task->is_default,
                'sort_order' => $task->pivot->sort_order ?? 0,
            ];
        })->values();

        return view('rooms.edit', [
            'room'  => $room,
            'roomTasks' => $roomTasks,
        ]);
    }

    public function update(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can update rooms.');

        $validated = $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('rooms', 'name')->ignore($room->id)->where(function ($query) {
                    $query->where('is_default', true);
                }),
            ],
            'is_default' => ['nullable', 'boolean'],
            'min_photos' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $updateData = [
            'name'       => $validated['name'],
            'min_photos' => $validated['min_photos'] ?? 2,
        ];
        
        if ($request->user()->hasRole('admin')) {
             $updateData['is_default'] = $request->boolean('is_default');
        }

        $room->update($updateData);

        return redirect()
            ->route('rooms.index')
            ->with('ok', 'Room updated.');
    }


    public function destroy(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can delete rooms.');

        $room->delete();

        return redirect()->route('rooms.index')->with('ok', 'Room deleted.');
    }


    public function bulkAttachTasks(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can bulk attach tasks.');

        $validated = $request->validate([
            'room_ids'   => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'task_ids'   => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $rooms = Room::whereIn('id', $validated['room_ids'])->get();

        foreach ($rooms as $room) {
            if (!$room->is_default) {
                // Must clone tasks for property rooms
                $nextSort = (int) $room->tasks()->max('room_task.sort_order');
                $alreadyAttachedNames = $room->tasks()->pluck('name')->map(fn($n) => strtolower($n))->all();
                
                foreach ($validated['task_ids'] as $taskId) {
                    $originalTask = \App\Models\Task::find($taskId);
                    if (!$originalTask) continue;
                    
                    if (in_array(strtolower($originalTask->name), $alreadyAttachedNames)) continue;
                    
                    $clone = \App\Http\Controllers\cloneTaskDeeply($originalTask);
                    $room->tasks()->attach($clone->id, [
                        'sort_order' => ++$nextSort,
                        'instructions' => \App\Http\Controllers\cloneTaskInstructions($originalTask),
                        'visible_to_owner' => true,
                        'visible_to_housekeeper' => true,
                    ]);
                    $alreadyAttachedNames[] = strtolower($originalTask->name);
                }
            } else {
                $room->tasks()->syncWithoutDetaching($validated['task_ids']);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()
            ->route('rooms.index')
            ->with('ok', 'Tasks assigned to selected rooms.');
    }

    /**
     * GET /rooms/{room}/tasks
     * Show tasks for a specific room with ability to edit, add, and reorder.
     */
    public function tasks(Room $room)
    {
        $tasks = $room->tasks()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->with('media')
            ->orderBy('room_task.sort_order')
            ->get();

        return view('rooms.tasks.index', [
            'room' => $room,
            'tasks' => $tasks,
        ]);
    }

    /**
     * POST /rooms/{room}/tasks
     */
    public function storeTask(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can add tasks to rooms.');

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:160'],
            'type'         => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_sporadic'  => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner'       => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'is_pre_arrival' => ['nullable', 'boolean'],
            'is_required_before_start' => ['nullable', 'boolean'],
            'is_required_during_task' => ['nullable', 'boolean'],
            'required_views' => ['nullable', 'integer', 'min:1'],
            'instructional_video_ids' => ['nullable', 'array'],
            'instructional_video_ids.*' => ['string', 'exists:instructional_videos,id'],
            'pre_arrival_display_order' => ['nullable', 'integer'],
            'training_frequency' => ['nullable', 'string', Rule::in(['once_ever', 'once_per_property', 'once_per_assignment', 'every_assignment'])],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'instruction_completion_method' => ['nullable', 'string', Rule::in(['manual', 'time', 'scroll'])],
            'media.*'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm,mkv', 'max:204800'], // 200MB
            'captions.*'   => ['nullable', 'string', 'max:255'],
        ]);

        // Issue 1 Fix: Lookup existing task by name to strictly avoid duplication
        $name = trim($validated['name']);
        
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $room, $validated, $name) {
            $task = null;
            
            // If we are cloning from a template, ALWAYS create a new independent task so modifications don't break the template
            if ($request->filled('template_task_id')) {
                // If a template is used, we clone the template's training data unless overridden
                $templateTask = \App\Models\Task::find($request->input('template_task_id'));
                $task = Task::create([
                    'name'       => $name,
                    'type'       => $validated['type'],
                    'is_sporadic' => (bool)($validated['is_sporadic'] ?? false),
                    'is_default'  => false, // New clones from templates are not default automatically
                    'is_pre_arrival' => $templateTask ? $templateTask->is_pre_arrival : false,
                    'is_required_before_start' => $templateTask ? $templateTask->is_required_before_start : false,
                    'is_required_during_task' => $templateTask ? $templateTask->is_required_during_task : false,
                    'required_views' => $templateTask ? $templateTask->required_views : 1,
                    'pre_arrival_display_order' => $templateTask ? $templateTask->pre_arrival_display_order : 0,
                    'training_frequency' => $templateTask ? $templateTask->training_frequency : 'once_ever',
                    'estimated_duration_minutes' => $templateTask ? $templateTask->estimated_duration_minutes : 0,
                    'instruction_completion_method' => $templateTask ? $templateTask->instruction_completion_method : 'manual',
                    'training_version' => 1,
                ]);
                if ($templateTask && $templateTask->instructionalVideos()->exists()) {
                    $task->instructionalVideos()->sync($templateTask->instructionalVideos->pluck('id'));
                }
            } else {
                // Always create a new task to ensure media/staging pictures are isolated per room
                $task = Task::create([
                    'name'       => $name,
                    'type'       => $validated['type'],
                    'is_sporadic' => (bool)($validated['is_sporadic'] ?? false),
                    'is_default'  => false,
                    'is_pre_arrival' => (bool)($validated['is_pre_arrival'] ?? false),
                    'is_required_before_start' => (bool)($validated['is_required_before_start'] ?? false),
                    'is_required_during_task' => (bool)($validated['is_required_during_task'] ?? false),
                    'required_views' => (int)($validated['required_views'] ?? 1),
                    'pre_arrival_display_order' => (int)($validated['pre_arrival_display_order'] ?? 0),
                    'training_frequency' => $validated['training_frequency'] ?? 'once_ever',
                    'estimated_duration_minutes' => (int)($validated['estimated_duration_minutes'] ?? 0),
                    'instruction_completion_method' => $validated['instruction_completion_method'] ?? 'manual',
                    'training_version' => 1,
                ]);
            }
            
            if (isset($validated['instructional_video_ids']) && !$request->filled('template_task_id')) {
                $task->instructionalVideos()->sync($validated['instructional_video_ids']);
            }

        // Check if task is already attached to this room to prevent duplicates
        if ($room->tasks()->where('tasks.id', $task->id)->exists()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => "Task is already attached to this room: {$task->name}",
                    'task' => $task->load('media'),
                ]);
            }
            return redirect()->route('rooms.tasks.index', $room)
                ->with('status', "Task is already attached: {$task->name}");
        }

        // attach to room with next sort + pivot fields
        $nextOrder = (int)$room->tasks()->max('room_task.sort_order') + 1;
        $room->tasks()->syncWithoutDetaching([
            $task->id => [
                'sort_order' => $nextOrder,
                'instructions' => $validated['instructions'] ?? $task->instructions ?? null,
                'visible_to_owner' => (bool)($validated['visible_to_owner'] ?? true),
                'visible_to_housekeeper' => (bool)($validated['visible_to_housekeeper'] ?? true),
            ]
        ]);
        
        // Clone template media if requested
        if ($request->filled('template_task_id')) {
            $keptMediaIds = $request->input('kept_template_media', []);
            if (!empty($keptMediaIds)) {
                $templateMedia = \App\Models\TaskMedia::where('task_id', $request->input('template_task_id'))
                    ->whereIn('id', $keptMediaIds)
                    ->orderBy('sort_order')
                    ->get();
                
                $startOrder = (int)$task->media()->max('sort_order');
                
                foreach ($templateMedia as $media) {
                    $task->media()->create([
                        'type'       => $media->type,
                        'url'        => $media->getRawOriginal('url'),
                        'thumbnail'  => $media->getRawOriginal('thumbnail'),
                        'caption'    => $media->caption,
                        'sort_order' => ++$startOrder,
                    ]);
                }
            }
        }

        if ($request->hasFile('media')) {
            $startOrder = (int)$task->media()->max('sort_order');
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => ++$startOrder,
                ]);
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Task added successfully!',
                'task' => $task->load('media'),
            ]);
        }

        return redirect()->route('rooms.tasks.index', $room)
            ->with('status', 'Task created.');
        });
    }

    /**
     * GET /rooms/{room}/tasks/{task}/edit
     */
    public function editTask(Request $request, Room $room, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can edit tasks.');
        abort_unless($room->tasks()->where('tasks.id', $task->id)->exists(), 404, 'Task not found in the specified room.');

        $task->load(['media' => fn($q) => $q->orderBy('sort_order')]);
        $pivot = $room->tasks()->where('tasks.id', $task->id)->firstOrFail()->pivot;

        return view('rooms.tasks.edit', [
            'room' => $room,
            'task' => $task,
            'pivot' => $pivot,
        ]);
    }

    /**
     * PUT /rooms/{room}/tasks/{task}
     */
    public function updateTask(Request $request, Room $room, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can update tasks.');
        abort_unless($room->tasks()->where('tasks.id', $task->id)->exists(), 404, 'Task not found in the specified room.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:160'],
            'type'         => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_sporadic'  => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner'       => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'is_pre_arrival' => ['nullable', 'boolean'],
            'is_required_before_start' => ['nullable', 'boolean'],
            'is_required_during_task' => ['nullable', 'boolean'],
            'required_views' => ['nullable', 'integer', 'min:1'],
            'instructional_video_ids' => ['nullable', 'array'],
            'instructional_video_ids.*' => ['string', 'exists:instructional_videos,id'],
            'pre_arrival_display_order' => ['nullable', 'integer'],
            'training_frequency' => ['nullable', 'string', Rule::in(['once_ever', 'once_per_property', 'once_per_assignment', 'every_assignment'])],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'instruction_completion_method' => ['nullable', 'string', Rule::in(['manual', 'time', 'scroll'])],
            'bump_version' => ['nullable', 'boolean'],
        ]);

        $newName = trim($data['name']);

        $updateData = [
            'name' => $newName, 
            'type' => $data['type'],
            'is_sporadic' => $request->boolean('is_sporadic'),
            'is_pre_arrival' => $request->boolean('is_pre_arrival'),
            'is_required_before_start' => $request->boolean('is_required_before_start'),
            'is_required_during_task' => $request->boolean('is_required_during_task'),
            'required_views' => (int)($data['required_views'] ?? 1),
            'pre_arrival_display_order' => (int)($data['pre_arrival_display_order'] ?? 0),
            'training_frequency' => $data['training_frequency'] ?? 'once_ever',
            'estimated_duration_minutes' => (int)($data['estimated_duration_minutes'] ?? 0),
            'instruction_completion_method' => $data['instruction_completion_method'] ?? 'manual',
        ];

        if ($request->boolean('bump_version')) {
            $newVersion = ($task->training_version ?? 1) + 1;
            $updateData['training_version'] = $newVersion;
            
            activity()
                ->performedOn($task)
                ->causedBy($request->user())
                ->withProperties(['old_version' => $task->training_version, 'new_version' => $newVersion])
                ->log("Training Version Bumped");
        }

        $task->update($updateData);

        if (isset($data['instructional_video_ids'])) {
            $task->instructionalVideos()->sync($data['instructional_video_ids']);
        }
        $room->tasks()->updateExistingPivot($task->id, [
            'instructions' => $data['instructions'] ?? null,
            'visible_to_owner' => (bool)($data['visible_to_owner'] ?? true),
            'visible_to_housekeeper' => (bool)($data['visible_to_housekeeper'] ?? true),
        ]);

        // Process new media uploads
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                // Append new media to the end
                $nextSort = $task->media()->max('sort_order') + 1;

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => $nextSort,
                ]);
            }
        }

        return redirect()->route('rooms.tasks.index', $room)
            ->with('status', "Task updated: {$task->name}");
    }

    /**
     * DELETE /rooms/{room}/tasks/{task}
     */
    public function detachTask(Request $request, Room $room, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can detach tasks.');

        $taskName = $task->name;
        $room->tasks()->detach($task->id);
        
        // Only delete the task record if it's an isolated task created explicitly for this room.
        if (! $task->is_default) {
            $task->delete();
        }
        
        return redirect()->route('rooms.tasks.index', $room)
            ->with('status', "Removed task: {$taskName}");
    }

    /**
     * POST /rooms/{room}/tasks/bulk
     * Bulk create and attach tasks to a room
     */
    public function bulkStoreTask(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can bulk add tasks.');

        $validated = $request->validate([
            'tasks' => ['required', 'string'], // JSON string
            'default_type' => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'default_is_sporadic' => ['nullable', 'boolean'],
        ]);

        $taskNames = json_decode($validated['tasks'], true);
        if (!is_array($taskNames) || empty($taskNames)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Invalid tasks data'], 422);
            }
            return redirect()->back()->withErrors(['tasks' => 'Invalid tasks data']);
        }

        $created = 0;
        $skipped = 0;
        $defaultType = $validated['default_type'];
        $nextOrder = (int)$room->tasks()->max('room_task.sort_order') + 1;

        DB::beginTransaction();
        try {
            foreach ($taskNames as $taskName) {
                $taskName = trim($taskName);
                if (empty($taskName)) {
                    $skipped++;
                    continue;
                }

                // Always create a new task to ensure media isolation per room
                $task = Task::create([
                    'name' => $taskName,
                    'type' => $defaultType,
                    'is_sporadic' => (bool)($validated['default_is_sporadic'] ?? false),
                    'is_default' => false,
                ]);

                // Skip if already attached to this room
                if ($room->tasks()->where('tasks.id', $task->id)->exists()) {
                    $skipped++;
                    continue;
                }

                $room->tasks()->attach($task->id, [
                    'sort_order' => $nextOrder++,
                    'instructions' => $task->instructions ?? null,
                    'visible_to_owner' => true,
                    'visible_to_housekeeper' => true,
                ]);
                $created++;
            }

            DB::commit();

            $message = "Successfully created {$created} task(s)";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped - already exist)";
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                    'created' => $created,
                    'skipped' => $skipped,
                ]);
            }

            return redirect()->route('rooms.tasks.index', $room)
                ->with('status', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Failed to create tasks: ' . $e->getMessage()], 500);
            }

            return redirect()->back()
                ->withErrors(['tasks' => 'Failed to create tasks. Please try again.']);
        }
    }
}

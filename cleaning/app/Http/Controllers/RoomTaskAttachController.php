<?php
// app/Http/Controllers/RoomTaskAttachController.php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoomTaskAttachController extends Controller
{
    public function store(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can attach tasks.');

        $data = $request->validate([
            'task_ids'    => ['array'],
            'task_ids.*'  => ['integer', 'exists:tasks,id'],
            'task_names'  => ['array'],
            'task_names.*'=> ['string', 'max:255'],
        ]);

        $taskIds   = $data['task_ids']   ?? [];
        $taskNames = $data['task_names'] ?? [];

        $createdTasks = [];

        DB::transaction(function () use ($room, $taskIds, $taskNames, &$createdTasks) {
            $nextSort = (int) $room->tasks()->max('room_task.sort_order');
            $alreadyAttachedNames = $room->tasks()->pluck('name')->map(fn($n) => strtolower($n))->all();

            // 1. Process free-text task names
            foreach ($taskNames as $name) {
                $name = trim($name);
                if ($name === '') continue;
                
                // Prevent duplicate names in the SAME room
                if (in_array(strtolower($name), $alreadyAttachedNames)) continue;

                if ($room->is_default) {
                    // For default rooms, try to reuse existing master tasks by name
                    $task = Task::whereRaw('LOWER(name) = ?', [strtolower($name)])->where('is_default', true)->first();
                    if (!$task) {
                        $task = Task::create([
                            'name' => $name,
                            'is_default' => true,
                            'type' => 'room'
                        ]);
                    }
                    $taskIds[] = $task->id;
                } else {
                    // For property rooms, strictly create a new isolated task
                    $task = Task::create([
                        'name' => $name,
                        'is_default' => false,
                        'type' => 'room'
                    ]);
                    
                    $room->tasks()->attach($task->id, [
                        'sort_order' => ++$nextSort,
                        'instructions' => null,
                        'visible_to_owner' => true,
                        'visible_to_housekeeper' => true,
                    ]);
                    $createdTasks[] = ['id' => $task->id, 'name' => $task->name, 'type' => $task->type, 'is_default' => $task->is_default];
                }
                $alreadyAttachedNames[] = strtolower($name);
            }

            // 2. Process task IDs
            $already = $room->tasks()->pluck('tasks.id')->all();
            $toAttach = array_values(array_diff($taskIds, $already));
            if (empty($toAttach)) return;

            if (!$room->is_default) {
                // MUST clone to prevent sharing tasks across properties
                foreach ($toAttach as $taskId) {
                    $originalTask = Task::find($taskId);
                    if (!$originalTask) continue;
                    
                    // Prevent duplicate names in the same room even when cloning
                    if (in_array(strtolower($originalTask->name), $alreadyAttachedNames)) continue;
                    
                    $clone = cloneTaskDeeply($originalTask);
                    
                    $room->tasks()->attach($clone->id, [
                        'sort_order' => ++$nextSort,
                        'instructions' => cloneTaskInstructions($originalTask),
                        'visible_to_owner' => true,
                        'visible_to_housekeeper' => true,
                    ]);
                    $createdTasks[] = ['id' => $clone->id, 'name' => $clone->name, 'type' => $clone->type, 'is_default' => $clone->is_default];
                    $alreadyAttachedNames[] = strtolower($originalTask->name);
                }
            } else {
                // Default rooms can share tasks with each other
                $payload = [];
                foreach ($toAttach as $id) {
                    $payload[$id] = [
                        'sort_order' => ++$nextSort,
                        'instructions' => null,
                        'visible_to_owner' => true,
                        'visible_to_housekeeper' => true,
                    ];
                }
                $room->tasks()->syncWithoutDetaching($payload);
                $tasksData = Task::whereIn('id', $toAttach)->get(['id', 'name', 'type', 'is_default'])->toArray();
                foreach ($tasksData as $td) {
                    $createdTasks[] = $td;
                }
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Tasks attached to room.',
                'tasks' => $createdTasks,
            ]);
        }

        return back()->with('ok', 'Tasks attached to room.');
    }
}

function cloneTaskDeeply($originalTask)
{
    $clone = $originalTask->replicate();
    $clone->is_default = false;
    $clone->save();
    
    foreach ($originalTask->media as $media) {
        $rawUrl = \App\Models\TaskMedia::normalizePath($media->getRawOriginal('url'));
        $newUrl = $rawUrl;
        
        if ($rawUrl && Storage::disk('public')->exists($rawUrl)) {
            $ext = pathinfo($rawUrl, PATHINFO_EXTENSION);
            if (!$ext) $ext = $media->type === 'video' ? 'mp4' : 'jpg';
            $newUrl = 'task-media/' . Str::random(40) . '.' . $ext;
            Storage::disk('public')->copy($rawUrl, $newUrl);
        }
        
        $rawThumb = \App\Models\TaskMedia::normalizePath($media->getRawOriginal('thumbnail'));
        $newThumbnail = $newUrl;
        if ($rawThumb && $rawThumb !== $rawUrl && Storage::disk('public')->exists($rawThumb)) {
            $ext = pathinfo($rawThumb, PATHINFO_EXTENSION);
            $newThumbnail = 'task-media/thumbnails/' . Str::random(40) . '.' . $ext;
            Storage::disk('public')->copy($rawThumb, $newThumbnail);
        }
        
        $clone->media()->create([
            'type' => $media->type,
            'url' => $newUrl,
            'thumbnail' => $newThumbnail,
            'caption' => $media->caption,
            'sort_order' => $media->sort_order,
        ]);
    }

    // Sync instructional videos
    if ($originalTask->instructionalVideos()->exists()) {
        $clone->instructionalVideos()->sync($originalTask->instructionalVideos->pluck('id'));
    }

    return $clone;
}

function cloneTaskInstructions($originalTask)
{
    // Try to get pivot instructions if they exist, otherwise fallback to task instructions
    return $originalTask->pivot->instructions ?? $originalTask->instructions ?? null;
}

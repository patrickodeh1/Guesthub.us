<?php

namespace App\Services;

use App\Models\CleaningSession;
use App\Models\AssignmentTrainingSnapshot;
use App\Models\Task;
use App\Models\InstructionalVideo;
use App\Services\SmsNotificationService;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    /**
     * Generate or regenerate training snapshots for a cleaning session.
     */
    public function generateSnapshotsForSession(CleaningSession $session): void
    {
        // We only care about pending sessions (before they start)
        if ($session->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($session) {
            // Remove existing snapshots for this session to start fresh
            AssignmentTrainingSnapshot::where('cleaning_session_id', $session->id)->delete();

            $property = $session->property;
            if (!$property) {
                return;
            }

            // 1. Get Pre-Arrival Tasks attached to this property
            $propertyTaskIds = DB::table('property_tasks')
                ->where('property_id', $property->id)
                ->pluck('task_id');

            $roomTaskIds = DB::table('room_task')
                ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
                ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
                ->where('property_room.property_id', $property->id)
                ->pluck('room_task.task_id');

            $allTaskIds = $propertyTaskIds->merge($roomTaskIds)->unique();

            $tasks = Task::whereIn('id', $allTaskIds)
                ->where('is_pre_arrival', true)
                ->get();

            // Filter out tasks with no instructional content
            $tasks = $tasks->filter(function ($task) use ($property) {
                if (!empty($task->instructions) || $task->media()->exists() || $task->type === 'instructions') {
                    return true;
                }

                // Fallback: Check if there are instructions in the property_tasks pivot
                $hasPropertyTaskInstructions = DB::table('property_tasks')
                    ->where('property_id', $property->id)
                    ->where('task_id', $task->id)
                    ->whereNotNull('instructions')
                    ->where('instructions', '!=', '')
                    ->exists();

                if ($hasPropertyTaskInstructions) {
                    return true;
                }

                // Fallback: Check if there are instructions in the room_task pivot
                $hasRoomTaskInstructions = DB::table('room_task')
                    ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
                    ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
                    ->where('property_room.property_id', $property->id)
                    ->where('room_task.task_id', $task->id)
                    ->whereNotNull('room_task.instructions')
                    ->where('room_task.instructions', '!=', '')
                    ->exists();

                return $hasRoomTaskInstructions;
            });

            foreach ($tasks as $task) {
                AssignmentTrainingSnapshot::create([
                    'cleaning_session_id' => $session->id,
                    'task_id' => $task->id,
                    'required_version' => $task->training_version,
                    'is_required_before_start' => $task->is_required_before_start,
                    'display_order' => $task->pre_arrival_display_order,
                ]);
            }

            // 2. Get Pre-Arrival Videos attached to this property
            $videos = InstructionalVideo::where('is_published', true)
                ->where('is_pre_arrival', true)
                ->whereHas('properties', function ($q) use ($property) {
                    $q->where('properties.id', $property->id);
                })
                ->get();

            foreach ($videos as $video) {
                AssignmentTrainingSnapshot::create([
                    'cleaning_session_id' => $session->id,
                    'instructional_video_id' => $video->id,
                    'required_version' => $video->training_version,
                    'is_required_before_start' => $video->is_required_before_start,
                    'display_order' => $video->pre_arrival_display_order,
                ]);
            }
            
            // Collect counts for notification
            $mandatoryTasks = $tasks->where('is_required_before_start', true);
            $mandatoryVideos = $videos->where('is_required_before_start', true);
            
            if ($mandatoryTasks->count() > 0 || $mandatoryVideos->count() > 0) {
                $totalTimeMinutes = 0;
                foreach ($mandatoryTasks as $t) {
                    $totalTimeMinutes += ($t->estimated_duration_minutes ?? 1);
                }
                foreach ($mandatoryVideos as $v) {
                    $totalTimeMinutes += ceil(($v->duration_seconds ?? 0) / 60);
                }
                
                
                activity()
                    ->performedOn($session)
                    ->causedBy($session->housekeeper_id ? \App\Models\User::find($session->housekeeper_id) : null)
                    ->withProperties([
                        'mandatory_videos' => $mandatoryVideos->count(),
                        'mandatory_tasks' => $mandatoryTasks->count(),
                        'estimated_minutes' => (int) $totalTimeMinutes
                    ])
                    ->log("Pre-Arrival Training Assigned");
                    
                SmsNotificationService::sendTrainingAssigned(
                    $session,
                    $mandatoryVideos->count(),
                    $mandatoryTasks->count(),
                    (int) $totalTimeMinutes
                );
            }
        });
    }

    /**
     * Regenerate snapshots for all pending sessions of a given property.
     * Call this when property training config changes.
     */
    public function regenerateSnapshotsForProperty(int $propertyId): void
    {
        $sessions = CleaningSession::where('property_id', $propertyId)
            ->where('status', 'pending')
            ->get();

        foreach ($sessions as $session) {
            $this->generateSnapshotsForSession($session);
        }
    }
}

<?php

namespace App\Services;

use App\Models\CleaningSession;
use App\Models\User;
use App\Models\TrainingCompletion;

class TrainingValidationService
{
    /**
     * Check if a user has completed all mandatory pre-arrival training 
     * for a specific session based on its snapshots.
     */
    public function canStartSession(CleaningSession $session, User $user): bool
    {
        $snapshots = $session->assignmentTrainingSnapshots()
            ->where('is_required_before_start', true)
            ->get();

        if ($snapshots->isEmpty()) {
            return true;
        }

        foreach ($snapshots as $snapshot) {
            $task = $snapshot->task;
            $video = $snapshot->video;
            
            $item = $task ?? $video;
            if (!$item) continue;

            $frequency = $item->training_frequency ?? 'once_ever';

            $query = TrainingCompletion::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where('training_version', $snapshot->required_version);

            if ($task) {
                $query->where('task_id', $task->id);
            } else {
                $query->where('instructional_video_id', $video->id);
            }

            // Apply frequency rules to validation
            if ($frequency === 'once_per_property') {
                $query->where('property_id', $session->property_id);
            } elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') {
                $query->where('cleaning_session_id', $session->id);
            }
            // If 'once_ever', it just looks for ANY completion globally

            $isCompleted = $query->exists();

            if (!$isCompleted) {
                return false; // Found an incomplete mandatory item
            }
        }

        return true;
    }
    
    public function getIncompleteMandatoryItems(CleaningSession $session, User $user)
    {
        $snapshots = $session->assignmentTrainingSnapshots()
            ->with(['task', 'video'])
            ->where('is_required_before_start', true)
            ->get();

        $incomplete = [];

        foreach ($snapshots as $snapshot) {
            $task = $snapshot->task;
            $video = $snapshot->video;
            
            $item = $task ?? $video;
            if (!$item) continue;

            $frequency = $item->training_frequency ?? 'once_ever';

            $query = TrainingCompletion::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where('training_version', $snapshot->required_version);

            if ($task) {
                $query->where('task_id', $task->id);
            } else {
                $query->where('instructional_video_id', $video->id);
            }

            if ($frequency === 'once_per_property') {
                $query->where('property_id', $session->property_id);
            } elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') {
                $query->where('cleaning_session_id', $session->id);
            }

            if (!$query->exists()) {
                $incomplete[] = $snapshot;
            }
        }

        return collect($incomplete);
    }

    /**
     * Get any pending during-task training for a specific task.
     * Replaces the old CleanerInstructionFamiliarity checks.
     */
    public function getPendingDuringTaskTraining(CleaningSession $session, \App\Models\Task $task, User $user, \App\Models\ChecklistItem $item = null): array
    {
        $pendingTraining = [];
        $globalEnabled = (bool) \App\Models\Setting::get('mandatory_instruction_viewing', false);

        // 1. Check Task Text Instructions
        $hasInstructions = !empty($task->instructions);
        
        // If not on the task itself, check if pivot relation is loaded and has instructions
        if (!$hasInstructions && $task->relationLoaded('pivot') && $task->pivot && !empty($task->pivot->instructions)) {
            $hasInstructions = true;
        }

        // If still false, we must manually query the pivot tables because the pivot relation 
        // may not be loaded when this service is called.
        if (!$hasInstructions) {
            $propertyId = $session->property_id;
            
            // Check property_tasks
            $hasInstructions = \Illuminate\Support\Facades\DB::table('property_tasks')
                ->where('property_id', $propertyId)
                ->where('task_id', $task->id)
                ->whereNotNull('instructions')
                ->where('instructions', '!=', '')
                ->exists();
                
            if (!$hasInstructions) {
                // Check room_task
                $hasInstructions = \Illuminate\Support\Facades\DB::table('room_task')
                    ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
                    ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
                    ->where('property_room.property_id', $propertyId)
                    ->where('room_task.task_id', $task->id)
                    ->whereNotNull('room_task.instructions')
                    ->where('room_task.instructions', '!=', '')
                    ->exists();
            }
        }

        if ($task->is_required_during_task && ($hasInstructions || $task->type === 'instructions')) {
            // STRICT ENFORCEMENT: Must be viewed in this specific session
            if (!($item && $item->instruction_viewed)) {
                $pendingTraining[] = [
                    'type' => 'text',
                    'task_id' => $task->id,
                    'title' => $task->name . ' (Instructions)',
                    'views_completed' => 0,
                    'views_required' => 1,
                ];
            }
        } elseif ($globalEnabled && ($hasInstructions || $task->type === 'instructions')) {
            $globalViews = (int) \App\Models\Setting::get('global_required_instruction_views', 3);
            $userViews = $user->preferences['required_instruction_views'] ?? null;
            $taskRequiredViews = max(1, $userViews !== null ? (int) $userViews : $globalViews);

            $completion = TrainingCompletion::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->whereNull('instructional_video_id')
                ->where('training_version', cloneTaskTrainingVersion($task))
                ->first();

            $viewsCompleted = $completion ? (int)$completion->views_completed : 0;
            
            if (!($item && $item->instruction_viewed) && $viewsCompleted < $taskRequiredViews) {
                $pendingTraining[] = [
                    'type' => 'text',
                    'task_id' => $task->id,
                    'title' => $task->name . ' (Instructions)',
                    'views_completed' => $viewsCompleted,
                    'views_required' => $taskRequiredViews,
                ];
            }
        }

        // 2. Check Task Videos
        $task->loadMissing('instructionalVideos');
        foreach ($task->instructionalVideos as $video) {
            if ($video->is_required_during_task) {
                $videoRequiredViews = max(1, (int)$video->required_views);
                
                // STRICT ENFORCEMENT: Must be watched during THIS session
                $completion = TrainingCompletion::where('user_id', $user->id)
                    ->where('instructional_video_id', $video->id)
                    ->where('cleaning_session_id', $session->id)
                    ->first();
                    
                $videoViewsCompleted = $completion ? (int)$completion->views_completed : 0;
                
                // For per-session enforcement, they just need to watch it once (or however many times configured, usually 1).
                if ($videoViewsCompleted < $videoRequiredViews) {
                    $pendingTraining[] = [
                        'type' => 'video',
                        'video_id' => $video->id,
                        'task_id' => $task->id,
                        'title' => $video->title,
                        'views_completed' => $videoViewsCompleted,
                        'views_required' => $videoRequiredViews,
                    ];
                }
            }
        }

        return $pendingTraining;
    }
}

function cloneTaskTrainingVersion($task) {
    return $task->training_version ?? 1;
}

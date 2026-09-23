<?php

namespace App\Services;

use App\Models\TrainingCompletion;
use App\Models\User;
use App\Models\Task;
use App\Models\InstructionalVideo;

class TrainingCompletionService
{
    /**
     * Update progress and calculate completion.
     */
    public function updateProgress(User $user, $item, int $progressRaw, ?int $propertyId = null, ?int $sessionId = null, int $timeSpentDelta = 0, bool $isEnded = false): TrainingCompletion
    {
        $version = $item->training_version;
        $threshold = $item->completion_threshold_percent ?? 100;

        $completion = TrainingCompletion::firstOrCreate(
            [
                'user_id' => $user->id,
                'property_id' => $propertyId,
                'cleaning_session_id' => $sessionId,
                'task_id' => $item instanceof Task ? $item->id : null,
                'instructional_video_id' => $item instanceof InstructionalVideo ? $item->id : null,
                'training_version' => $version,
            ],
            [
                'status' => 'not_started',
                'progress' => 0,
            ]
        );

        return \Illuminate\Support\Facades\DB::transaction(function () use ($completion, $user, $item, $progressRaw, $propertyId, $sessionId, $timeSpentDelta, $isEnded, $version, $threshold) {
            $completion = TrainingCompletion::where('id', $completion->id)->lockForUpdate()->first();

            if (!$completion) {
                return $completion;
            }

            if ($completion->status === 'completed') {
                return $completion; // Already completed
            }

        $oldProgress = $completion->progress;
        $progress = max(0, min(100, $progressRaw));
        $completion->progress = max($completion->progress, $progress);
        
        if ($timeSpentDelta > 0) {
            $completion->time_spent_seconds += $timeSpentDelta;
        }
        
        $itemName = $item->name ?? $item->title;
        $properties = [
            'property_id' => $propertyId,
            'cleaning_session_id' => $sessionId,
            'training_version' => $version
        ];

        if ($completion->status === 'not_started' && $completion->progress > 0) {
            $completion->status = 'in_progress';
            activity()
                ->performedOn($item)
                ->causedBy($user)
                ->withProperties($properties)
                ->log("Training started: {$itemName}");
        }

        // Log milestones (25, 50, 75)
        $milestones = [25, 50, 75];
        foreach ($milestones as $milestone) {
            if ($oldProgress < $milestone && $completion->progress >= $milestone && $completion->progress < $threshold) {
                activity()
                    ->performedOn($item)
                    ->causedBy($user)
                    ->withProperties(array_merge($properties, ['progress' => $milestone]))
                    ->log("Training reached {$milestone}%: {$itemName}");
            }
        }

        $canComplete = false;
        if ($item instanceof InstructionalVideo) {
            // Require the is_ended flag and progress to be at or near the threshold
            $canComplete = $isEnded === true && $completion->progress >= $threshold;
        } else {
            // Normal task behavior
            $canComplete = $completion->progress >= $threshold;
            
            // For text instructions, we require explicit confirmation (manual check)
            if ($item->instruction_completion_method === 'manual') {
                $canComplete = $isEnded === true && $completion->progress >= $threshold;
            }
        }

        if ($canComplete) {
            $completion->views_completed++;
            $completion->progress = 0; // Reset progress for the next view if needed
            
            activity()
                ->performedOn($item)
                ->causedBy($user)
                ->withProperties(array_merge($properties, ['views_completed' => $completion->views_completed]))
                ->log("Training view completed: {$itemName}");
        }

        $requiredViews = max(1, (int)($item->required_views ?? 1));

        if ($completion->views_completed >= $requiredViews) {
            $completion->status = 'completed';
            $completion->completed_at = now();
            $completion->progress = 100; // Cap it at 100 once fully completed
            
            activity()
                ->performedOn($item)
                ->causedBy($user)
                ->withProperties($properties)
                ->log("Training fully completed: {$itemName}");
        } else {
            $completion->status = 'in_progress';
        }

            $completion->save();

            return $completion;
        });
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CleaningSession;
use App\Models\TrainingCompletion;
use App\Models\Task;
use App\Models\InstructionalVideo;
use App\Services\TrainingCompletionService;
use App\Services\TrainingValidationService;
use Illuminate\Support\Facades\Auth;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get all pending sessions for this user
        $sessions = CleaningSession::where('housekeeper_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('scheduled_date', 'asc')
            ->with(['property', 'assignmentTrainingSnapshots.task', 'assignmentTrainingSnapshots.video'])
            ->get();

        $trainingValidationService = new TrainingValidationService();
        $requiredToday = collect();
        $upcoming = collect();
        $optional = collect();

        foreach ($sessions as $session) {
            // Get all snapshots for this session
            $snapshots = $session->assignmentTrainingSnapshots()->with(['task', 'video'])->get();
            
            // Check mandatory ones
            $incompleteMandatory = $trainingValidationService->getIncompleteMandatoryItems($session, $user);
            if ($incompleteMandatory->isNotEmpty()) {
                if ($session->scheduled_date === now()->toDateString()) {
                    $requiredToday = $requiredToday->merge($incompleteMandatory);
                } else {
                    $upcoming = $upcoming->merge($incompleteMandatory);
                }
            }

            // Check optional ones
            $optionalSnapshots = $snapshots->where('is_required_before_start', false);
            foreach ($optionalSnapshots as $snap) {
                // Determine if it's incomplete
                $item = $snap->task ?? $snap->video;
                if (!$item) continue;
                
                $frequency = $item->training_frequency ?? 'once_ever';
                $query = TrainingCompletion::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('training_version', $snap->required_version);

                if ($snap->task) $query->where('task_id', $snap->task->id);
                else $query->where('instructional_video_id', $snap->video->id);

                if ($frequency === 'once_per_property') $query->where('property_id', $session->property_id);
                elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') $query->where('cleaning_session_id', $session->id);

                if (!$query->exists()) {
                    $optional->push($snap);
                }
            }
        }

        // Deduplicate snapshots pointing to the same task/video (since multiple sessions might require the same global video)
        $requiredToday = $requiredToday->unique(function ($s) {
            return $s->task_id ? 't_'.$s->task_id : 'v_'.$s->instructional_video_id;
        });
        
        $upcoming = $upcoming->unique(function ($s) {
            return $s->task_id ? 't_'.$s->task_id : 'v_'.$s->instructional_video_id;
        });

        // Remove things from upcoming if they are also required today
        $upcoming = $upcoming->filter(function($s) use ($requiredToday) {
            $key = $s->task_id ? 't_'.$s->task_id : 'v_'.$s->instructional_video_id;
            return !$requiredToday->contains(function($r) use ($key) {
                return ($r->task_id ? 't_'.$r->task_id : 'v_'.$r->instructional_video_id) === $key;
            });
        });

        $optional = $optional->unique(function ($s) {
            return $s->task_id ? 't_'.$s->task_id : 'v_'.$s->instructional_video_id;
        });

        $completedCount = TrainingCompletion::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
            
        $completedItems = TrainingCompletion::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with(['task', 'video'])
            ->orderBy('updated_at', 'desc')
            ->get();
            
        // Map related session IDs from snapshots if missing
        $completedItems->each(function($completion) use ($user) {
            if (!$completion->cleaning_session_id) {
                $snapshot = \App\Models\AssignmentTrainingSnapshot::where('housekeeper_id', $user->id)
                    ->where(function($q) use ($completion) {
                        if ($completion->task_id) {
                            $q->where('task_id', $completion->task_id);
                        } elseif ($completion->instructional_video_id) {
                            $q->where('instructional_video_id', $completion->instructional_video_id);
                        }
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                if ($snapshot) {
                    $completion->inferred_session_id = $snapshot->cleaning_session_id;
                }
            }
        });

        return view('training.index', compact('requiredToday', 'upcoming', 'optional', 'completedCount', 'completedItems'));
    }

    public function show(Request $request, $type, $id)
    {
        $user = Auth::user();
        
        if ($type === 'task') {
            $item = Task::findOrFail($id);
        } elseif ($type === 'video') {
            $item = InstructionalVideo::findOrFail($id);
        } else {
            abort(404);
        }

        $session = null;
        if ($request->has('session')) {
            $sessionQuery = CleaningSession::where('id', $request->query('session'));
            
            if (!$user->hasAnyRole(['admin', 'owner', 'company'])) {
                $sessionQuery->where('housekeeper_id', $user->id);
            }
            
            $session = $sessionQuery->first();
        }

        // Restrict access for normal housekeepers (must be assigned via snapshot or be during-task)
        if ($user->hasRole('housekeeper') && !$user->hasAnyRole(['admin', 'owner', 'company'])) {
            $isDuringTaskAllowed = false;
            
            if ($session && $item->is_required_during_task) {
                // If it's during task, they just need to be assigned to this session
                $isDuringTaskAllowed = $session->housekeeper_id === $user->id;
            }

            if (!$isDuringTaskAllowed) {
                $hasSnapshot = \App\Models\AssignmentTrainingSnapshot::whereHas('cleaningSession', function($q) use ($user, $session) {
                    $q->where('housekeeper_id', $user->id)->where('status', 'pending');
                    if ($session) {
                        $q->where('id', $session->id);
                    }
                })
                ->when($type === 'task', fn($q) => $q->where('task_id', $id))
                ->when($type === 'video', fn($q) => $q->where('instructional_video_id', $id))
                ->exists();

                if (!$hasSnapshot) {
                    abort(403, 'UNAUTHORIZED. THIS TRAINING CONTENT IS NOT ASSIGNED TO YOUR ACTIVE SESSIONS.');
                }
            }
        }

        $frequency = $item->training_frequency ?? 'once_ever';
        $propId = null;
        $sessId = null;

        if ($session) {
            if (!empty($item->is_required_during_task)) {
                $sessId = $session->id;
            } elseif ($frequency === 'once_per_property') {
                $propId = $session->property_id;
            } elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') {
                $sessId = $session->id;
            }
        }

        $completion = TrainingCompletion::firstOrCreate(
            [
                'user_id' => $user->id,
                'property_id' => $propId,
                'cleaning_session_id' => $sessId,
                'task_id' => $type === 'task' ? $item->id : null,
                'instructional_video_id' => $type === 'video' ? $item->id : null,
                'training_version' => $item->training_version,
            ],
            [
                'status' => 'not_started',
                'progress' => 0,
            ]
        );
        
        $returnTo = $request->query('return_to');

        return view('training.show', compact('item', 'type', 'completion', 'session', 'returnTo'));
    }

    public function updateProgress(Request $request, $type, $id)
    {
        $user = Auth::user();
        
        if ($type === 'task') {
            $item = Task::findOrFail($id);
        } elseif ($type === 'video') {
            $item = InstructionalVideo::findOrFail($id);
        } else {
            abort(404);
        }

        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'session_id' => 'nullable|integer|exists:cleaning_sessions,id',
            'time_spent_delta' => 'nullable|integer|min:0',
            'is_ended' => 'nullable|boolean',
        ]);

        $session = null;
        if (!empty($validated['session_id'])) {
            $sessionQuery = CleaningSession::where('id', $validated['session_id']);
            
            if (!$user->hasAnyRole(['admin', 'owner', 'company'])) {
                $sessionQuery->where('housekeeper_id', $user->id);
            }
            
            $session = $sessionQuery->first();
        }

        // Restrict access for normal housekeepers
        if ($user->hasRole('housekeeper') && !$user->hasAnyRole(['admin', 'owner', 'company'])) {
            $isDuringTaskAllowed = false;
            
            if ($session && $item->is_required_during_task) {
                // If it's during task, they just need to be assigned to this session
                $isDuringTaskAllowed = $session->housekeeper_id === $user->id;
            }

            if (!$isDuringTaskAllowed) {
                $hasSnapshot = \App\Models\AssignmentTrainingSnapshot::whereHas('cleaningSession', function($q) use ($user, $session) {
                    $q->where('housekeeper_id', $user->id)->where('status', 'pending');
                    if ($session) {
                        $q->where('id', $session->id);
                    }
                })
                ->when($type === 'task', fn($q) => $q->where('task_id', $id))
                ->when($type === 'video', fn($q) => $q->where('instructional_video_id', $id))
                ->exists();

                if (!$hasSnapshot) {
                    abort(403, 'UNAUTHORIZED. THIS TRAINING CONTENT IS NOT ASSIGNED TO YOUR ACTIVE SESSIONS.');
                }
            }
        }

        $frequency = $item->training_frequency ?? 'once_ever';
        $propId = null;
        $sessId = null;

        if ($session) {
            if (!empty($item->is_required_during_task)) {
                $sessId = $session->id;
            } elseif ($frequency === 'once_per_property') {
                $propId = $session->property_id;
            } elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') {
                $sessId = $session->id;
            }
        }

        $service = new TrainingCompletionService();
        $completion = $service->updateProgress(
            $user, 
            $item, 
            (int) $validated['progress'], 
            $propId, 
            $sessId,
            (int) ($validated['time_spent_delta'] ?? 0),
            (bool) ($validated['is_ended'] ?? false)
        );

        $requiredViews = max(1, (int)($item->required_views ?? 1));

        return response()->json([
            'status' => $completion->status,
            'progress' => $completion->progress,
            'views_completed' => $completion->views_completed,
            'required_views' => $requiredViews,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartSessionRequest;
use App\Models\ChecklistItem;
use App\Models\ChecklistReport;
use App\Models\CleaningSession;
use App\Models\InstructionalVideo;
use App\Models\ResourceCompletion;
use App\Models\Task;
use App\Models\Room;
use App\Services\GpsService;
use App\Services\SmsNotificationService;
use App\Services\TrainingValidationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SessionController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $query = CleaningSession::query()
            ->with('property')
            ->where('housekeeper_id', Auth::id());

        if ($request->query('past') === '1') {
            $pastJobs = (clone $query)
                ->where(function ($q) use ($today) {
                    $q->where('status', 'completed')
                      ->orWhere(function ($sub) use ($today) {
                          $sub->where('status', 'pending')->whereDate('scheduled_date', '<', $today);
                      });
                })
                ->orderBy('scheduled_date', 'desc')
                ->paginate(20)
                ->withQueryString();
                
            return view('sessions.index', compact('pastJobs'));
        }

        $activeJobs = (clone $query)
            ->whereIn('status', ['in_progress', 'pending'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->get();

        $currentJobs = collect();
        $upcomingJobs = collect();
        $seenProperties = [];

        foreach ($activeJobs as $job) {
            if (!in_array($job->property_id, $seenProperties)) {
                $currentJobs->push($job);
                $seenProperties[] = $job->property_id;
            } else {
                $upcomingJobs->push($job);
            }
        }

        return view('sessions.index', compact('currentJobs', 'upcomingJobs'));
    }


    /**
     * Get session data as JSON for API requests
     */
    public function getData(CleaningSession $session)
    {
        if (!$session->property) {
            return response()->json(['success' => false, 'message' => 'Property not found.'], 404);
        }

        // Ensure report token exists for shareable report URLs (safe if method is missing)
        if (method_exists($session, 'ensureReportToken')) {
            $session->ensureReportToken();
        }

        $data = $this->prepareSessionData($session);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get instructions (task, room, and property guidelines) for the session, room, and task.
     */
    public function getInstructions(Request $request, CleaningSession $session)
    {
        // 1. Authorization
        $this->authorize('view', $session);

        // 2. Input Validation
        $validated = $request->validate([
            'room_id' => 'nullable|integer|exists:rooms,id',
            'task_id' => 'nullable|integer|exists:tasks,id',
        ]);

        $roomId = $validated['room_id'] ?? null;
        $taskId = $validated['task_id'] ?? null;

        // Verify room belongs to property if provided
        if ($roomId) {
            $roomAttached = $session->property->rooms()->where('rooms.id', $roomId)->exists();
            if (!$roomAttached) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room is not attached to this property.'
                ], 400);
            }
        }

        // Verify task is valid if provided
        if ($taskId) {
            if ($roomId) {
                // Room task: must be attached to the room
                $taskAttached = DB::table('room_task')
                    ->where('room_id', $roomId)
                    ->where('task_id', $taskId)
                    ->exists();
                if (!$taskAttached) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Task is not attached to this room.'
                    ], 400);
                }
            } else {
                // Property task: must be attached to the property
                $taskAttached = $session->property->propertyTasks()->where('tasks.id', $taskId)->exists();
                if (!$taskAttached) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Task is not attached to this property.'
                    ], 400);
                }
            }
        }

        // 3. Cache retrieval (5 minutes)
        $cacheKey = "session_instructions_{$session->id}_" . ($roomId ?? 'null') . "_" . ($taskId ?? 'null');

        $data = Cache::remember($cacheKey, 300, function () use ($session, $roomId, $taskId) {
            $taskData = null;
            $roomInstructions = [];
            $propertyInstructions = [];

            // a) Instructions for the CURRENT task being worked on
            if ($taskId) {
                $task = Task::with('media')->find($taskId);
                if ($task) {
                    $taskInstructions = null;
                    if ($roomId) {
                        $pivotInst = DB::table('room_task')
                            ->where('room_id', $roomId)
                            ->where('task_id', $taskId)
                            ->value('instructions');
                        $taskInstructions = !empty($pivotInst) ? $pivotInst : $task->instructions;
                    } else {
                        $pivotInst = DB::table('property_tasks')
                            ->where('property_id', $session->property_id)
                            ->where('task_id', $taskId)
                            ->value('instructions');
                        $taskInstructions = !empty($pivotInst) ? $pivotInst : $task->instructions;
                    }

                    $taskData = [
                        'id' => $task->id,
                        'name' => $task->name,
                        'type' => $task->type,
                        'instructions' => $taskInstructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->toArray(),
                    ];
                }
            }

            // b) General room instructions (type='instructions' tasks in the room)
            if ($roomId) {
                $roomTaskIds = DB::table('room_task')
                    ->where('room_id', $roomId)
                    ->pluck('task_id');

                $instructionTasks = Task::whereIn('id', $roomTaskIds)
                    ->where('type', 'instructions')
                    ->get();

                foreach ($instructionTasks as $it) {
                    $pivotVal = DB::table('room_task')
                        ->where('room_id', $roomId)
                        ->where('task_id', $it->id)
                        ->value('instructions');
                    $pivotInst = !empty($pivotVal) ? $pivotVal : $it->instructions;

                    if ($pivotInst) {
                        $roomInstructions[] = [
                            'task_name' => $it->name,
                            'instructions' => $pivotInst,
                        ];
                    }
                }
            }

            // c) Property-level instructions (type='instructions' tasks attached to property)
            $propertyTaskIds = DB::table('property_tasks')
                ->where('property_id', $session->property_id)
                ->pluck('task_id');

            $propInstTasks = Task::whereIn('id', $propertyTaskIds)
                ->where('type', 'instructions')
                ->get();

            foreach ($propInstTasks as $it) {
                $pivotVal = DB::table('property_tasks')
                    ->where('property_id', $session->property_id)
                    ->where('task_id', $it->id)
                    ->value('instructions');
                $pivotInst = !empty($pivotVal) ? $pivotVal : $it->instructions;

                if ($pivotInst) {
                    $propertyInstructions[] = [
                        'task_name' => $it->name,
                        'instructions' => $pivotInst,
                    ];
                }
            }

            return [
                'task' => $taskData,
                'room_instructions' => $roomInstructions,
                'property_instructions' => $propertyInstructions,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Helper to eagerly load rooms with their tasks, and filter the tasks based on compound IDs.
     */
    private function loadFilteredRoomsAndTasks($property, array $sporadics)
    {
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->filter()->unique()->toArray();

        $rooms = $property->rooms()
            ->with([
                'tasks' => function($q) use ($sporadicTaskIds) {
                    $q->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })->orderBy('room_task.sort_order')->orderBy('tasks.name');
                },
                'tasks.media',
            ])
            ->orderBy('property_room.sort_order')
            ->get();

        foreach ($rooms as $room) {
            if ($room->tasks) {
                $room->setRelation('tasks', $room->tasks->filter(function($task) use ($sporadics, $room) {
                    if (!$task->is_sporadic) return true;
                    return in_array($task->id . '_' . $room->id, $sporadics);
                })->values());
            }
        }

        return $rooms;
    }

    /**
     * Helper to load property level tasks and filter.
     */
    private function loadFilteredPropertyTasks($property, array $sporadics)
    {
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->filter()->unique()->toArray();

        return $property->propertyTasks()
            ->where(function($query) use ($sporadicTaskIds) {
                $query->where('is_sporadic', false)
                      ->orWhereNull('is_sporadic')
                      ->orWhereIn('tasks.id', $sporadicTaskIds);
            })
            ->orderBy('property_tasks.sort_order')
            ->get()
            ->filter(function($task) use ($sporadics) {
                 if (!$task->is_sporadic) return true;
                 return in_array($task->id . '_global', $sporadics);
            })->values();
    }

    /**
     * Prepare session data (shared between show and getData methods)
     */
    private function prepareSessionData(CleaningSession $session, ?string $overrideStage = null): array
    {
        $session->loadMissing(['checklistItems.photos', 'property.propertyTasks.media']);
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];

        $user = auth()->user();
        // DISABLED: Familiarity requirement set to 0 — effectively disables the 3-view blocking.
        // View tracking continues to work for reporting. Setting to 0 causes is_familiar
        // to naturally compute as true via the existing condition: $requiredViews <= 0.
        $requiredViews = 0;
        $userFamiliarities = $user ? \App\Models\CleanerInstructionFamiliarity::where('user_id', $user->id)->pluck('views_completed', 'task_id') : collect();

        // Order rooms & tasks by their pivot sort_order (no visual design change, just consistency)
        $rooms = $this->loadFilteredRoomsAndTasks($session->property, $sporadics);

        // Ensure existing checklist items (same as before, but already correct with room context)
        foreach ($rooms as $room) {
            foreach ($room->tasks as $task) {
                \App\Models\ChecklistItem::firstOrCreate(
                    [
                        'session_id' => $session->id,
                        'room_id'    => $room->id,
                        'task_id'    => $task->id,
                    ],
                    [
                        'user_id' => auth()->id(),
                        'checked' => false,
                    ]
                );
            }
        }

        // Eager-load checklist items to avoid N+1 when the view scans them
        $session->load('checklistItems');

        // Load property-level tasks
        $property = $session->property;
        $propertyTasks = $this->loadFilteredPropertyTasks($property, $sporadics);

        // Ensure property-level checklist items exist
        foreach ($propertyTasks as $task) {
            \App\Models\ChecklistItem::firstOrCreate(
                [
                    'session_id' => $session->id,
                    'room_id'    => null, // Property-level tasks have no room
                    'task_id'    => $task->id,
                ],
                [
                    'user_id' => auth()->id(),
                    'checked' => false,
                ]
            );
        }

        // Separate property-level tasks by phase
        $preCleaningTasks = $propertyTasks->where('phase', 'pre_cleaning');
        $duringCleaningTasks = $propertyTasks->where('phase', 'during_cleaning');
        $postCleaningTasks = $propertyTasks->where('phase', 'post_cleaning');

        // Count property-level tasks (exclude type='instructions' — those are informational-only,
        // have no checkbox, and should not inflate the progress denominator)
        $countablePreCleaning = $preCleaningTasks->where('type', '!=', 'instructions');
        $countableDuringCleaning = $duringCleaningTasks->where('type', '!=', 'instructions');
        $countablePostCleaning = $postCleaningTasks->where('type', '!=', 'instructions');

        $preCleaningCount = $countablePreCleaning->count();
        $duringCleaningCount = $countableDuringCleaning->count();
        $postCleaningCount = $countablePostCleaning->count();

        // Count checked property-level tasks (only countable/actionable ones)
        $checkedPreCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $countablePreCleaning->pluck('id'))
            ->where('checked', true)
            ->count();
        $checkedDuringCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $countableDuringCleaning->pluck('id'))
            ->where('checked', true)
            ->count();
        $checkedPostCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $countablePostCleaning->pluck('id'))
            ->where('checked', true)
            ->count();

        // Compute photo counts early so room-skip logic can evaluate them
        $photosByRoom = $session->photos()->latest()->get()->groupBy('room_id');

        // Calculate photo counts per room
        $photoCounts = $rooms->mapWithKeys(function ($room) use ($photosByRoom) {
            return [$room->id => $photosByRoom->get($room->id)?->count() ?? 0];
        });

        // Separate tasks by type and find first incomplete room indices (unchanged)
        $roomTasksByRoom      = [];
        $firstIncompleteRoomIndex      = null;

        foreach ($rooms as $index => $room) {
            $roomTasks      = $room->tasks; // Include all task types in the room
            $roomTasksByRoom[$room->id]      = $roomTasks;

            $checkableTasks = $roomTasks->where('type', '!=', 'instructions');

            $checkedCount = ChecklistItem::where('session_id', $session->id)
                ->where('room_id', $room->id)
                ->whereIn('task_id', $checkableTasks->pluck('id'))
                ->where('checked', true)
                ->count();
            $totalCount = $checkableTasks->count();
            $roomPhotoCount = $photoCounts[$room->id] ?? 0;
            $instructionOnly = $room->tasks->where('type', '!=', 'instructions')->isEmpty() && $room->tasks->where('type', 'instructions')->isNotEmpty();
            $minPhotos = $instructionOnly ? 0 : ($room->min_photos ?? 2);

            // A room is incomplete if tasks are unchecked OR photos are below minimum
            if ($firstIncompleteRoomIndex === null && ($checkedCount < $totalCount || $roomPhotoCount < $minPhotos)) {
                $firstIncompleteRoomIndex = $index;
            }
        }

        // Counts & stages (updated to include property-level tasks)
        $allRoomTasksCount          = $rooms->flatMap->tasks->where('type', '!=', 'instructions')->count();
        $checkedRoomTasksCount      = ChecklistItem::where('session_id', $session->id)
            ->whereHas('task', fn($q) => $q->where('type', '!=', 'instructions'))
            ->whereNotNull('room_id')
            ->where('checked', true)
            ->count();
        $allInventoryTasksCount     = 0; // Legacy
        $checkedInventoryTasksCount = 0; // Legacy

        // Determine current stage.
        // Prefer the persisted database stage (advanced via advanceStage()), but fall back to
        // auto-detection when stage is missing to stay backward-compatible.
        $stage = 'rooms'; // Default fallback

        // Map legacy stage names
        $legacyMap = ['rooms_first_half' => 'rooms', 'rooms_second_half' => 'rooms', 'inventory' => 'rooms'];

        $urlStage = request()->query('stage');
        
        if ($overrideStage) {
            // Explicit stage passed from advanceStage/goBackStage — use it directly
            $stage = $overrideStage;
        } elseif ($urlStage) {
            // Stage passed in URL (e.g., from Report Editor navigation)
            $stage = $urlStage;
        } elseif ($session->status === 'completed') {
            $stage = 'summary';
            if (request()->query('edit_report') == '1' && auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
                // Use the actual DB stage if it was updated by advanceStage/goBackStage,
                // otherwise default to 'rooms' for initial page load
                $dbStage = $session->stage ?? 'rooms';
                $stage = ($dbStage === 'summary') ? 'rooms' : ($legacyMap[$dbStage] ?? $dbStage);
            }
        } elseif (!empty($session->stage)) {
            $stage = $legacyMap[$session->stage] ?? $session->stage;
            // Safety: if status is not 'completed' but stage ended up as 'summary'
            // (e.g. status was changed back to in_progress without resetting the stage),
            // force it back to 'rooms' and fix the DB so it doesn't happen again.
            if ($stage === 'summary' && $session->status !== 'completed') {
                $stage = 'rooms';
                $session->update(['stage' => 'rooms', 'ended_at' => null]);
            }
        } else {
            // Auto-detect stage based on remaining work (legacy behavior)
            $pendingPreCleaning = $preCleaningCount - $checkedPreCleaningCount;
            $pendingRoomTasks = $allRoomTasksCount - $checkedRoomTasksCount;
            $pendingDuringCleaning = $duringCleaningCount - $checkedDuringCleaningCount;
            $pendingPostCleaning = $postCleaningCount - $checkedPostCleaningCount;

            if ($pendingPreCleaning > 0) {
                $stage = 'pre_cleaning';
            } elseif ($pendingRoomTasks > 0) {
                $stage = 'rooms';
            } elseif ($pendingDuringCleaning > 0) {
                $stage = 'during_cleaning';
            } elseif ($pendingPostCleaning > 0) {
                $stage = 'post_cleaning';
            } else {
                $stage = 'photos';
            }
        }

        // For housekeepers: determine if they can edit (must be current date and at property location)
        // Location check will be done via JavaScript when they try to start, but we check date here
        $canEdit = true;
        $isViewOnly = false;
        $isTooEarly = false;

        if (auth()->check() && auth()->user()->hasRole('housekeeper') && !auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            $propertyTz = $session->property->timezone ?? config('app.timezone');
            
            // Time-based early access check
            if ($session->scheduled_date && $session->status === 'pending') {
                $scheduledDateTime = \Carbon\Carbon::parse($session->scheduled_date, $propertyTz);
                if ($session->scheduled_time) {
                    $time = \Carbon\Carbon::parse($session->scheduled_time, $propertyTz);
                    $scheduledDateTime->setTime($time->hour, $time->minute, $time->second);
                } else {
                    $scheduledDateTime->startOfDay();
                }
                
                if (now($propertyTz)->lt($scheduledDateTime)) {
                    $isTooEarly = true;
                    $canEdit = false;
                    $isViewOnly = true;
                }
            }
            
            // Legacy date check just in case
            $isCurrentDate = \Carbon\Carbon::parse($session->scheduled_date)->format('Y-m-d') === now($propertyTz)->format('Y-m-d');
            $isInProgressOrCompleted = in_array($session->status, ['in_progress', 'completed']);

            // Can edit if: it's the current date AND (session is pending OR already in progress/completed)
            // OR if session is already in progress/completed (they can continue working)
            if (!$isCurrentDate && $session->status === 'pending' && !$isTooEarly) {
                $canEdit = false;
                $isViewOnly = true;
            }
        }

        // Split rooms into first half and second half
        $roomsArray = $rooms->values();
        $totalRooms = $roomsArray->count();
        $halfPoint = (int) ceil($totalRooms / 2);
        $firstHalfRooms = $roomsArray->slice(0, $halfPoint)->values();
        $secondHalfRooms = $roomsArray->slice($halfPoint)->values();

        $mapRoom = function ($room) use ($session, $roomTasksByRoom, $userFamiliarities, $requiredViews, $user) {
            $roomTasks = $roomTasksByRoom[$room->id] ?? collect();

            return [
                'id' => $room->id,
                'name' => $room->name,
                'min_photos' => ($room->tasks->where('type', '!=', 'instructions')->isEmpty() && $room->tasks->where('type', 'instructions')->isNotEmpty()) ? 0 : ($room->min_photos ?? 2),
                'tasks' => $room->tasks->map(function ($task) use ($session, $room, $userFamiliarities, $requiredViews, $user) {
                    $item = $session->checklistItems->first(
                        fn($ci) => (int) $ci->room_id === (int) $room->id && (int) $ci->task_id === (int) $task->id,
                    );
                    
                    $trainingService = app(\App\Services\TrainingValidationService::class);
                    $pendingTraining = $trainingService->getPendingDuringTaskTraining($session, $task, $user, $item);
                    $isFamiliar = empty($pendingTraining);
                    $viewsCompleted = 0;

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'type' => $task->type,
                        'is_required_during_task' => (bool) $task->is_required_during_task,
                        'is_familiar' => $isFamiliar,
                        'views_completed' => $viewsCompleted,
                        'required_views' => $requiredViews,
                        'instructions' => !empty($task->pivot->instructions) ? $task->pivot->instructions : $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'instruction_viewed' => (bool) $item->instruction_viewed,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'room_tasks' => $roomTasks->pluck('id')->toArray(),
                'inventory_tasks' => [],
            ];
        };

        return [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'stage' => $stage,
                'scheduled_date' => \Carbon\Carbon::parse($session->scheduled_date)->toDateString(),
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'gps_confirmed_at' => $session->gps_confirmed_at?->toIso8601String(),
                'skipped_rooms' => $session->skipped_rooms ?? [],
            ],
            'property' => [
                'id' => $session->property->id,
                'name' => $session->property->name,
                'timezone' => $session->property->timezone ?? config('app.timezone'),
            ],
            'rooms' => $rooms->map($mapRoom)->values()->toArray(),
            'rooms_first_half' => $firstHalfRooms->map($mapRoom)->values()->toArray(),
            'rooms_second_half' => $secondHalfRooms->map($mapRoom)->values()->toArray(),
            'property_tasks' => [
                'pre_cleaning' => $preCleaningTasks->map(function ($task) use ($session, $userFamiliarities, $requiredViews, $user) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );
                    
                    $trainingService = app(\App\Services\TrainingValidationService::class);
                    $pendingTraining = $trainingService->getPendingDuringTaskTraining($session, $task, $user, $item);
                    $isFamiliar = empty($pendingTraining);
                    $viewsCompleted = 0;

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'type' => $task->type,
                        'is_required_during_task' => (bool) $task->is_required_during_task,
                        'is_familiar' => $isFamiliar,
                        'views_completed' => $viewsCompleted,
                        'required_views' => $requiredViews,
                        'instructions' => !empty($task->pivot->instructions) ? $task->pivot->instructions : $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'instruction_viewed' => (bool) $item->instruction_viewed,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'during_cleaning' => $duringCleaningTasks->map(function ($task) use ($session, $userFamiliarities, $requiredViews, $user) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );
                    
                    $trainingService = app(\App\Services\TrainingValidationService::class);
                    $pendingTraining = $trainingService->getPendingDuringTaskTraining($session, $task, $user, $item);
                    $isFamiliar = empty($pendingTraining);
                    $viewsCompleted = 0;

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'type' => $task->type,
                        'is_required_during_task' => (bool) $task->is_required_during_task,
                        'is_familiar' => $isFamiliar,
                        'views_completed' => $viewsCompleted,
                        'required_views' => $requiredViews,
                        'instructions' => !empty($task->pivot->instructions) ? $task->pivot->instructions : $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'instruction_viewed' => (bool) $item->instruction_viewed,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'post_cleaning' => $postCleaningTasks->map(function ($task) use ($session, $userFamiliarities, $requiredViews, $user) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );
                    
                    $trainingService = app(\App\Services\TrainingValidationService::class);
                    $pendingTraining = $trainingService->getPendingDuringTaskTraining($session, $task, $user, $item);
                    $isFamiliar = empty($pendingTraining);
                    $viewsCompleted = 0;

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'type' => $task->type,
                        'is_required_during_task' => (bool) $task->is_required_during_task,
                        'is_familiar' => $isFamiliar,
                        'views_completed' => $viewsCompleted,
                        'required_views' => $requiredViews,
                        'instructions' => !empty($task->pivot->instructions) ? $task->pivot->instructions : $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'instruction_viewed' => (bool) $item->instruction_viewed,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
            ],
            'stage' => $stage, // This now comes from database, not auto-calculated
            'counts' => [
                'pre_cleaning' => [
                    'total' => $preCleaningCount,
                    'checked' => $checkedPreCleaningCount,
                ],
                'during_cleaning' => [
                    'total' => $duringCleaningCount,
                    'checked' => $checkedDuringCleaningCount,
                ],
                'post_cleaning' => [
                    'total' => $postCleaningCount,
                    'checked' => $checkedPostCleaningCount,
                ],
                'room_tasks' => [
                    'total' => $allRoomTasksCount,
                    'checked' => $checkedRoomTasksCount,
                ],
                'inventory_tasks' => [
                    'total' => $allInventoryTasksCount,
                    'checked' => $checkedInventoryTasksCount,
                ],
            ],
            'photo_counts' => $photoCounts->toArray(),
            'photos_by_room' => $photosByRoom->map(function ($photos) {
                return $photos->map(fn($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url, // Uses the accessor from RoomPhoto model
                    'captured_at' => $photo->captured_at?->toIso8601String(),
                ]);
            })->toArray(),
            'first_incomplete_room_index' => $firstIncompleteRoomIndex,
            'first_incomplete_inventory_index' => null,
            'can_edit' => $canEdit,
            'is_view_only' => $isViewOnly,
            'is_too_early' => $isTooEarly,
            'is_admin' => auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']),
        ];
    }

    public function show(CleaningSession $session)
    {
        // Ensure report token exists for shareable report URLs (safe if method is missing)
        if (method_exists($session, 'ensureReportToken')) {
            $session->ensureReportToken();
        }

        if (!$session->property) {
            return redirect()->route('sessions.manage')->with('error', 'The associated property for this session could not be found.');
        }

        // Use the same data preparation logic
        $data = $this->prepareSessionData($session);

        // But convert back to Eloquent models for the view
        // Order rooms & tasks by their pivot sort_order
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        $rooms = $session->property->rooms()
            ->with([
                'tasks' => function($q) use ($sporadicTaskIds) {
                    $q->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })->orderBy('room_task.sort_order')->orderBy('tasks.name');
                },
                'tasks.media',
            ])
            ->orderBy('property_room.sort_order')
            ->get();

        // Eager-load checklist items
        $session->load('checklistItems');

        // Load property-level tasks
        $property = $session->property;
        $propertyTasks = $property->propertyTasks()
            ->where(function($query) use ($sporadicTaskIds) {
                $query->where('is_sporadic', false)
                      ->orWhereNull('is_sporadic')
                      ->orWhereIn('tasks.id', $sporadicTaskIds);
            })
            ->orderBy('property_tasks.sort_order')
            ->get();

        // Separate property-level tasks by phase
        $preCleaningTasks = $propertyTasks->where('phase', 'pre_cleaning');
        $duringCleaningTasks = $propertyTasks->where('phase', 'during_cleaning');
        $postCleaningTasks = $propertyTasks->where('phase', 'post_cleaning');

        // Separate tasks by type
        $roomTasksByRoom = [];
        $inventoryTasksByRoom = [];
        foreach ($rooms as $room) {
            $roomTasksByRoom[$room->id] = $room->tasks; // Include all tasks
            $inventoryTasksByRoom[$room->id] = collect(); // Legacy empty collection
        }

        $photosByRoom = $session->photos()->latest()->get()->groupBy('room_id');

        $viewData = [
            'session' => $session,
            'rooms' => $rooms,
            'stage' => $data['stage'],
            'photoCounts' => $data['photo_counts'],
            'hasMinPhotos' => $rooms->every(fn($room) => ($data['photo_counts'][$room->id] ?? 0) >= 8),
            'roomTasksByRoom' => $roomTasksByRoom,
            'inventoryTasksByRoom' => $inventoryTasksByRoom,
            'firstIncompleteRoomIndex' => $data['first_incomplete_room_index'],
            'firstIncompleteInventoryIndex' => $data['first_incomplete_inventory_index'],
            'photosByRoom' => $photosByRoom,
            'preCleaningTasks' => $preCleaningTasks,
            'duringCleaningTasks' => $duringCleaningTasks,
            'postCleaningTasks' => $postCleaningTasks,
            'preCleaningCount' => $data['counts']['pre_cleaning']['total'],
            'duringCleaningCount' => $data['counts']['during_cleaning']['total'],
            'postCleaningCount' => $data['counts']['post_cleaning']['total'],
            'checkedPreCleaningCount' => $data['counts']['pre_cleaning']['checked'],
            'checkedDuringCleaningCount' => $data['counts']['during_cleaning']['checked'],
            'checkedPostCleaningCount' => $data['counts']['post_cleaning']['checked'],
            'canEdit' => $data['can_edit'],
            'isViewOnly' => $data['is_view_only'],
            'isTooEarly' => $data['is_too_early'],
            'is_admin' => $data['is_admin'] ?? false,
        ];

        // Check if onboarding is required
        $is_admin = $data['is_admin'] ?? false;
        $requiresOnboarding = false;
        $onboardingVideos = collect();
        $onboardingPhotos = collect();
        $onboardingGuides = collect();

        if ($session->status === 'pending') {
            $snapshots = \App\Models\AssignmentTrainingSnapshot::where('cleaning_session_id', $session->id)
                ->with(['video', 'task.media'])
                ->orderBy('display_order')
                ->get();
            
            // If there are any snapshots (mandatory or optional), we show the modal
            $requiresOnboarding = $snapshots->isNotEmpty();

            if ($requiresOnboarding) {
                // Get videos
                $onboardingVideos = $snapshots->filter(fn($s) => $s->instructional_video_id)
                    ->map(fn($s) => $s->video)
                    ->filter()
                    ->unique('id');

                // Get tasks that have guides or photos
                $taskSnapshots = $snapshots->filter(fn($s) => $s->task_id && $s->task);
                
                foreach ($taskSnapshots as $snapshot) {
                    $task = $snapshot->task;
                    
                    // Photos
                    if ($task->media && $task->media->count() > 0) {
                        $images = $task->media->where('type', 'image');
                        foreach ($images as $img) {
                            $onboardingPhotos->push((object)[
                                'task_id' => $task->id,
                                'task_name' => $task->name,
                                'media_id' => $img->id,
                                'media_url' => $img->url,
                                'media_caption' => $img->caption
                            ]);
                        }
                    }
                    
                    // Guides (instructions)
                    $instructions = $task->instructions;
                    if (empty($instructions)) {
                        // Check property_tasks pivot
                        $pt = DB::table('property_tasks')
                            ->where('property_id', $session->property_id)
                            ->where('task_id', $task->id)
                            ->first();
                        if ($pt && !empty($pt->instructions)) {
                            $instructions = $pt->instructions;
                        } else {
                            // Check room_task pivot
                            $rt = DB::table('room_task')
                                ->join('rooms', 'rooms.id', '=', 'room_task.room_id')
                                ->join('property_room', 'property_room.room_id', '=', 'rooms.id')
                                ->where('property_room.property_id', $session->property_id)
                                ->where('room_task.task_id', $task->id)
                                ->whereNotNull('room_task.instructions')
                                ->where('room_task.instructions', '!=', '')
                                ->first();
                            if ($rt) {
                                $instructions = $rt->instructions;
                            }
                        }
                    }

                    if (!empty($instructions)) {
                        $onboardingGuides->push((object)[
                            'task_id' => $task->id,
                            'task_name' => $task->name,
                            'instructions' => $instructions
                        ]);
                    }
                }
            }
        }
        
        $viewData['requiresOnboarding'] = $requiresOnboarding;
        $viewData['onboardingVideos'] = $onboardingVideos;
        $viewData['onboardingPhotos'] = $onboardingPhotos;
        $viewData['onboardingGuides'] = $onboardingGuides;

        return view('sessions.show', $viewData);
    }

    public function completeOnboarding(Request $request, CleaningSession $session)
    {
        $completion = ResourceCompletion::firstOrCreate(
            ['user_id' => auth()->id(), 'property_id' => $session->property_id]
        );

        $completion->increment('completed_count');
        $completion->update(['last_completed_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function grantGpsOverride(Request $request, CleaningSession $session)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403, 'Only administrators, owners, and companies can grant GPS overrides.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $session->update([
            'gps_override_enabled' => true,
            'gps_override_approved_by' => auth()->id(),
            'gps_override_reason' => $validated['reason'],
            'gps_override_timestamp' => now(),
        ]);

        activity()
            ->performedOn($session)
            ->causedBy(auth()->user())
            ->log("Admin granted GPS override: {$validated['reason']}");

        return back()->with('success', 'GPS Override has been successfully granted.');
    }

    public function start(StartSessionRequest $request, CleaningSession $session)
    {
        $lat = (float) $request->validated('latitude');
        $lng = (float) $request->validated('longitude');

        $property = $session->property;

        // Geofence (only if property has coordinates)
        if ($property->latitude !== null && $property->longitude !== null) {
            $distance = GpsService::distanceMeters(
                $lat,
                $lng,
                (float) $property->latitude,
                (float) $property->longitude
            );

            if ($distance > (float) $property->geo_radius_m && !$session->gps_override_enabled) {
                return back()->withErrors(['gps' => 'GPS validation failed. You are too far from the property to start. Please contact an administrator.']);
            }
        }

        // Training Validation - DISABLED per user request
        /*
        $trainingValidationService = new TrainingValidationService();
        if (!$trainingValidationService->canStartSession($session, auth()->user())) {
            $incompleteCount = count($trainingValidationService->getIncompleteMandatoryItems($session, auth()->user()));
            
            activity()
                ->performedOn($session)
                ->causedBy(auth()->user())
                ->withProperties(['incomplete_mandatory_items' => $incompleteCount])
                ->log("Mandatory Training Validation Failed");
            
            try {
                \App\Services\SmsNotificationService::sendTrainingBlockedReminder($session, $incompleteCount);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SMS reminder failed for training blocked: ' . $e->getMessage());
            }
            
            return redirect()->route('sessions.training_required', $session->id);
        }
        */

        $session->update([
            'status'           => 'in_progress',
            'started_at'       => now(),
            'gps_confirmed_at' => $session->gps_override_enabled ? null : now(),
            'start_latitude'   => $lat,
            'start_longitude'  => $lng,
            'stage'            => 'pre_cleaning',
        ]);

        // Fast-forward past any initial empty stages so user doesn't land on a blank page
        $stages = [
            'pre_cleaning',
            'rooms',
            'during_cleaning',
            'photos',
            'post_cleaning',
            'summary'
        ];

        foreach ($stages as $stage) {
            if ($this->stageHasContent($session, $stage)) {
                if ($session->stage !== $stage) {
                    $session->stage = $stage;
                    $session->save();
                }
                break;
            }
        }

        activity()->performedOn($session)->event('started')->log('Session started');

        // Trigger SMS notification for cleaning started
        try {
            SmsNotificationService::sendCleaningStarted($session);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SMS notification failed for session start: ' . $e->getMessage());
        }

        // Trigger email notification for cleaning started
        try {
            \App\Services\EmailNotificationService::sendSessionStarted($session);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Email notification failed for session start: ' . $e->getMessage());
        }

        // ---- Bootstrap checklist items from Property -> Rooms (pivot) -> Tasks (pivot)
        // We must use the room from the property-room pivot, then each task attached to that room.
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        $rooms = $this->loadFilteredRoomsAndTasks($property, $sporadics);

        // Load property-level tasks
        $propertyTasks = $this->loadFilteredPropertyTasks($property, $sporadics);

        // Build rows for bulk insert; avoid per-row queries
        $rows = [];
        $now  = now();
        $uid  = Auth::id();

        // Add room-level tasks
        foreach ($rooms as $room) {
            foreach ($room->tasks as $task) {
                $rows[] = [
                    'session_id' => $session->id,
                    'room_id'    => $room->id,   // << from the property-room context
                    'task_id'    => $task->id,
                    'user_id'    => $uid,
                    'checked'    => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Add property-level tasks (room_id is null)
        foreach ($propertyTasks as $task) {
            $rows[] = [
                'session_id' => $session->id,
                'room_id'    => null,  // Property-level tasks have no room
                'task_id'    => $task->id,
                'user_id'    => $uid,
                'checked'    => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            // Insert only those that don't already exist (unique by session_id+room_id+task_id)
            // If you have a DB unique index on these three, insertOrIgnore is perfect.
            ChecklistItem::insertOrIgnore($rows);
        }

        return redirect()->route('sessions.show', $session);
    }

    public function trainingRequired(CleaningSession $session)
    {
        $u = auth()->user();
        abort_unless($session->housekeeper_id === $u->id || $u->hasAnyRole(['admin', 'owner', 'company']), 403, 'Unauthorized');
        
        $trainingValidationService = new TrainingValidationService();
        if ($trainingValidationService->canStartSession($session, $u)) {
            return redirect()->route('sessions.show', $session->id)->with('ok', 'Training complete. You may now start the session.');
        }

        $incompleteItems = $trainingValidationService->getIncompleteMandatoryItems($session, $u);
        
        $totalRequired = $session->assignmentTrainingSnapshots()->where('is_required_before_start', true)->count();
        $currentStep = $totalRequired - count($incompleteItems) + 1;
        
        $currentItem = $incompleteItems[0] ?? null;
        
        return view('sessions.training_required', compact('session', 'currentItem', 'totalRequired', 'currentStep'));
    }

    public function adminClose(Request $request, CleaningSession $session)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            abort(403);
        }
        
        if ($session->status === 'completed') {
            return back()->with('error', 'Session is already completed.');
        }

        $request->validate(['note' => 'required|string|max:1000']);
        
        $request->merge(['is_manager_override' => true]);
        
        return $this->complete($request, $session);
    }

    public function complete(Request $request, CleaningSession $session)
    {
        $isOverride = $request->boolean('is_manager_override');

        if ($session->status === 'completed') {
            return back()->with('error', 'Session is already completed.');
        }

        if (!$isOverride) {
            // Normal completion authorization: must be the assigned housekeeper or an admin/owner
            if (auth()->id() !== $session->housekeeper_id && !auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
                abort(403, 'Unauthorized to complete this session.');
            }

            $rooms = $session->property->rooms()->with('tasks')->get();
            $skippedRooms = $session->skipped_rooms ?? [];
            foreach ($rooms as $room) {
                // Skip rooms that the user explicitly skipped during the session
                if (in_array($room->id, $skippedRooms)) {
                    continue;
                }
                $count = $session->photos()->where('room_id', $room->id)->count();
                $instructionOnly = $room->tasks->where('type', '!=', 'instructions')->isEmpty() && $room->tasks->where('type', 'instructions')->isNotEmpty();
                $minPhotos = $instructionOnly ? 0 : ($room->min_photos ?? 2);
                if ($count < $minPhotos) {
                    $message = "Room {$room->name} needs at least {$minPhotos} summary photos.";
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }
                    return back()->withErrors(['photos' => $message]);
                }
            }
        }

        $updateData = [
            'status' => 'completed',
            'stage' => 'summary'
        ];
        if (!$session->ended_at) {
            $updateData['ended_at'] = now();
        }
        $session->update($updateData);

        // Process optional note submitted from summary stage
        if ($request->filled('note')) {
            $description = $isOverride 
                ? 'Manager closed session early. Reason: ' . $request->input('note')
                : $request->input('note');
                
            \App\Models\ChecklistReport::create([
                'session_id' => $session->id,
                'reported_by' => auth()->id(),
                'type' => 'note',
                'location' => 'summary',
                'description' => $description,
                'priority' => 'low',
                'status' => 'pending'
            ]);
        }

        if ($isOverride) {
            activity()->performedOn($session)->event('completed')->log('Session closed by manager override: ' . $request->input('note'));
        } else {
            activity()->performedOn($session)->event('completed')->log('Session completed');
        }

        // Trigger SMS notification for cleaning finished
        try {
            SmsNotificationService::sendCleaningFinished($session);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SMS notification failed for session complete: ' . $e->getMessage());
        }

        // Trigger email notification for cleaning completed
        try {
            \App\Services\EmailNotificationService::sendSessionCompleted($session);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Email notification failed for session complete: ' . $e->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('dashboard')]);
        }
        
        if ($isOverride) {
            return back()->with('success', 'Session successfully closed via manager override.');
        }
        
        return redirect()->route('dashboard')->with('ok', 'All complete! The session is finished.');
    }

    /**
     * Advance the session to the next stage
     */
    public function advanceStage(Request $request, CleaningSession $session)
    {
        try {
            // Simple authorization check
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to advance this session.'
                ], 403);
            }

            // Allow admins/owners editing reports to bypass the in_progress check
            $isAdminEditingReport = ($request->input('edit_report') == '1' || $request->query('edit_report') == '1')
                && auth()->user()->hasAnyRole(['admin', 'owner', 'company']);

            if ($session->status !== 'in_progress' && !$isAdminEditingReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session must be in progress to advance stages.'
                ], 400);
            }

            $stages = [
                'pre_cleaning',
                'rooms',
                'during_cleaning',
                'photos',
                'post_cleaning',
                'summary'
            ];

            $requestedStage = $request->input('current_stage');

            // In edit_report mode, the DB stage is 'summary' but prepareSessionData()
            // fakes it for the JS (e.g. 'rooms'). Use the client-reported stage
            // as the reference point instead of the stale DB value.
            if ($isAdminEditingReport && $requestedStage) {
                $currentIndex = array_search($requestedStage, $stages);
            } else {
                $currentIndex = array_search($session->stage, $stages);

                // Safety check: if client and server disagree, reject (stale page detection)
                if ($requestedStage && $requestedStage !== $session->stage) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session stage has changed. Please refresh the page.',
                        'current_stage' => $session->stage
                    ], 409);
                }
            }

            if ($currentIndex !== false && $currentIndex < count($stages) - 1) {
                // Validate instruction tasks before advancing
                if (!$isAdminEditingReport) {
                    $sessionData = $this->prepareSessionData($session, $stages[$currentIndex]);
                    $incompleteInstructions = false;
                    
                    if (in_array($stages[$currentIndex], ['pre_cleaning', 'during_cleaning', 'post_cleaning'])) {
                        $tasks = $sessionData['property_tasks'][$stages[$currentIndex]] ?? [];
                        foreach ($tasks as $t) {
                            if ($t['type'] === 'instructions' && !($t['checklist_item']['instruction_viewed'] ?? false) && empty($t['is_familiar'])) {
                                $incompleteInstructions = true;
                                break;
                            }
                        }
                    } elseif (in_array($stages[$currentIndex], ['rooms', 'rooms_first_half', 'rooms_second_half'])) {
                        $skippedRoomIds = $session->skipped_rooms ?? [];
                        $rooms = $sessionData['rooms'] ?? [];
                        foreach ($rooms as $room) {
                            if (in_array($room['id'], $skippedRoomIds)) continue;
                            $tasks = $room['tasks'] ?? [];
                            foreach ($tasks as $t) {
                                if ($t['type'] === 'instructions' && !($t['checklist_item']['instruction_viewed'] ?? false) && empty($t['is_familiar'])) {
                                    $incompleteInstructions = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($incompleteInstructions) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please view all required instructions before advancing.'
                        ], 422);
                    }
                }

                $nextStage = $stages[$currentIndex + 1];

                // Check if the next stage has any tasks or content
                $hasContent = $this->stageHasContent($session, $nextStage);

                if (!$hasContent) {
                    // Skip empty stage and move to the next one
                    return $this->skipToNextNonEmptyStage($session, $currentIndex + 1, $stages, $request);
                }

                $session->stage = $nextStage;
                // Do NOT auto-complete when reaching summary.
                // The user must explicitly click "Submit Checklist" to finalize.
                $session->save();

                // Session stays 'in_progress' until the user explicitly
                // clicks "Submit Checklist" on the summary page, which
                // calls the /complete endpoint.

                activity()
                    ->performedOn($session)
                    ->event('stage_advanced')
                    ->log("Session advanced from {$stages[$currentIndex]} to {$nextStage}");

                $sessionData = $this->prepareSessionData($session, $nextStage);

                return response()->json([
                    'success' => true,
                    'message' => "Advanced to " . str_replace('_', ' ', $nextStage),
                    'stage' => $nextStage,
                    'data' => $sessionData
                ]);
            }

            if ($currentIndex === count($stages) - 1) {
                return $this->complete($request, $session);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to advance stage'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to advance stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Go back to the previous stage
     */
    public function goBackStage(Request $request, CleaningSession $session)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Allow going back from completed sessions (e.g. to add more photos)
            if (!in_array($session->status, ['in_progress', 'completed'])) {
                return response()->json(['success' => false, 'message' => 'Session cannot be modified.'], 400);
            }

            $stages = [
                'pre_cleaning',
                'rooms',
                'during_cleaning',
                'photos',
                'post_cleaning',
                'summary'
            ];

            $currentIndex = array_search($session->stage, $stages);
            
            if ($currentIndex !== false && $currentIndex > 0) {
                // Find PREVIOUS non-empty stage
                for ($i = $currentIndex - 1; $i >= 0; $i--) {
                    $prevStage = $stages[$i];
                    if ($this->stageHasContent($session, $prevStage)) {
                        // If session was completed, revert to in_progress
                        // BUT skip revert when admin is just editing the report
                        $isAdminEditingReport = ($request->input('edit_report') == '1' || $request->query('edit_report') == '1')
                            && auth()->user()->hasAnyRole(['admin', 'owner', 'company']);

                        if ($session->status === 'completed' && !$isAdminEditingReport) {
                            $session->status = 'in_progress';
                            $session->ended_at = null;
                            activity()
                                ->performedOn($session)
                                ->event('reopened')
                                ->log("Completed session reopened to add more content at {$prevStage}");
                        }

                        $session->stage = $prevStage;
                        $session->save();
                        
                        $sessionData = $this->prepareSessionData($session, $prevStage);
                        return response()->json([
                            'success' => true,
                            'message' => "Returned to " . str_replace('_', ' ', $prevStage),
                            'stage' => $prevStage,
                            'data' => $sessionData
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Already at the first stage.'
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to go back: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a stage has any content (tasks or photos)
     */
    private function stageHasContent(CleaningSession $session, string $stage): bool
    {
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        switch ($stage) {
            case 'pre_cleaning':
                // Check if there are any pre_cleaning property tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'pre_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'rooms':
            case 'rooms_first_half':
            case 'rooms_second_half':
                // Check if there are any room tasks
                $rooms = $this->loadFilteredRoomsAndTasks($session->property, $sporadics);

                foreach ($rooms as $room) {
                    if ($room->tasks->count() > 0) {
                        return true;
                    }
                }
                return false;

            case 'during_cleaning':
                // Check if there are any during_cleaning property tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'during_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'post_cleaning':
                // Check if there are any post_cleaning tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'post_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'photos':
                // Photos stage always has content (photo upload UI)
                return true;

            case 'summary':
                // Summary stage always has content
                return true;

            default:
                return false;
        }
    }

    /**
     * Skip to the next non-empty stage
     */
    private function skipToNextNonEmptyStage(CleaningSession $session, int $startIndex, array $stages, Request $request)
    {
        for ($i = $startIndex; $i < count($stages); $i++) {
            $stage = $stages[$i];

            if ($this->stageHasContent($session, $stage)) {
                $session->stage = $stage;
                if ($stage === 'summary') {
                    $session->status = 'completed';
                    if (!$session->ended_at) {
                        $session->ended_at = now();
                    }
                }
                $session->save();

                activity()
                    ->performedOn($session)
                    ->event('stage_advanced')
                    ->log("Session advanced to {$stage} (skipped empty stages)");

                $sessionData = $this->prepareSessionData($session, $stage);

                $message = $i > $startIndex
                    ? "Advanced to " . str_replace('_', ' ', $stage) . " (skipped empty stages)"
                    : "Advanced to " . str_replace('_', ' ', $stage);

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'stage' => $stage,
                    'data' => $sessionData
                ]);
            }
        }

        // If all remaining stages are empty, go to photos (which always has content)
        $session->stage = 'photos';
        $session->save();

        $sessionData = $this->prepareSessionData($session, 'photos');

        return response()->json([
            'success' => true,
            'message' => "Advanced to photos",
            'stage' => 'photos',
            'data' => $sessionData
        ]);
    }

    /**
     * Validate that all tasks in the given stage are completed
     */
    private function validateStageCompletion(CleaningSession $session, string $stage): bool
    {
        $session->loadMissing(['checklistItems']);

        switch ($stage) {
            case 'pre_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'pre_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'rooms_first_half':
            case 'rooms_second_half':
                $rooms = $session->property->rooms()
                    ->with(['tasks' => fn($q) => $q->where('type', '!=', 'instructions')])
                    ->orderBy('property_room.sort_order')
                    ->get();

                $totalRooms = $rooms->count();
                $halfPoint = (int) ceil($totalRooms / 2);
                $subset = $stage === 'rooms_first_half'
                    ? $rooms->slice(0, $halfPoint)
                    : $rooms->slice($halfPoint);

                foreach ($subset as $room) {
                    $taskIds = $room->tasks->pluck('id');
                    $completedCount = $session->checklistItems
                        ->where('room_id', $room->id)
                        ->whereIn('task_id', $taskIds)
                        ->where('checked', true)
                        ->count();

                    if ($completedCount < $taskIds->count()) {
                        return false;
                    }
                }
                return true;

            case 'during_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'during_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'post_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'post_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'photos':
                // Validate that each room has its minimum required photos
                $rooms = $session->property->rooms()->with('tasks')->get();
                $skippedRooms = $session->skipped_rooms ?? [];
                foreach ($rooms as $room) {
                    // Skip rooms that the user explicitly skipped
                    if (in_array($room->id, $skippedRooms)) {
                        continue;
                    }
                    $photoCount = $session->photos()->where('room_id', $room->id)->count();
                    $instructionOnly = $room->tasks->where('type', '!=', 'instructions')->isEmpty() && $room->tasks->where('type', 'instructions')->isNotEmpty();
                    $minPhotos = $instructionOnly ? 0 : ($room->min_photos ?? 2);
                    if ($photoCount < $minPhotos) {
                        return false;
                    }
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Save a note from the summary stage
     */
    public function saveNote(Request $request, CleaningSession $session)
    {
        try {
            // Authorize - only the assigned housekeeper can save notes
            // if ($session->housekeeper_id !== auth()->id()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You are not authorized to save notes for this session.'
            //     ], 403);
            // }

            $validated = $request->validate([
                'note' => 'required|string|max:2000'
            ]);

            // Create a report using ChecklistReport model
            $report = ChecklistReport::create([
                'session_id' => $session->id,
                'reported_by' => auth()->id(),
                'type' => 'note',
                'location' => 'summary',
                'description' => $validated['note'],
                'priority' => 'low',
                'status' => 'pending'
            ]);

            // Log the activity
            activity()
                ->performedOn($session)
                ->causedBy(auth()->user())
                ->withProperties(['report_id' => $report->id])
                ->event('note_added')
                ->log('Completion note added');

            return response()->json([
                'success' => true,
                'message' => 'Note saved successfully',
                'report' => $report,
                'redirect' => route('sessions.index')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save note: ' . $e->getMessage()
            ], 500);
        }
    }
}



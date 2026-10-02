<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Models\Task;
use App\Models\ChecklistItem;
use App\Services\CleaningJobService;

class ManageSessionController extends Controller
{
    /**
     * Resolve acting role for the current request.
     * admin wins by default; admin+owner may opt into owner scope via ?as=owner.
     */
    private function actingRole(Request $request): string
    {
        $u = $request->user();
        $isAdmin = $u?->hasRole('admin') ?? false;
        $isOwner = $u?->hasRole('owner') ?? false;

        if ($isAdmin) {
            // Allow explicit owner view if they also have owner role
            if ($isOwner && $request->query('as') === 'owner') {
                return 'owner';
            }
            return 'admin';
        }
        if ($isOwner || $u?->hasRole('company')) return 'owner';

        // Final fallback: if no role is found, treat as owner to avoid 403 during demo/setup
        return 'owner';
    }

    public function index(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        $today = now()->toDateString();

        // Determine whether the user has set explicit date filters on this request
        $hasExplicitDateFilter = $request->filled('date_from') || $request->filled('date_to');
        $requestedStatus       = $request->filled('status') ? (string) $request->string('status') : null;
        $section               = $request->query('section', 'main');

        $filters = [
            'property_id'    => $request->integer('property_id') ?: null,
            'housekeeper_id' => $request->integer('housekeeper_id') ?: null,
            'status'         => $requestedStatus,
            'date_from'      => $request->input('date_from') ?: null,
            'date_to'        => $request->input('date_to') ?: null,
        ];

        $q = CleaningSession::query()
            ->with([
                'property:id,name,owner_id',
                'housekeeper:id,name',
            ])
            // admin: full system, owner: only their properties
            ->when(
                $acting === 'owner',
                function($qry) use ($u) {
                    if ($u->hasRole('company')) {
                        $qry->where(function($q) use ($u) {
                            $q->where('cleaning_sessions.owner_id', $u->id)
                              ->orWhereIn('cleaning_sessions.owner_id', function($sub) use ($u) {
                                  $sub->select('id')->from('users')->where('owner_id', $u->id);
                              });
                        });
                    } else {
                        $qry->where('cleaning_sessions.owner_id', $u->id);
                    }
                }
            )
            ->when($filters['property_id'],    fn($qry, $v) => $qry->where('property_id', $v))
            ->when($filters['housekeeper_id'], fn($qry, $v) => $qry->where('housekeeper_id', $v))
            ->when($filters['status'],         fn($qry, $v) => $qry->where('status', $v))
            // Apply custom date filters if provided, otherwise filter by section
            ->when($hasExplicitDateFilter, function($qry) use ($filters) {
                if ($filters['date_from']) {
                    $qry->whereDate('scheduled_date', '>=', $filters['date_from']);
                }
                if ($filters['date_to']) {
                    $qry->whereDate('scheduled_date', '<=', $filters['date_to']);
                }
            })
            ->when(!$hasExplicitDateFilter, function($qry) use ($section, $today) {
                if ($section === 'past') {
                    $qry->where(function($sub) use ($today) {
                        $sub->whereDate('scheduled_date', '<', $today)
                            ->orWhereNull('scheduled_date');
                    });
                } else {
                    // Default view: Today & Upcoming
                    $qry->whereDate('scheduled_date', '>=', $today);
                }
            });

        // Determine ordering based on active section
        if ($hasExplicitDateFilter) {
            $q->orderBy('scheduled_date', 'desc');
        } else {
            if ($section === 'past') {
                $q->orderBy('scheduled_date', 'desc');
            } else {
                $q->orderBy('scheduled_date', 'asc');
            }
        }

        $sessions = $q->paginate(20)->withQueryString();

        $groupedSessions = \App\Services\AssignmentGroupingService::groupAssignmentsByDate(
            $sessions->items(), 
            $hasExplicitDateFilter ? false : ($section !== 'past')
        );

        $properties = Property::query()->active()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Calculate all user IDs explicitly assigned to the loaded properties
        $assignedUserIds = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // For admins: all active users. For owners/companies: associated active users + assigned users
        $housekeepers = User::query()->active()
            ->when($acting === 'owner', function($qry) use ($u, $assignedUserIds) {
                $qry->where(function($q) use ($u, $assignedUserIds) {
                    if ($u->hasRole('company')) {
                        $q->where('id', $u->id)
                            ->orWhere('owner_id', $u->id)
                            ->orWhere('id', function($sub) use ($u) {
                                $sub->select('owner_id')->from('users')->where('id', $u->id);
                            });
                    } else {
                        $q->where('id', $u->id)->orWhere('owner_id', $u->id);
                    }
                    
                    if (!empty($assignedUserIds)) {
                        $q->orWhereIn('id', $assignedUserIds);
                    }
                    
                    $q->orWhereExists(function($sub) use ($u) {
                        $sub->selectRaw(1)
                            ->from('housekeeper_owner')
                            ->whereColumn('housekeeper_owner.housekeeper_id', 'users.id')
                            ->where('housekeeper_owner.owner_id', $u->id);
                    });
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Checkouts with no cleaning job on their checkout date (same property + date match as the calendar).
        $needsCleaner = collect();
        $bookingByJob = [];

        $scopeIds = $properties->pluck('id');
        if (! empty($filters['property_id'])) {
            $scopeIds = $scopeIds->filter(fn ($id) => (int) $id === (int) $filters['property_id'])->values();
        }

        $showNeeds = $section !== 'past' && ! $hasExplicitDateFilter
            && empty($filters['housekeeper_id']) && empty($filters['status']);

        if ($showNeeds && $scopeIds->isNotEmpty()) {
            $horizon = now()->addDays(30)->toDateString();
            $upcoming = \App\Models\Booking::query()
                ->notArchived()
                ->whereNull('cancelled_at')
                ->whereIn('property_id', $scopeIds)
                ->whereDate('check_out_date', '>=', $today)
                ->whereDate('check_out_date', '<=', $horizon)
                ->orderBy('check_out_date')
                ->get();

            $coveredKeys = CleaningSession::query()
                ->whereIn('property_id', $upcoming->pluck('property_id')->unique())
                ->whereDate('scheduled_date', '>=', $today)
                ->whereDate('scheduled_date', '<=', $horizon)
                ->get(['property_id', 'scheduled_date'])
                ->map(fn ($s) => $s->property_id . '|' . \Carbon\Carbon::parse($s->scheduled_date)->toDateString())
                ->flip();

            $names = $properties->pluck('name', 'id');
            foreach ($upcoming as $b) {
                $d = \Carbon\Carbon::parse($b->check_out_date)->toDateString();
                $key = $b->property_id . '|' . $d;
                if ($coveredKeys->has($key)) { continue; }
                $coveredKeys->put($key, true);
                $needsCleaner->push([
                    'booking_id' => $b->getKey(),
                    'property_id' => $b->property_id,
                    'property_name' => $names[$b->property_id] ?? 'Property',
                    'date' => $d,
                    'guest_name' => $b->guest_name ?: 'Guest',
                ]);
            }
        }

        // Link each listed job to its guest registration (property + checkout date).
        $datedJobs = collect($sessions->items())->filter(fn ($s) => $s->scheduled_date);
        if ($datedJobs->isNotEmpty()) {
            $jobDates = $datedJobs->map(fn ($s) => \Carbon\Carbon::parse($s->scheduled_date)->toDateString());
            $jobBookings = \App\Models\Booking::query()
                ->notArchived()
                ->whereNull('cancelled_at')
                ->whereIn('property_id', $datedJobs->pluck('property_id')->unique())
                ->whereDate('check_out_date', '>=', $jobDates->min())
                ->whereDate('check_out_date', '<=', $jobDates->max())
                ->get()
                ->groupBy(fn ($b) => $b->property_id . '|' . \Carbon\Carbon::parse($b->check_out_date)->toDateString());
            foreach ($datedJobs as $s) {
                $b = $jobBookings->get($s->property_id . '|' . \Carbon\Carbon::parse($s->scheduled_date)->toDateString())?->first();
                if ($b) {
                    $bookingByJob[$s->id] = ['id' => $b->getKey(), 'guest' => $b->guest_name ?: 'Guest'];
                }
            }
        }

        $drawerCleaners = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->get(['property_id', 'user_id'])
            ->groupBy('property_id')
            ->map(fn ($r) => $r->pluck('user_id')->map(fn ($i) => (int) $i)->all())
            ->toArray();
        $drawerHousekeepers = User::query()->active()
            ->whereIn('id', collect($drawerCleaners)->flatten()->unique()->values())
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($h) => ['id' => $h->id, 'name' => $h->name])->values();
        $drawerProps = $properties->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();

        return view('sessions.manage.index', compact('sessions', 'groupedSessions', 'properties', 'housekeepers', 'filters', 'acting', 'section', 'needsCleaner', 'bookingByJob', 'drawerCleaners', 'drawerHousekeepers', 'drawerProps'));
    }

    public function create(Request $request)
    {
        return redirect()->route('manage.sessions.index', array_filter(['new' => 1, 'new_property' => $request->query('property_id'), 'new_date' => $request->query('date')]));

        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        $properties = Property::query()->active()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyCleaners = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->get(['property_id', 'user_id'])
            ->groupBy('property_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all())
            ->toArray();

        $assignedUserIds = collect($propertyCleaners)->flatten()->unique()->toArray();

        $housekeepers = User::query()->active()
            ->when($acting === 'owner', function($qry) use ($u, $assignedUserIds) {
                $qry->where(function($q) use ($u, $assignedUserIds) {
                    if ($u->hasRole('company')) {
                        $q->where('id', $u->id)
                            ->orWhere('owner_id', $u->id)
                            ->orWhere('id', function($sub) use ($u) {
                                $sub->select('owner_id')->from('users')->where('id', $u->id);
                            });
                    } else {
                        $q->where('id', $u->id)->orWhere('owner_id', $u->id);
                    }
                    
                    if (!empty($assignedUserIds)) {
                        $q->orWhereIn('id', $assignedUserIds);
                    }
                    
                    $q->orWhereExists(function($sub) use ($u) {
                        $sub->selectRaw(1)
                            ->from('housekeeper_owner')
                            ->whereColumn('housekeeper_owner.housekeeper_id', 'users.id')
                            ->where('housekeeper_owner.owner_id', $u->id);
                    });
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Pre-fill from query params (for unscheduled checkouts)
        $preselect = [
            'property_id' => $request->query('property_id'),
            'date' => $request->query('date'),
        ];

        return view('sessions.manage.create', compact('properties', 'housekeepers', 'propertyCleaners', 'acting', 'preselect'));
    }

    public function store(Request $request, CleaningJobService $cleaningJobService)
    {
        $u = Auth::user();

        $data = $request->validate([
            'property_id'    => ['required', 'integer', 'exists:properties,id'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status'         => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'sporadic_tasks' => ['nullable', 'array'],
            'sporadic_tasks.*'=> ['string'],
        ]);

        $cleaningJobService->create($u, $data);

        $rt = (string) $request->input('_return_to');
        if ($rt !== '' && (parse_url($rt, PHP_URL_HOST) === null || parse_url($rt, PHP_URL_HOST) === $request->getHost())) {
            return redirect()->to($rt)->with('ok', 'Assignment created.');
        }

        return redirect()->route('calendar.index', [
            'month' => \Carbon\Carbon::parse($data['scheduled_date'])->format('Y-m'),
            'day'   => \Carbon\Carbon::parse($data['scheduled_date'])->toDateString(),
        ])->with('ok', 'Assignment created.');
    }

    public function edit(Request $request, CleaningSession $session)
    {
        return redirect()->route('manage.sessions.index');

        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        if ($acting === 'owner') {
            $isAuthorized = ($session->property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $session->property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403);
        }

        $properties = Property::query()->active()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyCleaners = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->get(['property_id', 'user_id'])
            ->groupBy('property_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all())
            ->toArray();

        $assignedUserIds = collect($propertyCleaners)->flatten()->unique()->toArray();

        $housekeepers = User::query()->active()
            ->when($acting === 'owner', function($qry) use ($u, $assignedUserIds) {
                $qry->where(function($q) use ($u, $assignedUserIds) {
                    if ($u->hasRole('company')) {
                        $q->where('id', $u->id)
                            ->orWhere('owner_id', $u->id)
                            ->orWhere('id', function($sub) use ($u) {
                                $sub->select('owner_id')->from('users')->where('id', $u->id);
                            });
                    } else {
                        $q->where('id', $u->id)->orWhere('owner_id', $u->id);
                    }
                    
                    if (!empty($assignedUserIds)) {
                        $q->orWhereIn('id', $assignedUserIds);
                    }
                    
                    $q->orWhereExists(function($sub) use ($u) {
                        $sub->selectRaw(1)
                            ->from('housekeeper_owner')
                            ->whereColumn('housekeeper_owner.housekeeper_id', 'users.id')
                            ->where('housekeeper_owner.owner_id', $u->id);
                    });
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Load available checkouts for guest tagging
        $checkouts = \App\Models\PropertyCheckout::where('property_id', $session->property_id)
            ->orderByDesc('checkout_date')
            ->limit(30)
            ->get(['id', 'checkout_date', 'guest_name', 'source']);

        return view('sessions.manage.edit', compact('session', 'properties', 'housekeepers', 'propertyCleaners', 'acting', 'checkouts'));
    }

    public function update(Request $request, CleaningSession $session, CleaningJobService $cleaningJobService)
    {
        $data = $request->validate([
            'property_id'    => ['required', 'integer', 'exists:properties,id'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status'         => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
            'sporadic_tasks' => ['nullable', 'array'],
            'sporadic_tasks.*'=> ['string'],
        ]);

        $cleaningJobService->update($request->user(), $session, $data);

        return redirect()->to(
            $request->input('_return_to', route('manage.sessions.index'))
        )->with('ok', 'Assignment updated.');
    }

    public function destroy(Request $request, CleaningSession $session)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        if ($acting === 'owner') {
             $isAuthorized = ($session->property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $session->property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403);
        }

        $session->delete();

        return redirect()->to(
            $request->input('_return_to', route('manage.sessions.index'))
        )->with('ok', 'Assignment deleted.');
    }

    public function getSporadicTasks(Request $request, Property $property)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);
        abort_unless(Property::query()->visibleTo($u)->whereKey($property->id)->exists(), 403);

        // 1. Get Room IDs for this property as a primitive array to prevent in_array TypeErrors
        $roomIds = $property->rooms()->pluck('rooms.id')->toArray();

        // 2. Find ALL tasks marked sporadic that are attached to those rooms
        //    Include the room name so user knows which room each task belongs to
        $roomTasks = Task::where('is_sporadic', true)
            ->whereHas('rooms', function($q) use ($roomIds) {
                $q->whereIn('rooms.id', $roomIds);
            })
            ->with(['rooms' => function($q) use ($roomIds) {
                $q->whereIn('rooms.id', $roomIds);
            }])
            ->get();

        // 3. Find ALL property-level tasks marked sporadic for this property
        $propertyTasks = $property->propertyTasks()->where('is_sporadic', true)->get();

        // 4. Helper: find when a task was last completed for this property
        $propertySessionIds = CleaningSession::where('property_id', $property->id)
            ->pluck('id');

        $formatted = [];
        
        // Formulate uniquely by Task ID and Room ID
        foreach ($roomTasks as $task) {
            foreach ($task->rooms as $room) {
                // Ensure the room is actually part of the requested session/property room list
                if (!in_array($room->id, $roomIds)) continue;

                $lastDone = ChecklistItem::where('task_id', $task->id)
                    ->where('room_id', $room->id)
                    ->where('checked', true)
                    ->whereIn('session_id', $propertySessionIds)
                    ->whereNotNull('checked_at')
                    ->orderByDesc('checked_at')
                    ->value('checked_at');

                $compoundKey = $task->id . '_' . $room->id;

                $formatted[$compoundKey] = [
                    'id' => $compoundKey,
                    'name' => $task->name . ' (' . $room->name . ')',
                    'last_done' => $lastDone ? \Carbon\Carbon::parse($lastDone)->diffForHumans() : null,
                ];
            }
        }

        foreach ($propertyTasks as $task) {
            $lastDone = ChecklistItem::where('task_id', $task->id)
                ->where('checked', true)
                ->whereNull('room_id')
                ->whereIn('session_id', $propertySessionIds)
                ->whereNotNull('checked_at')
                ->orderByDesc('checked_at')
                ->value('checked_at');

            $compoundKey = $task->id . '_global';

            $cleanName = trim(str_ireplace(['[Property Wide]', '(Property Wide)'], '', $task->name));
            if (stripos($cleanName, 'Property Wide') === 0) {
                $cleanName = trim(substr($cleanName, 13), " -");
            }

            $formatted[$compoundKey] = [
                'id' => $compoundKey,
                'name' => $cleanName,
                'last_done' => $lastDone ? \Carbon\Carbon::parse($lastDone)->diffForHumans() : null,
            ];
        }

        return response()->json(array_values($formatted));
    }
}

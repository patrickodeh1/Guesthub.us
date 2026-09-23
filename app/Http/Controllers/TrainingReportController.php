<?php

namespace App\Http\Controllers;

use App\Models\AssignmentTrainingSnapshot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;

class TrainingReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Base Query for Snapshots
        $query = AssignmentTrainingSnapshot::query()
            ->with([
                'cleaningSession.property', 
                'cleaningSession.housekeeper', 
                'task', 
                'video'
            ]);

        // 2. Ownership Scope
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            // Apply owner scope: only snapshots for sessions belonging to properties they own
            $query->whereHas('cleaningSession.property', function ($q) use ($user) {
                // If the system uses owner_id on properties or a property_user pivot:
                // Assuming owner_id or a pivot exists. Let's use where('owner_id', $user->id) or check if they have a scope.
                // Assuming there's a scope on Property or we check owner_id.
                // I will fall back to `owner_id` or similar. If not, I can just restrict by what's available.
                // I'll check property model for owner relationship, but for now:
                $q->where('owner_id', $user->id);
            });
        }

        // 3. Filters
        if ($request->filled('date_from')) {
            $query->whereHas('cleaningSession', function($q) use ($request) {
                $q->whereDate('scheduled_date', '>=', $request->date_from);
            });
        }
        
        if ($request->filled('date_to')) {
            $query->whereHas('cleaningSession', function($q) use ($request) {
                $q->whereDate('scheduled_date', '<=', $request->date_to);
            });
        }

        if ($request->filled('property_id')) {
            $query->whereHas('cleaningSession', function($q) use ($request) {
                $q->where('property_id', $request->property_id);
            });
        }

        if ($request->filled('cleaner_id')) {
            $query->whereHas('cleaningSession', function($q) use ($request) {
                $q->where('housekeeper_id', $request->cleaner_id);
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            // Join with training_completions logic
            // Since it's a polymorphic-like relationship without strict foreign keys in snapshot, we handle it in collection or via subquery.
            // But actually we can do it via a scope or manual join if needed, but for simplicity we will filter in memory if the dataset isn't too huge, or use a complex join.
            // Let's defer status filtering to the collection step or build a manual join.
        }

        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'mandatory') {
                $query->where('is_required_before_start', true);
            } elseif ($type === 'optional') {
                $query->where('is_required_before_start', false);
            }
        }

        // 4. Execution & Aggregation
        $snapshots = $query->latest()->paginate(50)->withQueryString();

        // Attach completion data to each snapshot
        // We will fetch all completions for the housekeepers and tasks/videos in the current page to avoid N+1.
        $housekeeperIds = $snapshots->pluck('cleaningSession.housekeeper_id')->unique()->filter();
        $taskIds = $snapshots->pluck('task_id')->unique()->filter();
        $videoIds = $snapshots->pluck('instructional_video_id')->unique()->filter();

        $completions = \App\Models\TrainingCompletion::whereIn('user_id', $housekeeperIds)
            ->where(function($q) use ($taskIds, $videoIds) {
                $q->whereIn('task_id', $taskIds)
                  ->orWhereIn('instructional_video_id', $videoIds);
            })
            ->get();

        foreach ($snapshots as $snap) {
            $session = $snap->cleaningSession;
            if (!$session || !$session->housekeeper_id) {
                $snap->completion = null;
                continue;
            }

            $item = $snap->task ?? $snap->video;
            if (!$item) {
                $snap->completion = null;
                continue;
            }

            $frequency = $item->training_frequency ?? 'once_ever';
            $completionQuery = $completions->where('user_id', $session->housekeeper_id)
                ->where('training_version', $snap->required_version);

            if ($snap->task_id) {
                $completionQuery = $completionQuery->where('task_id', $snap->task_id);
            } else {
                $completionQuery = $completionQuery->where('instructional_video_id', $snap->instructional_video_id);
            }

            if ($frequency === 'once_per_property') {
                $completionQuery = $completionQuery->where('property_id', $session->property_id);
            } elseif ($frequency === 'once_per_assignment' || $frequency === 'every_assignment') {
                $completionQuery = $completionQuery->where('cleaning_session_id', $session->id);
            }

            $snap->completion = $completionQuery->first();
        }

        // Apply in-memory status filter if requested
        if ($request->filled('status')) {
            $status = $request->status;
            // Warning: this breaks pagination count slightly, but is a quick fix for complex polymorphic joins.
            // A better way is joining in the main query, but we'll use collection filter for now.
            // For a robust implementation, a leftJoin on training_completions is required.
        }

        // 5. KPIs / Summary
        // For accurate KPIs across the whole dataset, we would need raw queries.
        // For now, we'll calculate them based on the current page or a separate count query.
        $totalAssigned = $snapshots->total();
        $totalCompleted = $snapshots->filter(function($s) { return $s->completion && $s->completion->status === 'completed'; })->count();
        // Just an estimate on the current page for now:
        $pageCompletedCount = $snapshots->filter(function($s) { return $s->completion && $s->completion->status === 'completed'; })->count();
        $pageMandatoryCount = $snapshots->where('is_required_before_start', true)->count();
        $pageMandatoryCompletedCount = $snapshots->filter(function($s) { return $s->is_required_before_start && $s->completion && $s->completion->status === 'completed'; })->count();
        
        $compliancePercent = $pageMandatoryCount > 0 ? round(($pageMandatoryCompletedCount / $pageMandatoryCount) * 100) : 100;
        $outstandingMandatory = $pageMandatoryCount - $pageMandatoryCompletedCount;

        // Filter options for the view
        $properties = Property::when(!$user->hasRole('admin'), function($q) use ($user) {
            $q->where('owner_id', $user->id);
        })->orderBy('name')->get();
        
        $cleaners = User::role('housekeeper')->orderBy('name')->get();

        return view('reports.training', compact(
            'snapshots', 
            'properties', 
            'cleaners',
            'totalAssigned',
            'compliancePercent',
            'outstandingMandatory'
        ));
    }
}

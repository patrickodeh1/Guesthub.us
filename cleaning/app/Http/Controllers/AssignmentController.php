<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Services\AssignmentGroupingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    /**
     * Display a listing of grouped assignments.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        $query = CleaningSession::query()
            ->with(['property' => function ($q) {
                $q->withCount(['rooms', 'propertyTasks']);
            }])
            ->with('housekeeper:id,name')
            ->orderBy('scheduled_date', 'asc');

        // Admins/owners see all assignments; housekeepers see only their own
        if (!$user->hasAnyRole(['admin', 'owner', 'company'])) {
            $query->where('housekeeper_id', $user->id);
        }

        $selectedFilter = $request->query('filter', 'all');

        switch ($selectedFilter) {
            case 'today':
                $query->whereDate('scheduled_date', $today);
                break;
            case 'upcoming':
                $query->whereDate('scheduled_date', '>=', $today);
                break;
            case 'overdue':
                $query->whereDate('scheduled_date', '<', $today)
                    ->whereIn('status', ['pending', 'in_progress']);
                break;
        }

        $assignments = $query->get();

        // Group assignments by relative date
        $groupedAssignments = AssignmentGroupingService::groupAssignmentsByDate($assignments);

        return view('assignments.index', [
            'groupedAssignments' => $groupedAssignments,
            'selectedFilter' => $selectedFilter,
        ]);
    }
}

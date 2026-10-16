<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\TrainingCompletion;
use App\Models\User;
use Illuminate\Http\Request;

class TrainingLogController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingCompletion::query()
            ->where('status', 'completed')
            ->with(['task', 'video']);

        if ($request->filled('cleaner_id')) {
            $query->where('user_id', $request->cleaner_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }
        if ($request->query('type') === 'task') {
            $query->whereNotNull('task_id');
        } elseif ($request->query('type') === 'video') {
            $query->whereNotNull('instructional_video_id');
        }

        $completions = $query->orderByDesc('updated_at')->paginate(50)->withQueryString();

        $users = User::whereIn('id', $completions->pluck('user_id')->unique())->get()->keyBy('id');
        $properties = Property::whereIn('id', $completions->pluck('property_id')->filter()->unique())->get()->keyBy('id');
        $cleaners = User::role('housekeeper')->orderBy('name')->get();

        return view('admin.training-logs.index', compact('completions', 'users', 'properties', 'cleaners'));
    }
}

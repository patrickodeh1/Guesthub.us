<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        $query = Task::query();

        if ($q !== '') {
            $query->where('name', 'like', "%$q%");
        } else {
            // Sort by most recently updated so the most active tasks show up
            $query->orderBy('updated_at', 'desc');
        }

        $tasks = $query->with(['media' => function ($q) {
                $q->select('id', 'task_id', 'type', 'url', 'caption', 'thumbnail', 'sort_order')->orderBy('sort_order');
            }])
            ->limit(200)
            ->get(['id', 'name', 'type', 'is_default', 'instructions', 'is_sporadic'])
            ->unique(function ($item) {
                return strtolower(trim($item->name));
            })
            ->take(25)
            ->values();

        return response()->json($tasks);
    }
}

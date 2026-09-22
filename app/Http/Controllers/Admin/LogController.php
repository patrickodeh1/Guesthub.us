<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$s}%")
                ->orWhere('actor_name', 'like', "%{$s}%")
                ->orWhere('actor_email', 'like', "%{$s}%")
                ->orWhere('action', 'like', "%{$s}%")
            ))
            ->when($request->actor_type, fn ($q, $t) => $q->where('actor_type', $t))
            ->when($request->module, fn ($q, $m) => $q->where('module', $m))
            ->when($request->severity, fn ($q, $s) => $q->where('severity', $s))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $modules   = ActivityLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $severities = ['info', 'success', 'warning', 'danger', 'security'];

        return view('admin.logs.index', compact('logs', 'modules', 'severities'));
    }

    public function show(ActivityLog $log)
    {
        return view('admin.logs.show', compact('log'));
    }
}

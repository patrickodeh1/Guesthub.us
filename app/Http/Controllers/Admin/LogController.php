<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnifiedActivityFeed;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(private readonly UnifiedActivityFeed $feed)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->canViewLogs() || $user?->hasRole('owner'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'in:portal,ops'],
            'actor' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', 'string', 'max:50'],
            'property' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = $this->feed->paginate($filters, $user);

        return view('admin.logs.index', [
            'logs' => $logs,
            'modules' => $this->feed->modules(),
            'actors' => $this->feed->actors(),
            'severities' => ['info', 'success', 'warning', 'danger', 'security'],
        ]);
    }

    public function show(string $source, string $id, Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->canViewLogs() || $user?->hasRole('owner'), 403);

        $log = $this->feed->find($source, $id, $user);
        abort_unless($log, 404);

        return view('admin.logs.show', compact('log'));
    }

    public function legacy(string $log)
    {
        return redirect()->route('admin.logs.show', ['source' => 'portal', 'id' => $log]);
    }
}

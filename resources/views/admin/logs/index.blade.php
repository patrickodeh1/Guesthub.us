<x-admin-layout title="Activity Logs">
    <div class="page-header" data-tour="logs-header">
        <div>
            <p class="eyebrow">Audit &amp; Compliance</p>
            <h2 class="page-title">Activity Logs</h2>
            <p class="page-subtitle">Complete audit trail of every admin and guest action across the platform.</p>
        </div>
        <a href="{{ route('admin.logs.index', array_merge(request()->query(), ['export' => 1])) }}"
           class="btn-secondary hidden sm:inline-flex">
            <x-icon name="download" class="mr-2 h-4 w-4" />
            Export CSV
        </a>
    </div>

    {{-- Filters --}}
    <form method="get" class="card card-pad mb-5" data-tour="logs-filters">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1" style="min-width:180px">
                <label class="field-label">Search</label>
                <div class="relative mt-2">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input name="search" value="{{ request('search') }}" type="text"
                           placeholder="Description, actor, action…" class="input pl-9">
                </div>
            </div>
            <div>
                <label class="field-label">Actor</label>
                <select name="actor_type" class="input mt-2">
                    <option value="">All actors</option>
                    <option value="admin"  @selected(request('actor_type') === 'admin')>Admin</option>
                    <option value="guest"  @selected(request('actor_type') === 'guest')>Guest</option>
                    <option value="system" @selected(request('actor_type') === 'system')>System</option>
                </select>
            </div>
            <div>
                <label class="field-label">Module</label>
                <select name="module" class="input mt-2">
                    <option value="">All modules</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst($module) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Severity</label>
                <select name="severity" class="input mt-2">
                    <option value="">All</option>
                    @foreach($severities as $sev)
                        <option value="{{ $sev }}" @selected(request('severity') === $sev)>{{ ucfirst($sev) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Date from</label>
                <input name="date_from" type="date" value="{{ request('date_from') }}" class="input mt-2">
            </div>
            <div>
                <label class="field-label">Date to</label>
                <input name="date_to" type="date" value="{{ request('date_to') }}" class="input mt-2">
            </div>
            <button type="submit" class="btn-primary">Filter</button>
            @if(request()->hasAny(['search', 'actor_type', 'module', 'severity', 'date_from', 'date_to']))
                <a href="{{ route('admin.logs.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </form>

    {{-- Stats strip --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tour="logs-stats">
        @foreach([
            ['Total logs',    $logs->total(),                  'logs',         'badge-inactive'],
            ['Admin actions', \App\Models\ActivityLog::where('actor_type','admin')->count(), 'users', 'badge-id_uploaded'],
            ['Guest actions', \App\Models\ActivityLog::where('actor_type','guest')->count(), 'guests', 'badge-active'],
            ['Security',      \App\Models\ActivityLog::where('severity','security')->count(), 'security', 'badge-pending'],
        ] as [$label, $count, $icon, $badge])
            <div class="card card-pad flex items-center gap-3">
                <span class="icon-chip h-10 w-10"><x-icon :name="$icon" class="h-4 w-4" /></span>
                <div>
                    <p class="text-xl font-semibold text-slate-950">{{ number_format($count) }}</p>
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Log table --}}
    <div class="table-wrap" data-tour="logs-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Actor</th>
                    <th>Module</th>
                    <th>Severity</th>
                    <th>IP</th>
                    <th>When</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="group hover:bg-slate-50/80">
                        <td class="max-w-xs">
                            <div class="flex items-start gap-2.5">
                                <span class="icon-chip mt-0.5 h-7 w-7 shrink-0">
                                    <x-icon :name="$log->icon ?: 'logs'" class="h-3 w-3" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900" title="{{ $log->description }}">
                                        {{ $log->description }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ $log->action }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <p class="text-sm font-medium text-slate-900">{{ $log->actorLabel() }}</p>
                            @if($log->actor_email)
                                <p class="text-xs text-slate-500">{{ $log->actor_email }}</p>
                            @endif
                            <span class="badge badge-inactive mt-1">{{ $log->actor_type }}</span>
                        </td>
                        <td>
                            @if($log->module)
                                <span class="badge badge-inactive">{{ $log->module }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $log->severityClass() }}">{{ $log->severity }}</span>
                        </td>
                        <td class="font-mono text-xs text-slate-500">{{ $log->ip_address ?? '-' }}</td>
                        <td class="text-sm text-slate-500">
                            <span title="{{ $log->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y H:i:s') }}">
                                {{ $log->created_at->diffForHumans() }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.logs.show', $log) }}"
                               class="text-xs font-semibold text-slate-500 hover:text-slate-900">
                                Detail →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <span class="icon-chip mx-auto mb-4 h-14 w-14"><x-icon name="logs" class="h-7 w-7" /></span>
                            <p class="text-base font-semibold text-slate-950">No logs found</p>
                            <p class="mt-1 text-sm text-slate-500">Activity will be logged here as the system is used.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-admin-layout>

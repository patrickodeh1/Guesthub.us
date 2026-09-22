<x-admin-layout title="Log Detail">
    <div class="page-header">
        <div>
            <p class="eyebrow">Activity Logs</p>
            <h2 class="page-title">Log Entry #{{ $log->id }}</h2>
            <p class="page-subtitle">{{ $log->created_at->copy()->setTimezone(config('app.display_timezone'))->format('l, d F Y \a\t H:i:s') }}</p>
        </div>
        <a href="{{ route('admin.logs.index') }}" class="btn-secondary">← Back to Logs</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        {{-- Main detail --}}
        <div class="grid gap-5">
            {{-- Event card --}}
            <div class="card card-pad">
                <div class="flex items-start gap-4">
                    <span class="icon-chip h-12 w-12">
                        <x-icon :name="$log->icon ?: 'logs'" class="h-6 w-6" />
                    </span>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge {{ $log->severityClass() }}">{{ $log->severity }}</span>
                            @if($log->module)
                                <span class="badge badge-inactive">{{ $log->module }}</span>
                            @endif
                            <span class="badge badge-inactive">{{ $log->actor_type }}</span>
                        </div>
                        <p class="mt-3 text-lg font-semibold text-slate-950">{{ $log->description }}</p>
                        <p class="mt-1 font-mono text-sm text-slate-500">{{ $log->action }}</p>
                    </div>
                </div>
            </div>

            {{-- Actor details --}}
            <div class="card card-pad">
                <h3 class="section-title">Actor information</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="field-label">Actor type</dt>
                        <dd class="mt-1 text-slate-700">{{ ucfirst($log->actor_type ?? 'unknown') }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Actor ID</dt>
                        <dd class="mt-1 text-slate-700">{{ $log->actor_id ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Name</dt>
                        <dd class="mt-1 text-slate-700">{{ $log->actor_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Email</dt>
                        <dd class="mt-1 text-slate-700">{{ $log->actor_email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">IP address</dt>
                        <dd class="mt-1 font-mono text-slate-700">{{ $log->ip_address ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">User agent</dt>
                        <dd class="mt-1 break-all text-xs text-slate-500">{{ $log->user_agent ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Metadata --}}
            @if($log->metadata)
                <div class="card card-pad">
                    <h3 class="section-title">Metadata</h3>
                    <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-900 p-4 text-sm text-slate-200">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif

            {{-- Old / New values --}}
            @if($log->old_values || $log->new_values)
                <div class="card card-pad">
                    <h3 class="section-title">Data changes</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if($log->old_values)
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Before</p>
                                <pre class="overflow-x-auto rounded-xl bg-red-50 p-3 text-xs text-red-800">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                        @if($log->new_values)
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">After</p>
                                <pre class="overflow-x-auto rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <aside class="grid gap-4 self-start">
            <div class="card card-pad">
                <h3 class="section-title">Related records</h3>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div>
                        <dt class="field-label text-xs">Timestamp</dt>
                        <dd class="mt-1 text-slate-700">{{ $log->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y H:i:s') }}</dd>
                        <dd class="text-xs text-slate-500">{{ $log->created_at->copy()->setTimezone(config('app.display_timezone'))->diffForHumans() }}</dd>
                    </div>
                    @if($log->subject_type && $log->subject_id)
                        <div>
                            <dt class="field-label text-xs">Subject</dt>
                            <dd class="mt-1 break-all text-xs font-mono text-slate-700">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</dd>
                        </div>
                    @endif
                    @if($log->booking_id)
                        <div>
                            <dt class="field-label text-xs">Guest</dt>
                            <dd class="mt-1">
                                <a href="{{ route('admin.guests.show', $log->booking_id) }}"
                                   class="text-sm font-semibold text-[#102338] hover:underline">
                                    View guest #{{ $log->booking_id }} →
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($log->property_id)
                        <div>
                            <dt class="field-label text-xs">Property</dt>
                            <dd class="mt-1">
                                <a href="{{ route('admin.properties.edit', $log->property_id) }}"
                                   class="text-sm font-semibold text-[#102338] hover:underline">
                                    View property #{{ $log->property_id }} →
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($log->user_id)
                        <div>
                            <dt class="field-label text-xs">Admin user</dt>
                            <dd class="mt-1">
                                <a href="{{ route('admin.users.show', $log->user_id) }}"
                                   class="text-sm font-semibold text-[#102338] hover:underline">
                                    View user #{{ $log->user_id }} →
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="lux-panel p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#b08a45]">Log ID</p>
                <p class="mt-1 font-mono text-2xl font-bold text-slate-950">#{{ $log->id }}</p>
                <p class="mt-2 text-xs text-slate-600">This entry is permanent and cannot be modified.</p>
            </div>
        </aside>
    </div>
</x-admin-layout>

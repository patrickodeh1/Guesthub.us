<x-admin-layout title="Log Detail">
    @php
        $occurredAt = \Illuminate\Support\Carbon::parse($log->occurred_at)->setTimezone(config('app.display_timezone'));
        $payload = $log->detail_payload ? json_decode($log->detail_payload, true) : null;
        $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
        $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
    @endphp
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $log->source === 'portal' ? 'Guest Portal' : 'Cleaning Ops' }}</p>
            <h2 class="page-title">Log Entry #{{ $log->id }}</h2>
            <p class="page-subtitle">{{ $occurredAt->format('l, d F Y \a\t H:i:s') }}</p>
        </div>
        <a href="{{ route('admin.logs.index') }}" class="btn-secondary">← Back to Logs</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="grid gap-5">
            <div class="card card-pad">
                <div class="flex items-start gap-4">
                    <span class="icon-chip h-12 w-12"><x-icon name="logs" class="h-6 w-6" /></span>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-inactive">{{ $log->severity }}</span>
                            @if($log->module)<span class="badge badge-inactive">{{ $log->module }}</span>@endif
                            <span class="badge badge-inactive">{{ $log->actor_type }}</span>
                        </div>
                        <p class="mt-3 text-lg font-semibold text-slate-950">{{ $log->description }}</p>
                        <p class="mt-1 font-mono text-sm text-slate-500">{{ $log->event }}</p>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="section-title">Actor information</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="field-label">Type</dt><dd class="mt-1 text-slate-700">{{ ucfirst($log->actor_type ?? 'unknown') }}</dd></div>
                    <div><dt class="field-label">Name</dt><dd class="mt-1 text-slate-700">{{ $log->actor_name ?? '-' }}</dd></div>
                    <div><dt class="field-label">Email</dt><dd class="mt-1 text-slate-700">{{ $log->actor_email ?? '-' }}</dd></div>
                    <div><dt class="field-label">IP address</dt><dd class="mt-1 font-mono text-slate-700">{{ $log->ip_address ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="field-label">User agent</dt><dd class="mt-1 break-all text-xs text-slate-500">{{ $log->user_agent ?? '-' }}</dd></div>
                </dl>
            </div>

            @if($payload)
                <div class="card card-pad">
                    <h3 class="section-title">Properties</h3>
                    <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-900 p-4 text-sm text-slate-200">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
            @if($oldValues || $newValues)
                <div class="card card-pad">
                    <h3 class="section-title">Data changes</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <pre class="overflow-x-auto rounded-xl bg-red-50 p-3 text-xs text-red-800">{{ json_encode($oldValues, JSON_PRETTY_PRINT) }}</pre>
                        <pre class="overflow-x-auto rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800">{{ json_encode($newValues, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif
        </div>

        <aside class="card card-pad self-start">
            <h3 class="section-title">Related records</h3>
            <dl class="mt-4 grid gap-3 text-sm">
                <div><dt class="field-label text-xs">Timestamp</dt><dd class="mt-1 text-slate-700">{{ $occurredAt->format('d M Y H:i:s') }}</dd><dd class="text-xs text-slate-500">{{ $occurredAt->diffForHumans() }}</dd></div>
                @if($log->subject_type && $log->subject_id)
                    <div><dt class="field-label text-xs">Subject</dt><dd class="mt-1 break-all text-xs font-mono text-slate-700">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</dd></div>
                @endif
                @if($log->property_id)
                    <div><dt class="field-label text-xs">Property</dt><dd class="mt-1 text-slate-700">Property #{{ $log->property_id }}</dd></div>
                @endif
            </dl>
        </aside>
    </div>
</x-admin-layout>

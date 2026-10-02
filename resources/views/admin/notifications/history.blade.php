<x-admin-layout title="Notifications">
    <div class="page-header">
        <div>
            <p class="eyebrow">Communications</p>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Notifications that were sent, who received them, and whether they were delivered. To change rules, messages or recipients, use
                <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('admin.settings.notifications.edit') }}">Notification Settings</a>.</p>
        </div>
    </div>

    <form method="get" class="card card-pad mb-5 flex flex-wrap items-end gap-3">
        <label class="field-label">
            Status
            <select name="status" class="input">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-label">
            Type
            <select name="type" class="input">
                <option value="">All</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-label">
            Property
            <select name="property_id" class="input">
                <option value="">All</option>
                @foreach($properties as $property)
                    <option value="{{ $property->id }}" @selected((int) request('property_id') === $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn-primary">Filter</button>
        @if(request()->hasAny(['status', 'type', 'property_id']))
            <a href="{{ route('admin.notifications.index') }}" class="btn-secondary">Clear</a>
        @endif
    </form>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Sent</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Recipient</th>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3">Cleaning job</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php
                            $failed = in_array(strtolower((string) $log->delivery_status), ['failed', 'error', 'undelivered'], true);
                            $when = $log->sent_at ?? $log->created_at;
                        @endphp
                        <tr class="align-top hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $when?->setTimezone(config('app.display_timezone'))->format('M j, g:i A') }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-950">{{ \Illuminate\Support\Str::headline($log->notification_type) }}</p>
                                @if($log->message_content)
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-500" title="{{ $log->message_content }}">{{ $log->message_content }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $log->user?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-slate-500">{{ $log->recipient_email ?: $log->recipient_phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->property?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($log->cleaning_session_id)
                                    <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('sessions.show', $log->cleaning_session_id) }}">
                                        Job #{{ $log->cleaning_session_id }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $failed ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ \Illuminate\Support\Str::headline($log->delivery_status ?? 'unknown') }}
                                </span>
                                @if($log->error_message)
                                    <p class="mt-1 max-w-[14rem] truncate text-xs text-red-600" title="{{ $log->error_message }}">{{ $log->error_message }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-sm text-slate-500">No notifications match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="border-t border-slate-100 p-4">{{ $logs->links() }}</div>
        @endif
    </section>
</x-admin-layout>

<x-admin-layout title="Notifications">
    <div class="page-header">
        <div>
            <p class="eyebrow">Communications</p>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Notifications that were sent, who received them, and whether they were delivered. To change rules, messages or recipients, use
                <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('admin.settings.notifications.edit') }}">Notification Settings</a>.</p>
        </div>
    </div>

    <form method="get" class="card card-pad mb-5 grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
        <label class="field-label min-w-0">
            Status
            <select name="status" class="input w-full">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-label min-w-0">
            Type
            <select name="type" class="input w-full">
                <option value="">All</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-label min-w-0">
            Property
            <select name="property_id" class="input w-full">
                <option value="">All</option>
                @foreach($properties as $property)
                    <option value="{{ $property->id }}" @selected((int) request('property_id') === $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn-primary w-full sm:w-auto">Filter</button>
        @if(request()->hasAny(['status', 'type', 'property_id']))
            <a href="{{ route('admin.notifications.index') }}" class="btn-secondary w-full sm:w-auto">Clear</a>
        @endif
    </form>

    <section class="card overflow-hidden">
        <div class="grid gap-3 p-3 md:hidden">
            @forelse($logs as $log)
                @php
                    $failed = in_array(strtolower((string) $log->delivery_status), ['failed', 'error', 'undelivered'], true);
                    $when = $log->sent_at ?? $log->created_at;
                @endphp
                <article class="min-w-0 rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words font-semibold text-slate-950">{{ \Illuminate\Support\Str::headline($log->notification_type) }}</h2>
                            <p class="mt-1 break-words text-sm text-slate-600">{{ $log->message_content ?: 'No message content' }}</p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $failed ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ \Illuminate\Support\Str::headline($log->delivery_status ?? 'unknown') }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm">
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recipient</dt>
                            <dd class="mt-1 break-words font-medium text-slate-900">{{ $log->user?->name ?? 'Unknown' }}</dd>
                            <dd class="break-all text-xs text-slate-500">{{ $log->recipient_email ?: $log->recipient_phone }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                            <dd class="mt-1 break-words text-slate-700">{{ $log->property?->name ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sent</dt>
                            <dd class="mt-1 text-slate-700">{{ $when?->setTimezone(config('app.display_timezone'))->format('M j, g:i A') }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cleaning job</dt>
                            <dd class="mt-1">
                                @if($log->cleaning_session_id)
                                    <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('sessions.show', $log->cleaning_session_id) }}">Job #{{ $log->cleaning_session_id }}</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if($log->error_message)
                        <p class="mt-3 break-words border-t border-red-100 pt-3 text-xs text-red-600">{{ $log->error_message }}</p>
                    @endif
                </article>
            @empty
                <div class="py-8 text-center text-sm text-slate-500">No notifications match.</div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
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
                                    <p class="mt-0.5 max-w-xs min-w-0 break-words text-xs text-slate-500" title="{{ $log->message_content }}">{{ $log->message_content }}</p>
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
                                    <p class="mt-1 max-w-[14rem] min-w-0 break-words text-xs text-red-600" title="{{ $log->error_message }}">{{ $log->error_message }}</p>
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

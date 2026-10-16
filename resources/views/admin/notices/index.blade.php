<x-admin-layout title="Guest Notices">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest Admin</p>
            <h2 class="page-title">Guest Notices</h2>
            <p class="page-subtitle">Conditional pop-ups and check-in/check-out steps that appear only when their conditions are met (time of day, arrival/check-out day, parking, and more).</p>
        </div>
        <a href="{{ route('admin.notices.create') }}" class="btn-primary w-full sm:w-auto">
            <x-icon name="plus" class="mr-2 h-4 w-4" />
            Add Notice
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-3 md:hidden">
        @forelse($notices as $notice)
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="break-words font-semibold text-slate-950">{{ $notice->title }}</h3>
                        <p class="mt-1 break-words text-sm leading-5 text-slate-600">{{ $notice->body }}</p>
                    </div>
                    <span class="badge shrink-0 {{ $notice->active ? 'badge-active' : 'badge-inactive' }}">{{ $notice->active ? 'Active' : 'Inactive' }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm">
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                        <dd class="mt-1 break-words text-slate-700">{{ $notice->property?->name ?? 'All properties' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shows as</dt>
                        <dd class="mt-1 text-slate-700">{{ \App\Models\GuestNotice::TYPES[$notice->type] ?? $notice->type }}</dd>
                        <dd class="text-xs text-slate-500">{{ \App\Models\GuestNotice::PHASES[$notice->phase] ?? $notice->phase }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">When</dt>
                        <dd class="mt-1 text-slate-700">{{ \App\Models\GuestNotice::DAY_SCOPES[$notice->day_scope] ?? $notice->day_scope }}</dd>
                        @if($notice->start_time || $notice->end_time)
                            <dd class="text-xs text-slate-500">
                                {{ \Illuminate\Support\Str::of($notice->start_time ?? '00:00')->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($notice->end_time ?? '23:59')->substr(0, 5) }}
                            </dd>
                        @endif
                    </div>
                    @if($notice->requires_parking !== null)
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Parking</dt>
                            <dd class="mt-1 text-slate-700">{{ $notice->requires_parking ? 'Parkers only' : 'Non-parkers only' }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 flex gap-2 border-t border-slate-100 pt-3">
                    <a href="{{ route('admin.notices.edit', $notice) }}" class="btn-secondary flex-1">Edit</a>
                    <form method="post" action="{{ route('admin.notices.destroy', $notice) }}" class="flex-1" onsubmit="return confirm('Delete this notice?');">
                        @csrf @method('delete')
                        <button class="btn-secondary w-full text-red-600">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-slate-500">No guest notices yet.</div>
        @endforelse
    </div>

    <div class="table-wrap hidden md:block">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Notice</th>
                    <th>Property</th>
                    <th>Shows as</th>
                    <th>When</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notices as $notice)
                    <tr>
                        <td>
                            <p class="font-semibold text-slate-950">{{ $notice->title }}</p>
                            <p class="mt-0.5 max-w-md text-xs text-slate-500">{{ $notice->body }}</p>
                        </td>
                        <td class="text-sm text-slate-600">{{ $notice->property?->name ?? 'All properties' }}</td>
                        <td class="text-sm text-slate-600">
                            {{ \App\Models\GuestNotice::TYPES[$notice->type] ?? $notice->type }}
                            <span class="block text-xs text-slate-400">{{ \App\Models\GuestNotice::PHASES[$notice->phase] ?? $notice->phase }}</span>
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ \App\Models\GuestNotice::DAY_SCOPES[$notice->day_scope] ?? $notice->day_scope }}
                            @if($notice->start_time || $notice->end_time)
                                <span class="block text-xs text-slate-400">
                                    {{ \Illuminate\Support\Str::of($notice->start_time ?? '00:00')->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($notice->end_time ?? '23:59')->substr(0, 5) }}
                                </span>
                            @endif
                            @if($notice->requires_parking !== null)
                                <span class="block text-xs text-slate-400">{{ $notice->requires_parking ? 'Parkers only' : 'Non-parkers only' }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $notice->active ? 'badge-active' : 'badge-inactive' }}">{{ $notice->active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.notices.edit', $notice) }}" class="btn-secondary">Edit</a>
                            <form method="post" action="{{ route('admin.notices.destroy', $notice) }}" class="inline" onsubmit="return confirm('Delete this notice?');">
                                @csrf @method('delete')
                                <button class="btn-secondary text-red-600">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">No guest notices yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>

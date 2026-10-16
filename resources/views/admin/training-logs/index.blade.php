<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Training Logs</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Completed training across all cleaners</p>
        </div>
    </x-slot>

    @php
        $field = 'w-full text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
        $label = 'block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5';
    @endphp

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('admin.training-logs.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div class="min-w-0">
                    <label class="{{ $label }}">Cleaner</label>
                    <select name="cleaner_id" class="{{ $field }} min-w-0">
                        <option value="">All cleaners</option>
                        @foreach($cleaners as $cleaner)
                            <option value="{{ $cleaner->id }}" @selected(request('cleaner_id') == $cleaner->id)>{{ $cleaner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="{{ $label }}">Type</label>
                    <select name="type" class="{{ $field }} min-w-0">
                        <option value="">All types</option>
                        <option value="task" @selected(request('type') === 'task')>Tasks</option>
                        <option value="video" @selected(request('type') === 'video')>Videos</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="{{ $label }}">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="{{ $field }} min-w-0">
                </div>
                <div class="min-w-0">
                    <label class="{{ $label }}">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="{{ $field }} min-w-0">
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 sm:flex-none">Filter</button>
                    @if(request()->hasAny(['cleaner_id', 'type', 'date_from', 'date_to']))
                        <a href="{{ route('admin.training-logs.index') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 sm:flex-none">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Log --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            @if($completions->isEmpty())
                <div class="p-12 text-center">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">No completed training</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nothing matches these filters yet.</p>
                </div>
            @else
                <div class="grid gap-3 p-3 md:hidden">
                    @foreach($completions as $c)
                        @php
                            $isTask = (bool) $c->task_id;
                            $name = $c->task?->name ?? $c->video?->title ?? 'Removed item';
                            $cleanerName = $users->get($c->user_id)?->name ?? 'Unknown';
                            $propertyName = $c->property_id ? $properties->get($c->property_id)?->name : null;
                        @endphp
                        <article class="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-bold uppercase {{ $isTask ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' }}">{{ $isTask ? 'Task' : 'Video' }}</span>
                                    <h3 class="mt-2 break-words font-semibold text-gray-900 dark:text-gray-100">{{ $name }}</h3>
                                </div>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $c->views_completed ?? '-' }} views</span>
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cleaner</dt>
                                    <dd class="mt-1 break-words font-medium text-gray-900 dark:text-gray-100">{{ $cleanerName }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Context</dt>
                                    <dd class="mt-1 break-words text-gray-700 dark:text-gray-300">
                                        @if($propertyName){{ $propertyName }}@endif
                                        @if($c->cleaning_session_id)
                                            <a href="{{ route('sessions.show', $c->cleaning_session_id) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Session #{{ $c->cleaning_session_id }}</a>
                                        @elseif(!$propertyName)
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="col-span-2 min-w-0">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</dt>
                                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $c->updated_at->format('M j, Y g:i A') }}</dd>
                                    <dd class="text-xs text-gray-400">{{ $c->updated_at->diffForHumans() }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3">Cleaner</th>
                                <th class="px-5 py-3">Training</th>
                                <th class="px-5 py-3">Context</th>
                                <th class="px-5 py-3">Views</th>
                                <th class="px-5 py-3">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($completions as $c)
                                @php
                                    $isTask = (bool) $c->task_id;
                                    $name = $c->task?->name ?? $c->video?->title ?? 'Removed item';
                                    $cleanerName = $users->get($c->user_id)?->name ?? 'Unknown';
                                    $propertyName = $c->property_id ? $properties->get($c->property_id)?->name : null;
                                @endphp
                                <tr class="text-gray-700 dark:text-gray-300">
                                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $cleanerName }}</td>
                                    <td class="px-5 py-3">
                                        <span class="mr-2 inline-flex rounded px-2 py-0.5 text-xs font-bold uppercase {{ $isTask ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' }}">{{ $isTask ? 'Task' : 'Video' }}</span>
                                        {{ $name }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($propertyName){{ $propertyName }}@endif
                                        @if($c->cleaning_session_id)
                                            <a href="{{ route('sessions.show', $c->cleaning_session_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Session #{{ $c->cleaning_session_id }}</a>
                                        @elseif(!$propertyName)
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">{{ $c->views_completed ?? '-' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        {{ $c->updated_at->format('M j, Y g:i A') }}
                                        <span class="block text-xs text-gray-400">{{ $c->updated_at->diffForHumans() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($completions->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">{{ $completions->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>

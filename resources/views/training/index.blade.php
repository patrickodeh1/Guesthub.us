<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Training</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Required training for your cleanings, plus guides and videos.</p>
        </div>
    </x-slot>

    @php
        $sections = [
            ['title' => 'Required today', 'description' => "Complete these before you start today's cleanings.", 'items' => $requiredToday, 'empty' => 'You are all caught up for today.', 'border' => 'border-red-200 dark:border-red-900/50', 'heading' => 'text-red-600 dark:text-red-400', 'primary' => true],
            ['title' => 'Upcoming required', 'description' => 'Get ahead by completing training for future assignments.', 'items' => $upcoming, 'empty' => 'No upcoming training requirements.', 'border' => 'border-gray-200 dark:border-gray-700', 'heading' => 'text-gray-900 dark:text-gray-100', 'primary' => false],
            ['title' => 'Optional', 'description' => 'Available for your sessions but not required to start cleaning.', 'items' => $optional, 'empty' => 'No optional training available.', 'border' => 'border-gray-200 dark:border-gray-700', 'heading' => 'text-gray-900 dark:text-gray-100', 'primary' => false],
        ];
        $btnPrimary = 'inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors';
        $btnSecondary = 'inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors';
    @endphp

    <div class="space-y-6">
        {{-- Library --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ route('resources.videos') }}" class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 hover:border-purple-300 dark:hover:border-purple-700 transition-colors">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"></path></svg>
                </span>
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-gray-100">Videos</span>
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Watch how-to and training videos</span>
                </span>
            </a>
            <a href="{{ route('resources.guides') }}" class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </span>
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-gray-100">Guides</span>
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Step-by-step instructions by property and room</span>
                </span>
            </a>
        </div>

        {{-- Assigned training --}}
        @foreach($sections as $section)
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $section['border'] }} shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold {{ $section['heading'] }}">{{ $section['title'] }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $section['description'] }}</p>
                </div>

                @if($section['items']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">{{ $section['empty'] }}</p>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($section['items'] as $snapshot)
                            @php
                                $isTask = (bool) $snapshot->task;
                                $title = $isTask ? $snapshot->task->name : ($snapshot->video->title ?? 'Training');
                                $sess = $snapshot->cleaningSession;
                                $date = $sess?->scheduled_date ? \Carbon\Carbon::parse($sess->scheduled_date)->format('M j') : null;
                            @endphp
                            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">
                                        <span class="mr-2 inline-flex rounded px-2 py-0.5 text-xs font-bold uppercase {{ $isTask ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' }}">{{ $isTask ? 'Task' : 'Video' }}</span>
                                        {{ $title }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">For: {{ $sess?->property?->name ?? 'Unknown' }}@if($date) &middot; {{ $date }}@endif</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('training.show', ['type' => $isTask ? 'task' : 'video', 'id' => $isTask ? $snapshot->task->id : $snapshot->video->id, 'session' => $snapshot->cleaning_session_id]) }}" class="{{ $section['primary'] ? $btnPrimary : $btnSecondary }}">{{ $isTask ? 'Read' : 'Watch' }}</a>
                                    <a href="{{ route('sessions.show', $snapshot->cleaning_session_id) }}" class="{{ $btnSecondary }}">Session &rarr;</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>

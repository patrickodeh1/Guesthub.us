<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">Welcome, {{ auth()->user()->name }}</h2>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">Your cleaning schedule at a glance.</p>
        </div>
    </x-slot>

    @php
        $mapUrl = function ($p) {
            if (! $p || (! $p->address && ! ($p->latitude && $p->longitude))) { return null; }
            return 'https://www.google.com/maps/search/?api=1&query=' . ($p->latitude && $p->longitude ? $p->latitude . ',' . $p->longitude : urlencode($p->address));
        };
    @endphp

    <div class="max-w-4xl mx-auto space-y-6 px-1 sm:px-0">
        <div class="grid grid-cols-2 gap-4">
            <a href="{{ route('assignments.index', ['filter' => 'upcoming']) }}" class="block rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Upcoming (14 days)</div>
                <div class="mt-1 text-2xl font-semibold">{{ $todaySessions->count() + $upcomingSessions->count() }}</div>
            </a>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Completed (30 days)</div>
                <div class="mt-1 text-2xl font-semibold">{{ $completed30 }}</div>
            </div>
        </div>

        @if($overdueSessions->isNotEmpty())
            <section class="rounded-xl border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20">
                <div class="border-b border-red-200 px-4 py-3 font-semibold text-red-800 dark:border-red-800 dark:text-red-200">Overdue ({{ $overdueSessions->count() }})</div>
                <ul class="divide-y divide-red-100 dark:divide-red-800/40">
                    @foreach($overdueSessions as $s)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $s->property->name ?? 'Property' }}</div>
                                <div class="text-xs text-gray-500">Was due {{ \Illuminate\Support\Carbon::parse($s->scheduled_date)->format('M j, Y') }}</div>
                            </div>
                            <a href="{{ route('sessions.show', $s) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Open</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 font-semibold dark:border-gray-700">Today</div>
            @forelse($todaySessions as $s)
                @php $m = $mapUrl($s->property); @endphp
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-700">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $s->property->name ?? 'Property' }}</div>
                        <div class="text-xs text-gray-500"><x-status-badge :status="$s->status" /></div>
                        @if($m)
                            <a href="{{ $m }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-indigo-600 hover:underline">{{ $s->property->address ?: 'View on map' }}</a>
                        @endif
                    </div>
                    <a href="{{ route('sessions.show', $s) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Open</a>
                </div>
            @empty
                <p class="p-6 text-sm text-gray-600 dark:text-gray-300">Nothing scheduled for today.</p>
            @endforelse
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 font-semibold dark:border-gray-700">Coming up</div>
            @forelse($upcomingSessions as $s)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-700">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $s->property->name ?? 'Property' }}</div>
                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($s->scheduled_date)->format('D, M j') }}</div>
                    </div>
                    <a href="{{ route('sessions.show', $s) }}" class="text-sm text-indigo-600 hover:underline">Open</a>
                </div>
            @empty
                <p class="p-6 text-sm text-gray-600 dark:text-gray-300">No upcoming sessions in the next 14 days.</p>
            @endforelse
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 font-semibold dark:border-gray-700">Quick links</div>
            <div class="grid grid-cols-2 gap-2 p-4 text-sm sm:grid-cols-3">
                <a href="{{ route('assignments.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">My Jobs</a>
                <a href="{{ route('calendar.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">Calendar</a>
                <a href="{{ route('training.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">Training</a>
                <a href="{{ route('resources.videos') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">Videos</a>
                <a href="{{ route('resources.photos') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">Photos</a>
                <a href="{{ route('resources.guides') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">Guides</a>
            </div>
        </section>
    </div>
</x-app-layout>

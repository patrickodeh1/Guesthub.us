{{-- Mobile Card View --}}
<div class="md:hidden space-y-3 mb-4">
    @forelse($jobs as $s)
        <div class="relative block">
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ !empty($isCurrent) ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-700' }} p-4 hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
                <a href="{{ route('sessions.show', $s) }}" class="absolute inset-0 z-0 rounded-xl"></a>
                <div class="relative z-10 flex items-start justify-between gap-3 pointer-events-none">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $s->property->name }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $s->scheduled_date->format($dateFormat) }}
                            @if($s->status === 'in_progress' && $s->started_at)
                                &bull; Started {{ $s->started_at->format('g:i A') }}
                            @elseif($s->status === 'completed' && $s->ended_at)
                                &bull; Completed {{ $s->ended_at->format('g:i A') }}
                            @elseif($s->scheduled_time)
                                &bull; {{ \Carbon\Carbon::parse($s->scheduled_time)->format('g:i A') }}
                            @endif
                        </p>
                        @if($s->property->address)
                            @php
                                $mapsUrl = $s->property->latitude && $s->property->longitude
                                    ? 'https://www.google.com/maps/search/?api=1&query=' . $s->property->latitude . ',' . $s->property->longitude
                                    : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($s->property->address);
                            @endphp
                            <div class="mt-2 pointer-events-auto">
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-start text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    <svg class="w-3.5 h-3.5 mr-1 flex-shrink-0 mt-0.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span class="break-words line-clamp-2" title="{{ $s->property->address }}">{{ $s->property->address }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="flex-shrink-0 pointer-events-auto">
                        <x-status-badge :status="$s->status" />
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-end relative z-10 pointer-events-none gap-3">
                    @if($s->status === 'in_progress' && $s->report_token && (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company'])))
                        <a href="{{ route('reports.sessions.show', ['token' => $s->report_token]) }}" target="_blank" rel="noopener noreferrer" class="text-sm text-emerald-600 dark:text-emerald-400 font-medium pointer-events-auto hover:underline">
                            Live Report
                        </a>
                    @endif
                    <span class="text-sm text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                        Open
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
            No sessions
        </div>
    @endforelse
</div>

{{-- Desktop Table View --}}
<x-card class="mb-4 !px-0 hidden md:block {{ !empty($isCurrent) ? 'border-orange-400 ring-1 ring-orange-400' : '' }}">
    <table class="min-w-full text-sm">
        <thead class="uppercase">
            <tr>
                <th class="px-4 py-2 text-left">Date & Time</th>
                <th class="px-4 py-2 text-left">Property</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2 w-32"></th>
            </tr>
        </thead>
        <tbody class="divide-y dark:divide-gray-700">
            @forelse($jobs as $s)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ !empty($isCurrent) ? 'bg-orange-50/30 dark:bg-orange-900/10' : '' }}">
                    <td class="px-4 py-3">
                        {{ $s->scheduled_date->format($dateFormat) }}
                        @if($s->status === 'in_progress' && $s->started_at)
                            <div class="text-xs text-gray-500">Started {{ $s->started_at->format('g:i A') }}</div>
                        @elseif($s->status === 'completed' && $s->ended_at)
                            <div class="text-xs text-gray-500">Completed {{ $s->ended_at->format('g:i A') }}</div>
                        @elseif($s->scheduled_time)
                            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($s->scheduled_time)->format('g:i A') }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $s->property->name }}</div>
                        @if($s->property->address)
                            @php
                                $mapsUrl = $s->property->latitude && $s->property->longitude
                                    ? 'https://www.google.com/maps/search/?api=1&query=' . $s->property->latitude . ',' . $s->property->longitude
                                    : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($s->property->address);
                            @endphp
                            <div class="mt-1 max-w-[250px]">
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-start text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    <svg class="w-3.5 h-3.5 mr-1 flex-shrink-0 mt-0.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span class="break-words line-clamp-2" title="{{ $s->property->address }}">{{ $s->property->address }}</span>
                                </a>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center"><x-status-badge :status="$s->status" /></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('sessions.show', $s) }}" class="text-indigo-600 hover:underline font-medium">Open</a>
                        @if($s->status === 'in_progress' && $s->report_token && (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company'])))
                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                            <a href="{{ route('reports.sessions.show', ['token' => $s->report_token]) }}" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline font-medium whitespace-nowrap">Live Report</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="px-4 py-6 text-center text-gray-500" colspan="4">No sessions</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-card>

@php
    $canManage = auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']);
@endphp
@if (! $canManage)
    <a href="{{ route('sessions.show', $s) }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">Start cleaning</a>
@else
<div x-data="{ open: false }" class="relative inline-block text-left" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click.stop="open = !open" :aria-expanded="open" aria-label="Cleaning actions"
            class="rounded-full p-1.5 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/></svg>
    </button>
    <div x-show="open" style="display:none"
         class="absolute right-0 z-30 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
        <a href="{{ route('sessions.show', $s) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Start cleaning</a>
        @if ($canManage)
            <a href="{{ route('manage.sessions.edit', $s) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Edit cleaning</a>
            @if ($s->status === 'pending' && ! $s->gps_override_enabled)
                <button type="button"
                        @click="open = false; $dispatch('gps-override-open', { action: '{{ route('sessions.gps-override', $s) }}', label: @js($s->property->name) }); $dispatch('open-modal', 'jobs-gps-override-modal')"
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Grant GPS override</button>
            @endif
            <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" onsubmit="return confirm('Delete this cleaning? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-gray-700">Delete cleaning</button>
            </form>
        @endif
    </div>
</div>
@endif

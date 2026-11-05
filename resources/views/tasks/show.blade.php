<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg sm:text-xl font-semibold">{{ $task->name }}</h2>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('tasks.index') }}" class="text-gray-600 hover:underline">Back to tasks</a>
                <a href="{{ url('tasks/' . $task->id . '/edit') }}" class="px-3 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Edit</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4 space-y-6">
        <div class="bg-white shadow rounded-lg p-4 sm:p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-gray-500">Type</dt><dd class="font-medium">{{ $task->type ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Phase</dt><dd class="font-medium">{{ $task->phase ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Est. duration</dt><dd class="font-medium">{{ $task->estimated_duration_minutes ? $task->estimated_duration_minutes . ' min' : '—' }}</dd></div>
                <div><dt class="text-gray-500">Used in rooms</dt><dd class="font-medium">{{ $task->rooms_count }}</dd></div>
            </dl>

            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                @if($task->is_default)<span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Default</span>@endif
                @if($task->is_sporadic)<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Sporadic</span>@endif
            </div>

            @if($task->instructions)
                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-gray-700">Instructions</h3>
                    <p class="mt-1 text-sm text-gray-800 whitespace-pre-line">{{ $task->instructions }}</p>
                </div>
            @endif
        </div>

        <div class="bg-white shadow rounded-lg p-4 sm:p-6">
            <h3 class="text-base font-semibold mb-3">Where this task is used</h3>

            @forelse($rooms as $room)
                <div class="border-t first:border-t-0 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $room->name }}</span>
                        @if($room->is_default)<span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Default room</span>@endif
                        @if($room->pivot->visible_to_owner)<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Owner</span>@endif
                        @if($room->pivot->visible_to_housekeeper)<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Housekeeper</span>@endif
                    </div>
                    @if($room->properties->isNotEmpty())
                        <p class="mt-1 text-xs text-gray-500">{{ $room->properties->pluck('name')->implode(', ') }}</p>
                    @endif
                    @if($room->pivot->instructions)
                        <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $room->pivot->instructions }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">This task isn't attached to any rooms you can see.</p>
            @endforelse

            <div class="mt-4">{{ $rooms->links() }}</div>
        </div>
    </div>
</x-app-layout>

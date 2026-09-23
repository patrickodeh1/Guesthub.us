<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <h2 class="text-lg sm:text-xl font-semibold break-words min-w-0 flex-1">
                Tasks — {{ $property->name }} / {{ $room->name }}
            </h2>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}" class="w-full sm:w-auto whitespace-nowrap">← Rooms</x-button>
                @role('admin|owner|company')
                    <x-button variant="secondary" @click="$dispatch('open-preview-panel', 'bulk-add-tasks-{{ $property->id }}-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">+ Bulk Add Tasks</x-button>
                    <x-button variant="primary" @click="$dispatch('open-preview-panel', 'add-task-{{ $property->id }}-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">+ Add Task</x-button>
                @endrole
            </div>
        </div>
    </x-slot>

    @php $orderUrl = route('rooms.tasks.order', [$room]); @endphp

    <x-tasks.table 
        :tasks="$tasks" 
        :property="$property" 
        :room="$room" 
        context="room" 
        :orderUrl="$orderUrl" 
    />

    @role('admin|owner|company')
        {{-- Mobile Floating Action Button (FAB) --}}
        <div class="fixed bottom-20 right-4 z-40 sm:hidden">
            <button
                type="button"
                @click="$dispatch('open-preview-panel', 'add-task-{{ $property->id }}-{{ $room->id }}')"
                class="flex items-center justify-center w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                aria-label="Add Task"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>
    @endrole

    @role('admin|owner|company')
        {{-- Add Task Preview Panel --}}
        <x-preview-panel
            name="add-task-{{ $property->id }}-{{ $room->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Add Task"
            :subtitle="'Add a new task to ' . $room->name . ' in ' . $property->name">

            @php $suggestUrl = route('tasks.suggest'); @endphp

            @include('properties.tasks.__task_form', [
                'property' => $property,
                'room' => $room,
                'task' => null,
                'pivot' => null,
                'suggestUrl' => $suggestUrl,
                'mode' => 'create',
            ])
        </x-preview-panel>

        {{-- Bulk Add Tasks Preview Panel --}}
        <x-preview-panel
            name="bulk-add-tasks-{{ $property->id }}-{{ $room->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Bulk Add Tasks"
            :subtitle="'Quickly add multiple tasks to ' . $room->name . ' in ' . $property->name">

            @include('properties.tasks.__bulk_task_form', [
                'property' => $property,
                'room' => $room,
            ])
        </x-preview-panel>

        {{-- Edit Task Preview Panels --}}
        @foreach($tasks as $t)
            <x-preview-panel
                name="edit-task-{{ $property->id }}-{{ $room->id }}-{{ $t->id }}"
                :overlay="true"
                side="right"
                initialWidth="32rem"
                minWidth="24rem"
                title="Edit Task"
                :subtitle="$t->name">

                @php
                    $suggestUrl = route('tasks.suggest');
                    $pivot = $t->pivot;
                @endphp

                @include('properties.tasks.__task_form', [
                    'property' => $property,
                    'room' => $room,
                    'task' => $t,
                    'pivot' => $pivot,
                    'suggestUrl' => $suggestUrl,
                    'mode' => 'edit',
                ])
            </x-preview-panel>
        @endforeach
    @endrole
</x-app-layout>

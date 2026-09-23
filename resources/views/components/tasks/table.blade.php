@props([
    'tasks',
    'property',
    'room' => null,
    'context' => 'room', // 'room' or 'property'
    'orderUrl',
])

<div x-data="taskList({ orderUrl: @js($orderUrl), csrf: @js(csrf_token()) })" class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Drag <span class="inline-block text-lg font-bold align-middle" style="color: var(--theme-primary, #6366f1)">⋮⋮</span> to reorder. Auto-saves.</p>
        <div class="min-h-[28px]">
            <span x-show="status==='saving'" x-cloak class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">Saving…</span>
            <span x-show="status==='saved'" x-cloak class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300">✓ Saved at <span x-text="savedAt"></span></span>
            <span x-show="status==='error'" x-cloak class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300">Failed</span>
        </div>
    </div>

    <x-card class="!px-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="uppercase text-xs tracking-wide sticky top-0 z-10 bg-white dark:bg-gray-800">
                    <tr class="text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                        @role('admin|owner|company')
                            <th class="px-3 py-2 w-10"></th>
                        @endrole
                        <th class="px-4 py-2 text-left w-full">Task</th>
                        @role('admin|owner|company')
                            <th class="px-4 py-2 w-32 text-right">Action</th>
                        @endrole
                    </tr>
                </thead>
                <tbody x-ref="tbody" class="divide-y dark:divide-gray-700">
                    @forelse($tasks as $t)
                        @php
                            if ($context === 'property') {
                                $detachUrl = route('properties.property-tasks.detach', [$property, $t]);
                                $editEvent = 'edit-property-task-' . $property->id . '-' . $t->id;
                                $confirmMessage = 'Remove this task from the property?';
                            } else {
                                $detachUrl = route('properties.tasks.detach', [$property, $room, $t]);
                                $editEvent = 'edit-task-' . $property->id . '-' . $room->id . '-' . $t->id;
                                $confirmMessage = 'Detach this task from the room?';
                            }
                        @endphp
                        <tr data-task-id="{{ $t->id }}" class="hover:bg-gray-100 dark:hover:bg-gray-900 group">
                            @role('admin|owner|company')
                                <td class="px-1 sm:px-3 text-center">
                                    <button type="button" class="drag-handle cursor-grab active:cursor-grabbing p-3 sm:p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors touch-manipulation" title="Drag to reorder" style="min-width: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center;">
                                        <svg class="w-7 h-7 sm:w-6 sm:h-6" viewBox="0 0 24 24" fill="currentColor" style="color: var(--theme-primary, #6366f1)">
                                            <circle cx="9" cy="5" r="2"/>
                                            <circle cx="15" cy="5" r="2"/>
                                            <circle cx="9" cy="12" r="2"/>
                                            <circle cx="15" cy="12" r="2"/>
                                            <circle cx="9" cy="19" r="2"/>
                                            <circle cx="15" cy="19" r="2"/>
                                        </svg>
                                    </button>
                                </td>
                            @endrole
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-base text-gray-900 dark:text-gray-100">{{ $t->name }}</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide text-white shadow-sm" style="background-color: var(--theme-primary, #6366f1);">{{ $t->type ?? 'Room' }}</span>
                                </div>
                            </td>
                            @role('admin|owner|company')
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                                        <button type="button" 
                                                class="p-1.5 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-md dark:text-indigo-400 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 transition-colors focus:ring-2 focus:ring-indigo-500"
                                                title="Edit Task"
                                                @click="$dispatch('open-preview-panel', '{{ $editEvent }}')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        
                                        <form action="{{ $detachUrl }}" method="post" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" 
                                                    class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-md dark:text-rose-400 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 transition-colors focus:ring-2 focus:ring-rose-500"
                                                    title="Detach Task"
                                                    onclick="return confirm('{{ addslashes($confirmMessage) }}')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endrole
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" @if(auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) colspan="3" @else colspan="1" @endif>No tasks yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

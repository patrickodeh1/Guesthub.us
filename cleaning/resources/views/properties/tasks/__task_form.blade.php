@props([
    'property',
    'room',
    'task' => null, // null for create, Task model for edit
    'pivot' => null, // Pivot data for edit mode
    'suggestUrl',
    'mode' => 'create', // 'create' or 'edit'
])

@php
    $isEdit = $mode === 'edit' && $task;
    $storeUrl = $isEdit
        ? route('properties.tasks.update', [$property, $room, $task])
        : route('properties.tasks.store', [$property, $room]);
    $panelName = $isEdit
        ? "edit-task-{$property->id}-{$room->id}-{$task->id}"
        : "add-task-{$property->id}-{$room->id}";
@endphp

<div class="p-0 sm:p-2 md:p-4 lg:p-6 space-y-4 sm:space-y-6 max-w-full flex flex-col h-full" x-data="propertyTaskForm({
    suggestUrl: @js($suggestUrl),
    storeUrl: @js($storeUrl),
    csrf: @js(csrf_token()),
    propertyId: @js($property->id),
    roomId: @js($room->id),
    taskId: @js($task?->id),
    mode: @js($mode),
    panelName: @js($panelName),
    initialData: @js($isEdit ? [
        'name' => $task->name ?? '',
        'type' => $task->type ?? 'room',
        'is_sporadic' => (bool) ($task->is_sporadic ?? false),
        'is_default' => (bool) ($task->is_default ?? false),
        'is_pre_arrival' => (bool) ($task->is_pre_arrival ?? false),
        'is_required_before_start' => (bool) ($task->is_required_before_start ?? false),
        'is_required_during_task' => (bool) ($task->is_required_during_task ?? false),
        'required_views' => (int) ($task->required_views ?? 1),
        'training_frequency' => $task->training_frequency ?? 'once_ever',
        'estimated_duration_minutes' => $task->estimated_duration_minutes ?? 0,
        'instruction_completion_method' => $task->instruction_completion_method ?? 'manual',
        'instructions' => $pivot->instructions ?? '',
        'visible_to_owner' => true,
        'visible_to_housekeeper' => true,
    ] : [
        'name' => '',
        'type' => 'room',
        'is_sporadic' => false,
        'is_default' => false,
        'is_pre_arrival' => false,
        'is_required_before_start' => false,
        'is_required_during_task' => false,
        'required_views' => 1,
        'training_frequency' => 'once_ever',
        'estimated_duration_minutes' => 0,
        'instruction_completion_method' => 'manual',
        'instructions' => '',
        'visible_to_owner' => true,
        'visible_to_housekeeper' => true,
    ])
})">
    <form @submit.prevent="submitForm" enctype="multipart/form-data" class="space-y-6 flex flex-col flex-1">
        @if($isEdit)
            @method('PUT')
            
            {{-- Edit Context Banner --}}
            <div class="flex items-center gap-3 p-3 sm:p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
                <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Room Context</p>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $room?->name ?? 'Unknown Room' }}</p>
                </div>
            </div>
        @endif

        {{-- Task Name with Autocomplete --}}
        <div x-data="taskAutocomplete({ suggestUrl: @js($suggestUrl) })" 
             x-init="@if($isEdit) q = @js($task->name) @endif"
             @task-chosen="
                if (formData) {
                    if (typeof $event.detail.task.type !== 'undefined') formData.type = $event.detail.task.type;
                    if (typeof $event.detail.task.instructions !== 'undefined' && $event.detail.task.instructions) formData.instructions = $event.detail.task.instructions;
                    if (typeof $event.detail.task.is_sporadic !== 'undefined') formData.is_sporadic = $event.detail.task.is_sporadic;
                    formData.template_task_id = $event.detail.task.id;
                    if (mode === 'create' && $event.detail.task.media && Array.isArray($event.detail.task.media)) {
                        $dispatch('template-media-loaded', $event.detail.task.media);
                    }
                }
             ">
            <label for="task-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Task Name <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
                <input
                    x-model="q"
                    x-ref="nameInput"
                    name="name"
                    id="task-name"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="e.g., Wipe counters, Make bed"
                    class="w-full max-w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100
                           focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                           transition-all duration-200 px-3 sm:px-4 py-2.5 text-sm"
                    @input="onInput"
                    @focus="onFocus"
                    @keydown="keyDown"
                    aria-autocomplete="list"
                    aria-expanded="open"
                    aria-controls="task-suggest"
                />

                {{-- Autocomplete Dropdown --}}
                <div
                    x-cloak
                    x-show="open"
                    id="task-suggest"
                    role="listbox"
                    class="absolute z-50 mt-1 w-full max-w-full rounded-lg border border-gray-200 dark:border-gray-700
                           bg-white dark:bg-gray-800 shadow-xl overflow-hidden"
                >
                    <div x-show="loading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching…
                    </div>

                    <template x-if="hasResults">
                        <ul class="max-h-60 overflow-y-auto">
                            <template x-for="(item,idx) in items" :key="item.id">
                                <li
                                    class="px-4 py-2.5 cursor-pointer text-sm hover:bg-gray-50 dark:hover:bg-gray-700
                                           flex items-center justify-between transition-colors"
                                    :class="{'bg-gray-50 dark:bg-gray-700': focusedIndex===idx}"
                                    @mouseenter="focusedIndex=idx"
                                    @mouseleave="focusedIndex=-1"
                                    @click="choose(item)"
                                >
                                    <span x-text="item.name" class="text-gray-900 dark:text-gray-100 font-medium"></span>
                                    <span class="text-[10px] uppercase ml-2 px-2 py-0.5 rounded-full font-medium"
                                          :class="item.type==='inventory'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-400/20 dark:text-blue-300'
                                            : (item.type==='verify'
                                              ? 'bg-amber-100 text-amber-700 dark:bg-amber-400/20 dark:text-amber-300'
                                              : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300')">
                                        <span x-text="item.type"></span>
                                    </span>
                                </li>
                            </template>
                            <li
                                x-show="q && !items.some(i=>i.name.toLowerCase()===q.toLowerCase())"
                                class="px-4 py-2.5 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300
                                       bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50
                                       transition-colors flex items-center gap-2"
                                :class="{'ring-2 ring-indigo-400': focusedIndex===items.length}"
                                @mouseenter="focusedIndex=items.length"
                                @mouseleave="focusedIndex=-1"
                                @click="open=false"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Create "<span x-text="q" class="font-semibold"></span>"
                            </li>
                        </ul>
                    </template>

                    <div
                        x-show="!loading && !hasResults && q"
                        class="px-4 py-2.5 text-sm text-indigo-700 dark:text-indigo-300
                               bg-indigo-50/60 dark:bg-indigo-900/30 flex items-center gap-2"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create "<span x-text="q" class="font-semibold"></span>"
                    </div>
                </div>
            </div>
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Start typing to search existing tasks or create a new one</p>
        </div>

        {{-- Task Type --}}
        <div>
            <label for="task-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Type <span class="text-rose-500">*</span>
            </label>
                <select
                    name="type"
                    id="task-type"
                    x-model="formData.type"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="room">Room Task (Standard checklist item)</option>
                    <option value="inventory">Inventory (Prompt housekeeper for input quantity)</option>
                    <option value="verify">Verify (Requires a photo upload to complete)</option>
                    <option value="instructions">Instructions Only (Informational block for staff)</option>
                </select>
        </div>

        {{-- Sporadic --}}
        <div>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors">
                <x-form.checkbox name="is_sporadic" x-model="formData.is_sporadic" />
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Occasional Task</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Only included if specifically checked when scheduling a session</div>
                </div>
            </label>
        </div>

        {{-- Instructions --}}
        <div>
            <label for="task-instructions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Instructions <span class="text-xs text-gray-500">(optional)</span>
            </label>
            <textarea
                name="instructions"
                id="task-instructions"
                rows="4"
                x-model="formData.instructions"
                placeholder="Short steps, tips, or link to SOP..."
                class="w-full max-w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100
                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                       transition-all duration-200 px-3 sm:px-4 py-2.5 text-sm resize-none"
            ></textarea>
        </div>

        {{-- Training Configuration (Only for Instructions/Media) --}}
        <div x-show="formData.instructions || formData.type === 'instructions' || existingMedia.length > 0 || previews.length > 0" x-cloak class="p-4 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 space-y-4">
            <h4 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Training Configuration</h4>
            <p class="text-xs text-indigo-700 dark:text-indigo-300 mb-2">Allow the housekeeper to view this task's instructional content before arriving at the property.</p>
            
            <div>
                <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <x-form.checkbox name="is_pre_arrival" value="1" x-model="formData.is_pre_arrival" />
                    <div>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Include in Pre-Arrival Training Hub</span>
                    </div>
                </label>
            </div>

            <div x-show="formData.is_pre_arrival" x-cloak class="pl-8 space-y-4 border-l-2 border-indigo-200 dark:border-indigo-800 ml-2 mt-3 pt-2">
                <div>
                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <x-form.checkbox name="is_required_before_start" value="1" x-model="formData.is_required_before_start" />
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Mandatory (Block Session Start)</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Cleaners cannot start the cleaning session until they complete this.</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="pt-2 border-t border-indigo-200/50 dark:border-indigo-800 mt-2">
                <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <x-form.checkbox name="is_required_during_task" value="1" x-model="formData.is_required_during_task" />
                    <div>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Require During Task (Block Task Completion)</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Cleaners must complete this instruction/video while performing the specific task.</p>
                    </div>
                </label>
            </div>
            
            <div x-show="formData.is_required_during_task || formData.is_pre_arrival" x-cloak class="pt-2 space-y-4">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Training Frequency</label>
                        <select name="training_frequency" x-model="formData.training_frequency" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="once_ever">Once Ever</option>
                            <option value="once_per_property">Once Per Property</option>
                            <option value="once_per_assignment">Once Per Assignment</option>
                            <option value="every_assignment">Every Assignment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Completion Method</label>
                        <select name="instruction_completion_method" x-model="formData.instruction_completion_method" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="manual">Manual Checkbox</option>
                            <option value="time">Time Based</option>
                            <option value="scroll">Scroll to Bottom</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div x-show="formData.is_required_during_task">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Required Views</label>
                        <input type="number" min="1" name="required_views" x-model="formData.required_views" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </div>
                </div>
                
                <div x-show="formData.instruction_completion_method === 'time'">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Estimated Duration (Minutes)</label>
                    <input type="number" min="0" name="estimated_duration_minutes" x-model="formData.estimated_duration_minutes" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>

                @if($isEdit)
                <div class="pt-2 border-t border-indigo-200/50 dark:border-indigo-800 mt-2">
                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <x-form.checkbox name="bump_version" value="1" />
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Bump Version (Require Retraining)</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Check this to require cleaners to review the updated instructions.</p>
                        </div>
                    </label>
                </div>
                @endif
            </div>
        </div>

        @role('admin')
        {{-- Make Default --}}
        <div>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700
                          hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors">
                <x-form.checkbox name="is_default" x-model="formData.is_default" />
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Default Task</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Auto-assign this task to new rooms</div>
                </div>
            </label>
        </div>
        @endrole

        {{-- Visibility: always visible to both, hidden inputs ensure true is sent --}}
        <input type="hidden" name="visible_to_owner" value="1" />
        <input type="hidden" name="visible_to_housekeeper" value="1" />
        <input type="hidden" name="template_task_id" x-model="formData.template_task_id" />

        {{-- Media Upload --}}
        <div x-data="{
            files: [],
            previews: [],
            existingMedia: @js($isEdit && $task ? $task->media->map(fn($m) => [
                'id' => $m->id,
                'url' => (string) $m->url,
                'type' => $m->type,
                'caption' => $m->caption
            ]) : [])
        }" 
        @template-media-loaded.window="if(mode === 'create') { existingMedia = $event.detail.map(m => ({...m, is_template: true})) }"
        x-init="
            $watch('files', f => {
                previews = [...f].map(file => ({
                    url: URL.createObjectURL(file),
                    type: file.type.startsWith('video') ? 'video':'image',
                    name: file.name
                }));
                // Update the actual file input to contain ALL selected files
                const dt = new DataTransfer();
                f.forEach(file => dt.items.add(file));
                $refs.mediaInput.files = dt.files;
            })
        ">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Instructional Media <span class="text-xs text-gray-500">(optional)</span>
            </label>

            {{-- Existing Media (Edit Mode) --}}
            <template x-if="existingMedia.length > 0">
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                    <template x-for="media in existingMedia" :key="media.id">
                        <div class="relative group rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <template x-if="media.type==='image'">
                                <img :src="media.url" class="w-full h-32 object-cover" />
                            </template>
                            <template x-if="media.type==='video'">
                                <video :src="media.url" class="w-full h-32 object-cover" controls></video>
                            </template>
                            <div class="absolute top-0 right-0 p-1">
                                <button type="button" class="bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors shadow-sm" @click="if(confirm('Are you sure?')) { 
                                    if (media.is_template) {
                                        existingMedia = existingMedia.filter(m => m.id !== media.id);
                                    } else {
                                        fetch(`/tasks/${taskId}/media/${media.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }).then(() => { existingMedia = existingMedia.filter(m => m.id !== media.id) }) 
                                    }
                                }">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <input type="hidden" name="kept_template_media[]" :value="media.id" :disabled="!media.is_template" />
                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 p-1" x-show="media.caption">
                                <p class="text-xs text-white truncate px-1" x-text="media.caption"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div class="mt-2">
                <div class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed
                              border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer
                              hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors relative"
                     @click="$refs.mediaInput.click()">
                    
                    <div class="flex flex-col items-center justify-center pt-5 pb-6 pointer-events-none">
                        <svg class="w-10 h-10 mb-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-semibold">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Images or videos up to 20MB each</p>
                    </div>

                    <input
                        x-ref="mediaInput"
                        type="file"
                        name="media[]"
                        multiple
                        accept="image/*,video/*"
                        class="hidden"
                        x-on:change="files = [...files, ...Array.from($event.target.files)]"
                    />
                </div>
            </div>

            {{-- New Media Previews --}}
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3" x-show="previews.length" x-cloak>
                <template x-for="(p, i) in previews" :key="i">
                    <div class="relative group rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <template x-if="p.type==='image'">
                            <img :src="p.url" class="w-full h-32 object-cover" />
                        </template>
                        <template x-if="p.type==='video'">
                            <video :src="p.url" class="w-full h-32 object-cover" muted></video>
                        </template>
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <button
                                type="button"
                                @click="files = files.filter((_, idx) => idx !== i); previews = previews.filter((_, idx) => idx !== i)"
                                class="text-white hover:text-rose-300"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <input
                            type="text"
                            name="captions[]"
                            placeholder="Caption (optional)"
                            class="w-full max-w-full border-t border-gray-200 dark:border-gray-700 px-2 sm:px-3 py-2 text-xs
                                   dark:bg-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                </template>
            </div>
        </div>

        {{-- Error Message --}}
        <div x-show="error" x-cloak class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
            <p class="text-sm text-rose-800 dark:text-rose-200" x-text="error"></p>
        </div>

        {{-- Success Message --}}
        <div x-show="success" x-cloak class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            <p class="text-sm text-emerald-800 dark:text-emerald-200" x-text="success"></p>
        </div>

        {{-- Footer Actions - Inline at bottom of form --}}
        <div class="flex flex-row items-center gap-2 sm:gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
                type="button"
                @click="$dispatch('close-preview-panel', panelName)"
                class="w-1/2 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300
                       bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                       rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center"
            >
                Cancel
            </button>
            <button
                type="submit"
                :disabled="submitting"
                :class="submitting ? 'opacity-60 cursor-not-allowed' : ''"
                class="w-1/2 px-4 py-3 text-sm font-medium text-white bg-indigo-600
                       hover:bg-indigo-700 rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg x-show="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="submitting ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Task')"></span>
            </button>
        </div>
    </form>
</div>


<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-lg sm:text-xl font-semibold break-words">
                    Property Tasks — {{ $property->name }}
                </h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Manage property-level tasks that happen before, during, or after cleaning (not room-specific).
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $preTasks = $property
            ->propertyTasks()
            ->with('media')
            ->where('phase', 'pre_cleaning')
            ->orderBy('property_tasks.sort_order')
            ->get();

        $duringTasks = $property
            ->propertyTasks()
            ->with('media')
            ->where('phase', 'during_cleaning')
            ->orderBy('property_tasks.sort_order')
            ->get();

        $postTasks = $property
            ->propertyTasks()
            ->with('media')
            ->where('phase', 'post_cleaning')
            ->orderBy('property_tasks.sort_order')
            ->get();
    @endphp

    <div x-data="{
        activeTab: 'pre',
        init() {
            // Get tab from URL query parameter or local storage
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');
            const validTabs = ['pre', 'during', 'post'];

            if (tabFromUrl && validTabs.includes(tabFromUrl)) {
                this.activeTab = tabFromUrl;
            } else {
                // Fallback to local storage
                const savedTab = localStorage.getItem('property-tasks-active-tab');
                if (savedTab && validTabs.includes(savedTab)) {
                    this.activeTab = savedTab;
                }
            }

            // Watch for tab changes and update URL + storage
            this.$watch('activeTab', (value) => {
                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('tab', value);
                window.history.pushState({}, '', url);

                // Save to local storage
                localStorage.setItem('property-tasks-active-tab', value);
            });
        }
    }">
        {{-- Tabs and Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            {{-- Tabs Navigation --}}
            <div class="overflow-x-auto pb-1 sm:pb-0">
                <nav class="flex border-b border-gray-200 dark:border-gray-700 whitespace-nowrap" aria-label="Tabs">
                    {{-- Pre-Cleaning Tab --}}
                    <div @click="activeTab = 'pre'"
                        :class="activeTab === 'pre' ? 'text-theme-primary border-b-2 border-theme-primary' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap py-2.5 px-4 text-sm font-medium cursor-pointer transition-all duration-200 border-b-2 border-transparent">
                        <div class="flex items-center gap-1.5">
                            <span>Pre-Cleaning</span>
                            <span :class="activeTab === 'pre' ? '' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                  :style="activeTab === 'pre' ? 'background-color: color-mix(in srgb, var(--theme-primary) 15%, transparent); color: var(--theme-primary);' : ''"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors duration-200">
                                {{ $preTasks->count() }}
                            </span>
                        </div>
                    </div>

                    {{-- During-Cleaning Tab --}}
                    <div @click="activeTab = 'during'"
                        :class="activeTab === 'during' ? 'text-theme-primary border-b-2 border-theme-primary' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap py-2.5 px-4 text-sm font-medium cursor-pointer transition-all duration-200 border-b-2 border-transparent">
                        <div class="flex items-center gap-1.5">
                            <span>During-Cleaning</span>
                            <span :class="activeTab === 'during' ? '' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                  :style="activeTab === 'during' ? 'background-color: color-mix(in srgb, var(--theme-primary) 15%, transparent); color: var(--theme-primary);' : ''"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors duration-200">
                                {{ $duringTasks->count() }}
                            </span>
                        </div>
                    </div>

                    {{-- Post-Cleaning Tab --}}
                    <div @click="activeTab = 'post'"
                        :class="activeTab === 'post' ? 'text-theme-primary border-b-2 border-theme-primary' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap py-2.5 px-4 text-sm font-medium cursor-pointer transition-all duration-200 border-b-2 border-transparent">
                        <div class="flex items-center gap-1.5">
                            <span>Post-Cleaning</span>
                            <span :class="activeTab === 'post' ? '' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                  :style="activeTab === 'post' ? 'background-color: color-mix(in srgb, var(--theme-primary) 15%, transparent); color: var(--theme-primary);' : ''"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors duration-200">
                                {{ $postTasks->count() }}
                            </span>
                        </div>
                    </div>
                </nav>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2">
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}">← Rooms</x-button>

                <x-button variant="primary"
                    @click="
                        $dispatch('open-preview-panel', 'add-property-task-{{ $property->id }}');
                        $dispatch('set-property-task-phase', activeTab);
                    ">
                    + Add Property Task
                </x-button>
            </div>
        </div>

        {{-- Tab Content Tables --}}
        <x-card class="!px-0 overflow-hidden">
            <div class="overflow-hidden">
                {{-- Pre-Cleaning Tasks Content --}}
                <div x-show="activeTab === 'pre'" x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                    <x-tasks.table 
                        :tasks="$preTasks" 
                        :property="$property" 
                        context="property" 
                        :orderUrl="route('properties.property-tasks.order', $property)" 
                    />
                </div>

                {{-- During-Cleaning Tasks Content --}}
                <div x-show="activeTab === 'during'" x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                    <x-tasks.table 
                        :tasks="$duringTasks" 
                        :property="$property" 
                        context="property" 
                        :orderUrl="route('properties.property-tasks.order', $property)" 
                    />
                </div>

                {{-- Post-Cleaning Tasks Content --}}
                <div x-show="activeTab === 'post'" x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                    <x-tasks.table 
                        :tasks="$postTasks" 
                        :property="$property" 
                        context="property" 
                        :orderUrl="route('properties.property-tasks.order', $property)" 
                    />
                </div>
            </div>
        </x-card>

    </div>


    {{-- Add Property Task Preview Panel --}}
    <x-preview-panel name="add-property-task-{{ $property->id }}" :overlay="true" side="right"
        initialWidth="32rem" minWidth="24rem" title="Add Property Task" :subtitle="'Add a property-level task to ' . $property->name">
        @php $suggestUrl = route('tasks.suggest'); @endphp
        @include('properties.property-tasks.__property_task_form', [
            'property' => $property,
            'task' => null,
            'pivot' => null,
            'suggestUrl' => $suggestUrl,
            'mode' => 'create',
            'defaultPhase' => 'pre_cleaning',
        ])
    </x-preview-panel>

    {{-- Edit Property Task Preview Panels --}}
    @php
        $allPropertyTasks = $property->propertyTasks()->get();
    @endphp
    @foreach ($allPropertyTasks as $task)
        <x-preview-panel name="edit-property-task-{{ $property->id }}-{{ $task->id }}" :overlay="true"
            side="right" initialWidth="32rem" minWidth="24rem" title="Edit Property Task" :subtitle="$task->name">
            @php
                $suggestUrl = route('tasks.suggest');
                $pivot = $task->pivot;
            @endphp
            @include('properties.property-tasks.__property_task_form', [
                'property' => $property,
                'task' => $task,
                'pivot' => $pivot,
                'suggestUrl' => $suggestUrl,
                'mode' => 'edit',
            ])
        </x-preview-panel>
    @endforeach

    {{-- Floating Action Button for Mobile --}}
    <div class="fixed bottom-20 right-4 md:hidden z-40">
        <button @click="$dispatch('open-preview-panel', 'add-property-task-{{ $property->id }}'); $dispatch('set-property-task-phase', activeTab);" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-3 shadow-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        </button>
    </div>
</x-app-layout>


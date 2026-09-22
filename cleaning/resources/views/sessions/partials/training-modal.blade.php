{{-- Task-Level Training Requirement Modal --}}
<x-modal name="training-modal" maxWidth="md">
    <div class="flex flex-col bg-gray-50 dark:bg-gray-900" x-data="trainingModalFlow()">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-between items-center rounded-t-lg">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">TRAINING REQUIRED</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Before completing this task, you must complete the required training.</p>
                </div>
            </div>
            <button type="button" @click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- Content Area --}}
        <div class="p-6 space-y-4">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg" x-text="taskName"></h3>
            
            <p class="text-sm text-gray-600 dark:text-gray-300">
                <span x-text="pendingTraining.length"></span> required training item(s) remaining.
            </p>

            <div class="space-y-2 mt-4">
                <template x-for="(item, index) in pendingTraining" :key="index">
                    <div class="flex justify-between items-center bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="item.title || 'Task Instructions'"></span>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                            <span x-text="item.views_completed"></span> / <span x-text="item.views_required"></span>
                        </span>
                    </div>
                </template>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-end gap-3 rounded-b-lg">
            <x-button type="button" @click="$dispatch('close')" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50">
                Cancel
            </x-button>
            <x-button type="button" @click="startTraining" class="bg-indigo-600 hover:bg-indigo-700 text-white">
                Start Training
            </x-button>
        </div>
    </div>
</x-modal>

<script>
    window.trainingModalFlow = function() {
        return {
            taskId: null,
            roomId: null,
            taskName: '',
            pendingTraining: [],
            
            init() {
                window.addEventListener('show-training-modal', (e) => {
                    this.taskId = e.detail.task_id;
                    this.roomId = e.detail.room_id;
                    this.pendingTraining = e.detail.pending_training || [];
                    
                    // Try to extract task name from DOM
                    const taskEl = document.querySelector(`[data-task-id="${this.taskId}"]${this.roomId ? `[data-room-id="${this.roomId}"]` : ':not([data-room-id])'}`);
                    if (taskEl) {
                        const nameEl = taskEl.querySelector('[data-task-name]');
                        if (nameEl) this.taskName = nameEl.textContent.trim();
                    }
                    if (!this.taskName) this.taskName = 'Task Training';
                    
                    this.$dispatch('open-modal', 'training-modal');
                });
            },
            
            startTraining() {
                if (this.pendingTraining.length === 0) return;
                
                const sessionId = window.checklistHandler?.getSessionId();
                if (!sessionId) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Session ID not found' } }));
                    return;
                }
                
                // Get the first incomplete training item to start the flow
                const firstItem = this.pendingTraining.find(i => i.views_completed < i.views_required);
                if (!firstItem) return;
                
                const type = firstItem.type;
                const id = type === 'video' ? firstItem.video_id : firstItem.task_id;
                
                // Redirect to the training flow, passing the session and a return URL
                const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
                window.location.href = `/training/${type}/${id}?session=${sessionId}&return_to=${returnUrl}`;
            }
        };
    };
</script>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pre-Arrival Training') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-indigo-200 dark:border-indigo-900/50">
                <div class="p-8">
                    @if($currentItem)
                        @php
                            $isTask = $currentItem->task_id !== null;
                            $isVideo = $currentItem->instructional_video_id !== null;
                            $item = $isTask ? $currentItem->task : $currentItem->video;
                            $type = $isTask ? 'task' : 'video';
                        @endphp
                        
                        {{-- Wizard Header --}}
                        <div class="flex items-center justify-between mb-8 pb-6 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                                    @if($isVideo)
                                        <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    @else
                                        <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Required Training</h3>
                                    <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 mt-1">Step {{ $currentStep }} of {{ $totalRequired }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Wizard Content Area --}}
                        <div x-data="trainingWizard()" class="space-y-6 relative">
                            <h4 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">{{ $item->name ?? $item->title }}</h4>
                            
                            {{-- Instruction Overlay --}}
                            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-800 dark:text-blue-300 flex items-start gap-3">
                                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <div>
                                    @if($isVideo)
                                        <p class="font-bold">You must watch this video completely.</p>
                                        <p class="mt-1">The "Next" button will remain disabled until the video reaches the end.</p>
                                    @else
                                        <p class="font-bold">Please read the instructions carefully.</p>
                                        <p class="mt-1">You must explicitly click the confirmation button below to unlock the Next button.</p>
                                    @endif
                                </div>
                            </div>

                            @if($isVideo)
                                <div class="bg-black rounded-xl overflow-hidden aspect-video shadow-inner relative mt-6">
                                    <video id="training-video" class="w-full h-full object-contain" controls playsinline preload="auto">
                                        <source src="{{ $item->video_url }}" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            @endif

                            @if($isTask)
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 border border-gray-200 dark:border-gray-700 max-h-[60vh] overflow-y-auto mt-6">
                                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                                        @if($item->instructions)
                                            {!! nl2br(e($item->instructions)) !!}
                                        @else
                                            <p class="italic text-gray-500">No detailed text instructions provided for this task.</p>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-center">
                                        <button type="button" @click="markTextRead" :disabled="status === 'completed' || submitting" class="px-6 py-3 rounded-lg font-bold text-white transition-colors" :class="status === 'completed' ? 'bg-green-600' : (submitting ? 'bg-indigo-400 cursor-wait' : 'bg-indigo-600 hover:bg-indigo-700')">
                                            <span x-show="status !== 'completed' && !submitting">I Have Read and Understood This Instruction</span>
                                            <span x-show="submitting" style="display: none;">Saving...</span>
                                            <span x-show="status === 'completed'" style="display: none;">Completed! ✓</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Progress Bar (Videos Only) --}}
                            @if($isVideo)
                                <div class="mt-6 flex flex-col items-center">
                                    <div class="w-full max-w-md h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden relative">
                                        <div class="h-full bg-indigo-600 transition-all duration-300" :style="`width: ${progress}%`"></div>
                                    </div>
                                    <p class="text-sm font-bold mt-2" :class="status === 'completed' ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'" x-text="status === 'completed' ? '100% Completed ✓' : `${progress}% Watched`"></p>
                                    <template x-if="viewsRequired > 1 && status !== 'completed'">
                                        <p class="text-sm font-bold mt-1 text-orange-600 dark:text-orange-400" x-text="`Required Views: ${viewsCompleted} / ${viewsRequired}`"></p>
                                    </template>
                                </div>
                            @endif

                            {{-- Action Buttons --}}
                            <div class="flex justify-between items-center mt-10 pt-6 border-t border-gray-100 dark:border-gray-700">
                                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                    &larr; Exit to Dashboard
                                </a>
                                
                                <form method="POST" action="{{ route('sessions.start', $session->id) }}">
                                    @csrf
                                    <input type="hidden" name="latitude" value="{{ session('last_lat', 0) }}">
                                    <input type="hidden" name="longitude" value="{{ session('last_lng', 0) }}">
                                    
                                    <button type="submit" 
                                            :disabled="status !== 'completed'"
                                            class="px-8 py-3 rounded-lg font-bold text-white shadow-lg transition-all flex items-center gap-2"
                                            :class="status === 'completed' ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed opacity-50'">
                                        @if($currentStep < $totalRequired)
                                            Next Step 
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        @else
                                            Finish & Start Session
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </button>
                                </form>
                            </div>
                        </div>

                        <script>
                            window.trainingWizard = function() {
                                return {
                                    type: '{{ $type }}',
                                    itemId: '{{ $item->id }}',
                                    sessionId: '{{ $session->id }}',
                                    progress: 0,
                                    status: 'in_progress',
                                    lastSync: Date.now(),
                                    activeSecondsSinceSync: 0,
                                    submitting: false,
                                    timeTrackerInterval: null,
                                    viewsCompleted: 0,
                                    viewsRequired: 1,

                                    init() {
                                        if (this.type === 'video') {
                                            this.$nextTick(() => {
                                                let video = document.getElementById('training-video');
                                                if (video) {
                                                    video.addEventListener('timeupdate', () => {
                                                        if (this.status === 'completed') return;
                                                        
                                                        if (video.duration) {
                                                            let p = Math.floor((video.currentTime / video.duration) * 100);
                                                            p = Math.min(99, p); // Cap normal progress below completion to force 'ended' event
                                                            if (p > this.progress && p <= 99) {
                                                                this.progress = p;
                                                                if (p % 10 === 0) {
                                                                    this.syncProgress(p);
                                                                }
                                                            }
                                                        }
                                                    });

                                                    video.addEventListener('ended', () => {
                                                        if (this.status !== 'completed') {
                                                            this.progress = 100;
                                                            this.syncProgress(100, true);
                                                        }
                                                    });
                                                    
                                                    this.timeTrackerInterval = setInterval(() => {
                                                        if (!video.paused && this.status !== 'completed') {
                                                            this.activeSecondsSinceSync++;
                                                        }
                                                    }, 1000);
                                                }
                                            });
                                        }
                                    },

                                    markTextRead() {
                                        if (this.status === 'completed' || this.submitting) return;
                                        this.submitting = true;
                                        this.progress = 100;
                                        this.syncProgress(100, true).finally(() => {
                                            this.submitting = false;
                                        });
                                    },

                                    async syncProgress(val, isEnded = false) {
                                        if (this.status === 'completed') return Promise.resolve();
                                        let now = Date.now();
                                        if (val < 100 && !isEnded && now - this.lastSync < 2000) return Promise.resolve();
                                        
                                        this.lastSync = now;
                                        let timeDelta = this.activeSecondsSinceSync;
                                        this.activeSecondsSinceSync = 0; 

                                        try {
                                            const response = await fetch(`/training/${this.type}/${this.itemId}/progress`, {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                                },
                                                body: JSON.stringify({
                                                    progress: val,
                                                    session_id: this.sessionId,
                                                    time_spent_delta: timeDelta,
                                                    is_ended: isEnded
                                                })
                                            });
                                            
                                            const data = await response.json();
                                            if (this.status === 'completed') return; // Ignore stale responses
                                            this.status = data.status;
                                            if (data.views_completed !== undefined) this.viewsCompleted = data.views_completed;
                                            if (data.required_views !== undefined) this.viewsRequired = data.required_views;
                                            
                                            if (this.status === 'completed') {
                                                this.progress = 100;
                                                if (this.timeTrackerInterval) {
                                                    clearInterval(this.timeTrackerInterval);
                                                }
                                            } else {
                                                // If we sent isEnded but it's still in progress, it means more views are required.
                                                // We must reset the progress visually so the user can watch again.
                                                if (isEnded) {
                                                    this.progress = data.progress; // Will be 0
                                                    let video = document.getElementById('training-video');
                                                    if (video) {
                                                        video.currentTime = 0;
                                                    }
                                                }
                                            }
                                        } catch (error) {
                                            console.error('Failed to sync progress', error);
                                            this.activeSecondsSinceSync += timeDelta; 
                                        }
                                    }
                                };
                            };
                        </script>
                    @else
                        <div class="text-center py-12">
                            <h3 class="text-2xl font-bold text-green-600 dark:text-green-400 mb-4">All Training Completed!</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-8">You have successfully completed all required pre-arrival training.</p>
                            <form method="POST" action="{{ route('sessions.start', $session->id) }}">
                                @csrf
                                <input type="hidden" name="latitude" value="{{ session('last_lat', 0) }}">
                                <input type="hidden" name="longitude" value="{{ session('last_lng', 0) }}">
                                <button type="submit" class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg shadow-lg">Start Session Now</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

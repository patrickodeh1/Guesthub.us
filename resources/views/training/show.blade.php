<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $type === 'video' ? $item->title : $item->name }}
            </h2>
            @if(!empty($returnTo))
                <a href="{{ $returnTo }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-semibold transition-colors">
                    &larr; Back to Checklist
                </a>
            @elseif($session)
                <a href="{{ route('sessions.training_required', $session->id) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-semibold transition-colors">
                    &larr; Back to Session
                </a>
            @else
                <a href="{{ route('training.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-semibold transition-colors">
                    &larr; Back to Hub
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12" x-data="trainingTracker()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <template x-if="status === 'completed'">
                        <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-md font-semibold flex flex-col sm:flex-row items-center justify-between border border-green-200 dark:border-green-800 gap-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                <span>You have successfully completed this training!</span>
                            </div>
                            @if(!empty($returnTo))
                                <a href="{{ $returnTo }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm transition-colors whitespace-nowrap shadow-sm">
                                    Return to Task &rarr;
                                </a>
                            @endif
                        </div>
                    </template>

                    <template x-if="status !== 'completed'">
                        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 rounded-md flex items-center border border-blue-200 dark:border-blue-800/50">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <p class="font-semibold text-sm mb-0.5">Training Requirement</p>
                                <p class="text-xs opacity-90">You must watch this video completely (reach 100%) to mark it as finished and unlock your session. The progress bar will save automatically as you watch.</p>
                            </div>
                        </div>
                    </template>

                    @if($type === 'video')
                        <div class="aspect-w-16 aspect-h-9 mb-6 bg-black rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shadow-inner">
                            <video id="training-video" controls playsinline preload="metadata" class="w-full h-full" x-ref="video" @timeupdate="updateVideoProgress" @ended="completeVideo">
                                <source src="{{ $item->video_url }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <p>{{ $item->description }}</p>
                        </div>
                    @elseif($type === 'task')
                        <div class="prose dark:prose-invert max-w-none mb-8" x-ref="taskContent">
                            {!! nl2br(e($item->instructions)) !!}
                        </div>
                        
                        @if($item->instruction_completion_method === 'manual')
                            <div class="mt-8 border-t dark:border-gray-700 pt-6">
                                <button @click="markManualComplete" 
                                        class="px-6 py-2 rounded-md font-semibold text-white transition-colors"
                                        :class="status === 'completed' ? 'bg-green-600 hover:bg-green-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                        x-text="status === 'completed' ? 'Completed' : 'Mark as Read'"
                                        :disabled="status === 'completed'">
                                </button>
                            </div>
                        @endif
                    @endif

                    <!-- Progress Bar (Only show if not completed) -->
                    <div class="mt-8" x-show="status !== 'completed'" style="display: none;">
                        <div class="flex justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span>Video Progress</span>
                            <span x-text="progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden shadow-inner">
                            <div class="bg-indigo-600 h-full rounded-full transition-all duration-300 ease-out" :style="`width: ${progress}%`"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        window.trainingTracker = function() {
            return {
                status: '{{ $completion->status }}',
                progress: {{ $completion->progress }},
                type: '{{ $type }}',
                itemId: '{{ $item->id }}',
                sessionId: '{{ $session ? $session->id : "" }}',
                completionMethod: '{{ $item->instruction_completion_method ?? 'manual' }}',
                lastSync: 0,
                activeSecondsSinceSync: 0,
                timeTrackerInterval: null,

                init() {
                    // Track time spent on page while active
                    this.timeTrackerInterval = setInterval(() => {
                        if (document.visibilityState === 'visible' && this.status !== 'completed') {
                            this.activeSecondsSinceSync++;
                        }
                    }, 1000);
                    
                    if (this.type === 'video' && this.$refs.video) {
                        // Restore progress if not completed
                        if (this.status !== 'completed' && this.progress > 0 && this.progress < 100) {
                            let duration = {{ $type === 'video' ? $item->duration_seconds : 0 }};
                            if (duration > 0) {
                                this.$refs.video.currentTime = (this.progress / 100) * duration;
                            }
                        }
                    }

                    if (this.type === 'task') {
                        if (this.completionMethod === 'time' && this.status !== 'completed') {
                            this.startTimeTracking();
                        } else if (this.completionMethod === 'scroll' && this.status !== 'completed') {
                            this.setupScrollTracking();
                        }
                    }
                },

                updateVideoProgress() {
                    if (!this.$refs.video) return;
                    
                    let duration = this.$refs.video.duration;
                    if (!duration) return;
                    
                    let current = this.$refs.video.currentTime;
                    let currentProgress = Math.floor((current / duration) * 100);
                    
                    if (this.status !== 'completed') {
                        // Cap normal progress updates below completion
                        currentProgress = Math.min(99, currentProgress);
                        
                        if (currentProgress > this.progress) {
                            this.progress = currentProgress;
                            this.syncProgress(currentProgress);
                        }
                    } else {
                        // UI only update for re-watching
                        this.progress = currentProgress;
                    }
                },

                completeVideo() {
                    if (this.status !== 'completed') {
                        this.progress = 100;
                        this.syncProgress(100, true);
                    }
                },

                markManualComplete() {
                    this.progress = 100;
                    this.syncProgress(100);
                },

                startTimeTracking() {
                    let requiredSeconds = {{ $type === 'task' ? ($item->estimated_duration_minutes ?? 1) * 60 : 60 }};
                    let secondsSpent = 0;
                    
                    let interval = setInterval(() => {
                        if (this.status === 'completed') {
                            clearInterval(interval);
                            return;
                        }
                        
                        secondsSpent++;
                        let currentProgress = Math.floor((secondsSpent / requiredSeconds) * 100);
                        
                        if (currentProgress > this.progress && currentProgress <= 100) {
                            this.progress = currentProgress;
                            if (currentProgress % 10 === 0 || currentProgress === 100) {
                                this.syncProgress(currentProgress);
                            }
                        }
                        
                        if (secondsSpent >= requiredSeconds) {
                            clearInterval(interval);
                        }
                    }, 1000);
                },

                setupScrollTracking() {
                    window.addEventListener('scroll', () => {
                        if (this.status === 'completed') return;
                        
                        let scrollable = document.documentElement.scrollHeight - window.innerHeight;
                        if (scrollable <= 0) {
                            this.progress = 100;
                            this.syncProgress(100);
                            return;
                        }
                        
                        let scrolled = window.scrollY;
                        let currentProgress = Math.floor((scrolled / scrollable) * 100);
                        
                        if (currentProgress > this.progress) {
                            this.progress = currentProgress;
                            if (currentProgress % 20 === 0 || currentProgress >= 95) {
                                this.syncProgress(currentProgress === 95 ? 100 : currentProgress);
                            }
                        }
                    });
                },

                syncProgress(val, isEnded = false) {
                    let now = Date.now();
                    if (val < 100 && !isEnded && now - this.lastSync < 2000) return;
                    
                    this.lastSync = now;
                    let timeDelta = this.activeSecondsSinceSync;
                    this.activeSecondsSinceSync = 0; 

                    fetch(`/training/${this.type}/${this.itemId}/progress`, {
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
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.status = data.status;
                        if (this.status === 'completed' && this.timeTrackerInterval) {
                            clearInterval(this.timeTrackerInterval);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        this.activeSecondsSinceSync += timeDelta;
                    });
                }
            };
        };
    </script>
</x-app-layout>

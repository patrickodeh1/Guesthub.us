<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Upload Instructional Video
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add a new video to the global library and assign it to properties.
                </p>
            </div>
            <x-button variant="secondary" href="{{ route('admin.videos.index') }}">
                Back to Library
            </x-button>
        </div>
    </x-slot>

    <x-card>
        <form x-data="videoForm()" method="post" action="{{ route('admin.videos.store') }}" enctype="multipart/form-data" @submit.prevent="submitForm($event)">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left column: Video & Thumbnail Uploader --}}
                <div class="lg:col-span-1 space-y-6">
                    {{-- Video Uploader --}}
                    <div>
                        <x-form.label value="Video File (Required)" />
                        <div class="mt-1 border-2 border-dashed rounded-2xl p-5 flex flex-col items-center justify-center text-center cursor-pointer transition-colors"
                            :class="videoDrag ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                                'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                            @click="$refs.videoFile.click()" @dragover.prevent="videoDrag = true"
                            @dragleave.prevent="videoDrag = false" @drop.prevent="handleVideoDrop($event)">
                            
                            <template x-if="!videoName">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-2 text-sm font-medium">Drag &amp; drop video or click</p>
                                    <p class="mt-1 text-xs text-gray-400">MP4, MOV, AVI, WebM — up to 200MB</p>
                                </div>
                            </template>

                            <template x-if="videoName">
                                <div class="w-full text-left p-2">
                                    <div class="flex items-center gap-3 bg-indigo-50 dark:bg-indigo-950/40 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/40">
                                        <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" x-text="videoName"></p>
                                            <p class="text-xs text-gray-500" x-text="videoSize"></p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-center text-xs text-gray-400">Click or drop to replace.</p>
                                </div>
                            </template>

                            <input type="file" name="video" x-ref="videoFile" class="hidden" @change="previewVideo($event)" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska" required />
                        </div>
                        <x-form.error :messages="$errors->get('video')" />
                    </div>

                    {{-- Thumbnail Uploader --}}
                    <div>
                        <x-form.label value="Thumbnail Image (Optional)" />
                        <div class="mt-1 border-2 border-dashed rounded-2xl p-5 flex flex-col items-center justify-center text-center cursor-pointer transition-colors"
                            :class="thumbDrag ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                                'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                            @click="$refs.thumbFile.click()" @dragover.prevent="thumbDrag = true"
                            @dragleave.prevent="thumbDrag = false" @drop.prevent="handleThumbDrop($event)">
                            
                            <template x-if="!thumbUrl">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-2 text-sm font-medium">Drag &amp; drop thumbnail or click</p>
                                    <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — up to 5MB</p>
                                </div>
                            </template>

                            <template x-if="thumbUrl">
                                <div class="w-full">
                                    <img :src="thumbUrl" alt="Preview" class="rounded-xl object-cover aspect-video w-full max-h-40 mx-auto shadow-md" />
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Click or drop to replace.</p>
                                </div>
                            </template>

                            <input type="file" name="thumbnail" x-ref="thumbFile" class="hidden" @change="previewThumb($event)" accept="image/*" />
                        </div>
                        <x-form.error :messages="$errors->get('thumbnail')" />
                    </div>
                </div>

                {{-- Right column: Video Details and Properties --}}
                <div class="lg:col-span-2 space-y-4">
                    {{-- Title --}}
                    <div>
                        <x-form.label value="Title" />
                        <x-form.input name="title" class="w-full" required :value="old('title')"
                            placeholder="e.g. Living Room Mop & Sweeping Procedure" autofocus />
                        <x-form.error :messages="$errors->get('title')" />
                    </div>

                    {{-- Description --}}
                    <div>
                        <x-form.label value="Description / Details" />
                        <textarea name="description" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Provide a description of the procedures in the video... (Markdown supported)">{{ old('description') }}</textarea>
                        <x-form.error :messages="$errors->get('description')" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Category --}}
                        <div>
                            <x-form.label value="Category" />
                            <x-form.select name="category" class="w-full" required>
                                <option value="">— Select Category —</option>
                                <option value="general" @selected(old('category') === 'general')>General Guidelines</option>
                                <option value="room-specific" @selected(old('category') === 'room-specific')>Room Specific</option>
                                <option value="equipment" @selected(old('category') === 'equipment')>Equipment</option>
                                <option value="safety" @selected(old('category') === 'safety')>Safety</option>
                                <option value="process" @selected(old('category') === 'process')>Process</option>
                            </x-form.select>
                            <x-form.error :messages="$errors->get('category')" />
                        </div>

                        {{-- Duration --}}
                        <div>
                            <x-form.label value="Duration (Seconds, Optional)" />
                            <x-form.input name="duration_seconds" type="number" min="0" class="w-full" :value="old('duration_seconds')"
                                placeholder="e.g. 150 (for 2m 30s)" />
                            <x-form.error :messages="$errors->get('duration_seconds')" />
                        </div>
                    </div>

                    {{-- Published Toggle --}}
                    <div class="pt-2">
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" name="is_published" value="1"
                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-5 h-5"
                                @checked(old('is_published') == '1')>
                            <div>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">Publish Immediately</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">If checked, cleaners will immediately see this video. Keep unchecked to save as draft.</p>
                            </div>
                        </label>
                        <x-form.error :messages="$errors->get('is_published')" />
                    </div>

                    {{-- Pre-Arrival Training Configuration --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Pre-Arrival Training Configuration</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Configure how this video behaves in the Housekeeper Pre-Arrival Training Hub.
                        </p>

                        <div class="space-y-4">
                            <div>
                                <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                    <input type="checkbox" name="is_pre_arrival" value="1" x-model="isPreArrival"
                                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-5 h-5">
                                    <div>
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">Include in Pre-Arrival Training Hub</span>
                                    </div>
                                </label>
                            </div>

                            <div x-show="isPreArrival" class="pl-8 space-y-4">
                                <div>
                                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="is_required_before_start" value="1"
                                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-5 h-5"
                                            @checked(old('is_required_before_start') == '1')>
                                        <div>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">Mandatory (Block Session Start)</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cleaners cannot start the cleaning session until they watch this.</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="space-y-4 mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="is_required_during_task" value="1"
                                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-5 h-5"
                                            @checked(old('is_required_during_task') == '1')>
                                        <div>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">Require During Task (Block Task Completion)</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cleaners must complete this video while performing associated tasks.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div x-show="isPreArrival || formData?.is_required_during_task" class="pl-8 pt-2">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-form.label value="Required Views" />
                                        <x-form.input type="number" name="required_views" class="w-full" min="1" value="{{ old('required_views', 1) }}" />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Number of times this video must be viewed.</p>
                                    </div>
                                    <div>
                                        <x-form.label value="Completion Threshold (%)" />
                                        <x-form.input type="number" name="completion_threshold_percent" class="w-full" min="1" max="100" value="{{ old('completion_threshold_percent', 90) }}" />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Percentage of video that must be watched.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div x-show="isPreArrival" class="pl-8 pt-2 border-t border-gray-200 dark:border-gray-700 mt-4">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Pre-Arrival Specifics</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-form.label value="Training Frequency" />
                                        <x-form.select name="training_frequency" class="w-full">
                                            <option value="once_ever" @selected(old('training_frequency') === 'once_ever')>Once Ever</option>
                                            <option value="once_per_property" @selected(old('training_frequency') === 'once_per_property')>Once Per Property</option>
                                            <option value="once_per_assignment" @selected(old('training_frequency') === 'once_per_assignment')>Once Per Assignment</option>
                                            <option value="every_assignment" @selected(old('training_frequency') === 'every_assignment')>Every Assignment</option>
                                        </x-form.select>
                                    </div>
                                    <div>
                                        <!-- Empty placeholder column -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Property Assignment Checklist --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Assign to Properties</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Select which properties this video is relevant for. You must select at least one property to make it accessible to clean teams.
                        </p>
                        
                        @if ($properties->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-200 dark:border-gray-700 max-h-56 overflow-y-auto">
                                @foreach ($properties as $property)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <input type="checkbox" name="properties[]" value="{{ $property->id }}"
                                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                            @checked(in_array($property->id, old('properties', [])))>
                                        <span class="truncate">{{ $property->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-gray-500 p-4 border rounded-xl text-center">
                                No properties available. Please create a property first.
                            </div>
                        @endif
                        <x-form.error :messages="$errors->get('properties')" />
                    </div>

                    {{-- Task Assignment Checklist --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Assign to Tasks</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Select which tasks this video is associated with. When associated, it can be enforced during the task execution.
                        </p>
                        
                        @if ($tasks->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-200 dark:border-gray-700 max-h-56 overflow-y-auto">
                                @foreach ($tasks as $task)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <input type="checkbox" name="tasks[]" value="{{ $task->id }}"
                                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                            @checked(in_array($task->id, old('tasks', [])))>
                                        <span class="truncate">{{ $task->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-gray-500 p-4 border rounded-xl text-center">
                                No tasks available. Please create a task first.
                            </div>
                        @endif
                        <x-form.error :messages="$errors->get('tasks')" />
                    </div>
                </div>
            </div>

            {{-- Submit Footer --}}
            <div class="mt-8 flex flex-col gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                
                {{-- Upload Error Message --}}
                <div x-show="uploadError" x-cloak x-transition class="w-full">
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-red-800 dark:text-red-300">Upload Failed</h4>
                                <p class="text-sm text-red-700 dark:text-red-400 mt-1 whitespace-pre-line" x-text="uploadError"></p>
                            </div>
                            <button type="button" @click="uploadError = null" class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Upload Progress Bar --}}
                <div x-show="uploading" class="w-full" x-transition>
                    <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300 mb-1">
                        <span class="font-medium">Uploading Video...</span>
                        <span x-text="Math.round(uploadProgress) + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                        <div class="bg-indigo-600 h-3 rounded-full transition-all duration-300 ease-out relative"
                             :style="`width: ${uploadProgress}%`">
                            <div class="absolute inset-0 bg-white/20 animate-pulse rounded-full" x-show="uploadProgress < 100"></div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5" x-show="uploadSpeed">
                        <span x-text="uploadSpeed"></span> · <span x-text="uploadEta"></span> remaining
                    </p>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <x-button type="submit" x-bind:disabled="uploading" class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500">
                        <span x-show="!uploading">Upload &amp; Save</span>
                        <span x-show="uploading">Please Wait...</span>
                    </x-button>
                    <x-button variant="secondary" href="{{ route('admin.videos.index') }}" x-bind:disabled="uploading">
                        Cancel
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <script src="{{ url('/ffmpeg-assets/ffmpeg.js') }}"></script>
    <script>
        function videoForm() {
            return {
                isPreArrival: {{ old('is_pre_arrival') ? 'true' : 'false' }},
                videoDrag: false,
                videoName: null,
                videoSize: null,
                videoDuration: null,
                uploading: false,
                uploadProgress: 0,
                uploadError: null,
                uploadSpeed: null,
                uploadEta: null,
                _uploadStartTime: null,
                
                thumbDrag: false,
                thumbUrl: null,

                previewVideo(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    
                    const maxSize = 200 * 1024 * 1024; // 200MB
                    if (file.size > maxSize) {
                        this.uploadError = 'Video size (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) exceeds the 200MB limit. Please compress it or choose a smaller file.';
                        event.target.value = '';
                        this.videoName = null;
                        return;
                    }
                    this.uploadError = null;
                    
                    this.videoName = file.name;
                    this.videoSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                    
                    // Auto-detect video duration
                    const url = URL.createObjectURL(file);
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.src = url;
                    video.onloadedmetadata = () => {
                        this.videoDuration = Math.round(video.duration);
                        // Auto-fill duration field
                        const durationInput = document.querySelector('input[name="duration_seconds"]');
                        if (durationInput && !durationInput.value) {
                            durationInput.value = this.videoDuration;
                        }
                        URL.revokeObjectURL(url);
                    };
                },

                handleVideoDrop(evt) {
                    this.videoDrag = false;
                    const file = evt.dataTransfer.files?.[0];
                    if (!file) return;

                    this.$refs.videoFile.files = evt.dataTransfer.files;
                    this.previewVideo({
                        target: {
                            files: this.$refs.videoFile.files
                        }
                    });
                },

                previewThumb(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.thumbUrl = URL.createObjectURL(file);
                },

                handleThumbDrop(evt) {
                    this.thumbDrag = false;
                    const file = evt.dataTransfer.files?.[0];
                    if (!file) return;

                    this.$refs.thumbFile.files = evt.dataTransfer.files;
                    this.previewThumb({
                        target: {
                            files: this.$refs.thumbFile.files
                        }
                    });
                },
                
                async optimizeVideo(file) {
                    if (typeof window.FFmpegWASM === 'undefined') {
                        this.uploadSpeed = "Initializing video engine...";
                        await new Promise((resolve, reject) => {
                            let script = document.createElement('script');
                            script.src = "{{ url('/ffmpeg-assets/ffmpeg.js') }}?v=" + Date.now();
                            script.onload = resolve;
                            script.onerror = () => {
                                alert("Critical Error: Your server is blocking the FFmpeg script. Please contact your host.");
                                reject(new Error("FFmpeg script blocked"));
                            };
                            document.head.appendChild(script);
                        });
                    }

                    const { FFmpeg } = window.FFmpegWASM;
                    
                    this.isOptimizing = true;
                    this.uploadProgress = 0;
                    this.uploadSpeed = 'Downloading video engine (~30MB) - Please wait...';
                    
                    const fetchFile = async (f) => {
                        return new Uint8Array(await f.arrayBuffer());
                    };
                    
                    const ffmpeg = new FFmpeg();
                    
                    ffmpeg.on('log', ({ message }) => {
                        console.log(message);
                    });
                    
                    ffmpeg.on('progress', ({ progress }) => {
                        this.uploadProgress = Math.round(progress * 100);
                        this.uploadSpeed = `Optimizing video for iPhone... ${this.uploadProgress}%`;
                    });
                    
                    const baseURL = "{{ url('/ffmpeg-assets') }}";
                    
                    // Load FFmpeg.wasm from local server.
                    // CRITICAL: We explicitly DO NOT pass classWorkerURL. 
                    // If classWorkerURL is passed, ffmpeg.wasm forces {type: "module"} on the Web Worker,
                    // which breaks importScripts on Safari/iOS and throws "failed to import ffmpeg-core.js".
                    // By omitting it, ffmpeg.js automatically loads 814.ffmpeg.js as a standard script worker.
                    await ffmpeg.load({
                        coreURL: `${baseURL}/ffmpeg-core.js`,
                        wasmURL: `${baseURL}/ffmpeg-core.wasm`
                    });
                    
                    const inputName = 'input_' + Date.now() + '.mp4';
                    const outputName = 'output_' + Date.now() + '.mp4';
                    
                    await ffmpeg.writeFile(inputName, await fetchFile(file));
                    
                    // Run FFmpeg command - ultrafast preset to keep browser from freezing too long
                    await ffmpeg.exec([
                        '-i', inputName,
                        '-c:v', 'libx264',
                        '-preset', 'ultrafast',
                        '-crf', '28',
                        '-pix_fmt', 'yuv420p',
                        '-c:a', 'aac',
                        '-b:a', '128k',
                        outputName
                    ]);
                    
                    const data = await ffmpeg.readFile(outputName);
                    
                    // CRITICAL: Terminate the Web Worker to free up 500MB+ of memory.
                    // If we don't do this, subsequent uploads will hang due to OOM!
                    ffmpeg.terminate();
                    
                    // Ensure the final uploaded file always ends in .mp4 so the server sends the correct video/mp4 MIME type
                    const finalName = file.name.replace(/\.[^/.]+$/, "") + ".mp4";
                    return new File([data.buffer], finalName, { type: 'video/mp4' });
                },

                async submitForm(event) {
                    if (this.uploading) return;
                    const form = event.target;
                    
                    this.uploading = true;
                    this.uploadProgress = 0;
                    this.uploadError = null;
                    this.uploadSpeed = 'Preparing video...';
                    this.uploadEta = null;
                    
                    let fileToUpload = this.$refs.videoFile.files?.[0];
                    
                    // Process video with FFmpeg.wasm
                    if (fileToUpload) {
                        try {
                            this.uploadSpeed = 'Loading Apple Optimizer (One-time download ~25MB)...';
                            fileToUpload = await this.optimizeVideo(fileToUpload);
                            this.uploadSpeed = 'Video optimized! Starting upload...';
                        } catch (e) {
                            console.error("FFmpeg Error:", e);
                            let errorMsg = e;
                            if (e instanceof Error) {
                                errorMsg = e.message;
                            } else if (typeof e === 'object') {
                                errorMsg = JSON.stringify(e);
                            }
                            this.uploadError = 'Failed to optimize video: ' + errorMsg;
                            this.uploading = false;
                            return;
                        }
                    }
                    
                    const formData = new FormData(form);
                    if (fileToUpload) {
                        formData.set('video', fileToUpload);
                    }
                    
                    this.uploadProgress = 0;
                    this._uploadStartTime = Date.now();
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', form.action, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                            // Calculate speed and ETA
                            const elapsed = (Date.now() - this._uploadStartTime) / 1000;
                            if (elapsed > 0.5) {
                                const bytesPerSec = e.loaded / elapsed;
                                const remaining = e.total - e.loaded;
                                const etaSec = remaining / bytesPerSec;
                                
                                if (bytesPerSec > 1024 * 1024) {
                                    this.uploadSpeed = (bytesPerSec / (1024 * 1024)).toFixed(1) + ' MB/s (Uploading)';
                                } else {
                                    this.uploadSpeed = (bytesPerSec / 1024).toFixed(0) + ' KB/s (Uploading)';
                                }
                                
                                if (etaSec < 60) {
                                    this.uploadEta = Math.ceil(etaSec) + 's';
                                } else {
                                    this.uploadEta = Math.ceil(etaSec / 60) + 'm ' + Math.ceil(etaSec % 60) + 's';
                                }
                            }
                        }
                    };
                    
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            const res = JSON.parse(xhr.responseText);
                            window.location.href = res.redirect || '{{ route('admin.videos.index') }}';
                        } else {
                            this.uploading = false;
                            this.uploadProgress = 0;
                            let msg = 'Upload failed.';
                            try {
                                const res = JSON.parse(xhr.responseText);
                                msg = res.message || msg;
                                if (res.errors) {
                                    msg = Object.values(res.errors).flat().join('\n');
                                }
                            } catch (e) {}
                            if (xhr.status === 413) msg = 'The video file is too large for the server to accept. Please use a smaller file (under 200MB).';
                            if (xhr.status === 422) msg = 'Validation error: ' + msg;
                            this.uploadError = msg;
                        }
                    };
                    
                    xhr.onerror = () => {
                        this.uploading = false;
                        this.uploadProgress = 0;
                        this.uploadError = 'A network error occurred during upload. Please check your connection and try again.';
                    };
                    
                    xhr.send(formData);
                }
            }
        }
    </script>
</x-app-layout>

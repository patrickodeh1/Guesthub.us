<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pre-Arrival Training Hub') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Overview Stats -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Total Completed</h3>
                    <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-2">{{ $completedCount }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Items you have finished</p>
                </div>
            </div>

            <!-- Required Today -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-red-200 dark:border-red-900/50">
                <div class="p-6 text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-red-600 dark:text-red-400 mb-2">Required Today</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">You must complete these before starting your scheduled cleaning sessions today.</p>

                    @if($requiredToday->isEmpty())
                        <p class="text-green-600 dark:text-green-400 font-medium">You are all caught up for today!</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($requiredToday as $snapshot)
                                <div class="p-4 border border-red-200 dark:border-red-900/50 rounded-md bg-red-50 dark:bg-red-900/20 flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">
                                            @if($snapshot->task)
                                                <span class="text-xs uppercase font-bold tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 px-2 py-1 rounded mr-2">Task</span>
                                                {{ $snapshot->task->name }}
                                            @elseif($snapshot->video)
                                                <span class="text-xs uppercase font-bold tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 px-2 py-1 rounded mr-2">Video</span>
                                                {{ $snapshot->video->title }}
                                            @endif
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">For session: {{ $snapshot->cleaningSession->property->name ?? 'Unknown' }}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        @if($snapshot->task)
                                            <a href="{{ route('training.show', ['type' => 'task', 'id' => $snapshot->task->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium shadow-sm transition-colors">Read</a>
                                        @elseif($snapshot->video)
                                            <a href="{{ route('training.show', ['type' => 'video', 'id' => $snapshot->video->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium shadow-sm transition-colors">Watch</a>
                                        @endif
                                        <a href="{{ route('sessions.show', $snapshot->cleaning_session_id) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-medium shadow-sm transition-colors">Go to Session</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Upcoming -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">Upcoming Required Training</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Get ahead by completing training for future assignments.</p>

                    @if($upcoming->isEmpty())
                        <p class="text-gray-500 dark:text-gray-500 italic">No upcoming training requirements.</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($upcoming as $snapshot)
                                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-md flex items-center justify-between hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors bg-gray-50 dark:bg-gray-900/50">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-200">
                                            @if($snapshot->task)
                                                <span class="text-xs uppercase bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded mr-2">Task</span>
                                                {{ $snapshot->task->name }}
                                            @elseif($snapshot->video)
                                                <span class="text-xs uppercase bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded mr-2">Video</span>
                                                {{ $snapshot->video->title }}
                                            @endif
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">For session: {{ $snapshot->cleaningSession->property->name ?? 'Unknown' }} ({{ \Carbon\Carbon::parse($snapshot->cleaningSession->scheduled_date)->format('M d') }})</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        @if($snapshot->task)
                                            <a href="{{ route('training.show', ['type' => 'task', 'id' => $snapshot->task->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-medium transition-colors">Read</a>
                                        @elseif($snapshot->video)
                                            <a href="{{ route('training.show', ['type' => 'video', 'id' => $snapshot->video->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-medium transition-colors">Watch</a>
                                        @endif
                                        <a href="{{ route('sessions.show', $snapshot->cleaning_session_id) }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors">Session &rarr;</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        <!-- Optional -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6 border border-gray-200 dark:border-gray-700">
                <div class="p-6 text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">Optional Training</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">These items are available for your current or upcoming sessions but are not required to start cleaning.</p>

                    @if($optional->isEmpty())
                        <p class="text-gray-500 dark:text-gray-500 italic">No optional training items available.</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($optional as $snapshot)
                                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-200">
                                            @if($snapshot->task)
                                                <span class="text-xs uppercase bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded mr-2">Task</span>
                                                {{ $snapshot->task->name }}
                                            @elseif($snapshot->video)
                                                <span class="text-xs uppercase bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded mr-2">Video</span>
                                                {{ $snapshot->video->title }}
                                            @endif
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">For session: {{ $snapshot->cleaningSession->property->name ?? 'Unknown' }}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        @if($snapshot->task)
                                            <a href="{{ route('training.show', ['type' => 'task', 'id' => $snapshot->task->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors">Read</a>
                                        @elseif($snapshot->video)
                                            <a href="{{ route('training.show', ['type' => 'video', 'id' => $snapshot->video->id, 'session' => $snapshot->cleaning_session_id]) }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors">Watch</a>
                                        @endif
                                        <a href="{{ route('sessions.show', $snapshot->cleaning_session_id) }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors">Session &rarr;</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Completed -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6 border border-gray-200 dark:border-gray-700">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">Completed Training</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Review training you have already finished.</p>

                    @if($completedItems->isEmpty())
                        <p class="text-gray-500 dark:text-gray-500 italic">No completed training items.</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($completedItems as $completion)
                                <div class="p-4 border border-green-200 dark:border-green-900/50 rounded-md bg-green-50 dark:bg-green-900/20 flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                            @if($completion->task)
                                                <span class="text-xs uppercase font-bold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 px-2 py-0.5 rounded mr-2">Task</span>
                                                {{ $completion->task->name }}
                                            @elseif($completion->video)
                                                <span class="text-xs uppercase font-bold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 px-2 py-0.5 rounded mr-2">Video</span>
                                                {{ $completion->video->title }}
                                            @endif
                                        </h4>
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-1.5 ml-6">Completed {{ $completion->updated_at->diffForHumans() }}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        @if($completion->task)
                                            <a href="{{ route('training.show', ['type' => 'task', 'id' => $completion->task->id, 'session' => $completion->cleaning_session_id ?? $completion->inferred_session_id]) }}" class="text-green-700 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">Review</a>
                                        @elseif($completion->video)
                                            <a href="{{ route('training.show', ['type' => 'video', 'id' => $completion->video->id, 'session' => $completion->cleaning_session_id ?? $completion->inferred_session_id]) }}" class="text-green-700 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">Review</a>
                                        @endif
                                        
                                        @php
                                            $targetSessionId = $completion->cleaning_session_id ?? $completion->inferred_session_id;
                                        @endphp
                                        
                                        @if($targetSessionId)
                                            <a href="{{ route('sessions.show', $targetSessionId) }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-700 text-green-700 dark:text-green-400 rounded hover:bg-green-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors shadow-sm">Session &rarr;</a>
                                        @else
                                            <a href="{{ route('dashboard') }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-700 text-green-700 dark:text-green-400 rounded hover:bg-green-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors shadow-sm">Dashboard &rarr;</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

<x-app-layout>
    @php
        $dateFormat = \App\Models\Setting::get('date_format', 'M d, Y');
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-lg sm:text-xl font-semibold">My Assignments</h2>
            
            @if(!auth()->user()->hasRole('housekeeper'))
                @if(!isset($pastJobs))
                    <a href="{{ route('sessions.index', ['past' => 1]) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        View Past Jobs
                    </a>
                @else
                    <a href="{{ route('sessions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        View Current Jobs
                    </a>
                @endif
            @endif
        </div>
    </x-slot>

    @if(isset($pastJobs))
        <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Past Jobs</h3>
        @include('sessions._jobs_list', ['jobs' => $pastJobs, 'isPast' => true])
        <div class="mt-4">{{ $pastJobs->links() }}</div>
    @else
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Current Jobs</h3>
            @if($currentJobs->isEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                    No current jobs
                </div>
            @else
                @include('sessions._jobs_list', ['jobs' => $currentJobs, 'isCurrent' => true])
            @endif
        </div>

        <div>
            <div class="my-8 border-t-2 border-gray-300 dark:border-gray-600"></div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Upcoming Jobs</h3>
            @if($upcomingJobs->isEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                    No upcoming jobs scheduled
                </div>
            @else
                @include('sessions._jobs_list', ['jobs' => $upcomingJobs, 'isCurrent' => false])
            @endif
        </div>
    @endif
</x-app-layout>

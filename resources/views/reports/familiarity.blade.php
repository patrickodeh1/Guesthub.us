<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Cleaner Instruction Familiarity Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('reports.familiarity.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <x-form.label for="cleaner_id" value="Cleaner" />
                        <select id="cleaner_id" name="cleaner_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm">
                            <option value="">All Cleaners</option>
                            @foreach($cleaners as $cleaner)
                                <option value="{{ $cleaner->id }}" {{ request('cleaner_id') == $cleaner->id ? 'selected' : '' }}>
                                    {{ $cleaner->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-form.label for="status" value="Familiarity Status" />
                        <select id="status" name="status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm">
                            <option value="">All Statuses</option>
                            <option value="learning" {{ request('status') === 'learning' ? 'selected' : '' }}>Learning (Views Required)</option>
                            <option value="familiar" {{ request('status') === 'familiar' ? 'selected' : '' }}>Familiar (Requirement Met)</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-2">
                        <x-button type="submit">Filter</x-button>
                        <x-button type="button" variant="secondary" onclick="window.location='{{ route('reports.familiarity.index') }}'">Clear</x-button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-4 font-medium">Cleaner</th>
                                <th class="px-6 py-4 font-medium">Task</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium">Progress</th>
                                <th class="px-6 py-4 font-medium">Views</th>
                                <th class="px-6 py-4 font-medium text-right">Last Viewed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($familiarities as $fam)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $fam->user->name ?? 'Unknown' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900 dark:text-gray-100">{{ $fam->task->name ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($fam->is_familiar)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                                Familiar
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                                Learning
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="w-full max-w-[120px] bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: {{ $fam->progress }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $fam->views_completed }} / {{ $fam->required_views }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-500 dark:text-gray-400">
                                        {{ $fam->last_viewed_at ? $fam->last_viewed_at->format('M j, Y g:i A') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">No familiarity records found</p>
                                            <p class="mt-1">Try adjusting your filters or wait for cleaners to view instructions.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if ($familiarities->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        {{ $familiarities->links() }}
                    </div>
                @endif
            </div>
            
        </div>
    </div>
</x-app-layout>

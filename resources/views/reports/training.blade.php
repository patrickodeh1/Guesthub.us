<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Pre-Arrival Training Reports') }}
            </h2>
            
            <form method="GET" action="{{ route('reports.training.index') }}" class="flex flex-wrap items-end gap-3 bg-white p-3 rounded-md shadow-sm border border-gray-200">
                <div>
                    <label for="date_from" class="block text-xs text-gray-500 mb-1">From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="text-sm border-gray-300 rounded-md py-1.5 px-2 w-36">
                </div>
                <div>
                    <label for="date_to" class="block text-xs text-gray-500 mb-1">To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="text-sm border-gray-300 rounded-md py-1.5 px-2 w-36">
                </div>
                
                <div>
                    <label for="property_id" class="block text-xs text-gray-500 mb-1">Property</label>
                    <select name="property_id" id="property_id" class="text-sm border-gray-300 rounded-md py-1.5 px-2 w-48">
                        <option value="">All Properties</option>
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="cleaner_id" class="block text-xs text-gray-500 mb-1">Cleaner</label>
                    <select name="cleaner_id" id="cleaner_id" class="text-sm border-gray-300 rounded-md py-1.5 px-2 w-48">
                        <option value="">All Cleaners</option>
                        @foreach($cleaners as $cleaner)
                            <option value="{{ $cleaner->id }}" {{ request('cleaner_id') == $cleaner->id ? 'selected' : '' }}>
                                {{ $cleaner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label for="type" class="block text-xs text-gray-500 mb-1">Requirement</label>
                    <select name="type" id="type" class="text-sm border-gray-300 rounded-md py-1.5 px-2 w-32">
                        <option value="">All Types</option>
                        <option value="mandatory" {{ request('type') == 'mandatory' ? 'selected' : '' }}>Mandatory</option>
                        <option value="optional" {{ request('type') == 'optional' ? 'selected' : '' }}>Optional</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 pb-0.5">
                    <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Filter</button>
                    <a href="{{ route('reports.training.index') }}" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-md hover:bg-gray-200">Reset</a>
                </div>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="text-sm text-gray-500 font-medium mb-1">Overall Compliance</div>
                    <div class="text-3xl font-bold {{ $compliancePercent === 100 ? 'text-green-600' : 'text-indigo-600' }}">
                        {{ $compliancePercent }}%
                    </div>
                    <div class="text-xs text-gray-400 mt-1">mandatory completion</div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="text-sm text-gray-500 font-medium mb-1">Outstanding Mandatory</div>
                    <div class="text-3xl font-bold {{ $outstandingMandatory > 0 ? 'text-red-500' : 'text-gray-900' }}">
                        {{ $outstandingMandatory }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">items currently blocking start</div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="text-sm text-gray-500 font-medium mb-1">Total Assigned (Page)</div>
                    <div class="text-3xl font-bold text-gray-900">
                        {{ $snapshots->count() }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">items currently displayed</div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="text-sm text-gray-500 font-medium mb-1">Dataset Total</div>
                    <div class="text-3xl font-bold text-gray-900">
                        {{ $totalAssigned }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">total items across pages</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cleaner</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Item</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Req</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Freq</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Time Spent</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($snapshots as $snap)
                                @php
                                    $item = $snap->task ?? $snap->video;
                                    $session = $snap->cleaningSession;
                                    $completion = $snap->completion;
                                    
                                    if (!$session || !$item) continue;
                                    
                                    $statusColor = match($completion?->status) {
                                        'completed' => 'bg-green-100 text-green-800',
                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    $statusText = match($completion?->status) {
                                        'completed' => 'Completed',
                                        'in_progress' => 'In Progress',
                                        default => 'Not Started'
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $session->property->name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($session->scheduled_date)->format('M d, Y') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $session->housekeeper->name ?? 'Unassigned' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            @if($snap->task)
                                                <span class="text-[10px] uppercase bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded mr-2 border border-gray-200">Task</span>
                                                <span class="truncate max-w-[200px]" title="{{ $item->name }}">{{ $item->name }}</span>
                                            @else
                                                <span class="text-[10px] uppercase bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded mr-2 border border-gray-200">Video</span>
                                                <span class="truncate max-w-[200px]" title="{{ $item->title }}">{{ $item->title }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($snap->is_required_before_start)
                                            <span class="text-red-600 text-xs font-bold" title="Mandatory">M</span>
                                        @else
                                            <span class="text-gray-400 text-xs" title="Optional">O</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs text-gray-500">
                                        {{ match($item->training_frequency ?? 'once_ever') {
                                            'once_ever' => 'Ever',
                                            'once_per_property' => 'Property',
                                            'once_per_assignment' => 'Session',
                                            'every_assignment' => 'Session',
                                            default => 'Ever'
                                        } }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="text-sm text-gray-900">{{ $completion ? $completion->progress : 0 }}%</div>
                                        @if($item->completion_threshold_percent && $item->completion_threshold_percent < 100)
                                            <div class="text-[10px] text-gray-500">Req: {{ $item->completion_threshold_percent }}%</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($completion && $completion->time_spent_seconds > 0)
                                            @php
                                                $mins = floor($completion->time_spent_seconds / 60);
                                                $secs = $completion->time_spent_seconds % 60;
                                            @endphp
                                            <span class="text-sm font-mono text-gray-700">{{ $mins }}m {{ $secs }}s</span>
                                        @else
                                            <span class="text-sm text-gray-400">0s</span>
                                        @endif
                                        <div class="text-[10px] text-gray-400" title="Estimated Duration">
                                            Est: {{ $item->duration_seconds ? ceil($item->duration_seconds/60).'m' : ($item->estimated_duration_minutes ? $item->estimated_duration_minutes.'m' : '--') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">
                                        {{ $completion && $completion->completed_at ? \Carbon\Carbon::parse($completion->completed_at)->format('M d, g:i A') : '--' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                        No training assignments found matching your filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($snapshots->hasPages())
                    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                        {{ $snapshots->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

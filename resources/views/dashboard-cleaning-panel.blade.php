    <div class="grid grid-cols-1 gap-6">
        <div>
            @if(isset($unscheduledCheckouts) && $unscheduledCheckouts->isNotEmpty())
                <div class="mb-6 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-orange-100 dark:bg-orange-900/40 border-b border-orange-200 dark:border-orange-800 flex items-center justify-between">
                        <div class="font-semibold text-orange-800 dark:text-orange-200 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Action Required: Unscheduled Checkouts
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-orange-50 dark:bg-orange-900/20">
                                <tr class="text-left text-orange-800 dark:text-orange-200">
                                    <th class="px-4 py-2 font-medium">Checkout Date</th>
                                    <th class="px-4 py-2 font-medium">Property</th>
                                    <th class="px-4 py-2 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-100 dark:divide-orange-800/30">
                                @foreach($unscheduledCheckouts as $checkout)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-orange-900 dark:text-orange-100">
                                            {{ \Illuminate\Support\Carbon::parse($checkout['checkout_date'])->toFormattedDateString() }}
                                            @if(!empty($checkout['is_new']))
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white shadow-sm uppercase tracking-tighter animate-pulse">
                                                    New
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-orange-900 dark:text-orange-100">{{ $checkout['property_name'] }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('manage.sessions.create', [
                                                    'property_id' => $checkout['property_id'],
                                                    'date' => $checkout['checkout_date']
                                                ]) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                                Schedule Cleaning
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>

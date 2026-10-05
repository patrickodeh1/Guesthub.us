    @php
        $hour = (int) now()->setTimezone(config('app.display_timezone'))->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $dashTourSteps = [
            ['target' => 'dashboard-hero', 'title' => 'Your dashboard', 'body' => 'A quick greeting, one-tap Add Guest, and smart lock status at a glance.'],
            ['target' => 'guests-today', 'title' => 'Today', 'body' => 'Every guest arriving or checking out today.'],
        ];
    @endphp

    <div class="card card-pad mb-5" data-tour="dashboard-hero">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-slate-950">{{ $greeting }}, {{ auth()->user()->name }} 👋</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.guests.create') }}" class="btn-primary gap-2"><x-icon name="plus" class="h-4 w-4" />Add Guest</a>
                <button type="button" id="start-dashboard-tour" class="btn-secondary text-sm">✦ Tour</button>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
            <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500"><x-icon name="lock" class="h-3.5 w-3.5 text-slate-400" />Smart Locks</span>
            @forelse($propertyLocks as $propertyName => $locks)
                @foreach($locks as $lock)
                    <span class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1 text-xs">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ is_null($lock->last_known_locked) ? 'bg-slate-300' : ($lock->last_known_locked ? 'bg-emerald-500' : 'bg-red-500') }}"></span>
                        <span class="font-semibold text-slate-950">{{ $lock->label }}</span>
                        <span class="text-slate-500">{{ $propertyName }} &middot; {{ is_null($lock->last_known_locked) ? 'Unknown' : ($lock->last_known_locked ? 'Locked' : 'Unlocked') }}</span>
                    </span>
                @endforeach
            @empty
                <span class="text-xs text-slate-500">No smart locks configured.</span>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-5">
        @includeWhen(isset($checkoutBoard), 'dashboard-checkouts')

    </div>

    <div id="dashboard-tour-data" data-steps="{{ json_encode($dashTourSteps) }}" data-complete-url="{{ route('admin.tour.dashboard.complete') }}" data-csrf="{{ csrf_token() }}" class="hidden"></div>

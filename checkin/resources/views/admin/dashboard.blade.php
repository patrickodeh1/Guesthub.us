<x-admin-layout title="Dashboard">
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
        @if($needsAttentionGuests->count() > 0)
        <section id="guests-needs-attention" class="card scroll-mt-24 overflow-hidden border-2 border-amber-200">
            <div class="flex items-center justify-between border-b border-amber-100 bg-amber-50 p-4">
                <h2 class="font-bold text-amber-900">Needs Attention</h2>
                <span class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $needsAttentionGuests->count() }} guest{{ $needsAttentionGuests->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($needsAttentionGuests as $booking)
                    <div class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50">
                        <a href="{{ route('admin.guests.show', $booking) }}" class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <img src="{{ $booking->property->heroImageUrl() }}" alt="" class="h-6 w-9 shrink-0 rounded object-cover">
                                <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $booking->property->name }}</span>
                            </div>
                            <p class="mt-1 truncate font-semibold text-slate-950">{{ $booking->guest_name }}</p>
                            <p class="truncate text-sm font-semibold text-amber-700">{{ $booking->priorityReason() }}</p>
                        </a>
                        @if($booking->phone)
                            <a href="tel:{{ \App\Support\PhoneFormatter::toTelUri($booking->phone) }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700" title="Call guest" aria-label="Call {{ $booking->guest_name }}">
                                <x-icon name="contact-guest-services" class="h-5 w-5" />
                            </a>
                        @endif
                        <a href="{{ route('admin.guests.show', $booking) }}" class="badge badge-{{ $booking->effectiveStatus() }} shrink-0">{{ $booking->statusLabel() }}</a>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        <section id="guests-today" class="card scroll-mt-24 overflow-hidden" data-tour="guests-today">
            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                <div>
                    <h2 class="font-bold text-slate-950">Today</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ \Carbon\Carbon::parse($today)->format('l, M j, Y') }}</p>
                </div>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $todayGuests->count() }} guest{{ $todayGuests->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($todayGuests as $booking)
                    <div class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50">
                        <a href="{{ route('admin.guests.show', $booking) }}" class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <img src="{{ $booking->property->heroImageUrl() }}" alt="" class="h-6 w-9 shrink-0 rounded object-cover">
                                <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $booking->property->name }}</span>
                            </div>
                            <p class="mt-1 truncate font-semibold text-slate-950">{{ $booking->guest_name }}</p>
                            <p class="truncate text-sm text-slate-600">{!! $booking->dashboardArrivalLine($today) !!}</p>
                        </a>
                        @if($booking->phone)
                            <a href="tel:{{ \App\Support\PhoneFormatter::toTelUri($booking->phone) }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700" title="Call guest" aria-label="Call {{ $booking->guest_name }}">
                                <x-icon name="contact-guest-services" class="h-5 w-5" />
                            </a>
                        @endif
                        <a href="{{ route('admin.guests.show', $booking) }}" class="badge badge-{{ $booking->effectiveStatus() }} shrink-0">{{ $booking->statusLabel() }}</a>
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">No check-ins, check-outs, or current stays today.</p>
                @endforelse
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                <h2 class="font-bold text-slate-950">Upcoming</h2>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $upcomingGuests->count() }} guest{{ $upcomingGuests->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($upcomingGuests as $booking)
                    <div class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50">
                        <a href="{{ route('admin.guests.show', $booking) }}" class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <img src="{{ $booking->property->heroImageUrl() }}" alt="" class="h-6 w-9 shrink-0 rounded object-cover">
                                <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $booking->property->name }}</span>
                            </div>
                            <p class="mt-1 truncate font-semibold text-slate-950">{{ $booking->guest_name }}</p>
                            <p class="truncate text-sm text-slate-600">{!! $booking->dashboardArrivalLine($today) !!}</p>
                        </a>
                        @if($booking->phone)
                            <a href="tel:{{ \App\Support\PhoneFormatter::toTelUri($booking->phone) }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700" title="Call guest" aria-label="Call {{ $booking->guest_name }}">
                                <x-icon name="contact-guest-services" class="h-5 w-5" />
                            </a>
                        @endif
                        <a href="{{ route('admin.guests.show', $booking) }}" class="badge badge-{{ $booking->effectiveStatus() }} shrink-0">{{ $booking->statusLabel() }}</a>
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">No upcoming arrivals yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div id="dashboard-tour-data" data-steps="{{ json_encode($dashTourSteps) }}" data-complete-url="{{ route('admin.tour.dashboard.complete') }}" data-csrf="{{ csrf_token() }}" class="hidden"></div>
</x-admin-layout>

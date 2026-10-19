@php
    use Illuminate\Support\Str;
    use App\Models\ChecklistItem;
@endphp

@php
    $cleanerLayout = ! (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']));
@endphp
<x-dynamic-component :component="$cleanerLayout ? 'cleaner-layout' : 'app-layout'">
    <div class="cleaner-skin-marker" hidden></div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">
                    {{ $session->property->name }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $session->scheduled_date->format('F j, Y') }}
                    @if($session->status === 'completed' && $session->ended_at)
                        &bull; Completed {{ $session->ended_at->diffForHumans() }}
                    @elseif($session->status === 'in_progress' && $session->started_at)
                        &bull; Started {{ $session->started_at->diffForHumans() }}
                    @elseif($session->scheduled_time)
                        at {{ \Carbon\Carbon::parse($session->scheduled_time)->format('g:i A') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-col items-end gap-1" data-gh-assignment-chip>{{-- gh-assignment-chip --}}
                @if($session->housekeeper_id && $session->status === 'pending' && $session->assignment_status === 'pending_confirmation')
                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ (auth()->check() && (int) $session->housekeeper_id === (int) auth()->id()) ? 'Awaiting your confirmation' : 'Awaiting cleaner reply' }}</span>
                @elseif($session->housekeeper_id && $session->status === 'pending' && $session->assignment_status === 'confirmed')
                    <span class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Confirmed</span>
                @elseif($session->housekeeper_id && $session->assignment_status === 'declined')
                    <span class="inline-flex items-center rounded-full border border-red-300 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800">Declined</span>
                @endif
                @if($session->status !== 'pending')
                <span data-status-badge><x-status-badge :status="$session->status" /></span>
            @endif
            </div>
        </div>
    </x-slot>

    @php
        $ghAwaitingConfirm = $cleanerLayout
            && ! request()->has('edit_report')
            && $session->status === 'pending'
            && $session->assignment_status === 'pending_confirmation';
        $ghTooEarly = $cleanerLayout
            && ! $ghAwaitingConfirm
            && ! request()->has('edit_report')
            && $session->status === 'pending'
            && ! empty($isTooEarly)
            && empty($is_admin);
    @endphp
    @if ($ghAwaitingConfirm)
        {{-- gh-confirm-gate --}}
        <div class="guest-portal-card p-6 text-center">
            @php
                $ghLogo = \App\Support\Branding::logoUrl();
                $ghSite = \App\Support\Branding::siteName();
            @endphp
            @if ($ghLogo)
                <img src="{{ $ghLogo }}" alt="{{ $ghSite }}" class="mx-auto mb-3 h-9 w-auto object-contain">
            @else
                <p class="mb-3 text-sm font-semibold text-slate-700">{{ $ghSite }}</p>
            @endif
            <p class="guest-status-kicker">Job status</p>
            <h2 class="mt-2 text-lg font-bold text-slate-900">Awaiting your confirmation</h2>
            <p class="mt-2 text-sm text-slate-600">Reply to the text message to confirm this job. The details will appear here once it is confirmed.</p>
            @php
                $ghConfirmDashUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
            @endphp
            <a href="{{ $ghConfirmDashUrl }}" id="gh-confirm-back" class="guest-outline-btn mt-4 w-full max-w-xs justify-center">Back to dashboard</a>
        </div>
    @elseif ($ghTooEarly)
        {{-- gh-too-early-gate --}}
        @php
            $ghLogo = \App\Support\Branding::logoUrl();
            $ghSite = \App\Support\Branding::siteName();
        @endphp
        <div class="guest-portal-card">
            <div class="guest-status-bar">
                <div>
                    @if ($ghLogo)
                        <img src="{{ $ghLogo }}" alt="{{ $ghSite }}" class="h-8 max-w-[140px] w-auto object-contain">
                    @endif
                </div>
                <span class="guest-status-pill">
                    <x-icon name="clock" class="h-4 w-4" />
                    Not available yet
                </span>
            </div>
            <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                <h1 class="guest-status-title">Session not available yet</h1>
                <p class="max-w-md text-sm leading-6 text-slate-600">
                    This cleaning is not yet available. Access will be granted at the scheduled start time
                    ({{ $session->scheduled_date->format('F j, Y') }}@if($session->scheduled_time) at {{ \Carbon\Carbon::parse($session->scheduled_time)->format('g:i A') }}@endif).
                </p>
                @php
                    $ghDashUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
                @endphp
                <a href="{{ $ghDashUrl }}" id="gh-too-early-back" class="guest-outline-btn mt-2 w-full max-w-xs justify-center">Back to dashboard</a>
            </div>
        </div>
    @else

@php
    $queryParams = [];
    if (request()->has('edit_report')) {
        $queryParams['edit_report'] = request()->query('edit_report');
    }
    if (request()->has('stage')) {
        $queryParams['stage'] = request()->query('stage');
    }

    $dataUrl = route('sessions.data', array_merge(['session' => $session->id], $queryParams));

    $reportUrl = \Illuminate\Support\Facades\Route::has('reports.sessions.show')
        ? route('reports.sessions.show', ['token' => $session->report_token])
        : null;

    $photoDeleteUrl = route('photos.destroy', [
        'session' => $session->id,
        'photo' => 0
    ]);
@endphp

@if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']) && request()->query('edit_report') == '1')
    <div class="bg-indigo-600 border border-indigo-700 text-white p-4 mx-4 mt-6 rounded-xl shadow-md">
        <h3 class="font-bold text-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            Report Editor
        </h3>
        <p class="text-indigo-100 text-sm mt-1">You are editing the source data for the Final Report. Any tasks you check/uncheck, notes you change, or photos you manage here will mathematically regenerate and update the Final Report.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl mb-6 mx-4 mt-4 shadow-sm overflow-hidden flex flex-wrap text-sm font-medium">
        @php
            $navStages = [
                'pre_cleaning' => 'Pre-Cleaning',
                'rooms' => 'Rooms',
                'during_cleaning' => 'Arrival / Exit Jobs',
                'post_cleaning' => 'Post Cleaning',
                'photos' => 'Photos',
                'summary' => 'Summary'
            ];
        @endphp
        @foreach($navStages as $key => $label)
            <a href="{{ route('sessions.show', ['session' => $session->id, 'edit_report' => 1, 'stage' => $key]) }}"
               class="flex-1 min-w-[120px] text-center py-3 px-4 transition-colors border-r border-b border-gray-200 dark:border-gray-700 last:border-r-0
                      {{ $stage === $key ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-b-2 border-b-indigo-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
@endif

<div
    x-data="checklist({ dataUrl: @js($dataUrl) })"
    x-init="init()"
    data-session-id="{{ $session->id }}"
    @if($reportUrl)
        data-report-url="{{ $reportUrl }}"
    @endif
    class="space-y-6"
>
        @if(!empty($is_admin) && $is_admin)
            @php
                $tagDate = $session->scheduled_date;
                $tagBookings = \App\Models\Booking::where('property_id', $session->property_id)
                    ->whereNull('cancelled_at')
                    ->whereDate('check_in_date', '<=', $tagDate->copy()->addDay())
                    ->whereDate('check_out_date', '>=', $tagDate->copy()->subDays(3))
                    ->orderBy('check_in_date')->get();
            @endphp
            <form method="post" action="{{ route('cleaning-sessions.guest.update', $session) }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-center gap-3">
                @csrf @method('put')
                <label for="tag_booking" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Guest for this cleaning</label>
                <select id="tag_booking" name="booking_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    <option value="" @selected(!$session->booking_id)>No guest</option>
                    @foreach($tagBookings as $tb)
                        <option value="{{ $tb->id }}" @selected($session->booking_id == $tb->id)>{{ $tb->guest_name }} ({{ $tb->check_in_date->format('M j') }} - {{ $tb->check_out_date->format('M j') }})</option>
                    @endforeach
                </select>
                <button class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Save</button>
                <span class="text-xs text-gray-500">Auto-tagged to the departing guest. Change it, or choose No guest, for special cases.</span>
            </form>
        @endif

        {{-- Notification Toast --}}
        <div
            x-show="success || error"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            class="fixed top-4 right-4 z-50 max-w-sm w-full"
        >
            <div
                x-show="success"
                class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 shadow-lg"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-green-800 dark:text-green-200" x-text="success"></p>
                </div>
            </div>
            <div
                x-show="error"
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 shadow-lg"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200" x-text="error"></p>
                </div>
            </div>
        </div>

        {{-- View-only notice for housekeepers --}}
        @if (isset($isTooEarly) && $isTooEarly && !$is_admin)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-medium text-red-800 dark:text-red-200">Session Not Yet Available</p>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                            This cleaning is not yet available. Access will be granted at the scheduled start time 
                            ({{ $session->scheduled_date->format('F j, Y') }} 
                            @if($session->scheduled_time) at {{ \Carbon\Carbon::parse($session->scheduled_time)->format('g:i A') }}@endif).
                        </p>
                    </div>
                </div>
            </div>
        @elseif (isset($isViewOnly) && $isViewOnly)
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium text-amber-800 dark:text-amber-200">View Only Mode</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                            This assignment is scheduled for {{ $session->scheduled_date->format('F j, Y') }}.
                            You can view the checklist, but you can only start working on the scheduled date when you're at the property location.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @include('sessions.partials.photo-tutorial')

        {{-- gh-first-screen: cleaning details, shown first to the assigned cleaner before anything else --}}
        @if ($session->status === 'pending' && auth()->check() && (int) $session->housekeeper_id === (int) auth()->id()
            && ! \App\Services\CleanerAccessSteps::locationVerified($session))
            @php
                $fsProp = $session->property;
                $fsLogo = \App\Support\Branding::logoUrl();
                $fsSite = \App\Support\Branding::siteName();
                $fsDate = $session->scheduled_date ? $session->scheduled_date->format('l, F j, Y') : 'Not set';
                $fsTime = $session->scheduled_time ? $session->scheduled_time->format('g:i A') : 'Not set';
            @endphp
            <style id="gh-first-screen-hide">#getting-in, #pending-gate { display: none !important; }</style>
            <div id="gh-first-screen" class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if ($fsLogo)
                            <img src="{{ $fsLogo }}" alt="{{ $fsSite }}" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                </div>
                <div class="p-6 text-left">
                    <p class="guest-status-kicker">Your cleaning</p>
                    <h2 class="guest-status-title">{{ $fsProp?->internal_display_name ?? $fsProp?->name }}</h2>
                    <dl class="mt-4 space-y-2 text-sm text-slate-700">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Date</dt><dd class="font-medium">{{ $fsDate }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Start time</dt><dd class="font-medium">{{ $fsTime }}</dd></div>
                    </dl>
                    <button type="button" id="gh-first-proceed" class="guest-primary-btn is-go mt-6 w-full">Proceed</button>
                    <form method="POST" action="{{ route('sessions.cancel-assignment', $session) }}" class="mt-6 border-t border-slate-200 pt-4 text-center"
                          onsubmit="return confirm('Cancel this job? It will go back to the office to be given to another cleaner.');">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-red-700 underline">I can't make this job. Cancel it</button>
                    </form>
                </div>
            </div>
            <script>
            (function () {
                var key = 'gh-first-screen-{{ $session->id }}';
                function proceed() {
                    try { sessionStorage.setItem(key, '1'); } catch (e) {}
                    var st = document.getElementById('gh-first-screen-hide'); if (st) st.remove();
                    var c = document.getElementById('gh-first-screen'); if (c) c.remove();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                var seen = false;
                try { seen = sessionStorage.getItem(key) === '1'; } catch (e) {}
                if (seen) { proceed(); return; }
                document.getElementById('gh-first-proceed').addEventListener('click', proceed);
            })();
            </script>
        @endif

        {{-- Getting in: parking question + access steps for the cleaner --}}
        @if ($session->status !== 'completed')
            @php
                $ghHideGettingIn = $session->status === 'pending'
                    && auth()->check()
                    && (int) $session->housekeeper_id === (int) auth()->id()
                    && ! \App\Services\CleanerAccessSteps::locationVerified($session)
                    && count($accessSteps ?? []) === 0;
            @endphp
            @unless ($ghHideGettingIn)
                @include('sessions.partials.getting-in')
            @endunless
            @include('sessions.partials.door')
            @include('sessions.partials.lockbox')
        @endif

        {{-- PENDING: Start gate --}}
        @if ($session->status === 'pending')
            @php
            $gateCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
            $gateWait = $gateCleaner && count($accessSteps ?? []) > 0;
        @endphp
        <div id="pending-gate" @if ($gateWait) style="display:none" @endif>
            @php
                $ghBarLogo = \App\Support\Branding::logoUrl();
                $ghBarSite = \App\Support\Branding::siteName();
            @endphp
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if ($ghBarLogo)
                            <img src="{{ $ghBarLogo }}" alt="{{ $ghBarSite }}" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-ready">
                        <x-icon name="clock" class="h-4 w-4" />
                        Ready to start
                    </span>
                </div>
                <div class="px-6 py-10 md:py-16">
                <div class="max-w-2xl mx-auto text-center">
                    @if (isset($isViewOnly) && $isViewOnly)
                        <div class="mb-6">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Session Not Available Yet</h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                This assignment is scheduled for {{ $session->scheduled_date->format('F j, Y') }}.
                                You can start working on the scheduled date when you're at the property location.
                            </p>
                        </div>
                        <x-button disabled size="lg">Start Session</x-button>
                    @else
                        <div class="mb-6">
                            {{-- gh-ready-gate --}}
                            <h1 class="guest-status-title">Ready to start</h1>
                            @if (\App\Services\CleanerAccessSteps::locationVerified($session) || (int) $session->housekeeper_id !== (int) auth()->id())
                                <p class="mt-3 text-sm leading-6 text-slate-600 mb-4">You're all set. Tap Begin when you're ready to start cleaning.</p>
                            @endif
                        </div>

                        @if ($session->gps_override_enabled && $is_admin)
                            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">
                                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                <strong>GPS Override Active</strong> - Approved by {{ $session->gpsOverrideApprovedBy?->name ?? 'Admin' }}
                            </div>
                        @endif

                        @php $locationVerified = \App\Services\CleanerAccessSteps::locationVerified($session) || (int) $session->housekeeper_id !== (int) auth()->id(); @endphp
                        @php
                            $startLocked = false; $startMsg = '';
                            if ($session->scheduled_date && $session->scheduled_time
                                && (int) $session->housekeeper_id === (int) auth()->id()
                                && ! auth()->user()->hasRole('admin')) {
                                $startTz = $session->property?->timezone ?: config('app.display_timezone');
                                $startAt = \Carbon\Carbon::parse($session->scheduled_date->toDateString().' '.$session->scheduled_time->format('H:i'), $startTz);
                                $startNow = now($startTz);
                                $startLocked = $startNow->lt($startAt->copy()->subHour()) || $startNow->gt($startAt->copy()->addHour());
                                if ($startLocked) {
                                    $opensAt = $startAt->copy()->subHour();
                                    $closesAt = $startAt->copy()->addHour();
                                    $fmtWhen = fn ($c) => ($c->isSameDay($startNow) ? 'today' : $c->format('D, M j')).' at '.$c->format('g:i A');
                                    $startMsg = $startNow->lt($opensAt)
                                        ? 'Begin is locked until 1 hour before your scheduled start ('.$startAt->format('g:i A').'). You can begin '.$fmtWhen($opensAt).'.'
                                        : 'You can no longer begin this cleaning. The start window ended '.$fmtWhen($closesAt).' (1 hour after the scheduled start). Please contact your manager.';
                                }
                            }
                        @endphp
                        @if ($locationVerified)
                        <form method="post" action="{{ route('sessions.start', $session) }}" id="gps-start">
                            @csrf
                            <x-form.input type="hidden" name="latitude" id="lat" />
                            <x-form.input type="hidden" name="longitude" id="lng" />
                            @if(!empty($requiresOnboarding) && $requiresOnboarding)
                                <button type="button" id="start-btn" class="guest-primary-btn is-go w-full" x-data @click="$dispatch('open-modal', 'onboarding-modal')" @disabled($startLocked) @if($startLocked) style="opacity:.5;cursor:not-allowed" @endif>Begin</button>
                            @else
                                <button type="submit" id="start-btn" class="guest-primary-btn is-go w-full" @disabled($startLocked) @if($startLocked) style="opacity:.5;cursor:not-allowed" @endif>Begin</button>
                            @endif
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" id="location-status"></p>
                            @if($startLocked && $startMsg)
                                <p role="status" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">{{ $startMsg }}</p>
                            @endif
                        </form>
                        @else
                        <div id="verify-location-card">
                            <p class="mt-3 text-sm leading-6 text-slate-600 mb-4">Verify you are at the property to unlock your directions and the door. Then you can start your session.</p>
                            <div class="flex justify-center" data-gh-verify-center>{{-- gh-verify-center --}}<button type="button" id="verify-loc-btn" class="guest-primary-btn is-go w-full">Verify my location</button></div>
                            <p id="verify-loc-msg" class="mt-3 text-sm text-gray-500"></p>
                        </div>
                        <script>
                        (function () {
                            var btn = document.getElementById('verify-loc-btn');
                            var msg = document.getElementById('verify-loc-msg');
                            if (!btn) return;
                            btn.addEventListener('click', function () {
                                if (!navigator.geolocation) { msg.textContent = 'Location is not available in this browser.'; return; }
                                btn.disabled = true; msg.textContent = 'Checking your location...';
                                navigator.geolocation.getCurrentPosition(function (pos) {
                                    fetch('{{ route('sessions.verify-location', $session) }}', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy })
                                    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok && d.ok, d: d }; }); })
                                      .then(function (res) {
                                        if (res.ok) { window.location.reload(); return; }
                                        btn.disabled = false; msg.textContent = (res.d && res.d.message) || 'Could not verify. Try again.';
                                      })
                                      .catch(function () { btn.disabled = false; msg.textContent = 'Network error. Try again.'; });
                                }, function () {
                                    btn.disabled = false; msg.textContent = 'Location access is required. Please allow location and try again.';
                                }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
                            });
                        })();
                        </script>
                        @endif

                        @error('gps')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($is_admin && !$session->gps_override_enabled)
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <button type="button" x-data @click="$dispatch('open-modal', 'gps-override-modal')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    Admin: Grant GPS Override
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
                </div>
            </div>
        </div>

            @if ($is_admin)
                <x-modal name="gps-override-modal" :show="false" maxWidth="md">
                    <form method="post" action="{{ route('sessions.gps-override', $session) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Grant GPS Override
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Authorize the cleaner to start this session without passing GPS verification.
                        </p>
                        <div class="mt-4">
                            <x-form.label for="reason" value="Reason for Override" />
                            <x-form.input id="reason" name="reason" type="text" class="mt-1 block w-full" required placeholder="e.g., Cleaner's phone GPS broken" />
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">
                                Cancel
                            </x-button>
                            <x-button type="submit" class="ml-3">
                                Grant Override
                            </x-button>
                        </div>
                    </form>
                </x-modal>
            @endif

            {{-- GPS capture and location verification --}}
            @if ((!isset($isViewOnly) || !$isViewOnly) && (int) $session->housekeeper_id !== (int) auth()->id()) {{-- gh-no-gps-for-cleaner --}}
                @include('sessions.partials.gps-script', [
                    'propertyLat' => $session->property->latitude,
                    'propertyLng' => $session->property->longitude,
                    'propertyRadius' => \App\Support\GpsRadius::base(),
                    'overrideEnabled' => $session->gps_override_enabled
                ])
            @endif
        @else
            {{-- PROGRESS HEADER --}}
            @php
                $ghHdrLogo = \App\Support\Branding::logoUrl();
                $ghHdrSite = \App\Support\Branding::siteName();
            @endphp
            <div class="guest-portal-card" id="gh-header-card">
                <div class="guest-status-bar">
                    <div>
                        @if ($ghHdrLogo)
                            <img src="{{ $ghHdrLogo }}" alt="{{ $ghHdrSite }}" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span data-status-badge><x-status-badge :status="$session->status" /></span>
                </div>

                <div class="p-6">
                    <div class="guest-stay-grid">
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon">
                                <x-icon name="calendar" class="h-5 w-5" />
                            </div>
                            <p class="guest-stay-tile-label">Started</p>
                            <p class="guest-stay-tile-date">{{ optional($session->started_at)->format('M j, Y') ?? '—' }}</p>
                            @if ($session->started_at)
                                <p class="mt-0.5 text-xs text-slate-500">{{ $session->started_at->format('g:i A') }}</p>
                            @endif
                        </div>
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon">
                                <x-icon name="{{ $session->gps_confirmed_at ? 'check' : 'clock' }}" class="h-5 w-5" />
                            </div>
                            <p class="guest-stay-tile-label">Location</p>
                            <p class="guest-stay-tile-date {{ $session->gps_confirmed_at ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $session->gps_confirmed_at ? 'GPS confirmed' : 'Not confirmed' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5">
                        <div class="flex items-center gap-2">
                            <span class="guest-status-kicker">Current stage</span>
                            <span data-stage-area>
                                @if ($stage === 'summary')
                                    <span class="guest-status-pill is-checked">Completed</span>
                                @else
                                    <span class="guest-status-pill is-ready">{{ ucwords(str_replace('_', ' ', $stage)) }}</span>
                                @endif
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if($reportUrl && ($stage === 'summary' || (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']))))
                                <a href="{{ $reportUrl }}" id="live-report-btn" target="_blank" rel="noopener noreferrer" class="guest-outline-btn">
                                    {{ $stage === 'summary' ? 'View Report' : 'View Live Report' }}
                                </a>
                            @endif

                            @if ($is_admin && $session->status !== 'completed')
                                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'admin-close-modal' }))" class="guest-outline-btn">
                                    Close Session
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- gh-screens: details card hidden on the photo prompt and exit wizard; prompt gets the logo and Go back below --}}
            <script>
            (function () {
                var logo = @js($ghHdrLogo ?? null), site = @js($ghHdrSite ?? '');
                setInterval(function () {
                    var hdr = document.getElementById('gh-header-card');
                    var box = document.getElementById('checklist-container');
                    var panel = document.getElementById('exit-wizard-panel');
                    var exitOn = !!panel && panel.style.display !== 'none';
                    var wrap = box && box.firstElementChild;
                    var promptOn = !!box && box.textContent.indexOf('before the report is closed') !== -1
                        && !!wrap && wrap.children.length >= 2 && box.offsetParent !== null;
                    if (hdr) hdr.style.display = (exitOn || promptOn) ? 'none' : '';
                    if (!promptOn) return;
                    wrap.style.display = 'flex'; wrap.style.flexDirection = 'column'; wrap.style.gap = '1.5rem';
                    wrap.children[0].style.order = '2';
                    wrap.children[1].style.order = '1';
                    if (logo && !wrap.querySelector('.gh-prompt-logo')) {
                        var d = document.createElement('div');
                        d.className = 'gh-prompt-logo mb-5 flex justify-center';
                        d.innerHTML = '<img src="' + logo + '" alt="' + site + '" class="h-8 max-w-[140px] w-auto object-contain">';
                        wrap.children[1].insertBefore(d, wrap.children[1].firstChild);
                    }
                }, 250);
            })();
            </script>
            {{-- gh-summary-popup: the summary stage is shown as a popup for unfinished sessions --}}
            <script>
            (function () {
                setInterval(function () {
                    var box = document.getElementById('checklist-container');
                    var wrap = box && box.firstElementChild;
                    var card = wrap && wrap.firstElementChild;
                    if (!wrap || !card) return;
                    var on = box.textContent.indexOf('All Stages Complete!') !== -1;
                    if (on) {
                        wrap.style.cssText = 'position:fixed;inset:0;z-index:50;background:rgba(15,23,42,.7);display:flex;align-items:flex-start;justify-content:center;padding:.75rem;overflow-y:auto';
                        card.style.cssText = 'width:100%;max-width:32rem;margin:auto;max-height:94vh;overflow-y:auto;padding:1.25rem';
                        wrap.dataset.ghPopup = '1';
                    } else if (wrap.dataset.ghPopup) {
                        wrap.removeAttribute('style'); card.removeAttribute('style'); delete wrap.dataset.ghPopup;
                    }
                }, 250);
            })();
            </script>
            {{-- Exit wizard: shown under the header card when the cleaner finishes --}}
            @php
                $exitWiz = \App\Services\CleanerAccessSteps::exitForSession($session);
                $exitIsCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
            @endphp
            @if (($exitIsCleaner || auth()->user()?->hasRole('admin')) && $session->status === 'in_progress' && count($exitWiz) > 0)
                <div id="exit-wizard-panel" style="display:none" class="mb-4">
                    <x-step-wizard :steps="$exitWiz" type="exit" kicker="Before you leave" done-label="Complete session" next-section="exit-wizard-panel" />
                    <div class="mt-3 text-center"><button type="button" id="exit-wizard-back" class="guest-outline-btn">Back to photos</button></div>
                </div>
                <div id="exit-confirm-modal" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3">
                    <div class="guest-portal-card w-full max-w-sm p-6 text-center">
                        <p class="guest-status-kicker">Complete session</p>
                        <h2 class="guest-status-title">Are you finished?</h2>
                        <p class="mt-2 text-sm text-slate-600">Confirm that every task is done and you are ready to leave.</p>
                        <div class="mt-5 flex gap-3">
                            <button type="button" id="exit-confirm-cancel" class="guest-outline-btn flex-1">Cancel</button>
                            <button type="button" id="exit-confirm-ok" class="guest-primary-btn is-go flex-1">Complete session</button>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    function renderer() { return document.querySelector('[x-data*="checklistRenderer"]'); }
                    function panel() { return document.getElementById('exit-wizard-panel'); }
                    function modal() { return document.getElementById('exit-confirm-modal'); }
                    window.ghShowExitWizard = function () {
                        var r = renderer(), p = panel(); if (!r || !p) return;
                        r.style.display = 'none'; p.style.display = '';
                        var root = document.getElementById('step-wizard-exit'); if (root) root.style.display = '';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    };
                    function hideWizard() { var r = renderer(), p = panel(); if (r) r.style.display = ''; if (p) p.style.display = 'none'; }
                    document.addEventListener('click', function (e) {
                        var t = e.target;
                        if (t.closest('#exit-wizard-back')) { hideWizard(); return; }
                        if (t.closest('#exit-confirm-cancel')) { modal().style.display = 'none'; return; }
                        if (t.closest('#exit-confirm-ok')) {
                            modal().style.display = 'none';
                            hideWizard();
                            var r = renderer(); if (r && r._x_dataStack) { r._x_dataStack[0]._exitAcked = true; }
                            if (window.checklistHandler) window.checklistHandler.saveProgress();
                            return;
                        }
                        if (t.closest('#wizard-done-exit') && panel() && panel().contains(t)) {
                            e.preventDefault(); e.stopImmediatePropagation();
                            hideWizard();
                            var r2 = renderer(); if (r2 && r2._x_dataStack) { r2._x_dataStack[0]._exitAcked = true; }
                            if (window.checklistHandler) window.checklistHandler.saveProgress();
                            return;
                        }
                    }, true);
                })();
                </script>
            @endif

            {{-- Checklist Container - Rendered by JavaScript --}}
            @php
                $queryParams2 = [];
                if (request()->has('edit_report')) {
                    $queryParams2['edit_report'] = request()->query('edit_report');
                }
                if (request()->has('stage')) {
                    $queryParams2['stage'] = request()->query('stage');
                }
                $dataUrl = route('sessions.data', array_merge(['session' => $session->id], $queryParams2));
                $photoDeleteUrl = route('photos.destroy', ['session' => $session->id, 'photo' => 0]);
                $photoDeleteUrl = str_replace('/0', '/{photo}', $photoDeleteUrl);
            @endphp
            <div x-data="checklistRenderer({ dataUrl: @js($dataUrl), photoDeleteUrl: @js($photoDeleteUrl) })" x-init="init()" class="space-y-6">
                {{-- Loading State --}}
                <div x-show="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading checklist...</p>
                </div>

                {{-- Error State --}}
                <div x-show="error" x-cloak class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">Unable to Load Checklist</h3>
                        <p class="text-sm text-red-700 dark:text-red-300 mb-4" x-text="error"></p>
                        <button type="button"
                                @click="loadSessionData()"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium text-sm">
                            Try Again
                        </button>
                    </div>
                </div>

                {{-- Checklist Content - Dynamically Rendered --}}
                <div id="checklist-container" x-show="!loading && !error" x-html="renderedContent">
                    
                    {{-- Content will be rendered here by JavaScript --}}
                </div>
                <div>
                    <button @click="saveProgress" id="stepBtn" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium hidden">
                        Submit
                    </button>
                </div>

                {{-- Persistent Instructions Floating Trigger & Popup --}}
                <template x-if="sessionData && sessionData.session && sessionData.session.status === 'in_progress'">
                    <div>
                        <!-- Resources Menu -->
                        <x-resources-menu :property-id="$session->property_id" />

                        <!-- Floating Help Trigger -->
                        <div class="fixed bottom-6 right-6 z-45">
                            <button 
                                type="button"
                                @click="instructionsOpen = true; loadInstructions()"
                                class="flex items-center justify-center w-14 h-14 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 hover:scale-110 active:scale-95 group relative"
                                aria-label="View Instructions"
                            >
                                <!-- Info / Help Icon (Question Mark Inside Circle) -->
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <!-- Hover Tooltip -->
                                <span class="absolute bottom-full right-0 mb-2 hidden group-hover:block bg-gray-900 dark:bg-gray-700 text-white text-xs font-semibold px-2 py-1 rounded shadow-md whitespace-nowrap">
                                    View Instructions
                                </span>
                            </button>
                        </div>

                        <!-- Instructions Popup Modal -->
                        <div 
                            x-show="instructionsOpen"
                            x-cloak
                            class="fixed inset-0 z-[250] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                            @click.self="instructionsOpen = false"
                            @keydown.escape.window="instructionsOpen = false"
                        >
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col transition-all duration-200">
                                <!-- Modal Header -->
                                <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between rounded-t-xl z-10 flex-shrink-0">
                                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <span class="min-w-0 break-words max-w-[280px] sm:max-w-xs" x-text="instructionsData?.task ? (activeRoomId ? (sessionData?.rooms.find(r => r.id === activeRoomId)?.name + ' - ') : 'Property - ') + instructionsData.task.name : 'Instructions'"></span>
                                    </h3>
                                    <button type="button" @click="instructionsOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>

                                <!-- Modal Body -->
                                <div class="p-6 overflow-y-auto space-y-6 flex-1">
                                    <!-- Navigation/Selection Controls -->
                                    <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200/50 dark:border-gray-700/50">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Room / Section</label>
                                            <select 
                                                @change="
                                                    const selectedVal = $el.value;
                                                    if (selectedVal === 'property') {
                                                        activeRoomId = null;
                                                    } else {
                                                        activeRoomId = parseInt(selectedVal);
                                                    }
                                                    const tasks = getTasksForSelector();
                                                    if (tasks.length > 0) {
                                                        activeTaskId = tasks[0].id;
                                                    } else {
                                                        activeTaskId = null;
                                                    }
                                                    loadInstructions();
                                                "
                                                class="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-2 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                                <option value="property" :selected="activeRoomId === null">Property-Level</option>
                                                <template x-for="r in sessionData?.rooms" :key="r.id">
                                                    <option :value="r.id" :selected="activeRoomId === r.id" x-text="r.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Task Name</label>
                                            <select 
                                                @change="
                                                    activeTaskId = parseInt($el.value);
                                                    loadInstructions();
                                                "
                                                class="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-2 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                                <template x-for="t in getTasksForSelector()" :key="t.id">
                                                    <option :value="t.id" :selected="activeTaskId === t.id" x-text="t.name"></option>
                                                </template>
                                                <template x-if="getTasksForSelector().length === 0">
                                                    <option value="" disabled selected>No tasks available</option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Loading State -->
                                    <div x-show="instructionsLoading" class="text-center py-8">
                                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Loading instructions...</p>
                                    </div>

                                    <!-- Error State -->
                                    <div x-show="!instructionsLoading && instructionsError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <div>
                                                <p class="text-sm font-semibold text-red-800 dark:text-red-200">Failed to load</p>
                                                <p class="text-xs text-red-700 dark:text-red-300 mt-0.5" x-text="instructionsError"></p>
                                                <button type="button" @click="loadInstructions()" class="mt-2 text-xs font-bold text-red-800 dark:text-red-200 underline">Try Again</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content Section -->
                                    <div x-show="!instructionsLoading && !instructionsError" class="space-y-6">
                                        <!-- A) Current Task Instructions -->
                                        <div class="bg-indigo-50/50 dark:bg-indigo-950/10 border border-indigo-100 dark:border-indigo-900/30 rounded-xl p-4 sm:p-5">
                                            <h4 class="text-sm font-bold text-indigo-900 dark:text-indigo-200 mb-2.5 flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                                Task Guidelines
                                            </h4>
                                            <template x-if="instructionsData?.task?.instructions">
                                                <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed break-words whitespace-pre-line" x-html="formatInstructionsText(instructionsData.task.instructions)"></div>
                                            </template>
                                            <template x-if="!instructionsData?.task?.instructions">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">No instructions available for this task.</p>
                                            </template>

                                            <!-- Media examples -->
                                            <template x-if="instructionsData?.task?.media && instructionsData.task.media.length > 0">
                                                <div class="mt-4 border-t border-indigo-100/50 dark:border-indigo-900/30 pt-4">
                                                    <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Reference Media</div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <template x-for="media in instructionsData.task.media" :key="media.id">
                                                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                                                <template x-if="media.type === 'image'">
                                                                    <button type="button" @click="$dispatch('open-gallery', { src: media.url })" class="block w-full">
                                                                        <img :src="media.thumbnail || media.url" class="w-full aspect-video object-cover hover:scale-105 transition-transform" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\' viewBox=\'0 0 400 300\'%3E%3Crect width=\'100%25\' height=\'100%25\' fill=\'%23f1f5f9\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'14\' fill=\'%2364748b\'%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                                                    </button>
                                                                </template>
                                                                <template x-if="media.type === 'video'">
                                                                    <video :src="media.url" class="w-full aspect-video object-cover" controls playsinline></video>
                                                                </template>
                                                                <template x-if="media.caption">
                                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 text-center p-1 min-w-0 break-words" x-text="media.caption"></p>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- B) General Room Instructions -->
                                        <template x-if="activeRoomId !== null && instructionsData?.room_instructions && instructionsData.room_instructions.length > 0">
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                    General Room Guidelines
                                                </h4>
                                                <div class="space-y-2">
                                                    <template x-for="ri in instructionsData.room_instructions" :key="ri.task_name">
                                                        <div class="bg-blue-50/30 dark:bg-blue-950/5 border border-blue-100/50 dark:border-blue-900/10 rounded-xl p-4">
                                                            <div class="text-xs font-bold text-blue-600 dark:text-blue-400 mb-1" x-text="ri.task_name"></div>
                                                            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line" x-html="formatInstructionsText(ri.instructions)"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- C) Property-Level Instructions -->
                                        <template x-if="instructionsData?.property_instructions && instructionsData.property_instructions.length > 0">
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    General Property Guidelines
                                                </h4>
                                                <div class="space-y-2">
                                                    <template x-for="pi in instructionsData.property_instructions" :key="pi.task_name">
                                                        <div class="bg-emerald-50/30 dark:bg-emerald-950/5 border border-emerald-100/50 dark:border-emerald-900/10 rounded-xl p-4">
                                                            <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mb-1" x-text="pi.task_name"></div>
                                                            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line" x-html="formatInstructionsText(pi.instructions)"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <!-- Empty State Fallback -->
                                        <template x-if="!instructionsData?.task?.instructions && (!instructionsData?.room_instructions || instructionsData.room_instructions.length === 0) && (!instructionsData?.property_instructions || instructionsData.property_instructions.length === 0)">
                                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <p class="text-sm">No instructions available for this view.</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Modal Footer -->
                                <div class="sticky bottom-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-4 rounded-b-xl flex-shrink-0">
                                    <button type="button" @click="instructionsOpen = false"
                                            class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 text-white rounded-lg transition-colors font-semibold text-sm">
                                        Got it
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>

@if ($is_admin && $session->status !== 'completed')
    <x-modal name="admin-close-modal" :show="false" maxWidth="md">
        <form method="post" action="{{ route('sessions.admin-close', $session) }}" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Close Session Manually
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Are you sure you want to close this session? This will override any incomplete tasks or missing photos.
            </p>

            <div class="mt-4">
                <label for="admin_close_note" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Reason for closing</label>
                <textarea id="admin_close_note" name="note" class="mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block w-full" rows="3" required placeholder="Enter a reason (e.g., Cleaner left early, tested system, etc.)"></textarea>
                @error('note')
                    <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    Close Session
                </button>
            </div>
        </form>
    </x-modal>
@endif

{{-- Mandatory Resource Onboarding Modal --}}
@include('sessions.partials.onboarding-modal')

{{-- Task-Level Training Requirement Modal --}}
@include('sessions.partials.training-modal')
@endif
</x-dynamic-component>

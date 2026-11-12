<x-admin-layout title="Dashboard">
    <style>.page-shell { max-width: none; }</style>

    @if(auth()->user()->hasRole('admin') && ($pendingSignups ?? 0) > 0)
        <div class="mb-4">
            <a href="{{ route('admin.signup-requests.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3M9 12h.01M15 12h.01M9 16h6M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $pendingSignups }} pending signup {{ Str::plural('request', $pendingSignups) }} — review now
            </a>
        </div>
    @endif



    @if($canSeeGuestPortal ?? false)
        @include('admin.dashboard')
    @endif

    @if($canSeeCleaning ?? false)
        @include('dashboard-cleaning-panel')
    @elseif(!($canSeeGuestPortal ?? false))
        <section class="card card-pad">
            <h2 class="font-semibold text-slate-950">Welcome, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">Choose an available feature from the navigation.</p>
        </section>
    @endif
</x-admin-layout>

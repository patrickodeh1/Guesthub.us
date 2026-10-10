<x-admin-layout title="Dashboard">
    <style>.page-shell { max-width: none; }</style>


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

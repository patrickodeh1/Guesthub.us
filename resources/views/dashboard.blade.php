<x-admin-layout title="Dashboard">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Overview</p>
            <h1 class="page-title">Dashboard</h1>
        </div>
    </div>

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

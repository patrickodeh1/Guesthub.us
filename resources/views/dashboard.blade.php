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
    @elseif($recentActivity->isEmpty())
        <section class="card card-pad">
            <h2 class="font-semibold text-slate-950">Welcome, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">Choose an available feature from the navigation.</p>
        </section>
    @endif

    @if($recentActivity->isNotEmpty())
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-100 p-4">
                <h2 class="font-bold text-slate-950">Recent Activity</h2>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach($recentActivity as $activity)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $activity->description }}</p>
                            <p class="text-xs text-slate-500">{{ $activity->actor_name ?: ucfirst($activity->actor_type) }} · {{ $activity->occurred_at }}</p>
                        </div>
                        <span class="text-xs text-slate-500">{{ ucfirst($activity->module ?: $activity->source) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-admin-layout>

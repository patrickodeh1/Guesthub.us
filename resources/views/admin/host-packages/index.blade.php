<x-admin-layout title="Host billing">
    <div class="page-header">
        <div>
            <p class="eyebrow">Packages</p>
            <h1 class="page-title">Host billing</h1>
            <p class="page-subtitle">Choose each host's package and add-on features. Nothing is charged yet.</p>
        </div>
    </div>

    <form method="get" class="card card-pad mb-6 flex flex-wrap items-end gap-3">
        <label class="field-label">Search by name or email<input name="q" value="{{ $q }}" class="input"></label>
        <button class="btn-secondary">Search</button>
    </form>

    <div class="card card-pad overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500"><th class="py-2">Name</th><th>Email</th><th>Package</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    @php($plan = $plans->get($u->id))
                    <tr class="border-t">
                        <td class="py-2 font-medium">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $plan?->package?->name ?? '—' }}</td>
                        <td>
                            @if($plan)
                                <span class="capitalize">{{ $plan->status }}</span>
                                @if($plan->status === 'trialing' && $plan->trial_ends_at)<span class="text-xs text-slate-400">until {{ $plan->trial_ends_at->format('M j') }}</span>@endif
                            @else — @endif
                        </td>
                        <td class="text-right"><a class="underline" href="{{ route('admin.host-packages.edit', $u) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-admin-layout>

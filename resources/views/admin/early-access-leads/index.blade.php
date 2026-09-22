<x-admin-layout title="Early Access Signups">
    <div class="page-header">
        <div>
            <p class="eyebrow">Marketing</p>
            <h2 class="page-title">Early Access Signups</h2>
            <p class="page-subtitle">People who requested early access from the public landing page.</p>
        </div>
    </div>

    <div class="card card-pad">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="pb-3 font-semibold">Name</th>
                    <th class="pb-3 font-semibold">Role</th>
                    <th class="pb-3 font-semibold">Email</th>
                    <th class="pb-3 font-semibold">Phone</th>
                    <th class="pb-3 font-semibold">Message</th>
                    <th class="pb-3 font-semibold">Received</th>
                    <th class="pb-3 font-semibold">Status</th>
                    <th class="pb-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr class="border-b border-slate-50">
                        <td class="py-3 font-semibold text-slate-950">{{ $lead->name }}</td>
                        <td class="py-3 capitalize">{{ $lead->role }}</td>
                        <td class="py-3"><a href="mailto:{{ $lead->email }}" class="text-teal-800">{{ $lead->email }}</a></td>
                        <td class="py-3">{{ \App\Support\PhoneFormatter::format($lead->phone) ?: '-' }}</td>
                        <td class="py-3 max-w-xs truncate" title="{{ $lead->message }}">{{ $lead->message ?: '-' }}</td>
                        <td class="py-3 text-slate-500">{{ $lead->created_at->copy()->setTimezone(config('app.display_timezone'))->format('M j, Y g:i A') }}</td>
                        <td class="py-3">
                            @if($lead->contacted_at)
                                <span class="badge badge-active">Contacted</span>
                            @else
                                <span class="badge badge-pending">New</span>
                            @endif
                        </td>
                        <td class="py-3">
                            @unless($lead->contacted_at)
                                <form method="post" action="{{ route('admin.early-access-leads.mark-contacted', $lead) }}">
                                    @csrf
                                    <button class="text-sm font-semibold text-teal-800">Mark contacted</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">No signups yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $leads->links() }}</div>
    </div>
</x-admin-layout>

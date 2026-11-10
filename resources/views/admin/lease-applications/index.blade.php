<x-admin-layout title="Lease applications">
    <div class="mx-auto max-w-7xl px-4 py-8">
        <h1 class="text-2xl font-semibold">Lease applications</h1>
        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Applicant</th><th class="p-3">Listing</th><th class="p-3">Status</th><th class="p-3">Fee</th><th class="p-3">Submitted</th></tr></thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr class="border-t">
                            <td class="p-3"><a class="underline" href="{{ route('admin.lease-applications.show', $application) }}">{{ $application->applicant_name }}</a></td>
                            <td class="p-3">{{ $application->listing->public_title }}</td>
                            <td class="p-3">{{ str_replace('_', ' ', $application->status) }}</td>
                            <td class="p-3">{{ ucfirst($application->fee_status) }}</td>
                            <td class="p-3">{{ $application->submitted_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="5">No lease applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $applications->links() }}</div>
        </div>
    </div>
</x-admin-layout>

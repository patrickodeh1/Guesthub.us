<x-admin-layout title="Lease application">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a class="underline" href="{{ route('admin.lease-applications.index') }}">← Lease applications</a>
        <h1 class="mt-4 text-2xl font-semibold">{{ $application->applicant_name }}</h1>
        <p>{{ $application->listing->public_title }} · {{ str_replace('_', ' ', $application->status) }} · Fee {{ $application->fee_status }}</p>

        @if(session('success'))<p class="my-4 rounded bg-green-50 p-3">{{ session('success') }}</p>@endif
        @if($application->id_scan_status === 'manual_review')
            <p class="my-4 rounded bg-amber-50 p-3">The ID scan could not be read after two attempts and requires manual review.</p>
        @endif

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <section class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-lg font-semibold">Identity and contact</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="font-medium">Email</dt><dd>{{ $application->applicant_email }}</dd></div>
                    <div><dt class="font-medium">Phone</dt><dd>{{ $application->applicant_phone }}</dd></div>
                    <div><dt class="font-medium">ID name</dt><dd>{{ $application->id_name ?: 'Not read' }}</dd></div>
                    <div><dt class="font-medium">ID address</dt><dd>{{ $application->id_address ?: 'Not read' }}</dd></div>
                    <div><dt class="font-medium">ID number</dt><dd>{{ $application->id_number_last4 ? '•••• '.$application->id_number_last4 : 'Not read' }}</dd></div>
                    <div><dt class="font-medium">Current address</dt><dd>{{ $application->current_address }}</dd></div>
                </dl>
                @if($application->id_scan_path)
                    <a class="mt-4 inline-block underline" href="{{ route('admin.lease-applications.id', $application) }}">View ID front</a>
                    @if($application->id_back_scan_path)
                        <a class="mt-4 ml-4 inline-block underline" href="{{ route('admin.lease-applications.id', $application) }}?side=back">View ID back</a>
                    @endif
                @endif
            </section>
            <section class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-lg font-semibold">Move-in and household</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="font-medium">Move-in</dt><dd>{{ $application->move_in_date?->format('M j, Y') }}</dd></div>
                    <div><dt class="font-medium">Lease term</dt><dd>{{ $application->lease_term_months }} months</dd></div>
                    <div><dt class="font-medium">Occupants</dt><dd>{{ $application->occupants }}</dd></div>
                    <div><dt class="font-medium">Pets</dt><dd>{{ $application->pets ?: 'None listed' }}</dd></div>
                    <div><dt class="font-medium">Time at current address</dt><dd>{{ $application->current_address_months }} months</dd></div>
                </dl>
            </section>
            <section class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-lg font-semibold">Employment and references</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="font-medium">Employer</dt><dd>{{ $application->employer }} · {{ $application->job_title }}</dd></div>
                    <div><dt class="font-medium">Supervisor</dt><dd>{{ $application->supervisor_name }} {{ $application->supervisor_phone }}</dd></div>
                    <div><dt class="font-medium">Monthly income</dt><dd>${{ number_format((int) $application->monthly_income_cents / 100, 2) }}</dd></div>
                    <div><dt class="font-medium">Years employed</dt><dd>{{ $application->years_employed }}</dd></div>
                    <div><dt class="font-medium">Previous landlord</dt><dd>{{ $application->previous_landlord_name }} {{ $application->previous_landlord_phone }}</dd></div>
                    <div><dt class="font-medium">Emergency contact</dt><dd>{{ $application->emergency_contact_name }} {{ $application->emergency_contact_phone }}</dd></div>
                </dl>
            </section>
            <section class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-lg font-semibold">Consent and fee</h2>
                <p>Signed {{ $application->consent_at?->format('M j, Y g:i A') }} by {{ $application->consent_signature }}</p>
                <p>Fee ${{ number_format($application->total_fee_cents / 100, 2) }} · {{ $application->fee_status }} ({{ $application->payment_method }})</p>
                @if($application->payment_method === 'manual' && $application->fee_status === 'pending')
                    <form method="post" action="{{ route('admin.lease-applications.fee-received', $application) }}" class="mt-3">
                        @csrf
                        <button class="rounded bg-[var(--button-primary-color)] px-4 py-2 text-[var(--on-primary)] hover:bg-[var(--button-primary-hover)]">Mark manual fee received</button>
                    </form>
                @endif
            </section>
        </div>

        <form method="post" action="{{ route('admin.lease-applications.update', $application) }}" class="mt-6 rounded-lg bg-white p-5 shadow">
            @csrf
            @method('put')
            <h2 class="text-lg font-semibold">Review</h2>
            <div class="mt-3">
                <label for="status" class="block font-medium">Status</label>
                <select id="status" name="status" class="mt-1 rounded border p-2">
                    @foreach(['in_review' => 'In review', 'approved' => 'Approved', 'declined' => 'Declined'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $application->status === 'submitted' ? 'in_review' : $application->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="mt-3">
                <label for="host_notes" class="block font-medium">Private notes</label>
                <textarea id="host_notes" name="host_notes" rows="4" class="mt-1 w-full rounded border p-2">{{ old('host_notes', $application->host_notes) }}</textarea>
                @error('host_notes')<p class="text-red-700">{{ $message }}</p>@enderror
            </div>
            <button class="mt-3 rounded bg-[var(--button-primary-color)] px-4 py-2 text-[var(--on-primary)] hover:bg-[var(--button-primary-hover)]">Save review</button>
        </form>
    </div>
</x-admin-layout>

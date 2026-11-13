<x-admin-layout title="Lease application">
    @php
        $money = fn ($c) => '$'.number_format(((int) $c) / 100, 2);
        $sides = array_filter(['front' => $application->id_scan_path, 'back' => $application->id_back_scan_path]);
        $allApproved = count($sides) > 0 && collect($sides)->keys()->every(fn ($s) => $application->{'id_'.$s.'_approved_at'});
        $statusLabel = str_replace('_', ' ', $application->status);
        $statusClass = match ($application->status) {
            'approved' => 'bg-emerald-100 text-emerald-800',
            'declined' => 'bg-red-100 text-red-800',
            default => 'bg-amber-100 text-amber-800',
        };
        $feeClass = $application->fee_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($application->fee_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700');
        $card = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm';
        $row = 'flex justify-between gap-4 border-b border-slate-100 py-2 text-sm last:border-0';
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8">
        <a class="text-sm text-slate-500 underline" href="{{ route('admin.lease-applications.index') }}">← Lease applications</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ $application->applicant_name }}</h1>
                <p class="text-sm text-slate-500">{{ $application->listing->public_title }} · Application #{{ $application->id }} · Submitted {{ $application->submitted_at?->format('M j, Y g:i A') }}</p>
            </div>
            <div class="flex gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $statusClass }}">{{ $statusLabel }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $feeClass }}">Fee {{ $application->fee_status }}</span>
            </div>
        </div>

        @if(session('success'))<p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
        @if($application->id_scan_status === 'manual_review')
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">The ID could not be read automatically. Check the images below against the applicant's details.</p>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                {{-- ID verification --}}
                <section class="{{ $card }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">ID verification @if($application->id_type === 'passport')<span class="text-sm font-normal text-slate-500">(passport)</span>@endif</h2>
                        @if($allApproved)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">ID fully approved</span>
                        @elseif(count($sides))<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Awaiting review</span>@endif
                    </div>

                    @if(! count($sides))
                        <p class="mt-3 text-sm text-slate-500">No ID image on file.</p>
                    @else
                        <div class="mt-4 grid gap-4 {{ count($sides) > 1 ? 'sm:grid-cols-2' : '' }}">
                            @foreach($sides as $side => $path)
                                @php
                                    $url = route('admin.lease-applications.id', $application).($side === 'back' ? '?side=back' : '');
                                    $approvedAt = $application->{'id_'.$side.'_approved_at'};
                                    $declined = $application->{'id_'.$side.'_declined_reason'};
                                @endphp
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="mb-2 text-sm font-semibold">{{ $application->id_type === 'passport' ? 'Passport data page' : ucfirst($side).' of ID' }}</p>
                                    <a href="{{ $url }}" target="_blank" class="block overflow-hidden rounded-md bg-slate-50">
                                        <img src="{{ $url }}" alt="{{ ucfirst($side) }} of ID" class="h-56 w-full object-contain">
                                    </a>
                                    @if($approvedAt)
                                        <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-sm font-semibold text-emerald-800">Approved {{ \Illuminate\Support\Carbon::parse($approvedAt)->format('M j, Y g:i A') }}</div>
                                    @else
                                        @if($declined)<div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2 text-sm text-amber-800"><strong>Declined:</strong> {{ $declined }}</div>@endif
                                        <div class="mt-3 flex gap-2">
                                            <form method="post" action="{{ route('admin.lease-applications.id.approve', [$application, $side]) }}" class="flex-1">@csrf<button class="btn-primary w-full">Approve {{ $side }}</button></form>
                                            <button type="button" class="btn-danger flex-1" onclick="document.getElementById('decline-{{ $side }}').classList.toggle('hidden')">Decline</button>
                                        </div>
                                        <form id="decline-{{ $side }}" method="post" action="{{ route('admin.lease-applications.id.decline', [$application, $side]) }}" class="mt-2 hidden grid gap-2">
                                            @csrf
                                            <textarea name="decline_reason" rows="3" class="input" placeholder="Reason for declining the {{ $side }}" required></textarea>
                                            <button class="btn-secondary w-full">Submit decline</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 grid gap-x-8 sm:grid-cols-3">
                        <div class="{{ $row }} sm:block sm:border-0"><span class="block text-xs uppercase text-slate-400">Name on ID</span><span class="font-medium">{{ $application->id_name ?: '—' }}</span></div>
                        <div class="{{ $row }} sm:block sm:border-0"><span class="block text-xs uppercase text-slate-400">Address on ID</span><span class="font-medium">{{ $application->id_address ?: '—' }}</span></div>
                        <div class="{{ $row }} sm:block sm:border-0"><span class="block text-xs uppercase text-slate-400">ID number</span><span class="font-medium">{{ $application->id_number_last4 ? '•••• '.$application->id_number_last4 : '—' }}</span></div>
                    </div>
                </section>

                <div class="grid gap-6 md:grid-cols-2">
                    <section class="{{ $card }}">
                        <h2 class="mb-2 text-lg font-semibold">Contact</h2>
                        <div class="{{ $row }}"><span class="text-slate-500">Email</span><span class="text-right">{{ $application->applicant_email }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Phone</span><span class="text-right">{{ $application->applicant_phone }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Text updates</span><span class="text-right">{{ $application->sms_consent_at ? 'Opted in '.$application->sms_consent_at->format('M j, Y') : 'Not opted in (email only)' }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Current address</span><span class="text-right">{{ $application->current_address }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Time there</span><span class="text-right">{{ $application->current_address_months !== null ? $application->current_address_months.' months' : '—' }}</span></div>
                    </section>
                    <section class="{{ $card }}">
                        <h2 class="mb-2 text-lg font-semibold">Move-in and household</h2>
                        <div class="{{ $row }}"><span class="text-slate-500">Move-in</span><span>{{ $application->move_in_date?->format('M j, Y') }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Lease term</span><span>{{ $application->lease_term_months }} months</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Occupants</span><span>{{ $application->occupants }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Pets</span><span class="text-right">{{ $application->pets ?: 'None listed' }}</span></div>
                    </section>
                    <section class="{{ $card }}">
                        <h2 class="mb-2 text-lg font-semibold">Employment</h2>
                        <div class="{{ $row }}"><span class="text-slate-500">Employer</span><span class="text-right">{{ $application->employer }}@if($application->job_title) · {{ $application->job_title }}@endif</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Supervisor</span><span class="text-right">{{ $application->supervisor_name }} {{ $application->supervisor_phone }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Monthly income</span><span>{{ $money($application->monthly_income_cents) }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Years employed</span><span>{{ $application->years_employed }}</span></div>
                    </section>
                    <section class="{{ $card }}">
                        <h2 class="mb-2 text-lg font-semibold">References</h2>
                        <div class="{{ $row }}"><span class="text-slate-500">Previous landlord</span><span class="text-right">{{ $application->previous_landlord_name ?: '—' }} {{ $application->previous_landlord_phone }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">Emergency contact</span><span class="text-right">{{ $application->emergency_contact_name }} {{ $application->emergency_contact_phone }}</span></div>
                    </section>
                </div>
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                <form method="post" action="{{ route('admin.lease-applications.update', $application) }}" class="{{ $card }}">
                    @csrf
                    @method('put')
                    <h2 class="text-lg font-semibold">Review</h2>
                    @if(count($sides) && ! $allApproved)
                        <p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">Approve the ID before approving this application.</p>
                    @endif
                    <label for="status" class="mt-3 block text-sm font-medium">Status</label>
                    <select id="status" name="status" class="input mt-1 w-full" onchange="document.getElementById('decline-reason-wrap').classList.toggle('hidden', this.value !== 'declined')">
                        @foreach(['in_review' => 'In review', 'approved' => 'Approved', 'declined' => 'Declined'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $application->status === 'submitted' ? 'in_review' : $application->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Approving or declining emails and texts the applicant.</p>
                    <div id="decline-reason-wrap" class="mt-3 {{ old('status', $application->status) === 'declined' ? '' : 'hidden' }}">
                        <label for="decline_reason" class="block text-sm font-medium">Reason sent to applicant (optional)</label>
                        <textarea id="decline_reason" name="decline_reason" rows="3" maxlength="1000" class="input mt-1 w-full" placeholder="Included in the decline email and text">{{ old('decline_reason', $application->decline_reason) }}</textarea>
                    </div>
                    <label for="host_notes" class="mt-3 block text-sm font-medium">Private notes</label>
                    <textarea id="host_notes" name="host_notes" rows="5" class="input mt-1 w-full">{{ old('host_notes', $application->host_notes) }}</textarea>
                    <button class="btn-primary mt-4 w-full">Save review</button>
                </form>

                <section class="{{ $card }}">
                    <h2 class="mb-2 text-lg font-semibold">Consent and fee</h2>
                    <div class="{{ $row }}"><span class="text-slate-500">Signed by</span><span class="text-right">{{ $application->consent_signature ?: '—' }}</span></div>
                    <div class="{{ $row }}"><span class="text-slate-500">Signed at</span><span class="text-right">{{ $application->consent_at?->format('M j, Y g:i A') }}</span></div>
                    <div class="{{ $row }}"><span class="text-slate-500">Fee</span><span>{{ $money($application->application_fee_cents) }}</span></div>
                    @if((int) $application->processing_fee_cents > 0)<div class="{{ $row }}"><span class="text-slate-500">Processing</span><span>{{ $money($application->processing_fee_cents) }}</span></div>@endif
                    <div class="{{ $row }} font-semibold"><span>Total</span><span>{{ $money($application->total_fee_cents) }}</span></div>
                    <div class="{{ $row }}"><span class="text-slate-500">Method</span><span class="capitalize">{{ $application->payment_method }}</span></div>
                    @if($application->payment_method === 'manual' && $application->fee_status === 'pending')
                        @include('partials.payment-receipts', ['payable' => $application])
                        <form method="post" action="{{ route('admin.lease-applications.fee-received', $application) }}" class="mt-3">
                            @csrf
                            <button class="btn-primary w-full">Mark manual fee received</button>
                        </form>
                    @endif
                </section>

                @if($application->status === 'approved')
                    @php($activeOffer = $application->offers->first(fn ($offer) => in_array($offer->status, ['sent', 'signed', 'confirmed'], true)))
                    @if($activeOffer)
                        <section class="{{ $card }}">
                            <h2 class="text-lg font-semibold">Lease offer #{{ $activeOffer->id }}</h2>
                            <p class="mt-2 text-sm text-slate-600">Status: <strong class="capitalize">{{ $activeOffer->status }}</strong> · {{ $activeOffer->payment_method === 'manual' ? 'Manual payment' : 'Card payment' }}</p>
                            <p class="mt-1 text-sm text-slate-600">Initial payment due: <strong>{{ $money($activeOffer->initial_due_cents) }}</strong> (first rent + deposit + additional fees)</p>
                            <div class="mt-3 space-y-2">
                                @foreach($activeOffer->installments->where('required_at_signing', true) as $installment)
                                    <div class="flex justify-between gap-3 text-sm"><span>{{ $installment->label }}</span><span class="font-semibold">{{ $money($installment->amount_cents) }} · {{ ucfirst($installment->status) }}</span></div>
                                @endforeach
                            </div>
                            @if($activeOffer->status === 'signed' && $activeOffer->payment_method === 'manual' && $activeOffer->installments->where('required_at_signing', true)->every(fn ($item) => $item->status === 'pending'))
                                <form method="post" action="{{ route('admin.lease-applications.offers.initial-payment.received', [$application, $activeOffer]) }}" class="mt-4">
                                    @csrf
                                    <button class="btn-primary w-full">Record full initial payment received</button>
                                </form>
                            @elseif($activeOffer->status === 'signed' && $activeOffer->payment_method === 'card')
                                <p class="mt-3 text-xs text-slate-500">The guest pays the combined initial amount securely on the offer page. The payment status updates automatically.</p>
                            @endif
                            <p class="mt-3 text-xs text-slate-500">The tenant sees the same agreement stored on this offer. Later monthly rent installments remain separate.</p>
                        </section>
                    @endif
                    <section class="{{ $card }}">
                        <h2 class="text-lg font-semibold">Rental contract</h2>
                        @if($application->offers->contains(fn($offer) => in_array($offer->status, ['sent', 'signed', 'confirmed'], true)))
                            <p class="mt-2 text-sm text-slate-600">An offer is already in progress. Review signatures and payment progress below.</p>
                        @elseif($application->fee_status !== 'paid')
                            <p class="mt-2 text-sm text-slate-600">Record the application fee before preparing the rental contract.</p>
                        @else
                            <p class="mt-2 text-sm text-slate-600">Continue to the contract page to review the rental terms, edit the agreement and send it to the applicant.</p>
                            <a href="{{ route('admin.lease-applications.contract', $application) }}" class="btn-primary mt-4 inline-flex">Prepare rental contract</a>
                        @endif
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-admin-layout>

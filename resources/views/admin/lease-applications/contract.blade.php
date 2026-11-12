<x-admin-layout title="Prepare rental contract">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a class="text-sm text-slate-500 underline" href="{{ route('admin.lease-applications.show', $application) }}">← Back to application</a>
        <div class="mt-4">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Next step · Rental contract</p>
            <h1 class="mt-1 text-2xl font-semibold">Prepare the rental contract</h1>
            <p class="mt-2 text-sm text-slate-600">Application approved for {{ $application->applicant_name }} at {{ $application->listing->public_title }}. Review the terms, edit the agreement, choose a payment method and send it for signature.</p>
        </div>
        @if(session('success'))<p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
        @php($activeOffer = $application->offers->first(fn ($offer) => in_array($offer->status, ['sent', 'signed', 'confirmed'], true)))
        @if($application->fee_status !== 'paid')
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Record the application fee as received before sending the rental offer. <a class="font-semibold underline" href="{{ route('admin.lease-applications.show', $application) }}">Return to application</a>.</div>
        @elseif($activeOffer)
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">An active rental offer already exists</h2><p class="mt-1 text-sm text-slate-600">Offer #{{ $activeOffer->id }} · {{ ucfirst($activeOffer->status) }}</p><a class="mt-3 inline-block text-sm font-semibold underline" href="{{ route('admin.lease-applications.show', $application) }}">View offer and payment progress</a></div>
        @elseif(!$canCard && $paymentMethods->isEmpty())
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No payment method is available. Enable a host payment method or connect card payments before sending this contract.</div>
        @else
            <form method="post" action="{{ route('admin.lease-applications.offers.create', $application) }}" class="mt-6 grid gap-6">
                @csrf
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-semibold">Rental terms</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium">Rental start date<input type="date" name="start_date" required min="{{ now()->toDateString() }}" value="{{ old('start_date', $application->move_in_date?->format('Y-m-d')) }}" class="input mt-1 w-full"></label>
                        <label class="block text-sm font-medium">Rental end date<input type="date" name="end_date" required value="{{ old('end_date', $application->requested_end_date?->format('Y-m-d') ?: $application->move_in_date?->copy()->addMonthsNoOverflow($application->lease_term_months ?: 12)->format('Y-m-d')) }}" class="input mt-1 w-full"></label>
                        <label class="block text-sm font-medium">Term (months)<input type="number" name="term_months" min="1" max="600" required value="{{ old('term_months', $application->lease_term_months ?: 12) }}" class="input mt-1 w-full"></label>
                        <label class="block text-sm font-medium">Monthly rent (USD)<input type="number" name="monthly_rent" min="0.01" step="0.01" required value="{{ old('monthly_rent') }}" class="input mt-1 w-full" placeholder="1500.00"></label>
                        <label class="block text-sm font-medium">Security deposit (USD)<input type="number" name="deposit" min="0" step="0.01" value="{{ old('deposit', '0.00') }}" class="input mt-1 w-full"></label>
                        <label class="block text-sm font-medium">Other fees (USD)<input type="number" name="fees" min="0" step="0.01" value="{{ old('fees', '0.00') }}" class="input mt-1 w-full"></label>
                        <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600 sm:col-span-2"><strong class="text-slate-900">Initial payment is calculated automatically.</strong> The tenant pays the first month’s rent, security deposit and additional fees together in one payment. Remaining rent is scheduled monthly.</div>
                        <label class="block text-sm font-medium sm:col-span-2">Offer acceptance deadline<input type="datetime-local" name="accept_by" required min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('accept_by', now()->addDays(3)->format('Y-m-d\TH:i')) }}" class="input mt-1 w-full"></label>
                    </div>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-semibold">Rental agreement</h2>
                    <p class="mt-1 text-sm text-slate-500">The property’s saved lease template is loaded below. Edit this copy for the applicant; the property template itself is not changed.</p>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Insert a placeholder</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(app(\App\Services\LeaseAgreementService::class)->placeholders() as $ph)
                            <button type="button" data-lease-placeholder="[[{{ $ph }}]]" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">[[{{ $ph }}]]</button>
                        @endforeach
                    </div>
                    <textarea id="contract_html" name="contract_html" rows="18" required class="input mt-3 w-full">{{ old('contract_html', $leaseTemplate) }}</textarea>
                    @error('contract_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-semibold">Payment method</h2>
                    <p class="mt-1 text-sm text-slate-500">Only payment methods enabled by this host are shown.</p>
                    <label class="mt-4 block text-sm font-medium">How will the tenant pay?
                        <select id="lease-payment-method" name="payment_method" required class="input mt-1 w-full">
                            @if($canCard)<option value="card" @selected(old('payment_method', $paymentMethods->isEmpty() ? 'card' : '') === 'card')>Card through GuestHub</option>@endif
                            @if($paymentMethods->isNotEmpty())<option value="manual" @selected(old('payment_method') === 'manual')>External / manual payment</option>@endif
                        </select>
                    </label>
                    @if($paymentMethods->isNotEmpty())
                        <label id="lease-manual-method-wrap" class="mt-3 hidden block text-sm font-medium">Enabled host payment method
                            <select id="lease-manual-method" name="host_payment_method_id" class="input mt-1 w-full"><option value="">Choose an enabled method</option>
                                @foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected((string) old('host_payment_method_id') === (string) $method->id)>{{ $method->label }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                </section>
                <div class="flex flex-wrap items-center justify-between gap-3"><a href="{{ route('admin.lease-applications.show', $application) }}" class="text-sm font-semibold text-slate-600 underline">Cancel</a><button class="btn-primary">Review and send contract</button></div>
            </form>
        @endif
    </div>
    <script>
    (function () {
        var tries = 0;
        function editor() { return window.tinymce ? tinymce.get('contract_html') : null; }
        function boot() {
            if (!window.tinymce) {
                if (tries++ === 0) {
                    var script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js';
                    script.onload = boot;
                    document.head.appendChild(script);
                    return;
                }
                if (tries < 100) { setTimeout(boot, 200); }
                return;
            }
            if (editor()) return;
            tinymce.init({ selector: '#contract_html', height: 460, menubar: false, branding: false, promotion: false, plugins: 'lists link table code', content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; padding: 12px; }', toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | removeformat code' });
        }
        boot();
        document.querySelectorAll('[data-lease-placeholder]').forEach(function (button) { button.addEventListener('click', function () { var ed = editor(); if (ed) { ed.focus(); ed.insertContent(button.dataset.leasePlaceholder); } }); });
        var payment = document.getElementById('lease-payment-method'), wrap = document.getElementById('lease-manual-method-wrap'), select = document.getElementById('lease-manual-method');
        function syncPayment() { var manual = payment && payment.value === 'manual'; if (wrap) wrap.classList.toggle('hidden', !manual); if (select) { select.required = !!manual; if (!manual) select.value = ''; } }
        if (payment) { payment.addEventListener('change', syncPayment); syncPayment(); }
    })();
    </script>
</x-admin-layout>

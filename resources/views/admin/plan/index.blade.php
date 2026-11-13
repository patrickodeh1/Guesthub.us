<x-admin-layout title="Plan">
    @php
        $money = fn ($c) => $c > 0 ? '$'.number_format($c / 100, 2) : 'Free';
        $unitLabels = ['flat' => '', 'per_property' => ' per property', 'per_device' => ' per device'];
        $includedKeys = $selected ? ($keysByPackage[$selected->id] ?? []) : [];
        $shownAddons = $addonFeatures->reject(fn ($f) => in_array($f->key, $includedKeys, true));
        $adminManaged = $current && $current->billing_source === 'admin';
        $subscribed = $current && $current->stripe_subscription_id && in_array($current->status, ['active', 'trialing', 'past_due'], true);
        $trialOk = ! ($current && $current->stripe_customer_id);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Settings</p>
            <h1 class="page-title">Plan</h1>
            <p class="page-subtitle">Choose a package and add-ons, then start your plan.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if(request('checkout') === 'done')
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">Thanks. Your plan turns on as soon as the payment is confirmed. Refresh this page in a moment.</div>
    @elseif(request('checkout') === 'cancelled')
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Checkout was cancelled. Nothing was charged.</div>
    @endif

    <div class="card card-pad mb-6">
        <h2 class="section-title">Current plan</h2>
        @if($current && $current->package)
            <p class="mt-2 text-sm text-slate-700">{{ $current->package->name }} · {{ ucfirst(str_replace('_', ' ', $current->status)) }}
                @if($current->status === 'trialing' && $current->trial_ends_at) · trial ends {{ $current->trial_ends_at->format('M j, Y') }} @endif
            </p>
        @else
            <p class="mt-2 text-sm text-slate-500">No plan yet.</p>
        @endif
        @if($current && $current->cancel_at_period_end && $current->current_period_end)
            <p class="mt-1 text-xs text-amber-700">Ends {{ $current->current_period_end->format('M j, Y') }}.</p>
        @endif
        @if($current && $current->billing_source === 'stripe' && $current->stripe_customer_id)
            <form method="POST" action="{{ route('admin.plan.portal') }}" class="mt-3">@csrf<button type="submit" class="btn-secondary">Manage billing</button></form>
        @endif
    </div>

    <div class="card card-pad mb-6 overflow-x-auto">
        <h2 class="section-title">Compare plans</h2>
        <table class="mt-3 w-full text-sm">
            <thead>
                <tr>
                    <th class="py-2 text-left"></th>
                    @foreach($packages as $p)<th class="py-2 text-center">{{ $p->name }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="border-t"><td class="py-2 text-slate-500">Price</td>@foreach($packages as $p)<td class="py-2 text-center">{{ $money($p->price_cents) }}/mo</td>@endforeach</tr>
                <tr class="border-t"><td class="py-2 text-slate-500">Free trial</td>@foreach($packages as $p)<td class="py-2 text-center">{{ $p->trial_days ? $p->trial_days.' days' : '-' }}</td>@endforeach</tr>
                @foreach($compareFeatures as $f)
                    <tr class="border-t">
                        <td class="py-2">{{ $f->name }}</td>
                        @foreach($packages as $p)
                            <td class="py-2 text-center">{!! in_array($f->key, $keysByPackage[$p->id] ?? [], true) ? '<span class="text-emerald-700">&#10003;</span>' : '<span class="text-slate-300">-</span>' !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="GET" action="{{ route('admin.plan.index') }}">
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            @foreach($packages as $p)
                <label class="card card-pad block cursor-pointer {{ $selected && $selected->id === $p->id ? 'ring-2 ring-slate-900' : '' }}">
                    <input type="radio" name="package" value="{{ $p->id }}" @checked($selected && $selected->id === $p->id) onchange="this.form.submit()">
                    <span class="section-title ml-1">{{ $p->name }}</span>
                    <p class="mt-1 text-2xl font-semibold">{{ $money($p->price_cents) }}<span class="text-xs font-normal text-slate-400">/mo</span></p>
                    @if($p->trial_days)<p class="text-xs text-emerald-700">{{ $p->trial_days }} day free trial</p>@endif
                    @if($p->description)<p class="mt-2 text-sm text-slate-600">{{ $p->description }}</p>@endif
                </label>
            @endforeach
        </div>

        @if($shownAddons->isNotEmpty())
            <div class="card card-pad mb-6">
                <h2 class="section-title">Add-ons</h2>
                <p class="mt-1 text-xs text-slate-500">Per-property and per-device quantities are calculated from your active properties and connected smart locks.</p>
                <div class="mt-3 grid gap-3">
                    @foreach($shownAddons as $f)
                        @php
                            $inPkg = in_array($f->key, $includedKeys, true);
                            $unit = $f->billing_unit ?: 'flat';
                            $quantity = $unit === 'flat' ? 1 : ($billingQuantities[$unit] ?? 0);
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="addons[{{ $f->key }}][enabled]" value="1" @checked(isset($addons[$f->key]) && ! $inPkg) @disabled($inPkg || ($unit !== 'flat' && $quantity < 1))>
                                {{ $f->name }} <span class="text-slate-400">{{ $money($f->price_cents) }}{{ $unitLabels[$unit] ?? '' }}</span>
                                @if($inPkg)<span class="text-xs text-emerald-700">included</span>@endif
                            </label>
                            @if($unit !== 'flat' && ! $inPkg)
                                <span class="text-xs text-slate-500">{{ $quantity }}{{ $unitLabels[$unit] ?? '' }}</span>
                                @if($quantity > 0)
                                    <input type="hidden" name="addons[{{ $f->key }}][quantity]" value="{{ $quantity }}">
                                @else
                                    <span class="text-xs text-amber-700">Add a property or connect a device to enable</span>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card card-pad">
            <h2 class="section-title">Monthly total</h2>
            @forelse($quote['lines'] as $l)
                <p class="mt-1 text-sm text-slate-700">{{ $l['name'] }} · {{ $money($l['unit_cents']) }} × {{ $l['quantity'] }}</p>
            @empty
                <p class="mt-1 text-sm text-slate-500">Nothing selected.</p>
            @endforelse
            <p class="mt-3 text-xl font-semibold">{{ $money($quote['total_cents']) }}<span class="text-xs font-normal text-slate-400">/mo</span></p>
            <button type="submit" class="btn-secondary mt-3">Update total</button>
        </div>
    </form>

    @if($adminManaged)
        <p class="mt-4 text-sm text-slate-500">Your plan is managed by your account admin.</p>
    @elseif($subscribed)
        <p class="mt-4 text-sm text-slate-500">You have a subscription. Use Manage billing above to update your card or cancel.</p>
        @if($current && $current->package && $selected && $selected->id === $current->package_id)
            <form method="POST" action="{{ route('admin.plan.addons') }}" class="mt-3">
                @csrf
                @foreach($addons as $k => $q)
                    @unless(in_array($k, $includedKeys, true))
                        <input type="hidden" name="addons[{{ $k }}][enabled]" value="1">
                        <input type="hidden" name="addons[{{ $k }}][quantity]" value="{{ $q }}">
                    @endunless
                @endforeach
                <button type="submit" class="btn-primary">Update add-ons ({{ $money($quote['total_cents']) }}/mo)</button>
                <p class="mt-1 text-xs text-slate-500">@if($current->status === 'trialing' && $current->trial_ends_at)
                        Nothing is charged until your trial ends on {{ $current->trial_ends_at->format('M j, Y') }}.
                    @elseif($current->current_period_end)
                        The new amount is billed from your next invoice on {{ $current->current_period_end->format('M j, Y') }}.
                    @else
                        The new amount is billed from your next invoice.
                    @endif</p>
            </form>
        @elseif($current && $current->package && $selected && $selected->id !== $current->package_id)
            <form method="POST" action="{{ route('admin.plan.switch') }}" class="mt-3">
                @csrf
                <input type="hidden" name="package" value="{{ $selected->id }}">
                @foreach($addons as $k => $q)
                    @unless(in_array($k, $includedKeys, true))
                        <input type="hidden" name="addons[{{ $k }}][enabled]" value="1">
                        <input type="hidden" name="addons[{{ $k }}][quantity]" value="{{ $q }}">
                    @endunless
                @endforeach
                <button type="submit" class="btn-primary" onclick="return confirm('Switch to {{ $selected->name }} ({{ $money($quote['total_cents']) }}/mo)?')">Switch to {{ $selected->name }} ({{ $money($quote['total_cents']) }}/mo)</button>
                <p class="mt-1 text-xs text-slate-500">The new amount is billed from your next invoice. No charge or credit is made for the rest of this month.</p>
            </form>
        @endif
    @elseif($selected)
        @php
            $btnText = $trialOk && $selected->trial_days && $quote['total_cents'] > 0 ? 'Start '.$selected->trial_days.'-day free trial' : ($quote['total_cents'] > 0 ? 'Subscribe' : 'Choose this plan');
            $payloadAddons = collect($addons)->reject(fn ($q, $k) => in_array($k, $includedKeys, true))->map(fn ($q) => ['enabled' => 1, 'quantity' => $q])->all();
        @endphp
        @if($quote['total_cents'] <= 0)
            <form method="POST" action="{{ route('admin.plan.checkout') }}" class="mt-4">
                @csrf
                <input type="hidden" name="package" value="{{ $selected->id }}">
                <button type="submit" class="btn-primary">{{ $btnText }}</button>
            </form>
        @else
            <div class="card card-pad mt-4 max-w-xl">
                <h2 class="section-title">Pay securely by card</h2>
                <form id="plan-card-form" novalidate class="mt-3 grid gap-3">
                    <div>
                        <label class="guest-card-label" for="plan-number">Card number</label>
                        <div id="plan-number" class="guest-card-field"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="guest-card-label" for="plan-expiry">Expiry date</label><div id="plan-expiry" class="guest-card-field"></div></div>
                        <div><label class="guest-card-label" for="plan-cvc">Security code (CVC)</label><div id="plan-cvc" class="guest-card-field"></div></div>
                    </div>
                    <div>
                        <label class="guest-card-label" for="plan-postal">Billing ZIP / postal code</label>
                        <input id="plan-postal" class="guest-card-postal" type="text" autocomplete="postal-code" maxlength="12" placeholder="ZIP / Postal code">
                    </div>
                    <div id="plan-error" class="text-sm text-red-700"></div>
                    <button id="plan-button" type="submit" class="btn-primary" disabled>{{ $btnText }}</button>
                </form>
                <p class="mt-3 text-xs text-slate-500">Your card details are encrypted and never touch this site.</p>
            </div>
            <script src="https://js.stripe.com/v3/"></script>
            <script>
                (() => {
                    const stripe = Stripe(@json(config('services.stripe.key')));
                    const elements = stripe.elements();
                    const style = {base: {color: '#0f172a', fontSize: '15px', fontFamily: 'inherit', lineHeight: '24px', '::placeholder': {color: '#94a3b8'}}, invalid: {color: '#dc2626'}};
                    const cls = {classes: {base: 'guest-card-field-inner'}};
                    const number = elements.create('cardNumber', Object.assign({showIcon: true, placeholder: '1234 5678 9012 3456', style}, cls));
                    const expiry = elements.create('cardExpiry', Object.assign({placeholder: 'MM / YY', style}, cls));
                    const cvc = elements.create('cardCvc', Object.assign({placeholder: 'CVC', style}, cls));
                    number.mount('#plan-number'); expiry.mount('#plan-expiry'); cvc.mount('#plan-cvc');
                    const postal = document.getElementById('plan-postal');
                    const button = document.getElementById('plan-button');
                    const error = document.getElementById('plan-error');
                    const label = button.textContent;
                    const done = {number: false, expiry: false, cvc: false};
                    const showError = (m) => { error.textContent = m || ''; };
                    const update = () => { button.disabled = !(done.number && done.expiry && done.cvc && postal.value.trim().length >= 3); };
                    function wire(el, key, next) {
                        el.on('change', (e) => { done[key] = e.complete; showError(e.error ? e.error.message : ''); if (e.complete && next) next(); update(); });
                    }
                    wire(number, 'number', () => expiry.focus());
                    wire(expiry, 'expiry', () => cvc.focus());
                    wire(cvc, 'cvc', () => postal.focus());
                    postal.addEventListener('input', () => { showError(''); update(); });

                    const payload = {package: @json($selected->id), addons: @json($payloadAddons)};
                    const post = async (url, body) => {
                        const r = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify(body)});
                        let data = {};
                        try { data = await r.json(); } catch (e) {}
                        return {ok: r.ok, data};
                    };
                    document.getElementById('plan-card-form').addEventListener('submit', async (event) => {
                        event.preventDefault();
                        button.disabled = true; button.textContent = 'Processing...'; showError('');
                        const fail = (m) => { showError(m); button.textContent = label; update(); };
                        const s = await post(@json(route('admin.plan.setup')), payload);
                        if (!s.ok) return fail(s.data.error || 'Could not start card setup.');
                        const result = await stripe.confirmCardSetup(s.data.client_secret, {
                            payment_method: {card: number, billing_details: {address: {postal_code: postal.value.trim()}}}
                        });
                        if (result.error) return fail(result.error.message || 'Card could not be saved.');
                        const r = await post(@json(route('admin.plan.subscribe')), Object.assign({setup_intent_id: result.setupIntent.id}, payload));
                        if (r.ok && r.data.redirect) { window.location.assign(r.data.redirect); return; }
                        fail(r.data.error || 'Could not start the plan.');
                    });
                })();
            </script>
        @endif
    @endif
</x-admin-layout>

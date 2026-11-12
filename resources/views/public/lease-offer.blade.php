@extends('layouts.public')

@section('title', 'Rental offer')
@section('meta_description', 'Review and sign your rental offer.')

@section('content')
@php
    $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);
    $contract = app(\App\Services\LeaseOfferService::class)->renderContract($offer);
    $initialRequired = $offer->installments->where('required_at_signing', true);
    $initialPaymentPending = isset($initialPaymentPending) ? $initialPaymentPending : $initialRequired->contains(fn ($item) => $item->status === 'pending');
    $payAmountCents = $initialPaymentPending ? $offer->initial_due_cents : ($nextInstallment?->amount_cents ?? 0);
    $confirmUrl = route('public.lease-offer.confirm', ['application' => $offer->lease_application_id, 'token' => $offer->application->token, 'offerToken' => $offer->token]);
    $closed = in_array($offer->status, ['expired', 'declined', 'withdrawn'], true) || ($offer->status !== 'confirmed' && $offer->accept_by?->isPast());
@endphp
<style>
.lease-wrap{max-width:900px;margin:0 auto;padding:30px 16px 60px;color:#17221e}
.lease-card{background:#fff;border:1px solid #dfe6e1;border-radius:16px;padding:22px;margin-top:16px}
.lease-muted{color:#64746b;font-size:.9rem;line-height:1.55}
.lease-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.lease-stat{padding:13px;border-radius:11px;background:#f6f8f6}
.lease-stat small{display:block;color:#64746b;font-size:.76rem}.lease-stat strong{display:block;margin-top:4px;font-size:1.05rem}
.lease-table{width:100%;border-collapse:collapse;font-size:.9rem}.lease-table td{padding:10px 4px;border-bottom:1px solid #edf0ee}.lease-table td:last-child{text-align:right;font-weight:650}
.lease-button{border:0;border-radius:10px;padding:13px 18px;background:#1f6f54;color:white;font-weight:700;cursor:pointer;width:100%}
.lease-input{width:100%;border:1px solid #cfd8d2;border-radius:10px;padding:12px;box-sizing:border-box}
.lease-error{color:#a52828;font-size:.9rem;margin-top:10px}
.lease-contract{line-height:1.65;overflow-wrap:anywhere}.lease-contract table{width:100%;border-collapse:collapse}.lease-contract td{padding:7px;border-bottom:1px solid #e5e7eb}
.guest-card-label{display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px}
.guest-card-field{display:flex;align-items:center;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;padding:10px 12px;position:relative;overflow:visible;transition:border-color .2s ease,box-shadow .2s ease}
.guest-card-field:focus-within{border-color:#0f172a;box-shadow:0 0 0 3px rgb(15 23 42 / .08)}
.guest-card-field .StripeElement,.guest-card-field-inner{display:block;width:100%;font-size:15px;line-height:1.3}
.guest-card-field iframe{display:block!important;width:100%!important;height:32px!important;border:0!important}
.guest-card-postal{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;padding:10px 12px;color:#0f172a;font:inherit}
.guest-card-postal:focus{outline:none;border-color:#0f172a;box-shadow:0 0 0 3px rgb(15 23 42 / .08)}
.guest-card-wizard{position:relative}
.lease-card-pay-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;border-radius:6px;background:var(--site-button);padding:10px 16px;font-size:12px;font-weight:800;color:#fff;border:0;transition:background .18s ease,opacity .18s ease;cursor:pointer;width:100%}
.lease-card-pay-button:hover{background:var(--site-button-hover)}
.lease-card-pay-button:disabled{cursor:not-allowed;opacity:.55}

@media(max-width:600px){.lease-wrap{padding:20px 12px 40px}.lease-card{padding:16px}.lease-grid{grid-template-columns:1fr}.lease-table{font-size:.82rem}}
</style>
<div class="lease-wrap">
    <p class="lease-muted">{{ $offer->property->name }} · Rental offer</p>
    <h1 style="font-size:clamp(1.6rem,4vw,2.1rem);font-weight:800;margin:6px 0">Review your rental offer</h1>
    <p class="lease-muted">Offer expires {{ $offer->accept_by?->format('M j, Y g:i A') }}. Your reservation is held until then.</p>
    @if(session('status'))<div class="lease-card" role="status">{{ session('status') }}</div>@endif
    @if($closed)
        <section class="lease-card"><h2 style="font-weight:750">This offer is no longer available</h2><p class="lease-muted">The acceptance deadline has passed or this offer was closed. Contact the host if you would like to discuss a new offer.</p></section>
    @else
        <section class="lease-card">
            <div class="lease-grid">
                <div class="lease-stat"><small>Rental period</small><strong>{{ $offer->start_date->format('M j, Y') }} – {{ $offer->end_date->format('M j, Y') }}</strong></div>
                <div class="lease-stat"><small>Monthly rent</small><strong>{{ $money($offer->monthly_rent_cents) }}</strong></div>
                <div class="lease-stat"><small>Security deposit</small><strong>{{ $money($offer->deposit_cents) }}</strong></div>
                <div class="lease-stat"><small>Total for the term</small><strong>{{ $money($offer->total_cents) }}</strong></div>
            </div>
        </section>
        <section class="lease-card">
            <h2 style="font-size:1.15rem;font-weight:750;margin-bottom:10px">Payment schedule</h2>
            <table class="lease-table">
                @foreach($offer->installments as $item)
                    <tr><td>{{ $item->label }}<br><span class="lease-muted">Due {{ $item->due_date->format('M j, Y') }} @if($item->required_at_signing) · Required before confirmation @endif</span></td><td>{{ $money($item->amount_cents) }}<br><span class="lease-muted">{{ ucfirst($item->status) }}</span></td></tr>
                @endforeach
            </table>
            <p class="lease-muted" style="margin-top:12px">Amount required before confirmation: {{ $money($offer->initial_due_cents) }}.</p>
            @if($initialPaymentCharge)
                <div class="lease-muted" style="margin-top:10px;padding:10px;border:1px solid #e5e7eb;border-radius:10px">
                    Initial payment status: <strong>{{ str($initialPaymentCharge->status)->replace('_', ' ')->title() }}</strong>
                    · {{ $money($initialPaymentCharge->amount_cents) }}
                    @if($initialPaymentCharge->captured_at) · Paid {{ $initialPaymentCharge->captured_at->format('M j, Y g:i A') }} @endif
                    @if($initialPaymentCharge->stripe_payment_intent_id)<div style="margin-top:4px;overflow-wrap:anywhere">Payment reference: {{ $initialPaymentCharge->stripe_payment_intent_id }}</div>@endif
                </div>
            @endif
        </section>
        <section class="lease-card">
            <h2 style="font-size:1.15rem;font-weight:750;margin-bottom:12px">Rental agreement</h2>
            <div class="lease-contract">{!! $contract !!}</div>
            <p class="lease-muted" style="margin-top:14px">Host signature: {{ $offer->host_signed_name }} · {{ $offer->host_signed_at?->format('M j, Y g:i A') }}</p>
        </section>
        @if(!$offer->guest_signed_at)
            <section class="lease-card">
                <h2 style="font-size:1.15rem;font-weight:750">Sign the agreement</h2>
                <p class="lease-muted">Type your full name as it appears on your application to sign electronically.</p>
                <form method="post" action="{{ route('public.lease-offer.sign', ['application' => $offer->lease_application_id, 'token' => $offer->application->token, 'offerToken' => $offer->token]) }}" style="display:grid;gap:12px;margin-top:14px">
                    @csrf
                    <label>Full legal name<input class="lease-input" name="signature" value="{{ old('signature', $offer->application->applicant_name) }}" required maxlength="255"></label>
                    <label style="display:flex;gap:9px;align-items:flex-start;font-size:.9rem"><input type="checkbox" name="accept_terms" value="1" required style="margin-top:4px"> I have reviewed and agree to the rental agreement and payment schedule.</label>
                    @error('signature')<p class="lease-error">{{ $message }}</p>@enderror
                    @error('accept_terms')<p class="lease-error">{{ $message }}</p>@enderror
                    <button class="lease-button">Sign rental agreement</button>
                </form>
            </section>
        @elseif(in_array($offer->status, ['signed', 'confirmed'], true))
            <section class="lease-card">
                @if($initialPaymentPending)
                    <h2 style="font-size:1.15rem;font-weight:750">Initial payment · Due now</h2>
                    <p class="lease-muted">One payment covers the first month of rent, security deposit and additional fees. These are not separate transactions.</p>
                    <table class="lease-table" style="margin-top:10px">
                        @foreach($initialRequired as $item)
                            <tr><td>{{ $item->label }}</td><td>{{ $money($item->amount_cents) }}</td></tr>
                        @endforeach
                        <tr><td><strong>Total due now</strong></td><td><strong>{{ $money($offer->initial_due_cents) }}</strong></td></tr>
                    </table>
                    @if($offer->payment_method === 'manual')
                        <p class="lease-muted" style="margin-top:12px">Send the full amount using the host's payment method. Include application reference <strong>{{ $offer->lease_application_id }}</strong>. The host will record the combined payment after receiving it.</p>
                        @if($offer->hostPaymentMethod)
                            <p style="margin-top:12px"><strong>{{ $offer->hostPaymentMethod->label }}</strong><br>{{ $offer->hostPaymentMethod->handle }}</p>
                            @if($offer->hostPaymentMethod->instructions)<p class="lease-muted">{{ $offer->hostPaymentMethod->instructions }}</p>@endif
                        @endif
                    @else
                        <div id="lease-payment-choice" style="margin-top:16px">
                            <button type="button" id="lease-pay-here" class="lease-card-pay-button">Pay here with card</button>
                        </div>
                        <div id="lease-card-section" hidden style="margin-top:16px">
                            <div id="lease-payment-error" class="lease-error" role="alert" hidden></div>
                            <form id="lease-payment-form" data-secret="{{ $clientSecret }}">
                                <div class="guest-card-wizard" style="display:grid;gap:14px">
                                    <div><label class="guest-card-label" for="lease-card-number">Card number</label><div id="lease-card-number" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-expiry">Expiry date</label><div id="lease-card-expiry" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-cvc">Security code (CVC)</label><div id="lease-card-cvc" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-postal">Billing ZIP / postal code</label><input id="lease-card-postal" name="postal-code" type="text" autocomplete="postal-code" maxlength="12" placeholder="ZIP / Postal code" class="guest-card-postal"></div>
                                </div>
                                <p id="lease-payment-error-inline" class="lease-error" role="alert"></p>
                                <button id="lease-pay-button" type="submit" class="lease-card-pay-button" style="margin-top:16px" disabled>Pay {{ $money($offer->initial_due_cents) }}</button>
                            </form>
                        </div>
                    @endif
                @elseif($nextInstallment)
                    <h2 style="font-size:1.15rem;font-weight:750">Next rental payment</h2>
                    @if($offer->payment_method === 'manual')
                        <p class="lease-muted">Send {{ $money($nextInstallment->amount_cents) }} using the host's payment method. Include application reference <strong>{{ $offer->lease_application_id }}</strong>. The host records each payment after receiving it.</p>
                        @if($offer->hostPaymentMethod)<p style="margin-top:12px"><strong>{{ $offer->hostPaymentMethod->label }}</strong><br>{{ $offer->hostPaymentMethod->handle }}</p>@if($offer->hostPaymentMethod->instructions)<p class="lease-muted">{{ $offer->hostPaymentMethod->instructions }}</p>@endif @endif
                    @else
                        <p class="lease-muted">Pay the next rent installment of {{ $money($nextInstallment->amount_cents) }} by card.</p>
                        <div id="lease-payment-choice" style="margin-top:16px"><button type="button" id="lease-pay-here" class="lease-button">Pay here with card</button></div>
                        <div id="lease-card-section" hidden style="margin-top:16px">
                            <form id="lease-payment-form" data-secret="{{ $clientSecret }}">
                                <div class="guest-card-wizard" style="display:grid;gap:14px">
                                    <div><label class="guest-card-label" for="lease-card-number">Card number</label><div id="lease-card-number" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-expiry">Expiry date</label><div id="lease-card-expiry" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-cvc">Security code (CVC)</label><div id="lease-card-cvc" class="guest-card-field"></div></div>
                                    <div><label class="guest-card-label" for="lease-card-postal">Billing ZIP / postal code</label><input id="lease-card-postal" name="postal-code" type="text" autocomplete="postal-code" maxlength="12" placeholder="ZIP / Postal code" class="guest-card-field"></div>
                                </div>
                                <p id="lease-payment-error-inline" class="lease-error" role="alert"></p>
                                <button id="lease-pay-button" type="submit" class="lease-card-pay-button" style="margin-top:16px" disabled>Pay {{ $money($nextInstallment->amount_cents) }}</button>
                            </form>
                        </div>
                    @endif
                @elseif($offer->status === 'confirmed')
                    <h2 style="font-size:1.15rem;font-weight:750">Payments up to date</h2>
                    <p class="lease-muted">Your rental is confirmed. You can use your guest portal for check-in, parking, documents and stay information.</p>
                @endif
                @if($offer->status === 'signed' && !$initialPaymentPending)
                    <p class="lease-muted">All required initial payment items are recorded. Your reservation confirmation is being finalized.</p>
                @endif
            </section>
        @endif
        @if($offer->status === 'confirmed')
            <section class="lease-card"><h2 style="font-size:1.15rem;font-weight:750">Your rental is confirmed</h2><p class="lease-muted">Your booking is active. Use the guest portal for check-in, parking, documents and stay services.</p>@if($offer->booking)<a class="lease-button" style="display:block;text-align:center;text-decoration:none;margin-top:12px" href="{{ route('guest.show', [$offer->booking->booking_id, $offer->booking->token]) }}">Open guest portal</a>@endif</section>
        @endif
        @if($offer->status === 'sent')
            <form method="post" action="{{ route('public.lease-offer.decline', ['application' => $offer->lease_application_id, 'token' => $offer->application->token, 'offerToken' => $offer->token]) }}" class="lease-card">
                @csrf
                <button type="submit" style="background:none;border:0;color:#9b3434;text-decoration:underline;cursor:pointer">Decline this offer</button>
            </form>
        @endif
    @endif
</div>
@endsection

@if($clientSecret && ($initialPaymentPending || $nextInstallment))
@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
(() => {
    const choice = document.getElementById('lease-payment-choice');
    const cardSection = document.getElementById('lease-card-section');
    const startButton = document.getElementById('lease-pay-here');
    const form = document.getElementById('lease-payment-form');
    const button = document.getElementById('lease-pay-button');
    const error = document.getElementById('lease-payment-error-inline');
    const postal = document.getElementById('lease-card-postal');
    if (!form || !button) return;
    let stripe, number, expiry, cvc;
    const showError = message => { error.textContent = message; error.hidden = false; };
    startButton?.addEventListener('click', () => {
        choice.hidden = true;
        cardSection.hidden = false;
        if (stripe) return;
        stripe = Stripe(@json($publishableKey), {stripeAccount: @json($stripeAccountId)});
        const elements = stripe.elements({clientSecret: form.dataset.secret});
        const style = {base: {color:'#0f172a',fontSize:'16px',fontFamily:'inherit',lineHeight:'24px','::placeholder':{color:'#94a3b8'}},invalid:{color:'#dc2626'}};
        number = elements.create('cardNumber', {showIcon:true,placeholder:'1234 5678 9012 3456',style});
        expiry = elements.create('cardExpiry', {placeholder:'MM / YY',style});
        cvc = elements.create('cardCvc', {placeholder:'CVC',style});
        number.mount('#lease-card-number'); expiry.mount('#lease-card-expiry'); cvc.mount('#lease-card-cvc');
        let complete = {number:false,expiry:false,cvc:false};
        const update = () => { button.disabled = !Object.values(complete).every(Boolean) || postal.value.trim().length < 3; };
        postal.addEventListener('input', update);
        number.on('change', e => { complete.number = e.complete; if(e.error)showError(e.error.message); else error.hidden=true; update(); });
        expiry.on('change', e => { complete.expiry = e.complete; if(e.error)showError(e.error.message); else error.hidden=true; update(); });
        cvc.on('change', e => { complete.cvc = e.complete; if(e.error)showError(e.error.message); else error.hidden=true; update(); });
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!stripe || !number || !expiry || !cvc) return;
        button.disabled = true; button.textContent = 'Processing…'; error.hidden = true;
        const result = await stripe.confirmCardPayment(form.dataset.secret, {
            payment_method: {card:number,billing_details:{address:{postal_code:postal.value.trim()}}}
        });
        if (result.error) {
            showError(result.error.message || 'Payment failed. Please try again.');
            button.disabled = false; button.textContent = 'Pay {{ $money($payAmountCents) }}'; return;
        }
        try {
            const response = await fetch(@json($confirmUrl), {
                method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                body:JSON.stringify({payment_intent_id:result.paymentIntent.id})
            });
            const data = await response.json();
            if(response.ok && data.redirect){window.location.assign(data.redirect);return;}
            showError(data.error || 'Payment received; confirmation is pending. Refresh shortly.');
        } catch(e) { showError('Payment received; confirmation is pending. Refresh shortly.'); }
        button.disabled = false; button.textContent = 'Pay {{ $money($payAmountCents) }}';
    });
})();
</script>
@endpush
@endif

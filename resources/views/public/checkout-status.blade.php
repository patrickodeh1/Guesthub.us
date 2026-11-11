@extends('layouts.public')

@section('title', 'Booking status')
@section('meta_description', 'Review your direct booking payment and reservation status.')
@section('canonical', route('public.checkout.pay', [$booking->booking_id, $booking->token]))

@section('content')
    @php
        $isClosed = in_array($booking->status, ['expired', 'cancelled', 'canceled', 'declined'], true);
        $isPendingRequest = $booking->direct_booking_request && ! $booking->direct_booking_approved_at && ! $booking->payment_confirmed_at;
        $nights = max(1, (int) $booking->check_in_date->diffInDays($booking->check_out_date));
        $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);
        $holdText = $booking->hold_expires_at?->format('M j, g:i A');
    @endphp

    <style>
        .bk{display:grid;gap:28px;max-width:1040px;margin:0 auto;padding-bottom:40px}
        @media(min-width:900px){.bk{grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);align-items:start}.bk-side{position:sticky;top:90px}}
        .bk-main,.bk-side{min-width:0}
        .bk h1{font-size:clamp(1.5rem,3vw,2rem);margin:0 0 6px}
        .bk-ref{color:#66746d;font-size:.85rem;margin:0 0 22px}
        .bk-panel{border:1px solid #dfe6e1;border-radius:18px;padding:24px;background:#fff;box-shadow:0 6px 24px #17221e14}
        .bk-panel h2{margin:0 0 6px;font-size:1.2rem}
        .bk-copy{color:#4a5a52;font-size:.92rem;line-height:1.6;margin:8px 0}
        .bk-big{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;font-size:30px;background:#dcf3e8;color:#1f6f54;margin:0 auto 12px}
        .bk-center{text-align:center}
        .bk-label{display:block;font-size:.8rem;font-weight:700;margin:16px 0 6px}
        .bk-field{border:1px solid #cfd8d2;border-radius:12px;padding:14px;background:#fff;min-height:48px;box-sizing:border-box}
        .bk-field.focus{outline:2px solid #1f6f54;outline-offset:1px;border-color:#1f6f54}
        .bk-input{width:100%;border:1px solid #cfd8d2;border-radius:12px;padding:13px 14px;font:inherit;min-height:48px;box-sizing:border-box}
        .bk-input:focus{outline:2px solid #1f6f54;outline-offset:1px;border-color:#1f6f54}
        .bk-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .bk-btn{width:100%;border:0;border-radius:14px;background:#1f6f54;color:#fff;font:inherit;font-weight:700;font-size:1.02rem;padding:16px;cursor:pointer;margin-top:20px;text-decoration:none;display:block;text-align:center;box-sizing:border-box}
        .bk-btn:hover{background:#185a44}
        .bk-btn[disabled]{background:#b9c4bd;cursor:not-allowed}
        .bk-err{display:none;border:1px solid #f0c4c4;background:#fdf1f1;color:#8a1f1f;border-radius:12px;padding:12px 14px;margin:14px 0 0;font-size:.9rem}
        .bk-err.show{display:block}
        .bk-notice{border:1px solid #cfe5d9;background:#f4faf7;color:#1f5f47;border-radius:12px;padding:12px 14px;margin:0 0 18px;font-size:.9rem}
        .bk-instr{border:1px dashed #b9c4bd;border-radius:14px;padding:16px;margin:14px 0;background:#f8faf9}
        .bk-ref-box{font-weight:800;letter-spacing:.04em;font-size:1.1rem}
        .bk-sumrow{display:flex;justify-content:space-between;gap:12px;font-size:.92rem;margin:0 0 10px}
        .bk-sumrow span:first-child{color:#66746d}
        .bk-total{border-top:1px solid #e1e7e2;padding-top:14px;margin-top:6px;font-weight:800;font-size:1.05rem}
        .bk-total span:first-child{color:#17221e}
        .bk-side h3{margin:0 0 4px;font-size:1.05rem}
        .bk-side .dates{color:#66746d;font-size:.88rem;margin:0 0 16px}
        .bk-link{background:none;border:0;padding:0;font:inherit;color:#66746d;text-decoration:underline;cursor:pointer;margin-top:18px}
        .bk-dialog{border:0;border-radius:20px;padding:24px;width:min(92vw,400px);box-shadow:0 20px 60px #0004}
        .bk-dialog::backdrop{background:#0006}
        .bk-dialog h2{margin:0 0 8px;font-size:1.15rem}
        .bk-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
        .bk-dialog-actions button{border:1px solid #cfd8d2;background:#fff;border-radius:12px;padding:11px 18px;font:inherit;font-weight:650;cursor:pointer}
        .bk-dialog-actions .danger{background:#b42318;border-color:#b42318;color:#fff}
    </style>

    <div class="bk">
        <div class="bk-main">
            <h1>
                @if($isPendingRequest) Request sent to the host
                @elseif($booking->payment_confirmed_at) Your stay is confirmed
                @elseif($isClosed) Booking closed
                @else Complete your booking
                @endif
            </h1>
            <p class="bk-ref">Booking {{ $booking->booking_id }}</p>

            @if(session('status'))
                <div class="bk-notice" role="status">{{ session('status') }}</div>
            @endif

            <div class="bk-panel">
                @if($isPendingRequest)
                    <div class="bk-center"><div class="bk-big" style="background:#fef3c7;color:#b45309">⏱</div></div>
                    <h2 class="bk-center">Waiting for host approval</h2>
                    <p class="bk-copy">The host will review your request. If approved, you will get a link to complete payment. Your request is held until {{ $holdText }}.</p>
                @elseif($booking->payment_confirmed_at)
                    <div class="bk-center"><div class="bk-big">✓</div></div>
                    <h2 class="bk-center">Payment received</h2>
                    <p class="bk-copy bk-center">Your booking details are in the guest portal.</p>
                    <a class="bk-btn" href="{{ route('guest.show', [$booking->booking_id, $booking->token]) }}">Open guest portal</a>
                @elseif($isClosed)
                    <h2>This reservation is no longer active</h2>
                    <p class="bk-copy">The dates have been released. You can look for another stay.</p>
                    <a class="bk-btn" href="{{ route('public.stays.index') }}">Browse stays</a>
                @elseif($booking->payment_method === 'manual')
                    @php($method = $booking->hostPaymentMethod)
                    <h2>Pay with {{ $method?->label ?: 'the host\'s payment method' }}</h2>
                    <p class="bk-copy">Send <strong>{{ $money($booking->total_cents) }}</strong> using the details below. Your dates are held until {{ $holdText }}.</p>
                    <div class="bk-instr">
                        @if($method?->handle)<p class="bk-copy" style="margin-top:0">Send to: <strong>{{ $method->handle }}</strong></p>@endif
                        @if($method?->instructions)<p class="bk-copy">{!! nl2br(e($method->instructions)) !!}</p>@endif
                        <p class="bk-copy" style="margin-bottom:0">Put this reference in the payment note:</p>
                        <div class="bk-ref-box">{{ $booking->booking_id }}</div>
                    </div>
                    <p class="bk-copy">Your booking is confirmed once the host marks your payment as received. Keep this page and your payment receipt.</p>
                @elseif($booking->payment_method === 'card' && $clientSecret)
                    <h2>Pay securely by card</h2>
                    <p class="bk-copy">Your dates are held until {{ $holdText }}.</p>
                    <form id="payment-form" novalidate>
                        <label class="bk-label" for="f-number">Card number</label>
                        <div id="f-number" class="bk-field"></div>
                        <div class="bk-two">
                            <div>
                                <label class="bk-label" for="f-expiry">Expiry date</label>
                                <div id="f-expiry" class="bk-field"></div>
                            </div>
                            <div>
                                <label class="bk-label" for="f-cvc">Security code (CVC)</label>
                                <div id="f-cvc" class="bk-field"></div>
                            </div>
                        </div>
                        <label class="bk-label" for="f-postal">Billing ZIP / postal code</label>
                        <input id="f-postal" class="bk-input" type="text" autocomplete="postal-code" maxlength="12" placeholder="ZIP / Postal code">
                        <div id="payment-error" class="bk-err" role="alert"></div>
                        <button class="bk-btn" id="pay-button" type="submit" disabled>Pay {{ $money($booking->total_cents) }}</button>
                    </form>
                    <p class="bk-copy bk-center" style="font-size:.8rem">Card details go directly to Stripe and never touch this site.</p>
                @else
                    <h2>Awaiting the next step</h2>
                    <p class="bk-copy">This booking is waiting on the host. Check back shortly.</p>
                @endif
            </div>
        </div>

        <aside class="bk-side" aria-label="Booking summary">
            <div class="bk-panel">
                <h3>{{ $booking->property->name }}</h3>
                <p class="dates">{{ $booking->check_in_date->format('M j, Y') }} – {{ $booking->check_out_date->format('M j, Y') }} · {{ $booking->guests_count }} {{ \Illuminate\Support\Str::plural('guest', $booking->guests_count) }}</p>

                <div class="bk-sumrow"><span>{{ $nights }} {{ \Illuminate\Support\Str::plural('night', $nights) }}</span><span>{{ $money($booking->accommodation_cents) }}</span></div>
                @if((int) $booking->tax_cents > 0)
                    <div class="bk-sumrow"><span>Estimated tax</span><span>{{ $money($booking->tax_cents) }}</span></div>
                @endif
                @if((int) $booking->processing_fee_cents > 0)
                    <div class="bk-sumrow"><span>Card processing fee</span><span>{{ $money($booking->processing_fee_cents) }}</span></div>
                @endif
                <div class="bk-sumrow bk-total"><span>Total (USD)</span><span>{{ $money($booking->total_cents) }}</span></div>
            </div>
        </aside>
    </div>
@endsection

@if($clientSecret)
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (() => {
                const stripe = Stripe(@json($publishableKey), {stripeAccount: @json($stripeAccountId)});
                const elements = stripe.elements();
                const style = {
                    base: {color: '#17221e', fontSize: '16px', fontFamily: 'inherit', lineHeight: '24px', '::placeholder': {color: '#94a3b8'}},
                    invalid: {color: '#dc2626'}
                };
                const number = elements.create('cardNumber', {showIcon: true, placeholder: '1234 5678 9012 3456', style});
                const expiry = elements.create('cardExpiry', {placeholder: 'MM / YY', style});
                const cvc = elements.create('cardCvc', {placeholder: 'CVC', style});
                number.mount('#f-number');
                expiry.mount('#f-expiry');
                cvc.mount('#f-cvc');

                const postal = document.getElementById('f-postal');
                const button = document.getElementById('pay-button');
                const error = document.getElementById('payment-error');
                const amountLabel = button.textContent;
                const done = {number: false, expiry: false, cvc: false};

                function showError(message) {
                    error.textContent = message || '';
                    error.classList.toggle('show', !!message);
                }
                function update() {
                    button.disabled = !(done.number && done.expiry && done.cvc && postal.value.trim().length >= 3);
                }
                function wire(el, key, selector, next) {
                    el.on('focus', () => document.querySelector(selector).classList.add('focus'));
                    el.on('blur', () => document.querySelector(selector).classList.remove('focus'));
                    el.on('change', (event) => {
                        done[key] = event.complete;
                        showError(event.error ? event.error.message : '');
                        if (event.complete && next) next();
                        update();
                    });
                }
                wire(number, 'number', '#f-number', () => expiry.focus());
                wire(expiry, 'expiry', '#f-expiry', () => cvc.focus());
                wire(cvc, 'cvc', '#f-cvc', () => postal.focus());
                postal.addEventListener('input', () => { showError(''); update(); });

                document.getElementById('payment-form').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    button.disabled = true;
                    button.textContent = 'Processing...';
                    showError('');

                    const result = await stripe.confirmCardPayment(@json($clientSecret), {
                        payment_method: {
                            card: number,
                            billing_details: {address: {postal_code: postal.value.trim()}}
                        }
                    });
                    if (result.error) {
                        showError(result.error.message || 'Payment could not be completed.');
                        button.textContent = amountLabel;
                        update();
                        return;
                    }

                    const intentId = result.paymentIntent && result.paymentIntent.id;
                    if (!intentId) {
                        showError('Payment submitted. Confirmation is being processed, refresh this page shortly.');
                        return;
                    }
                    try {
                        const response = await fetch(@json(route('public.checkout.confirm', [$booking->booking_id, $booking->token])), {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                            body: JSON.stringify({payment_intent_id: intentId})
                        });
                        const data = await response.json();
                        if (response.ok && data.redirect) { window.location.assign(data.redirect); return; }
                        showError(data.error || 'Payment received. Confirmation is pending, refresh this page shortly.');
                    } catch (e) {
                        showError('Payment received. Confirmation is pending, refresh this page shortly.');
                    }
                    button.textContent = amountLabel;
                });
            })();
        </script>
    @endpush
@endif

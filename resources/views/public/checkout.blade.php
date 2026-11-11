@extends('layouts.public')

@section('title', 'Checkout · '.$listing->public_title)
@section('meta_description', 'Complete your direct booking for '.$listing->public_title.'.')
@section('canonical', route('public.checkout.show', $listing->listing_slug))

@section('content')
    @php
        $isRequest = $listing->booking_mode === 'request';
        $photo = $listing->photos->first();
        $photoUrl = $photo?->url ?? ($photo?->path ? asset('storage/'.$photo->path) : null);
        $defaultPaymentMethod = old('payment_method', $canCard ? 'card' : 'manual');
        $policyText = filled($listing->cancellation_policy_text)
            ? $listing->cancellation_policy_text
            : null;
    @endphp

    <style>
        .co{display:grid;gap:28px;max-width:1040px;margin:0 auto;padding-bottom:40px}
        @media(min-width:900px){.co{grid-template-columns:minmax(0,1.25fr) minmax(0,.85fr);align-items:start}}
        .co-main{order:1;min-width:0}
        .co-side{order:2;min-width:0}
        @media(min-width:900px){.co-side{position:sticky;top:90px}}
        .co h1{font-size:clamp(1.5rem,3vw,2rem);margin:0 0 6px}
        .co-sub{color:#66746d;margin:0 0 24px}
        .co-section{border-bottom:1px solid #e1e7e2;padding:0 0 26px;margin:0 0 26px}
        .co-section h2{font-size:1.2rem;margin:0 0 14px}
        .co-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .co-grid .full{grid-column:1/-1}
        .co-field{display:grid;gap:6px;min-width:0}
        .co-field label{font-size:.8rem;font-weight:700}
        .co-field input{border:1px solid #cfd8d2;border-radius:12px;padding:13px 14px;font:inherit;min-height:48px;width:100%;box-sizing:border-box;background:#fff}
        .co-field input:focus{outline:2px solid #1f6f54;outline-offset:1px;border-color:#1f6f54}
        .co-pay{display:flex;gap:12px;align-items:flex-start;border:1px solid #cfd8d2;border-radius:14px;padding:14px 16px;margin:0 0 10px;cursor:pointer}
        .co-pay:has(input:checked){border-color:#1f6f54;box-shadow:0 0 0 1px #1f6f54;background:#f4faf7}
        .co-pay input{margin-top:4px;width:auto;min-height:0}
        .co-pay strong{display:block}
        .co-pay span{display:block;color:#66746d;font-size:.86rem;margin-top:2px}
        .co-terms{display:flex;gap:10px;align-items:flex-start;font-size:.9rem;line-height:1.5}
        .co-terms input{width:auto;min-height:0;margin-top:4px}
        .co-btn{width:100%;border:0;border-radius:14px;background:#1f6f54;color:#fff;font:inherit;font-weight:700;font-size:1.02rem;padding:16px;cursor:pointer;margin-top:18px}
        .co-btn:hover{background:#185a44}
        .co-btn[disabled]{opacity:.6;cursor:wait}
        .co-note{color:#66746d;font-size:.82rem;text-align:center;margin:10px 0 0}
        .co-card{border:1px solid #dfe6e1;border-radius:18px;box-shadow:0 6px 24px #17221e1a;overflow:hidden;background:#fff}
        .co-card img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}
        .co-card-body{padding:20px}
        .co-card h3{margin:0 0 4px;font-size:1.05rem;line-height:1.3}
        .co-card .place{color:#66746d;font-size:.88rem;margin:0 0 16px}
        .co-rows{display:grid;gap:12px;border-top:1px solid #e1e7e2;padding-top:16px;font-size:.92rem}
        .co-row{display:flex;justify-content:space-between;gap:12px}
        .co-row span:first-child{color:#66746d}
        .co-row strong{text-align:right}
        .co-policy{border-top:1px solid #e1e7e2;margin-top:16px;padding-top:16px;font-size:.85rem;color:#34433c;line-height:1.5}
        .co-policy b{display:block;margin-bottom:4px;color:#17221e}
        .co-error{border:1px solid #f0c4c4;background:#fdf1f1;color:#8a1f1f;border-radius:12px;padding:12px 14px;margin:0 0 20px;font-size:.92rem}
        .co-inline{color:#8a1f1f;font-size:.82rem;min-height:1em}
        .co-hp{position:absolute;left:-10000px}
    </style>

    <div class="co">
        <div class="co-main">
            <h1>{{ $isRequest ? 'Request to book' : 'Confirm and pay' }}</h1>
            <p class="co-sub">
                @if($isRequest)
                    The host reviews your request first. You won't be charged until they approve it.
                @else
                    Your dates are held while you complete payment.
                @endif
            </p>

            @if($errors->any())
                <div class="co-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form id="checkout-form" method="post" action="{{ route('public.checkout.start', $listing->listing_slug) }}" novalidate>
                @csrf
                <div class="co-hp" aria-hidden="true">
                    <label for="checkout-website">Leave this field empty</label>
                    <input id="checkout-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <section class="co-section">
                    <h2>Your trip</h2>
                    <div class="co-grid">
                        <div class="co-field"><label for="check_in">Check-in</label><input id="check_in" name="check_in" type="date" min="{{ $minimumDate }}" value="{{ old('check_in', request('check_in')) }}" required></div>
                        <div class="co-field"><label for="check_out">Check-out</label><input id="check_out" name="check_out" type="date" min="{{ $minimumDate }}" value="{{ old('check_out', request('check_out')) }}" required></div>
                        <div class="co-field full"><label for="guests">Guests (up to {{ $listing->max_guests }})</label><input id="guests" name="guests" type="number" min="1" max="{{ $listing->max_guests }}" value="{{ old('guests', request('guests', 1)) }}" required></div>
                    </div>
                    <div class="co-inline" id="date-error" role="alert"></div>
                </section>

                <section class="co-section">
                    <h2>Your details</h2>
                    <div class="co-grid">
                        <div class="co-field full"><label for="guest_name">Full name</label><input id="guest_name" name="guest_name" value="{{ old('guest_name') }}" required maxlength="255" autocomplete="name"></div>
                        <div class="co-field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></div>
                        <div class="co-field"><label for="phone">Phone (optional)</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></div>
                    </div>
                </section>

                <section class="co-section">
                    <h2>{{ $isRequest ? 'How you would like to pay' : 'Pay with' }}</h2>
                    @if($canCard)
                        <label class="co-pay">
                            <input type="radio" name="payment_method" value="card" @checked($defaultPaymentMethod === 'card')>
                            <div>
                                <strong>Credit or debit card</strong>
                                <span>{{ $isRequest ? 'You pay by card once the host approves.' : 'Pay now by card. Your booking is confirmed instantly.' }}</span>
                            </div>
                        </label>
                    @endif
                    @foreach($manualMethods as $method)
                        <label class="co-pay">
                            <input type="radio" name="payment_method" value="manual" data-manual-method="{{ $method->id }}" @checked($defaultPaymentMethod === 'manual' && (old('host_payment_method_id') ? old('host_payment_method_id') == $method->id : $loop->first))>
                            <div>
                                <strong>{{ $method->label }}</strong>
                                <span>You'll see the payment instructions after you continue. Your dates are held for 24 hours while the host confirms your payment.</span>
                            </div>
                        </label>
                    @endforeach
                    <input type="hidden" name="host_payment_method_id" id="host-payment-method-id" value="{{ old('host_payment_method_id') }}">
                </section>

                <section style="margin-bottom:0">
                    <label class="co-terms">
                        <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                        <span>I agree to the host's house rules and the
                            <a href="{{ route('public.listing.show', $listing->listing_slug) }}#policies" target="_blank" rel="noopener noreferrer">cancellation policy</a>.</span>
                    </label>
                    <button class="co-btn" type="submit" id="co-submit">{{ $isRequest ? 'Send request' : 'Continue to payment' }}</button>
                    <p class="co-note">
                        @if($isRequest)
                            No charge until the host approves your request.
                        @else
                            You won't be charged until the next step.
                        @endif
                    </p>
                </section>
            </form>
        </div>

        <aside class="co-side" aria-label="Booking summary">
            <div class="co-card">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $listing->public_title }}">
                @endif
                <div class="co-card-body">
                    <h3>{{ $listing->public_title }}</h3>
                    <p class="place">{{ $listing->property->city }}@if($listing->property->state), {{ $listing->property->state }}@endif</p>

                    <div class="co-rows">
                        <div class="co-row"><span>Dates</span><strong id="sum-dates">Select your dates</strong></div>
                        <div class="co-row"><span>Guests</span><strong id="sum-guests">1 guest</strong></div>
                        <div class="co-row"><span>Total</span><strong>Shown on the next step</strong></div>
                    </div>

                    <div class="co-policy">
                        <b>Cancellation</b>
                        {{ $policyText ?: 'Refunds follow the host\'s cancellation policy. See the full policy before you book.' }}
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        (() => {
            const form = document.getElementById('checkout-form');
            const ci = document.getElementById('check_in');
            const co = document.getElementById('check_out');
            const guests = document.getElementById('guests');
            const err = document.getElementById('date-error');
            const hostId = document.getElementById('host-payment-method-id');
            const fmt = (v) => v ? new Date(v + 'T00:00:00').toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'}) : null;

            function setHostMethod() {
                const sel = document.querySelector('input[name="payment_method"]:checked');
                hostId.value = sel?.dataset.manualMethod || '';
            }
            document.querySelectorAll('input[name="payment_method"]').forEach((i) => i.addEventListener('change', setHostMethod));
            setHostMethod();

            function nextDay(v) {
                const d = new Date(v + 'T00:00:00'); d.setDate(d.getDate() + 1);
                return d.toISOString().slice(0, 10);
            }
            function refresh() {
                if (ci.value) {
                    const min = nextDay(ci.value);
                    co.min = min;
                    if (co.value && co.value < min) co.value = '';
                }
                const a = fmt(ci.value), b = fmt(co.value);
                document.getElementById('sum-dates').textContent = a && b ? a + ' to ' + b : 'Select your dates';
                const n = parseInt(guests.value || '1', 10);
                document.getElementById('sum-guests').textContent = n + (n === 1 ? ' guest' : ' guests');
                err.textContent = '';
            }
            [ci, co, guests].forEach((el) => el.addEventListener('input', refresh));
            refresh();

            form.addEventListener('submit', (e) => {
                if (ci.value && co.value && co.value <= ci.value) {
                    e.preventDefault();
                    err.textContent = 'Check-out must be after check-in.';
                    co.focus();
                    return;
                }
                if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
                const btn = document.getElementById('co-submit');
                btn.disabled = true; btn.textContent = 'One moment...';
            });
        })();
    </script>
@endsection

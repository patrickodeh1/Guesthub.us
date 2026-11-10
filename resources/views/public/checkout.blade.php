@extends('layouts.public')

@section('title', 'Checkout · '.$listing->public_title)
@section('meta_description', 'Complete your direct booking request for '.$listing->public_title.'.')
@section('canonical', route('public.checkout.show', $listing->listing_slug))

@section('content')
    <div class="eyebrow">Secure checkout</div>
    <h1>Book {{ $listing->public_title }}</h1>
    <p class="lede">Your price is calculated from current rates and fees on the server. Your dates are not reserved until this form is submitted.</p>

    @if($errors->any())
        <div class="empty" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('public.checkout.start', $listing->listing_slug) }}" class="search-panel" style="max-width:760px">
        @csrf
        <div style="position:absolute;left:-10000px" aria-hidden="true">
            <label for="checkout-website">Leave this field empty</label>
            <input id="checkout-website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="search-grid checkout-grid">
            <div class="field"><label for="guest_name">Full name</label><input id="guest_name" name="guest_name" value="{{ old('guest_name') }}" required maxlength="255" autocomplete="name"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></div>
            <div class="field"><label for="phone">Phone (optional)</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></div>
            <div class="field"><label for="guests">Guests</label><input id="guests" name="guests" type="number" min="1" max="{{ $listing->max_guests }}" value="{{ old('guests', request('guests', 1)) }}" required></div>
            <div class="field"><label for="check_in">Check-in</label><input id="check_in" name="check_in" type="date" min="{{ $minimumDate }}" value="{{ old('check_in', request('check_in')) }}" required></div>
            <div class="field"><label for="check_out">Check-out</label><input id="check_out" name="check_out" type="date" min="{{ $minimumDate }}" value="{{ old('check_out', request('check_out')) }}" required></div>
        </div>

        <fieldset style="border:0;padding:0;margin:20px 0">
            <legend style="font-weight:750;margin-bottom:10px">Payment method</legend>
            @php($defaultPaymentMethod = old('payment_method', $canCard ? 'card' : 'manual'))
            @if($canCard)
                <label style="display:flex;align-items:center;gap:8px;margin:9px 0"><input type="radio" name="payment_method" value="card" @checked($defaultPaymentMethod === 'card')> Card</label>
            @endif
            @foreach($manualMethods as $method)
                <label style="display:flex;align-items:center;gap:8px;margin:9px 0">
                    <input type="radio" name="payment_method" value="manual" data-manual-method="{{ $method->id }}" @checked($defaultPaymentMethod === 'manual' && (old('host_payment_method_id') ? old('host_payment_method_id') == $method->id : $loop->first))>
                    {{ $method->label }}
                </label>
            @endforeach
            <input type="hidden" name="host_payment_method_id" id="host-payment-method-id" value="{{ old('host_payment_method_id') }}">
        </fieldset>

        <div class="field" style="margin:18px 0">
            <label style="display:flex;align-items:flex-start;gap:10px">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms')) style="width:auto;min-height:auto;margin-top:4px">
                <span>I accept the host's house rules and the
                    <a href="{{ route('public.listing.show', $listing->listing_slug) }}" target="_blank" rel="noopener noreferrer">cancellation policy</a>.
                    The final price will be calculated using current availability and rates.
                </span>
            </label>
        </div>

        <p class="muted">Card holds last 15 minutes; manual-payment holds last 24 hours. Request-to-book stays require host approval first.</p>
        <button class="button" type="submit">{{ $listing->booking_mode === 'request' ? 'Request to book' : 'Continue to payment' }}</button>
    </form>

    <script>
        const methodInputs = document.querySelectorAll('input[name="payment_method"]');
        const hostMethodId = document.getElementById('host-payment-method-id');
        function setHostMethod() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            hostMethodId.value = selected?.dataset.manualMethod || '';
        }
        methodInputs.forEach((input) => input.addEventListener('change', setHostMethod));
        setHostMethod();
    </script>
@endsection

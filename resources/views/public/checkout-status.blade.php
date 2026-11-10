@extends('layouts.public')

@section('title', 'Booking status · GuestHub')
@section('meta_description', 'Review your direct booking payment and reservation status.')
@section('canonical', route('public.checkout.pay', [$booking->booking_id, $booking->token]))

@section('content')
    <div class="eyebrow">Booking {{ $booking->booking_id }}</div>
    <h1>
        @if($booking->direct_booking_request && ! $booking->direct_booking_approved_at && ! $booking->payment_confirmed_at)
            Request sent to the host
        @elseif($booking->payment_confirmed_at)
            Your stay is confirmed
        @elseif($booking->status === 'expired' || $booking->status === 'cancelled')
            Booking closed
        @else
            Complete your booking
        @endif
    </h1>
    <section class="panel" style="max-width:760px">
        @if(session('status'))
            <p class="status-message" role="status">{{ session('status') }}</p>
        @endif
        <p><strong>{{ $booking->property->name }}</strong></p>
        <p>{{ $booking->check_in_date->format('M j, Y') }} – {{ $booking->check_out_date->format('M j, Y') }} · {{ $booking->guests_count }} guests</p>
        <p>Booking total: <strong>${{ number_format($booking->total_cents / 100, 2) }}</strong></p>

        @if($booking->direct_booking_request && ! $booking->direct_booking_approved_at && ! $booking->payment_confirmed_at)
            <p class="muted">The host will review your request. If approved, you will receive a link to complete payment. Your request hold expires {{ $booking->hold_expires_at?->format('M j, g:i A') }}.</p>
        @elseif($booking->payment_confirmed_at)
            <p class="muted">Payment is confirmed. Your booking details are available in the guest portal.</p>
            <a class="button" href="{{ route('guest.show', [$booking->booking_id, $booking->token]) }}">Open guest portal</a>
        @elseif($booking->status === 'expired' || $booking->status === 'cancelled' || $booking->status === 'declined')
            <p class="muted">This reservation is no longer active.</p>
            <a class="button" href="{{ route('public.stays.index') }}">Browse stays</a>
        @elseif($booking->payment_method === 'manual')
            @php($method = $booking->hostPaymentMethod)
            <h2>Manual payment instructions</h2>
            <p><strong>{{ $method?->label ?: 'Manual payment' }}</strong></p>
            @if($method?->handle)<p>Account or handle: {{ $method->handle }}</p>@endif
            @if($method?->instructions)<p class="detail-copy">{!! nl2br(e($method->instructions)) !!}</p>@endif
            <p>Use booking reference <strong>{{ $booking->booking_id }}</strong>. The host will confirm payment. Your hold expires {{ $booking->hold_expires_at?->format('M j, g:i A') }}.</p>
            <p class="muted">The booking is not confirmed until the host marks payment received.</p>
        @elseif($booking->payment_method === 'card' && $clientSecret)
            <div id="payment-error" class="status-message" role="alert"></div>
            <form id="payment-form">
                <div id="payment-element"></div>
                <button class="button" id="pay-button" type="submit" style="margin-top:16px">Pay ${{ number_format($booking->total_cents / 100, 2) }}</button>
            </form>
            <p class="muted">This secure card payment is processed through the host's connected Stripe account. Your dates are held until {{ $booking->hold_expires_at?->format('M j, g:i A') }}.</p>
        @else
            <p class="muted">This booking is awaiting the next step from the host.</p>
        @endif

        @if(!in_array($booking->status, ['cancelled', 'canceled', 'declined', 'expired'], true))
            <form method="post" action="{{ $cancelUrl }}" style="margin-top:20px" onsubmit="return confirm('Cancel this direct booking? Refund eligibility depends on the listing cancellation policy.')">
                @csrf
                <input type="hidden" name="confirmation" value="1">
                <button type="submit" class="header-link">Cancel booking</button>
            </form>
        @endif
    </section>
@endsection

@if($clientSecret)
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            const stripe = Stripe(@json($publishableKey), {stripeAccount: @json($stripeAccountId)});
            const elements = stripe.elements({clientSecret: @json($clientSecret)});
            elements.create('payment').mount('#payment-element');
            document.getElementById('payment-form').addEventListener('submit', async (event) => {
                event.preventDefault();
                const button = document.getElementById('pay-button');
                const error = document.getElementById('payment-error');
                button.disabled = true;
                error.textContent = '';
                const result = await stripe.confirmPayment({
                    elements,
                    confirmParams: {return_url: window.location.href},
                    redirect: 'if_required'
                });
                if (result.error) {
                    error.textContent = result.error.message || 'Payment could not be completed.';
                    button.disabled = false;
                    return;
                }
                const intentId = result.paymentIntent?.id
                    || new URLSearchParams(window.location.search).get('payment_intent');
                if (!intentId) {
                    error.textContent = 'Payment submitted. Confirmation is being processed.';
                    return;
                }
                const response = await fetch(@json(route('public.checkout.confirm', [$booking->booking_id, $booking->token])), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                    body: JSON.stringify({payment_intent_id: intentId})
                });
                const data = await response.json();
                if (response.ok && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }
                error.textContent = data.error || 'Payment confirmation is pending. Refresh this page shortly.';
                button.disabled = false;
            });
        </script>
    @endpush
@endif

@extends('layouts.public')

@section('title', 'Application status · Lease application')
@section('meta_description', 'Review your lease application status.')
@section('canonical', route('public.lease-application.fee', [$application->id, $application->token]))

@section('content')
    <div class="eyebrow">Lease application</div>
    <h1>Application {{ str_replace('_', ' ', $application->status) }}</h1>
    <section class="panel" style="max-width:760px">
        <p><strong>{{ $application->listing->public_title }}</strong></p>
        <p>Application fee: ${{ number_format($application->total_fee_cents / 100, 2) }} · {{ ucfirst($application->fee_status) }}</p>
        @if($application->fee_status === 'paid')
            <p class="status-message">Your application fee is received. The host will contact you with any updates.</p>
        @elseif($application->payment_method === 'manual')
            <h2>Manual payment instructions</h2>
            <p><strong>{{ $application->hostPaymentMethod?->label ?: 'Manual payment' }}</strong></p>
            @if($application->hostPaymentMethod?->handle)<p>Account or handle: {{ $application->hostPaymentMethod->handle }}</p>@endif
            @if($application->hostPaymentMethod?->instructions)<p class="detail-copy">{!! nl2br(e($application->hostPaymentMethod->instructions)) !!}</p>@endif
            <p>Use your name and application reference <strong>{{ $application->id }}</strong>. Your application fee will be marked received after the host confirms payment.</p>
        @elseif($clientSecret)
            <div id="payment-error" class="status-message" role="alert"></div>
            <form id="payment-form">
                <div id="payment-element"></div>
                <button class="button" id="pay-button" type="submit" style="margin-top:16px">Pay ${{ number_format($application->total_fee_cents / 100, 2) }}</button>
            </form>
            <p class="muted">This secure card payment is processed through the host’s connected Stripe account. The application fee is non-refundable unless the host states otherwise.</p>
        @elseif($application->fee_status === 'failed')
            <p class="status-message">Payment was not completed. Contact the host for assistance.</p>
            @if($application->payment_method === 'card')
                <form method="post" action="{{ route('public.lease-application.retry-fee', [$application->id, $application->token]) }}">
                    @csrf
                    <button class="button" type="submit">Retry card payment</button>
                </form>
            @endif
        @endif
        @if(in_array($application->status, ['approved', 'declined'], true))
            <p>The host updated your application to <strong>{{ str_replace('_', ' ', $application->status) }}</strong>.</p>
        @endif
        @if($application->screening_requested_at)
            <p class="muted">The host has requested that you complete screening using their provider.</p>
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
                const response = await fetch(@json(route('public.lease-application.confirm', [$application->id, $application->token])), {
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

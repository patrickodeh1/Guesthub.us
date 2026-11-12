@extends('layouts.public')

@section('title', 'Rental offer')
@section('meta_description', 'Review and sign your rental offer.')

@section('content')
@php
    $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);
    $contract = (string) $offer->contract_html;
    $values = [
        '[[applicant_name]]' => e($offer->application->applicant_name),
        '[[host_name]]' => e($offer->property->owner?->host_name ?: $offer->property->owner?->name),
        '[[property_name]]' => e($offer->property->name),
        '[[property_address]]' => e($offer->property->address ?? ''),
        '[[start_date]]' => e($offer->start_date->format('M j, Y')),
        '[[end_date]]' => e($offer->end_date->format('M j, Y')),
        '[[nights]]' => (string) $offer->nights(),
        '[[monthly_rent]]' => $money($offer->monthly_rent_cents),
        '[[deposit]]' => $money($offer->deposit_cents),
        '[[total]]' => $money($offer->total_cents),
        '[[initial_due]]' => $money($offer->initial_due_cents),
        '[[payment_schedule]]' => '',
        '[[host_signed_name]]' => e($offer->host_signed_name),
        '[[host_signed_at]]' => e($offer->host_signed_at?->format('M j, Y')),
        '[[tenant_signed_name]]' => e($offer->guest_signed_name ?: 'Awaiting tenant signature'),
        '[[tenant_signed_at]]' => e($offer->guest_signed_at?->format('M j, Y') ?: 'Pending'),
        '[[tenant_signed_ip]]' => e($offer->guest_signed_ip ?: 'Pending'),
        '[[agreement_version]]' => e($offer->contract_version),
    ];
    $contract = strtr($contract, $values);
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
                <form method="post" action="{{ route('public.lease-offer.sign', [$offer->application_id, $offer->application->token, $offer->token]) }}" style="display:grid;gap:12px;margin-top:14px">
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
                <h2 style="font-size:1.15rem;font-weight:750">{{ $offer->status === 'confirmed' ? 'Next rental payment' : 'Initial payment' }}</h2>
                @if($nextInstallment && $offer->payment_method === 'manual')
                    <p class="lease-muted">Send {{ $nextInstallment ? $money($nextInstallment->amount_cents) : $money($offer->initial_due_cents) }} using the host's payment method. Include your application reference <strong>{{ $offer->application_id }}</strong>. The host records each payment after receiving it.</p>
                    @if($offer->hostPaymentMethod)
                        <p style="margin-top:12px"><strong>{{ $offer->hostPaymentMethod->label }}</strong><br>{{ $offer->hostPaymentMethod->handle }}</p>
                        @if($offer->hostPaymentMethod->instructions)<p class="lease-muted">{{ $offer->hostPaymentMethod->instructions }}</p>@endif
                    @endif
                @elseif($nextInstallment)
                    <p class="lease-muted">Pay the next installment of {{ $money($nextInstallment->amount_cents) }} by card.</p>
                    <form id="lease-payment-form" style="margin-top:14px">
                        <div id="lease-card" class="lease-input" style="min-height:46px"></div>
                        <p id="lease-payment-error" class="lease-error" role="alert"></p>
                        <button id="lease-pay-button" class="lease-button" style="margin-top:12px">Pay {{ $money($nextInstallment->amount_cents) }}</button>
                    </form>
                @endif
                @if($offer->status === 'confirmed' && !$nextInstallment)
                    <p class="lease-muted">Your rental is confirmed. You can now use your guest portal for check-in, parking, documents and stay information.</p>
                @elseif($offer->status === 'signed' && $offer->installments->where('required_at_signing', true)->every(fn($i) => $i->isSettled()))
                    <p class="lease-muted">All required initial payments are recorded. Your reservation confirmation is being finalized.</p>
                @endif
            </section>
        @endif
        @if($offer->status === 'confirmed')
            <section class="lease-card"><h2 style="font-size:1.15rem;font-weight:750">Your rental is confirmed</h2><p class="lease-muted">Your booking is active. Use the guest portal for check-in, parking, documents and stay services.</p>@if($offer->booking)<a class="lease-button" style="display:block;text-align:center;text-decoration:none;margin-top:12px" href="{{ route('guest.show', [$offer->booking->booking_id, $offer->booking->token]) }}">Open guest portal</a>@endif</section>
        @endif
        @if($offer->status === 'sent')
            <form method="post" action="{{ route('public.lease-offer.decline', [$offer->application_id, $offer->application->token, $offer->token]) }}" class="lease-card">
                @csrf
                <button type="submit" style="background:none;border:0;color:#9b3434;text-decoration:underline;cursor:pointer">Decline this offer</button>
            </form>
        @endif
    @endif
</div>
@endsection

@if($clientSecret && $nextInstallment)
@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
(() => {
    const stripe = Stripe(@json($publishableKey), {stripeAccount: @json($stripeAccountId)});
    const elements = stripe.elements();
    const card = elements.create('card', {hidePostalCode: true});
    card.mount('#lease-card');
    const form = document.getElementById('lease-payment-form');
    const button = document.getElementById('lease-pay-button');
    const error = document.getElementById('lease-payment-error');
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); button.disabled = true; button.textContent = 'Processing…'; error.textContent = '';
        const result = await stripe.confirmCardPayment(@json($clientSecret), {payment_method: {card}});
        if (result.error) { error.textContent = result.error.message || 'Payment failed.'; button.disabled = false; button.textContent = 'Pay {{ $money($nextInstallment->amount_cents) }}'; return; }
        try {
            const response = await fetch(@json(route('public.lease-offer.confirm', [$offer->application_id, $offer->application->token, $offer->token])), {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                body: JSON.stringify({payment_intent_id: result.paymentIntent.id})
            });
            const data = await response.json();
            if (response.ok && data.redirect) { window.location.assign(data.redirect); return; }
            error.textContent = data.error || 'Payment received; confirmation is pending. Refresh shortly.';
        } catch (e) { error.textContent = 'Payment received; confirmation is pending. Refresh shortly.'; }
        button.disabled = false; button.textContent = 'Pay {{ $money($nextInstallment->amount_cents) }}';
    });
})();
</script>
@endpush
@endif

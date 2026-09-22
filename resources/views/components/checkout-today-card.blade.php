@props(['booking', 'hasSteps' => false, 'linkOnly' => false])

<div {{ $attributes->merge(['class' => '']) }}>
    <h1 class="guest-status-title">Checking out today</h1>
    <p class="mt-2 text-sm leading-6 text-slate-600">Check-out time is {{ $booking->effectiveCheckoutTimeFormatted() }}. You can still use the guide until then.</p>
    @if($linkOnly)
        {{-- Category detail pages don't have the guide page's wizard/confirm-form
             elements, so this takes the guest back to the main guide page, where
             the real "begin checkout" control (wizard toggle or confirm form) is. --}}
        <a href="{{ route('guest.show', [$booking->booking_id, $booking->token]) }}" class="guest-primary-btn w-full is-go inline-flex items-center justify-center text-center">Thanks for staying. Time to check out. Click here to begin.</a>
    @elseif($hasSteps)
        <button type="button" onclick="document.getElementById('checkout-guide-section').style.display='none';document.getElementById('checkout-wizard-wrapper').style.display='';" class="guest-primary-btn w-full is-go">Thanks for staying. Time to check out. Click here to begin.</button>
    @else
        <form method="POST" action="{{ route('guest.confirm-checkout', [$booking->booking_id, $booking->token]) }}">
            @csrf
            <button type="submit" class="guest-primary-btn w-full is-go">Thanks for staying. Time to check out. Click here to begin.</button>
        </form>
    @endif
</div>

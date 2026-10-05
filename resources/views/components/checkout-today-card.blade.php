@props(['booking', 'hasSteps' => false, 'linkOnly' => false])

<div {{ $attributes->merge(['class' => '']) }}>
    <h1 class="guest-status-title">{{ $booking->isCheckoutDay() ? 'Checking out today' : 'Checking out tomorrow' }}</h1>
    @if(session('error'))<p class="mt-2 text-sm font-semibold text-red-600">{{ session('error') }}</p>@endif
    <p class="mt-2 text-sm leading-6 text-slate-600">Check-out time is {{ $booking->effectiveCheckoutTimeFormatted() }}{{ $booking->isCheckoutDay() ? '' : ' tomorrow' }}. You can still use the guide until then.</p>
    @if($linkOnly)
        {{-- Category pages have no wizard, so this sends the guest to the main guide page, which starts checkout. --}}
        <a href="{{ route('guest.show', [$booking->booking_id, $booking->token]) }}?begin_checkout=1#checkout-guide-section" class="guest-primary-btn w-full is-go inline-flex items-center justify-center text-center">Thanks for staying. Time to check out. Click here to begin.</a>
    @elseif($hasSteps)
        <button type="button" onclick="document.getElementById('checkout-guide-section').style.display='none';document.getElementById('checkout-wizard-wrapper').style.display='';" class="guest-primary-btn w-full is-go">Thanks for staying. Time to check out. Click here to begin.</button>
    @else
        <form id="checkout-plain-form" method="POST" action="{{ route('guest.confirm-checkout', [$booking->booking_id, $booking->token]) }}" onsubmit="return confirm('Checking out ends your access to the guest guide, door codes and the unit. Are you ready to check out now?')">
            @csrf
            <button type="submit" class="guest-primary-btn w-full is-go">Thanks for staying. Time to check out. Click here to begin.</button>
        </form>
    @endif
    @if(! $linkOnly)
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (new URLSearchParams(window.location.search).get('begin_checkout') !== '1') return;
        history.replaceState(null, '', window.location.pathname);
        var guide = document.getElementById('checkout-guide-section');
        var wizard = document.getElementById('checkout-wizard-wrapper');
        var plain = document.getElementById('checkout-plain-form');
        if (guide && wizard) { guide.style.display = 'none'; wizard.style.display = ''; window.scrollTo(0, 0); }
        else if (plain) { plain.requestSubmit ? plain.requestSubmit() : plain.submit(); }
    });
    </script>
    @endif
</div>

@php
    $platformCurrent = old('booking_platform', $booking->booking_platform ?: ($booking->exists ? '' : 'Airbnb'));
    $platformOptions = \App\Models\Booking::PLATFORMS;
@endphp
<label class="field-label">Booking platform
    <select name="booking_platform" class="input">
        @if($booking->exists && $platformCurrent === '')<option value="">— Not set —</option>@endif
        @if($platformCurrent !== '' && ! array_key_exists($platformCurrent, $platformOptions))<option value="{{ $platformCurrent }}" selected>{{ $platformCurrent }}</option>@endif
        @foreach($platformOptions as $value => $label)<option value="{{ $value }}" @selected($platformCurrent === $value)>{{ $label }}</option>@endforeach
    </select>
    <span class="field-help">Shown to the guest on the payment screen ("Pay on …"). Filled automatically for channel-manager bookings.</span>
</label>

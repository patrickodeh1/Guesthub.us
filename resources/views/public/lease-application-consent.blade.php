@extends('layouts.public')

@section('title', 'Consent and fee · Lease application')
@section('meta_description', 'Review screening consent and submit your application.')

@section('content')
    <div class="eyebrow">Lease application · Step 4 of 4</div>
    <h1>Consent and application fee</h1>
    <section class="panel" style="max-width:760px">
        <p>By submitting, you authorize the host to request rental screening through their screening provider. No Social Security number or full date of birth is collected by this application.</p>
        <p class="muted">The application fee is non-refundable unless the host states otherwise. Hosts are responsible for complying with applicable local application-fee limits.</p>
        @if($application->listing->screening_provider_url)
            <p><a href="{{ $application->listing->screening_provider_url }}" target="_blank" rel="noopener noreferrer">View the host’s screening provider</a></p>
        @endif
        <p>Application fee: <strong>${{ number_format($application->listing->lease_application_fee_cents / 100, 2) }}</strong>@if($canCard) <span class="muted">A card processing fee may apply.</span>@endif</p>
        <form method="post" action="{{ route('public.lease-application.submit', [$application->id, $application->token]) }}">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
            <div class="field">
                <label><input type="checkbox" name="screening_consent" value="1" required @checked(old('screening_consent'))> I authorize the host to request screening and agree to the application terms.</label>
                @error('screening_consent')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="signature">Type your full name as your signature</label>
                <input id="signature" name="signature" value="{{ old('signature', $application->applicant_name) }}" required maxlength="255">
                @error('signature')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            </div>
            @if($canCard || $manualMethods->isNotEmpty())
                <fieldset class="field">
                    <legend>Application fee payment</legend>
                    @if($canCard)
                        <label><input type="radio" name="payment_method" value="card" @checked(old('payment_method', 'card') === 'card')> Card via Stripe</label>
                    @endif
                    @if($manualMethods->isNotEmpty())
                        <label><input type="radio" name="payment_method" value="manual" @checked(old('payment_method') === 'manual' || ! $canCard)> Manual payment</label>
                        <select name="host_payment_method_id" aria-label="Manual payment method">
                            @foreach($manualMethods as $method)
                                <option value="{{ $method->id }}" @selected(old('host_payment_method_id') == $method->id)>{{ $method->label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('payment_method')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    @error('host_payment_method_id')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                </fieldset>
            @else
                <input type="hidden" name="payment_method" value="card">
            @endif
            @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            <button class="button" type="submit" style="margin-top:16px">Submit application</button>
        </form>
    </section>
@endsection

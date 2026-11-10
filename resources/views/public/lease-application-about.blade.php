@extends('layouts.public')

@section('title', 'About you · Lease application')
@section('meta_description', 'Add your move-in and household details.')

@section('content')
    <div class="eyebrow">Lease application · Step 2 of 4</div>
    <h1>About you and move-in</h1>
    <section class="panel" style="max-width:760px">
        @if($application->id_scan_status === 'manual_review')
            <p class="status-message">Your ID scan needs manual host review. You can continue with the application.</p>
        @endif
        <p><strong>Scanned name:</strong> {{ $application->id_name ?: 'Not read; host review required' }}</p>
        <p><strong>Scanned address:</strong> {{ $application->id_address ?: 'Not read; host review required' }}</p>
        <p><strong>Scanned ID number:</strong> {{ $application->id_number_last4 ? '•••• '.$application->id_number_last4 : 'Not read; host review required' }}</p>
        <form method="post" action="{{ route('public.lease-application.about.store', [$application->id, $application->token]) }}">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
            <div class="field"><label for="applicant_name">Full name</label><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $application->applicant_name ?: $application->id_name) }}" required maxlength="255">@error('applicant_name')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="applicant_email">Email</label><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', $application->applicant_email) }}" required maxlength="255">@error('applicant_email')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="applicant_phone">Phone</label><input id="applicant_phone" name="applicant_phone" type="tel" value="{{ old('applicant_phone', $application->applicant_phone) }}" required maxlength="40">@error('applicant_phone')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="move_in_date">Desired move-in date</label><input id="move_in_date" name="move_in_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('move_in_date', $application->move_in_date?->format('Y-m-d')) }}" required>@error('move_in_date')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="lease_term_months">Lease term (months)</label><input id="lease_term_months" name="lease_term_months" type="number" min="1" max="600" value="{{ old('lease_term_months', $application->lease_term_months) }}" required>@error('lease_term_months')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="occupants">Number of occupants</label><input id="occupants" name="occupants" type="number" min="1" max="30" value="{{ old('occupants', $application->occupants) }}" required>@error('occupants')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="pets">Pets</label><textarea id="pets" name="pets" maxlength="1000">{{ old('pets', $application->pets) }}</textarea></div>
            <div class="field"><label for="vehicles">Vehicles</label><textarea id="vehicles" name="vehicles" maxlength="1000">{{ old('vehicles', $application->vehicles) }}</textarea></div>
            <div class="field"><label for="current_address">Current address</label><textarea id="current_address" name="current_address" required maxlength="1000">{{ old('current_address', $application->current_address) }}</textarea>@error('current_address')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="current_address_months">Time at current address (months)</label><input id="current_address_months" name="current_address_months" type="number" min="0" max="1200" value="{{ old('current_address_months', $application->current_address_months) }}" required>@error('current_address_months')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            <button class="button" type="submit" style="margin-top:16px">Continue</button>
        </form>
    </section>
@endsection

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
        @if($application->id_name || $application->id_address || $application->id_number_last4)
            @if($application->id_name)<p><strong>Name on ID:</strong> {{ $application->id_name }}</p>@endif
            @if($application->id_address)<p><strong>Address on ID:</strong> {{ $application->id_address }}</p>@endif
            @if($application->id_number_last4)<p><strong>ID number:</strong> •••• {{ $application->id_number_last4 }}</p>@endif
        @else
            <p class="status-message">We could not read your ID automatically. Please enter your details below. The host will verify them against your ID.</p>
        @endif
        <form class="lease-about-form" method="post" action="{{ route('public.lease-application.about.store', [$application->id, $application->token]) }}">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
            <div class="field"><label for="applicant_name">Full name</label><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $application->applicant_name ?: $application->id_name) }}" required maxlength="255" @if($application->id_name) readonly style="background:#f1f5f9" @endif>@error('applicant_name')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="applicant_email">Email</label><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', $application->applicant_email) }}" required maxlength="255">@error('applicant_email')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="applicant_phone">Phone</label>@include('partials.phone-country-picker', ['id' => 'applicant_phone', 'name' => 'applicant_phone', 'value' => old('applicant_phone', $application->applicant_phone), 'inputClass' => 'input'])@error('applicant_phone')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="move_in_date">Desired move-in date</label><input id="move_in_date" name="move_in_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('move_in_date', $application->move_in_date?->format('Y-m-d')) }}" required>@error('move_in_date')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="requested_end_date">Desired move-out date (optional)</label><input id="requested_end_date" name="requested_end_date" type="date" min="{{ $application->move_in_date?->copy()->addDay()->toDateString() }}" value="{{ old('requested_end_date', $application->requested_end_date?->format('Y-m-d')) }}">@error('requested_end_date')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="lease_term_months">Lease term (months)</label><input id="lease_term_months" name="lease_term_months" type="number" min="1" max="600" value="{{ old('lease_term_months', $application->lease_term_months) }}" required>@error('lease_term_months')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="occupants">Number of occupants</label><input id="occupants" name="occupants" type="number" min="1" max="30" value="{{ old('occupants', $application->occupants) }}" required>@error('occupants')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            @php
                $petsSaved = old('pets', $application->pets);
                $hasPets = $petsSaved && ! str_starts_with(strtolower($petsSaved), 'no');
                $petsDetail = $hasPets ? preg_replace('/^yes:\s*/i', '', $petsSaved) : '';
            @endphp
            <div class="field">
                <label>Do you have pets?</label>
                <label style="display:inline-flex;gap:6px;margin-right:16px"><input type="radio" name="has_pets" value="no" @checked(! $hasPets)> No</label>
                <label style="display:inline-flex;gap:6px"><input type="radio" name="has_pets" value="yes" @checked($hasPets)> Yes</label>
            </div>
            <div class="field" id="pets-detail-field" @if(! $hasPets) style="display:none" @endif>
                <label for="pets_detail">Type and number of pets</label>
                <input id="pets_detail" type="text" maxlength="200" placeholder="e.g. 1 dog, 2 cats" value="{{ $petsDetail }}">
            </div>
            <input type="hidden" name="pets" id="pets" value="{{ $petsSaved }}">
            <div class="field"><label for="current_address">Current address</label><textarea id="current_address" name="current_address" required maxlength="1000">{{ old('current_address', $application->current_address) }}</textarea>@error('current_address')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="current_address_months">Time at current address in months (optional)</label><input id="current_address_months" name="current_address_months" type="number" min="0" max="1200" value="{{ old('current_address_months', $application->current_address_months) }}">@error('current_address_months')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            <button class="button" type="submit" style="margin-top:16px">Continue</button>
        </form>
        <script>
        (function () {
            var form = document.getElementById('pets').form;
            var radios = form.querySelectorAll('input[name="has_pets"]');
            var box = document.getElementById('pets-detail-field');
            var detail = document.getElementById('pets_detail');
            var hidden = document.getElementById('pets');
            function sync() {
                var yes = form.querySelector('input[name="has_pets"]:checked').value === 'yes';
                box.style.display = yes ? '' : 'none';
                detail.required = yes;
                hidden.value = yes ? 'Yes: ' + detail.value.trim() : 'No';
            }
            radios.forEach(function (r) { r.addEventListener('change', sync); });
            detail.addEventListener('input', sync);
            sync();
        })();
        </script>
    </section>
@endsection

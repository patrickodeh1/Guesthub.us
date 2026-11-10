@extends('layouts.public')

@section('title', 'Employment · Lease application')
@section('meta_description', 'Add employment and reference details.')

@section('content')
    <div class="eyebrow">Lease application · Step 3 of 4</div>
    <h1>Employment and references</h1>
    <section class="panel" style="max-width:760px">
        <form method="post" action="{{ route('public.lease-application.employment.store', [$application->id, $application->token]) }}">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
            <div class="field"><label for="employer">Employer</label><input id="employer" name="employer" value="{{ old('employer', $application->employer) }}" required maxlength="255">@error('employer')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="job_title">Job title</label><input id="job_title" name="job_title" value="{{ old('job_title', $application->job_title) }}" required maxlength="255">@error('job_title')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="supervisor_name">Supervisor name</label><input id="supervisor_name" name="supervisor_name" value="{{ old('supervisor_name', $application->supervisor_name) }}" maxlength="255"></div>
            <div class="field"><label for="supervisor_phone">Supervisor phone</label><input id="supervisor_phone" name="supervisor_phone" type="tel" value="{{ old('supervisor_phone', $application->supervisor_phone) }}" maxlength="40"></div>
            <div class="field"><label for="monthly_income">Monthly income</label><input id="monthly_income" name="monthly_income" inputmode="decimal" value="{{ old('monthly_income', $application->monthly_income_cents !== null ? number_format((int) $application->monthly_income_cents / 100, 2, '.', '') : '') }}" required>@error('monthly_income')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="years_employed">Years employed</label><input id="years_employed" name="years_employed" type="number" min="0" max="100" step="0.1" value="{{ old('years_employed', $application->years_employed) }}" required>@error('years_employed')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="previous_landlord_name">Previous landlord name</label><input id="previous_landlord_name" name="previous_landlord_name" value="{{ old('previous_landlord_name', $application->previous_landlord_name) }}" maxlength="255"></div>
            <div class="field"><label for="previous_landlord_phone">Previous landlord phone</label><input id="previous_landlord_phone" name="previous_landlord_phone" type="tel" value="{{ old('previous_landlord_phone', $application->previous_landlord_phone) }}" maxlength="40"></div>
            <div class="field"><label for="emergency_contact_name">Emergency contact</label><input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $application->emergency_contact_name) }}" required maxlength="255">@error('emergency_contact_name')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="emergency_contact_phone">Emergency contact phone</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $application->emergency_contact_phone) }}" required maxlength="40">@error('emergency_contact_phone')<span class="field-help text-red-700">{{ $message }}</span>@enderror</div>
            @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            <button class="button" type="submit" style="margin-top:16px">Continue</button>
        </form>
    </section>
@endsection

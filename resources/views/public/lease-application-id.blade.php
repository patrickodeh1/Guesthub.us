@extends('layouts.public')

@section('title', 'Lease application · '.$listing->public_title)
@section('meta_description', 'Apply for a long-term stay.')

@section('content')
    <div class="eyebrow">Lease application · Step 1 of 4</div>
    <h1>Scan your ID</h1>
    <section class="panel" style="max-width:760px">
        @if(session('status'))<p class="status-message" role="status">{{ session('status') }}</p>@endif
        <p class="muted">Upload a clear photo of a government ID. The image is stored privately for the host’s review. We do not save your full ID number or date of birth.</p>
        @if($application && $application->id_scan_status === 'manual_review')
            <p class="status-message">We could not read the ID after two attempts. You may continue; the host will review the image manually.</p>
        @endif
        @if(! $application || $application->id_scan_attempts < 2)
        <form method="post" enctype="multipart/form-data" action="{{ $application ? route('public.lease-application.id.retry.store', [$application->id, $application->token]) : route('public.lease-application.id', $listing->listing_slug) }}">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
            @if($application)
                <p><strong>ID name:</strong> {{ $application->id_name ?: 'Not read; host review required' }}</p>
                <p><strong>ID address:</strong> {{ $application->id_address ?: 'Not read; host review required' }}</p>
                <p><strong>ID number:</strong> {{ $application->id_number_last4 ? '•••• '.$application->id_number_last4 : 'Not read; host review required' }}</p>
            @endif
            <div class="field">
                <label for="id_scan">Government ID image</label>
                <input id="id_scan" name="id_scan" type="file" accept="image/jpeg,image/png,image/webp" required>
                @error('id_scan')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            </div>
            @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
            <button type="submit" class="button" style="margin-top:16px">{{ $application ? 'Retry scan' : 'Continue' }}</button>
        </form>
        @endif
        @if($application && $application->id_scan_status === 'manual_review')
            <a class="button" href="{{ route('public.lease-application.about', [$application->id, $application->token]) }}">Continue</a>
        @endif
    </section>
@endsection

@extends('layouts.public')

@section('title', 'Lease application · '.$listing->public_title)
@section('meta_description', 'Apply for a long-term stay.')

@section('content')
    @php
        $isPassport = (bool) $listing->lease_allow_passport && (request('id') === 'passport' || ($application && $application->id_type === 'passport'));
        $uploadUrl = $application
            ? route('public.lease-application.id.retry.photos', [$application->id, $application->token])
            : route('public.lease-application.id.photos', $listing->listing_slug);
    @endphp
    <style>
        .hidden{display:none!important}
        .id-tile{display:block;max-width:420px;width:100%;border:2px dashed #94a3b8;border-radius:12px;padding:8px;cursor:pointer;margin-top:12px}
        .id-tile img{width:100%;border-radius:8px;display:block}
        #camera-container{position:relative;width:100%;max-width:520px;aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden}
        #camera-stream{width:100%;height:100%;object-fit:cover}
        .id-preview{max-width:420px;width:100%;border-radius:12px}
        .link-btn{background:none;border:0;padding:0;margin-top:8px;color:#2563eb;text-decoration:underline;font-size:.9rem;cursor:pointer}
    </style>
    <div class="eyebrow">Lease application · Step 1 of 4</div>
    <h1>Scan your ID</h1>
    <section class="panel" style="max-width:760px">
        @if(session('status'))<p class="status-message" role="status">{{ session('status') }}</p>@endif
        <p class="muted">We require your ID to verify your identity. All submitted details are kept strictly private.</p>
        @if($application && $application->id_scan_status === 'manual_review')
            <p class="status-message">We could not read the ID after two attempts. You may continue; the host will review the image manually.</p>
        @endif
        @if(! $application || $application->id_scan_attempts < 2)
            @if($listing->lease_allow_passport && ! $application)
                <p>
                    @if($isPassport)
                        <a href="{{ route('public.lease-application.apply', $listing->listing_slug) }}">Use a state ID or driver's license instead</a>
                    @else
                        <a href="{{ route('public.lease-application.apply', $listing->listing_slug) }}?id=passport">Use a passport instead</a>
                    @endif
                </p>
            @endif
            @if($application && $application->id_scan_status === 'retry')
                <p class="status-message">We couldn't read your ID. Retake both photos in good light with all four edges visible, then try once more.</p>
            @endif
            <div id="id-capture-section">
                <div id="idw-desktop-notice" class="hidden" style="text-align:center">
                    <p><strong>It's easier to snap this on your phone</strong></p>
                    <p class="muted">Scan the code below with your phone's camera to pick up where you left off.</p>
                    <div id="idw-qr-canvas" style="width:180px;height:180px;margin:16px auto;display:flex;align-items:center;justify-content:center"></div>
                    <p class="muted">Or open this link on your phone:</p>
                    <p id="idw-qr-link" style="word-break:break-all"></p>
                    <button type="button" id="idw-continue-desktop-btn" class="link-btn">No phone? Continue on desktop</button>
                </div>
                <div id="idw-mobile-capture-ui">
                    <div id="camera-container" class="hidden">
                        <video id="camera-stream" autoplay playsinline muted></video>
                        <p id="camera-instruction-label" style="position:absolute;top:8px;left:0;right:0;text-align:center;color:#fff;font-weight:700"></p>
                    </div>
                    <div id="capture-btn-wrapper" class="hidden"><p id="idw-capture-status">Loading camera…</p></div>
                    <div id="front-preview-block" class="hidden">
                        <p class="muted">{{ $isPassport ? 'Passport data page' : 'Front of ID' }}</p>
                        <img id="front-preview" class="id-preview" alt="">
                        <p id="front-blur-warning" class="hidden text-red-700">Image is blurry. Please retake.</p>
                        <button type="button" id="retake-front-btn" class="link-btn">Retake</button>
                    </div>
                    <div id="back-preview-block" class="hidden">
                        <p class="muted">Back of ID</p>
                        <img id="back-preview" class="id-preview" alt="">
                        <p id="back-blur-warning" class="hidden text-red-700">Image is blurry. Please retake.</p>
                        <button type="button" id="retake-back-btn" class="link-btn">Retake back</button>
                    </div>
                    <div id="upload-zone-trigger" class="id-tile{{ $isPassport ? ' is-passport' : '' }}" onclick="startCamera('front')">
                        <img src="{{ asset($isPassport ? 'id_icons/passportID.png' : 'id_icons/frontID.jpg') }}" alt="{{ $isPassport ? 'Passport example' : 'Front of ID example' }}">
                    </div>
                    <p id="upload-zone-trigger-front-label"><strong>{{ $isPassport ? 'Tap to take a photo of the passport data page' : 'Tap to take a photo of the front of your ID' }}</strong></p>
                    <div id="upload-zone-trigger-back" class="id-tile hidden" onclick="startCamera('back')">
                        <img src="{{ asset('id_icons/backID.jpg') }}" alt="Back of ID example">
                    </div>
                    <p id="upload-zone-trigger-back-label" class="hidden"><strong>Tap to take a photo of the back of your ID</strong></p>
                    <input type="hidden" id="photo-id-data">
                    <input type="hidden" id="photo-id-back-data">
                </div>
                @error('application')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                <button type="button" class="button hidden" id="id-capture-next-btn" style="margin-top:16px">{{ $application ? 'Retry scan' : 'Continue' }}</button>
            </div>
            <script>
            (function () {
                var nb = document.getElementById("id-capture-next-btn"), f = document.getElementById("photo-id-data"), b = document.getElementById("photo-id-back-data"), pass = @json($isPassport);
                setInterval(function () { nb.classList.toggle("hidden", !(f.value && (pass || b.value))); }, 400);
            })();
            </script>
            <script>window.LEASE_ID_CONFIG = @json(['isPassport' => $isPassport, 'uploadUrl' => $uploadUrl, 'csrf' => csrf_token()]);</script>
            <script src="{{ asset('js/lease-id-capture.js') }}?v={{ filemtime(public_path('js/lease-id-capture.js')) }}"></script>
        @endif
        @if($application && $application->id_scan_status === 'manual_review')
            <a class="button" href="{{ route('public.lease-application.about', [$application->id, $application->token]) }}">Continue</a>
        @endif
    </section>
@endsection

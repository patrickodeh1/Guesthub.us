@props(['booking', 'property', 'title' => 'Guest Welcome', 'state' => null])

@php
    $favicon = \App\Support\Branding::faviconUrl();
    $weather = ($property->latitude && $property->longitude)
        ? app(\App\Services\WeatherService::class)->getCurrent((float) $property->latitude, (float) $property->longitude)
        : null;
@endphp

<!doctype html>
<html lang="en" data-theme="light" style="color-scheme:light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.theme-init')
    <title>{{ $title }} - {{ $property->name }} · {{ \App\Support\Branding::siteName() }}</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ \App\Support\Branding::siteName() }}">
    <meta property="og:title" content="{{ $property->guest_display_name }}">
    <meta property="og:description" content="Check-in and stay details for {{ $property->guest_display_name }}">
    <meta property="og:image" content="{{ $property->ogImageUrl() }}">
    <meta property="og:image:alt" content="{{ $property->guest_display_name }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $property->ogImageUrl() }}">
    @include('layouts.partials.brand-vars')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --brand: var(--theme-primary); }</style>
    @if($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
</head>
<body class="guest-canvas text-slate-950 antialiased">

@if(session('success') || $errors->any())
    <div class="mx-auto mt-4 w-full max-w-[390px] px-4">
        @if(session('success'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">
                {{ $errors->first() }}
            </div>
        @endif
    </div>
@endif

@php
    $quickContactPhone = ($property?->contact_phone) ?: \App\Models\Setting::getValue('contact_phone');
@endphp
@if($quickContactPhone)
    <a href="tel:{{ $quickContactPhone }}" class="guest-contact-fab" aria-label="Contact Guest Services">
        <span class="guest-contact-fab-icon"><x-icon name="contact-guest-services" class="h-12 w-12" /></span>
        <span class="guest-contact-fab-label">Call Guest Services</span>
    </a>
@endif

<main class="guest-stage">
    {{ $slot }}
</main>

<div id="completion-prompt" class="hidden fixed inset-0 z-[9998] grid place-items-center bg-slate-950/35 px-4 backdrop-blur-sm">
    <div class="w-full max-w-[340px] rounded-lg bg-white p-6 text-center shadow-xl">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white">
            <x-icon name="check" class="h-8 w-8" />
        </span>
        <h2 class="mt-4 text-xl font-semibold">Check-in details received</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Your information was submitted securely.</p>
        <button type="button" data-close-completion class="guest-primary-btn mt-5 w-full">Continue</button>
    </div>
</div>

</body>
</html>

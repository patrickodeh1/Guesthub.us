<!doctype html>
<html lang="en" data-theme="light" style="color-scheme:light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">
    @php
        $brandSiteName = \App\Support\Branding::siteName();
        $siteLogo = \App\Support\Branding::logoUrl();
        $favicon = \App\Support\Branding::faviconUrl();
    @endphp
    <title>{{ $brandSiteName }}</title>
    @include('layouts.partials.theme-init')
    @include('layouts.partials.brand-vars')
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/cleaning.css',
        'resources/js/cleaning-app.js',
    ])
    @if($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <link rel="preload" href="https://cdn.gtranslate.net/widgets/latest/dropdown.js" as="script">
    <style>
        [x-cloak] { display: none !important; }
        .gtranslate_wrapper select {
            width: 100% !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8rem !important;
            border-radius: 0.375rem !important;
            border: 1px solid #d1d5db !important;
            background-color: #ffffff !important;
            color: #1f2937 !important;
            outline: none !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body class="guest-canvas text-slate-950 antialiased" x-data="mainState" x-on:resize.window="handleWindowResize">
<script>
    window.__userCanUpload = {{ auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner']) ? 'true' : 'false' }};
</script>

<main class="guest-stage">
    <section class="phone-frame">
    <div class="phone-screen">
        @if(session('success') || session('ok') || $errors->any())
            <div class="mb-4 space-y-2">
                @if(session('success') || session('ok'))
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ session('success') ?: session('ok') }}</div>
                @endif
                @if($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">{{ $errors->first() }}</div>
                @endif
            </div>
        @endif


        {{ $slot }}
    </div>
</section>
</main>

<script>
        window.gtranslateSettings = {
            "default_language": "en",
            "detect_browser_language": true,
            "languages": ["en", "es", "pt"],
            "wrapper_selector": ".gtranslate_wrapper",
            "switcher_horizontal_position": "inline",
            "alt_flags": {"en": "usa"}
        };
    </script>
<script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js"></script>
</body>
</html>

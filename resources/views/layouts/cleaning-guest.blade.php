<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">

    <title>{{ \App\Support\Branding::siteName() }}</title>
    @include('layouts.partials.theme-init')
    @include('layouts.partials.brand-vars')

    <!-- Favicon -->
    @php($faviconUrl = \App\Support\Branding::faviconUrl())
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    <!-- Fonts -->
    <!-- External fonts removed for self-hosted compliance -->

    <!-- Styles -->
    <style>
        [x-cloak] {
            display: none;
        }

    </style>

    <!-- Scripts -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/cleaning.css',
        'resources/js/cleaning-app.js',
    ])
</head>

<body>
    <div
        x-data="mainState"
        class="font-sans antialiased"
        x-cloak
    >
        <div class="flex flex-col min-h-screen text-gray-900 bg-gray-100 dark:bg-dark-eval-0 dark:text-gray-200">
            {{ $slot }}

            <x-footer />
        </div>

    </div>
</body>
</html>

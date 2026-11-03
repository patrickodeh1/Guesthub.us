@if(auth()->check() && ! request()->routeIs('guest.*'))
    <x-admin-layout title="No access">
        @php
            $reason = isset($exception) && $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'Your account does not have permission to view this page.';
            $fallback = auth()->user()->hasAnyRole(['admin', 'owner', 'company', 'manager'])
                ? route('admin.dashboard')
                : route('dashboard');
        @endphp

        <div class="mx-auto mt-10 max-w-2xl">
            <section class="card card-pad text-center">
                <span class="icon-chip mx-auto h-14 w-14"><x-icon name="security" class="h-7 w-7" /></span>
                <p class="eyebrow mt-6">Access restricted</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-950">No access</h1>
                <p class="mt-3 leading-7 text-slate-600">{{ $reason }}</p>
                <div class="mt-6 flex justify-center gap-3">
                    <button type="button" class="btn-secondary" data-fallback="{{ $fallback }}" onclick="if (history.length > 1) { history.back(); } else { window.location.href = this.dataset.fallback; }">
                        Back
                    </button>
                    <a href="{{ $fallback }}" class="btn-primary">Dashboard</a>
                </div>
            </section>
        </div>
    </x-admin-layout>
@else
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Access Not Available</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="grid min-h-screen place-items-center bg-slate-50 px-4 text-slate-900">
        <main class="ui-enter w-full max-w-xl rounded-lg border border-slate-200 bg-white p-8 text-center shadow-xs">
            <span class="icon-chip mx-auto h-14 w-14"><x-icon name="security" class="h-7 w-7" /></span>
            <p class="eyebrow mt-6">Protected content</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-950">This section is not available yet.</h1>
            <p class="mt-3 leading-7 text-slate-600">Some guest guide pages unlock after check-in approval. If you believe this is incorrect, contact Guest Services.</p>
            @if(request()->route('booking_id') && request()->route('token'))
                <a href="{{ route('guest.show', [request()->route('booking_id'), request()->route('token')]) }}" class="btn-secondary mt-6">Go Back</a>
            @else
                <a href="{{ url('/') }}" class="btn-secondary mt-6">Go Back</a>
            @endif
        </main>
    </body>
    </html>
@endif

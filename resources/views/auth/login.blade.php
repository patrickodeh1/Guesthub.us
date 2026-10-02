<!doctype html>
@php
    $loginSiteName = \App\Support\Branding::siteName();
    $loginLogo = \App\Support\Branding::logoUrl();
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
    @include('layouts.partials.theme-init')
    @include('layouts.partials.brand-vars')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="grid min-h-screen lg:grid-cols-[1fr_520px]">
        <section class="hidden bg-[var(--brand-sidebar)] px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-3">
                    @if($loginLogo)
                        <img src="{{ $loginLogo }}" alt="{{ $loginSiteName }}" class="max-h-12 max-w-48 object-contain">
                    @else
                        <span class="grid h-11 w-11 place-items-center rounded-lg bg-white text-[var(--theme-primary)]"><x-icon name="guide" /></span>
                    @endif
                </div>
                <div class="mt-28 max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-200">Guests, properties, housekeeping</p>
                    <h1 class="mt-4 text-4xl font-semibold leading-tight">Arrivals that run themselves. Turnovers you can trust.</h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">Check guests in with GPS and ID verification, schedule cleanings with photo proof, and keep every property guide up to date, all from one dashboard.</p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4 text-sm text-slate-300">
                <div class="rounded-lg border border-white/10 p-4"><x-icon name="map" class="h-6 w-6 text-blue-200" /><p class="mt-3 text-sm">GPS check-in</p></div>
                <div class="rounded-lg border border-white/10 p-4"><x-icon name="upload" class="h-6 w-6 text-blue-200" /><p class="mt-3 text-sm">Photo-proof cleanings</p></div>
                <div class="rounded-lg border border-white/10 p-4"><x-icon name="guide" class="h-6 w-6 text-blue-200" /><p class="mt-3 text-sm">Always-current guides</p></div>
            </div>
        </section>
        <section class="grid place-items-center px-5 py-10">
            <form method="post" action="{{ route('login.store') }}" class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-xs dark:border-slate-700 dark:bg-slate-900">
                @csrf
                <p class="eyebrow">{{ $loginSiteName }}</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">Welcome back</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Sign in to see today's arrivals, cleanings and properties at a glance.</p>
                @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $errors->first() }}</div>@endif
                <label class="field-label mt-6">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required placeholder="you@example.com" class="input">
                @error('email')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                <label class="field-label mt-4">Password</label>
                <input name="password" type="password" required placeholder="Enter your password" class="input">
                @error('password')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                <label class="mt-4 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Remember me</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="mt-3 inline-block text-sm text-[var(--theme-primary)] underline">Forgot password?</a>
                @endif
                <button class="btn-primary mt-6 w-full">Sign in</button>
                {{--<p class="mt-5 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500">Demo access: admin@example.com / password. Change this password before production launch.</p> --}}
            </form>
        </section>
    </main>
    <footer class="border-t border-slate-200 bg-white px-4 py-5 text-center text-sm text-slate-600">
        <div class="space-x-3">
            <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms of Service</a>
            <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a>
            <a href="{{ route('privacy-request') }}" class="underline hover:text-slate-900">Privacy Request</a>
            <a href="{{ route('contact') }}" class="underline hover:text-slate-900">Contact</a>
        </div>
    </footer>
</body>
</html>

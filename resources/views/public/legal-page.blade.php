<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Guest Hub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    @if($siteLogo)
                        <img src="{{ url('/img/'.$siteLogo) }}" alt="GuestHub" class="h-8 w-auto object-contain">
                    @else
                        <span class="text-lg font-semibold tracking-tight">GuestHub</span>
                    @endif
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
                </div>
                <nav class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <a href="{{ route('legal.terms') }}" class="hover:text-slate-900 underline">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="hover:text-slate-900 underline">Privacy Policy</a>
                    <a href="{{ route('legal.rental-contract') }}" class="hover:text-slate-900 underline">Rental Contract</a>
                    <a href="{{ route('early-access') }}" class="hover:text-slate-900 underline">Home</a>
                </nav>
            </div>
        </header>

        <main class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-4 text-sm font-medium text-slate-500">Effective date: {{ $effectiveDate }}</div>
            <div class="prose prose-slate max-w-none prose-headings:font-semibold prose-h1:text-2xl prose-h2:text-xl prose-p:leading-7 prose-a:text-sky-700 prose-a:no-underline hover:prose-a:underline">
                {!! $content !!}
            </div>

            <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p>For help, please use our <a href="{{ route('contact') }}" class="font-medium underline">contact form</a>.</p>
            </div>
        </main>

        <footer class="mt-8 text-center text-sm text-slate-500">
            <p>{{ $siteCopyright }} | <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms of Service</a> | <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a> | <a href="{{ route('legal.rental-contract') }}" class="underline hover:text-slate-900">Rental Contract</a> | <a href="{{ route('privacy-request') }}" class="underline hover:text-slate-900">Privacy Request</a> | <a href="{{ route('contact') }}" class="underline hover:text-slate-900">Contact</a></p>
        </footer>
    </div>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Request | Guest Hub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    @if($siteLogo)
                        <img src="{{ url('/img/'.$siteLogo) }}" alt="GuestHub" class="h-8 w-auto object-contain">
                    @else
                        <span class="text-lg font-semibold tracking-tight">GuestHub</span>
                    @endif
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Privacy request</h1>
                </div>
                <nav class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a>
                    <a href="{{ route('contact') }}" class="underline hover:text-slate-900">Contact</a>
                </nav>
            </div>
        </header>

        <main class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if(session('success'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <section>
                    <h2 class="text-xl font-semibold text-slate-950">Request privacy help</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Use this form to request access to your information, corrections, deletion, a portable copy, or to appeal a privacy decision.
                    </p>

                    <form method="post" action="{{ route('privacy-request.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Name</label>
                            <input name="name" type="text" value="{{ old('name') }}" required class="input mt-1.5">
                            @error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label">Email</label>
                            <input name="email" type="email" value="{{ old('email') }}" required class="input mt-1.5">
                            @error('email')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label">Phone</label>
                            <input name="phone" type="tel" value="{{ old('phone') }}" class="input mt-1.5">
                            @error('phone')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label">Request type</label>
                            <select name="request_type" class="input mt-1.5" required>
                                <option value="">Select a request</option>
                                <option value="access" @selected(old('request_type') === 'access')>Access my information</option>
                                <option value="correction" @selected(old('request_type') === 'correction')>Correction</option>
                                <option value="deletion" @selected(old('request_type') === 'deletion')>Deletion</option>
                                <option value="portable_copy" @selected(old('request_type') === 'portable_copy')>Portable copy</option>
                                <option value="opt_out" @selected(old('request_type') === 'opt_out')>Opt out of targeted advertising or data sharing</option>
                                <option value="appeal" @selected(old('request_type') === 'appeal')>Appeal a denied request</option>
                            </select>
                            @error('request_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label">Details</label>
                            <textarea name="details" rows="6" class="textarea mt-1.5" placeholder="Tell us what information you need help with.">{{ old('details') }}</textarea>
                            @error('details')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn-primary">Submit request</button>
                    </form>
                </section>

                <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                    <h3 class="text-lg font-semibold text-slate-900">Need help now?</h3>
                    <p class="mt-3 leading-6">Use the <a href="{{ route('contact') }}" class="font-medium underline">contact form</a> for general questions and include your booking or reservation reference if you have it.</p>
                    <div class="mt-6 space-y-3">
                        <a href="{{ route('legal.terms') }}" class="block rounded-lg border border-slate-200 bg-white px-3 py-2 underline hover:text-slate-900">Terms of Service</a>
                        <a href="{{ route('legal.privacy') }}" class="block rounded-lg border border-slate-200 bg-white px-3 py-2 underline hover:text-slate-900">Privacy Policy</a>
                        <a href="{{ route('contact') }}" class="block rounded-lg border border-slate-200 bg-white px-3 py-2 underline hover:text-slate-900">Contact</a>
                    </div>
                </aside>
            </div>
        </main>

        <footer class="mt-8 text-center text-sm text-slate-500">
            <p>{{ \App\Models\Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub') }} | <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms of Service</a> | <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a></p>
        </footer>
    </div>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact | Guest Hub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    @if($siteLogo)
                        <img src="{{ url('/img/'.$siteLogo) }}" alt="GuestHub" class="h-8 w-auto object-contain">
                    @else
                        <span class="text-lg font-semibold tracking-tight">GuestHub</span>
                    @endif
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Contact</h1>
                </div>
                <nav class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a>
                    <a href="{{ route('privacy-request') }}" class="underline hover:text-slate-900">Privacy Request</a>
                </nav>
            </div>
        </header>

        <main class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if(session('success'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <h2 class="text-xl font-semibold text-slate-950">How can we help?</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Send us a message about guest support, reservation questions, notices, or privacy requests.
            </p>

            <form method="post" action="{{ route('contact.store') }}" class="mt-8 space-y-4">
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
                    <label class="field-label">Phone <span class="font-normal text-slate-500">(optional)</span></label>
                    <input name="phone" type="tel" value="{{ old('phone') }}" class="input mt-1.5">
                    @error('phone')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Subject</label>
                    <input name="subject" type="text" value="{{ old('subject') }}" required class="input mt-1.5">
                    @error('subject')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Message</label>
                    <textarea name="message" rows="6" required class="textarea mt-1.5">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary">Send message</button>
            </form>

            <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                <p>For a formal privacy request, use the dedicated <a href="{{ route('privacy-request') }}" class="font-medium underline">privacy request form</a>.</p>
            </div>
        </main>

        <footer class="mt-8 text-center text-sm text-slate-500">
            <p>{{ \App\Models\Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub') }} | <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-900">Terms of Service</a> | <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-900">Privacy Policy</a></p>
        </footer>
    </div>
</body>
</html>

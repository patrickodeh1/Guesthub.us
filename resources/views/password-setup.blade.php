<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create your password — {{ \App\Support\Branding::siteName() }}</title>
    @include('layouts.partials.brand-vars')
    @php($siteLogo = \App\Support\Branding::logoUrl())
    @php($siteFavicon = \App\Support\Branding::faviconUrl())
    @if($siteFavicon)<link rel="icon" href="{{ $siteFavicon }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#f5f7fa;font-family:Inter,ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased;color:#0d2137}
        .page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px}
        .logo{margin-bottom:24px;text-align:center}.logo img{height:40px;max-width:100%}
        .card{background:#fff;border:1px solid #e1eaf1;border-radius:18px;box-shadow:0 8px 28px #0d213712;padding:36px 40px;width:100%;max-width:440px;box-sizing:border-box}
        h1{margin:0 0 6px;font-size:22px;font-weight:800;letter-spacing:-.5px}
        .sub{margin:0 0 22px;font-size:13px;color:#5c7488;line-height:1.6}
        .field-label{display:block;font-size:12px;font-weight:600;color:#374d62;margin-bottom:6px}
        .input{width:100%;padding:11px 13px;border:1px solid #d0dbe6;border-radius:9px;font-size:16px;font-family:inherit;box-sizing:border-box;outline:none}
        .input:focus{border-color:#079dcc;box-shadow:0 0 0 3px #079dcc22}
        .btn{display:block;width:100%;padding:13px;border-radius:999px;font-size:14px;font-weight:700;border:none;cursor:pointer;background:#082b49;color:#fff;margin-top:6px}
        .err{font-size:12px;color:#d93025;margin-top:5px}
        a{color:#0a7fa8}
        @media(max-width:520px){.card{padding:26px 18px}}
    </style>
</head>
<body>
<div class="page">
    <div class="logo">
        @if($siteLogo)<img src="{{ $siteLogo }}" alt="{{ \App\Support\Branding::siteName() }}">
        @else<span style="font-size:22px;font-weight:800">{{ \App\Support\Branding::siteName() }}</span>@endif
    </div>
    <div class="card">
        @if($valid)
            <h1>Create your password</h1>
            <p class="sub">Hi {{ $user->name }}, choose a password for your {{ \App\Support\Branding::siteName() }} account.</p>
            <form method="post" action="{{ request()->fullUrl() }}">
                @csrf
                <div style="margin-bottom:14px">
                    <label class="field-label" for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    @error('password')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div style="margin-bottom:18px">
                    <label class="field-label" for="password_confirmation">Confirm password</label>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                </div>
                <button type="submit" class="btn">Save password</button>
            </form>
        @else
            <h1>This link has expired</h1>
            <p class="sub">Password links are valid for 24 hours and work once. Please contact us and we'll send you a new one.
                @if($contactEmail) <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> @endif</p>
            <p class="sub" style="margin:0">Already set your password? <a href="{{ route('login') }}">Log in</a></p>
        @endif
    </div>
</div>
</body>
</html>

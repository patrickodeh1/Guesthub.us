<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account — {{ \App\Support\Branding::siteName() }}</title>
    @include('layouts.partials.brand-vars')
    @php($siteLogo = \App\Support\Branding::logoUrl())
    @php($siteFavicon = \App\Support\Branding::faviconUrl())
    @if($siteFavicon)<link rel="icon" href="{{ $siteFavicon }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#f5f7fa;font-family:Inter,ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased;color:#0d2137}
        .page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:28px 16px}
        .logo{margin-bottom:24px;text-align:center}.logo img{height:40px;max-width:100%}
        .card{background:#fff;border:1px solid #e1eaf1;border-radius:18px;box-shadow:0 8px 28px #0d213712;padding:36px 40px;width:100%;max-width:460px;box-sizing:border-box}
        h1{margin:0 0 6px;font-size:22px;font-weight:800;letter-spacing:-.5px}
        .sub{margin:0 0 22px;font-size:13px;color:#5c7488;line-height:1.6}
        .field-label{display:block;font-size:12px;font-weight:600;color:#374d62;margin-bottom:6px}
        .input{width:100%;padding:11px 13px;border:1px solid #d0dbe6;border-radius:9px;font-size:16px;font-family:inherit;box-sizing:border-box;outline:none;transition:.15s}
        .input:focus{border-color:#079dcc;box-shadow:0 0 0 3px #079dcc22}
        .role-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:4px}
        .role-opt{position:relative}.role-opt input{position:absolute;opacity:0;width:0;height:0}
        .role-label{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;box-sizing:border-box;border:1.5px solid #d8e5ef;border-radius:11px;padding:14px 8px;cursor:pointer;text-align:center;transition:.15s;font-size:12px;font-weight:600;gap:5px;color:#374d62}
        .role-label .ico{font-size:22px}
        .role-opt input:checked + .role-label{border-color:#079dcc;background:#f0fbff;color:#0a7fa8}
        .role-opt input:focus-visible + .role-label{box-shadow:0 0 0 3px #079dcc33}
        .btn{display:block;width:100%;padding:14px;border-radius:999px;font-size:14px;font-weight:700;border:none;cursor:pointer;background:#082b49;color:#fff;margin-top:6px;transition:.2s}
        .btn:hover{background:#10456d}
        .err{font-size:12px;color:#d93025;margin-top:5px}
        .ok{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:13px;color:#166534;margin-bottom:16px}
        .tos{font-size:11px;color:#647d90;text-align:center;margin-top:14px;line-height:1.5}
        .tos a,.login-link a{color:#0a7fa8}
        .login-link{text-align:center;margin-top:18px;font-size:13px;color:#5c7488}.login-link a{font-weight:600}
        .hp{position:absolute;left:-9999px;height:0;overflow:hidden}
        @media(max-width:520px){
            .page{justify-content:flex-start;padding-top:20px}
            .card{padding:24px 18px;border-radius:14px}
            .role-grid{grid-template-columns:1fr}
            .role-label{flex-direction:row;justify-content:flex-start;padding:12px 14px;gap:12px;text-align:left;font-size:14px}
        }
    </style>
</head>
<body>
<div class="page">
    <div class="logo">
        @if($siteLogo)<img src="{{ $siteLogo }}" alt="{{ \App\Support\Branding::siteName() }}">
        @else<span style="font-size:22px;font-weight:800">{{ \App\Support\Branding::siteName() }}</span>@endif
    </div>

    <div class="card">
        <h1>Request an account</h1>
        <p class="sub">An admin will review your request. Once approved, we'll email you a link to create your password.</p>

        @if(session('success'))<div class="ok">{{ session('success') }}</div>@endif

        <form method="post" action="{{ route('signup.store') }}">
            @csrf
            <div class="hp" aria-hidden="true">
                <label>Leave this blank</label>
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div style="margin-bottom:16px">
                <label class="field-label">I am a&hellip;</label>
                <div class="role-grid">
                    <div class="role-opt">
                        <input type="radio" name="requested_role" id="role_owner" value="owner" @checked(old('requested_role') === 'owner')>
                        <label class="role-label" for="role_owner"><span class="ico">🏠</span>Property Owner</label>
                    </div>
                    <div class="role-opt">
                        <input type="radio" name="requested_role" id="role_housekeeper" value="housekeeper" @checked(old('requested_role') === 'housekeeper')>
                        <label class="role-label" for="role_housekeeper"><span class="ico">🧹</span>Cleaner</label>
                    </div>
                    <div class="role-opt">
                        <input type="radio" name="requested_role" id="role_company" value="company" @checked(old('requested_role') === 'company')>
                        <label class="role-label" for="role_company"><span class="ico">🏢</span>Company / Manager</label>
                    </div>
                </div>
                @error('requested_role')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div style="margin-bottom:14px">
                <label class="field-label" for="name">Full name</label>
                <input class="input" id="name" name="name" value="{{ old('name') }}" type="text" autocomplete="name" required>
                @error('name')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div style="margin-bottom:14px">
                <label class="field-label" for="email">Email address</label>
                <input class="input" id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" required>
                @error('email')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div style="margin-bottom:20px">
                <label class="field-label" for="phone">Phone number <span style="color:#d93025">*</span></label>
                @include('partials.phone-country-picker', [
                    'id' => 'phone', 'name' => 'phone_number', 'value' => old('phone_number'),
                    'placeholder' => '801 234 5678', 'inputClass' => 'input',
                ])
                @error('phone_number')<p class="err">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn">Request access</button>
        </form>

        <p class="tos">By requesting access you agree to our <a href="{{ route('legal.terms') }}">Terms</a> and <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
    </div>
    <p class="login-link">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</div>
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request received — {{ \App\Support\Branding::siteName() }}</title>
    @include('layouts.partials.brand-vars')
    @php($siteLogo = \App\Support\Branding::logoUrl())
    @php($siteFavicon = \App\Support\Branding::faviconUrl())
    @if($siteFavicon)<link rel="icon" href="{{ $siteFavicon }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#f5f7fa;font-family:Inter,ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased;color:#0d2137}
        .page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px;text-align:center}
        .logo img{height:40px;margin-bottom:28px}
        .card{background:#fff;border:1px solid #e1eaf1;border-radius:18px;box-shadow:0 8px 28px #0d213712;padding:40px;max-width:420px;width:100%}
        .icon{font-size:44px;margin-bottom:12px}
        h1{margin:0 0 10px;font-size:22px;font-weight:800}
        p{font-size:14px;color:#5c7488;line-height:1.7;margin:0}
        a{color:#0a7fa8}
    </style>
</head>
<body>
<div class="page">
    @if($siteLogo)<img src="{{ $siteLogo }}" alt="{{ \App\Support\Branding::siteName() }}">@endif
    <div class="card">
        <div class="icon">✅</div>
        <h1>Request received</h1>
        <p>Thanks for signing up for <strong>{{ \App\Support\Branding::siteName() }}</strong>. Your request is under review. Once it's approved we'll email you a link to create your password.</p>
        <p style="margin-top:16px"><a href="{{ route('login') }}">Back to login</a></p>
    </div>
</div>
</body>
</html>

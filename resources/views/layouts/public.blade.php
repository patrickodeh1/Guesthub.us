<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title') · GuestHub</title>
    <meta name="description" content="@yield('meta_description')">
    <link rel="canonical" href="@yield('canonical')">
    @if(!empty($noindex))
        <meta name="robots" content="noindex,follow">
    @else
        <meta name="robots" content="index,follow">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="GuestHub">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('meta_description')">
    <meta property="og:url" content="@yield('canonical')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title')">
    <meta name="twitter:description" content="@yield('meta_description')">
    @stack('head')
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17221e;background:#f7f8f6;font-synthesis:none;text-rendering:optimizeLegibility}
        *{box-sizing:border-box}body{margin:0}a{color:inherit}img{display:block;max-width:100%;height:auto}
        .site-header{background:#fff;border-bottom:1px solid #e1e7e2}.site-header-inner,.site-footer-inner{max-width:1180px;margin:auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .brand{font-weight:800;letter-spacing:-.04em;text-decoration:none;font-size:1.35rem}.brand span{color:#16805d}.header-link{font-size:.92rem;font-weight:650;text-decoration:none}
        main{max-width:1180px;margin:auto;padding:32px 24px 64px}.eyebrow{text-transform:uppercase;letter-spacing:.13em;color:#16805d;font-size:.72rem;font-weight:800}
        h1{font-size:clamp(2rem,4vw,3.5rem);letter-spacing:-.055em;line-height:1.05;margin:.45rem 0 1rem}h2{letter-spacing:-.03em}.lede{font-size:1.08rem;line-height:1.6;color:#53625b;max-width:760px}
        .search-panel{background:#fff;border:1px solid #e0e7e2;border-radius:16px;padding:18px;margin:26px 0;box-shadow:0 12px 30px #17221e0a}
        .search-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;align-items:end}.field label{display:block;font-size:.76rem;font-weight:750;margin:0 0 6px}
        input,select{width:100%;min-height:44px;border:1px solid #cbd6cf;border-radius:9px;padding:8px 10px;background:#fff;color:#17221e;font:inherit}
        .button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:0;border-radius:9px;padding:10px 18px;background:#147555;color:white;font:inherit;font-weight:750;text-decoration:none;cursor:pointer}.button:hover{background:#105f45}
        .split-heading{display:flex;justify-content:space-between;align-items:end;gap:16px;margin:34px 0 16px}.split-heading h2{margin:0}.results-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:22px;align-items:start}
        .listing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.listing-card,.panel{background:#fff;border:1px solid #e0e7e2;border-radius:14px;overflow:hidden}
        .listing-card>a{text-decoration:none}.listing-card img{width:100%;aspect-ratio:3/2;object-fit:cover;background:#e6ebe7}.card-body{padding:14px 15px 16px}.card-body h2{font-size:1.08rem;margin:0 0 5px}.muted{color:#66746d;font-size:.9rem}.price{font-weight:800;margin-top:12px}
        .map-panel{position:sticky;top:18px}.map-title{padding:14px 16px 0;margin:0;font-size:1rem}.map-canvas{height:420px;min-height:300px;background:#e7ece8}.map-note{padding:10px 16px;color:#66746d;font-size:.8rem}
        .empty{background:#fff;border:1px dashed #cbd6cf;border-radius:14px;padding:30px;color:#53625b}.pagination{margin-top:24px}
        .gallery{display:grid;grid-template-columns:2fr 1fr 1fr;grid-template-rows:repeat(2,minmax(150px,220px));gap:10px;border-radius:18px;overflow:hidden;margin:26px 0}.gallery img{width:100%;height:100%;object-fit:cover;background:#e6ebe7}.gallery img:first-child{grid-row:span 2}
        .detail-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:32px;align-items:start}.detail-copy{line-height:1.75;color:#34433c}.detail-aside{position:sticky;top:18px}.panel{padding:20px}.panel h2:first-child{margin-top:0}.specs{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.spec{border:1px solid #dfe6e1;background:#fff;border-radius:99px;padding:6px 11px;font-size:.84rem}
        .calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px;margin:14px 0}.calendar-day{min-height:54px;border-radius:7px;border:1px solid #dce5de;background:#eff8f2;text-align:center;padding:6px 2px;font-size:.72rem}.calendar-day.blocked{background:#edf0ee;color:#87918b;text-decoration:line-through}.calendar-day small{display:block;font-size:.65rem}
        .quote-lines{display:grid;gap:8px;margin:12px 0}.quote-line{display:flex;justify-content:space-between;gap:12px;font-size:.9rem}.quote-total{font-weight:850;font-size:1.12rem;border-top:1px solid #dfe6e1;padding-top:12px}.status-message{min-height:22px;color:#8a3c30;font-size:.88rem}
        .site-footer{background:#eaf0ec;border-top:1px solid #dce5de}.site-footer-inner{color:#53625b;font-size:.85rem}.site-footer a{font-weight:700}
        @media(max-width:850px){.search-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.results-layout{grid-template-columns:1fr}.map-panel{position:static;grid-row:1}.map-canvas{height:320px}.detail-grid{grid-template-columns:1fr}.detail-aside{position:static}}
        @media(max-width:580px){.site-header-inner,.site-footer-inner{padding:14px 16px}main{padding:24px 16px 48px}.search-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.listing-grid{grid-template-columns:1fr}.gallery{grid-template-columns:1fr 1fr;grid-template-rows:240px 130px 130px}.gallery img:first-child{grid-column:span 2;grid-row:auto}.split-heading{align-items:start;flex-direction:column}.calendar-day{min-height:46px}}
    </style>
</head>
<body>
<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="{{ route('public.stays.index') }}" aria-label="GuestHub stays home">Guest<span>Hub</span></a>
        <a class="header-link" href="{{ route('public.stays.index') }}">Find a stay</a>
    </div>
</header>
<main>
    @yield('content')
</main>
<footer class="site-footer">
    <div class="site-footer-inner"><span>GuestHub · Places to stay, directly from the host.</span><a href="{{ route('public.stays.index') }}">Browse stays</a></div>
</footer>
@stack('scripts')
</body>
</html>

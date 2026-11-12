<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Branding::siteName() }} — Everything behind a great stay</title>
    <meta name="description" content="Manage short-term rentals, bookings, guest journeys and property operations from one connected platform.">
    @include('layouts.partials.brand-vars')
    @php($siteLogo = \App\Support\Branding::logoUrl())
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://images.unsplash.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#082b49;--blue:#079dcc;--cyan:#dff7fc;--muted:#53677b;--line:#e4edf4}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#fff;color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}.wrap{width:min(1180px,calc(100% - 48px));margin:auto}
        .nav{height:76px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2f7;background:#fff;position:sticky;top:0;z-index:20}
        .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:21px;letter-spacing:-.7px}.brand img{height:38px;max-width:160px;object-fit:contain}
        .navlinks{display:flex;align-items:center;gap:32px;font-size:13px;font-weight:600;color:#233f5a}.navright{display:flex;align-items:center;gap:22px;font-size:13px;font-weight:700}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border-radius:999px;padding:14px 22px;font-size:13px;font-weight:700;transition:.2s;border:1px solid transparent}
        .btn-primary{background:var(--ink);color:#fff;box-shadow:0 5px 12px #082b4920}.btn-primary:hover{background:#10456d;transform:translateY(-1px)}
        .btn-outline{border-color:#8fc9df;color:var(--ink);background:#fff}.btn-outline:hover{background:#f3fbfe}
        .hero{padding:72px 0 48px;overflow:hidden;background:radial-gradient(ellipse at 83% 23%,#e2f7fc 0,transparent 34%),linear-gradient(180deg,#fff 0,#fbfdff 100%)}
        .hero-grid{display:grid;grid-template-columns:.92fr 1.08fr;gap:36px;align-items:center}.eyebrow{display:inline-flex;padding:7px 13px;border-radius:999px;background:#d8f5fb;color:#087da5;font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase}
        h1{font-size:clamp(42px,5vw,64px);line-height:1.04;letter-spacing:-3px;margin:20px 0 18px;font-weight:800;max-width:600px}h1 span,.accent{color:var(--blue)}
        .lead{font-size:16px;line-height:1.75;color:#38516a;max-width:510px;margin:0}.hero-actions{display:flex;gap:13px;flex-wrap:wrap;margin-top:28px}.trust{display:flex;align-items:center;gap:9px;color:#38516a;font-size:12px;margin-top:25px}
        .dashboard{position:relative;padding:16px 0 14px 12px}.dashboard:before{content:"";position:absolute;inset:0 -35px 0 15%;background:linear-gradient(135deg,#dff6fb,#b9eaf6);border-radius:44% 56% 52% 48%;transform:rotate(-7deg)}
        .dash-window{position:relative;background:#fff;border:1px solid #dce8f1;border-radius:18px;box-shadow:0 24px 55px #0b35551b;overflow:hidden;max-width:650px;margin:auto}
        .windowbar{height:34px;background:#f7fafc;border-bottom:1px solid #e8eef4;display:flex;align-items:center;gap:6px;padding:0 14px}.dot{width:8px;height:8px;border-radius:50%;background:#f87171}.dot:nth-child(2){background:#fbbf24}.dot:nth-child(3){background:#34d399}
        .dash-body{display:grid;grid-template-columns:145px 1fr;min-height:310px}.dash-side{padding:17px 12px;border-right:1px solid #edf2f6}.dash-logo{font-size:12px;font-weight:800;margin-bottom:18px}.dash-menu{font-size:10px;color:#52677d;padding:9px 8px;border-radius:7px;margin:3px 0}.dash-menu.active{background:#e5f5fb;color:#087da5;font-weight:700}
        .dash-main{padding:20px}.dash-title{font-size:15px;font-weight:800;margin-bottom:15px}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.stat{border:1px solid #e6edf4;border-radius:10px;padding:12px}.stat small{font-size:9px;color:#64788c}.stat strong{display:block;font-size:19px;margin:6px 0 2px}.stat em{font-style:normal;color:#0c9b68;font-size:9px}
        .booking-list{margin-top:13px;border:1px solid #e6edf4;border-radius:10px;padding:13px}.booking-head{font-size:11px;font-weight:800;margin-bottom:10px}.booking{display:flex;align-items:center;justify-content:space-between;border-top:1px solid #edf2f6;padding:9px 0;font-size:10px}.booking b{display:block;font-size:10px}.booking small{color:#687d90}.pill{border-radius:99px;background:#dff8ed;color:#087d53;padding:5px 9px;font-size:9px;font-weight:700}
        .feature-strip{display:grid;grid-template-columns:repeat(6,1fr);border:1px solid #e2edf4;border-radius:16px;box-shadow:0 8px 24px #0b35550a;padding:18px 8px;margin-top:42px;background:#fff}
        .feature{padding:8px 12px;text-align:center;border-right:1px solid #e5edf3}.feature:last-child{border:0}.feature .ico{font-size:25px;color:var(--blue)}.feature b{display:block;font-size:12px;margin:8px 0 5px}.feature small{display:block;font-size:10px;line-height:1.5;color:#5c7186}
        .section{padding:82px 0}.section-grid{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}.section h2{font-size:clamp(32px,3.8vw,44px);line-height:1.12;letter-spacing:-1.8px;margin:18px 0 14px}.section p{font-size:14px;line-height:1.8;color:#53677b;max-width:500px}.checks{display:grid;gap:17px;margin:25px 0}.check{display:flex;gap:12px;align-items:flex-start}.checkmark{color:#079dcc;font-size:18px}.check b{display:block;font-size:13px;margin-bottom:4px}.check small{font-size:11px;color:#60768a;line-height:1.5}
        .photo-card{position:relative;border-radius:18px;overflow:hidden;min-height:330px;background:#edf5f8}.photo-card img{width:100%;height:350px;object-fit:cover;display:block}.photo-note{position:absolute;right:18px;top:18px;background:#fff;border:1px solid #e5edf3;border-radius:12px;padding:15px 17px;box-shadow:0 10px 25px #0b35551a;font-size:12px}.photo-note b{display:block;margin-bottom:5px}
        .journey{background:#f5fbfd}.journey-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:35px}.journey-card{background:#fff;border:1px solid #e1edf3;border-radius:14px;padding:22px}.journey-card .num{font-size:11px;color:var(--blue);font-weight:800}.journey-card h3{font-size:14px;margin:12px 0 7px}.journey-card p{font-size:11px;margin:0;line-height:1.6}
        .integrations{text-align:center;background:#f8fbfd}.integrations h2{font-size:34px;letter-spacing:-1.3px;margin:16px 0 8px}.logos{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-top:32px}.logo-tile{height:72px;border:1px solid #e0eaf1;border-radius:13px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#173a5c}
        .teams{display:grid;grid-template-columns:.8fr 1.6fr;gap:44px;align-items:center}.team-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.team-card{border:1px solid #e3edf4;border-radius:14px;padding:24px 12px;text-align:center;min-height:140px}.team-card .ico{font-size:25px;color:var(--blue)}.team-card b{display:block;font-size:12px;margin:12px 0 6px}.team-card small{font-size:10px;color:#63788b;line-height:1.5}
        .cta{margin:20px auto 70px;border-radius:22px;overflow:hidden;background:#eaf7fb;display:grid;grid-template-columns:1fr 1fr;min-height:270px}.cta-copy{padding:42px 46px}.cta h2{font-size:32px;line-height:1.16;letter-spacing:-1.2px;margin:15px 0 10px}.cta p{font-size:13px;color:#53677b;line-height:1.7}.cta img{width:100%;height:100%;min-height:270px;object-fit:cover}
        footer{border-top:1px solid #e8eef3;padding:26px 0;color:#65788a;font-size:11px}.footer-row{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
        @media(max-width:900px){.navlinks{display:none}.hero-grid,.section-grid,.teams,.cta{grid-template-columns:1fr}.hero{padding-top:48px}.dashboard{padding:10px 0}.feature-strip{grid-template-columns:repeat(3,1fr)}.feature:nth-child(3){border-right:0}.feature:nth-child(-n+3){border-bottom:1px solid #e5edf3}.logos{grid-template-columns:repeat(3,1fr)}.team-cards{grid-template-columns:repeat(2,1fr)}.journey-grid{grid-template-columns:repeat(2,1fr)}.cta img{max-height:260px}.section{padding:60px 0}}
        @media(max-width:560px){.wrap{width:min(100% - 32px,1180px)}.nav{height:66px}.navright{gap:12px}.navright>a:first-child{display:none}.brand{font-size:18px}.brand img{height:32px}h1{font-size:42px;letter-spacing:-2px}.lead{font-size:14px}.dash-body{grid-template-columns:95px 1fr;min-height:280px}.dash-side{padding:12px 7px}.dash-main{padding:13px 10px}.dash-menu{font-size:8px;padding:8px 5px}.dash-title{font-size:12px}.stat{padding:8px}.stat strong{font-size:14px}.booking-list{padding:9px}.feature-strip{grid-template-columns:repeat(2,1fr);margin-top:25px}.feature:nth-child(3){border-right:1px solid #e5edf3}.feature:nth-child(2n){border-right:0}.feature:nth-child(-n+4){border-bottom:1px solid #e5edf3}.journey-grid{grid-template-columns:1fr 1fr;gap:9px}.journey-card{padding:15px}.logos{grid-template-columns:repeat(2,1fr)}.cta-copy{padding:28px 24px}.cta h2{font-size:27px}.section h2{font-size:34px}}
    </style>
</head>
<body>
<header class="nav">
    <div class="wrap" style="display:flex;align-items:center;justify-content:space-between">
        <a class="brand" href="{{ url('/') }}">
            @if($siteLogo)<img src="{{ $siteLogo }}" alt="{{ \App\Support\Branding::siteName() }}">@else<span>{{ \App\Support\Branding::siteName() }}</span>@endif
        </a>
        <nav class="navlinks" aria-label="Main navigation">
            <a href="#features">Features</a><a href="#integrations">Integrations</a><a href="#hosts">For Hosts</a><a href="#guest-journey">For Guests</a>
        </nav>
        <div class="navright">
            <a href="{{ route('login') }}">Log in</a>
            <a class="btn btn-primary" href="{{ route('login') }}">Get started <span>→</span></a>
        </div>
    </div>
</header>

<main>
<section class="hero">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">The all-in-one rental platform</span>
                <h1>Everything behind a great stay, managed in <span>one place.</span></h1>
                <p class="lead">From discovering and booking properties to guest check-in, smart access, housekeeping and checkout — GuestHub connects the entire rental operation.</p>
                <div class="hero-actions"><a class="btn btn-primary" href="{{ route('login') }}">Get started <span>→</span></a><a class="btn btn-outline" href="#features">▷ &nbsp;Explore GuestHub</a></div>
                <div class="trust"><span style="color:#079dcc;font-size:17px">◆</span> Secure. Reliable. Built for short-term rentals.</div>
            </div>
            <div class="dashboard" aria-label="GuestHub dashboard preview">
                <div class="dash-window">
                    <div class="windowbar"><i class="dot"></i><i class="dot"></i><i class="dot"></i></div>
                    <div class="dash-body">
                        <aside class="dash-side"><div class="dash-logo">◈ GuestHub</div><div class="dash-menu active">▦ &nbsp;Dashboard</div><div class="dash-menu">▤ &nbsp;Bookings</div><div class="dash-menu">⌂ &nbsp;Properties</div><div class="dash-menu">♙ &nbsp;Guests</div><div class="dash-menu">♧ &nbsp;Housekeeping</div><div class="dash-menu">▢ &nbsp;Messages</div><div class="dash-menu">⚙ &nbsp;Settings</div></aside>
                        <div class="dash-main"><div class="dash-title">Dashboard</div><div class="stats"><div class="stat"><small>▣ &nbsp;Bookings</small><strong>124</strong><em>↑ 12%</em></div><div class="stat"><small>$ &nbsp;Revenue</small><strong>$24,560</strong><em>↑ 18%</em></div><div class="stat"><small>▥ &nbsp;Occupancy</small><strong>92%</strong><em>↑ 5%</em></div></div><div class="booking-list"><div class="booking-head">Upcoming bookings</div><div class="booking"><span><b>Ocean View Apartment</b><small>Apr 24 – Apr 27 · 2 guests</small></span><span class="pill">Confirmed</span></div><div class="booking"><span><b>City Loft Studio</b><small>Apr 24 – Apr 30 · 1 guest</small></span><span class="pill">Confirmed</span></div></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="feature-strip" id="features">
            <div class="feature"><div class="ico">⌕</div><b>Direct Bookings</b><small>Let guests discover and book your properties</small></div>
            <div class="feature"><div class="ico">▦</div><b>Property Management</b><small>Manage inventory, pricing and availability</small></div>
            <div class="feature"><div class="ico">♢</div><b>Guest Verification</b><small>Verify guests and prepare check-in</small></div>
            <div class="feature"><div class="ico">♙</div><b>Smart Access</b><small>Connect supported smart-lock systems</small></div>
            <div class="feature"><div class="ico">♧</div><b>Housekeeping</b><small>Coordinate your cleaning team</small></div>
            <div class="feature"><div class="ico">▤</div><b>Secure Payments</b><small>Process payments with Stripe</small></div>
        </div>
    </div>
</section>

<section class="section" id="hosts">
    <div class="wrap section-grid">
        <div><span class="eyebrow">A complete rental ecosystem</span><h2>Your entire rental business, <span class="accent">in one platform.</span></h2><p>Manage properties, reservations, guest journeys and daily operations. Keep your team aligned and your properties running smoothly with connected tools.</p>
            <div class="checks">
                <div class="check"><span class="checkmark">●</span><div><b>Discover &amp; book</b><small>Give guests a direct path to browse and reserve available stays.</small></div></div>
                <div class="check"><span class="checkmark">●</span><div><b>Manage</b><small>Keep property details, reservations and availability organized.</small></div></div>
                <div class="check"><span class="checkmark">●</span><div><b>Automate</b><small>Coordinate guest communication, check-in and checkout steps.</small></div></div>
                <div class="check"><span class="checkmark">●</span><div><b>Operate</b><small>Assign cleaning tasks and keep property teams informed.</small></div></div>
            </div>
            <a class="btn btn-primary" href="{{ route('login') }}">Explore GuestHub →</a>
        </div>
        <div class="photo-card"><img src="https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1200&q=85" alt="Bright modern vacation rental living room" loading="lazy"><div class="photo-note"><b>✓ &nbsp;Stay operations, connected</b><span>Keep every detail in view.</span></div></div>
    </div>
</section>

<section class="section journey" id="guest-journey">
    <div class="wrap"><div style="text-align:center"><span class="eyebrow">A smoother guest experience</span><h2>From booking to checkout, <span class="accent">all connected.</span></h2><p style="margin:0 auto">Give guests clear instructions and a more organized stay, while reducing repetitive work for your team.</p></div>
        <div class="journey-grid">
            <article class="journey-card"><div class="num">01 / BEFORE ARRIVAL</div><h3>Guest verification</h3><p>Collect guest details and complete required verification before arrival.</p></article>
            <article class="journey-card"><div class="num">02 / PRE-CHECK-IN</div><h3>Agreements &amp; payments</h3><p>Manage rental agreements, signatures, deposits and payment steps.</p></article>
            <article class="journey-card"><div class="num">03 / ARRIVAL</div><h3>Check-in &amp; smart access</h3><p>Guide guests through arrival and supported connected-lock access.</p></article>
            <article class="journey-card"><div class="num">04 / DURING THE STAY</div><h3>Digital guest guide</h3><p>Share property information, instructions and helpful stay details.</p></article>
        </div>
    </div>
</section>

<section class="section" id="operations">
    <div class="wrap section-grid">
        <div class="photo-card"><img src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=85" alt="Contemporary short-term rental property interior" loading="lazy"></div>
        <div><span class="eyebrow">Property operations</span><h2>Keep the people and work behind every stay <span class="accent">in sync.</span></h2><p>Give cleaners and operations staff the information they need, assign work clearly, and keep track of completion across your properties.</p>
            <div class="checks">
                <div class="check"><span class="checkmark">●</span><div><b>Cleaner accounts</b><small>Give cleaning teams access to their assigned work.</small></div></div>
                <div class="check"><span class="checkmark">●</span><div><b>Task assignments</b><small>Coordinate turnovers, checklists and property instructions.</small></div></div>
                <div class="check"><span class="checkmark">●</span><div><b>Team visibility</b><small>See task progress and keep operations accountable.</small></div></div>
            </div>
        </div>
    </div>
</section>

<section class="section integrations" id="integrations">
    <div class="wrap"><span class="eyebrow">Connected tools</span><h2>Works with the tools you already use.</h2><p style="margin:0 auto">Connect supported services and build a more connected rental workflow.</p>
        <div class="logos"><div class="logo-tile">◉ Channex</div><div class="logo-tile">◉ SEAM</div><div class="logo-tile">August</div><div class="logo-tile">PriceLabs</div><div class="logo-tile">WHEELHOUSE</div><div class="logo-tile">stripe</div></div>
        <p style="font-size:10px;margin:16px auto 0">Integration availability and capabilities depend on your account configuration and provider support.</p>
    </div>
</section>

<section class="section">
    <div class="wrap teams">
        <div><span class="eyebrow">Built for teams</span><h2>Your entire team, working together.</h2><p>From property owners and managers to cleaners and operations teams, everyone has a place in GuestHub.</p><a class="btn btn-primary" href="{{ route('login') }}">Explore team features →</a></div>
        <div class="team-cards"><div class="team-card"><div class="ico">♙</div><b>Property Owners</b><small>Keep an eye on your rental business.</small></div><div class="team-card"><div class="ico">♧</div><b>Property Managers</b><small>Coordinate daily operations.</small></div><div class="team-card"><div class="ico">⌂</div><b>Cleaners</b><small>Get assigned tasks and instructions.</small></div><div class="team-card"><div class="ico">▣</div><b>Operations Teams</b><small>Keep activities moving smoothly.</small></div></div>
    </div>
</section>

<section class="wrap cta">
    <div class="cta-copy"><span class="eyebrow">Ready to get started?</span><h2>Take control of your rental business.</h2><p>Bring bookings, guest experience and property operations into one connected workspace.</p><a class="btn btn-primary" href="{{ route('login') }}">Get started →</a></div>
    <img src="https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1200&q=85" alt="Modern vacation rental home exterior" loading="lazy">
</section>
</main>

<footer><div class="wrap footer-row"><span>{{ \App\Models\Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub') }}</span><span><a href="{{ route('contact') }}">Contact</a> &nbsp;·&nbsp; <a href="{{ route('legal.terms') }}">Terms</a> &nbsp;·&nbsp; <a href="{{ route('legal.privacy') }}">Privacy</a> &nbsp;·&nbsp; <a href="{{ route('login') }}">Log in</a></span></div></footer>
</body>
</html>

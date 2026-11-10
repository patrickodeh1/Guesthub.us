@php
    $brandSiteName = \App\Support\Branding::siteName();
    $siteLogo = \App\Support\Branding::logoUrl();
    $siteThemeColor = \App\Support\Branding::themeColor();
    $siteButtonColor = \App\Support\Branding::buttonColor('primary');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title') · {{ $brandSiteName }}</title>
    <meta name="description" content="@yield('meta_description')">
    <link rel="canonical" href="@yield('canonical')">
    @if(!empty($noindex))
        <meta name="robots" content="noindex,follow">
    @else
        <meta name="robots" content="index,follow">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brandSiteName }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('meta_description')">
    <meta property="og:url" content="@yield('canonical')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title')">
    <meta name="twitter:description" content="@yield('meta_description')">
    @stack('head')
    <style>
        :root{--site-brand:{{ $siteThemeColor }};--site-button:{{ $siteButtonColor }};--site-button-hover:color-mix(in srgb,var(--site-button) 86%,black);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17221e;background:#f7f8f6;font-synthesis:none;text-rendering:optimizeLegibility}
        *{box-sizing:border-box}body{margin:0}a{color:inherit}img{display:block;max-width:100%;height:auto}
        .site-header{background:#fff;border-bottom:1px solid #e1e7e2}.site-header-inner,.site-footer-inner{max-width:1180px;margin:auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .brand{display:inline-flex;align-items:center;color:var(--site-brand);font-weight:800;letter-spacing:-.04em;text-decoration:none;font-size:1.35rem}.brand-logo{max-width:180px;max-height:44px;width:auto;object-fit:contain}.header-link{font-size:.92rem;font-weight:650;text-decoration:none}
        main{max-width:1180px;margin:auto;padding:32px 24px 64px}.eyebrow{text-transform:uppercase;letter-spacing:.13em;color:var(--site-brand);font-size:.72rem;font-weight:800}
        h1{font-size:clamp(2rem,4vw,3.5rem);letter-spacing:-.055em;line-height:1.05;margin:.45rem 0 1rem}h2{letter-spacing:-.03em}.lede{font-size:1.08rem;line-height:1.6;color:#53625b;max-width:760px}
        .search-panel{background:#fff;border:1px solid #e0e7e2;border-radius:16px;padding:18px;margin:26px 0;box-shadow:0 12px 30px #17221e0a}
        .search-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;align-items:end}.field label{display:block;font-size:.76rem;font-weight:750;margin:0 0 6px}
        .checkout-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        input,select{width:100%;min-height:44px;border:1px solid #cbd6cf;border-radius:9px;padding:8px 10px;background:#fff;color:#17221e;font:inherit}
        .button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:0;border-radius:9px;padding:10px 18px;background:var(--site-button);color:white;font:inherit;font-weight:750;text-decoration:none;cursor:pointer}.button:hover{background:var(--site-button-hover)}
        .split-heading{display:flex;justify-content:space-between;align-items:end;gap:16px;margin:34px 0 16px}.split-heading h2{margin:0}.results-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:22px;align-items:start}
        .listing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.listing-card,.panel{background:#fff;border:1px solid #e0e7e2;border-radius:14px;overflow:hidden}
        .listing-card>a{text-decoration:none}.listing-card img{width:100%;aspect-ratio:3/2;object-fit:cover;background:#e6ebe7}.card-body{padding:14px 15px 16px}.card-body h2{font-size:1.08rem;margin:0 0 5px}.muted{color:#66746d;font-size:.9rem}.price{font-weight:800;margin-top:12px}
        .map-panel{position:sticky;top:18px}.map-title{padding:14px 16px 0;margin:0;font-size:1rem}.map-canvas{height:420px;min-height:300px;background:#e7ece8}.map-note{padding:10px 16px;color:#66746d;font-size:.8rem}
        .empty{background:#fff;border:1px dashed #cbd6cf;border-radius:14px;padding:30px;color:#53625b}.pagination{margin-top:24px}
        .gallery{display:grid;grid-template-columns:2fr 1fr 1fr;grid-template-rows:repeat(2,minmax(150px,220px));gap:10px;border-radius:18px;overflow:hidden;margin:26px 0}.gallery img{width:100%;height:100%;object-fit:cover;background:#e6ebe7}.gallery img:first-child{grid-row:span 2}
        .detail-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:32px;align-items:start}.detail-copy{line-height:1.75;color:#34433c}.detail-aside{position:sticky;top:18px}.panel{padding:20px}.panel h2:first-child{margin-top:0}.specs{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.spec{border:1px solid #dfe6e1;background:#fff;border-radius:99px;padding:6px 11px;font-size:.84rem}
        .calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px;margin:14px 0}.calendar-day{min-height:54px;border-radius:7px;border:1px solid #dce5de;background:color-mix(in srgb,var(--site-brand) 5%,white);text-align:center;padding:6px 2px;font-size:.72rem}.calendar-day.blocked{background:#edf0ee;color:#87918b;text-decoration:line-through}.calendar-day small{display:block;font-size:.65rem}
        .quote-lines{display:grid;gap:8px;margin:12px 0}.quote-line{display:flex;justify-content:space-between;gap:12px;font-size:.9rem}.quote-total{font-weight:850;font-size:1.12rem;border-top:1px solid #dfe6e1;padding-top:12px}.status-message{min-height:22px;color:#8a3c30;font-size:.88rem}
        .site-footer{background:color-mix(in srgb,var(--site-brand) 5%,white);border-top:1px solid #dce5de}.site-footer-inner{color:#53625b;font-size:.85rem}.site-footer a{font-weight:700}
        @media(max-width:850px){.search-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.checkout-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.results-layout{grid-template-columns:1fr}.map-panel{position:static;grid-row:1}.map-canvas{height:320px}.detail-grid{grid-template-columns:1fr}.detail-aside{position:static}}
        @media(max-width:580px){.site-header-inner,.site-footer-inner{padding:14px 16px}main{padding:24px 16px 48px}.search-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.checkout-grid{grid-template-columns:1fr}.listing-grid{grid-template-columns:1fr}.gallery{grid-template-columns:1fr 1fr;grid-template-rows:240px 130px 130px}.gallery img:first-child{grid-column:span 2;grid-row:auto}.split-heading{align-items:start;flex-direction:column}.calendar-day{min-height:46px}}
        @font-face{font-family:Inter;src:local("Inter"),local("Inter Regular");font-style:normal;font-weight:400;font-display:swap}
        html,body,button,input,select,textarea{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        button,input,select,textarea{font-size:inherit}
        button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid color-mix(in srgb,var(--site-brand) 50%,white);outline-offset:2px}
        [hidden]{display:none!important}
        .stay-page{padding-bottom:32px}
        .stay-heading{margin:4px 0 22px}
        .stay-title{font-size:clamp(1.75rem,3vw,2.5rem);line-height:1.16;letter-spacing:-.04em;margin:0 0 12px}
        .stay-meta{list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px 18px;color:#53625b;font-size:.94rem}
        .stay-meta li{display:inline-flex;align-items:center;gap:7px;min-height:28px}
        .stay-meta svg,.policy-block>svg,.amenity-item svg,.host-card-mark svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}
        .stay-host-line{margin:8px 0 0;color:#53625b;font-size:.95rem}
        .stay-host-line a,.host-card a{color:var(--site-brand);font-weight:650;text-underline-offset:3px}
        .stay-gallery{position:relative;display:grid;grid-template-columns:1.25fr .75fr .75fr;grid-template-rows:repeat(2,minmax(150px,220px));gap:8px;border-radius:16px;overflow:hidden;margin:22px 0 16px;isolation:isolate}
        .photo-trigger{display:block;position:relative;min-width:0;min-height:0;padding:0;border:0;background:#e6ebe7;overflow:hidden;cursor:pointer}
        .stay-gallery .photo-trigger:nth-child(n+6){display:none}
        .photo-trigger::after{content:"";position:absolute;inset:0;background:transparent;transition:background .18s ease}
        .photo-trigger:hover::after{background:color-mix(in srgb,var(--site-brand) 16%,transparent)}
        .photo-trigger img{width:100%;height:100%;object-fit:cover}
        .photo-trigger:first-child{grid-column:1;grid-row:span 2}
        .photos-1{grid-template-columns:1fr;grid-template-rows:minmax(240px,420px);aspect-ratio:2.2/1}
        .photos-1 .photo-trigger:first-child{grid-row:1}
        .photos-2{grid-template-columns:1.2fr .8fr;grid-template-rows:minmax(240px,420px)}
        .photos-2 .photo-trigger:first-child{grid-row:1}
        .photos-3{grid-template-columns:1.2fr .8fr;grid-template-rows:repeat(2,minmax(130px,1fr))}
        .photos-3 .photo-trigger:first-child{grid-row:span 2}
        .photos-4 .photo-trigger:nth-child(4){grid-column:2/span 2}
        .show-all-photos,.gallery-counter{position:absolute;z-index:2;right:16px;bottom:16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 14px;border:1px solid #dfe6e1;border-radius:9px;background:#fff;color:#17221e;font:inherit;font-size:.88rem;font-weight:650;box-shadow:0 4px 14px #17221e14}
        .show-all-photos{cursor:pointer}
        .show-all-photos svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8}
        .gallery-counter{display:none}
        .photo-dialog,.amenities-dialog{width:min(100vw,1100px);max-width:100vw;max-height:100dvh;height:100dvh;margin:0;padding:20px;border:0;border-radius:0;background:color-mix(in srgb,var(--site-brand) 96%,black);color:#fff}
        .photo-dialog{width:100vw}
        .photo-dialog::backdrop,.amenities-dialog::backdrop{background:rgb(0 0 0 / .78)}
        .photo-dialog[open],.amenities-dialog[open]{display:flex;flex-direction:column}
        .dialog-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:44px}
        .icon-button{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;flex:0 0 44px;border:1px solid color-mix(in srgb,var(--site-brand) 25%,white);border-radius:50%;background:#fff;color:var(--site-brand);cursor:pointer}
        .icon-button svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .lightbox-content{display:grid;grid-template-columns:52px minmax(0,1fr) 52px;align-items:center;justify-items:center;gap:16px;flex:1;min-height:0;padding:12px 0}
        .lightbox-figure{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%;margin:0;min-width:0;min-height:0}
        .lightbox-figure img{width:auto;height:auto;max-width:100%;max-height:calc(100dvh - 116px);object-fit:contain}
        .lightbox-figure figcaption{min-height:24px;margin-top:8px;color:#fff;text-align:center;font-size:.9rem}
        .amenities-dialog{width:min(720px,calc(100vw - 32px));height:auto;max-height:calc(100dvh - 32px);margin:auto;padding:20px;border:1px solid #dfe6e1;border-radius:16px;background:#fff;color:#17221e}
        .amenities-dialog .dialog-toolbar{border-bottom:1px solid #e1e7e2;padding-bottom:12px}
        .amenities-dialog .dialog-toolbar h2{margin:0;font-size:1.25rem}
        .amenities-dialog-list{grid-template-columns:repeat(2,minmax(0,1fr));max-height:65dvh;overflow:auto;padding:16px 4px}
        .section-nav{position:sticky;top:0;z-index:8;display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;margin:0 0 24px;padding:8px 0;border-bottom:1px solid #e1e7e2;background:#f7f8f6}
        .section-nav::-webkit-scrollbar{display:none}
        .section-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;flex:0 0 auto;padding:0 14px;border-bottom:2px solid transparent;color:#53625b;font-size:.9rem;font-weight:600;text-decoration:none}
        .section-nav a:hover,.section-nav a.is-active{border-color:var(--site-brand);color:var(--site-brand)}
        .stay-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.75fr);gap:clamp(24px,4vw,48px);align-items:start}
        .stay-content{min-width:0}
        .stay-section{padding:24px 0;border-bottom:1px solid #e1e7e2;scroll-margin-top:76px}
        .stay-section:first-child{padding-top:8px}
        .stay-section h2,.host-card h2,.lease-card h2{margin:0 0 14px;font-size:1.25rem;line-height:1.35;letter-spacing:-.02em}
        .stay-description,.policy-block p,.section-copy{font-size:1rem;line-height:1.65;color:#34433c}
        .stay-description p,.policy-block p{margin:0;white-space:normal}
        .description-long.is-collapsed{max-height:10.4em;overflow:hidden;position:relative}
        .description-long.is-collapsed::after{content:"";position:absolute;inset:auto 0 0;height:2.5em;background:linear-gradient(transparent,#f7f8f6)}
        .text-action{display:inline-flex;align-items:center;min-height:44px;margin-top:6px;padding:0;border:0;background:none;color:var(--site-brand);font:inherit;font-weight:700;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
        .amenity-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 20px;list-style:none;margin:0;padding:0}
        .amenity-item{display:flex;align-items:center;gap:12px;min-height:44px;font-size:.96rem;line-height:1.4;overflow-wrap:anywhere}
        .amenity-item svg{width:21px;height:21px;color:var(--site-brand)}
        .secondary-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:18px;padding:0 16px;border:1px solid #17221e;border-radius:9px;background:#fff;color:#17221e;font:inherit;font-weight:650;text-decoration:none;cursor:pointer}
        .secondary-button:hover{background:#f7f8f6}
        .location-map{display:block;width:100%;height:340px;margin-top:16px;border:1px solid #dfe6e1;border-radius:14px;background:#e7ece8}
        .calendar-months{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:18px}
        .calendar-months-hint{display:none;margin:10px 0;color:#53625b;font-size:.85rem}
        .calendar-month{min-width:0}
        .calendar-month h3{margin:0 0 12px;text-align:center;font-size:1rem;font-weight:700}
        .calendar-weekdays,.calendar-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;text-align:center}
        .calendar-weekdays{margin-bottom:6px;color:#53625b;font-size:.76rem;font-weight:650}
        .calendar-weekdays span{display:grid;place-items:center;min-height:32px}
        .calendar-blank{min-height:44px}
        .calendar-day{display:grid;place-items:center;width:100%;min-width:0;min-height:44px;padding:0;border:1px solid transparent;border-radius:50%;background:transparent;color:#17221e;font:inherit;font-size:.86rem;cursor:pointer}
        .calendar-day:hover:not(:disabled){background:color-mix(in srgb,var(--site-brand) 10%,white)}
        .calendar-day.is-unavailable{color:#53625b;text-decoration:line-through}
        .calendar-day:disabled{color:#66746d;cursor:not-allowed;opacity:.72}
        .calendar-day.is-today{border-color:var(--site-brand);font-weight:800}
        .calendar-day.is-start,.calendar-day.is-end{background:var(--site-brand);color:#fff;font-weight:700;text-decoration:none}
        .calendar-day.in-range{border-radius:8px;background:color-mix(in srgb,var(--site-brand) 14%,white);color:#17221e;text-decoration:none}
        .calendar-hint{min-height:24px;margin:12px 0 0;color:#53625b;font-size:.88rem;line-height:1.5}
        .policy-block{display:grid;grid-template-columns:24px minmax(0,1fr);gap:14px;padding:18px 0;border-top:1px solid #e1e7e2}
        .policy-block:first-of-type{border-top:0;padding-top:0}
        .policy-block>svg{margin-top:2px;color:var(--site-brand)}
        .policy-block h3{margin:0 0 6px;font-size:1rem}
        .host-card{display:flex;align-items:flex-start;gap:16px;margin-top:24px;padding:20px;border:1px solid #e1e7e2;border-radius:14px;background:#fff}
        .host-card-mark{display:grid;place-items:center;width:48px;height:48px;flex:0 0 48px;border-radius:50%;background:color-mix(in srgb,var(--site-brand) 8%,white);color:var(--site-brand)}
        .host-card-mark svg{width:24px;height:24px}
        .host-card h2{margin:0 0 8px;font-size:1.08rem}
        .host-card a{display:block;width:fit-content;min-height:32px}
        .lease-card{margin-top:24px;padding:20px;border:1px solid #e1e7e2;border-radius:14px;background:color-mix(in srgb,var(--site-brand) 3%,white)}
        .lease-card h2{margin-bottom:8px}
        .lease-card p{margin:0;color:#53625b;font-size:.93rem;line-height:1.55}
        .lease-card p a{color:var(--site-brand);font-weight:650;text-underline-offset:3px}
        .lease-card .secondary-button{margin-top:14px}
        .booking-column{position:sticky;top:18px;min-width:0}
        .booking-card{padding:22px;border:1px solid #dfe6e1;border-radius:16px;background:#fff;box-shadow:0 12px 30px #17221e0a}
        .booking-price h2{margin:0 0 18px;font-size:1.65rem;line-height:1.2;letter-spacing:-.03em}
        .booking-price h2 span{font-size:.95rem;font-weight:400;letter-spacing:0;color:#53625b}
        .booking-form{display:grid;gap:12px}
        .date-control{display:grid;grid-template-columns:minmax(0,1fr) 1px minmax(0,1fr);align-items:stretch;border:1px solid #cbd6cf;border-radius:11px;overflow:hidden}
        .date-control label{display:flex;flex-direction:column;justify-content:center;min-width:0;min-height:72px;padding:10px 12px;cursor:pointer}
        .date-control label span,.booking-field>span{display:block;margin-bottom:4px;color:#53625b;font-size:.72rem;font-weight:700}
        .date-control input{width:100%;min-width:0;min-height:28px;padding:0;border:0;border-radius:0;background:transparent;font-size:.86rem}
        .date-control input:focus-visible{outline:2px solid var(--site-brand);outline-offset:2px}
        .date-divider{width:1px;background:#dfe6e1}
        .booking-field{display:block;min-width:0;min-height:68px;padding:10px 12px;border:1px solid #cbd6cf;border-radius:11px}
        .booking-field input,.booking-field select{min-height:28px;padding:0;border:0;border-radius:0;background:transparent;font-size:.9rem}
        .primary-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:48px;padding:10px 16px;border:0;border-radius:10px;background:var(--site-button);color:#fff;font:inherit;font-weight:700;text-align:center;text-decoration:none;cursor:pointer}
        .primary-button:hover{background:var(--site-button-hover)}
        .primary-button:disabled{cursor:wait;opacity:.8}
        .button-spinner{display:none;width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-dasharray:38;stroke-linecap:round;animation:spin 1s linear infinite}
        .button-spinner:not([hidden]){display:block}
        @keyframes spin{to{transform:rotate(360deg)}}
        .listing-card{border-radius:16px;transition:transform .18s ease,box-shadow .18s ease}
        .listing-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px #17221e0a}
        .site-header{box-shadow:0 1px 3px #17221e08}
        .quote-error{min-height:0;margin:0;color:#8a3c30;font-size:.9rem;line-height:1.5}
        .quote-error:not(:empty){min-height:24px;margin-top:10px}
        .quote-result{margin-top:16px;padding-top:16px;border-top:1px solid #dfe6e1}
        .quote-breakdown{display:grid;gap:10px}
        .quote-line,.quote-total{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;color:#34433c;font-size:.92rem;line-height:1.45}
        .quote-line span:first-child{min-width:0;overflow-wrap:anywhere}
        .quote-line span:last-child{flex:0 0 auto;white-space:nowrap}
        .quote-total{align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid #dfe6e1;color:#17221e;font-size:1rem}
        .quote-total strong{font-size:1.18rem}
        .charge-note{margin:10px 0 14px;color:#53625b;font-size:.82rem;text-align:center}
        .booking-reassurance{margin:12px 4px;color:#53625b;font-size:.83rem;line-height:1.5;text-align:center}
        .mobile-booking-bar{display:none}
        @media(max-width:900px){.stay-layout{grid-template-columns:minmax(0,1fr) minmax(290px,.85fr);gap:24px}.stay-gallery{grid-template-rows:repeat(2,minmax(140px,190px))}}
        @media(max-width:760px){.stay-layout{grid-template-columns:1fr}.booking-column{position:static}.section-nav{display:none}.calendar-months{gap:16px}.booking-card{max-width:none}}
        @media(max-width:600px){
            .stay-page{padding-bottom:104px}
            .stay-heading{margin-top:0}
            .stay-meta{gap:4px 12px;font-size:.86rem}
            .stay-meta li{min-height:30px}
            .stay-gallery{display:flex;gap:8px;width:calc(100% + 32px);height:clamp(260px,72vw,390px);margin:18px -16px 16px;padding:0 16px;border-radius:0;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scrollbar-width:none}
            .stay-gallery::-webkit-scrollbar{display:none}
            .photo-trigger,.stay-gallery .photo-trigger:nth-child(n+6),.photos-1 .photo-trigger:first-child,.photos-2 .photo-trigger:first-child,.photos-3 .photo-trigger:first-child{display:block;flex:0 0 88%;height:100%;grid-column:auto;grid-row:auto;border-radius:12px;overflow:hidden;scroll-snap-align:center}
            .show-all-photos{display:none}
            .gallery-counter{display:inline-flex;right:26px;bottom:12px;min-height:36px;padding:0 11px;font-size:.82rem}
            .amenities-dialog-list{grid-template-columns:1fr}
            .calendar-months{display:flex;gap:0;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none}
            .calendar-months::-webkit-scrollbar{display:none}
            .calendar-month{flex:0 0 100%;scroll-snap-align:start}
            .calendar-months-hint{display:block}
            .calendar-weekdays{font-size:.8rem}
            .stay-section{padding:22px 0}
            .stay-section h2{font-size:1.2rem}
            .location-map{height:280px}
            .booking-card{padding:18px}
            .mobile-booking-bar{position:fixed;z-index:20;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:76px;padding:10px 16px calc(10px + env(safe-area-inset-bottom));border-top:1px solid #dfe6e1;background:#fff;box-shadow:0 -8px 20px #17221e0a}
            .mobile-booking-bar>div{white-space:nowrap;font-size:.86rem;color:#53625b}
            .mobile-booking-bar strong{color:#17221e;font-size:1.05rem}
            .mobile-booking-bar .primary-button{width:auto;min-width:170px;min-height:48px}
            .photo-dialog{padding:12px}
            .lightbox-content{grid-template-columns:44px minmax(0,1fr) 44px;gap:4px}
            .lightbox-arrow{width:40px;height:40px;flex-basis:40px}
            .lightbox-figure img{max-height:calc(100dvh - 112px)}
        }
        @media(max-width:370px){.mobile-booking-bar{gap:8px;padding-right:10px;padding-left:10px}.mobile-booking-bar .primary-button{min-width:145px;padding-inline:10px}.stay-meta{gap:4px 8px;font-size:.82rem}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
    </style>
</head>
<body>
<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="{{ route('public.stays.index') }}" aria-label="{{ $brandSiteName }} stays home">
            @if($siteLogo)
                <img class="brand-logo" src="{{ $siteLogo }}" alt="{{ $brandSiteName }}">
            @else
                {{ $brandSiteName }}
            @endif
        </a>
        <a class="header-link" href="{{ route('public.stays.index') }}">Find a stay</a>
    </div>
</header>
<main>
    @yield('content')
</main>
<footer class="site-footer">
    <div class="site-footer-inner"><span>{{ $brandSiteName }} · Places to stay, directly from the host.</span><a href="{{ route('public.stays.index') }}">Browse stays</a></div>
</footer>
@stack('scripts')
</body>
</html>

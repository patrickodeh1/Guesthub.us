@php($pcInput = $inputClass ?? 'input')
<style>
.guest-phone-picker{display:flex;align-items:flex-start;gap:8px;width:100%;min-width:0;margin-top:4px}
.guest-phone-country-wrap{position:relative;flex:0 0 112px;min-width:0}
.guest-phone-country-button{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;min-height:48px;padding:0 10px;border:1px solid #cfd8d2;border-radius:12px;background:#fff;color:#17221e;font:inherit;cursor:pointer}
.guest-phone-country-label{display:inline-flex;align-items:center;gap:6px;min-width:0;font-size:14px;font-weight:600}
.guest-phone-country-flag{display:inline-flex;align-items:center;justify-content:center;width:22px;height:16px;flex:0 0 22px}
.guest-phone-country-flag img{display:block;width:22px;height:16px;max-width:none;object-fit:cover}
.guest-phone-country-menu{position:absolute;top:calc(100% + 6px);left:0;z-index:10000;width:min(320px,calc(100vw - 40px));overflow:hidden;border:1px solid #d9e1dc;border-radius:12px;background:#fff;box-shadow:0 12px 32px rgba(20,35,28,.16)}
.guest-phone-country-menu.hidden{display:none!important}
.guest-phone-country-search-wrap{padding:9px;border-bottom:1px solid #edf0ee;background:#fff}
.guest-phone-country-search{display:block;width:100%;min-height:42px;box-sizing:border-box;padding:9px 11px;border:1px solid #cfd8d2;border-radius:8px;background:#fff;color:#17221e;font:inherit;font-size:14px}
.guest-phone-country-list{max-height:240px;overflow-y:auto;padding:4px;background:#fff;color:#17221e}
.guest-phone-country-option{display:flex;align-items:center;gap:10px;width:100%;min-height:40px;padding:7px 10px;border:0;border-radius:7px;background:#fff;color:#17221e;text-align:left;font:inherit;font-size:14px;cursor:pointer}
.guest-phone-country-option:hover,.guest-phone-country-option:focus{background:#f1f5f2;outline:none}
.guest-phone-country-option img{display:block;width:22px;height:16px;flex:0 0 22px;object-fit:cover}
.guest-phone-number{flex:1 1 auto;min-width:0;width:0;box-sizing:border-box}
@media(max-width:420px){.guest-phone-country-wrap{flex-basis:104px}.guest-phone-country-menu{width:min(300px,calc(100vw - 32px))}}
</style>
<div class="guest-phone-picker">
    <div class="guest-phone-country-wrap">
        <button type="button" id="guest-phone-country-button" class="guest-phone-country-button" aria-haspopup="listbox" aria-expanded="false" aria-controls="guest-phone-country-menu">
            <span id="guest-phone-country-label" class="guest-phone-country-label"><span id="guest-phone-country-flag" class="guest-phone-country-flag">🇺🇸</span><span id="guest-phone-country-dial">+1</span></span>
            <span aria-hidden="true" style="font-size:12px;color:#64748b">▾</span>
        </button>
        <div id="guest-phone-country-menu" class="guest-phone-country-menu hidden">
            <div class="guest-phone-country-search-wrap"><input type="text" id="guest-phone-country-search" placeholder="Search country or code" class="guest-phone-country-search" autocomplete="off" aria-label="Search country or calling code"></div>
            <div id="guest-phone-country-list" class="guest-phone-country-list" role="listbox"></div>
        </div>
    </div>
    <input type="hidden" id="guest-phone-country-code" name="phone_country_code" value="{{ old('phone_country_code', '+1') }}">
    <input id="{{ $id ?? 'phone' }}" name="{{ $name ?? 'phone_number' }}" type="tel" value="{{ $value ?? '' }}" data-full-number="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? '801 234 5678' }}" class="{{ $pcInput }} guest-phone-number" autocomplete="tel">
</div>
<script src="{{ asset('js/guest-phone-country.js') }}?v=20261112-2"></script>

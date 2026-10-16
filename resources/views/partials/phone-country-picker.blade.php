@php($pcInput = $inputClass ?? 'input')
<div class="mt-1 flex gap-2">
    <div class="relative w-28 shrink-0">
        <button type="button" id="guest-phone-country-button" class="{{ $pcInput }} flex items-center justify-between gap-1.5 px-2.5 text-left">
            <span id="guest-phone-country-label" class="flex items-center gap-1.5 text-sm font-medium"><span id="guest-phone-country-flag">🇺🇸</span><span id="guest-phone-country-dial">+1</span></span>
            <span aria-hidden="true" class="text-xs text-slate-400">▾</span>
        </button>
        <div id="guest-phone-country-menu" class="absolute left-0 top-full z-20 mt-1 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
            <div class="border-b border-slate-100 p-2"><input type="text" id="guest-phone-country-search" placeholder="Search country or code" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-900" autocomplete="off"></div>
            <div id="guest-phone-country-list" class="max-h-56 overflow-y-auto text-slate-900"></div>
        </div>
    </div>
    <input type="hidden" id="guest-phone-country-code" name="phone_country_code" value="+1">
    <input id="{{ $id ?? 'phone' }}" name="{{ $name ?? 'phone_number' }}" type="tel" value="{{ $value ?? '' }}" data-full-number="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? '801 234 5678' }}" class="{{ $pcInput }} min-w-0 flex-1" autocomplete="tel-national">
</div>
<script src="{{ asset('js/guest-phone-country.js') }}"></script>

{{--
    Small "?" icon that, when clicked, shows a short explanation popover.
    Usage: <x-help text="What this field does." />
    Click-to-open (not hover-only) per requirement that these work on
    click; closes when another help icon is opened or when clicking
    outside. Shared toggleHelp()/document click-outside listener lives in
    resources/views/layouts/admin.blade.php so this works from any page
    without each page re-implementing the JS.
--}}
@props(['text'])
<span class="relative inline-block align-middle" data-help-wrapper>
    <button type="button" onclick="toggleHelp(this)" aria-label="What does this mean?" class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 text-[10px] font-bold leading-none text-slate-400 transition hover:border-slate-400 hover:text-slate-600">?</button>
    <span class="hidden absolute left-1/2 top-full z-30 mt-1.5 w-56 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-2.5 text-xs leading-5 text-slate-600 shadow-lg" data-help-panel>{{ $text }}</span>
</span>

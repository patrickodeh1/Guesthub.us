@props(['text' => null])
<span x-data="{ open: false }" class="inline-block align-middle">
    <button type="button" @click.prevent.stop="open = !open" :aria-expanded="open" aria-label="Show help"
        class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 text-[10px] font-bold leading-none text-slate-500 hover:bg-slate-100">?</button>
    <span x-show="open" x-cloak class="mt-1 block text-xs font-normal text-slate-500">@if($text){{ $text }}@else{{ $slot }}@endif</span>
</span>

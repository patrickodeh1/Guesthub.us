@php
    $navSections = collect($navigation)->filter(fn ($s) => ! empty($s['items']));
    $pinnedSections = $navSections->filter(fn ($s) => trim($s['label'] ?? '') === '');
    $regularSections = $navSections->filter(fn ($s) => trim($s['label'] ?? '') !== '');
@endphp
{{-- pr-5 keeps chevrons clear of the sidebar scrollbar; min-h-full + mt-auto pins the gear to the bottom --}}
<nav class="flex flex-1 flex-col gap-1 pb-4 pl-3 pr-5 text-sm" data-tour="sidebar-nav">
    @foreach($regularSections as $section)
        <p class="mt-4 mb-1 px-3 text-xs font-bold uppercase tracking-widest text-slate-400">{{ $section['label'] }}</p>

        @foreach($section['items'] as $item)
            @include('layouts.partials.navigation-item', ['item' => $item, 'nested' => false])
        @endforeach
    @endforeach

    @if($pinnedSections->isNotEmpty())
        <div class="mt-auto grid gap-1 border-t border-white/10 pt-3">
            @foreach($pinnedSections as $section)
                @foreach($section['items'] as $item)
                    @include('layouts.partials.navigation-item', ['item' => $item, 'nested' => false])
                @endforeach
            @endforeach
        </div>
    @endif
</nav>

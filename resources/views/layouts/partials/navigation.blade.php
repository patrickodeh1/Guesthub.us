<nav class="grid gap-1 px-3 pb-4 text-sm" data-tour="sidebar-nav">
    @foreach($navigation as $section)
        @if($section['items'])
            <p class="mt-4 mb-1 px-3 text-xs font-bold uppercase tracking-widest text-slate-400">{{ $section['label'] }}</p>

            @foreach($section['items'] as $item)
                @include('layouts.partials.navigation-item', ['item' => $item, 'nested' => false])
            @endforeach
        @endif
    @endforeach
</nav>

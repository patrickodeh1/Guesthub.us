@php($nested = $nested ?? false)

@if($item['collapsible'])
    @if($item['href'])
        <div data-nav-dropdown>
            <div class="flex items-center rounded-sm {{ $item['active'] ? 'bg-white/10 text-white' : 'text-slate-200' }}">
                <a href="{{ $item['href'] }}"
                   @if($item['tour']) data-tour="{{ $item['tour'] }}" @endif
                   class="flex min-w-0 flex-1 items-center gap-2.5 px-3 py-2.5 transition hover:text-white">
                    @unless($nested)
                        <span class="grid h-5 w-5 shrink-0 place-items-center">
                            <x-icon :name="$item['icon']" class="h-4 w-4" />
                        </span>
                    @endunless
                    <span class="min-w-0 flex-1 font-medium">{{ $item['label'] }}</span>
                </a>
                <button type="button"
                        aria-label="Toggle {{ $item['label'] }} submenu"
                        aria-controls="{{ $item['submenu_id'] }}"
                        aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
                        onclick="const submenu = document.getElementById(this.getAttribute('aria-controls')); submenu.classList.toggle('hidden'); this.setAttribute('aria-expanded', submenu.classList.contains('hidden') ? 'false' : 'true'); this.querySelector('svg').classList.toggle('rotate-90')"
                        class="grid h-9 w-8 shrink-0 place-items-center rounded-sm transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30 {{ $item['active'] ? 'rotate-90' : '' }}">
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" />
                </button>
            </div>
            <div id="{{ $item['submenu_id'] }}" data-nav-submenu class="ml-2 grid min-w-0 gap-1 border-l border-white/10 pl-2 {{ $item['active'] ? '' : 'hidden' }}">
                @foreach($item['children'] as $child)
                    @include('layouts.partials.navigation-item', ['item' => $child, 'nested' => true])
                @endforeach
            </div>
        </div>
    @else
        <details class="group/nav-section" @if($item['active']) open @endif>
            <summary data-tour="{{ $item['tour'] ?? 'nav-' . \Illuminate\Support\Str::slug($item['label']) }}"
                     class="flex cursor-pointer list-none items-center gap-2.5 rounded-sm px-3 py-2.5 text-slate-200 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/30 [&::-webkit-details-marker]:hidden">
                @unless($nested)
                    <span class="grid h-5 w-5 shrink-0 place-items-center">
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                    </span>
                @endunless
                <span class="min-w-0 flex-1 font-medium">{{ $item['label'] }}</span>
                <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-open/nav-section:rotate-90" />
            </summary>
            <div class="ml-2 grid min-w-0 gap-1 border-l border-white/10 pl-2">
                @foreach($item['children'] as $child)
                    @include('layouts.partials.navigation-item', ['item' => $child, 'nested' => true])
                @endforeach
            </div>
        </details>
    @endif
@else
    <a href="{{ $item['href'] }}"
       @if($item['tour']) data-tour="{{ $item['tour'] }}" @endif
       class="{{ $nested ? 'block rounded-sm px-2 py-1.5 text-xs leading-snug' : 'flex items-center gap-2.5 rounded-sm px-3 py-2.5' }} transition focus:outline-none focus:ring-2 focus:ring-white/30 {{ $item['active'] ? 'bg-white/10 text-white' : ($nested ? 'text-slate-300 hover:bg-white/10 hover:text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white') }}">
        @unless($nested)
            <span class="grid h-5 w-5 shrink-0 place-items-center">
                <x-icon :name="$item['icon']" class="h-4 w-4" />
            </span>
        @endunless
        <span class="{{ $nested ? '' : 'font-medium' }}">{{ $item['label'] }}</span>
    </a>
@endif

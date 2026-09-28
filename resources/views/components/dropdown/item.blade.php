@props([
    'as' => 'a',     // 'a'|'button'|'form'|'div'
    'href' => null,  // for links/forms
    'method' => null // for forms
])

@php
    $base = 'flex w-full items-center gap-2 px-3 py-2 text-sm
             rounded outline-none transition-colors text-gray-700 dark:text-gray-200
             hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-white
             focus:bg-gray-100 focus:text-gray-900 dark:focus:bg-gray-700 dark:focus:text-white';
@endphp

@if($as === 'button')
    <button type="button" data-menu-item class="{{ $base }}" {{ $attributes }}>
        {{ $slot }}
    </button>
@elseif($as === 'form')
    <form method="{{ $method ?? 'POST' }}" action="{{ $href }}" {{ $attributes->except(['class']) }}>
        @csrf
        <button type="submit" data-menu-item class="{{ $base }}">
            {{ $slot }}
        </button>
    </form>
@elseif($as === 'div')
    <div tabindex="0" data-menu-item class="{{ $base }}" {{ $attributes }}>
        {{ $slot }}
    </div>
@else
    <a href="{{ $href }}" data-menu-item class="{{ $base }}" {{ $attributes }}>
        {{ $slot }}
    </a>
@endif

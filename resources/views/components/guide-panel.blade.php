@props([
    'href',
    'icon',
    'title',
    'description' => null,
    'tone' => '#e9f5ff',
    'accent' => '#2486c8',
    'wide' => false,
    'guestIcon' => null,
])

<a href="{{ $href }}"
   class="guest-guide-panel"
   style="--guide-tone: {{ $tone }}; --guide-accent: {{ $accent }};">
    <span class="guest-guide-icon">
        @if($guestIcon)
            <img src="{{ url('/img/'.$guestIcon) }}" alt="{{ $title }}" class="h-full w-full object-contain">
        @else
            <x-icon :name="$icon" class="h-4/5 w-4/5" />
        @endif
    </span>
    <span class="guest-guide-title">{{ $title }}</span>
    @if($description)
        <span class="guest-guide-copy">{{ $description }}</span>
    @endif
    <span class="guest-guide-cta">Open guide <x-icon name="arrow-right" class="h-4 w-4" /></span>
</a>

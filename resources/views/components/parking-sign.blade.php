@props(['shape' => 'square', 'class' => 'h-6 w-6'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" role="img" aria-label="Parking" xmlns="http://www.w3.org/2000/svg">
    @if($shape === 'circle')
        <circle cx="32" cy="32" r="30" fill="#1570d6"/>
        <circle cx="32" cy="32" r="26" fill="none" stroke="#fff" stroke-width="2"/>
    @else
        <rect x="2" y="2" width="60" height="60" rx="9" fill="#1570d6"/>
        <rect x="6" y="6" width="52" height="52" rx="6" fill="none" stroke="#fff" stroke-width="2"/>
    @endif
    <path fill="#fff" fill-rule="evenodd" d="M23 15h11.5a10 10 0 0 1 0 20H30v14h-7V15zm7 6v8h4.2a4 4 0 0 0 0-8H30z"/>
</svg>

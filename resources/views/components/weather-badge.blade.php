@props(['property'])
@php
    $weather = ($property->latitude && $property->longitude)
        ? app(\App\Services\WeatherService::class)->getCurrent((float) $property->latitude, (float) $property->longitude)
        : null;
@endphp
@if($weather)
    <div {{ $attributes->merge(["class" => "guest-weather-badge flex items-center justify-between"]) }}>
        <span class="flex items-center gap-2">
            <x-icon name="{{ $weather['icon'] }}" class="h-5 w-5" />
            <span class="font-bold">{{ $weather['temperature'] }}&deg;{{ $weather['unit'] }}</span>
            <span class="text-slate-400">&middot;</span>
            <span>{{ $weather['condition'] }}</span>
        </span>
        @if($property->city)
            <span class="text-slate-400 flex items-center gap-1">
                <x-icon name="map-pin" class="h-4 w-4" />
                {{ $property->city }}{{ $property->state ? ', '.$property->state : '' }}
            </span>
        @endif
    </div>
@endif

@props([
    'name',
    'label' => 'Image',
    'value' => null,
    'previewClass' => 'h-32 w-full rounded-xl border border-slate-200 object-cover',
    'help' => null,
])
@php
    $previewId = 'media_field_' . $name . '_preview';
    $hiddenId = 'media_field_' . $name . '_existing';
    $fileId = 'media_field_' . $name . '_input';
    $previewUrl = $value ? url('/img/'.$value) : '';
@endphp

<div>
    <span class="field-label mb-1 block">{{ $label }}</span>
    @if($help)
        <p class="field-help mb-2">{{ $help }}</p>
    @endif

    <img id="{{ $previewId }}" src="{{ $previewUrl }}" alt="" class="{{ $previewClass }} {{ $previewUrl ? '' : 'hidden' }}">

    <div class="mt-2 flex gap-2">
        <label class="file-box flex-1 cursor-pointer">
            <x-icon name="upload" class="mb-2 h-7 w-7 text-[#b08a45]" />
            Upload image
            <input type="file"
                   name="{{ $name }}"
                   id="{{ $fileId }}"
                   accept="image/*"
                   class="media-field-upload-input sr-only"
                   data-preview-target="{{ $previewId }}"
                   data-hidden-target="{{ $hiddenId }}">
        </label>
    </div>

    <button type="button"
            onclick="openMediaLibraryPicker('{{ $previewId }}', '{{ $hiddenId }}', '{{ $fileId }}')"
            class="btn-secondary mt-2 w-full text-sm">
        Choose from Library
    </button>

    <input type="hidden" name="existing_{{ $name }}" id="{{ $hiddenId }}" value="{{ $value }}">
</div>

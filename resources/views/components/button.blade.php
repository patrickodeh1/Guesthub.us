@props([
    'variant' => 'primary',
    'iconOnly' => false,
    'srText' => '',
    'href' => false,
    'size' => 'base',
    'disabled' => false,
    'pill' => false,
    'squared' => false,
])

@php
    $baseClasses =
        'inline-flex !py-1 items-center transition-colors font-medium select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-dark-eval-2';

    $variantClasses = '';
    $inlineStyles = '';
    $focusRingColor = '';

    switch ($variant) {
        case 'primary':
            $variantClasses = 'text-[var(--on-primary)]';
            $inlineStyles = "background-color: var(--button-primary-color);";
            $focusRingColor = 'button-primary';
            break;
        case 'secondary':
            $variantClasses =
                'bg-white text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:bg-dark-eval-1 dark:hover:bg-dark-eval-2 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-700';
            $focusRingColor = 'button-primary';
            break;
        case 'success':
            $variantClasses = 'text-[var(--on-success)]';
            $inlineStyles = 'background-color: var(--button-success-color);';
            $focusRingColor = 'button-success';
            break;
        case 'danger':
            $variantClasses = 'text-[var(--on-danger)]';
            $inlineStyles = 'background-color: var(--button-danger-color);';
            $focusRingColor = 'button-danger';
            break;
        case 'warning':
            $variantClasses = 'text-[var(--on-warning)]';
            $inlineStyles = 'background-color: var(--button-warning-color);';
            $focusRingColor = 'button-warning';
            break;
        case 'info':
            $variantClasses = 'text-[var(--on-info)]';
            $inlineStyles = 'background-color: var(--button-info-color);';
            $focusRingColor = 'button-info';
            break;
        case 'black':
            $variantClasses =
                'bg-black text-gray-300 hover:text-white hover:bg-gray-800 focus:ring-black dark:hover:bg-dark-eval-3';
            $focusRingColor = '#000000';
            break;
        default:
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: var(--button-primary-color);";
            $focusRingColor = 'button-primary';
    }

    switch ($size) {
        case 'sm':
            $sizeClasses = $iconOnly ? 'p-1.5' : 'px-2.5 py-1.5 text-sm';
            break;
        case 'base':
            $sizeClasses = $iconOnly ? 'p-2' : 'px-4 py-2 text-base';
            break;
        case 'lg':
        default:
            $sizeClasses = $iconOnly ? 'p-3' : 'px-5 py-2 text-xl';
            break;
    }

    $classes = $baseClasses . ' ' . $sizeClasses . ' ' . $variantClasses;

    if (!$squared && !$pill) {
        $classes .= ' rounded-md';
    } elseif ($pill) {
        $classes .= ' rounded-full';
    }

    // Add focus ring color class if needed
    if ($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info'])) {
        $classes .= ' focus:ring';
    }

@endphp

@php
    // Build style attribute
    $styleAttr = '';
    if ($inlineStyles) {
        $styleAttr = "style=\"{$inlineStyles}\"";
    }

    // Add focus ring style
    $focusStyle = '';
    if ($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info'])) {
        $focusStyle = "data-focus-ring=\"{$focusRingColor}\"";
    }
@endphp

@if ($href)
    <a href="{{ $href }}"
       {{ $attributes->merge(['class' => $classes]) }}
       {!! $styleAttr !!}
       {!! $focusStyle !!}>
        {{ $slot }}
        @if ($iconOnly)
            <span class="sr-only">{{ $srText ?? '' }}</span>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}
            {!! $styleAttr !!}
            {!! $focusStyle !!}
            @if($disabled) disabled @endif>
        {{ $slot }}
        @if ($iconOnly)
            <span class="sr-only">{{ $srText ?? '' }}</span>
        @endif
    </button>
@endif

@if($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info']))
    <style>
        [data-focus-ring="button-primary"]:focus { --tw-ring-color: var(--button-primary-color) !important; }
        [data-focus-ring="button-success"]:focus { --tw-ring-color: var(--button-success-color) !important; }
        [data-focus-ring="button-danger"]:focus { --tw-ring-color: var(--button-danger-color) !important; }
        [data-focus-ring="button-warning"]:focus { --tw-ring-color: var(--button-warning-color) !important; }
        [data-focus-ring="button-info"]:focus { --tw-ring-color: var(--button-info-color) !important; }
        [style*="background-color: var(--button-primary-color)"]:hover {
            background-color: var(--button-primary-hover) !important;
        }
    </style>
@endif

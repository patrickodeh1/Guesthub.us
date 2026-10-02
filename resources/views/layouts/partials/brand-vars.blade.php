@php
    $themeColor = \App\Support\Branding::themeColor();
    $primaryColor = \App\Support\Branding::buttonColor('primary');
    $successColor = \App\Support\Branding::buttonColor('success');
    $dangerColor = \App\Support\Branding::buttonColor('danger');
    $warningColor = \App\Support\Branding::buttonColor('warning');
    $infoColor = \App\Support\Branding::buttonColor('info');
@endphp
<style>
    :root {
        --theme-primary: {{ $themeColor }};
        --button-primary-color: {{ $primaryColor }};
        --button-success-color: {{ $successColor }};
        --button-danger-color: {{ $dangerColor }};
        --button-warning-color: {{ $warningColor }};
        --button-info-color: {{ $infoColor }};
        --on-primary: {{ \App\Support\Branding::contrastText($primaryColor) }};
        --on-theme-primary: {{ \App\Support\Branding::contrastText($themeColor) }};
        --on-success: {{ \App\Support\Branding::contrastText($successColor) }};
        --on-danger: {{ \App\Support\Branding::contrastText($dangerColor) }};
        --on-warning: {{ \App\Support\Branding::contrastText($warningColor) }};
        --on-info: {{ \App\Support\Branding::contrastText($infoColor) }};
        --theme-primary-hover: color-mix(in srgb, var(--theme-primary) 86%, black);
        --theme-primary-active: color-mix(in srgb, var(--theme-primary) 76%, black);
        --theme-primary-soft: color-mix(in srgb, var(--theme-primary) 14%, transparent);
        --button-primary-hover: color-mix(in srgb, var(--button-primary-color) 86%, black);
        --button-primary-active: color-mix(in srgb, var(--button-primary-color) 76%, black);
        --brand-sidebar: color-mix(in srgb, var(--theme-primary) 85%, black);
    }
</style>

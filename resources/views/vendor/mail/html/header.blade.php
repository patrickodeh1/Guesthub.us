@props(['url'])
@php
    $mailLogoUrl = \App\Support\Branding::logoUrl();
    $mailSiteName = \App\Support\Branding::siteName();
    $mailBrandColor = \App\Support\Branding::themeColor();
@endphp
<tr>
<td class="header" style="background-color: {{ $mailBrandColor }}; text-align: center; padding: 18px 24px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($mailLogoUrl)
<img src="{{ $mailLogoUrl }}" class="logo" alt="{{ $mailSiteName }}" style="display: block; max-width: 180px; max-height: 48px; height: auto;">
@else
<span style="color: #ffffff; font-size: 18px; font-weight: bold;">{{ $mailSiteName }}</span>
@endif
</a>
</td>
</tr>

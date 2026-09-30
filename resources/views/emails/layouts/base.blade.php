@php
    $emailSiteName = \App\Support\Branding::siteName();
    $emailBrandColor = \App\Support\Branding::themeColor();
    $emailLogoUrl = \App\Support\Branding::logoUrl();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $emailSiteName }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f4f6f8;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr>
            <td style="padding:24px 32px;background:{{ $emailBrandColor }};color:#ffffff;">
                @if($emailLogoUrl)
                    <img src="{{ $emailLogoUrl }}" alt="{{ $emailSiteName }}" style="display:block;max-width:180px;max-height:48px;height:auto;margin-bottom:12px;">
                @else
                    <p style="margin:0 0 12px;font-size:18px;font-weight:bold;color:#ffffff;">{{ $emailSiteName }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px;font-size:15px;line-height:1.6;">
                @yield('content')
            </td>
        </tr>
        <tr>
            <td style="padding:16px 32px;background:#f9fafb;color:#64748b;text-align:center;font-size:12px;">
                {{ $emailSiteName }} &bull; Automated Notification
            </td>
        </tr>
    </table>
</body>
</html>

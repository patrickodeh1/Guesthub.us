<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Agreement — {{ $booking->reservation_id ?: $booking->booking_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.55; margin: 0; padding: 0; }
        .sheet { max-width: 760px; margin: 0 auto; padding: 40px 44px; }
        .head { border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 18px; }
        .host { font-size: 20px; font-weight: bold; }
        .website { font-size: 11px; color: #64748b; margin-top: 2px; }
        h1 { font-size: 17px; margin: 20px 0 6px; }
        h2 { font-size: 13px; margin: 22px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .meta td.k { background: #f8fafc; font-weight: bold; width: 34%; color: #334155; }
        .body { margin-top: 14px; }
        .body p { margin: 0 0 9px; }
        .idphoto { max-width: 320px; border: 1px solid #cbd5e1; border-radius: 4px; margin-top: 6px; }
        .sig { margin-top: 26px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; background: #f8fafc; }
        .sig .name { font-size: 20px; font-style: italic; border-bottom: 1px solid #94a3b8; display: inline-block; padding: 0 6px 3px; }
        .sig table { margin-top: 12px; }
        .sig .meta td { border: 0; padding: 3px 0; }
        .sig .meta td.k { background: transparent; width: 30%; }
        .muted { color: #64748b; }
        .toolbar { max-width: 760px; margin: 0 auto; padding: 14px 44px 0; }
        .toolbar a, .toolbar button { display: inline-block; margin-right: 8px; font-size: 12px; font-weight: bold; padding: 9px 14px; border-radius: 6px; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #06284a; color: #fff; border: 0; }
        .toolbar .ghost { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        @media print { .toolbar { display: none; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    @empty($pdfMode)
    <div class="toolbar">
        <a class="primary" href="{{ route('guest.rental-agreement.pdf', [$booking->booking_id, $booking->token]) }}">Download PDF</a>
        <button type="button" class="ghost" onclick="window.print()">Print</button>
    </div>
    @endempty

    <div class="sheet">
        <div class="head">
            <div class="host">{{ $hostName }}</div>
            <div class="website">{{ $websiteName }}</div>
        </div>

        <h1>Rental Agreement</h1>
        <p class="muted">Agreement version {{ $agreementVersion ?: '1' }}{{ $signedAt ? ' · Accepted '.$signedAt->format('M j, Y g:i A T') : '' }}</p>

        <h2>Reservation details</h2>
        <table class="meta">
            <tr><td class="k">Guest</td><td>{{ $booking->guest_name }}</td></tr>
            <tr><td class="k">Reservation ID</td><td>{{ $booking->reservation_id ?: '—' }}</td></tr>
            <tr><td class="k">Booking ID</td><td>{{ $booking->booking_id }}</td></tr>
            <tr><td class="k">Property</td><td>{{ $property?->name }}{{ $property?->fullAddress() ? ' — '.$property->fullAddress() : '' }}</td></tr>
            <tr><td class="k">Check-in</td><td>{{ $booking->check_in_date?->format('M d, Y') }} at {{ $booking->effectiveCheckinTimeFormatted() }}</td></tr>
            <tr><td class="k">Check-out</td><td>{{ $booking->check_out_date?->format('M d, Y') }} at {{ $booking->effectiveCheckoutTimeFormatted() }}</td></tr>
            <tr><td class="k">Government ID</td><td>{{ $idType }}</td></tr>
        </table>

        <div class="body">
            {!! $contractHtml !!}
        </div>

        @if($idImage)
        <h2>Government-issued ID on file</h2>
        <img src="{{ $idImage }}" alt="Government-issued ID" class="idphoto">
        @endif

        <h2>Digital signature</h2>
        <div class="sig">
            <div class="muted">Signed by</div>
            <div class="name">{{ $booking->contract_signed_name ?: $booking->guest_name }}</div>
            <table class="meta">
                <tr><td class="k">Signed at</td><td>{{ $signedAt?->format('M j, Y g:i A T') ?? '—' }}</td></tr>
                <tr><td class="k">IP address</td><td>{{ $booking->contract_signed_ip ?: '—' }}</td></tr>
                <tr><td class="k">Device ID</td><td>{{ $booking->contract_signed_device_id ?: '—' }}</td></tr>
                <tr><td class="k">Browser</td><td>{{ $booking->contract_signed_user_agent ?: '—' }}</td></tr>
                <tr><td class="k">Agreement version</td><td>{{ $agreementVersion ?: '1' }}</td></tr>
            </table>
        </div>

        <p class="muted" style="margin-top:18px;">This electronically signed agreement was accepted by the guest and is retained with the signature evidence above.</p>
    </div>
</body>
</html>

@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 6px;font-size:21px;">🧹 Cleaning Session Started</h1>
    <p style="margin-top:0;color:#64748b;">A cleaning session has begun</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;">
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Property</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $propertyName }}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Cleaner</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $cleanerName }}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Started At</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $startTime }}</td></tr>
        <tr><td style="padding:10px 0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Session ID</td><td style="padding:10px 0;">#{{ $sessionId }}</td></tr>
    </table>
@endsection

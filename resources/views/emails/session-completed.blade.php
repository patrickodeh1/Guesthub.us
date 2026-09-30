@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 6px;font-size:21px;">✅ Cleaning Session Completed</h1>
    <p style="margin-top:0;color:#64748b;">A cleaning session has been completed</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;">
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Property</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $propertyName }}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Cleaner</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $cleanerName }}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Completed At</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">{{ $completionTime }}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Status</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;"><span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:bold;background:{{ $statusText === 'Ready' ? '#d1fae5' : '#fef3c7' }};color:{{ $statusText === 'Ready' ? '#065f46' : '#92400e' }};">{{ $statusText }}</span></td></tr>
        <tr><td style="padding:10px 0;color:#64748b;font-size:12px;font-weight:bold;text-transform:uppercase;">Session ID</td><td style="padding:10px 0;">#{{ $sessionId }}</td></tr>
    </table>
    <p style="margin-top:24px;"><a href="{{ $reportUrl }}" style="display:inline-block;padding:10px 18px;background:{{ \App\Support\Branding::buttonColor() }};color:{{ \App\Support\Branding::contrastText(\App\Support\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">View Full Report →</a></p>
@endsection

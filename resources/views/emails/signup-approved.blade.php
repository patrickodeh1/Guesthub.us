@extends('emails.layouts.base')
@section('content')
    <p style="white-space:pre-line">{{ $body }}</p>
    @if (!empty($temporaryPassword))
        <p style="margin:20px 0 8px;font-size:13px;color:#64748b;font-weight:bold;">YOUR TEMPORARY PASSWORD</p>
        <p style="display:inline-block;padding:12px 18px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace;font-size:24px;font-weight:bold;letter-spacing:4px;color:#0f172a;">{{ $temporaryPassword }}</p>
        <p><a href="{{ $loginUrl }}" style="display:inline-block;padding:10px 18px;background:{{ \App\\Support\\Branding::buttonColor() }};color:{{ \App\\Support\\Branding::contrastText(\App\\Support\\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">Sign in to Guest Hub</a></p>
        <p style="font-size:13px;color:#64748b">For security, sign in and change this password immediately. You cannot access the rest of your account until you set a new password.</p>
    @else
        <p><a href="{{ $setupUrl }}" style="display:inline-block;padding:10px 18px;background:{{ \App\\Support\\Branding::buttonColor() }};color:{{ \App\\Support\\Branding::contrastText(\App\\Support\\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">Set your password</a></p>
        <p style="font-size:13px;color:#64748b">This link expires in 24 hours and can be used once. If it has expired, contact us and we'll send you a new one.</p>
    @endif
@endsection

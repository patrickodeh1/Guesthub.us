@extends('emails.layouts.base')
@section('content')
    <p style="white-space:pre-line">{{ $body }}</p>
    <p><a href="{{ $setupUrl }}" style="display:inline-block;padding:10px 18px;background:{{ \App\Support\Branding::buttonColor() }};color:{{ \App\Support\Branding::contrastText(\App\Support\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">Set your password</a></p>
    <p style="font-size:13px;color:#64748b">This link expires in 24 hours and can be used once. If it has expired, contact us and we'll send you a new one.</p>
@endsection

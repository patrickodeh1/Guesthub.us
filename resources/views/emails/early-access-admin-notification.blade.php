@extends('emails.layouts.base')

@section('content')
    <p style="white-space:pre-line">{{ $body }}</p>
    <p><a href="{{ route('admin.early-access-leads.index') }}" style="display:inline-block;padding:10px 18px;background:{{ \App\Support\Branding::buttonColor() }};color:{{ \App\Support\Branding::contrastText(\App\Support\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">View in admin</a></p>
@endsection

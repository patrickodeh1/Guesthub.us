@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 20px;font-size:22px;">New early access signup</h1>
    <p><strong>Name:</strong> {{ $lead->name }}<br>
    <strong>Email:</strong> {{ $lead->email }}<br>
    @if($lead->phone)<strong>Phone:</strong> {{ \App\Support\PhoneFormatter::format($lead->phone) }}<br>@endif
    @if($lead->role)<strong>Role:</strong> {{ ucfirst($lead->role) }}<br>@endif</p>
    @if($lead->message)
        <p><strong>Message:</strong><br>{{ $lead->message }}</p>
    @endif
    <p><a href="{{ route('admin.early-access-leads.index') }}" style="display:inline-block;padding:10px 18px;background:{{ \App\Support\Branding::buttonColor() }};color:{{ \App\Support\Branding::contrastText(\App\Support\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">View in admin</a></p>
    <p>Thanks,<br>{{ \App\Support\Branding::siteName() }}</p>
@endsection

@extends('emails.layouts.base')

@section('content')
    <div style="white-space:pre-line;">{{ $message }}</div>
    @isset($propertyName)
        <p style="margin-top:24px;color:#64748b;font-size:13px;">This is an automated update about your reservation at {{ $propertyName }}. If you weren't expecting it, you can safely ignore this email.</p>
    @endisset
@endsection

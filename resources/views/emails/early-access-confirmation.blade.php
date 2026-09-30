@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 20px;font-size:22px;">Thanks for your interest, {{ $lead->name }}</h1>
    <p>We've received your request for early access to {{ \App\Support\Branding::siteName() }}. We'll be in touch soon with next steps.</p>
    <p>Thanks,<br>{{ \App\Support\Branding::siteName() }}</p>
@endsection

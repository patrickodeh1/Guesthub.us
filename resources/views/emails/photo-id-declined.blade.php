@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 20px;font-size:22px;">ID photo needs to be re-uploaded</h1>
    <p>Hi {{ $guestName }},</p>
    <p>The <strong>{{ $sideLabel }}</strong> of the photo ID you submitted for your stay at {{ $propertyName ?? 'your property' }} was not approved.</p>
    <p><strong>Reason:</strong> {{ $reason }}</p>
    <p>Please log back in to {{ \App\Support\Branding::siteName() }} and re-upload a clear photo of the <strong>{{ $sideLabel }}</strong> of your ID. Any other ID photo you already had approved is still on file and does not need to be resubmitted.</p>
    <p><a href="{{ $reuploadUrl }}" style="display:inline-block;padding:10px 18px;background:{{ \App\Support\Branding::buttonColor() }};color:{{ \App\Support\Branding::contrastText(\App\Support\Branding::buttonColor()) }};text-decoration:none;border-radius:6px;font-weight:bold;">Re-upload my ID</a></p>
    <p>If you have any questions, please reach out to your host.</p>
@endsection

@component('mail::message')
# {{ $subjectLine ?? 'GuestHub booking update' }}

{{ $message }}

@component('mail::button', ['url' => $actionUrl])
{{ $actionLabel }}
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent

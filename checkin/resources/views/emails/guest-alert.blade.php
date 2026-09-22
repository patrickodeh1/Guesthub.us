<x-mail::message>
{{ $message }}

@isset($propertyName)
<x-slot:subcopy>
This is an automated update about your reservation at {{ $propertyName }}. If you weren't expecting it, you can safely ignore this email.
</x-slot:subcopy>
@endisset
</x-mail::message>

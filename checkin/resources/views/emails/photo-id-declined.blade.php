<x-mail::message>
# ID photo needs to be re-uploaded

Hi {{ $guestName }},

The **{{ $sideLabel }}** of the photo ID you submitted for your stay at {{ $propertyName ?? 'your property' }} was not approved.

**Reason:** {{ $reason }}

Please log back in to GuestHub and re-upload a clear photo of the **{{ $sideLabel }}** of your ID. Any other ID photo you already had approved is still on file and does not need to be resubmitted.

<x-mail::button :url="$reuploadUrl">
Re-upload my ID
</x-mail::button>

If you have any questions, please reach out to your host.
</x-mail::message>

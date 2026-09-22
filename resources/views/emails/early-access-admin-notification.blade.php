<x-mail::message>
# New early access signup

**Name:** {{ $lead->name }}<br>
**Email:** {{ $lead->email }}<br>
@if($lead->phone)
**Phone:** {{ \App\Support\PhoneFormatter::format($lead->phone) }}<br>
@endif
@if($lead->role)
**Role:** {{ ucfirst($lead->role) }}<br>
@endif

@if($lead->message)
**Message:**<br>
{{ $lead->message }}
@endif

<x-mail::button :url="route('admin.early-access-leads.index')">
View in admin
</x-mail::button>

Thanks,<br>
GuestHub
</x-mail::message>

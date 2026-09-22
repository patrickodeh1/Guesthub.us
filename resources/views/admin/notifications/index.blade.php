<x-admin-layout title="Notifications">
    <div class="page-header">
        <div>
            <p class="eyebrow">Global settings</p>
            <h1 class="page-title">Guest lifecycle notifications</h1>
            <p class="page-subtitle">Customize the message sent for each stage of a booking, and choose who gets it and over which channel(s). Guest and staff each get their own wording, since staff need to be told about the guest rather than spoken to as the guest.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.settings.notifications.update') }}" class="card card-pad">
        @csrf @method('put')

        <div class="flex flex-col gap-3">
            @foreach($alertEvents as $key => $meta)
                @php($row = $alertConfig[$key])
                <details class="rounded-xl border border-slate-200 p-4" {{ $loop->first ? 'open' : '' }}>
                    <summary class="cursor-pointer font-bold text-slate-800">{{ $alertLabels[$key] }}</summary>
                    <div class="mt-4 grid gap-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="field-label">
                                Message to guest
                                <textarea name="alerts[{{ $key }}][guest_message]" rows="3" class="input">{{ old("alerts.$key.guest_message", $row['guest_message']) }}</textarea>
                            </label>
                            <label class="field-label">
                                Message to staff
                                <textarea name="alerts[{{ $key }}][staff_message]" rows="3" class="input">{{ old("alerts.$key.staff_message", $row['staff_message']) }}</textarea>
                            </label>
                        </div>
                        <p class="field-help">Available tokens: {guest_name}, {guest_first_name}, {property_name}, {check_in_date}, {check_in_time}, {check_out_date}, {check_out_time}, {parking_status}, {step_name}@if($key === 'photo_id_declined'), {id_side}, {decline_reason}@endif</p>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                        <th class="py-2 pr-4">Recipient</th>
                                        <th class="py-2 pr-4">Text</th>
                                        <th class="py-2 pr-4">Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-4 font-semibold text-slate-700">Guest</td>
                                        <td class="py-2 pr-4">
                                            <input type="hidden" name="alerts[{{ $key }}][guest_sms]" value="0">
                                            <input type="checkbox" name="alerts[{{ $key }}][guest_sms]" value="1" {{ old("alerts.$key.guest_sms", $row['guest_sms']) ? 'checked' : '' }}>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <input type="hidden" name="alerts[{{ $key }}][guest_email]" value="0">
                                            <input type="checkbox" name="alerts[{{ $key }}][guest_email]" value="1" {{ old("alerts.$key.guest_email", $row['guest_email']) ? 'checked' : '' }}>
                                        </td>
                                    </tr>
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-4 font-semibold text-slate-700">Contact desk</td>
                                        <td class="py-2 pr-4">
                                            <input type="hidden" name="alerts[{{ $key }}][contact_sms]" value="0">
                                            <input type="checkbox" name="alerts[{{ $key }}][contact_sms]" value="1" {{ old("alerts.$key.contact_sms", $row['contact_sms']) ? 'checked' : '' }}>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <input type="hidden" name="alerts[{{ $key }}][contact_email]" value="0">
                                            <input type="checkbox" name="alerts[{{ $key }}][contact_email]" value="1" {{ old("alerts.$key.contact_email", $row['contact_email']) ? 'checked' : '' }}>
                                        </td>
                                    </tr>
                                    @foreach($staffRoles as $role)
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2 pr-4 font-semibold text-slate-700 capitalize">{{ $role }}</td>
                                            <td class="py-2 pr-4">
                                                <input type="hidden" name="alerts[{{ $key }}][{{ $role }}_sms]" value="0">
                                                <input type="checkbox" name="alerts[{{ $key }}][{{ $role }}_sms]" value="1" {{ old("alerts.$key.{$role}_sms", $row["{$role}_sms"]) ? 'checked' : '' }}>
                                            </td>
                                            <td class="py-2 pr-4">
                                                <input type="hidden" name="alerts[{{ $key }}][{{ $role }}_email]" value="0">
                                                <input type="checkbox" name="alerts[{{ $key }}][{{ $role }}_email]" value="1" {{ old("alerts.$key.{$role}_email", $row["{$role}_email"]) ? 'checked' : '' }}>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="field-help mt-2">Contact desk uses the phone/email set under Settings &gt; General. Owner/Manager/Staff/Viewer notify every user with that role, at their own phone/email.</p>
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <button class="btn-primary mt-6">Save notification settings</button>
    </form>
</x-admin-layout>

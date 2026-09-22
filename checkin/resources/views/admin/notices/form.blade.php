<x-admin-layout :title="$notice->exists ? 'Edit Guest Notice' : 'Add Guest Notice'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest Admin</p>
            <h2 class="page-title">{{ $notice->exists ? 'Edit Guest Notice' : 'Add Guest Notice' }}</h2>
            <p class="page-subtitle">Set the message and the conditions that must be met before a guest sees it.</p>
        </div>
        <a href="{{ route('admin.notices.index') }}" class="btn-secondary">Back to Notices</a>
    </div>

    <form method="post" action="{{ $notice->exists ? route('admin.notices.update', $notice) : route('admin.notices.store') }}" class="card card-pad space-y-6">
        @csrf
        @if($notice->exists) @method('put') @endif

        <div class="grid gap-5 md:grid-cols-2">
            <label class="field-label md:col-span-2">Title <span class="text-red-600">*</span>
                <input name="title" value="{{ old('title', $notice->title) }}" required class="input">
                @error('title')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>

            <label class="field-label md:col-span-2">Message <span class="text-red-600">*</span>
                <textarea name="body" rows="4" required class="textarea">{{ old('body', $notice->body) }}</textarea>
                @error('body')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>

            <label class="field-label">Property
                <select name="property_id" class="input">
                    <option value="">All properties</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) old('property_id', $notice->property_id) === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-label">Show as
                <select name="type" class="input">
                    @foreach(\App\Models\GuestNotice::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $notice->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-label">Phase
                <select name="phase" class="input">
                    @foreach(\App\Models\GuestNotice::PHASES as $value => $label)
                        <option value="{{ $value }}" @selected(old('phase', $notice->phase) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-label">Day
                <select name="day_scope" class="input">
                    @foreach(\App\Models\GuestNotice::DAY_SCOPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('day_scope', $notice->day_scope) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-label">Show from time <span class="font-normal text-slate-500">(optional)</span>
                <input type="time" name="start_time" value="{{ old('start_time', $notice->start_time ? substr($notice->start_time, 0, 5) : '') }}" class="input">
            </label>

            <label class="field-label">Show until time <span class="font-normal text-slate-500">(optional)</span>
                <input type="time" name="end_time" value="{{ old('end_time', $notice->end_time ? substr($notice->end_time, 0, 5) : '') }}" class="input">
                <span class="field-help">A window like 23:00–04:00 works across midnight.</span>
            </label>

            <label class="field-label">Parking condition
                @php $parkingValue = old('requires_parking', $notice->requires_parking === true ? 'yes' : ($notice->requires_parking === false ? 'no' : 'any')); @endphp
                <select name="requires_parking" class="input">
                    <option value="any" @selected($parkingValue === 'any')>Any guest</option>
                    <option value="yes" @selected($parkingValue === 'yes')>Only guests parking a car</option>
                    <option value="no" @selected($parkingValue === 'no')>Only guests not parking</option>
                </select>
            </label>

            <label class="field-label">Sort order
                <input type="number" name="sort_order" min="0" max="1000" value="{{ old('sort_order', $notice->sort_order ?? 0) }}" class="input">
            </label>
        </div>

        <div class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="active" value="1" @checked(old('active', $notice->active))>
                Active
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="once_per_booking" value="1" @checked(old('once_per_booking', $notice->once_per_booking))>
                Show only once per booking
            </label>
        </div>

        <div class="flex gap-3">
            <button class="btn-primary">{{ $notice->exists ? 'Save changes' : 'Create notice' }}</button>
            <a href="{{ route('admin.notices.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-admin-layout>

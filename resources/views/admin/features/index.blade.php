<x-admin-layout title="Direct booking features">
    <div class="page-header">
        <div>
            <p class="eyebrow">Direct booking</p>
            <h1 class="page-title">Feature switches</h1>
            <p class="page-subtitle">Set which direct-booking features are free, paid, or unavailable. Billing is not enabled here.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4">
        @foreach($features as $feature)
            <form method="post" action="{{ route('admin.features.update', $feature) }}" class="card card-pad grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                @csrf
                @method('put')
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">{{ $feature->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500"><code>{{ $feature->key }}</code></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3 sm:items-end">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_free" value="0">
                        <input type="checkbox" name="is_free" value="1" @checked($feature->is_free)>
                        Free for all hosts
                    </label>
                    <label class="field-label">Price (USD)
                        <div class="flex items-center gap-2"><span class="text-slate-500">$</span><input type="number" inputmode="decimal" name="price" min="0" step="0.01" placeholder="0.00" value="{{ old('price', $feature->price_cents !== null ? number_format($feature->price_cents / 100, 2, '.', '') : '') }}" class="input"></div>
                    </label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" @checked($feature->active)>
                            Active
                        </label>
                        <button class="btn-primary">Save</button>
                    </div>
                </div>
                @error('price_cents')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </form>
        @endforeach
    </div>

    <form method="post" action="{{ route('admin.features.direct-settings') }}" class="card card-pad mt-8 grid gap-6">
        @csrf
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Cancellation policies</h2>
            <p class="mt-1 text-sm text-slate-500">Hosts pick one policy per listing. A full refund applies when a guest cancels at least the "full refund" days before check-in. The partial refund applies between the partial days and the full days. After that, no refund.</p>
        </div>
        @foreach(['flexible' => 'Flexible', 'moderate' => 'Moderate', 'strict' => 'Strict'] as $key => $label)
            <div class="grid items-end gap-3 md:grid-cols-4">
                <strong>{{ $label }}</strong>
                <label class="field-label">Full refund days before<input type="number" min="0" max="365" name="cancellation[{{ $key }}][full_days]" value="{{ old('cancellation.'.$key.'.full_days', $policies[$key]['full_days']) }}" class="input" required></label>
                <label class="field-label">Partial refund days before<input type="number" min="0" max="365" name="cancellation[{{ $key }}][partial_days]" value="{{ old('cancellation.'.$key.'.partial_days', $policies[$key]['partial_days']) }}" class="input" required></label>
                <label class="field-label">Partial refund %<input type="number" min="0" max="100" name="cancellation[{{ $key }}][partial_percent]" value="{{ old('cancellation.'.$key.'.partial_percent', $policies[$key]['partial_percent']) }}" class="input" required></label>
            </div>
        @endforeach

        <div class="grid gap-4 md:grid-cols-2">
            <label class="field-label">Refund card processing fee on cancellation
                <select name="refund_processing_fee" class="input">
                    <option value="0" @selected(old('refund_processing_fee', $refundFee) !== '1')>No, keep the processing fee</option>
                    <option value="1" @selected(old('refund_processing_fee', $refundFee) === '1')>Yes, refund it with the rest</option>
                </select>
            </label>
            <label class="field-label">Platform fee on card bookings (%)
                <input type="number" step="0.01" min="0" max="100" name="platform_fee_percent" value="{{ old('platform_fee_percent', $platformFee) }}" class="input" required>
                <span class="field-help">Taken from the host's payout on top of the guest's processing fee. Leave 0 for none.</span>
            </label>
        </div>
        <p class="text-sm text-slate-500">Guest card processing fee: {{ $processingFee }}% (changed in general settings, because it is shared with incidentals).</p>
        @if($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <div><button class="btn-primary">Save direct booking settings</button></div>
    </form>
</x-admin-layout>

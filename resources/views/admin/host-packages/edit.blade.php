<x-admin-layout title="Host billing">
    @php
        $money = fn ($c) => '$'.number_format(((int) $c) / 100, 2);
        $unitLabels = ['flat' => 'flat', 'per_property' => 'per property', 'per_device' => 'per device'];
    @endphp
    <div class="page-header">
        <div>
            <p class="eyebrow"><a class="underline" href="{{ route('admin.host-packages.index') }}">Host billing</a></p>
            <h1 class="page-title">{{ $user->name }}</h1>
            <p class="page-subtitle">{{ $user->email }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.host-packages.update', $user) }}" class="grid gap-6">
        @csrf
        @method('put')

        <div class="card card-pad grid gap-4">
            <h2 class="text-lg font-semibold text-slate-950">Package</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <label class="field-label">Package
                    <select name="package_id" class="input">
                        <option value="">No package</option>
                        @foreach($packages as $p)
                            <option value="{{ $p->id }}" @selected((int) old('package_id', $hostPackage?->package_id) === (int) $p->id)>{{ $p->name }}@if($p->price_cents) ({{ $money($p->price_cents) }}/mo)@endif</option>
                        @endforeach
                    </select>
                </label>
                <label class="field-label">Status
                    <select name="status" class="input">
                        @foreach(['active' => 'Active', 'trialing' => 'In free trial', 'canceled' => 'Canceled'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $hostPackage?->status ?? 'active') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field-label">Trial ends
                    <input type="date" name="trial_ends_at" class="input" value="{{ old('trial_ends_at', $hostPackage?->trial_ends_at?->format('Y-m-d')) }}">
                </label>
            </div>
            <p class="text-xs text-slate-500">For a trial, leave the date empty to use the package's trial days. A canceled package gives no features.</p>
        </div>

        <div class="card card-pad">
            <h2 class="text-lg font-semibold text-slate-950">Add-on features</h2>
            <p class="mt-1 text-sm text-slate-500">Paid features outside the package. Unticking one removes it. Free features are on for everyone and are not listed.</p>
            <div class="mt-4 grid gap-3">
                @foreach($features as $f)
                    @php($g = $grants->get($f->key))
                    @php($unit = $f->billing_unit ?: 'flat')
                    <div class="grid items-center gap-3 border-t pt-3 sm:grid-cols-[1fr_auto_auto]">
                        <div>
                            <p class="font-medium">{{ $f->name }}</p>
                            <p class="text-xs text-slate-500">{{ $f->price_cents !== null ? $money($f->price_cents) : 'No price set' }} {{ $unitLabels[$unit] ?? $unit }}</p>
                        </div>
                        @if(in_array($f->key, $included, true))
                            <span class="text-sm font-semibold text-emerald-700 sm:col-span-2">Included in package</span>
                        @else
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="hidden" name="addons[{{ $f->key }}][enabled]" value="0">
                                <input type="checkbox" name="addons[{{ $f->key }}][enabled]" value="1" @checked(old('addons.'.$f->key.'.enabled', $g && $g->enabled))> Add
                            </label>
                            @if($unit !== 'flat')
                                <label class="field-label">{{ $unit === 'per_device' ? 'Devices' : 'Properties' }}
                                    <input type="number" min="1" max="100000" name="addons[{{ $f->key }}][quantity]" class="input w-24" value="{{ old('addons.'.$f->key.'.quantity', $g?->quantity ?? 1) }}">
                                </label>
                            @else
                                <span></span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-slate-500">Monthly total as saved (not billed yet)</p>
                <p class="text-2xl font-semibold">{{ $money($estimate) }}</p>
            </div>
            <button class="btn-primary">Save</button>
        </div>
    </form>
</x-admin-layout>

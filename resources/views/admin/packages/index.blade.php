<x-admin-layout title="Packages">
    @php
        $money = fn ($c) => $c > 0 ? '$'.number_format($c / 100, 2).'/mo' : 'Free';
        $live = $packages->where('active', true)->values();
        $keysByPackage = $live->mapWithKeys(fn ($p) => [$p->id => $p->allFeatureKeys()]);
        $unitLabels = ['flat' => 'flat', 'per_property' => 'per property', 'per_device' => 'per device'];
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Direct booking</p>
            <h1 class="page-title">Packages</h1>
            <p class="page-subtitle">Each package adds features on top of the one it builds on. Hosts can subscribe from Settings → Plan; feature prices also support à la carte add-ons.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="card card-pad mb-6 overflow-x-auto">
        <h2 class="text-lg font-semibold text-slate-950">Compare <span class="text-xs font-normal text-slate-400">(as last saved)</span></h2>
        <table class="mt-3 w-full text-sm">
            <thead>
                <tr>
                    <th class="py-2 text-left"></th>
                    @foreach($live as $p)<th class="py-2 text-center">{{ $p->name }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="border-t"><td class="py-2 text-slate-500">Price</td>@foreach($live as $p)<td class="py-2 text-center font-semibold">{{ $money($p->price_cents) }}</td>@endforeach</tr>
                <tr class="border-t"><td class="py-2 text-slate-500">Free trial</td>@foreach($live as $p)<td class="py-2 text-center">{{ $p->trial_days ? $p->trial_days.' days' : '—' }}</td>@endforeach</tr>
                @foreach($features as $f)
                    <tr class="border-t">
                        <td class="py-2">{{ $f->name }}@if($f->billing_unit && $f->billing_unit !== 'flat') <span class="text-xs text-slate-400">({{ $unitLabels[$f->billing_unit] ?? $f->billing_unit }})</span>@endif</td>
                        @foreach($live as $p)
                            <td class="py-2 text-center">{!! in_array($f->key, $keysByPackage[$p->id] ?? [], true) ? '<span class="font-bold text-emerald-700">✓</span>' : '<span class="text-slate-300">—</span>' !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-3 text-xs text-slate-500">Features marked free for all hosts are available on every package, whatever is ticked. A feature's own price is its à la carte price: it applies only when a host adds that feature outside their package, and never on top of a package that already includes it.</p>
    </div>

    <form method="post" action="{{ route('admin.packages.store') }}" class="card card-pad mb-6 flex flex-wrap items-end gap-3">
        @csrf
        <label class="field-label">New package name<input name="name" class="input" maxlength="80" required placeholder="e.g. Premium"></label>
        <button class="btn-primary">Add package</button>
    </form>

    <form method="post" action="{{ route('admin.packages.update-all') }}" id="packages-form">
        @csrf
        @method('put')

        <div class="grid gap-4">
            @foreach($packages as $package)
                @php($inherited = $package->parent ? $package->parent->allFeatureKeys() : [])
                @php($own = $package->ownFeatureKeys())
                @php($pid = $package->id)
                <div class="card card-pad grid gap-4" data-pkg="{{ $pid }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-slate-950">{{ $package->name }} <code class="text-xs font-normal text-slate-400">{{ $package->key }}</code></h2>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="hidden" name="packages[{{ $pid }}][active]" value="0">
                                <input type="checkbox" name="packages[{{ $pid }}][active]" value="1" @checked(old('packages.'.$pid.'.active', $package->active))> Active
                            </label>
                            <button type="submit" form="delete-{{ $pid }}" class="text-xs text-red-700 underline" onclick="return confirm('Delete {{ $package->name }}?')">Delete</button>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-4">
                        <label class="field-label">Name<input name="packages[{{ $pid }}][name]" class="input" value="{{ old('packages.'.$pid.'.name', $package->name) }}" required maxlength="80"></label>
                        <label class="field-label">Monthly price (USD)
                            <div class="flex items-center gap-2"><span class="text-slate-500">$</span><input type="number" inputmode="decimal" name="packages[{{ $pid }}][price]" min="0" step="0.01" class="input" value="{{ old('packages.'.$pid.'.price', number_format($package->price_cents / 100, 2, '.', '')) }}"></div>
                        </label>
                        <label class="field-label">Free trial days<input type="number" name="packages[{{ $pid }}][trial_days]" min="0" max="365" class="input" value="{{ old('packages.'.$pid.'.trial_days', $package->trial_days) }}" placeholder="None"></label>
                        <label class="field-label">Builds on
                            <select name="packages[{{ $pid }}][parent_id]" class="input">
                                <option value="">Nothing (starting tier)</option>
                                @foreach($packages->where('id', '!=', $pid) as $other)
                                    <option value="{{ $other->id }}" @selected((int) $package->parent_id === (int) $other->id)>{{ $other->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field-label">Description (shown to hosts)<input name="packages[{{ $pid }}][description]" class="input" maxlength="500" value="{{ old('packages.'.$pid.'.description', $package->description) }}"></label>

                    <div>
                        <p class="text-sm font-medium">Features</p>
                        <p class="text-xs text-slate-500">Greyed, ticked features come from the package this one builds on.</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($features as $f)
                                @php($fromParent = in_array($f->key, $inherited, true))
                                <label class="flex items-center gap-2 text-sm {{ $fromParent ? 'text-slate-400' : '' }}">
                                    <input type="checkbox" name="packages[{{ $pid }}][features][]" value="{{ $f->key }}" data-key="{{ $f->key }}" @if($fromParent) data-inh="1" disabled @endif @checked($fromParent || in_array($f->key, $own, true))>
                                    {{ $f->name }}@if($f->is_free) <span class="text-xs text-slate-400">(free for all)</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card card-pad mt-8">
            <h2 class="text-lg font-semibold text-slate-950">Features and à la carte prices</h2>
            <p class="mt-1 text-sm text-slate-500">The price is what a host pays when they add the feature on its own. "Per property" and "per device" multiply it by the count.</p>
            <div class="mt-4 grid gap-4">
                @foreach($features as $f)
                    @php($k = $f->key)
                    <div class="grid gap-4 border-t pt-4 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $f->name }}</p>
                            <p class="text-sm text-slate-500"><code>{{ $k }}</code></p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-4 sm:items-end">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="hidden" name="feats[{{ $k }}][is_free]" value="0">
                                <input type="checkbox" name="feats[{{ $k }}][is_free]" value="1" @checked($f->is_free)> Free for all hosts
                            </label>
                            <label class="field-label">Price (USD)
                                <div class="flex items-center gap-2"><span class="text-slate-500">$</span><input type="number" inputmode="decimal" name="feats[{{ $k }}][price]" min="0" step="0.01" placeholder="0.00" class="input" value="{{ $f->price_cents !== null ? number_format($f->price_cents / 100, 2, '.', '') : '' }}"></div>
                            </label>
                            <label class="field-label">Billed
                                <select name="feats[{{ $k }}][billing_unit]" class="input">
                                    @foreach(['flat' => 'Flat', 'per_property' => 'Per property', 'per_device' => 'Per device'] as $v => $l)
                                        <option value="{{ $v }}" @selected(($f->billing_unit ?: 'flat') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="hidden" name="feats[{{ $k }}][active]" value="0">
                                <input type="checkbox" name="feats[{{ $k }}][active]" value="1" @checked($f->active)> Active
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="sticky bottom-4 z-10 mt-6">
            <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Every package and feature above saves together.</span>
                <button class="btn-primary">Save all changes</button>
            </div>
        </div>
    </form>

    @foreach($packages as $package)
        <form id="delete-{{ $package->id }}" method="post" action="{{ route('admin.packages.destroy', $package) }}">@csrf @method('delete')</form>
    @endforeach

    <script>
        (() => {
            const cards = [...document.querySelectorAll('[data-pkg]')];
            const byId = Object.fromEntries(cards.map(c => [c.dataset.pkg, c]));
            const boxes = c => [...c.querySelectorAll('input[type=checkbox][data-key]')];
            const own = id => boxes(byId[id]).filter(b => b.checked && !b.dataset.inh).map(b => b.dataset.key);
            const parentOf = id => { const v = byId[id].querySelector('select[name$="[parent_id]"]').value; return v && byId[v] ? v : null; };
            const effective = (id, depth = 0) => {
                if (depth > 10) return new Set();
                const s = new Set(own(id));
                const p = parentOf(id);
                if (p) effective(p, depth + 1).forEach(k => s.add(k));
                return s;
            };
            const refresh = () => {
                cards.forEach(c => boxes(c).forEach(b => {
                    if (b.dataset.inh) { b.checked = false; b.disabled = false; delete b.dataset.inh; b.parentElement.classList.remove('text-slate-400'); }
                }));
                cards.forEach(c => {
                    const p = parentOf(c.dataset.pkg);
                    if (!p) return;
                    const inherited = effective(p);
                    boxes(c).forEach(b => {
                        if (inherited.has(b.dataset.key)) { b.checked = true; b.disabled = true; b.dataset.inh = '1'; b.parentElement.classList.add('text-slate-400'); }
                    });
                });
            };
            document.addEventListener('change', e => {
                if (e.target.closest('[data-pkg]') && (e.target.matches('[data-key]') || e.target.name.endsWith('[parent_id]'))) refresh();
            });
            refresh();
        })();
    </script>
</x-admin-layout>

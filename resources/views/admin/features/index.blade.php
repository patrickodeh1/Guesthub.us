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
                    <label class="field-label">Price (cents)
                        <input type="number" name="price_cents" min="0" step="1" value="{{ old('price_cents', $feature->price_cents) }}" class="input">
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
</x-admin-layout>

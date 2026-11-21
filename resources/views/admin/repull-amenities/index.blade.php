<x-admin-layout title="Repull amenity catalog">
    <div class="page-header">
        <div>
            <p class="eyebrow">Channel sync</p>
            <h1 class="page-title">Repull amenity catalog</h1>
            <p class="page-subtitle">Maintain approved canonical IDs that property editors can select. Only add IDs confirmed by Repull; Airbnb's own amenity IDs are not interchangeable.</p>
        </div>
        <a href="{{ route('admin.properties.index') }}" class="btn-secondary">Back to properties</a>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.repull-amenities.store') }}" class="card card-pad mb-6 grid gap-3 md:grid-cols-4">
        @csrf
        <label class="field-label">Display label<input name="label" required maxlength="120" class="input" placeholder="Hot tub"></label>
        <label class="field-label">Repull canonical ID<input name="canonical_id" required maxlength="100" pattern="[a-z0-9_]+" class="input" placeholder="hot_tub"></label>
        <label class="field-label">Category<input name="category" maxlength="80" class="input" placeholder="Outdoor"></label>
        <label class="field-label">Sort order<input name="sort_order" type="number" min="0" max="100000" value="100" class="input"></label>
        <div class="md:col-span-4"><button class="btn-primary">Add to catalog</button></div>
    </form>

    <div class="grid gap-3">
        @forelse($amenities as $amenity)
            <form method="post" action="{{ route('admin.repull-amenities.update', $amenity) }}" class="card card-pad grid gap-3 md:grid-cols-[1fr_1fr_1fr_120px_auto_auto] md:items-end">
                @csrf
                @method('put')
                <label class="field-label">Display label<input name="label" value="{{ $amenity->label }}" required maxlength="120" class="input"></label>
                <label class="field-label">Canonical ID<input name="canonical_id" value="{{ $amenity->canonical_id }}" required maxlength="100" pattern="[a-z0-9_]+" class="input"></label>
                <label class="field-label">Category<input name="category" value="{{ $amenity->category }}" maxlength="80" class="input"></label>
                <label class="field-label">Sort order<input name="sort_order" type="number" min="0" max="100000" value="{{ $amenity->sort_order }}" class="input"></label>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($amenity->active)> Active</label>
                <button class="btn-secondary">Save</button>
            </form>
            <form method="post" action="{{ route('admin.repull-amenities.destroy', $amenity) }}" class="hidden" id="delete-amenity-{{ $amenity->id }}">
                @csrf
                @method('delete')
            </form>
        @empty
            <p class="card card-pad text-sm text-slate-500">No canonical amenity IDs have been added.</p>
        @endforelse
    </div>
</x-admin-layout>

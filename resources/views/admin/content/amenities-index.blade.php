<x-admin-layout title="Amenities">
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $property->name }}</p>
            <h1 class="page-title">Amenities</h1>
            <p class="page-subtitle">Manage the amenities guests see for this property.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.categories.index', ['property_id' => $property->id]) }}" class="btn-secondary">Back to Categories</a>
            <a href="{{ route('admin.amenities.create', $property) }}" class="btn-primary">Add Amenity</a>
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($amenities as $amenity)
            <div class="card card-pad flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-950">{{ $amenity->title }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $amenity->active ? 'Active' : 'Inactive' }}</p>
                    @if($amenity->details)
                        <p class="mt-2 text-sm text-slate-600 line-clamp-2">{{ strip_tags($amenity->details) }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('admin.amenities.edit', $amenity) }}" class="admin-icon-action bg-blue-500" aria-label="Edit {{ $amenity->title }}" title="Edit {{ $amenity->title }}" data-tooltip="Edit">
                        <x-icon name="edit" class="h-4 w-4" />
                    </a>
                    <form method="post" action="{{ route('admin.amenities.destroy', $amenity) }}" onsubmit="return confirm('Delete this amenity?')">
                        @csrf
                        @method('delete')
                        <button class="admin-icon-action bg-red-500" aria-label="Delete {{ $amenity->title }}" title="Delete {{ $amenity->title }}" data-tooltip="Delete">
                            <x-icon name="delete" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded-sm border border-dashed border-slate-300 p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3">No amenities yet.</p>
        @endforelse
    </div>
</x-admin-layout>

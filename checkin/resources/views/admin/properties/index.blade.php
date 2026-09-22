<x-admin-layout title="Properties">
    <div class="page-header">
        <div>
            <p class="eyebrow">Portfolio</p>
            <h1 class="page-title">Properties</h1>
            <p class="page-subtitle">Manage addresses, maps, images, GPS points, and smart locks for each property.</p>
        </div>
        <a href="{{ route('admin.properties.create') }}" class="btn-primary">Add Property</a>
    </div>

    <div class="card card-pad mb-5">
        <form class="grid gap-3 sm:grid-cols-[1fr_auto]"><input name="search" value="{{ request('search') }}" placeholder="Search by property, city, or address" class="input mt-0"><button class="btn-secondary">Search</button></form>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($properties as $property)
            <article class="card overflow-hidden">
                <div class="h-44 bg-cover bg-center" style="background-image:url('{{ $property->heroImageUrl() }}')"></div>
                <div class="card-pad">
                    <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold text-slate-950">{{ $property->name }}</h2><span class="badge {{ $property->active ? 'badge-active' : 'badge-inactive' }}">{{ $property->active ? 'Active' : 'Inactive' }}</span></div>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $property->fullAddress() }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('admin.properties.edit', $property) }}" class="btn-secondary">Edit</a>
                        <button type="button" onclick="openDuplicateModal('{{ route('admin.properties.duplicate', $property) }}', '{{ $property->name }}')" class="btn-secondary">Duplicate</button>
                        <form method="post" action="{{ route('admin.properties.destroy', $property) }}" onsubmit="return confirm('Delete this property? This cannot be undone.')">@csrf @method('delete')<button class="btn-danger">Delete</button></form>
                    </div>
                </div>
            </article>
        @empty
            <div class="card card-pad text-center text-slate-500 md:col-span-2 xl:col-span-3">No properties yet. Add a property to begin building the guest welcome guide.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $properties->links() }}</div>

    {{-- Duplicate Property Modal --}}
    <div id="duplicate-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <p class="mb-1 text-sm font-bold text-slate-700">Duplicate <span id="duplicate-property-name"></span></p>
            <p class="mb-4 text-xs text-slate-500">This creates additional units at the same address with the same address, GPS, instructions, amenities, and guide content. You can edit each unit afterward.</p>
            <form id="duplicate-form" method="post">
                @csrf
                <label class="field-label">
                    How many additional units?
                    <input type="number" name="count" id="duplicate-count" min="1" max="50" value="1" required class="input">
                    <span class="field-help">E.g. entering 3 creates 3 new units (Unit 2, Unit 3, Unit 4).</span>
                </label>
                <div class="mt-4 flex gap-3">
                    <button type="button" onclick="closeDuplicateModal()" class="btn-secondary flex-1 text-sm">Cancel</button>
                    <button type="submit" class="btn-primary flex-1 text-sm">Create Units</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openDuplicateModal(actionUrl, propertyName) {
        document.getElementById('duplicate-form').action = actionUrl;
        document.getElementById('duplicate-property-name').textContent = propertyName;
        document.getElementById('duplicate-count').value = 1;
        document.getElementById('duplicate-modal').classList.remove('hidden');
        document.getElementById('duplicate-modal').classList.add('flex');
    }

    function closeDuplicateModal() {
        document.getElementById('duplicate-modal').classList.add('hidden');
        document.getElementById('duplicate-modal').classList.remove('flex');
    }

    document.getElementById('duplicate-modal').addEventListener('click', function (e) {
        if (e.target === this) closeDuplicateModal();
    });
    </script>
</x-admin-layout>

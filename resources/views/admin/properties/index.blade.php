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

    <form method="get" class="mb-3 flex items-center gap-2 text-sm text-slate-600">
        <input type="hidden" name="search" value="{{ request('search') }}">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="show_inactive" value="1" onchange="this.form.submit()" {{ request()->boolean('show_inactive') ? 'checked' : '' }}>
            Show inactive properties
        </label>
    </form>
    <div class="card divide-y divide-slate-100">
        @forelse($properties as $property)
            <div class="flex items-center gap-4 p-4">
                <img src="{{ $property->heroImageUrl() }}" alt="" class="h-16 w-24 shrink-0 rounded-lg bg-slate-100 object-cover {{ $property->active ? '' : 'opacity-60' }}">
                <div class="min-w-0 flex-1 {{ $property->active ? '' : 'opacity-60' }}">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.properties.edit', $property) }}" class="truncate font-semibold text-slate-950 hover:underline">{{ $property->name }}</a>
                        <span class="badge {{ $property->active ? 'badge-active' : 'badge-inactive' }}">{{ $property->active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <p class="truncate text-sm text-slate-500">{{ $property->fullAddress() }}</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                        <span>Owner: {{ $property->owner?->name ?? 'Unassigned' }}</span>
                        <a href="{{ route('properties.rooms.index', $property) }}" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:bg-slate-200">{{ $property->rooms_count }} {{ \Illuminate\Support\Str::plural('room', $property->rooms_count) }}</a>
                        <a href="{{ route('properties.property-tasks.index', $property) }}" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:bg-slate-200">{{ $property->property_tasks_count }} {{ \Illuminate\Support\Str::plural('task', $property->property_tasks_count) }}</a>
                    </div>
                </div>
                <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" class="btn-secondary" @click="open = !open" :aria-expanded="open" aria-label="Property actions for {{ $property->name }}">Actions &#9662;</button>
                    <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                        <a href="{{ route('admin.properties.edit', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Edit</a>
                        <button type="button" class="block w-full px-4 py-2 text-left hover:bg-slate-50" @click="open = false; openDuplicateModal(@js(route('admin.properties.duplicate', $property)), @js($property->name))">Duplicate</button>
                        <div class="my-1 border-t border-slate-100"></div>
                        <button type="button" class="block w-full px-4 py-2 text-left hover:bg-slate-50" @click="open = false; $dispatch('open-preview-panel', 'assign-rooms-{{ $property->id }}')">Add room</button>
                        <a href="{{ route('properties.rooms.index', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Rooms</a>
                        <a href="{{ route('properties.property-tasks.index', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Property tasks</a>
                        <a href="{{ route('manage.sessions.index', ['property_id' => $property->id]) }}" class="block px-4 py-2 hover:bg-slate-50">Cleaning schedule</a>
                        <a href="{{ route('admin.guest-guide.show', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Guest guide</a>
                        <a href="{{ route('admin.instructions.show', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Check-in / check-out details</a>
                        <a href="{{ route('admin.photo-guide.index', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Photo guide (cleaner)</a>
                        <a href="{{ route('admin.properties.availability.index', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Availability</a>
                        <a href="{{ route('properties.notifications.index', $property) }}" class="block px-4 py-2 hover:bg-slate-50">Notifications</a>
                        <div class="my-1 border-t border-slate-100"></div>
                        @if($property->active)
                        <form method="post" action="{{ route('properties.destroy', $property) }}" onsubmit="return confirm(@js('Mark ' . $property->name . ' inactive? It stays in the system and can be reactivated.'))">
                            @csrf @method('delete')
                            <button class="block w-full px-4 py-2 text-left hover:bg-slate-50">Mark inactive</button>
                        </form>
                        @else
                        <form method="post" action="{{ route('properties.activate', $property) }}" onsubmit="return confirm(@js('Reactivate ' . $property->name . '?'))">
                            @csrf
                            <button class="block w-full px-4 py-2 text-left hover:bg-slate-50">Reactivate</button>
                        </form>
                        @endif
                        @if(auth()->user()->hasRole('admin'))
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="post" action="{{ route('admin.properties.destroy', $property) }}" onsubmit="return prompt(@js('This permanently deletes the property and cannot be undone. Type the property name to confirm:')) === @js($property->name)">
                            @csrf @method('delete')
                            <button class="block w-full px-4 py-2 text-left text-rose-600 hover:bg-rose-50">Delete permanently</button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-slate-500">No properties yet. Add a property to begin building the guest welcome guide.</div>
        @endforelse
    </div>
    @foreach($properties as $property)
        @include('properties.__assign_rooms_panel', [
            'roomsForJs' => collect($rooms)->filter(fn ($r) => empty($r['property_ids']) || in_array($property->id, $r['property_ids']))->values()->all(),
            'property' => $property,
            'attachedRoomIds' => $property->rooms->pluck('id')->values()->all(),
        ])
    @endforeach
    <div class="mt-5">{{ $properties->links() }}</div>

    {{-- Duplicate Property Modal --}}
    <div id="duplicate-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <p class="mb-1 text-sm font-bold text-slate-700">Duplicate <span id="duplicate-property-name"></span></p>
            <p class="mb-4 text-xs text-slate-500">Choose which property content should be copied into each new unit.</p>
            <form id="duplicate-form" method="post">
                @csrf
                <label class="field-label">
                    How many additional units?
                    <input type="number" name="count" id="duplicate-count" min="1" max="50" value="1" required class="input">
                    <span class="field-help">E.g. entering 3 creates 3 new units (Unit 2, Unit 3, Unit 4).</span>
                </label>
                <div class="mt-4 grid gap-2 text-sm text-slate-700">
                    <label class="flex items-center gap-2"><input type="checkbox" name="copy_guest_portal" value="1" checked> Guest Portal content</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="copy_rooms_tasks" value="1" checked> Rooms & tasks</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="copy_property_tasks" value="1" checked> Property tasks</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="copy_notification_recipients" value="1" checked> Notification recipients</label>
                </div>
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

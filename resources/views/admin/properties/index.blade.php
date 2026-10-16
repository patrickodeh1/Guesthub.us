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
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($properties as $property)
            <div class="card relative flex flex-col" x-data="{ open: false }" :class="open ? 'z-30' : ''" @keydown.escape.window="open = false">
                <img src="{{ $property->heroImageUrl() }}" alt="" class="block h-44 w-full rounded-t-xl bg-slate-100 object-cover sm:h-48 {{ $property->active ? '' : 'opacity-60' }}">
                <div class="flex flex-1 flex-col p-4 {{ $property->active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('admin.properties.edit', $property) }}" class="min-w-0 break-words text-lg font-semibold text-slate-950 hover:underline">{{ $property->name }}</a>
                        <span class="badge shrink-0 {{ $property->active ? 'badge-active' : 'badge-inactive' }}">{{ $property->active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <p class="mt-1 break-words text-sm text-slate-500">{{ $property->fullAddress() }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-slate-500">
                        <span>Owner: {{ $property->owner?->name ?? 'Unassigned' }}</span>
                        <a href="{{ route('properties.rooms.index', $property) }}" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:bg-slate-200">{{ $property->rooms_count }} {{ \Illuminate\Support\Str::plural('room', $property->rooms_count) }}</a>
                        <a href="{{ route('properties.property-tasks.index', $property) }}" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:bg-slate-200">{{ $property->property_tasks_count }} {{ \Illuminate\Support\Str::plural('task', $property->property_tasks_count) }}</a>
                    </div>
                </div>
                <div class="border-t border-slate-100 p-3">
                    <button type="button" class="btn-primary flex w-full items-center justify-center" @click="open = !open" :aria-expanded="open" aria-label="Manage {{ $property->name }}">Manage <span class="ml-1 inline-block transition-transform duration-200" :class="open ? 'rotate-180' : ''">&#9662;</span></button>
                </div>

                <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-40 bg-slate-950/40 sm:hidden"
                     x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

                <div x-show="open" x-cloak @click.outside="open = false"
                     class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-2xl bg-white p-2 pb-6 shadow-2xl sm:absolute sm:inset-x-3 sm:bottom-16 sm:w-auto sm:max-h-[70vh] sm:rounded-xl sm:border sm:border-slate-200 sm:pb-2 sm:shadow-xl"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-2 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-2 sm:scale-95">
                    <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-slate-300 sm:hidden"></div>
                    <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Setup</p>
                    <a href="{{ route('admin.properties.edit', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Edit property</a>
                    <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm hover:bg-slate-50" @click="open = false; openDuplicateModal(@js(route('admin.properties.duplicate', $property)), @js($property->name))">Duplicate</button>
                    <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm hover:bg-slate-50" @click="open = false; $dispatch('open-preview-panel', 'assign-rooms-{{ $property->id }}')">Add room</button>
                    <a href="{{ route('properties.rooms.index', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Rooms</a>
                    <a href="{{ route('admin.properties.availability.index', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Availability</a>
                    <a href="{{ route('properties.notifications.index', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Notifications</a>

                    <p class="px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Cleaning</p>
                    <a href="{{ route('properties.property-tasks.index', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Property tasks</a>
                    <a href="{{ route('manage.sessions.index', ['property_id' => $property->id]) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Cleaning schedule</a>
                    <a href="{{ route('admin.photo-guide.index', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Photo guide (cleaner)</a>

                    <p class="px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Guest</p>
                    <a href="{{ route('admin.guest-guide.show', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Guest guide</a>
                    <a href="{{ route('admin.instructions.show', $property) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Check-in / check-out details</a>

                    <p class="mt-1 border-t border-slate-100 px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p>
                    @if($property->active)
                    <form method="post" action="{{ route('properties.destroy', $property) }}" onsubmit="return confirm(@js('Mark ' . $property->name . ' inactive? It stays in the system and can be reactivated.'))">
                        @csrf @method('delete')
                        <button class="block w-full rounded-lg px-3 py-2.5 text-left text-sm hover:bg-slate-50">Mark inactive</button>
                    </form>
                    @else
                    <form method="post" action="{{ route('properties.activate', $property) }}" onsubmit="return confirm(@js('Reactivate ' . $property->name . '?'))">
                        @csrf
                        <button class="block w-full rounded-lg px-3 py-2.5 text-left text-sm hover:bg-slate-50">Reactivate</button>
                    </form>
                    @endif
                    @if(auth()->user()->hasRole('admin'))
                    <form method="post" action="{{ route('admin.properties.destroy', $property) }}" onsubmit="return prompt(@js('This permanently deletes the property and cannot be undone. Type the property name to confirm:')) === @js($property->name)">
                        @csrf @method('delete')
                        <button class="block w-full rounded-lg px-3 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50">Delete permanently</button>
                    </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full card p-6 text-center text-slate-500">No properties yet. Add a property to begin building the guest welcome guide.</div>
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

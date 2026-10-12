<x-app-layout>
    <div class="mx-auto max-w-3xl p-4 space-y-5" style="background:#f6f7fb">
        <div>
            <a href="{{ route('admin.properties.index') }}" class="text-sm font-semibold text-slate-500">&larr; Properties</a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Cleaner photo guide</h1>
            <p class="text-sm text-slate-600">{{ $property->name }}. Show cleaners what a finished room looks like.</p>
        </div>

        @if (session('status')) <p class="rounded-lg bg-green-50 px-4 py-2 text-sm font-semibold text-green-700">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">{{ $errors->first() }}</p> @endif

        <form method="POST" action="{{ route('admin.photo-guide.store', $property) }}" enctype="multipart/form-data" class="guest-portal-card p-5 space-y-4">
            @csrf
            <p class="guest-status-kicker">Add photos</p>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Room</span>
                <select name="room_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All rooms</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}">{{ $room->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Instructions <span class="font-normal text-slate-500">(optional)</span></span>
                <input type="text" name="instructions" maxlength="1000" placeholder="e.g. Doors open, then doors closed" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Photos</span>
                <input type="file" name="images[]" accept="image/*" multiple required class="mt-1 w-full text-sm"
                       onchange="document.getElementById('pg-count').textContent = this.files.length + ' selected'">
                <span id="pg-count" class="text-xs text-slate-500"></span>
            </label>

            <button class="guest-primary-btn w-full">Add photos</button>
        </form>

        @php
            $groups = $references->groupBy(fn ($r) => $r->room_id ?: 0);
            $order = collect([0])->merge($rooms->pluck('id'))->filter(fn ($id) => $groups->has($id));
        @endphp

        @forelse ($order as $rid)
            <div class="guest-portal-card p-5">
                <p class="guest-status-kicker">{{ $rid === 0 ? 'All rooms' : ($rooms->firstWhere('id', $rid)->name ?? 'Room') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($groups[$rid] as $ref)
                        <div class="rounded-lg border border-slate-200 p-2">
                            <img src="{{ $ref->imageUrl() }}" alt="" class="w-full rounded-md">
                            @if ($ref->instructions) <p class="mt-2 text-xs text-slate-700">{{ $ref->instructions }}</p> @endif
                            <form method="POST" action="{{ route('admin.photo-guide.destroy', $ref) }}" onsubmit="return confirm('Remove this photo?')">
                                @csrf @method('DELETE')
                                <button class="mt-1 text-xs font-semibold text-red-600">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-center text-sm text-slate-500">No guide photos yet.</p>
        @endforelse
    </div>
</x-app-layout>

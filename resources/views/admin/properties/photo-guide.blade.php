<x-app-layout>
    <div class="mx-auto max-w-3xl p-4">
        <h1 class="text-xl font-bold">Expected finished photos: {{ $property->name }}</h1>
        <p class="mt-1 text-sm text-gray-600">Cleaners must view every image below before they can take finished photos.</p>

        @if (session('status')) <p class="mt-3 text-sm font-semibold text-green-700">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="mt-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</p> @endif

        <form method="POST" action="{{ route('admin.photo-guide.store', $property) }}" enctype="multipart/form-data" class="mt-4 rounded-xl border p-4">
            @csrf
            <div id="rows">
                <div class="mb-3 flex gap-2">
                    <input type="file" name="images[]" accept="image/*" required class="flex-1 text-sm">
                    <input type="text" name="captions[]" placeholder="Caption (e.g. Kitchen counter, wide shot)" class="flex-1 rounded border px-2 py-1 text-sm">
                </div>
            </div>
            <button type="button" onclick="var r=document.querySelector('#rows div').cloneNode(true);r.querySelectorAll('input').forEach(function(i){i.value=''});document.getElementById('rows').appendChild(r)" class="text-sm font-semibold text-indigo-600 underline">+ Add another</button>
            <div class="mt-4"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Upload</button></div>
        </form>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            @forelse ($references as $ref)
                <div class="rounded-xl border p-2">
                    <img src="{{ $ref->imageUrl() }}" alt="" class="w-full rounded-lg">
                    <p class="mt-2 text-sm">{{ $ref->caption ?: '(no caption)' }}</p>
                    <form method="POST" action="{{ route('admin.photo-guide.destroy', $ref) }}" onsubmit="return confirm('Remove this photo?')">
                        @csrf @method('DELETE')
                        <button class="mt-1 text-xs font-semibold text-red-600 underline">Remove</button>
                    </form>
                </div>
            @empty
                <p class="col-span-full text-sm italic text-gray-500">No expected photos yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>

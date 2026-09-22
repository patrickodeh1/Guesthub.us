<x-admin-layout :title="$amenity->exists ? 'Edit Amenity' : 'Add Amenity'">
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $property->name }}</p>
            <h1 class="page-title">{{ $amenity->exists ? 'Edit amenity' : 'Add amenity' }}</h1>
            <p class="page-subtitle">Amenities show up in the guest guide under the Amenities section.</p>
        </div>
        <a href="{{ route('admin.amenities.index', $property) }}" class="btn-secondary">Back to Amenities</a>
    </div>

    <form method="post" enctype="multipart/form-data"
          action="{{ $amenity->exists ? route('admin.amenities.update', $amenity) : route('admin.amenities.store', $property) }}"
          class="grid gap-6 xl:grid-cols-[1fr_360px]">
        @csrf
        @if($amenity->exists) @method('put') @endif

        <section class="card card-pad">
            <h2 class="section-title">Details</h2>
            <label class="field-label mt-6">Title <span class="text-red-600">*</span><input name="title" value="{{ old('title', $amenity->title) }}" required placeholder="Fitness Center" class="input"></label>
            <label class="field-label mt-5">Icon label<input name="icon" value="{{ old('icon', $amenity->icon) }}" placeholder="Fitness Center, Pool, Parking" class="input"></label>
            <label class="field-label mt-5">Details<textarea name="details" rows="6" placeholder="Open daily from 6:00 AM to 10:00 PM..." class="textarea">{{ old('details', $amenity->details) }}</textarea></label>
            <label class="mt-5 flex items-center gap-3 text-sm font-semibold"><input type="checkbox" name="active" value="1" @checked(old('active', $amenity->active ?? true)) class="rounded border-slate-300"> Active</label>
            <button class="btn-primary mt-6">{{ $amenity->exists ? 'Save Amenity' : 'Add Amenity' }}</button>
        </section>

        <aside class="card card-pad">
            <h2 class="section-title">Images</h2>
            <p class="section-copy">Upload new images, or add existing ones from the media library.</p>

            <div id="amenity-image-list" class="mt-4 grid grid-cols-2 gap-2">
                @if($amenity->exists)
                    @foreach($amenity->images ?? [] as $img)
                        <div class="relative">
                            <img src="{{ url('/img/'.$img) }}" alt="" class="h-20 w-full rounded-lg object-cover">
                            <input type="hidden" name="existing_images[]" value="{{ $img }}">
                            <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:4px;right:4px;z-index:50;width:22px;height:22px;border-radius:9999px;background:rgba(255,255,255,0.9);border:none;color:#dc2626;font-size:14px;font-weight:700;line-height:22px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.3);">X</button>
                        </div>
                    @endforeach
                @endif
            </div>

            <label class="field-label mt-4">
                Upload new images
                <span class="file-box min-h-20"><x-icon name="upload" class="mb-1 h-5 w-5 text-[#b08a45]" />Choose images<input type="file" name="images[]" accept="image/*" multiple class="sr-only"></span>
            </label>
            <button type="button" onclick="openAmenityMediaPicker()" class="btn-secondary mt-2 w-full text-sm">Choose from Library</button>

            {{-- Media Picker Modal --}}
            <div id="media-picker-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 p-4">
                <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                    <div class="mb-3 flex items-center justify-between">
                        <p id="media-picker-breadcrumb" class="text-sm font-bold text-slate-700">Library</p>
                        <button type="button" onclick="closeMediaPicker()" class="text-slate-400 hover:text-slate-700">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </div>
                    <div id="media-picker-body" class="grid max-h-96 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4"></div>
                </div>
            </div>
        </aside>
    </form>

    <script>
    function openAmenityMediaPicker() {
        document.getElementById('media-picker-modal').classList.remove('hidden');
        document.getElementById('media-picker-modal').classList.add('flex');
        loadMediaPicker(null);
    }
    function closeMediaPicker() {
        document.getElementById('media-picker-modal').classList.add('hidden');
        document.getElementById('media-picker-modal').classList.remove('flex');
    }
    function loadMediaPicker(folderId) {
        const url = '{{ route("admin.media.picker") }}' + (folderId ? '?folder_id=' + folderId : '');
        fetch(url).then(r => r.json()).then(data => {
            const body = document.getElementById('media-picker-body');
            const crumbText = data.breadcrumb.length ? data.breadcrumb.map(c => c.name).join(' / ') : 'Library';
            document.getElementById('media-picker-breadcrumb').textContent = crumbText;
            body.innerHTML = '';
            if (folderId !== null) {
                const up = document.createElement('button');
                up.type = 'button';
                up.className = 'col-span-full text-left text-xs font-semibold text-blue-600 hover:underline';
                up.textContent = 'Back';
                const parentId = data.breadcrumb.length > 1 ? data.breadcrumb[data.breadcrumb.length - 2].id : null;
                up.onclick = () => loadMediaPicker(parentId);
                body.appendChild(up);
            }
            data.folders.forEach(folder => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'flex flex-col items-center gap-1 rounded-lg border border-slate-200 p-3 hover:bg-slate-50';
                el.innerHTML = '<span class="text-xs font-semibold">' + folder.name + '</span>';
                el.onclick = () => loadMediaPicker(folder.id);
                body.appendChild(el);
            });
            data.files.forEach(file => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'overflow-hidden rounded-lg border border-slate-200 hover:ring-2 hover:ring-blue-400';
                el.innerHTML = '<img src="' + file.url + '" class="h-20 w-full object-cover">';
                el.onclick = () => selectAmenityMediaFile(file);
                body.appendChild(el);
            });
            if (!data.folders.length && !data.files.length) {
                body.innerHTML += '<p class="col-span-full text-center text-sm text-slate-400">No images in this folder yet.</p>';
            }
        });
    }
    function selectAmenityMediaFile(file) {
        const list = document.getElementById('amenity-image-list');
        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        wrapper.innerHTML = '<img src="' + file.url + '" class="h-20 w-full rounded-lg object-cover">' +
            '<input type="hidden" name="existing_images[]" value="' + file.path + '">' +
            '<button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:4px;right:4px;z-index:50;width:22px;height:22px;border-radius:9999px;background:rgba(255,255,255,0.9);border:none;color:#dc2626;font-size:14px;font-weight:700;line-height:22px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.3);">X</button>';
        list.appendChild(wrapper);
        closeMediaPicker();
    }
    </script>
</x-admin-layout>

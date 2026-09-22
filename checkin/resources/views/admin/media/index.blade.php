@php
    use App\Models\MediaFolder;
    use Illuminate\Support\Js;

    $allFolders = MediaFolder::with('children')->orderBy('name')->get();
    $rootFolders = $allFolders->whereNull('parent_id');
    $currentFolderId = $currentFolder?->id;

    $flatFolderOptions = function () use (&$flatFolderOptions, $allFolders) {
        $build = function ($parentId, $depth) use (&$build, $allFolders) {
            $out = [];
            foreach ($allFolders->where('parent_id', $parentId) as $f) {
                $out[] = ['id' => $f->id, 'label' => str_repeat('- ', $depth) . $f->name];
                $out = array_merge($out, $build($f->id, $depth + 1));
            }
            return $out;
        };
        return $build(null, 0);
    };
    $folderOptions = $flatFolderOptions();

    $formatSize = function (?int $bytes): string {
        if (!$bytes) return '-';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        return number_format($bytes / 1024, 1) . ' KB';
    };
@endphp

<x-admin-layout title="Media Library">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest experience</p>
            <h1 class="page-title">Media Library</h1>
            <p class="page-subtitle">Organize step photos into folders so they are easy to find and reuse.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="toggleSelectAll()" class="btn-secondary text-sm">Select All</button>
            <button type="button" onclick="toggleNewFolder()" class="btn-secondary text-sm">+ New Folder</button>
            <label class="btn-primary cursor-pointer text-sm">
                Upload Image
                <input type="file" id="media-upload-input" accept="image/*" class="sr-only">
            </label>
        </div>
    </div>

    {{-- New Folder Form (hidden by default) --}}
    <form id="new-folder-form" method="post" action="{{ route('admin.media.folders.store') }}" class="card card-pad mb-5 hidden max-w-md flex gap-2">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">
        <input type="text" name="name" required placeholder="Folder name" class="input mt-0 flex-1">
        <button class="btn-primary text-sm">Create</button>
    </form>

    {{-- Hidden upload form, submitted via JS after preview --}}
    <form id="media-upload-form" method="post" enctype="multipart/form-data" action="{{ route('admin.media.files.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="media_folder_id" value="{{ $currentFolder?->id }}">
    </form>

    <div class="card overflow-hidden">
        <div class="flex min-h-[520px] flex-col lg:flex-row">
            {{-- Folder sidebar --}}
            <aside class="w-full shrink-0 border-b border-slate-200 bg-slate-50 lg:w-60 lg:border-b-0 lg:border-r">
                <div class="border-b border-slate-200 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Folders</p>
                </div>
                <nav class="max-h-72 overflow-y-auto p-2 lg:max-h-none">
                    <a href="{{ route('admin.media.index') }}"
                       class="mb-0.5 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition {{ !$currentFolderId ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}">
                        <x-icon name="image" class="h-4 w-4 shrink-0" />
                        All Media
                    </a>

                    @foreach($rootFolders as $folder)
                        @include('admin.media.partials.folder-tree-item', [
                            'folder' => $folder,
                            'allFolders' => $allFolders,
                            'currentFolderId' => $currentFolderId,
                            'depth' => 0,
                        ])
                    @endforeach
                </nav>
            </aside>

            {{-- Main content --}}
            <div class="min-w-0 flex-1">
                {{-- Current location bar --}}
                <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-500">
                    <a href="{{ route('admin.media.index') }}" class="hover:text-slate-900">All Media</a>
                    @foreach($breadcrumb as $crumb)
                        <span class="text-slate-300">/</span>
                        <a href="{{ route('admin.media.index', ['folder_id' => $crumb['id']]) }}" class="hover:text-slate-900">
                            {{ $crumb['name'] }}
                        </a>
                    @endforeach
                </div>

                <div class="p-4">
                    @if($folders->isNotEmpty())
                        <div class="mb-4 flex flex-wrap gap-2">
                            @foreach($folders as $folder)
                                <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 pr-1">
                                    <label class="flex items-center pl-2">
                                        <input type="checkbox" class="select-folder-checkbox rounded border-slate-300" value="{{ $folder->id }}" onchange="updateSelectionBar()">
                                    </label>
                                    <a href="{{ route('admin.media.index', ['folder_id' => $folder->id]) }}"
                                       class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-700 hover:text-slate-900">
                                        <x-icon name="folder" class="h-4 w-4 text-amber-600" />
                                        {{ $folder->name }}
                                    </a>
                                    <form method="post" action="{{ route('admin.media.folders.destroy', $folder) }}"
                                          onsubmit="return confirm('Delete this folder and everything inside it?')">
                                        @csrf @method('delete')
                                        <button class="rounded p-1.5 text-slate-300 hover:bg-red-50 hover:text-red-500" title="Delete folder">
                                            <x-icon name="delete" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($files->isEmpty())
                        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 py-16 text-center">
                            <x-icon name="image" class="mb-3 h-10 w-10 text-slate-300" />
                            <p class="text-sm font-semibold text-slate-600">No images in this folder</p>
                            <p class="mt-1 text-xs text-slate-400">Upload an image or choose a subfolder to get started.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                            @foreach($files as $file)
                                <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white text-left transition hover:border-[#b08a45] hover:shadow-md">
                                    <label class="flex items-center gap-2 border-b border-slate-100 px-2.5 py-1.5">
                                        <input type="checkbox" class="select-file-checkbox rounded border-slate-300" value="{{ $file->id }}" onchange="updateSelectionBar()">
                                        <span class="text-xs font-semibold text-slate-500">Select</span>
                                    </label>
                                    <button type="button"
                                        onclick="openFileDetail({{ Js::from([
                                            'id' => $file->id,
                                            'url' => $file->url(),
                                            'name' => $file->original_name,
                                            'size' => $formatSize($file->size),
                                            'date' => $file->created_at?->copy()->setTimezone(config('app.display_timezone'))->format('M j, Y g:i A') ?? '-',
                                            'deleteUrl' => route('admin.media.files.destroy', $file),
                                        ]) }})"
                                        class="block w-full text-left focus:outline-none">
                                    <div class="relative bg-slate-50">
                                        <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" class="h-40 w-full object-contain p-2">
                                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-[#b08a45]/0 transition group-hover:bg-[#b08a45]/20">
                                            <span class="grid h-10 w-10 scale-75 place-items-center rounded-full bg-white text-[#b08a45] opacity-0 shadow transition group-hover:scale-100 group-hover:opacity-100">
                                                <x-icon name="check" class="h-5 w-5" />
                                            </span>
                                        </div>
                                    </div>
                                    <div class="border-t border-slate-100 p-2.5">
                                        <p class="truncate text-xs font-semibold text-slate-700" title="{{ $file->original_name }}">{{ $file->original_name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">{{ $formatSize($file->size) }}</p>
                                    </div>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Upload preview modal --}}
    <div id="upload-preview-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <p class="mb-3 text-sm font-bold text-slate-700">Upload this image?</p>
            <img id="upload-preview-img" src="" class="mb-4 h-48 w-full rounded-lg object-cover">
            <div class="flex gap-3">
                <button type="button" onclick="cancelUpload()" class="btn-secondary flex-1 text-sm">Cancel</button>
                <button type="button" onclick="confirmUpload()" class="btn-primary flex-1 text-sm">Upload</button>
            </div>
        </div>
    </div>

    {{-- File detail modal --}}
    <div id="file-detail-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
        <div class="flex w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl md:max-h-[85vh] md:flex-row">
            <div class="flex min-h-[240px] flex-1 items-center justify-center bg-slate-100 p-4 md:min-h-[400px]">
                <img id="detail-preview-img" src="" alt="" class="max-h-[60vh] max-w-full rounded-lg object-contain">
            </div>
            <div class="flex w-full flex-col border-t border-slate-200 md:w-72 md:border-l md:border-t-0">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <p class="text-sm font-bold text-slate-800">Attachment Details</p>
                    <button type="button" onclick="closeFileDetail()" class="text-slate-400 hover:text-slate-700">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">File name</p>
                        <p id="detail-name" class="mt-1 break-all text-sm font-semibold text-slate-800"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">File size</p>
                        <p id="detail-size" class="mt-1 text-sm text-slate-600"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Uploaded</p>
                        <p id="detail-date" class="mt-1 text-sm text-slate-600"></p>
                    </div>
                </div>
                <div class="border-t border-slate-200 p-4">
                    <form id="detail-delete-form" method="post" onsubmit="return confirm('Delete this image permanently?')">
                        @csrf @method('delete')
                        <button type="submit" class="btn-secondary w-full text-sm text-red-600 hover:border-red-200 hover:bg-red-50">
                            Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleNewFolder() {
        document.getElementById('new-folder-form').classList.toggle('hidden');
    }

    let pendingFile = null;

    document.getElementById('media-upload-input').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        pendingFile = file;
        document.getElementById('upload-preview-img').src = URL.createObjectURL(file);
        document.getElementById('upload-preview-modal').classList.remove('hidden');
        document.getElementById('upload-preview-modal').classList.add('flex');
    });

    function cancelUpload() {
        pendingFile = null;
        document.getElementById('media-upload-input').value = '';
        document.getElementById('upload-preview-modal').classList.add('hidden');
        document.getElementById('upload-preview-modal').classList.remove('flex');
    }

    function confirmUpload() {
        if (!pendingFile) return;
        const form = document.getElementById('media-upload-form');
        const dt = new DataTransfer();
        dt.items.add(pendingFile);
        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'image';
        input.files = dt.files;
        input.classList.add('hidden');
        form.appendChild(input);
        form.submit();
    }

    function openFileDetail(file) {
        document.getElementById('detail-preview-img').src = file.url;
        document.getElementById('detail-preview-img').alt = file.name;
        document.getElementById('detail-name').textContent = file.name;
        document.getElementById('detail-size').textContent = file.size;
        document.getElementById('detail-date').textContent = file.date;
        document.getElementById('detail-delete-form').action = file.deleteUrl;
        document.getElementById('file-detail-modal').classList.remove('hidden');
        document.getElementById('file-detail-modal').classList.add('flex');
    }

    function closeFileDetail() {
        document.getElementById('file-detail-modal').classList.add('hidden');
        document.getElementById('file-detail-modal').classList.remove('flex');
    }

    document.getElementById('file-detail-modal').addEventListener('click', function (e) {
        if (e.target === this) closeFileDetail();
    });

    function getSelectedFileIds() {
        return Array.from(document.querySelectorAll('.select-file-checkbox:checked')).map(el => el.value);
    }
    function getSelectedFolderIds() {
        return Array.from(document.querySelectorAll('.select-folder-checkbox:checked')).map(el => el.value);
    }

    function updateSelectionBar() {
        const fileIds = getSelectedFileIds();
        const folderIds = getSelectedFolderIds();
        const total = fileIds.length + folderIds.length;
        const bar = document.getElementById('bulk-action-bar');
        document.getElementById('bulk-selected-count').textContent = total + ' selected';
        bar.classList.toggle('hidden', total === 0);
        bar.classList.toggle('flex', total > 0);
    }

    function toggleSelectAll() {
        const fileBoxes = document.querySelectorAll('.select-file-checkbox');
        const folderBoxes = document.querySelectorAll('.select-folder-checkbox');
        const allChecked = Array.from(fileBoxes).every(b => b.checked) && Array.from(folderBoxes).every(b => b.checked) && (fileBoxes.length + folderBoxes.length > 0);
        fileBoxes.forEach(b => b.checked = !allChecked);
        folderBoxes.forEach(b => b.checked = !allChecked);
        updateSelectionBar();
    }

    function clearSelection() {
        document.querySelectorAll('.select-file-checkbox, .select-folder-checkbox').forEach(b => b.checked = false);
        updateSelectionBar();
    }

    function submitBulkMove() {
        const target = document.getElementById('bulk-move-target').value;
        const form = document.getElementById('bulk-move-form');
        form.querySelector('[name=target_folder_id]').value = target;
        fillBulkIds(form);
        form.submit();
    }

    function submitBulkDelete() {
        if (!confirm('Delete the selected items permanently? This cannot be undone.')) return;
        const form = document.getElementById('bulk-delete-form');
        fillBulkIds(form);
        form.submit();
    }

    function fillBulkIds(form) {
        form.querySelectorAll('.dynamic-id-input').forEach(el => el.remove());
        getSelectedFileIds().forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'file_ids[]';
            input.value = id;
            input.classList.add('dynamic-id-input');
            form.appendChild(input);
        });
        getSelectedFolderIds().forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'folder_ids[]';
            input.value = id;
            input.classList.add('dynamic-id-input');
            form.appendChild(input);
        });
    }
    </script>

    {{-- Floating bulk action bar --}}
    <div id="bulk-action-bar" class="fixed inset-x-0 bottom-0 z-[99998] hidden items-center justify-center gap-3 border-t border-slate-200 bg-white p-3 shadow-lg">
        <span id="bulk-selected-count" class="text-sm font-semibold text-slate-600">0 selected</span>
        <select id="bulk-move-target" class="input mt-0 w-56">
            <option value="">Move to: All Media (root)</option>
            @foreach($folderOptions as $opt)
                <option value="{{ $opt['id'] }}">Move to: {{ $opt['label'] }}</option>
            @endforeach
        </select>
        <button type="button" onclick="submitBulkMove()" class="btn-secondary text-sm">Move</button>
        <button type="button" onclick="submitBulkDelete()" class="btn-secondary text-sm text-red-600 hover:border-red-200 hover:bg-red-50">Delete</button>
        <button type="button" onclick="clearSelection()" class="text-sm text-slate-400 hover:text-slate-700">Cancel</button>
    </div>

    <form id="bulk-move-form" method="post" action="{{ route('admin.media.bulk-move') }}" class="hidden">
        @csrf
        <input type="hidden" name="target_folder_id" value="">
    </form>
    <form id="bulk-delete-form" method="post" action="{{ route('admin.media.bulk-delete') }}" class="hidden">
        @csrf
        @method('delete')
    </form>
</x-admin-layout>

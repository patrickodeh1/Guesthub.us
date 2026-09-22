@php
    $children = $allFolders->where('parent_id', $folder->id);
    $isActive = $currentFolderId === $folder->id;
@endphp

<div>
    <div class="group flex items-center gap-0.5" style="padding-left: {{ $depth * 12 }}px">
        <a href="{{ route('admin.media.index', ['folder_id' => $folder->id]) }}"
           class="mb-0.5 flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-2 text-sm font-semibold transition {{ $isActive ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}">
            <x-icon name="folder" class="h-4 w-4 shrink-0 {{ $isActive ? 'text-amber-600' : 'text-slate-400' }}" />
            <span class="truncate">{{ $folder->name }}</span>
        </a>
        <form method="post" action="{{ route('admin.media.folders.destroy', $folder) }}"
              onsubmit="return confirm('Delete this folder and everything inside it?')"
              class="opacity-0 transition group-hover:opacity-100">
            @csrf @method('delete')
            <button class="rounded p-1 text-slate-300 hover:text-red-500" title="Delete folder">
                <x-icon name="delete" class="h-3.5 w-3.5" />
            </button>
        </form>
    </div>

    @foreach($children as $child)
        @include('admin.media.partials.folder-tree-item', [
            'folder' => $child,
            'allFolders' => $allFolders,
            'currentFolderId' => $currentFolderId,
            'depth' => $depth + 1,
        ])
    @endforeach
</div>

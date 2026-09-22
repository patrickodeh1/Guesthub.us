<x-admin-layout title="Manage Categories">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest experience</p>
            <h1 class="page-title">Manage Categories</h1>
            <p class="page-subtitle">Create and manage the guide sections available to assign to properties.</p>
        </div>
        <a class="btn-primary" href="{{ route('admin.categories.create') }}">Add New Category</a>
    </div>

    @if($categories->isEmpty())
        <div class="card card-pad text-center text-slate-500">No categories yet. Click "Add New Category" to create your first one.</div>
    @endif

    <div id="sortable-categories" class="grid gap-4">
        @foreach($categories as $category)
            <div class="card overflow-hidden" data-id="{{ $category->id }}">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="drag-handle grid h-8 w-6 shrink-0 cursor-grab place-items-center text-slate-400 hover:text-slate-600" title="Drag to reorder">
                            <x-icon name="menu" class="h-5 w-5" />
                        </span>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600 overflow-hidden">
                            @if($category->guest_icon)
                                <img src="{{ url('/img/'.$category->guest_icon) }}" class="h-full w-full object-cover">
                            @else
                                <x-icon :name="$category->slug" class="h-5 w-5" />
                            @endif
                        </span>
                        <div>
                            <p class="font-semibold text-slate-950">{{ $category->title }}</p>
                            <p class="text-sm text-slate-500">{{ $category->description }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap shrink-0 gap-2">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn-secondary text-xs">Edit</a>
                        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? It will be removed from any properties it is assigned to.')">
                            @csrf @method('delete')
                            <button class="btn-danger text-xs">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
    (function () {
        var el = document.getElementById('sortable-categories');
        if (!el) return;
        new Sortable(el, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                var ids = Array.from(el.children).map(function (card) { return card.dataset.id; });
                fetch('{{ route("admin.categories.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ category_ids: ids })
                });
            }
        });
    })();
    </script>
</x-admin-layout>

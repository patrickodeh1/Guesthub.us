<x-admin-layout :title="$property->name">
    <div class="card overflow-hidden mb-8">
        <img src="{{ $property->heroImageUrl() }}" class="w-full max-h-96 object-cover">
        <div class="p-6">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-950">{{ $property->name }}</h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $property->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $property->active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    </div>

    @if($allProperties->where('id', '!=', $property->id)->isNotEmpty())
    <details class="card card-pad mb-8">
        <summary class="cursor-pointer font-bold text-slate-800">Copy this guide to other properties</summary>
        <p class="section-copy mt-3">Assigns every section below to the properties you pick. Sections stay shared and in sync with this guide, except any you mark to keep separate per unit (for example, Wi-Fi).</p>
        <form method="post" action="{{ route('admin.guest-guide.copy', $property) }}" class="mt-4">
            @csrf
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($allProperties->where('id', '!=', $property->id) as $other)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="target_property_ids[]" value="{{ $other->id }}" class="rounded border-slate-300 copy-guide-target">
                        <span class="truncate">{{ $other->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-5 border-t border-slate-100 pt-4">
                <p class="text-sm font-semibold text-slate-700">Keep these sections separate per unit</p>
                <p class="field-help">These are copied once so each unit can edit its own — use this for Wi-Fi and anything else that differs per unit. Everything not checked is shared and stays in sync with this property.</p>
                @if($property->categories->whereIn('id', $assignedIds)->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">This guide has no sections yet.</p>
                @else
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($property->categories->whereIn('id', $assignedIds) as $cat)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="separate_category_ids[]" value="{{ $cat->id }}" class="rounded border-slate-300">
                                <span class="truncate">{{ $cat->pivot->custom_title ?: $cat->title }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" class="btn-secondary text-xs" onclick="document.querySelectorAll('.copy-guide-target').forEach(function (c) { c.checked = true; })">Select all</button>
                <button class="btn-primary text-sm" onclick="return confirm('Copy this guide to the selected properties? Their sections will share and stay in sync with this guide.')">Copy guide</button>
            </div>
        </form>
    </details>
    @endif

    @php
        $unassigned = $categories->whereNotIn('id', $assignedIds);
    @endphp

    <div class="mb-3 mt-2 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-950">Categories</h2>
        <span class="text-sm text-slate-500">{{ count($assignedIds) }} of {{ $categories->count() }} sections active</span>
    </div>

    @if(count($assignedIds) === 0)
        <div class="card card-pad mb-6 text-center text-slate-500">No sections assigned yet. Add sections from the list below.</div>
    @endif

    <div id="sortable-categories" class="mb-8 grid gap-4">
        @foreach($categories->whereIn('id', $assignedIds) as $category)
            @php
                $page = $property->pages->firstWhere('category_id', $category->id);
                $pivot = $property->categories->firstWhere('id', $category->id)?->pivot;
            @endphp
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
                            <p class="font-semibold text-slate-950">{{ $pivot?->custom_title ?: $category->title }}</p>
                            <p class="text-sm text-slate-500">{{ $category->description }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap shrink-0 items-center gap-2">
                        @if($page?->isLinked())
                            @php $source = $page->resolvedPage(); @endphp
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Shared from {{ $source->property->name }}</span>
                        @elseif($page && $page->linkedPages->isNotEmpty())
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Shared with {{ $page->linkedPages->count() }} {{ \Illuminate\Support\Str::plural('property', $page->linkedPages->count()) }}</span>
                        @endif
                        <a href="{{ route('admin.categories.preview', [$category, $property]) }}" target="_blank" class="btn-secondary text-xs">Preview</a>
                        <a href="{{ route('admin.content.edit', [$property, $category]) }}" class="btn-secondary text-xs">{{ $page?->isLinked() ? 'Manage' : 'Edit' }}</a>
                        <form method="post" action="{{ route('admin.categories.assign') }}">
                            @csrf
                            <input type="hidden" name="property_id" value="{{ $property->id }}">
                            @foreach($categories->whereIn('id', $assignedIds)->where('id', '!=', $category->id) as $c)
                                <input type="hidden" name="category_ids[]" value="{{ $c->id }}">
                            @endforeach
                            <button class="btn-danger text-xs" onclick="return confirm('Remove this section from {{ $property->name }}?')">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($unassigned->count())
    <div class="mb-3">
        <button type="button" onclick="toggleSection('unassigned-pool')" class="text-sm font-semibold text-slate-500 hover:text-slate-900">
            + Show {{ $unassigned->count() }} available section(s) to add
        </button>
    </div>
    <div id="unassigned-pool" class="hidden">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($unassigned as $category)
                <div class="card card-pad flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 overflow-hidden">
                            @if($category->guest_icon)
                                <img src="{{ url('/img/'.$category->guest_icon) }}" class="h-full w-full object-cover">
                            @else
                                <x-icon :name="$category->slug" class="h-5 w-5 text-slate-400" />
                            @endif
                        </span>
                        <p class="text-sm font-semibold text-slate-700">{{ $category->title }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.categories.assign') }}">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $property->id }}">
                        @foreach($assignedIds as $assignedId)
                            <input type="hidden" name="category_ids[]" value="{{ $assignedId }}">
                        @endforeach
                        <input type="hidden" name="category_ids[]" value="{{ $category->id }}">
                        <button type="submit" class="btn-primary text-xs">Add</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif

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

    function toggleSection(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('hidden');
    }
    </script>
</x-admin-layout>

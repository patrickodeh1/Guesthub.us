<x-admin-layout :title="$category->exists ? 'Edit Category' : 'Add Category'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guide structure</p>
            <h1 class="page-title">{{ $category->exists ? 'Edit category' : 'Add category' }}</h1>
            <p class="page-subtitle">Categories organize the guest dashboard. Keep titles short and descriptions useful.</p>
        </div>
        <a href="{{ $returnTo ?? route('admin.properties.index') }}" class="btn-secondary">Back</a>
    </div>

    <form method="post" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-4xl card card-pad">
        @csrf @if($category->exists) @method('put') @endif
        <input type="hidden" name="return_to" value="{{ $returnTo ?? '' }}">
        <div class="grid gap-5 md:grid-cols-2">
            <label class="field-label">Title <span class="text-red-600">*</span><input name="title" value="{{ old('title', $category->title) }}" required placeholder="WiFi" class="input"></label>
            <label class="field-label">Slug<input name="slug" value="{{ old('slug', $category->slug) }}" placeholder="Auto-generated from title" class="input"></label>
            <label class="field-label">
                Category Type
                @php $currentAction = old('action', $category->action ?? 'content'); @endphp
                <select name="action" class="input mt-1">
                    <option value="content" @selected($currentAction === 'content')>Regular content page</option>
                    <option value="door_lock" @selected($currentAction === 'door_lock')>Door Lock/Unlock card</option>
                </select>
            </label>
            <label class="field-label">Icon label<input name="icon" value="{{ old('icon', $category->icon) }}" placeholder="WiFi, Pool, Food" class="input"><span class="field-help">Use short labels for the clean icon badge style.</span></label>
            <label class="field-label">Sort order<input name="sort_order" type="number" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="input"></label>
            <x-media-image-field
                name="guest_icon"
                label="Guest icon image"
                :value="$category->guest_icon"
                preview-class="h-24 w-24 rounded-xl border border-slate-200 object-contain bg-slate-50 p-1"
                help="Shown on guest guide category cards and category navigation."
            />

            <div>
                <x-media-image-field
                    name="header_image"
                    label="Page header image"
                    :value="$category->header_image"
                    preview-class="h-28 w-full rounded-xl border border-slate-200 object-cover"
                    help="Used as the default hero image for this category page. Leave blank to use the built-in default image for this category."
                />
            </div>
            <label class="field-label md:col-span-2">Description<textarea name="description" rows="4" placeholder="Short explanation guests will see on the guide card." class="textarea">{{ old('description', $category->description) }}</textarea></label>
            <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold"><span>Active</span><input type="checkbox" name="active" value="1" @checked(old('active', $category->active ?? true)) class="rounded border-slate-300"></label>
            <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold"><span>Global default</span><input type="checkbox" name="is_global" value="1" @checked(old('is_global', $category->is_global ?? true)) class="rounded border-slate-300"></label>
        </div>
        <button class="btn-primary mt-6">Save category</button>
    </form>
</x-admin-layout>

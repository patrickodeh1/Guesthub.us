<x-admin-layout title="Edit Category Content">
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $property->name }}</p>
            <h1 class="page-title">{{ $category->title }} content</h1>
            <p class="page-subtitle">Write the detailed guide page for this category.</p>
        </div>
        <a href="{{ route('admin.guest-guide.show', $property->id) }}" class="btn-secondary">Back</a>
    </div>

    @if($source)
        <section class="card card-pad border-amber-200 bg-amber-50">
            <p class="text-sm font-bold text-amber-900">Shared from {{ $source->property->name }}</p>
            <p class="mt-1 text-sm text-amber-800">This property shows the {{ $category->title }} content written on {{ $source->property->name }}. Editing it there updates every property that shares it. To give {{ $property->name }} its own version (for example, Wi-Fi), customize it locally.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.content.edit', [$source->property, $category]) }}" class="btn-secondary text-xs">Edit the shared page</a>
                <form method="post" action="{{ route('admin.content.unlink', [$property, $category]) }}">
                    @csrf
                    <button class="btn-primary text-xs" onclick="return confirm('Give {{ $property->name }} its own copy? It will stop following the shared version.')">Customize locally</button>
                </form>
            </div>
        </section>

        <section class="card card-pad mt-6">
            <h2 class="section-title">Preview</h2>
            <p class="section-copy mt-2">Read-only: this is the shared content guests will see.</p>
            <div class="prose-welcome mt-4 text-base">{!! $source->content !!}</div>
        </section>
    @else
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.content.update', [$property, $category]) }}" class="grid gap-6 xl:grid-cols-[1fr_360px]">
        @csrf @method('put')
        <section class="card card-pad">
            <h2 class="section-title">Page copy</h2>
            <label class="field-label mt-6">Title<input name="title" value="{{ old('title', $page->title) }}" required class="input"></label>
            <label class="field-label mt-5">Rich text content<textarea id="page-content-editor" name="content" rows="16" placeholder="Add guest-friendly instructions, recommendations, and helpful details." class="textarea">{{ old('content', $page->content) }}</textarea></label>
            <label class="mt-5 flex items-center gap-3 text-sm font-semibold"><input type="checkbox" name="active" value="1" @checked(old('active', $page->active ?? true)) class="rounded border-slate-300"> Active page</label>
            <button class="btn-primary mt-6">Save Page</button>
        </section>

        <aside class="card card-pad">
            <h2 class="section-title">Header image</h2>
            @if($category->header_image)
                <img src="{{ url('/img/'.$category->header_image) }}" alt="" class="mt-3 h-32 w-full rounded-xl object-cover">
            @else
                <div class="mt-3 flex h-32 w-full items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-400">No header image set</div>
            @endif
            <p class="section-copy mt-2">Set from the category's icon settings.</p>
            <a href="{{ route('admin.categories.edit', $category) }}" class="btn-secondary mt-3 w-full text-sm">Change Header</a>
        </aside>
    </form>
    @endif

    @unless($source)
    {{-- Media Picker Modal (for editor image insert) --}}
    <div id="media-picker-modal" class="fixed inset-0 hidden items-center justify-center bg-slate-950/40 p-4" style="z-index:2147483000;">
        <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p id="media-picker-breadcrumb" class="text-sm font-bold text-slate-700">Library</p>
                <div class="flex items-center gap-2">
                    <label class="btn-secondary cursor-pointer text-xs">
                        Upload
                        <input type="file" id="media-picker-upload-input" accept="image/*" class="sr-only">
                    </label>
                    <button type="button" onclick="closeMediaPickerForEditor()" class="text-slate-400 hover:text-slate-700">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <div id="media-picker-body" class="grid max-h-96 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4"></div>
        </div>
    </div>

    {{-- Page Link Picker Modal --}}
    <div id="page-link-modal" class="fixed inset-0 hidden items-center justify-center bg-slate-950/40 p-4" style="z-index:2147483000;">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-bold text-slate-700">Link to a page</p>
                <button type="button" onclick="closePageLinkModal()" class="text-slate-400 hover:text-slate-700">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            <div id="page-link-list" class="grid max-h-96 gap-2 overflow-y-auto"></div>
        </div>
    </div>

    {{-- Force TinyMCE's floating menus/dropdowns/overflow drawer below our modal --}}
    <style>
    .tox-tinymce-aux,
    .tox.tox-silver-sink,
    .tox-dialog-wrap {
        z-index: 1000 !important;
    }
    .tox-menu.tox-collection.tox-collection--list {
        max-height: 320px !important;
        overflow-y: auto !important;
    }
    .tox.tox-tinymce.tox-fullscreen,
    body.tox-fullscreen-body .tox.tox-tinymce.tox-fullscreen {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 2147483001 !important;
    }
    </style>

    {{-- Google Fonts loaded on the PAGE so toolbar dropdown labels render correctly --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <script>
    let __mediaPickerCurrentFolder = null;

    const __guidePages = @json($property->categories->map(fn($c) => ['id' => $c->id, 'title' => $c->title]));

    function openPageLinkModal() {
        const modal = document.getElementById('page-link-modal');
        const list = document.getElementById('page-link-list');
        list.innerHTML = '';
        if (!__guidePages.length) {
            list.innerHTML = '<p class="text-center text-sm text-slate-400">No guide pages available.</p>';
        }
        __guidePages.forEach(function(pageItem) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full rounded-lg border border-slate-200 p-3 text-left text-sm font-semibold hover:bg-slate-50';
            btn.textContent = pageItem.title;
            btn.onclick = function() { insertPageLink(pageItem); };
            list.appendChild(btn);
        });
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closePageLinkModal() {
        const modal = document.getElementById('page-link-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function insertPageLink(pageItem) {
        if (window.tinymce && tinymce.activeEditor) {
            const editor = tinymce.activeEditor;
            const selectedText = editor.selection.getContent({ format: 'text' });
            const label = selectedText && selectedText.trim() ? selectedText : pageItem.title;
            editor.insertContent('<a href="internal://category/' + pageItem.id + '">' + label + '</a>');
        }
        closePageLinkModal();
    }

    function closeMediaPickerForEditor() {
        const modal = document.getElementById('media-picker-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openMediaPickerForEditor() {
        const modal = document.getElementById('media-picker-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadMediaPickerForEditor(null);
    }

    function loadMediaPickerForEditor(folderId) {
        __mediaPickerCurrentFolder = folderId;
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
                up.onclick = () => loadMediaPickerForEditor(parentId);
                body.appendChild(up);
            }
            data.folders.forEach(folder => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'flex flex-col items-center gap-1 rounded-lg border border-slate-200 p-3 hover:bg-slate-50';
                el.innerHTML = '<span class="text-xs font-semibold">' + folder.name + '</span>';
                el.onclick = () => loadMediaPickerForEditor(folder.id);
                body.appendChild(el);
            });
            data.files.forEach(file => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'overflow-hidden rounded-lg border border-slate-200 bg-slate-50 hover:ring-2 hover:ring-blue-400';
                el.innerHTML = '<img src="' + file.url + '" class="h-20 w-full object-contain p-1">';
                el.onclick = () => {
                    if (window.tinymce && tinymce.activeEditor) {
                        tinymce.activeEditor.insertContent('<img src="' + file.url + '" alt="' + (file.name || '') + '" style="max-width:100%;">');
                    }
                    closeMediaPickerForEditor();
                };
                body.appendChild(el);
            });
            if (!data.folders.length && !data.files.length) {
                body.innerHTML += '<p class="col-span-full text-center text-sm text-slate-400">No images in this folder yet.</p>';
            }
        });
    }

    document.getElementById('media-picker-upload-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        if (__mediaPickerCurrentFolder) formData.append('media_folder_id', __mediaPickerCurrentFolder);
        fetch('{{ route("admin.media.files.store") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
        }).then(() => {
            loadMediaPickerForEditor(__mediaPickerCurrentFolder);
            e.target.value = '';
        });
    });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
    <script>
    tinymce.init({
        relative_urls: false,
        remove_script_host: false,
        selector: '#page-content-editor',
        plugins: 'lists advlist link code table searchreplace wordcount visualblocks charmap emoticons preview anchor fullscreen nonbreaking',
        toolbar: 'undo redo | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | customlineheight | link insertimage table anchor charmap emoticons | insertpagelink | searchreplace preview fullscreen | removeformat code | fontfamily fontsize',
        browser_spellcheck: true,
        contextmenu: false,
        font_size_formats: '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 42px 48px 60px 72px',
        font_family_formats:
            'Arial=arial,helvetica,sans-serif;' +
            'Helvetica=helvetica,arial,sans-serif;' +
            'Times New Roman=times new roman,times,serif;' +
            'Georgia=georgia,palatino,serif;' +
            'Garamond=garamond,serif;' +
            'Verdana=verdana,geneva,sans-serif;' +
            'Tahoma=tahoma,arial,helvetica,sans-serif;' +
            'Trebuchet MS=trebuchet ms,geneva,sans-serif;' +
            'Courier New=courier new,courier,monospace;' +
            'Comic Sans MS=comic sans ms,sans-serif;' +
            'Impact=impact,sans-serif;' +
            'Lucida Sans=lucida sans unicode,lucida grande,sans-serif;' +
            'Roboto=Roboto,arial,sans-serif;' +
            'Open Sans=\'Open Sans\',arial,sans-serif;' +
            'Montserrat=Montserrat,arial,sans-serif;' +
            'Merriweather=Merriweather,georgia,serif;' +
            'Playfair Display=\'Playfair Display\',georgia,serif',
        color_map: [
            '000000','Black', '424242','Dark Gray', '757575','Gray', 'BDBDBD','Light Gray', 'FFFFFF','White',
            'B71C1C','Dark Red', 'E53935','Red', 'F44336','Bright Red', 'FF7043','Orange Red', 'FB8C00','Orange',
            'FDD835','Yellow', 'C0CA33','Olive', '7CB342','Light Green', '43A047','Green', '00897B','Teal',
            '00ACC1','Cyan', '1E88E5','Blue', '3949AB','Indigo', '5E35B1','Purple', '8E24AA','Magenta', 'D81B60','Pink'
        ],
        valid_styles: {
            '*': 'font-size,font-family,color,background-color,text-align,text-decoration,line-height'
        },
        menubar: false,
        toolbar_mode: 'wrap',
        height: 480,
        ui_mode: 'split',
        promotion: false,
        branding: false,
        content_css: false,
        content_style: `
            @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap');
            body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; }
            p { margin: 0; }
        `,
        setup: function(editor) {
            var lineHeightValues = ['0.3', '0.5', '0.7', '0.9', '1', '1.15', '1.3', '1.5', '1.75', '2', '2.5', '3', '3.5', '4', '4.5', '5'];
            var lastSelectionRange = null;
            editor.on('NodeChange KeyUp MouseUp', function() {
                try { lastSelectionRange = editor.selection.getRng().cloneRange(); } catch (e) {}
            });
            editor.ui.registry.addMenuButton('customlineheight', {
                icon: 'line-height',
                tooltip: 'Line height',
                fetch: function(callback) {
                    var currentValue = null;
                    try {
                        var node0 = editor.selection.getNode();
                        var block0 = editor.dom.getParent(node0, editor.dom.isBlock) || node0;
                        if (block0 && block0.nodeName !== 'BODY') {
                            currentValue = editor.dom.getStyle(block0, 'line-height') || null;
                        }
                    } catch (e) {}
                    var items = lineHeightValues.map(function(v) {
                        return {
                            type: 'togglemenuitem',
                            text: v,
                            active: currentValue === v,
                            onAction: function() {
                                editor.focus();
                                if (lastSelectionRange) {
                                    try { editor.selection.setRng(lastSelectionRange); } catch (e) {}
                                }
                                var blocks = editor.selection.getSelectedBlocks();
                                if (!blocks || !blocks.length) {
                                    var node = editor.selection.getNode();
                                    var single = editor.dom.getParent(node, editor.dom.isBlock) || node;
                                    blocks = [single];
                                }
                                var applied = 0;
                                blocks.forEach(function(block) {
                                    if (block && block.nodeName !== 'BODY') {
                                        editor.dom.setStyle(block, 'line-height', v);
                                        applied++;
                                    }
                                });
                                if (applied > 0) {
                                    editor.nodeChanged();
                                }
                            }
                        };
                    });
                    callback(items);
                }
            });
            editor.ui.registry.addButton('insertimage', {
                icon: 'image',
                tooltip: 'Insert image from library',
                onAction: function() {
                    openMediaPickerForEditor();
                }
            });
            editor.ui.registry.addIcon('pagelink', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M9 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M14 3l5 5h-5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 14.5l2-2a2.1 2.1 0 0 1 3 3l-1 1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 14.5l-2 2a2.1 2.1 0 0 1-3-3l1-1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>');

            editor.ui.registry.addButton('insertpagelink', {
                icon: 'pagelink',
                tooltip: 'Link to a guide page',
                onAction: function() {
                    openPageLinkModal();
                }
            });
        }
    });
    </script>
    @endunless
</x-admin-layout>

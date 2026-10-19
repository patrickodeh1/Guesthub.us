<x-admin-layout title="Cleaner photo guide">
    <div class="page-header">
        <div>
            <a href="{{ route('admin.properties.index') }}" class="text-sm font-semibold text-slate-500">&larr; Properties</a>
            <p class="eyebrow mt-2">Cleaner experience</p>
            <h1 class="page-title">Cleaner photo guide</h1>
            <p class="page-subtitle">{{ $property->name }}. Show cleaners what a finished room looks like.</p>
        </div>
    </div>

    @if (session('status')) <p class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</p> @endif
    @if ($errors->any()) <p class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</p> @endif

    <form method="POST" action="{{ route('admin.photo-guide.store', $property) }}" enctype="multipart/form-data" class="card card-pad mb-8 space-y-5">
        @csrf
        <h2 class="text-lg font-semibold text-slate-950">Room instructions and photos</h2>

        <div>
            <label class="text-sm font-semibold text-slate-700" for="pg-room">Room</label>
            <select id="pg-room" name="room_id" class="input mt-1 w-full" required>
                <option value="" disabled selected>Select a room</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <span class="text-sm font-semibold text-slate-700">Photo note (shown with each photo you add)</span>
            <div id="pg-room-fields" class="mt-1 space-y-3">
                <textarea id="pg-editor"></textarea>
                <div id="pg-secs-row" class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <label for="pg-seconds">Seconds on screen</label>
                    <input type="number" id="pg-seconds" min="1" max="600" value="8" class="input mt-0 w-24">
                </div>
            </div>
            <p id="pg-all-note" class="mt-1 text-sm text-slate-500">Select a room to write its instructions and add photos.</p>
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700" for="pg-files">Photos</label>
            <input type="file" id="pg-files" name="images[]" accept="image/*" multiple class="mt-1 w-full text-sm"
                   onchange="document.getElementById('pg-count').textContent = this.files.length + ' selected'">
            <span id="pg-count" class="text-xs text-slate-500"></span>
        </div>

        <button class="btn-primary w-full">Save</button>
    </form>

    @php
        $groups = $references->groupBy(fn ($r) => $r->room_id ?: 0);
        $order = collect([0])->merge($rooms->pluck('id'))->filter(fn ($id) => $groups->has($id));
    @endphp

    @forelse ($order as $rid)
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-950">{{ $rid === 0 ? 'All rooms' : ($rooms->firstWhere('id', $rid)->name ?? 'Room') }}</h2>
            <span class="text-xs font-semibold text-slate-400">{{ $groups[$rid]->count() }} photo(s)</span>
        </div>
        <div class="grid gap-4 mb-8">
            @foreach ($groups[$rid] as $ref)
                <div class="card">
                    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <img src="{{ $ref->imageUrl() }}" loading="lazy" decoding="async" alt="" class="h-16 w-24 rounded-lg object-cover shrink-0">
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-400">Photo {{ $loop->iteration }}</span>
                                @if ($ref->instructions) <div class="mt-1 text-sm text-slate-700">{!! $ref->instructions !!}</div> @endif
                            </div>
                        </div>
                        <div style="position:relative" class="shrink-0">
                            <button type="button" class="pg-menu-btn btn-secondary" aria-label="Photo menu" style="font-size:1.25rem;line-height:1;padding:.2rem .8rem">&#8943;</button>
                            <div class="pg-menu" style="display:none;position:absolute;right:0;top:100%;z-index:20;min-width:9rem;margin-top:4px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.15);padding:4px 0">
                                <button type="button" class="pg-edit" data-url="{{ route('admin.photo-guide.update', $ref) }}" data-text="{{ $ref->instructions }}" style="display:block;width:100%;text-align:left;padding:8px 16px;font-size:.875rem;font-weight:600;color:#334155">Edit</button>
                                <form method="POST" action="{{ route('admin.photo-guide.destroy', $ref) }}" onsubmit="return confirm('Remove this photo?')">
                                    @csrf @method('DELETE')
                                    <button style="display:block;width:100%;text-align:left;padding:8px 16px;font-size:.875rem;font-weight:600;color:#dc2626">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card card-pad text-center text-slate-500">No guide photos yet.</div>
    @endforelse

    <div id="pg-modal" style="display:none;position:fixed;inset:0;z-index:900;background:rgba(15,23,42,.5);align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:760px;max-height:92vh;overflow:auto;padding:20px">
            <h3 class="text-lg font-semibold text-slate-950" style="margin-bottom:12px">Edit photo instructions</h3>
            <textarea id="pg-edit-editor"></textarea>
            <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
                <button type="button" id="pg-edit-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="pg-edit-save" class="btn-primary">Save</button>
            </div>
        </div>
    </div>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<style>
.tox-tinymce-aux, .tox.tox-silver-sink, .tox-dialog-wrap { z-index: 1000 !important; }
.tox-menu.tox-collection.tox-collection--list { max-height: 320px !important; overflow-y: auto !important; }
.tox.tox-tinymce.tox-fullscreen, body.tox-fullscreen-body .tox.tox-tinymce.tox-fullscreen {
    position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
    width: 100vw !important; height: 100vh !important; z-index: 2147483001 !important; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
<script>
(function () {
    var rooms = @json($rooms->mapWithKeys(fn ($r) => ['r' . $r->id => ['text' => $r->photo_guide_text, 'seconds' => $r->photo_guide_seconds ?? 8]]));
    var urlTpl = @json(route('admin.photo-guide.room', [$property, 0]));
    var sel = document.querySelector('select[name="room_id"]');
    var form = sel.closest('form');
    var fileEl = form.querySelector('input[type=file]');
    var fields = document.getElementById('pg-room-fields');
    var note = document.getElementById('pg-all-note');
    var secs = document.getElementById('pg-seconds');
    var ed = null;

    function apply() {
        var r = rooms['r' + sel.value];
        var row = document.getElementById('pg-secs-row');
        if (row) row.style.display = r ? '' : 'none';
        note.style.display = 'none';
        if (r) secs.value = r.seconds;
        if (ed) ed.setContent('');
    }
    sel.addEventListener('change', apply);

    window.__pgCfg = {
        relative_urls: false,
        remove_script_host: false,
        selector: '#pg-editor',
        plugins: 'lists advlist link code table searchreplace wordcount visualblocks charmap emoticons preview anchor fullscreen nonbreaking',
        toolbar: 'undo redo | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | customlineheight | link table anchor charmap emoticons | searchreplace preview fullscreen | removeformat code | fontfamily fontsize',
        browser_spellcheck: true,
        contextmenu: false,
        valid_styles: {
            '*': 'font-size,font-family,color,background-color,text-align,text-decoration,line-height'
        },
        menubar: false,
        toolbar_mode: 'wrap',
        height: 360,
        ui_mode: 'split',
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
        content_css: false,
        content_style: `
            @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap');
            body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; }
            p { margin: 0; }
        `,
        promotion: false,
        branding: false,
        setup: function(editor) {
            window.__contentEditorInstance = editor;

            function textBeforeNode(node, root) {
                var text = '';
                function walk(n) {
                    if (n === node) return true;
                    if (n.nodeType === 3) {
                        text += n.nodeValue;
                    } else if (n.nodeType === 1) {
                        var tag = n.nodeName;
                        if (tag === 'P' || tag === 'DIV' || tag === 'LI' || tag === 'BR') {
                            text = '';
                        }
                    }
                    for (var i = 0; i < n.childNodes.length; i++) {
                        if (walk(n.childNodes[i])) return true;
                    }
                    return false;
                }
                walk(root);
                return text;
            }

            editor.on('input', function() {
                var sel = editor.selection;
                var rng = sel.getRng();
                var node = rng.startContainer;
                if (node.nodeType !== 3) return;
                var offset = rng.startOffset;
                if (offset === 0) return;
                var text = node.nodeValue;
                var charIndex = offset - 1;
                var ch = text.charAt(charIndex);
                if (!/[a-z]/.test(ch)) return;

                var before = text.substring(0, charIndex);
                var isSentenceStart = /^\s*$/.test(before) || /[.!?]\s+$/.test(before);

                if (isSentenceStart && /^\s*$/.test(before)) {
                    var blockText = textBeforeNode(node, editor.getBody());
                    isSentenceStart = /^\s*$/.test(blockText) || /[.!?]\s*$/.test(blockText);
                }

                if (isSentenceStart) {
                    node.nodeValue = text.substring(0, charIndex) + ch.toUpperCase() + text.substring(offset);
                    rng.setStart(node, offset);
                    rng.setEnd(node, offset);
                    sel.setRng(rng);
                }
            });

            document.querySelectorAll('.token-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    editor.insertContent('<span style="background:#fef9c3;padding:0 3px;border-radius:3px;font-family:monospace;">' + btn.dataset.token + '</span>');
                });
            });

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
                    openMediaPicker('editor');
                }
            });
        }
    };
    tinymce.init(Object.assign({}, window.__pgCfg, { selector: '#pg-editor' })).then(function (eds) { ed = eds[0]; apply(); });

    form.addEventListener('submit', function (e) {
        var r = rooms['r' + sel.value];
        var hasFiles = fileEl && fileEl.files && fileEl.files.length;
        if (!hasFiles) { e.preventDefault(); alert('Choose photos to add.'); return; }
        var n = form.querySelector('input[name=instructions]');
        if (!n) { n = document.createElement('input'); n.type = 'hidden'; n.name = 'instructions'; form.appendChild(n); }
        n.value = ed ? ed.getContent() : '';
        if (!r) return;
        e.preventDefault();
        var fd = new FormData();
        fd.append('_token', form.querySelector('input[name=_token]').value);
        fd.append('photo_guide_text', r.text || '');
        fd.append('photo_guide_seconds', secs.value);
        fetch(urlTpl.replace(/\/0$/, '/' + sel.value), { method: 'POST', body: fd, headers: { 'Accept': 'text/html' } })
            .then(function (res) { if (!res.ok) throw new Error(); form.submit(); })
            .catch(function () { alert('Could not save. Please try again.'); });
    });
})();
</script>

<script>
(function () {
    var modal = document.getElementById('pg-modal'), url = null;
    function closeMenus() { document.querySelectorAll('.pg-menu').forEach(function (m) { m.style.display = 'none'; }); }
    function closeModal() { tinymce.remove('#pg-edit-editor'); modal.style.display = 'none'; }
    function openEdit(u, html) {
        url = u; modal.style.display = 'flex';
        document.getElementById('pg-edit-editor').value = html;
        tinymce.init(Object.assign({}, window.__pgCfg, { selector: '#pg-edit-editor', height: 320 }));
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.pg-menu-btn');
        var menu = b ? b.nextElementSibling : null;
        var wasOpen = menu && menu.style.display === 'block';
        closeMenus();
        if (menu && !wasOpen) {
            var r = b.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.marginTop = '0';
            menu.style.left = 'auto';
            menu.style.top = (r.bottom + 4) + 'px';
            menu.style.right = (window.innerWidth - r.right) + 'px';
            menu.style.display = 'block';
        }
        var ed = e.target.closest('.pg-edit');
        if (ed) openEdit(ed.dataset.url, ed.dataset.text || '');
    });
    document.getElementById('pg-edit-cancel').onclick = closeModal;
    window.addEventListener('scroll', closeMenus, { passive: true });
    document.getElementById('pg-edit-save').onclick = function () {
        var ed = tinymce.get('pg-edit-editor');
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('instructions', ed ? ed.getContent() : '');
        fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'text/html' } })
            .then(function (r) { if (!r.ok) throw new Error(); location.reload(); })
            .catch(function () { alert('Could not save. Please try again.'); });
    };
})();
</script>
</x-admin-layout>
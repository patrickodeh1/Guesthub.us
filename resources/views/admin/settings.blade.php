<x-admin-layout title="Settings">
    <div class="page-header">
        <div>
            <p class="eyebrow">Global settings</p>
            <h1 class="page-title">Brand and system settings</h1>
            <p class="page-subtitle">Control the default guest brand, GPS verification radius, contact information, and fallback intro text.</p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}" class="grid gap-6 xl:grid-cols-[1fr_360px]">
        @csrf @method('put')
        <section class="card card-pad">
            <h2 class="section-title">Guest experience defaults</h2>
            <p class="section-copy">These values are used when a property-specific value is not available.</p>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="field-label">GPS radius meters<input type="number" name="gps_radius_meters" value="{{ old('gps_radius_meters', $settings['gps_radius_meters']) }}" class="input"><span class="field-help">Typical range: 100 to 250 meters for buildings and resorts.</span></label>
                <label class="field-label">Brand color<input type="color" name="brand_color" value="{{ old('brand_color', $settings['brand_color']) }}" class="input h-12"></label>
                <label class="field-label">Contact phone<input name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" class="input"></label>
                <label class="field-label">Contact email<input name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" class="input"></label>
                <label class="field-label">Default incidentals threshold (USD)<input type="number" step="0.01" min="0" name="default_deposit_cap_dollars" value="{{ old('default_deposit_cap_dollars', $settings['default_deposit_cap_dollars']) }}" class="input"><span class="field-help">Used when a property doesn't have its own cap set. Guests are charged parking + incidentals up to this amount.</span></label>
                <label class="field-label">Processing fee (%)<input type="number" step="0.01" min="0" max="100" name="processing_fee_percent" value="{{ old('processing_fee_percent', $settings['processing_fee_percent']) }}" class="input"><span class="field-help">Added on top of the capped parking + incidentals total.</span></label>
                <div class="md:col-span-2">
                    @php
                        $messageFields = [
                            'default_intro' => ['label' => 'Default Welcome Message', 'help' => 'Fallback shown on the pre-check-in welcome step when a booking has no custom welcome message set.', 'rows' => 5],
                            'gps_verify_message' => ['label' => 'GPS Verify Message', 'help' => 'Shown above the map on the location verification step.', 'rows' => 3],
                            'lock_message' => ['label' => 'Smart Lock Message', 'help' => 'Shown on the smart lock step when a property has a lock configured.', 'rows' => 3],
                            'background_check_step_instructions' => ['label' => \App\Models\Setting::getValue('background_check_step_name', 'Background Check'), 'help' => 'Shown to guests on the waiting screen for this step.', 'rows' => 3],
                            'arrival_disclaimer' => ['label' => 'Arrival Disclaimer', 'help' => 'Shown on the day of arrival, before the property address is revealed. Guests must wait for a 20-second timer and type "agree" to continue.', 'rows' => 5],
                            'airbnb_payment_instructions' => ['label' => 'Pay on Booking Platform Instructions', 'help' => 'Shown when the guest chooses to pay on their booking platform instead of by card. Use [[platform]] to insert the platform name (for example, Airbnb or Vrbo).', 'rows' => 3],
                        ];
                    @endphp
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach($messageFields as $key => $field)
                            <button type="button" onclick="switchMessageType('{{ $key }}')" data-message-tab="{{ $key }}" class="message-tab-btn btn-secondary {{ $loop->first ? 'bg-slate-900 text-white' : '' }}">{{ $field['label'] }}</button>
                        @endforeach
                    </div>
                    @foreach($messageFields as $key => $field)
                        <div id="message-panel-{{ $key }}" class="message-panel" style="{{ !$loop->first ? 'position:absolute;left:-9999px;top:-9999px;visibility:hidden;' : 'position:relative;' }}">
                            <label class="field-label">
                                {{ $field['label'] }}
                                <textarea id="{{ str_replace('_', '-', $key) }}-editor" class="message-editor textarea" name="{{ $key }}" rows="{{ $field['rows'] }}">{{ old($key, $settings[$key]) }}</textarea>
                                @if($field['help'])<span class="field-help">{{ $field['help'] }}</span>@endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="card card-pad">
            <x-media-image-field
                name="site_logo"
                label="Logo"
                :value="$settings['site_logo']"
                preview-class="h-32 w-full rounded-xl border border-slate-200 object-contain p-4"
                help="Use a simple square or horizontal logo with good contrast."
            />

            <div class="mt-8">
                <x-media-image-field
                    name="favicon"
                    label="Favicon"
                    :value="$settings['favicon']"
                    preview-class="h-16 w-16 rounded-md border border-slate-200 object-contain p-2"
                    help="Shown in the browser tab. Upload a small square image (ico or png)."
                />
            </div>

            <button class="btn-primary mt-6 w-full">Save settings</button>
        </aside>
    </form>

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

    <script>
    function switchMessageType(key) {
        document.querySelectorAll('.message-panel').forEach(function (panel) {
            var isTarget = panel.id === 'message-panel-' + key;
            panel.style.position = isTarget ? 'relative' : 'absolute';
            panel.style.left = isTarget ? '' : '-9999px';
            panel.style.top = isTarget ? '' : '-9999px';
            panel.style.visibility = isTarget ? 'visible' : 'hidden';
        });
        document.querySelectorAll('.message-tab-btn').forEach(function (btn) {
            if (btn.dataset.messageTab === key) {
                btn.classList.add('bg-slate-900', 'text-white');
            } else {
                btn.classList.remove('bg-slate-900', 'text-white');
            }
        });
        window.dispatchEvent(new Event('resize'));
    }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
    <script>
    tinymce.init({
        relative_urls: false,
        remove_script_host: false,
        selector: '.message-editor',
        plugins: 'lists advlist link code table searchreplace wordcount visualblocks charmap emoticons preview anchor fullscreen nonbreaking',
        toolbar: 'undo redo | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | customlineheight | link insertimage table anchor charmap emoticons | searchreplace preview fullscreen | removeformat code | fontfamily fontsize',
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
        height: 320,
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
                        var node = editor.selection.getNode();
                        var block = editor.dom.getParent(node, editor.dom.isBlock) || node;
                        if (block && block.nodeName !== 'BODY') {
                            currentValue = editor.dom.getStyle(block, 'line-height') || null;
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
        }
    });
    </script>
</x-admin-layout>

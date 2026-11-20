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

    {{-- ══════════════════ INSTRUCTIONS ══════════════════ --}}
    @if (session('success')) <p class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</p> @endif
    @if ($errors->any()) <p class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</p> @endif

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-950">Step-by-Step Instructions</h2>
        <a href="{{ route('admin.instructions.create', ['property_id' => $property->id, 'type' => 'checkin']) }}" id="add-step-link" class="btn-primary text-xs">Add Step</a>
    </div>

    <div class="mb-5 flex gap-2">
        @foreach(['checkin' => 'Check-in', 'checkout' => 'Check-out'] as $t => $label)
            <button type="button" onclick="switchStepType('{{ $t }}')" data-step-tab="{{ $t }}" class="step-tab-btn btn-secondary {{ $t === 'checkin' ? 'bg-slate-900 text-white' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @foreach(['checkin', 'checkout'] as $t)
        <div id="step-panel-{{ $t }}" class="step-panel" style="{{ $t !== 'checkin' ? 'display:none' : '' }}">
            @if($stepsByType[$t]->isEmpty())
                <div class="card card-pad text-center text-slate-500 mb-8">No {{ $t === 'checkin' ? 'check-in' : ($t === 'checkout' ? 'check-out' : 'parking') }} steps yet for {{ $property->name }}. Add your first step.</div>
            @else
                <div id="steps-list-{{ $t }}" class="steps-list grid gap-4 mb-8" data-type="{{ $t }}">
                    @foreach($stepsByType[$t] as $step)
                        <div class="card overflow-hidden" data-id="{{ $step->id }}">
                            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-4">
                                    <span class="drag-handle grid h-8 w-6 shrink-0 cursor-grab place-items-center text-slate-400 hover:text-slate-600" title="Drag to reorder">
                                        <x-icon name="menu" class="h-5 w-5" />
                                    </span>
                                    @if($step->imageUrl())
                                        <img src="{{ $step->imageUrl() }}" loading="lazy" decoding="async" class="h-16 w-24 rounded-lg object-cover shrink-0">
                                    @else
                                        <div class="h-16 w-24 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center text-slate-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-slate-400">Step {{ $loop->iteration }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $step->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $step->active ? 'Active' : 'Inactive' }}</span>
                                        </div>
                                        <p class="mt-1 font-semibold text-slate-950">{{ $step->title }}</p>
                                        @if($step->linked_from_step_id)
                                            <span class="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Linked to {{ data_get($linkSources->get($step->linked_from_step_id), 'property.name', 'another property') }}</span>
                                        @elseif(($copyCounts[$step->id] ?? 0) > 0)
                                            <span class="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Synced to {{ $copyCounts[$step->id] }} {{ \Illuminate\Support\Str::plural('property', $copyCounts[$step->id]) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-wrap shrink-0 gap-2">
                                    @if($step->linked_from_step_id)
                                        <form method="post" action="{{ route('admin.instructions.unlink', $step) }}" onsubmit="return confirm('Unlink this step? It stops sharing changes with the other properties and becomes its own step.')">
                                            @csrf
                                            <button class="btn-secondary text-xs">Unlink</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.instructions.edit', $step) }}" class="btn-secondary text-xs">Edit</a>
                                    @if($copyTargets->count())
                                        <button type="button" class="btn-secondary text-xs" onclick="openStepCopy({{ $step->id }}, @js($step->title))">Copy to...</button>
                                    @endif
                                    <form method="post" action="{{ route('admin.instructions.destroy', $step) }}" onsubmit="return confirm('Delete this step?')">
                                        @csrf @method('delete')
                                        <button class="btn-danger text-xs">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    @if($copyTargets->count())
    <div id="step-copy-modal" style="display:none;position:fixed;inset:0;z-index:900;background:rgba(15,23,42,.5);align-items:center;justify-content:center;padding:16px">
        <form method="POST" id="step-copy-form" style="background:#fff;border-radius:16px;width:100%;max-width:460px;max-height:90vh;overflow:auto;padding:20px">
            @csrf
            <h3 class="text-lg font-semibold text-slate-950">Copy step to other properties</h3>
            <p class="mt-1 text-sm text-slate-600">Step: <strong id="step-copy-title"></strong></p>
            <div class="mt-3 space-y-2 text-sm">
                <label class="flex items-start gap-2"><input type="radio" name="mode" value="once" checked class="mt-1"><span><strong>Copy once</strong>: an independent copy. Editing it later does not change the original.</span></label>
                <label class="flex items-start gap-2"><input type="radio" name="mode" value="sync" class="mt-1"><span><strong>Keep in sync</strong>: the copy follows this step. Editing it on any linked property updates all of them. You can unlink one at any time.</span></label>
            </div>
            <p class="mt-2 text-xs text-slate-500">The copy goes at the same position as this step. Only properties with the same owner are listed.</p>
            <div class="mt-3 grid gap-2">
                @foreach($copyTargets as $t)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <input type="checkbox" name="target_ids[]" value="{{ $t->id }}">
                        <span class="font-semibold text-slate-900">{{ $t->name }}</span>
                    </label>
                @endforeach
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
                <button type="button" class="btn-secondary" onclick="document.getElementById('step-copy-modal').style.display='none'">Cancel</button>
                <button class="btn-primary">Copy</button>
            </div>
        </form>
    </div>
    <script>
    function openStepCopy(id, title) {
        var m = document.getElementById('step-copy-modal');
        document.getElementById('step-copy-form').action = @json(route('admin.instructions.copy', 0)).replace(/\/0\/copy$/, '/' + id + '/copy');
        document.getElementById('step-copy-title').textContent = title;
        document.querySelectorAll('#step-copy-form input[type=checkbox]').forEach(function (c) { c.checked = false; });
        m.style.display = 'flex';
    }
    </script>
    @endif

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
    function switchStepType(type) {
        document.querySelectorAll('.step-panel').forEach(function (panel) {
            panel.style.display = panel.id === 'step-panel-' + type ? '' : 'none';
        });
        document.querySelectorAll('.step-tab-btn').forEach(function (btn) {
            if (btn.dataset.stepTab === type) {
                btn.classList.add('bg-slate-900', 'text-white');
            } else {
                btn.classList.remove('bg-slate-900', 'text-white');
            }
        });
        document.getElementById('add-step-link').href =
            "{{ route('admin.instructions.create', ['property_id' => $property->id]) }}&type=" + type;
    }

    document.querySelectorAll('.steps-list').forEach(function (list) {
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                var ids = Array.from(list.children).map(function (card) { return card.dataset.id; });
                fetch('{{ route("admin.instructions.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ids: ids })
                });
            }
        });
    });
    </script>
</x-admin-layout>

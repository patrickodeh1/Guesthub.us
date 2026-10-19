@once
@if(auth()->user()?->hasAnyRole(['admin', 'owner', 'company']))
<div x-data="scheduleDrawer(@js([
        'optionsUrl' => route('admin.cleaning-jobs.assign-options'),
        'saveUrl' => route('admin.cleaning-jobs.calendar-assign'),
        'tasksUrl' => url('/api/properties'),
        'deleteBase' => url('/manage/sessions'),
        'canDelete' => (bool) auth()->user()?->hasAnyRole(['admin', 'owner']),
        'csrf' => csrf_token(),
    ]))"
    @open-schedule.window="openFrom($event.detail || {})"
    @open-assign.window="openFrom($event.detail || {})"
    @open-assignment.window="openFrom($event.detail || {})">
    <x-drawer title="Schedule cleaning" open="isOpen" on-close="close()" id="schedule-drawer">
        <form class="space-y-5" @submit.prevent="submit()">
            <p x-show="loading" class="text-sm text-slate-500">Loading...</p>
            <div class="space-y-2 rounded-lg bg-slate-50 p-3 text-sm">
                <div x-show="!info.property_locked">
                    <label class="field-label">Property</label>
                    <select class="input mt-1 w-full" x-model.number="form.property_id" @change="refresh(true)" :disabled="loading">
                        <option value="">Select a property</option>
                        <template x-for="p in info.properties" :key="p.id"><option :value="p.id" :disabled="!p.has_owner" x-text="p.name + (p.has_owner ? '' : ' (no owner)')"></option></template>
                    </select>
                </div>
                <p x-show="info.property_locked" class="font-semibold text-slate-900" x-text="info.property_name"></p>
                <p x-show="info.guest" class="text-slate-600">
                    <span x-text="'Checkout cleaning after ' + info.guest"></span>
                    <button type="button" x-show="!isEdit && !info.property_locked" class="ml-2 text-xs font-semibold text-teal-800 underline" @click="info.booking_id = null; info.guest = ''">Not for this guest</button>
                </p>
                <p x-show="!info.guest && form.property_id && !loading" class="text-slate-600">No guest linked to this cleaning.</p>
            </div>
            <div>
                <label class="field-label">Cleaner</label>
                <select class="input mt-1 w-full" x-model.number="form.housekeeper_id" required>
                    <option value="">Select a cleaner</option>
                    <template x-for="c in info.cleaners" :key="c.id"><option :value="c.id" :disabled="!c.has_phone" x-text="c.name + (c.has_phone ? '' : ' (no phone number)')"></option></template>
                </select>
                <p x-show="form.property_id && !loading && info.cleaners.length === 0" class="mt-2 text-sm text-amber-700">No active cleaners are assigned to this property.</p>
                <p x-show="isEdit && info.assignment_status" x-cloak class="mt-2 text-sm">
                    <span class="font-semibold">Status: </span>
                    <span x-show="info.assignment_status === 'pending_confirmation'" class="font-semibold text-amber-700">Pending (waiting for the cleaner's reply)</span>
                    <span x-show="info.assignment_status === 'confirmed'" class="font-semibold text-emerald-700">Accepted</span>
                    <span x-show="info.assignment_status === 'declined'" class="font-semibold text-red-700">Declined. Choose a different cleaner to send a new text.</span>
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="field-label">Date</label><input type="date" class="input mt-1 w-full" x-model="form.date" @change="refresh(false)" required></div>
                <div><label class="field-label">Time</label>
                    <select class="input mt-1 w-full" x-model="form.time">
                        <option value="">Not set</option>
                        <template x-for="o in timeOptions" :key="o.v"><option :value="o.v" :selected="o.v === form.time" x-text="o.l"></option></template>
                    </select></div>
            </div>
            <div x-show="isEdit" x-cloak>
                <label class="field-label">Status</label>
                <select class="input mt-1 w-full" x-model="form.status">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div x-show="form.property_id && (tasksLoading || tasks.length > 0)" x-cloak>
                <label class="field-label">Occasional tasks</label>
                <p class="text-xs text-slate-500">Extra tasks for this cleaning only.</p>
                <p x-show="tasksLoading" class="mt-2 text-xs text-slate-500">Loading tasks...</p>
                <div x-show="!tasksLoading && tasks.length > 0" class="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200">
                    <template x-for="t in tasks" :key="t.id">
                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm">
                            <input type="checkbox" :value="String(t.id)" x-model="form.tasks" class="h-4 w-4 rounded border-slate-300">
                            <span class="flex-1" x-text="t.name"></span>
                            <span class="text-xs text-slate-500" x-text="t.last_done ? 'Last done ' + t.last_done : 'Never done'"></span>
                        </label>
                    </template>
                </div>
            </div>
            <p class="text-xs text-slate-500">The cleaner gets a text and is assigned once they reply YES.</p>
            <div x-show="conflictsActive" x-cloak role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">This assignment has conflicts:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5"><template x-for="c in conflicts" :key="c.message"><li x-text="c.message"></li></template></ul>
                <p class="mt-2 text-amber-800">Review them, then choose Assign anyway or change the cleaner, date or time.</p>
            </div>
            <p x-show="error" x-text="error" role="alert" class="text-sm font-medium text-red-700"></p>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" @click="close()" :disabled="saving">Cancel</button>
                <button type="button" class="btn-primary" x-show="conflictsActive" @click="submit(true)" :disabled="saving"><span x-text="saving ? 'Saving...' : 'Assign anyway'"></span></button>
                <button type="submit" class="btn-primary" x-show="!conflictsActive" :disabled="saving || loading || !form.housekeeper_id || !form.property_id || !form.date"><span x-text="saving ? 'Saving...' : (isEdit ? 'Save changes' : 'Save assignment')"></span></button>
            </div>
        </form>
        <form x-show="isEdit && canDelete" x-cloak method="post" :action="deleteAction" onsubmit="return confirm('Delete this cleaning job?')" class="mt-4 border-t border-slate-100 pt-4 text-right">
            @csrf
            @method('DELETE')
            <input type="hidden" name="_return_to" :value="window.location.href">
            <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Delete this cleaning</button>
        </form>
    </x-drawer>
</div>
<script>
/* custom select menus for drawers: native popups close instantly / pick the wrong option on wide screens */
(function () {
  if (window.__cselInit) return; window.__cselInit = true;
  var st = document.createElement('style');
  st.textContent = '.csel-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;cursor:pointer}'
    + '.csel-btn[disabled]{opacity:.6;cursor:not-allowed}'
    + '.csel-txt{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
    + '.csel-menu{position:fixed;z-index:10000;max-height:280px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:4px 0;font-size:14px}'
    + '.csel-grp{padding:6px 12px 3px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b}'
    + '.csel-opt{padding:7px 12px;cursor:pointer;color:#1e293b}'
    + '.csel-opt.act{background:#eff6ff;color:#1d4ed8}'
    + '.csel-opt.sel{font-weight:700}'
    + '.csel-opt.dis{color:#94a3b8;cursor:not-allowed}';
  document.head.appendChild(st);

  var list = [], menu = null, cur = null, act = -1, items = [];

  function label(sel) { var o = sel.options[sel.selectedIndex]; return o ? o.textContent.trim() : ''; }
  function sync(e) {
    if (!e.sel.isConnected) { e.btn.remove(); return false; }
    var t = label(e.sel) || '\u00A0';
    if (e.txt.textContent !== t) e.txt.textContent = t;
    if (e.btn.disabled !== e.sel.disabled) e.btn.disabled = e.sel.disabled;
    return true;
  }
  function close() {
    if (menu) { menu.remove(); menu = null; }
    if (cur) { cur.btn.setAttribute('aria-expanded', 'false'); cur = null; }
    items = []; act = -1;
  }
  function setAct(i) {
    if (act >= 0 && items[act]) items[act].classList.remove('act');
    act = i;
    var it = items[i]; if (!it || !menu) return;
    it.classList.add('act');
    var top = it.offsetTop, bot = top + it.offsetHeight;
    if (top < menu.scrollTop) menu.scrollTop = top;
    else if (bot > menu.scrollTop + menu.clientHeight) menu.scrollTop = bot - menu.clientHeight;
  }
  function move(d) {
    var n = items.length, i = act; if (!n) return;
    for (var k = 0; k < n; k++) { i = (i + d + n) % n; if (!items[i].__o.disabled) break; }
    setAct(i);
  }
  function choose(e, o) {
    e.sel.value = o.value;
    e.sel.dispatchEvent(new Event('input', { bubbles: true }));
    e.sel.dispatchEvent(new Event('change', { bubbles: true }));
    sync(e); close(); e.btn.focus();
  }
  function open(e) {
    close(); cur = e; e.btn.setAttribute('aria-expanded', 'true');
    menu = document.createElement('div'); menu.className = 'csel-menu'; menu.setAttribute('role', 'listbox');
    function add(o) {
      var d = document.createElement('div');
      d.className = 'csel-opt' + (o.selected ? ' sel' : '') + (o.disabled ? ' dis' : '');
      d.textContent = o.textContent.trim() || '\u00A0';
      d.setAttribute('role', 'option'); d.__o = o;
      d.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
      d.addEventListener('mouseenter', function () { if (!o.disabled) setAct(items.indexOf(d)); });
      d.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); if (!o.disabled) choose(e, o); });
      menu.appendChild(d); items.push(d);
    }
    Array.prototype.forEach.call(e.sel.children, function (n) {
      if (n.tagName === 'OPTGROUP') {
        var g = document.createElement('div'); g.className = 'csel-grp'; g.textContent = n.label; menu.appendChild(g);
        Array.prototype.forEach.call(n.children, function (o) { if (o.tagName === 'OPTION') add(o); });
      } else if (n.tagName === 'OPTION') { add(n); }
    });
    document.body.appendChild(menu);
    var r = e.btn.getBoundingClientRect();
    menu.style.width = Math.max(r.width, 160) + 'px';
    var mh = menu.offsetHeight, below = window.innerHeight - r.bottom;
    var top = (below >= mh + 8 || below >= r.top) ? r.bottom + 4 : r.top - mh - 4;
    menu.style.top = Math.max(8, top) + 'px';
    menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - menu.offsetWidth - 8)) + 'px';
    var si = -1; items.forEach(function (d, i) { if (d.__o.selected) si = i; });
    if (si >= 0) {
      setAct(si);
      menu.scrollTop = Math.max(0, items[si].offsetTop - menu.clientHeight / 2 + items[si].offsetHeight / 2);
    }
  }
  function enhance(sel) {
    if (sel.__csel || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native')) return;
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = (sel.className || '') + ' csel-btn';
    btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false');
    var txt = document.createElement('span'); txt.className = 'csel-txt';
    var car = document.createElement('span'); car.textContent = '\u25BE'; car.setAttribute('aria-hidden', 'true');
    btn.appendChild(txt); btn.appendChild(car);
    sel.parentNode.insertBefore(btn, sel);
    sel.style.cssText += ';position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;';
    sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');
    var e = { sel: sel, btn: btn, txt: txt }; sel.__csel = e; list.push(e);
    btn.addEventListener('click', function (ev) { ev.preventDefault(); if (cur === e) close(); else open(e); });
    sel.addEventListener('change', function () { sync(e); });
    sync(e);
  }
  function scan() { Array.prototype.forEach.call(document.querySelectorAll('[role="dialog"] select'), enhance); }

  var t = null;
  new MutationObserver(function () { clearTimeout(t); t = setTimeout(scan, 50); }).observe(document.body, { childList: true, subtree: true });
  setInterval(function () { list = list.filter(sync); }, 200);
  scan();

  document.addEventListener('mousedown', function (ev) {
    if (!menu) return;
    if (menu.contains(ev.target) || (cur && cur.btn.contains(ev.target))) return;
    close();
    var eat = function (c) { c.stopPropagation(); c.preventDefault(); };
    document.addEventListener('click', eat, { capture: true, once: true });
    setTimeout(function () { document.removeEventListener('click', eat, true); }, 500);
  }, true);
  document.addEventListener('keydown', function (ev) {
    if (!menu) return;
    var k = ev.key;
    if (k === 'Escape') { ev.preventDefault(); ev.stopPropagation(); var b = cur && cur.btn; close(); if (b) b.focus(); }
    else if (k === 'ArrowDown') { ev.preventDefault(); move(1); }
    else if (k === 'ArrowUp') { ev.preventDefault(); move(-1); }
    else if (k === 'Enter') { if (act >= 0 && items[act] && !items[act].__o.disabled) { ev.preventDefault(); ev.stopPropagation(); choose(cur, items[act].__o); } }
    else if (k === 'Tab') { close(); }
  }, true);
  window.addEventListener('scroll', function (ev) { if (menu && !menu.contains(ev.target)) close(); }, true);
  window.addEventListener('resize', close);
})();
</script>
<script>
window.scheduleDrawer = function (cfg) {
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' };
    const blank = () => ({ properties: [], property_locked: false, property_name: '', guest: '', booking_id: null, cleaners: [], assignment_status: null });
    return {
        isOpen: false, loading: false, saving: false, error: '', conflicts: [], conflictKey: '',
        info: blank(), tasks: [], tasksLoading: false, canDelete: cfg.canDelete,
        form: { session_id: null, property_id: '', housekeeper_id: '', date: '', time: '', status: 'pending', tasks: [] },
        get isEdit() { return !!this.form.session_id; },
        get timeOptions() {
            const o = [];
            for (let m = 0; m < 1440; m += 30) {
                const h = Math.floor(m / 60), mi = m % 60;
                o.push({ v: String(h).padStart(2, '0') + ':' + String(mi).padStart(2, '0'), l: ((h % 12) || 12) + ':' + String(mi).padStart(2, '0') + ' ' + (h < 12 ? 'AM' : 'PM') });
            }
            const t = (this.form.time || '').slice(0, 5);
            if (t && !o.some(x => x.v === t)) { o.push({ v: t, l: t + ' (current)' }); o.sort((a, b) => a.v < b.v ? -1 : 1); }
            return o;
        },
        get deleteAction() { return cfg.deleteBase + '/' + this.form.session_id; },
        formKey() { return JSON.stringify([this.form.property_id, this.form.date, this.form.housekeeper_id, this.form.time || '']); },
        get conflictsActive() { return this.conflicts.length > 0 && this.conflictKey === this.formKey(); },
        init() {
            const p = new URLSearchParams(window.location.search);
            if (p.get('assign') && window.location.pathname.indexOf('/manage/sessions') === 0) {
                const sid = p.get('assign');
                try { p.delete('assign'); const q = p.toString(); history.replaceState(null, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash); } catch (e) {}
                setTimeout(() => this.openFrom({ session_id: sid }), 80);
            }
            if (p.get('new') === '1' && window.location.pathname.indexOf('/manage/sessions') === 0) {
                setTimeout(() => this.openFrom({ property_id: p.get('new_property'), date: p.get('new_date') }), 80);
            }
        },
        close() { if (!this.saving) this.isOpen = false; },
        async fetchOptions(params) {
            const qs = new URLSearchParams();
            Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') qs.set(k, v); });
            const res = await fetch(cfg.optionsUrl + '?' + qs.toString(), { headers, credentials: 'same-origin' });
            const r = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(r.message || 'Could not load the scheduling options.');
            return r;
        },
        async openFrom(d) {
            this.isOpen = true; this.loading = true; this.error = ''; this.conflicts = [];
            this.info = blank(); this.tasks = [];
            this.form = { session_id: null, property_id: '', housekeeper_id: '', date: '', time: '', status: 'pending', tasks: [] };
            try {
                const r = await this.fetchOptions({ session_id: d.session_id || d.id, booking_id: d.booking_id, property_id: d.property_id, date: d.date });
                this.info = { properties: r.properties || [], property_locked: !!r.property_locked, property_name: r.property_name || '', guest: r.guest || '', booking_id: r.booking_id || null, cleaners: r.cleaners || [], assignment_status: r.assignment_status || null };
                this.form = {
                    session_id: r.session_id || null,
                    property_id: r.property_id || '',
                    housekeeper_id: r.housekeeper_id || d.housekeeper_id || '',
                    date: r.date || d.date || '',
                    time: d.time || d.scheduled_time || r.time || '',
                    status: r.status || 'pending',
                    tasks: (r.sporadic_tasks || []).map(String),
                };
                if (this.form.property_id) this.loadTasks();
            } catch (e) { this.error = e.message; }
            finally { this.loading = false; }
        },
        async loadTasks() {
            if (!this.form.property_id) { this.tasks = []; return; }
            this.tasksLoading = true;
            try {
                const res = await fetch(cfg.tasksUrl + '/' + this.form.property_id + '/sporadic-tasks', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                this.tasks = res.ok ? await res.json() : [];
            } catch (e) { this.tasks = []; }
            this.tasksLoading = false;
        },
        async refresh(propertyChanged) {
            if (!this.form.property_id) { this.info.cleaners = []; this.info.guest = ''; this.info.booking_id = null; this.tasks = []; return; }
            if (propertyChanged) { this.form.housekeeper_id = ''; this.form.tasks = []; this.loadTasks(); }
            try {
                const r = await this.fetchOptions({ property_id: this.form.property_id, date: this.form.date });
                this.info.cleaners = r.cleaners || [];
                this.info.property_name = r.property_name || this.info.property_name;
                if (!this.isEdit) { this.info.guest = r.guest || ''; this.info.booking_id = r.booking_id || null; }
                if (this.form.housekeeper_id && !this.info.cleaners.some(c => Number(c.id) === Number(this.form.housekeeper_id))) this.form.housekeeper_id = '';
            } catch (e) { this.error = e.message; }
        },
        async submit(confirm = false) {
            this.saving = true; this.error = '';
            if (!confirm) this.conflicts = [];
            try {
                const res = await fetch(cfg.saveUrl, { method: 'POST', headers, credentials: 'same-origin', body: JSON.stringify({
                    property_id: this.form.property_id, scheduled_date: this.form.date, session_id: this.form.session_id,
                    booking_id: this.info.booking_id || null, housekeeper_id: this.form.housekeeper_id,
                    scheduled_time: this.form.time || null, status: this.isEdit ? this.form.status : null,
                    sporadic_tasks: this.form.tasks, confirm_conflicts: confirm }) });
                const r = await res.json().catch(() => ({}));
                if (res.status === 409 && r.needs_confirmation) { this.conflicts = r.conflicts || []; this.conflictKey = this.formKey(); return; }
                if (!res.ok) { this.error = (r.errors && Object.values(r.errors).flat()[0]) || r.message || 'Could not save the assignment.'; return; }
                this.isOpen = false;
                await this.afterSave();
            } catch (e) { this.error = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },
        async afterSave() {
            const root = document.getElementById('calendar-root');
            if (root) {
                try {
                    const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    const fresh = doc.getElementById('calendar-root');
                    if (fresh) { root.innerHTML = fresh.innerHTML; return; }
                } catch (e) {}
            }
            window.location.reload();
        },
    };
};
</script>
@endif
@endonce

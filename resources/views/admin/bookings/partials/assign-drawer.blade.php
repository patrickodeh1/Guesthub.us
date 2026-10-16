@if(auth()->user()?->hasAnyRole(['admin', 'owner', 'company']))
<div x-data="bookingAssign(@js(['url' => route('admin.cleaning-jobs.calendar-assign'), 'optionsUrl' => route('admin.cleaning-jobs.assign-options'), 'confirmUrl' => route('admin.cleaning-jobs.confirm-manually'), 'csrf' => csrf_token()]))" @open-assign.window="openFor($event.detail)">
    <x-drawer title="Assign cleaner" open="assignOpen" on-close="closeAssign()" id="booking-assign-drawer">
        <form class="space-y-5" @submit.prevent="submit()">
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p class="font-semibold text-slate-900" x-text="info.property_name || 'Loading...'"></p>
                <p class="text-slate-600" x-text="info.guest ? 'Checkout cleaning after ' + info.guest : ''"></p>
            </div>
            <div>
                <label class="field-label">Cleaner</label>
                <select class="input mt-1 w-full" x-model.number="form.housekeeper_id" required>
                    <option value="">Select a cleaner</option>
                    <template x-for="c in info.cleaners" :key="c.id"><option :value="c.id" :disabled="!c.has_phone" x-text="c.name + (c.has_phone ? '' : ' (no phone number)')"></option></template>
                </select>
                <p x-show="loaded && info.cleaners.length === 0" class="mt-2 text-sm text-amber-700">No active cleaners are assigned to this property.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="field-label">Date</label><input type="date" class="input mt-1 w-full" x-model="form.scheduled_date" required></div>
                <div><label class="field-label">Time</label><input type="time" class="input mt-1 w-full" x-model="form.scheduled_time"></div>
            </div>
            <p class="text-xs text-slate-500">The cleaner gets a text and is assigned once they reply YES.</p>
            <div x-show="conflictsActive" x-cloak role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">This assignment has conflicts:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5"><template x-for="c in conflicts" :key="c.message"><li x-text="c.message"></li></template></ul>
            </div>
            <p x-show="error" x-text="error" role="alert" class="text-sm font-medium text-red-700"></p>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" @click="closeAssign()" :disabled="saving">Cancel</button>
                <button type="button" class="btn-primary" x-show="conflictsActive" @click="submit(true)" :disabled="saving">Assign anyway</button>
                <button type="submit" class="btn-primary" x-show="!conflictsActive" :disabled="saving || !loaded || !form.housekeeper_id"><span x-text="saving ? 'Saving...' : 'Save assignment'"></span></button>
            </div>
        </form>
    </x-drawer>
</div>
<script>
window.bookingAssign = function (cfg) {
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' };
    return {
        bookingId: null, assignOpen: false, saving: false, loaded: false, error: '', conflictsActive: false, conflicts: [],
        info: { property_id: null, property_name: '', guest: '', session_id: null, cleaners: [] },
        form: { housekeeper_id: '', scheduled_date: '', scheduled_time: '' },
        closeAssign() { if (!this.saving) this.assignOpen = false; },
        async openFor(detail) {
            this.bookingId = detail.booking_id; this.assignOpen = true; this.loaded = false; this.error = ''; this.conflictsActive = false;
            this.info = { property_id: null, property_name: '', guest: '', session_id: null, cleaners: [] };
            try {
                const res = await fetch(cfg.optionsUrl + '?booking_id=' + encodeURIComponent(detail.booking_id), { headers });
                const r = await res.json();
                if (!res.ok) { this.error = r.message || 'Could not load this booking.'; return; }
                this.info = r;
                this.form = { housekeeper_id: r.housekeeper_id || '', scheduled_date: r.date, scheduled_time: r.time || '' };
                this.loaded = true;
            } catch (e) { this.error = 'Network error. Please try again.'; }
        },
        async submit(confirm = false) {
            this.saving = true; this.error = '';
            try {
                const res = await fetch(cfg.url, { method: 'POST', headers, body: JSON.stringify({
                    property_id: this.info.property_id, booking_id: this.bookingId, session_id: this.info.session_id,
                    housekeeper_id: this.form.housekeeper_id, scheduled_date: this.form.scheduled_date,
                    scheduled_time: this.form.scheduled_time || null, confirm_conflicts: confirm }) });
                const r = await res.json().catch(() => ({}));
                if (res.status === 409 && r.needs_confirmation) { this.conflicts = r.conflicts || []; this.conflictsActive = true; return; }
                if (!res.ok) { this.conflictsActive = false; this.error = (r.errors && Object.values(r.errors)[0][0]) || r.message || 'Could not assign the cleaner.'; return; }
                window.location.reload();
            } catch (e) { this.error = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },
    };
};
</script>
@endif

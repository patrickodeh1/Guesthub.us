<x-admin-layout title="Notifications">
@php
    $groups = [
        'Registration and ID' => ['registration_received','registration_reminder','photo_id_uploaded','photo_id_declined','photo_id_expired','photo_id_resubmitted','background_check_complete','fully_approved','deposit_paid'],
        'Check-in' => ['time_to_check_in','arrival_soon','checkin_ready','checkin_completed','early_checkin_granted','checkin_time_approved','checkin_time_denied','reservation_updated','cleaning_complete'],
        'Check-out' => ['checkout_reminder','checkout_soon','checkout_overdue','checkout_completed','late_checkout_granted','checkout_time_approved','checkout_time_denied','post_checkout_balance_due'],
        'Bookings (PMS)' => ['pms_booking_received','pms_booking_cancelled','pms_booking_updated'],
    ];
    $left = array_values(array_diff(array_keys($alertEvents), collect($groups)->flatten()->all()));
    if ($left) { $groups['Other'] = $left; }
    $allTokens = ['guest_name','guest_first_name','property_name','property_guest_name','property_internal_name','check_in_date','check_in_time','check_out_date','check_out_time','parking_status','step_name','guest_link','admin_link','id_side','decline_reason','pending_text','result','requested_time'];
    $globals = [
        'notify_cleaning_started_global' => 'Cleaning started',
        'notify_cleaning_finished_global' => 'Cleaning finished',
        'notify_photo_started_global' => 'Photo started',
        'notify_task_notes_global' => 'Task notes',
        'notify_assignments' => 'Assignments',
        'notify_session_started' => 'Session started',
        'notify_session_completed' => 'Session completed',
    ];
    $roleName = fn ($r) => $r === 'guest' ? 'Guest' : ($r === 'contact' ? 'Contact desk' : ucfirst($r));
    $recipients = array_merge(['guest', 'contact'], $staffRoles);
@endphp
    <div class="page-header">
        <div>
            <p class="eyebrow">Global settings</p>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Global settings control guest lifecycle messages and Cleaning Ops defaults. To configure cleaning event switches and recipients for a specific property, use its Notifications page from <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('admin.properties.index') }}">Properties</a>.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.settings.notifications.update') }}" class="card ns" id="ns" data-view="list">
        @csrf @method('put')
        @if($errors->any())<div class="ns-err">Settings were NOT saved: {{ $errors->first() }}</div>@endif

        <div class="ns-top">
            <input type="text" id="nsQ" class="ns-q" placeholder="Search messages" autocomplete="off">
            <div class="ns-pills">
                <button type="button" class="pill on" data-f="all">All</button>
                <button type="button" class="pill" data-f="active">Active</button>
                <button type="button" class="pill" data-f="off">Off</button>
                <button type="button" class="pill" data-f="cust">Customized</button>
            </div>
        </div>

        <div class="ns-body">
            <div class="ns-list">
                @foreach($groups as $gname => $keys)
                    <div class="ns-g">
                        <div class="ns-gh">{{ $gname }}</div>
                        @foreach($keys as $key)
                            @continue(! isset($alertEvents[$key]))
                            <div class="ns-row" data-id="a_{{ $key }}" data-name="{{ mb_strtolower($alertLabels[$key]) }}">
                                <span class="nm">{{ $alertLabels[$key] }}<span class="sub"></span></span>
                                <i class="dot" title="Customized wording" hidden></i>
                                <button type="button" class="sw" role="switch" aria-checked="false" aria-label="Send {{ $alertLabels[$key] }}" data-id="a_{{ $key }}"></button>
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <div class="ns-g">
                    <div class="ns-gh">Cleaning and staff</div>
                    <div class="ns-row" data-id="g_globals" data-name="global cleaning switches">
                        <span class="nm">Global cleaning switches<span class="sub"></span></span>
                        <i class="dot" hidden></i>
                    </div>
                    @foreach($cleaningEvents as $ckey => $cmeta)
                        <div class="ns-row" data-id="c_{{ $ckey }}" data-name="{{ mb_strtolower($cmeta['label']) }}">
                            <span class="nm">{{ $cmeta['label'] }}<span class="sub"></span></span>
                            <i class="dot" title="Customized wording" hidden></i>
                            <button type="button" class="sw" role="switch" aria-checked="false" aria-label="Send {{ $cmeta['label'] }}" data-id="c_{{ $ckey }}"></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ns-ed">
                <button type="button" class="ns-back">&larr; All messages</button>

                @foreach($alertEvents as $key => $meta)
                    @php
                        $row = $alertConfig[$key];
                        preg_match_all('/\{(\w+)\}/', $meta['default_guest_message'].' '.$meta['default_staff_message'].' '.($row['guest_message'] ?? '').' '.($row['staff_message'] ?? ''), $mm);
                        $used = array_values(array_unique($mm[1]));
                        $other = array_values(array_diff($allTokens, $used));
                    @endphp
                    <section class="ns-e" data-id="a_{{ $key }}" data-kind="alert" hidden>
                        <div class="ns-eh">
                            <h2>{{ $alertLabels[$key] }}</h2><span class="cu" hidden>customized</span>
                            <button type="button" class="sw" role="switch" aria-checked="false" aria-label="Send {{ $alertLabels[$key] }}" data-id="a_{{ $key }}"></button>
                        </div>
                        <label class="lb">Message to guest</label>
                        <textarea name="alerts[{{ $key }}][guest_message]" rows="3" class="input ta" data-def="{{ $meta['default_guest_message'] }}">{{ old("alerts.$key.guest_message", $row['guest_message']) }}</textarea>
                        <div class="pv"></div>
                        <label class="lb">Message to staff</label>
                        <textarea name="alerts[{{ $key }}][staff_message]" rows="2" class="input ta" data-def="{{ $meta['default_staff_message'] }}">{{ old("alerts.$key.staff_message", $row['staff_message']) }}</textarea>
                        <div class="pv"></div>
                        <div class="tools">
                            <select class="tokpick"><option value="">Insert variable…</option>
                                @if($used)<optgroup label="Used in this message">@foreach($used as $t)<option value="{{ '{'.$t.'}' }}">{{ '{'.$t.'}' }}</option>@endforeach</optgroup>@endif
                                <optgroup label="Other variables">@foreach($other as $t)<option value="{{ '{'.$t.'}' }}">{{ '{'.$t.'}' }}</option>@endforeach</optgroup>
                            </select>
                            <button type="button" class="lnk" data-reset data-guest="{{ $meta['default_guest_message'] }}" data-staff="{{ $meta['default_staff_message'] }}">Reset wording to default</button>
                        </div>
                        <label class="lb">Who gets it</label>
                        <div class="rc">
                            @foreach($recipients as $r)
                                <div class="rr" data-role="{{ $r }}">
                                    <span>{{ $roleName($r) }}</span>
                                    <span class="chips">
                                        <label class="chip"><input type="checkbox" name="alerts[{{ $key }}][{{ $r }}_sms]" value="1" {{ old("alerts.$key.{$r}_sms", $row["{$r}_sms"]) ? 'checked' : '' }}><span>Text</span></label>
                                        <label class="chip"><input type="checkbox" name="alerts[{{ $key }}][{{ $r }}_email]" value="1" {{ old("alerts.$key.{$r}_email", $row["{$r}_email"]) ? 'checked' : '' }}><span>Email</span></label>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="lnk addr">+ Add recipient</button>
                        <p class="hint">Contact desk uses the phone and email under Settings &gt; General. Owner, Manager, Staff, and Viewer notify every user with that role, at their own phone or email.</p>
                        <input type="hidden" name="alerts[{{ $key }}][_complete]" value="1">
                    </section>
                @endforeach

                <section class="ns-e" data-id="g_globals" data-kind="globals" hidden>
                    <div class="ns-eh"><h2>Global cleaning switches</h2></div>
                    <p class="hint">These switches control live notifications used by Cleaning Ops.</p>
                    <div class="gl">
                        @foreach($globals as $gk => $glabel)
                            <label class="gl-i">
                                <input type="hidden" name="{{ $gk }}" value="0">
                                <input type="checkbox" name="{{ $gk }}" value="1" @checked(\App\Models\Setting::get($gk, true))>
                                {{ $glabel }}
                            </label>
                        @endforeach
                    </div>
                </section>

                @foreach($cleaningEvents as $ckey => $cmeta)
                    @php
                        $crow = $cleaningConfig[$ckey];
                        $cother = array_values(array_diff($cmeta['tokens'], []));
                    @endphp
                    <section class="ns-e" data-id="c_{{ $ckey }}" data-kind="clean" data-aud="{{ $cmeta['audience'] }}" hidden>
                        <div class="ns-eh">
                            <h2>{{ $cmeta['label'] }}</h2><span class="cu" hidden>customized</span>
                            <button type="button" class="sw" role="switch" aria-checked="false" aria-label="Send {{ $cmeta['label'] }}" data-id="c_{{ $ckey }}"></button>
                        </div>
                        <p class="hint">{{ $cmeta['audience'] }}. Turn the switch off to stop this text completely. Texts also need SMS turned on for the server.</p>
                        <input type="checkbox" class="en" name="cleaning[{{ $ckey }}][enabled]" value="1" {{ old("cleaning.$ckey.enabled", $crow['enabled']) ? 'checked' : '' }} hidden>
                        <label class="lb">Message</label>
                        <textarea name="cleaning[{{ $ckey }}][message]" rows="3" class="input ta" data-def="{{ $cmeta['default'] }}">{{ old("cleaning.$ckey.message", $crow['message']) }}</textarea>
                        <div class="pv"></div>
                        <div class="tools">
                            @if($cother)
                                <select class="tokpick"><option value="">Insert variable…</option>@foreach($cother as $t)<option value="{{ '{'.$t.'}' }}">{{ '{'.$t.'}' }}</option>@endforeach</select>
                            @endif
                            <button type="button" class="lnk" data-reset-clean data-default="{{ $cmeta['default'] }}">Reset wording to default</button>
                        </div>
                        <input type="hidden" name="cleaning[{{ $ckey }}][_complete]" value="1">
                    </section>
                @endforeach
            </div>
        </div>

        <div class="ns-save">
            <span class="ns-cnt">No changes</span>
            <span class="ns-btns">
                <button type="button" class="btn-secondary" id="nsDis">Discard</button>
                <button class="btn-primary">Save notification settings</button>
            </span>
        </div>
    </form>

@verbatim
<style>
.ns{padding:0;overflow:visible;font-size:14px;color:#1e293b}
.ns [hidden]{display:none!important}
.ns-err{margin:12px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;padding:10px 12px;font-weight:600}
.ns-top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 14px;border-bottom:1px solid #e2e8f0}
.ns-q{flex:1;min-width:160px;border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:14px;background:#fff}
.ns-pills{display:flex;gap:6px;flex-wrap:wrap}
.ns .pill{font-size:12px;padding:4px 11px;border-radius:99px;border:1px solid #cbd5e1;background:#fff;color:#475569;cursor:pointer}
.ns .pill.on{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8;font-weight:600}
.ns-body{display:grid;grid-template-columns:290px minmax(0,1fr);min-height:520px}
.ns-list{border-right:1px solid #e2e8f0;background:#f8fafc;padding:6px 0;max-height:78vh;overflow-y:auto}
.ns-gh{font-size:12px;font-weight:600;color:#64748b;padding:12px 14px 4px}
.ns-row{display:flex;align-items:center;gap:8px;padding:8px 14px;cursor:pointer;border-left:3px solid transparent}
.ns-row:hover{background:#fff}
.ns-row.sel{background:#fff;border-left-color:var(--theme-primary,#2563eb)}
.ns-row .nm{flex:1;min-width:0;line-height:1.25}
.ns-row .sub{display:block;font-size:12px;color:#64748b;margin-top:1px}
.ns-row.off .nm{color:#94a3b8}
.dot{width:7px;height:7px;border-radius:50%;background:#d97706;flex:none}
.sw{width:32px;height:18px;border-radius:99px;background:#cbd5e1;position:relative;flex:none;border:0;padding:0;cursor:pointer}
.sw::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:left .12s}
.sw[aria-checked="true"]{background:var(--theme-primary,#2563eb)}
.sw[aria-checked="true"]::after{left:16px}
.ns-ed{padding:16px 20px;min-width:0}
.ns-back{display:none;border:0;background:none;color:var(--theme-primary,#2563eb);font-weight:600;padding:0 0 10px;cursor:pointer}
.ns-eh{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.ns-eh h2{font-size:17px;font-weight:700;flex:1;margin:0}
.cu{font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;border-radius:99px;padding:2px 8px}
.lb{display:block;font-size:12px;font-weight:600;color:#475569;margin:14px 0 4px}
.ns .ta{width:100%;box-sizing:border-box}
.pv{margin-top:5px;background:#f1f5f9;border-radius:8px;padding:7px 10px;font-size:12px;color:#475569;line-height:1.5;white-space:pre-line}
.tools{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px}
.tokpick{font-size:13px;border:1px solid #cbd5e1;border-radius:8px;padding:5px 8px;background:#fff;max-width:100%}
.lnk{border:0;background:none;padding:0;font-size:12px;font-weight:600;color:#475569;cursor:pointer;text-decoration:underline}
.addr{display:block;margin-top:8px;color:var(--theme-primary,#2563eb)}
.hint{font-size:12px;color:#64748b;margin:6px 0 0}
.rr{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;gap:10px}
.rr span:first-child{font-weight:600;color:#334155}
.ns-e:not(.all) .rr.rx{display:none}
.ns-e.all .addr{display:none}
.chips{display:flex;gap:6px}
.chip{position:relative;cursor:pointer}
.chip input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer}
.chip span{display:block;font-size:12px;font-weight:600;padding:5px 12px;border-radius:8px;border:1px solid #cbd5e1;color:#94a3b8;background:#fff}
.chip input:checked+span{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
.chip input:focus-visible+span{outline:2px solid var(--theme-primary,#2563eb);outline-offset:1px}
.gl{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:14px}
.gl-i{display:flex;align-items:center;gap:10px;font-weight:600}
.ns-save{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 12px 12px}
.ns-cnt{font-size:13px;color:#64748b}
.ns-cnt.dirty{color:#92400e;font-weight:600}
.ns-btns{display:flex;gap:8px}
@media (max-width:767px){
.ns-body{display:block;min-height:0}
.ns-list{border-right:0;max-height:none}
.ns[data-view="list"] .ns-ed{display:none}
.ns[data-view="editor"] .ns-list{display:none}
.ns-back{display:inline-block}
.ns-ed{padding:14px}
}
</style>
<script>
(function () {
  var f = document.getElementById('ns'); if (!f) return;
  var S = {guest_name:'Maya Rodriguez',guest_first_name:'Maya',property_name:'Lakeview Cottage',property_guest_name:'Lakeview Cottage',property_internal_name:'Lakeview 2B',check_in_date:'Fri, Oct 24',check_in_time:'4:00 PM',check_out_date:'Mon, Oct 27',check_out_time:'11:00 AM',parking_status:'Approved',step_name:'Background Check',guest_link:'guesthub.us/g/abc123',admin_link:'guesthub.us/admin/b/42',id_side:'front',decline_reason:'photo is blurry',pending_text:'your ID is still needed.',result:'approved',requested_time:'2:00 PM',cleaner:'Ana',when:'Fri, Oct 24 at 10:00 AM',old_when:'Fri, Oct 24 at 9:00 AM',lead:'1 hour',time:'10:00 AM',guest:'Maya Rodriguez',job_id:'1042'};
  var $ = function (s, r) { return (r || f).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || f).querySelectorAll(s)); };
  var eds = {}, rows = {}, snap = {}, base = {}, dirty = {}, state = 'all', q = '';
  $$('.ns-e').forEach(function (e) { eds[e.dataset.id] = e; });
  $$('.ns-row').forEach(function (r) { rows[r.dataset.id] = r; });

  function render(v) { return v.replace(/\{(\w+)\}/g, function (m, k) { return k in S ? S[k] : m; }); }
  function cb(e) { return $$('.rc input', e); }
  function isOn(e) {
    var k = e.dataset.kind;
    if (k === 'alert') return cb(e).some(function (i) { return i.checked; });
    if (k === 'clean') return $('.en', e).checked;
    return true;
  }
  function setOn(e, on) {
    var k = e.dataset.kind, id = e.dataset.id;
    if (k === 'clean') { $('.en', e).checked = on; return; }
    if (k !== 'alert') return;
    if (!on) {
      snap[id] = cb(e).filter(function (i) { return i.checked; });
      cb(e).forEach(function (i) { i.checked = false; });
    } else if (snap[id] && snap[id].length) {
      snap[id].forEach(function (i) { i.checked = true; });
    } else {
      var hasGuest = $('textarea[name$="[guest_message]"]', e).value.trim() !== '';
      ['guest', 'admin'].forEach(function (r) {
        if (r === 'guest' && !hasGuest) return;
        ['sms', 'email'].forEach(function (c) { var i = $('input[name$="[' + r + '_' + c + ']"]', e); if (i) i.checked = true; });
      });
    }
  }
  function sig(e) { return $$('input,textarea', e).map(function (i) { return i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; }).join('\u0001'); }

  function refresh(id) {
    var e = eds[id], r = rows[id], k = e.dataset.kind, on = isOn(e), sub = '';
    if (k === 'alert') {
      var t = cb(e).some(function (i) { return i.checked && /_sms\]/.test(i.name); });
      var m = cb(e).some(function (i) { return i.checked && /_email\]/.test(i.name); });
      sub = on ? [t ? 'Text' : '', m ? 'Email' : ''].filter(Boolean).join(', ') : 'Off';
    } else if (k === 'clean') {
      sub = on ? e.dataset.aud : 'Off';
    } else {
      var all = $$('input[type=checkbox]', e);
      sub = all.filter(function (i) { return i.checked; }).length + ' of ' + all.length + ' on';
    }
    var cu = $$('textarea', e).some(function (x) { return x.value.trim() !== '' && x.value !== x.dataset.def; });
    r.classList.toggle('off', k !== 'globals' && !on);
    r.dataset.on = (k === 'globals' || on) ? '1' : '0';
    $('.sub', r).textContent = sub;
    $('.dot', r).hidden = !cu;
    var tag = $('.cu', e); if (tag) tag.hidden = !cu;
    $$('.sw[data-id="' + id + '"]').forEach(function (s) { s.setAttribute('aria-checked', on ? 'true' : 'false'); });
    $$('.ta', e).forEach(function (x) {
      var v = x.value.trim() || x.dataset.def;
      x.nextElementSibling.textContent = v ? render(v) : 'Not sent';
    });
  }
  function apply() {
    var n = {all: 0, active: 0, off: 0};
    $$('.ns-g').forEach(function (g) {
      var shown = 0;
      $$('.ns-row', g).forEach(function (r) {
        var on = r.dataset.on === '1', cu = !$('.dot', r).hidden;
        var ok = (!q || r.dataset.name.indexOf(q) > -1) && (state === 'all' || (state === 'active' && on) || (state === 'off' && !on) || (state === 'cust' && cu));
        r.hidden = !ok; if (ok) shown++;
        n.all++; if (on) n.active++; else n.off++;
      });
      g.hidden = !shown;
    });
    $$('.pill').forEach(function (p) {
      var c = n[p.dataset.f]; var base = {all: 'All', active: 'Active', off: 'Off', cust: 'Customized'}[p.dataset.f];
      p.textContent = c === undefined ? base : base + ' ' + c;
    });
  }
  function count() {
    var n = Object.keys(dirty).length, c = $('.ns-cnt');
    c.textContent = n ? n + ' unsaved change' + (n > 1 ? 's' : '') : 'No changes';
    c.classList.toggle('dirty', n > 0);
  }
  function sync(id) {
    refresh(id);
    if (sig(eds[id]) !== base[id]) dirty[id] = 1; else delete dirty[id];
    apply(); count();
  }
  function select(id, show) {
    if (!eds[id]) return;
    Object.keys(eds).forEach(function (k) { eds[k].hidden = k !== id; rows[k].classList.toggle('sel', k === id); });
    if (show !== false) {
      f.dataset.view = 'editor';
      if (window.matchMedia('(max-width:767px)').matches) f.scrollIntoView();
    }
    try { history.replaceState(null, '', '#' + id); } catch (x) {}
  }

  Object.keys(eds).forEach(function (id) {
    var e = eds[id];
    $$('.rr', e).forEach(function (r) {
      var always = ['guest', 'contact', 'admin'].indexOf(r.dataset.role) > -1;
      if (!always && !$$('input', r).some(function (i) { return i.checked; })) r.classList.add('rx');
    });
    base[id] = sig(e);
    refresh(id);
  });
  apply(); count();
  var h = location.hash.slice(1);
  select(eds[h] ? h : Object.keys(eds)[0], false);

  f.addEventListener('focusin', function (ev) { var e = ev.target.closest && ev.target.closest('.ns-e'); if (e && ev.target.tagName === 'TEXTAREA') e.__ta = ev.target; });
  function onChange(ev) {
    var t = ev.target;
    if (t.classList && t.classList.contains('tokpick')) {
      var e = t.closest('.ns-e'), ta = e.__ta || $('textarea', e);
      if (t.value) {
        var s = ta.selectionStart == null ? ta.value.length : ta.selectionStart, en = ta.selectionEnd == null ? s : ta.selectionEnd;
        ta.value = ta.value.slice(0, s) + t.value + ta.value.slice(en);
        ta.focus(); ta.selectionStart = ta.selectionEnd = s + t.value.length;
        t.selectedIndex = 0;
        sync(e.dataset.id);
      }
      return;
    }
    var ed = t.closest && t.closest('.ns-e'); if (ed) sync(ed.dataset.id);
  }
  f.addEventListener('input', onChange);
  f.addEventListener('change', onChange);
  $('#nsQ').addEventListener('input', function (ev) { q = ev.target.value.trim().toLowerCase(); apply(); });

  f.addEventListener('click', function (ev) {
    var t = ev.target, b;
    if ((b = t.closest('.sw'))) { ev.preventDefault(); ev.stopPropagation(); var e = eds[b.dataset.id]; setOn(e, !isOn(e)); sync(b.dataset.id); return; }
    if ((b = t.closest('.pill'))) { state = b.dataset.f; $$('.pill').forEach(function (p) { p.classList.toggle('on', p === b); }); apply(); return; }
    if (t.closest('.ns-back')) { f.dataset.view = 'list'; return; }
    if ((b = t.closest('[data-reset]'))) {
      ev.preventDefault(); var e1 = b.closest('.ns-e');
      $('textarea[name$="[guest_message]"]', e1).value = b.dataset.guest;
      $('textarea[name$="[staff_message]"]', e1).value = b.dataset.staff;
      sync(e1.dataset.id); return;
    }
    if ((b = t.closest('[data-reset-clean]'))) {
      ev.preventDefault(); var e2 = b.closest('.ns-e');
      $('textarea', e2).value = b.dataset.default; sync(e2.dataset.id); return;
    }
    if ((b = t.closest('.addr'))) { ev.preventDefault(); b.closest('.ns-e').classList.add('all'); return; }
    if (t.closest('#nsDis')) {
      f.reset(); snap = {}; q = '';
      Object.keys(eds).forEach(function (id) { refresh(id); delete dirty[id]; });
      apply(); count(); return;
    }
    if ((b = t.closest('.ns-row'))) select(b.dataset.id);
  });

  var submitting = false;
  f.addEventListener('submit', function () { submitting = true; });
  window.addEventListener('beforeunload', function (ev) {
    if (!submitting && Object.keys(dirty).length) { ev.preventDefault(); ev.returnValue = ''; }
  });
})();
</script>
@endverbatim
</x-admin-layout>

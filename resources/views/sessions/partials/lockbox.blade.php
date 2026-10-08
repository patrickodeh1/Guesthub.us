@php
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
@endphp

@if ($isAssignedCleaner && $session->status === 'in_progress')
<script>
(function () {
    var key = 'lockbox_saved_{{ $session->id }}';
    window.GH_LOCKBOX = {
        done: function () { try { return localStorage.getItem(key) === '1'; } catch (e) { return false; } }
    };
    window.ghSaveLockbox = function (btn) {
        var input = document.getElementById('gh-lockbox-input');
        var msg = document.getElementById('gh-lockbox-msg');
        var v = input ? input.value.trim() : '';
        if (!v) { if (msg) { msg.textContent = 'Enter the new code first.'; msg.className = 'mt-2 text-sm text-red-600'; } return; }
        if (!confirm('Save this as the new lockbox code?')) return;
        btn.disabled = true;
        fetch('{{ route('sessions.lockbox', $session) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ lockbox_code: v })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok && d.ok, d: d }; }); })
          .then(function (res) {
            btn.disabled = false;
            if (!res.ok) { msg.textContent = res.d.message || 'Could not save. Try again.'; msg.className = 'mt-2 text-sm text-red-600'; return; }
            try { localStorage.setItem(key, '1'); } catch (e) {}
            msg.textContent = 'Lockbox code saved.'; msg.className = 'mt-2 text-sm text-emerald-700';
            var n = document.getElementById('gh-next-stage');
            if (n) { n.disabled = false; n.style.opacity = ''; }
          })
          .catch(function () { btn.disabled = false; msg.textContent = 'Network error. Try again.'; msg.className = 'mt-2 text-sm text-red-600'; });
    };
})();
</script>
@endif

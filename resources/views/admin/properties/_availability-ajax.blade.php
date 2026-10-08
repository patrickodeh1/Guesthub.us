{{-- No-reload handling for the availability page.
     Include once, inside the page, after the markup:
     @include('admin.properties._availability-ajax') --}}
<div id="avail-toasts" class="pointer-events-none fixed right-4 top-4 z-50 grid w-80 max-w-[calc(100vw-2rem)] gap-2" aria-live="polite"></div>

<script>
(function () {
    const page = document.querySelector('[data-avail-page]');
    if (!page) return;
    const toasts = document.getElementById('avail-toasts');

    function toast(message, kind) {
        const el = document.createElement('div');
        el.className = 'pointer-events-auto cursor-pointer rounded-xl border p-3 text-sm shadow-lg ' +
            (kind === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800');
        el.textContent = message;
        el.addEventListener('click', () => el.remove());
        toasts.appendChild(el);
        setTimeout(() => el.remove(), kind === 'error' ? 12000 : 6000);
    }

    // Replace one region of the page with the same region from freshly fetched HTML.
    function swap(doc, selector, innerOnly) {
        const cur = document.querySelector(selector);
        const next = doc.querySelector(selector);
        if (!cur || !next) return;
        if (innerOnly) {
            cur.innerHTML = next.innerHTML;
        } else {
            cur.replaceWith(document.importNode(next, true));
        }
    }

    function refresh(doc) {
        swap(doc, '#sync-status');
        swap(doc, '#cal-counts');
        swap(doc, '#settings-body', true);
        swap(doc, '#push-log-body', true);

        const settings = document.getElementById('settings');
        const nextSettings = doc.getElementById('settings');
        if (settings && nextSettings && nextSettings.hasAttribute('open')) settings.open = true;

        const daysEl = doc.getElementById('avail-days');
        if (daysEl) {
            try {
                window.dispatchEvent(new CustomEvent('avail-refresh', { detail: JSON.parse(daysEl.textContent) }));
            } catch (err) { /* calendar keeps its current data */ }
        }
    }

    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !page.contains(form)) return;
        if ((form.method || '').toLowerCase() !== 'post') return;
        if (e.defaultPrevented) return; // an inline confirm() was cancelled
        e.preventDefault();
        if (form.dataset.busy) return;
        form.dataset.busy = '1';

        const flashArea = document.getElementById('flash-area');
        if (flashArea) flashArea.innerHTML = '';

        const trigger = e.submitter || null;
        const body = trigger ? new FormData(form, trigger) : new FormData(form);
        const buttons = Array.from(form.querySelectorAll('button'));
        const triggerLabel = trigger ? trigger.textContent : null;
        // Not using .disabled: Alpine owns that attribute on some buttons.
        buttons.forEach(b => { b.classList.add('opacity-60', 'pointer-events-none'); b.setAttribute('aria-busy', 'true'); });
        if (trigger) trigger.textContent = 'Working\u2026';

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (res.status === 422) {
                const j = await res.json();
                const first = Object.values(j.errors || {})[0];
                toast((first && first[0]) || j.message || 'Please check the form.', 'error');
                return;
            }
            if (res.status === 419) {
                toast('Your session expired. Reload the page and try again.', 'error');
                return;
            }

            const type = res.headers.get('content-type') || '';
            if (!res.ok || !type.includes('text/html')) {
                toast('Something went wrong (' + res.status + '). Reload the page to see the current state.', 'error');
                return;
            }

            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            if (!doc.querySelector('[data-avail-page]')) {
                window.location.href = res.url; // e.g. sent to the login page
                return;
            }

            const flash = doc.querySelector('#flash-area [data-flash]');
            const kind = flash ? flash.dataset.flash : null;

            if (kind === 'success' && form.querySelector('input[type=date]')) form.reset();

            refresh(doc);
            if (flash) toast(flash.textContent.trim(), kind);
        } catch (err) {
            toast('Could not reach the server. Check your connection and try again.', 'error');
        } finally {
            delete form.dataset.busy;
            buttons.forEach(b => { b.classList.remove('opacity-60', 'pointer-events-none'); b.removeAttribute('aria-busy'); });
            if (trigger && triggerLabel !== null) trigger.textContent = triggerLabel;
        }
    });
})();
</script>

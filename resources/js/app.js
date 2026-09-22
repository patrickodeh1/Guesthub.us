import './bootstrap';

// ══════════════════════════════════════════════════════════════════════════════
// SPOTLIGHT TOUR ENGINE
// ══════════════════════════════════════════════════════════════════════════════

class SpotlightTour {
    constructor(steps, options = {}) {
        this.steps       = steps;
        this.index       = 0;
        this.options     = options;
        this.overlay     = null;
        this.spotlight   = null;
        this.tooltip     = null;
        this.active      = false;
        this._onKeydown  = this._handleKeydown.bind(this);
    }

    start() {
        if (this.active) return;
        this.active = true;
        this._buildDOM();
        this._showStep(0);
        document.addEventListener('keydown', this._onKeydown);
    }

    _buildDOM() {
        // Dark overlay
        this.overlay = Object.assign(document.createElement('div'), {
            className: 'tour-overlay',
        });
        this.overlay.style.cssText = [
            'position:fixed;inset:0;z-index:9990;',
            'background:rgba(10,17,30,0.65);',
            'animation:tourOverlayIn 0.25s ease both;',
        ].join('');

        // Spotlight (positioned box that creates the "hole" via box-shadow)
        this.spotlight = document.createElement('div');
        this.spotlight.style.cssText = [
            'position:fixed;z-index:9995;pointer-events:none;',
            'border-radius:12px;',
            'box-shadow:0 0 0 9999px rgba(10,17,30,0);',
            'outline:2px solid rgba(37,99,235,0.75);',
            'outline-offset:4px;',
            'transition:all 0.22s ease;',
            'opacity:0;',
        ].join('');

        // Tooltip card
        this.tooltip = document.createElement('div');
        this.tooltip.style.cssText = [
            'position:fixed;z-index:9999;',
            'width:340px;max-width:calc(100vw - 32px);',
            'background:#fff;border-radius:10px;',
            'border:1px solid #e2e8f0;',
            'box-shadow:0 12px 30px rgba(15,23,42,0.16);',
            'padding:20px;',
            'transition:all 0.22s ease;',
            'opacity:0;',
        ].join('');

        document.body.appendChild(this.overlay);
        document.body.appendChild(this.spotlight);
        document.body.appendChild(this.tooltip);
    }

    _showStep(index) {
        if (!this.active || index < 0 || index >= this.steps.length) return;
        this.index = index;
        const step   = this.steps[index];
        const target = step.target ? document.querySelector(`[data-tour="${step.target}"]`) : null;

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
            // Allow scroll to settle then position
            setTimeout(() => {
                const rect    = target.getBoundingClientRect();
                const pad     = 10;
                const sl      = this.spotlight;
                sl.style.left   = `${rect.left - pad}px`;
                sl.style.top    = `${rect.top - pad}px`;
                sl.style.width  = `${rect.width + pad * 2}px`;
                sl.style.height = `${rect.height + pad * 2}px`;
                sl.style.opacity = '1';
                sl.style.boxShadow = '0 0 0 9999px rgba(10,17,30,0.65)';
                this._positionTooltip(rect);
            }, 380);
        } else {
            this.spotlight.style.opacity   = '0';
            this.spotlight.style.boxShadow = '0 0 0 9999px rgba(10,17,30,0)';
            this._centerTooltip();
        }

        this._renderTooltip(step, index);
    }

    _positionTooltip(targetRect) {
        const TW     = 340;
        const TH_EST = 220;
        const margin = 18;
        const vw     = window.innerWidth;
        const vh     = window.innerHeight;
        const tt     = this.tooltip;

        let left, top;

        if (targetRect.right + margin + TW <= vw) {
            left = targetRect.right + margin;
            top  = Math.max(margin, Math.min(targetRect.top, vh - TH_EST - margin));
        } else if (targetRect.left - margin - TW >= 0) {
            left = targetRect.left - margin - TW;
            top  = Math.max(margin, Math.min(targetRect.top, vh - TH_EST - margin));
        } else if (targetRect.bottom + margin + TH_EST <= vh) {
            left = Math.max(margin, Math.min(targetRect.left, vw - TW - margin));
            top  = targetRect.bottom + margin;
        } else {
            left = Math.max(margin, Math.min(targetRect.left, vw - TW - margin));
            top  = Math.max(margin, targetRect.top - TH_EST - margin);
        }

        tt.style.left      = `${left}px`;
        tt.style.top       = `${top}px`;
        tt.style.transform = 'none';
        tt.style.opacity   = '1';
    }

    _centerTooltip() {
        const tt = this.tooltip;
        tt.style.left      = '50%';
        tt.style.top       = '50%';
        tt.style.transform = 'translate(-50%, -50%)';
        tt.style.opacity   = '1';
    }

    _renderTooltip(step, index) {
        const isLast  = index === this.steps.length - 1;
        const isFirst = index === 0;
        const pct     = Math.round(((index + 1) / this.steps.length) * 100);

        this.tooltip.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px">
                <div style="min-width:34px;height:34px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;display:grid;place-items:center;font-weight:700;font-size:13px;color:#2563eb;flex-shrink:0">${index + 1}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#2563eb;margin-bottom:4px">Step ${index + 1} of ${this.steps.length}</div>
                    <div style="font-size:15px;font-weight:600;color:#0f172a;line-height:1.4">${step.title}</div>
                </div>
            </div>
            <div style="font-size:13.5px;line-height:1.75;color:#475569;margin-bottom:18px">${step.body}</div>
            <div style="height:3px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin-bottom:16px">
                <div style="height:100%;width:${pct}%;background:#2563eb;border-radius:99px;transition:width 0.22s ease"></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <button class="tour-skip-btn" style="font-size:12px;color:#94a3b8;background:none;border:none;cursor:pointer;padding:4px 0;text-decoration:underline">Skip tour</button>
                <div style="display:flex;gap:8px">
                    ${!isFirst ? `<button class="tour-back-btn" style="padding:8px 16px;border-radius:9px;border:1px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;cursor:pointer;color:#334155">← Back</button>` : ''}
                    <button class="tour-next-btn" style="padding:8px 18px;border-radius:7px;background:#0b2d4d;color:#fff;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:background 0.15s">${isLast ? 'Finish' : 'Next'}</button>
                </div>
            </div>
        `;

        this.tooltip.querySelector('.tour-skip-btn')?.addEventListener('click', () => this.finish());
        this.tooltip.querySelector('.tour-back-btn')?.addEventListener('click', () => this._showStep(this.index - 1));
        this.tooltip.querySelector('.tour-next-btn')?.addEventListener('click', () => {
            if (isLast) this.finish();
            else this._showStep(this.index + 1);
        });

        const nextBtn = this.tooltip.querySelector('.tour-next-btn');
        nextBtn?.addEventListener('mouseenter', () => { nextBtn.style.background = '#1d4ed8'; });
        nextBtn?.addEventListener('mouseleave', () => { nextBtn.style.background = '#0b2d4d'; });
    }

    _handleKeydown(e) {
        if (!this.active) return;
        if (e.key === 'Escape')     this.finish();
        if (e.key === 'ArrowRight') this._showStep(this.index + 1);
        if (e.key === 'ArrowLeft')  this._showStep(this.index - 1);
    }

    async finish() {
        if (!this.active) return;
        this.active = false;

        [this.overlay, this.spotlight, this.tooltip].forEach(el => {
            if (el) { el.style.opacity = '0'; setTimeout(() => el?.remove(), 300); }
        });

        document.removeEventListener('keydown', this._onKeydown);

        if (this.options.completeUrl) {
            try {
                await fetch(this.options.completeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.options.csrf,
                        'Accept': 'application/json',
                    },
                });
            } catch (_) { /* best effort */ }
        }

        if (this.options.onComplete) this.options.onComplete();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// TOAST NOTIFICATION SYSTEM
// ══════════════════════════════════════════════════════════════════════════════

class ToastManager {
    constructor() {
        this.container = document.getElementById('toast-container');
    }

    show(message, type = 'success', duration = 4500) {
        if (!this.container) return;

        const cfg = {
            success: { bg: '#f0fdf4', border: '#bbf7d0', text: '#15803d', icon: '#22c55e', symbol: '✓' },
            error:   { bg: '#fef2f2', border: '#fecaca', text: '#dc2626', icon: '#ef4444', symbol: '✕' },
            warning: { bg: '#fffbeb', border: '#fde68a', text: '#92400e', icon: '#f59e0b', symbol: '!' },
            info:    { bg: '#eff6ff', border: '#bfdbfe', text: '#1d4ed8', icon: '#3b82f6', symbol: 'i' },
        };
        const c = cfg[type] || cfg.info;

        const toast = document.createElement('div');
        toast.style.cssText = [
            `background:${c.bg};border:1px solid ${c.border};color:${c.text};`,
            'pointer-events:auto;display:flex;align-items:center;gap:10px;',
            'padding:12px 14px;border-radius:12px;',
            'box-shadow:0 4px 16px rgba(0,0,0,0.1);',
            'font-size:13px;font-weight:500;min-width:220px;max-width:380px;',
            'animation:toastIn 0.3s ease both;',
            'transition:opacity 0.3s ease,transform 0.3s ease;',
        ].join('');

        toast.innerHTML = `
            <span style="width:20px;height:20px;border-radius:50%;background:${c.icon};color:#fff;display:grid;place-items:center;font-size:11px;font-weight:700;flex-shrink:0">${c.symbol}</span>
            <span style="flex:1">${message}</span>
            <button style="background:none;border:none;cursor:pointer;color:${c.text};opacity:0.5;font-size:18px;line-height:1;padding:0 2px" aria-label="Close">×</button>
        `;

        toast.querySelector('button').addEventListener('click', () => this._dismiss(toast));
        this.container.appendChild(toast);
        setTimeout(() => this._dismiss(toast), duration);
    }

    _dismiss(toast) {
        toast.style.opacity   = '0';
        toast.style.transform = 'translateX(16px)';
        setTimeout(() => toast.remove(), 300);
    }

    success(msg) { this.show(msg, 'success'); }
    error(msg)   { this.show(msg, 'error'); }
    warning(msg) { this.show(msg, 'warning'); }
    info(msg)    { this.show(msg, 'info'); }
}

window.Toast = new ToastManager();

// ══════════════════════════════════════════════════════════════════════════════
// GLOBAL SEARCH
// ══════════════════════════════════════════════════════════════════════════════

function initGlobalSearch() {
    const input    = document.getElementById('global-search-input');
    const dropdown = document.getElementById('search-dropdown');
    const results  = document.getElementById('search-results');
    if (!input || !dropdown || !results) return;

    const searchUrl = input.dataset.searchUrl
        || window.location.origin + '/admin/search';

    let debounce;

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = input.value.trim();

        if (q.length < 2) {
            dropdown.classList.add('hidden');
            results.innerHTML = '';
            return;
        }

        debounce = setTimeout(async () => {
            try {
                const res  = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();

                if (!data.results?.length) {
                    results.innerHTML = '<div style="padding:16px;text-align:center;color:#94a3b8;font-size:13px">No results found</div>';
                } else {
                    results.innerHTML = data.results.map(r => `
                        <a href="${r.url}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;text-decoration:none;color:#0f172a;transition:background 0.15s"
                           onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                            <span style="font-size:10px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;min-width:60px">${r.type}</span>
                            <span style="font-size:13px;font-weight:500">${r.label}</span>
                        </a>
                    `).join('');
                }

                dropdown.classList.remove('hidden');
            } catch (_) { /* silent fail */ }
        }, 220);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
            input.blur();
        }
    });

    input.addEventListener('click', (e) => e.stopPropagation());
    dropdown.addEventListener('click', (e) => e.stopPropagation());
}

// ══════════════════════════════════════════════════════════════════════════════
// DOM READY
// ══════════════════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {

    // Page enter animations
    document.querySelectorAll('[data-animate]').forEach((el, i) => {
        el.style.animationDelay = `${Math.min(i * 45, 360)}ms`;
        el.classList.add('ui-enter');
    });

    // Form submit loading states
    document.querySelectorAll('form[data-loading], form[method="post"], form[method="POST"]').forEach((form) => {
        if (form.hasAttribute('data-skip-loading')) return;
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            const btn = form.querySelector('[type="submit"], button:not([type="button"])');
            if (!btn || btn.dataset.loadingActive) return;
            btn.dataset.loadingActive = 'true';
            btn.dataset.originalHtml  = btn.innerHTML;
            btn.disabled  = true;
            btn.innerHTML = `<span class="ui-spinner"></span><span>${btn.dataset.loadingText || 'Working…'}</span>`;
        });
    });

    // Row action menus (kebab dropdowns) — table wrappers use overflow-x-auto
    // for horizontal scrolling on small screens, which also clips vertical
    // overflow, cutting off an absolutely-positioned panel and forcing a
    // scroll to see it. Instead, move the panel to <body> and position it
    // with fixed coordinates computed from the trigger button, so it renders
    // above everything and is never clipped by an ancestor's scroll
    // container. A WeakMap tracks each button's panel (rather than relying
    // on nextElementSibling, which stops finding it once the panel has been
    // moved out from beside the button).
    const rowMenuPanels = new WeakMap();
    function closeAllRowMenus() {
        document.querySelectorAll('[data-row-menu-panel]').forEach((panel) => {
            panel.classList.add('hidden');
        });
    }
    window.toggleRowMenu = function toggleRowMenu(btn) {
        let panel = rowMenuPanels.get(btn);
        if (!panel) {
            panel = btn.nextElementSibling;
            rowMenuPanels.set(btn, panel);
        }
        const isOpen = !panel.classList.contains('hidden');
        closeAllRowMenus();
        if (isOpen) return;
        if (panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }
        // Measure the panel's real size first (off-screen, still hidden from
        // view) so we can decide whether it fits below the button or needs
        // to open upward instead.
        panel.classList.remove('hidden');
        panel.style.visibility = 'hidden';
        panel.style.position = 'fixed';
        panel.style.top = '0px';
        panel.style.left = '0px';
        panel.style.right = 'auto';
        panel.style.margin = '0';
        const panelWidth = panel.offsetWidth || 224;
        const panelHeight = panel.offsetHeight || 0;

        const rect = btn.getBoundingClientRect();
        let top = rect.bottom + 4;
        if (top + panelHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - panelHeight - 4);
        }
        let left = rect.right - panelWidth;
        left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));

        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
        panel.style.visibility = 'visible';
    };

    // Copies a guest's public guide URL to the clipboard from the row's
    // quick menu, with a brief inline "Copied!" confirmation on the label.
    window.copyGuestUrl = function copyGuestUrl(btn, url) {
        const label = btn.querySelector('[data-copy-label]');
        const original = label ? label.textContent : null;
        const showCopied = () => {
            if (!label) return;
            label.textContent = 'Copied!';
            setTimeout(() => { label.textContent = original; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(showCopied).catch(() => {
                fallbackCopyText(url);
                showCopied();
            });
        } else {
            fallbackCopyText(url);
            showCopied();
        }
        closeAllRowMenus();
    };

    function fallbackCopyText(text) {
        const temp = document.createElement('textarea');
        temp.value = text;
        temp.style.position = 'fixed';
        temp.style.opacity = '0';
        document.body.appendChild(temp);
        temp.focus();
        temp.select();
        try { document.execCommand('copy'); } catch (e) { /* no-op */ }
        document.body.removeChild(temp);
    }

    // Notification bell: submit dismiss / mark-all-read via fetch instead of
    // a normal form POST, so the panel updates in place with no page reload.
    function updateNotifBadgeAfterRemoval(remaining) {
        const badge = document.getElementById('notif-badge');
        if (remaining > 0) {
            if (badge) badge.textContent = remaining;
        } else if (badge) {
            badge.remove();
        }
        if (remaining === 0) {
            const container = document.getElementById('notif-items');
            if (container && !container.querySelector('[data-notif-empty]')) {
                container.innerHTML = `
                    <div class="p-5 text-center text-sm text-slate-500" data-notif-empty>
                        <svg class="mx-auto mb-2 h-6 w-6 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                        All caught up. Nothing pending.
                    </div>`;
            }
            const markAllForm = document.querySelector('[data-notif-markall-form]');
            if (markAllForm) markAllForm.remove();
        }
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.matches('[data-notif-dismiss-form]')) {
            e.preventDefault();
            const item = form.closest('[data-notif-item]');
            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            }).then((res) => {
                if (!res.ok) return;
                if (item) item.remove();
                const remaining = document.querySelectorAll('[data-notif-item]').length;
                updateNotifBadgeAfterRemoval(remaining);
            }).catch(() => { /* leave item in place on failure */ });
        } else if (form.matches('[data-notif-markall-form]')) {
            e.preventDefault();
            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            }).then((res) => {
                if (!res.ok) return;
                document.querySelectorAll('[data-notif-item]').forEach((el) => el.remove());
                updateNotifBadgeAfterRemoval(0);
            }).catch(() => { /* no-op on failure */ });
        }
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-row-menu]') || e.target.closest('[data-row-menu-panel]')) return;
        closeAllRowMenus();
    });
    window.addEventListener('scroll', closeAllRowMenus, true);
    window.addEventListener('resize', closeAllRowMenus);
    // Copy-to-clipboard buttons
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const target = document.querySelector(btn.dataset.copy);
            const value  = target?.value || target?.textContent || btn.dataset.copyText || '';
            if (!value) return;

            const showCopied = () => {
                const orig = btn.innerHTML;
                btn.innerHTML = `<span class="ui-pop">✓ Copied</span>`;
                btn.classList.add('is-copied');
                window.Toast?.success('Copied to clipboard.');
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.classList.remove('is-copied');
                }, 1800);
            };

            const fallbackCopy = (text) => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.top = '-9999px';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                textarea.setSelectionRange(0, textarea.value.length);
                let ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch (_) {
                    ok = false;
                }
                document.body.removeChild(textarea);
                return ok;
            };

            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(value.trim());
                    showCopied();
                    return;
                } catch (_) {
                    // fall through to fallback below
                }
            }

            if (fallbackCopy(value.trim())) {
                showCopied();
            } else {
                window.Toast?.error('Could not copy. Please copy manually.');
            }
        });
    });

    // Global search
    initGlobalSearch();

    // ── Photo ID upload preview + drag-and-drop ──────────────────────────────
    const photoInput  = document.getElementById('photo-id-input');
    const uploadZone  = document.getElementById('upload-zone');
    const uploadLabel = document.getElementById('upload-label');

    if (photoInput && uploadLabel) {
        photoInput.addEventListener('change', () => {
            if (photoInput.files.length) {
                uploadLabel.textContent = '✓ ' + photoInput.files[0].name;
                uploadLabel.style.color = '#15803d';
            }
        });
    }

    if (uploadZone && photoInput) {
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#2563eb';
            uploadZone.style.background  = 'rgba(251,247,239,0.7)';
        });
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.style.borderColor = '';
            uploadZone.style.background  = '';
        });
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '';
            uploadZone.style.background  = '';
            if (e.dataTransfer.files.length) {
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                photoInput.files = dt.files;
                photoInput.dispatchEvent(new Event('change'));
            }
        });
    }

    // ── Guest portal tour (localStorage — no server required) ────────────────
    const guestTourEl = document.getElementById('guest-tour-data');
    const completionPrompt = document.getElementById('completion-prompt');
    const completionPromptVisible = completionPrompt && !completionPrompt.classList.contains('hidden');

    if (completionPromptVisible) {
        localStorage.setItem((guestTourEl?.dataset.tourKey) || 'guest_tour_seen', '1');
    }

    if (guestTourEl) {
        const guestSteps   = JSON.parse(guestTourEl.dataset.steps || '[]');
        const guestTourKey = guestTourEl.dataset.tourKey || 'guest_tour_seen';

        if (guestSteps.length && !localStorage.getItem(guestTourKey) && !completionPromptVisible) {
            setTimeout(() => {
                const tour = new SpotlightTour(guestSteps, {
                    onComplete: () => {
                        localStorage.setItem(guestTourKey, '1');
                        window.Toast?.info('Tour complete! Tap the help button any time if you need support.');
                    },
                });
                tour.start();
            }, 800);
        }

        document.getElementById('restart-guest-tour')?.addEventListener('click', () => {
            const tour = new SpotlightTour(guestSteps, {
                onComplete: () => localStorage.setItem(guestTourKey, '1'),
            });
            tour.start();
        });
    }

    document.querySelector('[data-close-completion]')?.addEventListener('click', () => {
        document.getElementById('completion-prompt')?.remove();
    });

    // ── Onboarding spotlight tour (first visit) ───────────────────────────────
    const onboardingEl = document.getElementById('admin-onboarding-tour');
    if (onboardingEl) {
        const steps       = JSON.parse(onboardingEl.dataset.steps || '[]');
        const completeUrl = onboardingEl.dataset.completeUrl;
        const csrf        = onboardingEl.dataset.csrf;

        // Small delay so page renders fully first
        setTimeout(() => {
            const tour = new SpotlightTour(steps, {
                completeUrl,
                csrf,
                onComplete: () => window.Toast?.success('Tour complete! You can restart it anytime from your profile menu.'),
            });
            tour.start();
        }, 600);
    }

    // ── Dashboard spotlight tour (triggered by button) ────────────────────────
    const dashTourEl = document.getElementById('dashboard-tour-data');
    const dashBtn    = document.getElementById('start-dashboard-tour');
    if (dashTourEl && dashBtn) {
        const steps       = JSON.parse(dashTourEl.dataset.steps || '[]');
        const completeUrl = dashTourEl.dataset.completeUrl;
        const csrf        = dashTourEl.dataset.csrf;

        dashBtn.addEventListener('click', () => {
            const tour = new SpotlightTour(steps, {
                completeUrl,
                csrf,
                onComplete: () => window.Toast?.success('Dashboard tour complete!'),
            });
            tour.start();
        });
    }

    // ── GPS JSON verify (AJAX) ─────────────────────────────────────────────────
    const gpsAjaxBtn = document.getElementById('gps-ajax-verify-btn');
    if (gpsAjaxBtn) {
        gpsAjaxBtn.addEventListener('click', async () => {
            const url  = gpsAjaxBtn.dataset.url;
            const csrf = gpsAjaxBtn.dataset.csrf;
            if (!url) return;

            gpsAjaxBtn.disabled  = true;
            gpsAjaxBtn.innerHTML = '<span class="ui-spinner"></span><span>Checking your location…</span>';

            const showMsg = (text, isError = false) => {
                const el = document.getElementById('gps-ajax-message');
                if (!el) return;
                el.textContent = text;
                el.className   = `mt-4 rounded-xl p-4 text-sm font-medium ${isError ? 'border border-red-200 bg-red-50 text-red-800' : 'border border-emerald-200 bg-emerald-50 text-emerald-800'}`;
                el.classList.remove('hidden');
            };

            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                latitude:  pos.coords.latitude,
                                longitude: pos.coords.longitude,
                                accuracy:  pos.coords.accuracy,
                            }),
                        });
                        const data = await res.json();
                        if (data.ok) {
                            showMsg('✓ Arrival verified. Your check-in guide is now unlocked.');
                            setTimeout(() => location.reload(), 1800);
                        } else {
                            showMsg(data.message || 'Location could not be verified.', true);
                            gpsAjaxBtn.disabled  = false;
                            gpsAjaxBtn.innerHTML = 'Try Again';
                        }
                    } catch (_) {
                        showMsg('A network error occurred. Please try again.', true);
                        gpsAjaxBtn.disabled  = false;
                        gpsAjaxBtn.innerHTML = 'Try Again';
                    }
                },
                (err) => {
                    if (err.code === err.PERMISSION_DENIED) {
                        showMsg('Location permission is blocked. After allowing location access in your browser settings, tap below to reload and try again.', true);
                        gpsAjaxBtn.disabled  = false;
                        gpsAjaxBtn.innerHTML = 'Reload Page';
                        gpsAjaxBtn.onclick   = () => location.reload();

                        // Where supported, auto-reload the moment permission is granted
                        // so the guest doesn't have to tap anything themselves.
                        if (navigator.permissions?.query) {
                            navigator.permissions.query({ name: 'geolocation' }).then((status) => {
                                status.onchange = () => {
                                    if (status.state === 'granted') location.reload();
                                };
                            }).catch(() => {});
                        }
                    } else {
                        showMsg('Location access failed. Please try again, or contact guest services.', true);
                        gpsAjaxBtn.disabled  = false;
                        gpsAjaxBtn.innerHTML = 'Retry Location Check';
                    }
                },
                { timeout: 14000, enableHighAccuracy: true }
            );
        });
    }

    // ── Flash session success as toast ─────────────────────────────────────────
    const categorySortList = document.getElementById('category-sort-list');
    if (categorySortList) {
        let draggedItem = null;

        const persistCategoryOrder = async () => {
            const categoryIds = Array.from(categorySortList.querySelectorAll('[data-category-id]'))
                .map((item) => item.dataset.categoryId);

            try {
                const res = await fetch(categorySortList.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ category_ids: categoryIds }),
                });

                if (!res.ok) throw new Error('Order save failed');
                window.Toast?.success('Category order saved.');
            } catch (_) {
                window.Toast?.error('Could not save category order. Please refresh and try again.');
            }
        };

        categorySortList.querySelectorAll('[data-category-id]').forEach((item) => {
            item.addEventListener('dragstart', () => {
                draggedItem = item;
                item.classList.add('opacity-50');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('opacity-50');
                draggedItem = null;
                persistCategoryOrder();
            });

            item.addEventListener('dragover', (event) => {
                event.preventDefault();
                if (!draggedItem || draggedItem === item) return;

                const rect = item.getBoundingClientRect();
                const insertAfter = event.clientY > rect.top + rect.height / 2;
                categorySortList.insertBefore(draggedItem, insertAfter ? item.nextSibling : item);
            });
        });
    }

    const successBanner = document.querySelector('[data-flash-success]');
    if (successBanner) {
        window.Toast?.success(successBanner.dataset.flashSuccess);
    }

    const pollTarget = document.querySelector('[data-poll-gps-status]');
    if (pollTarget) {
        const statusUrl = pollTarget.dataset.pollGpsStatus;
        const pollInterval = setInterval(async () => {
            try {
                const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.gps_verified) {
                    clearInterval(pollInterval);
                    location.reload();
                }
            } catch (_) {
                // network hiccup — next tick will retry
            }
        }, 5000);
    }
    const idPollTarget = document.querySelector('[data-poll-id-status]');
    if (idPollTarget) {
        const idStatusUrl = idPollTarget.dataset.pollIdStatus;
        const watchFields = (idPollTarget.dataset.pollFields || 'id_approved,background_check_complete,deposit_verified').split(',');
        const idStateKey = idPollTarget.dataset.idStateKey;
        const idRejectionKey = idPollTarget.dataset.idRejectionKey;
        const idPollInterval = setInterval(async () => {
            try {
                const res = await fetch(idStatusUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.id_rejected) {
                    let alreadyReloaded = false;
                    try {
                        alreadyReloaded = idRejectionKey && sessionStorage.getItem(idRejectionKey) === '1';
                        if (idRejectionKey) sessionStorage.setItem(idRejectionKey, '1');
                        if (idStateKey) sessionStorage.removeItem(idStateKey);
                    } catch (_) {}
                    if (!alreadyReloaded) {
                        clearInterval(idPollInterval);
                        location.reload();
                        return;
                    }
                } else if (idRejectionKey) {
                    try { sessionStorage.removeItem(idRejectionKey); } catch (_) {}
                }
                if (watchFields.every((field) => data[field])) {
                    clearInterval(idPollInterval);
                    location.reload();
                }
            } catch (_) {
                // network hiccup — next tick will retry
            }
        }, 5000);
    }

    const scrollTarget = document.querySelector('[data-scroll-to]');
    if (scrollTarget) {
        const anchorSelector = scrollTarget.dataset.scrollTo;
        const anchor = anchorSelector ? document.querySelector(anchorSelector) : null;
        if (anchor) {
            setTimeout(() => {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
                anchor.classList.add('scroll-highlight-pulse');
                setTimeout(() => anchor.classList.remove('scroll-highlight-pulse'), 2000);
            }, 150);
        }
    }
});

// --- session-keepalive: refresh CSRF token on tab return ---
// When a tab has been backgrounded (phone locked, app switched away, etc.)
// its CSRF token / page state can go stale. Rather than let the next tap
// fail with a 419 and dead-end the user, quietly re-sync the token as soon
// as the tab becomes visible again.
(function () {
    async function refreshCsrfToken() {
        try {
            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok || response.redirected) return;

            const html = await response.text();
            const match = html.match(/<meta name="csrf-token" content="([^"]+)"/i);
            if (!match) return;

            const freshToken = match[1];

            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) metaTag.setAttribute('content', freshToken);

            document.querySelectorAll('input[name="_token"]').forEach((input) => {
                input.value = freshToken;
            });

            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = freshToken;
            }
        } catch (e) {
            // Silently ignore — worst case, the user's next action still
            // shows the friendly 419 page instead of a dead end.
        }
    }

    // Fires when the tab regains focus/visibility (phone unlocked, app
    // switched back to, etc.)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshCsrfToken();
        }
    });

    // Fires specifically when a page is restored from the browser's
    // back-forward cache (bfcache) — common on mobile Safari/Chrome.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            refreshCsrfToken();
        }
    });
})();

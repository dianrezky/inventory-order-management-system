// Shared AJAX utilities, namespaced under window.App to avoid globals.
(function () {
    'use strict';

    var ALERT_CONTAINER_ID = 'app-alert-container';

    // ─── Utilities ────────────────────────────────────────────────────────────

    function $(selector, ctx) {
        return (ctx || document).querySelector(selector);
    }

    function $$(selector, ctx) {
        return Array.from((ctx || document).querySelectorAll(selector));
    }

    function esc(str) {
        // Escapes HTML special characters so callers can safely use innerHTML.
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatRupiah(num) {
        // Rupiah grouping uses dots as thousand separators: 1234567 becomes "1.234.567".
        return String(Math.round(Number(num) || 0))
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function getCsrfToken() {
        // Reads the first CSRF hidden input in the document; empty string when absent.
        var el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : '';
    }

    // Runs fn once the DOM is ready — safe to call even after DOMContentLoaded
    // has already fired (unlike a bare document.addEventListener), which is
    // exactly what happens for every per-page script (products.js,
    // categories.js, ...): the layout injects it via document.createElement +
    // appendChild(head), and a script inserted that way ignores its own
    // `defer` flag (defer only works for parser-inserted <script> tags) — so
    // it fetches and runs whenever the network returns, often after
    // DOMContentLoaded already fired, silently dropping a bare
    // 'DOMContentLoaded' listener registered too late. Every per-page script
    // must call App.ready(fn) instead of addEventListener('DOMContentLoaded', fn).
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    // ─── API Helper ───────────────────────────────────────────────────────────

    async function api(url, opts) {
        // Resolves to {ok, status, data, error}; never rejects on a non-2xx response.
        var method = (opts.method || 'GET').toUpperCase();
        var headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});

        var body = opts.body;

        // Inject CSRF token on state-changing requests
        if (method !== 'GET' && method !== 'HEAD') {
            if (body instanceof FormData) {
                body.set('_csrf_token', getCsrfToken());
            } else if (typeof body === 'string' && body) {
                // Assume URL-encoded
                body = body + '&_csrf_token=' + encodeURIComponent(getCsrfToken());
            } else if (!body) {
                var fd = new FormData();
                fd.set('_csrf_token', getCsrfToken());
                body = fd;
            }
        }

        // Assume JSON if Content-Type is explicitly set and body is not FormData
        if (!(body instanceof FormData) && body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        var response = await fetch(url, {
            method: method,
            headers: headers,
            body: body || undefined,
            signal: opts.signal,
        });

        var data = null;
        var error = null;
        var contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            data = await response.text();
        }

        if (!response.ok) {
            error = (data && (data.message || data.error || data))
                ? (data.message || data.error || String(data))
                : ('HTTP ' + response.status);
        }

        return { ok: response.ok, status: response.status, data: data, error: error };
    }

    // ─── Toast Notifications ──────────────────────────────────────────────────

    function toast(message, type, duration) {
        // duration is in ms; 0 makes the toast persistent, type is success/error/info/warning.
        if (duration === void 0) { duration = 4000; }

        var container = document.getElementById(ALERT_CONTAINER_ID);
        if (!container) {
            container = document.createElement('div');
            container.id = ALERT_CONTAINER_ID;
            container.setAttribute('aria-live', 'polite');
            container.style.cssText = [
                'position:fixed',
                'top:72px',
                'right:16px',
                'z-index:9999',
                'display:flex',
                'flex-direction:column',
                'gap:8px',
                'max-width:380px',
                'pointer-events:none',
            ].join(';');
            document.body.appendChild(container);
        }

        var iconMap = {
            success: '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
            error: '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
            info: '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
            warning: '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        };

        var typeStyles = {
            success: 'background:#059669;color:#fff;border-color:#047857',
            error: 'background:#dc2626;color:#fff;border-color:#b91c1c',
            info: 'background:#2563eb;color:#fff;border-color:#1d4ed8',
            warning: 'background:#d97706;color:#fff;border-color:#b45309',
        };

        var el = document.createElement('div');
        el.setAttribute('role', 'alert');
        el.style.cssText = [
            'display:flex',
            'align-items:center',
            'gap:10px',
            'padding:12px 16px',
            'border-radius:8px',
            'border:1px solid transparent',
            'box-shadow:0 4px 12px rgba(0,0,0,0.15)',
            'font-family:Inter,sans-serif',
            'font-size:14px',
            'line-height:1.4',
            'pointer-events:auto',
            'animation:app-toast-in 0.25s ease',
        ].join(';') + ';background:' + (typeStyles[type] || typeStyles.info);

        el.innerHTML =
            '<span style="flex-shrink:0">' + (iconMap[type] || iconMap.info) + '</span>' +
            '<span style="flex:1">' + esc(String(message)) + '</span>' +
            '<button type="button" aria-label="Dismiss" style="' +
            'background:none;border:none;cursor:pointer;padding:0;color:inherit;opacity:0.8;flex-shrink:0">' +
            '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
            '</button>';

        el.querySelector('button').addEventListener('click', function () { dismiss(); });
        container.appendChild(el);

        function dismiss() {
            el.style.transition = 'opacity 0.2s, transform 0.2s';
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            setTimeout(function () { el.remove(); }, 200);
        }

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }
    }

    // ─── Confirmation Dialog ─────────────────────────────────────────────────

    function confirm(message, confirmLabel, cancelLabel) {
        // Resolves true on confirm, false on cancel or a click on the backdrop.
        if (confirmLabel === void 0) { confirmLabel = 'Confirm'; }
        if (cancelLabel === void 0) { cancelLabel = 'Cancel'; }

        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.style.cssText = [
                'position:fixed',
                'inset:0',
                'background:rgba(0,0,0,0.45)',
                'z-index:10000',
                'display:flex',
                'align-items:center',
                'justify-content:center',
                'font-family:Inter,sans-serif',
            ].join(';');

            overlay.innerHTML =
                '<div role="alertdialog" aria-modal="true" aria-labelledby="__confirmMessage" style="' +
                'background:var(--color-bg,white);border-radius:12px;padding:24px;' +
                'max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);' +
                'border:1px solid var(--color-border,#e5e7eb);' +
                'animation:app-modal-in 0.2s ease' +
                '">' +
                '<div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:20px">' +
                '<div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;' +
                'background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706">' +
                '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>' +
                '</div>' +
                '<p id="__confirmMessage" style="margin:0;font-size:15px;line-height:1.5;color:var(--color-text,#111)">' +
                esc(message) + '</p>' +
                '</div>' +
                '<div style="display:flex;gap:10px;justify-content:flex-end">' +
                '<button id="__confirmCancel" type="button" style="' +
                'padding:8px 16px;border-radius:6px;border:1px solid var(--color-border,#d1d5db);' +
                'background:var(--color-surface,white);color:var(--color-text,#374151);' +
                'cursor:pointer;font-size:14px;font-weight:500">' + esc(cancelLabel) + '</button>' +
                '<button id="__confirmOk" type="button" style="' +
                'padding:8px 16px;border-radius:6px;border:none;' +
                'background:#dc2626;color:white;cursor:pointer;font-size:14px;font-weight:500">' +
                esc(confirmLabel) + '</button>' +
                '</div>' +
                '</div>';

            document.body.appendChild(overlay);

            var close = function (val) {
                overlay.remove();
                resolve(val);
            };

            $('#__confirmOk', overlay).addEventListener('click', function () { close(true); });
            $('#__confirmCancel', overlay).addEventListener('click', function () { close(false); });
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) { close(false); }
            });
            overlay.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { close(false); }
            });

            // 15_CONFIRMATION: initial focus lands on Cancel, never on the
            // destructive action, so Enter/Space on open never confirms by
            // accident.
            var cancelBtn = $('#__confirmCancel', overlay);
            if (cancelBtn) { cancelBtn.focus(); }
        });
    }

    // ─── Report Dialog (manual Notiflix-Report equivalent) ─────────────────────
    // A blocking, centered modal with an icon/title/message/single button —
    // for reporting one finished outcome (an error, a success, a warning),
    // as opposed to toast() (transient, corner, non-blocking) or confirm()
    // (asks a yes/no question). No external library: every page must be able
    // to report a hard failure (e.g. a form's own server-side error) without
    // ever falling back to an unstyled raw response taking over the page.
    var REPORT_STYLES = {
        success: { bg: '#dcfce7', fg: '#16a34a', icon: '<svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' },
        failure: { bg: '#fee2e2', fg: '#dc2626', icon: '<svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>' },
        warning: { bg: '#fef3c7', fg: '#d97706', icon: '<svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>' },
        info: { bg: '#dbeafe', fg: '#2563eb', icon: '<svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>' },
    };

    function report(type, title, message, buttonLabel) {
        // Resolves when the user dismisses the report (button, backdrop, or Escape).
        if (buttonLabel === void 0) { buttonLabel = 'OK'; }
        var style = REPORT_STYLES[type] || REPORT_STYLES.info;

        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.style.cssText = [
                'position:fixed',
                'inset:0',
                'background:rgba(0,0,0,0.45)',
                'z-index:10000',
                'display:flex',
                'align-items:center',
                'justify-content:center',
                'font-family:Inter,sans-serif',
            ].join(';');

            overlay.innerHTML =
                '<div role="alertdialog" aria-modal="true" aria-labelledby="__reportTitle" aria-describedby="__reportMessage" style="' +
                'background:var(--color-bg,white);border-radius:12px;padding:28px 24px 24px;' +
                'max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);' +
                'border:1px solid var(--color-border,#e5e7eb);text-align:center;' +
                'animation:app-modal-in 0.2s ease' +
                '">' +
                '<div style="width:56px;height:56px;border-radius:50%;margin:0 auto 16px;' +
                'background:' + style.bg + ';display:flex;align-items:center;justify-content:center;color:' + style.fg + '">' +
                style.icon +
                '</div>' +
                '<h2 id="__reportTitle" style="margin:0 0 8px;font-size:17px;font-weight:600;color:var(--color-text,#111)">' +
                esc(String(title)) + '</h2>' +
                '<p id="__reportMessage" style="margin:0 0 22px;font-size:14px;line-height:1.5;color:var(--color-text-secondary,#5b6472)">' +
                esc(String(message)) + '</p>' +
                '<button id="__reportOk" type="button" style="' +
                'padding:9px 28px;border-radius:6px;border:none;min-width:100px;' +
                'background:' + style.fg + ';color:white;cursor:pointer;font-size:14px;font-weight:600">' +
                esc(buttonLabel) + '</button>' +
                '</div>';

            document.body.appendChild(overlay);

            var close = function () {
                overlay.remove();
                resolve();
            };

            $('#__reportOk', overlay).addEventListener('click', close);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) { close(); }
            });
            overlay.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { close(); }
            });

            $('#__reportOk', overlay).focus();
        });
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    window.App = {
        api: api,
        toast: toast,
        confirm: confirm,
        report: report,
        $: $,
        $$: $$,
        esc: esc,
        formatRupiah: formatRupiah,
        getCsrfToken: getCsrfToken,
        ready: ready,
    };

    // ─── Global inline-confirm replacement ────────────────────────────────────
    // Replaces all <form onsubmit="return confirm('...')"> with App.confirm()
    document.addEventListener('DOMContentLoaded', function () {
        $$('form[data-confirm]').forEach(function (form) {
            // Read the attributes now: data-confirm is deleted right below, so reading it
            // inside the submit handler always fell back to the generic 'Are you sure?'.
            var msg = form.dataset.confirm || 'Are you sure?';
            var okLabel = form.dataset.confirmOk;
            var cancelLabel = form.dataset.confirmCancel;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                confirm(msg, okLabel, cancelLabel).then(function (ok) {
                    if (ok) { form.submit(); }
                });
            });
            delete form.dataset.confirm;
        });

        $$('[data-confirm-standalone]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                var msg = el.dataset.confirmStandalone || 'Are you sure?';
                e.preventDefault();
                confirm(msg).then(function (ok) {
                    if (ok) {
                        var href = el.getAttribute('href');
                        if (href) { window.location.href = href; }
                        else { el.disabled = true; el.click(); }
                    }
                });
            });
            delete el.dataset.confirmStandalone;
        });
    });

    // Inject keyframe for toast/modal animations (one-shot)
    var style = document.createElement('style');
    style.textContent = [
        '@keyframes app-toast-in {from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}',
        '@keyframes app-modal-in {from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}',
    ].join('\n');
    document.head.appendChild(style);

})();

/* ============================================================
   MULTI-SELECT — tag-based custom dropdown
   Initialise via: new App.MultiSelect(document.querySelector('.ms-wrapper'));
   ============================================================ */
(function () {
    'use strict';

    if (!window.App) { window.App = {}; }

    App.MultiSelect = function (wrapper) {
        this.wrapper = wrapper;
        this.input    = wrapper.querySelector('.ms-input');
        this.searchEl = wrapper.querySelector('.ms-search');
        this.dropdown = wrapper.querySelector('.ms-dropdown');
        this.nativeSelect = wrapper.querySelector('select.ms-native-select');

        this.options   = Array.from(this.dropdown.querySelectorAll('.ms-option'));
        this.allLabels = this.options.map(function (opt) { return opt.textContent.trim().toLowerCase(); });

        this._selected = [];
        this._search   = '';
        this._open     = false;

        this._bindEvents();
        this._syncFromNative();
    };

    App.MultiSelect.prototype._bindEvents = function () {
        var self = this;

        // Click on input → open
        this.input.addEventListener('click', function (e) {
            if (e.target.classList.contains('ms-tag__remove')) return;
            self.toggle();
        });

        // Typing in search box
        if (this.searchEl) {
            this.searchEl.addEventListener('input', function () {
                self._search = this.value.toLowerCase();
                self._filter();
            });
            this.searchEl.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { self.close(); }
            });
        }

        // Click option
        this.options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                self._toggle(opt.dataset.value);
            });
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!self.wrapper.contains(e.target)) { self.close(); }
        });

        // Keyboard: Escape closes, Enter on wrapper opens
        this.wrapper.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { self.close(); }
            if (e.key === 'Enter' && !self._open) { self.open(); }
        });
    };

    App.MultiSelect.prototype._syncFromNative = function () {
        var self = this;
        var vals  = [];
        this.nativeSelect.querySelectorAll('option:checked').forEach(function (opt) {
            vals.push(opt.value);
        });
        vals.forEach(function (v) { self._add(v, false); });
    };

    App.MultiSelect.prototype._filter = function () {
        var self = this;
        this.options.forEach(function (opt) {
            var label = opt.textContent.toLowerCase();
            opt.style.display = (self._search === '' || label.indexOf(self._search) > -1) ? '' : 'none';
        });
    };

    App.MultiSelect.prototype.toggle = function () {
        if (this._open) { this.close(); } else { this.open(); }
    };

    App.MultiSelect.prototype.open = function () {
        this._open = true;
        this.wrapper.classList.add('is-open');
        if (this.searchEl) { this.searchEl.focus(); }
    };

    App.MultiSelect.prototype.close = function () {
        this._open = false;
        this.wrapper.classList.remove('is-open');
        this._search = '';
        if (this.searchEl) { this.searchEl.value = ''; }
        this._filter();
    };

    App.MultiSelect.prototype._toggle = function (value) {
        var idx = this._selected.indexOf(value);
        if (idx > -1) {
            this._remove(value);
        } else {
            this._add(value);
        }
        this._syncNative();
    };

    App.MultiSelect.prototype._add = function (value, doSync) {
        if (doSync === undefined) { doSync = true; }
        if (this._selected.indexOf(value) > -1) return;
        this._selected.push(value);

        // Update option visual
        var opt = this.dropdown.querySelector('[data-value="' + value + '"]');
        if (opt) { opt.classList.add('is-selected'); }

        // Render tag
        var tag = document.createElement('span');
        tag.className = 'ms-tag';
        tag.dataset.value = value;

        var label = opt ? opt.textContent.trim() : value;
        tag.innerHTML = App.esc(label) +
            '<button type="button" class="ms-tag__remove" aria-label="Remove ' + App.esc(label) + '">' +
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">' +
            '<line x1="2" y1="2" x2="8" y2="8"/><line x1="8" y1="2" x2="2" y2="8"/></svg>' +
            '</button>';

        tag.querySelector('.ms-tag__remove').addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            self._remove(value);
            self._syncNative();
        }.bind(this));

        var self = this;
        // Insert before the search input
        var searchEl = this.input.querySelector('.ms-search');
        if (searchEl) {
            this.input.insertBefore(tag, searchEl);
        } else {
            this.input.appendChild(tag);
        }
    };

    App.MultiSelect.prototype._remove = function (value) {
        this._selected.splice(this._selected.indexOf(value), 1);

        // Update option visual
        var opt = this.dropdown.querySelector('[data-value="' + value + '"]');
        if (opt) { opt.classList.remove('is-selected'); }

        // Remove tag element
        var tag = this.input.querySelector('.ms-tag[data-value="' + value + '"]');
        if (tag) { tag.parentNode.removeChild(tag); }
    };

    App.MultiSelect.prototype._syncNative = function () {
        var self = this;
        this.nativeSelect.querySelectorAll('option').forEach(function (opt) {
            opt.selected = self._selected.indexOf(opt.value) > -1;
        });
        // A native <select> fires input/change when the user picks an option
        // directly; setting .selected in JS does not. Pages that live-filter
        // on 'input' (e.g. stock-ledger.js) need this dispatched explicitly,
        // or toggling a checkbox here silently does nothing until some other
        // field's real input event happens to fire.
        this.nativeSelect.dispatchEvent(new Event('input', { bubbles: true }));
        this.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Auto-init every .ms-wrapper on the page
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ms-wrapper').forEach(function (wrapper) {
            new App.MultiSelect(wrapper);
        });
    });

})();

/* ============================================================
   ROW ACTIONS — per-row "more actions" overflow menu
   Markup: .row-actions > button.row-actions__trigger + .row-actions__menu
   Auto-wired for every .row-actions on the page; no manual init needed.
   ============================================================ */
(function () {
    'use strict';

    function closeMenu(menu, trigger) {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function closeAllExcept(exceptMenu) {
        document.querySelectorAll('.row-actions__menu').forEach(function (menu) {
            if (menu !== exceptMenu && !menu.hidden) {
                var trigger = menu.previousElementSibling;
                if (trigger) { closeMenu(menu, trigger); }
            }
        });
    }

    // Positions the menu with fixed coordinates from the trigger's own
    // bounding rect (viewport-relative, so it escapes any scrollable
    // ancestor's clipping — see the CSS comment on .row-actions__menu) and
    // flips above the trigger when there isn't room below.
    function positionMenu(menu, trigger) {
        var rect = trigger.getBoundingClientRect();
        menu.style.left = 'auto';
        menu.style.top = (rect.bottom + 4) + 'px';
        menu.style.right = (window.innerWidth - rect.right) + 'px';

        // Measure once visible, then flip above the trigger if it would
        // overflow the bottom of the viewport.
        var menuRect = menu.getBoundingClientRect();
        if (menuRect.bottom > window.innerHeight) {
            menu.style.top = (rect.top - menuRect.height - 4) + 'px';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.row-actions__trigger').forEach(function (trigger) {
            var menu = trigger.nextElementSibling;
            if (!menu || !menu.classList.contains('row-actions__menu')) { return; }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var willOpen = menu.hidden;
                closeAllExcept(menu);
                if (willOpen) {
                    menu.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    positionMenu(menu, trigger);
                } else {
                    closeMenu(menu, trigger);
                }
            });
        });

        document.addEventListener('click', function () { closeAllExcept(null); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeAllExcept(null); }
        });
        // A scroll or resize invalidates the fixed coordinates computed at
        // open time — simplest correct behavior is to close, not re-track.
        window.addEventListener('scroll', function () { closeAllExcept(null); }, true);
        window.addEventListener('resize', function () { closeAllExcept(null); });
    });
})();


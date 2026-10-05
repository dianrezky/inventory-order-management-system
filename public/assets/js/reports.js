(function () {
    'use strict';

    function toIsoDate(d) {
        return d.toISOString().split('T')[0];
    }

    function today() { return toIsoDate(new Date()); }

    function firstDayOfMonth() {
        const d = new Date();
        // Day 2 is used because toISOString() shifts to UTC and could roll back to the previous month.
        return toIsoDate(new Date(d.getFullYear(), d.getMonth(), 2));
    }

    App.ready(function () {
        // ─── Auto-fill date defaults ───────────────────────────────────────────
        const dateInputs = [
            { from: '#ledger-from', to: '#ledger-to' },
            { from: '#order-from',   to: '#order-to'   },
        ];

        dateInputs.forEach(function (pair) {
            const fromEl = App.$(pair.from);
            const toEl   = App.$(pair.to);
            if (!fromEl || !toEl) return;

            if (!fromEl.value) fromEl.value = firstDayOfMonth();
            if (!toEl.value)   toEl.value   = today();

            // Prevent "from > to" validation
            fromEl.addEventListener('change', function () {
                fromEl.setCustomValidity('');
                if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
                    fromEl.setCustomValidity('From date must not be after To date');
                    fromEl.reportValidity();
                }
            });
            toEl.addEventListener('change', function () {
                toEl.setCustomValidity('');
                if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
                    toEl.setCustomValidity('To date must not be before From date');
                    toEl.reportValidity();
                }
            });
        });

        // ─── Loading state on submit ─────────────────────────────────────────
        App.$$('.report-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                const btn = form.querySelector('button[type="submit"]');
                if (!btn) return;
                btn.disabled = true;
                const orig = btn.textContent.trim();
                btn.textContent = 'Generating CSV…';
                // Re-enable after 30s as fallback (e.g. download didn't trigger)
                setTimeout(function () {
                    if (btn.disabled) {
                        btn.disabled = false;
                        btn.textContent = orig;
                    }
                }, 30000);
            });
        });

        // ─── Order export preview ────────────────────────────────────────────
        // Loads the same rows/columns the CSV contains; the download itself is
        // still the form's Export button.
        const previewBtn = App.$('#order-preview-btn');
        const previewBox = App.$('#order-preview');
        const filterForm = App.$('#report-filter-form');
        if (previewBtn && previewBox && filterForm) {
            const head = App.$('#order-preview-head');
            const body = App.$('#order-preview-body');
            const counter = App.$('#order-preview-counter');
            const info = App.$('#order-preview-info');
            const prevBtn = App.$('#order-preview-prev');
            const nextBtn = App.$('#order-preview-next');
            const numericCols = [6, 7];
            let current = { page: 1, totalPages: 1 };

            const cell = function (tag, text, colIndex) {
                const el = document.createElement(tag);
                el.textContent = text;
                if (numericCols.indexOf(colIndex) !== -1) el.style.textAlign = 'right';
                return el;
            };

            const showMessage = function (text, isError) {
                head.replaceChildren();
                body.replaceChildren();
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 9;
                td.textContent = text;
                td.style.cssText = 'text-align:center;padding:var(--space-6);color:' + (isError ? '#DC2626' : 'var(--color-text-secondary)');
                tr.appendChild(td);
                body.appendChild(tr);
                counter.textContent = '';
                info.textContent = '';
                prevBtn.disabled = true;
                nextBtn.disabled = true;
            };

            const render = function (data) {
                current = { page: data.page, totalPages: data.totalPages };
                head.replaceChildren();
                const hr = document.createElement('tr');
                data.headers.forEach(function (h, i) {
                    const th = cell('th', h, i);
                    th.scope = 'col';
                    hr.appendChild(th);
                });
                head.appendChild(hr);

                body.replaceChildren();
                if (data.rows.length === 0) {
                    showMessage('No orders found in the selected date range.', false);
                    return;
                }
                data.rows.forEach(function (row) {
                    const tr = document.createElement('tr');
                    row.forEach(function (v, i) { tr.appendChild(cell('td', v, i)); });
                    body.appendChild(tr);
                });

                const start = (data.page - 1) * data.perPage + 1;
                const end = start + data.rows.length - 1;
                counter.textContent = 'SHOWING ' + start + '-' + end + ' OF ' + data.total + ' ROWS';
                info.textContent = 'Page ' + data.page + ' of ' + data.totalPages;
                prevBtn.disabled = data.page <= 1;
                nextBtn.disabled = data.page >= data.totalPages;
            };

            const load = async function (page) {
                const fd = new FormData(filterForm);
                fd.set('page', String(page));
                previewBox.hidden = false;
                previewBtn.disabled = true;
                showMessage('Loading preview…', false);
                try {
                    const res = await App.api('/reports/preview/orders', { method: 'POST', body: fd });
                    if (res.ok && res.data && Array.isArray(res.data.rows)) {
                        render(res.data);
                    } else {
                        showMessage(typeof res.error === 'string' && res.error.length < 200 ? res.error : 'Could not load the preview.', true);
                    }
                } catch (e) {
                    showMessage('Could not load the preview.', true);
                } finally {
                    previewBtn.disabled = false;
                }
            };

            previewBtn.addEventListener('click', function () { load(1); });
            prevBtn.addEventListener('click', function () { if (current.page > 1) load(current.page - 1); });
            nextBtn.addEventListener('click', function () { if (current.page < current.totalPages) load(current.page + 1); });
        }
    });

    window.ReportsPage = { init: function () {} };
})();

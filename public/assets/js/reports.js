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
    });

    window.ReportsPage = { init: function () {} };
})();

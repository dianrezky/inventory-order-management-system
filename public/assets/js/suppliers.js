(function () {
    'use strict';

    async function submitStatusForm(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '…'; }

        try {
            const fd = new FormData(form);
            const result = await App.api(form.action, { method: 'POST', body: fd });

            if (result.ok) {
                App.toast('Status updated successfully.', 'success');
                window.location.reload();
            } else {
                App.toast(result.error || 'Failed to update status.', 'error');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.originalText || '…'; }
            }
        } catch (_) {
            App.toast('An unexpected error occurred.', 'error');
            if (submitBtn) { submitBtn.disabled = false; }
        }
    }

    App.ready(function () {
        App.$$('form[data-ajax-status]').forEach(function (form) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.dataset.originalText = btn.textContent.trim(); }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitStatusForm(form);
            });
        });
    });

    window.SuppliersPage = { init: function () {} };
})();

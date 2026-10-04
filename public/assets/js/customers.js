(function () {
    'use strict';

    async function submitStatusForm(form) {
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '…'; }

        try {
            var fd = new FormData(form);
            var result = await App.api(form.action, { method: 'POST', body: fd });

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
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.dataset.originalText = btn.textContent.trim(); }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitStatusForm(form);
            });
        });
    });

    window.CustomersPage = { init: function () {} };
})();

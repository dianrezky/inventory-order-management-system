(function () {
    'use strict';

    const USER = {};

    async function submitStatusForm(form) {
        // A full reload on success is intentional: the row markup is rendered server-side.
        const action = form.action;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent.trim() : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '…';
        }

        try {
            const fd = new FormData(form);
            const result = await App.api(action, { method: 'POST', body: fd });

            if (result.ok) {
                App.toast('Status updated successfully.', 'success');
                window.location.reload();
            } else {
                App.toast(result.error || 'Failed to update status.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        } catch (err) {
            App.toast('An unexpected error occurred.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    App.ready(function () {
        // Wire up all status forms to AJAX
        App.$$('form[data-ajax-status]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitStatusForm(form);
            });
        });
    });

    USER.init = function () {};
    window.UsersPage = USER;
})();

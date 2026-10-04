(function () {
    'use strict';

    var USER = {};

    async function submitStatusForm(form) {
        // A full reload on success is intentional: the row markup is rendered server-side.
        var action = form.action;
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn ? submitBtn.textContent.trim() : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '…';
        }

        try {
            var fd = new FormData(form);
            var result = await App.api(action, { method: 'POST', body: fd });

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

(function () {
    'use strict';

    // ─── Activate / Deactivate via AJAX

    async function submitStatusForm(form) {
        const action = form.action;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent.trim() : '';

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '…'; }

        try {
            const fd = new FormData(form);
            const result = await App.api(action, { method: 'POST', body: fd });

            if (result.ok) {
                App.toast('Status updated successfully.', 'success');
                window.location.reload();
            } else {
                App.toast(result.error || 'Failed to update status.', 'error');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
            }
        } catch (_) {
            App.toast('An unexpected error occurred.', 'error');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
        }
    }

    App.ready(function () {
        App.$$('form[data-ajax-status]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitStatusForm(form);
            });
        });

        // Product availability checker (detail / SKU lookup page)
        const skuInput = App.$('[data-sku-check]');
        const stockDisplay = App.$('[data-stock-display]');
        if (skuInput && stockDisplay) {
            skuInput.addEventListener('input', function () {
                const sku = skuInput.value.trim();
                if (!sku) { stockDisplay.textContent = ''; return; }
                App.api('/api/products/' + encodeURIComponent(sku) + '/availability')
                    .then(function (r) {
                        if (r.ok && typeof r.data === 'object' && r.data.available !== undefined) {
                            if (r.data.available) {
                                stockDisplay.innerHTML =
                                    '<span class="badge badge--active">In Stock: ' + r.data.total_stock + ' ' + App.esc(r.data.unit || '') + '</span>';
                            } else {
                                stockDisplay.innerHTML =
                                    '<span class="badge badge--inactive">Out of Stock</span>';
                            }
                        }
                    })
                    .catch(function () { stockDisplay.textContent = ''; });
            });
        }
    });

    window.ProductsPage = { init: function () {} };
})();

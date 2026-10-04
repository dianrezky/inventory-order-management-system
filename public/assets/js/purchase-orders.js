(function () {
    'use strict';

    // ─── Line Item Row Management ────────────────────────────────────────────

    function addItemRow() {
        // New rows are cloned from the first row, so its markup is the template.
        var body = document.getElementById('po-items-body');
        if (!body) return;

        var first = body.querySelector('.po-item-row');
        if (!first) return;

        var clone = first.cloneNode(true);
        // Clear inputs, reset selects
        clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
        clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
        body.appendChild(clone);

        // Focus the product select of the new row
        var newSelect = clone.querySelector('select[name="item_product_id[]"]');
        if (newSelect) { newSelect.focus(); }
    }

    function removeItemRow(tr) {
        var body = document.getElementById('po-items-body');
        if (!body) return;
        var rows = body.querySelectorAll('.po-item-row');
        if (rows.length > 1) {
            tr.closest('.po-item-row').remove();
        } else {
            App.toast('At least one line item is required.', 'info');
        }
    }

    function handleProductSelectChange(select) {
        var option = select.options[select.selectedIndex];
        var row = select.closest('.po-item-row');
        if (!row) return;

        var priceInput = row.querySelector('input[name="item_purchase_price[]"]');
        if (!priceInput) return;

        // Only pre-fill from data-price when the user has not typed a price yet.
        if (option && option.dataset.price && !priceInput.value) {
            priceInput.value = option.dataset.price;
        }
    }

    // ─── Form Validation ─────────────────────────────────────────────────────

    function validateForm() {
        // At least one row must have both a product and a positive quantity.
        var body = document.getElementById('po-items-body');
        if (!body) return true;

        var rows = body.querySelectorAll('.po-item-row');
        var valid = false;

        rows.forEach(function (row) {
            var product = row.querySelector('select[name="item_product_id[]"]');
            var qty = row.querySelector('input[name="item_qty_ordered[]"]');
            if (product && product.value && qty && parseFloat(qty.value) > 0) {
                valid = true;
            }
        });

        if (!valid) {
            App.toast('Please add at least one line item with product and quantity.', 'error');
            return false;
        }
        return true;
    }

    // ─── Submit handler ──────────────────────────────────────────────────────

    function handleFormSubmit(e) {
        if (!validateForm()) {
            e.preventDefault();
            return;
        }
        var btn = e.target.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    App.ready(function () {
        var body = document.getElementById('po-items-body');
        var addBtn = document.getElementById('po-item-add');
        var form = document.getElementById('po-form');

        // Add row
        if (addBtn) {
            addBtn.addEventListener('click', addItemRow);
        }

        // Remove row (delegated)
        if (body) {
            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('po-item-remove') ||
                    e.target.closest('.po-item-remove')) {
                    removeItemRow(e.target);
                }
            });

            // Auto-fill price on product change
            body.addEventListener('change', function (e) {
                if (e.target.tagName === 'SELECT' && e.target.name === 'item_product_id[]') {
                    handleProductSelectChange(e.target);
                }
            });
        }

        // Form submit validation
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        // PO detail page: approve/submit/receive buttons
        document.querySelectorAll('[data-po-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var action = btn.dataset.poAction;
                var msg = btn.dataset.poConfirm || ('Are you sure you want to ' + action + ' this purchase order?');
                App.confirm(msg, action.charAt(0).toUpperCase() + action.slice(1)).then(function (ok) {
                    if (!ok) return;
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = btn.href || btn.dataset.poHref;
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    });

    window.PurchaseOrdersPage = {
        addItemRow: addItemRow,
        removeItemRow: removeItemRow,
        init: function () {},
    };
})();

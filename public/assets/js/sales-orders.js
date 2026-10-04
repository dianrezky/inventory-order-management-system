(function () {
    'use strict';

    // ─── Product list (injected by server in form.php via json_encode) ────────

    // Each entry: {id, name, sku, unit, sale_price}.
    var productList = window._soProductList || [];

    // ─── HTML Helpers ────────────────────────────────────────────────────────

    function esc(s) {
        return App.esc(String(s));
    }

    function buildRow(selectedId) {
        // selectedId pre-selects a product; pass '' for an empty row.
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' +
                '<select name="item_product_id[]" class="input" required>' +
                    '<option value="">—</option>' +
                    productList.map(function (p) {
                        return '<option value="' + p.id + '"' +
                            (String(p.id) === String(selectedId) ? ' selected' : '') +
                            ' data-sku="' + esc(p.sku) + '"' +
                            ' data-unit="' + esc(p.unit) + '"' +
                            ' data-price="' + p.sale_price + '">' +
                            esc(p.name) + '</option>';
                    }).join('') +
                '</select>' +
            '</td>' +
            '<td class="item-sku"></td>' +
            '<td class="item-unit"></td>' +
            '<td><input type="number" name="item_qty[]" class="input input--sm" min="1" value="1" placeholder="0" required></td>' +
            '<td><input type="number" name="item_sale_price[]" class="input input--md" step="0.01" min="0" value="" placeholder="0.00" required></td>' +
            '<td><button type="button" class="btn btn--tertiary so-item-remove" aria-label="Remove row">' +
                '<svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-trash-2"></use></svg>' +
            '</button></td>';
        return tr;
    }

    function onProductChange(select) {
        var tr = select.closest('tr');
        if (!tr) return;
        var opt = select.options[select.selectedIndex];
        var skuCell = tr.querySelector('.item-sku');
        var unitCell = tr.querySelector('.item-unit');
        var priceInput = tr.querySelector('input[name="item_sale_price[]"]');

        if (skuCell) skuCell.textContent = opt && opt.dataset.sku ? opt.dataset.sku : '';
        if (unitCell) unitCell.textContent = opt && opt.dataset.unit ? opt.dataset.unit : '';
        if (priceInput && opt && opt.dataset.price && !priceInput.value) {
            priceInput.value = opt.dataset.price;
        }
    }

    function addItemRow() {
        var tbody = document.getElementById('items-body');
        if (!tbody) return;
        tbody.appendChild(buildRow(''));
        // Focus the new select
        var newRow = tbody.lastElementChild;
        var sel = newRow && newRow.querySelector('select');
        if (sel) sel.focus();
    }

    function removeItemRow(tr) {
        var tbody = document.getElementById('items-body');
        if (!tbody) return;
        // The form must always keep at least one line-item row.
        if (tbody.querySelectorAll('tr').length <= 1) {
            App.toast('At least one line item is required.', 'info');
            return;
        }
        tr.closest('tr').remove();
    }

    // ─── Form Validation ─────────────────────────────────────────────────────

    function validateForm() {
        var tbody = document.getElementById('items-body');
        if (!tbody) return true;

        var valid = false;
        tbody.querySelectorAll('tr').forEach(function (row) {
            var sel = row.querySelector('select[name="item_product_id[]"]');
            var qty = row.querySelector('input[name="item_qty[]"]');
            if (sel && sel.value && qty && parseFloat(qty.value) > 0) {
                valid = true;
            }
        });

        if (!valid) {
            App.toast('Please add at least one line item with product and quantity.', 'error');
            return false;
        }
        return true;
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    App.ready(function () {
        var tbody = document.getElementById('items-body');
        var addBtn = document.getElementById('add-item-btn');
        var form = document.getElementById('so-form');

        // Add row button
        if (addBtn) {
            addBtn.addEventListener('click', addItemRow);
        }

        // Initial rows: re-create what the user entered when the server re-rendered
        // the form after a validation error (window._soOldItems), else one blank row.
        var oldItems = window._soOldItems || [];
        if (tbody && tbody.children.length === 0) {
            if (oldItems.length > 0) {
                oldItems.forEach(function (item) {
                    var tr = buildRow(item.product_id);
                    tr.querySelector('input[name="item_qty[]"]').value = item.qty;
                    tr.querySelector('input[name="item_sale_price[]"]').value = item.sale_price;
                    tbody.appendChild(tr);
                    onProductChange(tr.querySelector('select[name="item_product_id[]"]'));
                });
            } else {
                tbody.appendChild(buildRow(''));
            }
        }

        // Delegated change: product select → fill cells
        if (tbody) {
            tbody.addEventListener('change', function (e) {
                if (e.target.name === 'item_product_id[]') {
                    onProductChange(e.target);
                }
            });

            // Delegated click: remove row
            tbody.addEventListener('click', function (e) {
                var removeBtn = e.target.closest('.so-item-remove');
                if (removeBtn) {
                    removeItemRow(e.target);
                }
            });
        }

        // Form validation on submit
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!validateForm()) {
                    e.preventDefault();
                    var btn = form.querySelector('button[type="submit"]');
                    if (btn) { btn.disabled = false; }
                    return;
                }
                var btn = form.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
            });
        }

        // SO detail page action buttons
        document.querySelectorAll('[data-so-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var action = btn.dataset.soAction;
                var msg = btn.dataset.soConfirm ||
                    ('Are you sure you want to ' + action + ' this sales order?');
                App.confirm(msg, action.charAt(0).toUpperCase() + action.slice(1)).then(function (ok) {
                    if (!ok) return;
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = btn.href || btn.dataset.soHref;
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    });

    window.SalesOrdersPage = {
        buildRow: buildRow,
        addItemRow: addItemRow,
        removeItemRow: removeItemRow,
        init: function () {},
    };
})();

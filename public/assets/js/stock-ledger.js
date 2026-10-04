(function () {
    'use strict';

    // Active sort survives filter changes and paging; null = server default order.
    var sortState = { col: null, dir: null };
    // Only the newest request may paint the table — a slow earlier response
    // arriving late must not overwrite the result of a newer filter.
    var requestSeq = 0;

    // Every reload sends the full state (filters + sort + page) so no action
    // silently drops what the others set.
    function currentState(page) {
        var filterForm = document.getElementById('ledger-filter-form');
        var data = filterForm ? new FormData(filterForm) : new FormData();
        return {
            sku: data.get('sku') || '',
            product_name: data.get('product_name') || '',
            movement_type: data.getAll('movement_type[]'),
            warehouse_id: data.getAll('warehouse_id[]'),
            sort_col: sortState.col || '',
            sort_dir: sortState.dir || '',
            page: page,
        };
    }

    async function loadLedger(params) {
        // params accepts sku, product_name, movement_type[], warehouse_id[], sort_col, sort_dir and page.
        // Posted as a normal form body — filter/sort/pagination state never rides
        // in the URL (the request always targets the plain /stock-ledger path).
        var loadingEl = document.getElementById('ledger-loading');
        var seq = ++requestSeq;

        if (loadingEl) loadingEl.style.display = 'block';

        try {
            var body = buildFormData(params);

            var result = await App.api('/stock-ledger', { method: 'POST', body: body });
            if (seq !== requestSeq) return;

            if (result.ok && result.data && result.data.tbody !== undefined) {
                var tbody = document.getElementById('ledger-tbody');
                if (tbody) {
                    tbody.innerHTML = result.data.tbody;
                }
                updatePagination(result.data.page, result.data.totalPages);
                // Keep the header count in step with the filtered result.
                var totalEl = document.getElementById('ledger-total');
                if (totalEl && result.data.total !== undefined) {
                    var n = parseInt(result.data.total, 10) || 0;
                    totalEl.textContent = n + ' movement' + (n === 1 ? '' : 's') + ' recorded';
                }
                App.toast('Stock ledger updated.', 'success', 2000);
            } else {
                App.toast(result.error || 'Failed to load stock ledger.', 'error');
            }
        } catch (_) {
            if (seq === requestSeq) App.toast('Network error loading stock ledger.', 'error');
        } finally {
            if (loadingEl && seq === requestSeq) loadingEl.style.display = 'none';
        }
    }

    /**
     * Build a FormData body, correctly handling scalar and array values.
     * Arrays are appended as repeated name[] entries.
     */
    function buildFormData(params) {
        var fd = new FormData();
        for (var key in params) {
            if (!Object.prototype.hasOwnProperty.call(params, key)) continue;
            var val = params[key];
            if (!val || (Array.isArray(val) && val.length === 0)) continue;
            if (Array.isArray(val)) {
                val.forEach(function (v) {
                    if (v !== '') { fd.append(key + '[]', v); }
                });
            } else {
                fd.append(key, val);
            }
        }
        return fd;
    }

    function updatePagination(currentPage, totalPages) {
        var nav = document.getElementById('ledger-pagination');
        var prev = document.getElementById('ledger-prev');
        var next = document.getElementById('ledger-next');
        var info = document.getElementById('ledger-page-info');

        // The nav is always in the DOM so a later filter can bring it back.
        if (nav) nav.hidden = totalPages <= 1;
        if (prev) {
            prev.disabled = currentPage <= 1;
            prev.dataset.page = currentPage;
        }
        if (next) {
            next.disabled = currentPage >= totalPages;
            next.dataset.page = currentPage;
        }
        if (info) info.textContent = 'Page ' + currentPage + ' of ' + totalPages;
    }

    function bindSortHandlers() {
        App.$$('[data-sort-col]').forEach(function (th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function () {
                var col = th.dataset.sortCol;
                var dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                th.dataset.sortDir = dir;
                // Update arrow indicators
                App.$$('[data-sort-col]').forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
                th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');

                sortState.col = col;
                sortState.dir = dir;
                loadLedger(currentState(1));
            });
        });
    }

    App.ready(function () {
        var filterForm = document.getElementById('ledger-filter-form');

        if (filterForm) {
            // Debounce helper
            var debounceTimer = null;
            function debounce(fn, delay) {
                return function () {
                    var args = arguments;
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function () { fn.apply(null, args); }, delay);
                };
            }

            // Live filter on input change (after 350ms debounce)
            var filterInputs = filterForm.querySelectorAll('input, select');
            filterInputs.forEach(function (input) {
                input.addEventListener('input', debounce(function () {
                    loadLedger(currentState(1));
                }, 350));
            });

            filterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                loadLedger(currentState(1));
            });
        }

        // Pagination buttons
        var prevBtn = document.getElementById('ledger-prev');
        var nextBtn = document.getElementById('ledger-next');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                var cur = parseInt(prevBtn.dataset.page || '1', 10);
                loadLedger(currentState(cur - 1));
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                var cur = parseInt(nextBtn.dataset.page || '1', 10);
                loadLedger(currentState(cur + 1));
            });
        }

        // Sort column headers
        bindSortHandlers();
    });

    window.StockLedgerPage = { loadLedger: loadLedger };
})();

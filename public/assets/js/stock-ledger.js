(function () {
    'use strict';

    // Active sort survives filter changes and paging; null = server default order.
    const sortState = { col: null, dir: null };
    // Only the newest request may paint the table — a slow earlier response
    // arriving late must not overwrite the result of a newer filter.
    let requestSeq = 0;

    // Every reload sends the full state (filters + sort + page) so no action
    // silently drops what the others set.
    function currentState(page) {
        const filterForm = document.getElementById('ledger-filter-form');
        const data = filterForm ? new FormData(filterForm) : new FormData();
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
        const loadingEl = document.getElementById('ledger-loading');
        const seq = ++requestSeq;

        if (loadingEl) loadingEl.style.display = 'block';

        try {
            const body = buildFormData(params);

            const result = await App.api('/stock-ledger', { method: 'POST', body: body });
            if (seq !== requestSeq) return;

            if (result.ok && result.data?.tbody !== undefined) {
                const tbody = document.getElementById('ledger-tbody');
                if (tbody) {
                    tbody.innerHTML = result.data.tbody;
                }
                updatePagination(result.data.page, result.data.totalPages);
                // Keep the header count in step with the filtered result.
                const totalEl = document.getElementById('ledger-total');
                if (totalEl && result.data.total !== undefined) {
                    const n = Number.parseInt(result.data.total, 10) || 0;
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
        const fd = new FormData();
        for (const key in params) {
            if (!Object.hasOwn(params, key)) continue;
            const val = params[key];
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
        const nav = document.getElementById('ledger-pagination');
        const prev = document.getElementById('ledger-prev');
        const next = document.getElementById('ledger-next');
        const info = document.getElementById('ledger-page-info');

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
                const col = th.dataset.sortCol;
                const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
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
        const filterForm = document.getElementById('ledger-filter-form');

        if (filterForm) {
            // Debounce helper
            let debounceTimer = null;
            function debounce(fn, delay) {
                return function () {
                    const args = arguments;
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function () { fn(...args); }, delay);
                };
            }

            // Live filter on input change (after 350ms debounce)
            const filterInputs = filterForm.querySelectorAll('input, select');
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
        const prevBtn = document.getElementById('ledger-prev');
        const nextBtn = document.getElementById('ledger-next');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                const cur = Number.parseInt(prevBtn.dataset.page || '1', 10);
                loadLedger(currentState(cur - 1));
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                const cur = Number.parseInt(nextBtn.dataset.page || '1', 10);
                loadLedger(currentState(cur + 1));
            });
        }

        // Sort column headers
        bindSortHandlers();
    });

    window.StockLedgerPage = { loadLedger: loadLedger };
})();

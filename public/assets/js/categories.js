(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Legacy: row-level activate/deactivate forms (data-ajax-status),
    // still used by the Category detail page (views/master/categories/detail.php).
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Categories list: Add/Edit modal + Delete confirmation
    // ---------------------------------------------------------------
    var FIELD_KEYWORDS = [
        { field: 'name', keywords: ['name'] },
        { field: 'code', keywords: ['code'] },
        { field: 'description', keywords: ['description'] },
    ];

    function initCategoriesModal() {
        var backdrop = document.getElementById('category-modal-backdrop');
        if (!backdrop) { return; } // not rendered for non-admin roles

        var panel = document.getElementById('category-modal-panel');
        var form = document.getElementById('category-form');
        var title = document.getElementById('modal-category-title');
        var idField = document.getElementById('category-id');
        var codeField = document.getElementById('category-code-input');
        var nameField = document.getElementById('category-name-input');
        var descField = document.getElementById('category-description-input');
        var statusField = document.getElementById('category-status-input');
        var errorBanner = document.getElementById('category-form-error');
        var saveBtn = document.getElementById('category-modal-save');
        var saveBtnOriginalText = saveBtn.textContent.trim();
        var lastFocusedTrigger = null;

        var fieldEls = {
            code: codeField,
            name: nameField,
            description: descField,
        };

        function clearErrors() {
            errorBanner.hidden = true;
            errorBanner.textContent = '';
            Object.keys(fieldEls).forEach(function (key) {
                var el = fieldEls[key];
                var errEl = document.getElementById('category-' + key + '-error');
                el.removeAttribute('aria-invalid');
                if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
            });
        }

        function showError(message) {
            var matched = FIELD_KEYWORDS.find(function (entry) {
                return entry.keywords.some(function (kw) { return message.toLowerCase().indexOf(kw) !== -1; });
            });

            if (matched && fieldEls[matched.field]) {
                var el = fieldEls[matched.field];
                var errEl = document.getElementById('category-' + matched.field + '-error');
                el.setAttribute('aria-invalid', 'true');
                if (errEl) {
                    errEl.hidden = false;
                    errEl.textContent = message;
                    el.setAttribute('aria-describedby', errEl.id);
                }
                el.focus();
            } else {
                // 12_MUTATION_ERROR: no specific field to blame — banner only.
                errorBanner.hidden = false;
                errorBanner.innerHTML = '<svg width="16" height="16" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">' +
                    '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>' +
                    '<span>' + App.esc(message) + '</span>';
            }
        }

        function getFocusable() {
            return Array.from(panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));
        }

        function trapFocus(e) {
            if (e.key !== 'Tab') { return; }
            var focusable = getFocusable();
            if (focusable.length === 0) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        function onKeydown(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeModal();
            } else {
                trapFocus(e);
            }
        }

        function openModal(mode, data) {
            clearErrors();
            form.reset();
            lastFocusedTrigger = document.activeElement;

            if (mode === 'edit' && data) {
                title.textContent = 'Edit Category: ' + data.name;
                idField.value = data.id;
                codeField.value = data.code || '';
                nameField.value = data.name || '';
                descField.value = data.description || '';
                statusField.value = data.status || 'active';
            } else {
                title.textContent = 'Add New Category';
                idField.value = '';
                statusField.value = 'active';
            }

            backdrop.hidden = false;
            document.addEventListener('keydown', onKeydown, true);
            nameField.focus();
        }

        function closeModal() {
            backdrop.hidden = true;
            document.removeEventListener('keydown', onKeydown, true);
            if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
                lastFocusedTrigger.focus();
            }
        }

        document.getElementById('btn-add-category')?.addEventListener('click', function () {
            openModal('add', null);
        });

        App.$$('.js-edit-category').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal('edit', {
                    id: btn.dataset.id,
                    code: btn.dataset.code,
                    name: btn.dataset.name,
                    description: btn.dataset.description,
                    status: btn.dataset.status,
                });
            });
        });

        document.getElementById('category-modal-close').addEventListener('click', closeModal);
        document.getElementById('category-modal-cancel').addEventListener('click', closeModal);
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) { closeModal(); }
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearErrors();

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving Category...';

            var id = idField.value;
            var url = id ? ('/categories/' + id + '/update') : '/categories';
            var fd = new FormData(form);

            try {
                var result = await App.api(url, { method: 'POST', body: fd });

                if (result.ok && result.data && result.data.ok) {
                    App.toast(result.data.message || 'Category saved.', 'success');
                    closeModal();
                    window.location.reload();
                } else {
                    var message = (result.data && result.data.error) || result.error || 'Could not save the category.';
                    showError(message);
                }
            } catch (_) {
                showError('An unexpected error occurred. Please try again.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = saveBtnOriginalText;
            }
        });

        // Auto-open after /categories/create or /categories/{id}/edit redirected here (see list.php).
        var pageData = window.CategoriesPageData || {};
        if (pageData.autoOpenAdd) {
            openModal('add', null);
        } else if (pageData.autoEditCategory) {
            // Server supplies the record itself, so this works even when that
            // category's row isn't on the current page.
            openModal('edit', pageData.autoEditCategory);
        }
    }

    function initDeleteDialog() {
        var backdrop = document.getElementById('delete-modal-backdrop');
        if (!backdrop) { return; }

        var message = document.getElementById('delete-modal-message');
        var confirmBtn = document.getElementById('delete-modal-confirm');
        var cancelBtn = document.getElementById('delete-modal-cancel');
        var lastFocusedTrigger = null;
        var pendingId = null;

        function open(id, name) {
            pendingId = id;
            message.textContent = 'Delete "' + name + '"? This cannot be undone.';
            lastFocusedTrigger = document.activeElement;
            backdrop.hidden = false;
            document.addEventListener('keydown', onKeydown, true);
            // 15_CONFIRMATION: initial focus on Cancel, not the destructive action.
            cancelBtn.focus();
        }

        function close() {
            backdrop.hidden = true;
            pendingId = null;
            document.removeEventListener('keydown', onKeydown, true);
            if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
                lastFocusedTrigger.focus();
            }
        }

        function onKeydown(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        }

        App.$$('.js-delete-category').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) { return; }
                open(btn.dataset.id, btn.dataset.name);
            });
        });

        cancelBtn.addEventListener('click', close);
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) { close(); }
        });

        confirmBtn.addEventListener('click', async function () {
            if (!pendingId) { return; }

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Deleting...';

            try {
                var result = await App.api('/categories/' + pendingId + '/delete', { method: 'POST' });

                if (result.ok && result.data && result.data.ok) {
                    App.toast(result.data.message || 'Category deleted.', 'success');
                    close();
                    window.location.reload();
                } else {
                    var msg = (result.data && result.data.error) || result.error || 'Could not delete the category.';
                    App.toast(msg, 'error');
                    close();
                }
            } catch (_) {
                App.toast('An unexpected error occurred.', 'error');
                close();
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Delete';
            }
        });
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

        initCategoriesModal();
        initDeleteDialog();
    });

    window.CategoriesPage = { init: function () {} };
})();

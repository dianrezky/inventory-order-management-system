<?php

/** @var list<\App\Entity\Category> $categories */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array $metrics */
/** @var string|null $categoryName */
/** @var string|null $categoryCode */
/** @var list<string>|null $statuses */
/** @var string $sort */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$selectedStatuses = $statuses ?? [];
$totalPages = max(1, (int) ceil($total / max($perPage, 1)));
$perPageOptions = [10, 25, 50];

$sortOptions = [
    \App\Service\CategoryService::SORT_NAME_ASC => 'Name (A-Z)',
    \App\Service\CategoryService::SORT_NAME_DESC => 'Name (Z-A)',
    \App\Service\CategoryService::SORT_CODE_ASC => 'Code (A-Z)',
    \App\Service\CategoryService::SORT_NEWEST => 'Newest First',
    \App\Service\CategoryService::SORT_SKUS_DESC => 'Most SKUs',
];

$statusOptions = ['' => 'All Status', 'active' => 'Active', 'inactive' => 'Inactive'];
$currentStatus = count($selectedStatuses) === 1 ? $selectedStatuses[0] : '';
$hasFilter = ($categoryName ?? '') !== '' || ($categoryCode ?? '') !== '';

// /categories/create and /categories/{id}/edit redirect here with a one-shot
// session flag (see CategoryController) that auto-opens the matching modal.
$autoEditCategory = $autoEditCategory ?? null;
$autoOpenAdd = !empty($autoOpenAdd);
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Master Data</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Categories</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Categories</h1>
        <?php if ($isAdmin): ?>
        <div class="page-header__actions">
            <button type="button" class="btn btn--primary" id="btn-add-category">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add Category
            </button>
        </div>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle">
        Organize product catalog classifications, manage category codes, and monitor SKU distributions.
    </p>
</div>

<!-- Summary Metrics Cards -->
<div class="categories-kpi-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Categories</span>
        </div>
        <div class="stat-card__value"><?= number_format((int) $metrics['total'], 0, ',', '.') ?></div>
        <div class="stat-card__sub">Registered in the system</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Active Categories</span>
        </div>
        <div class="stat-card__value"><?= number_format((int) $metrics['active'], 0, ',', '.') ?></div>
        <div class="stat-card__sub">Available for new products</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Assigned SKUs</span>
        </div>
        <div class="stat-card__value"><?= number_format((int) $metrics['totalAssignedSkus'], 0, ',', '.') ?> SKU</div>
        <div class="stat-card__sub">Linked to registered products</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Unassigned / Empty</span>
        </div>
        <div class="stat-card__value"><?= number_format((int) $metrics['empty'], 0, ',', '.') ?></div>
        <div class="stat-card__sub">0 SKU linked (can be deleted)</div>
    </div>
</div>

<!-- Filter & Operational Toolbar -->
<div class="card" style="margin-bottom:var(--space-4);">
    <!-- POST: filter/sort/pagination/per-page/export all submit this one form
         (via the HTML form="category-filter-form" attribute on controls that
         live outside it) so search state never lands in the URL. -->
    <form method="post" action="/categories/search" id="category-filter-form" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="category-name">Category Name</label>
                <input class="input" type="search" id="category-name" name="name"
                       value="<?= htmlspecialchars($categoryName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Category Name…" style="min-height:44px;">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="category-code">Category Code</label>
                <input class="input" type="search" id="category-code" name="code"
                       value="<?= htmlspecialchars($categoryCode ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Category Code…" style="min-height:44px;">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="category-status">Status</label>
                <select id="category-status" name="status" class="input" style="min-height:44px;">
                    <?php foreach ($statusOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-field__label" for="category-sort">Sort</label>
                <select id="category-sort" name="sort" class="input" style="min-height:44px;">
                    <?php foreach ($sortOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $sort === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions form-actions--search-right">
            <?php if (count($categories) === 0 && $total === 0): ?>
            <span id="category-export-disabled-reason" class="text-muted" style="font-size:var(--font-size-label-xs);">
                No categories match the current filters, so there is nothing to export.
            </span>
            <button type="button" class="btn btn--secondary" disabled aria-describedby="category-export-disabled-reason">Export CSV</button>
            <?php else: ?>
            <button type="submit" class="btn btn--secondary" formaction="/categories/export" formmethod="post">Export CSV</button>
            <?php endif; ?>
            <a class="btn btn--secondary" href="/categories">Reset</a>
            <button type="submit" name="page" value="1" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($categories) === 0): ?>
    <div class="empty-state-card" role="status" aria-live="polite">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-list"></use></svg>
        <?php if ($hasFilter): ?>
            <p><strong>No categories match your search criteria</strong></p>
            <p class="empty-state-card__hint">No results match the current filters. Try different search terms.</p>
        <?php else: ?>
            <p><strong>No categories found. Add your first category to get started.</strong></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap" role="status" aria-live="polite">
        <table class="table">
            <thead>
            <tr>
                <th>Code</th>
                <th>Category Name &amp; Description</th>
                <th style="text-align:right;">Assigned SKUs</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th style="width:220px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><span class="category-code"><?= htmlspecialchars($category->code, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <div class="category-name-cell__name"><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($category->description !== null && $category->description !== ''): ?>
                    <div class="category-name-cell__description" title="<?= htmlspecialchars($category->description, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category->description, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <?php if ($category->assignedSkuCount > 0): ?>
                    <form method="post" action="/products/search" style="display:inline;">
                        <input type="hidden" name="category[]" value="<?= (int) $category->id ?>">
                        <button type="submit" class="category-sku-link"><?= number_format($category->assignedSkuCount, 0, ',', '.') ?> SKU</button>
                    </form>
                    <?php else: ?>
                    <span class="category-sku-count--zero">0 SKU</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $category->isActive ? 'badge--active' : 'badge--inactive' ?>">
                        <?= $category->isActive ? 'ACTIVE' : 'INACTIVE' ?>
                    </span>
                </td>
                <td>
                    <span style="font-size:var(--font-size-label-sm);color:var(--color-on-surface-variant);">
                        <?= $category->updatedAt !== null ? htmlspecialchars($category->updatedAt->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-' ?>
                    </span>
                </td>
                <td class="table__actions">
                    <?php if ($isAdmin): ?>
                    <div class="row-actions">
                        <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?>">
                            <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                        </button>
                        <div class="row-actions__menu" role="menu" hidden>
                            <button type="button" class="row-actions__item row-actions__item--edit js-edit-category" role="menuitem"
                                    data-id="<?= htmlspecialchars($idObfuscator->encode($category->id), ENT_QUOTES, 'UTF-8') ?>"
                                    data-code="<?= htmlspecialchars($category->code, ENT_QUOTES, 'UTF-8') ?>"
                                    data-name="<?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-description="<?= htmlspecialchars($category->description ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-status="<?= $category->isActive ? 'active' : 'inactive' ?>">
                                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                Edit
                            </button>
                            <button type="button" class="row-actions__item row-actions__item--destructive js-delete-category" role="menuitem"
                                    data-id="<?= htmlspecialchars($idObfuscator->encode($category->id), ENT_QUOTES, 'UTF-8') ?>"
                                    data-name="<?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-sku-count="<?= (int) $category->assignedSkuCount ?>"
                                    <?= $category->assignedSkuCount > 0 ? 'disabled aria-describedby="cannot-delete-' . (int) $category->id . '"' : '' ?>>
                                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-trash-2"></use></svg>
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php if ($category->assignedSkuCount > 0): ?>
                    <span id="cannot-delete-<?= (int) $category->id ?>" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">
                        Cannot delete category with associated products. Reassign or delete products first.
                    </span>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="pagination-footer">
        <div class="pagination-footer__summary">
            Showing <?= number_format((($page - 1) * $perPage) + 1, 0, ',', '.') ?>
            to <?= number_format(min($page * $perPage, $total), 0, ',', '.') ?>
            of <?= number_format($total, 0, ',', '.') ?> categories
        </div>
        <div class="pagination-footer__controls">
            <div class="pagination-footer__per-page">
                <label for="per-page-select" class="pagination-footer__label">Rows per page:</label>
                <select id="per-page-select" name="per_page" form="category-filter-form" class="input" onchange="this.form.requestSubmit()" style="min-height:44px;padding:6px 28px 6px 10px;">
                    <?php foreach ($perPageOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <nav class="pagination-nav" aria-label="Table pagination">
                <?php if ($page > 1): ?>
                <button type="submit" form="category-filter-form" name="page" value="<?= $page - 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">&laquo; Prev</button>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <button type="submit" form="category-filter-form" name="page" value="<?= $i ?>"
                   class="btn btn--sm <?= $i === $page ? 'btn--primary' : 'btn--secondary' ?>"
                   aria-label="Page <?= $i ?>"
                   aria-current="<?= $i === $page ? 'page' : '' ?>"
                   style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;">
                    <?= $i ?>
                </button>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <button type="submit" form="category-filter-form" name="page" value="<?= $page + 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">Next &raquo;</button>
                <?php endif; ?>
            </nav>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- Add / Edit Category Modal -->
<div class="modal-backdrop" id="category-modal-backdrop" hidden>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-category-title" id="category-modal-panel">
        <div class="modal-header">
            <h2 class="modal-header__title" id="modal-category-title">Add New Category</h2>
            <button type="button" class="modal-close" id="category-modal-close" aria-label="Close dialog">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-x"></use></svg>
            </button>
        </div>
        <?php // Submitted via fetch in categories.js; method/action set so a pre-JS submit can never fall back to GET and leak the CSRF token into the URL. ?>
        <form id="category-form" method="post" action="/categories" novalidate>
            <div class="modal-body">
                <div class="modal-alert" role="alert" aria-live="assertive" id="category-form-error" hidden></div>

                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="category-id" value="">

                <div class="form-field">
                    <label class="form-field__label" for="category-code-input">Category Code</label>
                    <input class="input" type="text" id="category-code-input" name="code" maxlength="20"
                           placeholder="Auto-generated if left blank"
                           style="text-transform:uppercase;min-height:44px;"
                           aria-describedby="category-code-error">
                    <div class="form-field__error" id="category-code-error" hidden></div>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="category-name-input">Category Name <span aria-hidden="true">*</span></label>
                    <input class="input" type="text" id="category-name-input" name="name" required minlength="3" maxlength="80"
                           style="min-height:44px;" aria-describedby="category-name-error">
                    <div class="form-field__error" id="category-name-error" hidden></div>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="category-description-input">Description</label>
                    <textarea class="input" id="category-description-input" name="description" rows="3" maxlength="250"
                              aria-describedby="category-description-error"></textarea>
                    <div class="form-field__error" id="category-description-error" hidden></div>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="category-status-input">Status <span aria-hidden="true">*</span></label>
                    <select class="input" id="category-status-input" name="status" required style="min-height:44px;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" id="category-modal-cancel">Cancel</button>
                <button type="submit" class="btn btn--primary" id="category-modal-save">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Dialog -->
<div class="modal-backdrop" id="delete-modal-backdrop" hidden>
    <div class="modal-panel" role="alertdialog" aria-modal="true" aria-labelledby="delete-modal-title" style="max-width:26rem;">
        <div class="modal-header">
            <h2 class="modal-header__title" id="delete-modal-title">Delete Category</h2>
        </div>
        <div class="modal-body">
            <p id="delete-modal-message" style="margin:0;color:var(--color-on-surface);"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn--secondary" id="delete-modal-cancel">Cancel</button>
            <button type="button" class="btn btn--destructive" id="delete-modal-confirm">Delete</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.CategoriesPageData = {
    autoOpenAdd: <?= $autoOpenAdd ? 'true' : 'false' ?>,
    autoEditCategory: <?= $autoEditCategory !== null ? json_encode($autoEditCategory, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>,
};
</script>

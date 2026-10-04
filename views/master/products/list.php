<?php

/** @var list<\App\Entity\Product> $products */
/** @var array<int, int> $productStocks */
/** @var string|null $sku */
/** @var string|null $productName */
/** @var list<int>|null $categoryIds */
/** @var list<int>|null $warehouseIds */
/** @var string|null $stockStatus */
/** @var list<\App\Entity\Category> $categories */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var array $metrics */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$selectedCategories = $categoryIds ?? [];
$selectedWarehouses = $warehouseIds ?? [];
$categoryOptions = [];
foreach ($categories as $c) {
    $categoryOptions[$c->id] = $c->name;
}
$warehouseOptions = [];
foreach ($warehouses as $w) {
    $warehouseOptions[$w->id] = $w->name;
}
$totalPages = max(1, (int) ceil($total / max($perPage, 1)));

// When exactly one warehouse is selected, the stock column below shows that
// warehouse's own quantity instead of the network total (see the per-product
// loop below) — label the column to match so the number isn't misread as
// the total across all warehouses.
$stockColumnLabel = 'Total Physical Stock';
if (count($selectedWarehouses) === 1) {
    $stockColumnLabel = htmlspecialchars($warehouseOptions[$selectedWarehouses[0]] ?? 'Warehouse', ENT_QUOTES, 'UTF-8') . ' Stock';
} elseif (count($selectedWarehouses) > 1) {
    $stockColumnLabel = 'Stock in Selected Warehouses';
}

// Helper: stock status badge class + label
$stockBadge = static function (\App\Entity\Product $p, int $totalStock): string {
    if (!$p->isActive) {
        $class = 'badge--inactive';
    } elseif ($totalStock === 0) {
        $class = 'badge--error';
    } elseif ($totalStock < (int) $p->reorderPoint) {
        $class = 'badge--warning';
    } else {
        $class = 'badge--success';
    }
    return $class;
};

$stockBadgeLabel = static function (\App\Entity\Product $p, int $totalStock): string {
    if (!$p->isActive) {
        $label = 'Inactive';
    } elseif ($totalStock === 0) {
        $label = 'Out of Stock';
    } elseif ($totalStock < (int) $p->reorderPoint) {
        $label = 'Low Stock';
    } else {
        $label = 'In Stock';
    }
    return $label;
};

$stockStatusOptions = [
    '' => 'All Active',
    'in_stock' => 'In Stock',
    'low_stock' => 'Low Stock',
    'out_of_stock' => 'Out of Stock',
    'inactive' => 'Inactive',
];

$perPageOptions = [10, 25, 50, 100];
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <span>Master Data</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Products</span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title">Products</h1>
        <?php if ($isAdmin): ?>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/products/create">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add Product
            </a>
        </div>
        <?php endif; ?>
    </div>

    <p class="page-header__subtitle">
        Manage catalog items, category classifications, and master inventory units.
    </p>
    <?php if (!empty($flashError)): ?>
    <div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>

<!-- KPI Metrics Bar -->
<?php
$metricCards = [
    [
        'label' => 'Total Products (SKU)',
        'value' => number_format((int) ($metrics['total'] ?? 0), 0, ',', '.') . ' SKU',
        'sub' => 'Active across all warehouses',
        'accent' => 'badge--primary',
    ],
    [
        'label' => 'Normal Stock Items',
        'value' => number_format((int) ($metrics['normal'] ?? 0), 0, ',', '.') . ' SKU',
        'sub' => 'Stock above minimum threshold',
        'accent' => 'badge--active',
    ],
    [
        'label' => 'Low Stock Warning',
        'value' => number_format((int) ($metrics['low'] ?? 0), 0, ',', '.') . ' SKU',
        'sub' => 'Approaching reorder point',
        'accent' => 'badge--warning',
    ],
    [
        'label' => 'Out of Stock',
        'value' => number_format((int) ($metrics['out'] ?? 0), 0, ',', '.') . ' SKU',
        'sub' => 'Restock required immediately',
        'accent' => 'badge--error',
    ],
];
?>
<div class="products-kpi-grid">
    <?php foreach ($metricCards as $card): ?>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-card__sub"><?= htmlspecialchars($card['sub'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Toolbar -->
<div class="card" style="margin-bottom:var(--space-4);">
    <!-- POST: filter/sort/pagination/per-page all submit this one form (via
         the HTML form="products-filter-form" attribute on controls that live
         outside it) so search state never lands in the URL. -->
    <form method="post" action="/products/search" id="products-filter-form" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="product-sku">Product SKU / Barcode</label>
                <input class="input" type="search" id="product-sku" name="sku"
                       value="<?= htmlspecialchars($sku ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter SKU Code or Barcode…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="product-name">Product Name</label>
                <input class="input" type="search" id="product-name" name="product_name"
                       value="<?= htmlspecialchars($productName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Product Name…">
            </div>
            <?php
            $name = 'category'; $label = 'Category';
            $options = $categoryOptions; $selected = $selectedCategories;
            $id = 'product-category'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
            <div class="form-field">
                <label class="form-field__label" for="stock-status-filter">Stock Status</label>
                <select id="stock-status-filter" name="stock_status" class="input">
                    <?php foreach ($stockStatusOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $stockStatus === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $name = 'warehouse_id'; $label = 'Warehouse';
            $options = $warehouseOptions; $selected = $selectedWarehouses;
            $id = 'product-warehouse'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/products">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($products) === 0): ?>
<div class="empty-state" role="status" aria-live="polite">
    <svg width="40" height="40" aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
    <?php $hasProductFilter = ($sku ?? '') !== '' || ($productName ?? '') !== '' || !empty($categoryIds) || !empty($warehouseIds) || ($stockStatus ?? null) !== null; ?>
    <?php if ($hasProductFilter): ?>
    <p class="empty-state__message">No products match your search or filter criteria.</p>
    <?php else: ?>
    <p class="empty-state__message">No products found. Add your first product to get started.</p>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- Table with ARIA status for loading/filtering -->
<div class="table-wrap" role="status" aria-live="polite">
    <table class="table" id="products-table">
        <thead>
            <tr>
                <th>SKU Code</th>
                <th>Product Name</th>
                <th>UOM</th>
                <th style="text-align:right;">Price (Rp)</th>
                <th style="text-align:right;"><?= $stockColumnLabel ?></th>
                <th style="text-align:center;">Status</th>
                <th style="width:240px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product):
                $totalStock = (int) ($productStocks[$product->id] ?? 0);
                $reorderPt = (int) $product->reorderPoint;
            ?>
            <tr>
                <td>
                    <span class="font-mono" style="font-size:12px;font-weight:500;"><?= htmlspecialchars($product->sku, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td>
                    <div style="font-weight:500;color:var(--color-on-surface);"><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:var(--font-size-label-xs);color:var(--color-on-surface-variant);"><?= htmlspecialchars($product->categoryName ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td>
                    <span style="font-size:var(--font-size-label-sm);color:var(--color-on-surface-variant);"><?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td style="text-align:right;">
                    <span style="font-weight:600;">Rp <?= number_format((float) $product->salePrice, 0, ',', '.') ?></span>
                </td>
                <td style="text-align:right;">
                    <strong><?= (int) $totalStock ?></strong>
                    <span style="font-size:var(--font-size-label-xs);color:var(--color-on-surface-variant);"><?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td style="text-align:center;">
                    <span class="badge <?= $stockBadge($product, $totalStock) ?>">
                        <?= htmlspecialchars($stockBadgeLabel($product, $totalStock), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td class="table__actions">
                    <div class="row-actions">
                        <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($product->sku, ENT_QUOTES, 'UTF-8') ?>">
                            <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                        </button>
                        <div class="row-actions__menu" role="menu" hidden>
                            <a class="row-actions__item row-actions__item--view" role="menuitem" href="/products/<?= $idObfuscator->encode($product->id) ?>">
                                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-eye"></use></svg>
                                View
                            </a>
                            <?php if ($isAdmin): ?>
                            <a class="row-actions__item row-actions__item--edit" role="menuitem" href="/products/<?= $idObfuscator->encode($product->id) ?>/edit">
                                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                Edit
                            </a>
                            <form method="post" action="/products/<?= $idObfuscator->encode($product->id) ?>/<?= $product->isActive ? 'deactivate' : 'activate' ?>" data-ajax-status>
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="row-actions__item <?= $product->isActive ? 'row-actions__item--destructive' : 'row-actions__item--activate' ?>" role="menuitem">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-power"></use></svg>
                                    <?= $product->isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
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
        of <?= number_format($total, 0, ',', '.') ?> entries
    </div>
    <div class="pagination-footer__controls">
        <div class="pagination-footer__per-page">
            <label for="per-page-select" class="pagination-footer__label">Rows per page:</label>
            <select id="per-page-select" name="per_page" form="products-filter-form" class="input" onchange="this.form.requestSubmit()" style="min-height:44px;padding:6px 28px 6px 10px;">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <nav class="pagination-nav" aria-label="Table pagination">
            <?php if ($page > 1): ?>
            <button type="submit" form="products-filter-form" name="page" value="<?= $page - 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">&laquo; Prev</button>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <button type="submit" form="products-filter-form" name="page" value="<?= $i ?>"
               class="btn btn--sm <?= $i === $page ? 'btn--primary' : 'btn--secondary' ?>"
               aria-label="Page <?= $i ?>"
               aria-current="<?= $i === $page ? 'page' : '' ?>"
               style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;">
                <?= $i ?>
            </button>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <button type="submit" form="products-filter-form" name="page" value="<?= $page + 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">Next &raquo;</button>
            <?php endif; ?>
        </nav>
    </div>
</div>
<?php endif; ?>

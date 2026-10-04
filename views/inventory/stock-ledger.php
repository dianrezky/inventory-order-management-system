<?php

/** @var list<array<string,mixed>> $entries */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var array<string, mixed> $filters */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var string|null $queryError */

$selectedMovementTypes = (array) ($filters['movement_type'] ?? []);
$selectedWarehouseIds  = (array) ($filters['warehouse_id']  ?? []);

$movementTypeOptions = [
    'Receipt'   => 'Receipt',
    'Issue'     => 'Issue',
    'Adjustment' => 'Adjustment',
];
$warehouseOptions = [];
foreach ($warehouses as $w) {
    $warehouseOptions[$w->id] = $w->name;
}

// Same sort-arrow mechanism stock-ledger.js uses after an AJAX re-sort
// (.sort-asc/.sort-desc class + CSS ::after), so the arrow a client-side sort
// click adds/removes lines up with what the server renders on first load —
// a literal arrow character baked into one <th>'s text never gets cleared by
// the JS click handler, which only ever toggles classes on the clicked column.
$sortClass = static function (string $col) use ($filters): string {
    $isActive = $filters['sort_col'] === $col || ($col === 'date' && $filters['sort_col'] === 'done_at');
    if (!$isActive) {
        return '';
    }

    return $filters['sort_dir'] === 'ASC' ? 'sort-asc' : 'sort-desc';
};
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Inventory</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Stock Ledger</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Stock Ledger</h1>
    </div>
    <p class="page-header__subtitle" id="ledger-total">
        <?= (int) $total ?> movement<?= $total === 1 ? '' : 's' ?> recorded
    </p>
</div>

<?php if ($queryError !== null): ?>
<div class="alert alert--error" role="alert" aria-live="assertive" style="margin-bottom:var(--space-4);">
    <svg width="20" height="20" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
    </svg>
    <div>
        <strong>Stock ledger could not be loaded</strong>
        <p style="margin:4px 0 0;"><?= htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    App.report('failure', 'Stock Ledger Could Not Be Loaded', <?= json_encode($queryError) ?>);
});
</script>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="margin-bottom: var(--space-4);">
    <form id="ledger-filter-form" method="post" action="/stock-ledger" novalidate>
        <div class="form-grid">
            <div class="form-field">
                <label class="form-field__label" for="ledger-sku">Product SKU</label>
                <input class="input" type="search" id="ledger-sku" name="sku"
                       value="<?= htmlspecialchars($filters['sku'], ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Product SKU Code…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="ledger-product">Product Name</label>
                <input class="input" type="search" id="ledger-product" name="product_name"
                       value="<?= htmlspecialchars($filters['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Product Name…">
            </div>
            <?php
            $name = 'movement_type'; $label = 'Movement Type';
            $options = $movementTypeOptions; $selected = $selectedMovementTypes;
            $id = 'ledger-type'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
            <?php
            $name = 'warehouse_id'; $label = 'Warehouse';
            $options = $warehouseOptions; $selected = $selectedWarehouseIds;
            $id = 'ledger-warehouse'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
        </div>
        <div class="form-actions" style="margin-top: var(--space-3);">
            <button type="submit" class="btn btn--primary">Filter</button>
            <a class="btn btn--secondary" href="/stock-ledger">Reset</a>
        </div>
    </form>
</div>

<!-- Loading indicator -->
<div id="ledger-loading" style="display:none; text-align:center; padding:8px; color:var(--color-text-secondary); font-size:13px;">
    Loading…
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrap ledger-table-wrap">
        <table class="table" id="ledger-table">
            <thead>
                <tr>
                    <th data-sort-col="sku" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('sku') ?>">
                        SKU
                    </th>
                    <th data-sort-col="product" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('product') ?>">
                        Product Name
                    </th>
                    <th data-sort-col="warehouse" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('warehouse') ?>">
                        Warehouse
                    </th>
                    <th data-sort-col="type" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('type') ?>">
                        Type
                    </th>
                    <th data-sort-col="qty" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('qty') ?>">
                        Qty
                    </th>
                    <th>Stock Before → After</th>
                    <th>Reference / Note</th>
                    <th>Done By</th>
                    <th data-sort-col="date" data-sort-dir="<?= htmlspecialchars($filters['sort_dir'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $sortClass('date') ?>">
                        Date
                    </th>
                </tr>
            </thead>
            <tbody id="ledger-tbody">
                <?php require __DIR__ . '/_stock-ledger-rows.php'; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php // Always rendered (hidden when single-page) so an AJAX filter that grows the result can reveal it. ?>
    <nav class="pagination" id="ledger-pagination" style="margin-top:var(--space-4);" <?= $totalPages <= 1 ? 'hidden' : '' ?>>
        <button type="button" class="btn btn--secondary" id="ledger-prev"
                data-page="<?= (int) $page ?>" <?= $page <= 1 ? 'disabled' : '' ?>>
            &laquo; Prev
        </button>
        <span class="pagination__info" id="ledger-page-info">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
        <button type="button" class="btn btn--secondary" id="ledger-next"
                data-page="<?= (int) $page ?>" <?= $page >= $totalPages ? 'disabled' : '' ?>>
            Next &raquo;
        </button>
    </nav>
</div>

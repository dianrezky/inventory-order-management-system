<?php

/** @var list<\App\Entity\PurchaseOrder> $orders */
/** @var list<string>|null $statuses */
/** @var string|null $orderNumber */
/** @var string|null $supplierName */
/** @var string $sortDirection */
/** @var list<int>|null $warehouseIds */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */

$statusKeys = ['Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled'];
$statusOptions = [];
foreach ($statusKeys as $s) {
    $statusOptions[$s] = \App\Entity\PurchaseOrder::statusLabelFor($s);
}
$selectedStatuses = $statuses ?? [];
$selectedWarehouses = $warehouseIds ?? [];
$hasFilter = count($selectedStatuses) > 0 || count($selectedWarehouses) > 0
    || ($orderNumber ?? '') !== '' || ($supplierName ?? '') !== '';
$warehouseOptions = [];
foreach ($warehouses as $w) {
    $warehouseOptions[$w->id] = $w->name;
}

$badgeClass = static function (string $status): string {
    return match ($status) {
        'Draft' => 'badge--neutral',
        'Ordered' => 'badge--info',
        'PartiallyReceived' => 'badge--warning',
        'Received' => 'badge--active',
        'Cancelled' => 'badge--inactive',
        default => 'badge--neutral',
    };
};
$totalPages = max(1, (int) ceil($total / max($perPage, 1)));
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Procurement</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Purchase Orders</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Purchase Orders</h1>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/purchase-orders/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                New Order
            </a>
        </div>
    </div>
    <p class="page-header__subtitle"><?= (int) $total ?> order<?= $total === 1 ? '' : 's' ?> total</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter/sort/pagination all submit this one form (via the HTML
         form="po-filter-form" attribute on controls that live outside it) so
         search state never lands in the URL. -->
    <form method="post" action="/purchase-orders/search" id="po-filter-form" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="po-order-number">Order Number</label>
                <input class="input" type="search" id="po-order-number" name="order_number"
                       value="<?= htmlspecialchars($orderNumber ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Order Number…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="po-supplier-name">Supplier Name</label>
                <input class="input" type="search" id="po-supplier-name" name="supplier_name"
                       value="<?= htmlspecialchars($supplierName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Supplier Name…">
            </div>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'po-status'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
            <?php
            $name = 'warehouse_id'; $label = 'Warehouse';
            $options = $warehouseOptions; $selected = $selectedWarehouses;
            $id = 'po-warehouse'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/purchase-orders">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
        <?php // Carries the current sort through Search/Prev/Next; the Date header's sort button comes later in DOM order, so its value wins when it is the one clicked. ?>
        <input type="hidden" name="sort" value="<?= $sortDirection === 'asc' ? 'asc' : 'desc' ?>">
    </form>
</div>

<?php if (count($orders) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
        <?php if ($hasFilter): ?>
            <p><strong>No orders match your search criteria</strong></p>
            <p class="empty-state-card__hint">No results match the current filters. Try different search terms.</p>
        <?php else: ?>
            <p><strong>No purchase orders found. Create a PO when stock is low.</strong></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>
                    <button type="submit" form="po-filter-form" name="sort" value="<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>" class="th-sort-btn">
                        Date <?= $sortDirection === 'asc' ? '&uarr;' : '&darr;' ?>
                    </button>
                </th>
                <th>Supplier</th>
                <th>Warehouse</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: right;">Items / Inbound Progress</th>
                <th style="text-align: right;">Total Value</th>
                <th style="width: 120px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?= $order->id ?></td>
                    <td><?= htmlspecialchars($order->orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($order->supplierName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($order->destinationWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align: center;">
                        <span class="badge <?= $badgeClass($order->status) ?>">
                            <?= htmlspecialchars(\App\Entity\PurchaseOrder::statusLabelFor($order->status), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <strong><?= (int) $order->itemsCount ?></strong>
                        <span class="text--muted" style="font-size: 11px;">line<?= $order->itemsCount === 1 ? '' : 's' ?></span>
                        <div class="text--muted" style="font-size: 11px;">
                            <?= (int) $order->itemsQtyReceived ?> / <?= (int) $order->itemsQtyOrdered ?> unit<?= $order->itemsQtyOrdered === 1 ? '' : 's' ?>
                        </div>
                        <div class="progress-bar" title="<?= (int) $order->receiptProgressPct ?>% received">
                            <div class="progress-bar__fill" style="width:<?= (int) $order->receiptProgressPct ?>%;"></div>
                        </div>
                    </td>
                    <td style="text-align: right;">Rp <?= number_format((float) $order->totalValue, 0, ',', '.') ?></td>
                    <td class="table__actions">
                        <a class="btn btn--tertiary" href="/purchase-orders/<?= $idObfuscator->encode($order->id) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <button type="submit" form="po-filter-form" name="page" value="<?= $page - 1 ?>" class="btn btn--secondary">&laquo; Prev</button>
        <?php endif; ?>
        <span class="pagination__info">Page <?= (int) $page ?> of <?= (int) $totalPages ?> (<?= (int) $total ?> total)</span>
        <?php if ($page < $totalPages): ?>
            <button type="submit" form="po-filter-form" name="page" value="<?= $page + 1 ?>" class="btn btn--secondary">Next &raquo;</button>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>

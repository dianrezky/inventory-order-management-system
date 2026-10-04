<?php

/** @var list<\App\Entity\SalesOrder> $orders */
/** @var list<string>|null $statuses */
/** @var string|null $orderNumber */
/** @var string|null $customerName */
/** @var string $sortDirection */
/** @var list<int>|null $warehouseIds */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var \App\Entity\User $currentUser */

$statusKeys = ['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'];
$statusOptions = [];
foreach ($statusKeys as $s) {
    $statusOptions[$s] = \App\Entity\SalesOrder::statusLabelFor($s);
}
$selectedStatuses = $statuses ?? [];
$selectedWarehouses = $warehouseIds ?? [];
$warehouseOptions = [];
foreach ($warehouses as $w) {
    $warehouseOptions[$w->id] = $w->name;
}

$badgeClass = static function (string $s): string {
    return match ($s) {
        'Draft' => 'badge--neutral',
        'PendingApproval' => 'badge--warning',
        'Approved' => 'badge--info',
        'Fulfilled' => 'badge--active',
        'Cancelled' => 'badge--inactive',
        default => 'badge--neutral',
    };
};
$totalPages = max(1, (int) ceil($total / max($perPage, 1)));
$canCreate = $currentUser->role !== \App\Entity\Role::WarehouseStaff;
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Sales</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Sales Orders</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Sales Orders</h1>
        <?php if ($canCreate): ?>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/sales-orders/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                New Order
            </a>
        </div>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle"><?= (int) $total ?> order<?= $total === 1 ? '' : 's' ?> total</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter/sort/pagination all submit this one form (via the HTML
         form="so-filter-form" attribute on controls that live outside it) so
         search state never lands in the URL. -->
    <form method="post" action="/sales-orders/search" id="so-filter-form" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="so-order-number">Order Number</label>
                <input class="input" type="search" id="so-order-number" name="order_number"
                       value="<?= htmlspecialchars($orderNumber ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Order Number…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="so-customer-name">Customer Name</label>
                <input class="input" type="search" id="so-customer-name" name="customer_name"
                       value="<?= htmlspecialchars($customerName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Customer Name…">
            </div>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'so-status'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
            <?php
            $name = 'warehouse_id'; $label = 'Warehouse';
            $options = $warehouseOptions; $selected = $selectedWarehouses;
            $id = 'so-warehouse'; $placeholder = null;
            include __DIR__ . '/../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/sales-orders">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
        <?php // Carries the current sort through Search/Prev/Next; the Date header's sort button comes later in DOM order, so its value wins when it is the one clicked. ?>
        <input type="hidden" name="sort" value="<?= $sortDirection === 'asc' ? 'asc' : 'desc' ?>">
    </form>
</div>

<?php if (count($orders) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg>
        <?php if (count($selectedStatuses) > 0): ?>
            <p><strong>No orders found</strong></p>
            <p class="empty-state-card__hint">No orders match the selected status filter.</p>
        <?php else: ?>
            <p><strong>No sales orders found. Create a new order to get started.</strong></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>
                    <button type="submit" form="so-filter-form" name="sort" value="<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>" class="th-sort-btn">
                        Date <?= $sortDirection === 'asc' ? '&uarr;' : '&darr;' ?>
                    </button>
                </th>
                <th>Customer</th>
                <th>Warehouse</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: right;">Items / Qty</th>
                <th style="text-align: right;">Total Value</th>
                <th style="width: 120px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= $o->id ?></td>
                    <td><?= htmlspecialchars($o->orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o->customerName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o->sourceWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align: center;">
                        <span class="badge <?= $badgeClass($o->status) ?>">
                            <?= htmlspecialchars(\App\Entity\SalesOrder::statusLabelFor($o->status), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <strong><?= (int) $o->itemsCount ?></strong>
                        <span class="text--muted" style="font-size: 11px;">line<?= $o->itemsCount === 1 ? '' : 's' ?></span>
                        <div class="text--muted" style="font-size: 11px;"><?= (int) $o->itemsQty ?> unit<?= $o->itemsQty === 1 ? '' : 's' ?></div>
                    </td>
                    <td style="text-align: right;">Rp <?= number_format((float) $o->totalValue, 0, ',', '.') ?></td>
                    <td class="table__actions">
                        <a class="btn btn--tertiary" href="/sales-orders/<?= $idObfuscator->encode($o->id) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <button type="submit" form="so-filter-form" name="page" value="<?= $page - 1 ?>" class="btn btn--secondary">&laquo; Prev</button>
        <?php endif; ?>
        <span class="pagination__info">Page <?= (int) $page ?> of <?= (int) $totalPages ?> (<?= (int) $total ?> total)</span>
        <?php if ($page < $totalPages): ?>
            <button type="submit" form="so-filter-form" name="page" value="<?= $page + 1 ?>" class="btn btn--secondary">Next &raquo;</button>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>

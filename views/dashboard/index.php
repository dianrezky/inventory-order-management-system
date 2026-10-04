<?php

/** @var \App\Entity\User $user */
/** @var array $stats */
/** @var list<\App\Entity\Notification> $notifications */
/** @var string $csrfToken */

$roleLabel = $user->role->label();
$isAdmin = $user->role === \App\Entity\Role::Admin;
$isSales = $user->role === \App\Entity\Role::Sales;
$isWarehouse = $user->role === \App\Entity\Role::WarehouseStaff;
$notifications = $notifications ?? [];

if ($isAdmin) {
    $dashboardTitle = 'Admin Dashboard';
} elseif ($isSales) {
    $dashboardTitle = 'Sales Dashboard';
} elseif ($isWarehouse) {
    $dashboardTitle = 'Warehouse Dashboard';
} else {
    $dashboardTitle = 'Dashboard';
}

// Stats helpers
$poTotal = array_sum($stats['po_by_status'] ?? []);
$soTotal = array_sum($stats['so_by_status'] ?? []);
$invValue = $stats['inventory_value'] ?? '0';
$lowStockCount = (int) ($stats['low_stock_count'] ?? 0);
$lowStockProducts = $stats['low_stock_products'] ?? [];
$recentTransactions = $stats['recent_transactions'] ?? [];
$auditTrail = $stats['audit_trail'] ?? [];
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <span>IOMS</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="live-badge">
            <span class="live-badge__dot"></span>
            LIVE SYNCED
        </span>
        <div class="page-header__actions">
            <button type="button" class="btn btn--secondary" data-dashboard-refresh aria-label="Refresh dashboard">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-refresh-cw"></use></svg>
                Refresh
            </button>
            <a href="/reports" class="btn btn--primary">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-file-text"></use></svg>
                Generate Summary Report
            </a>
        </div>
    </div>

    <p class="page-header__subtitle">Real-time system overview and critical inventory metrics.</p>
</div>

<?php if (($isAdmin || $isWarehouse) && count($notifications) > 0): ?>
<div class="dashboard-section">
    <div class="dashboard-section__header">
        <h2 class="dashboard-section__title">
            Notifications
            <span class="badge badge--error"><?= count($notifications) ?></span>
        </h2>
        <form method="post" action="/notifications/mark-all-read">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--secondary">Mark All as Read</button>
        </form>
    </div>
    <ul class="notification-list">
        <?php foreach ($notifications as $n): ?>
        <li class="notification-list__item">
            <span class="notification-list__icon">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-alert-triangle"></use></svg>
            </span>
            <span class="notification-list__message"><?= htmlspecialchars($n->message ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="notification-list__time"><?= htmlspecialchars($n->createdAt?->format('Y-m-d H:i') ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- KPI Summary Cards (4-column) -->
<div class="dashboard-kpi-grid">
    <!-- Card 1: Inventory Value -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Inventory Value</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value">Rp <?= number_format((float) $invValue, 0, ',', '.') ?></div>
            <div class="stat-card__sub"><?= number_format((int) ($stats['total_products'] ?? 0), 0, ',', '.') ?> Products Registered</div>
        </div>
    </div>

    <!-- Card 2: Low Stock Alerts -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">
                <span class="stat-card__label-dot stat-card__label-dot--error"></span>
                Low Stock Alerts
            </span>
            <div class="stat-card__icon stat-card__icon--error">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-alert-triangle"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $lowStockCount ?> Items</div>
            <div class="stat-card__sub"><?= $lowStockCount > 0 ? 'Immediate restock action required' : 'All stock levels healthy' ?></div>
        </div>
    </div>

    <!-- Card 3: Purchase Orders -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Pending PO Approvals</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $poTotal ?></div>
            <div class="stat-card__sub">
                <?php foreach (['Ordered', 'PartiallyReceived'] as $s):
                    if (($stats['po_by_status'][$s] ?? 0) > 0): ?>
                    <span class="badge badge--warning"><?= (int) $stats['po_by_status'][$s] ?>
                        <?= htmlspecialchars(\App\Entity\PurchaseOrder::statusLabelFor($s), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif;
                endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Card 4: Sales Orders -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Pending SO Fulfillment</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $soTotal ?></div>
            <div class="stat-card__sub">
                <?php if (($stats['so_by_status']['PendingApproval'] ?? 0) > 0): ?>
                    <span class="badge badge--warning"><?= (int) $stats['so_by_status']['PendingApproval'] ?> Awaiting Approval</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Split: Critical Inventory + Quick Actions / Activity Feed -->
<?php if (count($lowStockProducts) > 0 || count($recentTransactions) > 0 || count($auditTrail) > 0): ?>
<div class="dashboard-split">
    <!-- Left: Critical Inventory Table -->
    <?php if (count($lowStockProducts) > 0): ?>
    <div class="critical-inventory-card">
        <h2 class="critical-inventory-card__title">
            <svg width="16" height="16" aria-hidden="true" style="vertical-align:-2px"><use href="/assets/img/icons.svg#icon-alert-triangle"></use></svg>
            Critical Inventory Alerts
        </h2>
        <div class="table-wrap" style="border-radius:var(--radius-md); overflow-x:auto;">
            <table class="table low-stock-table">
                <thead>
                    <tr>
                        <th>SKU Code</th>
                        <th>Product Name</th>
                        <th>Stock</th>
                        <th>Min. Stock</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($lowStockProducts, 0, 10) as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="<?= $p['is_out'] ? 'badge badge--error' : 'badge badge--warning' ?>">
                                <?= (int) $p['total_stock'] ?> Unit
                            </span>
                        </td>
                        <td><?= (int) $p['reorder_point'] ?> Unit</td>
                        <td>
                            <span class="<?= $p['is_out'] ? 'badge badge--error' : 'badge badge--warning' ?>">
                                <?= $p['is_out'] ? 'OUT OF STOCK' : 'CRITICAL LOW' ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn--secondary btn--sm" href="/purchase-orders/create">Create PO</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right: Quick Actions + Activity Feed -->
    <div style="display:flex;flex-direction:column;gap:var(--space-4)">
        <!-- Quick Action Shortcuts -->
        <div class="quick-actions">
            <h3 class="quick-actions__title">Operational Quick Actions</h3>
            <div class="quick-actions__grid">
                <a class="quick-actions__btn" href="/purchase-orders/create">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                    New Purchase Order
                </a>
                <a class="quick-actions__btn" href="/sales-orders/create">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                    New Sales Order
                </a>
                <a class="quick-actions__btn" href="/purchase-orders">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
                    Receive Goods
                </a>
                <a class="quick-actions__btn" href="/sales-orders">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
                    Issue Goods
                </a>
                <?php if ($isAdmin): ?>
                <a class="quick-actions__btn" href="/users/create">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-user"></use></svg>
                    Add User
                </a>
                <?php endif; ?>
                <a class="quick-actions__btn" href="/stock-ledger">
                    <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-list"></use></svg>
                    Stock Ledger
                </a>
            </div>
        </div>

        <!-- Recent Activity / Audit Feed -->
        <?php if (count($auditTrail) > 0): ?>
        <div class="activity-feed">
            <h3 class="activity-feed__title">Recent System Activity</h3>
            <ul class="activity-feed__list">
                <?php foreach ($auditTrail as $log): ?>
                <li class="activity-feed__item">
                    <span class="activity-feed__time"><?= htmlspecialchars($log['time'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span>
                        <span class="activity-feed__actor"><?= htmlspecialchars($log['actor'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="activity-feed__text"> — <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Transactions Table -->
<?php if (count($recentTransactions) > 0): ?>
<div class="dashboard-section" style="margin-top:var(--space-4)">
    <h2 class="dashboard-section__title">
        <svg width="18" height="18" aria-hidden="true" style="vertical-align:-3px"><use href="/assets/img/icons.svg#icon-file-text"></use></svg>
        Recent Transactions
    </h2>
    <div class="table-wrap recent-transactions">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Type</th>
                    <th>Partner / Entity</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th style="text-align:right">Total Amount (Rp)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTransactions as $tx):
                    $badgeClass = match ($tx['status'] ?? '') {
                        'Draft' => 'badge--neutral',
                        'Ordered' => 'badge--info',
                        'PartiallyReceived' => 'badge--warning',
                        'Received' => 'badge--active',
                        'Cancelled' => 'badge--inactive',
                        'PendingApproval' => 'badge--warning',
                        'Approved' => 'badge--info',
                        'Fulfilled' => 'badge--active',
                        default => 'badge--neutral',
                    };
                    $label = $tx['type'] === 'Purchase'
                        ? \App\Entity\PurchaseOrder::statusLabelFor($tx['status'] ?? '')
                        : \App\Entity\SalesOrder::statusLabelFor($tx['status'] ?? '');
                    $encId = $idObfuscator->encode($tx['id'] ?? 0);
                    $detailHref = $tx['type'] === 'Purchase'
                        ? "/purchase-orders/{$encId}"
                        : "/sales-orders/{$encId}";
                ?>
                <tr>
                    <td><?= htmlspecialchars($tx['ref'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $tx['type'] === 'Purchase' ? 'badge--info' : 'badge--primary' ?>">
                            <?= $tx['type'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($tx['partner'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($tx['date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </span></td>
                    <td style="text-align:right">Rp <?= number_format((float) ($tx['total'] ?? 0), 0, ',', '.') ?></td>
                    <td><a class="btn btn--tertiary btn--sm" href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>">View Detail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($isSales): ?>
<?php $total = array_sum($stats); ?>
<div class="dashboard-grid">
    <div class="stat-card stat-card--large">
        <div class="stat-card__header">
            <span class="stat-card__label">My Orders Total</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $total ?></div>
        </div>
    </div>
    <?php foreach (['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'] as $s):
        $cnt = (int) ($stats[$s] ?? 0);
        if ($cnt === 0) {
            continue;
        }
    ?>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label"><?= htmlspecialchars(\App\Entity\SalesOrder::statusLabelFor($s), ENT_QUOTES, 'UTF-8') ?></span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-file-text"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $cnt ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="quick-actions" style="margin-top:var(--space-4)">
    <h3 class="quick-actions__title">Quick Actions</h3>
    <div class="quick-actions__grid">
        <a class="quick-actions__btn" href="/sales-orders/create">
            <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
            Create Sales Order
        </a>
        <a class="quick-actions__btn" href="/sales-orders">
            <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-list"></use></svg>
            My Sales Orders
        </a>
    </div>
</div>

<?php elseif ($isWarehouse): ?>
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Goods Receipt Queue</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) ($stats['po_receipt_queue'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Goods Issue Queue</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) ($stats['so_issue_queue'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">
                <span class="stat-card__label-dot stat-card__label-dot--error"></span>
                Below Reorder Point
            </span>
            <div class="stat-card__icon stat-card__icon--error">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-alert-triangle"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) ($stats['low_stock_count'] ?? 0) ?></div>
        </div>
    </div>
</div>
<?php // WarehouseStaff has no Sales Orders menu — this queue is its entry point to Process Goods Issue. ?>
<div class="dashboard-section">
    <h2 class="dashboard-section__title">Goods Issue Queue — Approved Sales Orders</h2>
    <?php if (count($stats['so_issue_orders'] ?? []) === 0): ?>
    <p class="text--muted">No approved sales orders are waiting for goods issue.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table" id="warehouse-issue-queue">
            <thead>
                <tr>
                    <th>SO</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Source Warehouse</th>
                    <th style="text-align: right;">Lines</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['so_issue_orders'] as $o): ?>
                <tr>
                    <td>#<?= (int) $o->id ?></td>
                    <td><?= htmlspecialchars((string) $o->orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o->customerName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o->sourceWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align: right;"><?= (int) $o->itemsCount ?></td>
                    <td class="table__actions">
                        <a class="btn btn--tertiary" href="/sales-orders/<?= $idObfuscator->encode($o->id) ?>">Process Issue</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<div class="dashboard-section">
    <h2 class="dashboard-section__title">Goods Receipt Queue — Purchase Orders Awaiting Delivery</h2>
    <?php if (count($stats['po_receipt_orders'] ?? []) === 0): ?>
    <p class="text--muted">No purchase orders are waiting for goods receipt.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table" id="warehouse-receipt-queue">
            <thead>
                <tr>
                    <th>PO</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Destination Warehouse</th>
                    <th style="text-align: right;">Received</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['po_receipt_orders'] as $po): ?>
                <tr>
                    <td>#<?= (int) $po->id ?></td>
                    <td><?= htmlspecialchars((string) $po->orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($po->supplierName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($po->destinationWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align: right;"><?= (int) $po->receiptProgressPct ?>%</td>
                    <td class="table__actions">
                        <a class="btn btn--tertiary" href="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>">Receive</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php if (count($stats['low_stock_products'] ?? []) > 0): ?>
<div class="dashboard-section">
    <h2 class="dashboard-section__title">Low Stock Products</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Current Stock</th>
                    <th>Reorder Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($stats['low_stock_products'], 0, 10) as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge badge--error"><?= (int) $p['total_stock'] ?></span></td>
                    <td><?= (int) $p['reorder_point'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

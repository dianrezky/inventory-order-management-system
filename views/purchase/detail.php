<?php

/** @var string $csrfToken */
/** @var \App\Entity\PurchaseOrder $po */
/** @var int $orderedQty */
/** @var int $receivedQty */
/** @var float $orderedValue */
/** @var float $receivedValue */
/** @var array $ledgerEntries */
/** @var string|null $currentUserRole */

$badgeClass = match ($po->status) {
    'Draft' => 'badge--neutral',
    'Ordered' => 'badge--info',
    'PartiallyReceived' => 'badge--warning',
    'Received' => 'badge--active',
    'Cancelled' => 'badge--inactive',
    default => 'badge--neutral',
};
$isAdmin = $currentUserRole === 'Admin';
$canManage = in_array($currentUserRole, ['Admin', 'WarehouseStaff'], true);
$remainingQty = max(0, $orderedQty - $receivedQty);
$progressPct = $orderedQty > 0 ? (int) round(($receivedQty / $orderedQty) * 100) : 0;

// Workflow stepper — Stitch §12.5 (Draft → Ordered → PartiallyReceived → Received | Cancelled)
$steps = [
    ['Draft', $po->status === 'Draft'],
    ['Ordered', in_array($po->status, ['Ordered', 'PartiallyReceived', 'Received'], true)],
    ['PartiallyReceived', $po->status === 'PartiallyReceived'],
    ['Received', $po->status === 'Received'],
];
$cancelled = $po->status === 'Cancelled';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/purchase-orders">Purchase Orders</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">PO-<?= str_pad((string) $po->id, 4, '0', STR_PAD_LEFT) ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">PO-<?= str_pad((string) $po->id, 4, '0', STR_PAD_LEFT) ?></h1>
        <span class="badge <?= $badgeClass ?>">
            <?= htmlspecialchars(\App\Entity\PurchaseOrder::statusLabelFor($po->status), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <p class="page-header__subtitle">
        Order Date: <?= htmlspecialchars($po->orderDate, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($po->createdByName !== null): ?>
            · Created by <?= htmlspecialchars($po->createdByName, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
        <?php if ($po->destinationWarehouseName !== null): ?>
            · Destination: <?= htmlspecialchars($po->destinationWarehouseName, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
    <div class="page-header__actions">
        <?php if ($isAdmin && $po->status === 'Draft'): ?>
            <form class="form--inline" method="post" action="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>/submit">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn--primary" data-i18n="purchase_orders.submit">Submit Order</button>
            </form>
        <?php endif; ?>
        <?php if ($canManage && $po->canReceiveGoods()): ?>
            <a class="btn btn--primary" href="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>/receive">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
                Receive Goods
            </a>
        <?php endif; ?>
        <?php if ($isAdmin && $po->canBeCancelled()): ?>
            <form class="form--inline" method="post" action="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>/cancel"
                  data-confirm="Cancel this purchase order? This cannot be undone."
                  data-confirm-ok="Yes, cancel order" data-confirm-cancel="Keep order">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn--destructive">Cancel Order</button>
            </form>
        <?php endif; ?>
        <a class="btn btn--tertiary" href="/purchase-orders" data-i18n="common.back">Back</a>
    </div>
</div>

<!-- Workflow Stepper -->
<div class="workflow-stepper" aria-label="Procurement workflow progress">
    <?php foreach ($steps as $i => [$label, $done]): ?>
        <div class="workflow-stepper__step <?= $done ? 'is-done' : 'is-pending' ?> <?= $label === $po->status ? 'is-current' : '' ?>">
            <span class="workflow-stepper__dot"><?= $i + 1 ?></span>
            <span class="workflow-stepper__label"><?= htmlspecialchars(\App\Entity\PurchaseOrder::statusLabelFor($label), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if ($i < count($steps) - 1): ?>
            <span class="workflow-stepper__bar <?= $done ? 'is-done' : '' ?>"></span>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($cancelled): ?>
        <span class="workflow-stepper__bar is-cancelled"></span>
        <div class="workflow-stepper__step is-cancelled is-current">
            <span class="workflow-stepper__dot">!</span>
            <span class="workflow-stepper__label">Cancelled</span>
        </div>
    <?php endif; ?>
</div>

<!-- Governance callout -->
<div class="callout" role="note" style="margin-bottom: 1.5rem;">
    <strong>Enterprise Stock Governance Protocol.</strong>
    Goods receipt increments <code>product_stocks.quantity</code> and writes a matching immutable
    <code>stock_ledger</code> row in one DB transaction. Stock quantities cannot be edited directly here.
</div>

<!-- Receiving Progress (2-segment bar per Stitch spec) -->
<div class="card" style="margin-bottom: 1rem;">
    <h2 class="section-title" style="margin-top: 0;">Inbound Intake &amp; Fulfillment Progress</h2>
    <div class="fulfillment-progress">
        <div class="fulfillment-progress__bar">
            <div class="fulfillment-progress__received" style="width: <?= $progressPct ?>%;"></div>
            <?php if ($remainingQty > 0 && $progressPct < 100): ?>
                <div class="fulfillment-progress__remaining" style="width: <?= 100 - $progressPct ?>%;"></div>
            <?php endif; ?>
        </div>
        <div class="fulfillment-progress__legend">
            <span class="fulfillment-progress__legend-item">
                <span class="fulfillment-progress__dot fulfillment-progress__dot--received"></span>
                Received: <strong><?= number_format($receivedQty) ?></strong> <?= htmlspecialchars($po->items[0]->productUnit ?? 'units', ENT_QUOTES, 'UTF-8') ?> (<?= $progressPct ?>%)
            </span>
            <span class="fulfillment-progress__legend-item">
                <span class="fulfillment-progress__dot fulfillment-progress__dot--remaining"></span>
                Remaining: <strong><?= number_format($remainingQty) ?></strong> <?= htmlspecialchars($po->items[0]->productUnit ?? 'units', ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>
    <div class="fulfillment-progress__tiles">
        <div class="fulfillment-progress__tile">
            <div class="fulfillment-progress__tile-label">Total Ordered</div>
            <div class="fulfillment-progress__tile-value"><?= number_format($orderedQty) ?></div>
        </div>
        <div class="fulfillment-progress__tile">
            <div class="fulfillment-progress__tile-label">Total Received</div>
            <div class="fulfillment-progress__tile-value"><?= number_format($receivedQty) ?></div>
            <div class="fulfillment-progress__tile-hint"><?= $progressPct ?>% Verified at Dock</div>
        </div>
        <div class="fulfillment-progress__tile">
            <div class="fulfillment-progress__tile-label">Remaining to Receive</div>
            <div class="fulfillment-progress__tile-value"><?= number_format($remainingQty) ?></div>
        </div>
    </div>
</div>

<!-- Metadata Cards (3-up: Supplier, Destination, Audit) -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Supplier</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;"><?= htmlspecialchars($po->supplierName ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                <?= htmlspecialchars($po->note ?? 'Procurement liaison registered in master data', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Destination Facility</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;"><?= htmlspecialchars($po->destinationWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                Inbound goods are recorded against this facility's <code>product_stocks</code>.
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Audit &amp; Schedule</span>
        </div>
        <div class="stat-card__body">
            <div style="font-size: 13px; line-height: 1.7;">
                <div><strong>Order Date:</strong> <?= htmlspecialchars($po->orderDate, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($po->createdByName !== null): ?>
                    <div><strong>Created By:</strong> <?= htmlspecialchars($po->createdByName, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div><strong>Stock Ledger Entries:</strong> <?= count($ledgerEntries) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Line Items (full table with subtotal) -->
<h2 class="section-title">Line Items <span class="badge badge--neutral" style="margin-left: 0.5rem;"><?= count($po->items) ?> line<?= count($po->items) === 1 ? '' : 's' ?></span></h2>
<div class="card" style="padding: 0;">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th style="text-align: right;">Ordered Qty</th>
                <th style="text-align: right;">Received Qty</th>
                <th style="text-align: right;">Remaining</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Line Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $lineSubtotal = 0.0;
            foreach ($po->items as $item):
                $lineTotal = (float) $item->qtyOrdered * (float) $item->purchasePrice;
                $lineSubtotal += $lineTotal;
            ?>
                <tr>
                    <td><?= htmlspecialchars(($item->productName ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="font-mono" style="font-size: 12px;"><?= htmlspecialchars(($item->productSku ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align: right;"><?= (int) $item->qtyOrdered ?> <?= htmlspecialchars($item->productUnit ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align: right;"><?= (int) $item->qtyReceived ?></td>
                    <td style="text-align: right;"><?= $item->qtyRemaining() ?></td>
                    <td style="text-align: right;">Rp <?= number_format((float) $item->purchasePrice, 0, ',', '.') ?></td>
                    <td style="text-align: right;">Rp <?= number_format($lineTotal, 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align: right;">Total Purchase Value</td>
                    <td style="text-align: right;">Rp <?= number_format($lineSubtotal, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<p class="text--muted" style="margin-top: 0.5rem; font-size: 13px;">
    Subtotal strictly governed by Unit Price × Ordered Qty. Tax &amp; logistics calculated separately.
</p>

<!-- Audit Log Timeline -->
<?php if (count($ledgerEntries) > 0): ?>
<h2 class="section-title" style="margin-top: 2rem;">Audit Log</h2>
<p class="text--muted" style="margin-bottom: 0.75rem; font-size: 13px;">
    Every Stock Ledger entry referencing this purchase order. The ledger is append-only — entries cannot be edited or deleted.
</p>
<div class="card">
    <ol class="audit-timeline">
        <?php foreach ($ledgerEntries as $entry): ?>
            <li class="audit-timeline__entry">
                <span class="audit-timeline__dot audit-timeline__dot--<?= strtolower(htmlspecialchars($entry->type ?? '', ENT_QUOTES, 'UTF-8')) ?>"></span>
                <div class="audit-timeline__body">
                    <div class="audit-timeline__head">
                        <strong><?= htmlspecialchars($entry->type ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        ·
                        <span class="font-mono"><?= (int) $entry->qty > 0 ? '+' . (int) $entry->qty : (int) $entry->qty ?></span> unit<?= abs((int) $entry->qty) === 1 ? '' : 's' ?>
                        of <em><?= htmlspecialchars($entry->productName ?? '-', ENT_QUOTES, 'UTF-8') ?></em>
                        at <strong><?= htmlspecialchars($entry->warehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="audit-timeline__meta">
                        <?php if ($entry->doneAt !== null): ?>
                            <span><?= htmlspecialchars($entry->doneAt->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?></span> ·
                        <?php endif; ?>
                        by <?= htmlspecialchars($entry->userName ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($entry->note !== null): ?>
                            · <em><?= htmlspecialchars($entry->note, ENT_QUOTES, 'UTF-8') ?></em>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

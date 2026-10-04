<?php

/** @var \App\Entity\SalesOrder $so */
/** @var \App\Entity\User $currentUser */
/** @var array $ledgerEntries */

$statusBadge = static fn (string $s): string => match ($s) {
    'Draft' => 'badge--neutral',
    'PendingApproval' => 'badge--warning',
    'Approved' => 'badge--info',
    'Fulfilled' => 'badge--active',
    'Cancelled' => 'badge--inactive',
    default => 'badge--neutral',
};

$isSalesRole = $currentUser->role === \App\Entity\Role::Sales;
$isAdminRole = $currentUser->role === \App\Entity\Role::Admin;
$isWarehouseRole = $currentUser->role === \App\Entity\Role::WarehouseStaff;
$isCreator = $so->createdBy === $currentUser->id;

$canSubmit = $so->status === 'Draft' && $isCreator;
// SalesOrderPolicy::assertCanCancel(): Admin can cancel any non-Fulfilled, non-Cancelled SO
// (Draft/PendingApproval/Approved) — the creator can only cancel their own
// while it's still Draft/PendingApproval. This must mirror the policy
// exactly, or the button won't be offered for something the backend allows.
if ($isAdminRole) {
    $canCancel = !in_array($so->status, ['Fulfilled', 'Cancelled'], true);
} else {
    $canCancel = $isCreator && in_array($so->status, ['Draft', 'PendingApproval'], true);
}
$canApprove = $so->status === 'PendingApproval' && $isAdminRole;
$canReject = $so->status === 'PendingApproval' && $isAdminRole;
$canIssue = $so->status === 'Approved' && ($isAdminRole || $isWarehouseRole);

// SOD-01: lock the approve action if the current user created the order,
// even though the role guard above already blocks Sales/Non-Admin.
$selfApprovalBlocked = $so->status === 'PendingApproval' && $isSalesRole && $isCreator;

// Workflow stepper — Stitch §12.6: Draft → PendingApproval → Approved → Fulfilled
$steps = [
    ['Draft', $so->status === 'Draft'],
    ['PendingApproval', in_array($so->status, ['PendingApproval', 'Approved', 'Fulfilled'], true)],
    ['Approved', in_array($so->status, ['Approved', 'Fulfilled'], true)],
    ['Fulfilled', $so->status === 'Fulfilled'],
];
$cancelled = $so->status === 'Cancelled';

$totalQty = 0;
$totalValue = 0.0;
foreach ($so->items as $item) {
    $totalQty += (int) $item->qty;
    $totalValue += (float) $item->qty * (float) $item->salePrice;
}
$totalUnits = (int) $so->itemsQty > 0 ? (int) $so->itemsQty : $totalQty;
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/sales-orders">Sales Orders</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">SO-<?= str_pad((string) $so->id, 4, '0', STR_PAD_LEFT) ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">SO-<?= str_pad((string) $so->id, 4, '0', STR_PAD_LEFT) ?></h1>
        <span class="badge <?= $statusBadge($so->status) ?>">
            <?= htmlspecialchars(\App\Entity\SalesOrder::statusLabelFor($so->status), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($selfApprovalBlocked): ?>
            <span class="page-header__pill" style="background: var(--color-status-warning, #F59E0B); color: #FFFFFF;">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-lock"></use></svg>
                Self-Approval Restricted
            </span>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle">
        <?= htmlspecialchars($so->customerName ?? '-', ENT_QUOTES, 'UTF-8') ?>
        · Order Date: <?= htmlspecialchars($so->orderDate, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($so->createdByName !== null): ?>
            · Created by <?= htmlspecialchars($so->createdByName, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
</div>

<!-- Workflow Stepper -->
<div class="workflow-stepper" aria-label="Sales order lifecycle">
    <?php foreach ($steps as $i => [$label, $done]): ?>
        <div class="workflow-stepper__step <?= $done ? 'is-done' : 'is-pending' ?> <?= $label === $so->status ? 'is-current' : '' ?>">
            <span class="workflow-stepper__dot"><?= $i + 1 ?></span>
            <span class="workflow-stepper__label"><?= htmlspecialchars(\App\Entity\SalesOrder::statusLabelFor($label), ENT_QUOTES, 'UTF-8') ?></span>
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

<!-- SOD-01 governance callout (shown when Sales user tries to approve their own order) -->
<?php if ($selfApprovalBlocked): ?>
    <div class="callout" role="note" style="border-left-color: var(--color-status-warning, #F59E0B); margin-bottom: 1rem;">
        <strong>Segregation of Duties (SOD-01).</strong>
        As the order creator, you cannot self-approve customer sales orders.
        This order has been placed in the Administrative review queue to verify customer credit standing and inventory thresholds.
    </div>
<?php else: ?>
    <div class="callout" role="note" style="margin-bottom: 1rem;">
        <strong>Sales Order &amp; Stock Allocation Protocol.</strong>
        Approved Sales Orders place a hard stock reservation against the assigned Source Warehouse.
        Physical inventory is deducted from <code>ProductStock</code> only upon validated Goods Issue,
        recording an immutable audit entry in the Stock Ledger.
    </div>
<?php endif; ?>

<!-- Metadata Cards (3-up: Customer, Source Facility, Audit) -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Customer</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;"><?= htmlspecialchars($so->customerName ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                <?php if ($so->cancellationReason !== null): ?>
                    <span class="badge badge--error"><?= htmlspecialchars($so->cancellationReason, ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <?= htmlspecialchars($so->note ?? 'Customer master record verified', ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Source Facility</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;"><?= htmlspecialchars($so->sourceWarehouseName ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                Physical inventory deducted here upon Goods Issue.
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Audit &amp; Chain of Custody</span>
        </div>
        <div class="stat-card__body">
            <div style="font-size: 13px; line-height: 1.7;">
                <div><strong>Order Date:</strong> <?= htmlspecialchars($so->orderDate, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($so->approvedByName !== null): ?>
                    <div><strong>Approved By:</strong> <?= htmlspecialchars($so->approvedByName, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($so->approvedAt !== null): ?>
                            (<?= htmlspecialchars($so->approvedAt->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($so->issuedByName !== null): ?>
                    <div><strong>Issued By:</strong> <?= htmlspecialchars($so->issuedByName, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($so->issuedAt !== null): ?>
                            (<?= htmlspecialchars($so->issuedAt->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div><strong>Stock Ledger Entries:</strong> <?= count($ledgerEntries) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Line Items -->
<h2 class="section-title">Line Items <span class="badge badge--neutral" style="margin-left: 0.5rem;"><?= count($so->items) ?> line<?= count($so->items) === 1 ? '' : 's' ?></span></h2>
<?php if (count($so->items) > 0): ?>
    <div class="card" style="padding: 0;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th style="text-align: right;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Line Subtotal</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($so->items as $item): ?>
                    <?php $lineTotal = (float) $item->qty * (float) $item->salePrice; ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($item->productName ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($item->productUnit !== null): ?>
                                <div class="text--muted" style="font-size: 11px;"><?= htmlspecialchars($item->productUnit, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="font-mono" style="font-size: 12px;"><?= htmlspecialchars($item->productSku ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="text-align: right;"><?= (int) $item->qty ?></td>
                        <td style="text-align: right;">Rp <?= number_format((float) $item->salePrice, 0, ',', '.') ?></td>
                        <td style="text-align: right;">Rp <?= number_format($lineTotal, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">Distinct SKUs: <strong><?= count($so->items) ?></strong> · Total Quantity: <strong><?= number_format($totalQty) ?></strong> unit<?= $totalQty === 1 ? '' : 's' ?></td>
                        <td style="text-align: right;">Rp <?= number_format($totalValue, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Audit Log Timeline -->
<?php if (count($ledgerEntries) > 0): ?>
    <h2 class="section-title" style="margin-top: 2rem;">Audit Log</h2>
    <p class="text--muted" style="margin-bottom: 0.75rem; font-size: 13px;">
        Every Stock Ledger entry referencing this sales order. The ledger is append-only — entries cannot be edited or deleted.
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

<!-- Action Bar -->
<?php if (!in_array($so->status, ['Fulfilled', 'Cancelled'], true)): ?>
<div class="action-bar">
    <?php if ($so->status === 'Draft' && ($isAdminRole || ($isSalesRole && $isCreator))): ?>
        <a class="btn btn--secondary" href="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/edit">Edit Draft</a>
    <?php endif; ?>
    <?php if ($canSubmit): ?>
        <form class="action-bar__form" method="post" action="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/submit">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--primary" data-i18n="sales_orders.submit">Submit for Approval</button>
        </form>
    <?php endif; ?>

    <?php if ($canApprove && !$selfApprovalBlocked): ?>
        <form class="action-bar__form" method="post" action="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/approve">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--primary" data-i18n="sales_orders.approve">Approve</button>
        </form>
    <?php endif; ?>

    <?php if ($canReject): ?>
        <form class="action-bar__form" method="post" action="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/reject">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="reason" class="input input--lg" placeholder="Reason (optional)" aria-label="Rejection reason">
            <button type="submit" class="btn btn--secondary" data-i18n="sales_orders.reject">Reject</button>
        </form>
    <?php endif; ?>

    <?php if ($canIssue): ?>
        <form class="action-bar__form" method="post" action="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/issue">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--primary">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg>
                Issue Goods
            </button>
        </form>
    <?php endif; ?>

    <?php if ($canCancel): ?>
        <form class="action-bar__form" method="post" action="/sales-orders/<?= $idObfuscator->encode($so->id) ?>/cancel"
              data-confirm="Cancel this sales order? This cannot be undone."
              data-confirm-ok="Yes, cancel order" data-confirm-cancel="Keep order">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--destructive">Cancel Order</button>
        </form>
    <?php endif; ?>

    <a class="btn btn--tertiary" href="/sales-orders">Back</a>
</div>
<?php else: ?>
<div class="action-bar__back">
    <a class="btn btn--tertiary" href="/sales-orders">Back</a>
</div>
<?php endif; ?>

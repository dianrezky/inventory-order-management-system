<?php

/** @var \App\Entity\Product $product */
/** @var array $stockBreakdown */
/** @var array $lastMovements */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$totalStock = (int) ($stockBreakdown['total'] ?? 0);
$reorderPoint = (int) $product->reorderPoint;
$isLowStock = $totalStock < $reorderPoint;
$deficit = max(0, $reorderPoint - $totalStock);
$breakdown = $stockBreakdown['breakdown'] ?? [];
$activeCount = count(array_filter($breakdown, static fn ($r) => (int) $r['warehouse_active'] === 1));
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/products">Products</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($product->sku, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="badge <?= $product->isActive ? 'badge--active' : 'badge--inactive' ?>" style="margin-left: 0.5rem;">
            <?= htmlspecialchars($product->isActive ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span class="page-header__pill" style="margin-left: 0.5rem;">SKU: <?= htmlspecialchars($product->sku, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <p class="page-header__subtitle">
        View product master data, pricing definitions, and aggregated multi-warehouse stock positions.
    </p>
    <div class="page-header__actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--secondary" href="/products/<?= $idObfuscator->encode($product->id) ?>/edit" data-i18n="common.edit">Edit</a>
            <form class="form--inline" method="post" action="/products/<?= $idObfuscator->encode($product->id) ?>/<?= $product->isActive ? 'deactivate' : 'activate' ?>"
                  data-ajax-status>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn <?= $product->isActive ? 'btn--destructive' : 'btn--secondary' ?>">
                    <?= htmlspecialchars($product->isActive ? 'Deactivate' : 'Reactivate', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        <?php else: ?>
            <span class="page-header__pill">Read-Only Mode (Sales &amp; Warehouse)</span>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid">
    <!-- Left column: identity, pricing, replenishment -->
    <div class="detail-grid__main">
        <div class="card">
            <?php if ($product->imagePath !== null): ?>
                <img class="image-preview image-preview--lg" src="<?= htmlspecialchars($product->imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php else: ?>
                <p class="text--muted" data-i18n="products.no_image">No image uploaded.</p>
            <?php endif; ?>

            <h2 class="section-title">Identity</h2>
            <dl class="detail-list">
                <div class="detail-list__row">
                    <dt>Category</dt>
                    <dd><?= htmlspecialchars($product->categoryName ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="detail-list__row">
                    <dt>Unit</dt>
                    <dd><?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            </dl>
        </div>

        <div class="card">
            <h2 class="section-title">Pricing</h2>
            <dl class="detail-list">
                <div class="detail-list__row">
                    <dt>Purchase Price</dt>
                    <dd>Rp <?= number_format((float) $product->purchasePrice, 0, ',', '.') ?></dd>
                </div>
                <div class="detail-list__row">
                    <dt>Selling Price</dt>
                    <dd><strong>Rp <?= number_format((float) $product->salePrice, 0, ',', '.') ?></strong></dd>
                </div>
                <?php $margin = (float) $product->salePrice - (float) $product->purchasePrice; ?>
                <?php if ((float) $product->purchasePrice > 0): ?>
                    <?php $marginPct = ($margin / (float) $product->purchasePrice) * 100; ?>
                    <div class="detail-list__row">
                        <dt>Gross Margin</dt>
                        <dd>
                            Rp <?= number_format($margin, 0, ',', '.') ?>
                            <span class="badge badge--active" style="margin-left: 0.5rem;">
                                +<?= number_format($marginPct, 2) ?>%
                            </span>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="card">
            <h2 class="section-title">Replenishment</h2>
            <dl class="detail-list">
                <div class="detail-list__row">
                    <dt>Reorder Point</dt>
                    <dd><strong><?= $reorderPoint ?> <?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></strong></dd>
                </div>
                <div class="detail-list__row">
                    <dt>Status</dt>
                    <dd>
                        <?php if ($isLowStock): ?>
                            <span class="badge badge--error">Low Stock</span>
                        <?php else: ?>
                            <span class="badge badge--active">Healthy</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <p class="text--muted" style="margin-top: 0.75rem; font-size: 0.875rem;">
                Threshold trigger for automated operational replenishment warnings when aggregated stock drops below this value.
            </p>
        </div>
    </div>

    <!-- Right column: Network Stock Position -->
    <div class="detail-grid__side">
        <div class="card stock-network-card">
            <div class="card__header">
                <h2 class="section-title" style="margin: 0;">Network Stock Position</h2>
                <span class="page-header__pill"><?= $activeCount ?> Facility<?= $activeCount === 1 ? '' : 'ies' ?></span>
            </div>

            <div class="stock-network-card__hero">
                <div class="stock-network-card__label">Aggregate Stock On Hand</div>
                <?php if ($isLowStock): ?>
                    <div class="stock-network-card__badge"><span class="badge badge--error">Low Stock Warning</span></div>
                <?php endif; ?>
                <div class="stock-network-card__value">
                    <?= $totalStock ?>
                    <span class="stock-network-card__unit"><?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if ($isLowStock && $reorderPoint > 0): ?>
                    <div class="stock-network-card__delta">
                        -<?= $deficit ?> <?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?> below target
                    </div>
                <?php endif; ?>
                <div class="stock-network-card__threshold">
                    <span>Target Reorder: <?= $reorderPoint ?> <?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($reorderPoint > 0): ?>
                        <?php $consumptionPct = min(100, (int) round(($totalStock / $reorderPoint) * 100)); ?>
                        <span>Buffer: <?= $consumptionPct ?>%</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($breakdown) > 0): ?>
                <ul class="stock-network-card__facilities">
                    <?php foreach ($breakdown as $row): ?>
                        <?php
                            $facilityQty = (int) $row['quantity'];
                            $facilityStatus = match (true) {
                                !$row['warehouse_active']      => ['Deactivated', 'badge--inactive'],
                                $facilityQty === 0             => ['Depleted', 'badge--error'],
                                $facilityQty < $reorderPoint    => ['Low', 'badge--warning'],
                                default                        => ['Healthy', 'badge--active'],
                            };
                        ?>
                        <li class="stock-network-card__facility">
                            <div class="stock-network-card__facility-main">
                                <span class="stock-network-card__facility-name">
                                    <?= htmlspecialchars($row['warehouse_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="stock-network-card__facility-code">(<?= htmlspecialchars($row['warehouse_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                </span>
                                <span class="stock-network-card__facility-qty">
                                    <strong><?= $facilityQty ?></strong>
                                    <?= htmlspecialchars($product->unit, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="stock-network-card__facility-meta">
                                <span class="badge <?= $facilityStatus[1] ?>"><?= $facilityStatus[0] ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text--muted">No warehouse stock records yet.</p>
            <?php endif; ?>

            <div class="callout" role="note">
                <strong>Direct physical ledger edits are prohibited.</strong>
                Stock quantities update strictly via verified Purchase Order Goods Receipts or Sales Order Goods Issues.
            </div>
        </div>
    </div>
</div>

<!-- Stock by Warehouse table (Stitch §12.4 Product Detail) -->
<div class="card" style="margin-top: var(--space-6);">
    <h2 class="section-title" style="margin-top: 0;">Stock by Warehouse</h2>
    <p class="text--muted" style="margin-bottom: var(--space-4);">
        Physical stock distribution across authorized logistics facilities. Stock adjustments occur exclusively via Goods Receipt (PO) or Goods Issue (SO).
    </p>

    <?php if (count($breakdown) === 0): ?>
        <div class="empty-state-card">
            <p><strong>No stock records yet</strong></p>
            <p class="empty-state-card__hint">Stock balances initialize automatically when a Goods Receipt (PO) is confirmed.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Warehouse Facility</th>
                    <th>Code</th>
                    <th style="text-align: right;">Quantity On Hand</th>
                    <th style="text-align: right;">Reorder Threshold</th>
                    <th style="text-align: center;">Operational Status</th>
                    <th style="text-align: right;">Last Physical Movement</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($breakdown as $row): ?>
                    <?php
                        $facilityQty = (int) $row['quantity'];
                        $facilityStatus = match (true) {
                            !$row['warehouse_active']   => ['Deactivated', 'badge--inactive'],
                            $facilityQty === 0          => ['Depleted', 'badge--error'],
                            $facilityQty < $reorderPoint => ['Low', 'badge--warning'],
                            default                     => ['Healthy', 'badge--active'],
                        };
                        $lastMovement = $lastMovements[$row['warehouse_id']] ?? null;
                        $lastMovementDisplay = '-';
                        if ($lastMovement !== null && $lastMovement->doneAt !== null) {
                            $lastMovementDisplay = htmlspecialchars($lastMovement->doneAt->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['warehouse_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="font-mono"><?= htmlspecialchars($row['warehouse_code'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="text-align: right;"><?= $facilityQty ?></td>
                        <td style="text-align: right;"><?= $reorderPoint ?></td>
                        <td style="text-align: center;">
                            <span class="badge <?= $facilityStatus[1] ?>"><?= $facilityStatus[0] ?></span>
                        </td>
                        <td style="text-align: right;"><?= $lastMovementDisplay ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="2"><strong>Total Network Stock (<?= $activeCount ?> Active Facilit<?= $activeCount === 1 ? 'y' : 'ies' ?>)</strong></td>
                    <td style="text-align: right;"><strong><?= $totalStock ?></strong></td>
                    <td style="text-align: right;"><strong><?= $reorderPoint ?></strong> ref</td>
                    <td colspan="2" style="text-align: center;">
                        <?php if ($isLowStock): ?>
                            <span class="badge badge--warning">Buffer Depleted</span>
                        <?php else: ?>
                            <span class="badge badge--active">Ledger Balanced</span>
                        <?php endif; ?>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <div class="callout" role="note" style="margin-top: var(--space-5);">
        <strong>Physical Inventory Governance.</strong>
        Warehouse master records define operational facilities and spatial logistics boundaries. Physical stock balances, quantity adjustments, and bin-to-bin transfers are recorded strictly via verified Purchase Order Goods Receipts and Sales Order Goods Issues within the Stock Ledger.
    </div>
</div>

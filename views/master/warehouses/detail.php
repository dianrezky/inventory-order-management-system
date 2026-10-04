<?php

/** @var \App\Entity\Warehouse $warehouse */
/** @var list<array<string, mixed>> $stocks */
/** @var int $stockTotal */
/** @var int $stockCount */
/** @var int $stockPage */
/** @var int $stockPerPage */
/** @var int $stockTotalPages */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
?>
<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($warehouse->name, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="page-header__actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--secondary" href="/warehouses/<?= $idObfuscator->encode($warehouse->id) ?>/edit" data-i18n="common.edit">Edit</a>
        <?php endif; ?>
        <a class="btn btn--tertiary" href="/warehouses" data-i18n="common.back">Back</a>
    </div>
</div>

<div class="card">
    <dl class="detail-grid">
        <div>
            <dt data-i18n="warehouses.code">Code</dt>
            <dd><?= htmlspecialchars($warehouse->code, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div>
            <dt data-i18n="warehouses.location">Location</dt>
            <dd><?= htmlspecialchars($warehouse->location ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div>
            <dt data-i18n="common.status">Status</dt>
            <dd>
                <span class="badge <?= $warehouse->isActive ? 'badge--active' : 'badge--inactive' ?>">
                    <?= htmlspecialchars(($warehouse->isActive ? 'Active' : 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </dd>
        </div>
        <div>
            <dt data-i18n="common.created_at">Created At</dt>
            <dd><?= htmlspecialchars($warehouse->createdAt?->format('Y-m-d H:i') ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>
</div>

<div class="dashboard-kpi-grid" style="margin-top:var(--space-4)">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Products Stocked Here</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= number_format($stockCount, 0, ',', '.') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Stock Quantity</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= number_format($stockTotal, 0, ',', '.') ?> Unit</div>
        </div>
    </div>
</div>

<div class="dashboard-section" style="margin-top:var(--space-4)">
    <h2 class="dashboard-section__title">Stock by Product</h2>
    <?php if (count($stocks) === 0): ?>
        <div class="empty-state-card">
            <p><strong>No products stocked at this warehouse</strong></p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Stock</th>
                    <th>Reorder Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $row['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="<?= (int) $row['quantity'] < (int) $row['reorder_point'] ? 'badge badge--warning' : 'badge badge--active' ?>">
                            <?= (int) $row['quantity'] ?> Unit
                        </span>
                    </td>
                    <td><?= (int) $row['reorder_point'] ?> Unit</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($stockTotalPages > 1): ?>
    <div class="pagination-footer">
        <div class="pagination-footer__summary">
            Showing <?= number_format((($stockPage - 1) * $stockPerPage) + 1, 0, ',', '.') ?>
            to <?= number_format(min($stockPage * $stockPerPage, $stockCount), 0, ',', '.') ?>
            of <?= number_format($stockCount, 0, ',', '.') ?> products
        </div>
        <div class="pagination-footer__controls">
            <form method="post" action="/warehouses/<?= $idObfuscator->encode($warehouse->id) ?>/stock" class="pagination-nav" aria-label="Stock by product pagination">
                <?php if ($stockPage > 1): ?>
                <button type="submit" name="stock_page" value="<?= $stockPage - 1 ?>" class="btn btn--secondary btn--sm" aria-label="Previous page" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">&laquo; Prev</button>
                <?php endif; ?>

                <?php
                $stockPageStart = max(1, $stockPage - 2);
                $stockPageEnd = min($stockTotalPages, $stockPage + 2);
                for ($i = $stockPageStart; $i <= $stockPageEnd; $i++):
                ?>
                <button type="submit" name="stock_page" value="<?= $i ?>"
                   class="btn btn--sm <?= $i === $stockPage ? 'btn--primary' : 'btn--secondary' ?>"
                   aria-label="Page <?= $i ?>"
                   <?= $i === $stockPage ? 'aria-current="page"' : '' ?>
                   style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;">
                    <?= $i ?>
                </button>
                <?php endfor; ?>

                <?php if ($stockPage < $stockTotalPages): ?>
                <button type="submit" name="stock_page" value="<?= $stockPage + 1 ?>" class="btn btn--secondary btn--sm" aria-label="Next page" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">Next &raquo;</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

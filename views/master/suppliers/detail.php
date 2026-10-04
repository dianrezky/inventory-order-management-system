<?php

/** @var \App\Entity\Supplier $supplier */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/suppliers">Suppliers</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($supplier->name, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= htmlspecialchars($supplier->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="badge <?= $supplier->isActive ? 'badge--active' : 'badge--inactive' ?>">
            <?= htmlspecialchars($supplier->isActive ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <p class="page-header__subtitle">
        <?php if ($supplier->contactPerson !== null): ?>
            <?= htmlspecialchars($supplier->contactPerson, ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
            Supplier master record
        <?php endif; ?>
        <?php if ($supplier->phone !== null): ?>
            · <?= htmlspecialchars($supplier->phone, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
    <div class="page-header__actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--secondary" href="/suppliers/<?= $idObfuscator->encode($supplier->id) ?>/edit">Edit</a>
            <form class="form--inline" method="post" action="/suppliers/<?= $idObfuscator->encode($supplier->id) ?>/<?= $supplier->isActive ? 'deactivate' : 'activate' ?>"
                  data-ajax-status>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn <?= $supplier->isActive ? 'btn--destructive' : 'btn--secondary' ?>">
                    <?= htmlspecialchars($supplier->isActive ? 'Deactivate' : 'Reactivate', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        <?php endif; ?>
        <a class="btn btn--tertiary" href="/suppliers">Back</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Contact</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;">
                <?= htmlspecialchars($supplier->contactPerson ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                <?= htmlspecialchars($supplier->phone ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Email</span>
        </div>
        <div class="stat-card__body">
            <?php if ($supplier->email !== null): ?>
                <div class="stat-card__value" style="font-size: 16px;">
                    <a href="mailto:<?= htmlspecialchars($supplier->email, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($supplier->email, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="text--muted">—</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Address</span>
        </div>
        <div class="stat-card__body">
            <div style="font-size: 13px; line-height: 1.5;">
                <?= htmlspecialchars($supplier->address ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
</div>

<div class="callout" role="note" style="margin-top: 1rem;">
    <strong>Procurement Master Data Governance.</strong>
    Supplier master data is referenced by Purchase Order Goods Receipts and accounts payable workflows.
    Deactivated suppliers cannot receive new Purchase Orders; historical procurement records are preserved for audit.
</div>

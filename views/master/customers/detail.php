<?php

/** @var \App\Entity\Customer $customer */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/customers">Customers</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($customer->name, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= htmlspecialchars($customer->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="badge <?= $customer->isActive ? 'badge--active' : 'badge--inactive' ?>">
            <?= htmlspecialchars($customer->isActive ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <p class="page-header__subtitle">
        <?php if ($customer->contactPerson !== null): ?>
            <?= htmlspecialchars($customer->contactPerson, ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
            Customer master record
        <?php endif; ?>
        <?php if ($customer->phone !== null): ?>
            · <?= htmlspecialchars($customer->phone, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
    <div class="page-header__actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--secondary" href="/customers/<?= $idObfuscator->encode($customer->id) ?>/edit">Edit</a>
            <form class="form--inline" method="post" action="/customers/<?= $idObfuscator->encode($customer->id) ?>/<?= $customer->isActive ? 'deactivate' : 'activate' ?>"
                  data-ajax-status>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn <?= $customer->isActive ? 'btn--destructive' : 'btn--secondary' ?>">
                    <?= htmlspecialchars($customer->isActive ? 'Deactivate' : 'Reactivate', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        <?php endif; ?>
        <a class="btn btn--tertiary" href="/customers">Back</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Contact</span>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size: 18px;">
                <?= htmlspecialchars($customer->contactPerson ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text--muted" style="margin-top: 0.25rem; font-size: 13px;">
                <?= htmlspecialchars($customer->phone ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Email</span>
        </div>
        <div class="stat-card__body">
            <?php if ($customer->email !== null): ?>
                <div class="stat-card__value" style="font-size: 16px;">
                    <a href="mailto:<?= htmlspecialchars($customer->email, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($customer->email, ENT_QUOTES, 'UTF-8') ?>
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
                <?= htmlspecialchars($customer->address ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
</div>

<div class="callout" role="note" style="margin-top: 1rem;">
    <strong>Sales Order Master Data Governance.</strong>
    Customer master data is referenced by Sales Orders, Goods Issue, and invoice workflows.
    Deactivated customers cannot receive new orders; existing order history is preserved for audit.
</div>

<?php

/** @var \App\Entity\Category $category */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/categories">Categories</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($isAdmin): ?>
            <div class="page-header__actions">
                <a class="btn btn--secondary" href="/categories/<?= $idObfuscator->encode($category->id) ?>/edit">Edit</a>
                <form class="form--inline" method="post"
                      action="/categories/<?= $idObfuscator->encode($category->id) ?>/<?= $category->isActive ? 'deactivate' : 'activate' ?>"
                      data-ajax-status>
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn <?= $category->isActive ? 'btn--destructive' : 'btn--secondary' ?>">
                        <?= $category->isActive ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="detail-card">
    <dl class="detail-list">
        <div class="detail-list__row">
            <dt>Name</dt>
            <dd><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="detail-list__row">
            <dt>Description</dt>
            <dd><?= $category->description !== null ? htmlspecialchars($category->description, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">No description</span>' ?></dd>
        </div>
        <div class="detail-list__row">
            <dt>Status</dt>
            <dd>
                <span class="badge <?= $category->isActive ? 'badge--active' : 'badge--inactive' ?>">
                    <?= $category->isActive ? 'Active' : 'Inactive' ?>
                </span>
            </dd>
        </div>
        <div class="detail-list__row">
            <dt>Created</dt>
            <dd><?= htmlspecialchars($category->createdAt?->format('Y-m-d H:i') ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>
</div>

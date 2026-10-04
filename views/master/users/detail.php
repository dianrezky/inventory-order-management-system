<?php

/** @var \App\Entity\User $user */
?>
<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="page-header__actions">
        <a class="btn btn--secondary" href="/users/<?= $idObfuscator->encode($user->id) ?>/edit" data-i18n="common.edit">Edit</a>
        <a class="btn btn--tertiary" href="/users" data-i18n="common.back">Back</a>
    </div>
</div>

<div class="card">
    <dl class="detail-grid">
        <div>
            <dt data-i18n="users.email">Email</dt>
            <dd><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div>
            <dt data-i18n="users.role">Role</dt>
            <dd><?= htmlspecialchars($user->role->label(), ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div>
            <dt data-i18n="common.status">Status</dt>
            <dd>
                <span class="badge <?= $user->isActive ? 'badge--active' : 'badge--inactive' ?>">
                    <?= htmlspecialchars(($user->isActive ? 'Active' : 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </dd>
        </div>
        <div>
            <dt data-i18n="common.created_at">Created At</dt>
            <dd><?= htmlspecialchars($user->createdAt?->format('Y-m-d H:i') ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>
</div>

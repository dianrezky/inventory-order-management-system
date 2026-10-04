<?php

/** @var list<\App\Entity\User> $users */
/** @var string|null $filterName */
/** @var string|null $email */
/** @var list<string>|null $statuses */
/** @var list<string>|null $roles */

$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
$selectedStatuses = $statuses ?? [];
$roleOptions = [];
foreach (\App\Entity\Role::cases() as $rc) {
    $roleOptions[$rc->value] = $rc->label();
}
$selectedRoles = $roles ?? [];
$hasFilter = ($filterName ?? '') !== '' || ($email ?? '') !== '';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Administration</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Users</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Users</h1>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/users/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add User
            </a>
        </div>
    </div>
    <p class="page-header__subtitle"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?> registered</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter state never lands in the URL. -->
    <form method="post" action="/users/search" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="user-name">Name</label>
                <input class="input" type="search" id="user-name" name="name"
                       value="<?= htmlspecialchars($filterName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Name…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="user-email">Email</label>
                <input class="input" type="search" id="user-email" name="email"
                       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Email Address…">
            </div>
            <?php
            $name = 'role'; $label = 'Role';
            $options = $roleOptions; $selected = $selectedRoles;
            $id = 'user-role'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'user-status'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/users">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($users) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-users"></use></svg>
        <p><strong>No users found</strong></p>
        <p class="empty-state-card__hint"><?php if ($hasFilter): ?>No results match the current filters. Try different search terms.<?php else: ?>Get started by adding your first user.<?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th data-i18n="users.name">Name</th>
                <th data-i18n="users.email">Email</th>
                <th data-i18n="users.role">Role</th>
                <th data-i18n="common.status">Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user->role->label(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $user->isActive ? 'badge--active' : 'badge--inactive' ?>">
                            <?= $user->isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="table__actions">
                        <div class="row-actions">
                            <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?>">
                                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                            </button>
                            <div class="row-actions__menu" role="menu" hidden>
                                <a class="row-actions__item row-actions__item--view" role="menuitem" href="/users/<?= $idObfuscator->encode($user->id) ?>">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-eye"></use></svg>
                                    View
                                </a>
                                <a class="row-actions__item row-actions__item--edit" role="menuitem" href="/users/<?= $idObfuscator->encode($user->id) ?>/edit">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                    Edit
                                </a>
                                <?php // The service refuses self-deactivation; don't offer it ?>
                                <?php if (!($user->isActive && $user->id === ($currentUserId ?? null))): ?>
                                <form method="post" action="/users/<?= $idObfuscator->encode($user->id) ?>/<?= $user->isActive ? 'deactivate' : 'activate' ?>" data-ajax-status>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="row-actions__item <?= $user->isActive ? 'row-actions__item--destructive' : 'row-actions__item--activate' ?>" role="menuitem">
                                        <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-power"></use></svg>
                                        <?= $user->isActive ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

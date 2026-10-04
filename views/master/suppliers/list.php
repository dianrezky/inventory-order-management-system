<?php

/** @var list<\App\Entity\Supplier> $suppliers */
/** @var string|null $filterName */
/** @var string|null $contactPerson */
/** @var string|null $email */
/** @var list<string>|null $statuses */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
$selectedStatuses = $statuses ?? [];
$hasFilter = ($filterName ?? '') !== '' || ($contactPerson ?? '') !== '' || ($email ?? '') !== '';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Suppliers</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Suppliers</h1>
        <?php if ($isAdmin): ?>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/suppliers/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add Supplier
            </a>
        </div>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle"><?= count($suppliers) ?> supplier<?= count($suppliers) === 1 ? '' : 's' ?> registered</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter state never lands in the URL. -->
    <form method="post" action="/suppliers/search" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="supplier-name">Supplier Name</label>
                <input class="input" type="search" id="supplier-name" name="name"
                       value="<?= htmlspecialchars($filterName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Supplier Name…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="supplier-contact-person">Contact Person</label>
                <input class="input" type="search" id="supplier-contact-person" name="contact_person"
                       value="<?= htmlspecialchars($contactPerson ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Contact Person…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="supplier-email">Email</label>
                <input class="input" type="search" id="supplier-email" name="email"
                       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Email Address…">
            </div>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'supplier-status'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/suppliers">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($suppliers) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg>
        <p><strong>No suppliers found</strong></p>
        <p class="empty-state-card__hint"><?php if ($hasFilter): ?>No results match the current filters. Try different search terms.<?php else: ?>Get started by adding your first supplier.<?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th data-i18n="suppliers.name">Name</th>
                <th data-i18n="suppliers.contact_person">Contact Person</th>
                <th data-i18n="suppliers.phone">Phone</th>
                <th data-i18n="suppliers.email">Email</th>
                <th data-i18n="common.status">Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= htmlspecialchars($supplier->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($supplier->contactPerson ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($supplier->phone ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($supplier->email ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $supplier->isActive ? 'badge--active' : 'badge--inactive' ?>">
                            <?= $supplier->isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="table__actions">
                        <div class="row-actions">
                            <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($supplier->name, ENT_QUOTES, 'UTF-8') ?>">
                                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                            </button>
                            <div class="row-actions__menu" role="menu" hidden>
                                <a class="row-actions__item row-actions__item--view" role="menuitem" href="/suppliers/<?= $idObfuscator->encode($supplier->id) ?>">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-eye"></use></svg>
                                    View
                                </a>
                                <?php if ($isAdmin): ?>
                                <a class="row-actions__item row-actions__item--edit" role="menuitem" href="/suppliers/<?= $idObfuscator->encode($supplier->id) ?>/edit">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                    Edit
                                </a>
                                <form method="post" action="/suppliers/<?= $idObfuscator->encode($supplier->id) ?>/<?= $supplier->isActive ? 'deactivate' : 'activate' ?>" data-ajax-status>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="row-actions__item <?= $supplier->isActive ? 'row-actions__item--destructive' : 'row-actions__item--activate' ?>" role="menuitem">
                                        <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-power"></use></svg>
                                        <?= $supplier->isActive ? 'Deactivate' : 'Activate' ?>
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

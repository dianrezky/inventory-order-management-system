<?php

/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var string|null $code */
/** @var string|null $filterName */
/** @var string|null $location */
/** @var list<string>|null $statuses */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
$selectedStatuses = $statuses ?? [];
$hasFilter = ($code ?? '') !== '' || ($filterName ?? '') !== '' || ($location ?? '') !== '';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Warehouses</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Warehouses</h1>
        <?php if ($isAdmin): ?>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/warehouses/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add Warehouse
            </a>
        </div>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle"><?= count($warehouses) ?> warehouse<?= count($warehouses) === 1 ? '' : 's' ?> registered</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter state never lands in the URL. -->
    <form method="post" action="/warehouses/search" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="warehouse-code">Warehouse Code</label>
                <input class="input" type="search" id="warehouse-code" name="code"
                       value="<?= htmlspecialchars($code ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Warehouse Code…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="warehouse-name">Warehouse Name</label>
                <input class="input" type="search" id="warehouse-name" name="name"
                       value="<?= htmlspecialchars($filterName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Warehouse Name…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="warehouse-location">Location</label>
                <input class="input" type="search" id="warehouse-location" name="location"
                       value="<?= htmlspecialchars($location ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Location…">
            </div>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'warehouse-status'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/warehouses">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($warehouses) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-warehouse"></use></svg>
        <p><strong>No warehouses found</strong></p>
        <p class="empty-state-card__hint"><?php if ($hasFilter): ?>No results match the current filters. Try different search terms.<?php else: ?>Get started by adding your first warehouse.<?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th data-i18n="warehouses.code">Code</th>
                <th data-i18n="warehouses.name">Name</th>
                <th data-i18n="warehouses.location">Location</th>
                <th data-i18n="common.status">Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($warehouses as $warehouse): ?>
                <tr>
                    <td><?= htmlspecialchars($warehouse->code, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($warehouse->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($warehouse->location ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $warehouse->isActive ? 'badge--active' : 'badge--inactive' ?>">
                            <?= $warehouse->isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="table__actions">
                        <div class="row-actions">
                            <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($warehouse->name, ENT_QUOTES, 'UTF-8') ?>">
                                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                            </button>
                            <div class="row-actions__menu" role="menu" hidden>
                                <a class="row-actions__item row-actions__item--view" role="menuitem" href="/warehouses/<?= $idObfuscator->encode($warehouse->id) ?>">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-eye"></use></svg>
                                    View
                                </a>
                                <?php if ($isAdmin): ?>
                                <a class="row-actions__item row-actions__item--edit" role="menuitem" href="/warehouses/<?= $idObfuscator->encode($warehouse->id) ?>/edit">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                    Edit
                                </a>
                                <form method="post" action="/warehouses/<?= $idObfuscator->encode($warehouse->id) ?>/<?= $warehouse->isActive ? 'deactivate' : 'activate' ?>" data-ajax-status>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="row-actions__item <?= $warehouse->isActive ? 'row-actions__item--destructive' : 'row-actions__item--activate' ?>" role="menuitem">
                                        <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-power"></use></svg>
                                        <?= $warehouse->isActive ? 'Deactivate' : 'Activate' ?>
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

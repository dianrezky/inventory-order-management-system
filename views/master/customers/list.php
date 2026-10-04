<?php

/** @var list<\App\Entity\Customer> $customers */
/** @var string|null $filterName */
/** @var string|null $email */
/** @var string|null $phone */
/** @var string|null $contactPerson */
/** @var list<string>|null $statuses */
/** @var string|null $currentUserRole */

$isAdmin = $currentUserRole === 'Admin';
$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
$selectedStatuses = $statuses ?? [];
$hasFilter = ($filterName ?? '') !== '' || ($email ?? '') !== '' || ($phone ?? '') !== '' || ($contactPerson ?? '') !== '';
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Customers</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">Customers</h1>
        <?php if ($isAdmin): ?>
        <div class="page-header__actions">
            <a class="btn btn--primary" href="/customers/create">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
                Add Customer
            </a>
        </div>
        <?php endif; ?>
    </div>
    <p class="page-header__subtitle"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?> registered</p>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
    <!-- POST: filter state never lands in the URL. -->
    <form method="post" action="/customers/search" novalidate>
        <div class="filter-grid">
            <div class="form-field">
                <label class="form-field__label" for="customer-name">Name</label>
                <input class="input" type="search" id="customer-name" name="name"
                       value="<?= htmlspecialchars($filterName ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Customer Name…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="customer-contact-person">Contact Person</label>
                <input class="input" type="search" id="customer-contact-person" name="contact_person"
                       value="<?= htmlspecialchars($contactPerson ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Contact Person…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="customer-phone">Phone</label>
                <input class="input" type="search" id="customer-phone" name="phone"
                       value="<?= htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Phone Number…">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="customer-email">Email</label>
                <input class="input" type="search" id="customer-email" name="email"
                       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter Email Address…">
            </div>
            <?php
            $name = 'status'; $label = 'Status';
            $options = $statusOptions; $selected = $selectedStatuses;
            $id = 'customer-status'; $placeholder = null;
            include __DIR__ . '/../../_multi-select.php';
            ?>
        </div>
        <div class="form-actions form-actions--search-right">
            <a class="btn btn--secondary" href="/customers">Reset</a>
            <button type="submit" class="btn btn--primary">Search</button>
        </div>
    </form>
</div>

<?php if (count($customers) === 0): ?>
    <div class="empty-state-card">
        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-users"></use></svg>
        <p><strong>No customers found</strong></p>
        <p class="empty-state-card__hint"><?php if ($hasFilter): ?>No results match the current filters. Try different search terms.<?php else: ?>Get started by adding your first customer.<?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th data-i18n="customers.name">Name</th>
                <th data-i18n="customers.contact_person">Contact Person</th>
                <th data-i18n="customers.phone">Phone</th>
                <th data-i18n="customers.email">Email</th>
                <th data-i18n="common.status">Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= htmlspecialchars($customer->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($customer->contactPerson ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($customer->phone ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($customer->email ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $customer->isActive ? 'badge--active' : 'badge--inactive' ?>">
                            <?= $customer->isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="table__actions">
                        <div class="row-actions">
                            <button type="button" class="row-actions__trigger" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($customer->name, ENT_QUOTES, 'UTF-8') ?>">
                                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-more-vertical"></use></svg>
                            </button>
                            <div class="row-actions__menu" role="menu" hidden>
                                <a class="row-actions__item row-actions__item--view" role="menuitem" href="/customers/<?= $idObfuscator->encode($customer->id) ?>">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-eye"></use></svg>
                                    View
                                </a>
                                <?php if ($isAdmin): ?>
                                <a class="row-actions__item row-actions__item--edit" role="menuitem" href="/customers/<?= $idObfuscator->encode($customer->id) ?>/edit">
                                    <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-edit-2"></use></svg>
                                    Edit
                                </a>
                                <form method="post" action="/customers/<?= $idObfuscator->encode($customer->id) ?>/<?= $customer->isActive ? 'deactivate' : 'activate' ?>" data-ajax-status>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="row-actions__item <?= $customer->isActive ? 'row-actions__item--destructive' : 'row-actions__item--activate' ?>" role="menuitem">
                                        <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-power"></use></svg>
                                        <?= $customer->isActive ? 'Deactivate' : 'Activate' ?>
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

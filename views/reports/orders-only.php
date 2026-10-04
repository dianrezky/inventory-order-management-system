<?php

/** @var string $dateFrom */
/** @var string $dateTo */
/** @var bool $canExportOrders */

// Role matrix "Mengunduh laporan (CSV)": Sales only downloads its own orders,
// so this page carries no stock valuation, ledger or inventory analytics.
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Reports</span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title">Reports</h1>
    </div>

    <p class="page-header__subtitle">
        Export your sales orders within a date range as CSV.
    </p>
</div>

<div class="report-filter-card">
    <div class="report-filter-card__header">
        <span class="report-filter-card__title">Order Export</span>
        <span class="report-filter-card__preset">DEFAULT: LAST 30 DAYS</span>
    </div>

    <form method="post" action="/reports/export/orders" id="report-filter-form" novalidate>
        <div class="report-filter-grid">
            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="date_from">Date Range <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                </div>
                <div style="display:flex;align-items:center;gap:var(--space-2);">
                    <input class="input" type="date" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"
                           required style="min-height:44px;" aria-required="true">
                    <span style="color:var(--color-text-secondary);font-size:var(--font-size-body-sm);flex-shrink:0;">to</span>
                    <input class="input" type="date" id="date_to" name="date_to"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"
                           required style="min-height:44px;" aria-required="true">
                </div>
            </div>

            <?php if ($canExportOrders): ?>
            <button type="submit" class="btn btn--primary" id="export-orders-btn" style="min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-5);">
                Export Orders
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

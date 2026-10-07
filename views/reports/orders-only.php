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
        Preview your sales orders within a date range, then export them as CSV.
    </p>
</div>

<div class="report-filter-card">
    <div class="report-filter-card__header">
        <span class="report-filter-card__title">Order Export</span>
        <span class="report-filter-card__preset">DEFAULT: LAST 30 DAYS</span>
    </div>

    <form method="post" action="/reports/export/orders" id="report-filter-form" novalidate>
        <div class="report-export-row">
            <div class="form-field report-export-row__range">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="date_from">Date Range <span class="report-export-row__required" aria-hidden="true">*</span></label>
                </div>
                <div class="report-export-row__dates">
                    <input class="input" type="date" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"
                           required aria-required="true">
                    <span class="report-export-row__sep">to</span>
                    <input class="input" type="date" id="date_to" name="date_to" aria-label="End date"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"
                           required aria-required="true">
                </div>
            </div>

            <?php if ($canExportOrders): ?>
            <div style="display:flex;gap:var(--space-2);">
                <?php // Preview is type="button" and listed after nothing: Enter in a date field must not start a download, so Export stays the only submit button. ?>
                <button type="button" class="btn btn--secondary" id="order-preview-btn" style="min-height:44px;padding:0 var(--space-5);">
                    Preview
                </button>
                <button type="submit" class="btn btn--primary" id="export-orders-btn" style="min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-5);">
                    Export Orders
                </button>
            </div>
            <?php endif; ?>
        </div>
        <p class="text--muted" style="margin:var(--space-3) 0 0;font-size:var(--font-size-body-sm);">
            Preview shows the same rows and columns as the CSV file.
        </p>
    </form>
</div>

<?php if ($canExportOrders): ?>
<?php require __DIR__ . '/_order-preview.php'; ?>
<?php endif; ?>

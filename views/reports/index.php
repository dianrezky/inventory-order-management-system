<?php

/** @var string $csrfToken */
/** @var int $activeSku */
/** @var int $lowSku */
/** @var int $outSku */
/** @var int $inboundQty */
/** @var int $outboundQty */
/** @var int $netQty */
/** @var float $turnoverVelocity */
/** @var float $totalValuation */
/** @var float $categoryTotal */
/** @var array $categoryData */
/** @var array $trendData */
/** @var array $lineItems */
/** @var int $lineItemsTotal */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
/** @var string $dateRangeLabel */
/** @var string $reportType */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var int $warehouseId */
/** @var int $categoryId */
/** @var string $search */
/** @var string $sort */
/** @var bool $canExport */
/** @var bool $canExportOrders */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var list<\App\Entity\Category> $categories */
/** @var array $whData */
/** @var float $whGrandTotal */

$startRecord = $lineItemsTotal > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endRecord   = min($page * $perPage, $lineItemsTotal);

// Valuation change vs. the end of last month — both points come from the
// ledger-reconstructed trend series, so this is a real comparison (it used to
// be a hardcoded "+4.2%"). Shown in Rupiah, not %, so a near-zero baseline
// (e.g. stock first received this month) can't blow up into "+1.000.000.000%".
$valuationNow   = (float) ($trendData[count($trendData) - 1]['valuation'] ?? 0);
$valuationPrev  = (float) ($trendData[count($trendData) - 2]['valuation'] ?? 0);
$valuationDelta = $valuationNow - $valuationPrev;
$movedSkuPct = $activeSku > 0 ? round($movedSku / $activeSku * 100, 1) : 0;

// Format helpers
$fmtRp   = static fn($v) => 'Rp ' . number_format((float) $v, 2, ',', '.');
$fmtNum  = static fn(int $v): string => number_format($v, 0, ',', '.');

// $sortOptions comes from ReportService::SORT_OPTIONS — only the sorts the
// selected report type can actually apply.
$CATEGORY_COLORS  = ['#1E3A8A', '#0284C7', '#3B82F6', '#64748B', '#94A3B8'];
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
        <div class="page-header__actions" style="display:flex;gap:var(--space-2);">
            <?php // Must stay first in DOM: it is the form's default button, so Enter in any filter field searches instead of triggering an export. ?>
            <button class="btn btn--primary" style="order:3;min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-4);" id="generate-report-btn" form="report-filter-form" type="submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Generate Report
            </button>
            <?php if ($canExport): ?>
            <button type="submit" form="report-filter-form" formaction="/reports/export/stock-ledger" formmethod="post" class="btn btn--secondary" style="border-color:#E1E4E8;color:#1A1D23;min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-4);" id="export-csv-btn">
                <span style="width:8px;height:8px;border-radius:2px;background:#15803D;display:inline-block;flex-shrink:0;" aria-hidden="true"></span>
                Export Stock Ledger
            </button>
            <?php endif; ?>
            <?php if ($canExportOrders): ?>
            <button type="submit" form="report-filter-form" formaction="/reports/export/orders" formmethod="post" class="btn btn--secondary" style="border-color:#E1E4E8;color:#1A1D23;min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-4);" id="export-orders-btn">
                <span style="width:8px;height:8px;border-radius:2px;background:#15803D;display:inline-block;flex-shrink:0;" aria-hidden="true"></span>
                Export Orders
            </button>
            <?php endif; ?>
        </div>
    </div>

    <p class="page-header__subtitle">
        Generate operational reports, analyze historical inventory movements, and export data audits.
    </p>
</div>

<!-- ── Report Query Parameters Card ─────────────────────────────── -->
<div class="report-filter-card">
    <div class="report-filter-card__header">
        <span class="report-filter-card__title">Report Query Parameters</span>
        <span class="report-filter-card__preset">DEFAULT: SNAPSHOT LAST 30 DAYS</span>
    </div>

    <form method="post" action="/reports/search" id="report-filter-form" novalidate>
        <div class="report-filter-grid">
            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="report_type">Report Type <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Report type information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The type of operational report to generate. Stock Valuation &amp; Turnover Summary provides an overview of current inventory value and movement velocity. Inventory Aging Analysis shows how long items have been in stock. Slow-Moving &amp; Dead Stock identifies items with low or no turnover. Movement Ledger Audit provides a detailed chronological record of all stock transactions.</span></button>
                </div>
                <select class="input" id="report_type" name="report_type" required style="min-height:44px;" aria-required="true">
                    <option value="stock_valuation" <?= $reportType === 'stock_valuation' ? 'selected' : '' ?>>Stock Valuation &amp; Turnover Summary</option>
                    <option value="inventory_aging" <?= $reportType === 'inventory_aging' ? 'selected' : '' ?>>Inventory Aging Analysis</option>
                    <option value="slow_moving" <?= $reportType === 'slow_moving' ? 'selected' : '' ?>>Slow-Moving &amp; Dead Stock</option>
                    <option value="movement_ledger" <?= $reportType === 'movement_ledger' ? 'selected' : '' ?>>Movement Ledger Audit</option>
                </select>
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="date_from">Date Range <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Date range information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The date range for the report query. The Start Date and End Date define the period of analysis. All metrics, charts, and tabular data will reflect transactions and stock levels within this range. Defaults to the last 30 days from today.</span></button>
                </div>
                <div style="display:flex;align-items:center;gap:var(--space-2);">
                    <input class="input" type="date" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"
                           required style="min-height:44px;" aria-required="true">
                    <span style="color:var(--color-text-secondary);font-size:var(--font-size-body-sm);flex-shrink:0;">to</span>
                    <input class="input" type="date" id="date_to" name="date_to" aria-label="End date"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"
                           required style="min-height:44px;" aria-required="true">
                </div>
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="warehouse_id">Warehouse Location</label>
                    <button type="button" class="field-info-icon" aria-label="Warehouse filter information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">Filter the report to show data for a specific warehouse location only. Select a warehouse from the list to narrow down the analysis to that facility. Select "All Warehouses" to include data from all locations.</span></button>
                </div>
                <select class="input" id="warehouse_id" name="warehouse_id" style="min-height:44px;">
                    <option value="">All Warehouses (National)</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w->id ?>" <?= $warehouseId === $w->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w->name, ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($w->location)): ?> — <?= htmlspecialchars($w->location, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="category_id">Product Category</label>
                    <button type="button" class="field-info-icon" aria-label="Category filter information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">Filter the report to show data for a specific product category only. Select a category to narrow the analysis to that product group. Select "All Categories" to include all product types in the report.</span></button>
                </div>
                <select class="input" id="category_id" name="category_id" style="min-height:44px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c->id ?>" <?= $categoryId === $c->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c->name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="report-filter-card__actions">
            <a class="btn-report-action-reset" href="/reports">Reset Filters</a>
            <button type="submit" class="btn btn--primary" id="apply-generate-btn" style="min-height:44px;display:inline-flex;align-items:center;gap:var(--space-2);padding:0 var(--space-5);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Apply &amp; Generate
            </button>
        </div>
    </form>
</div>

<!-- ── KPI Summary Metrics Cards ─────────────────────────────── -->
<div class="report-kpi-grid">
    <div class="report-kpi-card">
        <div class="report-kpi-card__label">Total Stock Valuation</div>
        <div class="report-kpi-card__value"><?= $fmtRp($totalValuation) ?></div>
        <div class="report-kpi-card__meta">
            <span class="report-kpi-card__change <?= $valuationDelta >= 0 ? 'report-kpi-card__change--up' : 'report-kpi-card__change--down' ?>"><?= $valuationDelta >= 0 ? '+' : '−' ?><?= $fmtRp(abs($valuationDelta)) ?></span>
            <span class="report-kpi-card__sub">vs end of last month</span>
        </div>
    </div>

    <div class="report-kpi-card">
        <div class="report-kpi-card__label">Active SKU Catalog</div>
        <div class="report-kpi-card__value"><?= number_format($activeSku, 0, ',', '.') ?> Items</div>
        <div class="report-kpi-card__meta">
            <span class="report-kpi-card__change report-kpi-card__change--up"><?= number_format($movedSkuPct, 1, ',', '.') ?>% moved in range</span>
            <span class="report-kpi-card__sub"><?= number_format($lowSku, 0, ',', '.') ?> low &middot; <?= number_format($outSku, 0, ',', '.') ?> out of stock</span>
        </div>
    </div>

    <div class="report-kpi-card">
        <div class="report-kpi-card__label">Inbound / Outbound</div>
        <div class="report-kpi-card__value"><?= $fmtNum($inboundQty) ?> In / <?= $fmtNum($outboundQty) ?> Out</div>
        <div class="report-kpi-card__meta">
            <span class="report-kpi-card__change <?= $netQty >= 0 ? 'report-kpi-card__change--up' : 'report-kpi-card__change--down' ?>">
                <?= $netQty >= 0 ? '+' : '' ?><?= $fmtNum($netQty) ?> Net
            </span>
            <span class="report-kpi-card__sub">net units moved in range</span>
        </div>
    </div>

    <div class="report-kpi-card">
        <div class="report-kpi-card__label">Turnover Velocity</div>
        <div class="report-kpi-card__value"><?= number_format($turnoverVelocity, 1, ',', '.') ?>x / yr</div>
        <div class="report-kpi-card__meta">
            <span class="report-kpi-card__change <?= $turnoverVelocity >= 4.0 ? 'report-kpi-card__change--up' : 'report-kpi-card__change--warn' ?>">
                <?= $turnoverVelocity >= 4.0 ? 'Healthy' : 'Below Target' ?>
            </span>
            <span class="report-kpi-card__sub">Benchmark Target: &gt;4.0x / yr</span>
        </div>
    </div>
</div>

<!-- ── Visual Analytics & Trends ─────────────────────────────── -->
<div class="report-visual-card">
    <div class="report-visual-card__header">
        <div>
            <h2 class="report-visual-card__title">Report Visual Analytics &amp; Trends</h2>
            <p class="report-visual-card__subtitle">Visual breakdown for Stock Valuation &amp; Turnover (<?= htmlspecialchars($dateRangeLabel, ENT_QUOTES, 'UTF-8') ?>)</p>
        </div>
    </div>

    <?php
    // Insight highlight: category with the highest real turnover, and the one
    // holding the most capital. Seeds start at 0 (not -INF): with no category
    // data, -INF reached $fmtRp(int) below and threw a TypeError.
    $fastestCat = null;
    $highestValCat = null;
    $maxVelocity = 0.0;
    $maxValuation = 0;
    foreach ($categoryData as $cat) {
        if ($cat['velocity'] > $maxVelocity) {
            $maxVelocity = $cat['velocity'];
            $fastestCat = $cat['name'];
        }
        if ($cat['valuation'] > $maxValuation) {
            $maxValuation = $cat['valuation'];
            $highestValCat = $cat['name'];
        }
    }
    ?>
    <div class="report-insight-pill">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        Fastest Turnover: <?= htmlspecialchars($fastestCat ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> &bull; Highest Capital: <?= htmlspecialchars($highestValCat ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (<?= $fmtRp($maxValuation) ?>)
    </div>

    <div class="report-view-tabs" role="tablist" aria-label="Visualization view mode">
        <button class="report-view-tab report-view-tab--active" role="tab" aria-selected="true" data-view="trend" id="tab-trend" aria-controls="panel-trend">Trend Overview (6-Month)</button>
        <button class="report-view-tab" role="tab" aria-selected="false" data-view="category" id="tab-category" aria-controls="panel-category">Category Distribution</button>
        <button class="report-view-tab" role="tab" aria-selected="false" data-view="warehouse" id="tab-warehouse" aria-controls="panel-warehouse">Warehouse Allocation</button>
    </div>

    <div class="report-visual-panels">
        <!-- Panel: Trend Overview -->
        <div class="report-visual-panel" id="panel-trend" role="tabpanel" aria-labelledby="tab-trend">
            <div class="report-chart-legend">
                <span class="report-chart-legend__item">
                    <span class="report-chart-legend__dot" style="background:#1E3A8A;"></span>Valuation (Rp M)
                </span>
                <span class="report-chart-legend__item">
                    <span class="report-chart-legend__dot" style="background:#0284C7;"></span>Inbound Volume (Units)
                </span>
                <span class="report-chart-legend__item">
                    <span class="report-chart-legend__dot" style="background:#BAE6FD;border:1px solid #0284C7;"></span>Outbound Volume (Units)
                </span>
                <span class="report-chart-legend__item">
                    <span class="report-chart-legend__line" style="background:#DC2626;"></span>Target Turnover SLA
                </span>
            </div>
            <div class="report-chart-container" style="position:relative;height:300px;overflow:hidden;" id="trend-chart" role="img" aria-label="6-month trend chart showing valuation, inbound, and outbound volume against SLA target"></div>
        </div>

        <!-- Panel: Category Distribution -->
        <div class="report-visual-panel" id="panel-category" role="tabpanel" aria-labelledby="tab-category" hidden>
            <div class="report-category-header">
                Valuation Share &amp; Velocity by Inventory Category — Total: <?= $fmtRp($categoryTotal) ?> Across <?= count($categoryData) ?> Categories
            </div>

            <?php if (count($categoryData) > 0): ?>
            <div class="report-category-bar">
                <?php foreach ($categoryData as $idx => $cat): ?>
                <div class="report-category-bar__segment"
                     style="width:<?= $cat['percentage'] ?>%;background:<?= $CATEGORY_COLORS[$idx % count($CATEGORY_COLORS)] ?>;"
                     title="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>: <?= $cat['percentage'] ?>%"
                     role="img"
                     aria-label="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?> <?= $cat['percentage'] ?>%">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="report-category-grid">
                <?php foreach ($categoryData as $idx => $cat):
                    $velocity = (float) $cat['velocity'];
                    $isLeader = $velocity > 0 && $cat['name'] === $fastestCat;
                ?>
                <div class="report-category-item">
                    <div class="report-category-item__header">
                        <span class="report-category-item__dot" style="background:<?= $CATEGORY_COLORS[$idx % count($CATEGORY_COLORS)] ?>;"></span>
                        <strong class="report-category-item__name"><?= htmlspecialchars($cat['name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="report-category-item__pct"><?= $cat['percentage'] ?>%</div>
                    <div class="report-category-item__valuation"><?= $fmtRp($cat['valuation']) ?></div>
                    <div class="report-category-item__sku"><?= number_format($cat['sku_count'], 0, ',', '.') ?> SKUs</div>
                    <div class="report-category-item__velocity <?= $isLeader ? 'report-category-item__velocity--leader' : '' ?>">
                        Velocity: <?= number_format($velocity, 1, ',', '.') ?>x / yr
                        <?php if ($isLeader): ?> (Leader)<?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="report-empty-state">No category data available for the selected filters.</div>
            <?php endif; ?>
        </div>

        <!-- Panel: Warehouse Allocation -->
        <div class="report-visual-panel" id="panel-warehouse" role="tabpanel" aria-labelledby="tab-warehouse" hidden>
            <div class="report-warehouse-header">
                Warehouse Stock Allocation Across <?= count($warehouses) ?> Locations
            </div>
            <?php if ($whGrandTotal > 0): ?>
            <div class="report-category-bar">
                <?php $whIdx = 0; foreach ($whData as $wdId => $wd):
                    $pct = $whGrandTotal > 0 ? round($wd['valuation'] / $whGrandTotal * 100, 1) : 0;
                    if ($pct <= 0) {
                        continue;
                    }
                ?>
                <div class="report-category-bar__segment"
                     style="width:<?= $pct ?>%;background:<?= $CATEGORY_COLORS[$whIdx % count($CATEGORY_COLORS)] ?>;"
                     title="<?= htmlspecialchars($wd['name'], ENT_QUOTES, 'UTF-8') ?>: <?= $pct ?>%"
                     role="img"
                     aria-label="<?= htmlspecialchars($wd['name'], ENT_QUOTES, 'UTF-8') ?> <?= $pct ?>%">
                </div>
                <?php $whIdx++; endforeach; ?>
            </div>
            <div class="report-category-grid">
                <?php $whIdx2 = 0; foreach ($whData as $wdId => $wd):
                    $pct = $whGrandTotal > 0 ? round($wd['valuation'] / $whGrandTotal * 100, 1) : 0;
                    if ($pct <= 0) {
                        continue;
                    }
                ?>
                <div class="report-category-item">
                    <div class="report-category-item__header">
                        <span class="report-category-item__dot" style="background:<?= $CATEGORY_COLORS[$whIdx2 % count($CATEGORY_COLORS)] ?>;"></span>
                        <strong class="report-category-item__name"><?= htmlspecialchars($wd['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="report-category-item__pct"><?= $pct ?>%</div>
                    <div class="report-category-item__valuation"><?= $fmtRp($wd['valuation']) ?></div>
                    <div class="report-category-item__sku"><?= htmlspecialchars($wd['location'] ?: 'No location', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php $whIdx2++; endforeach; ?>
            </div>
            <?php else: ?>
            <div class="report-empty-state">No warehouse allocation data available for the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Tabular Data View ─────────────────────────────────────── -->
<div class="report-table-card">
    <div class="report-table-toolbar">
        <div class="report-table-toolbar__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#5B6472;pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="input" type="search" id="report-search" name="q" form="report-filter-form"
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Please Enter Keywords To Filter Report Results Here..."
                   style="padding-left:36px;min-height:44px;" aria-label="Filter report results">
        </div>
        <div class="report-table-toolbar__meta">
            <span class="report-table-toolbar__counter">SHOWING <?= $startRecord ?>-<?= $endRecord ?> OF <?= $lineItemsTotal ?> RECORDS</span>
            <select class="input" id="report-sort" name="sort" form="report-filter-form" onchange="this.form.requestSubmit()" style="min-height:44px;" aria-label="Sort order">
                <?php foreach ($sortOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php
    $reportBadgeClass = static function ($statusType) {
        return match ($statusType) {
            'in_stock'      => 'badge--active',
            'high_velocity' => 'badge--info',
            'low_stock'     => 'badge--warning',
            'out_of_stock'  => 'badge--inactive',
            default         => 'badge--neutral',
        };
    };
    $reportColumnCount = match ($reportType) {
        'inventory_aging' => 8,
        'slow_moving'     => 8,
        'movement_ledger' => 8,
        default           => 9,
    };
    ?>
    <div class="table-wrap">
        <table class="table" aria-label="Report line items">
            <?php if ($reportType === 'movement_ledger'): ?>
            <thead>
                <tr>
                    <th scope="col">Date/Time</th>
                    <th scope="col">Product</th>
                    <th scope="col">SKU Code</th>
                    <th scope="col">Warehouse</th>
                    <th scope="col" style="text-align:right;">Quantity</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Done By</th>
                    <th scope="col" style="text-align:center;">Type</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($lineItems) === 0): ?>
                <tr>
                    <td colspan="<?= $reportColumnCount ?>" style="text-align:center;padding:var(--space-6);color:var(--color-text-secondary);">
                        No stock movements recorded yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $item): ?>
                <tr>
                    <td class="text--muted"><?= htmlspecialchars($item['date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="font-weight:500;color:var(--color-text-primary);"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="font-mono" style="font-size:12px;font-weight:600;color:#1E3A8A;"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><span class="text--muted"><?= htmlspecialchars($item['warehouse'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;font-weight:600;<?= $item['qty'] < 0 ? 'color:#DC2626;' : 'color:#15803D;' ?>"><?= htmlspecialchars($item['qty_display'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text--muted"><?= htmlspecialchars($item['ref'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text--muted"><?= htmlspecialchars($item['done_by'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:center;">
                        <span class="badge <?= $reportBadgeClass($item['status_type']) ?>" style="border-radius:4px;">
                            <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php elseif ($reportType === 'inventory_aging'): ?>
            <thead>
                <tr>
                    <th scope="col">SKU Code</th>
                    <th scope="col">Product Name</th>
                    <th scope="col">Category</th>
                    <th scope="col" style="text-align:right;">Current Stock</th>
                    <th scope="col">Last Receipt</th>
                    <th scope="col" style="text-align:right;">Aging</th>
                    <th scope="col" style="text-align:right;">Total Valuation</th>
                    <th scope="col" style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($lineItems) === 0): ?>
                <tr>
                    <td colspan="<?= $reportColumnCount ?>" style="text-align:center;padding:var(--space-6);color:var(--color-text-secondary);">
                        No products found for the selected filter parameters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $item): ?>
                <tr>
                    <td><span class="font-mono" style="font-size:12px;font-weight:600;color:#1E3A8A;"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="font-weight:500;color:var(--color-text-primary);"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="text--muted"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($item['stock'], 0, ',', '.') ?> <span class="text--muted" style="font-size:var(--font-size-label-xs);"><?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="text--muted"><?= htmlspecialchars($item['last_receipt'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right;" class="text--muted"><?= htmlspecialchars($item['aging'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right;font-weight:600;color:var(--color-text-primary);"><?= $fmtRp($item['valuation']) ?></td>
                    <td style="text-align:center;">
                        <span class="badge <?= $reportBadgeClass($item['status_type']) ?>" style="border-radius:4px;">
                            <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php elseif ($reportType === 'slow_moving'): ?>
            <thead>
                <tr>
                    <th scope="col">SKU Code</th>
                    <th scope="col">Product Name</th>
                    <th scope="col">Category</th>
                    <th scope="col" style="text-align:right;">Current Stock</th>
                    <th scope="col" style="text-align:right;">30-Day Outflow</th>
                    <th scope="col" style="text-align:right;">Velocity</th>
                    <th scope="col" style="text-align:right;">Total Valuation</th>
                    <th scope="col" style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($lineItems) === 0): ?>
                <tr>
                    <td colspan="<?= $reportColumnCount ?>" style="text-align:center;padding:var(--space-6);color:var(--color-text-secondary);">
                        No slow-moving or dead stock found for the selected filter parameters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $item): ?>
                <tr>
                    <td><span class="font-mono" style="font-size:12px;font-weight:600;color:#1E3A8A;"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="font-weight:500;color:var(--color-text-primary);"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="text--muted"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($item['stock'], 0, ',', '.') ?> <span class="text--muted" style="font-size:var(--font-size-label-xs);"><?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;" class="text--muted"><?= number_format($item['outflow_30d'], 0, ',', '.') ?></td>
                    <td style="text-align:right;" class="text--muted"><?= number_format($item['velocity'], 1, ',', '.') ?>x</td>
                    <td style="text-align:right;font-weight:600;color:var(--color-text-primary);"><?= $fmtRp($item['valuation']) ?></td>
                    <td style="text-align:center;">
                        <span class="badge <?= $reportBadgeClass($item['status_type']) ?>" style="border-radius:4px;">
                            <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php else: ?>
            <thead>
                <tr>
                    <th scope="col">SKU Code</th>
                    <th scope="col">Product Name</th>
                    <th scope="col">Category</th>
                    <th scope="col">Warehouse Hub</th>
                    <th scope="col" style="text-align:right;">Current Stock</th>
                    <th scope="col" style="text-align:right;">Unit Cost</th>
                    <th scope="col" style="text-align:right;">Total Valuation</th>
                    <th scope="col" style="text-align:right;">30-Day Turnover</th>
                    <th scope="col" style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($lineItems) === 0): ?>
                <tr>
                    <td colspan="<?= $reportColumnCount ?>" style="text-align:center;padding:var(--space-6);color:var(--color-text-secondary);">
                        No transaction or valuation data found for the selected filter parameters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $item): ?>
                <tr>
                    <td><span class="font-mono" style="font-size:12px;font-weight:600;color:#1E3A8A;"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="font-weight:500;color:var(--color-text-primary);"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="text--muted"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><span class="text--muted"><?= htmlspecialchars($item['warehouse'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($item['stock'], 0, ',', '.') ?> <span class="text--muted" style="font-size:var(--font-size-label-xs);"><?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="text-align:right;" class="text--muted"><?= $fmtRp($item['unit_cost']) ?></td>
                    <td style="text-align:right;font-weight:600;color:var(--color-text-primary);"><?= $fmtRp($item['valuation']) ?></td>
                    <td style="text-align:right;" class="text--muted"><?= number_format($item['velocity'], 1, ',', '.') ?>x</td>
                    <td style="text-align:center;">
                        <span class="badge <?= $reportBadgeClass($item['status_type']) ?>" style="border-radius:4px;">
                            <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="report-pagination">
        <span class="report-pagination__info">Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= $lineItemsTotal ?> line items</span>
        <nav class="pagination" aria-label="Report pagination">
            <?php if ($page > 1): ?>
                <button type="submit" form="report-filter-form" name="page" value="<?= $page - 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">&laquo; Prev</button>
            <?php else: ?>
                <span class="btn btn--secondary btn--sm" aria-disabled="true" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;opacity:0.4;cursor:not-allowed;">&laquo; Prev</span>
            <?php endif; ?>

            <?php
            $window = 2;
            $start = max(1, $page - $window);
            $end   = min($totalPages, $page + $window);
            if ($start > 1): ?>
                <button type="submit" form="report-filter-form" name="page" value="1" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">1</button>
            <?php endif; ?>
            <?php if ($start > 2): ?>
                <span style="padding:0 4px;color:var(--color-text-secondary);">…</span>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="btn btn--primary btn--sm" aria-current="page" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;"><?= $i ?></span>
                <?php else: ?>
                    <button type="submit" form="report-filter-form" name="page" value="<?= $i ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;"><?= $i ?></button>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $totalPages - 1): ?>
                <span style="padding:0 4px;color:var(--color-text-secondary);">…</span>
            <?php endif; ?>
            <?php if ($end < $totalPages): ?>
                <button type="submit" form="report-filter-form" name="page" value="<?= $totalPages ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;"><?= $totalPages ?></button>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <button type="submit" form="report-filter-form" name="page" value="<?= $page + 1 ?>" class="btn btn--secondary btn--sm" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">Next &raquo;</button>
            <?php else: ?>
                <span class="btn btn--secondary btn--sm" aria-disabled="true" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;opacity:0.4;cursor:not-allowed;">Next &raquo;</span>
            <?php endif; ?>
        </nav>
    </div>
    <?php else: ?>
    <div class="report-pagination">
        <span class="report-pagination__info">Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= $lineItemsTotal ?> line items</span>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Pure-Vanilla SVG Bar + Line Chart (No External Library) ────
    var trendLabels = <?= json_encode(array_column($trendData, 'label')) ?>;
    var trendValuation = <?= json_encode(array_map(fn($r) => round($r['valuation'] / 1000000, 1), $trendData)) ?>;
    var trendInbound   = <?= json_encode(array_column($trendData, 'inbound')) ?>;
    var trendOutbound  = <?= json_encode(array_column($trendData, 'outbound')) ?>;
    var slaTarget = <?= json_encode(array_fill(0, count($trendData), 4.0)) ?>;

    var svgEl = document.getElementById('trend-chart');
    if (svgEl && trendLabels.length > 0) {
        var CHART_W = 800, CHART_H = 280, PAD_L = 60, PAD_R = 20, PAD_T = 20, PAD_B = 40;
        var plotW = CHART_W - PAD_L - PAD_R;
        var plotH = CHART_H - PAD_T - PAD_B;

        // Flatten all numeric values for scale
        var allVals = [].concat(trendValuation, trendInbound, trendOutbound, slaTarget);
        var maxVal  = Math.max.apply(null, allVals);
        var minVal  = 0;
        var valRange = maxVal - minVal || 1;
        var SLACK = valRange * 0.12;
        var yMax = maxVal + SLACK;

        // x-step
        var n = trendLabels.length;
        var xStep = plotW / n;

        // Scale helpers
        var scaleX = function(i) { return PAD_L + (i + 0.5) * xStep; };
        var scaleY = function(v) {
            return PAD_T + plotH - ((v - minVal) / (yMax - minVal)) * plotH;
        };

        // Bar width
        var barW = Math.max(4, (xStep * 0.55) / 3 - 3);

        // Build SVG elements
        var svg = '<svg viewBox="0 0 ' + CHART_W + ' ' + CHART_H + '" '
            + 'xmlns="http://www.w3.org/2000/svg" role="img" '
            + 'aria-label="6-month trend chart: valuation, inbound, outbound volume, and SLA target"'
            + 'preserveAspectRatio="xMidYMid meet" '
            + 'style="display:block;width:100%;height:100%;font-family:system-ui,sans-serif;">';

        // ── Y-axis grid lines & labels ──
        var yTicks = 5;
        for (var t = 0; t <= yTicks; t++) {
            var tickVal = minVal + (yMax - minVal) * (t / yTicks);
            var yPx = scaleY(tickVal);
            var yLabel = tickVal >= 1000
                ? (tickVal / 1000).toFixed(0) + 'K'
                : tickVal.toFixed(0);

            svg += '<line x1="' + PAD_L + '" y1="' + yPx.toFixed(1) + '" '
                + 'x2="' + (CHART_W - PAD_R) + '" y2="' + yPx.toFixed(1) + '" '
                + 'stroke="#F5F6F8" stroke-width="1"/>';
            svg += '<text x="' + (PAD_L - 6) + '" y="' + (yPx + 4).toFixed(1) + '" '
                + 'text-anchor="end" font-size="10" fill="#5B6472">' + yLabel + '</text>';
        }

        // ── Y-axis title (Valuation side) ──
        svg += '<text x="' + (PAD_L - 44) + '" y="' + (PAD_T + plotH / 2) + '" '
            + 'transform="rotate(-90,' + (PAD_L - 44) + ',' + (PAD_T + plotH / 2) + ')" '
            + 'text-anchor="middle" font-size="10" fill="#5B6472">Valuation (Rp M)</text>';

        // ── X-axis labels ──
        for (var i = 0; i < n; i++) {
            var xPx = scaleX(i);
            svg += '<text x="' + xPx.toFixed(1) + '" y="' + (CHART_H - 6) + '" '
                + 'text-anchor="middle" font-size="10" fill="#5B6472">' + trendLabels[i] + '</text>';
        }

        // ── Grouped Bars ──
        var barColors = ['#1E3A8A', '#0284C7', '#BAE6FD'];
        var barDatasets = [trendValuation, trendInbound, trendOutbound];
        var labels = ['Valuation (Rp M)', 'Inbound Volume (Units)', 'Outbound Volume (Units)'];

        for (var bi = 0; bi < barDatasets.length; bi++) {
            var data = barDatasets[bi];
            var color = barColors[bi];
            // Position bars side-by-side within each group
            var groupOffset = (bi - (barDatasets.length - 1) / 2) * barW;
            for (var i = 0; i < data.length; i++) {
                var xPx = scaleX(i) + groupOffset;
                var yTop = scaleY(data[i]);
                var yBot = scaleY(minVal);
                var bh   = Math.max(0, yBot - yTop);
                svg += '<rect x="' + xPx.toFixed(1) + '" y="' + yTop.toFixed(1) + '" '
                    + 'width="' + (barW - 2) + '" height="' + bh.toFixed(1) + '" '
                    + 'fill="' + color + '" rx="2"'
                    + '><title>' + labels[bi] + ' — ' + trendLabels[i] + ': ' + data[i] + '</title></rect>';
            }
        }

        // ── SLA Target Line (dashed red) ──
        var slaPx = scaleY(slaTarget[0]);
        svg += '<line x1="' + PAD_L + '" y1="' + slaPx.toFixed(1) + '" '
            + 'x2="' + (CHART_W - PAD_R) + '" y2="' + slaPx.toFixed(1) + '" '
            + 'stroke="#DC2626" stroke-width="2" stroke-dasharray="5,4"'
            + '><title>Target Turnover SLA: ' + slaTarget[0] + '</title></line>';
        svg += '<text x="' + (CHART_W - PAD_R + 4) + '" y="' + (slaPx + 4).toFixed(1) + '" '
            + 'font-size="9" fill="#DC2626" font-weight="600">SLA</text>';

        svg += '</svg>';
        svgEl.innerHTML = svg;
    }

    // ── View Tabs ───────────────────────────────────────────────
    var tabs = document.querySelectorAll('.report-view-tab');
    var panels = document.querySelectorAll('.report-visual-panel');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var view = this.dataset.view;
            tabs.forEach(function(t) {
                t.classList.remove('report-view-tab--active');
                t.setAttribute('aria-selected', 'false');
            });
            this.classList.add('report-view-tab--active');
            this.setAttribute('aria-selected', 'true');

            panels.forEach(function(p) {
                p.hidden = true;
            });
            var targetPanel = document.getElementById('panel-' + view);
            if (targetPanel) targetPanel.hidden = false;
        });
    });

    // ── Loading state ────────────────────────────────────────────
    // Search (Enter), sort, pagination, Generate and both exports all submit
    // #report-filter-form by POST, so none of that state reaches the URL.
    // Set on 'submit' (not 'click') so the submission is never cancelled by
    // disabling its own submitter.
    var filterForm = document.getElementById('report-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            var btn = e.submitter;
            if (!btn || btn.name === 'page') return;
            var originalLabel = btn.innerHTML;
            var isExport = btn.id === 'export-csv-btn' || btn.id === 'export-orders-btn';
            btn.innerHTML = isExport
                ? '<span style="width:8px;height:8px;border-radius:2px;background:#5B6472;display:inline-block;flex-shrink:0;"></span> Generating CSV...'
                : '<span class="btn__spinner" aria-hidden="true"></span> Generating...';
            // A CSV download keeps this page open, so the label must come back.
            if (isExport) {
                setTimeout(function() { btn.innerHTML = originalLabel; }, 3000);
            }
        });
    }
});
</script>

<?php

/** @var \App\Entity\User $user */
/** @var array $stats */
/** @var string $period */
/** @var string $csrfToken */

$totalRevenue = $stats['total_revenue'] ?? 0.0;
$revenueTrendPct = $stats['revenue_trend_pct'] ?? 0.0;
$totalOrders = $stats['total_orders'] ?? 0;
$pendingApproval = $stats['pending_approval'] ?? 0;
$readyForIssue = $stats['ready_for_issue'] ?? 0;
$pipeline = $stats['pipeline'] ?? [];
$topCustomers = $stats['top_customers'] ?? [];
$recentOrders = $stats['recent_orders'] ?? [];

$periodOptions = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'all' => 'All Time',
];
$currentPeriodLabel = $periodOptions[$period] ?? 'This Month';

$fmtCurrency = static fn (float $v): string =>
    'Rp ' . number_format($v, 0, ',', '.');

$badgeClass = static fn (string $s): string => match ($s) {
    'Draft' => 'badge--neutral',
    'PendingApproval' => 'badge--warning',
    'Approved' => 'badge--info',
    'Fulfilled' => 'badge--active',
    'Cancelled' => 'badge--inactive',
    default => 'badge--neutral',
};

?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <span>IOMS</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Operations</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Sales Dashboard</span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title">Sales Dashboard</h1>
        <form method="post" action="/sales-dashboard" class="period-selector" aria-label="Filter by period">
            <select
                name="period"
                class="period-selector__select"
                onchange="this.form.submit()"
                aria-label="Select time period"
            >
                <?php foreach ($periodOptions as $key => $label): ?>
                <option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php // Same permission SalesOrderController::createFormAction() requires — otherwise WarehouseStaff gets a 403. ?>
        <?php if (in_array('sales_orders.menu', $grantedPermissions ?? [], true)): ?>
        <a href="/sales-orders/create" class="btn btn--primary">
            <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-plus"></use></svg>
            Create Sales Order
        </a>
        <?php endif; ?>
    </div>

    <p class="page-header__subtitle">
        Monitor sales performance, pipeline tracking, and order fulfillment status.
    </p>
</div>

<!-- KPI Summary Cards -->
<div class="dashboard-kpi-grid">
    <!-- Card 1: Total Sales Revenue -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Sales Revenue</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-bar-chart"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $fmtCurrency($totalRevenue) ?></div>
            <div class="stat-card__sub">
                <?php if ($revenueTrendPct !== 0.0): ?>
                <span class="<?= $revenueTrendPct >= 0 ? 'trend--up' : 'trend--down' ?>">
                    <?= $revenueTrendPct >= 0 ? '+' : '' ?><?= $revenueTrendPct ?>%
                </span>
                vs previous period
                <?php else: ?>
                No prior period data
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Sales Orders -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">Total Sales Orders</span>
            <div class="stat-card__icon">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $totalOrders ?> Orders</div>
            <div class="stat-card__sub"><?= $currentPeriodLabel ?></div>
        </div>
    </div>

    <!-- Card 3: Pending Approval -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">
                <span class="stat-card__label-dot stat-card__label-dot--warning"></span>
                Pending Approval
            </span>
            <div class="stat-card__icon stat-card__icon--warning">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-clock"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $pendingApproval ?> Orders</div>
            <div class="stat-card__sub">Requires manager validation</div>
        </div>
    </div>

    <!-- Card 4: Ready for Goods Issue -->
    <div class="stat-card">
        <div class="stat-card__header">
            <span class="stat-card__label">
                <span class="stat-card__label-dot stat-card__label-dot--info"></span>
                Ready for Issue
            </span>
            <div class="stat-card__icon stat-card__icon--info">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $readyForIssue ?> Orders</div>
            <div class="stat-card__sub">Approved, awaiting dispatch</div>
        </div>
    </div>
</div>

<!-- Middle Split: Pipeline + Top Customers -->
<?php if (count($pipeline) > 0 || count($topCustomers) > 0): ?>
<div class="dashboard-split">
    <!-- Left: Pipeline Stage Breakdown -->
    <?php if (count($pipeline) > 0): ?>
    <div class="sales-pipeline-card">
        <h2 class="sales-pipeline-card__title">
            <svg width="16" height="16" aria-hidden="true" style="vertical-align:-2px"><use href="/assets/img/icons.svg#icon-git-branch"></use></svg>
            Sales Pipeline
        </h2>
        <div class="pipeline-stages">
            <?php foreach ($pipeline as $stage): ?>
            <div class="pipeline-stage">
                <div class="pipeline-stage__meta">
                    <span class="pipeline-stage__label" style="color:<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="pipeline-stage__count"><?= (int) $stage['count'] ?></span>
                </div>
                <div class="pipeline-stage__bar-track">
                    <div
                        class="pipeline-stage__bar-fill"
                        style="width:<?= (float) $stage['percent'] ?>%; background:<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"
                    ></div>
                </div>
                <span class="pipeline-stage__pct"><?= (float) $stage['percent'] ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right: Top Customers + Inquiries placeholder -->
    <div style="display:flex;flex-direction:column;gap:var(--space-4)">
        <?php if (count($topCustomers) > 0): ?>
        <div class="top-customers-card">
            <h2 class="top-customers-card__title">
                <svg width="16" height="16" aria-hidden="true" style="vertical-align:-2px"><use href="/assets/img/icons.svg#icon-users"></use></svg>
                Top Customers by Volume
            </h2>
            <ol class="top-customers-list">
                <?php foreach ($topCustomers as $i => $cust): ?>
                <li class="top-customers-list__item">
                    <span class="top-customers-list__rank"><?= $i + 1 ?></span>
                    <span class="top-customers-list__name"><?= htmlspecialchars($cust['customer_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="top-customers-list__value"><?= $fmtCurrency($cust['total_value']) ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <!-- Quick Stats Summary -->
        <div class="sales-summary-card">
            <h2 class="sales-summary-card__title">Quick Stats</h2>
            <dl class="sales-summary-list">
                <div class="sales-summary-list__item">
                    <dt>Avg. Order Value</dt>
                    <dd><?= $totalOrders > 0 ? $fmtCurrency($totalRevenue / $totalOrders) : 'Rp 0' ?></dd>
                </div>
                <div class="sales-summary-list__item">
                    <dt>Fulfillment Rate</dt>
                    <dd>
                        <?php
                        $fulfilled = 0;
                        foreach ($pipeline as $s) {
                            if ($s['status'] === 'Fulfilled') {
                                $fulfilled = $s['count'];
                            }
                        }
                        echo $totalOrders > 0 ? round(($fulfilled / $totalOrders) * 100, 1) . '%' : '0%';
                        ?>
                    </dd>
                </div>
                <div class="sales-summary-list__item">
                    <dt>Cancellation Rate</dt>
                    <dd>
                        <?php
                        $cancelled = 0;
                        foreach ($pipeline as $s) {
                            if ($s['status'] === 'Cancelled') {
                                $cancelled = $s['count'];
                            }
                        }
                        echo $totalOrders > 0 ? round(($cancelled / $totalOrders) * 100, 1) . '%' : '0%';
                        ?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Sales Orders Table -->
<div class="dashboard-section" style="margin-top:var(--space-4)">
    <div class="dashboard-section__header">
        <h2 class="dashboard-section__title">
            <svg width="18" height="18" aria-hidden="true" style="vertical-align:-3px"><use href="/assets/img/icons.svg#icon-file-text"></use></svg>
            Recent Sales Orders
        </h2>
        <div class="table-toolbar">
            <div class="table-toolbar__search">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-search"></use></svg>
                <input
                    type="search"
                    id="so-search"
                    class="table-toolbar__search-input"
                    placeholder="Search by SO number or customer..."
                    aria-label="Search sales orders"
                >
            </div>
            <select class="table-toolbar__filter" id="so-status-filter" aria-label="Filter by status">
                <option value="">Status: All</option>
                <option value="Draft">Draft</option>
                <option value="PendingApproval">Pending Approval</option>
                <option value="Approved">Approved</option>
                <option value="Fulfilled">Fulfilled</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <button type="button" class="btn btn--secondary" id="so-export-btn" aria-describedby="so-export-desc">
                <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-download"></use></svg>
                Export CSV
            </button>
            <span id="so-export-desc" class="sr-only">Exports the current filtered results as CSV</span>
        </div>
    </div>

    <?php if (count($recentOrders) === 0): ?>
    <div class="empty-state" role="status" aria-live="polite">
        <svg width="40" height="40" aria-hidden="true"><use href="/assets/img/icons.svg#icon-inbox"></use></svg>
        <p class="empty-state__message">No sales orders match the selected filters.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap" role="status" aria-live="polite">
        <table class="table" id="so-table">
            <thead>
                <tr>
                    <th>SO Number</th>
                    <th>Customer Name</th>
                    <th>Order Date</th>
                    <th>Items</th>
                    <th style="text-align:right">Total Amount (IDR)</th>
                    <?php // No payment data exists in the model — the old "Payment Status" column always showed a fabricated "Unpaid". ?>
                    <th>Fulfillment Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $o): ?>
                <tr data-status="<?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($o['so_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['order_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $o['items_count'] ?> Items</td>
                    <td style="text-align:right"><?= $fmtCurrency($o['total_value']) ?></td>
                    <td>
                        <span class="badge <?= $badgeClass($o['status']) ?>">
                            <?= htmlspecialchars(\App\Entity\SalesOrder::statusLabelFor($o['status']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <?php $encId = $idObfuscator->encode($o['id']); ?>
                        <a class="btn btn--tertiary btn--sm" href="/sales-orders/<?= $encId ?>">View Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('so-search');
    var statusFilter = document.getElementById('so-status-filter');
    var rows = document.querySelectorAll('#so-table tbody tr');
    var emptyState = document.querySelector('.empty-state');
    var tableWrap = document.querySelector('#so-table') ? document.querySelector('#so-table').closest('.table-wrap') : null;

    function applyFilters() {
        var query = (searchInput.value || '').toLowerCase();
        var status = (statusFilter.value || '').toLowerCase();
        var visibleCount = 0;

        rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            var matchesSearch = query === '' || text.indexOf(query) > -1;
            var matchesStatus = status === '' || rowStatus === status;

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';
        }
        if (tableWrap) {
            tableWrap.style.display = visibleCount === 0 ? 'none' : 'block';
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    // Client-side CSV export
    var exportBtn = document.getElementById('so-export-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var visibleRows = Array.from(rows).filter(function (r) { return r.style.display !== 'none'; });
            if (visibleRows.length === 0) return;

            var headers = ['SO Number', 'Customer', 'Order Date', 'Items', 'Total (IDR)', 'Status'];
            var csv = [headers.join(',')];

            // REPORT-01.04: same CSV-injection guard as CsvExportService — a cell
            // starting with = + - @ or tab is prefixed with ' so spreadsheets
            // treat it as text, not a formula.
            var csvCell = function (c) {
                var v = /^[=+\-@\t]/.test(c) ? "'" + c : c;
                return '"' + v.replace(/"/g, '""') + '"';
            };

            visibleRows.forEach(function (row) {
                var cols = row.querySelectorAll('td');
                var cells = [
                    (cols[0].textContent || '').trim(),
                    (cols[1].textContent || '').trim(),
                    (cols[2].textContent || '').trim(),
                    (cols[3].textContent || '').trim(),
                    (cols[4].textContent || '').trim().replace(/[^\d,]/g, ''),
                    (cols[5].textContent || '').trim(),
                ];
                csv.push(cells.map(csvCell).join(','));
            });

            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'sales-orders-<?= $period ?>.csv';
            a.click();
            URL.revokeObjectURL(url);
        });
    }
});
</script>

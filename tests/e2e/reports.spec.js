// Ad-hoc Playwright QA script for the Reports menu (/reports).
// Checks against PROJECT_REFERENCE.md REPORT-01 (CSV export, date range,
// CSV column spec, injection prevention, per-role export access) and the
// §1.2 role matrix.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const DATE_FROM = '2026-08-01';
const DATE_TO = '2026-09-24';

const ROLES = [
    { role: 'Admin', email: 'admin@example.com', password: 'admin123' },
    { role: 'Sales', email: 'sales1@example.com', password: 'sales123', displayName: 'Beni' },
    { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123' },
];

let pass = 0;
let fail = 0;

function check(label, condition, detail) {
    if (condition) {
        pass += 1;
        console.log(`  [PASS] ${label}`);
    } else {
        fail += 1;
        console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
    }
}

function note(label) {
    console.log(`  [INFO] ${label}`);
}

async function login(page, email, password) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
}

async function logout(page) {
    await page.goto(`${BASE_URL}/logout`, { waitUntil: 'networkidle' }).catch(() => {});
}

// /reports filter/sort/pagination state is POST-only (query-string params are
// ignored server-side), so drive the page's real #report-filter-form instead
// of building URLs. Returns the navigation response of the POST.
async function submitReport(page, params = {}) {
    await page.goto(`${BASE_URL}/reports`, { waitUntil: 'networkidle' });
    const fields = {
        report_type: '#report_type', date_from: '#date_from', date_to: '#date_to',
        warehouse_id: '#warehouse_id', category_id: '#category_id', q: '#report-search', sort: '#report-sort',
    };
    const [resp] = await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.evaluate(({ params, fields }) => {
        const form = document.getElementById('report-filter-form');
        for (const [key, selector] of Object.entries(fields)) {
            if (params[key] !== undefined) document.querySelector(selector).value = String(params[key]);
        }
        if (params.page !== undefined) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'page';
            input.value = String(params.page);
            form.appendChild(input);
        }
        // Plain submit(): bypasses the loading-state handler and any submitter.
        HTMLFormElement.prototype.submit.call(form);
    }, { params, fields })]);
    return resp;
}

// Both CSV exports are POST-only; filters go in the form body.
function postExport(page, path, form) {
    return page.request.post(`${BASE_URL}${path}`, { form });
}

function parseCsv(text) {
    return text.trim().split(/\r\n/).map((line) => line.split(','));
}

// ---------------------------------------------------------------------
// Unauthenticated access must redirect to /login.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/reports`, { waitUntil: 'networkidle' });
    check('GET /reports without session redirects to /login', page.url().includes('/login'), `landed on ${page.url()}`);
    await context.close();
}

// ---------------------------------------------------------------------
// Per-role dashboard view: page loads for everyone (no REPORT-01
// requirement restricts viewing the analytics page itself, only exporting),
// but the two Export buttons must be gated exactly per REPORT-01's access
// matrix — Stock Ledger: Admin/WarehouseStaff; Orders: Admin/Sales.
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: dashboard view ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (err) => pageErrors.push(String(err)));

    await login(page, email, password);

    // Role matrix: Sales only downloads its own orders, so /reports is an
    // orders-only export form for that role — no inventory analytics at all.
    if (role === 'Sales') {
        const salesResp = await page.goto(`${BASE_URL}/reports`, { waitUntil: 'networkidle' });
        check('Sales: GET /reports returns 200', salesResp.status() === 200, `got ${salesResp.status()}`);
        const salesBody = await page.locator('body').innerText();
        check('Sales: /reports shows no stock valuation / inventory analytics', !/Total Stock Valuation|Visual Analytics|Turnover Velocity/.test(salesBody));
        check('Sales: /reports offers "Export Orders"', (await page.locator('#export-orders-btn').count()) > 0);
        check('Sales: /reports offers no "Export Stock Ledger"', (await page.locator('#export-csv-btn').count()) === 0);
        check('Sales: /reports form posts to the Orders export', (await page.getAttribute('#report-filter-form', 'action')) === '/reports/export/orders');
        check('No console errors on Sales /reports', consoleErrors.length === 0, consoleErrors.join(' | '));
        check('No page errors on Sales /reports', pageErrors.length === 0, pageErrors.join(' | '));
        await logout(page);
        await context.close();
        return;
    }

    const resp = await submitReport(page, { date_from: DATE_FROM, date_to: DATE_TO });
    check('GET /reports returns 200', resp.status() === 200, `got ${resp.status()}`);

    const canExportLedger = role === 'Admin' || role === 'WarehouseStaff';
    const canExportOrders = role === 'Admin' || role === 'Sales';
    const ledgerBtnVisible = await page.locator('#export-csv-btn').count();
    check('"Export Stock Ledger" button visible only for Admin/WarehouseStaff', (ledgerBtnVisible > 0) === canExportLedger, `visible=${ledgerBtnVisible > 0}`);
    const ordersBtnVisible = await page.locator('#export-orders-btn').count();
    check('"Export Orders" button visible only for Admin/Sales', (ordersBtnVisible > 0) === canExportOrders, `visible=${ordersBtnVisible > 0}`);

    const kpiCount = await page.locator('.stat-card, [class*="kpi"]').count();
    note(`KPI/stat cards rendered: ${kpiCount}`);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `reports-dashboard-${role}.png`), fullPage: true });

    check('No console errors', consoleErrors.length === 0, JSON.stringify(consoleErrors));
    check('No page (fatal) errors', pageErrors.length === 0, JSON.stringify(pageErrors));

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// REPORT-01: Stock Ledger CSV export — access, columns, date range.
// ---------------------------------------------------------------------
async function checkStockLedgerExport(browser) {
    console.log('\n=== Stock Ledger CSV export ===');

    for (const { role, email, password, expectOk } of [
        { role: 'Admin', email: 'admin@example.com', password: 'admin123', expectOk: true },
        { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123', expectOk: true },
        { role: 'Sales', email: 'sales1@example.com', password: 'sales123', expectOk: false },
    ]) {
        const context = await browser.newContext();
        const page = await context.newPage();
        await login(page, email, password);
        const resp = await postExport(page, '/reports/export/stock-ledger', { date_from: DATE_FROM, date_to: DATE_TO });
        if (expectOk) {
            check(`${role} can export Stock Ledger CSV (200)`, resp.status() === 200, `got ${resp.status()}`);
            check(`${role}: Content-Type is text/csv`, /text\/csv/.test(resp.headers()['content-type'] || ''), resp.headers()['content-type']);
            const rows = parseCsv(await resp.text());
            check(
                `${role}: header matches REPORT-01 CSV Columns (Date, Product, SKU, Warehouse, Type, Quantity, Ref Type, Ref ID, Done By)`,
                rows[0].join(',') === 'Date,Product,SKU,Warehouse,Type,Quantity,Ref Type,Ref ID,Done By',
                rows[0].join(',')
            );
            if (rows.length > 1) {
                const skuColIdx = 2;
                const nonEmptySku = rows.slice(1).some((r) => (r[skuColIdx] || '').trim() !== '');
                check(`${role}: at least one row has a non-empty SKU`, nonEmptySku);
            } else {
                note(`${role}: no data rows in this date range to check SKU population.`);
            }
        } else {
            check(`${role} cannot export Stock Ledger CSV (403)`, resp.status() === 403, `got ${resp.status()}`);
        }
        await logout(page);
        await context.close();
    }

    // Missing date range is rejected, not silently exported with a default range.
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');
    const noRangeResp = await postExport(adminPage, '/reports/export/stock-ledger', {});
    check('Missing date range is rejected (400), not silently exported', noRangeResp.status() === 400, `got ${noRangeResp.status()}`);
    await logout(adminPage);
    await adminContext.close();
}

// ---------------------------------------------------------------------
// REPORT-01: Orders CSV export — access matrix, per-role row scoping,
// and the Items Count column that was previously missing entirely.
// ---------------------------------------------------------------------
async function checkOrdersExport(browser) {
    console.log('\n=== Orders CSV export ===');

    // Admin: sees both PO and SO rows.
    {
        const context = await browser.newContext();
        const page = await context.newPage();
        await login(page, 'admin@example.com', 'admin123');
        const resp = await postExport(page, '/reports/export/orders', { from: DATE_FROM, to: DATE_TO });
        check('Admin can export Orders CSV (200)', resp.status() === 200, `got ${resp.status()}`);
        const rows = parseCsv(await resp.text());
        check(
            'Header matches REPORT-01 CSV Columns (+Type, since PO/SO are merged) with Items Count present',
            rows[0].join(',') === 'Type,Order No.,Date,Customer/Supplier,Warehouse,Status,Items Count,Total Value,Created By',
            rows[0].join(',')
        );
        const types = new Set(rows.slice(1).map((r) => r[0]));
        check('Admin export includes both PO and SO rows', types.has('PO') && types.has('SO'), `types found: ${JSON.stringify([...types])}`);
        const itemsCountColIdx = 6;
        const hasNonZeroItemsCount = rows.slice(1).some((r) => Number(r[itemsCountColIdx]) > 0);
        check('At least one row has a non-zero Items Count (regression: this column used to be entirely absent)', hasNonZeroItemsCount, `sample: ${rows[1] ? rows[1].join(',') : 'no rows'}`);
        await logout(page);
        await context.close();
    }

    // Sales: only their own SO rows, no PO rows at all.
    {
        const context = await browser.newContext();
        const page = await context.newPage();
        await login(page, 'sales1@example.com', 'sales123');
        const resp = await postExport(page, '/reports/export/orders', { from: DATE_FROM, to: DATE_TO });
        check('Sales can export Orders CSV (200)', resp.status() === 200, `got ${resp.status()}`);
        const rows = parseCsv(await resp.text());
        const types = new Set(rows.slice(1).map((r) => r[0]));
        check('Sales export contains no Purchase Order rows', !types.has('PO'), `types found: ${JSON.stringify([...types])}`);
        const createdByColIdx = 8;
        const allOwnOrders = rows.slice(1).every((r) => r[createdByColIdx] === 'Beni');
        check("Sales export is scoped to only their own orders (Created By = Beni)", rows.length <= 1 || allOwnOrders, `rows: ${rows.length - 1}, sample: ${rows[1] ? rows[1].join(',') : 'none'}`);
        await logout(page);
        await context.close();
    }

    // WarehouseStaff: no access to either order type's export.
    {
        const context = await browser.newContext();
        const page = await context.newPage();
        await login(page, 'warehouse@example.com', 'wh123');
        const resp = await postExport(page, '/reports/export/orders', { from: DATE_FROM, to: DATE_TO });
        check('WarehouseStaff cannot export Orders CSV (403)', resp.status() === 403, `got ${resp.status()}`);
        await logout(page);
        await context.close();
    }

    // Missing date range is rejected.
    {
        const context = await browser.newContext();
        const page = await context.newPage();
        await login(page, 'admin@example.com', 'admin123');
        const noRangeResp = await postExport(page, '/reports/export/orders', {});
        check('Missing date range is rejected (400) for Orders export too', noRangeResp.status() === 400, `got ${noRangeResp.status()}`);
        await logout(page);
        await context.close();
    }
}

// ---------------------------------------------------------------------
// FR-11.4: CSV injection prevention — a cell whose real value starts with
// '=' must be prefixed with a leading single quote in the exported file.
// (Already covered by PHPUnit's CsvExportServiceTest; this is a live
// end-to-end sanity check via the actual HTTP export endpoint.)
// ---------------------------------------------------------------------
async function checkCsvInjectionPrevention(browser) {
    console.log('\n=== CSV injection prevention (live) ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    const resp = await postExport(page, '/reports/export/stock-ledger', { date_from: '2000-01-01', date_to: DATE_TO });
    const rows = parseCsv(await resp.text());
    // Quantity (column 5) is deliberately exempt from the injection prefix —
    // it's a system-computed signed integer, and Issue rows legitimately
    // carry a negative value the prefix would otherwise corrupt into text
    // (see CsvExportService::exportStockLedger()'s own comment on this).
    // Every OTHER column is free text and must be escaped if it starts with
    // one of the injection-trigger characters.
    const quantityColIdx = 5;
    let offendingCell = null;
    for (const row of rows.slice(1)) {
        for (let i = 0; i < row.length; i++) {
            if (i === quantityColIdx) continue;
            if (/^[=+\-@\t]/.test(row[i]) && !row[i].startsWith("'")) {
                offendingCell = `row column ${i}: "${row[i]}"`;
                break;
            }
        }
        if (offendingCell) break;
    }
    check('No unescaped formula-injection prefix (=,+,-,@,tab) in any free-text CSV column', offendingCell === null, offendingCell || 'clean');
    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// The "Report Type" dropdown on the dashboard (stock_valuation /
// inventory_aging / slow_moving / movement_ledger) — 2026-09-24 found that
// showFormAction() read $reportType from the query string but never branched
// on it, so 3 of the 4 options silently rendered byte-identical output to
// Stock Valuation. Fixed by giving each type its own query builder
// (buildInventoryAgingLineItems/buildSlowMovingLineItems/
// buildMovementLedgerRows in ReportController). This locks in that the 4
// types stay genuinely distinct, and that each still respects pagination,
// the Warehouse Location filter, and doesn't crash for any role.
// ---------------------------------------------------------------------
async function checkReportTypes(browser) {
    console.log('\n=== Report Type dropdown (stock_valuation / inventory_aging / slow_moving / movement_ledger) ===');
    const REPORT_TYPES = ['stock_valuation', 'inventory_aging', 'slow_moving', 'movement_ledger'];

    const context = await browser.newContext();
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (err) => pageErrors.push(String(err)));
    await login(page, 'admin@example.com', 'admin123');

    const tableTextByType = {};
    for (const type of REPORT_TYPES) {
        const resp = await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO });
        check(`report_type=${type}: GET /reports returns 200`, resp.status() === 200, `got ${resp.status()}`);
        const bodyText = await page.locator('body').innerText();
        check(`report_type=${type}: no PHP warning/fatal/SQL error leaked into the page`, !/Warning:|Fatal error:|Notice:|SQLSTATE/.test(bodyText));
        tableTextByType[type] = (await page.locator('.table-wrap').innerText()).trim();
    }
    const distinctOutputs = new Set(Object.values(tableTextByType)).size;
    check('All 4 Report Type options render genuinely distinct tables (regression: used to be 1 identical table x4)', distinctOutputs === 4, `only ${distinctOutputs} distinct outputs`);

    // 2026-09-24: filtering by Warehouse Location changed which SKUs were listed
    // but every displayed figure (Current Stock, Total Valuation, Warehouse Hub)
    // still came from getStockBreakdownByProduct() with no warehouse argument —
    // it always returned the global, all-warehouse breakdown. Filtering to
    // Jakarta Warehouse showed "Bandung Warehouse" as the hub on every single
    // row. Fixed by scoping the breakdown to the selected warehouse_id in
    // ReportController::buildStockValuationLineItems() before computing stock/
    // valuation/hub. Assert every row's Warehouse Hub cell (4th column) matches
    // the filter, across every page.
    {
        let mismatches = [];
        let rowsChecked = 0;
        for (let p = 1; p <= 25; p++) {
            const resp = await submitReport(page, { report_type: 'stock_valuation', date_from: DATE_FROM, date_to: DATE_TO, warehouse_id: 1, page: p });
            const rows = await page.locator('.table-wrap tbody tr').count();
            if (rows === 0 || resp.status() !== 200) break;
            const hubs = await page.locator('.table-wrap tbody tr td:nth-child(4)').allInnerTexts();
            if (hubs.length === 0 || hubs[0] === '') break;
            rowsChecked += hubs.length;
            hubs.forEach((h) => { if (h.trim() !== 'Jakarta Warehouse' && h.trim() !== '-') mismatches.push(h); });
        }
        check(
            'stock_valuation: Warehouse Hub column matches the Warehouse Location filter on every row (regression: used to always show a different warehouse)',
            mismatches.length === 0 && rowsChecked > 0,
            `${mismatches.length} mismatched rows out of ${rowsChecked} checked`
        );
    }

    // Warehouse Location filter must scope inventory_aging/slow_moving too, not just stock_valuation.
    for (const type of ['inventory_aging', 'slow_moving']) {
        await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO, warehouse_id: 1 });
        const jakarta = (await page.locator('.table-wrap').innerText()).trim();
        await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO, warehouse_id: 2 });
        const bandung = (await page.locator('.table-wrap').innerText()).trim();
        check(`report_type=${type}: Warehouse Location filter changes the table (Jakarta vs Bandung differ)`, jakarta !== bandung);
    }

    // Pagination must actually paginate for the two SQL-driven new types and the ledger listing.
    for (const type of ['inventory_aging', 'slow_moving', 'movement_ledger']) {
        await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO, page: 1 });
        const page1 = (await page.locator('.table-wrap').innerText()).trim();
        await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO, page: 2 });
        const page2 = (await page.locator('.table-wrap').innerText()).trim();
        check(`report_type=${type}: page 2 differs from page 1`, page1 !== page2);
    }

    // Empty-filter combination must render the table's own empty state, not 500.
    for (const type of ['inventory_aging', 'slow_moving', 'movement_ledger']) {
        const resp = await submitReport(page, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO, q: 'zzz_no_such_product_ever' });
        const rowCount = await page.locator('.table-wrap tbody tr').count();
        check(`report_type=${type}: a no-match search filter renders its empty state (not a crash)`, resp.status() === 200 && rowCount === 1);
    }

    check('No console/page errors across all Report Type checks', pageErrors.length === 0, JSON.stringify(pageErrors));
    await logout(page);
    await context.close();

    // Every role must be able to load every report type without a 500 or a leaked PHP error —
    // report_type has no permission gate of its own (only export does).
    for (const { role, email, password } of ROLES.filter((r) => r.role !== 'Sales')) {
        const roleContext = await browser.newContext();
        const rolePage = await roleContext.newPage();
        await login(rolePage, email, password);
        for (const type of REPORT_TYPES) {
            const resp = await submitReport(rolePage, { report_type: type, date_from: DATE_FROM, date_to: DATE_TO });
            const bodyText = await rolePage.locator('body').innerText();
            check(`${role}: report_type=${type} returns 200 with no PHP error leaked`, resp.status() === 200 && !/Warning:|Fatal error:|SQLSTATE/.test(bodyText));
        }
        await logout(rolePage);
        await roleContext.close();
    }
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);

        for (const roleConfig of ROLES) {
            await checkRoleView(browser, roleConfig);
        }

        await checkStockLedgerExport(browser);
        await checkOrdersExport(browser);
        await checkCsvInjectionPrevention(browser);
        await checkReportTypes(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

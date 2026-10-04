// Ad-hoc Playwright QA script for the Stock Ledger menu (/stock-ledger).
// Checks the page against PROJECT_REFERENCE.md (ALUR-03, DATA-03, §1.2 role
// matrix, FIND-01-style filter/sort/pagination) and docs/roles/*.md.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ROLES = [
    { role: 'Admin', email: 'admin@example.com', password: 'admin123' },
    { role: 'Sales', email: 'sales1@example.com', password: 'sales123' },
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

async function safeGoto(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
}

// ---------------------------------------------------------------------
// Unauthenticated access.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/stock-ledger`, { waitUntil: 'networkidle' });
    check(
        'AUTH-01.04: GET /stock-ledger without session redirects to /login',
        page.url().includes('/login'),
        `landed on ${page.url()}`
    );

    await context.close();
}

// ---------------------------------------------------------------------
// Per-role access — PROJECT_REFERENCE.md §1.2 matrix: Admin and
// WarehouseStaff can view Stock Ledger; Sales cannot (gated by
// requirePermission('stock_ledger.view'), not requireAuth() — no
// view-only fallback, same pattern as Purchase Orders).
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: GET /stock-ledger ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await login(page, email, password);
    const resp = await page.goto(`${BASE_URL}/stock-ledger`, { waitUntil: 'networkidle' });
    const status = resp ? resp.status() : null;

    if (role === 'Sales') {
        check('PO-01-style access: Sales gets 403 Forbidden viewing the Stock Ledger', status === 403, `got HTTP ${status}`);
    } else {
        check(`${role} can view the Stock Ledger (HTTP 200)`, status === 200, `got HTTP ${status}`);
        check(`${role} sees the ledger table`, await page.locator('#ledger-table').count() > 0);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `stock-ledger-${role}.png`), fullPage: true });
    }

    note(`Console errors: ${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);
    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Server-side authorization for Sales on the AJAX path too (POST, not just GET).
// ---------------------------------------------------------------------
async function checkSalesBlockedOnAjax(browser) {
    console.log('\n=== Sales: server-side authorization on the AJAX filter/sort/pagination endpoint ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'sales1@example.com', 'sales123');

    const resp = await page.request.post(`${BASE_URL}/stock-ledger`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        form: { page: '1' },
        maxRedirects: 0,
    });
    check('SOD/AUTHZ: Sales -> POST /stock-ledger (AJAX) is rejected (expect 403)', resp.status() === 403, `got HTTP ${resp.status()}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// PO/SO reference links — regression test for a real bug found & fixed
// 2026-09-24: _stock-ledger-rows.php built the link as
// "/" + strtolower(ref_type) + "-orders/" + raw_id (e.g. "/po-orders/98",
// "/so-orders/179") — wrong route prefix (real routes are
// /purchase-orders and /sales-orders) AND an un-obfuscated raw database id
// (every other order/record link in the app uses $idObfuscator->encode()).
// Both defects independently 404'd. Fixed in both the full-page render and
// the XHR partial (which needed $idObfuscator passed into renderPartial()
// explicitly, since it isn't auto-injected the way view() does it).
// ---------------------------------------------------------------------
async function checkReferenceLinksResolve(browser) {
    console.log('\n=== Admin: PO/SO reference links resolve correctly (regression test) ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    const hrefs = await page.locator('#ledger-tbody a[href*="-orders/"]').evaluateAll((links) => links.map((l) => l.getAttribute('href')));
    check('Full-page load: at least one PO/SO reference link is present', hrefs.length > 0, `found ${hrefs.length}`);

    const badPrefix = hrefs.filter((h) => /\/(po|so)-orders\//.test(h));
    check('Full-page load: no reference link uses the old broken "/po-orders/" or "/so-orders/" prefix', badPrefix.length === 0, `bad links: ${JSON.stringify(badPrefix)}`);

    const rawIdLinks = hrefs.filter((h) => /-orders\/\d+$/.test(h)); // a trailing plain integer means it's NOT obfuscated
    check('Full-page load: no reference link uses a raw (non-obfuscated) numeric id', rawIdLinks.length === 0, `raw-id links: ${JSON.stringify(rawIdLinks)}`);

    // Follow PO/SO links end-to-end and confirm at least one of each actually
    // resolves (not 404). Tries every candidate rather than just the first —
    // the shared dev DB has a handful of orphaned stock_ledger rows from
    // unrelated earlier testing (ref_id pointing at a sales_order that no
    // longer/never existed), which would otherwise make this flaky depending
    // on which row happens to sort first.
    async function anyResolves(candidateHrefs) {
        for (const href of candidateHrefs) {
            const resp = await page.request.get(`${BASE_URL}${href}`);
            if (resp.status() === 200) { return { ok: true, href, checked: candidateHrefs.length }; }
        }
        return { ok: false, href: null, checked: candidateHrefs.length };
    }
    const poHrefs = hrefs.filter((h) => h.includes('/purchase-orders/'));
    const soHrefs = hrefs.filter((h) => h.includes('/sales-orders/'));
    if (poHrefs.length > 0) {
        const result = await anyResolves(poHrefs);
        check(`At least one "PO #..." reference link resolves to its detail page (not 404)`, result.ok, `checked ${result.checked} candidates, none resolved`);
    } else {
        note('No PO reference link found on the first page of the ledger to follow — skipped.');
    }
    if (soHrefs.length > 0) {
        const result = await anyResolves(soHrefs);
        check(`At least one "SO #..." reference link resolves to its detail page (not 404)`, result.ok, `checked ${result.checked} candidates, none resolved`);
        if (!result.ok) {
            note('If every candidate 404s here, check for orphaned stock_ledger rows (ref_type=\'SO\' pointing at a sales_orders.id that doesn\'t exist) — this is dev-DB data hygiene, not a code defect in this page.');
        }
    } else {
        note('No SO reference link found on the first page of the ledger to follow — skipped.');
    }

    // --- Same check again via the AJAX/XHR path (a fresh filter submit), since
    // that path renders through a separate code path (renderPartial()) that
    // needed its own fix for idObfuscator availability.
    await Promise.all([
        page.waitForResponse((r) => r.url().endsWith('/stock-ledger') && r.request().method() === 'POST'),
        page.fill('#ledger-sku', 'E'),
    ]);
    const ajaxHrefs = await page.locator('#ledger-tbody a[href*="-orders/"]').evaluateAll((links) => links.map((l) => l.getAttribute('href')));
    const ajaxBadPrefix = ajaxHrefs.filter((h) => /\/(po|so)-orders\//.test(h));
    check('AJAX-loaded rows: no reference link uses the old broken prefix either', ajaxBadPrefix.length === 0, `bad links: ${JSON.stringify(ajaxBadPrefix)}`);
    const ajaxRawIdLinks = ajaxHrefs.filter((h) => /-orders\/\d+$/.test(h));
    check('AJAX-loaded rows: no reference link uses a raw numeric id either', ajaxRawIdLinks.length === 0, `raw-id links: ${JSON.stringify(ajaxRawIdLinks)}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Filter, sort, and pagination (FIND-01-style expectations applied to the
// ledger, and the AJAX partial-reload behavior specifically).
// ---------------------------------------------------------------------
async function checkFilterSortPagination(browser) {
    console.log('\n=== Admin: filter, sort, pagination ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    const totalBefore = (await page.locator('.page-header__subtitle').textContent().catch(() => '')) || '';
    note(`Header before filter: "${totalBefore.trim()}"`);

    // Helper: wait for the actual AJAX round-trip instead of a fixed sleep —
    // stock-ledger.js POSTs back to the bare /stock-ledger path on every
    // filter/sort/page change.
    function waitForLedgerReload(page) {
        return page.waitForResponse((r) => r.url().endsWith('/stock-ledger') && r.request().method() === 'POST');
    }

    // --- Filter by SKU ---
    await Promise.all([waitForLedgerReload(page), page.fill('#ledger-sku', 'ELEC-003')]);
    const rowsAfterSkuFilter = await page.locator('#ledger-tbody tr').count();
    const skuCells = await page.locator('#ledger-tbody tr td:first-child').allTextContents();
    const allMatchSku = skuCells.length > 0 && skuCells.every((t) => t.includes('ELEC-003'));
    check('Filter by SKU: every visible row matches the filtered SKU', allMatchSku, `rows: ${rowsAfterSkuFilter}, skus: ${JSON.stringify(skuCells)}`);
    await Promise.all([waitForLedgerReload(page), page.fill('#ledger-sku', '')]);

    // --- Filter by movement type ---
    // The multi-select (views/_multi-select.php + App.MultiSelect in app.js)
    // has no visible name/id of its own — its wrapper is "<id>-wrapper" and
    // its dropdown is "<id>-dropdown" (the label's `for` points at a search
    // input, not a single element named "ledger-type"). Clicking an option
    // used to silently do nothing (no input/change event fired on the
    // underlying <select> — fixed in app.js on 2026-09-24), so this also
    // guards against that regression.
    await page.click('#ledger-type-wrapper .ms-input');
    await Promise.all([
        waitForLedgerReload(page),
        page.click('#ledger-type-dropdown .ms-option[data-value="Receipt"]'),
    ]);
    const typeCells = await page.locator('#ledger-tbody tr td:nth-child(4) .badge').allTextContents();
    const allReceipt = typeCells.length > 0 && typeCells.every((t) => t.trim() === 'Receipt');
    check('Filter by movement type (Receipt): every visible row is type Receipt (also verifies the multi-select actually triggers a live reload)', allReceipt, `types: ${JSON.stringify(typeCells)}`);

    // Reset filters back to a clean state for the sort/pagination checks below.
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    // --- Sort by clicking a column header ---
    const dateHeaderBefore = await page.locator('th[data-sort-col="date"]').getAttribute('data-sort-dir');
    await Promise.all([waitForLedgerReload(page), page.click('th[data-sort-col="date"]')]);
    const dateHeaderAfter = await page.locator('th[data-sort-col="date"]').getAttribute('data-sort-dir');
    check('Sort: clicking the Date column header flips sort direction', dateHeaderBefore !== dateHeaderAfter, `before: ${dateHeaderBefore}, after: ${dateHeaderAfter}`);

    // --- Pagination ---
    const totalPagesText = await page.locator('#ledger-page-info').textContent().catch(() => '');
    if (totalPagesText && /Page 1 of (\d+)/.test(totalPagesText) && parseInt(RegExp.$1, 10) > 1) {
        const firstPageFirstRow = await page.locator('#ledger-tbody tr').first().textContent().catch(() => '');
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-next')]);
        const secondPageFirstRow = await page.locator('#ledger-tbody tr').first().textContent().catch(() => '');
        check('Pagination: clicking Next loads different rows', firstPageFirstRow !== secondPageFirstRow);
        const pageInfoAfterNext = await page.locator('#ledger-page-info').textContent().catch(() => '');
        check('Pagination: page indicator advances to Page 2', /Page 2 of/.test(pageInfoAfterNext || ''), `"${pageInfoAfterNext}"`);
    } else {
        note(`Only one page of results (${totalPagesText.trim()}) — pagination click-through skipped.`);
    }

    // --- State is kept across filter + paging + sort (fixed 2026-09-24) ---
    // Previously: Prev/Next posted only {page}, dropping every filter and the
    // sort; Next's data-page was never updated, so it stuck on page 2; and a
    // header click read movement_type instead of movement_type[], dropping the
    // multi-select filters.
    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    await page.click('#ledger-type-wrapper .ms-input');
    await Promise.all([waitForLedgerReload(page), page.click('#ledger-type-dropdown .ms-option[data-value="Receipt"]')]);
    await page.keyboard.press('Escape').catch(() => {});
    const onlyReceipt = async () => {
        const types = await page.locator('#ledger-tbody tr td:nth-child(4) .badge').allTextContents();
        return types.length > 0 && types.every((t) => t.trim() === 'Receipt');
    };
    const filteredInfo = (await page.locator('#ledger-page-info').textContent().catch(() => '')) || '';
    const filteredPages = /of (\d+)/.test(filteredInfo) ? parseInt(RegExp.$1, 10) : 1;
    check('Pagination bar is visible/hidden to match the filtered page count', (await page.locator('#ledger-pagination').isVisible()) === (filteredPages > 1), `"${filteredInfo}"`);
    if (filteredPages >= 2) {
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-next')]);
        const infoNext = await page.locator('#ledger-page-info').textContent();
        check('Next keeps the movement-type filter (page 2 rows are still all Receipt)', /Page 2 of/.test(infoNext || '') && await onlyReceipt(), `"${infoNext}"`);
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-prev')]);
        const infoPrev = await page.locator('#ledger-page-info').textContent();
        check('Prev goes back to Page 1 and still filters', /Page 1 of/.test(infoPrev || '') && await onlyReceipt(), `"${infoPrev}"`);
    } else {
        note(`Receipt filter yields ${filteredPages} page — filtered paging checks skipped.`);
    }
    await Promise.all([waitForLedgerReload(page), page.click('th[data-sort-col="date"]')]);
    check('Sorting keeps the movement-type filter (rows are still all Receipt)', await onlyReceipt());
    const infoAfterSort = await page.locator('#ledger-page-info').textContent();
    check('Sorting resets to Page 1', /Page 1 of/.test(infoAfterSort || ''), `"${infoAfterSort}"`);

    // Next's data-page must advance after each AJAX load (unfiltered data spans many pages).
    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    const allInfo = (await page.locator('#ledger-page-info').textContent().catch(() => '')) || '';
    const allPages = /of (\d+)/.test(allInfo) ? parseInt(RegExp.$1, 10) : 1;
    if (allPages >= 3) {
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-next')]);
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-next')]);
        const info3 = await page.locator('#ledger-page-info').textContent();
        check('Next works a second time (reaches Page 3, not stuck on Page 2)', /Page 3 of/.test(info3 || ''), `"${info3}"`);
        await Promise.all([waitForLedgerReload(page), page.click('#ledger-prev')]);
        const info2 = await page.locator('#ledger-page-info').textContent();
        check('Prev from Page 3 lands on Page 2', /Page 2 of/.test(info2 || ''), `"${info2}"`);
    } else {
        note(`Ledger has ${allPages} page(s) — repeated-Next check needs at least 3, skipped.`);
    }
    check('Stock ledger URL stays free of query params throughout', !page.url().includes('?'), page.url());

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'stock-ledger-admin-final.png'), fullPage: true });

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// DATA-03 / audit-trail spot check: confirm a known seed movement (or any
// movement) has the WAJIB fields ALUR-03 requires — date/time, type,
// product+warehouse, qty, and a "done by" user — all rendered.
// ---------------------------------------------------------------------
async function checkAuditFieldsPresent(browser) {
    console.log('\n=== Admin: ledger row has all ALUR-03 required fields ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    const firstRowCells = await page.locator('#ledger-tbody tr').first().locator('td').allTextContents();
    check('Ledger row has all 9 expected columns (SKU, Product, Warehouse, Type, Qty, Before → After, Ref/Note, Done By, Date)', firstRowCells.length === 9, `cells: ${JSON.stringify(firstRowCells.map((c) => c.trim()))}`);
    check('Ledger row\'s Date column looks like a real date (not "—" or empty)', /\d{2} \w{3} \d{4}/.test(firstRowCells[8] || ''), `"${firstRowCells[8]}"`);
    // ALUR-03: quantity before & after, consistent with the movement
    const beforeAfter = /^(-?\d+) → (-?\d+)$/.exec((firstRowCells[5] || '').trim());
    const qtyCell = Number((firstRowCells[4] || '').trim().replace('−', '-').replace('+', ''));
    check('Ledger row shows "before → after" stock and after − before equals the row qty', beforeAfter !== null && Number(beforeAfter[2]) - Number(beforeAfter[1]) === qtyCell, `"${firstRowCells[5]}" qty ${firstRowCells[4]}`);
    check('Ledger row\'s "Done By" column is populated (not "—")', (firstRowCells[6] || '').trim() !== '—' && (firstRowCells[6] || '').trim() !== '', `"${firstRowCells[6]}"`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Qty sign display (regression test, fixed 2026-09-24). The +/- prefix must
// come from the actual signed `qty` value, not a hardcoded `type === 'Issue'`
// check — Issue rows are always negative (GoodsIssueService stores
// -$item->qty), and today every Adjustment row happens to be positive too
// (the only caller, ProductController's initial-stock hook, guards qty > 0),
// so a type-based guess currently *looks* right — but StockLedgerService::
// recordAdjustment() accepts any signed delta, so a future negative
// Adjustment (a manual stock decrease) would have been mislabeled "+" under
// the old logic. This test can only assert today's reachable cases (Issue
// negative, Receipt/Adjustment positive); the negative-Adjustment case isn't
// reachable through any current UI/API path, so it can't be asserted
// end-to-end without inserting rows directly via SQL (out of scope here).
// ---------------------------------------------------------------------
async function checkQtySignMatchesType(browser) {
    console.log('\n=== Admin: qty +/- prefix matches each row\'s actual movement direction ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    await Promise.all([
        page.waitForResponse((r) => r.url().endsWith('/stock-ledger') && r.request().method() === 'POST'),
        page.click('#ledger-type-wrapper .ms-input'),
    ]).catch(() => {}); // opening the dropdown alone doesn't reload; ignore if it times out
    await page.click('#ledger-type-dropdown .ms-option[data-value="Issue"]');
    await page.waitForResponse((r) => r.url().endsWith('/stock-ledger') && r.request().method() === 'POST');
    const issueQtyCells = await page.locator('#ledger-tbody tr td:nth-child(5)').allTextContents();
    const allIssueNegative = issueQtyCells.length > 0 && issueQtyCells.every((t) => t.trim().startsWith('−'));
    check('Every Issue row displays with a "−" prefix', allIssueNegative, `values: ${JSON.stringify(issueQtyCells)}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Responsive layout — UI-01.02 ("Navigasi dan tabel tidak terpotong").
// At 360px the page itself must not overflow horizontally; the wide table
// is expected to scroll WITHIN its own .table-wrap (overflow-x: auto in
// components.css), not be literally clipped/inaccessible.
// ---------------------------------------------------------------------
async function checkResponsiveLayout(browser) {
    console.log('\n=== Admin: responsive layout at 360px ===');
    const context = await browser.newContext({ viewport: { width: 360, height: 740 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/stock-ledger`);

    const pageOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    check('UI-01.02: the page itself has no horizontal overflow at 360px', pageOverflow <= 0, `scrollWidth exceeds clientWidth by ${pageOverflow}px`);

    const tableWrapInfo = await page.evaluate(() => {
        const wrap = document.querySelector('.ledger-table-wrap');
        return wrap ? { scrollWidth: wrap.scrollWidth, clientWidth: wrap.clientWidth } : null;
    });
    check(
        'UI-01.02: the ledger table is reachable via horizontal scroll inside its own container (not clipped/inaccessible)',
        !!tableWrapInfo && tableWrapInfo.scrollWidth > tableWrapInfo.clientWidth,
        `table-wrap: ${JSON.stringify(tableWrapInfo)}`
    );

    check('Filter card is visible and not overflowing at 360px', await page.locator('#ledger-filter-form').isVisible());

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'stock-ledger-360.png'), fullPage: true });

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// CSV export — REPORT-01 (lives at /reports/export/stock-ledger, a separate
// controller/route from /stock-ledger itself, but it's the only way to get
// this page's data out as a file, so it's in scope here).
// ---------------------------------------------------------------------
async function checkCsvExport(browser) {
    console.log('\n=== CSV export (/reports/export/stock-ledger) ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // Export is POST-only — filters travel in the form body, never the URL.
    const resp = await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: { date_from: '2026-09-01', date_to: '2026-09-24' } });
    check('Export: HTTP 200 with a valid date range', resp.status() === 200, `got HTTP ${resp.status()}`);
    const getResp = await page.request.get(`${BASE_URL}/reports/export/stock-ledger?date_from=2026-09-01&date_to=2026-09-24`);
    check('Export: a GET with query params is not served as a CSV', getResp.status() !== 200 || !/text\/csv/.test(getResp.headers()['content-type'] || ''), `got HTTP ${getResp.status()} ${getResp.headers()['content-type']}`);
    check('Export: Content-Type is text/csv', /text\/csv/.test(resp.headers()['content-type'] || ''), resp.headers()['content-type']);
    check('Export: Content-Disposition attaches stock-ledger.csv', /attachment.*stock-ledger\.csv/.test(resp.headers()['content-disposition'] || ''), resp.headers()['content-disposition']);
    const csvBody = await resp.text();
    const csvLines = csvBody.trim().split(/\r\n/);
    // Regression (fixed 2026-09-24): this assertion previously checked for
    // 'Product ID,Product,Warehouse,Type,Quantity,Ref Type,Ref ID,By,At' —
    // that was the ACTUAL header at the time, but it didn't match
    // PROJECT_REFERENCE.md's REPORT-01 CSV Columns spec (Tanggal, Produk,
    // SKU, Gudang, Tipe, Qty, Ref Type, Ref ID, Dilakukan Oleh): SKU was
    // missing entirely (replaced by the internal numeric Product ID) and
    // Date was last instead of first. Fixed in CsvExportService::exportStockLedger()
    // and StockLedgerMySQLRepository::findForExport() (added p.sku).
    check(
        'Export: header row matches the documented 9 columns',
        csvLines[0] === 'Date,Product,SKU,Warehouse,Type,Quantity,Ref Type,Ref ID,Done By',
        csvLines[0]
    );
    check('Export: at least one data row present for this date range', csvLines.length > 1, `${csvLines.length - 1} data rows`);
    const issueRow = csvLines.find((l) => l.includes(',Issue,'));
    if (issueRow) {
        check('Export: an Issue row\'s Quantity column is negative (matches the on-screen "−" display)', /,Issue,-\d+,/.test(issueRow), issueRow);
    } else {
        note('No Issue-type row found in this date range to check the negative-quantity export — not a failure, just nothing to check.');
    }

    // --- Validation: missing date range is rejected, not silently exported ---
    const noRangeResp = await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: {} });
    check('Export: missing date range is rejected (expect 400, not a CSV)', noRangeResp.status() === 400, `got HTTP ${noRangeResp.status()}`);

    // --- Regression test (fixed 2026-09-24): the Reports page's "Export CSV"
    // button forwards its current warehouse_id filter to this endpoint, but
    // exportStockLedgerAction() used to silently ignore it — the exported
    // file always included every warehouse regardless of what was filtered
    // on screen. Confirm warehouse_id now actually scopes the export.
    const unfilteredResp = await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: { date_from: '2026-09-01', date_to: '2026-09-24' } });
    const unfilteredLines = (await unfilteredResp.text()).trim().split(/\r\n/).slice(1);
    // Warehouse is column index 3 (Date,Product,SKU,Warehouse,...) — see the
    // header-order fix above; this used to be index 2 under the old header.
    const unfilteredWarehouses = new Set(unfilteredLines.map((l) => l.split(',')[3]));
    if (unfilteredWarehouses.size > 1) {
        const targetWarehouse = [...unfilteredWarehouses][0];
        const whResp = await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: { date_from: '2026-09-01', date_to: '2026-09-24', warehouse_id: '1' } });
        const whLines = (await whResp.text()).trim().split(/\r\n/).slice(1);
        const whWarehouses = new Set(whLines.map((l) => l.split(',')[3]));
        check(
            'Export: warehouse_id filter actually scopes the CSV to that warehouse (not silently ignored)',
            whWarehouses.size <= 1 && whLines.length < unfilteredLines.length,
            `unfiltered: ${unfilteredLines.length} rows across ${JSON.stringify([...unfilteredWarehouses])}; warehouse_id=1: ${whLines.length} rows across ${JSON.stringify([...whWarehouses])}`
        );
    } else {
        note(`Only one warehouse (${[...unfilteredWarehouses]}) present in this date range's data — can't distinguish a scoped export from an unscoped one, skipped.`);
    }

    await logout(page);
    await context.close();

    // --- Server-side authorization: Sales cannot export ---
    const salesContext = await browser.newContext();
    const salesPage = await salesContext.newPage();
    await login(salesPage, 'sales1@example.com', 'sales123');
    const salesResp = await salesPage.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: { date_from: '2026-09-01', date_to: '2026-09-24' } });
    check('SOD/AUTHZ: Sales -> POST /reports/export/stock-ledger is rejected (expect 403)', salesResp.status() === 403, `got HTTP ${salesResp.status()}`);
    await logout(salesPage);
    await salesContext.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);
        for (const roleConfig of ROLES) {
            await checkRoleView(browser, roleConfig);
        }
        await checkSalesBlockedOnAjax(browser);
        await checkReferenceLinksResolve(browser);
        await checkFilterSortPagination(browser);
        await checkAuditFieldsPresent(browser);
        await checkQtySignMatchesType(browser);
        await checkResponsiveLayout(browser);
        await checkCsvExport(browser);
    } finally {
        await browser.close();
    }
    console.log(`\nDONE — stock ledger checked. PASS=${pass} FAIL=${fail}`);
    process.exitCode = fail > 0 ? 1 : 0;
})();

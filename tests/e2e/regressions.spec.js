// Regression checks for the bugs fixed 2026-09-25 (see README.md, "Fixed
// 2026-09-25"). Each check reproduces the original failure path in a real
// browser and asserts it no longer happens.
// Mutations: one product is set Inactive and then restored to Active; nothing
// else is written (failed-validation submits never persist).
// Not run in CI. See README.md for setup/usage.
'use strict';

const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const DATE_FROM = '2026-01-01';
const DATE_TO = new Date().toISOString().slice(0, 10);

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

async function login(page, email, password) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[type="submit"]')]);
}

function submitNative(page, formLocatorJs) {
    return Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.evaluate((js) => HTMLFormElement.prototype.submit.call(eval(js)), formLocatorJs),
    ]);
}

async function footerText(page) {
    return ((await page.locator('.pagination-footer__summary').innerText({ timeout: 2000 }).catch(() => ''))).replace(/\s+/g, ' ').trim();
}

// Drives #report-filter-form (POST) — /reports state never travels in the URL.
async function submitReport(page, params) {
    await page.goto(`${BASE_URL}/reports`, { waitUntil: 'networkidle' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.evaluate((p) => {
        const f = document.getElementById('report-filter-form');
        const map = { report_type: '#report_type', date_from: '#date_from', date_to: '#date_to', warehouse_id: '#warehouse_id', category_id: '#category_id', q: '#report-search', sort: '#report-sort' };
        for (const [k, sel] of Object.entries(map)) {
            if (p[k] === undefined) continue;
            const el = document.querySelector(sel);
            if (el.tagName === 'SELECT' && ![...el.options].some((o) => o.value === String(p[k]))) {
                const o = document.createElement('option');
                o.value = String(p[k]);
                el.appendChild(o);
            }
            el.value = String(p[k]);
        }
        if (p.page) {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'page'; i.value = String(p.page);
            f.appendChild(i);
        }
        HTMLFormElement.prototype.submit.call(f);
    }, params)]);
}

async function adminChecks(browser) {
    console.log('\n=== Admin ===');
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (e) => pageErrors.push(String(e)));
    await login(page, 'admin@example.com', 'admin123');

    // Failed edit stays an edit (was: re-rendered as "New User" posting to /users → duplicate).
    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    const userEdit = await page.$$eval('a[href$="/edit"]', (as) => as.map((a) => a.getAttribute('href')).find((h) => h.startsWith('/users/')));
    await page.goto(`${BASE_URL}${userEdit}`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await submitNative(page, 'document.querySelector(\'input[name="email"]\').form');
    const userAction = await page.$eval('input[name="email"]', (el) => el.form.getAttribute('action'));
    check('Users: a failed edit re-renders as Edit (form still posts to /update)', /\/update$/.test(userAction || '') && /Edit User/.test(await page.locator('h1').first().innerText()), userAction);

    for (const [list, field] of [['/customers', 'name'], ['/suppliers', 'name'], ['/warehouses', 'name']]) {
        await page.goto(`${BASE_URL}${list}`, { waitUntil: 'networkidle' });
        const edit = await page.$$eval('a[href$="/edit"]', (as, l) => as.map((a) => a.getAttribute('href')).find((h) => h.startsWith(l + '/')), list);
        await page.goto(`${BASE_URL}${edit}`, { waitUntil: 'networkidle' });
        await page.fill(`input[name="${field}"]`, '');
        await submitNative(page, `document.querySelector('input[name="${field}"]').form`);
        const action = await page.$eval(`input[name="${field}"]`, (el) => el.form.getAttribute('action'));
        check(`${list}: a failed edit re-renders as Edit`, /\/update$/.test(action || ''), action);
    }

    // Product edit: saving an untouched form works (unit preselected) and Inactive persists.
    await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' });
    const productEdit = await page.$$eval('a[href$="/edit"]', (as) => as.map((a) => a.getAttribute('href')).find((h) => h.startsWith('/products/')));
    async function saveProductStatus(value) {
        await page.goto(`${BASE_URL}${productEdit}`, { waitUntil: 'networkidle' });
        const unit = await page.inputValue('#unit');
        await page.check(`input[name="is_active"][value="${value}"]`, { force: true });
        await submitNative(page, 'document.querySelector(\'input[name="is_active"]\').form');
        const landed = page.url();
        await page.goto(`${BASE_URL}${productEdit}`, { waitUntil: 'networkidle' });
        return { unit, landed, saved: await page.$eval('input[name="is_active"]:checked', (el) => el.value) };
    }
    const inactive = await saveProductStatus('0');
    check('Products: the edit form preselects the stored unit (was always empty → every save failed)', inactive.unit !== '', `unit="${inactive.unit}"`);
    check('Products: saving the edit form succeeds (redirects to the list, not a re-rendered error)', /\/products$/.test(inactive.landed), inactive.landed);
    check('Products: Inactive chosen on the edit form is persisted', inactive.saved === '0', `saved=${inactive.saved}`);
    const restored = await saveProductStatus('1');
    check('Products: restored to Active (test cleanup)', restored.saved === '1');

    // Products list filters.
    for (const status of ['in_stock', 'low_stock', 'out_of_stock']) {
        await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' });
        await page.selectOption('#stock-status-filter', status);
        await submitNative(page, 'document.getElementById("products-filter-form")');
        const rows = await page.locator('table tbody tr').count();
        const footer = await footerText(page);
        const badges = await page.locator('table tbody .badge').allInnerTexts();
        // The original bug: rows rendered but the footer said "of 0 entries".
        // Zero rows (e.g. nothing out of stock right now) is a valid empty state.
        check(`Products: stock_status=${status} total matches its rows (was "0 of 0")`, rows === 0 || !/of 0 entries/.test(footer), `${rows} rows, "${footer}"`);
        check(`Products: stock_status=${status} excludes inactive products`, !badges.some((b) => /inactive/i.test(b)));
    }
    await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' });
    await page.evaluate(() => { const s = document.querySelector('select[name="warehouse_id[]"]'); [...s.options].forEach((o) => { if (o.value === '1' || o.value === '2') o.selected = true; }); });
    await submitNative(page, 'document.getElementById("products-filter-form")');
    const skus = (await page.locator('table tbody tr td:first-child').allInnerTexts()).map((s) => s.trim());
    check('Products: filtering by 2 warehouses lists each product once', skus.length > 0 && new Set(skus).size === skus.length, `${skus.length} rows, ${new Set(skus).size} unique`);
    await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.selectOption('#per-page-select', '25')]);
    check('Products: "Rows per page" = 25 shows 25 rows', (await page.locator('table tbody tr').count()) === 25);

    // PO/SO sort survives pagination.
    for (const path of ['/purchase-orders', '/sales-orders']) {
        await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[name="sort"]')]);
        const before = (await page.locator('button[name="sort"]').innerText()).trim();
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('nav.pagination button:has-text("Next")')]);
        const after = (await page.locator('button[name="sort"]').innerText()).trim();
        check(`${path}: sort direction is kept after Next`, before === after, `"${before}" → "${after}"`);
    }

    // PO add line adds exactly one row.
    await page.goto(`${BASE_URL}/purchase-orders/create`, { waitUntil: 'networkidle' });
    const r0 = await page.locator('#po-items-body tr').count();
    await page.click('#po-item-add');
    check('PO form: "Add Line Item" adds exactly one row (was 2)', (await page.locator('#po-items-body tr').count()) - r0 === 1);

    // SO line items survive a server-side validation failure.
    await page.goto(`${BASE_URL}/sales-orders/create`, { waitUntil: 'networkidle' });
    await page.click('#add-item-btn');
    const sel = page.locator('#items-body select[name="item_product_id[]"]');
    await sel.nth(0).selectOption({ index: 1 });
    await sel.nth(1).selectOption({ index: 2 });
    const chosen = await page.$$eval('#items-body select[name="item_product_id[]"]', (ss) => ss.map((s) => s.value));
    await page.evaluate(() => { document.getElementById('customer_id').value = ''; document.querySelectorAll('[required]').forEach((e) => e.removeAttribute('required')); });
    await submitNative(page, 'document.getElementById("items-body").closest("form")');
    const kept = await page.$$eval('#items-body select[name="item_product_id[]"]', (ss) => ss.map((s) => s.value));
    check('SO form: line items survive a validation failure (were wiped)', JSON.stringify(chosen) === JSON.stringify(kept), `${JSON.stringify(chosen)} → ${JSON.stringify(kept)}`);

    // Stock ledger header count follows the filter.
    await page.goto(`${BASE_URL}/stock-ledger`, { waitUntil: 'networkidle' });
    const totalBefore = (await page.locator('#ledger-total').innerText()).trim();
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/stock-ledger') && r.request().method() === 'POST'), page.fill('#ledger-sku', 'ELEC-003')]);
    await page.waitForTimeout(200);
    check('Stock ledger: "N movements recorded" updates with the filter', (await page.locator('#ledger-total').innerText()).trim() !== totalBefore);

    // Category edit link opens the modal for that category.
    await page.goto(`${BASE_URL}/categories`, { waitUntil: 'networkidle' });
    const catToken = await page.$eval('.js-edit-category', (b) => b.dataset.id).catch(() => null);
    await page.goto(`${BASE_URL}/categories/${catToken}/edit`, { waitUntil: 'networkidle' });
    check('Categories: /categories/{id}/edit opens the edit modal prefilled', (await page.inputValue('#category-form input[name="id"]').catch(() => '')) === catToken);

    // Mark-all-read from a POST-only page lands on its GET page.
    const csrf = await page.$eval('input[name="_csrf_token"]', (i) => i.value);
    for (const [ref, expected] of [['/warehouses/abc123abc123/update', '/warehouses/abc123abc123/edit'], ['/products/search', '/products'], ['/reports/search', '/reports']]) {
        const r = await page.request.post(`${BASE_URL}/notifications/mark-all-read`, { form: { _csrf_token: csrf }, headers: { Referer: BASE_URL + ref }, maxRedirects: 0 });
        check(`Mark-all-read from ${ref} redirects to ${expected}`, r.status() === 302 && r.headers()['location'] === expected, `${r.status()} ${r.headers()['location']}`);
    }

    // Reports: scoped KPIs, real trend delta, cross-page sort, scoped exports.
    await submitReport(page, { date_from: DATE_FROM, date_to: DATE_TO });
    const kpiAll = await page.locator('.report-kpi-card__value').allInnerTexts();
    check('Reports: no hardcoded "+4.2%"', !(await page.locator('body').innerText()).includes('+4.2%'));
    await submitReport(page, { date_from: DATE_FROM, date_to: DATE_TO, warehouse_id: 1 });
    const kpiWh = await page.locator('.report-kpi-card__value').allInnerTexts();
    check('Reports: KPI cards follow the Warehouse filter', kpiAll[0] !== kpiWh[0], `${kpiAll[0]} vs ${kpiWh[0]}`);
    const vals = [];
    for (const p of [1, 2]) {
        await submitReport(page, { report_type: 'stock_valuation', sort: 'valuation_desc', date_from: DATE_FROM, date_to: DATE_TO, page: p });
        vals.push((await page.locator('.table-wrap tbody tr td:nth-child(7)').allInnerTexts()).map((c) => parseInt(c.replace(/[^\d]/g, ''), 10) || 0));
    }
    check('Reports: "Valuation (High-Low)" sorts across pages (page 1 ≥ page 2)', Math.min(...vals[0]) >= Math.max(...vals[1]), `${Math.min(...vals[0])} vs ${Math.max(...vals[1])}`);
    await submitReport(page, { report_type: 'movement_ledger' });
    const ledgerSorts = await page.$$eval('#report-sort option', (os) => os.map((o) => o.value));
    check('Reports: Movement Ledger offers only date/name sorts', JSON.stringify(ledgerSorts) === JSON.stringify(['date_desc', 'date_asc', 'name_asc']), JSON.stringify(ledgerSorts));
    const rows = async (resp) => (await resp.text()).trim().split(/\r\n/).slice(1).map((l) => l.split(','));
    const ledgerQ = await rows(await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, { form: { date_from: DATE_FROM, date_to: DATE_TO, q: 'ELEC-003' } }));
    check('Reports: Stock Ledger export applies the table search (q)', ledgerQ.length > 0 && ledgerQ.every((r) => r[2] === 'ELEC-003'), `${ledgerQ.length} rows`);
    const ordersWh = await rows(await page.request.post(`${BASE_URL}/reports/export/orders`, { form: { from: DATE_FROM, to: DATE_TO, warehouse_id: '1' } }));
    check('Reports: Orders export applies the Warehouse filter', ordersWh.length > 0 && new Set(ordersWh.map((r) => r[4])).size === 1, JSON.stringify([...new Set(ordersWh.map((r) => r[4]))]));

    check('Admin: no JavaScript errors', pageErrors.length === 0, JSON.stringify(pageErrors.slice(0, 3)));
    await context.close();
}

async function warehouseChecks(browser) {
    console.log('\n=== WarehouseStaff ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'warehouse@example.com', 'wh123');
    const issueRows = await page.locator('#warehouse-issue-queue tbody tr').count();
    check('Dashboard lists Approved SOs to issue (the role has no Sales Orders menu)', issueRows > 0 || /No approved sales orders/.test(await page.locator('body').innerText()));
    if (issueRows > 0) {
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('#warehouse-issue-queue tbody tr:first-child a')]);
        check('Issue queue link opens the SO with its Goods Issue action', (await page.locator('button:has-text("Issue"), a:has-text("Issue")').count()) > 0, page.url());
    }
    await page.goto(`${BASE_URL}/sales-dashboard`, { waitUntil: 'networkidle' });
    check('Sales Dashboard hides "Create Sales Order" from WarehouseStaff (it 403s)', (await page.locator('a[href="/sales-orders/create"]').count()) === 0);
    await context.close();
}

async function salesChecks(browser) {
    console.log('\n=== Sales ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'sales1@example.com', 'sales123');
    await page.goto(`${BASE_URL}/my-profile`, { waitUntil: 'networkidle' });
    check('/my-profile renders without a PHP warning', !/(Warning|Notice)\s*:/.test(await page.locator('body').innerText()));
    const totals = {};
    for (const period of ['today', 'all']) {
        await page.goto(`${BASE_URL}/sales-dashboard`, { waitUntil: 'networkidle' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.selectOption('select[name="period"]', period)]);
        totals[period] = (await page.locator('.stat-card__value, [class*="kpi"] [class*="value"]').allInnerTexts()).slice(1, 2).join('');
    }
    check('Sales Dashboard: order counts follow the period (today ≠ all)', totals.today !== totals.all, JSON.stringify(totals));
    check('Sales Dashboard: no fabricated "Payment Status" column', (await page.locator('th:has-text("Payment")').count()) === 0);
    await context.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await adminChecks(browser);
        await warehouseChecks(browser);
        await salesChecks(browser);
    } finally {
        await browser.close();
    }
    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

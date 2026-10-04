// Ad-hoc Playwright QA script for the Warehouses menu (/warehouses).
// Checks against PROJECT_REFERENCE.md WH-01 (Gudang & Stok Multi-Lokasi),
// the §1.2 role/access matrix, and BR-017 (UI hiding is not authorization —
// every warehouses.manage endpoint must reject Sales/WarehouseStaff
// server-side, not just hide the button).
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const TEST_WAREHOUSE_NAME = 'QA Playwright Warehouse';
const TEST_WAREHOUSE_CODE = 'QA-WH-01';

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

function getCsrfToken(page) {
    return page.locator('input[name="_csrf_token"]').first().inputValue();
}

// ---------------------------------------------------------------------
// Unauthenticated access must redirect to /login, not leak the page.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    const resp = await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    check('GET /warehouses without session redirects to /login', page.url().includes('/login'), `landed on ${page.url()}`);
    await context.close();
}

// ---------------------------------------------------------------------
// Per-role UI checks: index page, filters, row actions, detail page
// (this page previously threw a fatal error — Container::getProductStockService()
// did not exist — for every role, since indexAction/showAction only require
// login, not the warehouses.manage permission).
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: view ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (err) => pageErrors.push(String(err)));

    await login(page, email, password);
    const resp = await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    check(`GET /warehouses returns 200`, resp.status() === 200, `got ${resp.status()}`);

    const isAdmin = role === 'Admin';
    const addButtonCount = await page.locator('a:has-text("Add Warehouse")').count();
    check(`"Add Warehouse" button visible only for Admin`, (addButtonCount > 0) === isAdmin, `visible=${addButtonCount > 0}`);

    check('Filter fields present (Code, Name, Location, Status)',
        (await page.locator('#warehouse-code').count()) > 0
        && (await page.locator('#warehouse-name').count()) > 0
        && (await page.locator('#warehouse-location').count()) > 0
        && (await page.locator('#warehouse-status-wrapper').count()) > 0);

    const rowCount = await page.locator('table.table tbody tr').count();
    note(`warehouse rows in list: ${rowCount}`);

    if (rowCount > 0) {
        // Open first row's actions menu and check per-role action visibility.
        await page.locator('.row-actions__trigger').first().click();
        await page.waitForTimeout(150);
        const menu = page.locator('.row-actions__menu').first();
        const viewVisible = await menu.locator('.row-actions__item--view').count();
        const editVisible = await menu.locator('.row-actions__item--edit').count();
        const toggleVisible = await menu.locator('.row-actions__item--destructive, .row-actions__item--activate').count();
        check('Row action "View" always visible', viewVisible > 0);
        check('Row action "Edit" visible only for Admin', (editVisible > 0) === isAdmin, `visible=${editVisible > 0}`);
        check('Row action Activate/Deactivate visible only for Admin', (toggleVisible > 0) === isAdmin, `visible=${toggleVisible > 0}`);

        // Follow the View link to the detail page — this is the page that used
        // to throw a fatal error (Container::getProductStockService() missing).
        const viewHref = await menu.locator('.row-actions__item--view').getAttribute('href');
        await page.keyboard.press('Escape');
        const detailResp = await page.goto(`${BASE_URL}${viewHref}`, { waitUntil: 'networkidle' });
        check('Warehouse detail page returns 200 (no fatal error)', detailResp.status() === 200, `got ${detailResp.status()}`);
        const kpiCount = await page.locator('.stat-card').count();
        check('Detail page renders stock KPI cards (Products Stocked / Total Quantity)', kpiCount >= 2, `found ${kpiCount}`);
        const stockTableRows = await page.locator('.dashboard-section table.table tbody tr').count();
        note(`stock-by-product rows rendered on detail page: ${stockTableRows}`);
        check('Stock table page 1 shows at most 10 rows', stockTableRows <= 10, `found ${stockTableRows}`);
        const editLinkOnDetail = await page.locator('.page-header__actions a:has-text("Edit")').count();
        check('Edit link on detail page visible only for Admin', (editLinkOnDetail > 0) === isAdmin, `visible=${editLinkOnDetail > 0}`);

        // Pagination: a warehouse stocking >10 products must expose page 2+,
        // and page 2 must show different rows than page 1 (not silently
        // truncated to the same first 10 forever).
        const paginationVisible = await page.locator('.pagination-nav').count();
        if (paginationVisible > 0) {
            const page1FirstSku = await page.locator('.dashboard-section table.table tbody tr').first().locator('td').first().textContent();
            const page2Button = page.locator('.pagination-nav button[aria-label="Page 2"]');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }),
                page2Button.click(),
            ]);
            const page2FirstSku = await page.locator('.dashboard-section table.table tbody tr').first().locator('td').first().textContent();
            check('Stock page 2 shows different products than page 1', page1FirstSku !== page2FirstSku, `page1="${page1FirstSku}" page2="${page2FirstSku}"`);
            // Pagination is POSTed — stock_page must never appear in the address bar.
            check('Stock pagination keeps the URL free of query params', !page.url().includes('?'), `url=${page.url()}`);
        } else {
            note('Stock table fits on one page for this warehouse — pagination not shown (expected, not a failure).');
        }

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `warehouses-detail-${role}.png`), fullPage: true });
    }

    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `warehouses-list-${role}.png`), fullPage: true });

    check('No console errors', consoleErrors.length === 0, JSON.stringify(consoleErrors));
    check('No page (fatal) errors', pageErrors.length === 0, JSON.stringify(pageErrors));

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// BR-017 — server-side authorization must reject Sales/WarehouseStaff on
// every warehouses.manage endpoint even when called directly, not just
// hidden in the UI.
// ---------------------------------------------------------------------
async function checkServerSideAuthorization(browser, { role, email, password }, sampleWarehouseHref) {
    console.log(`\n=== ${role}: direct endpoint access (BR-017) ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);

    const csrfToken = await getCsrfToken(page).catch(() => null);
    // A logged-in non-admin still has a valid session/CSRF pair from any form
    // on the page; grab one from the warehouses list itself (row-actions has
    // none for non-admin, so read from another page's hidden CSRF field).
    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    const anyCsrf = await page.evaluate(() => {
        const el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : null;
    });

    const createFormResp = await page.request.get(`${BASE_URL}/warehouses/create`, { maxRedirects: 0 }).catch((e) => e);
    check('GET /warehouses/create rejected (403) for non-admin', createFormResp.status && createFormResp.status() === 403, `got ${createFormResp.status ? createFormResp.status() : 'ERROR'}`);

    const storeResp = await page.request.post(`${BASE_URL}/warehouses`, {
        form: { code: 'HACK-01', name: 'Should Not Be Created', _csrf_token: anyCsrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check('POST /warehouses (store) rejected (403) for non-admin', storeResp.status && storeResp.status() === 403, `got ${storeResp.status ? storeResp.status() : 'ERROR'}`);

    if (sampleWarehouseHref) {
        const updateResp = await page.request.post(`${BASE_URL}${sampleWarehouseHref}/update`, {
            form: { code: 'HACK', name: 'Hacked', _csrf_token: anyCsrf || '' },
            maxRedirects: 0,
        }).catch((e) => e);
        check('POST .../update rejected (403) for non-admin', updateResp.status && updateResp.status() === 403, `got ${updateResp.status ? updateResp.status() : 'ERROR'}`);

        const deactivateResp = await page.request.post(`${BASE_URL}${sampleWarehouseHref}/deactivate`, {
            form: { _csrf_token: anyCsrf || '' },
            maxRedirects: 0,
        }).catch((e) => e);
        check('POST .../deactivate rejected (403) for non-admin', deactivateResp.status && deactivateResp.status() === 403, `got ${deactivateResp.status ? deactivateResp.status() : 'ERROR'}`);
    }

    await context.close();
}

// ---------------------------------------------------------------------
// Admin CRUD flow: idempotent create-or-reuse, duplicate-code validation,
// edit, deactivate, reactivate. No hard-delete route exists for warehouses
// (soft delete only, per PRD-DATA-02-style pattern), so the test warehouse
// is left in an Active state at the end for the next run to reuse.
// ---------------------------------------------------------------------
async function checkAdminCrud(browser) {
    console.log('\n=== Admin: CRUD flow ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // Reuse the test warehouse if a previous run left it behind (idempotent re-run).
    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    await page.fill('#warehouse-code', TEST_WAREHOUSE_CODE);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    let existingRow = await page.locator('table.table tbody tr').count();

    if (existingRow === 0) {
        await page.goto(`${BASE_URL}/warehouses/create`, { waitUntil: 'networkidle' });
        await page.fill('#code', TEST_WAREHOUSE_CODE);
        await page.fill('#name', TEST_WAREHOUSE_NAME);
        await page.fill('#location', 'QA Test Location');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
        check('Create warehouse redirects back to /warehouses', page.url() === `${BASE_URL}/warehouses` || page.url() === `${BASE_URL}/warehouses/`, `landed on ${page.url()}`);
    } else {
        note('Reusing existing QA test warehouse from a previous run.');
    }

    // Duplicate-code validation: try to create another warehouse with the same code as a real seeded one.
    await page.goto(`${BASE_URL}/warehouses/create`, { waitUntil: 'networkidle' });
    await page.fill('#code', 'WH-JKT');
    await page.fill('#name', 'Duplicate Code Attempt');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
    const dupError = await page.locator('.alert--error').textContent().catch(() => '');
    check('Duplicate warehouse code is rejected with a validation message', /already in use/i.test(dupError || ''), `message: "${dupError}"`);

    // Edit the QA test warehouse (rename).
    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    await page.fill('#warehouse-code', TEST_WAREHOUSE_CODE);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    await page.locator('.row-actions__trigger').first().click();
    await page.waitForTimeout(150);
    const editHref = await page.locator('.row-actions__item--edit').first().getAttribute('href');
    await page.goto(`${BASE_URL}${editHref}`, { waitUntil: 'networkidle' });
    const renamedTo = TEST_WAREHOUSE_NAME + ' (Edited)';
    await page.fill('#name', renamedTo);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);

    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    await page.fill('#warehouse-code', TEST_WAREHOUSE_CODE);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    const rowText = await page.locator('table.table tbody tr').first().textContent();
    check('Edited warehouse name is reflected in the list', (rowText || '').includes(renamedTo), `row text: "${rowText}"`);

    // Deactivate then reactivate (round-trip), leaving it Active for the next
    // run. The (de)activate form is intercepted client-side (warehouses.js,
    // data-ajax-status) and submitted via fetch, then the page does its own
    // window.location.reload() — not a Playwright-visible "navigation" tied
    // to the click — so each step re-navigates itself afterwards rather than
    // waiting on that reload. Whichever of Deactivate/Activate is actually
    // rendered is read at each step instead of assumed, since a previous
    // interrupted run may have left the row in either state.
    async function reapplyTestWarehouseFilter() {
        // The app's own post-toggle window.location.reload() can race this
        // goto (ERR_ABORTED if both fire at once) — retry once if so.
        try {
            await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
        } catch (_) {
            await page.waitForTimeout(500);
            await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
        }
        await page.fill('#warehouse-code', TEST_WAREHOUSE_CODE);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    }

    async function toggleStatus() {
        await page.locator('.row-actions__trigger').first().click();
        await page.waitForTimeout(150);
        const toggleBtn = page.locator('.row-actions__item--destructive, .row-actions__item--activate').first();
        await toggleBtn.waitFor({ state: 'visible', timeout: 5000 });
        await toggleBtn.click();
        await page.waitForTimeout(3000); // fetch + toast + client-side reload
        await reapplyTestWarehouseFilter();

        return (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    }

    await reapplyTestWarehouseFilter();
    const statusBefore = (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    const statusAfterFirstToggle = await toggleStatus();
    const expectedAfterFirstToggle = statusBefore === 'Active' ? 'Inactive' : 'Active';
    check(`Toggle flips status ${statusBefore} -> ${expectedAfterFirstToggle}`, statusAfterFirstToggle === expectedAfterFirstToggle, `got "${statusAfterFirstToggle}"`);

    const statusAfterSecondToggle = await toggleStatus();
    check(`Toggle flips status back ${expectedAfterFirstToggle} -> ${statusBefore}`, statusAfterSecondToggle === statusBefore, `got "${statusAfterSecondToggle}"`);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'warehouses-admin-crud.png'), fullPage: true });
    await logout(page);
    await context.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);

        for (const roleConfig of ROLES) {
            await checkRoleView(browser, roleConfig);
        }

        // Grab a real warehouse detail href (as Admin) to use as the target of
        // the direct-endpoint-access checks below.
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await login(adminPage, 'admin@example.com', 'admin123');
        await adminPage.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
        await adminPage.locator('.row-actions__trigger').first().click();
        await adminPage.waitForTimeout(150);
        const sampleHref = await adminPage.locator('.row-actions__item--view').first().getAttribute('href');
        await adminContext.close();

        for (const roleConfig of ROLES.filter((r) => r.role !== 'Admin')) {
            await checkServerSideAuthorization(browser, roleConfig, sampleHref);
        }

        await checkAdminCrud(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

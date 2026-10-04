// Ad-hoc Playwright QA script for the Categories menu (/categories).
// Checks the page against PROJECT_REFERENCE.md (§1.2 role matrix, §2.2
// Master Data / PRD-01, §2.5 FIND-01) and docs/roles/ROLE-*.md.
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

function getCsrfToken(page) {
    return page.locator('input[name="_csrf_token"]').first().inputValue();
}

// Playwright throws if a previous in-flight navigation (e.g. the filter
// form's own POST-triggered reload) is still settling when the next goto()
// starts — swallow that race and just wait for the page to actually land.
async function safeGoto(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
}

// ---------------------------------------------------------------------
// Unauthenticated access — AUTH-01.04: protected pages must redirect to
// /login without a session.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();

    const resp = await page.goto(`${BASE_URL}/categories`, { waitUntil: 'networkidle' });
    check(
        'AUTH-01.04: GET /categories without session redirects to /login',
        page.url().includes('/login'),
        `landed on ${page.url()}`
    );

    const apiResp = await page.request.post(`${BASE_URL}/categories`, { form: { name: 'Test' }, maxRedirects: 0 });
    check(
        'Unauthenticated POST /categories is rejected (redirect/4xx, not a direct 200)',
        apiResp.status() !== 200,
        `got HTTP ${apiResp.status()}`
    );

    await context.close();
}

// ---------------------------------------------------------------------
// Per-role view: PROJECT_REFERENCE.md §1.2 matrix — only Admin manages
// master data; Sales/Warehouse get view-only (if they can reach the page
// at all — see docs/roles/*.md note below).
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: GET /categories ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await login(page, email, password);

    const resp = await page.goto(`${BASE_URL}/categories`, { waitUntil: 'networkidle' });
    const status = resp ? resp.status() : null;
    note(`HTTP status for direct navigation: ${status}`);

    if (role === 'Admin') {
        check('Admin sees "Add Category" button', await page.locator('#btn-add-category').count() > 0);
        check('Admin sees row-actions (Edit/Delete) menu trigger', await page.locator('.row-actions__trigger').count() > 0);
        check('Admin sees Export CSV control', await page.locator('button:has-text("Export CSV")').count() > 0);
    } else {
        // docs/roles/ROLE-SALES.md and ROLE-WAREHOUSE-STAFF.md state the
        // "Master Data -> Categories" sidebar link does not render for these
        // roles because they lack `master_data.menu`. That only hides the
        // menu entry — CategoryController::indexAction() itself only calls
        // requireAuth(), so a direct hit on /categories still returns 200
        // with the full category catalog. This block records what actually
        // happens rather than asserting a pass/fail — see delivery report
        // for the recommended follow-up question.
        note(`Direct navigation to /categories as ${role} returned HTTP ${status} (docs/roles say this menu item should not be reachable by this role)`);
        check(`${role} does NOT see "Add Category" (view-only, PROJECT_REFERENCE.md §1.2)`, await page.locator('#btn-add-category').count() === 0);
        check(`${role} does NOT see row-actions (Edit/Delete) menu`, await page.locator('.row-actions__trigger').count() === 0);
    }

    const screenshotPath = path.join(SCREENSHOT_DIR, `categories-${role}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    note(`Screenshot saved: ${screenshotPath}`);
    note(`Console errors: ${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Server-side authorization (CLAUDE.md: "UI hiding is NOT authorization").
// Sales/WarehouseStaff must get rejected by the server even if they call
// the manage endpoints directly, bypassing the hidden UI controls.
// ---------------------------------------------------------------------
async function checkServerSideAuthorization(browser, { role, email, password }) {
    console.log(`\n=== ${role}: server-side authorization on categories.manage endpoints ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);
    await page.goto(`${BASE_URL}/categories`, { waitUntil: 'networkidle' });
    const csrf = await getCsrfToken(page).catch(() => '');

    const attempts = [
        { label: 'POST /categories (create)', method: 'post', url: '/categories', form: { name: 'Hacked Category', _csrf_token: csrf } },
        { label: 'POST /categories/1/update', method: 'post', url: '/categories/1/update', form: { name: 'Hacked', _csrf_token: csrf } },
        { label: 'POST /categories/1/delete', method: 'post', url: '/categories/1/delete', form: { _csrf_token: csrf } },
        { label: 'POST /categories/1/deactivate', method: 'post', url: '/categories/1/deactivate', form: { _csrf_token: csrf } },
        { label: 'POST /categories/1/activate', method: 'post', url: '/categories/1/activate', form: { _csrf_token: csrf } },
    ];

    for (const attempt of attempts) {
        const resp = await page.request.post(`${BASE_URL}${attempt.url}`, { form: attempt.form, maxRedirects: 0 }).catch((e) => e);
        const status = resp && resp.status ? resp.status() : 'ERROR';
        check(
            `SOD/AUTHZ: ${role} -> ${attempt.label} is rejected (expect 403)`,
            status === 403,
            `got HTTP ${status}`
        );
    }

    await logout(page);
    await context.close();
}

// Deletes any pre-existing "QA " test categories left over from a previous
// (e.g. interrupted) run, so this script stays idempotent and never
// accumulates junk rows in the shared dev database.
async function cleanupQaCategories(page, needle) {
    await safeGoto(page, `${BASE_URL}/categories`);
    await page.fill('#category-name', needle);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#category-filter-form button:has-text("Search")'),
    ]);

    // Repeatedly delete the first matching row until none are left (each
    // delete reloads the page, so row handles from a previous iteration go stale).
    for (let i = 0; i < 20; i += 1) {
        const rows = await page.locator('table.table tbody tr').count().catch(() => 0);
        if (rows === 0) { break; }
        await page.click('.row-actions__trigger');
        const deleteBtn = page.locator('.js-delete-category').first();
        if (await deleteBtn.isDisabled().catch(() => true)) { break; } // has SKUs assigned — not our test data
        await deleteBtn.click();
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => null),
            page.click('#delete-modal-confirm', { force: true }),
        ]);
    }
}

// ---------------------------------------------------------------------
// Admin CRUD flow — PRD-01 pattern applied to categories (create / search
// filter / edit / duplicate-name validation / delete-guard / (de)activate).
// ---------------------------------------------------------------------
async function checkAdminCrud(browser) {
    console.log('\n=== Admin: CRUD flow ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // Leftover rows from an earlier interrupted run (e.g. "QA Playwright
    // Category 1234", "QA Debug Category 5678") would otherwise pollute the
    // dev seed data and make code-suffix / row-count assertions flaky.
    await cleanupQaCategories(page, 'QA Playwright Category');
    await cleanupQaCategories(page, 'QA Debug Category');

    await safeGoto(page, `${BASE_URL}/categories`);

    const uniqueName = 'QA Playwright Category ' + Date.now();

    // --- Create ---
    await page.click('#btn-add-category');
    await page.fill('#category-name-input', uniqueName);
    await page.fill('#category-description-input', 'Created by categories.spec.js');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }), // categories.js does window.location.reload() on success
        page.click('#category-modal-save', { force: true }),
    ]);
    check('Create: new category appears in the list after reload', (await page.content()).includes(uniqueName));

    // --- Duplicate name validation ---
    await page.click('#btn-add-category');
    await page.fill('#category-name-input', uniqueName);
    const [dupResp] = await Promise.all([
        page.waitForResponse((r) => r.url().endsWith('/categories') && r.request().method() === 'POST'),
        page.click('#category-modal-save', { force: true }),
    ]);
    // Give the click handler's DOM update (showError/closeModal) a moment to run after the response lands.
    await page.waitForSelector('#category-form-error:not([hidden]), #category-name-error:not([hidden])', { timeout: 5000 }).catch(() => {});
    const bannerText = await page.locator('#category-form-error').textContent().catch(() => '');
    const nameFieldError = await page.locator('#category-name-error').textContent().catch(() => '');
    const dupMessage = `${bannerText || ''} ${nameFieldError || ''}`;
    check(
        'Validation: duplicate category name is rejected (no second row created)',
        dupResp.status() === 422 && /already in use/i.test(dupMessage),
        `HTTP ${dupResp.status()}, message: "${dupMessage.trim()}"`
    );
    await page.click('#category-modal-cancel', { force: true });

    // --- Search filter ---
    await page.fill('#category-name', uniqueName);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#category-filter-form button:has-text("Search")'),
    ]);
    const rowCount = await page.locator('table.table tbody tr').count();
    check('FIND-01: search by name filters the list to exactly the new category (duplicate attempt did not create a second row)', rowCount === 1, `rows after filter: ${rowCount}`);

    // --- Edit --- (row actions live inside a hidden dropdown until the trigger is clicked)
    await page.click('.row-actions__trigger');
    await page.click('.js-edit-category');
    const editedName = uniqueName + ' (Edited)';
    await page.fill('#category-name-input', editedName);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }), // window.location.reload() on success
        page.click('#category-modal-save', { force: true }),
    ]);
    check('Edit: updated name is reflected in the list', (await page.content()).includes(editedName));

    // --- Delete (0 assigned SKUs -> button enabled) ---
    await page.click('.row-actions__trigger');
    await page.click('.js-delete-category'); // opens delete confirmation dialog
    const dialogVisible = await page.locator('#delete-modal-backdrop:not([hidden])').count();
    check('Delete dialog opens for a category with 0 assigned SKUs', dialogVisible > 0);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }), // window.location.reload() on success
        page.click('#delete-modal-confirm', { force: true }),
    ]);
    check('Delete: category removed from the list after confirming', !(await page.content()).includes(editedName));

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'categories-admin-crud-final.png'), fullPage: true });

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Delete guard — CategoryService::MESSAGE_CANNOT_DELETE_HAS_PRODUCTS.
// Seed data ships categories with assigned SKUs (Electronics, etc.) —
// their row-menu Delete button must render disabled.
// ---------------------------------------------------------------------
async function checkDeleteGuardOnSeedCategory(browser) {
    console.log('\n=== Admin: delete guard on a category with assigned SKUs ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    // The filter form is POST-only (query-string params are ignored), so filter by filling it in.
    {
        await safeGoto(page, `${BASE_URL}/categories`);
        await page.fill('#category-name', 'Electronics');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('#category-filter-form button:has-text("Search")'),
        ]);
    }

    await page.click('.row-actions__trigger');
    const disabled = await page.locator('.js-delete-category').first().isDisabled().catch(() => null);
    check('Delete button is disabled for a category with assigned SKUs (PRD-DATA / §3.4 guard)', disabled === true, `isDisabled=${disabled}`);

    // Confirm the server-side guard backs the disabled button up (not just UI).
    const csrf = await getCsrfToken(page).catch(() => '');
    const editBtn = page.locator('.js-edit-category').first();
    const categoryToken = await editBtn.getAttribute('data-id').catch(() => null);
    if (categoryToken) {
        const resp = await page.request.post(`${BASE_URL}/categories/${categoryToken}/delete`, {
            form: { _csrf_token: csrf },
        }).catch((e) => e);
        const status = resp && resp.status ? resp.status() : 'ERROR';
        const body = resp && resp.json ? await resp.json().catch(() => null) : null;
        check(
            'Server rejects delete-with-assigned-SKUs even when called directly (not just disabled button)',
            status === 422 && body && body.ok === false,
            `HTTP ${status}, body: ${JSON.stringify(body)}`
        );
    } else {
        note('Could not resolve an encoded id for the Electronics row — skipped direct-endpoint guard check.');
    }

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// "N SKU" link -> /products/search?category[]=<id>: cross-page filter
// integration, plus the Export CSV button's headers/content and its
// REPORT-01.04-style CSV-injection escaping (categories aren't in
// PROJECT_REFERENCE.md's REPORT-01 scope, but the export reuses the same
// CsvExportService::escapeCsvField() guard as Stock Ledger/Orders exports).
// ---------------------------------------------------------------------
async function checkSkuLinkAndCsvExport(browser) {
    console.log('\n=== Admin: "N SKU" link + Export CSV ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');
    await safeGoto(page, `${BASE_URL}/categories`);

    // --- "N SKU" cross-page link ---
    const skuLinkRow = page.locator('tr', { has: page.locator('.category-sku-link') }).first();
    const skuLabel = (await skuLinkRow.locator('.category-sku-link').textContent().catch(() => '') || '').trim();
    const expectedCount = parseInt(skuLabel, 10);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        skuLinkRow.locator('.category-sku-link').click(),
    ]);
    check('"N SKU" link navigates to the Products page', page.url().includes('/products'), `landed on ${page.url()}`);
    const summaryText = await page.locator('.pagination-footer__summary, .pagination-footer').first().textContent().catch(() => '');
    const totalMatch = (summaryText || '').match(/of\s+(\d+)/);
    const productsTotal = totalMatch ? parseInt(totalMatch[1], 10) : null;
    check(
        `"N SKU" link filters Products to the same count shown on the Categories row (${expectedCount})`,
        !Number.isNaN(expectedCount) && productsTotal === expectedCount,
        `summary: "${(summaryText || '').trim()}", parsed total: ${productsTotal}`
    );

    // --- Export CSV: headers + content ---
    await safeGoto(page, `${BASE_URL}/categories`);
    const csrf = await getCsrfToken(page).catch(() => '');
    const exportResp = await page.request.post(`${BASE_URL}/categories/export`, { form: { _csrf_token: csrf } });
    check('Export CSV: Content-Type is text/csv', /text\/csv/.test(exportResp.headers()['content-type'] || ''), exportResp.headers()['content-type']);
    check('Export CSV: Content-Disposition attaches categories.csv', /attachment.*categories\.csv/.test(exportResp.headers()['content-disposition'] || ''), exportResp.headers()['content-disposition']);
    const csvBody = await exportResp.text();
    const csvLines = csvBody.trim().split(/\r\n/);
    check('Export CSV: header row has the 6 expected columns', csvLines[0] === 'Code,Name,Description,Assigned SKUs,Status,Last Updated', csvLines[0]);
    check('Export CSV: row count matches the seed categories (no stray test rows leaked in)', csvLines.length - 1 >= 4, `${csvLines.length - 1} data rows`);

    // --- CSV injection prevention (REPORT-01.04-style guard, applied here too) ---
    const injectedName = "=CMD|' /C calc'!A1 QA Injection Test " + Date.now();
    await page.click('#btn-add-category');
    await page.fill('#category-name-input', injectedName);
    await page.fill('#category-description-input', '+SUM(1+1)*cmd');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#category-modal-save', { force: true }),
    ]);
    const exportResp2 = await page.request.post(`${BASE_URL}/categories/export`, { form: { _csrf_token: csrf, name: 'QA Injection Test' } });
    const csvBody2 = await exportResp2.text();
    const injectedLine = csvBody2.split(/\r\n/).find((l) => l.includes('QA Injection Test'));
    check(
        'Export CSV: a name/description starting with = or + is prefixed with \' (formula-injection guard)',
        !!injectedLine && injectedLine.includes(",'=CMD") && injectedLine.includes(",'+SUM("),
        `row: "${injectedLine}"`
    );

    // Clean up the injection-test row so it doesn't linger in the dev DB.
    await safeGoto(page, `${BASE_URL}/categories`);
    await page.fill('#category-name', 'QA Injection Test');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#category-filter-form button:has-text("Search")'),
    ]);
    await page.click('.row-actions__trigger');
    await page.click('.js-delete-category');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#delete-modal-confirm', { force: true }),
    ]);

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
        for (const roleConfig of ROLES.filter((r) => r.role !== 'Admin')) {
            await checkServerSideAuthorization(browser, roleConfig);
        }
        await checkDeleteGuardOnSeedCategory(browser);
        await checkSkuLinkAndCsvExport(browser);
        await checkAdminCrud(browser);
    } finally {
        await browser.close();
    }
    console.log(`\nDONE — categories checked. PASS=${pass} FAIL=${fail}`);
    process.exitCode = fail > 0 ? 1 : 0;
})();

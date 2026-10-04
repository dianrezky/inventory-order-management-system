// Ad-hoc Playwright QA script for the Customers menu (/customers).
// Checks against PROJECT_REFERENCE.md's Master Data entity list (§1.3),
// DATA-01 (soft delete only — customers are deactivated, never hard
// deleted), SEC-06.03, the §1.2 role/access matrix, and BR-017 (UI hiding
// is not authorization — every customers.manage endpoint must reject
// Sales/WarehouseStaff server-side, not just hide the button).
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const TEST_CUSTOMER_NAME = 'QA Playwright Customer';

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

// ---------------------------------------------------------------------
// Unauthenticated access must redirect to /login, not leak the page.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    check('GET /customers without session redirects to /login', page.url().includes('/login'), `landed on ${page.url()}`);
    await context.close();
}

// ---------------------------------------------------------------------
// Per-role UI checks: index page, filters, row actions, detail page.
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
    const resp = await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });

    // PROJECT_REFERENCE.md §1.2: WarehouseStaff's master-data scope is
    // explicitly "Lihat produk & stok saja" (view products & stock ONLY) —
    // narrower than Sales's "Lihat katalog saja". CustomerController now
    // gates indexAction()/showAction() behind a `customers.view` permission
    // (Admin + Sales only, migration 003_add_customers_view_permission.sql)
    // instead of just requireAuth(), so WarehouseStaff must get 403 here —
    // requires that migration to have been run against the target DB.
    if (role === 'WarehouseStaff') {
        check('GET /customers returns 403 for WarehouseStaff (customers.view not granted)', resp.status() === 403, `got ${resp.status()} — has migration 003_add_customers_view_permission.sql been run?`);
        // The 403 navigation itself logs a "Failed to load resource: 403"
        // console message in Chromium — that's the browser noting the main
        // document's own response status, not a real JS error. Filter it out.
        const realConsoleErrors = consoleErrors.filter((msg) => !/Failed to load resource.*403/.test(msg));
        check('No console errors', realConsoleErrors.length === 0, JSON.stringify(realConsoleErrors));
        check('No page (fatal) errors', pageErrors.length === 0, JSON.stringify(pageErrors));
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `customers-list-${role}.png`), fullPage: true });
        await logout(page);
        await context.close();

        return;
    }

    check('GET /customers returns 200', resp.status() === 200, `got ${resp.status()}`);

    const isAdmin = role === 'Admin';
    const addButtonCount = await page.locator('a:has-text("Add Customer")').count();
    check('"Add Customer" button visible only for Admin', (addButtonCount > 0) === isAdmin, `visible=${addButtonCount > 0}`);

    check('Filter fields present (Name, Contact Person, Phone, Email, Status)',
        (await page.locator('#customer-name').count()) > 0
        && (await page.locator('#customer-contact-person').count()) > 0
        && (await page.locator('#customer-phone').count()) > 0
        && (await page.locator('#customer-email').count()) > 0
        && (await page.locator('#customer-status-wrapper').count()) > 0);

    const rowCount = await page.locator('table.table tbody tr').count();
    note(`customer rows in list: ${rowCount}`);

    if (rowCount > 0) {
        await page.locator('.row-actions__trigger').first().click();
        await page.waitForTimeout(150);
        const menu = page.locator('.row-actions__menu').first();
        const viewVisible = await menu.locator('.row-actions__item--view').count();
        const editVisible = await menu.locator('.row-actions__item--edit').count();
        const toggleVisible = await menu.locator('.row-actions__item--destructive, .row-actions__item--activate').count();
        check('Row action "View" always visible', viewVisible > 0);
        check('Row action "Edit" visible only for Admin', (editVisible > 0) === isAdmin, `visible=${editVisible > 0}`);
        check('Row action Activate/Deactivate visible only for Admin', (toggleVisible > 0) === isAdmin, `visible=${toggleVisible > 0}`);

        const viewHref = await menu.locator('.row-actions__item--view').getAttribute('href');
        await page.keyboard.press('Escape');
        const detailResp = await page.goto(`${BASE_URL}${viewHref}`, { waitUntil: 'networkidle' });
        check('Customer detail page returns 200 (no fatal error)', detailResp.status() === 200, `got ${detailResp.status()}`);
        const editLinkOnDetail = await page.locator('.page-header__actions a:has-text("Edit")').count();
        check('Edit link on detail page visible only for Admin', (editLinkOnDetail > 0) === isAdmin, `visible=${editLinkOnDetail > 0}`);
        const toggleOnDetail = await page.locator('.page-header__actions button:has-text("Deactivate"), .page-header__actions button:has-text("Reactivate")').count();
        check('Deactivate/Reactivate button on detail page visible only for Admin', (toggleOnDetail > 0) === isAdmin, `visible=${toggleOnDetail > 0}`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `customers-detail-${role}.png`), fullPage: true });
    }

    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `customers-list-${role}.png`), fullPage: true });

    check('No console errors', consoleErrors.length === 0, JSON.stringify(consoleErrors));
    check('No page (fatal) errors', pageErrors.length === 0, JSON.stringify(pageErrors));

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// BR-017 — server-side authorization must reject Sales/WarehouseStaff on
// every customers.manage endpoint even when called directly, not just
// hidden in the UI.
// ---------------------------------------------------------------------
async function checkServerSideAuthorization(browser, { role, email, password }, sampleCustomerHref) {
    console.log(`\n=== ${role}: direct endpoint access (BR-017) ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);

    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    const anyCsrf = await page.evaluate(() => {
        const el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : null;
    });

    const createFormResp = await page.request.get(`${BASE_URL}/customers/create`, { maxRedirects: 0 }).catch((e) => e);
    check('GET /customers/create rejected (403) for non-admin', createFormResp.status && createFormResp.status() === 403, `got ${createFormResp.status ? createFormResp.status() : 'ERROR'}`);

    if (role === 'WarehouseStaff' && sampleCustomerHref) {
        const showResp = await page.request.get(`${BASE_URL}${sampleCustomerHref}`, { maxRedirects: 0 }).catch((e) => e);
        check('GET /customers/{id} rejected (403) for WarehouseStaff (customers.view not granted)', showResp.status && showResp.status() === 403, `got ${showResp.status ? showResp.status() : 'ERROR'}`);
    }

    const storeResp = await page.request.post(`${BASE_URL}/customers`, {
        form: { name: 'Should Not Be Created', _csrf_token: anyCsrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check('POST /customers (store) rejected (403) for non-admin', storeResp.status && storeResp.status() === 403, `got ${storeResp.status ? storeResp.status() : 'ERROR'}`);

    if (sampleCustomerHref) {
        const updateResp = await page.request.post(`${BASE_URL}${sampleCustomerHref}/update`, {
            form: { name: 'Hacked', _csrf_token: anyCsrf || '' },
            maxRedirects: 0,
        }).catch((e) => e);
        check('POST .../update rejected (403) for non-admin', updateResp.status && updateResp.status() === 403, `got ${updateResp.status ? updateResp.status() : 'ERROR'}`);

        const deactivateResp = await page.request.post(`${BASE_URL}${sampleCustomerHref}/deactivate`, {
            form: { _csrf_token: anyCsrf || '' },
            maxRedirects: 0,
        }).catch((e) => e);
        check('POST .../deactivate rejected (403) for non-admin', deactivateResp.status && deactivateResp.status() === 403, `got ${deactivateResp.status ? deactivateResp.status() : 'ERROR'}`);
    }

    await context.close();
}

// ---------------------------------------------------------------------
// Admin CRUD flow: idempotent create-or-reuse, invalid-email validation,
// edit, deactivate/reactivate round-trip. No hard-delete route exists for
// customers (soft delete only, DATA-01), so the test customer is left
// Active at the end for the next run to reuse.
// ---------------------------------------------------------------------
async function checkAdminCrud(browser) {
    console.log('\n=== Admin: CRUD flow ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    await page.fill('#customer-name', TEST_CUSTOMER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    const existingRow = await page.locator('table.table tbody tr').count();

    if (existingRow === 0) {
        await page.goto(`${BASE_URL}/customers/create`, { waitUntil: 'networkidle' });
        await page.fill('#name', TEST_CUSTOMER_NAME);
        await page.fill('#contact_person', 'QA Bot');
        await page.fill('#phone', '081200000000');
        await page.fill('#email', 'qa-playwright-customer@example.com');
        await page.fill('#address', 'QA Test Address');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
        check('Create customer redirects back to /customers', page.url() === `${BASE_URL}/customers` || page.url() === `${BASE_URL}/customers/`, `landed on ${page.url()}`);
    } else {
        note('Reusing existing QA test customer from a previous run.');
    }

    // Invalid email format is rejected.
    await page.goto(`${BASE_URL}/customers/create`, { waitUntil: 'networkidle' });
    await page.fill('#name', 'Invalid Email Attempt');
    await page.fill('#email', 'not-an-email');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
    const emailError = await page.locator('.alert--error').textContent().catch(() => '');
    check('Invalid email format is rejected with a validation message', /valid email/i.test(emailError || ''), `message: "${emailError}"`);

    // Empty name is rejected.
    await page.goto(`${BASE_URL}/customers/create`, { waitUntil: 'networkidle' });
    await page.fill('#name', '');
    await page.fill('#email', 'someone@example.com');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
    const nameError = await page.locator('.alert--error').textContent().catch(() => '');
    check('Empty name is rejected with a validation message', /name is required/i.test(nameError || ''), `message: "${nameError}"`);

    // Edit the QA test customer (rename).
    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    await page.fill('#customer-name', TEST_CUSTOMER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    await page.locator('.row-actions__trigger').first().click();
    await page.waitForTimeout(150);
    const editHref = await page.locator('.row-actions__item--edit').first().getAttribute('href');
    check('Edit link uses obfuscated id, not raw numeric id', editHref !== null && !/\/customers\/\d+\/edit/.test(editHref), `href="${editHref}"`);
    await page.goto(`${BASE_URL}${editHref}`, { waitUntil: 'networkidle' });
    const formAction = await page.locator('form').first().getAttribute('action');
    check('Edit form action uses obfuscated id, not raw numeric id', formAction !== null && !/\/customers\/\d+\/update/.test(formAction), `action="${formAction}"`);
    const renamedTo = TEST_CUSTOMER_NAME + ' (Edited)';
    await page.fill('#name', renamedTo);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);

    await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
    await page.fill('#customer-name', TEST_CUSTOMER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    const rowText = await page.locator('table.table tbody tr').first().textContent();
    check('Edited customer name is reflected in the list', (rowText || '').includes(renamedTo), `row text: "${rowText}"`);

    // Deactivate then reactivate (round-trip), state-agnostic like warehouses.spec.js
    // (the AJAX (de)activate form does its own window.location.reload(), not a
    // Playwright-visible "navigation" tied directly to the click).
    async function reapplyTestCustomerFilter() {
        try {
            await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
        } catch (_) {
            await page.waitForTimeout(500);
            await page.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
        }
        await page.fill('#customer-name', TEST_CUSTOMER_NAME);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    }

    async function toggleStatus() {
        await page.locator('.row-actions__trigger').first().click();
        await page.waitForTimeout(150);
        const toggleBtn = page.locator('.row-actions__item--destructive, .row-actions__item--activate').first();
        await toggleBtn.waitFor({ state: 'visible', timeout: 5000 });
        await toggleBtn.click();
        await page.waitForTimeout(3000);
        await reapplyTestCustomerFilter();

        return (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    }

    await reapplyTestCustomerFilter();
    const statusBefore = (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    const statusAfterFirstToggle = await toggleStatus();
    const expectedAfterFirstToggle = statusBefore === 'Active' ? 'Inactive' : 'Active';
    check(`Toggle flips status ${statusBefore} -> ${expectedAfterFirstToggle}`, statusAfterFirstToggle === expectedAfterFirstToggle, `got "${statusAfterFirstToggle}"`);

    const statusAfterSecondToggle = await toggleStatus();
    check(`Toggle flips status back ${expectedAfterFirstToggle} -> ${statusBefore}`, statusAfterSecondToggle === statusBefore, `got "${statusAfterSecondToggle}"`);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'customers-admin-crud.png'), fullPage: true });
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

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await login(adminPage, 'admin@example.com', 'admin123');
        await adminPage.goto(`${BASE_URL}/customers`, { waitUntil: 'networkidle' });
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

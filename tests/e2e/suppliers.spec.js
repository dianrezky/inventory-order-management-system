// Ad-hoc Playwright QA script for the Suppliers menu (/suppliers).
// Checks the page against PROJECT_REFERENCE.md (§1.2 role matrix, §1.3 Supplier
// entity, DATA-01 soft-delete rule) and docs/roles/ROLE-*.md.
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

    await page.goto(`${BASE_URL}/suppliers`, { waitUntil: 'networkidle' });
    check(
        'AUTH-01.04: GET /suppliers without session redirects to /login',
        page.url().includes('/login'),
        `landed on ${page.url()}`
    );

    const apiResp = await page.request.post(`${BASE_URL}/suppliers`, { form: { name: 'Test' }, maxRedirects: 0 });
    check(
        'Unauthenticated POST /suppliers is rejected (redirect/4xx, not a direct 200)',
        apiResp.status() !== 200,
        `got HTTP ${apiResp.status()}`
    );

    await context.close();
}

// ---------------------------------------------------------------------
// Per-role view: PROJECT_REFERENCE.md §1.2 matrix — only Admin manages
// master data; Sales/Warehouse get view-only (docs/roles/*.md: the sidebar
// link is hidden for them, but the page itself is still reachable directly —
// see the note in docs/roles/ROLE-SALES.md §2).
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: GET /suppliers ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await login(page, email, password);

    const resp = await page.goto(`${BASE_URL}/suppliers`, { waitUntil: 'networkidle' });
    const status = resp ? resp.status() : null;
    note(`HTTP status for direct navigation: ${status}`);

    if (role === 'Admin') {
        check('Admin sees "Add Supplier" button', await page.locator('a:has-text("Add Supplier")').count() > 0);
        check('Admin sees row-actions (Edit/Deactivate) menu trigger', await page.locator('.row-actions__trigger').count() > 0);
    } else {
        note(`Direct navigation to /suppliers as ${role} returned HTTP ${status} (docs/roles say the sidebar link is hidden, but the page itself is reachable — see ROLE-${role === 'Sales' ? 'SALES' : 'WAREHOUSE-STAFF'}.md §2)`);
        check(`${role} does NOT see "Add Supplier" (view-only, PROJECT_REFERENCE.md §1.2)`, await page.locator('a:has-text("Add Supplier")').count() === 0);
        check(`${role} does NOT see Edit link / Deactivate button in the row menu`, await page.locator('.row-actions__item--edit').count() === 0);
        // "View" is still expected to work for read-only roles.
        check(`${role} still sees the "View" row action (read-only detail access)`, await page.locator('.row-actions__item--view').count() > 0);
    }

    const screenshotPath = path.join(SCREENSHOT_DIR, `suppliers-${role}.png`);
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
// (Suppliers has no delete/export endpoints — only create/update/(de)activate.)
// ---------------------------------------------------------------------
async function checkServerSideAuthorization(browser, { role, email, password }) {
    console.log(`\n=== ${role}: server-side authorization on suppliers.manage endpoints ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);
    await page.goto(`${BASE_URL}/suppliers`, { waitUntil: 'networkidle' });
    const csrf = await getCsrfToken(page).catch(() => '');

    const attempts = [
        { label: 'GET /suppliers/create (create form)', method: 'get', url: '/suppliers/create' },
        { label: 'POST /suppliers (create)', method: 'post', url: '/suppliers', form: { name: 'Hacked Supplier', _csrf_token: csrf } },
        { label: 'GET /suppliers/1/edit (edit form)', method: 'get', url: '/suppliers/1/edit' },
        { label: 'POST /suppliers/1/update', method: 'post', url: '/suppliers/1/update', form: { name: 'Hacked', _csrf_token: csrf } },
        { label: 'POST /suppliers/1/deactivate', method: 'post', url: '/suppliers/1/deactivate', form: { _csrf_token: csrf } },
        { label: 'POST /suppliers/1/activate', method: 'post', url: '/suppliers/1/activate', form: { _csrf_token: csrf } },
    ];

    for (const attempt of attempts) {
        const req = attempt.method === 'get'
            ? page.request.get(`${BASE_URL}${attempt.url}`, { maxRedirects: 0 })
            : page.request.post(`${BASE_URL}${attempt.url}`, { form: attempt.form, maxRedirects: 0 });
        const resp = await req.catch((e) => e);
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

// Suppliers has no delete endpoint (by design — DATA-01 soft-delete-only),
// so a timestamp-suffixed name each run would accumulate forever. Use one
// fixed fixture name and reuse/reset it on every run instead.
const SUPPLIER_FIXTURE_NAME = 'QA Playwright Supplier (E2E Test Fixture)';

async function searchSuppliers(page, name) {
    await safeGoto(page, `${BASE_URL}/suppliers`);
    await page.fill('#supplier-name', name);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('form[action="/suppliers/search"] button:has-text("Search")'),
    ]);
}

// ---------------------------------------------------------------------
// Admin CRUD flow — create (full-page form) / search filter / edit /
// email-format validation / deactivate-activate toggle. Idempotent: reuses
// SUPPLIER_FIXTURE_NAME across runs instead of creating a new row each time.
// ---------------------------------------------------------------------
async function checkAdminCrud(browser) {
    console.log('\n=== Admin: CRUD flow ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // --- Create (only if a previous run didn't already leave the fixture behind) ---
    await searchSuppliers(page, SUPPLIER_FIXTURE_NAME);
    const alreadyExists = (await page.locator('table.table tbody tr').count()) > 0;
    if (!alreadyExists) {
        await safeGoto(page, `${BASE_URL}/suppliers/create`);
        await page.fill('#name', SUPPLIER_FIXTURE_NAME);
        await page.fill('#contact_person', 'QA Contact');
        await page.fill('#phone', '021-0000000');
        await page.fill('#email', 'qa-contact@example.com');
        await page.fill('#address', 'QA Test Address');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]:has-text("Save")'),
        ]);
        check('Create: redirected back to /suppliers list', page.url() === `${BASE_URL}/suppliers`, `landed on ${page.url()}`);
        check('Create: new supplier appears in the list', (await page.content()).includes(SUPPLIER_FIXTURE_NAME));
    } else {
        note('Fixture supplier already exists from a previous run — reusing it (create step skipped).');
    }

    // --- Required-field validation ---
    await safeGoto(page, `${BASE_URL}/suppliers/create`);
    const csrfForValidation = await getCsrfToken(page).catch(() => '');
    const emptyNameResp = await page.request.post(`${BASE_URL}/suppliers`, { form: { name: '', _csrf_token: csrfForValidation } });
    const emptyNameBody = await emptyNameResp.text();
    check('Validation: empty name is rejected server-side', /Name is required/i.test(emptyNameBody), `HTTP ${emptyNameResp.status()}`);

    // --- Invalid email format validation ---
    const badEmailResp = await page.request.post(`${BASE_URL}/suppliers`, {
        form: { name: 'QA Bad Email Supplier ' + Date.now(), email: 'not-an-email', _csrf_token: csrfForValidation },
    });
    const badEmailBody = await badEmailResp.text();
    check('Validation: invalid email format is rejected server-side', /valid email/i.test(badEmailBody), `HTTP ${badEmailResp.status()}`);

    // --- Search filter ---
    await searchSuppliers(page, SUPPLIER_FIXTURE_NAME);
    const rowCount = await page.locator('table.table tbody tr').count();
    check('Search by name filters the list to exactly the fixture supplier', rowCount === 1, `rows after filter: ${rowCount}`);

    // --- Edit --- (row actions live inside a hidden dropdown until the trigger is clicked;
    // toggle a suffix so the edit is observable but the name round-trips back to the fixture name)
    //
    // IMPORTANT: create/update/(de)activate all redirect to the FULL unfiltered
    // /suppliers list, never back to a filtered search. Re-running
    // searchSuppliers() before every .row-actions__trigger click below is not
    // optional — without it, the trigger targets whatever row happens to sort
    // first in the full table (a real seed supplier), not our fixture. Skipping
    // this once already renamed/overwrote a real seed supplier's data in an
    // earlier version of this script — see git history if this comment is ever
    // "simplified" away.
    await searchSuppliers(page, SUPPLIER_FIXTURE_NAME);
    await page.click('.row-actions__trigger');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('.row-actions__item--edit'),
    ]);
    const editedName = SUPPLIER_FIXTURE_NAME + ' [edited]';
    await page.fill('#name', editedName);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]:has-text("Save")'),
    ]);
    check('Edit: updated name is reflected in the list', (await page.content()).includes(editedName));

    // Rename back to the canonical fixture name so the next run's search-by-fixture-name still works.
    await searchSuppliers(page, editedName);
    await page.click('.row-actions__trigger');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('.row-actions__item--edit'),
    ]);
    await page.fill('#name', SUPPLIER_FIXTURE_NAME);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]:has-text("Save")'),
    ]);
    check('Edit: name round-trips back to the fixture name (idempotency reset)', (await page.content()).includes(SUPPLIER_FIXTURE_NAME));

    // --- Deactivate / Activate toggle ---
    // submitStatusForm() (public/assets/js/categories.js, shared across list
    // pages) does window.location.reload() on success — wait for that actual
    // navigation, not just waitForLoadState, or the badge read races the reload.
    await searchSuppliers(page, SUPPLIER_FIXTURE_NAME);
    await page.click('.row-actions__trigger');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('.row-actions__item--destructive'), // "Deactivate" button (data-ajax-status, no confirm dialog)
    ]);
    const badgeAfterDeactivate = await page.locator(`tr:has-text("${SUPPLIER_FIXTURE_NAME}") .badge`).first().textContent().catch(() => '');
    check('Deactivate: badge flips to Inactive', /inactive/i.test(badgeAfterDeactivate || ''), `badge: "${badgeAfterDeactivate}"`);

    await searchSuppliers(page, SUPPLIER_FIXTURE_NAME);
    await page.click('.row-actions__trigger');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('.row-actions__item--activate'), // "Activate" button
    ]);
    const badgeAfterActivate = await page.locator(`tr:has-text("${SUPPLIER_FIXTURE_NAME}") .badge`).first().textContent().catch(() => '');
    check('Activate: badge flips back to Active', /^\s*active\s*$/i.test(badgeAfterActivate || ''), `badge: "${badgeAfterActivate}"`);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'suppliers-admin-crud-final.png'), fullPage: true });

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Deactivated suppliers must disappear from the Purchase Order "Supplier"
// dropdown (PurchaseOrderController uses listActiveSuppliers()) but a
// supplier record itself is never hard-deleted (no delete route exists —
// DATA-01 soft-delete-only rule, verified structurally, not just by UI).
// ---------------------------------------------------------------------
async function checkDeactivatedSupplierHiddenFromPoDropdown(browser, supplierName) {
    console.log('\n=== Admin: deactivated supplier disappears from PO "Supplier" dropdown ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // Deactivate the test supplier created earlier.
    await searchSuppliers(page, supplierName);
    const isAlreadyInactive = /inactive/i.test((await page.locator('.badge').first().textContent().catch(() => '')) || '');
    if (!isAlreadyInactive) {
        await page.click('.row-actions__trigger');
        await Promise.all([
            page.waitForResponse((r) => /\/suppliers\/.+\/deactivate$/.test(r.url())),
            page.click('.row-actions__item--destructive'),
        ]);
        await page.waitForLoadState('networkidle');
    }

    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    const optionCount = await page.locator(`#supplier_id option:has-text("${supplierName}")`).count();
    check('Deactivated supplier is absent from the PO create form\'s Supplier dropdown', optionCount === 0, `matching options found: ${optionCount}`);

    // Clean up: reactivate so the record is left in its original (active) state.
    await searchSuppliers(page, supplierName);
    await page.click('.row-actions__trigger');
    await Promise.all([
        page.waitForResponse((r) => /\/suppliers\/.+\/activate$/.test(r.url())),
        page.click('.row-actions__item--activate'),
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
        await checkAdminCrud(browser);
        await checkDeactivatedSupplierHiddenFromPoDropdown(browser, SUPPLIER_FIXTURE_NAME);
    } finally {
        await browser.close();
    }
    console.log(`\nDONE — suppliers checked. PASS=${pass} FAIL=${fail}`);
    process.exitCode = fail > 0 ? 1 : 0;
})();

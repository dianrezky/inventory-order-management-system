// Ad-hoc Playwright QA script for the Users menu (/users).
// Checks against PROJECT_REFERENCE.md ALUR-01 ("Admin dapat membuat,
// mengedit, dan menonaktifkan user"), AUTH-01 (deactivated-user session
// invalidation, live role sync), and the §1.2 role matrix (Mengelola user:
// Admin only — no view-only mode for Sales/WarehouseStaff, unlike master
// data).
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const TEST_USER_NAME = 'QA Playwright User';
const TEST_USER_EMAIL = 'qa-playwright-user@example.com';
const TEST_USER_PASSWORD = 'qapass123';

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
// Unauthenticated access must redirect to /login.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    check('GET /users without session redirects to /login', page.url().includes('/login'), `landed on ${page.url()}`);
    await context.close();
}

// ---------------------------------------------------------------------
// Sales/WarehouseStaff must be rejected on every single /users endpoint —
// there is no view-only mode here (unlike Products/Warehouses/Customers/
// Suppliers), matching Purchase Orders' and Stock Ledger's pattern:
// indexAction()/showAction() are gated by requirePermission('users.manage'),
// not requireAuth().
// ---------------------------------------------------------------------
async function checkNonAdminFullyBlocked(browser, { role, email, password }) {
    console.log(`\n=== ${role}: fully blocked from /users ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);

    const indexResp = await page.request.get(`${BASE_URL}/users`, { maxRedirects: 0 });
    check(`GET /users rejected (403) for ${role}`, indexResp.status() === 403, `got ${indexResp.status()}`);

    const createFormResp = await page.request.get(`${BASE_URL}/users/create`, { maxRedirects: 0 });
    check(`GET /users/create rejected (403) for ${role}`, createFormResp.status() === 403, `got ${createFormResp.status()}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Admin: full CRUD flow — create (with password/role/duplicate-email
// validation), edit (regression check for the obfuscated-id bug fixed
// 2026-09-24), deactivate/reactivate.
// ---------------------------------------------------------------------
async function checkAdminCrud(browser) {
    console.log('\n=== Admin: CRUD flow ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // Filter fields present.
    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    check('Filter fields present (Name, Email, Role, Status)',
        (await page.locator('#user-name').count()) > 0
        && (await page.locator('#user-email').count()) > 0
        && (await page.locator('#user-role-wrapper').count()) > 0
        && (await page.locator('#user-status-wrapper').count()) > 0);

    // Reuse the QA test user if a previous run left it behind.
    await page.fill('#user-name', TEST_USER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    const existingRow = await page.locator('table.table tbody tr').count();

    if (existingRow === 0) {
        await page.goto(`${BASE_URL}/users/create`, { waitUntil: 'networkidle' });
        await page.fill('#name', TEST_USER_NAME);
        await page.fill('#email', TEST_USER_EMAIL);
        await page.selectOption('#role', 'Sales');
        await page.fill('#password', TEST_USER_PASSWORD);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
        check('Create user redirects back to /users', page.url() === `${BASE_URL}/users` || page.url() === `${BASE_URL}/users/`, `landed on ${page.url()}`);
    } else {
        note('Reusing existing QA test user from a previous run.');
    }

    // Duplicate email is rejected (reuse a known seeded email).
    await page.goto(`${BASE_URL}/users/create`, { waitUntil: 'networkidle' });
    await page.fill('#name', 'Duplicate Email Attempt');
    await page.fill('#email', 'admin@example.com');
    await page.selectOption('#role', 'Sales');
    await page.fill('#password', 'somepassword');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
    const dupError = await page.locator('.alert--error').textContent().catch(() => '');
    check('Duplicate email is rejected with a validation message', /already in use/i.test(dupError || ''), `message: "${dupError}"`);

    // Password too short is rejected.
    await page.goto(`${BASE_URL}/users/create`, { waitUntil: 'networkidle' });
    await page.fill('#name', 'Short Password Attempt');
    await page.fill('#email', 'short-password-attempt@example.com');
    await page.selectOption('#role', 'Sales');
    await page.fill('#password', '123');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Save")')]);
    const pwError = await page.locator('.alert--error').textContent().catch(() => '');
    check('Password shorter than 6 characters is rejected', /at least 6 characters/i.test(pwError || ''), `message: "${pwError}"`);

    // Edit the QA test user (rename + verify the obfuscated-id form-action fix).
    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    await page.fill('#user-name', TEST_USER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    await page.locator('.row-actions__trigger').first().click();
    await page.waitForTimeout(150);
    const editHref = await page.locator('.row-actions__item--edit').first().getAttribute('href');
    check('Edit link uses obfuscated id, not raw numeric id', editHref !== null && !/\/users\/\d+\/edit/.test(editHref), `href="${editHref}"`);
    await page.goto(`${BASE_URL}${editHref}`, { waitUntil: 'networkidle' });
    const formAction = await page.locator('form').first().getAttribute('action');
    check(
        'Edit form action uses obfuscated id, not raw numeric id (regression: this was broken — editing any user 404d)',
        formAction !== null && !/\/users\/\d+\/update/.test(formAction),
        `action="${formAction}"`
    );
    const renamedTo = TEST_USER_NAME + ' (Edited)';
    await page.fill('#name', renamedTo);
    const saveResp = await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button:has-text("Save")'),
    ]).then(([resp]) => resp);
    check('Saving the edit form succeeds (not a 404)', saveResp.status() === 200, `got ${saveResp.status()} at ${saveResp.url()}`);

    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    await page.fill('#user-name', TEST_USER_NAME);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    const rowText = await page.locator('table.table tbody tr').first().textContent();
    check('Edited user name is reflected in the list', (rowText || '').includes(renamedTo), `row text: "${rowText}"`);

    // Deactivate / reactivate round-trip (state-agnostic: a previous
    // interrupted run may have left the row in either state).
    async function reapplyTestUserFilter() {
        try {
            await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
        } catch (_) {
            await page.waitForTimeout(500);
            await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
        }
        await page.fill('#user-name', TEST_USER_NAME);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button:has-text("Search")')]);
    }

    async function toggleStatus() {
        await page.locator('.row-actions__trigger').first().click();
        await page.waitForTimeout(150);
        const toggleBtn = page.locator('.row-actions__item--destructive, .row-actions__item--activate').first();
        await toggleBtn.waitFor({ state: 'visible', timeout: 5000 });
        await toggleBtn.click();
        await page.waitForTimeout(2000);
        await reapplyTestUserFilter();

        return (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    }

    await reapplyTestUserFilter();
    const statusBefore = (await page.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    const statusAfterFirstToggle = await toggleStatus();
    const expectedAfterFirstToggle = statusBefore === 'Active' ? 'Inactive' : 'Active';
    check(`Toggle flips status ${statusBefore} -> ${expectedAfterFirstToggle}`, statusAfterFirstToggle === expectedAfterFirstToggle, `got "${statusAfterFirstToggle}"`);

    const statusAfterSecondToggle = await toggleStatus();
    check(`Toggle flips status back ${expectedAfterFirstToggle} -> ${statusBefore}`, statusAfterSecondToggle === statusBefore, `got "${statusAfterSecondToggle}"`);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'users-admin-crud.png'), fullPage: true });
    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// AUTH-01: deactivating a logged-in user must invalidate their session
// immediately — they should be bounced back to /login on their very next
// request, not stay logged in until the session naturally expires.
// ---------------------------------------------------------------------
async function checkDeactivationInvalidatesSession(browser) {
    console.log('\n=== AUTH-01: deactivation invalidates an active session ===');

    // Ensure the QA test user exists and is Active before logging in as them.
    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');
    await adminPage.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    await adminPage.fill('#user-name', TEST_USER_NAME);
    await Promise.all([adminPage.waitForNavigation({ waitUntil: 'networkidle' }), adminPage.click('button:has-text("Search")')]);
    let badge = (await adminPage.locator('table.table tbody tr').first().locator('.badge').textContent() || '').trim();
    if (badge !== 'Active') {
        await adminPage.locator('.row-actions__trigger').first().click();
        await adminPage.waitForTimeout(150);
        await adminPage.locator('.row-actions__item--activate').first().click();
        await adminPage.waitForTimeout(2000);
        await adminPage.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
        await adminPage.fill('#user-name', TEST_USER_NAME);
        await Promise.all([adminPage.waitForNavigation({ waitUntil: 'networkidle' }), adminPage.click('button:has-text("Search")')]);
    }

    // Log in as the QA test user in a separate session.
    const testUserContext = await browser.newContext();
    const testUserPage = await testUserContext.newPage();
    await login(testUserPage, TEST_USER_EMAIL, TEST_USER_PASSWORD);
    const preDeactivateResp = await testUserPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
    check('QA test user can reach /dashboard while Active', preDeactivateResp.status() === 200, `got ${preDeactivateResp.status()}`);

    // Admin deactivates them while their session is still live.
    await adminPage.locator('.row-actions__trigger').first().click();
    await adminPage.waitForTimeout(150);
    await adminPage.locator('.row-actions__item--destructive').first().click();
    await adminPage.waitForTimeout(2000);
    await logout(adminPage);
    await adminContext.close();

    // The already-logged-in test user's NEXT request must bounce to /login.
    await testUserPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
    check("Deactivated user's next request is redirected to /login (session invalidated)", testUserPage.url().includes('/login'), `landed on ${testUserPage.url()}`);

    await testUserContext.close();

    // Cleanup: reactivate the QA test user so it's Active for the next run
    // (matches checkAdminCrud()'s idempotent-reuse assumption).
    const cleanupContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const cleanupPage = await cleanupContext.newPage();
    await login(cleanupPage, 'admin@example.com', 'admin123');
    await cleanupPage.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    await cleanupPage.fill('#user-name', TEST_USER_NAME);
    await Promise.all([cleanupPage.waitForNavigation({ waitUntil: 'networkidle' }), cleanupPage.click('button:has-text("Search")')]);
    await cleanupPage.locator('.row-actions__trigger').first().click();
    await cleanupPage.waitForTimeout(150);
    await cleanupPage.locator('.row-actions__item--activate').first().click();
    await cleanupPage.waitForTimeout(1500);
    await logout(cleanupPage);
    await cleanupContext.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);

        await checkNonAdminFullyBlocked(browser, { role: 'Sales', email: 'sales1@example.com', password: 'sales123' });
        await checkNonAdminFullyBlocked(browser, { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123' });

        await checkAdminCrud(browser);
        await checkDeactivationInvalidatesSession(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

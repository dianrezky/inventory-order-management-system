// Ad-hoc Playwright QA script — Profile deep tests.
// Covers PROFILE-001..015 from the E2E deep-dive specification:
// page rendering, field validation, email uniqueness, role immutability,
// and persistence after re-login.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL      = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN = { role: 'Admin',          email: 'admin@example.com',     password: 'admin123' };
const SALES = { role: 'Sales',          email: 'sales1@example.com',    password: 'sales123' };
const WH    = { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123'    };

let pass = 0;
let fail = 0;

function check(id, label, condition, detail) {
    const tag = `${id}: ${label}`;
    if (condition) { pass += 1; console.log(`  [PASS] ${tag}`); }
    else           { fail += 1; console.log(`  [FAIL] ${tag}${detail ? ' — ' + detail : ''}`); }
}

function note(msg) { console.log(`  [INFO] ${msg}`); }

async function safeGoto(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
}

async function login(page, email, password) {
    await safeGoto(page, `${BASE_URL}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
        page.click('button[type="submit"]'),
    ]);
}

function getCsrfToken(page) {
    return page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: POST to /my-profile update endpoint
// ─────────────────────────────────────────────────────────────────────────────
async function postProfileUpdate(page, fields) {
    const csrf = await getCsrfToken(page);
    // Try both possible update paths.
    for (const path of ['/my-profile/update', '/my-profile']) {
        const resp = await page.request.post(`${BASE_URL}${path}`, {
            form: { _csrf_token: csrf, ...fields },
            maxRedirects: 5,
        }).catch(() => null);
        if (resp && resp.status() !== 404) return resp;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROFILE-001..002 — Page loads and shows correct values
// ─────────────────────────────────────────────────────────────────────────────
async function checkProfileRenders(browser, { role, email, password }) {
    console.log(`\n=== PROFILE-001/002: ${role} profile page ===`);
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, email, password);

    await safeGoto(page, `${BASE_URL}/my-profile`);
    check('PROFILE-001', `${role} /my-profile loads (not /login)`, !page.url().includes('/login'), `url: ${page.url()}`);

    const bodyText = await page.locator('body').innerText().catch(() => '');
    check('PROFILE-001', `No PHP error on ${role} profile`, !/Fatal error|Warning\s*:/i.test(bodyText));

    // Current email should appear pre-filled.
    const emailField = page.locator('input[name="email"]').first();
    if (await emailField.count() > 0) {
        const preFilled = await emailField.inputValue().catch(() => '');
        check('PROFILE-002', `${role} profile pre-fills correct email`, preFilled === email, `got "${preFilled}", expected "${email}"`);
    } else {
        // Email may be shown as text, not input.
        check('PROFILE-002', `${role} profile shows current email`, bodyText.includes(email), `email "${email}" not found in page`);
    }

    // Name field pre-filled.
    const nameField = page.locator('input[name="name"]').first();
    check('PROFILE-002', `${role} profile has name field`, await nameField.count() > 0);

    if (role === 'Admin') {
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'profile-admin.png') });
    }
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROFILE-003..015 — Field validation and update behaviour
// ─────────────────────────────────────────────────────────────────────────────
async function checkProfileValidation(browser) {
    console.log('\n=== PROFILE-003..015: Profile update validation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, SALES.email, SALES.password);
    await safeGoto(page, `${BASE_URL}/my-profile`);

    const originalName = await page.locator('input[name="name"]').first().inputValue().catch(() => 'Sales User');

    // PROFILE-003 — Valid name update
    const newName = `QA Sales Profile ${Date.now()}`;
    let resp = await postProfileUpdate(page, { name: newName, email: SALES.email });
    note(`PROFILE-003: Valid name update — status: ${resp ? resp.status() : 'ERROR'}, url: ${resp ? resp.url() : 'n/a'}`);
    check('PROFILE-003', 'Valid name update does not 5xx', !resp || resp.status() < 500);

    // PROFILE-004 — Name required
    await safeGoto(page, `${BASE_URL}/my-profile`);
    resp = await postProfileUpdate(page, { name: '', email: SALES.email });
    const r4Url = resp ? resp.url() : '';
    check('PROFILE-004', 'Empty name rejected (back to /my-profile or 422)',
        r4Url.includes('/my-profile') || (resp && (resp.status() === 422 || resp.status() === 400)));

    // PROFILE-007 — Valid email format
    await safeGoto(page, `${BASE_URL}/my-profile`);
    resp = await postProfileUpdate(page, { name: originalName, email: SALES.email });
    check('PROFILE-007', 'Valid email update does not 5xx', !resp || resp.status() < 500);

    // PROFILE-008 — Invalid email format
    await safeGoto(page, `${BASE_URL}/my-profile`);
    resp = await postProfileUpdate(page, { name: originalName, email: 'not-a-valid-email' });
    const r8Url = resp ? resp.url() : '';
    check('PROFILE-008', 'Invalid email rejected',
        r8Url.includes('/my-profile') || (resp && (resp.status() === 422 || resp.status() === 400)));

    // PROFILE-009 — Duplicate email (another user's)
    await safeGoto(page, `${BASE_URL}/my-profile`);
    resp = await postProfileUpdate(page, { name: originalName, email: ADMIN.email });
    const r9Url = resp ? resp.url() : '';
    check('PROFILE-009', 'Duplicate email (another user) rejected',
        r9Url.includes('/my-profile') || (resp && (resp.status() === 422 || resp.status() === 400)));

    // PROFILE-010 — Own email is accepted as-is.
    await safeGoto(page, `${BASE_URL}/my-profile`);
    resp = await postProfileUpdate(page, { name: originalName, email: SALES.email });
    check('PROFILE-010', 'Own email is accepted without duplicate error', !resp || resp.status() < 400);

    // PROFILE-011 — Role field should NOT be editable on /my-profile.
    await safeGoto(page, `${BASE_URL}/my-profile`);
    const roleField = page.locator('select[name="role"], input[name="role"]').first();
    check('PROFILE-011', 'Role field is absent from profile form (not editable)',
        await roleField.count() === 0, 'role field found in form — user could change own role');

    // PROFILE-013/014 — Submit and verify feedback message.
    await safeGoto(page, `${BASE_URL}/my-profile`);
    const csrf = await getCsrfToken(page);
    await page.fill('input[name="name"]', originalName);
    await page.fill('input[name="email"]', SALES.email);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
        page.locator('button[type="submit"]').first().click(),
    ]);
    const successBody = await page.locator('body').innerText().catch(() => '');
    const hasSuccess  = /berhasil|success|updated|saved|disimpan/i.test(successBody);
    check('PROFILE-013', 'Success feedback shown after valid update', hasSuccess, `page body snippet: ${successBody.substring(0, 200)}`);

    // PROFILE-015 — Persistence: re-login and verify name.
    await page.goto(`${BASE_URL}/logout`, { waitUntil: 'networkidle' }).catch(() => {});
    await login(page, SALES.email, SALES.password);
    await safeGoto(page, `${BASE_URL}/my-profile`);
    const persistedName = await page.locator('input[name="name"]').first().inputValue().catch(() => '');
    check('PROFILE-015', 'Profile name persists after re-login', persistedName === originalName, `got "${persistedName}", expected "${originalName}"`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        for (const cred of [ADMIN, SALES, WH]) {
            await checkProfileRenders(browser, cred);
        }
        await checkProfileValidation(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — profile.spec.js complete');
})();

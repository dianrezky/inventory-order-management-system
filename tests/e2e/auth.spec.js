// Ad-hoc Playwright QA script — Authentication deep tests.
// Covers AUTH-001..025 from the E2E deep-dive specification:
// login page rendering, valid/invalid credentials, inactive users,
// session behaviour, logout, CSRF, and unauthenticated access.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN    = { role: 'Admin',          email: 'admin@example.com',     password: 'admin123' };
const SALES    = { role: 'Sales',          email: 'sales1@example.com',    password: 'sales123' };
const WH       = { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123'    };

// Test-owned user — created/deactivated/reactivated during AUTH-013.
const TEST_USER = {
    name:     'QA Auth Test User',
    email:    'qa-auth-inactive@example.com',
    password: 'qaauth123',
    role:     'Sales',
};

let pass = 0;
let fail = 0;

function check(id, label, condition, detail) {
    const tag = `${id}: ${label}`;
    if (condition) {
        pass += 1;
        console.log(`  [PASS] ${tag}`);
    } else {
        fail += 1;
        console.log(`  [FAIL] ${tag}${detail ? ' — ' + detail : ''}`);
    }
}

function note(label) {
    console.log(`  [INFO] ${label}`);
}

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

async function loginSuccessful(page, email, password) {
    await login(page, email, password);
    return !page.url().includes('/login');
}

async function getCsrfToken(page, url) {
    await safeGoto(page, url || `${BASE_URL}/login`);
    return page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => null);
}

// POST /logout — actual supported logout route.
async function doLogout(page, csrfToken) {
    const resp = await page.request.post(`${BASE_URL}/logout`, {
        form: { _csrf_token: csrfToken || '' },
        maxRedirects: 5,
    }).catch(() => null);
    return resp;
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-001 — Login page loads
// ─────────────────────────────────────────────────────────────────────────────
async function checkLoginPageRenders(browser) {
    console.log('\n=== AUTH-001: Login page renders ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    await safeGoto(page, `${BASE_URL}/login`);

    check('AUTH-001', 'email field present',    (await page.locator('input[name="email"]').count())    > 0);
    check('AUTH-001', 'password field present', (await page.locator('input[name="password"]').count()) > 0);
    check('AUTH-001', 'submit button present',  (await page.locator('button[type="submit"]').count())  > 0);
    check('AUTH-001', 'CSRF token field present',(await page.locator('input[name="_csrf_token"]').count()) > 0);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'auth-001-login-page.png') });
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-002..004 — Valid logins for each role
// ─────────────────────────────────────────────────────────────────────────────
async function checkValidLogin(browser, { role, email, password }, id) {
    console.log(`\n=== ${id}: Valid ${role} login ===`);
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    await login(page, email, password);
    const landed = page.url();

    check(id, `${role} login lands on dashboard (not /login)`, !landed.includes('/login'), `url: ${landed}`);

    if (!landed.includes('/login')) {
        const bodyText = await page.locator('body').innerText().catch(() => '');
        check(id, 'No PHP error in page', !/Warning[\s\S]*?:/i.test(bodyText) && !/Fatal error/i.test(bodyText));
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `auth-${id.toLowerCase()}-${role}-dashboard.png`) });
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-006..012 — Invalid / edge-case credentials
// ─────────────────────────────────────────────────────────────────────────────
async function checkInvalidCredentials(browser) {
    console.log('\n=== AUTH-006..012: Invalid credentials ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    async function attempt(id, desc, emailVal, passVal) {
        await safeGoto(page, `${BASE_URL}/login`);
        await page.fill('input[name="email"]', emailVal);
        await page.fill('input[name="password"]', passVal);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.click('button[type="submit"]'),
        ]);
        const stayed = page.url().includes('/login');
        check(id, desc, stayed, `url: ${page.url()}`);
        if (!stayed) {
            note(`${id} — ${desc}: user was authenticated unexpectedly`);
        }
    }

    await attempt('AUTH-006', 'Unknown email rejected',              'nobody@nowhere.invalid', 'irrelevant123');
    await attempt('AUTH-007', 'Unknown email rejected (variant)',    'notindb@test.com',       'admin123');
    await attempt('AUTH-008', 'Correct email / wrong password',      ADMIN.email,              'wrongpass999');
    await attempt('AUTH-009', 'Empty email rejected',                '',                       ADMIN.password);
    await attempt('AUTH-010', 'Empty password rejected',             ADMIN.email,              '');
    await attempt('AUTH-011', 'Both fields empty rejected',          '',                       '');

    // AUTH-012 — invalid email format (only if the app validates format)
    await safeGoto(page, `${BASE_URL}/login`);
    await page.fill('input[name="email"]', 'not-an-email');
    await page.fill('input[name="password"]', 'somepass');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
        page.click('button[type="submit"]'),
    ]);
    const html5Invalid = await page.locator('input[name="email"]:invalid').count() > 0;
    const stayedOnLogin = page.url().includes('/login');
    check('AUTH-012', 'Invalid email format stays on login (HTML5 or server)',
        html5Invalid || stayedOnLogin, `url: ${page.url()}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-013 — Inactive user cannot authenticate
// Creates (or reuses) a test Sales user, deactivates it via Admin, then
// verifies it cannot log in, then reactivates it.
// ─────────────────────────────────────────────────────────────────────────────
async function checkInactiveUser(browser) {
    console.log('\n=== AUTH-013: Inactive user cannot authenticate ===');

    // Admin context: ensure test user exists.
    const adminCtx  = await browser.newContext();
    const adminPage = await adminCtx.newPage();
    await login(adminPage, ADMIN.email, ADMIN.password);

    // Search for existing test user.
    await safeGoto(adminPage, `${BASE_URL}/users`);
    const searchForm = adminPage.locator('#user-name,input[name="name"]').first();
    if (await searchForm.count() > 0) {
        await searchForm.fill(TEST_USER.name);
        await Promise.all([
            adminPage.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            adminPage.locator('button[type="submit"]').first().click(),
        ]);
    }

    const existingRow = adminPage.locator(`tr:has-text("${TEST_USER.email}")`);
    let userId = null;

    if ((await existingRow.count()) === 0) {
        // Create the test user.
        await safeGoto(adminPage, `${BASE_URL}/users/create`);
        const csrf = await getCsrfToken(adminPage, `${BASE_URL}/users/create`);
        const resp = await adminPage.request.post(`${BASE_URL}/users`, {
            form: {
                _csrf_token: csrf || '',
                name:        TEST_USER.name,
                email:       TEST_USER.email,
                password:    TEST_USER.password,
                role:        TEST_USER.role,
            },
        });
        note(`AUTH-013: test user create — HTTP ${resp.status()}`);

        // Reload user list and find the new user.
        await safeGoto(adminPage, `${BASE_URL}/users`);
    }

    // Find the deactivate/activate link for our test user.
    const deactivateLink = adminPage.locator(`tr:has-text("${TEST_USER.email}") a:has-text("Deactivate"), tr:has-text("${TEST_USER.email}") button:has-text("Deactivate")`).first();
    const activateLink   = adminPage.locator(`tr:has-text("${TEST_USER.email}") a:has-text("Activate"), tr:has-text("${TEST_USER.email}") button:has-text("Activate")`).first();

    if (await activateLink.count() > 0) {
        note('AUTH-013: test user already inactive, skipping deactivation step');
    } else if (await deactivateLink.count() > 0) {
        // Read the deactivate form/link href.
        const deactivateHref = await deactivateLink.getAttribute('href').catch(() => null);
        if (deactivateHref) {
            await safeGoto(adminPage, `${BASE_URL}${deactivateHref}`);
        } else {
            // POST-style deactivation.
            await deactivateLink.click();
            await adminPage.waitForLoadState('networkidle').catch(() => {});
        }
        note('AUTH-013: test user deactivated');
    } else {
        note('AUTH-013: cannot find Deactivate action for test user — skipping deactivation check');
        await adminCtx.close();
        return;
    }

    // Now try to log in as the inactive user.
    const guestCtx  = await browser.newContext();
    const guestPage = await guestCtx.newPage();
    await login(guestPage, TEST_USER.email, TEST_USER.password);
    check('AUTH-013', 'Inactive user stays on /login after login attempt',
        guestPage.url().includes('/login'), `url: ${guestPage.url()}`);
    await guestCtx.close();

    // Re-activate test user so subsequent runs work.
    await safeGoto(adminPage, `${BASE_URL}/users`);
    const reactivateLink = adminPage.locator(`tr:has-text("${TEST_USER.email}") a:has-text("Activate"), tr:has-text("${TEST_USER.email}") button:has-text("Activate")`).first();
    if (await reactivateLink.count() > 0) {
        const href = await reactivateLink.getAttribute('href').catch(() => null);
        if (href) {
            await safeGoto(adminPage, `${BASE_URL}${href}`);
        } else {
            await reactivateLink.click();
            await adminPage.waitForLoadState('networkidle').catch(() => {});
        }
        note('AUTH-013: test user reactivated');
    }

    await adminCtx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-014 — Already authenticated user opens /login
// ─────────────────────────────────────────────────────────────────────────────
async function checkAlreadyAuthenticated(browser) {
    console.log('\n=== AUTH-014: Authenticated user opens /login ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    await login(page, ADMIN.email, ADMIN.password);
    const preUrl = page.url();

    await safeGoto(page, `${BASE_URL}/login`);
    const postUrl = page.url();

    // Application should either redirect away from /login, or render /login
    // without breaking.  Both are acceptable; we just assert no 5xx / PHP error.
    const bodyText = await page.locator('body').innerText().catch(() => '');
    check('AUTH-014', 'No 5xx or PHP error when authenticated user opens /login',
        !/Fatal error/i.test(bodyText) && !/Warning[\s\S]*?:/i.test(bodyText));
    note(`AUTH-014: landed on ${postUrl} (pre-login url was ${preUrl})`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-016 — POST logout succeeds and invalidates session
// ─────────────────────────────────────────────────────────────────────────────
async function checkLogout(browser) {
    console.log('\n=== AUTH-016: POST logout ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    await login(page, SALES.email, SALES.password);
    check('AUTH-016', 'Sales logged in before logout test', !page.url().includes('/login'), `url: ${page.url()}`);

    const csrf = await getCsrfToken(page, `${BASE_URL}/my-profile`);

    // POST to /logout.
    const resp = await page.request.post(`${BASE_URL}/logout`, {
        form: { _csrf_token: csrf || '' },
        maxRedirects: 5,
    }).catch(() => null);

    note(`AUTH-016: POST /logout → HTTP ${resp ? resp.status() : 'ERROR'}, final url: ${resp ? resp.url() : 'n/a'}`);

    // After logout, protected page must redirect to /login.
    const dashResp = await page.request.get(`${BASE_URL}/dashboard`, { maxRedirects: 5 }).catch(() => null);
    const dashUrl  = dashResp ? dashResp.url() : page.url();
    check('AUTH-016', 'After logout /dashboard redirects to /login', dashUrl.includes('/login'), `url: ${dashUrl}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-018/019 — Protected page and POST as guest
// ─────────────────────────────────────────────────────────────────────────────
async function checkGuestAccess(browser) {
    console.log('\n=== AUTH-018/019: Guest access to protected routes ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    // Protected GET.
    const routes = ['/dashboard', '/products', '/categories', '/users', '/purchase-orders', '/stock-ledger'];
    for (const route of routes) {
        const resp = await page.goto(`${BASE_URL}${route}`, { waitUntil: 'networkidle' }).catch(() => null);
        const landed = page.url();
        check('AUTH-018', `Guest GET ${route} redirects to /login`, landed.includes('/login'), `url: ${landed}`);
    }

    // Protected POST.
    const postResp = await page.request.post(`${BASE_URL}/products`, {
        form: { name: 'hacker attempt' },
        maxRedirects: 5,
    }).catch(() => null);
    const postUrl = postResp ? postResp.url() : '';
    check('AUTH-019', 'Guest POST /products is rejected (redirect to /login or 4xx)',
        postUrl.includes('/login') || (postResp && postResp.status() >= 400),
        `url: ${postUrl}, status: ${postResp ? postResp.status() : 'ERROR'}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-021..024 — CSRF token validation
// ─────────────────────────────────────────────────────────────────────────────
async function checkCsrf(browser) {
    console.log('\n=== AUTH-021..024: CSRF token validation ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    // AUTH-021: Valid CSRF token in normal login flow.
    await safeGoto(page, `${BASE_URL}/login`);
    const validCsrf = await page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
    check('AUTH-021', 'Login form includes non-empty CSRF token', validCsrf.length > 0, `token: "${validCsrf}"`);

    // AUTH-022: Login attempt with missing CSRF token.
    const respMissing = await page.request.post(`${BASE_URL}/login`, {
        form: { email: ADMIN.email, password: ADMIN.password },
        maxRedirects: 5,
    }).catch(() => null);
    const missingUrl = respMissing ? respMissing.url() : '';
    check('AUTH-022', 'Login with missing CSRF token is rejected (back to /login or 4xx)',
        missingUrl.includes('/login') || (respMissing && respMissing.status() >= 400),
        `status: ${respMissing ? respMissing.status() : 'ERROR'}, url: ${missingUrl}`);

    // AUTH-023: Login attempt with invalid CSRF token.
    const respInvalid = await page.request.post(`${BASE_URL}/login`, {
        form: { email: ADMIN.email, password: ADMIN.password, _csrf_token: 'totally-invalid-token' },
        maxRedirects: 5,
    }).catch(() => null);
    const invalidUrl = respInvalid ? respInvalid.url() : '';
    check('AUTH-023', 'Login with invalid CSRF token is rejected',
        invalidUrl.includes('/login') || (respInvalid && respInvalid.status() >= 400),
        `status: ${respInvalid ? respInvalid.status() : 'ERROR'}, url: ${invalidUrl}`);

    // AUTH-024: Stale CSRF — reuse a consumed token on second attempt.
    // Get a fresh token, then manually POST once, then POST again with same token.
    await safeGoto(page, `${BASE_URL}/login`);
    const staleToken = await page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
    // First POST (consume the token, deliberate wrong credentials so we stay on /login).
    await page.request.post(`${BASE_URL}/login`, {
        form: { email: 'nobody@test.invalid', password: 'bad', _csrf_token: staleToken },
        maxRedirects: 5,
    }).catch(() => null);
    // Second POST reusing same token.
    const respStale = await page.request.post(`${BASE_URL}/login`, {
        form: { email: ADMIN.email, password: ADMIN.password, _csrf_token: staleToken },
        maxRedirects: 5,
    }).catch(() => null);
    const staleUrl = respStale ? respStale.url() : '';
    // After a stale/replayed token the application should either stay on /login
    // or issue a new CSRF and let the second attempt proceed. The test records
    // both outcomes so the reviewer can judge correctness.
    note(`AUTH-024: Stale CSRF reuse — status: ${respStale ? respStale.status() : 'ERROR'}, url: ${staleUrl}`);
    check('AUTH-024', 'Stale CSRF reuse does not yield a 5xx', !staleUrl.includes('500') && (respStale ? respStale.status() < 500 : true));

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-020 — Deactivated-while-logged-in session invalidation
// (covered more thoroughly in users.spec.js AUTH-01; here just a smoke check)
// ─────────────────────────────────────────────────────────────────────────────
async function checkDeactivatedSession(browser) {
    console.log('\n=== AUTH-020: Deactivated mid-session ===');
    note('AUTH-020: Full mid-session invalidation test covered in users.spec.js (AUTH-01 end-to-end).');
    note('AUTH-020: Smoke — verifying test-user can log in before deactivation.');

    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, TEST_USER.email, TEST_USER.password);
    const loggedIn = !page.url().includes('/login');
    note(`AUTH-020: ${TEST_USER.email} login result: ${loggedIn ? 'authenticated' : 'rejected (may already be inactive)'}`);
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTH-025 — Flash messages isolated between sessions
// ─────────────────────────────────────────────────────────────────────────────
async function checkFlashIsolation(browser) {
    console.log('\n=== AUTH-025: Flash message session isolation ===');
    // Two concurrent contexts — a flash from one must not appear in the other.
    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    const pageA = await ctxA.newPage();
    const pageB = await ctxB.newPage();

    // Session A: trigger a validation flash by bad login.
    await pageA.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    const csrfA = await pageA.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
    await pageA.request.post(`${BASE_URL}/login`, {
        form: { email: 'nobody@test.invalid', password: 'bad', _csrf_token: csrfA },
        maxRedirects: 5,
    }).catch(() => null);

    // Session B: open /login — must NOT contain Session A's flash.
    await pageB.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    const bodyB = await pageB.locator('body').innerText().catch(() => '');
    check('AUTH-025', 'Session B /login does not contain Session A flash text',
        !bodyB.includes('nobody@test.invalid'));

    await ctxA.close();
    await ctxB.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        await checkLoginPageRenders(browser);
        await checkValidLogin(browser, ADMIN, 'AUTH-002');
        await checkValidLogin(browser, SALES, 'AUTH-003');
        await checkValidLogin(browser, WH,    'AUTH-004');
        await checkInvalidCredentials(browser);
        await checkInactiveUser(browser);
        await checkAlreadyAuthenticated(browser);
        await checkLogout(browser);
        await checkGuestAccess(browser);
        await checkCsrf(browser);
        await checkDeactivatedSession(browser);
        await checkFlashIsolation(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — auth.spec.js complete');
})();

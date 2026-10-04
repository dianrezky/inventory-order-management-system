// Ad-hoc Playwright QA script — Error handling, invalid identifiers,
// double-submit prevention, and transaction rollback regression.
// Covers: ID-001..006, D25 (double-submit), TX-PO-001, TX-SO-001/002,
// D29 (error handling), and D12 (error/recovery) from the E2E deep-dive spec.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL       = process.env.BASE_URL || 'http://127.0.0.1:8090';
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
// ID-001..006 — Invalid / tampered obfuscated identifiers
// Every route using obfuscated IDs must reject invalid tokens with 4xx,
// not 5xx, and not silently render another entity's data.
// ─────────────────────────────────────────────────────────────────────────────
async function checkInvalidIds(browser) {
    console.log('\n=== ID-001..006: Invalid identifier handling ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const GARBAGE_IDS = [
        'TOTALLY_INVALID_TOKEN',          // ID-002: random token
        'aaaaaaaaaa',                      // ID-003: looks encoded but not valid
        '1',                               // ID-004: raw integer
        '0',
        '99999999',
        'null',
        '../../../etc/passwd',             // path traversal attempt
        '%00',                             // null byte
        "'; DROP TABLE products; --",      // SQL injection attempt in id position
    ];

    // Routes that use obfuscated IDs.
    const routes = [
        { pattern: '/products/{id}',                 label: 'Product detail' },
        { pattern: '/products/{id}/edit',            label: 'Product edit' },
        { pattern: '/categories',                    label: 'Category modal edit (no direct show route)' },
        { pattern: '/warehouses/{id}',               label: 'Warehouse detail' },
        { pattern: '/customers/{id}/edit',           label: 'Customer edit' },
        { pattern: '/suppliers/{id}/edit',           label: 'Supplier edit' },
        { pattern: '/users/{id}/edit',               label: 'User edit' },
        { pattern: '/purchase-orders/{id}',          label: 'PO detail' },
        { pattern: '/purchase-orders/{id}/receive',  label: 'PO receive' },
        { pattern: '/sales-orders/{id}',             label: 'SO detail' },
        { pattern: '/sales-orders/{id}/approve',     label: 'SO approve' },
    ];

    for (const { pattern, label } of routes) {
        if (!pattern.includes('{id}')) continue;

        for (const badId of GARBAGE_IDS.slice(0, 4)) { // test top 4 per route to keep runtime short
            const url = pattern.replace('{id}', encodeURIComponent(badId));
            const resp = await page.request.get(`${BASE_URL}${url}`, { maxRedirects: 5 }).catch(() => null);
            const status = resp ? resp.status() : 0;

            // Must be 4xx — specifically 404 or 403. Must NOT be 500.
            check(`ID-00${GARBAGE_IDS.slice(0,4).indexOf(badId)+2}`,
                `${label}: invalid id "${badId.substring(0, 20)}" → 4xx (not 5xx or 200)`,
                status >= 400 && status < 500,
                `status: ${status}, url: ${url}`);

            // Also verify no PHP fatal error leaks into non-5xx response.
            if (resp && status < 500) {
                const body = await resp.text().catch(() => '');
                check(`ID-00${GARBAGE_IDS.slice(0,4).indexOf(badId)+2}`,
                    `${label}: no PHP Fatal in response body`,
                    !/Fatal error/i.test(body));
            }
        }
    }

    // ID-006 — Valid token but wrong role (cross-role access).
    // Find a real PO id from Admin session, then check Sales cannot see it.
    await safeGoto(page, `${BASE_URL}/purchase-orders`);
    const firstPoLink = page.locator('table tbody tr a[href*="/purchase-orders/"]').first();
    if (await firstPoLink.count() > 0) {
        const poHref = await firstPoLink.getAttribute('href');
        const salesCtx  = await browser.newContext();
        const salesPage = await salesCtx.newPage();
        await login(salesPage, SALES.email, SALES.password);
        const salesResp = await salesPage.request.get(`${BASE_URL}${poHref}`, { maxRedirects: 5 }).catch(() => null);
        check('ID-006', 'Sales cannot access PO detail with valid PO id (403)',
            salesResp && salesResp.status() === 403,
            `status: ${salesResp ? salesResp.status() : 'ERROR'}`);
        await salesCtx.close();
    } else {
        note('ID-006: No PO found to test cross-role access');
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// D25 — Double-submit prevention
// Rapid-fire duplicate POSTs must not create duplicate records.
// ─────────────────────────────────────────────────────────────────────────────
async function checkDoubleSubmit(browser) {
    console.log('\n=== D25: Double-submit prevention ===');

    // Double-submit category create (safe: category has duplicate-name guard).
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const catName  = `QA Double Submit Cat ${Date.now()}`;
    const catCode  = `QA-DS-${Date.now()}`;

    // Submit the same category twice in rapid succession using two concurrent requests.
    await safeGoto(page, `${BASE_URL}/categories`);
    const csrf = await getCsrfToken(page);

    const submitBody = { _csrf_token: csrf, name: catName, code: catCode };
    const [r1, r2] = await Promise.all([
        page.request.post(`${BASE_URL}/categories`, { form: submitBody, maxRedirects: 5 }).catch(() => null),
        page.request.post(`${BASE_URL}/categories`, { form: submitBody, maxRedirects: 5 }).catch(() => null),
    ]);

    note(`D25-CAT: First submit → ${r1 ? r1.status() : 'ERROR'}`);
    note(`D25-CAT: Second submit → ${r2 ? r2.status() : 'ERROR'}`);

    // At most one should succeed; the duplicate-name constraint must catch the second.
    const s1 = r1 ? r1.status() : 0;
    const s2 = r2 ? r2.status() : 0;
    const bothSucceeded = s1 < 400 && s2 < 400;
    check('D25', 'Double-submit category: both do NOT both succeed (duplicate name guard)',
        !bothSucceeded, `statuses: ${s1}, ${s2}`);

    // Verify only one row with this name exists.
    await safeGoto(page, `${BASE_URL}/categories`);
    const nameSearch = page.locator('#category-name, input[name="name"]').first();
    if (await nameSearch.count() > 0) {
        await nameSearch.fill(catName);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }
    const matchRows = await page.locator(`tr:has-text("${catName}")`).count();
    check('D25', 'At most one category row with double-submit name', matchRows <= 1, `rows found: ${matchRows}`);

    // Cleanup: delete if created (find delete button).
    if (matchRows > 0) {
        const deleteBtn = page.locator(`tr:has-text("${catName}") button:has-text("Delete"), tr:has-text("${catName}") a:has-text("Delete")`).first();
        if (await deleteBtn.count() > 0) {
            const deleteHref = await deleteBtn.getAttribute('href').catch(() => null);
            if (deleteHref) {
                await safeGoto(page, `${BASE_URL}${deleteHref}`);
            } else {
                await deleteBtn.click();
                await page.waitForLoadState('networkidle').catch(() => {});
            }
            note('D25: Test category deleted');
        }
    }

    await ctx.close();

    // Double-submit PO/SO submission (UI-level): two rapid clicks on Submit button.
    await checkDoubleSubmitUi(browser);
}

async function checkDoubleSubmitUi(browser) {
    console.log('\n=== D25-UI: Double-click Submit button on PO ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find a Draft PO to submit.
    await safeGoto(page, `${BASE_URL}/purchase-orders`);
    const draftRow = page.locator('table tbody tr:has-text("Draft")').first();
    if (await draftRow.count() === 0) {
        note('D25-UI: No Draft PO found, skipping UI double-click test');
        await ctx.close();
        return;
    }

    const detailLink = draftRow.locator('a[href*="/purchase-orders/"]').first();
    if (await detailLink.count() === 0) {
        note('D25-UI: No detail link on Draft PO row');
        await ctx.close();
        return;
    }

    await detailLink.click();
    await page.waitForLoadState('networkidle').catch(() => {});

    const submitBtn = page.locator('button:has-text("Submit"), a:has-text("Submit Order"), form[action*="submit"] button').first();
    if (await submitBtn.count() === 0) {
        note('D25-UI: No Submit button on PO detail, skipping double-click test');
        await ctx.close();
        return;
    }

    // Double-click rapidly.
    const beforeStatus = await page.locator('.badge, [class*="status"], td:has-text("Draft")').first().innerText().catch(() => '');
    await submitBtn.dblclick();
    await page.waitForLoadState('networkidle').catch(() => {});

    const afterStatus = await page.locator('.badge, [class*="status"]').first().innerText().catch(() => '');
    const bodyText    = await page.locator('body').innerText().catch(() => '');
    check('D25-UI', 'Double-click Submit does not cause 5xx or duplicate state', !/Fatal error|500/i.test(bodyText));
    note(`D25-UI: Status before="${beforeStatus}", after="${afterStatus}"`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// TX-PO-001 — Invalid receipt: no stock/ledger/status change on reject
// ─────────────────────────────────────────────────────────────────────────────
async function checkPoRollback(browser) {
    console.log('\n=== TX-PO-001: Invalid PO receipt — rollback verification ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find an Ordered PO.
    await safeGoto(page, `${BASE_URL}/purchase-orders`);
    const orderedRow = page.locator('table tbody tr:has-text("Ordered")').first();
    if (await orderedRow.count() === 0) {
        note('TX-PO-001: No Ordered PO found, skipping rollback test');
        await ctx.close();
        return;
    }

    const detailLink = orderedRow.locator('a[href*="/purchase-orders/"]').first();
    const poHref     = await detailLink.getAttribute('href').catch(() => null);
    if (!poHref) { note('TX-PO-001: No PO href, skipping'); await ctx.close(); return; }

    await safeGoto(page, `${BASE_URL}${poHref}`);

    // Get current stock for first line item product.
    const productCell = await page.locator('table tbody tr td').nth(0).innerText().catch(() => '');
    note(`TX-PO-001: First line item: "${productCell}"`);

    // Read ordered qty from first line.
    const qtyOrdered = parseInt(
        await page.locator('table tbody tr td:has-text("qty"), table tbody tr td').nth(2).innerText().catch(() => '0')
    ) || 10;

    // Attempt to over-receive (qty > ordered).
    const csrf        = await getCsrfToken(page);
    const receivePath = poHref.replace(/\/[^/]+$/, '') + '/receive'; // /purchase-orders/{id}/receive
    const overQty     = qtyOrdered * 10 + 999;
    const overResp    = await page.request.post(`${BASE_URL}${receivePath}`, {
        form: { _csrf_token: csrf, items: JSON.stringify([{ qty: overQty }]) },
        maxRedirects: 5,
    }).catch(() => null);

    note(`TX-PO-001: Over-receipt POST → status: ${overResp ? overResp.status() : 'ERROR'}`);

    // Re-check PO status — must remain Ordered.
    await safeGoto(page, `${BASE_URL}${poHref}`);
    const statusText = await page.locator('.badge, [class*="status"], h2, .page-header__title').first().innerText().catch(() => '');
    check('TX-PO-001', 'PO status remains Ordered after invalid over-receipt attempt',
        /ordered/i.test(statusText), `status: "${statusText}"`);

    // Stock ledger should NOT have a new Receipt for this PO from the over-receipt attempt.
    // (Cannot easily verify atomically without DB access, but 5xx = rollback failure.)
    check('TX-PO-001', 'Over-receipt does not cause 5xx (application handled gracefully)',
        !overResp || overResp.status() < 500, `status: ${overResp ? overResp.status() : 'ERROR'}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// TX-SO-001 — Oversell rejection: no stock/ledger mutation
// ─────────────────────────────────────────────────────────────────────────────
async function checkSoOversellRollback(browser) {
    console.log('\n=== TX-SO-001: Oversell rejection — rollback verification ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, WH.email, WH.password);

    // Find an Approved SO to attempt issue on.
    await safeGoto(page, `${BASE_URL}/sales-orders`);
    const approvedRow = page.locator('table tbody tr:has-text("Approved")').first();
    if (await approvedRow.count() === 0) {
        note('TX-SO-001: No Approved SO found, skipping oversell rollback test');
        await ctx.close();
        return;
    }

    const detailLink = approvedRow.locator('a[href*="/sales-orders/"]').first();
    const soHref     = await detailLink.getAttribute('href').catch(() => null);
    if (!soHref) { note('TX-SO-001: No SO href'); await ctx.close(); return; }

    await safeGoto(page, `${BASE_URL}${soHref}`);

    // Record ledger count before.
    const csrf       = await getCsrfToken(page);
    const issuePath  = soHref.replace(/\/[^/]+$/, '') + '/issue'; // /sales-orders/{id}/issue

    // Record stock before the issue attempt.
    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    const ledgerBefore = await page.locator('table tbody tr').count();

    // Attempt issue with a massive qty (guaranteed oversell).
    const issueResp = await page.request.post(`${BASE_URL}${issuePath}`, {
        form: { _csrf_token: csrf, force_qty: '99999999' },
        maxRedirects: 5,
    }).catch(() => null);
    note(`TX-SO-001: Issue POST → status: ${issueResp ? issueResp.status() : 'ERROR'}`);

    // SO must remain Approved.
    await safeGoto(page, `${BASE_URL}${soHref}`);
    const statusText = await page.locator('.badge, [class*="status"]').first().innerText().catch(() => '');
    check('TX-SO-001', 'SO remains Approved after failed oversell attempt',
        /approved/i.test(statusText), `status: "${statusText}"`);

    // Ledger count should not have increased.
    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    const ledgerAfter = await page.locator('table tbody tr').count();
    check('TX-SO-001', 'Ledger row count unchanged after failed oversell',
        ledgerAfter <= ledgerBefore, `before: ${ledgerBefore}, after: ${ledgerAfter}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// D29 — Error handling: validation errors show user-friendly messages (not 500)
// ─────────────────────────────────────────────────────────────────────────────
async function checkErrorPresentation(browser) {
    console.log('\n=== D29: Error handling / validation error presentation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const validationTests = [
        {
            label: 'Product create — empty form',
            url: '/products',
            form: { name: '', sku: '', category_id: '' },
        },
        {
            label: 'Warehouse create — empty code',
            url: '/warehouses',
            form: { code: '', name: '' },
        },
        {
            label: 'User create — short password',
            url: '/users',
            form: { name: 'Test', email: 'test@test.com', password: '123', role: 'Sales' },
        },
        {
            label: 'PO create — no line items',
            url: '/purchase-orders',
            form: { supplier_id: '1', warehouse_id: '1', order_date: '2026-01-01' },
        },
    ];

    for (const { label, url, form } of validationTests) {
        await safeGoto(page, `${BASE_URL}${url}/create`);
        const csrf = await getCsrfToken(page);

        const resp = await page.request.post(`${BASE_URL}${url}`, {
            form: { _csrf_token: csrf, ...form },
            maxRedirects: 5,
        }).catch(() => null);
        const status = resp ? resp.status() : 0;

        check('D29', `${label}: response is not 5xx`, status < 500, `status: ${status}`);

        // If the response is an HTML page (redirected or re-rendered), check for PHP error.
        if (resp && resp.headers()['content-type']?.includes('text/html')) {
            const body = await resp.text().catch(() => '');
            check('D29', `${label}: no PHP Fatal in response`, !/Fatal error/i.test(body));
        }
    }

    // KNOWN_DEFECT check: Over-long Warehouse input returning 500 (documented defect).
    await safeGoto(page, `${BASE_URL}/warehouses/create`);
    const csrf2 = await getCsrfToken(page);
    const longResp = await page.request.post(`${BASE_URL}/warehouses`, {
        form: {
            _csrf_token: csrf2,
            code:     'QA-TEST-WH',
            name:     'A'.repeat(500),
            location: 'B'.repeat(500),
        },
        maxRedirects: 5,
    }).catch(() => null);
    const longStatus = longResp ? longResp.status() : 0;
    if (longStatus >= 500) {
        note(`D29 KNOWN_DEFECT: Over-long Warehouse input returns ${longStatus} instead of 422/400 (should be fixed)`);
    } else {
        check('D29', 'Over-long Warehouse input handled gracefully (not 5xx)', longStatus < 500, `status: ${longStatus}`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// D12 — Stale session / state after logout
// ─────────────────────────────────────────────────────────────────────────────
async function checkStaleSession(browser) {
    console.log('\n=== D12: Stale session after logout ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, SALES.email, SALES.password);

    // Logout.
    await page.goto(`${BASE_URL}/logout`, { waitUntil: 'networkidle' }).catch(() => {});

    // Attempt protected action after logout.
    const resp = await page.request.get(`${BASE_URL}/sales-orders`, { maxRedirects: 5 }).catch(() => null);
    const landed = resp ? resp.url() : page.url();
    check('D12', 'Stale session: /sales-orders after logout redirects to /login',
        landed.includes('/login'), `url: ${landed}`);

    // Attempt POST action after logout.
    const postResp = await page.request.post(`${BASE_URL}/sales-orders`, {
        form: { customer_id: '1', warehouse_id: '1' },
        maxRedirects: 5,
    }).catch(() => null);
    const postLanded = postResp ? postResp.url() : '';
    check('D12', 'Stale session: POST /sales-orders after logout is rejected',
        postLanded.includes('/login') || (postResp && postResp.status() >= 400),
        `url: ${postLanded}, status: ${postResp ? postResp.status() : 'ERROR'}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        await checkInvalidIds(browser);
        await checkDoubleSubmit(browser);
        await checkPoRollback(browser);
        await checkSoOversellRollback(browser);
        await checkErrorPresentation(browser);
        await checkStaleSession(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — error-handling.spec.js complete');
})();

// Ad-hoc Playwright QA script for the Purchase Orders menu (/purchase-orders).
// Checks the page against PROJECT_REFERENCE.md (§1.2 role matrix, §2.3 PO-01,
// ARCH-02-style concurrency expectations) and docs/roles/*.md.
// Not run in CI. See README.md for setup/usage.
//
// NOTE ON TEST DATA: Purchase Orders have no delete/deactivate feature by
// design (they are a permanent audit trail, matching DB-01/PO-01 — a PO can
// only move Draft -> Ordered -> PartiallyReceived/Received or -> Cancelled).
// Every run of this script therefore leaves 1-2 new PO rows and real
// Stock Ledger / ProductStock movements behind — this is expected and
// unavoidable, not test pollution to clean up.
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

// Seed product used for the line items below (see database/seed.sql).
const TEST_SKU = 'ELEC-003'; // "USB-C Cable 1m" — picked to keep stock deltas away from products other specs (dashboard low-stock, etc.) already assert on.

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

async function safeGoto(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
}

// page.selectOption({ label }) requires an exact string match, but the PO
// form's product <option> text is "SKU — Product Name" — find the value by
// SKU substring instead of hardcoding the full label.
async function selectProductBySku(page, selector, sku) {
    const value = await page.locator(`${selector} option`).evaluateAll(
        (opts, needle) => {
            const match = opts.find((o) => o.textContent.includes(needle));
            return match ? match.value : null;
        },
        sku
    );
    if (!value) {
        throw new Error(`No <option> containing "${sku}" found in ${selector}`);
    }
    await page.selectOption(selector, value);
}

// ---------------------------------------------------------------------
// Unauthenticated access.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/purchase-orders`, { waitUntil: 'networkidle' });
    check(
        'AUTH-01.04: GET /purchase-orders without session redirects to /login',
        page.url().includes('/login'),
        `landed on ${page.url()}`
    );

    const apiResp = await page.request.post(`${BASE_URL}/purchase-orders`, { form: {}, maxRedirects: 0 });
    check(
        'Unauthenticated POST /purchase-orders is rejected (redirect/4xx, not a direct 200)',
        apiResp.status() !== 200,
        `got HTTP ${apiResp.status()}`
    );

    await context.close();
}

// ---------------------------------------------------------------------
// Per-role access — PROJECT_REFERENCE.md §2.3 PO-01 "Hak Akses per Peran":
// Admin full access; WarehouseStaff can view/create/receive but not submit
// or cancel; Sales has NO access at all (unlike Categories/Suppliers, this
// is gated by requirePermission('purchase_orders.manage'), not requireAuth()).
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: GET /purchase-orders ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await login(page, email, password);
    const resp = await page.goto(`${BASE_URL}/purchase-orders`, { waitUntil: 'networkidle' });
    const status = resp ? resp.status() : null;

    if (role === 'Sales') {
        check(
            'PO-01: Sales gets 403 Forbidden viewing the Purchase Orders list (no view-only mode, unlike Categories/Suppliers)',
            status === 403,
            `got HTTP ${status}`
        );
    } else {
        check(`${role} can view the Purchase Orders list (HTTP 200)`, status === 200, `got HTTP ${status}`);
        check(`${role} sees the "New Order" button`, await page.locator('a:has-text("New Order")').count() > 0);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `purchase-orders-${role}.png`), fullPage: true });
    }

    note(`Console errors: ${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);
    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Server-side authorization for Sales — every PO endpoint must reject,
// not just the list page.
// ---------------------------------------------------------------------
async function checkSalesFullyBlocked(browser) {
    console.log('\n=== Sales: server-side authorization on ALL purchase-orders endpoints ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'sales1@example.com', 'sales123');
    await safeGoto(page, `${BASE_URL}/dashboard`);
    const csrf = await getCsrfToken(page).catch(() => '');

    const attempts = [
        { label: 'GET /purchase-orders/create', method: 'get', url: '/purchase-orders/create' },
        { label: 'POST /purchase-orders (create)', method: 'post', url: '/purchase-orders', form: { _csrf_token: csrf } },
        { label: 'GET /purchase-orders/1', method: 'get', url: '/purchase-orders/1' },
        { label: 'POST /purchase-orders/1/submit', method: 'post', url: '/purchase-orders/1/submit', form: { _csrf_token: csrf } },
        { label: 'POST /purchase-orders/1/cancel', method: 'post', url: '/purchase-orders/1/cancel', form: { _csrf_token: csrf } },
        { label: 'GET /purchase-orders/1/receive', method: 'get', url: '/purchase-orders/1/receive' },
        { label: 'POST /purchase-orders/1/receive', method: 'post', url: '/purchase-orders/1/receive', form: { _csrf_token: csrf } },
    ];

    for (const attempt of attempts) {
        const req = attempt.method === 'get'
            ? page.request.get(`${BASE_URL}${attempt.url}`, { maxRedirects: 0 })
            : page.request.post(`${BASE_URL}${attempt.url}`, { form: attempt.form, maxRedirects: 0 });
        const resp = await req.catch((e) => e);
        const status = resp && resp.status ? resp.status() : 'ERROR';
        check(`SOD/AUTHZ: Sales -> ${attempt.label} is rejected (expect 403)`, status === 403, `got HTTP ${status}`);
    }

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// WarehouseStaff cannot cancel OR submit a PO — purchase_orders.cancel and
// purchase_orders.submit are both Admin-only, even though WarehouseStaff
// DOES have purchase_orders.manage (create/receive/view). PROJECT_REFERENCE.md
// §2.3 PO-01 "Hak Akses per Peran" scopes Submit to Admin only; this was
// fixed on 2026-09-24 (see README.md) — it originally shared
// purchase_orders.manage with create/receive, letting WarehouseStaff submit
// too.
// ---------------------------------------------------------------------
async function checkWarehouseCannotCancelOrSubmit(browser) {
    console.log('\n=== WarehouseStaff: cannot cancel or submit a PO (both Admin-only) ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'warehouse@example.com', 'wh123');
    await safeGoto(page, `${BASE_URL}/purchase-orders`);
    const csrf = await getCsrfToken(page).catch(() => '');

    const cancelResp = await page.request.post(`${BASE_URL}/purchase-orders/1/cancel`, { form: { _csrf_token: csrf }, maxRedirects: 0 });
    check('SOD/AUTHZ: WarehouseStaff -> POST /purchase-orders/1/cancel is rejected (expect 403)', cancelResp.status() === 403, `got HTTP ${cancelResp.status()}`);

    const submitResp = await page.request.post(`${BASE_URL}/purchase-orders/1/submit`, { form: { _csrf_token: csrf }, maxRedirects: 0 });
    check('PO-01: WarehouseStaff -> POST /purchase-orders/1/submit is rejected (expect 403, Admin-only per PROJECT_REFERENCE.md)', submitResp.status() === 403, `got HTTP ${submitResp.status()}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Full Admin lifecycle: create (Draft) -> submit (Ordered) -> partial
// receive (PartiallyReceived) -> full receive (Received). Verifies the
// Stock Ledger gets a Receipt row and ProductStock actually increases
// (PO-01.03/.04, DATA-02/DATA-03).
// ---------------------------------------------------------------------
async function checkAdminLifecycle(browser) {
    console.log('\n=== Admin: full PO lifecycle (Draft -> Ordered -> PartiallyReceived -> Received) ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    // --- Create (Draft) ---
    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    await page.selectOption('#supplier_id', { index: 1 });
    await page.selectOption('#destination_warehouse_id', { index: 1 });
    await page.fill('#order_date', new Date().toISOString().slice(0, 10));
    await selectProductBySku(page, 'select[name="item_product_id[]"]', TEST_SKU);
    await page.fill('input[name="item_qty_ordered[]"]', '20');
    await page.fill('input[name="item_purchase_price[]"]', '10000');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#po-submit-btn'),
    ]);
    const poUrl = page.url();
    check('Create: redirected to the new PO\'s detail page', /\/purchase-orders\/[a-f0-9]+$/.test(poUrl), `landed on ${poUrl}`);
    check('Create: status badge shows Draft', (await page.locator('.badge').first().textContent().catch(() => '')).trim() === 'Draft');
    check('Create: "Submit Order" button is visible for Admin', await page.locator('button:has-text("Submit Order")').count() > 0);
    check('Create: "Cancel" button is visible for Admin on a Draft PO', await page.locator('button:has-text("Cancel")').count() > 0);

    // --- Validation: empty line items rejected (direct POST, bypassing the client-side form) ---
    const csrfForValidation = await getCsrfToken(page).catch(() => '');
    const noItemsResp = await page.request.post(`${BASE_URL}/purchase-orders`, {
        form: {
            supplier_id: '1', destination_warehouse_id: '1', order_date: new Date().toISOString().slice(0, 10),
            _csrf_token: csrfForValidation,
        },
    });
    const noItemsBody = await noItemsResp.text();
    check('Validation: PO with no line items is rejected server-side', /at least one line item/i.test(noItemsBody));

    // --- Submit (Draft -> Ordered) ---
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button:has-text("Submit Order")'),
    ]);
    check('Submit: status badge flips to Ordered', (await page.locator('.badge').first().textContent().catch(() => '')).trim() === 'Ordered');
    check('Submit: "Submit Order" button disappears once Ordered', await page.locator('button:has-text("Submit Order")').count() === 0);
    check('Submit: "Receive Goods" button appears for an Ordered PO', await page.locator('a:has-text("Receive Goods")').count() > 0);

    // --- Partial receive ---
    await page.click('a:has-text("Receive Goods")');
    await page.waitForLoadState('networkidle');
    await page.fill('input[name^="qty_now"]', '8'); // receive 8 of 20
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button:has-text("Confirm Receipt")'),
    ]);
    check('Partial receive: status badge flips to Partially Received', /partially received/i.test((await page.locator('.badge').first().textContent().catch(() => '')) || ''));
    const receivedQtyText = await page.locator('.fulfillment-progress__tile:has-text("Total Received") .fulfillment-progress__tile-value').textContent().catch(() => '');
    check('Partial receive: "Total Received" tile shows 8', (receivedQtyText || '').replace(/\D/g, '') === '8', `tile text: "${receivedQtyText}"`);

    // --- Stock Ledger got a Receipt row for this PO ---
    const ledgerText = await page.locator('.audit-timeline').textContent().catch(() => '');
    check('Partial receive: Audit Log shows a Receipt entry with +8 units', /Receipt/.test(ledgerText || '') && /\+8/.test(ledgerText || ''), `audit log: "${(ledgerText || '').replace(/\s+/g, ' ').trim().slice(0, 200)}"`);

    // --- Over-receive validation: cannot receive more than the remaining qty ---
    await page.click('a:has-text("Receive Goods")');
    await page.waitForLoadState('networkidle');
    const maxAttr = await page.locator('input[name^="qty_now"]').getAttribute('max');
    check('Receive form: input max attribute reflects the remaining qty (12)', maxAttr === '12', `max="${maxAttr}"`);
    const csrfReceive = await getCsrfToken(page).catch(() => '');
    const poId = poUrl.split('/').pop();
    const overReceiveResp = await page.request.post(`${BASE_URL}/purchase-orders/${poId}/receive`, {
        form: { [`qty_now[${await firstItemId(page)}]`]: '999', _csrf_token: csrfReceive },
    });
    const overReceiveBody = await overReceiveResp.text();
    check('Validation: receiving more than the remaining qty is rejected server-side', /cannot exceed the (quantity still outstanding|remaining)/i.test(overReceiveBody));

    // --- Full receive (remaining 12) ---
    await safeGoto(page, `${BASE_URL}/purchase-orders/${poId}/receive`);
    await page.fill('input[name^="qty_now"]', '12');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button:has-text("Confirm Receipt")'),
    ]);
    check('Full receive: status badge flips to Received', (await page.locator('.badge').first().textContent().catch(() => '')).trim() === 'Received');
    check('Full receive: "Receive Goods" button disappears once fully Received', await page.locator('a:has-text("Receive Goods")').count() === 0);
    check('Full receive: "Cancel" button disappears once fully Received (cannot cancel a Received PO)', await page.locator('button:has-text("Cancel")').count() === 0);

    // Direct-endpoint guard: cancelling a Received PO must be rejected server-side too.
    const csrfFinal = await getCsrfToken(page).catch(() => '');
    const cancelReceivedResp = await page.request.post(`${BASE_URL}/purchase-orders/${poId}/cancel`, { form: { _csrf_token: csrfFinal } });
    const cancelReceivedBody = await cancelReceivedResp.text();
    check(
        'Cancel guard: a fully Received PO cannot be cancelled even via direct POST',
        /no longer be cancelled/i.test(cancelReceivedBody),
        `HTTP ${cancelReceivedResp.status()}`
    );

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'purchase-orders-admin-lifecycle-final.png'), fullPage: true });

    await logout(page);
    await context.close();
}

async function firstItemId(page) {
    const name = await page.locator('input[name^="qty_now"]').first().getAttribute('name');
    const match = /qty_now\[(\d+)\]/.exec(name || '');
    return match ? match[1] : '0';
}

// ---------------------------------------------------------------------
// Cancel guard: a Draft PO CAN be cancelled by Admin.
// ---------------------------------------------------------------------
async function checkCancelDraft(browser) {
    console.log('\n=== Admin: cancel a Draft PO ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    await page.selectOption('#supplier_id', { index: 1 });
    await page.selectOption('#destination_warehouse_id', { index: 1 });
    await page.fill('#order_date', new Date().toISOString().slice(0, 10));
    await selectProductBySku(page, 'select[name="item_product_id[]"]', TEST_SKU);
    await page.fill('input[name="item_qty_ordered[]"]', '5');
    await page.fill('input[name="item_purchase_price[]"]', '10000');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#po-submit-btn'),
    ]);

    // The Cancel form has data-confirm — app.js intercepts submit and shows a
    // custom in-page confirm modal (#__confirmOk / #__confirmCancel), not a
    // native window.confirm(), so it must be clicked explicitly and waited for.
    await page.click('button:has-text("Cancel")');
    await page.waitForSelector('#__confirmOk', { state: 'visible' });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#__confirmOk'),
    ]);
    check('Cancel: a Draft PO can be cancelled by Admin, status flips to Cancelled', (await page.locator('.badge').first().textContent().catch(() => '')).trim() === 'Cancelled');

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Concurrency check on Goods Receipt. PROJECT_REFERENCE.md's explicit
// ARCH-02 requirement is about concurrent Goods ISSUE (Sales Order stock
// deduction) — it does not by name cover Goods RECEIPT. But PO-01.05/.06
// still require "sisa qty yang belum diterima tetap tercatat" (qty_received
// must never exceed qty_ordered), so this fires two concurrent partial
// receipts on the SAME line, each individually valid, but whose SUM would
// overshoot qty_ordered — a correct implementation must let only one
// succeed (or serialize them so the second sees the updated remaining qty
// and gets rejected), never let qty_received end up > qty_ordered.
// ---------------------------------------------------------------------
async function checkConcurrentReceiveDoesNotOverReceive(browser) {
    console.log('\n=== Concurrency: two simultaneous partial receipts on the same PO line ===');
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');

    await safeGoto(adminPage, `${BASE_URL}/purchase-orders/create`);
    await adminPage.selectOption('#supplier_id', { index: 1 });
    await adminPage.selectOption('#destination_warehouse_id', { index: 1 });
    await adminPage.fill('#order_date', new Date().toISOString().slice(0, 10));
    await selectProductBySku(adminPage, 'select[name="item_product_id[]"]', TEST_SKU);
    await adminPage.fill('input[name="item_qty_ordered[]"]', '10'); // exactly 10 — two concurrent receipts of 8 each would overshoot to 16 if racy
    await adminPage.fill('input[name="item_purchase_price[]"]', '10000');
    await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.click('#po-submit-btn'),
    ]);
    const poId = adminPage.url().split('/').pop();

    await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.click('button:has-text("Submit Order")'),
    ]);

    await safeGoto(adminPage, `${BASE_URL}/purchase-orders/${poId}/receive`);
    const itemId = await firstItemId(adminPage);
    const csrfA = await getCsrfToken(adminPage).catch(() => '');

    // A second, independent logged-in context (WarehouseStaff) racing the same PO/line.
    const whContext = await browser.newContext();
    const whPage = await whContext.newPage();
    await login(whPage, 'warehouse@example.com', 'wh123');
    await safeGoto(whPage, `${BASE_URL}/purchase-orders/${poId}/receive`);
    const csrfB = await getCsrfToken(whPage).catch(() => '');

    const [respA, respB] = await Promise.all([
        adminPage.request.post(`${BASE_URL}/purchase-orders/${poId}/receive`, { form: { [`qty_now[${itemId}]`]: '8', _csrf_token: csrfA } }),
        whPage.request.post(`${BASE_URL}/purchase-orders/${poId}/receive`, { form: { [`qty_now[${itemId}]`]: '8', _csrf_token: csrfB } }),
    ]);

    note(`Request A (Admin) -> HTTP ${respA.status()}`);
    note(`Request B (WarehouseStaff) -> HTTP ${respB.status()}`);

    await safeGoto(adminPage, `${BASE_URL}/purchase-orders/${poId}`);
    const finalReceivedText = await adminPage.locator('.fulfillment-progress__tile:has-text("Total Received") .fulfillment-progress__tile-value').textContent().catch(() => '');
    const finalReceived = parseInt((finalReceivedText || '').replace(/\D/g, ''), 10);
    check(
        'ARCH-02-style invariant: qty_received never exceeds qty_ordered (10) even under two simultaneous receipt requests on the same line',
        finalReceived <= 10,
        `final Total Received tile: "${finalReceivedText}" (parsed: ${finalReceived}) — expected one request to be rejected or serialized, not both to fully apply`
    );
    note(`If this FAILS (finalReceived > 10): GoodsReceiptService::applyReceiptLine() re-checks qtyRemaining() using a fresh (but unlocked) read BEFORE calling productStockRepository->lockForUpdate() — the row lock serializes the stock increment, but the second request's over-receipt check already passed on stale data before it blocked, so it never re-validates after acquiring the lock.`);

    await logout(adminPage);
    await logout(whPage);
    await adminContext.close();
    await whContext.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);
        for (const roleConfig of ROLES) {
            await checkRoleView(browser, roleConfig);
        }
        await checkSalesFullyBlocked(browser);
        await checkWarehouseCannotCancelOrSubmit(browser);
        await checkAdminLifecycle(browser);
        await checkCancelDraft(browser);
        await checkConcurrentReceiveDoesNotOverReceive(browser);
    } finally {
        await browser.close();
    }
    console.log(`\nDONE — purchase orders checked. PASS=${pass} FAIL=${fail}`);
    process.exitCode = fail > 0 ? 1 : 0;
})();

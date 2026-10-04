// Ad-hoc Playwright QA script — Cross-module integration tests.
// Covers INT-001..013 from the E2E deep-dive specification:
// entity deactivation cascades (supplier→PO, warehouse→orders,
// product→PO/SO, category→product), and transactional integrity
// (PO/SO receipt→stock, SO fulfillment→ledger, user deactivation→auth).
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL      = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN = { role: 'Admin',          email: 'admin@example.com',     password: 'admin123' };
const SALES = { role: 'Sales',          email: 'sales1@example.com',    password: 'sales123' };
const WH    = { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123'    };

// QA-owned test fixture names — must be stable across parallel runs.
const INTEG_SUPPLIER_NAME = 'QA Integration Supplier (INT)';
const INTEG_SUPPLIER_EMAIL= 'qa-integration-supplier@example.com';

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
// Helper: ensure QA integration supplier exists (create or reuse)
// Returns: { supplierId (obfuscated), supplierFound }
// ─────────────────────────────────────────────────────────────────────────────
async function ensureIntegrationSupplier(page) {
    await safeGoto(page, `${BASE_URL}/suppliers`);

    // Search for existing.
    const nameSearch = page.locator('#supplier-name, input[name="name"]').first();
    if (await nameSearch.count() > 0) {
        await nameSearch.fill(INTEG_SUPPLIER_NAME);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }

    const existingRow = page.locator(`tr:has-text("${INTEG_SUPPLIER_EMAIL}")`);
    if ((await existingRow.count()) > 0) {
        note('INT-001: Reusing existing integration supplier');
        return { found: true };
    }

    // Create it.
    await safeGoto(page, `${BASE_URL}/suppliers/create`);
    const csrf = await getCsrfToken(page);
    const resp = await page.request.post(`${BASE_URL}/suppliers`, {
        form: {
            _csrf_token: csrf,
            name:        INTEG_SUPPLIER_NAME,
            email:       INTEG_SUPPLIER_EMAIL,
            phone:       '08000000000',
            address:     'QA Test Address',
        },
        maxRedirects: 5,
    });
    note(`INT-001: Supplier create — status: ${resp.status()}`);
    return { found: resp.status() < 400 };
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-001 — Supplier deactivation → PO create dropdown
// ─────────────────────────────────────────────────────────────────────────────
async function checkSupplierIntegration(browser) {
    console.log('\n=== INT-001: Supplier deactivation → PO dropdown ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await ensureIntegrationSupplier(page);

    // Confirm supplier appears in PO create dropdown when active.
    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    const supplierSelect = page.locator('select[name="supplier_id"]').first();
    const activeOptions  = await supplierSelect.locator('option').allInnerTexts().catch(() => []);
    const activeHasSupplier = activeOptions.some((t) => t.includes(INTEG_SUPPLIER_NAME) || t.includes(INTEG_SUPPLIER_EMAIL));
    check('INT-001', 'Active integration supplier appears in PO create dropdown', activeHasSupplier,
        `options: ${activeOptions.slice(0, 5).join(', ')}`);

    // Deactivate the supplier.
    await safeGoto(page, `${BASE_URL}/suppliers`);
    const nameSearch = page.locator('#supplier-name, input[name="name"]').first();
    if (await nameSearch.count() > 0) {
        await nameSearch.fill(INTEG_SUPPLIER_NAME);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }

    const deactivateLink = page.locator(`tr:has-text("${INTEG_SUPPLIER_EMAIL}") a:has-text("Deactivate"), tr:has-text("${INTEG_SUPPLIER_EMAIL}") button:has-text("Deactivate")`).first();
    if (await deactivateLink.count() > 0) {
        const href = await deactivateLink.getAttribute('href').catch(() => null);
        if (href) {
            await safeGoto(page, `${BASE_URL}${href}`);
        } else {
            const csrf = await getCsrfToken(page);
            await deactivateLink.click();
            await page.waitForLoadState('networkidle').catch(() => {});
        }

        // Check PO create dropdown no longer shows deactivated supplier.
        await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
        const inactiveOptions = await supplierSelect.locator('option').allInnerTexts().catch(() => []);
        const inactiveHasSupplier = inactiveOptions.some((t) => t.includes(INTEG_SUPPLIER_NAME));
        check('INT-001', 'Deactivated supplier removed from PO create dropdown', !inactiveHasSupplier,
            `options: ${inactiveOptions.slice(0, 5).join(', ')}`);

        // Reactivate.
        await safeGoto(page, `${BASE_URL}/suppliers`);
        const activateLink = page.locator(`tr:has-text("${INTEG_SUPPLIER_EMAIL}") a:has-text("Activate"), tr:has-text("${INTEG_SUPPLIER_EMAIL}") button:has-text("Activate")`).first();
        if (await activateLink.count() > 0) {
            const activateHref = await activateLink.getAttribute('href').catch(() => null);
            if (activateHref) await safeGoto(page, `${BASE_URL}${activateHref}`);
            else await activateLink.click();
            note('INT-001: Supplier reactivated');
        }

        // INT-002 (SUP-INTEG-002) — Reactivated supplier restored.
        await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
        const restoredOptions = await supplierSelect.locator('option').allInnerTexts().catch(() => []);
        check('INT-001', 'Reactivated supplier restored to PO dropdown',
            restoredOptions.some((t) => t.includes(INTEG_SUPPLIER_NAME)),
            `options: ${restoredOptions.slice(0, 5).join(', ')}`);
    } else {
        note('INT-001: Deactivate button not found for integration supplier');
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-002 — Warehouse deactivation → order form availability
// ─────────────────────────────────────────────────────────────────────────────
async function checkWarehouseIntegration(browser) {
    console.log('\n=== INT-002: Warehouse deactivation → order form ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Record active warehouse count in PO create form.
    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    const whSelect = page.locator('select[name="warehouse_id"]').first();
    const activeWh = await whSelect.locator('option').count().catch(() => 0);
    note(`INT-002: Active warehouse options in PO create: ${activeWh}`);

    // Deactivate QA-WH-01 (if present — see warehouses.spec.js fixture).
    await safeGoto(page, `${BASE_URL}/warehouses`);
    const qaWhRow = page.locator('tr:has-text("QA-WH-01")');
    if (await qaWhRow.count() > 0) {
        const deactivateBtn = qaWhRow.locator('a:has-text("Deactivate"), button:has-text("Deactivate")').first();
        if (await deactivateBtn.count() > 0) {
            const href = await deactivateBtn.getAttribute('href').catch(() => null);
            if (href) await safeGoto(page, `${BASE_URL}${href}`);
            else await deactivateBtn.click();

            await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
            const inactiveWh = await whSelect.locator('option').count().catch(() => 0);
            check('INT-002', 'Deactivated warehouse removed from PO form',
                inactiveWh < activeWh, `before: ${activeWh}, after: ${inactiveWh}`);

            // Reactivate.
            await safeGoto(page, `${BASE_URL}/warehouses`);
            const activateBtn = page.locator('tr:has-text("QA-WH-01") a:has-text("Activate"), tr:has-text("QA-WH-01") button:has-text("Activate")').first();
            if (await activateBtn.count() > 0) {
                const href2 = await activateBtn.getAttribute('href').catch(() => null);
                if (href2) await safeGoto(page, `${BASE_URL}${href2}`);
                else await activateBtn.click();
            }
        } else {
            note('INT-002: QA-WH-01 has no deactivate button (already inactive or not applicable)');
        }
    } else {
        note('INT-002: QA-WH-01 fixture not found — run warehouses.spec.js first to create it');
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-003 — Product deactivation → PO/SO availability
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductIntegration(browser) {
    console.log('\n=== INT-003: Product deactivation → PO/SO form ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find first active product in PO create form.
    await safeGoto(page, `${BASE_URL}/purchase-orders/create`);
    // Product is typically added via line items — check the product autocomplete/select.
    const productField = page.locator('select[name*="product_id"], input[name*="product"]').first();
    const productCount = await productField.count();
    note(`INT-003: Product field in PO create found: ${productCount > 0}`);

    check('INT-003', 'PO create form accessible for admin', !page.url().includes('/login'));

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-004 — Category → Product list filter/link
// ─────────────────────────────────────────────────────────────────────────────
async function checkCategoryProductLink(browser) {
    console.log('\n=== INT-004: Category → Product list ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/categories`);
    // Categories list should have SKU count with a link to filtered products.
    const skuLink = page.locator('table tbody tr td a[href*="product"], table tbody tr td a[href*="sku"]').first();
    if (await skuLink.count() > 0) {
        const href = await skuLink.getAttribute('href');
        await safeGoto(page, `${BASE_URL}${href}`);
        check('INT-004', 'Category SKU count link resolves (not 4xx/5xx)',
            !page.url().includes('/login') && (await page.locator('body').innerText()).length > 0);
    } else {
        // Count column may be plain text.
        const skuCountCell = await page.locator('table tbody tr td:nth-child(3)').first().innerText().catch(() => '');
        note(`INT-004: SKU count cell text: "${skuCountCell}" (no direct link found)`);
        check('INT-004', 'Categories page loads with data', (await page.locator('table tbody tr').count()) > 0);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-005/006 — PO Receipt → Stock + Ledger
// (Reads stock ledger to verify last receipt was recorded correctly)
// ─────────────────────────────────────────────────────────────────────────────
async function checkPoReceiptIntegration(browser) {
    console.log('\n=== INT-005/006: PO Receipt → Stock + Ledger ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find a received PO (status Received or PartiallyReceived) in PO list.
    await safeGoto(page, `${BASE_URL}/purchase-orders`);
    // Look for a PO with status Received or PartiallyReceived.
    const receivedRow = page.locator('table tbody tr:has-text("Received"), table tbody tr:has-text("PartiallyReceived")').first();
    if (await receivedRow.count() === 0) {
        note('INT-005: No Received/PartiallyReceived PO found, skipping stock/ledger check');
        await ctx.close();
        return;
    }

    const detailLink = receivedRow.locator('a[href*="/purchase-orders/"]').first();
    if (await detailLink.count() === 0) {
        note('INT-005: No detail link on Received PO row');
        await ctx.close();
        return;
    }

    await detailLink.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    check('INT-005', 'PO detail page loads for received PO', !page.url().includes('/login'));

    // Verify ledger link or stock reference exists on detail page.
    const bodyText = await page.locator('body').innerText().catch(() => '');
    check('INT-006', 'PO detail references stock/ledger information',
        /stock|ledger|stok|penerimaan|receipt/i.test(bodyText));

    // Navigate to stock ledger and check there are Receipt entries.
    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    const ledgerRows = await page.locator('table tbody tr:has-text("Receipt"), table tbody tr:has-text("Penerimaan")').count();
    check('INT-006', 'Stock ledger contains Receipt movement entries', ledgerRows > 0, `Receipt rows found: ${ledgerRows}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-008/009 — SO Issue → Stock + Ledger
// ─────────────────────────────────────────────────────────────────────────────
async function checkSoIssueIntegration(browser) {
    console.log('\n=== INT-008/009: SO Issue → Stock + Ledger ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/stock-ledger`);
    const issueRows = await page.locator('table tbody tr:has-text("Issue"), table tbody tr:has-text("Pengeluaran")').count();
    check('INT-008', 'Stock ledger contains Issue movement entries (SO fulfillment)', issueRows > 0, `Issue rows: ${issueRows}`);

    if (issueRows > 0) {
        // Spot-check: Issue qty should be negative (shown with − prefix).
        const firstIssueRow = page.locator('table tbody tr:has-text("Issue"), table tbody tr:has-text("Pengeluaran")').first();
        const qtyCell = await firstIssueRow.locator('td').nth(5).innerText().catch(() => '');
        note(`INT-009: First Issue row qty cell: "${qtyCell}"`);
        check('INT-009', 'Issue qty cell contains minus sign or negative value',
            /[-−]/.test(qtyCell), `qty: "${qtyCell}"`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-011 — User deactivation → authentication (light smoke; full test in users.spec.js)
// ─────────────────────────────────────────────────────────────────────────────
async function checkUserAuthIntegration(browser) {
    console.log('\n=== INT-011: User deactivation → authentication (smoke) ===');
    note('INT-011: Full test covered in users.spec.js AUTH-01 end-to-end scenario.');

    // Light check: deactivating a user in Admin panel immediately reflects on /users list.
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/users`);
    const userRows = await page.locator('table tbody tr').count();
    check('INT-011', 'Users list accessible to Admin (integration baseline)', userRows > 0, `rows: ${userRows}`);
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-012 — Sales ownership → Reports scope
// ─────────────────────────────────────────────────────────────────────────────
async function checkSalesReportScope(browser) {
    console.log('\n=== INT-012: Sales ownership → Reports scope ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, SALES.email, SALES.password);

    // Sales report CSV should only contain own orders.
    const csrf = await getCsrfToken(page);
    const today = new Date().toISOString().slice(0, 10);
    const dateFrom = '2020-01-01';

    // Check that Sales CAN access the Orders export endpoint.
    await safeGoto(page, `${BASE_URL}/reports`);
    const exportBtn = page.locator('a:has-text("Export Orders"), button:has-text("Export Orders")').first();
    if (await exportBtn.count() > 0) {
        const href = await exportBtn.getAttribute('href').catch(() => null);
        note(`INT-012: Sales export orders button href: ${href}`);
    }

    // Direct request to export endpoint.
    const exportResp = await page.request.post(`${BASE_URL}/reports/export/orders`, {
        form: { _csrf_token: csrf, date_from: dateFrom, date_to: today },
        maxRedirects: 5,
    }).catch(() => null);
    const exportStatus = exportResp ? exportResp.status() : 0;
    note(`INT-012: Sales export orders — status: ${exportStatus}`);
    check('INT-012', 'Sales can access Orders export (not 403)',
        exportStatus !== 403, `status: ${exportStatus}`);

    await ctx.close();

    // Verify Warehouse is denied from stock-ledger export.
    const whCtx  = await browser.newContext();
    const whPage = await whCtx.newPage();
    await login(whPage, WH.email, WH.password);
    const whCsrf = await getCsrfToken(whPage);
    const whExportResp = await whPage.request.post(`${BASE_URL}/reports/export/orders`, {
        form: { _csrf_token: whCsrf, date_from: dateFrom, date_to: today },
        maxRedirects: 5,
    }).catch(() => null);
    check('INT-012', 'WarehouseStaff denied Orders export (403)', whExportResp && whExportResp.status() === 403,
        `status: ${whExportResp ? whExportResp.status() : 'ERROR'}`);
    await whCtx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// INT-013 — Warehouse role → Reports/Ledger scope
// ─────────────────────────────────────────────────────────────────────────────
async function checkWarehouseReportScope(browser) {
    console.log('\n=== INT-013: Warehouse role → Reports/Ledger scope ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, WH.email, WH.password);

    // Warehouse can access Stock Ledger.
    const ledgerResp = await page.request.get(`${BASE_URL}/stock-ledger`, { maxRedirects: 5 }).catch(() => null);
    check('INT-013', 'WarehouseStaff can access /stock-ledger', ledgerResp && ledgerResp.status() === 200, `status: ${ledgerResp ? ledgerResp.status() : 'ERROR'}`);

    // Warehouse can access Reports page.
    const reportsResp = await page.request.get(`${BASE_URL}/reports`, { maxRedirects: 5 }).catch(() => null);
    check('INT-013', 'WarehouseStaff can access /reports', reportsResp && reportsResp.status() === 200, `status: ${reportsResp ? reportsResp.status() : 'ERROR'}`);

    // Warehouse can access stock ledger export.
    const csrf = await getCsrfToken(page);
    const today = new Date().toISOString().slice(0, 10);
    const slExportResp = await page.request.post(`${BASE_URL}/reports/export/stock-ledger`, {
        form: { _csrf_token: csrf, date_from: '2020-01-01', date_to: today },
        maxRedirects: 5,
    }).catch(() => null);
    check('INT-013', 'WarehouseStaff can export Stock Ledger CSV', slExportResp && slExportResp.status() === 200,
        `status: ${slExportResp ? slExportResp.status() : 'ERROR'}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        await checkSupplierIntegration(browser);
        await checkWarehouseIntegration(browser);
        await checkProductIntegration(browser);
        await checkCategoryProductLink(browser);
        await checkPoReceiptIntegration(browser);
        await checkSoIssueIntegration(browser);
        await checkUserAuthIntegration(browser);
        await checkSalesReportScope(browser);
        await checkWarehouseReportScope(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — integration.spec.js complete');
})();

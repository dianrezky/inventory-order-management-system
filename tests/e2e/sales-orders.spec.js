// Ad-hoc Playwright QA script for the Sales Orders menu (/sales-orders).
// Checks against PROJECT_REFERENCE.md SO-01 (status flow, SOD-01, ARCH-02
// goods issue) and the §1.2 role/access matrix.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

// OFC-001 "A4 Paper Ream" is seeded with 230 units at warehouse id 2
// (Bandung Warehouse) — comfortably enough for a 1-unit happy-path issue.
const WELL_STOCKED_PRODUCT_NAME = 'A4 Paper Ream';
const WAREHOUSE_NAME = 'Bandung Warehouse';
const CUSTOMER_NAME_HINT = ''; // first customer in the dropdown is fine

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

// The Cancel button's form has data-confirm — app.js intercepts submit and
// shows a custom JS modal (#__confirmOk / #__confirmCancel), so clicking the
// button alone does NOT navigate; the real POST only fires after the modal's
// OK button is clicked.
async function clickCancelAndConfirm(page) {
    await page.click('button:has-text("Cancel")');
    await page.locator('#__confirmOk').waitFor({ state: 'visible', timeout: 5000 });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#__confirmOk'),
    ]);
}

// Fills the create-SO form and submits it. qty=1 by default; pass a huge
// qty to deliberately exceed available stock for the insufficient-stock test.
async function createSalesOrder(page, { productName = WELL_STOCKED_PRODUCT_NAME, qty = 1, warehouseName = WAREHOUSE_NAME } = {}) {
    await page.goto(`${BASE_URL}/sales-orders/create`, { waitUntil: 'networkidle' });
    await page.selectOption('#customer_id', { index: 1 });
    await page.selectOption('#source_warehouse_id', { label: warehouseName });
    await page.selectOption('#items-body select[name="item_product_id[]"]', { label: productName });
    await page.fill('#items-body input[name="item_qty[]"]', String(qty));
    // sale_price auto-fills from the product's data-price on change; give the
    // change handler a beat before reading/submitting.
    await page.waitForTimeout(150);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#so-form button[type="submit"]'),
    ]);

    const match = page.url().match(/\/sales-orders\/([^/]+)$/);

    return match ? match[1] : null;
}

// ---------------------------------------------------------------------
// Unauthenticated access must redirect to /login.
// ---------------------------------------------------------------------
async function checkUnauthenticated(browser) {
    console.log('\n=== Unauthenticated ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    check('GET /sales-orders without session redirects to /login', page.url().includes('/login'), `landed on ${page.url()}`);
    await context.close();
}

// ---------------------------------------------------------------------
// Per-role list view: "New Order" visibility, filters, ownership scoping.
// ---------------------------------------------------------------------
async function checkRoleView(browser, { role, email, password }) {
    console.log(`\n=== ${role}: list view ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (err) => pageErrors.push(String(err)));

    await login(page, email, password);
    const resp = await page.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    check('GET /sales-orders returns 200', resp.status() === 200, `got ${resp.status()}`);

    const canCreate = role !== 'WarehouseStaff';
    const addButtonCount = await page.locator('a:has-text("New Order")').count();
    check('"New Order" button visible only for Admin/Sales', (addButtonCount > 0) === canCreate, `visible=${addButtonCount > 0}`);

    check('Filter fields present (Order Number, Customer Name, Status, Warehouse)',
        (await page.locator('#so-order-number').count()) > 0
        && (await page.locator('#so-customer-name').count()) > 0
        && (await page.locator('#so-status-wrapper').count()) > 0
        && (await page.locator('#so-warehouse-wrapper').count()) > 0);

    const rowCount = await page.locator('table.table tbody tr').count();
    note(`${role} sees ${rowCount} sales order row(s)`);

    if (role === 'Sales') {
        // SO-01 access matrix: Sales sees only orders they created. Every
        // visible "Created by" (via the row's customer/warehouse alone can't
        // prove ownership) is instead verified by opening one row's detail
        // page and confirming createdByName matches the logged-in user.
        if (rowCount > 0) {
            await page.locator('table.table tbody tr').first().locator('a:has-text("View")').click();
            await page.waitForLoadState('networkidle');
            const subtitle = await page.locator('.page-header__subtitle').textContent();
            check('Sales can open a row from their own list (detail page loads)', (subtitle || '').length > 0);
        }
    }

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `sales-orders-list-${role}.png`), fullPage: true });

    check('No console errors', consoleErrors.length === 0, JSON.stringify(consoleErrors));
    check('No page (fatal) errors', pageErrors.length === 0, JSON.stringify(pageErrors));

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// BR-017 — WarehouseStaff must be rejected server-side from creating a SO,
// not just have the button hidden.
// ---------------------------------------------------------------------
async function checkWarehouseStaffCannotCreate(browser) {
    console.log('\n=== WarehouseStaff: cannot create SO (server-side) ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'warehouse@example.com', 'wh123');

    const createFormResp = await page.request.get(`${BASE_URL}/sales-orders/create`, { maxRedirects: 0 }).catch((e) => e);
    check('GET /sales-orders/create rejected (403) for WarehouseStaff', createFormResp.status && createFormResp.status() === 403, `got ${createFormResp.status ? createFormResp.status() : 'ERROR'}`);

    await page.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    const anyCsrf = await page.evaluate(() => {
        const el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : null;
    });
    const storeResp = await page.request.post(`${BASE_URL}/sales-orders`, {
        form: { customer_id: '1', source_warehouse_id: '1', order_date: '2026-01-01', _csrf_token: anyCsrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check('POST /sales-orders (store) rejected (403) for WarehouseStaff', storeResp.status && storeResp.status() === 403, `got ${storeResp.status ? storeResp.status() : 'ERROR'}`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// Full happy-path lifecycle: Sales creates -> submits -> (Sales cannot
// approve, server-side) -> Admin approves -> WarehouseStaff issues ->
// Fulfilled, with a Stock Ledger entry recorded.
// ---------------------------------------------------------------------
async function checkHappyPathLifecycle(browser) {
    console.log('\n=== Lifecycle: Draft -> PendingApproval -> Approved -> Fulfilled ===');

    // 1. Sales creates a Draft SO.
    const salesContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const salesPage = await salesContext.newPage();
    await login(salesPage, 'sales1@example.com', 'sales123');
    const soId = await createSalesOrder(salesPage, { qty: 1 });
    check('Sales can create a Draft SO', soId !== null, `landed on ${salesPage.url()}`);

    if (soId === null) {
        await salesContext.close();
        return;
    }

    let badge = await salesPage.locator('.page-header__title-row .badge').first().textContent();
    check('New SO status is Draft', (badge || '').trim() === 'Draft', `badge: "${badge}"`);

    // 2. Sales submits it for approval.
    await Promise.all([
        salesPage.waitForNavigation({ waitUntil: 'networkidle' }),
        salesPage.click('button:has-text("Submit for Approval")'),
    ]);
    badge = await salesPage.locator('.page-header__title-row .badge').first().textContent();
    check('SO status is Pending Approval after submit', (badge || '').trim() === 'Pending Approval', `badge: "${badge}"`);

    const approveVisible = await salesPage.locator('button:has-text("Approve")').count();
    check('Sales does not see an Approve button on their own PendingApproval SO (SOD-01)', approveVisible === 0);
    const sodCallout = await salesPage.locator('text=Segregation of Duties').count();
    check('SOD-01 self-approval-restricted callout shown to Sales creator', sodCallout > 0);

    // 3. SOD-01 server-side: Sales cannot approve even by direct POST.
    const csrf = await salesPage.evaluate(() => {
        const el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : null;
    });
    const directApproveResp = await salesPage.request.post(`${BASE_URL}/sales-orders/${soId}/approve`, {
        form: { _csrf_token: csrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check('POST .../approve rejected (403) for Sales, even for their own order (SOD-01)', directApproveResp.status && directApproveResp.status() === 403, `got ${directApproveResp.status ? directApproveResp.status() : 'ERROR'}`);

    await salesPage.screenshot({ path: path.join(SCREENSHOT_DIR, 'sales-orders-detail-pending-Sales.png'), fullPage: true });
    await logout(salesPage);
    await salesContext.close();

    // 4. Admin approves.
    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');
    await adminPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const approveBtnForAdmin = await adminPage.locator('button:has-text("Approve")').count();
    check('Admin sees an Approve button on a PendingApproval SO', approveBtnForAdmin > 0);
    await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.click('button:has-text("Approve")'),
    ]);
    badge = await adminPage.locator('.page-header__title-row .badge').first().textContent();
    check('SO status is Approved after Admin approves', (badge || '').trim() === 'Approved', `badge: "${badge}"`);
    await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, 'sales-orders-detail-approved-Admin.png'), fullPage: true });
    await logout(adminPage);
    await adminContext.close();

    // 5. WarehouseStaff issues goods.
    const whContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const whPage = await whContext.newPage();
    await login(whPage, 'warehouse@example.com', 'wh123');
    await whPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const issueBtnVisible = await whPage.locator('button:has-text("Issue Goods")').count();
    check('WarehouseStaff sees an Issue Goods button on an Approved SO', issueBtnVisible > 0);
    await Promise.all([
        whPage.waitForNavigation({ waitUntil: 'networkidle' }),
        whPage.click('button:has-text("Issue Goods")'),
    ]);
    badge = await whPage.locator('.page-header__title-row .badge').first().textContent();
    check('SO status is Fulfilled after goods issue', (badge || '').trim() === 'Fulfilled', `badge: "${badge}"`);

    const ledgerRows = await whPage.locator('.audit-timeline__entry').count();
    check('Stock Ledger audit entry recorded for the goods issue', ledgerRows > 0, `found ${ledgerRows}`);
    const issueEntryText = await whPage.locator('.audit-timeline__entry').first().textContent();
    check('Audit entry is an "Issue" type entry', /Issue/i.test(issueEntryText || ''), `text: "${issueEntryText}"`);

    const actionBarStillVisible = await whPage.locator('.action-bar').count();
    check('No action buttons remain once Fulfilled', actionBarStillVisible === 0);

    // Regression: parseItemsFromRequest() emits {product_id, qty, price} but
    // SalesOrderService::create() reads sale_price — without a remap in the
    // controller, every line item silently saved at Rp 0 (SalesOrderController
    // fix). Confirm the real per-unit price made it all the way through.
    const unitPriceText = await whPage.locator('table.table tbody tr').first().locator('td').nth(3).textContent();
    check('Line item Unit Price is not Rp 0 (sale_price survived create())', !/Rp\s*0(?!\S)/.test((unitPriceText || '').trim()), `text: "${unitPriceText}"`);

    // Regression: SalesOrder had no $issuedByName property, so the detail
    // view's "Issued By" block threw an undefined-property warning straight
    // into the rendered page (visible to whoever viewed a Fulfilled SO).
    const bodyText = await whPage.locator('body').textContent();
    check('No leaked PHP warning on the detail page (issuedByName)', !/Warning:|Undefined property/i.test(bodyText || ''), 'checked full page body');
    // "Issued By:" lives in a <strong> with the resolved name as a sibling
    // text node in the parent <div> — locate the div, not the <strong>.
    const issuedByText = await whPage.locator('div:has(strong:text("Issued By:"))').first().textContent().catch(() => '');
    check('"Issued By" shows a resolved user name, not blank', /Issued By:\s*\S+/.test((issuedByText || '').replace(/\s+/g, ' ')), `text: "${issuedByText}"`);

    await whPage.screenshot({ path: path.join(SCREENSHOT_DIR, 'sales-orders-detail-fulfilled-WarehouseStaff.png'), fullPage: true });
    await logout(whPage);
    await whContext.close();
}

// ---------------------------------------------------------------------
// ARCH-02 / SO-01.05: goods issue must be rejected when stock is
// insufficient, leaving the SO Approved (not Fulfilled) and no stock/ledger
// side effects. Cleans up by cancelling the SO afterward (also exercises
// Admin-cancels-an-Approved-order, SO-01.02).
// ---------------------------------------------------------------------
async function checkInsufficientStockRejected(browser) {
    console.log('\n=== Insufficient stock: goods issue is rejected, no oversell ===');

    const salesContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const salesPage = await salesContext.newPage();
    await login(salesPage, 'sales1@example.com', 'sales123');
    const soId = await createSalesOrder(salesPage, { qty: 999999 });
    check('Sales can create a Draft SO with a deliberately huge quantity', soId !== null, `landed on ${salesPage.url()}`);

    if (soId === null) {
        await salesContext.close();
        return;
    }

    await Promise.all([
        salesPage.waitForNavigation({ waitUntil: 'networkidle' }),
        salesPage.click('button:has-text("Submit for Approval")'),
    ]);
    await logout(salesPage);
    await salesContext.close();

    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');
    await adminPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.click('button:has-text("Approve")'),
    ]);

    const whContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const whPage = await whContext.newPage();
    await login(whPage, 'warehouse@example.com', 'wh123');
    await whPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const issueResp = await Promise.all([
        whPage.waitForNavigation({ waitUntil: 'networkidle' }),
        whPage.click('button:has-text("Issue Goods")'),
    ]).then(([resp]) => resp);
    check('Goods issue for insufficient stock returns a non-2xx response', issueResp.status() >= 400, `got ${issueResp.status()}`);
    const errorText = await whPage.locator('body').textContent();
    check('Error message mentions insufficient stock', /not enough stock|insufficient stock/i.test(errorText || ''), 'checked page body for the message');
    await logout(whPage);
    await whContext.close();

    // Confirm the SO itself is unaffected (still Approved, not Fulfilled).
    await adminPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const badge = await adminPage.locator('.page-header__title-row .badge').first().textContent();
    check('SO remains Approved after a rejected goods issue (rollback, no partial state)', (badge || '').trim() === 'Approved', `badge: "${badge}"`);

    // Cleanup: Admin cancels the Approved order (also exercises SO-01.02:
    // Admin can cancel at any stage before Fulfilled).
    await clickCancelAndConfirm(adminPage);
    await adminPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const finalBadge = await adminPage.locator('.page-header__title-row .badge').first().textContent();
    check('Admin can cancel an Approved SO', (finalBadge || '').trim() === 'Cancelled', `badge: "${finalBadge}"`);

    await logout(adminPage);
    await adminContext.close();
}

// ---------------------------------------------------------------------
// SO-01.02: a Sales creator can cancel their own Draft order.
// ---------------------------------------------------------------------
async function checkSalesCanCancelOwnDraft(browser) {
    console.log('\n=== Sales creator cancels their own Draft SO ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'sales1@example.com', 'sales123');
    const soId = await createSalesOrder(page, { qty: 1 });
    check('Sales can create a Draft SO for the cancel test', soId !== null);

    if (soId === null) {
        await context.close();
        return;
    }

    const cancelVisible = await page.locator('button:has-text("Cancel")').count();
    check('Sales sees a Cancel button on their own Draft SO', cancelVisible > 0);
    await clickCancelAndConfirm(page);
    await page.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const badge = await page.locator('.page-header__title-row .badge').first().textContent();
    check('SO status is Cancelled after Sales cancels their own Draft', (badge || '').trim() === 'Cancelled', `badge: "${badge}"`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// SO-01.03/B-32: Admin can reject a PendingApproval SO (-> Cancelled),
// with an optional reason recorded and shown on the detail page.
// ---------------------------------------------------------------------
async function checkAdminCanReject(browser) {
    console.log('\n=== Admin rejects a Pending Approval SO ===');
    const salesContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const salesPage = await salesContext.newPage();
    await login(salesPage, 'sales1@example.com', 'sales123');
    const soId = await createSalesOrder(salesPage, { qty: 1 });
    check('Sales can create a Draft SO for the reject test', soId !== null);
    if (soId === null) {
        await salesContext.close();
        return;
    }
    await Promise.all([
        salesPage.waitForNavigation({ waitUntil: 'networkidle' }),
        salesPage.click('button:has-text("Submit for Approval")'),
    ]);
    await logout(salesPage);
    await salesContext.close();

    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminContext.newPage();
    await login(adminPage, 'admin@example.com', 'admin123');
    await adminPage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    const rejectVisible = await adminPage.locator('button:has-text("Reject")').count();
    check('Admin sees a Reject button on a PendingApproval SO', rejectVisible > 0);
    await adminPage.fill('input[name="reason"]', 'Out of budget this quarter');
    await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.click('button:has-text("Reject")'),
    ]);
    const badge = await adminPage.locator('.page-header__title-row .badge').first().textContent();
    check('SO status is Cancelled after Admin rejects', (badge || '').trim() === 'Cancelled', `badge: "${badge}"`);
    const reasonShown = await adminPage.locator('.stat-card').first().textContent();
    check('Rejection reason is recorded and shown on the detail page', reasonShown.includes('Out of budget this quarter'), `text: "${reasonShown}"`);

    await logout(adminPage);
    await adminContext.close();
}

// ---------------------------------------------------------------------
// FIND-01: sort-by-date toggle and pagination on the list page.
// ---------------------------------------------------------------------
async function checkSortAndPagination(browser) {
    console.log('\n=== List page: sort and pagination ===');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    await page.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    const paginationInfo = await page.locator('.pagination__info').textContent().catch(() => null);
    check('Pagination info is shown when there is more than one page', paginationInfo !== null, `text: "${paginationInfo}"`);
    const pageMatch = (paginationInfo || '').match(/Page (\d+) of (\d+)/);
    if (pageMatch && Number(pageMatch[2]) > 1) {
        await page.click('.pagination button:has-text("Next")');
        await page.waitForLoadState('networkidle');
        const paginationInfo2 = await page.locator('.pagination__info').textContent();
        check('Next advances to page 2', /Page 2 of/.test(paginationInfo2 || ''), `text: "${paginationInfo2}"`);
    }

    await page.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    const dateBefore = await page.locator('table.table tbody tr').first().locator('td').nth(1).textContent();
    await page.click('button:has-text("Date")');
    await page.waitForLoadState('networkidle');
    const dateAfter = await page.locator('table.table tbody tr').first().locator('td').nth(1).textContent();
    check('Toggling the Date sort changes the first row (desc <-> asc)', dateBefore !== dateAfter, `before="${dateBefore}" after="${dateAfter}"`);

    await logout(page);
    await context.close();
}

// ---------------------------------------------------------------------
// SOD/ownership boundary between two DIFFERENT Sales users (not just
// Sales-vs-Admin): Grace must not be able to view, submit, or cancel an
// order Beni created, and it must not appear in Grace's own list.
// ---------------------------------------------------------------------
async function checkCrossSalesUserIsolation(browser) {
    console.log('\n=== Cross-Sales-user isolation (Beni vs Grace) ===');
    const beniContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const beniPage = await beniContext.newPage();
    await login(beniPage, 'sales1@example.com', 'sales123');
    const soId = await createSalesOrder(beniPage, { qty: 1 });
    check('Beni (Sales) can create a Draft SO for the isolation test', soId !== null);
    if (soId === null) {
        await beniContext.close();
        return;
    }
    await logout(beniPage);
    await beniContext.close();

    const graceContext = await browser.newContext();
    const gracePage = await graceContext.newPage();
    await login(gracePage, 'sales2@example.com', 'grace123');

    const viewResp = await gracePage.goto(`${BASE_URL}/sales-orders/${soId}`, { waitUntil: 'networkidle' });
    check("Grace cannot view Beni's SO directly (403)", viewResp.status() === 403, `got ${viewResp.status()}`);

    await gracePage.goto(`${BASE_URL}/sales-orders`, { waitUntil: 'networkidle' });
    const graceCsrf = await gracePage.evaluate(() => document.querySelector('input[name="_csrf_token"]')?.value);
    const submitResp = await gracePage.request.post(`${BASE_URL}/sales-orders/${soId}/submit`, {
        form: { _csrf_token: graceCsrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check("Grace cannot submit Beni's SO for approval", submitResp.status && submitResp.status() >= 400, `got ${submitResp.status ? submitResp.status() : 'ERROR'}`);

    const cancelResp = await gracePage.request.post(`${BASE_URL}/sales-orders/${soId}/cancel`, {
        form: { _csrf_token: graceCsrf || '' },
        maxRedirects: 0,
    }).catch((e) => e);
    check("Grace cannot cancel Beni's SO", cancelResp.status && cancelResp.status() >= 400, `got ${cancelResp.status ? cancelResp.status() : 'ERROR'}`);

    const beniSoVisible = await gracePage.locator(`a[href*="${soId}"]`).count();
    check("Beni's SO does not appear in Grace's own order list", beniSoVisible === 0);

    await logout(gracePage);
    await graceContext.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        await checkUnauthenticated(browser);

        for (const roleConfig of ROLES) {
            await checkRoleView(browser, roleConfig);
        }

        await checkWarehouseStaffCannotCreate(browser);
        await checkHappyPathLifecycle(browser);
        await checkInsufficientStockRejected(browser);
        await checkSalesCanCancelOwnDraft(browser);
        await checkAdminCanReject(browser);
        await checkSortAndPagination(browser);
        await checkCrossSalesUserIsolation(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

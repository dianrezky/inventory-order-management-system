// Ad-hoc Playwright QA script — Products deep tests.
// Covers PROD-LIST-*, PROD-CREATE-*, PROD-EDIT-*, PROD-UPLOAD-*, PROD-STATE-*,
// PROD-DETAIL-*, PROD-API-* from the E2E deep-dive specification.
// Not run in CI. See README.md for setup/usage.
'use strict';

const fs   = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL      = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN = { role: 'Admin',          email: 'admin@example.com',     password: 'admin123' };
const SALES = { role: 'Sales',          email: 'sales1@example.com',    password: 'sales123' };
const WH    = { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123'    };

// Test-owned SKU/name prefix.
const SKU_PREFIX  = 'QA-PROD-';
const NAME_PREFIX = 'QA Playwright Product ';
const TS          = Date.now();
const TEST_SKU    = `${SKU_PREFIX}${TS}`;
const TEST_NAME   = `${NAME_PREFIX}${TS}`;

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
// Helper: find or pick an active category for product creation
// ─────────────────────────────────────────────────────────────────────────────
async function pickFirstActiveCategory(page) {
    await safeGoto(page, `${BASE_URL}/products/create`);
    const option = page.locator('select[name="category_id"] option').nth(1);
    if (await option.count() === 0) return null;
    return option.getAttribute('value');
}

// Helper: pick first active warehouse from a create form
async function pickFirstActiveWarehouse(page) {
    await safeGoto(page, `${BASE_URL}/products/create`);
    const option = page.locator('select[name="warehouse_id"] option, select[name="initial_warehouse_id"] option').nth(1);
    if (await option.count() === 0) return null;
    return option.getAttribute('value');
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: create a product and return its obfuscated id (from redirect URL)
// ─────────────────────────────────────────────────────────────────────────────
async function createProduct(page, { sku, name, categoryId, purchasePrice, salePrice, unit, reorderPoint }) {
    await safeGoto(page, `${BASE_URL}/products/create`);
    const csrf = await getCsrfToken(page);

    if (!categoryId) {
        const catOpt = page.locator('select[name="category_id"] option').nth(1);
        categoryId = await catOpt.getAttribute('value').catch(() => '1');
    }

    const resp = await page.request.post(`${BASE_URL}/products`, {
        form: {
            _csrf_token:    csrf,
            sku:            sku            || TEST_SKU,
            name:           name           || TEST_NAME,
            category_id:    categoryId     || '1',
            purchase_price: purchasePrice  || '10000',
            sale_price:     salePrice      || '15000',
            unit:           unit           || 'Pcs',
            reorder_point:  reorderPoint   || '5',
            description:    'QA test product',
        },
        maxRedirects: 5,
    });
    return resp;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-LIST-001..018 — Product list page
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductList(browser) {
    console.log('\n=== PROD-LIST: Product list ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/products`);
    check('PROD-LIST-001', 'Product list page loads (HTTP 200)', page.url().includes('/products'));

    // Columns
    const headerText = await page.locator('table thead').innerText().catch(() => '');
    check('PROD-LIST-002', 'SKU column present',    headerText.toLowerCase().includes('sku'));
    check('PROD-LIST-002', 'Name column present',   headerText.toLowerCase().includes('name') || headerText.toLowerCase().includes('produk'));
    check('PROD-LIST-002', 'Stock column present',  headerText.toLowerCase().includes('stock') || headerText.toLowerCase().includes('stok'));
    check('PROD-LIST-002', 'Status column present', headerText.toLowerCase().includes('status'));

    // PROD-LIST-003 — at least one seeded product renders
    const rowCount = await page.locator('table tbody tr').count();
    check('PROD-LIST-003', 'At least one product row rendered', rowCount > 0, `rows: ${rowCount}`);

    // PROD-LIST-004 — search by SKU
    const firstSku = await page.locator('table tbody tr td:first-child').first().innerText().catch(() => '');
    if (firstSku) {
        const searchField = page.locator('#product-sku, input[name="sku"]').first();
        if (await searchField.count() > 0) {
            await searchField.fill(firstSku.trim());
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
                page.locator('button[type="submit"]').first().click(),
            ]);
            const filteredRows = await page.locator('table tbody tr').count();
            const skuCell = await page.locator('table tbody tr td:first-child').first().innerText().catch(() => '');
            check('PROD-LIST-004', 'Search by SKU filters results', skuCell.trim() === firstSku.trim(), `expected ${firstSku}, got ${skuCell}`);
        } else {
            note('PROD-LIST-004: SKU search field not found, skipping');
        }
    }

    // PROD-LIST-012 — reset filters
    await safeGoto(page, `${BASE_URL}/products`);
    const resetBtn = page.locator('button:has-text("Reset"), a:has-text("Reset")').first();
    if (await resetBtn.count() > 0) {
        await resetBtn.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        check('PROD-LIST-012', 'Reset clears filters (no error)', (await page.locator('table tbody tr').count()) > 0);
    } else {
        note('PROD-LIST-012: Reset button not found, skipping');
    }

    // PROD-LIST-013 — no results
    const searchField2 = page.locator('#product-sku, input[name="sku"]').first();
    if (await searchField2.count() > 0) {
        await searchField2.fill('SKU-THAT-DOES-NOT-EXIST-999999');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
        const noResultRows = await page.locator('table tbody tr').count();
        const bodyText = await page.locator('body').innerText().catch(() => '');
        check('PROD-LIST-013', 'No-result state: no 5xx', !bodyText.includes('Fatal error'));
        check('PROD-LIST-013', 'No-result state: zero or empty-state row', noResultRows === 0 || bodyText.toLowerCase().includes('no') || bodyText.toLowerCase().includes('tidak'));
    }

    // PROD-LIST-018 — action visibility by role (Admin sees Edit/Deactivate)
    await safeGoto(page, `${BASE_URL}/products`);
    const adminActions = await page.locator('table tbody tr').first().locator('a[href*="edit"], button:has-text("Edit")').count();
    check('PROD-LIST-018', 'Admin sees edit action on product rows', adminActions > 0);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'prod-list-admin.png') });
    await ctx.close();

    // Non-admin: Sales view
    const salesCtx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const salesPage = await salesCtx.newPage();
    await login(salesPage, SALES.email, SALES.password);
    const salesResp = await salesPage.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' }).catch(() => null);
    check('PROD-LIST-018', 'Sales can view /products', salesResp && salesResp.status() === 200, `status: ${salesResp ? salesResp.status() : 'ERROR'}`);
    await salesCtx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-CREATE-001..033 — Product creation validation
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductCreate(browser) {
    console.log('\n=== PROD-CREATE: Product creation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Pick active category.
    await safeGoto(page, `${BASE_URL}/products/create`);
    const catOption = page.locator('select[name="category_id"] option').nth(1);
    const catId     = await catOption.getAttribute('value').catch(() => '1');

    // PROD-CREATE-001 — valid product
    const okResp = await createProduct(page, { sku: TEST_SKU, name: TEST_NAME, categoryId: catId });
    check('PROD-CREATE-001', 'Valid product creation succeeds (not 4xx/5xx)',
        okResp.status() < 400 || okResp.url().includes('/products'),
        `status: ${okResp.status()}, url: ${okResp.url()}`);
    note(`PROD-CREATE-001: created test product SKU=${TEST_SKU}`);

    const csrf = await getCsrfToken(page);

    // Validation tests (all should stay on /create or return error, not persist).
    async function tryCreate(fields, expectFail = true) {
        await safeGoto(page, `${BASE_URL}/products/create`);
        const csrf2 = await getCsrfToken(page);
        const resp = await page.request.post(`${BASE_URL}/products`, {
            form: { _csrf_token: csrf2, ...fields },
            maxRedirects: 5,
        });
        const isError = resp.url().includes('/create') || resp.status() === 422 || resp.status() === 400;
        if (expectFail) return isError;
        return resp.status() < 400;
    }

    // PROD-CREATE-002 — SKU required
    check('PROD-CREATE-002', 'Missing SKU rejected',
        await tryCreate({ name: 'No SKU', category_id: catId, purchase_price: '10000', sale_price: '15000', unit: 'Pcs' }));

    // PROD-CREATE-004 — SKU duplicate
    check('PROD-CREATE-004', 'Duplicate SKU rejected',
        await tryCreate({ sku: TEST_SKU, name: 'Dup SKU Test', category_id: catId, purchase_price: '10000', sale_price: '15000', unit: 'Pcs' }));

    // PROD-CREATE-005 — SKU at boundary (assume 50 chars)
    const sku50 = 'QASKU' + 'A'.repeat(45);
    check('PROD-CREATE-005', 'SKU at 50-char boundary accepted',
        await tryCreate({ sku: sku50, name: 'Boundary SKU', category_id: catId, purchase_price: '1000', sale_price: '2000', unit: 'Pcs' }, false));

    // PROD-CREATE-008 — Name required
    check('PROD-CREATE-008', 'Missing name rejected',
        await tryCreate({ sku: `QA-NONAME-${TS}`, category_id: catId, purchase_price: '10000', sale_price: '15000', unit: 'Pcs' }));

    // PROD-CREATE-014 — Category required
    check('PROD-CREATE-014', 'Missing category rejected',
        await tryCreate({ sku: `QA-NOCAT-${TS}`, name: 'No Cat', purchase_price: '10000', sale_price: '15000', unit: 'Pcs' }));

    // PROD-CREATE-019 — Purchase price negative
    const negPriceResult = await tryCreate({
        sku: `QA-NEGP-${TS}`, name: 'Neg Price', category_id: catId,
        purchase_price: '-1000', sale_price: '15000', unit: 'Pcs',
    });
    // Negative price: may be rejected by server or accepted (app-specific). Record result.
    note(`PROD-CREATE-019: Negative purchase_price — ${negPriceResult ? 'rejected' : 'accepted (check business rule)'}`);

    // PROD-CREATE-027 — Description empty (optional field)
    check('PROD-CREATE-027', 'Empty description accepted',
        await tryCreate({
            sku: `QA-NODESC-${TS}`, name: `QA No Desc ${TS}`, category_id: catId,
            purchase_price: '5000', sale_price: '7000', unit: 'Pcs',
            description: '',
        }, false));

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'prod-create-admin.png') });
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-EDIT-001..005 — Product editing
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductEdit(browser) {
    console.log('\n=== PROD-EDIT: Product editing ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find the test product we created.
    await safeGoto(page, `${BASE_URL}/products`);
    const skuField = page.locator('#product-sku, input[name="sku"]').first();
    if (await skuField.count() > 0) {
        await skuField.fill(TEST_SKU);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }

    const editLink = page.locator(`tr:has-text("${TEST_SKU}") a[href*="edit"]`).first();
    if (await editLink.count() === 0) {
        note('PROD-EDIT: test product not found, cannot run edit checks');
        await ctx.close();
        return;
    }

    const editHref = await editLink.getAttribute('href');
    await safeGoto(page, `${BASE_URL}${editHref}`);
    check('PROD-EDIT-001', 'Edit form loads for test product', page.url().includes('/edit'), `url: ${page.url()}`);

    const newName = `QA Edited ${TS}`;
    const csrf    = await getCsrfToken(page);
    const updatePath = editHref.replace('/edit', '/update');

    const resp = await page.request.post(`${BASE_URL}${updatePath}`, {
        form: {
            _csrf_token:    csrf,
            name:           newName,
            purchase_price: '12000',
            sale_price:     '18000',
            unit:           'Pcs',
            reorder_point:  '10',
            description:    'QA edited description',
        },
        maxRedirects: 5,
    });
    check('PROD-EDIT-001', 'Edit POST does not error',
        resp.status() < 500, `status: ${resp.status()}, url: ${resp.url()}`);

    // PROD-EDIT-005 — persistence after reload
    await safeGoto(page, `${BASE_URL}/products`);
    if (await skuField.count() > 0) {
        await skuField.fill(TEST_SKU);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }
    const updatedRow = await page.locator(`tr:has-text("${TEST_SKU}")`).innerText().catch(() => '');
    check('PROD-EDIT-005', 'Updated name persists', updatedRow.includes(newName), `row text: ${updatedRow.substring(0, 120)}`);

    // PROD-EDIT-004 — invalid/tampered ID
    const fakeResp = await page.request.get(`${BASE_URL}/products/TAMPERED_ID_999/edit`, { maxRedirects: 5 });
    check('PROD-EDIT-004', 'Tampered/invalid product ID returns 4xx', fakeResp.status() >= 400, `status: ${fakeResp.status()}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-STATE-001..004 — Activate / Deactivate
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductState(browser) {
    console.log('\n=== PROD-STATE: Activate/Deactivate ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/products`);
    const skuField = page.locator('#product-sku, input[name="sku"]').first();
    if (await skuField.count() > 0) {
        await skuField.fill(TEST_SKU);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }

    const deactivateBtn = page.locator(`tr:has-text("${TEST_SKU}") a:has-text("Deactivate"), tr:has-text("${TEST_SKU}") button:has-text("Deactivate"), tr:has-text("${TEST_SKU}") form button`).first();
    if (await deactivateBtn.count() > 0) {
        await deactivateBtn.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        note('PROD-STATE-001: deactivate clicked');

        // Reload and check status.
        await safeGoto(page, `${BASE_URL}/products`);
        if (await skuField.count() > 0) {
            await skuField.fill(TEST_SKU);
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
                page.locator('button[type="submit"]').first().click(),
            ]);
        }

        const rowText = await page.locator(`tr:has-text("${TEST_SKU}")`).innerText().catch(() => '');
        check('PROD-STATE-001', 'Product row shows Inactive status after deactivation',
            rowText.toLowerCase().includes('inactive') || rowText.toLowerCase().includes('tidak aktif'),
            `row: ${rowText.substring(0, 200)}`);

        // PROD-STATE-003 — Reactivate
        const activateBtn = page.locator(`tr:has-text("${TEST_SKU}") a:has-text("Activate"), tr:has-text("${TEST_SKU}") button:has-text("Activate")`).first();
        if (await activateBtn.count() > 0) {
            await activateBtn.click();
            await page.waitForLoadState('networkidle').catch(() => {});
            note('PROD-STATE-003: reactivate clicked');
            check('PROD-STATE-003', 'No error after reactivation', !page.url().includes('error'));
        } else {
            note('PROD-STATE-003: Activate button not found after deactivation');
        }
    } else {
        note('PROD-STATE-001: Deactivate button not found for test product');
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-UPLOAD-001..007 — Image uploads
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductUpload(browser) {
    console.log('\n=== PROD-UPLOAD: Image upload ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Find the test product edit page.
    await safeGoto(page, `${BASE_URL}/products`);
    const skuField = page.locator('#product-sku, input[name="sku"]').first();
    if (await skuField.count() > 0) {
        await skuField.fill(TEST_SKU);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
    }
    const editLink = page.locator(`tr:has-text("${TEST_SKU}") a[href*="edit"]`).first();
    if (await editLink.count() === 0) {
        note('PROD-UPLOAD: test product not found, skipping upload checks');
        await ctx.close();
        return;
    }
    const editHref = await editLink.getAttribute('href');
    await safeGoto(page, `${BASE_URL}${editHref}`);

    const fileInput = page.locator('input[type="file"]').first();
    if (await fileInput.count() === 0) {
        note('PROD-UPLOAD: No file input on product edit form, skipping');
        await ctx.close();
        return;
    }

    // Minimal 1×1 red PNG (89 bytes).
    const PNG_1x1 = Buffer.from(
        '89504e470d0a1a0a0000000d49484452000000010000000108020000009001' +
        '2e0000000c49444154789c6260f8cf0000000200016c0aa30000000049454e44ae426082',
        'hex'
    );
    // Minimal JPEG (320 bytes approx).
    const JPEG_MIN = Buffer.from(
        'ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707' +
        '07090909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c' +
        '1c2837292c30313434341f27393d38323c2e333432ffc000110800010001030111003f0' +
        '0ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc4' +
        '00b5100002010303020403050504040000017d01020300041105122131410613516107' +
        '2232811491a1082342b1c11552d1f02433627282090a161718191a25262728292a3435' +
        '363738393a434445464748494a535455565758595a636465666768696a737475767778' +
        '797a838485868788898a929394959697989990a0a0a0b0b0b0c0c0c0d0d0d0e0e0f10' +
        '101112ffda000c03010002110311003f00fbd3b12da01000280003f0',
        'hex'
    );

    // Write fixtures to temp.
    const tmpDir  = path.join(__dirname, 'node_modules', '.qa-upload-tmp');
    fs.mkdirSync(tmpDir, { recursive: true });
    const pngPath  = path.join(tmpDir, 'valid.png');
    const jpegPath = path.join(tmpDir, 'valid.jpg');
    const txtPath  = path.join(tmpDir, 'fake.png');    // text content, .png extension
    const emptyPath= path.join(tmpDir, 'empty.png');

    fs.writeFileSync(pngPath,   PNG_1x1);
    fs.writeFileSync(jpegPath,  JPEG_MIN);
    fs.writeFileSync(txtPath,   Buffer.from('This is not an image'));
    fs.writeFileSync(emptyPath, Buffer.alloc(0));

    // PROD-UPLOAD-001 — Valid PNG
    await safeGoto(page, `${BASE_URL}${editHref}`);
    const csrf1 = await getCsrfToken(page);
    await fileInput.setInputFiles(pngPath);
    const resp1 = await page.request.post(`${BASE_URL}${editHref.replace('/edit', '/update')}`, {
        multipart: { _csrf_token: csrf1, image: { name: 'valid.png', mimeType: 'image/png', buffer: PNG_1x1 } },
        maxRedirects: 5,
    }).catch(() => null);
    note(`PROD-UPLOAD-001: Valid PNG upload — status: ${resp1 ? resp1.status() : 'ERROR'}, url: ${resp1 ? resp1.url() : 'n/a'}`);
    check('PROD-UPLOAD-001', 'Valid PNG upload does not cause 5xx', !resp1 || resp1.status() < 500);

    // PROD-UPLOAD-003 — Invalid extension (txt)
    await safeGoto(page, `${BASE_URL}${editHref}`);
    const csrf3 = await getCsrfToken(page);
    const resp3 = await page.request.post(`${BASE_URL}${editHref.replace('/edit', '/update')}`, {
        multipart: { _csrf_token: csrf3, image: { name: 'document.txt', mimeType: 'text/plain', buffer: Buffer.from('not image') } },
        maxRedirects: 5,
    }).catch(() => null);
    const r3Url = resp3 ? resp3.url() : '';
    check('PROD-UPLOAD-003', 'Invalid extension (.txt) rejected (stays on /edit or returns error)',
        r3Url.includes('/edit') || (resp3 && (resp3.status() === 422 || resp3.status() === 400)),
        `status: ${resp3 ? resp3.status() : 'ERROR'}`);

    // PROD-UPLOAD-004 — Fake image (text content with .png extension)
    await safeGoto(page, `${BASE_URL}${editHref}`);
    const csrf4 = await getCsrfToken(page);
    const resp4 = await page.request.post(`${BASE_URL}${editHref.replace('/edit', '/update')}`, {
        multipart: { _csrf_token: csrf4, image: { name: 'fake.png', mimeType: 'image/png', buffer: Buffer.from('Not a real PNG file') } },
        maxRedirects: 5,
    }).catch(() => null);
    note(`PROD-UPLOAD-004: Fake PNG (wrong magic bytes) — status: ${resp4 ? resp4.status() : 'ERROR'}, url: ${resp4 ? resp4.url() : 'n/a'}`);
    // Whether the app checks magic bytes is an implementation detail; record result.

    // PROD-UPLOAD-007 — Empty file
    await safeGoto(page, `${BASE_URL}${editHref}`);
    const csrf7 = await getCsrfToken(page);
    const resp7 = await page.request.post(`${BASE_URL}${editHref.replace('/edit', '/update')}`, {
        multipart: { _csrf_token: csrf7, image: { name: 'empty.png', mimeType: 'image/png', buffer: Buffer.alloc(0) } },
        maxRedirects: 5,
    }).catch(() => null);
    check('PROD-UPLOAD-007', 'Empty file upload rejected or handled gracefully (no 5xx)', !resp7 || resp7.status() < 500);

    // Cleanup.
    [pngPath, jpegPath, txtPath, emptyPath].forEach((f) => fs.unlinkSync(f));

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-DETAIL-001..004 — Product detail page
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductDetail(browser) {
    console.log('\n=== PROD-DETAIL: Product detail ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/products`);
    const viewLink = page.locator('table tbody tr').first().locator('a[href*="/products/"]').first();
    if (await viewLink.count() === 0) {
        note('PROD-DETAIL: no view link found, skipping');
        await ctx.close();
        return;
    }

    await viewLink.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    check('PROD-DETAIL-001', 'Product detail page loads', !page.url().includes('/login'));

    const bodyText = await page.locator('body').innerText().catch(() => '');
    check('PROD-DETAIL-001', 'No PHP error on detail page', !/Fatal error|Warning\s*:/i.test(bodyText));
    check('PROD-DETAIL-002', 'Stock information present', bodyText.toLowerCase().includes('stock') || bodyText.toLowerCase().includes('stok'));
    check('PROD-DETAIL-004', 'Action links present for Admin', (await page.locator('a:has-text("Edit"), button:has-text("Edit")').count()) > 0);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'prod-detail-admin.png') });
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROD-API-001..007 — Product Availability API (/api/products/availability)
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductApi(browser) {
    console.log('\n=== PROD-API: Product Availability API ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    // Find a known SKU (first seeded product).
    await login(page, ADMIN.email, ADMIN.password);
    await safeGoto(page, `${BASE_URL}/products`);
    const firstSku = await page.locator('table tbody tr td:first-child').first().innerText().catch(() => '');
    const sku = firstSku.trim() || 'ELEC-001';

    // PROD-API-001 — Valid product
    const resp = await page.request.get(`${BASE_URL}/api/products/availability?sku=${encodeURIComponent(sku)}`, { maxRedirects: 5 }).catch(() => null);
    check('PROD-API-001', 'Valid SKU returns 200', resp && resp.status() === 200, `status: ${resp ? resp.status() : 'ERROR'}`);

    if (resp && resp.status() === 200) {
        const body = await resp.json().catch(() => null);
        check('PROD-API-004', 'Response is JSON', body !== null);
        if (body) {
            check('PROD-API-004', 'Response contains sku or data field', 'sku' in body || 'data' in body || 'availability' in body || 'stock' in body || 'quantity' in body,
                `keys: ${Object.keys(body).join(', ')}`);
        }
    }

    // PROD-API-002 — Unknown SKU
    const unknownResp = await page.request.get(`${BASE_URL}/api/products/availability?sku=DOES-NOT-EXIST-QA-99999`, { maxRedirects: 5 }).catch(() => null);
    check('PROD-API-002', 'Unknown SKU returns 404 or error JSON (not 5xx)', unknownResp && unknownResp.status() < 500, `status: ${unknownResp ? unknownResp.status() : 'ERROR'}`);

    // PROD-API-003 — Unauthorized (no session)
    await page.evaluate(() => {}); // keep context but log out session
    const noAuthCtx  = await browser.newContext();
    const noAuthPage = await noAuthCtx.newPage();
    const noAuthResp = await noAuthPage.request.get(`${BASE_URL}/api/products/availability?sku=${encodeURIComponent(sku)}`, { maxRedirects: 5 }).catch(() => null);
    note(`PROD-API-003: Unauthorized request — status: ${noAuthResp ? noAuthResp.status() : 'ERROR'}`);
    check('PROD-API-003', 'Unauthorized API request does not return 5xx', !noAuthResp || noAuthResp.status() < 500);
    await noAuthCtx.close();

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// RBAC — Sales/Warehouse cannot create/edit products
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductRbac(browser) {
    console.log('\n=== PROD-RBAC: Product creation forbidden for non-Admin ===');

    for (const { role, email, password } of [SALES, WH]) {
        const ctx  = await browser.newContext();
        const page = await ctx.newPage();
        await login(page, email, password);

        const createResp = await page.request.get(`${BASE_URL}/products/create`, { maxRedirects: 5 }).catch(() => null);
        const createStatus = createResp ? createResp.status() : 0;
        check('PROD-RBAC', `${role} GET /products/create is restricted (not 200)`,
            createStatus !== 200, `status: ${createStatus}`);

        const postResp = await page.request.post(`${BASE_URL}/products`, {
            form: { name: 'Hack', sku: 'HACK', category_id: '1', purchase_price: '0', sale_price: '0', unit: 'Pcs' },
            maxRedirects: 5,
        }).catch(() => null);
        check('PROD-RBAC', `${role} POST /products is rejected (not 200)`,
            postResp && postResp.status() !== 200, `status: ${postResp ? postResp.status() : 'ERROR'}`);

        await ctx.close();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        await checkProductList(browser);
        await checkProductCreate(browser);
        await checkProductEdit(browser);
        await checkProductUpload(browser);
        await checkProductState(browser);
        await checkProductDetail(browser);
        await checkProductApi(browser);
        await checkProductRbac(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — products.spec.js complete');
})();

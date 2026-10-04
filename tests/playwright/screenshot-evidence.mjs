/**
 * Screenshot Evidence Collector — IOMS (Full CRUD Coverage)
 * Usage:  cd tests/playwright && node screenshot-evidence.mjs
 * Output: docs/testing/screenshots/<##-feature>/
 */

import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..', '..');
const OUT  = path.join(ROOT, 'docs', 'testing', 'screenshots');
const BASE = 'http://localhost:8090';

const CREDS = {
  admin:     { email: 'admin@example.com',     password: 'admin123' },
  sales:     { email: 'sales1@example.com',    password: 'sales123' },
  warehouse: { email: 'warehouse@example.com', password: 'wh123'    },
  sales2:    { email: 'sales2@example.com',    password: 'grace123' },
};

let browser;
const contexts = {};

// ── helpers ──────────────────────────────────────────────────────────────────

function dir(folder) {
  const p = path.join(OUT, folder);
  mkdirSync(p, { recursive: true });
  return p;
}

async function ss(page, folder, name) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.screenshot({ path: path.join(dir(folder), `${name}.png`), fullPage: true });
  console.log(`  ✓ ${folder}/${name}.png`);
}

async function getPage(role) {
  if (!contexts[role]) {
    contexts[role] = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const pg = await contexts[role].newPage();
    pg.setDefaultTimeout(25_000);
    await pg.goto(`${BASE}/login`);
    await pg.locator('#email').fill(CREDS[role].email);
    await pg.locator('#password').fill(CREDS[role].password);
    await Promise.all([pg.waitForURL(/\/(dashboard|sales-dashboard)/), pg.locator('button[type="submit"]').click()]);
    await pg.close();
  }
  const pg = await contexts[role].newPage();
  pg.setDefaultTimeout(25_000);
  return pg;
}

async function go(page, url) {
  await page.goto(`${BASE}${url}`);
  await page.waitForLoadState('networkidle').catch(() => {});
}

/** Extract href from first row-actions view link (works even when menu is hidden) */
async function firstHref(page, feature) {
  // Primary: dedicated view link
  const link = page.locator(`a.row-actions__item--view`).first();
  if (await link.count() > 0) {
    return await link.getAttribute('href').catch(() => null);
  }
  // Fallback: any link to /{feature}/{token}
  const anyLink = page.locator(`a[href^="/${feature}/"]`).first();
  if (await anyLink.count() > 0) {
    return await anyLink.getAttribute('href').catch(() => null);
  }
  return null;
}

/** Get token from first item in a feature list page */
async function firstToken(page, feature) {
  const href = await firstHref(page, feature);
  if (!href) return null;
  const m = href.match(new RegExp(`/${feature}/([^/]+)`));
  return m ? m[1] : null;
}

/** Click a submit button by partial text or CSS selector, wait for navigation */
async function submit(page, selector) {
  let btn;
  if (selector.startsWith('#') || selector.startsWith('.') || selector.startsWith('button') || selector.startsWith('form')) {
    btn = page.locator(selector).first();
  } else {
    btn = page.locator(`button[type="submit"]:has-text("${selector}"), button.btn--submit`).first();
  }
  if (!await btn.isVisible({ timeout: 4000 }).catch(() => false)) return false;
  await Promise.all([page.waitForNavigation({ timeout: 15_000 }).catch(() => {}), btn.click()]);
  await page.waitForLoadState('networkidle').catch(() => {});
  return true;
}

/** Click a button (form action), wait for navigation */
async function clickBtn(page, text) {
  const btn = page.locator(`button:has-text("${text}"), form[action*="${text.toLowerCase()}"] button`).first();
  if (!await btn.isVisible({ timeout: 4000 }).catch(() => false)) return false;
  await Promise.all([page.waitForNavigation({ timeout: 15_000 }).catch(() => {}), btn.click()]);
  await page.waitForLoadState('networkidle').catch(() => {});
  return true;
}

/** Fill text input — prefers type=text over type=search */
async function fillText(page, name, value) {
  // Try type=text first
  let field = page.locator(`input[type="text"][name="${name}"]`).first();
  if (!await field.isVisible({ timeout: 2000 }).catch(() => false)) {
    field = page.locator(`input[name="${name}"]`).first();
  }
  if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
    await field.clear();
    await field.fill(value);
    return true;
  }
  return false;
}

/** Try deactivate then re-activate on a detail page */
async function deactActivate(page, folder, prefix) {
  const deact = page.locator('form[action*="deactivate"] button, button:has-text("Deactivate")').first();
  if (await deact.isVisible({ timeout: 3000 }).catch(() => false)) {
    await Promise.all([page.waitForNavigation({ timeout: 10_000 }).catch(() => {}), deact.click()]);
    await page.waitForLoadState('networkidle').catch(() => {});
    await ss(page, folder, `${prefix}-deactivated`);
    const act = page.locator('form[action*="activate"] button, button:has-text("Activate")').first();
    if (await act.isVisible({ timeout: 3000 }).catch(() => false)) {
      await Promise.all([page.waitForNavigation({ timeout: 10_000 }).catch(() => {}), act.click()]);
      await page.waitForLoadState('networkidle').catch(() => {});
      await ss(page, folder, `${prefix}-reactivated`);
    }
    return true;
  }
  return false;
}

// ── SECTION 01: AUTH ─────────────────────────────────────────────────────────
console.log('\n[01] AUTH');
browser = await chromium.launch({ headless: true });
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  pg.setDefaultTimeout(25_000);

  await go(pg, '/login');
  await ss(pg, '01-auth', '01-login-page');

  await pg.locator('#email').fill(CREDS.admin.email);
  await pg.locator('#password').fill('wrong-password');
  await Promise.all([
    pg.waitForResponse(r => r.url().includes('/login') && r.request().method() === 'POST'),
    pg.locator('button[type="submit"]').click(),
  ]);
  await ss(pg, '01-auth', '02-wrong-password-error');

  await pg.locator('#email').fill(CREDS.admin.email);
  await pg.locator('#password').fill(CREDS.admin.password);
  await Promise.all([pg.waitForURL(/\/dashboard/), pg.locator('button[type="submit"]').click()]);
  await ss(pg, '01-auth', '03-login-success-dashboard');
  await ctx.close();
}

// ── SECTION 02: DASHBOARD ────────────────────────────────────────────────────
console.log('\n[02] DASHBOARD');
{
  const [admin, sales, wh] = await Promise.all([getPage('admin'), getPage('sales'), getPage('warehouse')]);
  await go(admin, '/dashboard');  await ss(admin, '02-dashboard', '01-admin');
  await go(sales, '/dashboard');  await ss(sales, '02-dashboard', '02-sales-role');
  await go(wh,    '/dashboard');  await ss(wh,    '02-dashboard', '03-warehouse-role');
  await go(sales, '/sales-dashboard'); await ss(sales, '02-dashboard', '04-sales-special');
  await Promise.all([admin.close(), sales.close(), wh.close()]);
}

// ── helper for standard CRUD entity ─────────────────────────────────────────
async function crudEntity({ folder, feature, role = 'admin', createFields, createSubmit, editSubmit = 'Save changes' }) {
  const pg = await getPage(role);

  // LIST
  await go(pg, `/${feature}`);
  await ss(pg, folder, '01-list');

  // Search
  const searchInput = pg.locator('input[type="search"], input[name="search"], input[name="q"]').first();
  if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
    await ss(pg, folder, '02-list-with-search-bar');
  }

  // CREATE FORM
  await go(pg, `/${feature}/create`);
  await ss(pg, folder, '03-create-form-empty');

  // Fill create form
  for (const [name, value] of Object.entries(createFields)) {
    if (name.startsWith('select:')) {
      const selName = name.slice(7);
      const sel = pg.locator(`select[name="${selName}"]`).first();
      if (await sel.isVisible({ timeout: 2000 }).catch(() => false)) {
        const opts = await sel.locator('option').all();
        if (opts.length > 1) await sel.selectOption({ index: 1 });
      }
    } else if (name.startsWith('textarea:')) {
      const taName = name.slice(9);
      const ta = pg.locator(`textarea[name="${taName}"]`).first();
      if (await ta.isVisible({ timeout: 2000 }).catch(() => false)) await ta.fill(value);
    } else {
      await fillText(pg, name, value);
    }
  }
  await ss(pg, folder, '04-create-form-filled');
  await submit(pg, createSubmit);
  await ss(pg, folder, '05-created-result');

  // VIEW (show)
  await go(pg, `/${feature}`);
  const token = await firstToken(pg, feature);
  if (token) {
    await go(pg, `/${feature}/${token}`);
    await ss(pg, folder, '06-detail-view');

    // EDIT FORM
    await go(pg, `/${feature}/${token}/edit`);
    await ss(pg, folder, '07-edit-form');

    const firstTextField = pg.locator('input[type="text"]').first();
    if (await firstTextField.isVisible({ timeout: 2000 }).catch(() => false)) {
      const val = await firstTextField.inputValue().catch(() => '');
      await firstTextField.fill(val + ' (edited)');
    }
    await ss(pg, folder, '08-edit-form-filled');
    await submit(pg, editSubmit);
    await ss(pg, folder, '09-edit-saved');

    // DEACTIVATE / ACTIVATE
    await go(pg, `/${feature}/${token}`);
    await deactActivate(pg, folder, '10');
  }

  await pg.close();
}

// ── SECTION 03: PRODUCTS ─────────────────────────────────────────────────────
console.log('\n[03] PRODUCTS');
await crudEntity({
  folder: '03-products', feature: 'products',
  createFields: {
    'sku': 'TEST-SS-001',
    'name': 'Test Product Screenshot',
    'select:category_id': '',
    'select:unit': '',
    'reorder_point': '5',
  },
  createSubmit: 'Save Product',
  editSubmit: 'Save Product',
});
{
  // Extra: warehouse read-only view
  const wh = await getPage('warehouse');
  await go(wh, '/products');
  await ss(wh, '03-products', '11-list-warehouse-readonly-no-add');
  await wh.close();
}

// ── SECTION 04: CATEGORIES ───────────────────────────────────────────────────
console.log('\n[04] CATEGORIES');
await crudEntity({
  folder: '04-categories', feature: 'categories',
  createFields: {
    'name': 'Screenshot Test Category',
    'textarea:description': 'Category for evidence capture',
  },
  createSubmit: 'Create category',
  editSubmit: 'Save changes',
});
{
  // Categories use a modal for edit; get category ID via data-id attribute
  const pg = await getPage('admin');
  await go(pg, '/categories');
  const editBtn = pg.locator('button.js-edit-category[data-id]').first();
  const catId = await editBtn.getAttribute('data-id').catch(() => null);
  if (catId) {
    await go(pg, `/categories/${catId}`);
    await ss(pg, '04-categories', '06-detail-view');
    await go(pg, `/categories/${catId}/edit`);
    await ss(pg, '04-categories', '07-edit-form');
    const firstTextField = pg.locator('input[type="text"]').first();
    if (await firstTextField.isVisible({ timeout: 2000 }).catch(() => false)) {
      const val = await firstTextField.inputValue().catch(() => '');
      await firstTextField.fill(val + ' (edited)');
    }
    await ss(pg, '04-categories', '08-edit-form-filled');
    await submit(pg, 'Save changes');
    await ss(pg, '04-categories', '09-edit-saved');
    await go(pg, `/categories/${catId}`);
    await deactActivate(pg, '04-categories', '10');
  }
  await pg.close();
}
{
  // Hard delete
  const pg = await getPage('admin');
  await go(pg, '/categories');
  const deleteBtn = pg.locator('form[action*="/delete"] button, button:has-text("Delete")').first();
  if (await deleteBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await ss(pg, '04-categories', '11-delete-button-in-list');
    await Promise.all([pg.waitForNavigation({ timeout: 10_000 }).catch(() => {}), deleteBtn.click()]);
    await pg.waitForLoadState('networkidle').catch(() => {});
    await ss(pg, '04-categories', '12-deleted-result');
  }
  // CSV export button
  await go(pg, '/categories');
  const exportBtn = pg.locator('a[href*="export"], button:has-text("Export")').first();
  if (await exportBtn.isVisible({ timeout: 3000 }).catch(() => false))
    await ss(pg, '04-categories', '13-csv-export-button');
  await pg.close();
}

// ── SECTION 05: WAREHOUSES ───────────────────────────────────────────────────
console.log('\n[05] WAREHOUSES');
await crudEntity({
  folder: '05-warehouses', feature: 'warehouses',
  createFields: { 'name': 'Screenshot Warehouse', 'location': 'Test Location' },
  createSubmit: 'Save',
  editSubmit: 'Save',
});

// ── SECTION 06: SUPPLIERS ────────────────────────────────────────────────────
console.log('\n[06] SUPPLIERS');
await crudEntity({
  folder: '06-suppliers', feature: 'suppliers',
  createFields: { 'name': 'PT Screenshot Supplier', 'email': 'supplier@screenshot.test' },
  createSubmit: 'Save',
  editSubmit: 'Save',
});

// ── SECTION 07: CUSTOMERS ────────────────────────────────────────────────────
console.log('\n[07] CUSTOMERS');
await crudEntity({
  folder: '07-customers', feature: 'customers',
  createFields: { 'name': 'Budi Screenshot', 'email': 'budi@screenshot.test' },
  createSubmit: 'Save',
  editSubmit: 'Save',
});

// ── SECTION 08: PURCHASE ORDERS ──────────────────────────────────────────────
console.log('\n[08] PURCHASE ORDERS');
{
  const admin = await getPage('admin');
  const wh    = await getPage('warehouse');

  // List
  await go(admin, '/purchase-orders');
  await ss(admin, '08-purchase-orders', '01-list');

  // Create form
  await go(admin, '/purchase-orders/create');
  await ss(admin, '08-purchase-orders', '02-create-form');

  // Find Draft PO → submit (btn--tertiary is the view link class for PO/SO)
  await go(admin, '/purchase-orders');
  const draftLink = admin.locator('table tbody tr:has-text("Draft") a.btn--tertiary').first();
  let draftId = null;
  if (await draftLink.count() > 0) {
    draftId = (await draftLink.getAttribute('href') ?? '').match(/\/purchase-orders\/([^/]+)/)?.[1] ?? null;
  }
  if (!draftId) {
    // fallback: any first token
    draftId = await firstToken(admin, 'purchase-orders');
  }

  if (draftId) {
    await go(admin, `/purchase-orders/${draftId}`);
    await ss(admin, '08-purchase-orders', '03-detail-draft');

    // Submit button
    const submitBtn = admin.locator('form[action*="submit"] button').first();
    if (await submitBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await ss(admin, '08-purchase-orders', '04-submit-button-visible');
      await Promise.all([admin.waitForNavigation({ timeout: 15_000 }).catch(() => {}), submitBtn.click()]);
      await admin.waitForLoadState('networkidle').catch(() => {});
      await ss(admin, '08-purchase-orders', '05-submitted-result');
    }

    // Cancel button on same or different PO
    await go(admin, `/purchase-orders/${draftId}`);
    const cancelBtn = admin.locator('form[action*="cancel"] button').first();
    if (await cancelBtn.isVisible({ timeout: 3000 }).catch(() => false))
      await ss(admin, '08-purchase-orders', '06-cancel-button-visible');
  }

  // Receive goods form — find a submitted/approved PO
  await go(admin, '/purchase-orders');
  const submittedHref = await (async () => {
    const link = admin.locator('table tbody tr:has-text("Submitted") a.btn--tertiary, table tbody tr:has-text("Ordered") a.btn--tertiary, table tbody tr:has-text("Processing") a.btn--tertiary').first();
    if (await link.count() > 0) return link.getAttribute('href').catch(() => null);
    return null;
  })();
  const submittedId = submittedHref?.match(/\/purchase-orders\/([^/]+)/)?.[1];
  if (submittedId) {
    await go(admin, `/purchase-orders/${submittedId}`);
    await ss(admin, '08-purchase-orders', '07-detail-submitted-receive-button');
    await go(admin, `/purchase-orders/${submittedId}/receive`);
    await ss(admin, '08-purchase-orders', '08-receive-goods-form');
  }

  // Warehouse role
  await go(wh, '/purchase-orders');
  await ss(wh, '08-purchase-orders', '09-list-warehouse-role');
  const whId = await firstToken(wh, 'purchase-orders');
  if (whId) {
    await go(wh, `/purchase-orders/${whId}`);
    await ss(wh, '08-purchase-orders', '10-detail-warehouse-role');
  }

  await admin.close(); await wh.close();
}

// ── SECTION 09: SALES ORDERS ─────────────────────────────────────────────────
console.log('\n[09] SALES ORDERS');
{
  const admin  = await getPage('admin');
  const sales  = await getPage('sales');
  const wh     = await getPage('warehouse');

  // List per role
  await go(admin, '/sales-orders');  await ss(admin, '09-sales-orders', '01-list-admin');
  await go(sales, '/sales-orders');  await ss(sales, '09-sales-orders', '02-list-sales-role');
  await go(wh,    '/sales-orders');  await ss(wh,    '09-sales-orders', '03-list-warehouse-role');

  // Create form
  await go(sales, '/sales-orders/create');
  await ss(sales, '09-sales-orders', '04-create-form');

  // Draft SO → submit (btn--tertiary is the view link class for SO)
  await go(sales, '/sales-orders');
  const draftHref = await (async () => {
    const l = sales.locator('table tbody tr:has-text("Draft") a.btn--tertiary').first();
    if (await l.count() > 0) return l.getAttribute('href').catch(() => null);
    return firstHref(sales, 'sales-orders');
  })();
  const draftId = draftHref?.match(/\/sales-orders\/([^/]+)/)?.[1];
  if (draftId) {
    await go(sales, `/sales-orders/${draftId}`);
    await ss(sales, '09-sales-orders', '05-detail-draft-with-submit');
    const submitBtn = sales.locator('form[action*="submit"] button').first();
    if (await submitBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await Promise.all([sales.waitForNavigation({ timeout: 15_000 }).catch(() => {}), submitBtn.click()]);
      await sales.waitForLoadState('networkidle').catch(() => {});
      await ss(sales, '09-sales-orders', '06-submitted-by-sales');
    }
  }

  // Submitted SO → admin approves / rejects
  await go(admin, '/sales-orders');
  const subHref1 = await (async () => {
    const l = admin.locator('table tbody tr:has-text("Pending Approval") a.btn--tertiary, table tbody tr:has-text("Submitted") a.btn--tertiary').first();
    if (await l.count() > 0) return l.getAttribute('href').catch(() => null);
    return null;
  })();
  const subId1 = subHref1?.match(/\/sales-orders\/([^/]+)/)?.[1];
  if (subId1) {
    await go(admin, `/sales-orders/${subId1}`);
    await ss(admin, '09-sales-orders', '07-detail-submitted-approve-reject-buttons');

    // Reject
    const rejectBtn = admin.locator('form[action*="reject"] button').first();
    if (await rejectBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await Promise.all([admin.waitForNavigation({ timeout: 15_000 }).catch(() => {}), rejectBtn.click()]);
      await admin.waitForLoadState('networkidle').catch(() => {});
      await ss(admin, '09-sales-orders', '08-so-rejected');
    }
  }

  // Another Submitted → Approve
  await go(admin, '/sales-orders');
  const subHref2 = await (async () => {
    const l = admin.locator('table tbody tr:has-text("Pending Approval") a.btn--tertiary, table tbody tr:has-text("Submitted") a.btn--tertiary').first();
    if (await l.count() > 0) return l.getAttribute('href').catch(() => null);
    return null;
  })();
  const subId2 = subHref2?.match(/\/sales-orders\/([^/]+)/)?.[1];
  if (subId2) {
    await go(admin, `/sales-orders/${subId2}`);
    await ss(admin, '09-sales-orders', '09-detail-for-approval');
    const approveBtn = admin.locator('form[action*="approve"] button').first();
    if (await approveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await Promise.all([admin.waitForNavigation({ timeout: 15_000 }).catch(() => {}), approveBtn.click()]);
      await admin.waitForLoadState('networkidle').catch(() => {});
      await ss(admin, '09-sales-orders', '10-so-approved');
    }
  }

  // Approved SO → warehouse issues goods
  await go(wh, '/sales-orders');
  const approvedHref = await (async () => {
    const l = wh.locator('table tbody tr:has-text("Approved") a.btn--tertiary').first();
    if (await l.count() > 0) return l.getAttribute('href').catch(() => null);
    return null;
  })();
  const approvedId = approvedHref?.match(/\/sales-orders\/([^/]+)/)?.[1];
  if (approvedId) {
    await go(wh, `/sales-orders/${approvedId}`);
    await ss(wh, '09-sales-orders', '11-detail-approved-issue-button');
    const issueBtn = wh.locator('form[action*="issue"] button').first();
    if (await issueBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await Promise.all([wh.waitForNavigation({ timeout: 15_000 }).catch(() => {}), issueBtn.click()]);
      await wh.waitForLoadState('networkidle').catch(() => {});
      await ss(wh, '09-sales-orders', '12-goods-issued');
    }
  }

  // SOD: sales cannot approve own SO
  await go(sales, '/sales-orders');
  const ownHref = await (async () => {
    const l = sales.locator('table tbody tr:has-text("Pending Approval") a.btn--tertiary, table tbody tr:has-text("Submitted") a.btn--tertiary').first();
    if (await l.count() > 0) return l.getAttribute('href').catch(() => null);
    return firstHref(sales, 'sales-orders');
  })();
  const ownId = ownHref?.match(/\/sales-orders\/([^/]+)/)?.[1];
  if (ownId) {
    await go(sales, `/sales-orders/${ownId}`);
    await ss(sales, '09-sales-orders', '13-sod-sales-no-approve-button');
  }

  await admin.close(); await sales.close(); await wh.close();
}

// ── SECTION 10: STOCK LEDGER ─────────────────────────────────────────────────
console.log('\n[10] STOCK LEDGER');
{
  const pg = await getPage('admin');
  await go(pg, '/stock-ledger');
  await ss(pg, '10-stock-ledger', '01-all-entries');

  // Filter by warehouse
  const whSel = pg.locator('select[name="warehouse_id"]').first();
  if (await whSel.isVisible({ timeout: 2000 }).catch(() => false)) {
    const opts = await whSel.locator('option').all();
    if (opts.length > 1) {
      await whSel.selectOption({ index: 1 });
      await pg.waitForLoadState('networkidle').catch(() => {});
      await ss(pg, '10-stock-ledger', '02-filtered-by-warehouse');
    }
  }

  // Filter by product
  const prodSel = pg.locator('select[name="product_id"]').first();
  if (await prodSel.isVisible({ timeout: 2000 }).catch(() => false)) {
    const opts = await prodSel.locator('option').all();
    if (opts.length > 1) {
      await prodSel.selectOption({ index: 1 });
      await pg.waitForLoadState('networkidle').catch(() => {});
      await ss(pg, '10-stock-ledger', '03-filtered-by-product');
    }
  }

  // Filter by type (if exists)
  const typeSel = pg.locator('select[name="type"], select[name="movement_type"]').first();
  if (await typeSel.isVisible({ timeout: 2000 }).catch(() => false)) {
    const opts = await typeSel.locator('option').all();
    if (opts.length > 1) {
      await typeSel.selectOption({ index: 1 });
      await pg.waitForLoadState('networkidle').catch(() => {});
      await ss(pg, '10-stock-ledger', '04-filtered-by-movement-type');
    }
  }

  await pg.close();
}

// ── SECTION 11: REPORTS ──────────────────────────────────────────────────────
console.log('\n[11] REPORTS');
{
  const pg = await getPage('admin');
  await go(pg, '/reports');
  await ss(pg, '11-reports', '01-reports-page');

  // Date filter
  const dateFrom = pg.locator('input[type="date"], input[name="date_from"], input[name="from_date"]').first();
  if (await dateFrom.isVisible({ timeout: 2000 }).catch(() => false)) {
    await dateFrom.fill('2026-01-01');
    const dateTo = pg.locator('input[name="date_to"], input[name="to_date"]').first();
    if (await dateTo.isVisible({ timeout: 2000 }).catch(() => false)) await dateTo.fill('2026-12-31');
    const filterBtn = pg.locator('button[type="submit"]').first();
    if (await filterBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await Promise.all([pg.waitForNavigation({ timeout: 15_000 }).catch(() => {}), filterBtn.click()]);
      await pg.waitForLoadState('networkidle').catch(() => {});
      await ss(pg, '11-reports', '02-filtered-by-date');
    }
  }

  // Export buttons
  const exportBtns = await pg.locator('button[type="submit"], a[href*="export"]').all();
  if (exportBtns.length > 0) {
    await ss(pg, '11-reports', '03-export-buttons-visible');
  }

  await pg.close();
}

// ── SECTION 12: USERS & RBAC ─────────────────────────────────────────────────
console.log('\n[12] USERS & RBAC');
{
  const admin = await getPage('admin');
  const sales = await getPage('sales');
  const wh    = await getPage('warehouse');

  // List
  await go(admin, '/users');
  await ss(admin, '12-users-rbac', '01-user-list');

  // Create form
  await go(admin, '/users/create');
  await ss(admin, '12-users-rbac', '02-create-form');
  await fillText(admin, 'name', 'Test User Screenshot');
  await fillText(admin, 'email', `testss${Date.now()}@example.com`);
  const passField = admin.locator('input[name="password"]').first();
  if (await passField.isVisible({ timeout: 2000 }).catch(() => false)) await passField.fill('Password123!');
  await ss(admin, '12-users-rbac', '03-create-form-filled');
  await submit(admin, 'Save');
  await ss(admin, '12-users-rbac', '04-user-created');

  // View and edit first user
  await go(admin, '/users');
  const uid = await firstToken(admin, 'users');
  if (uid) {
    await go(admin, `/users/${uid}`);
    await ss(admin, '12-users-rbac', '05-user-detail');

    await go(admin, `/users/${uid}/edit`);
    await ss(admin, '12-users-rbac', '06-edit-form');
    const nameField = admin.locator('input[type="text"][name="name"]').first();
    if (await nameField.isVisible({ timeout: 2000 }).catch(() => false)) {
      const orig = await nameField.inputValue();
      await nameField.fill(orig + ' (upd)');
    }
    await ss(admin, '12-users-rbac', '07-edit-form-filled');
    await submit(admin, 'Save');
    await ss(admin, '12-users-rbac', '08-edit-saved');

    // Deactivate / Activate (skip the admin's own account)
    await go(admin, `/users/${uid}`);
    await deactActivate(admin, '12-users-rbac', '09');
  }

  // 403 screens
  for (const [role, pg, routes] of [
    ['sales', sales, ['/users', '/users/create', '/warehouses/create']],
    ['warehouse', wh,  ['/users', '/sales-orders/create']],
  ]) {
    for (const route of routes) {
      await go(pg, route);
      await ss(pg, '12-users-rbac', `rbac-403-${role}${route.replace(/\//g, '-')}`);
    }
  }

  await admin.close(); await sales.close(); await wh.close();
}

// ── SECTION 13: NOTIFICATIONS ────────────────────────────────────────────────
console.log('\n[13] NOTIFICATIONS');
{
  const pg = await getPage('admin');
  await go(pg, '/notifications');
  await ss(pg, '13-notifications', '01-notification-list');

  const markBtn = pg.locator('form[action*="mark-all"] button, button:has-text("Mark all"), button:has-text("Mark All")').first();
  if (await markBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await ss(pg, '13-notifications', '02-mark-all-read-button-visible');
    await Promise.all([pg.waitForNavigation({ timeout: 10_000 }).catch(() => {}), markBtn.click()]);
    await pg.waitForLoadState('networkidle').catch(() => {});
    await ss(pg, '13-notifications', '03-after-mark-all-read');
  }
  await pg.close();
}

// ── SECTION 14: PROFILE ──────────────────────────────────────────────────────
console.log('\n[14] PROFILE');
{
  const admin = await getPage('admin');
  const sales = await getPage('sales');

  await go(admin, '/my-profile');
  await ss(admin, '14-profile', '01-admin-profile');

  const nameField = admin.locator('input[type="text"][name="name"]').first();
  if (await nameField.isVisible({ timeout: 2000 }).catch(() => false)) {
    const orig = await nameField.inputValue();
    await nameField.fill(orig + ' (updated)');
    await ss(admin, '14-profile', '02-profile-edit-filled');
    await submit(admin, 'Save');
    await ss(admin, '14-profile', '03-profile-saved');
    // Restore
    await go(admin, '/my-profile');
    const nf2 = admin.locator('input[type="text"][name="name"]').first();
    if (await nf2.isVisible({ timeout: 2000 }).catch(() => false)) {
      await nf2.fill(orig);
      await submit(admin, 'Save');
    }
  }

  await go(sales, '/my-profile');
  await ss(sales, '14-profile', '04-sales-profile');

  await admin.close(); await sales.close();
}

// ── cleanup ──────────────────────────────────────────────────────────────────
for (const ctx of Object.values(contexts)) await ctx.close().catch(() => {});
await browser.close();

// Index
const folders = [
  ['01', '01-auth', 'Login page, wrong password, successful login'],
  ['02', '02-dashboard', 'Admin / Sales / Warehouse / Sales-special dashboards'],
  ['03', '03-products', 'List, search, create (empty→filled→result), view, edit, deactivate/activate, warehouse read-only'],
  ['04', '04-categories', 'List, create, view, edit, deactivate/activate, hard delete, CSV export'],
  ['05', '05-warehouses', 'List, create, view (with stock), edit, deactivate/activate'],
  ['06', '06-suppliers', 'List, create, view, edit, deactivate/activate'],
  ['07', '07-customers', 'List, create, view, edit, deactivate/activate'],
  ['08', '08-purchase-orders', 'List, create form, detail (draft), submit, cancel, receive-goods form, warehouse role'],
  ['09', '09-sales-orders', 'List (3 roles), create, draft+submit, approve, reject, issue goods, SOD constraint'],
  ['10', '10-stock-ledger', 'All entries, filtered by warehouse / product / type'],
  ['11', '11-reports', 'Reports page, date filter, export buttons'],
  ['12', '12-users-rbac', 'List, create, view, edit, deactivate/activate; 403 guards per role'],
  ['13', '13-notifications', 'Notification list, mark-all-read, after mark-all-read'],
  ['14', '14-profile', 'Admin profile, edit, saved; Sales profile'],
];

writeFileSync(path.join(OUT, 'README.md'), [
  '# Screenshot Evidence Index',
  '',
  `Generated: ${new Date().toISOString()}`,
  `App: ${BASE}`,
  '',
  '| # | Folder | Coverage |',
  '|---|---|---|',
  ...folders.map(([n, f, d]) => `| ${n} | ${f} | ${d} |`),
  '',
  '## Regenerate',
  '```bash',
  'cd tests/playwright',
  'node screenshot-evidence.mjs',
  '```',
].join('\n'));

console.log('\n✅ Done! Screenshots saved to docs/testing/screenshots/');

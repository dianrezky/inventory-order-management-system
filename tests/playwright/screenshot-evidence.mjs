/**
 * Screenshot Evidence Collector — IOMS
 * Navigates the running app at http://localhost:8090 and captures
 * organised screenshots for the project evidence checklist (B-01 – B-141).
 *
 * Usage:
 *   node tests/playwright/screenshot-evidence.mjs
 *
 * Output: docs/testing/screenshots/<feature>/
 */

import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..', '..');
const OUT  = path.join(ROOT, 'docs', 'testing', 'screenshots');
const BASE = 'http://localhost:8090';

// ── helpers ─────────────────────────────────────────────────────────────────
let browser, adminCtx, salesCtx, warehouseCtx;

function dir(folder) {
  const p = path.join(OUT, folder);
  mkdirSync(p, { recursive: true });
  return p;
}

async function ss(page, folder, name) {
  const d = dir(folder);
  const file = path.join(d, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  console.log(`  ✓ ${folder}/${name}.png`);
}

async function login(context, email, password) {
  const page = await context.newPage();
  page.setDefaultTimeout(20_000);
  await page.goto(`${BASE}/login`);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await Promise.all([
    page.waitForURL(/\/(dashboard|sales-dashboard)/),
    page.locator('button[type="submit"]').click(),
  ]);
  return page;
}

async function nav(page, url) {
  await page.goto(`${BASE}${url}`);
  await page.waitForLoadState('networkidle');
}

// ── main ────────────────────────────────────────────────────────────────────
browser = await chromium.launch({ headless: true });

// ── 1. AUTH ──────────────────────────────────────────────────────────────────
console.log('\n[1/14] AUTH — login / logout');
{
  const demoCtx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const pg = await demoCtx.newPage();
  pg.setDefaultTimeout(20_000);
  await pg.goto(`${BASE}/login`);
  await ss(pg, '01-auth', '01-login-page');

  // wrong password
  await pg.locator('#email').fill('admin@example.com');
  await pg.locator('#password').fill('wrongpassword');
  await Promise.all([
    pg.waitForResponse(r => r.url().includes('/login') && r.request().method() === 'POST'),
    pg.locator('button[type="submit"]').click(),
  ]);
  await ss(pg, '01-auth', '02-login-wrong-password');

  // correct login
  await pg.locator('#email').fill('admin@example.com');
  await pg.locator('#password').fill('admin123');
  await Promise.all([
    pg.waitForURL(/\/dashboard/),
    pg.locator('button[type="submit"]').click(),
  ]);
  await ss(pg, '01-auth', '03-admin-dashboard-after-login');
  await demoCtx.close(); // discard — fresh contexts below
}

// Fresh contexts for each role (no leftover session state)
adminCtx     = await browser.newContext({ viewport: { width: 1280, height: 800 } });
salesCtx     = await browser.newContext({ viewport: { width: 1280, height: 800 } });
warehouseCtx = await browser.newContext({ viewport: { width: 1280, height: 800 } });

const adminPage     = await login(adminCtx,     'admin@example.com',     'admin123');
const salesPage     = await login(salesCtx,     'sales1@example.com',    'sales123');
const warehousePage = await login(warehouseCtx, 'warehouse@example.com', 'wh123');

// ── 2. DASHBOARD ─────────────────────────────────────────────────────────────
console.log('\n[2/14] DASHBOARD');
{
  await nav(adminPage, '/dashboard');
  await ss(adminPage, '02-dashboard', '01-admin-dashboard');
  await nav(salesPage, '/dashboard');
  await ss(salesPage, '02-dashboard', '02-sales-dashboard');
  await nav(warehousePage, '/dashboard');
  await ss(warehousePage, '02-dashboard', '03-warehouse-dashboard');
  // Sales dashboard (special route)
  await nav(salesPage, '/sales-dashboard');
  await ss(salesPage, '02-dashboard', '04-sales-special-dashboard');
}

// ── 3. PRODUCTS ───────────────────────────────────────────────────────────────
console.log('\n[3/14] PRODUCTS');
{
  await nav(adminPage, '/products');
  await ss(adminPage, '03-products', '01-product-list');

  // open add form
  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '03-products', '02-product-create-form');
    await adminPage.goBack();
  }

  // open first product detail
  const firstRow = adminPage.locator('table tbody tr').first();
  if (await firstRow.isVisible()) {
    const detailLink = firstRow.locator('a').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await adminPage.waitForLoadState('networkidle');
      await ss(adminPage, '03-products', '03-product-detail');
      await adminPage.goBack();
    }
  }

  // warehouse cannot see add button
  await nav(warehousePage, '/products');
  await ss(warehousePage, '03-products', '04-product-list-warehouse-role');
}

// ── 4. CATEGORIES ─────────────────────────────────────────────────────────────
console.log('\n[4/14] CATEGORIES');
{
  await nav(adminPage, '/categories');
  await ss(adminPage, '04-categories', '01-category-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '04-categories', '02-category-create-form');
    await adminPage.goBack();
  }
}

// ── 5. WAREHOUSES ─────────────────────────────────────────────────────────────
console.log('\n[5/14] WAREHOUSES');
{
  await nav(adminPage, '/warehouses');
  await ss(adminPage, '05-warehouses', '01-warehouse-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '05-warehouses', '02-warehouse-create-form');
    await adminPage.goBack();
  }

  const firstRow = adminPage.locator('table tbody tr').first();
  if (await firstRow.isVisible()) {
    const detailLink = firstRow.locator('a').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await adminPage.waitForLoadState('networkidle');
      await ss(adminPage, '05-warehouses', '03-warehouse-detail-stock');
      await adminPage.goBack();
    }
  }
}

// ── 6. SUPPLIERS ──────────────────────────────────────────────────────────────
console.log('\n[6/14] SUPPLIERS');
{
  await nav(adminPage, '/suppliers');
  await ss(adminPage, '06-suppliers', '01-supplier-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '06-suppliers', '02-supplier-create-form');
    await adminPage.goBack();
  }
}

// ── 7. CUSTOMERS ──────────────────────────────────────────────────────────────
console.log('\n[7/14] CUSTOMERS');
{
  await nav(adminPage, '/customers');
  await ss(adminPage, '07-customers', '01-customer-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '07-customers', '02-customer-create-form');
    await adminPage.goBack();
  }
}

// ── 8. PURCHASE ORDERS ────────────────────────────────────────────────────────
console.log('\n[8/14] PURCHASE ORDERS');
{
  await nav(adminPage, '/purchase-orders');
  await ss(adminPage, '08-purchase-orders', '01-po-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("New"), a:has-text("Create")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '08-purchase-orders', '02-po-create-form');
    await adminPage.goBack();
  }

  // open a PO detail — look for first view/detail link
  await nav(adminPage, '/purchase-orders');
  const rows = adminPage.locator('table tbody tr');
  const count = await rows.count();
  if (count > 0) {
    const link = rows.first().locator('a').first();
    if (await link.isVisible()) {
      await link.click();
      await adminPage.waitForLoadState('networkidle');
      await ss(adminPage, '08-purchase-orders', '03-po-detail');

      // check if approve/receive button visible
      const approveBtn = adminPage.locator('button:has-text("Approve"), a:has-text("Approve")').first();
      if (await approveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await ss(adminPage, '08-purchase-orders', '04-po-detail-pending-approval');
      }
      const receiveBtn = adminPage.locator('button:has-text("Receive"), a:has-text("Receive Goods")').first();
      if (await receiveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await ss(adminPage, '08-purchase-orders', '05-po-detail-approved-receive');
      }
    }
  }

  // warehouse view of PO
  await nav(warehousePage, '/purchase-orders');
  await ss(warehousePage, '08-purchase-orders', '06-po-list-warehouse-role');
}

// ── 9. SALES ORDERS ───────────────────────────────────────────────────────────
console.log('\n[9/14] SALES ORDERS');
{
  await nav(adminPage, '/sales-orders');
  await ss(adminPage, '09-sales-orders', '01-so-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("New"), a:has-text("Create")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '09-sales-orders', '02-so-create-form');
    await adminPage.goBack();
  }

  // detail
  await nav(adminPage, '/sales-orders');
  const rows = adminPage.locator('table tbody tr');
  if (await rows.count() > 0) {
    const link = rows.first().locator('a').first();
    if (await link.isVisible()) {
      await link.click();
      await adminPage.waitForLoadState('networkidle');
      await ss(adminPage, '09-sales-orders', '03-so-detail');

      const approveBtn = adminPage.locator('button:has-text("Approve"), a:has-text("Approve")').first();
      if (await approveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await ss(adminPage, '09-sales-orders', '04-so-pending-approval');
      }
      const issueBtn = adminPage.locator('button:has-text("Issue"), a:has-text("Issue Goods")').first();
      if (await issueBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await ss(adminPage, '09-sales-orders', '05-so-approved-issue-goods');
      }
    }
  }

  // Sales role view
  await nav(salesPage, '/sales-orders');
  await ss(salesPage, '09-sales-orders', '06-so-list-sales-role');

  // SOD: sales cannot approve own order (screenshot the constraint)
  await nav(salesPage, '/sales-orders');
  const salesRows = salesPage.locator('table tbody tr');
  if (await salesRows.count() > 0) {
    const link = salesRows.first().locator('a').first();
    if (await link.isVisible()) {
      await link.click();
      await salesPage.waitForLoadState('networkidle');
      await ss(salesPage, '09-sales-orders', '07-so-sod-sales-cannot-approve-own');
    }
  }
}

// ── 10. STOCK LEDGER ──────────────────────────────────────────────────────────
console.log('\n[10/14] STOCK LEDGER');
{
  await nav(adminPage, '/stock-ledger');
  await ss(adminPage, '10-stock-ledger', '01-stock-ledger-list');

  // filter by warehouse if input exists
  const warehouseFilter = adminPage.locator('select[name="warehouse_id"], select[name="warehouse"]').first();
  if (await warehouseFilter.isVisible({ timeout: 3000 }).catch(() => false)) {
    const options = await warehouseFilter.locator('option').all();
    if (options.length > 1) {
      await warehouseFilter.selectOption({ index: 1 });
      await adminPage.waitForLoadState('networkidle');
      await ss(adminPage, '10-stock-ledger', '02-stock-ledger-filtered');
    }
  }
}

// ── 11. REPORTS ───────────────────────────────────────────────────────────────
console.log('\n[11/14] REPORTS');
{
  await nav(adminPage, '/reports');
  await ss(adminPage, '11-reports', '01-reports-page');

  // Try CSV export link
  const csvLink = adminPage.locator('a[href*="csv"], a:has-text("Export"), a:has-text("CSV")').first();
  if (await csvLink.isVisible({ timeout: 3000 }).catch(() => false)) {
    await ss(adminPage, '11-reports', '02-reports-with-export-button');
  }
}

// ── 12. USERS & RBAC ──────────────────────────────────────────────────────────
console.log('\n[12/14] USERS & RBAC');
{
  await nav(adminPage, '/users');
  await ss(adminPage, '12-users-rbac', '01-user-list');

  const addBtn = adminPage.locator('a[href*="create"], button:has-text("Add"), a:has-text("Add User")').first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await adminPage.waitForLoadState('networkidle');
    await ss(adminPage, '12-users-rbac', '02-user-create-form');
    await adminPage.goBack();
  }

  // sales cannot access /users (403)
  await nav(salesPage, '/users');
  await ss(salesPage, '12-users-rbac', '03-users-403-sales-role');

  // warehouse cannot access /users (403)
  await nav(warehousePage, '/users');
  await ss(warehousePage, '12-users-rbac', '04-users-403-warehouse-role');
}

// ── 13. NOTIFICATIONS ─────────────────────────────────────────────────────────
console.log('\n[13/14] NOTIFICATIONS');
{
  await nav(adminPage, '/notifications');
  await ss(adminPage, '13-notifications', '01-notification-list');
}

// ── 14. PROFILE ───────────────────────────────────────────────────────────────
console.log('\n[14/14] PROFILE');
{
  await nav(adminPage, '/profile');
  await ss(adminPage, '14-profile', '01-profile-page');

  await nav(salesPage, '/profile');
  await ss(salesPage, '14-profile', '02-profile-sales');
}

// ── cleanup ──────────────────────────────────────────────────────────────────
await browser.close();

// Write index
const index = [
  '# Screenshot Evidence Index',
  '',
  `Generated: ${new Date().toISOString()}`,
  `App: ${BASE}`,
  '',
  '| Folder | Description |',
  '|---|---|',
  '| 01-auth | Login page, wrong password, successful login |',
  '| 02-dashboard | Admin / Sales / Warehouse dashboard |',
  '| 03-products | Product list, create form, detail |',
  '| 04-categories | Category list, create form |',
  '| 05-warehouses | Warehouse list, create form, stock detail |',
  '| 06-suppliers | Supplier list, create form |',
  '| 07-customers | Customer list, create form |',
  '| 08-purchase-orders | PO list, create, detail, approve, receive goods |',
  '| 09-sales-orders | SO list, create, detail, approve, issue goods, SOD constraint |',
  '| 10-stock-ledger | Stock ledger list and filter |',
  '| 11-reports | Reports page and CSV export |',
  '| 12-users-rbac | User management, 403 for non-admin roles |',
  '| 13-notifications | Notification list |',
  '| 14-profile | Profile page |',
].join('\n');

writeFileSync(path.join(OUT, 'README.md'), index);
console.log('\n✅ Done! Screenshots saved to docs/testing/screenshots/');
console.log(`   Index: docs/testing/screenshots/README.md`);

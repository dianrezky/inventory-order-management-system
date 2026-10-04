// Ad-hoc Playwright QA script for the Dashboard menu (DASH-01 in PROJECT_REFERENCE.md).
// Not run in CI. See README.md for setup/usage.
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
    // Best-effort: hit the logout route directly instead of hunting for a menu item.
    await page.goto(`${BASE_URL}/logout`, { waitUntil: 'networkidle' }).catch(() => {});
}

async function checkRole(browser, { role, email, password }) {
    console.log(`\n=== ${role} ===`);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err) => pageErrors.push(String(err)));
    page.on('requestfailed', (req) => failedRequests.push(`${req.method()} ${req.url()} — ${req.failure()?.errorText}`));

    await login(page, email, password);

    if (!page.url().includes('/dashboard')) {
        console.log(`  LOGIN DID NOT LAND ON /dashboard, current url: ${page.url()}`);
    }

    // dashboard/index.php always renders "Admin Dashboard" as the H1/breadcrumb
    // regardless of role — capture what's actually shown so this is visible in
    // the run output, whether or not it's later fixed.
    const title = await page.textContent('.page-header__title').catch(() => null);
    const breadcrumbCurrent = await page.textContent('.page-header__breadcrumb-current').catch(() => null);
    console.log(`  Page title shown: "${title?.trim()}" (breadcrumb: "${breadcrumbCurrent?.trim()}")`);

    // Role-specific KPI presence
    if (role === 'Admin') {
        const kpiCount = await page.locator('.dashboard-kpi-grid .stat-card').count();
        console.log(`  Admin KPI cards rendered: ${kpiCount} (expected 4)`);
        const addUserVisible = await page.locator('a.quick-actions__btn:has-text("Add User")').count();
        console.log(`  "Add User" quick action visible: ${addUserVisible > 0}`);
    } else if (role === 'Sales') {
        const cardCount = await page.locator('.dashboard-grid .stat-card').count();
        const myOrdersTotal = await page.locator('.stat-card--large .stat-card__value').textContent().catch(() => null);
        console.log(`  Sales order status cards rendered: ${cardCount}, "My Orders Total": ${myOrdersTotal?.trim()}`);
        const addUserVisible = await page.locator('a.quick-actions__btn:has-text("Add User")').count();
        console.log(`  "Add User" quick action visible (should be false): ${addUserVisible > 0}`);
    } else if (role === 'WarehouseStaff') {
        const cardCount = await page.locator('.dashboard-grid .stat-card').count();
        console.log(`  Warehouse queue cards rendered: ${cardCount} (expected 3)`);
    }

    // "Generate Summary Report" link — known to point at a route that does not
    // exist in config/routes.php (/reports/summary); confirm what actually
    // happens rather than assuming.
    const reportLink = await page.locator('a:has-text("Generate Summary Report")').getAttribute('href').catch(() => null);
    if (reportLink) {
        const resp = await page.request.get(`${BASE_URL}${reportLink}`, { maxRedirects: 0 }).catch((e) => e);
        const status = resp && resp.status ? resp.status() : 'ERROR';
        console.log(`  "Generate Summary Report" href="${reportLink}" -> HTTP ${status}`);
    }

    // Notifications block (Admin/WarehouseStaff only per DashboardController)
    const notifBlockVisible = await page.locator('.dashboard-section:has-text("Notifications")').count();
    console.log(`  Notifications block present: ${notifBlockVisible > 0}`);

    const screenshotPath = path.join(SCREENSHOT_DIR, `dashboard-${role}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    console.log(`  Screenshot saved: ${screenshotPath}`);

    console.log(`  Console errors: ${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);
    console.log(`  Page errors: ${pageErrors.length ? JSON.stringify(pageErrors) : 'none'}`);
    console.log(`  Failed requests: ${failedRequests.length ? JSON.stringify(failedRequests) : 'none'}`);

    await logout(page);
    await context.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        for (const roleConfig of ROLES) {
            await checkRole(browser, roleConfig);
        }
    } finally {
        await browser.close();
    }
    console.log('\nDONE — dashboard checked for all 3 roles');
})();

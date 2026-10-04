// Ad-hoc Playwright QA script — Application shell / navigation deep tests.
// Covers NAV-001..014 from the E2E deep-dive specification:
// correct menu by role, forbidden menu hidden + server-side denied,
// logo, breadcrumbs, profile/notification links, logout, invalid routes,
// and mobile/tablet/desktop navigation layout.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL       = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN = { role: 'Admin',          email: 'admin@example.com',     password: 'admin123' };
const SALES = { role: 'Sales',          email: 'sales1@example.com',    password: 'sales123' };
const WH    = { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123'    };

// ─────────────────────────────────────────────────────────
// Role → expected menu items (from PROJECT_REFERENCE §1.2)
// ─────────────────────────────────────────────────────────
const MENU_MATRIX = {
    Admin: {
        visible: [
            { label: /dashboard/i,        href: '/dashboard' },
            { label: /products?|produk/i, href: '/products' },
            { label: /categor|kategori/i, href: '/categories' },
            { label: /warehouse|gudang/i, href: '/warehouses' },
            { label: /supplier/i,         href: '/suppliers' },
            { label: /customer|pelanggan/i, href: '/customers' },
            { label: /purchase.order|pembelian/i, href: '/purchase-orders' },
            { label: /sales.order|penjualan/i,    href: '/sales-orders' },
            { label: /stock.ledger|buku.stok/i,   href: '/stock-ledger' },
            { label: /report|laporan/i,   href: '/reports' },
            { label: /user|pengguna/i,    href: '/users' },
        ],
        forbidden: [],
    },
    Sales: {
        visible: [
            { label: /dashboard/i,        href: '/dashboard' },
            { label: /products?|produk/i, href: '/products' },
            { label: /sales.order|penjualan/i, href: '/sales-orders' },
            { label: /report|laporan/i,   href: '/reports' },
        ],
        forbidden: [
            { href: '/users',         label: 'Users' },
            { href: '/stock-ledger',  label: 'Stock Ledger' },
            { href: '/purchase-orders', label: 'Purchase Orders' },
        ],
    },
    WarehouseStaff: {
        visible: [
            { label: /dashboard/i,          href: '/dashboard' },
            { label: /products?|produk/i,   href: '/products' },
            { label: /purchase.order|pembelian/i, href: '/purchase-orders' },
            { label: /stock.ledger|buku.stok/i,   href: '/stock-ledger' },
        ],
        forbidden: [
            { href: '/users',       label: 'Users' },
            { href: '/sales-orders', label: 'Sales Orders (create)' },
        ],
    },
};

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

// ─────────────────────────────────────────────────────────────────────────────
// NAV-001..003 — Correct menu links per role
// ─────────────────────────────────────────────────────────────────────────────
async function checkMenuByRole(browser, { role, email, password }) {
    console.log(`\n=== NAV-00${['Admin','Sales','WarehouseStaff'].indexOf(role)+1}: ${role} menu ===`);
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, email, password);

    await safeGoto(page, `${BASE_URL}/dashboard`);
    const navHtml = await page.locator('nav, aside, .sidebar, .nav-menu, .main-nav').first().innerHTML().catch(() => '');
    const navText = await page.locator('nav, aside, .sidebar').first().innerText().catch(() => '');

    const matrix = MENU_MATRIX[role];

    for (const item of matrix.visible) {
        const found = item.label.test(navText) || item.label.test(navHtml);
        check(`NAV-00${['Admin','Sales','WarehouseStaff'].indexOf(role)+1}`,
            `${role} sees ${item.href} in nav`, found, `nav text: ${navText.substring(0, 200)}`);
    }

    // NAV-004 — Forbidden menu hidden in UI
    for (const item of matrix.forbidden) {
        const menuLink = page.locator(`nav a[href*="${item.href}"], aside a[href*="${item.href}"], .sidebar a[href*="${item.href}"]`).first();
        const linkCount = await menuLink.count();
        check('NAV-004', `${role} does NOT see ${item.label} in sidebar menu`, linkCount === 0,
            `found ${linkCount} link(s) for ${item.href}`);
    }

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `nav-menu-${role.toLowerCase()}.png`) });
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-005 — Hidden menu direct URL still denied (server-side enforcement)
// ─────────────────────────────────────────────────────────────────────────────
async function checkDirectUrlDenied(browser) {
    console.log('\n=== NAV-005: Direct URL to forbidden route denied ===');

    const tests = [
        { cred: SALES, route: '/users',        label: 'Sales → /users' },
        { cred: SALES, route: '/stock-ledger', label: 'Sales → /stock-ledger' },
        { cred: SALES, route: '/purchase-orders', label: 'Sales → /purchase-orders' },
        { cred: WH,    route: '/users',        label: 'WH → /users' },
        { cred: WH,    route: '/sales-orders/create', label: 'WH → /sales-orders/create' },
    ];

    for (const { cred, route, label } of tests) {
        const ctx  = await browser.newContext();
        const page = await ctx.newPage();
        await login(page, cred.email, cred.password);

        const resp = await page.request.get(`${BASE_URL}${route}`, { maxRedirects: 5 }).catch(() => null);
        const status = resp ? resp.status() : 0;
        check('NAV-005', `${label} is denied (403 or redirect, not 200)`,
            status !== 200, `status: ${status}`);

        await ctx.close();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-006 — Logo/home navigation
// ─────────────────────────────────────────────────────────────────────────────
async function checkLogoNav(browser) {
    console.log('\n=== NAV-006: Logo/home navigation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Navigate away from dashboard.
    await safeGoto(page, `${BASE_URL}/products`);

    // Click logo/brand link.
    const logoLink = page.locator('a.navbar-brand, a.logo, header a[href="/"], header a[href*="dashboard"], .sidebar-logo a').first();
    if (await logoLink.count() > 0) {
        await logoLink.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        check('NAV-006', 'Logo link navigates to dashboard or home',
            page.url().includes('/dashboard') || page.url() === `${BASE_URL}/` || page.url() === `${BASE_URL}`,
            `url: ${page.url()}`);
    } else {
        note('NAV-006: Logo/brand link not found, checking header brand text');
        const brand = await page.locator('.navbar-brand, .brand, .logo').first().innerText().catch(() => '');
        note(`NAV-006: brand text: "${brand}"`);
        check('NAV-006', 'Brand/logo element exists in header', brand.length > 0 || (await page.locator('header img').count()) > 0);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-007 — Breadcrumb correctness
// ─────────────────────────────────────────────────────────────────────────────
async function checkBreadcrumbs(browser) {
    console.log('\n=== NAV-007: Breadcrumb correctness ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const breadcrumbRoutes = [
        { url: '/products',       expectedTexts: [/product|produk/i] },
        { url: '/categories',     expectedTexts: [/categor|kategori/i] },
        { url: '/purchase-orders', expectedTexts: [/purchase|pembelian/i] },
    ];

    for (const { url, expectedTexts } of breadcrumbRoutes) {
        await safeGoto(page, `${BASE_URL}${url}`);
        const breadcrumb = await page.locator('.breadcrumb, .page-header__breadcrumb, nav[aria-label="breadcrumb"]').first().innerText().catch(() => '');
        const bodyTitle  = await page.locator('.page-header__title, h1').first().innerText().catch(() => '');
        for (const rx of expectedTexts) {
            check('NAV-007', `Breadcrumb/title for ${url} contains expected text`,
                rx.test(breadcrumb) || rx.test(bodyTitle),
                `breadcrumb: "${breadcrumb.trim()}", title: "${bodyTitle.trim()}"`);
        }
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-008 — Profile navigation link works
// ─────────────────────────────────────────────────────────────────────────────
async function checkProfileNav(browser) {
    console.log('\n=== NAV-008: Profile navigation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, SALES.email, SALES.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);
    const profileLink = page.locator('a[href*="profile"], a[href*="my-profile"], .user-menu a:first-child, nav a:has-text("Profile")').first();
    if (await profileLink.count() > 0) {
        await profileLink.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        check('NAV-008', 'Profile nav link reaches /my-profile', page.url().includes('/my-profile') || page.url().includes('/profile'), `url: ${page.url()}`);
    } else {
        // Try direct navigation.
        await safeGoto(page, `${BASE_URL}/my-profile`);
        check('NAV-008', '/my-profile reachable for Sales', !page.url().includes('/login'), `url: ${page.url()}`);
        note('NAV-008: Profile link not found in nav; verified direct URL works');
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-009 — Notification navigation
// ─────────────────────────────────────────────────────────────────────────────
async function checkNotifNav(browser) {
    console.log('\n=== NAV-009: Notification navigation ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);
    const notifLink = page.locator('a[href*="notification"], .nav-notif, [class*="notif"] a').first();
    if (await notifLink.count() > 0) {
        const href = await notifLink.getAttribute('href').catch(() => null);
        note(`NAV-009: Notification link href: ${href}`);
        check('NAV-009', 'Notification link exists in nav', true);
    } else {
        note('NAV-009: No dedicated notification link found (may be a dropdown bell only)');
        const bell = await page.locator('[class*="notif"], [class*="bell"], .badge').count();
        check('NAV-009', 'Notification bell/badge present', bell > 0);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-010 — Logout via navigation
// ─────────────────────────────────────────────────────────────────────────────
async function checkLogoutNav(browser) {
    console.log('\n=== NAV-010: Logout navigation/action ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, WH.email, WH.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);
    const logoutForm = page.locator('form[action*="logout"]').first();
    const logoutLink = page.locator('a[href*="logout"]').first();

    if (await logoutForm.count() > 0) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            logoutForm.locator('button[type="submit"]').first().click(),
        ]);
        check('NAV-010', 'Logout form submission lands on /login', page.url().includes('/login'), `url: ${page.url()}`);
    } else if (await logoutLink.count() > 0) {
        await logoutLink.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        check('NAV-010', 'Logout link redirects to /login', page.url().includes('/login'), `url: ${page.url()}`);
    } else {
        // POST /logout directly.
        const csrf = await getCsrfTokenSafe(page);
        await page.request.post(`${BASE_URL}/logout`, { form: { _csrf_token: csrf }, maxRedirects: 5 }).catch(() => null);
        await safeGoto(page, `${BASE_URL}/dashboard`);
        check('NAV-010', 'After logout /dashboard redirects to /login', page.url().includes('/login'), `url: ${page.url()}`);
    }

    await ctx.close();
}

async function getCsrfTokenSafe(page) {
    return page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-011 — Invalid route returns 404 (not 500 or login redirect)
// ─────────────────────────────────────────────────────────────────────────────
async function checkInvalidRoute(browser) {
    console.log('\n=== NAV-011: Invalid route → 404 ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const invalidRoutes = [
        '/this-route-does-not-exist',
        '/products/99999999-invalid-route',
        '/admin/secret-backdoor',
        '/purchase-orders/TAMPERED_ID_00000/receive',
    ];

    for (const route of invalidRoutes) {
        const resp = await page.request.get(`${BASE_URL}${route}`, { maxRedirects: 5 }).catch(() => null);
        const status = resp ? resp.status() : 0;
        check('NAV-011', `${route} returns 4xx (not 500 or 200)`,
            status >= 400 && status < 500, `status: ${status}`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NAV-012..014 — Responsive navigation (mobile / tablet / desktop)
// ─────────────────────────────────────────────────────────────────────────────
async function checkResponsiveNav(browser) {
    const viewports = [
        { name: 'mobile',  width: 360,  height: 780 },
        { name: 'tablet',  width: 768,  height: 1024 },
        { name: 'desktop', width: 1440, height: 900  },
    ];

    for (const vp of viewports) {
        console.log(`\n=== NAV-01${viewports.indexOf(vp)+2}: ${vp.name} navigation ===`);
        const ctx  = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
        const page = await ctx.newPage();
        await login(page, ADMIN.email, ADMIN.password);

        await safeGoto(page, `${BASE_URL}/dashboard`);

        // Page must not overflow horizontally.
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        check(`NAV-01${viewports.indexOf(vp)+2}`,
            `${vp.name} (${vp.width}px) dashboard no horizontal overflow`,
            scrollWidth <= vp.width, `scrollWidth: ${scrollWidth}px`);

        // Nav/sidebar must exist.
        const navEl = page.locator('nav, aside, .sidebar, .main-nav').first();
        check(`NAV-01${viewports.indexOf(vp)+2}`,
            `${vp.name} has nav element`, (await navEl.count()) > 0);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `nav-${vp.name}-dashboard.png`) });
        await ctx.close();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        for (const cred of [ADMIN, SALES, WH]) {
            await checkMenuByRole(browser, cred);
        }
        await checkDirectUrlDenied(browser);
        await checkLogoNav(browser);
        await checkBreadcrumbs(browser);
        await checkProfileNav(browser);
        await checkNotifNav(browser);
        await checkLogoutNav(browser);
        await checkInvalidRoute(browser);
        await checkResponsiveNav(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — navigation.spec.js complete');
})();

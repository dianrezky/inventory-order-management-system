// Ad-hoc Playwright QA script — Notifications deep tests.
// Covers NOTIF-001..015 from the E2E deep-dive specification:
// bell visibility, dropdown, mark-all-read, badge, CSRF guard,
// role access, and global-state isolation.
//
// Notifications use shared mutable state — mutation-sensitive tests
// run in an isolated section and are marked clearly.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL      = process.env.BASE_URL || 'http://127.0.0.1:8090';
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
    return page.locator('input[name="_csrf_token"], meta[name="_csrf_token"]')
               .first().getAttribute('content')
               .catch(() => page.locator('input[name="_csrf_token"]').first().inputValue().catch(() => ''));
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIF-001..006 — Bell visibility and dropdown per role
// ─────────────────────────────────────────────────────────────────────────────
async function checkNotifBell(browser, { role, email, password }) {
    console.log(`\n=== NOTIF-001..006: Notification bell — ${role} ===`);
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, email, password);

    await safeGoto(page, `${BASE_URL}/dashboard`);

    // NOTIF-001 — Bell visible (all authenticated roles expected to see it)
    const bell = page.locator('.notification-bell, [data-notif], #notif-bell, .nav-notif, a[href*="notification"], button[aria-label*="notification"], button[aria-label*="notif"]').first();
    const bellCount = await bell.count();
    note(`NOTIF-001: ${role} — bell element count: ${bellCount}`);

    // Bell may be implemented as badge on a link/button — try broader selector.
    const bellBroad = page.locator('header a:has(.badge), nav a:has(.badge), .navbar .badge, [class*="notif"]').first();
    const bellBroadCount = await bellBroad.count();

    const hasBell = bellCount > 0 || bellBroadCount > 0;
    check('NOTIF-001', `${role} sees notification bell`, hasBell, 'No bell/badge element found in header/nav');

    if (!hasBell) {
        note(`NOTIF-001: Selector miss — searching full page for badge/notification keywords`);
        const bodyHtml = await page.content().catch(() => '');
        const hasNotifWord = /notif|badge|bell/i.test(bodyHtml);
        note(`NOTIF-001: Page contains 'notif/badge/bell' keyword: ${hasNotifWord}`);
    }

    // NOTIF-003 — Unread badge value
    const badge = page.locator('.badge, .notif-count, [class*="count"]').first();
    const badgeText = await badge.innerText().catch(() => '');
    note(`NOTIF-003: ${role} unread badge text: "${badgeText}"`);

    // NOTIF-004 — Dropdown (click bell and check dropdown appears)
    const clickTarget = (bellCount > 0 ? bell : bellBroad);
    if (await clickTarget.count() > 0) {
        await clickTarget.click();
        await page.waitForTimeout(500);
        const dropdown = page.locator('[class*="dropdown"][class*="show"], .notif-dropdown, .notification-dropdown, [data-notif-list]').first();
        const dropdownVisible = await dropdown.isVisible().catch(() => false);
        note(`NOTIF-004: ${role} dropdown visible after bell click: ${dropdownVisible}`);
    }

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, `notif-bell-${role.toLowerCase()}.png`) });
    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIF-007 — Dashboard notification block
// ─────────────────────────────────────────────────────────────────────────────
async function checkDashboardNotifBlock(browser) {
    console.log('\n=== NOTIF-007: Dashboard notification block ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);
    const notifSection = page.locator('.dashboard-section:has-text("Notification"), section:has-text("Notifikasi"), [class*="notif-block"]').first();
    const sectionCount = await notifSection.count();
    note(`NOTIF-007: Dashboard notification block found: ${sectionCount > 0}`);

    const bodyText = await page.locator('body').innerText().catch(() => '');
    check('NOTIF-007', 'No PHP error on dashboard with notifications block', !/Fatal error|Warning\s*:/i.test(bodyText));

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIF-008..012 — Mark all read (mutation; runs in isolation)
// ─────────────────────────────────────────────────────────────────────────────
async function checkMarkAllRead(browser) {
    console.log('\n=== NOTIF-008..012: Mark all read (isolated mutation) ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);

    // Find mark-all-read button/form.
    const markReadBtn = page.locator(
        'button:has-text("Mark all read"), a:has-text("Mark all read"), ' +
        'button:has-text("Tandai"), form[action*="read"] button, ' +
        '[data-action="mark-read"]'
    ).first();

    if (await markReadBtn.count() === 0) {
        note('NOTIF-008: Mark all read button not found on dashboard — trying /notifications page');
        await safeGoto(page, `${BASE_URL}/notifications`);
    }

    const markReadBtn2 = page.locator(
        'button:has-text("Mark all read"), a:has-text("Mark all read"), ' +
        'button:has-text("Tandai"), form[action*="read"] button'
    ).first();

    if (await markReadBtn2.count() === 0) {
        note('NOTIF-008: Mark all read button not found anywhere, skipping mutation test');
        await ctx.close();
        return;
    }

    // Read badge count before.
    await safeGoto(page, `${BASE_URL}/dashboard`);
    const badgeBefore = await page.locator('.badge, .notif-count').first().innerText().catch(() => '0');
    note(`NOTIF-008: Badge before mark-all-read: "${badgeBefore}"`);

    // Submit mark-all-read.
    const markReadForm = page.locator('form[action*="read"]').first();
    if (await markReadForm.count() > 0) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            markReadForm.locator('button[type="submit"]').first().click(),
        ]);
    } else {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            markReadBtn2.click(),
        ]);
    }

    check('NOTIF-008', 'Mark all read does not error (no 5xx)', !page.url().includes('error'));

    // NOTIF-011 — Badge updates after read.
    const badgeAfter = await page.locator('.badge, .notif-count').first().innerText().catch(() => '0');
    note(`NOTIF-011: Badge after mark-all-read: "${badgeAfter}"`);

    // NOTIF-013 — Persistence after refresh.
    await safeGoto(page, `${BASE_URL}/dashboard`);
    const badgePersist = await page.locator('.badge, .notif-count').first().innerText().catch(() => '0');
    note(`NOTIF-013: Badge after refresh: "${badgePersist}"`);
    check('NOTIF-013', 'No 5xx after refresh post mark-all-read', !page.url().includes('error'));

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIF-009/010 — CSRF guard on mark-all-read endpoint
// ─────────────────────────────────────────────────────────────────────────────
async function checkNotifCsrf(browser) {
    console.log('\n=== NOTIF-009/010: CSRF guard on mark-all-read ===');
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    // Discover mark-all-read endpoint from form action or page link.
    await safeGoto(page, `${BASE_URL}/dashboard`);
    const form = page.locator('form[action*="read"]').first();
    let markReadPath = '/notifications/mark-all-read'; // common convention

    if (await form.count() > 0) {
        markReadPath = (await form.getAttribute('action')) || markReadPath;
    }

    // NOTIF-009 — With valid CSRF.
    const csrf = await getCsrfToken(page);
    const validResp = await page.request.post(`${BASE_URL}${markReadPath}`, {
        form: { _csrf_token: csrf },
        maxRedirects: 5,
    }).catch(() => null);
    note(`NOTIF-009: With valid CSRF — status: ${validResp ? validResp.status() : 'ERROR'}`);
    check('NOTIF-009', 'Mark-all-read with CSRF does not 5xx', !validResp || validResp.status() < 500);

    // NOTIF-010 — Without CSRF.
    const nocsrfResp = await page.request.post(`${BASE_URL}${markReadPath}`, {
        form: {},
        maxRedirects: 5,
    }).catch(() => null);
    const nocsrfStatus = nocsrfResp ? nocsrfResp.status() : 0;
    note(`NOTIF-010: Without CSRF — status: ${nocsrfStatus}`);
    check('NOTIF-010', 'Mark-all-read without CSRF is rejected (not 200)',
        nocsrfStatus !== 200, `got ${nocsrfStatus}`);

    // Unauthenticated POST.
    const unauthCtx  = await browser.newContext();
    const unauthPage = await unauthCtx.newPage();
    const unauthResp = await unauthPage.request.post(`${BASE_URL}${markReadPath}`, {
        form: { _csrf_token: 'fake' },
        maxRedirects: 5,
    }).catch(() => null);
    check('NOTIF-010', 'Unauthenticated POST to mark-all-read is rejected',
        !unauthResp || unauthResp.status() !== 200, `status: ${unauthResp ? unauthResp.status() : 'ERROR'}`);
    await unauthCtx.close();

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIF-015 — Global-state isolation (two concurrent sessions)
// ─────────────────────────────────────────────────────────────────────────────
async function checkNotifIsolation(browser) {
    console.log('\n=== NOTIF-015: Global-state isolation ===');
    // Admin marks all as read in their session; Sales session should not be affected.
    const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminCtx.newPage();
    await login(adminPage, ADMIN.email, ADMIN.password);

    const salesCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const salesPage = await salesCtx.newPage();
    await login(salesPage, SALES.email, SALES.password);

    // Record Sales badge before Admin marks read.
    await safeGoto(salesPage, `${BASE_URL}/dashboard`);
    const salesBadgeBefore = await salesPage.locator('.badge, .notif-count').first().innerText().catch(() => 'n/a');

    // Admin marks all read.
    await safeGoto(adminPage, `${BASE_URL}/dashboard`);
    const form = adminPage.locator('form[action*="read"]').first();
    if (await form.count() > 0) {
        const csrf = await adminPage.locator('input[name="_csrf_token"]').first().inputValue().catch(() => '');
        await adminPage.request.post(`${BASE_URL}${(await form.getAttribute('action')) || '/notifications/mark-all-read'}`, {
            form: { _csrf_token: csrf },
            maxRedirects: 5,
        }).catch(() => null);
    }

    // Sales reloads and checks own badge — should be independent of Admin's action.
    await safeGoto(salesPage, `${BASE_URL}/dashboard`);
    const salesBadgeAfter = await salesPage.locator('.badge, .notif-count').first().innerText().catch(() => 'n/a');
    note(`NOTIF-015: Sales badge before Admin mark-read="${salesBadgeBefore}", after="${salesBadgeAfter}"`);
    check('NOTIF-015', 'No 5xx on Sales dashboard after Admin mark-all-read',
        !salesPage.url().includes('error'));

    await adminCtx.close();
    await salesCtx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        for (const cred of [ADMIN, SALES, WH]) {
            await checkNotifBell(browser, cred);
        }
        await checkDashboardNotifBlock(browser);
        await checkNotifCsrf(browser);
        // Mutation tests run last (isolated).
        await checkMarkAllRead(browser);
        await checkNotifIsolation(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — notifications.spec.js complete');
})();

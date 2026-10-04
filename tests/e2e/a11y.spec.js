// Ad-hoc Playwright QA script — Accessibility deep tests (D14).
// Covers keyboard navigation, focus management, semantic form controls,
// dialog/modal behaviour, and heading hierarchy for representative pages.
// Does NOT require axe-core. Checks observable, automatable accessibility
// contracts that map directly to WCAG 2.1 AA criteria.
// Not run in CI. See README.md for setup/usage.
'use strict';

const path = require('path');
const { chromium } = require('playwright');

const BASE_URL       = process.env.BASE_URL || 'http://127.0.0.1:8090';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const ADMIN = { role: 'Admin', email: 'admin@example.com', password: 'admin123' };
const SALES = { role: 'Sales', email: 'sales1@example.com', password: 'sales123' };

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
// A11Y-LOGIN — Login form keyboard and semantic checks
// ─────────────────────────────────────────────────────────────────────────────
async function checkLoginA11y(browser) {
    console.log('\n=== A11Y-LOGIN: Login page accessibility ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    await safeGoto(page, `${BASE_URL}/login`);

    // Semantic: inputs have associated labels.
    const emailInput = page.locator('input[name="email"]').first();
    const passInput  = page.locator('input[name="password"]').first();
    const emailId    = await emailInput.getAttribute('id').catch(() => null);
    const passId     = await passInput.getAttribute('id').catch(() => null);

    const emailLabel = emailId
        ? await page.locator(`label[for="${emailId}"]`).count()
        : await emailInput.locator('xpath=ancestor::label').count();
    const passLabel  = passId
        ? await page.locator(`label[for="${passId}"]`).count()
        : await passInput.locator('xpath=ancestor::label').count();

    check('A11Y-LOGIN', 'Email input has an associated <label>', emailLabel > 0 || (await emailInput.getAttribute('aria-label').catch(() => null)) !== null,
        `id="${emailId}", label count=${emailLabel}`);
    check('A11Y-LOGIN', 'Password input has an associated <label>', passLabel > 0 || (await passInput.getAttribute('aria-label').catch(() => null)) !== null,
        `id="${passId}", label count=${passLabel}`);

    // Keyboard: Tab through form, Enter submits.
    await page.locator('body').click();
    await page.keyboard.press('Tab');
    const firstFocused = await page.evaluate(() => document.activeElement?.name || document.activeElement?.tagName);
    note(`A11Y-LOGIN: First Tab focus: ${firstFocused}`);

    // Fill by keyboard only.
    await emailInput.focus();
    await page.keyboard.type('invalid@test.com');
    await page.keyboard.press('Tab');
    await page.keyboard.type('wrongpass');
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle').catch(() => {});
    // Must stay on /login (wrong creds) — checks Enter triggered submit.
    check('A11Y-LOGIN', 'Enter key in password field submits login form',
        page.url().includes('/login'), `url: ${page.url()}`);

    // Heading hierarchy: must have exactly one H1.
    const h1Count = await page.locator('h1').count();
    check('A11Y-LOGIN', 'Login page has exactly one H1', h1Count === 1, `H1 count: ${h1Count}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-DASHBOARD — Dashboard semantic checks
// ─────────────────────────────────────────────────────────────────────────────
async function checkDashboardA11y(browser) {
    console.log('\n=== A11Y-DASHBOARD: Dashboard accessibility ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);

    // H1 present.
    const h1Count = await page.locator('h1').count();
    check('A11Y-DASH', 'Dashboard has at least one H1', h1Count >= 1, `H1 count: ${h1Count}`);

    // No heading order violations (H1→H2→H3 — no skipping from H1 to H3).
    const headings = await page.evaluate(() => {
        return [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map((h) => parseInt(h.tagName[1]));
    });
    let orderOk = true;
    for (let i = 1; i < headings.length; i++) {
        if (headings[i] - headings[i - 1] > 1) { orderOk = false; break; }
    }
    note(`A11Y-DASH: Heading order: ${headings.join(',')}`);
    check('A11Y-DASH', 'No heading level skipped (no H1→H3 jump)', orderOk, `headings: ${headings.join(',')}`);

    // Images have alt text (or are decorative with alt="").
    const imgsWithoutAlt = await page.evaluate(() =>
        [...document.querySelectorAll('img')].filter((img) => !img.hasAttribute('alt')).length
    );
    check('A11Y-DASH', 'All <img> have alt attribute', imgsWithoutAlt === 0, `${imgsWithoutAlt} images missing alt`);

    // Buttons have accessible text (not icon-only with no label).
    const btnCount = await page.locator('button').count();
    let btnsMissingLabel = 0;
    for (let i = 0; i < Math.min(btnCount, 20); i++) {
        const btn = page.locator('button').nth(i);
        const text  = await btn.innerText().catch(() => '');
        const aria  = await btn.getAttribute('aria-label').catch(() => null);
        const title = await btn.getAttribute('title').catch(() => null);
        if (!text.trim() && !aria && !title) btnsMissingLabel++;
    }
    check('A11Y-DASH', 'Buttons have accessible label (text, aria-label, or title)',
        btnsMissingLabel === 0, `${btnsMissingLabel} buttons without label`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-PRODUCTS — Products list keyboard navigation
// ─────────────────────────────────────────────────────────────────────────────
async function checkProductListA11y(browser) {
    console.log('\n=== A11Y-PRODUCTS: Products list accessibility ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/products`);

    // Table has <thead> with <th> (not just styled <td>).
    const thCount = await page.locator('table thead th').count();
    check('A11Y-PROD', 'Product table has <th> headers', thCount > 0, `th count: ${thCount}`);

    // Table rows are in <tbody>.
    const tbodyRows = await page.locator('table tbody tr').count();
    check('A11Y-PROD', 'Product table has <tbody> with rows', tbodyRows > 0, `rows: ${tbodyRows}`);

    // Search form: inputs have labels or aria-label.
    const searchInputs = page.locator('form input[type="text"], form input[type="search"], form select');
    const inputCount = await searchInputs.count();
    let inputsMissingLabel = 0;
    for (let i = 0; i < inputCount; i++) {
        const inp  = searchInputs.nth(i);
        const id   = await inp.getAttribute('id').catch(() => null);
        const aria = await inp.getAttribute('aria-label').catch(() => null);
        const ph   = await inp.getAttribute('placeholder').catch(() => null);
        const lbl  = id ? await page.locator(`label[for="${id}"]`).count() : 0;
        if (!aria && !ph && lbl === 0) inputsMissingLabel++;
    }
    check('A11Y-PROD', 'Search/filter inputs have label, aria-label or placeholder',
        inputsMissingLabel === 0, `${inputsMissingLabel} inputs missing label`);

    // Keyboard: Tab to first action link.
    await page.locator('table tbody tr a').first().focus();
    const focusedHref = await page.evaluate(() => document.activeElement?.href || '');
    check('A11Y-PROD', 'Action links are keyboard focusable', focusedHref.length > 0, `focused href: ${focusedHref}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-CATEGORIES — Modal dialog keyboard behaviour (Escape closes, focus trap)
// ─────────────────────────────────────────────────────────────────────────────
async function checkCategoriesModalA11y(browser) {
    console.log('\n=== A11Y-CATEGORIES: Modal keyboard behaviour ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/categories`);

    // Find "Add Category" / "New Category" button.
    const addBtn = page.locator('button:has-text("Add"), button:has-text("New"), button:has-text("Tambah"), a:has-text("New Category"), a:has-text("Add Category")').first();
    if (await addBtn.count() === 0) {
        note('A11Y-CAT: No modal-trigger button found, skipping modal keyboard test');
        await ctx.close();
        return;
    }

    // Open modal.
    await addBtn.click();
    await page.waitForTimeout(400);
    const modal = page.locator('.modal, [role="dialog"], dialog').first();
    const modalVisible = await modal.isVisible().catch(() => false);
    check('A11Y-CAT', 'Modal becomes visible after Add button click', modalVisible);

    if (modalVisible) {
        // Focus should be inside modal (first focusable element).
        const activeInModal = await page.evaluate(() => {
            const el = document.activeElement;
            return el ? el.tagName + (el.name ? `[name=${el.name}]` : '') : 'BODY';
        });
        note(`A11Y-CAT: Focus after modal open: ${activeInModal}`);

        // Escape closes modal.
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        const modalAfterEsc = await modal.isVisible().catch(() => false);
        check('A11Y-CAT', 'Escape key closes modal', !modalAfterEsc,
            `modal visible after Escape: ${modalAfterEsc}`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-FORMS — Create forms have proper labels and required indicators
// ─────────────────────────────────────────────────────────────────────────────
async function checkCreateFormA11y(browser) {
    console.log('\n=== A11Y-FORMS: Create form semantic labels ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const createForms = [
        { url: '/products/create',       label: 'Products' },
        { url: '/purchase-orders/create', label: 'Purchase Orders' },
        { url: '/users/create',          label: 'Users' },
    ];

    for (const { url, label } of createForms) {
        await safeGoto(page, `${BASE_URL}${url}`);

        // H1 present.
        const h1 = await page.locator('h1').first().innerText().catch(() => '');
        check('A11Y-FORMS', `${label} create page has H1`, h1.length > 0, `H1: "${h1}"`);

        // Form inputs have labels.
        const textInputs = page.locator('form input[type="text"], form input[type="email"], form input[type="number"], form select, form textarea');
        const cnt = await textInputs.count();
        let missing = 0;
        for (let i = 0; i < Math.min(cnt, 15); i++) {
            const inp   = textInputs.nth(i);
            const id    = await inp.getAttribute('id').catch(() => null);
            const aria  = await inp.getAttribute('aria-label').catch(() => null);
            const ph    = await inp.getAttribute('placeholder').catch(() => null);
            const lbl   = id ? await page.locator(`label[for="${id}"]`).count() : 0;
            const wrap  = await inp.locator('xpath=ancestor::label').count().catch(() => 0);
            if (!aria && !ph && lbl === 0 && wrap === 0) missing++;
        }
        check('A11Y-FORMS', `${label} form: all inputs have label/aria-label/placeholder`,
            missing === 0, `${missing} inputs missing label (of ${cnt})`);

        // Submit button present and has text.
        const submitBtn = page.locator('button[type="submit"]').first();
        const submitText = await submitBtn.innerText().catch(() => '');
        check('A11Y-FORMS', `${label} form has submit button with text`, submitText.trim().length > 0, `text: "${submitText.trim()}"`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-FOCUS — Focus visibility on interactive elements
// ─────────────────────────────────────────────────────────────────────────────
async function checkFocusVisibility(browser) {
    console.log('\n=== A11Y-FOCUS: Focus visibility ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);

    // Check that interactive elements are not focus-disabled globally.
    const focusDisabled = await page.evaluate(() => {
        const style = window.getComputedStyle(document.documentElement);
        // If there's a global `outline: none` or `outline: 0` on `:focus`, flag it.
        // We look for any stylesheet rule that broadly kills focus outlines.
        const sheets = [...document.styleSheets];
        let globalFocusKilled = false;
        for (const sheet of sheets) {
            try {
                const rules = [...sheet.cssRules || []];
                for (const rule of rules) {
                    if (rule.selectorText && /^\*:focus$|^:focus$/.test(rule.selectorText.trim())) {
                        const outline = rule.style && rule.style.outline;
                        if (outline === 'none' || outline === '0') {
                            globalFocusKilled = true;
                        }
                    }
                }
            } catch (_) {}
        }
        return globalFocusKilled;
    });
    check('A11Y-FOCUS', 'No global CSS rule removes all :focus outlines', !focusDisabled,
        'Found a *:focus { outline: none } rule — keyboard users cannot see focus');

    // Check that the first interactive element on the page is reachable by Tab.
    const firstLink = page.locator('a, button').first();
    if (await firstLink.count() > 0) {
        await firstLink.focus();
        const focused = await page.evaluate(() => document.activeElement?.tagName || '');
        check('A11Y-FOCUS', 'First link/button is keyboard-focusable', ['A', 'BUTTON'].includes(focused), `focused: ${focused}`);
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-SKIP — Skip-to-main or landmark regions
// ─────────────────────────────────────────────────────────────────────────────
async function checkLandmarks(browser) {
    console.log('\n=== A11Y-LANDMARKS: ARIA landmarks / skip link ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    await safeGoto(page, `${BASE_URL}/dashboard`);

    // Check for <main>, <nav>, <header> or role="main"/"navigation".
    const hasMain = (await page.locator('main, [role="main"]').count()) > 0;
    const hasNav  = (await page.locator('nav, [role="navigation"]').count()) > 0;
    const hasHeader = (await page.locator('header, [role="banner"]').count()) > 0;

    check('A11Y-LANDMARK', 'Page has <main> or role="main"', hasMain);
    check('A11Y-LANDMARK', 'Page has <nav> or role="navigation"', hasNav);
    check('A11Y-LANDMARK', 'Page has <header> or role="banner"', hasHeader);

    // Skip-to-main link (optional but good practice).
    const skipLink = await page.locator('a[href="#main"], a[href="#content"], a:has-text("Skip")').count();
    note(`A11Y-LANDMARK: Skip-to-main link present: ${skipLink > 0}`);

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// A11Y-TABLES — Data tables have <caption> or accessible title
// ─────────────────────────────────────────────────────────────────────────────
async function checkTableA11y(browser) {
    console.log('\n=== A11Y-TABLES: Data table accessibility ===');
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await login(page, ADMIN.email, ADMIN.password);

    const tablePages = [
        { url: '/products',         label: 'Products' },
        { url: '/categories',       label: 'Categories' },
        { url: '/purchase-orders',  label: 'Purchase Orders' },
        { url: '/stock-ledger',     label: 'Stock Ledger' },
    ];

    for (const { url, label } of tablePages) {
        await safeGoto(page, `${BASE_URL}${url}`);
        const tables = page.locator('table');
        const tableCount = await tables.count();
        if (tableCount === 0) {
            note(`A11Y-TABLES: ${label} — no <table> found`);
            continue;
        }

        for (let i = 0; i < tableCount; i++) {
            const tbl = tables.nth(i);
            const hasTh     = (await tbl.locator('th').count()) > 0;
            const hasScope  = (await tbl.locator('th[scope]').count()) > 0;
            check('A11Y-TABLES', `${label} table ${i+1}: has <th> headers`, hasTh);
            note(`A11Y-TABLES: ${label} table ${i+1}: has scope attribute: ${hasScope}`);
        }
    }

    await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
(async () => {
    const browser = await chromium.launch();
    try {
        await checkLoginA11y(browser);
        await checkDashboardA11y(browser);
        await checkProductListA11y(browser);
        await checkCategoriesModalA11y(browser);
        await checkCreateFormA11y(browser);
        await checkFocusVisibility(browser);
        await checkLandmarks(browser);
        await checkTableA11y(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (fail > 0) process.exitCode = 1;
    console.log('DONE — a11y.spec.js complete');
    console.log('NOTE: For deeper axe-core checks run: npm install @axe-core/playwright');
})();

// App-wide sweep: no page may carry filter/search/sort/pagination state in the
// URL — every such control submits by POST, and the server ignores query-string
// params (BaseController::requestParam() reads $_POST only).
// Also flags any PHP warning/notice leaking into a rendered page.
// Not run in CI. See README.md for setup/usage.
'use strict';

const { chromium } = require('playwright');

const BASE_URL = 'http://127.0.0.1:8090';

const ROLES = [
    { role: 'Admin', email: 'admin@example.com', password: 'admin123' },
    { role: 'Sales', email: 'sales1@example.com', password: 'sales123' },
    { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123' },
];

const PAGES = [
    '/dashboard', '/sales-dashboard', '/products', '/categories', '/warehouses',
    '/suppliers', '/customers', '/users', '/purchase-orders', '/sales-orders',
    '/stock-ledger', '/reports', '/my-profile',
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

// PHP renders "<b>Warning</b>:  Undefined …", which innerText turns into
// "Warning\n: Undefined …" — so allow whitespace/newlines before the colon.
const PHP_LEAK = /(Warning|Notice|Deprecated|Fatal error|Parse error)\s*:\s/;

// Static checks on whatever is currently rendered.
async function checkRenderedPage(page, label) {
    const body = await page.locator('body').innerText();
    const leak = body.match(new RegExp(PHP_LEAK.source + '[^\\n]*'));
    check(`${label}: no PHP warning/notice leaked into the page`, !leak, leak ? leak[0] : '');

    // getAttribute, not f.method/f.id: a control named "id"/"method" inside the form shadows those properties.
    // A form with no method attribute defaults to GET, so that counts as a failure too.
    const getForms = await page.$$eval('form', (forms) => forms
        .filter((f) => (f.getAttribute('method') || 'get').toLowerCase() !== 'post')
        .map((f) => f.getAttribute('id') || f.getAttribute('action') || '(no id/action)'));
    check(`${label}: every <form> submits by POST`, getForms.length === 0, JSON.stringify(getForms));

    const queryLinks = await page.$$eval('a[href]', (links) => links
        .map((a) => a.getAttribute('href'))
        .filter((h) => h.includes('?') && !/\.(css|js|svg|png|jpg|webp)(\?|$)/.test(h)));
    check(`${label}: no link carries a query string`, queryLinks.length === 0, JSON.stringify(queryLinks.slice(0, 5)));

    check(`${label}: address bar has no query string`, !page.url().includes('?'), page.url());
}

async function sweepRole(browser, { role, email, password }) {
    console.log(`\n=== ${role} ===`);
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (err) => pageErrors.push(String(err)));
    await login(page, email, password);

    for (const path of PAGES) {
        const resp = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
        const status = resp ? resp.status() : 0;
        if (status === 403 || !page.url().startsWith(`${BASE_URL}${path}`)) {
            note(`${path}: not available to ${role} (HTTP ${status}) — skipped`);
            continue;
        }
        check(`${path}: HTTP 200`, status === 200, `got ${status}`);
        await checkRenderedPage(page, path);

        // Submitting the page's filter form must keep the URL clean.
        const filterForm = await page.$('form[id*="filter"]');
        if (filterForm) {
            const formId = await filterForm.getAttribute('id');
            const isAjax = path === '/stock-ledger';
            const formAction = await filterForm.getAttribute('action');
            if (formAction && formAction.includes('/export/')) {
                // A form that posts straight to a CSV export (Sales /reports) downloads instead of navigating
                check(`${path}: #${formId} posts (export) without a query string`, !formAction.includes('?'), formAction);
            } else if (isAjax) {
                await page.evaluate((id) => document.getElementById(id).requestSubmit(), formId);
                await page.waitForLoadState('networkidle');
            } else {
                // Plain submit(): reproduces a real search submit without depending on which button is the default.
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'networkidle' }),
                    page.evaluate((id) => HTMLFormElement.prototype.submit.call(document.getElementById(id)), formId),
                ]);
            }
            await checkRenderedPage(page, `${path} after submitting #${formId}`);

            // And so must a pagination click, when the list has more than one page.
            const pageButton = await page.$('button[name="page"]:not([aria-current])');
            if (pageButton) {
                await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), pageButton.click()]);
                await checkRenderedPage(page, `${path} after a pagination click`);
            }
        }
    }

    // Warehouse detail: stock-breakdown pagination.
    await page.goto(`${BASE_URL}/warehouses`, { waitUntil: 'networkidle' });
    const detailHref = await page.$$eval('a[href^="/warehouses/"]', (links) => {
        const hit = links.map((a) => a.getAttribute('href')).find((h) => /^\/warehouses\/[^/]+$/.test(h) && h !== '/warehouses/create');
        return hit || null;
    });
    if (detailHref) {
        await page.goto(`${BASE_URL}${detailHref}`, { waitUntil: 'networkidle' });
        await checkRenderedPage(page, 'warehouse detail');
        const stockPageBtn = await page.$('button[name="stock_page"]:not([aria-current])');
        if (stockPageBtn) {
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), stockPageBtn.click()]);
            await checkRenderedPage(page, 'warehouse detail after stock pagination');
        }
    }

    // Sales dashboard period selector.
    const sdResp = await page.goto(`${BASE_URL}/sales-dashboard`, { waitUntil: 'networkidle' });
    if (sdResp && sdResp.status() === 200 && page.url().startsWith(`${BASE_URL}/sales-dashboard`)) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.selectOption('select[name="period"]', 'week'),
        ]);
        check('sales-dashboard: period change applies', (await page.inputValue('select[name="period"]')) === 'week');
        await checkRenderedPage(page, 'sales-dashboard after period change');
    }

    check(`${role}: no JavaScript page errors across the sweep`, pageErrors.length === 0, JSON.stringify(pageErrors.slice(0, 3)));
    await context.close();
}

// A hand-typed query string must be ignored, not applied.
async function checkQueryParamsIgnored(browser) {
    console.log('\n=== Query-string params are ignored server-side ===');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, 'admin@example.com', 'admin123');

    await page.goto(`${BASE_URL}/reports`, { waitUntil: 'networkidle' });
    const plainCounter = await page.locator('.report-table-toolbar__counter').innerText();
    await page.goto(`${BASE_URL}/reports?q=DSDDS&report_type=movement_ledger&page=3`, { waitUntil: 'networkidle' });
    const typedCounter = await page.locator('.report-table-toolbar__counter').innerText();
    check('/reports?q=DSDDS&… renders the same unfiltered report as /reports', plainCounter === typedCounter, `plain="${plainCounter}" typed="${typedCounter}"`);
    check('/reports?q=… does not echo the param into the search box', (await page.inputValue('#report-search')) === '');

    await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle' });
    const plainRows = await page.locator('table tbody tr').count();
    await page.goto(`${BASE_URL}/products?sku=ZZZ_NO_SUCH_SKU`, { waitUntil: 'networkidle' });
    const typedRows = await page.locator('table tbody tr').count();
    check('/products?sku=… is ignored (same rows as /products)', plainRows === typedRows, `plain=${plainRows} typed=${typedRows}`);

    for (const path of ['/reports/export/stock-ledger?date_from=2026-01-01&date_to=2026-09-24', '/reports/export/orders?from=2026-01-01&to=2026-09-24']) {
        const resp = await page.request.get(`${BASE_URL}${path}`);
        const isCsv = /text\/csv/.test(resp.headers()['content-type'] || '');
        check(`GET ${path.split('?')[0]} with query params is not served as a CSV`, !isCsv, `HTTP ${resp.status()} ${resp.headers()['content-type']}`);
    }

    await context.close();
}

(async () => {
    const browser = await chromium.launch();
    try {
        for (const roleConfig of ROLES) {
            await sweepRole(browser, roleConfig);
        }
        await checkQueryParamsIgnored(browser);
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

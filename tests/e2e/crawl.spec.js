// Crawls every reachable page for each role and flags runtime defects:
// HTTP 4xx/5xx on followed links, PHP warnings/notices leaking into the page,
// JavaScript errors, failed sub-resource requests, and links a role is shown
// but then refused (403). Also submits every "create" form empty, since the
// validation-failure re-render path is where views most often reference a
// variable the controller didn't pass.
// Not run in CI. See README.md for setup/usage.
'use strict';

const { chromium } = require('playwright');
const { execSync } = require('child_process');

const BASE_URL = 'http://127.0.0.1:8090';
const MAX_PER_PATTERN = 3;
const MAX_PAGES = 400;

const ROLES = [
    { role: 'Admin', email: 'admin@example.com', password: 'admin123' },
    { role: 'Sales', email: 'sales1@example.com', password: 'sales123' },
    { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123' },
];

const SKIP = [/^\/logout/, /^\/login/, /^\/assets\//, /^\/uploads\//, /^\/api\//, /\.(csv|png|jpe?g|webp|svg|pdf)$/i];
// PHP renders "<b>Warning</b>:  Undefined …", which innerText turns into
// "Warning\n: Undefined …" — so allow whitespace/newlines before the colon.
const PHP_LEAK = /(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\s*:\s*[^\n]{0,200}/;

let pass = 0;
let fail = 0;
const findings = [];

function check(label, condition, detail) {
    if (condition) {
        pass += 1;
    } else {
        fail += 1;
        findings.push(`${label}${detail ? ' — ' + detail : ''}`);
        console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
    }
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

// Obfuscated ids (IdObfuscator::encode() → long hex, see REGEX_ID_TOKEN) and
// plain digits collapse to ":id" so we visit a few representatives of each
// page type instead of every product/order.
function patternOf(path) {
    return path.split('/').map((seg) => (/^\d+$/.test(seg) || /^[0-9a-f]{8,}$/.test(seg) ? ':id' : seg)).join('/');
}

function normalise(href) {
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return null;
    let url;
    try { url = new URL(href, BASE_URL); } catch (_) { return null; }
    if (url.origin !== BASE_URL) return null;
    const path = url.pathname.replace(/\/+$/, '') || '/';
    if (SKIP.some((re) => re.test(path))) return null;
    return path;
}

async function inspect(page, label, resp, issues) {
    const status = resp ? resp.status() : 0;
    const body = await page.locator('body').innerText().catch(() => '');
    const leak = body.match(PHP_LEAK);
    check(`${label}: no PHP warning/error in page`, !leak, leak ? leak[0] : '');
    if (/An internal error occurred/i.test(body)) {
        check(`${label}: no generic internal-error page`, false, `HTTP ${status}`);
    }
    for (const issue of issues.splice(0)) {
        check(`${label}: ${issue.kind}`, false, issue.detail);
    }
    return status;
}

async function crawlRole(browser, { role, email, password }) {
    console.log(`\n=== ${role} ===`);
    const context = await browser.newContext();
    const page = await context.newPage();
    const issues = [];
    // During the empty-form submits a 400 document is the expected answer
    // (validation failure re-render), and Chrome logs it as a console error.
    let expectingValidation400 = false;
    page.on('pageerror', (err) => issues.push({ kind: 'no JavaScript error', detail: String(err).slice(0, 200) }));
    page.on('console', (msg) => {
        const expected400 = expectingValidation400 && /status of 400/.test(msg.text());
        if (msg.type() === 'error' && !/status of 403/.test(msg.text()) && !expected400) {
            issues.push({ kind: 'no console error', detail: msg.text().slice(0, 200) });
        }
    });
    page.on('response', (r) => {
        const u = new URL(r.url());
        if (u.origin === BASE_URL && r.status() >= 400 && r.request().resourceType() !== 'document') {
            issues.push({ kind: 'no failed sub-request', detail: `${r.request().method()} ${u.pathname} → ${r.status()}` });
        }
    });

    await login(page, email, password);

    const queue = [{ path: '/dashboard', from: '(start)' }];
    const seenPaths = new Set();
    const perPattern = new Map();
    const createForms = new Set();
    let visited = 0;

    while (queue.length > 0 && visited < MAX_PAGES) {
        const { path, from } = queue.shift();
        if (seenPaths.has(path)) continue;
        seenPaths.add(path);
        const pattern = patternOf(path);
        const count = perPattern.get(pattern) || 0;
        if (count >= MAX_PER_PATTERN) continue;
        perPattern.set(pattern, count + 1);

        visited += 1;
        const resp = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' }).catch(() => null);
        const status = await inspect(page, `${role} ${path}`, resp, issues);
        if (status === 403) {
            check(`${role} ${path}: link shown to this role is not refused (403)`, false, `linked from ${from}`);
            continue;
        }
        check(`${role} ${path}: HTTP 2xx`, status >= 200 && status < 300, `HTTP ${status}, linked from ${from}`);
        if (status >= 300) continue;

        if (/\/create$/.test(path)) createForms.add(path);

        const hrefs = await page.$$eval('a[href]', (links) => links.map((a) => a.getAttribute('href'))).catch(() => []);
        for (const href of hrefs) {
            const next = normalise(href);
            if (next && !seenPaths.has(next)) queue.push({ path: next, from: path });
        }
    }
    console.log(`  visited ${visited} pages (${perPattern.size} page types)`);

    // Validation-failure re-render: submit each create form with nothing filled in.
    for (const path of createForms) {
        await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
        const form = await page.$('main form[method="post"]:not([action*="logout"]), form[method="post"][action$="' + path.replace(/\/create$/, '') + '"]');
        if (!form) continue;
        await page.$$eval('form input:not([type=hidden]):not([type=checkbox]):not([type=radio]), form textarea', (els) => els.forEach((el) => {
            el.value = '';
            el.removeAttribute('required');
        }));
        expectingValidation400 = true;
        const [resp] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => null),
            form.evaluate((f) => { f.noValidate = true; HTMLFormElement.prototype.submit.call(f); }),
        ]);
        const status = await inspect(page, `${role} POST (empty) from ${path}`, resp, issues);
        expectingValidation400 = false;
        check(`${role} POST (empty) from ${path}: validation failure is handled (no 5xx)`, status < 500, `HTTP ${status}`);
        // PROJECT_REFERENCE: "Validasi gagal → 400 Bad Request, form di-render ulang"
        check(`${role} POST (empty) from ${path}: answered with HTTP 400`, status === 400, `HTTP ${status}`);
    }

    await context.close();
}

// display_errors is off, so PHP warnings no longer reach the HTML: read them
// from the app container's log instead. Set IOM_APP_CONTAINER='' to skip.
const APP_CONTAINER = process.env.IOM_APP_CONTAINER ?? 'iom_app';

function checkServerLog(sinceIso) {
    if (APP_CONTAINER === '') {
        console.log('\n  [SKIP] server log check (IOM_APP_CONTAINER empty)');
        return;
    }
    let log = '';
    try {
        // Apache writes PHP errors to the container's stderr, so merge it into stdout
        log = execSync(`docker logs --since ${sinceIso} ${APP_CONTAINER} 2>&1`, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    } catch (e) {
        console.log(`\n  [SKIP] server log check (docker logs failed: ${String(e.message).split('\n')[0]})`);
        return;
    }
    const leaks = log.split('\n').filter((line) => /PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|Uncaught /.test(line));
    check('server log: no PHP warning/notice/fatal during the crawl', leaks.length === 0, leaks.slice(0, 5).join(' | '));
}

(async () => {
    const startedAt = new Date(Date.now() - 1000).toISOString();
    const browser = await chromium.launch();
    try {
        for (const roleConfig of ROLES) {
            await crawlRole(browser, roleConfig);
        }
    } finally {
        await browser.close();
    }

    checkServerLog(startedAt);

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    if (findings.length > 0) {
        console.log('\nFindings:');
        [...new Set(findings)].forEach((f) => console.log(`  - ${f}`));
    }
    process.exit(fail > 0 ? 1 : 0);
})();

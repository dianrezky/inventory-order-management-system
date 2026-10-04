// REPORT-01: clicks every CSV export button the way a user does and asserts
// a real .csv download arrives (not just that the button exists).
//
// Run: node csv-export.spec.js   (BASE_URL defaults to http://localhost:8090)

const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8090';

// [page, button selector, expected filename] per role — per the REPORT-01 access matrix
const CASES = [
    { role: 'Admin', email: 'admin@example.com', password: 'admin123', buttons: [
        ['/reports', '#export-csv-btn', 'stock-ledger.csv'],
        ['/reports', '#export-orders-btn', 'orders.csv'],
        ['/categories', 'button[formaction="/categories/export"]', '.csv'],
        ['/sales-dashboard', '#so-export-btn', '.csv'],
    ] },
    { role: 'Sales', email: 'sales1@example.com', password: 'sales123', buttons: [
        ['/reports', '#export-orders-btn', 'orders.csv'],
        ['/sales-dashboard', '#so-export-btn', '.csv'],
    ] },
    { role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123', buttons: [
        ['/reports', '#export-csv-btn', 'stock-ledger.csv'],
    ] },
];

let pass = 0;
let fail = 0;

function check(label, ok, detail) {
    if (ok) {
        pass += 1;
        console.log(`  [PASS] ${label}`);
    } else {
        fail += 1;
        console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
    }
}

async function login(page, email, password) {
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

(async () => {
    const browser = await chromium.launch();
    try {
        for (const { role, email, password, buttons } of CASES) {
            console.log(`\n=== ${role} ===`);
            const context = await browser.newContext({ acceptDownloads: true, viewport: { width: 1440, height: 900 } });
            const page = await context.newPage();
            await login(page, email, password);

            for (const [path, selector, expectedName] of buttons) {
                const label = `${role} ${path} ${selector}`;
                await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
                const button = page.locator(selector).first();
                if ((await button.count()) === 0 || !(await button.isVisible())) {
                    check(`${label}: button is visible`, false, 'not found / hidden');
                    continue;
                }
                try {
                    const [download] = await Promise.all([
                        page.waitForEvent('download', { timeout: 15000 }),
                        button.click(),
                    ]);
                    const name = download.suggestedFilename();
                    const stream = await download.createReadStream();
                    let body = '';
                    for await (const chunk of stream) body += chunk;
                    const lines = body.split(/\r?\n/).filter((l) => l !== '');
                    check(`${label}: downloads a CSV (${name})`, name.endsWith(expectedName), `got "${name}"`);
                    check(`${label}: CSV has a header row`, lines.length >= 1 && lines[0].includes(','), JSON.stringify(lines[0] || ''));
                    console.log(`    rows: ${Math.max(0, lines.length - 1)}`);
                } catch (e) {
                    check(`${label}: click starts a download`, false, String(e.message).split('\n')[0]);
                }
            }
            await context.close();
        }
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

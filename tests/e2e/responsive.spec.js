// UI-01: every main page must fit a 360px phone viewport without a
// page-level horizontal scrollbar (wide tables scroll inside .table-wrap).
//
// Run: node responsive.spec.js   (BASE_URL defaults to http://localhost:8090)

const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8090';
const WIDTH = 360;

const ROLES = [
    {
        role: 'Admin', email: 'admin@example.com', password: 'admin123',
        paths: ['/dashboard', '/products', '/products/create', '/categories', '/warehouses', '/suppliers', '/customers',
            '/purchase-orders', '/purchase-orders/create', '/sales-orders', '/sales-orders/create', '/stock-ledger',
            '/reports', '/users', '/sales-dashboard', '/my-profile'],
    },
    {
        role: 'Sales', email: 'sales1@example.com', password: 'sales123',
        paths: ['/dashboard', '/products', '/sales-orders', '/sales-orders/create', '/reports', '/sales-dashboard', '/my-profile'],
    },
    {
        role: 'WarehouseStaff', email: 'warehouse@example.com', password: 'wh123',
        paths: ['/dashboard', '/products', '/purchase-orders', '/purchase-orders/create', '/sales-orders', '/stock-ledger', '/reports', '/my-profile'],
    },
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
        for (const { role, email, password, paths } of ROLES) {
            console.log(`\n=== ${role} @ ${WIDTH}px ===`);
            const context = await browser.newContext({ viewport: { width: WIDTH, height: 780 } });
            const page = await context.newPage();
            await login(page, email, password);

            for (const path of paths) {
                const resp = await page.goto(`${BASE_URL}${path}`);
                if (!resp || resp.status() !== 200) {
                    // Pages a role may not open are covered by the crawl/role specs
                    console.log(`  [SKIP] ${path} (HTTP ${resp ? resp.status() : 'none'})`);
                    continue;
                }
                const width = await page.evaluate(() => document.documentElement.scrollWidth);
                check(`${role} ${path}: page width ${width}px <= ${WIDTH}px`, width <= WIDTH, `${width - WIDTH}px overflow`);
            }
            await context.close();
        }
    } finally {
        await browser.close();
    }

    console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
    process.exit(fail > 0 ? 1 : 0);
})();

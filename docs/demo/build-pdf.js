// Menghasilkan IOMS-Panduan-Demo.pdf dari panduan-demo.html (Chromium headless).
// Pakai: NODE_PATH=<folder node_modules berisi playwright> node docs/demo/build-pdf.js
const path = require('path');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('file://' + path.join(__dirname, 'panduan-demo.html'), { waitUntil: 'load' });
  await page.pdf({
    path: path.join(__dirname, 'IOMS-Panduan-Demo.pdf'),
    format: 'A4',
    printBackground: true,
    margin: { top: '0', right: '0', bottom: '10mm', left: '0' },
    displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: '<div style="width:100%;font-size:8px;color:#666;text-align:right;padding-right:12mm"><span class="pageNumber"></span> / <span class="totalPages"></span></div>',
  });
  await browser.close();
})();

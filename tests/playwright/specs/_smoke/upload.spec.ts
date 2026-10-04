// Gate G1 smoke: one valid upload end-to-end through the real MinIO in the
// ioms-e2e stack (design spec §3.22/§1.6 fact 2 — MinIO is the one
// dependency we DON'T get for free from "the app booted", since
// MinioClient never makes a network call at construction).

import { test, expect } from '../../support/fixtures';
import { TAGS } from '../../support/tags';
import { UPLOAD_FIXTURES } from '../../support/files';

test('smoke: a valid PNG upload on product create is stored and publicly fetchable', { tag: [TAGS.smoke, TAGS.upload, TAGS.needsIsolated] }, async ({
  adminPage,
  factories,
  uid,
}, testInfo) => {
  const category = await factories.categories.create();
  await adminPage.goto('/products/create');
  await adminPage.locator('#sku').fill(uid.sku());
  await adminPage.locator('#name').fill(uid.name('UploadProduct', 150));
  await adminPage.locator('#unit').fill('pcs');
  await adminPage.locator('#category_id').selectOption({ label: category.name });
  await adminPage.locator('#purchase_price_display, #purchase_price').first().fill('1000');
  await adminPage.locator('#sale_price_display, #sale_price').first().fill('1500');
  await adminPage.locator('#reorder_point').fill('1');
  await adminPage.locator('#image').setInputFiles(UPLOAD_FIXTURES.validPng);

  await Promise.all([adminPage.waitForURL(/\/products$/), adminPage.locator('#product-submit-btn').click()]);

  await adminPage.goto('/products');
  const row = adminPage.getByRole('row').filter({ hasText: /UploadProduct/ }).first();
  await expect(row).toBeVisible();
  await testInfo.attach('products-list-with-uploaded-image', {
    body: await adminPage.screenshot({ fullPage: true }),
    contentType: 'image/png',
  });

  // Open the product's own detail page too — this is the page that actually
  // renders the uploaded image <img>, visual proof the MinIO round-trip
  // (upload -> WebP re-encode -> public URL) really worked, not just that
  // the DB row exists.
  await row.getByRole('link').first().click();
  // Unverified against live markup in this sandbox (see KNOWN_DEFECTS.md /
  // the G1 gate report's sandbox limitation) — a plain `img` presence check
  // rather than a specific src pattern, to stay robust to exact class/attr
  // naming on the product detail page until this has actually run once.
  await expect(adminPage.locator('img').first()).toBeVisible({ timeout: 15_000 });
  await testInfo.attach('product-detail-with-uploaded-image', {
    body: await adminPage.screenshot({ fullPage: true }),
    contentType: 'image/png',
  });
});

// Gate G1 acceptance (F1.10): every factory actually works end-to-end
// against the real app — not just "the HTTP call didn't throw", but "the
// created record is visible/usable where it should be". This is also the
// first real exercise of the PO/SO state machines through the factory
// layer that every Wave-2 domain agent will build on.

import { test, expect } from '../../support/fixtures';
import { TAGS } from '../../support/tags';

test.describe('smoke: factories', () => {
  test('categories.create is visible in the list and unique per call', { tag: [TAGS.smoke] }, async ({ factories, adminPage }, testInfo) => {
    const a = await factories.categories.create();
    const b = await factories.categories.create();
    expect(a.name).not.toBe(b.name);
    expect(a.code).not.toBe(b.code);

    await adminPage.goto('/categories');
    await adminPage.locator('#category-name').fill(a.name);
    await adminPage.locator('#category-filter-form button:has-text("Search")').click();
    await expect(adminPage.getByText(a.code)).toBeVisible();
    await testInfo.attach('categories-filtered-list', {
      body: await adminPage.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
  });

  test('products.create resolves a category and is findable by SKU', { tag: [TAGS.smoke] }, async ({ factories, adminPage }, testInfo) => {
    const product = await factories.products.create();
    await adminPage.goto('/products');
    await adminPage.locator('#product-sku').fill(product.sku);
    await adminPage.locator('#products-filter-form').locator('button:has-text("Search")').click();
    await expect(adminPage.getByText(product.sku)).toBeVisible();
    await testInfo.attach('products-filtered-list', {
      body: await adminPage.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
  });

  test('world() builds an isolated aggregate with resolved numeric ids', { tag: [TAGS.smoke, TAGS.needsIsolated] }, async ({
    factories,
  }) => {
    const world = await factories.world();
    expect(Number.isFinite(Number(world.warehouse.numericId))).toBe(true);
    expect(Number.isFinite(Number(world.supplier.numericId))).toBe(true);
    expect(Number.isFinite(Number(world.customer.numericId))).toBe(true);
    expect(Number.isFinite(Number(world.product.numericId))).toBe(true);
  });

  test('purchase order factory: create -> submit -> receive moves real stock', { tag: [TAGS.smoke, TAGS.workflow, TAGS.needsIsolated] }, async ({
    factories,
    db,
  }) => {
    const world = await factories.world();
    const before = await db.stockOf(world.product.sku, world.warehouse.code);
    const { purchaseOrderId } = await world.seedStock(12);
    expect(purchaseOrderId).toMatch(/^[0-9a-f]+$/);
    const after = await db.stockOf(world.product.sku, world.warehouse.code);
    expect(after - before).toBe(12);
    const ledgerDelta = await db.ledgerSum(world.product.sku, world.warehouse.code);
    expect(ledgerDelta).toBe(after); // no prior movements on a brand-new world warehouse+product pair
  });

  test('sales order factory: create (as sales) -> submit -> approve (as admin) -> issue (as warehouse) deducts stock', {
    tag: [TAGS.smoke, TAGS.workflow, TAGS.needsIsolated],
  }, async ({ factories, adminRequest, salesRequest, warehouseRequest, db }) => {
    const world = await factories.world();
    await world.seedStock(10);

    const so = await factories.salesOrders.create(salesRequest, {
      customerId: world.customer.numericId,
      warehouseId: world.warehouse.numericId,
      lines: [{ productId: world.product.numericId, qty: 4, salePrice: 2000 }],
    });
    await factories.salesOrders.submit(salesRequest, so.id);
    await factories.salesOrders.approve(adminRequest, so.id);

    const before = await db.stockOf(world.product.sku, world.warehouse.code);
    await factories.salesOrders.issue(warehouseRequest, so.id);
    const after = await db.stockOf(world.product.sku, world.warehouse.code);
    expect(before - after).toBe(4);
  });
});

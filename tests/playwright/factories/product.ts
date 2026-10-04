// Product factory (design spec §8 item 5 / §1.5). ProductController::storeAction
// redirects to the LIST on success (self::ROUTE_PRODUCTS), not to the new
// product's detail page, so the token is resolved via `/products/search`
// filtered by the SKU we just created (unique, so exactly one row matches).

import type { APIRequestContext } from '@playwright/test';
import { createViaForm, findTokenBySearch, resolveSelectValueByText } from './_common';
import { createUid } from '../support/unique';
import type { CategoryFactory } from './category';

export interface ProductOptions {
  sku?: string;
  name?: string;
  categoryId?: number | string; // real numeric category_id the form posts, NOT the obfuscated token
  unit?: string;
  description?: string;
  purchasePrice?: number;
  salePrice?: number;
  reorderPoint?: number;
  barcode?: string;
  /** Opening stock, applied only if BOTH are set and qty>0 — the create
   * form's initial_warehouse_id/initial_qty fields (KNOWN_DEFECT DEF-04 if
   * only one is set: ProductController::storeAction silently ignores a
   * half-filled pair instead of validating it — see KNOWN_DEFECTS.md). */
  initialStock?: { warehouseId: number | string; qty: number };
}

export interface CreatedProduct {
  id: string; // obfuscated token
  sku: string;
  name: string;
}

export function createProductFactory(request: APIRequestContext, workerIndex: number, categories: CategoryFactory) {
  return {
    async create(opts: ProductOptions = {}): Promise<CreatedProduct> {
      const uid = createUid(workerIndex);
      const sku = opts.sku ?? uid.sku();
      const name = opts.name ?? uid.name('Product', 150);
      let categoryId = opts.categoryId;
      if (categoryId === undefined) {
        // Products require an existing, active category_id (the real numeric
        // id the <select> posts) — create a throwaway one bound to this
        // product's own uid token rather than reusing a seed category, so
        // this factory never depends on seed state being untouched.
        const created = await categories.create({ name: uid.name('cat-for-product', 80) });
        categoryId = await resolveSelectValueByText(request, '/products/create', 'category_id', created.name);
      }

      const form: Record<string, string> = {
        sku,
        name,
        unit: opts.unit ?? 'pcs',
        description: opts.description ?? 'E2E test product',
        category_id: String(categoryId),
        purchase_price: String(opts.purchasePrice ?? 10000),
        sale_price: String(opts.salePrice ?? 15000),
        reorder_point: String(opts.reorderPoint ?? 5),
        barcode: opts.barcode ?? '',
      };
      if (opts.initialStock) {
        form.initial_warehouse_id = String(opts.initialStock.warehouseId);
        form.initial_qty = String(opts.initialStock.qty);
      }

      await createViaForm(request, '/products/create', '/products', form);
      const id = await findTokenBySearch(request, '/products', { sku });
      return { id, sku, name };
    },

    async setActive(id: string, active: boolean): Promise<void> {
      const action = active ? 'activate' : 'deactivate';
      await createViaForm(request, '/products/create', `/products/${id}/${action}`, {});
    },
  };
}

export type ProductFactory = ReturnType<typeof createProductFactory>;

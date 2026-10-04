// `world` — an isolated mini-aggregate (design spec §8 item 5 / §15):
// its own warehouse, category, product, supplier and customer, so a
// workflow/concurrency spec never shares an aggregate (or its stock) with
// any other test, in or out of this run.
//
// Numeric ids (as opposed to the obfuscated tokens the individual
// factories return) are resolved once here, by reading the real option
// values off the PO/SO create forms' own <select>s / JSON blob — see
// factories/_common.ts resolveSelectValueByText / resolveSoProductId.

import type { APIRequestContext } from '@playwright/test';
import { createCategoryFactory } from './category';
import { createProductFactory } from './product';
import { createWarehouseFactory } from './warehouse';
import { createCustomerFactory, createSupplierFactory } from './party';
import { resolveSelectValueByText, resolveSoProductId } from './_common';
import { createStockSeeder } from './stock';

export interface World {
  warehouse: { id: string; numericId: string; code: string; name: string };
  category: { id: string; name: string };
  product: { id: string; numericId: string; sku: string; name: string };
  supplier: { id: string; numericId: string; name: string };
  customer: { id: string; numericId: string; name: string };
  /** Seeds `qty` units of real stock onto this world's product+warehouse
   * via a full PO create->submit->receive cycle. */
  seedStock(qty: number, opts?: { receiverRequest?: APIRequestContext }): Promise<{ purchaseOrderId: string }>;
}

/** `adminRequest` performs every creation call — the admin role can create
 * every master-data type, which keeps world-building independent of which
 * roles a given spec actually wants to test. */
export async function createWorld(adminRequest: APIRequestContext, workerIndex: number): Promise<World> {
  const warehouses = createWarehouseFactory(adminRequest, workerIndex);
  const categories = createCategoryFactory(adminRequest, workerIndex);
  const products = createProductFactory(adminRequest, workerIndex, categories);
  const suppliers = createSupplierFactory(adminRequest, workerIndex);
  const customers = createCustomerFactory(adminRequest, workerIndex);

  const warehouse = await warehouses.create();
  const category = await categories.create();
  const product = await products.create({});
  const supplier = await suppliers.create();
  const customer = await customers.create();

  const [warehouseNumericId, supplierNumericId, customerNumericId] = await Promise.all([
    resolveSelectValueByText(adminRequest, '/purchase-orders/create', 'destination_warehouse_id', warehouse.name),
    resolveSelectValueByText(adminRequest, '/purchase-orders/create', 'supplier_id', supplier.name),
    resolveSelectValueByText(adminRequest, '/sales-orders/create', 'customer_id', customer.name),
  ]);
  const productNumericId = await resolveSoProductId(adminRequest, product.sku);

  const seeder = createStockSeeder(adminRequest, workerIndex);

  return {
    warehouse: { ...warehouse, numericId: warehouseNumericId },
    category,
    product: { ...product, numericId: productNumericId },
    supplier: { ...supplier, numericId: supplierNumericId },
    customer: { ...customer, numericId: customerNumericId },
    async seedStock(qty, opts) {
      return seeder.seed({
        supplierNumericId,
        warehouseNumericId,
        productNumericId,
        qty,
        receiverRequest: opts?.receiverRequest,
      });
    },
  };
}

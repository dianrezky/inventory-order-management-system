// Factory API (FOUNDATION_CONTRACT.md §5). The `factories` fixture
// (support/fixtures.ts) binds all of these to the adminRequest context by
// default — Admin can create every master-data type. Workflow factories
// that care about WHO performs an action (SO create/submit/cancel; PO
// receive) take an explicit request-context argument instead of silently
// reusing the bound one, so a spec can never accidentally create "as
// admin" when it meant to create "as sales".
//
// Each factory generates a FRESH uid token on every `.create()` call
// (never one shared token bound at factory-construction time) so a single
// test calling e.g. `factories.categories.create()` three times gets three
// distinct names, not three collisions.

import type { APIRequestContext } from '@playwright/test';
import { createCategoryFactory, type CategoryFactory } from './category';
import { createProductFactory, type ProductFactory } from './product';
import { createWarehouseFactory, type WarehouseFactory } from './warehouse';
import { createCustomerFactory, createSupplierFactory, type CustomerFactory, type SupplierFactory } from './party';
import { createUserFactory, type UserFactory } from './user';
import { createPurchaseOrderFactory, type PurchaseOrderFactory } from './purchase-order';
import { createSalesOrderFactory, type SalesOrderFactory } from './sales-order';
import { createStockSeeder, type StockSeeder } from './stock';
import { createWorld, type World } from './world';

export interface Factories {
  categories: CategoryFactory;
  products: ProductFactory;
  warehouses: WarehouseFactory;
  suppliers: SupplierFactory;
  customers: CustomerFactory;
  users: UserFactory;
  purchaseOrders: PurchaseOrderFactory;
  salesOrders: SalesOrderFactory;
  stock: StockSeeder;
  /** Builds a fresh isolated mini-aggregate (own warehouse/category/
   * product/supplier/customer) — the standard starting point for any
   * workflow or concurrency spec (design spec §3.14/§15). */
  world(): Promise<World>;
}

/** `workerIndex` is threaded through from the `factories` fixture's own
 * testInfo.parallelIndex (support/fixtures.ts) — every uid token this
 * module's factories generate stays worker-scoped. */
export function createFactories(adminRequest: APIRequestContext, workerIndex: number): Factories {
  const categories = createCategoryFactory(adminRequest, workerIndex);
  const products = createProductFactory(adminRequest, workerIndex, categories);
  const warehouses = createWarehouseFactory(adminRequest, workerIndex);
  const suppliers = createSupplierFactory(adminRequest, workerIndex);
  const customers = createCustomerFactory(adminRequest, workerIndex);
  const users = createUserFactory(adminRequest, workerIndex);
  const purchaseOrders = createPurchaseOrderFactory(adminRequest, workerIndex);
  const salesOrders = createSalesOrderFactory(adminRequest, workerIndex);
  const stock = createStockSeeder(adminRequest, workerIndex);

  return {
    categories,
    products,
    warehouses,
    suppliers,
    customers,
    users,
    purchaseOrders,
    salesOrders,
    stock,
    world: () => createWorld(adminRequest, workerIndex),
  };
}

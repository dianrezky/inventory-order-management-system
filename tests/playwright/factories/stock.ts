// Stock-seeding helper (design spec §8 item 5 / §14). Deliberately goes
// through a REAL Purchase Order create -> submit -> receive cycle rather
// than any shortcut — "test-owned stock" means stock that arrived via the
// same business process the app enforces everywhere else, so a seeded
// aggregate also exercises (and benefits from) the PO invariants.

import type { APIRequestContext } from '@playwright/test';
import { createPurchaseOrderFactory } from './purchase-order';

export interface StockSeedOptions {
  supplierNumericId: number | string;
  warehouseNumericId: number | string;
  productNumericId: number | string;
  qty: number;
  /** Who receives the goods — must hold purchase_orders.manage (Admin or
   * WarehouseStaff). Defaults to the same context used for create/submit. */
  receiverRequest?: APIRequestContext;
}

export function createStockSeeder(adminRequest: APIRequestContext, workerIndex: number) {
  const po = createPurchaseOrderFactory(adminRequest, workerIndex);
  return {
    /** Creates a single-line PO, submits it (Admin-only) and receives the
     * full quantity, leaving `qty` units of real, ledger-backed stock on
     * the given product+warehouse. Returns the PO token for traceability. */
    async seed(opts: StockSeedOptions): Promise<{ purchaseOrderId: string }> {
      const created = await po.create({
        supplierId: opts.supplierNumericId,
        warehouseId: opts.warehouseNumericId,
        lines: [{ productId: opts.productNumericId, qtyOrdered: opts.qty, purchasePrice: 1000 }],
      });
      await po.submit(adminRequest, created.id);
      const receivable = await po.readReceivableLines(adminRequest, created.id);
      if (receivable.length !== 1) {
        throw new Error(`Expected exactly 1 receivable line on seeded PO ${created.id}, found ${receivable.length}.`);
      }
      const receiver = opts.receiverRequest ?? adminRequest;
      // Length checked above (=== 1), so index 0 is guaranteed present.
      await po.receive(receiver, created.id, { [receivable[0]!.itemId]: opts.qty });
      return { purchaseOrderId: created.id };
    },
  };
}

export type StockSeeder = ReturnType<typeof createStockSeeder>;

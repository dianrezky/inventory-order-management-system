// Purchase Order factory (design spec §8 item 5 / §1.5). create() redirects
// straight to the new PO's detail page with its token already in the
// Location header (unlike the master-data factories) — no search needed.
//
// submit()/cancel() require `purchase_orders.submit`/`.cancel`, which are
// ADMIN-ONLY (PurchaseOrderController) — callers must pass an admin request
// context even if a WarehouseStaff request created/will receive the PO.
// receive() needs `purchase_orders.manage` (Admin or WarehouseStaff).

import type { APIRequestContext } from '@playwright/test';
import { post } from '../support/request';
import { createViaForm, FactoryError, tokenFromPath } from './_common';
import { todayYmd } from '../support/dates';
import { createUid } from '../support/unique';

export interface PoLine {
  productId: number | string; // real numeric product_id (see note in product.ts/resolveSelectValueByText)
  qtyOrdered: number;
  purchasePrice: number;
}

export interface PoOptions {
  supplierId: number | string;
  warehouseId: number | string;
  orderDate?: string;
  note?: string;
  lines: PoLine[];
}

export interface CreatedPo {
  id: string; // obfuscated token
}

export function createPurchaseOrderFactory(request: APIRequestContext, workerIndex: number) {
  return {
    async create(opts: PoOptions): Promise<CreatedPo> {
      const form: Record<string, string | string[]> = {
        supplier_id: String(opts.supplierId),
        destination_warehouse_id: String(opts.warehouseId),
        order_date: opts.orderDate ?? todayYmd(),
        note: opts.note ?? createUid(workerIndex).reason(),
        item_product_id: opts.lines.map((l) => String(l.productId)),
        item_qty_ordered: opts.lines.map((l) => String(l.qtyOrdered)),
        item_purchase_price: opts.lines.map((l) => String(l.purchasePrice)),
      };
      const res = await createViaForm(request, '/purchase-orders/create', '/purchase-orders', form);
      return { id: tokenFromPath(res.location as string) };
    },

    /** Admin-only (purchase_orders.submit). Pass an admin request context. */
    async submit(adminRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(adminRequest, `/purchase-orders/${id}/submit`, {}, { csrf: 'valid', csrfSourcePath: `/purchase-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /purchase-orders/${id}/submit`, res.status, res.body);
    },

    /** Admin-only (purchase_orders.cancel). */
    async cancel(adminRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(adminRequest, `/purchase-orders/${id}/cancel`, {}, { csrf: 'valid', csrfSourcePath: `/purchase-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /purchase-orders/${id}/cancel`, res.status, res.body);
    },

    /** `receiverRequest` needs purchase_orders.manage (Admin or WarehouseStaff).
     * `lines` maps real purchase_order_item DB id -> quantity now received
     * (views/purchase/receive.php names inputs `qty_now[{item->id}]` — a
     * genuinely stable, non-obfuscated per-line key, see design spec §13). */
    async receive(receiverRequest: APIRequestContext, id: string, lines: Record<string, number>): Promise<void> {
      const form: Record<string, string> = {};
      for (const [itemId, qty] of Object.entries(lines)) form[`qty_now[${itemId}]`] = String(qty);
      const res = await post(receiverRequest, `/purchase-orders/${id}/receive`, form, {
        csrf: 'valid',
        csrfSourcePath: `/purchase-orders/${id}/receive`,
      });
      if (res.status !== 302) throw new FactoryError(`POST /purchase-orders/${id}/receive`, res.status, res.body);
    },

    /** Reads the real purchase_order_item ids + remaining qty off the
     * detail page's receive form, IN DOCUMENT ORDER (the same order
     * `create()`'s `lines` were submitted in — PurchaseOrderService
     * persists/returns items in insertion order). Factories never see raw
     * DB ids from a redirect/search alone for line items, so this is the
     * one place that reads them directly off rendered markup. */
    async readReceivableLines(anyAuthedRequest: APIRequestContext, id: string): Promise<{ itemId: string; remaining: number }[]> {
      const res = await anyAuthedRequest.get(`/purchase-orders/${id}/receive`);
      const body = await res.text();
      // views/purchase/receive.php renders `max="{remaining}"` BEFORE
      // `name="qty_now[{item->id}]"` on the same <input> — order matters here.
      const rowRe = /max="(\d+)"[^>]*name="qty_now\[(\d+)\]"/g;
      const rows: { itemId: string; remaining: number }[] = [];
      let m: RegExpExecArray | null;
      while ((m = rowRe.exec(body))) rows.push({ itemId: m[2] as string, remaining: Number(m[1]) });
      return rows;
    },
  };
}

export type PurchaseOrderFactory = ReturnType<typeof createPurchaseOrderFactory>;

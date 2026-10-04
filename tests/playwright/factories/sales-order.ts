// Sales Order factory (design spec §8 item 5 / §1.5). create() redirects
// straight to the new SO's detail page with its token in Location.
//
// RBAC reminders baked into the method names/docs below so a consuming
// spec can't misuse these by accident:
//   - submit(): the ACTOR must be the creator (any role) — pass the same
//     request context that created it.
//   - approve()/reject(): sales_orders.approve — Admin only (an Admin MAY
//     approve/reject an order they created themselves — role-based SOD,
//     design spec Correction C9).
//   - issue(): sales_orders.issue — Admin or WarehouseStaff.
//   - cancel(): Admin any non-final order; a non-admin only their OWN
//     Draft/PendingApproval order.

import type { APIRequestContext } from '@playwright/test';
import { post } from '../support/request';
import { createViaForm, FactoryError, tokenFromPath } from './_common';
import { todayYmd } from '../support/dates';
import { createUid } from '../support/unique';

export interface SoLine {
  productId: number | string;
  qty: number;
  salePrice: number;
}

export interface SoOptions {
  customerId: number | string;
  warehouseId: number | string;
  orderDate?: string;
  note?: string;
  lines: SoLine[];
}

export interface CreatedSo {
  id: string;
}

export function createSalesOrderFactory(request: APIRequestContext, workerIndex: number) {
  return {
    /** `creatorRequest` is whichever role (Admin or Sales) should own this
     * order — pass salesRequest/adminRequest/sales2Request explicitly; this
     * factory never defaults to the bound `request` so SOD/isolation tests
     * can't accidentally create an order "as" the wrong actor. */
    async create(creatorRequest: APIRequestContext, opts: SoOptions): Promise<CreatedSo> {
      const form: Record<string, string | string[]> = {
        customer_id: String(opts.customerId),
        source_warehouse_id: String(opts.warehouseId),
        order_date: opts.orderDate ?? todayYmd(),
        note: opts.note ?? createUid(workerIndex).reason(),
        item_product_id: opts.lines.map((l) => String(l.productId)),
        item_qty: opts.lines.map((l) => String(l.qty)),
        item_sale_price: opts.lines.map((l) => String(l.salePrice)),
      };
      const res = await createViaForm(creatorRequest, '/sales-orders/create', '/sales-orders', form);
      return { id: tokenFromPath(res.location as string) };
    },

    async submit(creatorRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(creatorRequest, `/sales-orders/${id}/submit`, {}, { csrf: 'valid', csrfSourcePath: `/sales-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /sales-orders/${id}/submit`, res.status, res.body);
    },

    /** Admin-only. */
    async approve(adminRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(adminRequest, `/sales-orders/${id}/approve`, {}, { csrf: 'valid', csrfSourcePath: `/sales-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /sales-orders/${id}/approve`, res.status, res.body);
    },

    /** Admin-only. */
    async reject(adminRequest: APIRequestContext, id: string, reason?: string): Promise<void> {
      const res = await post(
        adminRequest,
        `/sales-orders/${id}/reject`,
        { reason: reason ?? createUid(workerIndex).reason() },
        { csrf: 'valid', csrfSourcePath: `/sales-orders/${id}` }
      );
      if (res.status !== 302) throw new FactoryError(`POST /sales-orders/${id}/reject`, res.status, res.body);
    },

    /** Admin or WarehouseStaff. */
    async issue(issuerRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(issuerRequest, `/sales-orders/${id}/issue`, {}, { csrf: 'valid', csrfSourcePath: `/sales-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /sales-orders/${id}/issue`, res.status, res.body);
    },

    async cancel(actorRequest: APIRequestContext, id: string): Promise<void> {
      const res = await post(actorRequest, `/sales-orders/${id}/cancel`, {}, { csrf: 'valid', csrfSourcePath: `/sales-orders/${id}` });
      if (res.status !== 302) throw new FactoryError(`POST /sales-orders/${id}/cancel`, res.status, res.body);
    },
  };
}

export type SalesOrderFactory = ReturnType<typeof createSalesOrderFactory>;

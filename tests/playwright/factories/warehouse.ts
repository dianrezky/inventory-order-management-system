import type { APIRequestContext } from '@playwright/test';
import { createViaForm, findTokenBySearch } from './_common';
import { createUid } from '../support/unique';

export interface WarehouseOptions {
  name?: string;
  code?: string;
  location?: string;
}

export interface CreatedWarehouse {
  id: string; // obfuscated token
  code: string;
  name: string;
}

export function createWarehouseFactory(request: APIRequestContext, workerIndex: number) {
  return {
    async create(opts: WarehouseOptions = {}): Promise<CreatedWarehouse> {
      const uid = createUid(workerIndex);
      const name = opts.name ?? uid.name('wh', 100);
      const code = opts.code ?? uid.warehouseCode();
      await createViaForm(request, '/warehouses/create', '/warehouses', {
        name,
        code,
        location: opts.location ?? 'E2E test location',
      });
      // WarehouseController::storeAction redirects to the LIST, not the new
      // record's detail page — resolve the token by searching for our
      // unique code (design spec §8 item 5 token-discovery note).
      const id = await findTokenBySearch(request, '/warehouses', { code });
      return { id, code, name };
    },
  };
}

export type WarehouseFactory = ReturnType<typeof createWarehouseFactory>;

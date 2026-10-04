// Category factory (design spec §8 item 5). CategoryController::storeAction
// answers JSON directly (categoryJsonResponse()) with the created record's
// id ALREADY encoded as a token (`$this->encodeId($category->id)`) — no
// search-based token discovery needed, unlike most other master-data
// factories.
//
// IMPORTANT: never omit `code` here. CategoryService::generateUniqueCode()
// is a sequential `CAT-<PREFIX>-01..99` scan with no locking — concurrent
// workers both creating a category from the same product name prefix can
// race onto the same candidate code and one will get a 500
// (KNOWN_DEFECT DEF-03, see KNOWN_DEFECTS.md). Always pass an explicit,
// worker-unique code (uid.categoryCode()) from here.

import type { APIRequestContext } from '@playwright/test';
import { post } from '../support/request';
import { FactoryError } from './_common';
import { createUid } from '../support/unique';

export interface CategoryOptions {
  name?: string;
  code?: string;
  description?: string;
  active?: boolean;
}

export interface CreatedCategory {
  id: string; // obfuscated token
  code: string;
  name: string;
  description: string | null;
  isActive: boolean;
}

export function createCategoryFactory(request: APIRequestContext, workerIndex: number) {
  return {
    async create(opts: CategoryOptions = {}): Promise<CreatedCategory> {
      const uid = createUid(workerIndex);
      const name = opts.name ?? uid.name('cat', 80);
      const code = opts.code ?? uid.categoryCode();
      const res = await post(
        request,
        '/categories',
        {
          name,
          code,
          description: opts.description ?? '',
          status: opts.active === false ? 'inactive' : 'active',
        },
        { csrf: 'valid', csrfSourcePath: '/categories' }
      );
      if (res.status !== 200 || res.json === null) {
        throw new FactoryError('POST /categories', res.status, res.body);
      }
      const payload = res.json as { ok: boolean; error?: string; category?: Record<string, unknown> };
      if (!payload.ok || !payload.category) {
        throw new FactoryError('POST /categories', res.status, JSON.stringify(payload));
      }
      const c = payload.category;
      return {
        id: String(c.id),
        code: String(c.code),
        name: String(c.name),
        description: (c.description as string | null) ?? null,
        isActive: Boolean(c.isActive),
      };
    },

    async setActive(id: string, active: boolean): Promise<void> {
      const action = active ? 'activate' : 'deactivate';
      const res = await post(request, `/categories/${id}/${action}`, {}, { csrf: 'valid', csrfSourcePath: '/categories' });
      if (res.status !== 302) throw new FactoryError(`POST /categories/${id}/${action}`, res.status, res.body);
    },
  };
}

export type CategoryFactory = ReturnType<typeof createCategoryFactory>;

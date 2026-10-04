import type { APIRequestContext } from '@playwright/test';
import { createViaForm, findTokenBySearch } from './_common';
import { createUid } from '../support/unique';

export type AppRole = 'Admin' | 'Sales' | 'WarehouseStaff';

export interface UserOptions {
  name?: string;
  email?: string;
  role?: AppRole;
  password?: string;
  active?: boolean;
}

export interface CreatedUser {
  id: string;
  name: string;
  email: string;
  role: AppRole;
  password: string;
}

export function createUserFactory(request: APIRequestContext, workerIndex: number) {
  return {
    async create(opts: UserOptions = {}): Promise<CreatedUser> {
      const uid = createUid(workerIndex);
      const name = opts.name ?? uid.name('user', 100);
      const email = opts.email ?? uid.email('user');
      const password = opts.password ?? 'E2ePassw0rd!';
      const role = opts.role ?? 'Sales';
      await createViaForm(request, '/users/create', '/users', { name, email, role, password });
      const id = await findTokenBySearch(request, '/users', { email });
      if (opts.active === false) {
        await createViaForm(request, '/users/create', `/users/${id}/deactivate`, {});
      }
      return { id, name, email, role, password };
    },
  };
}

export type UserFactory = ReturnType<typeof createUserFactory>;

// Customers and Suppliers share identical fields/validation (party.ts
// avoids writing the same factory twice — see design spec §1.5: both
// services require only `name`, with an optional FILTER_VALIDATE_EMAIL
// `email`).

import type { APIRequestContext } from '@playwright/test';
import { createViaForm, findTokenBySearch } from './_common';
import { createUid } from '../support/unique';

export interface PartyOptions {
  name?: string;
  email?: string;
  contactPerson?: string;
  phone?: string;
  address?: string;
}

export interface CreatedParty {
  id: string;
  name: string;
  email: string | null;
}

function makePartyFactory(request: APIRequestContext, workerIndex: number, kind: 'customer' | 'supplier') {
  const base = kind === 'customer' ? '/customers' : '/suppliers';
  return {
    async create(opts: PartyOptions = {}): Promise<CreatedParty> {
      const uid = createUid(workerIndex);
      const name = opts.name ?? uid.name(kind, 150);
      const email = opts.email ?? uid.email(kind);
      await createViaForm(request, `${base}/create`, base, {
        name,
        email,
        contact_person: opts.contactPerson ?? 'E2E Contact',
        phone: opts.phone ?? uid.phone(),
        address: opts.address ?? 'E2E test address',
      });
      const id = await findTokenBySearch(request, base, { name });
      return { id, name, email };
    },
    async setActive(id: string, active: boolean): Promise<void> {
      const action = active ? 'activate' : 'deactivate';
      // (De)activate also redirects with 302 on success, so createViaForm's
      // "expect 302 or throw" behaviour applies unchanged here.
      await createViaForm(request, `${base}/create`, `${base}/${id}/${action}`, {});
    },
  };
}

export const createCustomerFactory = (request: APIRequestContext, workerIndex: number) => makePartyFactory(request, workerIndex, 'customer');
export const createSupplierFactory = (request: APIRequestContext, workerIndex: number) => makePartyFactory(request, workerIndex, 'supplier');

export type CustomerFactory = ReturnType<typeof createCustomerFactory>;
export type SupplierFactory = ReturnType<typeof createSupplierFactory>;

// THE central fixture file (FOUNDATION_CONTRACT.md §3). This is the ONLY
// place `test`/`expect` are imported directly from @playwright/test — every
// spec, page object and component imports them from here instead (enforced
// by eslint.config.js's no-restricted-imports rule). This is also where
// role access is resolved per-worker (design spec §3.12/§3.13 "per-worker
// sessions", AD-5) — there are no per-role Playwright projects.

import { test as base, expect as baseExpect, request as requestModule } from '@playwright/test';
import type { APIRequestContext, Page } from '@playwright/test';
import path from 'node:path';
import { CREDENTIALS, storageStatePath, type Role } from './roles';
import { loadEnv, type E2eEnv } from './env';
import { createUid, type Uid } from './unique';
import { todayYmd } from './dates';
import { DiagnosticsRecorder } from './diagnostics';
import { csrf } from './csrf';
import { ids } from './ids';
import { post, get } from './request';
import { downloadCsv } from './csv';
import { createFactories, type Factories } from '../factories';
import * as dbModule from './db';
import { ModeBUnavailable } from './env';

const AUTH_DIR = path.resolve(__dirname, '..', '.auth');

export interface LoginSession {
  page: Page;
  request: APIRequestContext;
  /** Logs this (and only this) session out. Never call on a shared
   * role/worker fixture session — see "never log out shared sessions"
   * (FOUNDATION_CONTRACT.md §3). */
  logout(): Promise<void>;
  close(): Promise<void>;
}

type Fixtures = {
  env: E2eEnv;
  uid: Uid;
  diagnostics: DiagnosticsRecorder;
  csrf: typeof csrf;
  ids: typeof ids;
  http: { post: typeof post; get: typeof get; downloadCsv: typeof downloadCsv };
  db: typeof dbModule;
  factories: Factories;

  adminPage: Page;
  salesPage: Page;
  warehousePage: Page;
  sales2Page: Page;

  adminRequest: APIRequestContext;
  salesRequest: APIRequestContext;
  warehouseRequest: APIRequestContext;
  sales2Request: APIRequestContext;

  guestPage: Page;
  guestRequest: APIRequestContext;

  /** Creates a fresh, independent session for a role with its own browser
   * context/cookies — for tests that MUST own their session lifecycle
   * (logout, session regeneration, deactivation-mid-session, stale CSRF
   * across login, etc.) rather than reusing the shared per-worker session,
   * which other tests on the same worker keep using concurrently. */
  loginAs: (role: Role | { email: string; password: string }) => Promise<LoginSession>;
};

function rolePageFixture(role: Role) {
  return async ({ browser }: { browser: import('@playwright/test').Browser }, use: (p: Page) => Promise<void>, testInfo: { parallelIndex: number }) => {
    const statePath = storageStatePath(AUTH_DIR, role, testInfo.parallelIndex);
    const context = await browser.newContext({ storageState: statePath });
    const page = await context.newPage();
    await use(page);
    await context.close();
  };
}

function roleRequestFixture(role: Role) {
  return async (_: unknown, use: (r: APIRequestContext) => Promise<void>, testInfo: { parallelIndex: number }) => {
    const statePath = storageStatePath(AUTH_DIR, role, testInfo.parallelIndex);
    const ctx = await requestModule.newContext({ storageState: statePath, baseURL: loadEnv().baseURL });
    await use(ctx);
    await ctx.dispose();
  };
}

export const test = base.extend<Fixtures>({
  env: async ({}, use) => {
    await use(loadEnv());
  },

  uid: async ({}, use, testInfo) => {
    await use(createUid(testInfo.parallelIndex));
  },

  diagnostics: [
    async ({ page }, use, testInfo) => {
      const recorder = new DiagnosticsRecorder(page);
      await use(recorder);
      recorder.assertClean();
      if (testInfo.status !== testInfo.expectedStatus || recorder.hasAnything()) {
        await testInfo.attach('diagnostics.json', { body: JSON.stringify(recorder.summary(), null, 2), contentType: 'application/json' });
      }
    },
    { auto: true },
  ],

  csrf: async ({}, use) => use(csrf),
  ids: async ({}, use) => use(ids),
  http: async ({}, use) => use({ post, get, downloadCsv }),
  db: async ({}, use) => use(dbModule),

  factories: async ({ adminRequest }, use, testInfo) => {
    await use(createFactories(adminRequest, testInfo.parallelIndex));
  },

  adminPage: rolePageFixture('admin'),
  salesPage: rolePageFixture('sales'),
  warehousePage: rolePageFixture('warehouse'),
  sales2Page: rolePageFixture('sales2'),

  adminRequest: roleRequestFixture('admin'),
  salesRequest: roleRequestFixture('sales'),
  warehouseRequest: roleRequestFixture('warehouse'),
  sales2Request: roleRequestFixture('sales2'),

  guestPage: async ({ browser }, use) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await use(page);
    await context.close();
  },
  guestRequest: async ({}, use) => {
    const ctx = await requestModule.newContext({ baseURL: loadEnv().baseURL });
    await use(ctx);
    await ctx.dispose();
  },

  loginAs: async ({ browser }, use) => {
    const sessions: { context: import('@playwright/test').BrowserContext }[] = [];
    await use(async (roleOrCreds) => {
      const creds = typeof roleOrCreds === 'string' ? CREDENTIALS[roleOrCreds] : roleOrCreds;
      const context = await browser.newContext();
      sessions.push({ context });
      const page = await context.newPage();
      await page.goto('/login');
      await page.locator('#email').fill(creds.email);
      await page.locator('#password').fill(creds.password);
      await Promise.all([page.waitForURL(/\/(dashboard|sales-dashboard)/), page.locator('button[type="submit"]').click()]);
      const requestCtx = context.request;
      return {
        page,
        request: requestCtx,
        async logout() {
          const token = await csrf.fromPage(page);
          await requestCtx.post('/logout', { form: { _csrf_token: token } });
        },
        async close() {
          await context.close();
        },
      };
    });
    for (const s of sessions) await s.context.close().catch(() => {});
  },
});

export const expect = baseExpect;
export { todayYmd };
export { ModeBUnavailable };

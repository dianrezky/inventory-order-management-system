// globalSetup — ENVIRONMENT ONLY (design spec §3.11 / Correction C5). It
// never logs anyone in; that is setup/auth.setup.ts's job, kept as a
// separate project with a separate responsibility on purpose.

import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { request as newRequestContext } from '@playwright/test';
import { loadEnv } from '../support/env';
import { assertComposePrerequisites, resetIsolated, up, PROJECT_NAME } from '../support/compose';

const RUN_DIR = path.resolve(__dirname, '..', '.run');

async function waitForOk(url: string, timeoutMs: number): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  let lastErr: unknown = null;
  while (Date.now() < deadline) {
    try {
      const ctx = await newRequestContext.newContext();
      const res = await ctx.get(url, { timeout: 5000, failOnStatusCode: false });
      await ctx.dispose();
      if (res.status() === 200) return;
      lastErr = new Error(`${url} -> HTTP ${res.status()}`);
    } catch (err) {
      lastErr = err;
    }
    await new Promise((r) => setTimeout(r, 1000));
  }
  throw new Error(`Timed out waiting for ${url} to return 200. Last error: ${String(lastErr)}`);
}

async function probeSeedViaApp(baseURL: string): Promise<void> {
  // We don't have a DB connection here (globalSetup is environment-only —
  // the seed-content check belongs to setup/auth.setup.ts, which logs in as
  // Admin and can see /products). Here we only prove the app itself is
  // reachable and the login page is real (renders a CSRF form), plus the
  // JSON API's 401 contract (design spec §3.11 step 4).
  const ctx = await newRequestContext.newContext({ baseURL });
  try {
    const loginRes = await ctx.get('/login');
    if (loginRes.status() !== 200) throw new Error(`GET /login returned ${loginRes.status()}, expected 200.`);
    const loginBody = await loginRes.text();
    if (!loginBody.includes('_csrf_token')) {
      throw new Error('GET /login did not render a _csrf_token field — app bootstrap may be broken.');
    }
    const apiRes = await ctx.get('/api/products/ELEC-001/availability', { failOnStatusCode: false });
    if (apiRes.status() !== 401) {
      throw new Error(
        `Unauthenticated GET /api/products/ELEC-001/availability returned ${apiRes.status()}, expected 401 ` +
          '(ProductApiController::ERROR_UNAUTHORIZED contract).'
      );
    }
  } finally {
    await ctx.dispose();
  }
}

export default async function globalSetup(): Promise<void> {
  const env = loadEnv();
  mkdirSync(RUN_DIR, { recursive: true });
  mkdirSync(path.resolve(__dirname, '..', '.auth'), { recursive: true });

  if (env.mode === 'isolated') {
    await assertComposePrerequisites();

    if (env.reset === 'always') {
      console.log(`[globalSetup] E2E_RESET=always — tearing down and recreating project "${PROJECT_NAME}"...`);
      await resetIsolated();
    } else if (env.reset === 'fast') {
      console.log('[globalSetup] E2E_RESET=fast — reusing volumes, letting `up` reconcile running services.');
    } else {
      console.log('[globalSetup] E2E_RESET=never — reusing whatever is already running, if anything.');
    }

    console.log(`[globalSetup] docker compose up (project "${PROJECT_NAME}")...`);
    await up();
  } else {
    console.log(
      `[globalSetup] E2E_MODE=existing — connecting to ${env.baseURL}. No reset, no Docker, seed state is ` +
        'NOT guaranteed. Tests tagged @needs-isolated / @seed-dependent should be excluded from this run.'
    );
  }

  await waitForOk(`${env.baseURL}/login`, env.mode === 'isolated' ? 180_000 : 30_000);
  await probeSeedViaApp(env.baseURL);

  writeFileSync(
    path.join(RUN_DIR, 'env.json'),
    JSON.stringify({ mode: env.mode, baseURL: env.baseURL, reset: env.reset, startedAt: new Date().toISOString() }, null, 2)
  );

  console.log(`[globalSetup] environment ready: mode=${env.mode} baseURL=${env.baseURL}`);
}

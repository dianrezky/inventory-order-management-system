// AUTHENTICATION ONLY (design spec §3.12 / Correction C5). Logs in every
// role at every worker slot and saves per-worker storageState files, then
// runs the seed-readiness + Redis-session proofs. Environment bootstrap
// (starting the stack, waiting for health) already happened in
// global-setup.ts — this file's only job is producing authenticated state.

import { test as base, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { ROLES, CREDENTIALS, storageStatePath } from '../support/roles';
import { loadEnv, effectiveWorkers } from '../support/env';

const AUTH_DIR = path.resolve(__dirname, '..', '.auth');

base('provision per-worker authenticated sessions for every role', async ({ browser }, testInfo) => {
  mkdirSync(AUTH_DIR, { recursive: true });
  const env = loadEnv();
  const workerCount = effectiveWorkers(env);

  let adminW0Path = '';

  for (const role of ROLES) {
    for (let w = 0; w < workerCount; w++) {
      const creds = CREDENTIALS[role];
      const context = await browser.newContext();
      const page = await context.newPage();

      await page.goto('/login');
      await page.locator('#email').fill(creds.email);
      await page.locator('#password').fill(creds.password);
      await Promise.all([page.waitForURL(/\/(dashboard|sales-dashboard)/), page.locator('button[type="submit"]').click()]);

      // Every role lands on /dashboard per AuthController::ROUTE_DASHBOARD
      // (it is always '/dashboard', never '/sales-dashboard' — that is a
      // separately-permissioned route a Sales user navigates to afterwards).
      expect(page.url()).toContain('/dashboard');

      const dest = storageStatePath(AUTH_DIR, role, w);
      await context.storageState({ path: dest });
      await context.close();
      testInfo.annotations.push({ type: 'provisioned', description: `${role} w${w} -> ${dest}` });

      if (role === 'admin' && w === 0) adminW0Path = dest;
    }
  }

  // --- Seed readiness proof. Confirms the catalog actually has the seed
  // SKU the suite's read-only/@seed-dependent specs assume (design spec
  // §3.12). In Mode B this only proves the shared app has *a* usable
  // catalog right now — not that it is untouched (documented limitation).
  const adminContext = await browser.newContext({ storageState: adminW0Path });
  const adminPage = await adminContext.newPage();
  await adminPage.goto('/products');
  await expect(adminPage.getByText('ELEC-001', { exact: false })).toBeVisible({ timeout: 15_000 });
  await adminContext.close();

  // --- Redis-session proof (Mode A only). SessionManager silently falls
  // back to file-based sessions if Redis is unreachable (design spec §1.6
  // fact 3) — that failure mode produces a working-looking app with no
  // visible symptom, so we assert the session really is Redis-backed by
  // checking a PHPREDIS_SESSION:* key exists for the session we just made.
  if (env.mode === 'isolated') {
    const { execIn } = await import('../support/compose');
    const { stdout } = await execIn('redis', ['redis-cli', '--scan', '--pattern', 'PHPREDIS_SESSION:*']);
    const keys = stdout.trim().split('\n').filter(Boolean);
    expect(
      keys.length,
      'Expected at least one PHPREDIS_SESSION:* key in Redis after logging in — if this is 0, ' +
        'SessionManager silently fell back to file-based sessions (Redis unreachable), which the design ' +
        'spec requires this proof to catch.'
    ).toBeGreaterThan(0);
  }
});

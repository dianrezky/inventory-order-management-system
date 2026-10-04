// globalTeardown — pairs with global-setup.ts's environment-only
// responsibility: the invariant gate (design spec §3.9), an app-log scan
// for PHP leaks (replaces legacy crawl.spec.js's docker-logs check), and
// stopping (not deleting, unless E2E_KEEP=0 is explicit) the stack.

import { writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { loadEnv } from '../support/env';
import { logs, stop, resetIsolated } from '../support/compose';
import { assertInvariants } from '../support/db';

const PHP_LEAK_RE = /(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\s*:/;
const REPORT_DIR = path.resolve(__dirname, '..', 'playwright-report');

export default async function globalTeardown(): Promise<void> {
  const env = loadEnv();
  if (env.mode !== 'isolated') {
    console.log('[globalTeardown] E2E_MODE=existing — nothing to tear down.');
    return;
  }

  const failures: string[] = [];

  try {
    await assertInvariants();
    console.log('[globalTeardown] invariant gate passed: product_stocks == SUM(stock_ledger) for every pair, no negative stock, no over-receipt.');
  } catch (err) {
    failures.push(`Invariant gate failed: ${(err as Error).message}`);
  }

  try {
    const appLog = await logs('app');
    mkdirSync(REPORT_DIR, { recursive: true });
    writeFileSync(path.join(REPORT_DIR, 'app.log'), appLog);
    const leakLines = appLog.split('\n').filter((l) => PHP_LEAK_RE.test(l));
    if (leakLines.length > 0) {
      failures.push(`PHP leak(s) found in app container logs:\n${leakLines.slice(0, 20).join('\n')}`);
    }
  } catch (err) {
    failures.push(`Could not collect app logs: ${(err as Error).message}`);
  }

  if (!env.keepStack) {
    try {
      await stop();
    } catch (err) {
      console.warn(`[globalTeardown] docker compose stop failed (non-fatal): ${(err as Error).message}`);
    }
  } else {
    console.log('[globalTeardown] E2E_KEEP=1 — leaving the ioms-e2e stack running.');
  }

  if (failures.length > 0) {
    throw new Error(`globalTeardown found ${failures.length} problem(s):\n\n${failures.join('\n\n')}`);
  }
}

/** Exposed for a future "reset between phases" CLI helper; not called from
 * teardown itself (teardown stops, it does not destroy, unless asked). */
export async function hardReset(): Promise<void> {
  await resetIsolated();
}

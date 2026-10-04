#!/usr/bin/env -S npx tsx
// `npm run e2e:env:down` — tears down the ioms-e2e stack via the guarded
// resetIsolated() wrapper (volume-prefix-checked `down -v`, project
// "ioms-e2e" only — see support/compose.ts). Refuses in Mode B by design:
// resetIsolated() has no mode awareness itself, but this CLI is the only
// caller outside globalSetup/globalTeardown, and a developer running it
// against an "existing" environment they didn't start themselves would be
// a mistake this script should not make easy — so it requires
// E2E_MODE=isolated explicitly, same as the rest of the suite.
import { loadEnv } from './env';
import { resetIsolated } from './compose';

async function main(): Promise<void> {
  const env = loadEnv();
  if (env.mode !== 'isolated') {
    throw new Error('e2e:env:down only tears down the isolated ioms-e2e stack. Set E2E_MODE=isolated to run it.');
  }
  await resetIsolated();
  console.log('[e2e:env:down] ioms-e2e stack and its volumes have been removed.');
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});

#!/usr/bin/env -S npx tsx
// `npm run e2e:env:up` — convenience CLI wrapper around support/compose.ts's
// `up()`, for a developer who wants the ioms-e2e stack running WITHOUT
// running the whole suite (e.g. to poke at it manually, or to run a single
// spec repeatedly with E2E_RESET=never). Goes through the same guarded
// wrapper as everything else — see support/compose.ts.
import { assertComposePrerequisites, up } from './compose';

async function main(): Promise<void> {
  await assertComposePrerequisites();
  await up({ profiles: process.argv.includes('--session-expiry') ? ['session-expiry'] : [] });
  console.log('[e2e:env:up] ioms-e2e stack is up. App: http://127.0.0.1:18090');
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});

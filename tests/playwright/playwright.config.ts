// Playwright execution model (design spec §3.10 / FOUNDATION_CONTRACT.md).
// Three projects — setup (auth only) -> parallel (everything independent)
// -> exclusive (global-state specs, single worker, runs last) — so role
// access is NEVER achieved by multiplying every spec across N role
// projects (design spec Correction C4).

import { defineConfig, devices } from '@playwright/test';
import { loadEnv, effectiveWorkers } from './support/env';

// playwright.config.ts is loaded before test files, including by `--list`
// and by tooling that just wants the config shape — so env validation
// failures here must still produce a readable message, not a stack trace
// from deep inside Playwright's loader.
let env: ReturnType<typeof loadEnv> | null = null;
let envError: Error | null = null;
try {
  env = loadEnv();
} catch (err) {
  envError = err as Error;
}

if (envError) {
  console.error(`\n[ioms-e2e] ${envError.message}\n`);
}

const baseURL = env?.baseURL ?? 'http://127.0.0.1:18090';
// Same function setup/auth.setup.ts uses to decide how many per-role
// storageState files to provision — this is the one place "how many
// workers" is decided, so the two can never disagree (design spec §3.12).
const workers = env ? effectiveWorkers(env) : 4;

export default defineConfig({
  testDir: '.',
  outputDir: 'test-results',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }], ['junit', { outputFile: 'test-results/junit.xml' }]] : 'list',
  globalSetup: './global/global-setup.ts',
  globalTeardown: './global/global-teardown.ts',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL,
    timezoneId: 'UTC',
    locale: 'en-US',
    viewport: { width: 1280, height: 800 },
    // Screenshot + trace are captured for EVERY test, pass or fail — not
    // just failures — per explicit user request to always have visual
    // evidence of what the suite actually drove in the browser. This is
    // noisier/slower than the design spec's original §3.10 default
    // ('only-on-failure'); revisit once Wave 2 has produced enough runs
    // that "always" is no longer needed for manual verification.
    trace: 'on',
    screenshot: 'on',
    video: 'on',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    ...(env?.chromiumPath ? { launchOptions: { executablePath: env.chromiumPath } } : {}),
  },
  projects: [
    {
      name: 'setup',
      testMatch: /setup\/auth\.setup\.ts/,
      // Always a single worker: auth.setup.ts provisions ALL per-worker
      // storageState files itself, sequentially, inside one test — it does
      // not rely on testInfo.parallelIndex, so running it on >1 worker
      // would just race multiple processes writing the same files.
      workers: 1,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'parallel',
      testMatch: /specs\/.*\.spec\.ts/,
      testIgnore: [/specs\/exclusive\//, /specs\/_smoke\/guards\.spec\.ts/],
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'exclusive',
      testMatch: /specs\/exclusive\/.*\.spec\.ts/,
      dependencies: ['parallel'],
      fullyParallel: false,
      workers: 1,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'guards', // env/compose unit-style checks, no browser, no auth dependency
      testMatch: /specs\/_smoke\/guards\.spec\.ts/,
      use: {},
    },
  ],
});

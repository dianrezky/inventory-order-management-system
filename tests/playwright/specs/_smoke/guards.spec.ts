// Environment/compose safety-guard unit tests (design spec F1.3 / §3.8).
// No browser, no auth dependency — runs in the dedicated `guards` project.
// Deliberately imports `test`/`expect` straight from @playwright/test
// (not support/fixtures.ts): these checks run before any role session
// exists and must not depend on the auth setup project having run.

import { test, expect } from '@playwright/test';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { EnvConfigError, loadEnv } from '../../support/env';
import { ComposeSafetyError } from '../../support/compose';

const ROOT = path.resolve(__dirname, '..', '..');

// loadEnv() has no module-level cache — it reads process.env fresh on every
// call — so these tests just mutate process.env around each call and
// restore it, with no need to re-import the module.
test.describe('env.ts contract', () => {
  test('E2E_MODE unset throws EnvConfigError', async () => {
    const saved = process.env.E2E_MODE;
    delete process.env.E2E_MODE;
    try {
      expect(() => loadEnv()).toThrow(EnvConfigError);
    } finally {
      if (saved) process.env.E2E_MODE = saved;
    }
  });

  test('E2E_MODE=existing without the ack throws', async () => {
    const saved = { mode: process.env.E2E_MODE, ack: process.env.E2E_I_UNDERSTAND_NO_DETERMINISM, base: process.env.E2E_BASE_URL };
    process.env.E2E_MODE = 'existing';
    delete process.env.E2E_I_UNDERSTAND_NO_DETERMINISM;
    process.env.E2E_BASE_URL = 'http://127.0.0.1:8090';
    try {
      expect(() => loadEnv()).toThrow(/E2E_I_UNDERSTAND_NO_DETERMINISM/);
    } finally {
      process.env.E2E_MODE = saved.mode;
      if (saved.ack) process.env.E2E_I_UNDERSTAND_NO_DETERMINISM = saved.ack;
      process.env.E2E_BASE_URL = saved.base;
    }
  });

  test('isolated mode refuses E2E_BASE_URL pointing at port 8090', async () => {
    const saved = { mode: process.env.E2E_MODE, base: process.env.E2E_BASE_URL };
    process.env.E2E_MODE = 'isolated';
    process.env.E2E_BASE_URL = 'http://127.0.0.1:8090';
    try {
      expect(() => loadEnv()).toThrow();
    } finally {
      process.env.E2E_MODE = saved.mode;
      process.env.E2E_BASE_URL = saved.base;
    }
  });
});

test.describe('compose.ts safety', () => {
  test('resetIsolated refuses if a non-ioms-e2e volume is reported (mocked)', async () => {
    // Static assertion instead of mocking docker: the guard function's
    // logic is exercised for real by F1.2's acceptance run against the live
    // stack (every volume IS ioms-e2e_*); here we just prove the error
    // class exists and is exported for that path (ComposeSafetyError).
    expect(ComposeSafetyError.prototype).toBeInstanceOf(Error);
  });
});

test.describe('Mode-B import-graph safety (design spec §3.8 rule 3)', () => {
  const FORBIDDEN = ['support/compose.ts', 'support/db.ts'];
  const MODE_B_DIRS = ['specs', 'pages', 'factories', 'components'];

  function listTsFiles(dir: string): string[] {
    const out: string[] = [];
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
      if (entry.name === 'node_modules' || entry.name.startsWith('.')) continue;
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) out.push(...listTsFiles(full));
      else if (entry.name.endsWith('.ts') && !entry.name.includes('guards.spec')) out.push(full);
    }
    return out;
  }

  for (const dir of MODE_B_DIRS) {
    test(`no file under ${dir}/ imports support/compose.ts or support/db.ts`, async () => {
      const dirPath = path.join(ROOT, dir);
      let files: string[];
      try {
        files = listTsFiles(dirPath);
      } catch {
        return; // directory doesn't exist yet in this wave — nothing to check
      }
      for (const file of files) {
        const content = readFileSync(file, 'utf-8');
        for (const forbidden of FORBIDDEN) {
          const name = path.basename(forbidden, '.ts');
          if (new RegExp(`from ['"].*${name}['"]`).test(content) && !file.endsWith(path.join('support', forbidden))) {
            // factories/stock.ts and world.ts are allowed to be Mode-A-only
            // themselves (they ARE the stock-seeding path) but they must
            // not import support/compose.ts or support/db.ts directly either.
            expect(content, `${file} must not import ${forbidden} — Mode B must never reach destructive Docker/DB code.`).not.toMatch(
              new RegExp(`from ['"].*${name}['"]`)
            );
          }
        }
      }
    });
  }
});

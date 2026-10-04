// Foundation lint rules (FOUNDATION_CONTRACT.md §8). Two rules encode
// architectural decisions as enforceable lint, not just convention:
//   1. no-restricted-properties on page.waitForTimeout — determinism (§3.27
//      of the design spec). An `// ALLOW-WAIT:` comment on the same line is
//      the only sanctioned escape, and even that needs Agent 0 sign-off.
//   2. no-restricted-imports of "@playwright/test" outside support/fixtures.ts
//      and the Mode-B import-graph ban on support/compose.ts and support/db.ts
//      (AD-5 / AD-7: Mode B must not be able to reach destructive Docker code).
import tseslint from 'typescript-eslint';

const FIXTURES_FILE = 'support/fixtures.ts';
const MODE_B_FORBIDDEN = ['support/compose.ts', 'support/db.ts'];

export default tseslint.config(
  {
    ignores: ['node_modules/**', 'playwright-report/**', 'test-results/**', '.auth/**', '.run/**', '.cache/**'],
  },
  ...tseslint.configs.recommended,
  {
    files: ['**/*.ts'],
    rules: {
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'warn',
      'no-restricted-properties': [
        'error',
        {
          object: 'page',
          property: 'waitForTimeout',
          message:
            'Arbitrary sleeps are forbidden (determinism rule, design spec §3.27). Use expect.poll/waitForResponse/waitForURL, or add `// ALLOW-WAIT: <reason>` with Agent 0 sign-off.',
        },
      ],
    },
  },
  {
    // Only support/fixtures.ts may import the VALUE exports test/expect
    // directly — every spec and page object must go through it
    // (FOUNDATION_CONTRACT §3). Type-only imports (APIRequestContext, Page,
    // Locator, ...) are fine everywhere — the restriction is about the
    // runtime `test`/`expect` objects, not the ambient types.
    files: ['**/*.ts'],
    ignores: [FIXTURES_FILE, 'playwright.config.ts', 'global/**', 'setup/**', 'specs/_smoke/guards.spec.ts'],
    rules: {
      '@typescript-eslint/no-restricted-imports': [
        'error',
        {
          paths: [
            {
              name: '@playwright/test',
              message: 'Import `test`/`expect` from support/fixtures.ts, never directly from @playwright/test.',
              allowTypeImports: true,
            },
          ],
        },
      ],
    },
  },
  {
    // Mode-B safety: nothing reachable in "existing" mode may import the
    // Docker/compose wrapper or the DB oracle (AD-7 / design spec §3.8 rule 3).
    files: ['specs/**/*.ts', 'pages/**/*.ts', 'factories/**/*.ts', 'components/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          patterns: MODE_B_FORBIDDEN.map((p) => ({
            group: [`**/${p}`, `./${p}`, `../${p}`, `../../${p}`],
            message: `${p} is Mode-A-only (destructive Docker / DB oracle). Specs must reach it only through the db fixture, which throws ModeBUnavailable in Mode B.`,
          })),
        },
      ],
    },
  }
);

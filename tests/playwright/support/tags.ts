// Tag taxonomy (design spec §3.26 / FOUNDATION_CONTRACT.md §7). All agents
// use ONLY these tags — Agent 8's lint audit fails any `test()` call that
// carries a tag not in this list, or carries no tag at all.

export const TAGS = {
  smoke: '@smoke', // small, fast, safe-in-Mode-B subset
  crud: '@crud',
  validation: '@validation',
  rbac: '@rbac', // role/permission matrix, UI + direct request
  workflow: '@workflow', // multi-step state machines
  security: '@security', // CSRF, ID tamper, session, injection, ownership
  a11y: '@a11y',
  concurrency: '@concurrency',
  export: '@export',
  upload: '@upload',
  regression: '@regression', // maps to a legacy test or a fixed bug
  // modifiers
  needsIsolated: '@needs-isolated', // requires the ioms-e2e stack / DB oracle / exclusive global state
  seedDependent: '@seed-dependent', // asserts on seed data, read-only
  quirk: '@quirk', // asserts CURRENT behaviour of a documented KNOWN_DEFECT/SPEC_CODE_MISMATCH
  slow: '@slow',
} as const;

export type Tag = (typeof TAGS)[keyof typeof TAGS];

export const ALL_TAGS: readonly Tag[] = Object.values(TAGS);

/** Mode-B compatible tag expression, used by `npm run e2e:existing` and by
 * the audit lint: a test is Mode-B-safe only if it carries neither modifier. */
export const MODE_B_EXCLUDE_GREP = `${TAGS.needsIsolated}|${TAGS.seedDependent}`;

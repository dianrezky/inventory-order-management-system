// Environment-variable contract (FOUNDATION_CONTRACT.md §1). The single
// place every other file reads E2E_* from — never `process.env.E2E_*`
// directly outside this file, so the contract has one source of truth.
//
// `E2E_MODE` has NO default on purpose (design spec AD-7 / §3.8: "Do not
// silently change environments. Execution mode must be explicit."). Every
// other default here is safe for local iteration.

export type E2eMode = 'isolated' | 'existing';
export type ResetMode = 'always' | 'fast' | 'never';

export interface E2eEnv {
  mode: E2eMode;
  baseURL: string;
  shortSessionURL: string;
  reset: ResetMode;
  keepStack: boolean;
  workers: number | undefined;
  chromiumPath: string | undefined;
  debugArtifacts: boolean;
  existingAckGiven: boolean;
}

const ISOLATED_BASE_URL = 'http://127.0.0.1:18090';
const ISOLATED_SHORT_SESSION_URL = 'http://127.0.0.1:18091';

export class EnvConfigError extends Error {}

function readBool(name: string, fallback: boolean): boolean {
  const raw = process.env[name];
  if (raw === undefined || raw === '') return fallback;
  return raw === '1' || raw.toLowerCase() === 'true';
}

/**
 * Parses and validates the E2E_* contract. Throws EnvConfigError with an
 * actionable message rather than letting a misconfigured run limp along
 * with an undefined baseURL or a silently-wrong mode.
 */
export function loadEnv(): E2eEnv {
  const rawMode = process.env.E2E_MODE;
  if (rawMode !== 'isolated' && rawMode !== 'existing') {
    throw new EnvConfigError(
      `E2E_MODE must be set to "isolated" or "existing" (got ${JSON.stringify(rawMode ?? null)}). ` +
        'There is no default — the design spec requires the execution mode to always be explicit. ' +
        'Use `npm run e2e` (isolated) or see package.json for the existing-environment script.'
    );
  }
  const mode = rawMode;

  const existingAckGiven = readBool('E2E_I_UNDERSTAND_NO_DETERMINISM', false);
  if (mode === 'existing' && !existingAckGiven) {
    throw new EnvConfigError(
      'E2E_MODE=existing requires E2E_I_UNDERSTAND_NO_DETERMINISM=1. Mode B connects to an ' +
        'already-running environment, never resets anything, and does not guarantee seed state — ' +
        'you must acknowledge that explicitly before any test runs against it.'
    );
  }

  let baseURL: string;
  if (mode === 'isolated') {
    if (process.env.E2E_BASE_URL && process.env.E2E_BASE_URL !== ISOLATED_BASE_URL) {
      throw new EnvConfigError(
        `E2E_MODE=isolated always targets ${ISOLATED_BASE_URL} (the ioms-e2e stack). ` +
          'E2E_BASE_URL is only honoured in E2E_MODE=existing.'
      );
    }
    baseURL = ISOLATED_BASE_URL;
  } else {
    const raw = process.env.E2E_BASE_URL;
    if (!raw) {
      throw new EnvConfigError('E2E_MODE=existing requires E2E_BASE_URL (e.g. http://127.0.0.1:8090).');
    }
    baseURL = raw;
  }

  // Safety net (design spec §3.8 rule 4): isolated mode must never be
  // accidentally pointed at the developer's own app port.
  if (mode === 'isolated' && new URL(baseURL).port === '8090') {
    throw new EnvConfigError(
      'Refusing to run E2E_MODE=isolated against port 8090 — that is the developer stack\'s port, ' +
        'not the ioms-e2e stack\'s (18090). This guard exists so a misconfiguration can never let an ' +
        'isolated-mode destructive reset reach developer infrastructure.'
    );
  }

  // Default depends on mode: isolated defaults to the safe-but-thorough
  // "always", existing defaults to (and can only ever be) "never" — a
  // caller in existing mode is never required to pass E2E_RESET=never
  // explicitly just to avoid tripping the mismatch check below.
  const reset = (process.env.E2E_RESET ?? (mode === 'existing' ? 'never' : 'always')) as ResetMode;
  if (!['always', 'fast', 'never'].includes(reset)) {
    throw new EnvConfigError(`E2E_RESET must be "always", "fast" or "never" (got ${reset}).`);
  }
  if (mode === 'existing' && reset !== 'never') {
    throw new EnvConfigError(
      'E2E_MODE=existing never resets anything. Do not set E2E_RESET in existing mode (or set it to "never").'
    );
  }

  const workersRaw = process.env.E2E_WORKERS;
  const workers = workersRaw ? Number.parseInt(workersRaw, 10) : undefined;
  if (workersRaw && (!Number.isFinite(workers) || (workers as number) < 1)) {
    throw new EnvConfigError(`E2E_WORKERS must be a positive integer (got ${workersRaw}).`);
  }

  return {
    mode,
    baseURL,
    shortSessionURL: process.env.E2E_SHORT_SESSION_URL ?? ISOLATED_SHORT_SESSION_URL,
    reset,
    keepStack: readBool('E2E_KEEP', false),
    workers,
    chromiumPath: process.env.E2E_CHROMIUM_PATH,
    debugArtifacts: readBool('E2E_DEBUG_ARTIFACTS', false),
    existingAckGiven,
  };
}

/**
 * The worker count playwright.config.ts actually configures AND the number
 * of per-role storageState files setup/auth.setup.ts provisions — the same
 * function is the single source of truth for both, so "which worker am I"
 * (testInfo.parallelIndex, 0-based) always has a matching auth file (design
 * spec §3.12 "per-worker sessions").
 */
const DEFAULT_ISOLATED_WORKERS = 4; // matches php -S's PHP_CLI_SERVER_WORKERS=8 ceiling with headroom (design spec §1.6)
const DEFAULT_EXISTING_WORKERS = 2; // Mode B is a courtesy local run against a shared app; stay conservative

export function effectiveWorkers(env: Pick<E2eEnv, 'mode' | 'workers'>): number {
  if (env.workers) return env.workers;
  return env.mode === 'existing' ? DEFAULT_EXISTING_WORKERS : DEFAULT_ISOLATED_WORKERS;
}

/** Thrown by the `db` fixture (and anything else Mode-A-only) in Mode B. */
export class ModeBUnavailable extends Error {
  constructor(what: string) {
    super(
      `${what} is only available in E2E_MODE=isolated. Mode B (existing) never runs Docker/DB ` +
        'commands against a shared environment — this test should be tagged @needs-isolated and ' +
        'excluded from existing-environment runs.'
    );
  }
}

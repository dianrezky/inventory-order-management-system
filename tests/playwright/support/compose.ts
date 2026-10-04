// Guarded Docker Compose wrapper for the isolated `ioms-e2e` stack
// (design spec §3.8 "safety rules", enforced here, not just documented).
//
// THIS FILE MUST NEVER BE IMPORTED FROM ANYTHING REACHABLE IN MODE B.
// (enforced by eslint.config.js's no-restricted-imports rule; see also
// specs/_smoke/guards.spec.ts which asserts the import graph statically.)
//
// Every docker invocation in the whole suite goes through `compose()` below.
// It is the ONLY function in this codebase allowed to spawn `docker`.

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const execFileAsync = promisify(execFile);

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const ENV_DIR = path.resolve(__dirname, '..', 'env');
export const COMPOSE_FILE = path.join(ENV_DIR, 'docker-compose.e2e.yaml');
export const ENV_FILE = path.join(ENV_DIR, 'e2e.env');

/** The ONLY project name this wrapper will ever operate on. */
export const PROJECT_NAME = 'ioms-e2e';

/** Volume names this project is allowed to own/remove. Any destructive op
 * that would touch a volume outside this prefix is refused. */
const OWNED_VOLUME_PREFIX = `${PROJECT_NAME}_`;

/** Forbidden tokens: smuggling a different project/compose-file in, or
 * naming a developer volume, must never reach `docker` from this wrapper. */
const FORBIDDEN_ARG_PATTERNS = [/^-p$/, /^--project-name$/, /^-f$/, /^--file$/, /^iom_db_data$/, /^iom_redis_data$/];

export class ComposeSafetyError extends Error {}

function assertSafeArgs(args: string[]): void {
  for (const arg of args) {
    if (FORBIDDEN_ARG_PATTERNS.some((re) => re.test(arg))) {
      throw new ComposeSafetyError(
        `Refusing to run docker compose with argument "${arg}" — this wrapper always pins the project ` +
          `name and compose file itself; a caller may never override them.`
      );
    }
  }
}

/** Scrubs environment variables that could redirect `docker compose` at a
 * different project than the one this wrapper pins explicitly. */
function safeChildEnv(): NodeJS.ProcessEnv {
  const env = { ...process.env };
  delete env.COMPOSE_PROJECT_NAME;
  delete env.COMPOSE_FILE;
  delete env.COMPOSE_PROJECT_DIR;
  return env;
}

export interface ComposeResult {
  stdout: string;
  stderr: string;
}

/** Runs `docker compose -p ioms-e2e -f docker-compose.e2e.yaml --env-file
 * e2e.env <args>`. This is the one and only call site for `docker` in the
 * whole suite. */
export async function compose(args: string[], opts: { timeoutMs?: number } = {}): Promise<ComposeResult> {
  assertSafeArgs(args);
  const fullArgs = ['compose', '-p', PROJECT_NAME, '-f', COMPOSE_FILE, '--env-file', ENV_FILE, ...args];
  try {
    const { stdout, stderr } = await execFileAsync('docker', fullArgs, {
      cwd: ENV_DIR,
      env: safeChildEnv(),
      timeout: opts.timeoutMs ?? 10 * 60 * 1000,
      maxBuffer: 64 * 1024 * 1024,
    });
    return { stdout, stderr };
  } catch (err) {
    const e = err as { stdout?: string; stderr?: string; message: string };
    throw new Error(
      `docker ${fullArgs.join(' ')} failed: ${e.message}\n--- stdout ---\n${e.stdout ?? ''}\n--- stderr ---\n${e.stderr ?? ''}`
    );
  }
}

async function dockerRaw(args: string[]): Promise<ComposeResult> {
  const { stdout, stderr } = await execFileAsync('docker', args, { env: safeChildEnv(), maxBuffer: 16 * 1024 * 1024 });
  return { stdout, stderr };
}

/** Lists the Docker volumes Compose considers part of this project. */
export async function listProjectVolumes(): Promise<string[]> {
  const { stdout } = await dockerRaw([
    'volume',
    'ls',
    '--filter',
    `label=com.docker.compose.project=${PROJECT_NAME}`,
    '--format',
    '{{.Name}}',
  ]);
  return stdout.split('\n').map((s) => s.trim()).filter(Boolean);
}

/** Checks Docker Compose's version is new enough for `--wait` + per-service
 * `depends_on: condition:` + `profiles:` (v2.24+), per env/README.md. */
export async function assertComposePrerequisites(): Promise<void> {
  let versionLine: string;
  try {
    const { stdout } = await dockerRaw(['compose', 'version', '--short']);
    versionLine = stdout.trim();
  } catch (err) {
    throw new Error(
      'Docker Compose v2 plugin not found (`docker compose version` failed). Install Docker Desktop or the ' +
        `compose-plugin package. Underlying error: ${(err as Error).message}`
    );
  }
  const match = versionLine.match(/^v?(\d+)\.(\d+)/);
  if (!match) {
    throw new Error(`Could not parse \`docker compose version --short\` output: "${versionLine}"`);
  }
  const major = Number(match[1]);
  const minor = Number(match[2]);
  if (major < 2 || (major === 2 && minor < 24)) {
    throw new Error(
      `Docker Compose ${versionLine} is too old for the ioms-e2e stack (needs v2.24+ for --wait with ` +
        'per-service depends_on conditions and profiles). Upgrade Docker Compose and try again.'
    );
  }
}

/**
 * THE destructive reset. Refuses unless:
 *   - mode is isolated (caller must check — this function does not read env.ts
 *     to keep this module import-graph-isolated from support/env.ts's own concerns)
 *   - every volume Docker reports for this project is prefixed `ioms-e2e_`
 * This is the concrete enforcement of design spec §3.8 rule 2.
 */
export async function resetIsolated(): Promise<void> {
  const volumes = await listProjectVolumes();
  const unexpected = volumes.filter((v) => !v.startsWith(OWNED_VOLUME_PREFIX));
  if (unexpected.length > 0) {
    throw new ComposeSafetyError(
      `Refusing destructive reset: Docker reports volume(s) [${unexpected.join(', ')}] under project ` +
        `"${PROJECT_NAME}" that do NOT start with "${OWNED_VOLUME_PREFIX}". This should be impossible given ` +
        'the compose file as committed — treat it as a safety-critical bug and stop immediately rather than ' +
        'running `down -v`.'
    );
  }
  await compose(['down', '-v', '--remove-orphans']);
}

export async function up(opts: { profiles?: string[] } = {}): Promise<void> {
  const args = ['up', '-d', '--build', '--wait', '--wait-timeout', '180'];
  for (const p of opts.profiles ?? []) args.push('--profile', p);
  await compose(args, { timeoutMs: 15 * 60 * 1000 });
}

export async function stop(): Promise<void> {
  await compose(['stop']);
}

export async function ps(): Promise<string> {
  const { stdout } = await compose(['ps', '--format', 'json']);
  return stdout;
}

export async function logs(service: string): Promise<string> {
  const { stdout } = await compose(['logs', '--no-color', service]);
  return stdout;
}

/** Runs a command inside a running project container (used by the DB oracle
 * and the low-stock job runner — both Mode-A-only). */
export async function execIn(service: string, cmd: string[]): Promise<ComposeResult> {
  return compose(['exec', '-T', service, ...cmd]);
}

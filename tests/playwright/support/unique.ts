// The ONE collision-resistant naming helper (FOUNDATION_CONTRACT.md §4).
// No domain agent may invent its own naming convention — every factory and
// every spec that needs a unique name/code/email goes through `uid`.
//
// Token shape: `${workerIndex}-${base36 timestamp}-${4-char random}`. Worker
// index rules out cross-worker collisions even with identical timestamps;
// the random suffix rules out same-worker collisions from two `uid` calls in
// the same millisecond (observed possible under fast synchronous factory
// calls). Field-specific wrappers below respect each column's real DB/service
// bound (design spec §3.14 / Correction C14) — nobody should have to go
// re-derive "SKU is varchar(50) but ProductService caps it at 30" themselves.

function randomSuffix(len = 4): string {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
  return out;
}

export interface UidToken {
  workerIndex: number;
  raw: string;
}

/** Call once per test (or once per factory invocation) — do not cache/reuse
 * across tests, or "unique" stops being true. */
export function makeToken(workerIndex: number): UidToken {
  const raw = `${workerIndex}-${Date.now().toString(36)}-${randomSuffix()}`;
  return { workerIndex, raw };
}

export interface Uid {
  readonly token: string;
  /** `E2E-<kind>-<token>`, capped to maxLen (left-truncating the token, never the "E2E-<kind>-" prefix so the kind stays readable in failure output). */
  name(kind: string, maxLen?: number): string;
  /** Product SKU: service caps at 30 chars (ProductService::validateCreatePayload), uppercase, no format restriction in code — see design spec Correction C1/§1.5. */
  sku(): string;
  /** Category code: must match `^[A-Z0-9-]{3,20}$` (CategoryService::resolveCode). */
  categoryCode(): string;
  /** Warehouse code: no service-level format rule confirmed; DB column is varchar(20) (schema.sql). Kept at or under 20 defensively. */
  warehouseCode(): string;
  email(localPrefix?: string): string;
  phone(): string;
  /** Exact-length text for boundary-value tests (e.g. "exactly 150 chars"). */
  text(len: number, filler?: string): string;
  reason(): string;
}

function truncateToken(token: string, maxFreeLen: number): string {
  return token.length > maxFreeLen ? token.slice(-maxFreeLen) : token;
}

export function createUid(workerIndex: number): Uid {
  const { raw: token } = makeToken(workerIndex);

  return {
    token,
    name(kind: string, maxLen = 60): string {
      const prefix = `E2E-${kind}-`;
      const free = Math.max(4, maxLen - prefix.length);
      return `${prefix}${truncateToken(token, free)}`;
    },
    sku(): string {
      // "E2E" + token, uppercased, hyphens stripped (SKU has no documented
      // format rule, but keep it alnum to stay inside any future tightening),
      // hard-capped at 30 (ProductService::validateCreatePayload: strlen($sku) > 30 -> reject).
      const compact = `E2E${token}`.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
      return compact.slice(0, 30);
    },
    categoryCode(): string {
      // ^[A-Z0-9-]{3,20}$
      const compact = `E2${token}`.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
      return compact.slice(0, 20);
    },
    warehouseCode(): string {
      const compact = `E2${token}`.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
      return compact.slice(0, 20);
    },
    email(localPrefix = 'e2e'): string {
      return `${localPrefix}-${token}@e2e.test`;
    },
    phone(): string {
      // Deterministic-looking local phone number, never dialled.
      const digits = token.replace(/[^0-9]/g, '').padEnd(8, '0').slice(0, 8);
      return `0812${digits}`;
    },
    text(len: number, filler = 'x'): string {
      const base = `E2E-${token}-`;
      if (base.length >= len) return base.slice(0, len);
      return base + filler.repeat(len - base.length);
    },
    reason(): string {
      return `E2E automated test — ${token}`;
    },
  };
}

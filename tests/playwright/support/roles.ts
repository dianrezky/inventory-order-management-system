// Role & credential contract (FOUNDATION_CONTRACT.md §2). Seed users are
// READ-ONLY (design spec §3.9) — this file is the only place their
// credentials are written down; nothing else may hardcode them.
// Evidence: database/seed.sql:14-19.

export type Role = 'admin' | 'sales' | 'warehouse' | 'sales2';

export interface Credentials {
  email: string;
  password: string;
  /** The seed user's display name, for assertions like "Done By" / "Created By". */
  name: string;
}

export const ROLES: readonly Role[] = ['admin', 'sales', 'warehouse', 'sales2'] as const;

export const CREDENTIALS: Record<Role, Credentials> = {
  admin: { email: 'admin@example.com', password: 'admin123', name: 'Rita' },
  sales: { email: 'sales1@example.com', password: 'sales123', name: 'Beni' },
  warehouse: { email: 'warehouse@example.com', password: 'wh123', name: 'Wawan' },
  sales2: { email: 'sales2@example.com', password: 'grace123', name: 'Grace' },
};

/** Storage-state file for one role at one worker slot. auth.setup.ts writes
 * these; fixtures.ts reads them via testInfo.parallelIndex. */
export function storageStatePath(authDir: string, role: Role, workerIndex: number): string {
  return `${authDir}/${role}.w${workerIndex}.json`;
}

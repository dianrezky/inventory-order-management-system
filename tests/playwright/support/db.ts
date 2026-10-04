// Read-only DB oracle (design spec §3.9 / §8 item 6). Mode-A-only — never
// imported from anything reachable in Mode B (enforced by eslint.config.js
// + specs/_smoke/guards.spec.ts). Used for invariant checks that the UI
// cannot observe directly (e.g. "stock == SUM(ledger)") and by the
// low-stock job runner.
//
// Every query here is a plain SELECT. This file has no write methods on
// purpose — "DB oracle" means read-only verification, never a shortcut to
// mutate state that a factory should create through the real HTTP path.

import { execIn } from './compose';
import { loadEnv, ModeBUnavailable } from './env';

interface DbRow {
  [key: string]: string;
}

function parseTsv(stdout: string): DbRow[] {
  const lines = stdout.trim().split('\n').filter((l) => l.length > 0);
  if (lines.length === 0) return [];
  const header = (lines[0] as string).split('\t');
  return lines.slice(1).map((line) => {
    const cells = line.split('\t');
    const row: DbRow = {};
    header.forEach((h, i) => (row[h] = cells[i] ?? ''));
    return row;
  });
}

async function assertModeA(what: string): Promise<void> {
  const env = loadEnv();
  if (env.mode !== 'isolated') throw new ModeBUnavailable(what);
}

/** Runs a read-only SELECT inside the e2e `db` container via the MySQL CLI
 * (same credentials as env/e2e.env) and returns rows as plain objects. */
export async function query(sql: string): Promise<DbRow[]> {
  await assertModeA('The DB oracle (support/db.ts)');
  if (!/^\s*(SELECT|SHOW|EXPLAIN)\b/i.test(sql)) {
    throw new Error(`support/db.ts only runs read-only statements; refused: ${sql}`);
  }
  const { stdout } = await execIn('db', [
    'mysql',
    '-u',
    'root',
    `-p${process.env.E2E_DB_ROOT_PASSWORD ?? 'ioms_e2e_root_pw'}`,
    'ioms_e2e',
    '-e',
    sql,
  ]);
  return parseTsv(stdout);
}

export async function stockOf(productSku: string, warehouseCode: string): Promise<number> {
  const rows = await query(
    `SELECT ps.quantity AS q FROM product_stocks ps
     JOIN products p ON p.id = ps.product_id
     JOIN warehouses w ON w.id = ps.warehouse_id
     WHERE p.sku = '${productSku.replace(/'/g, "''")}' AND w.code = '${warehouseCode.replace(/'/g, "''")}'`
  );
  return rows.length > 0 ? Number(rows[0]?.q ?? 0) : 0;
}

export async function ledgerSum(productSku: string, warehouseCode: string): Promise<number> {
  const rows = await query(
    `SELECT COALESCE(SUM(sl.qty), 0) AS s FROM stock_ledger sl
     JOIN products p ON p.id = sl.product_id
     JOIN warehouses w ON w.id = sl.warehouse_id
     WHERE p.sku = '${productSku.replace(/'/g, "''")}' AND w.code = '${warehouseCode.replace(/'/g, "''")}'`
  );
  return Number(rows[0]?.s ?? 0);
}

/**
 * The invariant gate (design spec §3.9): product_stocks.quantity ==
 * SUM(stock_ledger.qty) for every product+warehouse pair, no negative
 * stock, and no PO line with qty_received > qty_ordered. Called from
 * globalTeardown in Mode A; throws (failing the run) on any violation.
 */
export async function assertInvariants(): Promise<void> {
  const mismatches = await query(`
    SELECT ps.product_id, ps.warehouse_id, ps.quantity AS stock_qty,
           COALESCE((SELECT SUM(sl.qty) FROM stock_ledger sl
                     WHERE sl.product_id = ps.product_id AND sl.warehouse_id = ps.warehouse_id), 0) AS ledger_sum
    FROM product_stocks ps
    HAVING stock_qty <> ledger_sum
  `);
  if (mismatches.length > 0) {
    throw new Error(`Stock/ledger invariant violated for ${mismatches.length} product+warehouse pair(s): ${JSON.stringify(mismatches)}`);
  }

  const negative = await query(`SELECT product_id, warehouse_id, quantity FROM product_stocks WHERE quantity < 0`);
  if (negative.length > 0) {
    throw new Error(`Negative stock found: ${JSON.stringify(negative)}`);
  }

  const overReceived = await query(
    `SELECT id, purchase_order_id, qty_ordered, qty_received FROM purchase_order_items WHERE qty_received > qty_ordered`
  );
  if (overReceived.length > 0) {
    throw new Error(`Over-receipt found in purchase_order_items: ${JSON.stringify(overReceived)}`);
  }
}

/** Runs the low-stock CLI job (scripts/check-low-stock.php) inside the app
 * container (design spec §1.4 "additions" / §3.25 classification: this is a
 * CLI job, not a browser concern — Playwright only verifies its outcome).
 * Idempotent (NotificationService dedupes by unread existing row), so
 * calling this more than once in a test is safe and will NOT create
 * duplicate notifications. */
export async function runLowStockJob(): Promise<string> {
  await assertModeA('runLowStockJob()');
  const { stdout } = await execIn('app', ['php', 'scripts/check-low-stock.php']);
  return stdout;
}

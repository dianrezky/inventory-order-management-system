// CSV export helpers (design spec §3.23 / FOUNDATION_CONTRACT.md §6).
// CsvExportService writes CRLF line endings, RFC-4180 quoting, no BOM, and
// prefixes a leading `= + - @ TAB CR LF` with `'` (Quantity column exempt —
// see messages.ts / design spec §1.5).

import type { APIRequestContext } from '@playwright/test';
import { post } from './request';

export interface CsvResult {
  status: number;
  contentType: string;
  filename: string | null;
  raw: string;
  rows: string[][];
}

const DISPOSITION_FILENAME_RE = /filename="?([^";]+)"?/;

/** Naive-but-correct RFC 4180 parser: handles quoted fields, escaped `""`,
 * commas/CRLF inside quotes. Good enough for our own CsvExportService
 * output, which we fully control the shape of. */
export function parseCsv(raw: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let field = '';
  let inQuotes = false;
  let i = 0;
  while (i < raw.length) {
    const c = raw[i];
    if (inQuotes) {
      if (c === '"') {
        if (raw[i + 1] === '"') {
          field += '"';
          i += 2;
          continue;
        }
        inQuotes = false;
        i++;
        continue;
      }
      field += c;
      i++;
      continue;
    }
    if (c === '"') {
      inQuotes = true;
      i++;
      continue;
    }
    if (c === ',') {
      row.push(field);
      field = '';
      i++;
      continue;
    }
    if (c === '\r' && raw[i + 1] === '\n') {
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
      i += 2;
      continue;
    }
    if (c === '\n') {
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
      i++;
      continue;
    }
    field += c;
    i++;
  }
  if (field !== '' || row.length > 0) {
    row.push(field);
    rows.push(row);
  }
  return rows.filter((r) => !(r.length === 1 && r[0] === ''));
}

/**
 * POSTs a CSV-export route (ledger/orders/categories exports are POST-only;
 * GET is never served as CSV — design spec §1.5) and parses the response.
 * `csrfSourcePath` lets a POST-only export route (e.g.
 * /reports/export/stock-ledger) borrow a token from a page that actually
 * renders a form (e.g. /reports), in the same session.
 */
export async function downloadCsv(
  request: APIRequestContext,
  path: string,
  form: Record<string, string | string[]> = {},
  opts: { csrfSourcePath?: string } = {}
): Promise<CsvResult> {
  const res = await post(request, path, form, { csrf: 'valid', csrfSourcePath: opts.csrfSourcePath });
  const disposition = res.headers['content-disposition'] ?? '';
  const match = disposition.match(DISPOSITION_FILENAME_RE);
  const isCsv = res.status === 200 && res.contentType.includes('text/csv');
  return {
    status: res.status,
    contentType: res.contentType,
    filename: match ? match[1] ?? null : null,
    raw: res.body,
    rows: isCsv ? parseCsv(res.body) : [],
  };
}

/** The injection-prefix check used across Categories/Ledger/Orders export
 * specs (design spec §3.23 / CsvExportService::escapeCsvField). */
export function expectInjectionGuarded(cellRaw: string): void {
  if (/^[ ]*[=+\-@\t\r\n]/.test(cellRaw) && !cellRaw.startsWith("'")) {
    throw new Error(`CSV cell "${cellRaw}" looks like an un-prefixed formula-injection payload.`);
  }
}

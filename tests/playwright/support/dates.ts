// UTC date helpers (design spec §3.27 determinism rule / §3.14). The app
// server and MySQL both run in UTC (no php.ini tz override; confirmed by
// spike S-4 in F1.2's acceptance). Never hardcode a date literal in a spec —
// seed.sql is date-relative (CURDATE() - INTERVAL n DAY) and the legacy
// suite's frozen 2026-08/09 windows are exactly the bug this file prevents
// (design spec Correction C19 / §15 parity notes).

export function utcToday(): Date {
  const now = new Date();
  return new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
}

export function toYmd(date: Date): string {
  return date.toISOString().slice(0, 10);
}

export function addDays(date: Date, days: number): Date {
  const copy = new Date(date.getTime());
  copy.setUTCDate(copy.getUTCDate() + days);
  return copy;
}

export function todayYmd(): string {
  return toYmd(utcToday());
}

export function ymdDaysAgo(days: number): string {
  return toYmd(addDays(utcToday(), -days));
}

/** [from, to] covering the last `days` days inclusive of today, matching the
 * ReportService default window shape (last-30-days) without hardcoding it. */
export function lastNDaysRange(days: number): { from: string; to: string } {
  return { from: ymdDaysAgo(days - 1), to: todayYmd() };
}

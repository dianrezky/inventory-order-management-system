// Common HTTP request helper (FOUNDATION_CONTRACT.md §6). Wraps Playwright's
// APIRequestContext so every direct-request security/RBAC/workflow spec
// posts forms the same way the browser would (urlencoded body, no redirect
// auto-follow so the exact status/Location is observable) instead of each
// spec hand-rolling fetch/axios calls.

import type { APIRequestContext } from '@playwright/test';

export type CsrfMode = 'valid' | 'missing' | 'invalid' | 'stale' | ((token: string | null) => string | undefined);

export interface PostResult {
  status: number;
  location: string | null;
  body: string;
  json: unknown | null;
  contentType: string;
  headers: Record<string, string>;
}

const CSRF_FIELD = '_csrf_token';
const CSRF_INPUT_RE = /name=["']_csrf_token["']\s+value=["']([0-9a-f]*)["']/;

/** Extracts the hidden `_csrf_token` value from an HTML page body. Every
 * authenticated page embeds one (BaseController::view()); returns null if
 * none is present (e.g. the page itself denied access). */
export function extractCsrfFromHtml(html: string): string | null {
  const m = html.match(CSRF_INPUT_RE);
  return m ? m[1] ?? null : null;
}

/** GETs `path` and returns its CSRF token, or null if the page has none
 * (e.g. a 403/404 page carries no form). */
export async function fetchCsrfToken(request: APIRequestContext, path: string): Promise<string | null> {
  const res = await request.get(path);
  const body = await res.text();
  return extractCsrfFromHtml(body);
}

/**
 * PHP's $_POST array-field convention requires the WIRE field name to carry
 * a literal `[]` suffix for every repeated value (e.g.
 * `item_product_id[]=1&item_product_id[]=2`) — sending the same key twice
 * WITHOUT `[]` makes PHP keep only the last value, not an array. Playwright's
 * `request.post({form:...})` option also has no array-value support at all,
 * so this helper builds the urlencoded body by hand instead, letting every
 * factory/spec pass a plain `{key: string[]}` without knowing that detail.
 */
function encodeForm(form: Record<string, string | string[]>): string {
  const parts: string[] = [];
  for (const [key, value] of Object.entries(form)) {
    if (Array.isArray(value)) {
      for (const v of value) parts.push(`${encodeURIComponent(`${key}[]`)}=${encodeURIComponent(v)}`);
    } else {
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
    }
  }
  return parts.join('&');
}

function resolveCsrfValue(mode: CsrfMode, validToken: string | null): string | undefined {
  if (typeof mode === 'function') return mode(validToken);
  switch (mode) {
    case 'valid':
      return validToken ?? undefined;
    case 'missing':
      return undefined;
    case 'invalid':
      return '0'.repeat(64);
    case 'stale':
      // Caller must have already captured a real-but-superseded token and
      // passed it in via the function form; falling through to 'invalid'
      // shape keeps this helper total.
      return '0'.repeat(64);
  }
}

/**
 * POSTs an urlencoded form to `path` using the given request context's
 * cookies (i.e. whichever role's storageState created it), with
 * maxRedirects:0 so a 302 is observable rather than silently followed.
 *
 * `csrf` controls the `_csrf_token` field: 'valid' fetches a fresh token
 * from `path` first (GET then POST, matching how a real form works); pass a
 * function to supply a captured/stale token explicitly.
 */
export async function post(
  request: APIRequestContext,
  path: string,
  form: Record<string, string | string[]> = {},
  opts: { csrf?: CsrfMode; headers?: Record<string, string>; csrfSourcePath?: string } = {}
): Promise<PostResult> {
  const csrfMode = opts.csrf ?? 'valid';
  let token: string | null = null;
  if (csrfMode === 'valid' || csrfMode === 'missing' || csrfMode === 'invalid') {
    // Even 'missing'/'invalid' GET the page first so cookies/behaviour match
    // a real browser session that already rendered the form once. A caller
    // whose target route has no GET form of its own (e.g. a POST-only
    // export action) passes csrfSourcePath to borrow a token from a page
    // that does render one, in the same session.
    token = await fetchCsrfToken(request, opts.csrfSourcePath ?? path);
  }
  const resolved = resolveCsrfValue(csrfMode, token);

  const multiForm: Record<string, string | string[]> = { ...form };
  if (resolved !== undefined) multiForm[CSRF_FIELD] = resolved;

  const res = await request.post(path, {
    data: encodeForm(multiForm),
    headers: { 'content-type': 'application/x-www-form-urlencoded', ...opts.headers },
    maxRedirects: 0,
    failOnStatusCode: false,
  });

  const contentType = res.headers()['content-type'] ?? '';
  const body = await res.text();
  let json: unknown | null = null;
  if (contentType.includes('application/json')) {
    try {
      json = JSON.parse(body);
    } catch {
      json = null;
    }
  }

  return {
    status: res.status(),
    location: res.headers()['location'] ?? null,
    body,
    json,
    contentType,
    headers: res.headers(),
  };
}

/** GET with maxRedirects:0, for RBAC/tamper specs that need the exact
 * status/Location rather than the final destination page. */
export async function get(
  request: APIRequestContext,
  path: string,
  opts: { headers?: Record<string, string> } = {}
): Promise<PostResult> {
  const res = await request.get(path, { maxRedirects: 0, headers: opts.headers, failOnStatusCode: false });
  const contentType = res.headers()['content-type'] ?? '';
  const body = await res.text();
  let json: unknown | null = null;
  if (contentType.includes('application/json')) {
    try {
      json = JSON.parse(body);
    } catch {
      json = null;
    }
  }
  return { status: res.status(), location: res.headers()['location'] ?? null, body, json, contentType, headers: res.headers() };
}

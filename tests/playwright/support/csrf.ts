// CSRF helpers (design spec §3.20 / FOUNDATION_CONTRACT.md §6). Nobody
// outside this file hand-constructs a `_csrf_token` value.

import type { APIRequestContext, Page } from '@playwright/test';
import { extractCsrfFromHtml, fetchCsrfToken } from './request';

export const csrf = {
  /** Reads the token already rendered into a live Page (any authenticated
   * page carries one — BaseController::view()). */
  async fromPage(page: Page): Promise<string> {
    const value = await page.locator('input[name="_csrf_token"]').first().inputValue();
    if (!value) throw new Error('No _csrf_token input found on the current page.');
    return value;
  },

  /** GETs `path` with the given request context and returns its token. */
  fromRequest,

  /** A syntactically-plausible but definitely-wrong token (same length as a
   * real one: IdObfuscator/csrf both use 64 lowercase-hex via bin2hex(32
   * random bytes), so this won't be rejected merely for shape). */
  invalid(): string {
    return 'f'.repeat(64);
  },

  /**
   * Captures a token from an ANONYMOUS context (before any login), for
   * "stale pre-login CSRF" tests: AuthService::login() discards the
   * pre-login token and issues a fresh one, so this must be rejected after
   * login completes.
   */
  async staleFromAnonymousLogin(request: APIRequestContext): Promise<string> {
    const token = await fetchCsrfToken(request, '/login');
    if (!token) throw new Error('Could not capture a pre-login CSRF token from /login.');
    return token;
  },
};

async function fromRequest(request: APIRequestContext, path: string): Promise<string> {
  const token = await fetchCsrfToken(request, path);
  if (!token) throw new Error(`No _csrf_token found on ${path} — page may have denied access before rendering a form.`);
  return token;
}

export { extractCsrfFromHtml };

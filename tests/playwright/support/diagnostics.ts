// Failure diagnostics (design spec §3.25 / FOUNDATION_CONTRACT.md §10).
// Auto-attached fixture: every test gets console/pageerror/failed-request
// capture, and a PHP-leak / unexpected-5xx guard that auto-fails a test
// unless it explicitly opted in via `diagnostics.expect500()`.

import type { Page, ConsoleMessage, Request, Response } from '@playwright/test';

// index.php's last-resort catch logs the real trace server-side only and
// never echoes it (ERR-01.04/.05) — any of these patterns appearing in an
// HTTP response body means a PHP error leaked to the browser.
const PHP_LEAK_RE = /(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\s*:/;

export interface CapturedFailedRequest {
  url: string;
  method: string;
  status: number;
  statusText: string;
}

export class DiagnosticsRecorder {
  readonly consoleErrors: string[] = [];
  readonly pageErrors: string[] = [];
  readonly failedResponses: CapturedFailedRequest[] = [];
  readonly actions: string[] = [];
  private expectingNon2xx = false;
  private phpLeakDetected: string | null = null;

  constructor(private readonly page: Page) {
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') this.consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err: Error) => {
      this.pageErrors.push(err.stack ?? err.message);
    });
    page.on('requestfailed', (req: Request) => {
      this.failedResponses.push({ url: req.url(), method: req.method(), status: 0, statusText: req.failure()?.errorText ?? 'failed' });
    });
    page.on('response', async (res: Response) => {
      if (res.status() >= 400) {
        this.failedResponses.push({ url: res.url(), method: res.request().method(), status: res.status(), statusText: res.statusText() });
      }
      if (res.status() >= 500 && this.phpLeakDetected === null) {
        try {
          const ct = res.headers()['content-type'] ?? '';
          if (ct.includes('text/html') || ct.includes('text/plain')) {
            const body = await res.text().catch(() => '');
            const m = body.match(PHP_LEAK_RE);
            if (m) this.phpLeakDetected = `${res.url()} → ${m[0]} (PHP error leaked to response body)`;
          }
        } catch {
          /* response body not available (e.g. redirected/aborted) — ignore */
        }
      }
    });
  }

  /** Call when a test deliberately exercises a 500-presentation path
   * (design spec §3.25) so the auto-guard does not fail it. */
  expect500(): void {
    this.expectingNon2xx = true;
  }

  step(label: string): void {
    this.actions.push(`${new Date().toISOString()} ${label}`);
  }

  /** Called by the fixture teardown. Throws (failing the test) on an
   * undeclared PHP leak, unless expect500() was called. */
  assertClean(): void {
    if (this.phpLeakDetected && !this.expectingNon2xx) {
      throw new Error(`PHP error leaked into an HTTP response: ${this.phpLeakDetected}`);
    }
  }

  summary(): object {
    return {
      consoleErrors: this.consoleErrors,
      pageErrors: this.pageErrors,
      failedResponses: this.failedResponses,
      lastActions: this.actions.slice(-20),
      url: this.page.url(),
    };
  }

  hasAnything(): boolean {
    return this.consoleErrors.length > 0 || this.pageErrors.length > 0 || this.failedResponses.length > 0;
  }
}

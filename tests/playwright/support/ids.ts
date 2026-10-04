// ID-obfuscation helpers (design spec §3.21 / FOUNDATION_CONTRACT.md §6).
// Tests must NEVER hardcode or hand-construct an encoded id token or a raw
// numeric id (except as a deliberate negative-test input via raw()). Every
// token used positively is captured from the rendered UI/a redirect.
//
// IdObfuscator (app/Core/IdObfuscator.php): token = bin2hex(KEY .
// bin2hex((string)$id)), route constraint `[0-9a-f]+`. This file never
// knows KEY — it only mutates/inspects already-issued tokens, exactly like
// an attacker without the key would have to.

import type { Locator } from '@playwright/test';

/** Extracts the trailing hex token from an href/URL path like
 * `/sales-orders/{token}` or `/sales-orders/{token}/edit`. */
export function tokenFromPath(path: string): string {
  const url = new URL(path, 'http://placeholder.invalid');
  const segments = url.pathname.split('/').filter(Boolean);
  // Walk from the end: the id segment is hex and is followed only by
  // literal action segments (edit/update/submit/...), never another id.
  for (let i = segments.length - 1; i >= 0; i--) {
    const seg = segments[i] as string;
    if (/^[0-9a-f]+$/.test(seg) && seg.length >= 8) return seg;
  }
  throw new Error(`No obfuscated id token found in path: ${path}`);
}

export async function tokenFromHref(locator: Locator): Promise<string> {
  const href = await locator.getAttribute('href');
  if (!href) throw new Error('Locator has no href attribute.');
  return tokenFromPath(href);
}

/** Extracts the id token from a 302 redirect's Location header (e.g. after
 * PO/SO/product create: `/purchase-orders/{token}`). */
export function tokenFromRedirect(location: string | null): string {
  if (!location) throw new Error('Response carried no Location header.');
  return tokenFromPath(location);
}

export const ids = {
  tokenFromPath,
  tokenFromHref,
  tokenFromRedirect,

  /** Flips one hex nibble near the end of a real token — decodes to a
   * *different* id (still passes the router's `[0-9a-f]+` constraint, so it
   * reaches the controller and must be rejected there: 404 for an allowed
   * role, since no row — or a different row's owner check — will match). */
  tamper(token: string): string {
    const chars = token.split('');
    const i = chars.length - 1;
    const current = chars[i] as string;
    const next = current === 'f' ? '0' : String.fromCharCode(current.charCodeAt(0) + 1);
    chars[i] = next;
    return chars.join('');
  },

  /** Drops the last 2 hex chars — still matches the route's hex constraint
   * but decodes to garbage (IdObfuscator::decode returns null -> 404). */
  truncate(token: string): string {
    return token.slice(0, Math.max(1, token.length - 2));
  },

  /** Not hex at all — fails the router's own `[0-9a-f]+` constraint, so this
   * never reaches the controller (plain 404 from the route matcher). */
  nonHex(): string {
    return 'zzzznothex';
  },

  /** A raw small integer, used only as a deliberate negative-test input
   * (it happens to be hex-shaped, e.g. "1", "10", so it reaches the
   * controller, where IdObfuscator::decode rejects it as not
   * key-prefixed -> 404). Never used to address a real resource. */
  raw(n: number | string = 1): string {
    return String(n);
  },
};

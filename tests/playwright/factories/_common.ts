// Shared factory plumbing (design spec §8 item 5). Every factory creates
// through the real HTTP+CSRF path (never a DB insert shortcut) so creation
// also exercises real validation, then resolves the created record's
// obfuscated id token the same way an attacker without the key would have
// to: either from a redirect Location (PO/SO/products... when the
// controller gives one) or by searching the list and parsing a row link
// (Warehouses/Customers/Suppliers/Users/Products all redirect to their
// LIST page on create, not to a detail page — confirmed in
// *Controller::storeAction() — so there is no Location to take a token
// from for those).

import type { APIRequestContext } from '@playwright/test';
import { post } from '../support/request';
import { tokenFromPath } from '../support/ids';

export class FactoryError extends Error {
  constructor(action: string, status: number, body: string) {
    super(`${action} failed: HTTP ${status}\n${body.slice(0, 500)}`);
  }
}

export async function createViaForm(
  request: APIRequestContext,
  createFormPath: string,
  storePath: string,
  form: Record<string, string | string[]>
): Promise<{ status: number; location: string | null; body: string }> {
  const res = await post(request, storePath, form, { csrf: 'valid', csrfSourcePath: createFormPath });
  if (res.status !== 302 && res.status !== 303) {
    throw new FactoryError(`POST ${storePath}`, res.status, res.body);
  }
  return res;
}

/**
 * Resolves the obfuscated id token of a just-created record that redirects
 * to a LIST page (not a detail page) by POSTing the list's own `/search`
 * route filtered to the exact unique value we just created, then parsing
 * the first href matching `basePath/{token}` out of the HTML.
 *
 * This is the one sanctioned place that regexes HTML instead of using a
 * Playwright Locator — factories run over APIRequestContext (no DOM), by
 * design, so creation never depends on a browser page being open.
 */
export async function findTokenBySearch(
  request: APIRequestContext,
  basePath: string,
  searchForm: Record<string, string>
): Promise<string> {
  const res = await post(request, `${basePath}/search`, searchForm, { csrf: 'valid', csrfSourcePath: basePath });
  if (res.status !== 200) throw new FactoryError(`POST ${basePath}/search`, res.status, res.body);
  const re = new RegExp(`href="${basePath.replace(/\//g, '\\/')}\\/([0-9a-f]{8,})(?:["/])`);
  const match = res.body.match(re);
  if (!match) {
    throw new Error(
      `Could not find a ${basePath}/{token} link in the search results for ${JSON.stringify(searchForm)}. ` +
        'The record may not have been created, or the list markup changed — see factories/_common.ts.'
    );
  }
  return match[1] as string;
}

/**
 * Several create forms (Products, Purchase Orders, Sales Orders) need the
 * REAL numeric id a <select> posts (category_id, destination_warehouse_id,
 * supplier_id, source_warehouse_id, customer_id) — never the obfuscated
 * token a factory's `.create()` otherwise returns. There is no HTTP-only
 * way to recover a numeric id from a token (that is the entire point of
 * IdObfuscator) — so instead we scrape the option whose VISIBLE TEXT
 * matches the unique name/code we just created out of the relevant form's
 * own <select>, exactly as a sighted user picking from a dropdown would.
 */
export async function resolveSelectValueByText(
  request: APIRequestContext,
  formPath: string,
  selectName: string,
  optionText: string
): Promise<string> {
  const res = await request.get(formPath);
  const body = await res.text();
  const selectMatch = body.match(new RegExp(`<select[^>]*name="${selectName}(?:\\[\\])?"[^>]*>([\\s\\S]*?)</select>`));
  if (!selectMatch) throw new Error(`No <select name="${selectName}"> found on ${formPath}.`);
  const escaped = optionText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const optionMatch = (selectMatch[1] as string).match(new RegExp(`<option value="(\\d+)"[^>]*>[^<]*${escaped}`));
  if (!optionMatch) {
    throw new Error(`Could not find an option containing "${optionText}" in <select name="${selectName}"> on ${formPath}.`);
  }
  return optionMatch[1] as string;
}

/**
 * The Sales Order create form does NOT render a static `<select>` for
 * products — sales-orders.js builds rows client-side from a
 * `window._soProductList` JSON blob embedded in the page
 * (views/sales/form.php). This reads that blob directly instead of trying
 * to regex a <select> that doesn't exist server-side.
 */
export async function resolveSoProductId(request: APIRequestContext, sku: string): Promise<string> {
  const res = await request.get('/sales-orders/create');
  const body = await res.text();
  const match = body.match(/window\._soProductList\s*=\s*(\[[\s\S]*?\]);/);
  if (!match) throw new Error('Could not find window._soProductList on /sales-orders/create.');
  const list = JSON.parse(match[1] as string) as { id: number; sku: string; name: string }[];
  const found = list.find((p) => p.sku === sku);
  if (!found) throw new Error(`SKU "${sku}" not found in /sales-orders/create's product list (inactive product, or not yet created?).`);
  return String(found.id);
}

export { tokenFromPath };

// Gate G1 smoke: one representative RBAC check per direction (guest, role
// denial, CSRF). The FULL RBAC/security matrices are Agent 2's Wave-2
// scope (design spec §29/§12) — this is only a foundation-level proof that
// the http/csrf/ids helpers actually produce the documented contract
// against the real app.

import { test, expect } from '../../support/fixtures';
import { TAGS } from '../../support/tags';

test.describe('smoke: representative RBAC/CSRF checks', () => {
  test('guest GET /products redirects to /login', { tag: [TAGS.smoke, TAGS.rbac] }, async ({ guestRequest }) => {
    const res = await guestRequest.get('/products', { maxRedirects: 0 });
    expect(res.status()).toBe(302);
    expect(res.headers()['location']).toBe('/login');
  });

  test('guest GET /api/products/ELEC-001/availability returns 401 JSON', { tag: [TAGS.smoke, TAGS.rbac, TAGS.seedDependent] }, async ({
    guestRequest,
  }) => {
    const res = await guestRequest.get('/api/products/ELEC-001/availability');
    expect(res.status()).toBe(401);
    expect(await res.json()).toEqual({ error: 'unauthorized' });
  });

  test('Sales gets 403 on GET /purchase-orders', { tag: [TAGS.smoke, TAGS.rbac] }, async ({ salesRequest }) => {
    const res = await salesRequest.get('/purchase-orders', { maxRedirects: 0 });
    expect(res.status()).toBe(403);
  });

  test('missing CSRF on a real POST is rejected with HTTP 400 and the contract message', { tag: [TAGS.smoke, TAGS.security] }, async ({
    adminRequest,
    http,
  }) => {
    const res = await http.post(adminRequest, '/categories', { name: 'should-not-be-created', code: 'NOPE01' }, { csrf: 'missing' });
    expect(res.status).toBe(400);
    expect(res.body).toContain('Your session has expired or the form is invalid. Please try again.');
  });

  test('a tampered id token on an allowed role is rejected with 404, not 403 or 200', { tag: [TAGS.smoke, TAGS.security] }, async ({
    adminRequest,
    factories,
    ids,
  }) => {
    const category = await factories.categories.create();
    const tampered = ids.tamper(category.id);
    const res = await adminRequest.get(`/categories/${tampered}`, { maxRedirects: 0 });
    expect(res.status()).toBe(404);
  });
});

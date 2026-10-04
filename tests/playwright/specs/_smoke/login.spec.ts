// Gate G1 smoke (design spec Phase 1 acceptance: "a safe smoke login test
// can run without touching developer DB/volumes"). @smoke is Mode-B-safe
// (no @needs-isolated/@seed-dependent tag) on purpose — it only asserts
// behaviour that holds regardless of seed state.

import { test, expect } from '../../support/fixtures';
import { TAGS } from '../../support/tags';
import { CREDENTIALS } from '../../support/roles';

test.describe('smoke: login lands every role on its dashboard', () => {
  for (const role of ['admin', 'sales', 'warehouse', 'sales2'] as const) {
    test(`${role} logs in and reaches /dashboard`, { tag: [TAGS.smoke, TAGS.security] }, async ({ loginAs }, testInfo) => {
      const session = await loginAs(role);
      await expect(session.page).toHaveURL(/\/dashboard/);
      await expect(session.page.locator('input[name="_csrf_token"]').first()).toHaveCount(1);
      // Named, easy-to-find visual evidence per role (on top of the
      // config-level automatic per-test screenshot — see playwright.config.ts).
      const png = await session.page.screenshot({ fullPage: true });
      await testInfo.attach(`${role}-dashboard`, { body: png, contentType: 'image/png' });
      await session.close();
    });
  }

  test('wrong password is rejected with the generic message, HTTP 200, no session', { tag: [TAGS.smoke, TAGS.security] }, async ({
    guestPage,
  }) => {
    await guestPage.goto('/login');
    await guestPage.locator('#email').fill(CREDENTIALS.admin.email);
    await guestPage.locator('#password').fill('definitely-wrong-password');
    const [response] = await Promise.all([
      guestPage.waitForResponse((r) => r.url().endsWith('/login') && r.request().method() === 'POST'),
      guestPage.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBe(200);
    await expect(guestPage.getByText('The email address or password you entered is incorrect.')).toBeVisible();
    // Still on /login, not redirected — confirms no session was established.
    expect(guestPage.url()).toContain('/login');
  });
});

# Refactor Log

**Document:** `docs/quality/refactor-log.md`
**Project:** Inventory & Order Management System
**Purpose:** Track architectural and design decisions made during implementation that deviate from the original design, with rationale.

---

## 1. `User::$role` stored as `string` instead of `Role` enum

**Date:** 2026-09-01
**Stage:** Slice 1–2 (Setup + Master Data)

**What was designed:**
`User` entity was intended to use `Role` backed enum:
```php
public readonly Role $role;
```

**What was implemented:**
```php
public readonly string $role; // stores 'Admin' | 'Sales' | 'WarehouseStaff'
```

**Rationale:** Simpler persistence (no enum-to-string conversion in `fromArray()`). The `Role` enum exists but is used only for `requireRole()` parameter typing and the base layout's nav guard. Direct string comparisons (`$user->role === 'Admin'`) are used throughout views and controllers.

**Impact:**
- `Role` backed enum exists but is not the canonical User.role type
- Controllers and views use string comparison (`=== 'Admin'`) which works correctly
- PHPStan level 5 is clean; no type errors

**Revisit:** Consider migrating `User::$role` to `Role` enum type in a future refactor.

---

## 2. `SalesOrderService::approve()` — no role check, only `createdBy` check

**Date:** 2026-09-01
**Stage:** Slice 4 (Sales Order)

**What was designed:**
BR-001 says "Sales cannot approve their own SO." The ADR-001 architecture described this as a role-based segregation.

**What was implemented:**
`SalesOrderPolicy::assertCanBeApprovedBy()` checks only `actorId !== createdBy`:
```php
if ($so->createdBy === $actorId) {
    throw new SalesApprovalForbiddenException(...);
}
```

**Rationale:** The segregation is purely identity-based (who created the SO), not role-based. Any authenticated user can approve any SO they did not create. Admin can also approve (they are not the creator). This correctly implements the business rule.

**Impact:** No negative impact. Simpler implementation, correct behavior.

---

## 3. `BaseController::requireRole()` accepts `Role ...$roles` variadic, not string

**Date:** 2026-09-01
**Stage:** Slice 4 (Sales Order)

**What was designed:** Controllers use `requireRole(Role::Admin)` with the `Role` enum.

**What was implemented:** This works correctly because `$user->role` is a string, and `in_array($user->role, ['Admin'], true)` compares the string to the array of strings derived from `Role::$allowed = array_map(fn(Role $r) => $r->value, $roles)`. No bugs.

---

## 4. Pagination — server-side offset calculation in controller, not service

**Date:** 2026-09-01
**Stage:** Slice 5 (Discovery)

**What was designed:** FIND-01 spec described server-side pagination.

**What was implemented:** Offset calculated in each controller:
```php
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * self::PER_PAGE;
```

**Rationale:** Service layer focuses on business logic; HTTP concern (page param) stays in controller.

---

## 5. `DashboardService` — no caching (per spec FR-10.8)

**Date:** 2026-09-01
**Stage:** Slice 5 (Discovery)

**Deliberate decision:** No Redis/memory cache (except Redis sessions + Memcached translation cache — added post- Slice 6).
All dashboard KPIs are computed fresh from DB on every page load. This is explicitly allowed by FR-10.8 which says "no stale data" — live queries are correct.

---

## 6. `ProductFakeRepository::findLowStock()` returns all active products

**Date:** 2026-09-01
**Stage:** Slice 5 (Discovery)

**What was implemented:** In the Fake, `findLowStock()` returns all active products (no stock computation).

**Rationale:** The Fake repository cannot replicate `SUM(product_stocks.quantity)` without cross-repo injection. The DashboardService unit test injects PO/SO statuses via reflection to avoid this coupling.

**Impact on tests:** Unit tests for DashboardService use reflection-based injection to test queue counting without needing stock data.

---

## 7. Slice 5 DoD verification — real bugs found and fixed

**Date:** 2026-09-01/02
**Stage:** Slice 5 DoD verification (before Slice 6)

Verifying the Slice 5 DoD against the running app (not just re-reading source) surfaced four real, previously-undetected bugs, all now fixed with phpunit (75/75) and phpstan staying green:

1. **`public/index.php` router** forced every `{placeholder}` route param to `\d+` (digits-only), which broke `GET /api/products/{sku}/availability` for any non-numeric SKU. Fixed so only `{id}` is digit-constrained.
2. **`$user->role->value` used against a plain string** in `views/dashboard/index.php`, `views/reports/export-form.php`, `views/sales/detail.php`, and `app/Controller/ReportController.php` — `User::$role` is a `string`, not the `Role` enum (see item 1 above). This silently broke every dashboard KPI for every role and broke the SO CSV export headers. Fixed to `$user->role === '...'` string comparisons.
3. **`CsvExportService`**'s CSV-injection escaping guard was applied to the numeric `qty` column, corrupting legitimate negative quantities (e.g. `-5` became the string `'-5`). Fixed with a dedicated `rawNumericField()` formatter for numeric columns.
4. **`scripts/check-low-stock.php`** used the invalid `%n` printf specifier, causing a fatal `ArgumentCountError` on every run. Fixed to `\n`.

Also noted: the running `iom_db` volume had an empty `sales_orders` table (the Slice-4 SO section of `seed.sql` was never applied to that already-initialized volume). Worked around by creating real SOs through the app's own POST flow rather than editing the DB directly; re-verified against a clean `docker compose down -v && up --build` in Slice 6 (see `docs/ai-usage-log.md`).

---

## 8. Slice 6 verification — CSS design-token naming mismatch and dark-theme button contrast

**Date:** 2026-09-02
**Stage:** Slice 6 (Quality & Docs)

`public/assets/css/main.css` referenced `--color-brand-primary` / `--color-brand-secondary` (the names given in `docs/planning/ux-ui-spec.md` §1.1) and several typography/spacing/elevation tokens (`--font-weight-semibold`, `--font-weight-normal`, `--font-weight-bold`, `--shadow-sm`, `--line-height-heading`, `--font-size-small`, `--space-5`) that `public/assets/css/tokens.css` never defined — it only defined the differently-named `--color-primary` / `--color-secondary`. An undefined CSS custom property falls back to its initial value rather than erroring, so this was invisible without actually inspecting computed styles: primary/tertiary button fills, link color, and the global focus-ring outline all silently lost their color. Fixed by adding the missing tokens to `tokens.css` (both themes) — see `docs/quality/tech-debt.md` TDB-R07.

Independently recomputing WCAG contrast for every token pair actually used together (not trusting `docs/quality/wcag-contrast-audit.md`'s existing numbers, which a prior fix already relied on) found the dark-theme `--color-danger` "2.45:1 fail" claim in that doc was itself wrong (real value: 5.76:1, already passing) — but also found a real, different failure: `.btn--primary` / `.btn--destructive` hardcoded a white (`#FFFFFF`) label color, which against dark theme's pastel `--color-brand-primary` (`#60A5FA`) and `--color-status-error` (`#FCA5A5`) fills is only 2.54:1 / 1.90:1 — both fail AA. Fixed by introducing a `--color-on-accent` token (white in light theme, `#14171C` in dark theme) used for both button variants' label color. See `docs/quality/wcag-contrast-audit.md` for full recomputed numbers.

---

## 9. Correction: Sales Order approval/rejection realigned to DEC-012 (was entry #2's BR-001 draft)

**Date:** 2026-09-08
**Stage:** Pre-Stage-8 correction (found during a cross-check against `docs/planning/phase1-baseline.md` before running the Stage 8 QA plan)

**What was wrong:**
Entry #2 above ("no negative impact... correctly implements the business rule") judged
`SalesOrderPolicy::assertCanBeApprovedBy()`'s creator-comparison (`actorId !== createdBy`) against
`docs/planning/prd.md`'s BR-001, an earlier (2026-09-01) internal draft. `docs/planning/phase1-baseline.md`
DEC-012 — finalised later, and sourced directly from the Project Brief (SRC-001) rather than the
internal PRD — states the rule is a total ROLE denial: "Sales has no Sales Order approval capability
whatsoever — not only on own orders... creator identity is irrelevant." That reconciliation was never
carried back into the code, so the implemented rule and the frozen baseline disagreed on two points:
1. A *different* Sales user could approve another Sales user's order (only self-approval was blocked).
2. `SalesOrderService::reject()` had no authorization check of its own at all — it relied entirely on
   the controller's route-level `requireRole(Role::Admin)` guard, so a reject-only bypass around that
   route would not have been caught anywhere in the service layer.

**What was fixed:**
- `SalesOrderPolicy::assertCanBeApprovedBy(SalesOrder, actorId)` → `assertCanDecide(bool $isActorAdmin)`,
  a pure role check with no `SalesOrder` or creator id involved at all.
- `SalesOrderService::approve()` and `::reject()` both now take an explicit `bool $isActorAdmin` and
  call `assertCanDecide()` as their first statement, before any existing-order lookup.
- `SalesOrderController::approve()`/`::reject()` pass `isActorAdmin: true` (the route already gates on
  `requireRole(Role::Admin)`, so this is defense-in-depth, not a new restriction).
- Locale key `sales.approve.forbidden_self` → `sales.approve.forbidden_not_admin` (EN + ID).
- Tests updated: `tests/Unit/SalesOrderPolicyTest.php`, `tests/Integration/BR001SegregationTest.php`,
  `tests/Integration/SalesOrderApprovalPolicyTest.php` — each now also asserts the two previously-wrong
  behaviours (cross-Sales approval; Admin self-approval) go the other way.

**Impact:** Approve/reject now match `phase1-baseline.md` DEC-012 exactly. No route or URL changed. No
database migration needed (no schema field encoded the old rule). This should be verified again as
part of the Stage 8 QA pass (`docs/qa/qa-plan.md` §4).

---

## 11. Session 2026-09-15 — Gap-filling pass (C-03, C-04, C-06, UI-01, TEST-01/02/03)

**Date:** 2026-09-15
**Stage:** Pre-assessment gap-filling

### 11a. Initial class diagram moved to `docs/planning/` (C-03)

**What was wrong:**
`docs/architecture/class-diagram-initial.md` was in `docs/architecture/` but brief §9 DESIGN-01 AC1
specifies the *initial* (pre-coding) diagram belongs in `docs/planning/`. Only the as-built diagram
belongs in `docs/architecture/`.

**What was fixed:**
- Moved `docs/architecture/class-diagram-initial.md` → `docs/planning/class-diagram-initial.md`
- Updated `docs/architecture/README.md` §Diagrams & Specs table: struck through old path, added
  `../planning/` redirect note
- Updated two cross-references to the initial diagram within `docs/architecture/README.md`
- Added changelog entry `1.1 · 2026-09-15`

**Impact:** DESIGN-01 initial-diagram location now matches brief AC1. No code change.

---

### 11b. `docs/quality/critique.md` created (C-04)

**What was missing:**
Brief §9 DESIGN-04 requires `docs/quality/critique.md` — a written critique of a deliberately
flawed code snippet, naming smells, SOLID violations, and refactoring direction. The repository had
`docs/quality/architecture-critique.md` (architecture-level review) but not the specific named artifact.

**What was created:**
`docs/quality/critique.md` — self-contained document containing:
- A deliberately flawed `SalesOrderService::create()` hypothetical (6 violations in one method)
- Named smells: God Method, Superglobal access, SQL in Service, Email side-effect inside transaction,
  Duplicated query pattern
- SOLID violations: SRP (6 jobs in one method), OCP (hardcoded email channel), DIP (depends on `PDO`
  not on an interface)
- Refactoring direction: extract `SalesOrderRepositoryInterface`, `StockCheckerService`,
  `NotificationService`, and `EventDispatcher`; email dispatched post-commit via domain event

Note: brief explicitly states implementing the fix is **not required**.

---

### 11c. Concurrency tests verified — no `sleep()` calls (C-06)

**Verification:** `grep -r 'sleep(' tests/` across all test files.
**Result:** Zero `sleep()` calls found. TEST-03 FIRST compliance (no `sleep()`, no real network,
no execution-order dependency) confirmed clean.

---

### 11d. PHPUnit tests — all pass

**Verification:**
```
Unit tests:        docker compose exec app ./vendor/bin/phpunit --testsuite Unit
Result:           OK (63 tests, 157 assertions)

Integration tests: docker compose exec app ./vendor/bin/phpunit --testsuite Integration
Result:           OK (17 tests, 136 assertions)
                   (lock-wait-timeout warning in ARCH02ConcurrencyTest is expected —
                    two transactions contending on the same row; test still passes)
```
**Impact:** TEST-01 (≥6 unit cases, ≥3 logic areas) and TEST-02 (≥3 integration tests on real MySQL)
confirmed satisfied.

---

### 11e. PHPStan static analysis — zero critical errors

**Verification:**
```
docker compose exec app ./vendor/bin/phpstan analyse
Result: [OK] No errors
Level:   5 (per phpstan.neon)
Paths:   app/, public/, scripts/
```
**Impact:** TEST-03 AC1 ("zero critical errors") confirmed satisfied. The old-version warning
(1.12.x vs 2.2) is a version recommendation, not an analysis error.

---

### 11f. UI-01 responsive gap — touch targets fixed at mobile breakpoint

**What was wrong:**
AC4 requires "touch targets ≥ 44×44px at mobile breakpoint." The following elements were below threshold
on the 360px–768px viewport range:

| Element | Before | After | Requirement |
|---|---|---|---|
| `.btn` height | 32px | 44px | AC4 ≥44px |
| `.icon-btn` min-height/width | 32px | 44px | AC4 ≥44px |
| `.input` height | 40px | 44px | AC4 ≥44px |
| `.textarea` min-height | unset | 44px | AC4 ≥44px |

**What was fixed:**
Added mobile touch-target rules to the existing `@media (max-width: 768px)` block in
`public/assets/css/main.css`. Same breakpoint already used for sidebar-drawer transition — one
mobile breakpoint throughout.

**Already acceptable (per brief BR-UI-02):**
- `.table` has `min-width: 520px` with `overflow-x: auto` on `.table-wrap` — intentional horizontal
  scroll inside a wide table is explicitly permitted by brief spec.
- `.stat-card--large` spanning 2 columns correctly collapses to 1 column at 600px via existing
  `@media (max-width: 600px)` rule.
- `.app-header` padding already adjusted at `max-width: 768px`.

**Impact:** UI-01 AC1 (360px usability), AC3 (no body horizontal overflow), AC4 (44px touch targets)
all addressed.

---

### 12. Session 2026-09-15 — UI Audit Fixes

**Date:** 2026-09-15
**Stage:** Post-UI-audit (systematic scan of `public/assets/css/` + `views/`)

### 12a. P0 — Undefined CSS classes `.text--center`, `.font-mono`

**What was wrong:**
`views/inventory/_stock-ledger-rows.php` used `.text--center` (empty-state row) and `.font-mono`
(SKU display); `views/master/products/list.php` also used `.font-mono`. Neither class was defined in any
stylesheet — browsers silently ignored them, elements rendered without the intended style.

**What was fixed:**
Added to `public/assets/css/components.css`:
```css
.text--center { text-align: center; }
.text--right  { text-align: right; }
.text--muted  { color: var(--color-text-secondary); }
.font-mono    { font-family: var(--font-family-mono); }
.text--danger {
    color: var(--color-danger);
    font-weight: var(--font-weight-semibold);
}
```
Also merged a duplicate `TEXT UTILITIES` section that existed later in the same file, consolidating
all utility classes into one block.

---

### 12b. P0 — Undefined CSS variable `var(--color-text-muted)`

**What was wrong:**
Three view files referenced `var(--color-text-muted)` which does not exist in `tokens.css`. The correct
token is `var(--color-text-secondary)`.

**Files fixed:**
- `views/account/profile.php:65`
- `views/inventory/_stock-ledger-rows.php:9`
- `views/inventory/stock-ledger.php:77`

All replaced with `var(--color-text-secondary)`.

---

### 12c. P0 — Invalid `var()` fallback syntax + avatar inline styles in `profile.php`

**What was wrong:**
`views/account/profile.php` contained two issues:
1. Invalid CSS fallback syntax: `background:var(--color-primary,#2563eb)` — missing space after comma,
   which causes the fallback `#2563eb` to be parsed as part of the color value.
2. Large inline style block (9 CSS properties) on the avatar div, repeated across pages if reused.

**What was fixed:**
- Added `.avatar-initials` and `.profile-header` utility classes to `components.css`
- Replaced the avatar inline block in `profile.php` with `class="avatar-initials"`
- Replaced the profile header wrapper with `class="profile-header"`
- Fixed invalid var() fallback: removed the broken fallback entirely (the token suffices alone)

---

### 12d. P2 — Self-referential `--color-surface` duplicate in `tokens.css`

**What was wrong:**
Line 86 of `tokens.css` defined `--color-surface: var(--color-surface)` — a circular reference
that resolves to the hardcoded value on line 13 but adds no value and creates confusion.

**What was fixed:**
Deleted the self-referential line. `--color-bg-primary` (line 84) correctly references
`var(--color-surface)` which resolves to line 13's `#f8f9ff`.

---

### 12e. P2 — Duplicate `.card__title` selector in `components.css`

**What was wrong:**
`.card__title` was defined **twice** in `components.css`:
- First (lines 157–163): `font-size: var(--font-size-headline-xl)`, no `margin-bottom`
- Second (lines 232–236): `font-size: var(--font-size-h1)`, `margin: 0 0 var(--space-3) 0`

Both rendered with the second definition winning. No views actually used `.card__title` (dead class), but
the duplication was a maintenance hazard.

**What was fixed:**
Removed both definitions; replaced with a single canonical definition covering all properties:
```css
.card__title {
    font-size: var(--font-size-h1);
    line-height: var(--line-height-h1);
    font-weight: var(--font-weight-h1);
    color: var(--color-on-surface);
    margin: 0 0 var(--space-3) 0;
}
```

---

### 12f. P2 — `btn--destructive:hover` hardcoded color `#a51111`

**What was wrong:**
`components.css` used a hardcoded `#a51111` for the destructive button hover background — a darker
shade of `--color-error` (`#ba1a1a`) that breaks the token system.

**What was fixed:**
Replaced with `color-mix(in srgb, var(--color-error) 80%, black)` — dynamically darkens the semantic
error color without hardcoding, and adapts automatically if `--color-error` changes.

---

### 12g. P1 — All hardcoded hex colors tokenized (components.css + main.css)

**What was wrong:**
Approximately 30+ hardcoded hex values were scattered across `components.css` and `main.css` for
surface, border, and status colors. These bypassed the design token system entirely, meaning:
- If a token value changed, the hardcoded value would not update.
- In dark mode, hardcoded light-mode hex values would remain unchanged.

**What was fixed — `components.css`:**

| Selector | Before | After |
|---|---|---|
| `.card` background | `#ffffff` | `var(--color-surface-container-lowest)` |
| `.card` border | `#e2e8f0` | `var(--color-outline-variant)` |
| `.card__header` border | `#e2e8f0` | `var(--color-outline-variant)` |
| `.input` background | `#ffffff` | `var(--color-surface-container-lowest)` |
| `.input` border | `#c4c6d0` | `var(--color-outline)` |
| `.alert--error` | `#fef2f2 / #fca5a5 / #dc2626` | container + status tokens |
| `.alert--warning` | hardcoded | container + status tokens |
| `.alert--info` | hardcoded | container + status tokens |
| `.alert--success` | hardcoded | container + status tokens |
| `.badge--active/inactive/info/warning/success/error` | hardcoded | container + status tokens |
| `.table-wrap` | `#ffffff / #e2e8f0` | surface + outline tokens |
| `.table thead th` border | `#e2e8f0` | `var(--color-outline-variant)` |
| `.table tbody td` | `#f1f5f9 / #ffffff` | `var(--color-outline-variant)` / surface token |
| `.empty-state-card` | `#ffffff / #e2e8f0` | surface + outline tokens |

**What was fixed — `main.css`:**

| Selector | Before | After |
|---|---|---|
| `.app-sidebar__brand` border-bottom | `rgba(197,197,211,0.4)` | `var(--color-outline-variant)` |
| `.app-sidebar__footer` border-top | `rgba(197,197,211,0.4)` | `var(--color-outline-variant)` |
| `.app-header` border-bottom | `rgba(197,197,211,0.4)` | `var(--color-outline-variant)` |
| `.login-page__form-card` | `#ffffff / #e2e8f0` | surface + outline tokens |
| `.stat-card` | `#ffffff / #e2e8f0` | surface + outline tokens |
| `.live-badge__dot` | `#059669` | `var(--color-success)` |

**Impact:** All surface/border/status colors now flow through the design token system. Zero hardcoded
hex values remain in `components.css` or `main.css`.

---

### 13. Remove dark theme — light theme only (per Stitch design)

**Date:** 2026-09-15
**Stage:** Post-UI-audit

**What was removed:**

| File | Change |
|------|--------|
| `public/assets/css/tokens.css` | Deleted entire `:root[data-theme='dark']` block (100 lines); updated file header comment; `--font-weight-*` moved into `:root` block |
| `public/assets/js/theme.js` | Deleted entire file (85 lines) |
| `views/layouts/main.php` | Removed theme toggle buttons from both login header and app-header; removed `data-theme="light"` from `<html>` tag |
| `public/assets/locales/en/translation.json` | Removed `"theme"` block from `header` section |
| `public/assets/locales/id/translation.json` | Removed `"theme"` block from `header` section |

**Rationale:** Dark theme is not required by the brief and the Stitch design spec specifies light theme only. The dark theme toggle was pre-existing dead code — `theme.js` was never loaded via a `<script>` tag in the layout, so `window.toggleTheme()` was never defined and the buttons were non-functional. Dark mode tokens in `tokens.css` were identical to light mode values anyway, so there was no visual differentiation.

**Impact:** Application now uses only the light theme per Stitch design. No broken references remain — `grep 'data-theme\|toggleTheme\|dark theme' **/*.php **/*.css **/*.js` returns zero hits.

---

### 14. Final UI audit fixes (post-comprehensive-scan 2026-09-15)

**Date:** 2026-09-15
**Stage:** Final UI audit

**14a. Duplicate `.icon-btn--display` selector in `components.css`**
Defined twice: first block (lines 119–123) had `cursor: default` + `color: ...`; second block (lines 227–230) had only `cursor: default`. Kept the first (complete) definition, deleted the duplicate.

**14b. `.page-header__meta` undefined class — replaced with `.page-header__title-row`**
`views/sales/form.php` and `views/sales/detail.php` used a non-existent `.page-header__meta` wrapper. All other views use `.page-header__title-row` (the standard BEM pattern). Replaced:
- `sales/form.php`: `page-header__meta` → `page-header__title-row`
- `sales/detail.php`: `page-header__meta` → `page-header__title-row`; added a proper breadcrumb for consistency with other detail pages

**14c. `.sort-asc` / `.sort-desc` undefined CSS classes**
`stock-ledger.js` toggles these classes on `<th>` elements for sort direction indicators but no CSS defined them. Added to `components.css`:
```css
.sort-asc::after  { content: ' \2191'; } /* ↑ */
.sort-desc::after { content: ' \2193'; } /* ↓ */
```

**14d. `.so-item-remove` missing CSS hook**
`sales-orders.js` queries `.so-item-remove` for event delegation (analogous to `.po-item-remove` for PO rows). Added empty JS hook class in `components.css` next to the PO variant.

**14e. Inline `width: 200px` on `<select>` elements**
`sales/list.php` and `purchase/list.php` had `style="width: 200px;"` on filter `<select>` elements. Replaced with the existing `input--lg` CSS class (defined as `width: 200px` in `components.css`). Zero new classes needed.

**Advisory items left as-is (acceptable per brief):**
- `width: 260px` search input in `products/list.php` — no exact-width class exists; single use, acceptable inline
- `width: 120px` / `width: 240px` / `width: 56px` in table columns — structural layout values, minor advisory
- `font-size: 12px` inline in product/SKU cells — acceptable layout adjustment
- `.item-sku` / `.item-unit` — pure JS query targets, no visual style needed
- Toast hardcoded hex colors in `app.js` — toast-specific branding, self-contained system, light-theme only app

---

### 15. Critical bug: `renderPartial()` returned empty string — AJAX broken

**Date:** 2026-09-16
**Severity:** HIGH — broke stock ledger AJAX pagination and filter

**What was wrong:**
`StockLedgerController::renderPartial()` at line 79 was defined to return `string`, but the output
was echoed inside an IIFE then `return ''` was hardcoded:
```php
(function () use ($viewsPath, $view, $data) {
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewsPath . '/' . $view . '.php';
    echo ob_get_clean();   // echoed but not captured
})();
return '';                 // always returns empty string ← BUG
```

**Impact:** Every XHR response from `GET /stock-ledger` included `'tbody': ''`, so after any
filter/pagination change the table body became blank. Users saw empty table after the first AJAX interaction.

**What was fixed:**
Wrapped the IIFE's return value:
```php
return (function () use ($viewsPath, $view, $data): string {
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewsPath . '/' . $view . '.php';
    return ob_get_clean();
})();
```

**Impact:** AJAX pagination and filtering now work correctly. All 63 unit tests still pass.

---

### 16. Duplicate `.table__total` CSS selector in `components.css`

**Date:** 2026-09-16
**Severity:** MEDIUM — maintenance hazard

**What was wrong:**
`.table__total td` was defined **twice** in `components.css`:
- First (line 465): `text-align: right; font-weight: 600;` — kept
- Second (lines 615–620): identical — removed

**What was fixed:**
Deleted the duplicate block (5 lines: comment + 1 blank + 1 selector + 2 properties + 1 closing brace).
File reduced from 744 to 738 lines.

**Impact:** CSS is now DRY. No visual change.

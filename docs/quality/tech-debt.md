# Technical Debt Register

**Document:** `docs/quality/tech-debt.md`
**Project:** Inventory & Order Management System
**Date:** 2026-09-01; reviewed 2026-10-04
**Priority scale:** High (blocks release) / Medium (should fix before production) / Low (nice to have)

---

## HIGH — Fix Before Production

### TDB-001: `User::$role` — **RESOLVED (2026-09-16)**

**File:** `app/Entity/User.php`
**Status:** Resolved — `User::$role` is now the `Role` enum. `fromArray()` uses `Role::tryFrom($roleStr)` and `toArray()` handles enum→string conversion. `requireRole()` compares `$user->role` against the enum's value array. No string comparison remains.

This entry was previously open. Migration confirmed complete 2026-09-16.

---

## MEDIUM — Fix Before or Soon After Production

### TDB-002: Dark theme `--color-danger` contrast — RESOLVED, prior doc math was wrong (see below)

**File:** `public/assets/css/tokens.css`
**Status:** Resolved (Slice 6 verification, 2026-09-02) — see `wcag-contrast-audit.md` for full recomputation
**Effort:** N/A

> **Historical note (2026-09-18):** the entry below describes a dark theme and specific token values
> (`--color-brand-primary: #60A5FA`, `--color-status-error: #FCA5A5`, `--color-on-accent`) that no
> longer exist in `tokens.css`. The application now ships **one light theme only** — confirmed as a
> deliberate product decision, not a regression (see ADR-006). `tokens.css` was rewritten since this
> entry was written and now uses a different, single-theme Material Design 3–inspired palette
> (`--color-primary: #00236f`, etc.). This entry is kept as a historical record of a real bug that was
> genuinely found and fixed at the time, per this document's stated convention of documenting
> before/after rather than erasing history — it should not be read as describing current CSS.

This entry originally claimed dark surface (`#1E222A`) + old danger red (`#F87171`) was 2.45:1, failing AA. **That number was wrong.** Independently recomputing with the real WCAG 2.1 relative-luminance formula gives `#F87171` on `#1E222A` = **5.76:1**, which already passed AA — there was never a text-contrast bug here. `#FCA5A5` (applied by an earlier session) is still fine (9.46:1 / 8.40:1) so it was left in place rather than reverted.

The Slice 6 audit found a **different, real** dark-theme contrast bug in the same area: `.btn--primary` / `.btn--destructive` hardcoded `color: #FFFFFF` for the label, which on dark theme's pastel fills (`--color-brand-primary: #60A5FA`, `--color-status-error: #FCA5A5`) computed to 2.54:1 and 1.90:1 — both real AA failures. **Fixed** by adding a `--color-on-accent` token (white in light theme, `#14171C` in dark theme) and using it for both button variants' label color instead of a hardcoded white.

Also fixed in the same pass: `public/assets/css/main.css` referenced `--color-brand-primary`, `--color-brand-secondary`, `--font-weight-semibold`, `--font-weight-normal`, `--font-weight-bold`, `--shadow-sm`, `--line-height-heading`, and `--font-size-small` — none of which existed in `tokens.css` (only `--color-primary`/`--color-secondary` did, a naming mismatch against `docs/planning/ux-ui-spec.md` §1.1 which specifies `color-brand-primary`). This silently broke primary/tertiary button backgrounds, links, and focus-ring outlines (undefined CSS custom property → property falls back to its initial/inherited value, e.g. `.btn--primary`'s background rendered as transparent). All eight tokens were added to `tokens.css`.

---

### TDB-003: `Role` enum canonical usage — **RESOLVED (2026-09-16)**

**Files:** `app/Entity/Role.php`, `app/Entity/User.php`
**Status:** Resolved — `User::$role` is now the `Role` enum. `fromArray()` uses `Role::tryFrom($roleStr)`. `toArray()` handles enum→string conversion. No string comparison remains. See TDB-001.

---

### TDB-004: No transaction timeout configuration

**Files:** `app/Core/Database.php`
**Status:** Low urgency
**Effort:** 30 minutes

`Database::transaction()` has no timeout. Long-running transactions hold `FOR UPDATE` locks. MySQL `innodb_lock_wait_timeout` (default 50s) acts as safety net but is not configurable via `.env`.

**Fix:** Add `DB_LOCK_TIMEOUT` to `.env` and configure the PDO connection attribute `PDO::ATTR_TIMEOUT` (not directly supported by MySQL PDO — use `SET innodb_lock_wait_timeout`). Alternatively, add a comment documenting the default 50s timeout.

---

### TDB-013: `event_logs` table exists but is not wired to the application — **RESOLVED (2026-09-18)**

**Files:** `app/Entity/EventLog.php`, `app/Repository/Interface/EventLogRepositoryInterface.php`, `app/Repository/MySQL/EventLogMySQLRepository.php`, `app/Repository/Fake/EventLogFakeRepository.php`, `app/Service/EventLogService.php`, `app/Core/Container.php`
**Status:** Resolved — option (a) from the original "Fix" note below was implemented: explicit `$this->eventLogService->record(...)` calls, not a decorator/middleware.
**Effort:** N/A (done)

`EventLogService` is the single shared "utility" every other Service calls into (per explicit request — one home, not duplicated per Service). It is injected as an **optional, nullable** constructor dependency (`EventLogService $eventLogService = null`) into every Service with a CUD action, specifically to avoid touching the ~15 existing Unit/Integration test call sites that construct these Services directly without it — `Container` always passes a real instance in production, so logging still runs on every real request; only test doubles omit it.

**Wired into every CUD transaction:**
- `AuthService`: `login` (success), `login_failed`, `logout`
- `CategoryService` / `CustomerService` / `SupplierService` / `WarehouseService` / `ProductService` / `UserService`: create / update / activate / deactivate (actor id threaded from `Controller::currentUser()->id`, added as an optional trailing param on each method — `PurchaseOrderService::submit()`/`cancel()` and `UserService::updateProfile()` also gained/used an actor param the same way)
- `PurchaseOrderService`: create, submit, cancel
- `SalesOrderService`: create, submit, approve, reject, cancel
- `GoodsReceiptService` / `GoodsIssueService`: logged **after** `commit()`, never inside the transaction that owns the actual stock/ledger write — a logging failure can never roll back a real business transaction (`EventLogService::record()` catches every `\Throwable` internally and only `error_log()`s)

**Verified:** `vendor/bin/phpstan analyse app --level 5` → 0 errors; `vendor/bin/phpunit --testsuite Unit` → 72/72 pass (unchanged — no existing test needed modification, confirming the optional-dependency approach didn't break any construction site); `php -l` clean across every file under `app/`.

**Not done in this pass:** the Integration test suite (which needs a live MySQL + app server) was not re-run — the constructor calls there were checked by reading, not executed, and are positionally compatible (every new parameter is an optional trailing one).

---

## LOW — Nice to Have

### TDB-005: CSRF token lifetime — reassessed as informational (2026-10-04)

**Files:** `app/Core/SessionManager.php`, `app/Service/AuthService.php`, `app/Controller/BaseController.php`
**Status:** The previous stale-role claim does not apply to the current implementation.

AuthService::currentUser() reloads the user, invalidates inactive/expired sessions, and synchronizes the session role with the database value. A changed role therefore does not retain its previous permissions until logout. CSRF tokens remain stable for the session so multiple tabs/back navigation can submit safely; rotating them on every authenticated request is not a required fix. Any future rotation policy should be a deliberate session-security decision, not a remedy for the obsolete role-sync claim.

---

### TDB-006: No request rate limiting on `/api/products/{sku}/availability`

**Files:** `app/Controller/ProductApiController.php`
**Status:** Low urgency
**Effort:** 1 hour

API endpoint has no rate limiting. A malicious client could exhaust stock query capacity.

**Fix:** Implement simple IP-based rate limiting via `.env`-controlled counter in `SessionManager` or a lightweight middleware.

---

### TDB-007: `ProductService::listProducts()` doesn't accept pagination parameters

**Files:** `app/Service/ProductService.php`
**Status:** Low urgency
**Effort:** 1 hour

`ProductController` calls `$productRepo->findAll()` directly for pagination. The `ProductService` method `listProducts()` doesn't forward limit/offset. This creates a bypass: if someone calls `listProducts()` instead of going through the controller, they get all products.

**Fix:** Add `$limit` and `$offset` to `ProductService::listProducts()`.

---

## MEDIUM/HIGH — ARCH-01 deviations found by Slice 6 class-diagram-asbuilt review (both resolved 2026-09-17)

These were identified in `docs/architecture/class-diagram-asbuilt.md` (Agent A, 2026-09-01/02) as real deviations from the "Service layer owns business logic + persistence orchestration, no SQL in Controller" invariant (ARCH-01). Both entries below were fixed on 2026-09-17 and are kept here as a record rather than deleted, per the project's refactor-log convention of documenting before/after rather than erasing history.

### TDB-008: `GoodsIssueService` injects concrete `Database` and runs raw SQL directly — **RESOLVED (2026-09-17)**

**File:** `app/Service/GoodsIssueService.php`
**Status:** Resolved — constructor now type-hints `TransactionManagerInterface` (same abstraction `GoodsReceiptService` already used), verified by `vendor/bin/phpstan analyse` (0 errors) and `vendor/bin/phpunit --testsuite Unit` (64/64 pass) after the change. No raw SQL remains in the service; row locking still goes through `ProductStockRepositoryInterface::lockForUpdate()`.
**Effort:** N/A (fixed)

This entry was previously open, describing `GoodsIssueService` as the one service still coupled to the concrete `Database` class while `GoodsReceiptService` used `TransactionManagerInterface`. Both services are now consistent.

### TDB-009: `ReportController` executes raw multi-line SQL directly via `Database::pdo()` — **RESOLVED**

**File:** `app/Controller/ReportController.php`
**Status:** Resolved — `ReportController` now delegates data fetching to `getStockLedgerService()->findForExport()`, `getSalesOrderService()->findForExport()`, and `getPurchaseOrderService()->findForExport()`. Verified 2026-09-17: no `pdo(`, `->query(`, or raw `SELECT` string remains in the controller (see also `docs/architecture/class-diagram-asbuilt.md` Deviations #2/#3, resolved by commit `f59854ae`).
**Effort:** N/A (fixed)

**Fix:** Extract both raw-SQL blocks into a `ReportService`/repository method; controller should only call the service and pass the result to the view.

### TDB-010: Controllers bypass the Service layer for read paths — **RESOLVED (verified 2026-09-17)**

**Files:** `app/Controller/ProductController.php::index()`, `app/Controller/PurchaseOrderController.php::index()`, `app/Controller/ProductApiController.php::getAvailability()`
**Status:** Resolved — verified 2026-09-17 by reading all three actions: `ProductController::indexAction()` calls `getProductService()->countAll()/findAll()`, `PurchaseOrderController::indexAction()` calls `getPurchaseOrderService()->countAll()/findAll()`, `ProductApiController::getAvailabilityAction()` calls `getProductService()->getAvailability()`. No repository is called directly from any of the three. This entry was stale — the fix landed with the QueryBuilder/Service refactor in commit `f59854ae` but the register was not updated at the time.
**Effort:** N/A (fixed)

This entry was previously open, describing these three read actions as calling their repositories directly instead of going through a Service. All three are now consistent with the rest of the app.

### TDB-011: `CsvExportService` has zero repository dependencies — **informational, not blocking (re-assessed 2026-09-17)**

**File:** `app/Service/CsvExportService.php`, `app/Controller/ReportController.php`
**Status:** Not a violation — `CsvExportService` is a pure formatter by design (locale-aware headers + RFC 4180/CSV-injection escaping only). Since TDB-009 was fixed, `ReportController` no longer runs raw SQL; it fetches rows via `getStockLedgerService()->findForExport()` / `getSalesOrderService()->findForExport()` / `getPurchaseOrderService()->findForExport()` and hands them to `CsvExportService` to format. The data path is Service → Controller → formatter, with no SQL anywhere outside the Service/Repository layer, so ARCH-01 is satisfied.
**Effort:** N/A

Originally recorded on the assumption that fixing TDB-009 would make `CsvExportService` itself depend on a Service. That was optional, not required — the controller orchestrating "fetch via Service, then format" is an acceptable thin-controller pattern and does not reintroduce raw SQL. Left here as a design note only.

### TDB-012: `.input` border contrast below SC 1.4.11 (3:1) in both themes

**Files:** `public/assets/css/tokens.css` (`--color-border`), `public/assets/css/main.css` (`.input`)
**Status:** Not fixed — flagged during Slice 6 WCAG audit
**Effort:** ~1 hour (design decision + token/rule change)

`.input`'s background equals `--color-bg-primary` (same as the page), so `--color-border` is the only visual cue for the field's boundary. Computed: light `#E1E4E8` vs `#FFFFFF` = 1.28:1; dark `#2C313B` vs `#14171C` = 1.38:1 — both fail the 3:1 SC 1.4.11 threshold for UI-component boundaries. Pre-existing (not in `ux-ui-spec.md` §1.1's contrast table either), not a regression, not fixed this pass since it needs a design call (darker border vs. giving `.input` its own background/shadow) rather than a value swap.

**Fix:** Either darken `--color-border` enough to hit 3:1 against both page backgrounds, or give `.input` a distinct background (e.g. `--color-bg-surface`) so the border is a secondary cue rather than the only one.

---

### TDB-014: Duplicate order-line validation in PO/SO

**Files:** `app/Service/PurchaseOrderService.php::normalizeAndValidateItems`, `app/Service/SalesOrderService.php::normalizeAndValidateItems`
**Status:** Open; low priority after input/range validation was fixed on 2026-10-04.

Both workflows contain similar line validation and normalization. Purchase and sales price fields remain distinct domain contracts. A future consolidation should have a clear order-line responsibility; no tiny generic Result helper was added merely to reduce duplication/metrics. Current regression tests cover both workflows independently.

### TDB-015: Current release/runtime evidence pending

**Status:** Open. Current source fixes have passing standalone regressions, Unit (132 tests/404 assertions), isolated Integration (17 tests/143 assertions), and PHPStan evidence. Dedicated MySQL cancellation-race coverage, clean-clone build, role/mobile demo, VPS image-storage verification and a revision-matched Sonar run remain pending. The previously inspected active container mounted another checkout. See [current test evidence](../testing/README.md) and [reference audit](reference-gap-audit-2026-10-04.md). No passing historical result is promoted to proof of the current working tree.

### TDB-016: Assessment provenance and dependency interpretation

**Status:** Open. The original project brief is now available; its Composer wording and the stricter reference blueprint must be reconciled before altering the existing phpdotenv runtime dependency. DESIGN-04 requires the assessor-provided snippet; critique.md currently analyzes a hypothetical example. Asset source/license notices were added after geometry comparison, but the original icon import version is not documented. MinIO uses the owner's existing VPS; runtime endpoint/bucket connectivity remains pending.


## RESOLVED

### TDB-R01: `assert()` inside GoodsIssueService transaction (resolved Slice 4)

Early implementation used `assert()` to check SO existence inside the `Database::transaction()` callback. Fixed to use a proper null check throwing `InvalidStateException`.

### TDB-R02: Translation JSON duplicate `sales_orders` key (resolved Slice 5)

`translation.json` had a duplicate `sales_orders` section after a previous edit. Fixed by rewriting both EN and ID translation files completely.

### TDB-R03: Router forced all `{placeholder}` route params to digits-only (resolved Slice 5 DoD verification)

**File:** `public/index.php`

The router regex constrained every named route placeholder to `\d+`, breaking `/api/products/{sku}/availability` for any non-numeric SKU. Fixed so only `{id}` is digit-constrained; other named placeholders are passed through as strings.

### TDB-R04: `$user->role->value` used on a plain string (resolved Slice 5 DoD verification)

**Files:** `views/dashboard/index.php`, `views/reports/export-form.php`, `views/sales/detail.php`, `app/Controller/ReportController.php`

Several views/controllers accessed `$user->role->value` as if `User::$role` were the `Role` backed enum (see TDB-001 / refactor-log #1 — it is actually a plain `string`). This silently broke all dashboard KPIs for every role and broke the SO CSV export headers (fatal error on enum-only `->value` access against a string). Fixed to plain `$user->role === '...'` string comparisons.

### TDB-R05: `CsvExportService` CSV-injection guard corrupted numeric `qty` column (resolved Slice 5 DoD verification)

**File:** `app/Service/CsvExportService.php`

The CSV-injection escaping guard (prefixing values that start with `=`, `+`, `-`, `@` with a `'`) was applied to the numeric `qty` column, turning legitimate negative quantities like `-5` into the string `'-5`. Fixed with a separate `rawNumericField()` formatter that bypasses the text-escaping guard for genuinely numeric columns.

### TDB-R06: `scripts/check-low-stock.php` fatal on every run (resolved Slice 5 DoD verification)

**File:** `scripts/check-low-stock.php`

Used the invalid `%n` printf format specifier, causing a fatal `ArgumentCountError` on every invocation. Fixed to `\n`.

### TDB-R07: Missing/mismatched CSS design tokens broke primary buttons, links, and focus rings (resolved Slice 6)

**Files:** `public/assets/css/tokens.css`, `public/assets/css/main.css`

`main.css` referenced `--color-brand-primary`/`--color-brand-secondary` (the names specified in `docs/planning/ux-ui-spec.md` §1.1) plus six typography/spacing/elevation tokens (`--font-weight-semibold`, `--font-weight-normal`, `--font-weight-bold`, `--shadow-sm`, `--line-height-heading`, `--font-size-small`, `--space-5`) that were never defined in `tokens.css` (which instead defined the differently-named `--color-primary`/`--color-secondary`, and only had `--space-4`/`--space-6` in that part of the spacing scale). Undefined custom properties silently fell back to their initial value, so `.btn--primary`'s background, `.btn--tertiary`'s text color, links, the global focus-ring outline, and `.stat-card`'s padding (`padding: var(--space-5) var(--space-5)` → computed padding `0`, dashboard stat tiles rendered edge-to-edge) all silently broke. Verified there are no more such gaps by diffing every `var(--…)` reference in `main.css` against every token actually defined in `tokens.css` after the fix — zero missing. Fixed by adding all seven missing tokens (light + dark where applicable) to `tokens.css`. See also TDB-002 for the related dark-theme button-label contrast fix (`--color-on-accent`).

> **Historical note (2026-09-18):** "light + dark where applicable" above describes `tokens.css` as it
> existed at Slice 6. The file has since been rewritten to a single-theme (light-only) Material Design
> 3–inspired palette, by deliberate product decision (see ADR-006) — there is no dark-theme variant of
> any token in the current file. This entry remains accurate as a historical record of the Slice 6 fix;
> it does not describe the current token structure.

### TDB-R08: TD-14 / C-06 — integration suite could report green while skipping every test (resolved 2026-09-17)

**Files:** `phpunit.xml`

The Integration suite's 6 test files guard on MySQL/seed data/`ext-curl`/`proc_open`/app-server reachability and call `markTestSkipped()` when unavailable (15 call sites) — matching the brief's "test yang hanya lulus karena di-skip" disqualifier. Fixed by adding `failOnSkipped="true"` to the root `<phpunit>` element (no Unit test relies on skipping, verified via `grep -rn markTestSkipped tests/Unit` → 0 hits, so this only affects Integration). Verified: with no MySQL/Redis/Memcached/app-server running, `vendor/bin/phpunit --testsuite Integration` now reports `Tests: 17, Skipped: 17` **and exits 1** (previously exited 0 with "OK, but some tests were skipped!"). A CI/build step checking the exit code now correctly fails instead of treating an all-skipped run as passing.

### TDB-R09: TD-01 / C-02 — Redis outage broke login entirely; TD-02 / C-01 — session TTL 7200s vs spec 3600s (resolved 2026-09-17)

**Files:** `app/Core/SessionManager.php`, `compose.yaml`

`SessionManager::start()` set `session.save_handler=redis` unconditionally with no reachability check, so a Redis outage broke every session-dependent request with no fallback (C-02). Separately, the constructor default and `compose.yaml`'s `SESSION_LIFETIME` fallback were `7200`, against the spec's `3600` (C-01). Fixed both: `start()` now probes Redis with a 0.5s `fsockopen()` timeout before switching the save handler, falling back to file-based sessions with an `error_log()` warning if unreachable; constructor default and the Compose env fallback are now `3600`.

### TDB-R10: TD-03 / C-08 — CACHE-01 (`product:<sku>` cache-aside) was unimplemented (resolved 2026-09-17)

**Files:** `app/Service/ProductService.php`, `app/Core/Container.php`

`CacheService` was only ever used by `PermissionService`; `ProductService::findBySku()` (the lookup behind `GET /api/products/{sku}/availability`) always hit MySQL. Fixed with a cache-aside: `findBySku()` reads `product:<SKU>` from Memcached first, falling back to the repository and populating the cache (TTL 300s) on miss; every write path (`createProduct`, `updateProduct`, `updateImagePath`, `setActive`) invalidates the affected SKU key(s) after its repository write succeeds (post-commit invalidation, matching `PermissionService`'s existing cache-aside pattern). Stock is deliberately **not** cached — `getAvailability()` and `getStockBreakdownByProduct()` still read `ProductStockRepositoryInterface` live on every call, per §23.5/INV. Verified: `vendor/bin/phpstan analyse app --level 5` → no errors; `vendor/bin/phpunit --testsuite Unit` → 72/72 pass.

### TDB-R11: Stray fatal syntax error in `ProductStockMySQLRepository.php` (resolved 2026-09-17)

**File:** `app/Repository/MySQL/ProductStockMySQLRepository.php`

Found while verifying the CACHE-01 fix: a duplicated `return $result; } }` block was left dangling after the class's closing brace (leftover from an earlier uncommitted edit), causing a fatal parse error on this file — `vendor/bin/phpstan analyse` couldn't even start, and any request touching stock would fatal. Fixed by removing the duplicated dead code after the class closes. Verified with `php -l` on every file under `app/` (all clean) and a full PHPStan level 5 pass (0 errors).

### TDB-R12: TD-12 / C-09 — PHP pinned at 8.2 instead of the fixed 8.3.20 (resolved 2026-09-18)

**Files:** `Dockerfile`, `composer.json`

`Dockerfile` was `FROM php:8.2-cli` and `composer.json` was `"php": ">=8.2"`, against the project's rank-2 decision to fix PHP at 8.3.20 (brief itself only requires "8.2+", so this was a spec/impl mismatch rather than a brief violation). Fixed: `Dockerfile` is now `FROM php:8.3.20-cli` (tag confirmed to exist on Docker Hub) and `composer.json` is now `"php": "^8.3"`. Verified: PHPStan level 5 clean and Unit suite 72/72 pass against the local PHP 8.3.13 CLI. **Not yet verified against an actual Docker build** — the Docker daemon was unavailable in the session that made this change, so the image has not been rebuilt/run with the new base tag.

### TDB-R13: Deterministic stock lock ordering (source verified 2026-10-04)

**Files:** `app/Service/GoodsIssueService.php`, `app/Service/GoodsReceiptService.php`, `tests/Unit/GoodsIssueServiceLockOrderTest.php`, `tests/Unit/GoodsReceiptServiceLockOrderTest.php`

Both goods workflows sort lines by ascending productId before stock locking. New public-workflow regressions use real reversed fake-repository fixtures `[5,3]`, assert locks `[3,5]`, and verify per-product stock and ledger quantities. They pass in the standalone runner and Unit suite. Existing isolated Integration concurrency tests also pass, but do not specifically prove a two-product overlapping lock-order schedule or the new cancellation race. This addresses inconsistent stock lock ordering, not a guarantee that every possible database deadlock is eliminated. This entry follows R12 (2026-09-18) chronologically.

### TDB-R14: Confirmed reference audit code/documentation gaps (source verified 2026-10-04)

Conditional status updates now reject stale transitions; raw integer/date/price validation rejects malformed and schema-out-of-range values; PHPStan assignment failures are fixed. Draft SO editing, product sort, display-number search, API failure classification, shared inventory valuation and decimal presentation have been implemented. Unit session/cache boundaries are isolated, integration DB selection is explicit, API/as-built/testing documentation matches current source, and asset notices are distributed. See the [remediation audit](reference-gap-audit-2026-10-04.md) for individual findings and limits. Outstanding runtime/provenance decisions remain open in TDB-015/TDB-016.

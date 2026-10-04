# IOMS Playwright E2E — Foundation Contract

**Version:** 1.0.0 (frozen at Gate G1 — Wave 1 completion)
**Owner:** Agent 1 (Foundation), integrated by Agent 0 (Coordinator)
**Authority:** This contract is binding on every domain agent (Wave 2: Agents 2–7) and the audit agent (Wave 4: Agent 8). It is the operational companion to `docs/testing/e2e-playwright/IOMS-E2E-SPEC.md` (the architectural design document) — when they appear to disagree, the design spec's intent wins and this file is corrected to match, never the other way round.

A domain agent that needs something not in this contract **must not invent it**. File a `requests/SHARED_CHANGE_REQUEST-<agent>-<n>.md` (template in §8 of the design spec) instead.

---

## 1. Environment variables (`support/env.ts`)

| Variable | Values | Default | Notes |
|---|---|---|---|
| `E2E_MODE` | `isolated` \| `existing` | **none — required** | No default, by design. Missing/invalid throws `EnvConfigError`. |
| `E2E_BASE_URL` | URL | `http://127.0.0.1:18090` (isolated, fixed) | Only settable in `existing` mode; isolated mode rejects any other value, and explicitly rejects port `8090` (the developer stack's port). |
| `E2E_RESET` | `always` \| `fast` \| `never` | `always` (isolated) / `never` (existing, forced) | `existing` + anything but `never` throws. |
| `E2E_KEEP` | `0`/`1`/`true`/`false` | `false` | `1` leaves the isolated stack running after the run (skips `docker compose stop` in `globalTeardown`). |
| `E2E_WORKERS` | positive integer | `4` (isolated) / `2` (existing) | Also the number of per-role storageState files `setup/auth.setup.ts` provisions — see `effectiveWorkers()`. |
| `E2E_CHROMIUM_PATH` | path | unset | Overrides the Chromium binary Playwright launches (sandbox/CI path mismatches). |
| `E2E_I_UNDERSTAND_NO_DETERMINISM` | `1` | — | Required to run `E2E_MODE=existing` at all. |
| `E2E_DEBUG_ARTIFACTS` | bool | `false` | Reserved for future use by diagnostics; not yet consumed. |
| `E2E_SHORT_SESSION_URL` | URL | `http://127.0.0.1:18091` | The `app-shortsession` profile's base URL (idle-session-expiry spec). |

Read these **only** through `support/env.ts::loadEnv()`. Never read `process.env.E2E_*` anywhere else.

## 2. Roles & credentials (`support/roles.ts`)

`Role = 'admin' | 'sales' | 'warehouse' | 'sales2'`. Credentials and seed display names are in `CREDENTIALS` — the only place they're written down (seed.sql:14-19). Do not hardcode an email/password anywhere else.

## 3. Fixtures (`support/fixtures.ts`) — the ONLY place `test`/`expect` are imported from `@playwright/test`

| Fixture | Type | Scope |
|---|---|---|
| `adminPage` / `salesPage` / `warehousePage` / `sales2Page` | `Page` | per-test, backed by that role's **per-worker** storageState |
| `adminRequest` / `salesRequest` / `warehouseRequest` / `sales2Request` | `APIRequestContext` | same storageState, no browser |
| `guestPage` / `guestRequest` | unauthenticated | fresh context, no state |
| `loginAs(role \| {email,password})` | `() => Promise<LoginSession>` | creates an **independent** session — use for logout/session-regeneration/deactivation-mid-session tests; **never** call `.logout()` on a role/worker-bound fixture session |
| `uid` | `Uid` | fresh per test, worker-scoped (`support/unique.ts`) |
| `factories` | `Factories` | bound to `adminRequest`, worker-scoped uid generation (`factories/index.ts`) |
| `csrf` / `ids` / `http` / `db` | helper namespaces | see §6 |
| `diagnostics` | auto-attached | console/pageerror/failed-request capture + PHP-leak guard (`support/diagnostics.ts`) |
| `env` | `E2eEnv` | the loaded, validated env contract |

Exceptions allowed to import `test`/`expect` directly from `@playwright/test`: `setup/auth.setup.ts` (runs before any fixture exists) and `specs/_smoke/guards.spec.ts` (runs before any role session exists, in the dedicated `guards` project). Enforced by `eslint.config.js`.

**Per-worker sessions, mapped exactly:** `setup/auth.setup.ts` loops every role × every worker slot `0..effectiveWorkers(env)-1` and writes `.auth/<role>.w<n>.json`. `support/fixtures.ts`'s role fixtures read `testInfo.parallelIndex` (Playwright's real, stable 0-based worker index for the life of the run) to pick the matching file. `playwright.config.ts` and `auth.setup.ts` both call the same `effectiveWorkers()` so the file count and the real worker count can never disagree. The `setup` project itself always runs on exactly 1 worker (it provisions files for ALL slots sequentially, inside one test — it does not rely on its own `parallelIndex`).
**Cleanup lifecycle:** `.auth/*.json` files are gitignored and regenerated every run (`E2E_RESET=always` recreates the stack and therefore the sessions too; `E2E_RESET=never/fast` reuses the stack but `setup` still re-logs-in and overwrites the files each run, so stale sessions are never silently reused across runs).

## 4. Unique-data helper (`support/unique.ts`) — the ONLY naming source

`createUid(workerIndex)` → `Uid` with `.name(kind, maxLen?)`, `.sku()` (≤30, uppercase — see KNOWN_DEFECTS.md DEF-01 on why 30 not 50), `.categoryCode()` (`[A-Z0-9-]{3,20}`), `.warehouseCode()` (≤20), `.email(prefix?)`, `.phone()`, `.text(len)`, `.reason()`. Token shape: `${workerIndex}-${base36 timestamp}-${4-char random}`. **Call `createUid()` fresh per entity created**, never bind one `Uid` instance across multiple `.create()` calls (a shared instance returns the same name every time — see the comment at the top of `factories/index.ts`).

Dates: `support/dates.ts` — `utcToday()`, `todayYmd()`, `addDays()`, `ymdDaysAgo()`, `lastNDaysRange()`. Server and MySQL both run UTC with no explicit timezone override (confirmed live: `date()`/`CURDATE()` in the running containers). **Exception:** `SalesDashboardService` anchors `today/week/month` periods to `Asia/Jakarta`, not UTC — see KNOWN_DEFECTS.md note under Agent 7/Agent 6 scope before writing any period-boundary assertion.

## 5. Factory API (`factories/*.ts`)

All factories create through the **real HTTP+CSRF path** (never a DB insert shortcut), so creation also exercises real validation. Every `.create()` generates a **fresh** uid internally — safe to call repeatedly in one test.

| Factory | Token discovery | Notes |
|---|---|---|
| `categories.create()` | JSON response (`category.id`, already encoded) | **Always pass an explicit `code`** (uid.categoryCode()) via the uid default — never omit it: `CategoryService::generateUniqueCode()` races under parallel workers (KNOWN_DEFECTS.md DEF-03) |
| `products.create()` | redirect is to the LIST, not detail — resolved via `POST /products/search?sku=` | auto-creates a throwaway category if none given; resolves its real numeric `category_id` by scraping `/products/create`'s `<select>` |
| `warehouses.create()` / `suppliers.create()` / `customers.create()` / `users.create()` | same "redirects to list" pattern — resolved via `/{base}/search` | |
| `purchaseOrders.create/submit/cancel/receive` | `create()` redirects to detail (token in `Location`) | `submit`/`cancel` are **Admin-only** (`purchase_orders.submit`/`.cancel`) — pass `adminRequest` even if a different role created/receives the PO. `receive()` needs `purchase_orders.manage` (Admin or Warehouse). |
| `salesOrders.create/submit/approve/reject/issue/cancel` | `create()` redirects to detail | `create()`/`submit()`/`cancel()` take an explicit `creatorRequest`/`actorRequest` — this factory never assumes who's acting. `approve()`/`reject()` are **Admin-only** (role-based SOD — Admin MAY approve their own order; Sales may approve none — see KNOWN_DEFECTS.md SPEC-01). `issue()` needs Admin or Warehouse. |
| `stock.seed({...})` | runs a real PO create→submit→receive cycle | never a shortcut — "test-owned stock" means stock that arrived through the same invariant-checked path as everything else |
| `world()` | — | isolated mini-aggregate: own warehouse/category/product/supplier/customer, with numeric ids pre-resolved. The standard starting point for any workflow/concurrency spec. |

**Resolving a real numeric id from a token:** there is no HTTP-only way to do this (that's the point of `IdObfuscator`). `factories/_common.ts::resolveSelectValueByText()` scrapes the option whose **visible text** matches the unique name/code just created out of the relevant create-form's own `<select>` (Products/PO forms render these server-side). `resolveSoProductId()` does the same against the Sales Order form's `window._soProductList` JSON blob instead, because that form's product list is **client-rendered, not a static `<select>`** (confirmed live in `views/sales/form.php`).

## 6. Helper namespaces

- **`support/csrf.ts`**: `csrf.fromPage(page)`, `csrf.fromRequest(request, path)`, `csrf.invalid()`, `csrf.staleFromAnonymousLogin(request)`.
- **`support/request.ts`**: `post(request, path, form, {csrf, csrfSourcePath, headers})` / `get(...)`. **Array-valued form fields** (`{item_product_id: ['1','2']}`) are encoded with a literal `[]` suffix on the wire (`item_product_id[]=1&item_product_id[]=2`) — PHP's `$_POST` array convention requires this; sending the bare key twice makes PHP keep only the last value. Body is sent as a hand-built `application/x-www-form-urlencoded` string, not Playwright's `form:` option (which has no array support at all).
- **`support/ids.ts`**: `ids.tokenFromPath/tokenFromHref/tokenFromRedirect`, `ids.tamper/truncate/nonHex/raw`.
- **`support/csv.ts`**: `downloadCsv(request, path, form, {csrfSourcePath})`, `parseCsv`, `expectInjectionGuarded`.
- **`support/files.ts`**: `UPLOAD_FIXTURES` (see `fixtures/files/`), `ensureGeneratedFixtures()`.
- **`support/db.ts`** (Mode A only — throws `ModeBUnavailable` in Mode B): `query()` (read-only SELECT/SHOW/EXPLAIN only), `stockOf`, `ledgerSum`, `assertInvariants()`, `runLowStockJob()`.
- **`support/messages.ts`**: `MESSAGES.*` — every contract-level string, copied verbatim from source. `CSV_HEADERS`, `CSV_FILENAMES`, `UNICODE_MINUS` (the ledger's negative-quantity sign is U+2212, **not** ASCII hyphen — confirmed in `views/inventory/_stock-ledger-rows.php`).

## 7. Tags (`support/tags.ts`)

`@smoke @crud @validation @rbac @workflow @security @a11y @concurrency @export @upload @regression` + modifiers `@needs-isolated @seed-dependent @quirk @slow`. Apply via `test(title, {tag:[...]}, fn)`. No other tag strings are permitted — Agent 8's audit lints this.

## 8. Selector conventions

Priority: `getByRole` → `getByLabel` → `getByTestId` → stable `#id` → business-key attribute → `getByText`. The app already has substantial semantic markup (custom confirm dialog is `role="alertdialog"` with `#__confirmOk`/`#__confirmCancel`; toast container is `aria-live`; most forms have stable `#id`s; Categories list rows carry real `data-id`/`data-name`/`data-code` business-key attributes already). **Known gaps requiring a `SELECTOR_REQUEST`** (provisional — confirm against the live app before filing): KPI/stat cards are anonymous `.stat-card` divs distinguished only by label text; most list-page table rows (Products/Warehouses/Customers/Suppliers/Users/PO/SO) carry no row-identity attribute at all — PO/SO rows are the exception, identifiable by the plain-text `#<id>` the page itself renders. See `docs/testing/e2e-playwright/IOMS-E2E-SPEC.md` §13 for the full provisional table.

## 9. Parallelism rules

Parallel by default. Serial **only** inside one `test.describe.configure({mode:'serial'})` per aggregate (PO lifecycle, SO lifecycle, a single user's deactivation test). The `exclusive` Playwright project (single worker, runs after `parallel`) is for genuinely global mutable state only: notifications (`read_at` has no per-user column — confirmed in schema), exact dashboard/report KPI snapshots. Concurrency tests (`@concurrency`) always build their own `world()` — never share seeded or another test's aggregate.

## 10. Diagnostics

Auto-attached per test (`support/diagnostics.ts`): console errors, `pageerror`, failed requests, any response ≥400, and a PHP-leak regex scan of ≥500 HTML/text response bodies that **fails the test** unless it called `diagnostics.expect500()`. Artifacts (trace/screenshot/video) are `retain-on-failure`/`only-on-failure` — nothing extra on a passing test. `globalTeardown` additionally scans the whole `app` container log for the same PHP-leak pattern (Mode A only) and fails the run if found.

## 11. Shared-file modification process

`playwright.config.ts`, `global/*`, `setup/*`, `support/*`, `factories/*`, `components/*`, `env/*`, `fixtures/*`, `package.json`, this contract — **Agent 1 only** (Agent 0 approves). A domain agent that needs a new shared helper files `requests/SHARED_CHANGE_REQUEST-<agent>-<n>.md` and continues with a local stub under its own `pages/<domain>/_local-*.ts` until it lands centrally.

---

## Contract version log

- **1.0.0** (Gate G1): initial freeze. Covers env/roles/fixtures/uid/factories(core 9)/helpers(csrf,request,ids,csv,files,db,messages)/tags/diagnostics. `components/*` (shared ConfirmDialog/Toast/MultiSelect/Pagination/DataTable wrappers) are **not yet implemented** — Phase 2 per the design spec's phased plan, not required for the G1 gate ("a safe smoke login test can run"); domain agents needing one before Agent 1 delivers it should file a `SHARED_CHANGE_REQUEST` rather than build a competing one.

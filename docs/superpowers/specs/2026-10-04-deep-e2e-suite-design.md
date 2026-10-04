# Deep E2E Test Suite — Design Spec

- **Date:** 2026-10-04
- **Author:** Dian Rezky Wulandari (with Claude)
- **Status:** Draft — awaiting review
- **Scope:** Replace the ad-hoc Node Playwright scripts with a comprehensive, maximum-depth
  `@playwright/test` suite covering **every menu** of the Inventory & Order Management System,
  down to field-level validation, edge/empty/error states, every interactive UI element,
  role-based authorization, and accessibility.

---

## 1. Goals

1. **Deep coverage of all menus.** Auth, Dashboard (3 role variants), Products, Categories,
   Warehouses, Customers, Suppliers, Users, Purchase Orders, Sales Orders, Stock Ledger,
   Reports, Profile, Notifications.
2. **Four depth dimensions per menu** (explicitly requested):
   - Field-level validation (required/optional, min/max length, format, uniqueness, exact
     server error messages).
   - Edge & empty/error states (empty list, no-results, pagination bounds, 403/404, CSRF
     expiry, double-submit).
   - Every interactive UI element (buttons, links, row-action menus, modals, confirm dialogs,
     toasts, badges, disabled states, tabs, multi-select).
   - Accessibility & keyboard (axe scan + tab-order / keyboard operation).
3. **Preserve business-critical invariants** already covered by the old scripts: SOD approval,
   overselling prevention, goods receipt (partial/full + over-receipt guard), concurrency,
   stock-ledger entries.
4. **Deterministic, repeatable runs** via a clean DB per run.
5. **Maintainable & CI-ready**: `@playwright/test` with HTML report, retries, trace/video on
   failure, storageState auth, Page Object Models.

### Non-goals
- No load/performance testing. No visual-regression snapshots (beyond what axe/layout needs).
- Not rewriting the app's behavior — only adding `data-testid` hooks (see §4).
- The old `tests/e2e/` scripts stay in place as reference; deprecated later, not deleted now.

---

## 2. Key facts the design is built on (from codebase exploration)

- **Base URL:** `http://127.0.0.1:8090` → container `:8080` (docker-compose `app` service,
  `APP_PORT` default 8090).
- **Seeded roles** (`database/seed.sql`): Admin `admin@example.com/admin123`,
  Sales `sales1@example.com/sales123`, WarehouseStaff `warehouse@example.com/wh123`,
  Sales2 `sales2@example.com/grace123`.
- **Clean-start data:** 4 categories, 2 warehouses (WH-JKT, WH-BDG), 10 suppliers,
  10 customers, 100 products (stock 50 @ WH-JKT / 30 @ WH-BDG each), 50 POs
  (10 Draft/12 Ordered/8 PartiallyReceived/15 Received/5 Cancelled), 50 SOs
  (8 Draft/12 PendingApproval/12 Approved/10 Fulfilled/8 Cancelled).
- **Auth:** session cookie `iom_session` (Redis-backed). CSRF token `_csrf_token` — submitted
  automatically by browser form posts; must be scraped for request-context posts. Login
  regenerates session + token.
- **Routing/authorization:** per-action guards in controllers (not middleware). Filters/search/
  sort/pagination are **POST-only** (`POST /{resource}/search`); a GET with a query string
  renders the unfiltered page. IDs in URLs are **obfuscated tokens**, never raw integers —
  tests must capture encoded tokens from rendered links.
- **Selectors:** app has **zero `data-testid`** today. Stable hooks that DO exist: form/field
  `#id`s (`#sku`, `#po-form`, `#category-filter-form`, …), BEM classes (`.stat-card`,
  `.badge--*`, `.row-actions__*`, `.empty-state*`, `.alert--error/success`), ARIA roles, and
  `body[data-page]`. Toasts are `#app-alert-container > div[role=alert]`; confirm dialogs
  `div[role=alertdialog]` with `#__confirmOk`/`#__confirmCancel`; report modal `#__reportOk`.
- **Validation messages** are literal strings produced at the service layer (no registry) — the
  suite asserts against the exact text (see §7 matrix). Full list captured in the route/validation
  inventory.
- **Error→status:** validation failure → HTTP 400; internal error → 500; unauthorized action →
  403; bad/obfuscated id → 404.

---

## 3. Decisions (approved by user 2026-10-04)

| Topic | Decision | Consequence |
|---|---|---|
| Framework | Migrate to `@playwright/test` | New suite under `tests/e2e-playwright/` |
| Selectors | **Add `data-testid` to views** | Touches production `views/**` (§4) |
| DB reset | **Full reset each run** (`docker compose down -v` + up) in global setup | Destructive to local DB each run — HUMAN-OWNED (§5) |
| Execution | **All menus at once** | One implementation plan covering every menu (§7) |

### ⚠️ Governance flags (CLAUDE.md STOP conditions)
- **Destructive DB behavior:** global setup wipes the `iom_db_data` volume every run. Running DB/
  volume operations is HUMAN-OWNED. The suite will *contain* the reset step but it must be gated
  by an explicit opt-in env flag (e.g. `E2E_RESET_DB=1`) so a stray `npx playwright test` cannot
  nuke a dev DB silently.
- **App-code changes:** adding `data-testid` is production-template editing under the AGENT.md
  workflow. Changes are inert (attribute-only, no behavior) → `lightweight` risk tier, but there
  are many files. Each view edit is attribute-additive only.

---

## 4. Selector strategy — `data-testid` plan

Add `data-testid` attributes (inert, additive) to views where stable selection is otherwise
fragile. Convention: `data-testid="<menu>-<element>[-<qualifier>]"`.

- **List rows:** `tr[data-testid="row"]` + `data-row-key="<sku|code|email|order_number>"` so a row
  is locatable by business key (rows currently have no id).
- **Row actions:** `data-testid="row-action-view|edit|activate|deactivate|delete"`.
- **Primary buttons:** `data-testid="btn-add|btn-save|btn-submit|btn-search|btn-reset|btn-export"`.
- **Flash/toast/modal:** reuse existing `role` + add `data-testid` on toast container and confirm
  buttons where ids are dynamic.
- **Field errors:** per-field `data-testid="error-<field>"` on the `.form-field__error` element.

Where a stable `#id` or unambiguous role already exists (forms, named fields), **use it as-is** —
`data-testid` is added only to close genuine gaps. A short PR-style list of touched view files
will accompany implementation. Primary locator priority in tests:
`getByTestId` → `getByRole` → `#id` → `getByText` (last resort).

---

## 5. Infrastructure

### 5.1 Directory layout
```
tests/e2e-playwright/
  package.json                # @playwright/test, @axe-core/playwright
  playwright.config.ts
  global-setup.ts             # (opt-in) DB reset + wait-for-healthy; builds storageState per role
  global-teardown.ts
  .auth/                      # storageState json per role (gitignored)
  fixtures/
    roles.ts                  # role → storageState mapping, test.use projects
    app.ts                    # custom fixtures (loginAs, csrf helper, uniqueName)
  support/
    seed.ts                   # seed constants (counts, known SKUs/codes/emails)
    locators.ts               # shared testid/role helpers
    pom/
      ListPage.ts FormPage.ts DetailPage.ts   # generic base POMs
      ProductsPage.ts CategoriesPage.ts ...    # per-menu POMs
  specs/
    auth/ dashboard/ products/ categories/ warehouses/ customers/
    suppliers/ users/ purchase-orders/ sales-orders/ stock-ledger/
    reports/ profile/ notifications/ a11y/
```

### 5.2 `playwright.config.ts`
- `baseURL: http://127.0.0.1:8090`, `testDir: ./specs`.
- Projects: `setup` (auth), then `admin`/`sales`/`warehouse`/`sales2` each with their
  storageState; a `guest` project with no state for unauth tests.
- `reporter: [['html'], ['list']]`, `trace: 'on-first-retry'`, `video/screenshot:
  'retain-on-failure'`, `retries: process.env.CI ? 2 : 0`.
- `webServer`: **not** used for the app (docker owns it); global-setup waits on
  `/login` health.

### 5.3 DB reset & auth (global-setup)
1. If `E2E_RESET_DB=1`: `docker compose down -v` → `docker compose up -d --build` → poll
   `http://127.0.0.1:8090/login` until 200 (timeout ~3 min for first boot / composer install).
   Otherwise assume app already running and seeded.
2. UI-login each role once, save `.auth/<role>.json` via `storageState`.
3. Fast alternative (documented, not default): re-pipe `schema.sql`+`seed.sql` into the running
   `db` container on host port 3307.

### 5.4 Data hygiene
Even with per-run reset, tests **create uniquely-named fixtures** (`E2E-<spec>-<timestamp>`) and
self-clean, so individual specs are order-independent and re-runnable without a full reset.
Soft-deleted master data is reactivated in cleanup; categories created for delete-tests are hard-
deleted.

---

## 6. Cross-cutting test modules (apply to every menu)

1. **Auth gate:** unauthenticated GET → redirect `/login`; unauthenticated POST → rejected.
2. **RBAC matrix:** for each role, assert allowed pages 200 and forbidden pages 403 — both the
   menu link visibility (UI) AND direct navigation / direct POST (server-side), per BR-017.
3. **CSRF:** a POST with missing/stale `_csrf_token` → 400 "Your session has expired…".
4. **No-query-string invariant:** every list form is `method=POST`; no link carries `?query`;
   hand-typed `?q=…&page=` is ignored server-side.
5. **Obfuscated id:** tampered/garbage id token → 404; hrefs never expose numeric ids.
6. **Accessibility:** `@axe-core/playwright` scan (no serious/critical violations) + keyboard
   tab-order and Enter/Escape operation on forms, modals, row-action menus.
7. **Flash/toast/modal behavior:** success toast after mutation; confirm dialog focus starts on
   Cancel; Escape closes; report modal on server error.

---

## 7. Per-menu test matrix (summary)

Each menu gets: **List** (filters each field, search, sort, pagination bounds, empty/no-results,
per-page), **Create** (happy + every field validation), **Edit** (happy + validation + obfuscated
id in action), **Detail**, **Activate/Deactivate round-trip** (or Delete for Categories),
**RBAC**, **a11y**. Field validations assert the exact server message.

- **Auth/Login:** valid login per role → `/dashboard`; invalid email/password/inactive → single
  generic message; required fields; already-authed redirect; logout; demo-fill buttons; session
  regeneration.
- **Dashboard (deep, was shallow):** per role — KPI card counts & values, quick actions
  visibility (Add User admin-only), low-stock table + "Create PO" link, activity feed, recent
  transactions, warehouse issue/receipt queues, "Generate Summary Report" link resolves, refresh
  button, notifications block (Admin/WH only), empty states.
- **Products (new — no spec today):** filters (sku, name, category multi-select, stock_status,
  warehouse multi-select), KPI grid, stock-status badges, CRUD, all field rules incl.
  `sale_price ≥ purchase_price`, reorder_point ≥ 0, SKU uppercase/unique/≤30, name 3–150,
  description ≤500, **image upload** (type by magic-byte, 2 MB max, wrong-type rejection),
  initial stock on create, activate/deactivate (default list hides inactive), `/api/products/{sku}/
  availability` (200/401/404 JSON).
- **Categories:** modal create/edit, code auto-gen vs custom (`^[A-Z0-9-]{3,20}$`), name 3–80
  unique, description ≤250, status, **hard delete + delete-guard** (disabled when SKUs>0, direct
  POST 422), SKU-count cross-link to Products, **CSV export** + formula-injection escaping.
- **Warehouses:** code required/unique/uppercase, name required, location optional; detail page
  stock-by-product pagination; activate/deactivate.
- **Customers:** name required, email optional+valid; `customers.view` gating (WarehouseStaff 403);
  CRUD; obfuscated-id edit action.
- **Suppliers:** same shape as customers; integration — deactivated supplier disappears from PO
  create dropdown.
- **Users:** name/email/role/password rules (password ≥6, required on create / optional on edit),
  email unique, **self-role-change guard**, **self-deactivate guard**, **AUTH-01 session
  invalidation** (deactivated mid-session → next request bounces to login).
- **Purchase Orders:** create (supplier/warehouse/date/≥1 line, qty>0, price≥0), submit
  (Admin-only), cancel (Admin-only; state guards), **goods receipt** partial→full,
  **over-receipt guard** (max attr + direct POST), **stock-ledger Receipt entry**, cancel-guard on
  received PO, **concurrency** (two partial receipts never exceed ordered).
- **Sales Orders:** create (customer/warehouse/date/≥1 line), submit (creator-only), **SOD-01**
  (Sales cannot approve own order — UI absent + direct POST 403), Admin approve/reject (reason),
  **goods issue** → Fulfilled + **stock-ledger Issue entry (negative qty)**, **oversell
  prevention** (huge qty → rejected, SO stays Approved, no partial state), **BR-018 cross-Sales
  isolation** (Grace cannot see/act on Beni's SO).
- **Stock Ledger:** RBAC (Sales 403), filter (sku/name/type multi-select/warehouse), AJAX
  sort/pagination, audit fields (before→after arithmetic, Done By), qty sign per movement type,
  CSV export (date-range required 400, warehouse scoping, Sales 403), reference-link regression.
- **Reports:** 4 report types render distinct tables, date-range required, warehouse/category
  filters scope rows, KPI cards, trend/category/warehouse tabs, **stock-ledger CSV**
  (Admin/WH 200, Sales 403), **orders CSV** (Admin all, Sales own SO only, WH 403),
  injection escaping, empty states per type (no 500).
- **Profile:** name 3–100 required, email valid/unique (excl self), role field disabled, success/
  error alerts, cannot change password here.
- **Notifications:** header bell dropdown + dashboard block (Admin/WH only), unread badge,
  mark-all-read (POST+CSRF), empty state, Sales 403 on mark-all-read.

---

## 8. Risks & mitigations

| Risk | Mitigation |
|---|---|
| `down -v` wipes real dev data | Opt-in `E2E_RESET_DB=1`; document loudly; default = assume running |
| First boot slow (composer install) | Longer health-poll timeout; reuse volume when not resetting |
| Obfuscated ids break hardcoded URLs | Always capture tokens from rendered hrefs, never construct |
| `data-testid` churn in views | Attribute-only edits, reviewed as one batch; no behavior change |
| Flaky toasts (transient, inline-styled) | Assert via `role=alert` + text with proper waits |
| SO line-item names generated in JS | Confirm exact `name`s from `sales-orders.js` during impl |
| Concurrency tests racy | Reuse proven two-context pattern from old scripts |

---

## 9. Open questions for review

1. OK to add `data-testid` across `views/**` as attribute-only edits? (approved in principle)
2. Confirm `E2E_RESET_DB` opt-in gate is acceptable instead of always-reset.
3. Should the suite be wired into CI now (no `.github/` exists yet) or left runnable locally only?
4. Keep old `tests/e2e/` until the new suite reaches parity, then delete in a follow-up?

---

## 10. Next step
On approval of this spec, proceed to the `writing-plans` skill to produce a detailed, phased
implementation plan (foundation → cross-cutting modules → per-menu specs → a11y → CI wiring).

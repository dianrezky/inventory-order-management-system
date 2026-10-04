# IOMS — Deep E2E Playwright: Final Design Specification & Implementation Plan

**Status:** PLANNING ONLY — nothing has been implemented. No repository file was modified. Awaiting explicit approval.
**Scope:** `inventory-order-management-system/` (PHP 8.3 native app inside the `portfolio-apps` monorepo).
**Evidence legend:** **[R]** = I read the source file myself. **[L]** = taken from the legacy-suite audit (secondary, to be re-confirmed by the owning agent in its first task). **[C]** = still to be confirmed (listed in §A0.1).

> ## ⚠ Read this first — the "existing draft design specification" was not found
> You asked me to validate an existing draft spec. **No such document exists in the repository, on the branch, or in the message you sent** (I searched `*.md`, `docs/`, all branches, stashes, and grepped for "playwright/E2E design/down -v"). The only E2E material in the repo is the legacy `tests/e2e/*.spec.js` scripts. I therefore did **not** invent a draft. Instead:
> * §2 "Corrections" lists every draft assumption **implied by your brief** (e.g. `docker compose down -v` on the standard project, one project per role, `globalSetup` doing all logins, timestamp-only names, `GET /login == 200` as readiness, 401 expectations) and states what the repository actually requires.
> * If a real draft exists, send it and I will diff it against this document. Nothing here depends on it.
> * `inventory-order-management-system.rar` (20 MB, repo root) could not be opened (no unrar tool). If the draft is inside it, tell me.

---

# 1. FINAL REPOSITORY FINDINGS

## 1.1 What the system is
| Fact | Evidence |
|---|---|
| Monorepo `portfolio-apps`; IOMS lives in `inventory-order-management-system/`. Everything else (apps/, packages/, functions/, render.yaml, root CI) is **unrelated**. `run-e2e-2-5.ps1` at repo root is for a different product (Xyra Code) — ignore. | [R] tree, [L] |
| PHP 8.3 native, Controller→Service→Repository, **server-rendered** PHP views + vanilla JS + Fetch. Router is a custom matcher in `public/index.php`; `php -S` with `PHP_CLI_SERVER_WORKERS` (default 4). | [R] `public/index.php`, `Dockerfile`, `docker-compose.yaml` |
| 5 compose services: `app`, `cron` (low-stock job every 15 min), `db` (MySQL 8.0), `redis` (sessions), `memcached` (permissions cache + product-by-SKU cache). Product images go to an **external** MinIO (`portfolio-minio`, shared with the monorepo). | [R] compose |
| Compose file is `docker-compose.yaml` (README says `compose.yaml` — stale). Fixed `container_name`s (`iom_app`, `iom_db`, …), fixed host ports (**app 8090, db 3307, memcached 11211**), named volumes `iom_db_data`, `iom_redis_data`. | [R] |
| Source is bind-mounted `.:/var/www/html` into `app` (dev `.env`, `vendor/` live there). App reads `.env` via Dotenv *immutable* (real env vars win). | [R] compose, `public/index.php` |
| **No IOMS CI exists.** Root `.github/workflows/*` target other apps. | [R] |
| **No health endpoint.** Compose healthcheck = `GET /login` only. | [R] |
| Schema/seed load only on an **empty** DB volume via `docker-entrypoint-initdb.d`. `seed.sql` is idempotent but **date-relative** (`CURDATE() - INTERVAL n DAY`). | [R] |
| Zero-byte tracked junk files at project root and `tests/e2e/encode(467)` (12 files, from commit `1a4f1ec`). Out of scope; flagged for a separate cleanup decision. | [R] |

## 1.2 Roles, seed users, permissions
Roles (`Role` enum / DB enum): `Admin`, `Sales`, `WarehouseStaff`. **[R]**

| Seed user | Email | Password | Role |
|---|---|---|---|
| Rita | admin@example.com | admin123 | Admin |
| Beni | sales1@example.com | sales123 | Sales |
| Wawan | warehouse@example.com | wh123 | WarehouseStaff |
| Grace | sales2@example.com | grace123 | Sales |

Permission keys seeded in `role_permissions` **[R]** (`database/seed.sql` 395-424):
`users.manage, suppliers.manage, products.manage, categories.manage, warehouses.manage, customers.manage, master_data.menu` → Admin only · `customers.view` → Admin+Sales · `purchase_orders.manage` → Admin+WarehouseStaff · `purchase_orders.submit`, `purchase_orders.cancel` → **Admin only** · `sales_orders.menu` → Admin+Sales (create SO + sales dashboard) · `sales_orders.approve` → Admin · `sales_orders.issue` → Admin+WarehouseStaff · `stock_ledger.view`, `reports.stock_ledger.view` → Admin+WarehouseStaff · `reports.sales_orders.view` → Admin+Sales · `reports.purchase_orders.view` → Admin.
Plain `requireAuth()` (any logged-in role) guards: Products list/detail, Categories list/detail/export, Warehouses list/detail, Suppliers list/detail, SO list/detail/submit/cancel, Dashboard, Reports page, Profile, product availability API.

## 1.3 Auth / session / CSRF / ID obfuscation (all [R])
* **Sessions:** native PHP sessions; `session.save_handler=redis` if a 0.5 s TCP probe to Redis succeeds, **else silent fallback to file sessions**. Cookie `iom_session` (env `SESSION_NAME`), `HttpOnly`, `SameSite=Lax`, `Secure` only when `APP_ENV=production`. Cookie lifetime and server **idle timeout** both = `SESSION_LIFETIME` (default 3600 s); `last_activity` refreshed on every authenticated request.
* **Login:** `session_regenerate_id(true)` + CSRF token discarded and re-issued (fresh token on first page after login). **Failed login re-renders `/login` with HTTP 200** and the single message *"The email address or password you entered is incorrect."* (same for unknown user, wrong password, **inactive user**). Empty fields → same message, 200.
* **Logout is `POST /logout` with CSRF** (not GET — legacy tests `goto('/logout')` which 404s/405s silently, so legacy "logout" never logged anyone out).
* **Deactivation invalidates live sessions:** `AuthService::currentUser()` re-reads the user each request and destroys the session if inactive/missing. Role changes are synced from DB each request.
* **CSRF:** hidden input `_csrf_token` in every form (no meta tag); token is **stable for the session** (not rotated per request), regenerated at login. Check is `hash_equals` on `$_POST['_csrf_token']`. **Missing/invalid CSRF → HTTP 400 HTML error page** ("Your session has expired or the form is invalid. Please try again.") — also for the JSON-only Categories endpoints (the guard returns an HTML page, not JSON). Order of guards on POST: `requireAuth` (guest → **302 /login**) → permission (**403**) → CSRF (**400**) → id decode (**404**) → business rule.
* **Status-code vocabulary actually produced:** 200, 302, **400** (CSRF; form validation re-render; invalid PO/SO transition page; CSV missing dates), **403** (permission / SO ownership view), **404** (bad/unknown id, unknown route), **422** (Categories JSON validation & delete-guard only), **500** (internal; also form re-render when `Result::CODE_INTERNAL`). **401 only on `GET /api/products/{sku}/availability`** (JSON `{"error":"unauthorized"}`); `404` JSON `{"error":"not_found","sku":…}`. **409 is never produced** by the app.
* **ID obfuscation:** `{id}` URL segments = `bin2hex(KEY . bin2hex((string)$id))`, route constraint `[0-9a-f]+`. It is encoding, not crypto, and `KEY` = env `ID_OBFUSCATION_KEY`. Anything not matching the hex constraint → router 404 page; hex that does not decode → controller `notFound()` 404; raw `1` is hex-shaped but fails decode → 404. **Permission guards run before decode**, so a forbidden role gets **403 even for garbage ids**.
* **List/filter/sort/pagination state is POST-only** (`requestParam()` reads `$_POST`); query strings are ignored by design ("no-query-string rule"). Each list has `POST /{base}/search`.

## 1.4 Active modules (validated from `config/routes.php`) — 14 listed + 3 additions
Authentication · Dashboard (Admin/WH view `/dashboard`; Sales dashboard `/sales-dashboard`) · Products (+ Availability API) · Categories · Warehouses · Customers · Suppliers · Users · Purchase Orders (+ Goods Receipt) · Sales Orders (+ Goods Issue) · Stock Ledger · Reports (4 report types + 2 CSV exports) · Profile · Notifications.
**Additions the draft list missed:** (1) **Sales Dashboard** (`/sales-dashboard`, period selector, own export button) ; (2) **Product Availability JSON API**; (3) **Low-stock scheduled job** (`scripts/check-low-stock.php`, source of notifications — CLI, not a page). **`event_logs` has no UI/route** → backend-only, not an E2E module (verify via DB oracle only; see §A0.1).

## 1.5 Business rules verified in source ([R])
**Sales Order** (`SalesOrderService`, `SalesOrderPolicy`, `GoodsIssueService`, `SalesOrderController`)
* Create: `sales_orders.menu` (Admin+Sales). Header: active customer, active warehouse, date `Y-m-d`; ≥1 line; each line active product, qty>0, price≥0. **Stock is NOT checked at creation or approval — only at goods issue.** Duplicate product lines are allowed.
* Submit: any authenticated role passes the controller; **service requires actor == creator** and status Draft → else **400** ("Only the sales person who created this order can submit it." / "Only a Draft sales order can be submitted for approval.").
* Approve/Reject: permission `sales_orders.approve` (Admin) — Sales/WH get **403** with message "Only an administrator can approve a sales order." (reject: "…reject…"). Status must be PendingApproval (else 400). **Segregation of duties is a ROLE rule (DEC-012), not creator comparison: an Admin MAY approve an SO they created.** (`docs/testing/README.md` says "Admin CANNOT approve own" — **stale; code, unit and integration tests agree Admin self-approval succeeds**.) Reject reason optional (stored in `cancellation_reason`); a rejected SO becomes **Cancelled**.
* Cancel: Admin any non-Fulfilled/non-Cancelled; non-admin only the **creator** and only Draft/PendingApproval; all violations **400**.
* Issue: `sales_orders.issue` (Admin+WH; Sales **403**). Locks SO row `FOR UPDATE`, re-checks status Approved, per line locks `product_stocks`, rejects if qty<needed ("Insufficient stock: there is not enough stock on hand to issue this sales order." → **400**, full rollback), writes `Issue` ledger rows with **negative qty**, sets Fulfilled + `issued_by/at`.
* Visibility: Sales sees/exports **only own** SOs (list scoped; `GET /sales-orders/{token}` of another Sales' SO → **403**, not 404). Admin & WH see all.

**Purchase Order** (`PurchaseOrderService`, `GoodsReceiptService`, controller)
* All PO pages need `purchase_orders.manage` (Admin+WH); **Sales gets 403 on everything**. Create: active supplier+warehouse, date, ≥1 line, qty>0, price≥0 (message "A purchase order needs at least one line item."). Submit (Draft→Ordered) and Cancel are **Admin only**; WH → 403.
* Cancel allowed from **Draft, Ordered, PartiallyReceived**; refused for Received/Cancelled ("…can no longer be cancelled…" 400). Note: cancelling a PartiallyReceived PO does **not** reverse already-received stock.
* Receive: status Ordered/PartiallyReceived only; `qty_now[itemId]`; per-line over-receipt blocked twice (pre-check + locked re-check) → "Quantity received cannot exceed the quantity still outstanding for this line." (400 re-render of receive form); PO row locked `FOR UPDATE` so concurrent receipts serialise. Writes `Receipt` ledger rows (+qty), increments stock, recomputes status (PartiallyReceived/Received).
* Duplicate lines allowed. Draft PO has no edit route (no edit/update).

**Stock Ledger** (`stock_ledger` table has only signed `qty`; **no before/after columns**) — UI "Before → After" = running `SUM(qty)` per product+warehouse (`qty_after`), `qty_before = after − qty`. Filters are **SKU, product name, movement type (multi), warehouse (multi)** — **there is no date filter or free-text `q` on the ledger page** (dates exist only on Reports/exports). Sort columns: date, product, sku, type, qty, warehouse; per page **25**; all list interactions are AJAX `POST /stock-ledger` with `X-Requested-With`, returning JSON `{tbody,page,totalPages,total}`; Sales → 403 on GET and POST.

**CSV** (`CsvExportService`) — CRLF line endings, no BOM, RFC-4180 quoting, injection guard: any field whose trimmed-right value matches `^[ ]*[=+\-@\t\r\n]` is prefixed with `'`; **ledger Quantity column is exempt** (negative numbers). Exports are **POST-only**; GET is not served as CSV. Ledger export needs `reports.stock_ledger.view` (Admin, WH; Sales 403); orders export needs a `reports.*_orders.view` (Admin, Sales; **WH 403**), Sales rows are own-only; missing dates → **400** "Please select both a start date and an end date."; filenames fixed: `stock-ledger.csv`, `orders.csv`, `categories.csv`; locale is English in practice (id-locale code path exists, [C]).
Headers: ledger `Date,Product,SKU,Warehouse,Type,Quantity,Ref Type,Ref ID,Done By`; orders `Type,Order No.,Date,Customer/Supplier,Warehouse,Status,Items Count,Total Value,Created By`; categories `Code,Name,Description,Assigned SKUs,Status,Last Updated`.

**Master data** (validation, all [R] unless marked)
* **Product:** SKU required, uppercased, **max 30** (schema allows 50), **no format regex**, unique ("This SKU is already in use."); name 3–150 by **byte length (`strlen`)**; description ≤500 (bytes); unit required; category required & must exist; purchase/sale price numeric ≥0 and **sale ≥ purchase** ("Selling price must be greater than or equal to purchase price."); reorder ≥0; barcode unvalidated. Initial stock (`initial_warehouse_id`+`initial_qty`) is applied **only if both >0, otherwise silently ignored**; it writes an `Adjustment` ledger row. List default **hides inactive** products; stock-status filter values `in_stock|low_stock|out_of_stock|inactive`; per-page 10/25/50/100; KPI metrics total/normal/low/out/inactive. Failure status: 400 (500 if internal). Edit/activate/deactivate: Admin; any role can list/view.
* **Image upload** (`ImageUploadService`): max 2 MiB; MIME by **`finfo` magic bytes** (+`getimagesize`), allowed JPEG/PNG/WebP; extension ignored; >1200 px is down-scaled; always re-encoded to **WebP**, stored at `ioms/products/YYYY/MM/<hash>.webp` in MinIO; `image_path` = public URL. Errors: "Only JPEG, PNG, or WebP images are allowed." / "File size exceeds the maximum allowed." / "The image could not be processed. Please try a different file." — all re-render the form with 400.
* **Category:** name 3–80 chars (mb), unique (case-insensitive collation) "This name is already in use."; code optional, regex `^[A-Z0-9-]{3,20}$`, unique "This category code is already in use."; **auto-generated code `CAT-<PREFIX6>-NN` is race-prone under parallel creation → factories must always pass an explicit unique code**; description ≤250. JSON AJAX modal (`POST /categories`, `/update`, `/delete`) → `{ok,…}`; validation **422**; **hard delete** only when 0 SKUs assigned (else 422); activate/deactivate are redirects; list default sort name asc; per-page 10/25/50; export `categories.csv` (any authenticated role).
* **Warehouse:** only `code` (uppercased) and `name` required; code unique on **update** (create relies on uniqueness check at line ~69, [C]); **no length/format guard in service** (DB code ≤20, name ≤100 → over-long input surfaces as a 500 — OBSERVED QUIRK); no deactivation guard even with stock. **List has no pagination**; detail page has paginated stock table (10/page, POST `stock_page`).
* **Customer / Supplier:** only name required + optional email (`FILTER_VALIDATE_EMAIL`: "Please enter a valid email address."); no uniqueness, no length guards (DB 150/255 → 500 on overflow — QUIRK). Customers: `customers.view` for Admin+Sales; **WH gets 403 even on GET**. Suppliers: any authed role can view. **No pagination on these lists.** Deactivated suppliers/customers disappear from PO/SO dropdowns (service rejects inactive: "Please select a valid, active supplier.").
* **User:** name required; email valid + unique ("This email is already in use."); role must be enum ("Please select a valid role."); password ≥6 chars on create ("Password must be at least 6 characters."), optional on edit (blank = unchanged); bcrypt. Guards: **cannot change own role** ("You cannot change your own role."), **cannot deactivate self** ("You cannot deactivate your own account." → 400 page). No "last admin" guard beyond self. Whole module Admin-only (others **403**). **No pagination on the list.**
* **Profile:** `GET/POST /my-profile`, **name + email only**, email unique excluding self; **no password change and no role field editable** (role not posted). Success sets flash + redirect.
* **Notifications:** table has **no per-user state** (`read_at` global): `POST /notifications/mark-all-read` marks everything read for everybody; Admin+WH only (Sales **403**); redirect target derived from `Referer` path (`…/update`→`…/edit`, trailing `/search|/stock` stripped), default `/dashboard`. Bell/dashboard block visible to Admin+WH. Created only by `scripts/check-low-stock.php` (dedupe by type/product/warehouse while unread; low stock = **total across warehouses < reorder_point**, active products only).

**Dashboard/Reports** — Admin and WH both render `dashboard/index` (legacy notes H1 always "Admin Dashboard" and a dead "Generate Summary Report" link `/reports/summary` — quirks [L]); Sales dashboard at `/sales-dashboard` (periods `today|week|month|all`, default month, Sales scoped to own). Reports: types `stock_valuation` (default), `inventory_aging`, `slow_moving`, `movement_ledger`; page size 5; default date window = last 30 days; invalid dates silently fall back to defaults; Sales sees `orders-only` view; WH/Admin full analytics view.

## 1.6 Infrastructure facts that drive the isolation design ([R])
1. **A second compose project cannot be created by just adding `-p`**: `container_name` is hard-coded → name collision with the dev stack; `ports` lists would *append* (not replace) on a layered `-f base -f e2e` merge, binding 8090/3307/11211 again; the dev `.env` is bind-mounted into the container.
2. MinIO is external and shared; E2E image uploads would otherwise write into the developer's `portfolio-uploads` bucket.
3. Session store is Redis **with silent file fallback** → E2E must assert Redis is actually the handler.
4. Seed is date-relative; PHP `date()` and MySQL are UTC in the image (no php.ini); forms default `order_date` to server `date('Y-m-d')`.
5. `php -S` has 4 workers by default → concurrency ceiling for Playwright workers.
6. Sessions are shared server-side per cookie: several parallel Playwright workers reusing **one** storageState share one PHP session, and the app uses **session flash** (`pullFlash`) → cross-test flash/CSRF interference. (Design consequence: per-worker sessions, §3.13.)
7. Permissions are cached in Memcached (TTL 3600) and product-by-SKU in Memcached (TTL 300).
8. `docker`, `php`, `redis-server`, Node 22, Playwright browsers exist in this authoring sandbox; MySQL and a running Docker daemon do not → **Phase 1 spikes must run where the stack can start.**

---

# 2. CORRECTIONS TO THE ORIGINAL DRAFT
(Draft not available — each row is a draft assumption **implied by your brief**, with the repository-validated correction.)

| # | Draft assumption (implied) | Reality / correction | Evidence |
|---|---|---|---|
| C1 | Reset with `docker compose down -v` on the standard project | **Forbidden.** Standard project owns `iom_db_data/iom_redis_data`. E2E uses a **separate standalone compose file** + fixed project name `ioms-e2e` + guarded wrapper (§3.9). A layered `-f base -f e2e` is **rejected**: fixed `container_name`, list-appending `ports`, shared `.env` bind mount. | compose |
| C2 | "Override file" reuses base services | Standalone file (no merge semantics, cannot accidentally reference dev volumes/ports/names). Image built from the existing `Dockerfile`; **no source bind mount** (code under test = built image; no dev `.env`/`vendor` bleed). | compose, `.dockerignore` |
| C3 | E2E needs DB+Redis only | Also needs **memcached**, and a **dedicated MinIO + bucket** (otherwise uploads hit shared storage / fail). | `config/global.php`, `ImageUploadService` |
| C4 | One Playwright project per role | Would run every spec ×4. → one `parallel` project + `setup` project + one `exclusive` project; role-aware fixtures (§3.12). | — |
| C5 | `globalSetup` logs in all roles | `globalSetup` = environment only; `setup` project = auth only. **Plus per-worker sessions** (shared session + session flash is unsafe). | `BaseController::pullFlash` |
| C6 | Login failure returns 401 | Returns **200** with inline message; 401 exists only for the JSON availability API. Guest GET/POST → **302 /login**. | `AuthController`, `ProductApiController` |
| C7 | Expect 401/403/404/409/422 broadly | 409 never occurs; 422 only Categories JSON; business-rule transition failures are **400**; CSRF **400**; ownership view **403**; non-creator submit/cancel **400** (not 403). | §1.3 |
| C8 | Logout is a link/GET | `POST /logout` + CSRF; shared storageState sessions must never be used for logout tests (it destroys the session). | `routes.php`, `main.php:318` |
| C9 | "Sales cannot approve own SO; Admin cannot approve own" | SOD is **role-based**; Admin self-approval is **allowed** (docs stale). Sales cannot approve *any* SO. | `SalesOrderPolicy`, `BR001SegregationTest` |
| C10 | Ledger filters by date / has before-after columns in DB | No date filter on `/stock-ledger`; before/after is a **computed running sum**. Date scope exists on Reports/exports. | `StockLedgerMySQLRepository` |
| C11 | Pagination on all master lists | **Only** Products (10/25/50/100), Categories (10/25/50), PO/SO (10), Ledger (25), Reports (5), Warehouse-detail stock (10). Warehouses/Customers/Suppliers/Users lists are **unpaginated** → "pagination boundary" scenarios N/A there. | controllers, views |
| C12 | `GET /login == 200` ⇒ ready | Insufficient (page renders without DB). Readiness = compose healthchecks + HTTP probes + DB seed probe + Redis-session proof + login/seed verification in `setup` (§3.17). | `compose` healthcheck |
| C13 | Session expiry testable by waiting | Server idle timeout = `SESSION_LIFETIME` env. Use a **second app instance with `SESSION_LIFETIME=3`** (profile `session-expiry`), polled at intervals > lifetime. E2E main env sets **`SESSION_LIFETIME=28800`** so saved storageState cookies can't expire mid-run. | `SessionManager`, `AuthService` |
| C14 | Timestamp-based unique names | Collision-prone & **length-limited fields** (SKU ≤30, category code ≤20 `[A-Z0-9-]`, warehouse code ≤20 DB-only). One shared `uid` helper with kind-specific bounded generators; **never rely on auto-generated category codes** (race). | `CategoryService::generateUniqueCode` |
| C15 | Image test uses extension tricks | Validation is **magic-byte (finfo) + getimagesize**; extension ignored; output always WebP; limits 2 MiB / 1200 px. | `ImageUploadService` |
| C16 | CSV "injection test" generic | Prefix set is `= + - @ TAB CR LF` (leading spaces tolerated); **Quantity column deliberately exempt**; GET export not served; filter/scope contract per route. | `CsvExportService` |
| C17 | Notifications per-user | **Global** read state; `mark-all-read` mutates shared state → notification tests are **exclusive/serial** and need the low-stock script to create data (Mode A only). | `NotificationService`, schema |
| C18 | Concurrency tests use seeded orders | Forbidden (shared state). Each uses **test-owned warehouse+product+aggregate**. | — |
| C19 | Legacy suite is a Playwright Test suite | It is **15 standalone `node` scripts** with `check()` printouts, no `@playwright/test`, no config, no CI, hardcoded `127.0.0.1:8090`, 24 `waitForTimeout`, mutates seed (ELEC-003, OFC-001, first product), date windows frozen at 2026-08/09 (fail on fresh seed). Parity map in §15. | [L] |
| C20 | Auth failure for *inactive* user is a distinct message | Same generic message as bad password (no enumeration). | `AuthService::login` |
| C21 | `data-testid` broadly needed | Markup already has ids/roles/aria in many places (custom dialog is `role=alertdialog`, `#__confirmOk`; `role=alert/status`, `aria-live` toast container). Initial selector-change inventory is **small and conditional** (§13). | [R] app.js, [L] |
| C22 | Role-permission matrix from README | Matrix derived from `role_permissions` seed + controllers (§1.2); e.g. **WH cannot view Customers (403)**, **Sales cannot view POs (403)**, **Categories/Suppliers/Warehouses readable by all roles**. | seed, controllers |

---

# 3. FINAL DESIGN SPECIFICATION

## 3.1 Executive summary
A new `@playwright/test` (TypeScript) suite in `tests/playwright/` that runs against a **fully isolated, disposable stack** (`ioms-e2e`: own MySQL, Redis, Memcached, MinIO, app, own ports/volumes/network), with an explicit second mode for an already-running environment that can never reset anything. Authentication is a single `setup` project that produces **per-worker** storageState per role; tests receive role-aware pages/requests through fixtures (no per-role project multiplication). Seed data is read-only; every mutation test owns its data via shared factories and one collision-resistant naming helper. Business-critical invariants (stock = Σ ledger, no over-receipt, no oversell, SOD, session invalidation) are asserted through UI **and** direct HTTP **and** (Mode A) a read-only SQL oracle. Work is split across 9 agents with exclusive path ownership, a frozen foundation contract, wave gates, and a legacy parity audit before the old scripts can ever be retired.

## 3.2 Goals
Maximum-depth functional coverage of all active modules (§28); deterministic, parallel-safe execution; zero risk to developer data; rich failure diagnostics; maintainable shared architecture so independent agents never rediscover decisions.

## 3.3 Non-goals
Penetration testing; load/performance testing; changing application behaviour (the suite asserts **implemented** behaviour; discovered defects are reported, never silently "fixed"); implementing CI before approval; deleting legacy tests; fixing the zero-byte junk files; testing `event_logs` through UI (none).

## 3.4 Architecture decisions (summary)
| ID | Decision |
|---|---|
| AD-1 | Location `inventory-order-management-system/tests/playwright/` (already `.dockerignore`d via `tests`, sits beside legacy `tests/e2e/`, own `package.json`). |
| AD-2 | TypeScript, `@playwright/test`, `@axe-core/playwright`; version pinned in F1.1 (legacy lock resolved `playwright 1.63.0`; sandbox ships chromium-1194 → `E2E_CHROMIUM_PATH` override supported). |
| AD-3 | Standalone `docker-compose.e2e.yaml` + fixed project `ioms-e2e` + guarded `compose()` wrapper. |
| AD-4 | Projects: `setup` → `parallel` → `exclusive` (+ opt-in `session-expiry` profile). No per-role projects. |
| AD-5 | Per-worker authenticated sessions (role × worker). |
| AD-6 | Factories create test-owned data over HTTP (admin request context + CSRF), UI only for the scenario under test. |
| AD-7 | Mode A (isolated, default for everything) / Mode B (existing, subset, no reset ever). Mode must be explicit (`E2E_MODE`), no default. |
| AD-8 | Assertions: contract-level exact for source-confirmed messages/status; semantic for generic errors (§3.19). |
| AD-9 | Production changes: attribute-only, behaviour-neutral, batched centrally, separately approved. Initially **none required**; candidates in §13. |

## 3.5–3.6 Modules & role matrix
Module inventory in §1.4. Role access matrix (✔ = allowed, 403 = Forbidden, 302 = redirect to /login for guests):

| Area / action | Guest | Admin | Sales | Warehouse |
|---|---|---|---|---|
| Dashboard `/dashboard` | 302 | ✔ | ✔ (renders sales stats) | ✔ |
| `/sales-dashboard` | 302 | ✔ (all) | ✔ (own) | **403** |
| Products list/detail/availability API | 302 / API **401** | ✔ | ✔ | ✔ |
| Products create/edit/(de)activate | 302 | ✔ | 403 | 403 |
| Categories list/detail/export | 302 | ✔ | ✔ | ✔ |
| Categories create/edit/(de)activate/delete | 302 | ✔ | 403 | 403 |
| Warehouses list/detail | 302 | ✔ | ✔ | ✔ |
| Warehouses manage | 302 | ✔ | 403 | 403 |
| Suppliers list/detail | 302 | ✔ | ✔ | ✔ |
| Suppliers manage | 302 | ✔ | 403 | 403 |
| Customers list/detail | 302 | ✔ | ✔ | **403** |
| Customers manage | 302 | ✔ | 403 | 403 |
| Users (all) | 302 | ✔ | 403 | 403 |
| PO list/detail/create/receive | 302 | ✔ | **403** | ✔ |
| PO submit / cancel | 302 | ✔ | 403 | **403** |
| SO list/detail | 302 | ✔ all | own only (other's detail **403**) | ✔ all |
| SO create | 302 | ✔ | ✔ | **403** |
| SO submit | 302 | creator only (else 400) | creator only (else 400) | n/a (400) |
| SO approve / reject | 302 | ✔ (incl. own) | **403** | **403** |
| SO cancel | 302 | ✔ any non-final | creator, Draft/Pending only (else 400) | 400 |
| SO issue | 302 | ✔ | **403** | ✔ |
| Stock Ledger page + AJAX | 302 | ✔ | **403** | ✔ |
| Reports page | 302 | full | orders-only view | full |
| Export stock-ledger | 302 | ✔ | **403** | ✔ |
| Export orders | 302 | ✔ (SO+PO) | ✔ (own SO only) | **403** |
| Profile | 302 | ✔ | ✔ | ✔ |
| Notifications mark-all-read | 302 | ✔ | **403** | ✔ |

## 3.7–3.8 Execution modes & safe environment
**Files (Agent 1 owns):** `tests/playwright/env/docker-compose.e2e.yaml`, `tests/playwright/env/e2e.env` (non-secret throwaway values; tracked), `tests/playwright/support/compose.ts`.

**Stack `ioms-e2e`:** services `db` (mysql:8.0, volume `db_data`, schema+seed mounted read-only into initdb.d — same files as dev, mounted **read-only**), `redis` (volume or tmpfs), `memcached`, `minio` (+ one-shot `minio-init` creating bucket `ioms-e2e` with anonymous-download policy), `app` (built from `../../../Dockerfile`, **no bind mount**, env fully explicit: `DB_NAME=ioms_e2e`, `SESSION_NAME=ioms_e2e_session`, `SESSION_LIFETIME=28800`, `PHP_CLI_SERVER_WORKERS=8`, `ID_OBFUSCATION_KEY=<fixed e2e key>`, MinIO → internal `minio:9000` endpoint, public URL `http://127.0.0.1:19000`, bucket `ioms-e2e`), optional profile `session-expiry` (`app-shortsession`, same image, `SESSION_LIFETIME=3`, distinct `SESSION_NAME`). No `container_name` anywhere, no cron service, DB/Redis/Memcached **not published** to the host. Published ports (loopback only, `127.0.0.1`): app **18090**, short-session app **18091**, MinIO **19000** (+console 19001). Top-level `name: ioms-e2e`. Network = project default.

**Safety rules (enforced in code, tested in F1.5):**
1. Every docker invocation goes through `compose(args)` which always injects `-p ioms-e2e -f docker-compose.e2e.yaml --env-file e2e.env`, deletes `COMPOSE_PROJECT_NAME`/`COMPOSE_FILE` from the child env, and refuses any argument that smuggles `-p`/`-f`/`--project-name`.
2. Destructive ops (`down -v`, DB drop) go through `resetIsolated()` only; it first lists `docker volume ls --filter label=com.docker.compose.project=ioms-e2e` and aborts unless **every** volume name starts with `ioms-e2e_`, and aborts if `iom_db_data`/`iom_redis_data` would be touched. It never runs when `E2E_MODE=existing`.
3. Mode-B code path imports **no** `compose`/`db` module (lint rule + unit test) — it physically cannot exec docker.
4. `globalSetup` refuses to start if the target `baseURL` port equals the dev app port (8090) while `E2E_MODE=isolated`.

**Modes**
| | Mode A `isolated` (default for all suites, CI, workflow/mutation) | Mode B `existing` |
|---|---|---|
| Select | `E2E_MODE=isolated` (**required, no implicit default**) | `E2E_MODE=existing E2E_BASE_URL=… E2E_I_UNDERSTAND_NO_DETERMINISM=1` |
| Infra | disposable `ioms-e2e` | whatever is running |
| Reset | `E2E_RESET=always` (default: `down -v` of **e2e project only** + `up --build --wait`), `fast` (drop/recreate e2e DB + re-seed via `docker exec`, restart app), `never` | **never** — no reset, no docker, no DB |
| Seed guarantee | pristine baseline each run | **not guaranteed** (documented, banner printed) |
| Tests run | everything | only tests **not** tagged `@needs-isolated` / `@seed-dependent` / dirs under `exclusive` that need global state |
| Data | test-owned | test-owned (prefix `E2E-`; accumulates; soft-deactivate best-effort cleanup helper) |
| DB oracle | read-only SQL via `docker exec` | UI/HTTP oracle only |

## 3.9 DB / data isolation
Seed = **read-only baseline** (4 users, 2 warehouses, 4 categories, 10 suppliers, 10 customers, 100 products, 50 PO, 50 SO, notifications none, 100×2 opening stock + ledger). Read-only specs may assert on seed (`@seed-dependent`, Mode A only). All mutations use test-owned entities. **Final gate** (Mode A, `globalTeardown`, read-only SELECT): for every product/warehouse `product_stocks.quantity == SUM(stock_ledger.qty)`; no negative stock; no PO line with `qty_received > qty_ordered` — failure fails the run.

## 3.10 Playwright execution model
`workers`: `E2E_WORKERS` default `min(4, cpus)` (app concurrency ceiling 8 php workers). `fullyParallel: true`.
* **`setup`** — `testMatch: setup/auth.setup.ts`; logs in each role × each worker slot.
* **`parallel`** — `dependencies:['setup']`, `testIgnore: ['**/exclusive/**']`. All independent specs.
* **`exclusive`** — `dependencies:['parallel']` (so it runs **after** everything else), `workers:1`, `fullyParallel:false`; contains specs that depend on **global state** (dashboard exact KPIs, notifications mark-all-read, global report KPIs/valuation, low-stock job, session-expiry app). Oracle for dashboard KPIs: cross-surface consistency (card value == list total) because nothing else mutates during this project.
* Retries: `0` locally, `1` in CI with `failOnFlakyTests:true` (a pass-on-retry is reported as a failure of stability, never silently green).
* Timezone `UTC`, locale `en-US`, `viewport 1280×800` (responsive specs override to 360 px).
* Artifacts: `trace:'retain-on-failure'`, `screenshot:'only-on-failure'`, `video:'retain-on-failure'`; reporters `list` + `html` + `junit`.
* No `webServer` block (stack lifecycle is owned by `globalSetup` so mode/safety logic is in one place).

## 3.11 `globalSetup` (environment only — single responsibility)
1. Parse & validate env contract; **fail fast** if `E2E_MODE` unset/invalid.
2. Mode A: run guard checks; per `E2E_RESET`: reset or reuse; `compose up --build --wait` (healthchecks: db `mysqladmin ping`, redis `ping`, memcached, minio live, app `/login`).
3. Probes (Mode A): `SELECT COUNT(*)` on `users/products/purchase_orders/sales_orders/role_permissions` meets seed minimums; `redis-cli PING`; `redis-cli --scan --pattern 'PHPREDIS_SESSION:*'` is checked *after* login in `setup` (proves Redis is the session handler, not file fallback).
4. Probes (both modes): `GET /login` 200 + contains `_csrf_token`; unauthenticated `GET /api/products/ELEC-001/availability` → **401 JSON** (proves router+bootstrap+JSON path); write `.run/env.json` (mode, baseURL, workers, runId).
5. **Does not log in.** Seed-record & authenticated-state verification belong to `setup` (below).

`globalTeardown`: Mode A → run invariant gate, collect `docker compose logs --no-color app` into the report dir (grep for `PHP (Warning|Notice|Deprecated|Fatal)` → fails the run, replacing legacy `crawl` log check), then stop (not delete) the stack unless `E2E_KEEP=0`. Mode B → nothing.

## 3.12–3.13 Authentication setup & role model
`setup/auth.setup.ts` (project `setup`) — for each role in `admin|sales|warehouse|sales2` and each worker slot `0..workers-1`: fresh context → UI login (`/login`, `#email`, `#password`) → assert redirect to `/dashboard` (Sales also valid) → save `.auth/<role>.w<n>.json`. Then (once): assert Admin can see seed SKU `ELEC-001` in `/products` (**seed readiness** in Mode A), and in Mode A assert ≥1 `PHPREDIS_SESSION:*` key (**Redis session proof**). Rationale for per-worker sessions: PHP sessions + `pullFlash` are not safe to share across concurrent workers; separate logins give separate Redis sessions & CSRF tokens at negligible cost (16 logins).

**Fixtures** (`support/fixtures.ts`, the **only** place tests import `test` from):
`adminPage`, `salesPage`, `warehousePage`, `sales2Page` (Playwright `Page` from the worker's storageState), `adminRequest`/`salesRequest`/`warehouseRequest`/`sales2Request` (`APIRequestContext`, same state), `guestPage`/`guestRequest` (no state), `loginAs(role|credentials) → {page, request, logout()}` for tests that **must** own their session (logout, regeneration, deactivation, CSRF-stale), `uid`, `factories`, `csrf`, `ids`, `diagnostics` (auto), `db` (Mode A only; throws a typed `ModeBUnavailable`). Role names are the literal union `'admin'|'sales'|'warehouse'|'sales2'`.
**Never** log out on a shared fixture session.

## 3.14 Test-data ownership & unique data
**Rule:** seed read-only; mutations on test-owned entities only; no test depends on another test's data (except steps inside one `serial` aggregate describe).
`support/unique.ts` (single helper, **only** naming source): `uid = createUid({runId, workerIndex})` where base token = `${workerIndex}-${Date.now().toString(36)}-${rand4}`. API: `uid.name(kind)` → `E2E-<kind>-<token>` (≤60 chars) · `uid.sku()` → `E2E<token>` upper-cased, **≤30** · `uid.categoryCode()` → `E2E-<token>` `[A-Z0-9-]{3,20}` · `uid.warehouseCode()` → ≤20 chars · `uid.email(prefix)` → `e2e-<token>@e2e.test` · `uid.phone()` · `uid.text(n)` (exact-length boundary strings) · `uid.reasons`. Constraint table lives in FOUNDATION_CONTRACT. Dates via `support/dates.ts` (`utcToday()`, `addDays()`), never hardcoded.

## 3.15 Parallelism policy
* **Parallel:** all CRUD/validation/RBAC/list/read specs using owned data; read-only seed specs.
* **Serial at smallest scope** (`test.describe.configure({mode:'serial'})` inside **one** describe per aggregate): PO lifecycle (Draft→Ordered→PartiallyReceived→Received), SO lifecycle (Draft→PendingApproval→Approved→Fulfilled), user-deactivation session test, product create→edit→deactivate chain. Different aggregates stay parallel.
* **`exclusive` project** only for global-state specs (§3.10).
* **Concurrency tests stay concurrent** (`Promise.all` of ≥2 requests from independent sessions) and each uses a dedicated warehouse+product+order created by the test itself.

## 3.16 Selectors
Priority: `getByRole` → `getByLabel` → `getByTestId` → stable `#id` → business-key attribute → `getByText`. Repeated-row identity uses **test-unique business keys** (`getByRole('row').filter({hasText: sku})`). Existing stable ids confirmed by legacy [L] (e.g. `#ledger-sku`, `#category-name-input`, `#po-submit-btn`, `#__confirmOk`) are allowed as tier-4. New `data-testid`/`data-row-key` only through the central selector process (§13).

## 3.17 Readiness (no health endpoint exists; none added)
Layered: compose healthchecks → `/login` 200 → unauth API 401 JSON → SQL seed probe (A) → login + seed-SKU visibility + Redis-session proof (`setup`). Documented fallback for Mode B: first four HTTP steps minus SQL.

## 3.18 POM / components
Domain page objects only where they carry intent (`ProductPage.createProduct`, `PurchaseOrderPage.receiveItems`, `SalesOrderPage.approve`, …); **no** generic `fillField/doAction/submitForm`. Shared components (justified by ≥2 consumers): `DataTable`, `Pagination` (POST page buttons), `ConfirmDialog` (`role=alertdialog` / `#__confirmOk`), `Toast` (`aria-live` container), `MultiSelect` (`.ms-wrapper/.ms-option`), `ReportModal`, `RowActionsMenu` (`.row-actions__trigger`), `Download` helper (CSV), `Upload` helper.

## 3.19 Assertion strategy — contract classes
| Class | Rule | Examples |
|---|---|---|
| **Contract (exact)** | exact status + exact text from source constants | login error text; every `validationResult()` string; SOD message; over-receipt; insufficient stock; CSRF message; CSV headers; CSV filenames; category 422 JSON shape |
| **Contract (status-only)** | status/redirect + state unchanged | guest 302; 403s; 404 tamper; double-submit |
| **Semantic** | status class + error container visible + regex fragment | 500 presentation ("internal error"), MinIO outage, generic modal errors |
| **Quirk** | asserts current behaviour, annotated `{type:'quirk'}`, reported in completion report | SKU 30 vs 50, byte-length names, 500-on-overflow, dead "Generate Summary Report" link |

## 3.20 CSRF/session helpers (`support/csrf.ts`, `support/request.ts`)
`csrf.fromPage(page)`, `csrf.fromRequest(request, path)` (GET and parse `input[name=_csrf_token]`), `csrf.invalid()`, `csrf.stale(role)` (token captured from an anonymous `/login` render and from a pre-login session; after login it must be rejected → 400), `csrf.otherSession()`, `post(request, path, form, {csrf:'valid'|'missing'|'invalid'|'stale'})` returning `{status, location, body, json}` with `maxRedirects:0` by default.

## 3.21 ID obfuscation (`support/ids.ts`)
Never hardcode/encode ids. `ids.fromHref(locator)`, `ids.fromRedirect(response)`, `ids.fromList(request, base, filters)` (POST `/…/search`, parse row link). Negative generators operate on a **captured** token: `tamper(token)` (flip last hex nibble), `truncate`, `nonHex` (→ router 404), `raw('1')`, `forbiddenToken` (token of a resource the role may not access, captured by an authorised role). Expected: non-hex → 404 (router); hex-invalid → 404; forbidden role → **403 before decode**; Sales on other Sales' SO → **403**.

## 3.22 Upload (`fixtures/files/`, `scripts/make-fixtures.mjs`)
Deterministic local binaries, no network: valid PNG, valid JPEG, valid WebP, text file renamed `.png` (magic-byte reject), PNG renamed `.jpg` (**accepted** — extension ignored), empty file, GIF (reject), **>2 MiB** valid PNG (generated deterministically by script into `.cache/`, not committed), 2000×1500 PNG (scale-down to ≤1200), exactly-at-limit & limit+1 byte cases. Assertions: error strings (§1.5), stored `image_path` ends `.webp`, object retrievable from the E2E MinIO public URL.

## 3.23 Export (`support/csv.ts`)
`downloadCsv(page|request, …)` returns `{status, contentType, filename, raw, rows}`; asserts `text/csv; charset=utf-8`, `Content-Disposition` filename, **CRLF**, header row exact, column order, row count vs filter/scope, RFC-4180 quoting round-trip (commas, quotes, newlines), injection prefix on `= + - @ TAB CR LF` and leading-space variants, Quantity exempt, role/ownership/warehouse/date scope, GET not served.

## 3.24 Accessibility
`@axe-core/playwright`, tags `wcag2a,wcag2aa,wcag21aa`, fail on `serious|critical`. One scan per distinct rendered UI (not per role) except where role UI differs (dashboard variants, SO detail actions, products list for admin vs read-only). Keyboard flows: login Tab/Enter, confirm dialog (focus lands on Cancel, Escape closes, focus return), category modal, row-actions menu (Enter/Escape), multi-select (Enter opens, Escape closes), notification/account menus, report modal Escape. **Pre-existing violations:** discovery run → `a11y/known-issues.json` allowlist (rule+selector+reason), approved by Agent 0; new violations fail; app fixes are separate approvals.

## 3.25 Diagnostics (`support/diagnostics.ts`, auto fixture)
Per test: collect `console` errors, `pageerror`, failed requests, responses ≥400 (url/method/status), main-frame URL history, and last N actions (wrapped `step`s). Attach as JSON + text **only on failure**. A request ≥500 or a PHP-leak regex in any HTML body (`(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\s*:`) auto-fails the test unless the test declares `diagnostics.expect500()` (for 500-presentation specs). Plus `globalTeardown` app-log scan.

## 3.26 Tags (all agents use only these)
`@smoke` (≤20 specs, <2 min, safe in Mode B) · `@crud` · `@validation` · `@rbac` (UI + direct request role matrix) · `@workflow` (multi-step state machines) · `@security` (CSRF, ID tamper, session, injection) · `@a11y` · `@concurrency` · `@export` · `@upload` · `@regression` (maps to a legacy/ fixed bug) · **modifiers:** `@needs-isolated` (docker/DB/exclusive or seed-exact) · `@seed-dependent` · `@quirk` · `@slow`. Applied via `test(title, {tag:[…]}, fn)`; `grep` by tag in commands (§16). A lint (Agent 8) fails untagged tests or unknown tags.

## 3.27 Determinism rules
No `waitForTimeout` (lint `no-restricted-properties`; documented exceptions only with `// ALLOW-WAIT:` and Agent 0 approval). Use `expect`/`expect.poll`, `waitForResponse`, `waitForURL`. App has custom debounce/AJAX only on ledger/categories/dashboard/reports (to confirm in spike); every such action awaits its response. Session-expiry polling uses `expect.poll` with interval > lifetime. All date logic UTC via helpers; cross-midnight guard: specs reading "today" compute from server-rendered date, not wall clock, where the server date matters.

## 3.28 Directory structure
```
tests/playwright/                      (new; legacy tests/e2e/ untouched)
  package.json  tsconfig.json  playwright.config.ts  .eslintrc.cjs  .gitignore(.auth, .run, .cache, reports)
  FOUNDATION_CONTRACT.md               ← Agent 1 (frozen at Gate G1)
  env/        docker-compose.e2e.yaml  e2e.env  README.md
  global/     global-setup.ts  global-teardown.ts
  setup/      auth.setup.ts
  support/    env.ts compose.ts db.ts(A-only) roles.ts unique.ts dates.ts fixtures.ts
              diagnostics.ts csrf.ts request.ts ids.ts csv.ts files.ts tags.ts messages.ts
  factories/  category.ts product.ts warehouse.ts supplier.ts customer.ts user.ts
              purchase-order.ts sales-order.ts stock.ts
  components/ DataTable.ts Pagination.ts ConfirmDialog.ts Toast.ts MultiSelect.ts
              ReportModal.ts RowActionsMenu.ts
  pages/      <domain>/…  (owned by the domain agent; e.g. pages/products/ProductPage.ts)
  fixtures/files/  png/jpg/webp/invalid/empty (committed) ; scripts/make-fixtures.mjs
  specs/
    auth/ security/ rbac/                                   ← Agent 2
    products/ categories/ warehouses/ customers/ suppliers/ users/   ← Agent 3
    purchase-orders/                                         ← Agent 4
    sales-orders/                                            ← Agent 5
    stock-ledger/ reports/                                   ← Agent 6
    dashboard/ profile/ notifications/ a11y/                 ← Agent 7
    exclusive/<domain>/   (global-state specs; owned by the same domain agent)
  requests/   SHARED_CHANGE_REQUEST-*.md  SELECTOR_REQUEST-*.md  (agents drop requests here)
  reports/    completion reports AGENT-<n>-COMPLETION.md
```

## 4. SOURCE EVIDENCE MATRIX
| Fact / Decision | Source file | Symbol / Route | Finding | Verified |
|---|---|---|---|---|
| Roles | `app/Entity/Role.php`, `schema.sql` users.role | enum | Admin / Sales / WarehouseStaff | R |
| Seed users & passwords | `database/seed.sql:14-19`, `login.php:63-88` | users insert | 4 users; login page has 3 demo-fill buttons (`.login-demo__item`, no Sales2) | R |
| Permission matrix | `seed.sql:395-424` | `role_permissions` | §1.2 | R |
| Permission cache | `PermissionService`, `CacheService` | `grantedKeysForRole` | Memcached TTL 3600 | R |
| Session handler & fallback | `SessionManager::start` | `isRedisReachable` | redis else files (silent) | R |
| Cookie flags | `SessionManager::start/isHttps` | cookie params | HttpOnly, Lax, Secure only prod | R |
| Idle timeout | `AuthService::currentUser` | `last_activity` | `time()-last>SESSION_LIFETIME` ⇒ destroy | R |
| Session regeneration | `AuthService::login` | `regenerate()` | `session_regenerate_id(true)`, csrf reset | R |
| Deactivation kills session | `AuthService::currentUser` | `!$user->isActive` | session destroyed | R |
| Logout POST+CSRF | `routes.php`, `AuthController::logoutAction`, `main.php:318` | `/logout` POST | not GET | R |
| Login failure status | `AuthController::loginAction` | view re-render | 200 + generic msg | R |
| CSRF contract | `BaseController::requireCsrf/csrfToken` | `_csrf_token` | stable per session; 400 HTML | R |
| Guard order | `BaseController::requirePermissionWithCsrf` | — | auth→perm→csrf | R |
| 401 only on API | `ProductApiController` | `/api/products/{sku}/availability` | JSON 401/404 | R |
| ID obfuscation | `IdObfuscator`, `config/global.php REGEX_ID_TOKEN` | encode/decode | hex(key+hex(id)); `[0-9a-f]+` | R |
| 404 for unknown route | `public/index.php` | renderErrorPage 404 | HTML (JSON for `/api/`) | R |
| 500 presentation | `public/index.php` catch | renderErrorPage 500 | "An internal error occurred. Please try again later."; JSON `{"error":"internal_error"}` for /api | R |
| No-query-string rule | `BaseController::requestParam`, `routes.php` `/search` | POST only | GET params ignored | R |
| Compose collisions | `docker-compose.yaml` | container_name/ports/volumes | §1.6 | R |
| Dev `.env` bleed | `public/index.php` Dotenv immutable, compose bind mount | — | env vars win but file still mounted | R |
| MinIO external/shared | `config/global.php`, `MinioClient::IOMS_PREFIX` | `ioms/` prefix | shared bucket | R |
| Seed date-relative | `seed.sql` PO/SO inserts | `CURDATE()` | fresh seed shifts windows | R |
| Product validation | `ProductService::validateCreatePayload` | lines 369-440 | §1.5 | R |
| Image validation | `ImageUploadService::uploadInvalidInfo` | finfo | magic-byte; 2 MiB; WebP re-encode | R |
| Category rules | `CategoryService` 346-445 | validateName/resolveCode | §1.5 | R |
| Category AJAX status | `CategoryController::statusForResult` | 422/500 | JSON | R |
| Warehouse/Customer/Supplier/User rules | respective `*Service::validate*` | — | §1.5 | R |
| Self guards | `UserService::update/setActive` | — | own role/own deactivate | R |
| PO transitions | `PurchaseOrder::canBeCancelled`, `PurchaseOrderService`, `GoodsReceiptService` | — | §1.5 | R |
| PO permission split | `PurchaseOrderController` | `purchase_orders.manage/submit/cancel` | submit/cancel Admin only | R |
| SO transitions & SOD | `SalesOrderPolicy`, `SalesOrderService`, `SalesOrderController` | assertCanDecide/Cancel/Issue | role-based SOD; admin self-approve OK | R |
| SO issue integrity | `GoodsIssueService::executeIssuanceTransaction` | FOR UPDATE | rollback on insufficient | R |
| SO ownership 403 | `SalesOrderController::showAction` | — | Sales other's → 403 | R |
| Ledger SQL | `StockLedgerMySQLRepository::findFiltered` | `qty_after` subquery | running sum; no before/after columns | R |
| Ledger controller | `StockLedgerController` | PER_PAGE 25, `isXhr` | AJAX JSON `{tbody,page,totalPages,total}` | R |
| CSV | `CsvExportService`, `ReportController` | export* | §1.5 | R |
| Report params | `ReportService::normalizeParams`, `ReportController` | types, 5/page, 30-day default | R |
| Notifications global | `NotificationController`, schema `notifications` | markAllRead | global; Admin+WH | R |
| Low-stock semantics | `scripts/check-low-stock.php` | HAVING SUM<reorder | total across warehouses | R |
| Dashboard role split | `DashboardController` | role branches | Admin/Sales/WH stats; notifications Admin+WH | R |
| Sales dashboard | `SalesDashboardController` | periods | today/week/month/all | R |
| Legacy harness facts | `tests/e2e/*` | — | non-Playwright-Test scripts | L |
| Custom dialog/toast markup | `public/assets/js/app.js:112-330` | `confirm()`, `toast()` | `role=alertdialog #__confirmOk`; toast `aria-live=polite` | R |
| Row identity | `grep` of `views/` | `<tr` w/ attrs | only 2 `<tr>` carry attributes (no business keys) | R |
| Order date defaults | `purchase/form.php:104`, `sales/form.php:67` | `date('Y-m-d')` | server-date default | R |
| No pagination on 4 master lists | `views/master/{warehouses,customers,suppliers,users}/list.php` | — | 0 `pagination` markers; controllers have no page param | R |
| No IOMS CI | `.github/workflows/*` | — | none for IOMS | R |
| Concurrency tests exist in PHPUnit | `tests/Integration/*Concurrency*` | — | model for invariants | R |

## 28. COMPLETE MODULE TEST MATRIX
Legend — **Role:** A=Admin, S=Sales, W=Warehouse, S2=Sales2, G=Guest, *=all. **Data:** O=test-owned, Sd=seed (read-only), — none. **Par:** P=parallel-safe, Ser=serial inside aggregate, Ex=`exclusive` project.

### Authentication
| Area | Scenario | Role | Tag | Data | Par | Expected |
|---|---|---|---|---|---|---|
| Login | each active role signs in (A,S,W,S2) | * | @smoke @security | Sd users | P | 302→`/dashboard`; user menu shows name+role |
| Login | demo-fill buttons fill email/password (3 roles) | G | @regression | — | P | inputs populated; submit works |
| Login | wrong password / unknown email / **inactive user** | G | @security @validation | O user | P | 200, exact generic message, no session cookie auth |
| Login | empty email / empty password | G | @validation | — | P | 200, same message; HTML5 required also tested |
| Login | invalid CSRF / missing CSRF / stale pre-login CSRF | G | @security | — | P | 400 CSRF message |
| Login | already authenticated visit `/login` | * | — | — | P | 302 `/dashboard` |
| Login | session cookie regenerated on login (id before≠after), flags HttpOnly/SameSite=Lax | * | @security | O | P | cookie id changes; attributes asserted |
| Login | role landing: Sales uses `/dashboard` renders sales stats | S | — | — | P | 200 |
| Logout | POST /logout w/ CSRF destroys session; old cookie redirected | * | @security | own session | P | 302 `/login`; protected page 302 |
| Logout | logout without CSRF | * | @security | own session | P | 400, still logged in |
| Logout | GET `/logout` | * | @regression | — | P | 404 (route not defined for GET) — *documents legacy mismatch* |
| Session | idle expiry (short-session app) | * | @security @needs-isolated | O | Ex | after > lifetime ⇒ 302 `/login` |
| Session | deactivation invalidates live session | A+O user | @security @workflow | O user | Ser | victim's next request → `/login`; reactivation restores login |
| Session | role change applied mid-session (sync from DB) | A+O user | @security | O user | P | access matrix changes without relogin |
| Session | Redis handler proof (session key present) | A | @needs-isolated | — | setup | `PHPREDIS_SESSION:*` key exists |
| Session | Sales2/Sales parallel sessions independent | S,S2 | @security | — | P | each sees only own SOs |
| Users | self-role-change & self-deactivate blocked (see Users) | A | @security | — | P | exact messages |

### Dashboard
| Area | Scenario | Role | Tag | Data | Par | Expected |
|---|---|---|---|---|---|---|
| Admin | KPI cards present; each value equals source surface (products total, PO/SO status counts, inventory value) | A | @smoke | Sd | Ex | cross-surface equality |
| Admin | quick actions (Add User etc.) links reach 200; dead "Generate Summary Report" documented | A | @regression @quirk | — | P | link target 404 recorded |
| Admin | low-stock block lists product after job run | A | @needs-isolated | O product low | Ex | row visible |
| Admin | transactions/activity & queues sections render; empty-state variants | A | — | Sd | Ex | sections visible |
| Admin | notifications block + bell badge | A | @needs-isolated | job | Ex | counts consistent |
| WH | 3 stat cards + goods-issue queue lists Approved SOs; first link opens SO w/ Issue action | W | @regression | O SO | P | queue contains owned approved SO |
| WH | reports link / quick actions | W | — | — | P | 200 |
| Sales | role-specific stat cards scoped to own orders; no admin-only quick actions | S | @rbac | O | P | no "Add User" |
| Dashboard | no PHP leak / no console errors on all roles | * | @regression | — | P | clean |
| Dashboard | refresh reload keeps consistency | * | — | — | P | same values |

### Sales Dashboard
| Period selector today/week/month/all (POST `period`) | S,A | @regression | O | P | totals differ/scoped; selection persists; unknown value → month |
| WH access | W | @rbac | — | P | 403 |
| Export button `#so-export-btn` downloads orders.csv (own only for S) | S,A | @export | O | P | CSV scope |
| Recent orders/top customers scoped to owner | S | @rbac | O | P | only own |

### Products
| Area | Scenario | Role | Tag | Data | Par | Expected |
|---|---|---|---|---|---|---|
| List | renders seed 100 products; default hides inactive | * | @smoke @seed-dependent | Sd | P | rows, KPI cards |
| List | filters: SKU, name (substring), category (multi), warehouse (multi), stock status ×4; combined | * | @crud | O | P | exact subset |
| List | per-page 10/25/50/100; invalid per_page → 10 | * | — | Sd | P | row counts; pagination footer text |
| List | pagination: next/prev/boundary pages; page beyond last; filter persistence | * | — | Sd | P | consistent summary ("of 0" never with rows) |
| List | no-results state & empty-state | * | — | O | P | empty row |
| List | KPI metrics unaffected by filters | * | — | Sd | P | totals constant |
| List | inactive filter shows deactivated owned product | A | @crud | O | P | visible only there |
| List | warehouse filter column shows selected-warehouse qty | * | @regression | O stock | P | qty equals warehouse stock |
| List | role UI: Add/row-actions only Admin | * | @rbac | — | P | hidden for S/W |
| Create | happy path all fields; redirect `/products`; persisted values | A | @crud | O cat | P | row visible |
| Create | required: SKU, name, unit, category (exact msgs) | A | @validation | O | P | 400 + messages |
| Create | SKU: lowercase→upper-case; max 30 ok / 31 rejected; spaces trimmed; symbol chars accepted (**quirk**: no format rule) | A | @validation @quirk | O | P | stored upper-case |
| Create | SKU uniqueness (case-insens. via upper-case) | A | @validation | O | P | "This SKU is already in use." |
| Create | name 3 / 150 ok; 2 / 151 rejected; multibyte byte-length (**quirk**) | A | @validation @quirk | O | P | message |
| Create | description 500 ok / 501 rejected | A | @validation | O | P | message |
| Create | prices: negative, non-numeric, sale<purchase (reject), equal (ok), decimals normalised to 2dp | A | @validation | O | P | messages |
| Create | reorder 0 ok; negative rejected | A | @validation | O | P | message |
| Create | barcode optional (no validation) | A | — | O | P | persists |
| Create | category must exist (tampered category id) | A | @security | O | P | "Selected category does not exist." |
| Create | initial stock: wh+qty → Adjustment ledger row & stock; qty only / wh only silently ignored (**quirk**) | A | @workflow @quirk | O wh | P | ledger row; no row |
| Create | duplicate submit (double click / two parallel POST) | A | @security | O | P | one product, one error |
| Create | non-Admin POST/GET | S,W | @rbac | — | P | 403 |
| Image | valid PNG/JPEG/WebP accepted → `.webp` URL, object fetchable | A | @upload | O | P | image visible |
| Image | invalid binary, empty, GIF, renamed text→.png rejected | A | @upload @validation | O | P | exact messages, 400, form preserved |
| Image | PNG named .jpg accepted (ext ignored) | A | @upload | O | P | accepted |
| Image | >2 MiB rejected; ≤2 MiB ok; 2000×1500 scaled ≤1200 | A | @upload | O | P | message / dims |
| Image | replacing image deletes old object; failed validation leaves no orphan | A | @upload | O | P | old URL 404 |
| Edit | prefilled form; unit preselected; save persists; invalid keeps edit mode (action `…/update`) | A | @crud @regression | O | P | values |
| Edit | SKU unique excluding self | A | @validation | O | P | ok/err |
| Edit | invalid/tampered/raw id; valid token of other entity | A | @security | O | P | 404 |
| Edit | non-Admin | S,W | @rbac | — | P | 403 |
| State | deactivate/activate; hidden from default list, PO/SO line dropdowns; existing orders keep it | A | @workflow | O | Ser | state + dropdown membership |
| Detail | fields, stock-by-warehouse breakdown, last movement, links | * | @crud | O stock | P | values equal ledger |
| API | availability: 200 JSON shape (sku,total_stock,availability[active warehouses only]); unknown SKU 404 JSON; guest 401 JSON; lowercase SKU; cache staleness after stock change (**risk R-9**) | * | @security | O | P | per contract |

### Categories
| List | filter name/code/status (multi), sort options, per-page 10/25/50, pagination, empty/no-results | * | @crud | O | P | subset |
| List | KPI cards; SKU-count link → `/products` count equal | * | @regression | O | P | equal |
| List | role UI (Add/row actions Admin only) | * | @rbac | — | P | hidden |
| Create (modal) | happy, explicit code; persisted; no page reload quirk handled | A | @crud | O | P | 200 `{ok:true}` + row |
| Create | required name; name 3–80 bounds; description 250 | A | @validation | O | P | 422 exact msgs |
| Create | code format (3–20 `[A-Z0-9-]`), lowercase→upper, invalid chars, dup code, dup name (case-insens.) | A | @validation | O | P | 422 msgs |
| Create | omitted code → auto `CAT-…` format (single-threaded test only) | A | @crud @quirk | O | Ser | code matches pattern |
| Edit | via modal & via `/categories/{token}/edit` deep link; unique excluding self | A | @crud | O | P | prefilled |
| State | deactivate/activate; inactive hidden from product category dropdown | A | @workflow | O | Ser | dropdown |
| Delete | hard delete empty category (confirm dialog); guard with assigned product 422; Delete button disabled w/ SKUs | A | @workflow | O | P | JSON/row gone |
| Export | CSV headers, filters honoured, **injection** prefix on name/description, any role can export | * | @export @security | O | P | per §3.23 |
| RBAC | direct POST create/update/delete/(de)activate | S,W | @rbac | — | P | 403 |
| CSRF | missing CSRF on JSON endpoints → 400 HTML | A | @security | O | P | 400 |

### Warehouses
| List | filters code/name/location/status; unpaginated; empty; role UI | * | @crud | O | P | subset |
| Create | happy; code uppercased; required code/name; duplicate code **on create** ([C] confirm) ; over-length code (**quirk 500**) | A | @validation @quirk | O | P | msgs / semantic 500 |
| Edit | persist; dup code excl. self; failed edit stays edit mode | A | @crud @regression | O | P | values |
| State | deactivate/activate (no stock guard — **quirk**); inactive excluded from PO/SO/product dropdowns & ledger filter | A | @workflow | O | Ser | dropdown |
| Detail | stats cards; stock table 10/page via POST `stock_page`; page 2 differs; URL no `?` | * | @crud | Sd | P | pagination |
| RBAC | create/edit/state | S,W | @rbac | — | P | 403 |

### Customers
| List/filters name/contact/phone/email/status; role UI; WH 403 on list+detail | * | @rbac | O | P | |
| Create | happy; required name; email format; optional fields blank; persistence | A | @validation | O | P | msgs |
| Edit/State | persist; invalid stays edit; deactivate hides from SO form; active customers only | A | @workflow | O | Ser | |
| Detail | fields/links; WH 403 | A,S | @rbac | O | P | |
| RBAC | Sales create/edit/state | S | @rbac | — | P | 403 |
| Quirk | name >150 → 500 | A | @quirk | O | P | semantic |

### Suppliers
| List filters name/contact/email/status; all roles view; manage Admin | * | @rbac | O | P | |
| CRUD + validation (name, email) | A | @validation | O | P | msgs |
| State + **PO create dropdown integration** (inactive absent; reactivate returns) | A | @workflow | O | Ser | |
| RBAC | S,W manage | @rbac | — | P | 403 |

### Users
| List filters name/email/role(multi)/status(multi) | A | @crud | O | P | |
| Create | happy for each role; login works with new credentials | A | @crud @workflow | O | P | |
| Create | name required; email format/unique (case-insens.); role enum; password <6 rejected, =6 ok; | A | @validation | O | P | exact msgs |
| Edit | name/email/role change; blank password keeps old; new password works; failed edit stays edit | A | @crud @regression | O | P | |
| Guards | self-role-change & self-deactivation | A | @security | Sd admin (**read-only attempt**) | P | exact msgs, no mutation |
| State | deactivate → cannot log in; live session killed; reactivate | A | @security @workflow | O | Ser | |
| Obfuscation | edit href/action never contain raw ids | A | @security | O | P | |
| RBAC | S,W GET/POST everything | * | @rbac | — | P | 403 |

### Purchase Orders
| List | filters order number, supplier, status (multi), warehouse (multi); sort date asc/desc survives pagination; 10/page; empty | A,W | @crud | O | P | |
| List | role: Sales 403 | S | @rbac | — | P | 403 |
| Create | happy (supplier, warehouse, date, 1..n lines); redirect to token URL; Draft; totals | A,W | @crud @workflow | O | Ser | |
| Create | validation: no lines, qty 0/neg, price neg/non-numeric, inactive supplier/warehouse/product (tampered select), invalid date, empty date, duplicate lines (allowed) | A | @validation | O | P | exact msgs, 400 |
| Create | double-submit | A | @security | O | P | single PO |
| Submit | Admin Draft→Ordered; WH 403; Sales 403; non-Draft 400 | A,W,S | @rbac @workflow | O | Ser | |
| Cancel | Draft/Ordered/PartiallyReceived ok (A); WH 403; Received/Cancelled 400; **no stock reversal after partial receipt** | A | @workflow | O | Ser | |
| Receive | form `max`=remaining; partial (8/20) → PartiallyReceived; second partial; full → Received; Receive/Cancel buttons disappear | A,W | @workflow | O | Ser | |
| Receive | over-receipt via UI max & direct POST (999) → 400 exact; negative/zero/blank lines; unknown line id ("Invalid purchase order line item."); no lines ("Enter a quantity for at least one line item.") | A,W | @validation | O | P | |
| Receive | on Draft/Received/Cancelled PO → 400 | A | @workflow | O | P | |
| Invariants | each receipt: stock +n (product detail & warehouse detail), ledger Receipt rows (+qty, ref PO link, Done By = actor), progress tiles, audit timeline | A,W | @workflow | O | Ser | |
| Concurrency | two simultaneous receipts same PO (A+W) never exceed; one success one 400; ledger/stock consistent | A,W | @concurrency | O | P | invariants |
| Concurrency | cancel racing receive | A,W | @concurrency | O | P | consistent final state |
| Security | tampered/raw/forbidden ids; stale/missing CSRF on every POST | A | @security | O | P | per §29 |
| Integration | newly created supplier/warehouse/product appear in dropdowns; seeded inactive don't | A | — | O | P | |

### Sales Orders
| List | filters order no., customer, status (multi), warehouse (multi); sort; paginate; role scope: S own only, A/W all | * | @crud @rbac | O | P | |
| Create | happy (customer, warehouse, date, lines; price auto-fills from product); Draft; redirect token | A,S | @crud @workflow | O | Ser | |
| Create | validation: no lines, qty ≤0, price neg, inactive customer/warehouse/product, invalid date; chosen items survive failure; WH 403 | A,S,W | @validation @rbac | O | P | |
| Create | **no stock check at creation** (qty > stock accepted) | A | @quirk | O | P | created |
| Submit | creator only; Admin non-creator 400; non-Draft 400 | A,S | @workflow | O | Ser | |
| Approve | Admin ok; Admin own SO ok; Sales (own & other) **403** UI absent + direct POST; WH 403; non-Pending 400 | A,S,W | @rbac @security | O | Ser | exact SOD message |
| Reject | Admin with reason / without; Sales 403; reason displayed; becomes Cancelled | A,S | @workflow | O | Ser | |
| Cancel | Sales own Draft/Pending ok; Sales Approved 400; Admin any non-final; Fulfilled/Cancelled 400; non-creator 400 | A,S,S2,W | @workflow | O | Ser | |
| Issue | WH/Admin on Approved → Fulfilled; stock −qty; ledger Issue negative; Issued By; Sales 403; non-Approved 400 | A,W,S | @workflow | O wh+product | Ser | |
| Oversell | insufficient stock → 400 exact, SO stays Approved, **no partial change** (multi-line: line1 ok / line2 short ⇒ nothing deducted, ledger unchanged) | W | @workflow @regression | O | Ser | rollback |
| Concurrency | double issue same SO ⇒ exactly one succeeds; two SOs competing for the last unit ⇒ one Fulfilled | W,A | @concurrency | O | P | invariants |
| Isolation | Sales2 vs Sales1: GET **403**, submit/cancel **400**, list excludes, export excludes, dashboard excludes | S,S2 | @rbac @security | O | P | |
| Security | tampered/raw/forbidden token; missing/stale CSRF; double-submit on approve/issue | A,W | @security | O | P | |

### Stock Ledger
| RBAC | Sales 403 (GET + AJAX POST), Admin/WH 200 | * | @rbac | — | P | |
| List | 9 columns, date format `DD Mon YYYY`, signs (Issue uses `−`), Done By resolved | A,W | @smoke | O | P | |
| Filter | SKU, product name, type (multi), warehouse (multi), combos, no results | A | @crud | O | P | scoped to owned SKU |
| Sort | each column asc/desc; stable; keeps filter; resets page | A | — | O | P | |
| Pagination | 25/page; next/prev/boundaries; `Page X of Y`; no `?` in URL | A | — | Sd | P | |
| AJAX | JSON shape, `X-Requested-With`, error path (`query_failed`) semantic | A | @regression | — | P | |
| Arithmetic | per row after−before==qty; per product+warehouse cumulative sums; final after == product stock | A | @workflow @regression | O | P | |
| Movements | Receipt(+), Issue(−), Adjustment(+ initial) types, reference links resolve (PO/SO tokens, no raw ids), actor == Done By | A | @workflow | O | P | |
| Same-timestamp tie-break by id | A | @quirk | O | P | order stable |

### Reports
| Types | `stock_valuation|inventory_aging|slow_moving|movement_ledger` each 200, distinct, no PHP leak; sort options per type | A,W | @crud | Sd | P | |
| Params | date range, warehouse, category, search `q`, page (5/page), invalid dates → defaults, KPI follows warehouse | A | @regression | O | P | |
| Params | `GET /reports?…` query ignored | A | @regression | — | P | |
| Empty | no-match shows single empty row | A | — | O | P | |
| Tabs/charts | tab semantics (`role=tab`), keyboard | A | @a11y | — | P | |
| Role | Sales orders-only view (no analytics); WH/Admin full | * | @rbac | — | P | |
| Export ledger | A/W 200; S 403; missing dates 400 exact; warehouse/category/q scope; header exact; rows == filtered ledger; CRLF | A,W,S | @export | O | P | |
| Export orders | A (SO+PO) / S (own SO only) / W 403; warehouse scope; date scope (inclusive); header exact; Items Count/Total Value correct | A,S,W | @export | O | P | |
| Injection | owned product/customer/supplier/user names `=…`,`+…`,`-…`,`@…`,TAB,leading space,comma,quote,newline → prefixed/quoted; Quantity exempt | A | @export @security | O | P | |
| Download | GET export route not CSV | A | @security | — | P | |
| Modal | ReportModal Escape/backdrop | A | @a11y | — | P | |

### Profile
| View own data; role shown read-only (not posted) | * | @crud | O user | P | |
| Update name; email; success flash; persists; session user_name updates | * | @crud | O user | P | |
| Validation: empty name, bad email, email taken by other, email unchanged (self excluded) | * | @validation | O | P | exact msgs |
| No password change field; role tamper in POST ignored | * | @security | O | P | role unchanged |
| CSRF/guest | G | @security | — | P | 302 / 400 |

### Notifications
| Bell hidden for Sales; visible Admin/WH with unread badge & list | * | @rbac | job | Ex | |
| Dashboard notification block lists unread | A,W | — | job | Ex | |
| Mark all read → redirect to Referer path (default `/dashboard`; `/update`→`/edit`; `/search|/stock` stripped); badge 0; empty state | A,W | @regression | job | Ex | exact Location |
| Sales / guest / missing CSRF direct POST | S,G | @security | — | P | 403 / 302 / 400 |
| Low-stock job idempotent (dedupe) & creates for new low product | A | @needs-isolated | O product | Ex | one unread per product+warehouse |
| Global read state (W reads → Admin sees read) | A,W | @quirk | job | Ex | documented |

### Cross-cutting (Agent 2 unless noted)
| Guest GET every protected route → 302 `/login`; guest POST → 302; API → 401 JSON | G | @rbac | — | P | |
| RBAC GET+POST matrix (§3.5) generated from one table-driven spec | A,S,W | @rbac | O ids | P | exact codes |
| CSRF: missing/invalid/stale/other-session on a representative POST per module + all state transitions | * | @security | O | P | 400 |
| Tampered/malformed/raw/forbidden ids on every `{id}` route family | * | @security | O | P | 404/403 |
| Cross-user ownership (SO) | S,S2 | @security | O | P | 403/400 |
| Disabled resource selection (inactive supplier/customer/warehouse/product/category in forged POST) | A | @security | O | P | exact msgs |
| Double-submit (create/approve/issue/receive) | A,W | @security | O | P | single effect |
| Error pages: 404 route, 403 page, 400 page, 500 presentation (forced via oversize input quirk) styled & navigable | * | @regression | — | P | layout-less styled page + links |
| No-query-string rule sweep + forms method=post (legacy parity) | * | @regression | — | P | |
| Crawl: BFS of reachable links per role, no PHP leak/console error/≥500; links shown to role never 403 (legacy parity) | * | @regression | — | P | |
| Responsive 360 px no horizontal page scroll (legacy parity) | * | @regression | — | P | |

## 29. SECURITY MATRIX (executed by Agent 2 from table-driven specs)
| Attack / check | Target set | Method | Expected |
|---|---|---|---|
| Guest access | every non-login route (from `routes.php` inventory) | GET/POST no cookie | 302 `/login`; API 401 JSON |
| Unauthorised navigation | role × route table (§3.5) | GET | exact code |
| Unauthorised POST | every POST route × forbidden role (valid CSRF) | `request.post` | 403 (before id decode) |
| Missing CSRF | every POST route (authorised role) | no token | 400 message |
| Invalid CSRF | same | random token | 400 |
| Stale CSRF | login, logout/relogin flows | pre-login token | 400 |
| Other-session CSRF | role A token in role B session | — | 400 |
| Tampered id | every `{id}` family | flip nibble | 404 |
| Malformed id | `zz`, empty-ish, very long hex | — | 404 |
| Raw numeric id | `1`, `0`, `999999` | — | 404 (403 first for forbidden roles) |
| Forbidden-resource token | Sales2→Sales1 SO | GET/POST | GET 403, POST 400 |
| Disabled resource selection | forged POST with inactive ids | — | exact validation msgs |
| Double-submit | create/approve/issue/receive | 2 parallel posts | one effect |
| Session fixation | pre/post-login cookie | — | id changes |
| Session invalidation | deactivate live user | — | redirect login |
| Inactive login | deactivated user credentials | — | generic error |
| Open redirect | mark-all-read Referer | external URL / protocol-relative | path-only redirect (`parse_url` PHP_URL_PATH) |
| CSV injection | every exported free-text column | owned hostile names | prefixed |
| Upload type confusion | image upload | magic-byte cases | per §3.22 |
| XSS reflection (basic) | names/descriptions rendered in lists, detail, ledger, CSV | `<img src=x onerror=…>` owned data | text escaped; no dialog/script executed |
| Error info leak | forced 500 / 404 / API 500 | — | no paths/stack/SQL |


# 6. MULTI-AGENT ARCHITECTURE

**Model:** Agent 0 (Coordinator) + Agent 1 (Foundation) first; then six domain agents in parallel against a **frozen contract**; then Agent 8 (audit). Agents never edit each other's paths. Shared change = request file, not an edit.

**Governance rules (all agents):**
1. Read `FOUNDATION_CONTRACT.md` before writing a line; the contract version is stamped in each completion report.
2. Import `test`/`expect` **only** from `support/fixtures`; naming only via `uid`; data only via `factories`; no `page.waitForTimeout`; tags only from `support/tags.ts`.
3. A domain agent may create **only** files under its owned paths (§7). Anything else → `requests/SHARED_CHANGE_REQUEST-<agent>-<n>.md` (fields: required helper · reason · proposed API · consumers · affected files). Agent 0/1 integrates centrally within one Wave-2 integration cycle; the agent continues with a local stub **inside its own path** (`pages/<domain>/_local-*.ts`, deleted when the shared helper lands).
4. Selector gaps → `requests/SELECTOR_REQUEST-<agent>-<n>.md` using the table in §13. **No agent edits `views/**` or `public/assets/**`.** Hooks are applied by Agent 1 in one reviewed batch ("Behavior change: No — attribute-only").
5. Discovered application defects are **reported, not worked around silently**: assert implemented behaviour tagged `@quirk` and list in the completion report.
6. A task is done only when its verification command passes **twice in a row** from a clean `E2E_RESET=always` run (Mode A) and the completion report (§41-format) is filed.

## Completion-report format (mandatory, `reports/AGENT-<n>-COMPLETION.md`)
Agent · Scope · Files created · Files modified · Tests added · Shared dependencies used · Shared change requests · Selector changes required · Commands run · Results (pass/fail counts) · Known limitations · Risks · **Ready for integration? YES/NO (+ exact reason)**.

# 7. AGENT OWNERSHIP MATRIX
| Agent | Workstream | Owned paths (exclusive write) | Dependencies | Can start in | Integration owner |
|---|---|---|---|---|---|
| **0** Coordinator/Architect | Architecture, plan, allocation, shared-change & selector integration (with A1), coverage/parity audit gates, final stabilization | `docs`-level plan files, `requests/**` triage, `reports/INTEGRATION-*.md`; co-owner of shared files | approval of this spec | Wave 0 (now) | self |
| **1** Foundation | Package, config, isolated env, modes, global setup/teardown, auth setup, fixtures, uid, diagnostics, csrf/request/ids/csv/files helpers, factories, shared components, tags, selector-hook batch, FOUNDATION_CONTRACT, smoke spec | `package.json, tsconfig, playwright.config.ts, .eslintrc*, env/**, global/**, setup/**, support/**, factories/**, components/**, fixtures/**, scripts/**, FOUNDATION_CONTRACT.md, specs/_smoke/**` + (batched) selector attributes in `views/**` | A0 approval | Wave 1 | A0 |
| **2** Auth/Security/RBAC | Login/logout/session/CSRF/ID-tamper/RBAC matrices/guest/error pages/crawl/no-query-string/double-submit | `specs/auth/**, specs/security/**, specs/rbac/**, pages/auth/**, exclusive/auth/**` | G1 contract (may do read-only inventory in Wave 1) | Wave 1 (inventory) → Wave 2 | A0 |
| **3** Master data | Products, Categories, Warehouses, Customers, Suppliers, Users, product image upload, availability API, categories CSV | `specs/{products,categories,warehouses,customers,suppliers,users}/**, pages/{products,categories,warehouses,customers,suppliers,users}/**` | G1 | Wave 2 | A0 |
| **4** Purchase Orders | PO full lifecycle, receipt, concurrency | `specs/purchase-orders/**, pages/purchase-orders/**` | G1 (+factories) | Wave 2 | A0 |
| **5** Sales Orders | SO lifecycle, SOD, isolation, issue/oversell, concurrency | `specs/sales-orders/**, pages/sales-orders/**` | G1 (+factories) | Wave 2 | A0 |
| **6** Ledger/Reports/Export | Stock Ledger, Reports, exports, CSV injection | `specs/stock-ledger/**, specs/reports/**, pages/{stock-ledger,reports}/**, exclusive/{reports,stock-ledger}/**` | G1; needs stable PO/SO/product factories (A1) and cross-reads from A4/A5 reference expectations | Wave 2 | A0 |
| **7** Dashboard/Profile/Notif/A11y | Role dashboards, sales dashboard, profile, notifications, axe scans, keyboard flows, responsive | `specs/{dashboard,profile,notifications,a11y}/**, pages/{dashboard,profile,notifications}/**, exclusive/{dashboard,notifications}/**, a11y/known-issues.json (proposal only; approval by A0)` | G1 | Wave 2 | A0 |
| **8** Parity/Quality audit | Read-only audits + audit scripts (lint rules proposals) | `reports/AUDIT-*.md`, `tests/playwright/audit/**` (read-only scripts) | W2 complete + W3 | Wave 4 | A0 |

**Shared-file single owners:** `playwright.config.ts`, `global/*`, `setup/*`, `support/*`, `factories/*`, `components/*`, `env/*`, `fixtures/*`, `package.json`, tag list, diagnostics → **Agent 1 (Agent 0 approves)**. `views/**` selector attributes → **Agent 1 batch only**. Any other agent touching these = integration-gate failure.

# 8. SHARED FOUNDATION CONTRACT — DESIGN (content of `FOUNDATION_CONTRACT.md`, frozen at Gate G1)
1. **Env variables:** `E2E_MODE` (isolated|existing — required), `E2E_BASE_URL` (existing only; isolated forces `http://127.0.0.1:18090`), `E2E_RESET` (always|fast|never), `E2E_KEEP`, `E2E_WORKERS`, `E2E_CHROMIUM_PATH`, `E2E_SHORT_SESSION_URL` (isolated: `:18091`), `E2E_I_UNDERSTAND_NO_DETERMINISM`, `E2E_DEBUG_ARTIFACTS`.
2. **Roles:** `'admin'|'sales'|'warehouse'|'sales2'` + credentials table; guest = no state.
3. **Fixtures:** names/signatures in §3.12–3.13; `test` & `expect` re-export; `loginAs` semantics; "never log out shared sessions".
4. **uid API & field bounds** (§3.14), **dates API**.
5. **Factory API** (all return `{ …fields, token, … }`, never raw numeric ids except where a form needs an option value read from the DOM): `categories.create({code?,name?,active?})`, `products.create({category?,sku?,price?,stock?:{warehouse,qty},active?})`, `warehouses.create()`, `suppliers.create()`, `customers.create()`, `users.create({role,active?})` (returns credentials), `purchaseOrders.create({supplier?,warehouse?,lines:[{product,qty,price}]}) / .submit() / .receive(lines) / .cancel()`, `salesOrders.create(as:'sales'|'sales2'|'admin',{…}) / .submit() / .approve() / .issue() / .reject()`, `stock.seed({product,warehouse,qty})` (via product initial stock or PO receipt), `world.create()` = isolated mini-world (own warehouse+category+product(s)+supplier+customer) for workflow/concurrency specs. Factories use the same HTTP+CSRF path as the UI (so they exercise real validation) and **assert success**; token discovery strategy per entity documented (category JSON `category.id`; PO/SO redirect `Location`; others via POST `/…/search` + row href parse).
6. **Helper APIs:** csrf/request/ids/csv/files/db (§3.20–3.23, DB oracle read-only: `db.stock(productSku, warehouseCode)`, `db.ledgerSum(...)`, `db.assertInvariants()`; throws `ModeBUnavailable`).
7. **Tag taxonomy** (§3.26) and tag→project routing rules; `exclusive/` directory rule.
8. **Selector conventions:** priority list; allowed existing ids; `data-testid` naming `kebab-case` `<area>-<element>`, `data-row-key` = business key; request process.
9. **Parallelism rules:** §3.15; serial only inside a single aggregate describe.
10. **Diagnostics:** auto fixture behaviour; `diagnostics.expect500()`; PHP-leak guard.
11. **Messages registry** `support/messages.ts`: contract-level strings copied verbatim from source (single place; a contract-drift check compares against `app/**` constants — Agent 8 audit).
12. **Shared-file modification process** (§6 rule 3) and versioning (`CONTRACT_VERSION`), change log.

# 9. TASK DEPENDENCY GRAPH
```
Wave 0:  A0.1 residual verification ─► A0.2 approval gate (YOU)
                    │
Wave 1:  F1.1 package ─┬► F1.2 isolated stack ─► F1.3 modes+guards ─► F1.5 global setup/teardown ─┐
                       ├► F1.4 playwright.config ─────────────────────────────────────────────────┤
                       ├► F1.7 uid/dates/tags/roles/messages ─► F1.8 diagnostics ─┐              │
                       └► F1.9 csrf/request/ids ─► F1.10 factories ─► F1.11 components/csv/files ─┤
                       F1.6 auth.setup + fixtures (needs F1.4,F1.5,F1.7) ───────────────────────►─┤
                       F1.12 DB oracle + low-stock helper (needs F1.2,F1.3) ─────────────────────►┤
                       F1.13 smoke + FOUNDATION_CONTRACT + selector batch #1 ───────────────────►─┘
                                              (A2 read-only inventory runs in parallel)
                                                            ▼
                                                  ★ GATE G1 (frozen contract) ★
                                                            ▼
Wave 2 (parallel, no cross-agent file edits):
   A2 Auth/Sec ─┐                      ┌─ requests/ (shared change + selector) ─► A0/A1 integrate continuously
   A3 Master ───┤                      │
   A4 PO ───────┼─► per-agent completion reports
   A5 SO ───────┤        (A6 consumes A4/A5 factories only via A1's factories —
   A6 Ledger/Rep┤         no dependency on A4/A5 specs)
   A7 Dash/etc ─┘
                                                            ▼
                                  ★ GATE G2 (each agent green ×2, report filed) ★
Wave 3:  A0 integration: resolve requests ─► apply selector batch #2 ─► dedupe helpers ─► full regression (isolated, ×3)
                                                            ▼
                                                  ★ GATE G3 (full suite green ×3, 0 flaky) ★
Wave 4:  A8 parity audit + coverage audit + flaky-risk audit + lint/audit scripts
                                                            ▼
                                                  ★ GATE G4 (no open gaps) ★
Wave 5:  A0 + owners: close gaps ─► repeated runs (flake hardening) ─► completion report ─► (later, separate approval) CI + legacy retirement
```
Simultaneity: inside Wave 1 only F1.x with no edge between them; Wave 2 all six agents; Wave 4 audit streams A8.1–A8.6 in parallel.

# 10. PHASED DETAILED IMPLEMENTATION PLAN
| Phase | Content | Acceptance |
|---|---|---|
| **0 Baseline inventory** | A0.1 residual verification (§A0.1); route inventory table; role matrix; validation inventory; workflow inventory; legacy inventory (done: §15) | Every residual item has a verified finding or a documented `[C]`; **you approve this spec** |
| **1 Foundation** | F1.1–F1.13 | `E2E_MODE=isolated npx playwright test --grep @smoke` passes; proves dev volumes untouched (before/after `docker volume ls` + `docker ps` snapshot equal); Mode B refuses without ack; no `waitForTimeout`; contract frozen |
| **2 Shared components** | Part of F1.11 plus any approved SHARED_CHANGE_REQUESTs | ≥2 modules naturally reuse each component (checked by A8) |
| **3 Auth/Security** | S2.1–S2.8 | RBAC/CSRF/tamper/session/guest suites green; every `routes.php` route present in the RBAC table (generated-vs-routes drift check) |
| **4 Master data** | M3.1–M3.11 | all six modules pass independently (`--grep` per module) |
| **5 Purchase Orders** | P4.1–P4.6 | full state machine + stock/ledger invariants + concurrency |
| **6 Sales Orders** | O5.1–O5.7 | full state machine, SOD, isolation, oversell, rollback, concurrency |
| **7 Ledger/Reports** | L6.1–L6.6 | ledger arithmetic and all export contracts |
| **8 Dashboard/Profile/Notifications** | D7.1–D7.4,D7.7 | role UI + exclusive project green |
| **9 Accessibility** | D7.5–D7.6 | axe: 0 serious/critical outside approved allowlist; keyboard flows pass |
| **10 Full regression / flake hardening** | I0.1–I0.4 | 3 consecutive clean full runs, `failOnFlakyTests`, `--repeat-each=5` on all `@concurrency` + `@workflow`, worker sweep 1/2/4 |
| **11 Legacy parity** | A8.1–A8.6 | parity matrix 100 % "Covered" or approved exception |
| **12 CI (plan only)** | §16.5 plan | **not implemented** until you approve |

**Wave-0 residual verification A0.1 (read-only, before/at approval)** — items I did **not** read in full because the delegated research agents hit a rate limit; the owning agent re-confirms in its first task, but they do not change the architecture:
`views/**` detail/list markup per page (selector gaps, §13 is therefore provisional) · `public/assets/js/{stock-ledger,reports,dashboard,products,sales-orders,purchase-orders}.js` (debounce/polling/AJAX completion signals; notification bell polling) · `WarehouseService::create` duplicate-code check (line ~69) · repositories' search/sort columns for Warehouses/Customers/Suppliers/Users · `ReportService` per-type SQL & KPI definitions, `DashboardService`/`SalesDashboardService` KPI SQL · `CsvExportService` `id` locale reachability · `NotificationService`/bell markup · `EventLogService` coverage (DB-only). Each is mapped to a task below (`R:` prefix).

# 11. PER-AGENT DETAILED TASK LISTS
**Common to every task:** Parallel = "with all other tasks of the same Wave unless stated"; Production code touched = **No** unless stated; Verification always `cd tests/playwright && E2E_MODE=isolated E2E_RESET=always npx playwright test <path>` (T = that line); Acceptance always includes "completion-report row filed" and "passes twice consecutively".

## Agent 0 — Coordinator
* **A0.1 Residual verification** (Wave 0) — Deps: none · Files: appended to this plan · Scope: §10 list · Accept: each `[C]` resolved · Risk L · Blocks: A0.2.
* **A0.2 Approval gate** — blocked on you; **no implementation starts before explicit approval**.
* **I0.1 Request triage** (W2–3) — consume `requests/**`, decide, delegate to A1; SLA: each Wave-2 cycle (daily).
* **I0.2 Cross-domain regression** (W3) — full suite ×3, worker sweep; Accept: G3.
* **I0.3 Coverage audit** (W3/5) — module matrix (§28) ↔ spec inventory (§12): every row mapped to a spec; no orphan rows.
* **I0.4 Final completion report & CI plan hand-off** (W5).

## Agent 1 — Foundation (Wave 1; gate G1)
* **F1.1 Package & tooling** — Create `package.json` (scripts per §16), `tsconfig.json`, `.eslintrc.cjs` (rules: `no-restricted-properties` for `page.waitForTimeout`, restrict imports of `@playwright/test` outside `support/fixtures.ts`, forbid `support/compose` import from Mode-B-reachable files), `.gitignore`. Pin `@playwright/test`, `@axe-core/playwright`, `typescript`, `eslint`, `cross-env`. Verify: `npm ci && npx tsc --noEmit && npx eslint .`. Accept: lint rules proven by fixture violation tests. Risk L. Blocks: all.
* **F1.2 Isolated stack** — Create `env/docker-compose.e2e.yaml`, `env/e2e.env`, `env/README.md`. Content per §3.8 (+ `minio-init`, healthchecks, profile `session-expiry`). Scenarios: stack comes up from empty; `docker compose -p ioms-e2e ps` all healthy; dev containers/volumes snapshot identical before/after (script `scripts/assert-dev-untouched.sh`). Data: seed from repo SQL (mounted `:ro`). Risk **H** (Docker/Compose semantics). Verify: `docker compose -p ioms-e2e -f env/docker-compose.e2e.yaml --env-file env/e2e.env up -d --build --wait` then script. Accept: no `container_name`, no host ports except 18090/18091/19000/19001 on 127.0.0.1, **spike S-1..S-4 documented**: (S-1) `ID_OBFUSCATION_KEY`/env wins over absent `.env`, (S-2) MinIO upload+public GET works end-to-end, (S-3) PHP session cookie refresh/expiry behaviour for `SESSION_LIFETIME=3`, (S-4) server/MySQL timezone = UTC. Blocked-by F1.1. Blocks F1.3, F1.5, F1.12.
* **F1.3 Modes, guards, compose wrapper** — Create `support/env.ts`, `support/compose.ts` (rules 1–4 of §3.8), `resetIsolated()`. Scenarios (unit-style Playwright tests in `specs/_smoke/guards.spec.ts`, run without browser): unset mode fails; `existing` without ack fails; wrapper rejects `-p other`; reset refuses when a volume name lacks `ioms-e2e_` prefix (mock `docker volume ls`); `COMPOSE_PROJECT_NAME=iom` in env is scrubbed; Mode B import-graph test. Risk **H**. Accept: all guard tests green; code review by A0 mandatory. Blocks F1.5.
* **F1.4 playwright.config.ts** — projects `setup`/`parallel`/`exclusive`; timezone/locale; artifacts; retries; reporters; `workers`; `grep`/`grepInvert` for Mode B (`@needs-isolated|@seed-dependent`); chromium path override. Accept: `npx playwright test --list` shows no test duplicated across projects (script asserts unique test ids). Risk M. Blocked-by F1.1.
* **F1.5 Global setup/teardown** — Create `global/global-setup.ts`, `global-teardown.ts`; per §3.11; invariant gate SQL; log scan; stack stop. Scenarios: broken seed (db empty) fails with clear message; Redis-down simulated → `setup` Redis proof fails. Risk H. Blocked-by F1.2, F1.3, F1.4.
* **F1.6 Auth setup + role fixtures** — Create `setup/auth.setup.ts`, `support/roles.ts`, `support/fixtures.ts` (`adminPage … loginAs`, per-worker storage selection by `testInfo.parallelIndex`). Scenarios: 16 states created; seed SKU visible; Redis key present; `loginAs` logout does not affect shared sessions (smoke). Data: seed users. Risk M. Blocked-by F1.4, F1.5, F1.7.
* **F1.7 uid/dates/tags/messages** — Create `support/unique.ts`, `dates.ts`, `tags.ts`, `messages.ts`. Tests: uniqueness under 4 workers × 10k generations (pure unit spec), bounds (SKU ≤30, codes ≤20 charset), UTC date helpers. Risk L.
* **F1.8 Diagnostics** — `support/diagnostics.ts` auto fixture; PHP-leak/500 guard; `expect500()`. Tests: induced console error / 404 resource / 500 attach artifacts only on failure; passing tests attach nothing. Risk M.
* **F1.9 csrf/request/ids** — `support/csrf.ts`, `request.ts`, `ids.ts` per §3.20–3.21. Tests: valid/missing/invalid/stale semantics; token tamper generators produce 404 on a real owned product. Risk M.
* **F1.10 Factories** — all `factories/*.ts`, `world`. Tests (`specs/_smoke/factories.spec.ts`): each factory creates and the entity is visible via its list/detail; PO/SO factories drive real transitions; `world` stock arithmetic equals ledger (via UI); parallel creation (4 workers × 25) yields no collision (incl. categories with explicit codes). Risk M–H. Blocked-by F1.6, F1.7, F1.9.
* **F1.11 Components, CSV, files, fixtures** — `components/*`, `support/csv.ts`, `support/files.ts`, `fixtures/files/**`, `scripts/make-fixtures.mjs` (oversized deterministic PNG into `.cache/`). Tests: `ConfirmDialog` on PO cancel; `MultiSelect` on ledger type; `Pagination` on products; `downloadCsv` on categories export. Accept: ≥2 consumers each (A8 re-check later). Risk M.
* **F1.12 DB oracle + low-stock helper (Mode A)** — `support/db.ts` (read-only SELECT through `docker compose exec -T db mysql`, credentials from e2e.env), `runLowStockJob()` (exec in app container). Tests: invariant query on pristine seed returns clean; helper raises `ModeBUnavailable` in Mode B. Risk M.
* **F1.13 Smoke + contract + selector batch #1** — `specs/_smoke/*.spec.ts` (`@smoke`: login each role, product list renders, RBAC one-liner, CSV download, upload one valid PNG); write `FOUNDATION_CONTRACT.md`; apply approved selector hooks batch #1 (initially likely **none**); Accept = **Gate G1** checklist (§18-G1). Prod code: possibly attribute-only `views/**` (needs your explicit approval first). Risk M.

## Agent 2 — Auth / Security / RBAC (Wave 2; read-only inventory in Wave 1)
* **S2.0 (W1, read-only)** produce `rbac-routes.json` draft from `config/routes.php` + controllers (no code).
* **S2.1 Login/logout** — `specs/auth/login.spec.ts`, `logout.spec.ts`, `pages/auth/LoginPage.ts`. Cover: §28 Authentication rows 1–11. Data: seed users + `users.create` for inactive. T: `…/specs/auth`. Accept: exact generic message; 200 vs 302 semantics; GET `/logout` 404 documented.
* **S2.2 Session** — `specs/auth/session.spec.ts` (regeneration, cookie flags, deactivation invalidation serial, mid-session role change), `specs/exclusive/auth/session-expiry.spec.ts` (`@needs-isolated`, uses `:18091` app; `expect.poll` interval 3.5 s). Accept: no fixed sleeps.
* **S2.3 RBAC GET matrix** — `specs/rbac/rbac-get.spec.ts` table-driven from `rbac-routes.json`; includes drift test comparing JSON to parsed `routes.php` (static parse in test) so new routes cannot be forgotten. 
* **S2.4 RBAC direct-POST matrix** — `specs/rbac/rbac-post.spec.ts`: every POST route × forbidden role with valid CSRF → 403 (and ordering proof: forbidden+tampered id → 403; allowed+tampered → 404).
* **S2.5 CSRF suite** — `specs/security/csrf.spec.ts`: missing/invalid/stale/other-session for login, logout and one write per module + every transition; JSON endpoints return HTML 400.
* **S2.6 ID tamper suite** — `specs/security/id-tamper.spec.ts`: families `/users /products /categories /warehouses /suppliers /customers /purchase-orders /sales-orders` with tamper/truncate/non-hex/raw/forbidden token; ledger reference links obfuscated.
* **S2.7 Ownership & double-submit & disabled-resource** — `specs/security/ownership.spec.ts`, `double-submit.spec.ts`, `disabled-resources.spec.ts`, `xss-escape.spec.ts`, `open-redirect.spec.ts`.
* **S2.8 Legacy-parity sweeps** — `specs/security/guest-access.spec.ts`, `specs/security/error-pages.spec.ts`, `specs/security/no-query-string.spec.ts`, `specs/security/crawl.spec.ts` (BFS per role, uses Diagnostics PHP-leak guard; collapses id patterns, ≤400 pages), `responsive` lives with A7.
 Risk overall M; Parallel: S2.1–S2.8 independent. Selector needs: none expected beyond role/label locators.

## Agent 3 — Master data (Wave 2)
* **M3.1 Categories** — `specs/categories/{list,create-validation,edit-state,delete,export,rbac}.spec.ts`; `pages/categories/CategoryPage.ts`. R: read `categories.js`/`list.php` for modal completion signal (`window.location.reload()` after save → await `waitForResponse` + `waitForLoadState('domcontentloaded')`, never sleep).
* **M3.2 Product list** — `specs/products/products-list.spec.ts` (filters, per-page, pagination boundaries, KPIs, states, role UI, seed-dependent subset tagged `@seed-dependent`).
* **M3.3 Product create validation** — `products-create.spec.ts` — every rule §1.5 (SKU bounds 30/31, normalisation, uniqueness; name 3/2/150/151 + multibyte quirk; description 500/501; prices & cross-field; reorder; barcode; category tamper; initial stock cases; double-submit) · Data: O category per describe, O product per test · Acceptance: **all ProductService rules covered**.
* **M3.4 Product edit/state/detail** — `products-edit.spec.ts`, `products-state.spec.ts`, `products-detail.spec.ts` (serial chain per product) incl. dropdown integration (inactive absent in PO/SO forms).
* **M3.5 Product image** — `products-image.spec.ts` (`@upload`) per §3.22; needs `minio` healthy.
* **M3.6 Availability API** — `specs/products/products-availability-api.spec.ts` (shape, inactive warehouse excluded, 404/401 JSON, SKU case, behaviour after stock change — see R-9).
* **M3.7 Warehouses** — `specs/warehouses/{list,crud,state,detail,rbac}.spec.ts` (+ R: confirm create-duplicate-code behaviour; quirk 500 test).
* **M3.8 Customers** — `specs/customers/{list,crud,state,rbac}.spec.ts` (WH 403 incl. detail).
* **M3.9 Suppliers** — `specs/suppliers/{list,crud,state,po-dropdown,rbac}.spec.ts`.
* **M3.10 Users** — `specs/users/{list,crud-validation,guards,state,rbac,obfuscation}.spec.ts` (session-kill coupling with S2.2: A3 asserts only list/state semantics; A2 owns session semantics).
* **M3.11 Master-data cross-module integration** — `specs/products/integration-dropdowns.spec.ts` (new category/warehouse/supplier/customer appear where expected).
 Parallel: all independent (owned data). Risk M (validation breadth). Selector needs: row-action menus & dynamic filter widgets (report in §13).

## Agent 4 — Purchase Orders (Wave 2)
* **P4.1 Create & validation** — `specs/purchase-orders/po-create.spec.ts` (§28 PO rows) · Data: `world.create()` per test file, per-test PO.
* **P4.2 List/filter/sort/paginate/RBAC** — `po-list.spec.ts` (seed 50 POs: `@seed-dependent` subset; owned subset for filters).
* **P4.3 Lifecycle (serial)** — `po-lifecycle.spec.ts`: Draft→Ordered→Partial→Partial→Received; buttons per state; progress tiles; audit timeline.
* **P4.4 Receipt invariants** — `po-receipt.spec.ts`: stock +n verified on product detail, warehouse detail, ledger (`Receipt`, +qty, `ref PO` link, Done By), over-receipt UI+direct, zero/neg/unknown line, wrong state.
* **P4.5 Guards & RBAC** — `po-guards.spec.ts`: submit/cancel admin-only, cancel matrix, no stock reversal after partial cancel, Sales fully blocked (token-captured, no raw ids).
* **P4.6 Concurrency** — `po-concurrency.spec.ts` (`@concurrency`): two simultaneous receipts (A+W sessions); cancel-vs-receive; repeat-each 5 in G3.
 Risk H (concurrency) · Blocked-by G1 · Parallel with A2/A3/A5/A6/A7.

## Agent 5 — Sales Orders (Wave 2)
* **O5.1 Create & validation**, **O5.2 List/scope/RBAC**, **O5.3 Lifecycle (serial)**, **O5.4 SOD & isolation** (`sod.spec.ts`, `isolation.spec.ts`), **O5.5 Goods issue / oversell / rollback** (`goods-issue.spec.ts`: multi-line partial-shortage ⇒ no mutation, ledger unchanged, SO stays Approved), **O5.6 Concurrency** (`so-concurrency.spec.ts`: double-issue; two SOs for last unit), **O5.7 Cancel/reject matrix** (`so-cancel-reject.spec.ts`). Paths `specs/sales-orders/**`. Data: `world` with stock seeded via `stock.seed`. Risk H. Admin-self-approve **allowed** asserted; Sales approve **403** asserted with exact message.

## Agent 6 — Ledger / Reports / Export (Wave 2)
* **L6.1 Ledger UI** — `specs/stock-ledger/ledger-list-filter-sort-page.spec.ts` (filters scoped by owned SKU; AJAX waits via `waitForResponse`).
* **L6.2 Ledger arithmetic & references** — `ledger-arithmetic.spec.ts` (per-row, cumulative, final-after==stock; reference link resolves; Done By).
* **L6.3 Report types & params** — `specs/reports/reports-types.spec.ts`, `reports-params.spec.ts`; R: confirm KPI definitions (`ReportService`) — exact KPI numbers only in `exclusive/reports/reports-kpi.spec.ts` (global state).
* **L6.4 Ledger export** — `reports-export-ledger.spec.ts`.
* **L6.5 Orders export** — `reports-export-orders.spec.ts` (owner/role/warehouse/date scope; inclusive bounds; Items Count & Total Value arithmetic from owned orders).
* **L6.6 CSV injection & escaping** — `reports-export-injection.spec.ts` (+ categories export cross-check with A3 via shared `csv.ts`).
 Coordination: A6 consumes only A1 factories; ledger reference expectations are validated through `world` flows, not A4/A5 specs. Risk M.

## Agent 7 — Dashboard / Profile / Notifications / A11y (Wave 2)
* **D7.1 Role dashboards** — `specs/dashboard/dashboard-{admin,warehouse,sales}.spec.ts` (exact KPI ones under `exclusive/dashboard/`).
* **D7.2 Sales dashboard** — `specs/dashboard/sales-dashboard.spec.ts`.
* **D7.3 Notifications** — `specs/exclusive/notifications/{bell,mark-all-read,low-stock-job,rbac}.spec.ts` (`@needs-isolated`; uses `runLowStockJob()`).
* **D7.4 Profile** — `specs/profile/profile.spec.ts` (owned user via `users.create` + `loginAs` — **never mutate seed users**).
* **D7.5 A11y scans** — `specs/a11y/axe-*.spec.ts` one per distinct page; `a11y/known-issues.json` proposal.
* **D7.6 Keyboard & focus** — `specs/a11y/keyboard-*.spec.ts` (dialog focus + Escape + focus return, modal, menus, multi-select, form submit via Enter).
* **D7.7 Responsive 360 px** — `specs/dashboard/responsive.spec.ts` (legacy parity) .
 Risk M (a11y unknowns) — discovery run first; Accept per §3.24.

## Agent 8 — Audit (Wave 4, read-only)
* **A8.1** legacy parity matrix verification (§15) · **A8.2** coverage-gap & missing-workflow detection vs §28 · **A8.3** duplicate-test detection · **A8.4** selector-quality audit (locator tier statistics, `nth()/index` bans) · **A8.5** shared-seed mutation audit (static: any POST/PUT hitting seed entities; dynamic: pristine-seed fingerprint hash before/after full run in Mode A) · **A8.6** wait/flake-risk audit (`waitForTimeout`, `.first()` on repeated rows, un-awaited AJAX, tag lint, contract-message drift vs source). Output `reports/AUDIT-*.md`; blockers go to A0.

# 12. TEST FILE INVENTORY
| Spec file (under `tests/playwright/specs/`) | Owner | Purpose | Roles | Tags | Parallel |
|---|---|---|---|---|---|
| `_smoke/smoke.spec.ts` | 1 | stack + login + basic render | all | @smoke | P |
| `_smoke/guards.spec.ts` | 1 | env/compose guard unit tests | — | @security | P |
| `_smoke/factories.spec.ts` | 1 | factory self-tests | A,S,W | @smoke | P |
| `auth/login.spec.ts` · `logout.spec.ts` · `session.spec.ts` | 2 | authN | all | @security @smoke | P |
| `exclusive/auth/session-expiry.spec.ts` | 2 | idle timeout (short-session app) | all | @security @needs-isolated | Ex |
| `rbac/rbac-get.spec.ts` · `rbac-post.spec.ts` | 2 | route×role matrices | A,S,W,G | @rbac | P |
| `security/csrf.spec.ts` · `id-tamper.spec.ts` · `ownership.spec.ts` · `double-submit.spec.ts` · `disabled-resources.spec.ts` · `xss-escape.spec.ts` · `open-redirect.spec.ts` | 2 | cross-cutting security | A,S,S2,W | @security | P |
| `security/guest-access.spec.ts` · `error-pages.spec.ts` · `no-query-string.spec.ts` · `crawl.spec.ts` | 2 | legacy-parity sweeps | all | @regression | P |
| `products/products-list.spec.ts` · `products-create.spec.ts` · `products-edit.spec.ts` · `products-state.spec.ts` · `products-detail.spec.ts` · `products-image.spec.ts` · `products-availability-api.spec.ts` · `integration-dropdowns.spec.ts` | 3 | Products | A,S,W | @crud @validation @upload @rbac | P (state/edit: Ser) |
| `categories/list/create-validation/edit-state/delete/export/rbac.spec.ts` | 3 | Categories | A,S,W | @crud @export @rbac | P |
| `warehouses/list/crud/state/detail/rbac.spec.ts` | 3 | Warehouses | A,S,W | @crud @rbac | P |
| `customers/list/crud/state/rbac.spec.ts` | 3 | Customers | A,S,W | @crud @rbac | P |
| `suppliers/list/crud/state/po-dropdown/rbac.spec.ts` | 3 | Suppliers | A,S,W | @crud @rbac | P |
| `users/list/crud-validation/guards/state/rbac/obfuscation.spec.ts` | 3 | Users | A,S,W | @crud @security @rbac | P |
| `purchase-orders/po-create/po-list/po-lifecycle/po-receipt/po-guards/po-concurrency.spec.ts` | 4 | PO | A,W,S | @workflow @validation @concurrency @rbac | Ser (lifecycle) / P |
| `sales-orders/so-create/so-list/so-lifecycle/sod/isolation/goods-issue/so-concurrency/so-cancel-reject.spec.ts` | 5 | SO | A,S,S2,W | @workflow @rbac @security @concurrency | Ser / P |
| `stock-ledger/ledger-list-filter-sort-page/ledger-arithmetic.spec.ts` | 6 | ledger | A,W,S | @crud @workflow @rbac | P |
| `reports/reports-types/params/export-ledger/export-orders/export-injection.spec.ts` | 6 | reports/export | A,S,W | @export @security @rbac | P |
| `exclusive/reports/reports-kpi.spec.ts` | 6 | global KPI/valuation | A,W | @needs-isolated | Ex |
| `dashboard/dashboard-admin/warehouse/sales/sales-dashboard/responsive.spec.ts` | 7 | dashboards | A,S,W | @smoke @rbac @regression | P |
| `exclusive/dashboard/dashboard-kpi.spec.ts` | 7 | exact KPI cross-surface | A,W | @needs-isolated | Ex |
| `exclusive/notifications/bell/mark-all-read/low-stock-job/rbac.spec.ts` | 7 | notifications | A,W,S | @needs-isolated | Ex |
| `profile/profile.spec.ts` | 7 | profile | all | @crud @validation | P |
| `a11y/axe-*.spec.ts` · `keyboard-*.spec.ts` | 7 | accessibility | A,S,W | @a11y | P |

# 13. SELECTOR CHANGE INVENTORY (provisional — finalised after A0.1/F1.13 spike)
Default position: **zero mandatory production changes.** Semantic/id locators already exist for most controls (custom dialog `role=alertdialog` + `#__confirmOk/#__confirmCancel`; toast container `aria-live`; stable form ids `#category-name-input`, `#po-submit-btn`, `#ledger-sku`…; `role=tab/tabpanel/menu/menuitem/alert/status`, 98 `aria-label`s). Candidates only if a spec proves role/label/text cannot disambiguate:
| Production file | Owner | Existing problem | Proposed hook | Behavior change |
|---|---|---|---|---|
| `views/purchase/detail.php`, `views/sales/detail.php` | A1 batch | status read via `.badge` position (legacy used two different class paths) | `data-testid="order-status"` on the status badge | No — attribute-only |
| `views/dashboard/index.php`, `views/sales-dashboard/index.php`, `views/master/products/list.php`, `views/master/categories/list.php`, `views/reports/index.php` | A1 batch | KPI/stat cards are anonymous `div.stat-card` | `data-testid="kpi-<key>"` | No — attribute-only |
| `views/layouts/main.php` (notification list items) | A1 batch | `li[role=menuitem]` with no identity | `data-testid="notification-item"` | No — attribute-only |
| `views/master/*/list.php`, `views/purchase/list.php`, `views/sales/list.php` | A1 batch | rows carry no business key (only 2 `<tr>` have any attribute) | `data-row-key="<sku|code|email|PO#|SO#>"` **only if** `getByRole('row').filter(hasText)` proves ambiguous | No — attribute-only |
| `views/purchase/form.php`, `views/sales/form.php` | A1 batch | dynamic line rows (`.po-item-row`, `#items-body tr`) | `data-testid="line-row"` (indices by DOM order) | No — attribute-only |
| `views/layouts/main.php` account menu | — | already `#account-menu-btn`, `role=menu` | none | — |
Hook process: SELECTOR_REQUEST → A0 approve → A1 batch commit labelled "test hooks: attribute-only, Behavior change: No" and **reviewed separately from test code**.

# 14. TEST DATA INVENTORY
| Data type | Seed / Test-owned | Creator | Used by | Cleanup | Parallel-safe |
|---|---|---|---|---|---|
| Users (4 seed) | Seed, **read-only**; sessions only | DB seed | `setup`, role fixtures | none (never mutated) | Yes (per-worker sessions) |
| Users (extra) | Owned | `factories.users` | A2/A3/A7 | Mode B: soft-deactivate | Yes |
| Categories | Seed (4) read-only / Owned | `factories.categories` (explicit code) | A3, factories | hard-delete if empty (test-chosen); else reset | Yes |
| Products | Seed (100) read-only / Owned | `factories.products` (+stock) | all domains | none (soft-deactivate Mode B) | Yes |
| Warehouses | Seed (2) read-only / Owned (`world`) | `factories.warehouses` | A4/A5/A6/A3 | deactivate Mode B | Yes |
| Customers / Suppliers | Seed (10 each) read-only / Owned | factories | A3/A4/A5 | deactivate Mode B | Yes |
| Purchase orders | Seed (50) read-only (`@seed-dependent` list checks) / Owned | `factories.purchaseOrders` | A4/A6 | none | Yes |
| Sales orders | Seed (50) read-only / Owned | `factories.salesOrders` | A5/A6/A7 | none | Yes |
| Stock | Seed read-only / Owned per `world` warehouse+product | `stock.seed` | A4/A5/A6 | none | Yes (isolated warehouse) |
| Ledger | Append-only; owned rows identified by owned SKU | via workflows | A6 | none | Yes (SKU-scoped filters) |
| Notifications | **Global shared state** | `runLowStockJob()` | A7 only (`exclusive`) | reset (Mode A) | **No → exclusive project** |
| Upload files | Committed + generated binaries | `scripts/make-fixtures.mjs` | A3 | `.cache/` ignored | Yes |
| Sessions | Per worker/role | `setup` | all | recreated each run | Yes |

# 15. LEGACY PARITY MATRIX (nothing deleted; retirement = later, separately approved)
Legacy files are standalone node scripts (`check()` printouts) in `tests/e2e/`. "Covered?" = planned destination; A8.1 verifies after implementation.
| Legacy test | Scenario | Replacement spec | Owner | Coverage status |
|---|---|---|---|---|
| `categories` `checkUnauthenticated` | guest GET redirect / POST ≠200 | `security/guest-access` (302 exact) | 2 | Planned (stricter) |
| `categories` `checkRoleView` | Admin sees add/actions/export; S/W don't | `categories/rbac`, `categories/list` | 3 | Planned |
| `categories` `checkServerSideAuthorization` | S/W 403 on 5 POST routes | `rbac/rbac-post`, `categories/rbac` | 2/3 | Planned (token-captured ids, not raw `/1`) |
| `categories` `checkDeleteGuardOnSeedCategory` | delete disabled + 422 for category w/ SKUs | `categories/delete` (owned category + owned product) | 3 | Planned (no seed mutation risk) |
| `categories` `checkSkuLinkAndCsvExport` | SKU link count equality; CSV headers; **injection** | `categories/list`, `categories/export`, `reports-export-injection` | 3/6 | Planned |
| `categories` `checkAdminCrud` | modal create/dup 422/search/edit/delete | `categories/create-validation`, `edit-state`, `delete` | 3 | Planned |
| `crawl` `crawlRole` + log check | BFS, no PHP leak, no 403 on shown links, empty-form 400, docker log scan | `security/crawl` + diagnostics guard + `globalTeardown` log scan | 2/1 | Planned |
| `csv-export` | download buttons per role | `categories/export`, `reports-export-*`, `sales-dashboard` export | 3/6/7 | Planned (content-level, not just download) |
| `customers` checks | unauth, role view, WH 403, token authz, CRUD, obfuscation, toggles | `customers/*`, `rbac/*`, `users/obfuscation` pattern | 3/2 | Planned |
| `dashboard` (info-only) | per-role cards, quick actions, notifications, dead report link | `dashboard/dashboard-*`, `exclusive/dashboard/*` | 7 | Planned (now asserting) |
| `no-query-string` `sweepRole`, `checkQueryParamsIgnored` | forms POST, no `?`, GET params ignored | `security/no-query-string` | 2 | Planned |
| `purchase-orders` unauth/role view/Sales blocked/WH cannot submit-cancel | RBAC | `po-list`, `po-guards`, `rbac/*` | 4/2 | Planned |
| `purchase-orders` `checkAdminLifecycle` | create/submit/partial/over-receipt/full/cancel guard | `po-create`, `po-lifecycle`, `po-receipt`, `po-guards` | 4 | Planned (owned supplier/product/warehouse; no ELEC-003 mutation) |
| `purchase-orders` `checkCancelDraft` | confirm-modal cancel | `po-guards` (ConfirmDialog) | 4 | Planned |
| `purchase-orders` `checkConcurrentReceive…` | concurrent receipt ≤ ordered | `po-concurrency` (stronger: exactly-one + ledger/stock) | 4 | Planned |
| `regressions` (admin: edit re-render ×5, product unit/inactive, stock-status filters, 2-warehouse dedupe, per-page 25, sort survives paging, PO row add, SO create keeps items, ledger AJAX, category deep-link modal, mark-all-read referer, reports KPIs/sorts/exports) | fixed-bug regressions | spread: `users/crud-validation`, `customers/crud`, `suppliers/crud`, `warehouses/crud`, `products-edit`, `products-list`, `po-list`, `po-create`, `so-create`, `so-list`, `ledger-*`, `categories/edit-state`, `notifications/mark-all-read`, `reports-*` (tag `@regression`) | 3,4,5,6,7 | Planned (each item tracked by A8.1 checklist of 40 sub-checks) |
| `regressions` WH/Sales checks | issue queue; hidden create link; profile no warnings; period totals; no Payment column | `dashboard-warehouse`, `dashboard-sales`, `sales-dashboard`, `profile` | 7 | Planned |
| `reports` role view / ledger export / orders export / **injection** / report types | RBAC, headers, scope, injection, types | `reports-types`, `reports-params`, `reports-export-*` | 6 | Planned (dates now dynamic — legacy windows 2026-08/09 break on fresh seed) |
| `responsive` 360 px sweep | no horizontal scroll | `dashboard/responsive` | 7 | Planned |
| `sales-orders` role view/WH cannot create | RBAC | `so-list`, `so-create`, `rbac/*` | 5/2 | Planned |
| `sales-orders` `checkHappyPathLifecycle` | create→submit→SOD→approve→issue→ledger | `so-lifecycle`, `sod`, `goods-issue` | 5 | Planned (owned stock, no OFC-001 mutation) |
| `sales-orders` `checkInsufficientStockRejected` | oversell 400, stays Approved | `goods-issue` (+ multi-line rollback) | 5 | Planned (extended) |
| `sales-orders` cancel/reject/sort/pagination/**cross-user isolation** | | `so-cancel-reject`, `so-list`, `isolation` | 5 | Planned (exact 403/400 instead of ≥400) |
| `stock-ledger` role/AJAX 403/reference links/filter-sort-page/audit fields/sign/responsive/CSV | | `ledger-*`, `rbac/*`, `reports-export-ledger`, responsive | 6/7 | Planned |
| `suppliers` unauth/role/authz/CRUD/PO-dropdown | | `suppliers/*` | 3 | Planned |
| `users` unauth/non-admin blocked/CRUD/obfuscation/**deactivation invalidates session** | | `users/*`, `auth/session` | 3/2 | Planned |
| `warehouses` unauth/role/authz/CRUD/detail pagination | | `warehouses/*` | 3 | Planned |
| **Legacy gaps the new suite closes:** logout (legacy GET no-op), exact status codes, stale/missing CSRF, tampered-id matrix, SOD message, uploads, a11y, concurrency invariants, test-owned data, dynamic dates | | various | — | New coverage |
**Retirement criteria (future task):** parity matrix 100 % Covered/approved-exception · 3 clean full runs · A8 audits closed · explicit approval. Also recommend (separately) documenting that legacy `/logout` GET is a no-op.

# 16. EXECUTION COMMAND MATRIX
All run from `inventory-order-management-system/tests/playwright/`. **Scripts below do not exist yet; each is created in F1.1** (`package.json` implementation task) using `cross-env`.
| Purpose | Command | Notes |
|---|---|---|
| Full regression (Mode A) | `npm run e2e` ⇒ `E2E_MODE=isolated E2E_RESET=always playwright test` | default CI/regression |
| Smoke | `npm run e2e:smoke` ⇒ `… --grep @smoke` | |
| RBAC | `npm run e2e:rbac` ⇒ `--grep @rbac` | |
| Security | `npm run e2e:security` ⇒ `--grep @security` | |
| Master data | `npm run e2e:master` ⇒ `playwright test specs/{products,categories,warehouses,customers,suppliers,users}` | |
| PO | `npm run e2e:po` ⇒ `specs/purchase-orders` | |
| SO | `npm run e2e:so` ⇒ `specs/sales-orders` | |
| Ledger/Reports | `npm run e2e:reports` ⇒ `specs/stock-ledger specs/reports specs/exclusive/reports` | |
| Accessibility | `npm run e2e:a11y` ⇒ `--grep @a11y` | |
| One spec | `E2E_MODE=isolated npx playwright test specs/products/products-create.spec.ts` | |
| Fast iterate | `E2E_MODE=isolated E2E_RESET=never E2E_KEEP=1 npx playwright test …` | reuses running `ioms-e2e` |
| Isolated up/down helpers | `npm run e2e:env:up` / `e2e:env:down` (down = e2e project only, volume-guarded) | never touches `iom` project |
| Existing environment (Mode B) | `E2E_MODE=existing E2E_BASE_URL=http://127.0.0.1:8090 E2E_I_UNDERSTAND_NO_DETERMINISM=1 npx playwright test --grep-invert "@needs-isolated|@seed-dependent"` | no reset, no docker, banner warns |
| Concurrency soak | `--grep @concurrency --repeat-each=5` | Gate G3 |
| List/lint | `npx playwright test --list && npm run lint` | tag/lint audit |
**16.5 CI plan (not implemented):** none exists for IOMS. Proposed single workflow (path-filtered to `inventory-order-management-system/**`): ubuntu-latest, Docker Compose, Node 22, `npx playwright install --with-deps chromium`, `npm run e2e`, upload `playwright-report`/traces/compose logs, `failOnFlakyTests`, nightly `--repeat-each` soak. Requires your approval and changes to `.github/workflows/` (CODEOWNERS-protected).

# 17. RISKS AND MITIGATIONS
| ID | Risk | Mitigation |
|---|---|---|
| R-1 | Wiping dev data | standalone compose + guarded wrapper + volume-prefix check + Mode B has no docker code + F1.2 "dev untouched" assertion |
| R-2 | Shared PHP session + session flash across workers | per-worker sessions (AD-5) |
| R-3 | `php -S` capacity (flaky timeouts) | `PHP_CLI_SERVER_WORKERS=8`, workers ≤4, action timeouts 15 s, nav 30 s |
| R-4 | Silent Redis→file session fallback | `setup` Redis-key proof; globalSetup probe |
| R-5 | Date-relative seed / midnight crossing | dynamic dates via helper; seed-dependent assertions limited & tagged |
| R-6 | Global notification state | `exclusive` project after parallel; Mode A only |
| R-7 | Concurrency tests flaky by nature | assert invariants (no over-receipt/oversell, ledger = stock) + "≥1 success"; investigate—not retry—deviations; `--repeat-each=5` gate |
| R-8 | Auto category-code race → 500 | factories pass explicit codes; A3 single serial test for auto-generation |
| R-9 | Memcached staleness (permissions TTL 3600, availability) | availability API reads DB directly (verified `getAvailability` has no cache); `findBySku` cache is not on that path — A3 confirms; E2E never edits `role_permissions` |
| R-10 | Quirks mistaken for bugs / hidden defects | `@quirk` + completion-report section; no silent adaptation; no app changes without approval |
| R-11 | Over-long input → 500 on warehouses/customers/suppliers | asserted semantically; counts as defect report |
| R-12 | MinIO image/tag/availability; public-read policy | pinned image, `minio-init`, spike S-2; upload specs skip with explicit reason if MinIO unhealthy (fail in CI) |
| R-13 | Playwright/Chromium revision mismatch in sandboxes | `E2E_CHROMIUM_PATH` override |
| R-14 | A11y findings in existing UI | discovery + approved allowlist; app fixes separate |
| R-15 | Agent drift (competing fixtures/helpers) | contract freeze, ownership, lint, A8 audit |
| R-16 | Seed mutation by accident | A8.5 pristine-fingerprint; lint banning seed SKU/email literals in mutating specs |
| R-17 | Time cost of full reset (~1–2 min) | `E2E_RESET=fast/never` for iteration; CI uses `always` |
| R-18 | Docs conflict (Admin self-approve) | tests follow code; doc fix suggested separately |
| R-19 | MySQL lock-wait/deadlock under concurrency surfaces as 500 | accepted only if invariants hold; report as finding |
| R-20 | Hardcoded `127.0.0.1` ports clash on shared CI hosts | all published ports env-configurable in `e2e.env` |

# 18. FINAL REVIEW CHECKLIST
**Gates**
* **G0** you approve spec + answer §19 questions.
* **G1 Foundation:** smoke green in Mode A; dev-untouched assertion; guard tests; contract frozen & versioned; lint rules active; selector batch #1 resolved; A2 inventory delivered.
* **G2 Domain:** each agent report `Ready: YES`, green ×2 on clean reset, no shared-file edits (git diff path check), requests triaged.
* **G3 Integration:** full run ×3 green, 0 flaky, worker sweep 1/2/4, concurrency soak, invariant gate passes, no PHP log warnings.
* **G4 Audit:** parity matrix complete, coverage matrix complete, audits clean.
**Environment** ☐ normal dev DB cannot be wiped ☐ E2E env isolated ☐ destructive reset limited to `ioms-e2e` ☐ execution mode explicit.
**Architecture** ☐ globalSetup env-only ☐ auth setup auth-only ☐ no role project multiplication ☐ shared fixture contract exists.
**Data** ☐ seed read-only ☐ mutations own data ☐ worker-safe unique ids ☐ parallel mutation isolated.
**Coverage** ☐ all active modules inventoried (incl. Sales Dashboard, Availability API, low-stock job) ☐ all validation covered ☐ RBAC UI+backend ☐ CSRF ☐ ID tampering ☐ PO state machine ☐ SO state machine ☐ stock invariants ☐ exports ☐ uploads ☐ accessibility.
**Multi-agent** ☐ exclusive scopes ☐ single owner of shared files ☐ foundation contract ☐ no competing architecture ☐ waves ☐ gates ☐ completion-report format.
**Maintainability** ☐ semantic selectors first ☐ no gratuitous test-ids ☐ no sleeps ☐ readable POMs ☐ diagnostics ☐ tags standardised.
**Migration** ☐ legacy retained ☐ parity matrix ☐ removal only after later approval.

# 19. OPEN QUESTIONS (only what code cannot answer)
1. **The draft spec** — not found (see top). Please supply it (or confirm it can be ignored).
2. **Location:** OK to put the suite at `inventory-order-management-system/tests/playwright/` (vs. a new top-level `e2e/`)?
3. **Quirk policy:** assert-and-report (my default, `@quirk`) vs. fail-the-test until the app is fixed? Defects found so far: SKU limit 30 vs schema 50; byte-based name length; no length guards ⇒ 500 on Warehouse/Customer/Supplier overflow; initial stock silently ignored; dead "Generate Summary Report" link; H1 "Admin Dashboard" for all roles; `docs/testing/README.md` claims Admin cannot self-approve (code allows).
4. **A11y policy:** allowlist pre-existing violations (approved by A0) vs. fix the app first?
5. **Selector hooks:** pre-approve attribute-only changes in `views/**` (batch process) or require approval per batch?
6. **Docker requirement:** Compose v2.24+ and a daemon available in your CI/dev; E2E needs ~1.5 GB RAM (MySQL+MinIO+app). Acceptable?
7. **Docs location:** after approval, commit this spec under `docs/testing/e2e-playwright/` (I did not write it into the repo during planning).
8. The 12 zero-byte junk files tracked in the repo — remove in a separate commit (out of scope here)?

---
**STOP.** No code, config, Docker, DB, package, CI or documentation file in the repository was created or changed. Implementation starts only after your explicit approval.

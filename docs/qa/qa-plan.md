# Stage 8 — QA Plan

**Document:** `docs/qa/qa-plan.md`
**Project:** Inventory & Order Management System
**Date:** 2026-09-16 (updated)
**Scope:** End-to-end quality verification of all 6 implementation slices.
**Standard:** Clean Docker rebuild → full demo end-to-end, 2 languages (EN/ID), light theme only.

---

## Prerequisites

```bash
# Clean start every test
docker compose down -v          # destroy containers + volumes
docker compose up --build -d    # fresh rebuild
# Wait for MySQL to be ready (~10s)
docker compose exec app php database/seed.php
# OR: docker compose exec db mysql -u root -p <pass> inventory_order_management < database/seed.sql
```

---

## 0. Smoke Test — App Bootstraps

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 0.1 | `docker compose up --build` exits 0 | CLI | No error |
| 0.2 | `http://localhost:8090` returns 200 | Browser/curl | Login page loads |
| 0.3 | Login page has EN/ID language toggle | Visual | Toggle visible |
| 0.4 | No PHP fatal errors in container logs | `docker compose logs app` | No `[error]` or `Fatal error` |
| 0.5 | `php vendor/bin/phpunit --testsuite Unit` | CLI | All unit tests pass |
| 0.6 | `php vendor/bin/phpunit --testsuite Integration` | CLI | All integration tests pass |
| 0.7 | `php vendor/bin/phpstan analyse --level=5` | CLI | [OK] No errors |

---

## 1. Authentication & Authorization (BR-017)

| # | Check | Method | Expected | BR |
|---|-------|--------|----------|----|
| 1.1 | Login as Rita (Admin) succeeds | Browser | Dashboard renders |
| 1.2 | Login as Beni (Sales) succeeds | Browser | Dashboard renders |
| 1.3 | Login as Wawan (Warehouse) succeeds | Browser | Dashboard renders |
| 1.4 | Login with wrong password fails | Browser | Error message, no redirect |
| 1.5 | Login with unknown email fails | Browser | Error message |
| 1.6 | Logout clears session | Browser | Redirect to login |
| 1.7 | Unauthenticated access to `/dashboard` redirects to login | Browser/curl | 302 redirect |
| 1.8 | Sales accessing `/users` → 403 | Browser | Forbidden page |
| 1.9 | Warehouse accessing `/users` → 403 | Browser | Forbidden page |
| 1.10 | Sales accessing `/purchase-orders/create` → 403 | Browser | Forbidden page |
| 1.11 | CSRF token missing on POST → rejected | Browser | Error / 400 |
| 1.12 | CSRF token tampered on POST → rejected | Browser | Error / 400 |

---

## 2. Master Data — Products (PRD-01, BR-019)

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 2.1 | Product list loads (paginated, 10/page per FIND-01) | Browser | Table renders |
| 2.2 | Search by SKU finds product | Browser | Filtered results |
| 2.3 | Search by name finds product | Browser | Filtered results |
| 2.4 | Create product: required field validation | Browser | Error on empty |
| 2.5 | Create product: SKU duplicate rejected | Browser | Error message |
| 2.6 | Create product with image upload succeeds | Browser | Image shown on detail |
| 2.7 | Image > 2MB upload rejected | Browser | Error message |
| 2.8 | Inactive product hidden from active dropdowns | Browser | Not in list |
| 2.9 | Deactivate product hides it from list | Browser | Row disappears |
| 2.10 | Reactivate product restores it | Browser | Row reappears |

---

## 3. Purchase Order + Goods Receipt (BR-003, BR-008, BR-015)

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 3.1 | PO list loads (paginated) | Browser | Table renders |
| 3.2 | Create PO with items → status Draft | Browser | PO detail shows Draft |
| 3.3 | Submit PO → status Ordered | Browser | Status badge changes |
| 3.4 | Cancel Draft PO → status Cancelled | Browser | Badge red |
| 3.5 | Cancel Ordered PO → allowed | Browser | Status Cancelled |
| 3.6 | Cancel PartiallyReceived PO → **rejected** | Browser | 400 error |
| 3.7 | Receive goods: partial receipt | Browser | Status → PartiallyReceived |
| 3.8 | Receive goods: full receipt | Browser | Status → Received |
| 3.9 | Receive more than remaining → rejected | Browser | Error, no partial update |
| 3.10 | **Rollback on error:** failed receipt leaves stock unchanged | DB query | `product_stocks.quantity` = pre-receipt value |
| 3.11 | **Stock ledger invariant:** `product_stocks` = SUM(`stock_ledger.qty`) | DB query | Both match |

---

## 4. Sales Order + BR-SOD-01/SOD-02 Segregation of Duties

> **Note:** Per brief DEC-012 (2026-09-08), BR-SOD-02 (total role denial) is the only check at the approve gate. Sales may NEVER approve any order. Admin may approve any order including their own.

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 4.1 | SO list loads (paginated, Sales scope) | Browser | Beni sees only own SOs |
| 4.2 | Create SO with items → status Draft | Browser | Draft badge |
| 4.3 | Submit SO for approval | Browser | PendingApproval badge |
| 4.4 | **BR-SOD-02:** Beni (Sales) approves any SO → **403 Forbidden** | Browser | HTTP 403 |
| 4.5 | **Admin self-approval:** Rita (Admin) approves her own SO → **success** | Browser | Approved badge |
| 4.6 | Cancel Draft SO by creator → allowed | Browser | Cancelled |
| 4.7 | Cancel PendingApproval SO by creator → allowed | Browser | Cancelled |
| 4.8 | Cancel Approved SO → **rejected** | Browser | 400 error |
| 4.9 | Cancel Fulfilled SO → **rejected** | Browser | 400 error |
| 4.10 | Cancel Cancelled SO → **rejected** | Browser | 400 error |
| 4.11 | Issue goods on Draft SO → **rejected** | Browser | 400 error |
| 4.12 | Issue goods on PendingApproval SO → **rejected** | Browser | 400 error |
| 4.13 | Issue goods on Approved SO → **success** | Browser | Fulfilled badge |

---

## 5. ARCH-02 Concurrency — Goods Issue (Critical)

> **Test must run inside container** since it requires real MySQL.
> `docker compose exec app vendor/bin/phpunit --testsuite Integration tests/Integration/ARCH02ConcurrencyTest.php`

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 5.1 | ARCH-02 integration test passes | CLI | 2 tests green |
| 5.2 | First concurrent issue request succeeds | Test | SO → Fulfilled |
| 5.3 | Second concurrent request on same SO → lock timeout → **fails** | Test | InsufficientStockException |
| 5.4 | **Stock never goes negative** after concurrent requests | Test | `quantity >= 0` |
| 5.5 | Only one `stock_ledger` Issue entry per SO | DB query | COUNT = 1 |
| 5.6 | **Manual simulation:** Issue full stock → Issue again → second fails | Browser | 400 error |

---

## 6. Dashboard DASH-01

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 6.1 | Admin dashboard: inventory value > 0 | Browser | Rp amount shown |
| 6.2 | Admin dashboard: low stock count shown | Browser | Number displayed |
| 6.3 | Admin dashboard: PO counts by status | Browser | All statuses |
| 6.4 | Admin dashboard: SO counts by status | Browser | All statuses |
| 6.5 | Sales dashboard: own SO counts by status | Browser | Filtered to own SOs |
| 6.6 | Warehouse dashboard: receipt queue > 0 | Browser | Number displayed |
| 6.7 | Warehouse dashboard: issue queue > 0 | Browser | Number displayed |
| 6.8 | Dashboard refresh shows **live data** (no cache) | Browser | Numbers match DB |

---

## 7. Reports REPORT-01

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 7.1 | Stock ledger CSV: EN headers | Browser | `Product, Warehouse, Type, Quantity...` |
| 7.2 | Stock ledger CSV: ID headers | Browser | `Produk, Gudang, Tipe, Kuantitas...` |
| 7.3 | Orders CSV download: PO | Browser | CSV file downloads |
| 7.4 | Orders CSV download: SO (Sales scope) | Browser (as Beni) | Only Beni's SOs in CSV |
| 7.5 | CSV: Injection `=HYPERLINK(...)` → **escaped** | CSV check | Value starts with `'` |
| 7.6 | CSV: `+5-10` in cell → **escaped** | CSV check | Value starts with `'` |
| 7.7 | CSV: `=CMD|...` → **escaped** | CSV check | Value starts with `'` |
| 7.8 | Date range filter applied | Browser | Results within range |

---

## 8. Product Availability API API-01

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 8.1 | `GET /api/products/ELEC-001/availability` → 200 | curl | JSON with `sku, name, total_quantity, warehouses` |
| 8.2 | `GET /api/products/NOTEXIST/availability` → 404 | curl | `{"error": "not_found", ...}` |
| 8.3 | Unauthenticated request → 401 | curl | `{"error": "unauthenticated", ...}` |
| 8.4 | Invalid SKU format → 400 | curl | `{"error": "bad_request", ...}` |
| 8.5 | `total_quantity` matches `SUM(product_stocks)` | DB query | Numbers match |

---

## 9. Internationalization I18N-01 (EN/ID) — OUT OF SCOPE

> **I18N-01 is OUT OF SCOPE (confirmed 2026-09-08, CF-02)** — see `master-project-specification.md`
> §46. `i18next` is a third-party JS library outside the brief's allowed frontend list, so the
> EN/ID locale toggle was descoped along with the theme toggle. The checks below are kept as a
> historical record of what was originally planned; **none of them should be run or claimed as
> passing** — the shipped UI has no locale switcher (English only).

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 9.1 | ~~EN locale: all nav labels in English~~ | Browser | Not applicable — English only, no toggle |
| 9.2 | ~~ID locale: all nav labels in Bahasa Indonesia~~ | Browser | Not applicable — descoped |
| 9.3 | ~~Switch locale → page reloads with new language~~ | Browser | Not applicable — descoped |

> Dark theme is also OUT OF SCOPE (confirmed 2026-09-08). Application is light theme only per Stitch design spec.

---

## 10. Low-Stock CLI JOB-01

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 10.1 | `php scripts/check-low-stock.php` exits 0 | CLI | No error |
| 10.2 | Products below reorder point listed | CLI | Table output |
| 10.3 | Products above reorder point NOT listed | CLI | Absent from output |
| 10.4 | Works via `docker compose exec app php scripts/check-low-stock.php` | CLI | Same output |

---

## 11. Cross-Flow Integration Test

> Full happy-path test from login to Fulfilled SO (end-to-end).

```
Admin creates product → Admin sets stock → Admin creates PO → Admin receives goods
  → Beni creates SO → Beni submits → Rita approves → Wawan issues goods
  → SO = Fulfilled → stock decremented → ledger entry created
```

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 11.1 | Final stock matches initial - issued qty | DB query | Consistent |
| 11.2 | `stock_ledger` has Issue entry for this SO | DB query | Entry exists |
| 11.3 | SO status = Fulfilled | DB query | Confirmed |

---

## 12. Security Checklist

| # | Check | Method | Expected |
|---|-------|--------|----------|
| 12.1 | No SQL injection in search field (`' OR 1=1 --`) | Browser | Treated as literal |
| 12.2 | No SQL injection in SKU field | Browser | Treated as literal |
| 12.3 | CSRF token on every POST/PUT/DELETE | Code review | All forms have token |
| 12.4 | `htmlspecialchars()` on all user output | Code review | No raw echo |
| 12.5 | No `new PDO()` in Service classes | Code review | Container provides |
| 12.6 | All queries use prepared statements | Code review | No string concatenation |
| 12.7 | Session cookie `HttpOnly` + `Secure` | Browser DevTools | Cookie flags set |
| 12.8 | No secrets in `.env` (no hardcoded passwords) | Code review | `.env` checked in |

---

## Definition of Done — Stage 8 QA

All rows in every section above must be **PASS** before marking Stage 8 complete.

| Section | Total Checks | Required Pass |
|---------|-------------|-------------|
| 0. Smoke | 7 | 7 |
| 1. Auth | 12 | 12 |
| 2. Products | 10 | 10 |
| 3. Purchase Order | 11 | 11 |
| 4. Sales Order + SoD | 13 | 13 |
| 5. ARCH-02 Concurrency | 6 | 6 |
| 6. Dashboard | 8 | 8 |
| 7. Reports | 8 | 8 |
| 8. API | 5 | 5 |
| 9. I18n | 3 | 3 |
| 10. Low-Stock CLI | 4 | 4 |
| 11. Cross-Flow | 3 | 3 |
| 12. Security | 8 | 8 |
| **TOTAL** | **98** | **98** |

**Pass rate required: 100%** — zero tolerance for critical failures per project brief.

---

## Bug Report Template

```markdown
## Bug #[N]
**Severity:** Critical / High / Medium / Low
**Section:** [#](#check-list)
**Environment:** Docker (clean rebuild), OS: Windows

**Steps to reproduce:**
1. Login as ...
2. Navigate to ...
3. Action: ...

**Expected:** ...
**Actual:** ...

**Evidence:** Screenshot / container logs / curl output

**Root cause:** (after investigation)
**Fix:** (after investigation)
```

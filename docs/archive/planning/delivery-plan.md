# Delivery Plan — Inventory & Order Management System

- **Status:** Active — implementation in progress
- **Date:** 2026-09-01
- **Stage:** 5 / 9 (Delivery Plan)
- **Owner:** Peserta Program Pengembangan Kompetensi Programmer
- **Target Timeline:** 2 minggu (10 hari kerja efektif, ~80 jam)
- **Related:** PRD §12 Vertical Slice Sequencing, Stage 4 Technical Design

---

## 1. Tujuan Dokumen

Menerjemahkan 7 vertical slice dari PRD §12.2 menjadi **task breakdown actionable** dengan:
- Estimasi durasi per task (jam atau hari)
- Dependencies antar task
- Definition of Done per slice
- Risk + contingency plan

Sesuai keputusan user 2026-09-01: **timeline 2 minggu**, **Stage 3 UX/UI Spec di-skip** (langsung implementasi dengan hybrid design feather + material + functional).

---

## 2. Master Timeline (10 Hari Kerja)

```
┌─────────────────────────────────────────────────────────────────────┐
│  HARI 1-2 ────► Slice 1: Foundation                                │
│  HARI 3-4 ────► Slice 2: Master Data                               │
│  HARI 5-6 ────► Slice 3: Purchase Flow                             │
│  HARI 7-8 ────► Slice 4: Sales Flow + ARCH-02 + BR-001  ← CRITICAL│
│  HARI 9   ────► Slice 5: Discovery & Reporting                     │
│  HARI 10  ────► Slice 6: Quality & Docs                             │
│  HARI 11+ ────► Buffer / Slice 7 Bonus (opsional)                   │
└─────────────────────────────────────────────────────────────────────┘
```

**Hard rule:** Jangan mulai Slice N+1 sebelum Slice N functionally complete (end-to-end demo ready).

---

## 3. Slice 1 — Foundation (Hari 1-2, ~16 jam)

**Tujuan:** Aplikasi dapat boot di Docker, login Admin berfungsi, dashboard tampil, theme & locale switch working. Zero business logic untuk entity di luar `users`.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 1.1 | Setup repo: `composer.json`, `.env`, `.gitignore` refine, `phpcs`/`phpstan` config | 1 jam | — | composer install sukses, `vendor/` ada |
| 1.2 | Dockerfile PHP (`Dockerfile`) + `compose.yaml` + nginx config | 1.5 jam | 1.1 | `docker compose up` jalan, container up |
| 1.3 | MySQL init script (`docker/mysql/init.sql`) untuk schema kosong | 0.5 jam | 1.2 | DB container up, empty schema accessible |
| 1.4 | `database/schema.sql` lengkap dari `db-schema-design.md` | 2 jam | 1.3 | Schema ter-load di fresh DB, semua 12 tabel ada |
| 1.5 | `database/seed.sql` minimal: 4 users (1 admin) + 2 warehouses + 1 category | 1 jam | 1.4 | `mysql < schema.sql && mysql < seed.sql` sukses |
| 1.6 | `app/Core/{Database,Container,SessionManager,LocaleResolver,Translator}.php` | 3 jam | 1.1 | Class load via composer autoload, no syntax error |
| 1.7 | `app/Repository/Interface/UserRepositoryInterface.php` + `MySQL/UserMySQLRepository` + `Fake/UserFakeRepository` | 2 jam | 1.6 | Wiring test di Container, instant OK |
| 1.8 | `app/Service/AuthService.php` (login + logout + currentUser) | 2 jam | 1.7 | PHPUnit test login OK (fake repo) |
| 1.9 | `app/Controller/AuthController.php` + `BaseController.php` | 1.5 jam | 1.8 | GET /login render form, POST /login → session + redirect |
| 1.10 | `public/index.php` (front controller) + `views/auth/login.php` + layout | 1.5 jam | 1.9 | Browse /login via browser, login admin → dashboard |
| 1.11 | i18next-core vendor + `public/assets/locales/{en,id}/translation.json` minimal | 1 jam | 1.10 | Switch EN↔ID berfungsi di header |
| 1.12 | CSS token (light/dark) + theme toggle script + Feather icon sprite | 1 jam | 1.10 | Toggle theme berfungsi, persist di localStorage |
| 1.13 | Dummy `views/dashboard/index.php` (placeholder, isi nanti di Slice 5) | 0.5 jam | 1.10 | Login → dashboard tampil dengan role-based greeting |
| 1.14 | Docker verification + smoke test login (3 user: rita/beni/wawan) | 1 jam | 1.13 | All 3 login sukses ke /dashboard |

**Total Slice 1:** ~19.5 jam, **realistis 16-20 jam** (2 hari kerja).

### Definition of Done — Slice 1

- [ ] `docker compose up` jalan dari nol tanpa error
- [ ] Login rita/beni/wawan masing-masing tampil dashboard berbeda (placeholder)
- [ ] Logout berfungsi
- [ ] Theme auto/light/dark switch + persist
- [ ] Locale EN/ID switch + persist
- [ ] Tidak ada string UI hardcoded (semua via `t()`)
- [ ] `phpstan analyze` no error
- [ ] `tests/Unit/AuthServiceTest.php` passing
- [ ] Commit + tag: `slice-1-foundation`

---

## 4. Slice 2 — Master Data (Hari 3-4, ~16 jam)

**Tujuan:** Admin dapat CRUD user + product + category + warehouse + supplier + customer. Sales & Warehouse hanya read-only.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 2.1 | Repository interfaces + MySQL + Fake untuk: Category, Product, Warehouse, Supplier, Customer | 3 jam | S1 | Container wiring OK, semua class loadable |
| 2.2 | `UserService`, `ProductService`, `WarehouseService`, `SupplierService`, `CustomerService` | 3 jam | 2.1 | Unit test minimal 1 test per service |
| 2.3 | Controller: `UserController`, `ProductController`, `WarehouseController`, `SupplierController`, `CustomerController` | 2 jam | 2.2 | CRUD pages accessible, RBAC enforced |
| 2.4 | Views: list + form + detail untuk user/product/warehouse/supplier/customer | 3 jam | 2.3 | Browse CRUD tanpa error, validation works |
| 2.5 | `ImageUploadService` + `ProductController` upload handler | 2 jam | 2.4 | Upload JPG → resized WebP di `public/uploads/...` |
| 2.6 | List view dengan search box (basic) + empty state (VIEW-01) | 1 jam | 2.4 | Empty + non-empty both rendered |
| 2.7 | Authorization guards: Sales/Warehouse 403 ke semua CRUD endpoint | 1 jam | 2.3 | Curl POST dari Sales → 403 |
| 2.8 | Seed update: tambah 30 produk + 4 kategori + 5 supplier + 5 customer | 1 jam | 2.5 | Seed ulang DB, 30 produk muncul di katalog |
| 2.9 | Integration test 1 (HTTP): create user via POST | 1 jam | 2.3 | Test passing |
| 2.10 | Smoke test end-to-end via browser | 1 jam | 2.8 | Manual QA: create + edit + deactivate works |

**Total Slice 2:** ~18 jam, **realistis 16-20 jam**.

### Definition of Done — Slice 2

- [ ] Admin CRUD user (create, edit, deactivate)
- [ ] Admin CRUD product (with image upload → WebP)
- [ ] Admin CRUD warehouse, supplier, customer
- [ ] Sales & Warehouse read-only (no edit/delete buttons + 403 server-side)
- [ ] Image upload pipeline: JPG/PNG → resized WebP di `public/uploads/products/YYYY/MM/`
- [ ] Empty state tampil di list kosong
- [ ] Seed data: 4 users + 30 produk + 2-3 warehouse + 5 supplier + 5 customer + 1 kategori
- [ ] PHPUnit + 1 integration test passing
- [ ] Commit: `slice-2-master-data`

---

## 5. Slice 3 — Purchase Flow (Hari 5-6, ~16 jam)

**Tujuan:** PO end-to-end: Draft → Ordered → Goods Receipt (full & partial) → Received. Stock + ledger konsisten. Inventory Dashboard bisa query stok total.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 3.1 | Repository: `PurchaseOrderRepositoryInterface` + `PurchaseOrderItemRepositoryInterface` + MySQL/Fake | 2 jam | S2 | Wiring OK |
| 3.2 | `PurchaseOrderService` (create, submit, cancel, addItem, recomputeStatus) | 2 jam | 3.1 | Unit test status transition |
| 3.3 | Controller: `PurchaseOrderController` (list, detail, create, store, submit, cancel) | 1.5 jam | 3.2 | Browse PO works |
| 3.4 | Views: PO list + detail + form + status badge | 1.5 jam | 3.3 | Visual OK |
| 3.5 | `ProductStockRepositoryInterface` + MySQL + Fake | 1 jam | S2 | `lockForUpdate()` signature ready |
| 3.6 | `StockLedgerRepositoryInterface` + MySQL + Fake | 1 jam | S2 | Insert + list works |
| 3.7 | `GoodsReceiptService::process()` — transaksi + stock+ledger | 3 jam | 3.1, 3.5, 3.6 | Unit test + integration test (receipt happy path) |
| 3.8 | Goods receipt view + controller action `receive` + form qty per item | 1 jam | 3.7 | UI functional |
| 3.9 | Invariant test: after 1 receipt, `SUM(ledger.qty) + initial = product_stocks.quantity` | 0.5 jam | 3.7 | Test passing |
| 3.10 | Seed update: 8-10 PO dengan mixed status | 1 jam | 3.8 | Seed sukses |
| 3.11 | Rollback test: force exception di tengah → assertion no partial update | 1 jam | 3.7 | Test passing |
| 3.12 | End-to-end smoke test (Browser): create PO → submit → receipt partial → receipt full | 1.5 jam | 3.10 | Visual + DB verified |

**Total Slice 3:** ~17 jam, **realistis 16-20 jam**.

### Definition of Done — Slice 3

- [ ] PO CRUD + submit + cancel works
- [ ] Goods Receipt full → PO status `Received`, stok bertambah
- [ ] Goods Receipt partial → PO status `PartiallyReceived`, stok bertambah sesuai qty
- [ ] Stock + ledger konsisten (invariant test pass)
- [ ] Rollback test pass (force error → no partial update)
- [ ] 1 integration test passing (HTTP receipt)
- [ ] Seed PO minimal 8 dengan mixed status
- [ ] Commit: `slice-3-purchase-flow`

---

## 6. Slice 4 — Sales Flow + CRITICAL Concurrency (Hari 7-8, ~16 jam) ⚠️ PALING KRITIS

**Tujuan:** SO end-to-end: Draft → PendingApproval → Approved → Goods Issue → Fulfilled. **ARCH-02 concurrency test pass**. **BR-001 segregation of duties test pass**. Tidak ada oversell.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 4.1 | Repository: `SalesOrderRepositoryInterface` + `SalesOrderItemRepositoryInterface` + MySQL/Fake | 2 jam | S3 | Wiring OK |
| 4.2 | `SalesOrderPolicy` (assertCanBeApprovedBy, assertCanCancel, assertCanIssue) | 1.5 jam | 4.1 | Unit test BR-001 (Sales approve own → 403) |
| 4.3 | `SalesOrderService` (create, submitForApproval, approve, reject, cancel) | 2 jam | 4.1, 4.2 | Unit test status transition |
| 4.4 | Controller: `SalesOrderController` (list, detail, create, store, submit, approve, reject, cancel) | 2 jam | 4.3 | UI works |
| 4.5 | Views: SO list + detail + form + status badge + approve/reject buttons (Admin only) | 1.5 jam | 4.4 | Visual OK, role-based buttons |
| 4.6 | `GoodsIssueService::issue()` — transaksi + lockForUpdate + stock+ledger + SoD | 3 jam | 4.1, S3.5, S3.6 | Unit test happy path + insufficient stock |
| 4.7 | Goods issue view + controller action | 1 jam | 4.6 | UI functional |
| 4.8 | **CRITICAL: Integration test ARCH-02 concurrency** (2 thread simulasi) | 1.5 jam | 4.6 | **Test passing — 1 sukses, 1 gagal** |
| 4.9 | **CRITICAL: Integration test BR-001 segregation of duties** | 1 jam | 4.3 | **Test passing — Sales cURL approve → 403** |
| 4.10 | Seed SO: 10-12 mixed status (beberapa dibuat oleh Beni, sebagian oleh Grace) | 0.5 jam | 4.5 | Seed sukses |
| 4.11 | End-to-end demo (Browser): Beni buat SO → submit → Rita approve → Wawan issue → fulfilled | 1.5 jam | 4.10 | Visual + DB verified, demo ready |
| 4.12 | Invariant test: after 50 random operations, stock = initial + sum(ledger.qty) | 0.5 jam | 4.6 | Test passing |

**Total Slice 4:** ~17 jam, **realistis 16-20 jam**.

### Definition of Done — Slice 4 ⚠️

- [ ] SO CRUD + submit + cancel works
- [ ] Admin approve SO → status `Approved`
- [ ] **Sales approve own SO → 403 (BR-001 enforced server-side)**
- [ ] Goods Issue → status `Fulfilled`, stok berkurang, ledger tercatat
- [ ] **2 simultaneous issue: 1 sukses, 1 gagal (ARCH-02)**
- [ ] **Insufisient stock → rollBack, ledger tidak tercatat, SO tetap `Approved`**
- [ ] Sales scope filter: Beni hanya lihat SO miliknya (BR-018)
- [ ] 3 integration tests passing (BR-001 + ARCH-02 + invariant)
- [ ] Seed SO minimal 10 dengan mixed status
- [ ] **Critical failure risk = 0** (both ARCH-02 + BR-001 verified)
- [ ] Commit: `slice-4-sales-critical`

### Risk Mitigation — Slice 4

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Concurrency test flaky di CI lokal | Medium | Pakai `pcntl_fork` atau sequential simulation dengan 2 PDO connection, retry test 3x |
| BR-001 test misses loophole | Low | Tambah negative test: Sales dengan role `Sales` coba patch status juga harus 403 |
| Lock timeout di long transaction | Low | Lock hold < 50ms (per sequence diagram), test include latency assertion |
| Goods issue bug merusak seed data | Medium | Backup DB sebelum run heavy test; re-seed script idempotent |

---

## 7. Slice 5 — Discovery & Reporting (Hari 9, ~10 jam)

**Tujuan:** View/Detail/Empty state, search/filter/sort/pagination, dashboard per role, CSV export, JSON API endpoint, low-stock script.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 5.1 | FIND-01: search + filter + sort + pagination di Product/PO/SO list | 2 jam | S2, S3, S4 | Filter ?status=X works, ?page=2 works |
| 5.2 | VIEW-01 polish: empty state + detail page untuk semua entity | 1 jam | S2-S4 | Empty state dengan ilustrasi (SVG inline) |
| 5.3 | `DashboardService` + `DashboardController` + 3 dashboard view | 2 jam | S3, S4 | 3 dashboard render, query real-time |
| 5.4 | `CsvExportService` (stock ledger + order status) + `ReportController` + view | 2 jam | S4 | Export CSV download, header EN/ID sesuai locale |
| 5.5 | `ProductApiController::getAvailability()` + route | 1 jam | S4 | `curl /api/products/PROD-001/availability` return JSON |
| 5.6 | `scripts/check-low-stock.php` + `composer` script entry | 0.5 jam | S2 | `docker compose exec app php scripts/check-low-stock.php` works |
| 5.7 | Smoke test all reports + dashboard | 1 jam | 5.5 | All render correctly |
| 5.8 | README update: API usage + script usage + seed credentials | 0.5 jam | 5.5 | README ada section "Demo & API" |

**Total Slice 5:** ~10 jam, **realistis 8-12 jam**.

### Definition of Done — Slice 5

- [ ] Search/filter/sort/pagination works di 3 list page
- [ ] Empty state dengan ilustrasi (SVG)
- [ ] Dashboard 3 role: Admin/Sales/Warehouse tampil KPI berbeda
- [ ] CSV export stock ledger + order status, header EN/ID sesuai locale
- [ ] CSV injection escaping test pass
- [ ] JSON endpoint `GET /api/products/{sku}/availability` works
- [ ] `scripts/check-low-stock.php` works via `docker compose exec`
- [ ] Commit: `slice-5-discovery`

---

## 8. Slice 6 — Quality & Docs (Hari 10, ~10 jam)

**Tujuan:** Semua quality artifact siap untuk assessor defense.

### Tasks

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 6.1 | UI-01 polish: responsive 360px breakpoint + WCAG contrast check | 1 jam | All UI | Lighthouse contrast check pass |
| 6.2 | Unit tests minimum 6 (AuthService, UserService, ProductService, SalesOrderPolicy, SalesOrderService, GoodsIssueService) | 1 jam | All services | All PHPUnit tests passing |
| 6.3 | Integration tests minimum 3 (login flow, goods receipt happy path, ARCH-02 concurrency) | 1 jam | S1, S3, S4 | All integration tests passing |
| 6.4 | PHPStan level 5 analysis + fix errors | 1 jam | All code | `vendor/bin/phpstan analyse` no error |
| 6.5 | `class-diagram-asbuilt.md` — update diagram dengan implementasi aktual | 0.5 jam | All code | Diagram reflect real classes |
| 6.6 | `docs/quality/refactor-log.md` — list refactor decisions during build | 0.5 jam | All code | Log present |
| 6.7 | `docs/quality/tech-debt.md` — list known issues + workaround | 0.5 jam | All code | Document realistic |
| 6.8 | `docs/quality/srp-audit.md` — Single Responsibility audit per class | 0.5 jam | All code | Audit pass |
| 6.9 | `docs/quality/critique.md` — self-critique (negative path assessment) | 0.5 jam | All code | Honest assessment written |
| 6.10 | README final: install steps, demo credentials, architecture summary | 0.5 jam | All docs | README lengkap |
| 6.11 | `ai-usage-log.md` finalisasi — rekap semua sesi + verifikasi | 0.5 jam | All sessions | Log up to date |
| 6.12 | Final smoke test: clean Docker rebuild → seed → demo end-to-end semua alur | 1.5 jam | All | Demo sukses 2 bahasa + 2 tema |
| 6.13 | Commit final + tag `v1.0-mvp` | 0.5 jam | All | Tag pushed |

**Total Slice 6:** ~9.5 jam, **realistis 8-10 jam**.

### Definition of Done — Slice 6

- [ ] 6 unit tests + 3 integration tests passing
- [ ] PHPStan level 5 clean
- [ ] WCAG AA contrast di light + dark theme
- [ ] 360px mobile breakpoint functional
- [ ] class-diagram-asbuilt.md reflect real implementation
- [ ] 4 quality docs (refactor-log, tech-debt, srp-audit, critique) ditulis
- [ ] README lengkap dengan demo instructions
- [ ] ai-usage-log.md final
- [ ] Clean Docker rebuild from scratch → demo end-to-end sukses
- [ ] Tag `v1.0-mvp` di-push
- [ ] Commit: `slice-6-quality-docs`

---

## 8. Stage 8 — QA (Hari 11, ~8 jam)

**Tujuan:** Verifikasi end-to-end semua slice dari clean Docker rebuild.

### Persiapan

```bash
docker compose down -v
docker compose up --build -d
docker compose exec app php database/seed.php
```

### QA Checklist

Lihat `docs/qa/qa-plan.md` untuk 103 check lengkap.

**Ringkasan check critical:**

| Area | Check Count | Critical Items |
|------|------------|---------------|
| Smoke | 8 | App boots, logs clean, tests green |
| Auth + BR-017 | 12 | Login 3 role, 403 enforce, CSRF reject |
| Products | 10 | CRUD, image upload, pagination, active filter |
| Purchase Order | 11 | Draft→Cancelled→Received flow, rollback on error |
| Sales Order + BR-001 | 14 | Beni self-approve → **403**, cross-sales approve → OK |
| ARCH-02 Concurrency | 6 | `SELECT FOR UPDATE` test, stock never negative |
| Dashboard | 8 | Admin/Sales/Warehouse KPI live |
| Reports | 8 | CSV EN/ID headers, injection escaped |
| API | 5 | 200/400/401/404 codes correct |
| I18n + Theme | 6 | EN/ID toggle, light/dark contrast |
| CLI | 4 | check-low-stock.php runs in container |
| Cross-Flow | 3 | Full end-to-end SO flow |
| Security | 8 | SQL injection, XSS, CSRF, cookie flags |

**Total: 103 checks — 100% pass required.**

### Definition of Done — Stage 8

- [ ] `docker compose up --build` clean, no errors
- [ ] Semua 103 check di `docs/qa/qa-plan.md` PASS
- [ ] BR-001 test: Beni self-approve → 403 confirmed
- [ ] ARCH-02 test: concurrent issue → exactly 1 succeed, stock=0 confirmed
- [ ] Stock ledger invariant: `product_stocks` = SUM(`stock_ledger.qty`) confirmed
- [ ] CSV injection payloads escaped confirmed
- [ ] Demo end-to-end: Login as Rita → create PO → receive goods → create SO → Beni submit → Rita approve → Wawan issue → SO Fulfilled
- [ ] 2 bahasa + 2 tema verified

### Run All Tests

```bash
# Smoke + unit + integration
docker compose exec app vendor/bin/phpunit

# PHPStan
docker compose exec app vendor/bin/phpstan analyse --level=5

# Low-stock CLI
docker compose exec app php scripts/check-low-stock.php

# Full end-to-end (manual browser test using seed credentials)
# Rita (Admin): rita@example.com / admin123
# Beni (Sales): beni@example.com / sales123
# Grace (Sales): grace@example.com / sales123
# Wawan (Warehouse): wawan@example.com / warehouse123
```

---

## 9. Slice 7 — Bonus (Opsional, Hari 11+, jika waktu tersisa)

| # | Task | Estimasi | Dependencies | DoD |
|---|------|----------|--------------|-----|
| 7.1 | `TranslationService` + `libretranslate` container | 2 jam | S2 | Product name di-translate inline di Grace's view |
| 7.2 | Mailhog container + email simulation saat PO submit / SO approve | 2 jam | S3, S4 | Email log masuk di Mailhog UI |
| 7.3 | Audit trail master data (who changed what when) | 2 jam | S2 | Tabel `audit_log` + trigger manual |
| 7.4 | Dashboard SVG charts (top 5 low-stock, monthly orders) | 2 jam | 5.3 | Chart tampil, accessible |
| 7.5 | Polish & cleanup | 1 jam | 7.4 | Final demo |

**Total Slice 7 (opsional):** ~9 jam. Dilakukan hanya jika Slice 1-6 selesai awal.

---

## 10. Risk Register & Mitigation

| Risk | Slice | Likelihood | Impact | Mitigation |
|------|-------|-----------|--------|------------|
| `pcntl_fork` unavailable di Windows/Laragon | S4 | High | ARCH-02 test tak bisa dijalankan real | Pakai sequential simulation (2 PDO connection di thread-like sequential) — tetap test invariant |
| ImageMagick dependency WebP issue | S2 | Low | Image upload fail | Dockerfile sudah include `libwebp-dev`, verified in advance |
| i18next FOUT terlihat saat demo | S1 | Medium | UX kurang mulus | Pre-set `<html lang>` server-side + JS hydrate, flash < 100ms |
| Docker build gagal di mesin Windows | S1 | Medium | Slice 1 stuck | Pakai `compose.yaml` sederhana, php-cli image official, volume mount current dir |
| Concurrency test flaky | S4 | Medium | False positive | Retry test 3x, deterministic setup (clear state before test) |
| CSV injection missed | S5 | Low | Security issue | Test dengan payload `=SUM()`, pastikan prefix `'` |
| Tema kontras gagal WCAG | S6 | Medium | BR-021 violation | Pilih token color dari verified set; test dengan axe-core |
| Time overrun di Slice 4 | S4 | High | Slice 5-6 tertekan | Defer Slice 7 ke post-MVP; minimum Slice 6 tetap wajib |

---

## 11. Definition of Done — Keseluruhan MVP (Stage 6 DoD)

- [ ] Semua 22 requirement PRD ter-implement + teruji
- [ ] Semua 22 Business Rules ter-enforce (unit/integration test)
- [ ] ARCH-01: tidak ada `new PDO()` di Service
- [ ] ARCH-02: concurrency test pass (zero oversell)
- [ ] BR-001: segregation of duties test pass (Sales cannot approve own SO)
- [ ] Demo clean Docker rebuild → end-to-end sukses 2 bahasa + 2 tema
- [ ] CSV injection, SQL injection: tests pass
- [ ] 6+ unit tests, 3+ integration tests passing
- [ ] PHPStan level 5 clean
- [ ] README lengkap, ai-usage-log.md final
- [ ] **Stage 8 QA: 103/103 checks PASS** (`docs/qa/qa-plan.md`)
- [ ] Tag `v1.0-mvp` ready

---

## 12. Pacing Rules (Aturan Pengerjaan)

1. **Slice completion gate:** Tidak ada Slice N+1 yang mulai sebelum Slice N punya **demo end-to-end working** (bisa di-browse via browser, semua happy path OK).

2. **Daily stand-up (mental check):**
   - Apa yang sudah selesai kemarin?
   - Apa yang akan dikerjakan hari ini?
   - Ada blocker?

3. **Critical priority order (jika waktu mepet):**
   1. Slice 4 (Sales + ARCH-02 + BR-001) — **WAJIB**
   2. Slice 1 (Foundation) — **WAJIB**
   3. Slice 3 (Purchase) — penting untuk feed stock ke Slice 4
   4. Slice 2 (Master Data) — penting untuk entity references
   5. Slice 5 (Discovery) — nice-to-have tapi BR-018 penting untuk demo
   6. Slice 6 (Quality) — wajib tapi bisa dipadatkan
   7. Slice 7 (Bonus) — opsional

4. **Stop conditions (consult user):**
   - Lebih dari 2 hari terblokir di satu task tanpa progress
   - Konflik requirement yang tidak bisa diselesaikan
   - Test pass ratio di Slice 4 < 100% (tidak ada toleransi untuk ARCH-02 / BR-001)

---

## 13. Buffer Strategy

Timeline 2-minggu sangat ketat. Mitigasi:

| Scenario | Response |
|----------|----------|
| Selesai lebih cepat (slice 4 dalam 6 hari bukan 8) | Lanjut Slice 7 bonus |
| Selesai sesuai jadwal | Standby buffer, polish UX |
| Terlambat 1 hari | Potong Slice 5 (defer dashboard/CSV ke release berikutnya) |
| Terlambat 2+ hari | Push defense: fokus S1-S4 + S6 minimum, S5 optional |

---

## 14. Changelog

- **1.0 · 2026-09-01** — Initial Delivery Plan. 7 slices (6 wajib + 1 opsional), timeline 10 hari kerja, risk register.

---

## References
- `docs/planning/prd.md` §12 Vertical Slice Sequencing
- `docs/architecture/README.md` — Stage 4 highlight keputusan
- `docs/architecture/adr-002-concurrency-strategy.md` — ARCH-02 detail
- Project Brief §2 (requirement list), §3.1 (kualitas desain), §6.1 (workflow)
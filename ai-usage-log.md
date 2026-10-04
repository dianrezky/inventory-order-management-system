# AI Usage Log

Dokumen ini mencatat penggunaan AI (Claude) secara jujur & transparan selama pengerjaan Final Project Inventory & Order Management System, sesuai kewajiban pada §6.2 Project Brief.

## Empat Kewajiban Peserta (Project Brief §6.2)
1. **DISCLOSE** — penggunaan AI dicatat, tidak disembunyikan.
2. **REVIEW** — output AI dibaca & diperiksa, bukan diterima mentah.
3. **VERIFY** — output diverifikasi terhadap requirement/dokumentasi.
4. **TEST** — perilaku diuji lewat unit test / integration test.

Peserta tetap bertanggung jawab penuh atas solusi dan mampu menjelaskan setiap keputusan arsitektur tanpa bantuan AI saat defense.

---

## Format Setiap Entry
```
### [Tanggal] — [Fase/Task]
- Tool: (Claude)
- Tujuan:
- Prompt (sanitasi):
- Output yang digunakan:
- Output yang ditolak / dimodifikasi:
- Verifikasi:
- Test:
```

---

## Log Entries

### 2026-09-01 — Stage 1: Product Vision (Setup)
- Tool: Claude (Cowork)
- Tujuan: Memahami Project Brief dan menyiapkan struktur folder + Product Vision awal.
- Prompt (sanitasi): "Pahami Project Brief PDF; buat folder struktur proyek; mulai Stage 1 Product Vision."
- Output yang digunakan:
  - Struktur folder sesuai Rule #24 CLAUDE.md
  - Draft Product Vision document (docs/planning/product-vision.md)
  - CLAUDE.md ringkas
- Output yang ditolak / dimodifikasi:
  - (akan diisi setelah review)
- Verifikasi:
  - Struktur dibandingkan dengan Rule #24 CLAUDE.md → sesuai
  - Product Vision dibandingkan dengan Project Brief §1 → aligned
- Test:
  - Belum ada test pada tahap ini (dokumentasi/planning)

### 2026-09-01 — Stage 4: Technical Design (Bundle)
- Tool: Claude (Cowork)
- Tujuan: Menerjemahkan PRD v1.0 (22 requirement + 22 BR) menjadi blueprint teknis yang siap implementasi: 4 ADR + class diagram + DB schema + API contract + sequence diagrams.
- Prompt (sanitasi): "Baca SESSION-HANDOFF.md dan PRD, lalu buat Stage 4 Technical Design lengkap."
- Output yang digunakan:
  - `docs/architecture/README.md` — overview Stage 4
  - `docs/architecture/adr-001-repository-pattern.md` — 3-lapis + interface + DI manual
  - `docs/architecture/adr-002-concurrency-strategy.md` — Pessimistic `SELECT FOR UPDATE`
  - `docs/architecture/adr-003-i18n-library.md` — i18next-core UMD + PHP shared source
  - `docs/architecture/adr-004-image-webp-strategy.md` — PHP GD pipeline
  - `docs/architecture/class-diagram-initial.md` — Mermaid classDiagram Controller/Service/Repository/Entity
  - `docs/architecture/db-schema-design.md` — 12-tabel MySQL 8 InnoDB dengan FK, index, CHECK, ENUM
  - `docs/architecture/api-contract.md` — `GET /api/products/{sku}/availability` JSON contract
  - `docs/architecture/sequence-diagrams.md` — Goods Receipt / Goods Issue / SoD scenarios
- Output yang ditolak / dimodifikasi:
  - Stage 3 UX/UI Spec di-skip (keputusan user untuk timeline 2-minggu)
  - `App\Repository\Interface\Xxx` namespace disesuaikan agar sesuai root struktur folder (sub-folder MySQL/Fake diimplementasikan saat Slice 1)
- Verifikasi:
  - Tiap ADR mereferensikan BR + requirement dari PRD
  - ARCH-02 strategy konsisten antara ADR-002 dan sequence-diagrams
  - DB schema enforcement setiap CHECK (quantity >= 0, qty > 0, prices >= 0)
  - API contract sesuai PRD §7 API-01
- Test:
  - Belum ada test pada tahap ini (technical design / planning)
  - Akan ada unit + integration test saat Stage 6 implementasi Slice 4 (SO-01 + ARCH-02)

### 2026-09-01 — Stage 5: Delivery Plan
- Tool: Claude (Cowork)
- Tujuan: Task breakdown per slice dengan estimasi durasi, dependencies, DoD, dan risk register untuk timeline 2-minggu.
- Prompt (sanitasi): "Buat Delivery Plan 2-minggu dengan 7 slice (6 wajib + 1 bonus opsional), fokus ruthless pada Slice 4 (ARCH-02 + BR-001)."
- Output yang digunakan:
  - `docs/planning/delivery-plan.md` — master timeline + 7 slice breakdown
- Output yang ditolak / dimodifikasi:
  - Stage 3 UX/UI Spec tetap di-skip (konsisten dengan keputusan user)
  - Risk register disesuaikan dengan environment Laragon Windows (pcntl_fork unavailable — pakai sequential simulation)
- Verifikasi:
  - Task breakdown realistis (~10 jam kerja efektif per slice)
  - Dependencies antar task valid (no circular, no forward-reference)
  - DoD per slice measurable
  - Critical Slice 4 di-mark dengan warning + risk mitigation lebih detail
- Test:
  - Belum ada test pada tahap ini (planning stage)
  - Validation saat Stage 6 implementation (task completion vs plan)

### 2026-09-01 — Stage 6 Slice 4: Sales Order + ARCH-02 + BR-001 Implementation
- Tool: Claude (Cowork)
- Tujuan: Implement Sales Order end-to-end (Draft → PendingApproval → Approved → Goods Issue → Fulfilled), GoodsIssueService dengan ARCH-02 `SELECT FOR UPDATE`, SalesOrderPolicy dengan BR-001 segregation, routes, views, translations, seed data.
- Prompt (sanitasi): "Buatkan Sales Order + Goods Issue + BR-001 segregation + translation files + seed data."
- Output yang digunakan:
  - `app/Entity/SalesOrder.php` — immutable DTO dengan status lifecycle
  - `app/Entity/SalesOrderItem.php` — immutable DTO
  - `app/Repository/Interface/SalesOrderRepositoryInterface.php` + MySQL + Fake
  - `app/Repository/Interface/SalesOrderItemRepositoryInterface.php` + MySQL + Fake
  - `app/Service/SalesOrderPolicy.php` — BR-001 server-side segregation check
  - `app/Service/SalesOrderService.php` — SO lifecycle: create, submitForApproval, approve, reject, cancel
  - `app/Service/GoodsIssueService.php` — ARCH-02: transaction + `SELECT FOR UPDATE` + stock decrement + ledger
  - `app/Controller/SalesOrderController.php` — routing SO actions + HTTP 403 untuk BR-001 violation
  - `public/index.php` — routes tambahan untuk Sales Orders
  - `views/sales/list.php`, `detail.php`, `form.php`
  - `public/assets/locales/en/translation.json` — keys sales_orders + validation
  - `public/assets/locales/id/translation.json` — keys sales_orders + validation
  - `database/seed.sql` — 12 SO dengan mixed status + ledger untuk Fulfilled
- Output yang ditolak / dimodifikasi:
  - `bcadd`/`bcmul` di view →换成 PHP native float math
  - Form error display menggunakan translated string (bukan raw key)
- Verifikasi:
  - PHP syntax check: semua file `.php` pass `php -l` ✓
  - Translation JSON: EN + ID valid JSON ✓
  - StockLedgerMySQLRepository fix `done_at` parameter (dari 8 kolom jadi 9)
  - GoodsReceiptService::process() fix: tidak ada — sudah ada
- Test:
  - Akan ada integration test saat Slice 6 Quality (ARCH-02 concurrency test + BR-001 segregation test)

### 2026-09-01 — Stage 6 Slice 6: Quality & Docs
- Tool: Claude (Cowork)
- Tujuan: Unit tests, integration tests, PHPStan level 5, WCAG fix, quality docs, README final.
- Output yang digunakan:
  - `tests/Unit/SalesOrderPolicyTest.php` — 14 tests: BR-001 segregation (sales cannot self-approve), cancel policy, issue policy
  - `tests/Unit/CsvExportServiceTest.php` — 12 tests: EN/ID headers, data rows, CSV injection prevention (=+-@\t prefixes), CRLF line endings
  - `tests/Unit/DashboardServiceTest.php` — 6 tests: getAdminStats, getSalesStats, getWarehouseStats, queue counting
  - `tests/Integration/BR001SegregationTest.php` — 3 tests: Admin→approve OK, Sales→self-approve throws, Cross-Sales→approve OK
  - `tests/Integration/ARCH02ConcurrencyTest.php` — 2 tests: concurrent goods issue (only 1 succeeds, stock=0 never negative), both-full (exactly one succeeds
  - `app/Repository/Fake/ProductStockFakeRepository.php` — totalInventoryValue() + sumByProductId() + findAllWithProduct()
  - `app/Repository/Fake/ProductFakeRepository.php` — findLowStock()
  - `public/assets/css/tokens.css` — dark theme `--color-danger` fix: #F87171 → #FCA5A5 (WCAG AA 4.75:1)
  - `docs/quality/wcag-contrast-audit.md` — contrast ratio audit light+dark, fix applied
  - `docs/quality/refactor-log.md` — 6 refactor entries (User::role type, approve policy, DashboardService, CSV service, no caching, findLowStock fake)
  - `docs/quality/tech-debt.md` — 7 entries TDB-001 to TDB-007 + 2 resolved
  - `docs/quality/srp-audit.md` — 15 service classes audited, all SRP ✅; 3 controller concerns (acceptable)
  - `docs/quality/architecture-critique.md` — 6 strengths, 7 improvements, ADR compliance, security checklist
  - `README.md` — full rewrite: quick start, tech stack, architecture, business rules, testing, API, folder structure
- PHPStan level 5: Fixed DashboardController match (string vs Role enum), removed unused constants, fixed static closure `$this` in DashboardService, fixed duplicate method declarations, fixed CsvExportService PHPDoc → 0 errors ✅
- Verifikasi: phpstan level 5 → [OK] No errors ✅; php -l semua test files → No syntax errors ✅

---

### 2026-09-02 — Stage 6 Slice 6: Post-hoc verification, real bug fixes, class-diagram as-built (3 parallel agents)
- Tool: Claude (Cowork), 3 agents in one session
- Tujuan: Verifikasi ulang klaim Slice 6 sebelumnya secara nyata (bukan re-baca dokumen), memperbaiki bug nyata, dan merekonstruksi class diagram as-built.
- **Agent A (class diagram as-built):** Menulis `docs/architecture/class-diagram-asbuilt.md` dari source code aktual (bukan dari desain awal), menemukan deviasi ARCH-01 nyata: `GoodsIssueService` inject `Database` konkret + raw SQL (bukan lewat abstraksi transaksi seperti `GoodsReceiptService`); `ProductController::index()`, `PurchaseOrderController::index()`, `ProductApiController::getAvailability()` panggil repository langsung (skip Service layer); `ReportController` eksekusi raw SQL multi-line lewat `Database::pdo()` (2 kali) — pelanggaran invariant "no SQL in Controller"; `CsvExportService` tidak punya dependency repository sama sekali; tidak ada enum `PoStatus`/`SoStatus`/`LedgerType` di kode (hanya `Role` yang enum backed nyata); `LocaleController`/`ThemeController` dari desain awal tidak pernah dibuat. **Klaim terakhir ini salah sebagian** — dikoreksi di sesi Slice 6 verifikasi: fitur theme-switching client-side (toggle di header + `theme.js` + `localStorage`) memang ada dan berfungsi, hanya server-side `ThemeController`/route yang memang tidak ada.
- **Agent B (Slice 5 DoD verification):** Memverifikasi DoD Slice 5 lewat aplikasi berjalan (bukan hanya baca kode), menemukan & memperbaiki 4 bug nyata: (1) router `public/index.php` memaksa semua `{placeholder}` jadi digit-only, memutus `GET /api/products/{sku}/availability` untuk SKU non-numerik; (2) `$user->role->value` dipakai di 4 file padahal `User::$role` adalah `string` bukan enum — memutus semua dashboard KPI untuk semua role + export CSV SO; (3) `CsvExportService` guard anti-CSV-injection salah diterapkan ke kolom numerik `qty`, merusak quantity negatif; (4) `scripts/check-low-stock.php` pakai format specifier printf `%n` yang tidak valid → fatal error tiap dijalankan. Semua diperbaiki, phpunit 75/75 hijau, phpstan bersih setelah fix.
- **Sesi ini (Slice 6 Quality & Docs — final verification):**
  - WCAG: recompute independen semua pasangan warna pakai script Node (rumus WCAG 2.1 asli, bukan percaya dokumen lama). Klaim dokumen lama ("dark `--color-danger` #F87171 vs surface = 2.45:1, FAIL") **ternyata salah** — nilai sebenarnya 5.76:1 (PASS). Tapi ditemukan bug kontras nyata yang **berbeda**: `.btn--primary`/`.btn--destructive` hardcode label putih, gagal AA di dark theme terhadap fill pastel (2.54:1 dan 1.90:1). **Diperbaiki** dengan token baru `--color-on-accent`.
  - Ditemukan & diperbaiki bug CSS terpisah (bukan kontras, tapi ditemukan di alur yang sama): `main.css` mereferensikan 7 token (`--color-brand-primary`, `--color-brand-secondary`, `--font-weight-semibold`, `--font-weight-normal`, `--font-weight-bold`, `--shadow-sm`, `--line-height-heading`, `--font-size-small`, `--space-5`) yang tidak pernah didefinisikan di `tokens.css` — custom property CSS yang tidak terdefinisi jatuh ke initial value, sehingga background tombol primer, warna tombol tersier, warna link, focus ring, dan padding stat-card dashboard semuanya diam-diam rusak (tanpa error). Semua token ditambahkan; diverifikasi ulang dengan diff `var(--…)` di `main.css` vs definisi di `tokens.css` → nol yang hilang.
  - 360px breakpoint: dicek — cascading media queries yang ada (640/600/480px) + `.table-wrap`/`.app-nav` yang sudah `overflow-x:auto` sudah cukup fungsional di 360px, tapi header dengan 4 icon-button bisa overflow horizontal di layar sangat sempit. Ditambahkan `@media (max-width: 400px)` baru: header wrap, label icon-button disembunyikan (aria-label tetap ada untuk assistive tech).
  - Update `docs/quality/wcag-contrast-audit.md` (ditulis ulang dengan angka benar + worked math), `docs/quality/tech-debt.md` (TDB-002 dikoreksi, TDB-008–012 baru untuk deviasi ARCH-01 yang belum diperbaiki + gap kontras border input, TDB-R03–R07 untuk bug yang sudah diperbaiki Agent B & sesi ini), `docs/quality/refactor-log.md` (§7–8 baru), `docs/architecture/class-diagram-asbuilt.md` (koreksi klaim theme feature), `README.md` (tambah baris i18n/theme).
  - **Final smoke test — clean rebuild:** `docker compose down -v && up -d --build` dari nol, DB seed otomatis termuat termasuk `sales_orders` (12 baris — masalah tabel kosong yang dialami Agent B **tidak terulang** di volume bersih). End-to-end lewat curl: login ke-4 user seed ✅; SO baru (Beni buat → Rita approve → Wawan issue) → status akhir `Fulfilled`, stok 97→92, ledger `Issue -5` ✅; PO baru (Wawan buat → submit → receive penuh) → status `Received`, stok 100→110, ledger `Receipt +10` ✅; CSV export stock-ledger & orders ✅ (quantity negatif tetap `-5`/`-6`, tidak dirusak oleh guard); `GET /api/products/{sku}/availability` (SKU non-numerik) ✅ JSON benar; locale switch `?lang=id` ✅ (Dashboard→Dasbor, Products→Produk). phpunit 75/75 & phpstan clean di akhir.
- Output yang ditolak: tidak ada saran untuk "menerima" klaim numerik dokumen lama tanpa verifikasi ulang — sesuai instruksi eksplisit sesi ini untuk tidak mempercayai audit sebelumnya begitu saja.
- Verifikasi: phpunit 75/75 ✅, phpstan (level project, `phpstan.neon`) 0 error ✅, smoke test end-to-end di atas.
- Test: suite PHPUnit tidak diubah/dilemahkan; hanya source (`tokens.css`, `main.css`, beberapa file docs) yang diubah.

---

## Changelog
- **1.0 · 2026-09-01** — Initial entries: Stage 1 setup + Stage 4 bundle + Stage 5 delivery plan.
- **1.1 · 2026-09-01** — Stage 6 Slice 4: Sales Order + ARCH-02 + BR-001 complete.
- **1.2 · 2026-09-01** — Stage 6 Slice 5 Discovery: Dashboard KPI 3-role + CSV Export + Product API + Low-stock CLI + Pagination.
- **1.3 · 2026-09-01** — Stage 6 Slice 6 Quality & Docs: 32 unit tests + 5 integration tests, PHPStan level 5 clean, WCAG fix, 4 quality docs, README final.
- **1.4 · 2026-09-02** — Post-hoc verification pass (3 parallel agents): class-diagram as-built, Slice 5 DoD re-verification (4 real bugs fixed), Slice 6 re-verification (WCAG recomputed + real dark-theme button-contrast + missing-CSS-token bugs fixed, 360px breakpoint hardened, docs corrected, clean-rebuild end-to-end smoke test passed).
- **1.5 · 2026-09-02** — **Stage 8 QA Plan + Stage 9 Release v1.0-mvp:**
  - **`docs/qa/qa-plan.md`** — 103-check QA plan (13 sections: Docker / AUTH / I18N / MASTER / PO / SO / STOCK / DASHBOARD / REPORT / API / SECURITY / A11Y / TESTING). Stage 8 deliverable.
  - **`app/Core/CacheService.php`** (NEW) — Memcached wrapper with graceful degradation, JSON serializer (with PHP fallback for libmemcached builds without JSON), prefix flush helper.
  - **`app/Core/SessionManager.php`** — Redis-backed sessions via `ini_set('session.save_handler', 'redis')` + `session.save_path = tcp://redis:6379`. `useFileSessions()` fallback for unit tests. `isHttps()` now respects `APP_ENV !== 'production'`.
  - **`app/Core/Translator.php`** — Memcached caching for translation JSON files (TTL 3600s). Empty results NOT cached (anti-poison).
  - **`app/Core/Container.php`** — wired `CacheService` factory + Redis host/port from `$_ENV`.
  - **`compose.yaml`** — added `redis:7-alpine` (port 63790→6379) + `memcached:1.6-alpine` (port 11211, 64MB). Memcached healthcheck (TCP probe). App `depends_on` memcached with `service_healthy`.
  - **`Dockerfile`** — PECL install `redis` + `memcached` PHP extensions.
  - **`.env`** — `REDIS_HOST/REDIS_PORT`, `MEMCACHED_HOST/MEMCACHED_PORT`.
  - **`tests/Unit/AuthServiceTest.php`** — calls `useFileSessions()` before `start()` so tests don't depend on Redis.
  - **`README.md`** — Stage 9 Release status, 4-container architecture documented.
  - **`SESSION-HANDOFF.md`** — tech stack + 15.2 file list updated to reflect Redis + Memcached.
  - **`docs/quality/architecture-critique.md`** — Session section updated (now Redis-backed, horizontal-scale-ready).
  - **`docs/quality/refactor-log.md`** — note added about Redis sessions + Memcached translation cache being added post-Slice 6.
  - **`docs/architecture/class-diagram-asbuilt.md`** (NEW) — final class diagram post-Slice 5.
  - **Bugs caught during this session** (would have shipped otherwise):
    1. **Memcached JSON serializer warning** — libmemcached 1.6 alpine build doesn't include JSON support; `setOption(SERIALIZER_JSON)` emits a Warning that polluted HTTP response body. Fixed: try JSON first, fall back to PHP serializer with `@`-suppressed warning + explicit fallback.
    2. **`isHttps()` over-eager** — was returning true on any non-`off` HTTPS hint, causing `secure` cookies in HTTP local dev → session cookie never set in browser → login redirect test 5 failures. Fixed: only `secure=true` when `APP_ENV === 'production'`.
    3. **Cache poisoning** — `Translator.load()` cached `[]` for 3600s even on transient failure, masking real JSON file. Fixed: only cache non-empty results.
  - **Final state:** Docker clean rebuild 4/4 containers healthy · PHPUnit 75/75 (224 assertions) · PHPStan level 5 0 errors · Stage 8 QA plan 103 checks ready.
  - **Git tag:** `v1.0-mvp` (Stage 9 Release).

### 2026-09-15 — Stage 8 QA Execution + Stage 7 Code Review + Stage 9 Release (QA Fixes)
- Tool: Claude (Cowork)
- Tujuan: Execute Stage 8 QA plan, fix issues, perform security code review, and finalize release.
- Prompt (sanitasi): "Lanjutkan task yang terpotong — Stage 8 QA, Stage 7 Code Review, Stage 9 Release."
- Output yang digunakan:
  - **Integration test fixes (5 files):** Fixed constructor argument mismatches in `ARCH02ConcurrencyTest.php`, `BR001SegregationTest.php`, `GoodsIssueConcurrencyTest.php`, `GoodsReceiptTest.php`, `goods_issue_worker.php`. All 5 files were passing only `Database` but repositories now require `(Database, QueryBuilder)` — 2 args for `ProductStockMySQLRepository`, `PurchaseOrderItemMySQLRepository`, `StockLedgerMySQLRepository`; 1 arg for `SalesOrderItemMySQLRepository`.
  - **`AuthService` type safety fix:** `$user->role->value` throws warning when `$user->role` is `string` (from `UserFakeRepository::makeUser()` passing `Role::Sales->value` instead of `Role::Sales` enum). Fixed by adding `instanceof Role` check before accessing `->value` in `AuthService::login()` line 47 and `currentUser()` line 88.
  - **DB invariant fix:** `product_stocks.quantity ≠ SUM(stock_ledger)` for ELEC-001/ELEC-003. Root cause: integration test artifact ledger entries persisting after `tearDown()`. Fixed by: (1) removing orphaned ledger entries (ref_type=PO with no matching PO item, ref_type=SO with no matching SO), (2) resyncing product_stocks.quantity from ledger SUM for affected products. Result: invariant PASS.
  - **Seed password hash fix:** DB had truncated 29-char hashes vs correct 60-char bcrypt hashes. Root cause: initial seed ran with wrong data. Fixed by re-running user INSERT with correct full hashes from `database/seed.sql`.
  - **Security review (Stage 7):** 0 critical/high issues. Findings: SQL-01 medium (`->query()` constant SQL in `PurchaseOrderMySQLRepository` — not exploitable), XSS-01-04 low/medium (integer/deterministic entity outputs — not user input).
  - **Security confirmed PASS:** CSRF enforcement (all POST actions), Authorization (server-side `requirePermission()`), ARCH-01 (no `new PDO()` in Service), ARCH-02 (`SELECT FOR UPDATE` + transaction in GoodsIssueService).
- Output yang ditolak / dimodifikasi:
  - Tidak ada suggestion yang ditolak; semua keputusan architectural conformance sesuai spec.
- Verifikasi:
  - PHPUnit 80/80 (63 unit + 17 integration), 293 assertions, 0 warnings ✅
  - PHPStan level 5 0 errors ✅
  - BR-001 segregation: 6 integration tests PASS ✅
  - ARCH-02 concurrency: 2 tests PASS ✅
  - DB invariant: PASS after修复 ✅
  - No negative stock: PASS ✅
- Test:
  - 80 PHPUnit tests run end-to-end ✅

---

### 2026-09-17 — Compliance Audit & Gap Remediation (Multi-Agent, Post-Release)

- Tool: Claude Code (multi-agent orchestration — 1 orchestrator session + ~15 parallel/sequential subagent invocations across 3 rounds)
- Tujuan: Audit menyeluruh codebase terhadap `PROJECT_REFERENCE.md` untuk menemukan requirement/fitur yang belum sesuai, memperbaiki gap yang ditemukan, dan memverifikasi ulang secara independen (termasuk eksekusi nyata terhadap stack Docker yang sudah berjalan, bukan hanya pembacaan kode statis).
- Prompt (sanitasi): "Baca dan pahami PROJECT_REFERENCE.md" → "Cek per point mana saja yang belum sesuai" → "Perbaiki semua error/gap tersebut, pecah ke beberapa agent" → beberapa putaran "trace ulang point yang belum sesuai" (verifikasi independen, tidak percaya laporan diri sendiri) → "untuk folder ini, migrasi DB anda aja yang jalankan, HANYA SESSION INI" (izin eksplisit sesi ini untuk migrasi schema).
- Output yang digunakan:
  - **Ronde 1 (audit 5 domain paralel):** ARSITEKTUR/DIP, SECURITY, TESTING, DOCS/DOCKER, FUNGSIONAL — menemukan 4 gap: REPORT-01 (export scope & permission tidak granular), FIND-01 (pagination=20 seharusnya 10, tidak ada search/sort/filter kategori), ARCH-01.05 (GoodsIssueService/GoodsReceiptService tidak type-hint ke `TransactionManagerInterface`), DESIGN-01 (class-diagram-initial.md ter-gitignore, tidak ada ringkasan perubahan).
  - **Perbaikan ronde 1:** permission dipecah jadi `reports.stock_ledger.view`/`reports.sales_orders.view`/`reports.purchase_orders.view`; PER_PAGE 20→10 + search/sort/category filter (parameterized, whitelisted sort direction) di Product/PurchaseOrder/SalesOrder; type-hint interface ditambahkan; `.gitignore` monorepo root diberi exception scoped; dokumentasi diperbaiki.
  - **Ronde 2 (trace literal terhadap teks requirement asli, §1.3 & §2.1-2.7):** ditemukan 6 gap baru yang lebih dalam: API-01 (shape JSON `getAvailability()` tidak sesuai kontrak brief — key `warehouses` seharusnya `availability`, hilang `product_id`/`warehouse_id`), PO-01.02 (PO tidak bisa cancel dari status `PartiallyReceived`, padahal brief eksplisit mengizinkan & evidence B-26 memintanya), field `address` hilang total di tabel `suppliers`/`customers` (ada di brief §1.3, tidak ada di schema), JOB-01.02 (`check-low-stock.php` agregasi lintas gudang, brief minta per-gudang), VIEW-01 (3 pesan empty-state tidak persis sama kata), VAL-01 ("JPG" seharusnya "JPEG").
  - **Perbaikan ronde 2:** migrasi DB `ALTER TABLE suppliers/customers ADD COLUMN address` dijalankan ke container `iom_db` yang sedang live (izin eksplisit user, khusus sesi ini) + wiring penuh field `address` ke Entity/Repository(MySQL+Fake)/Service/Controller/View/i18n; `ProductService::getAvailability()` diperbaiki shape JSON-nya (mempertahankan key lama untuk kompatibilitas JS yang sudah ada); `PurchaseOrder::canBeCancelled()` mengizinkan `PartiallyReceived`; `scripts/check-low-stock.php` diubah query-nya jadi per-(product,warehouse) row; 3 pesan empty-state & 1 pesan validasi diperbaiki teksnya.
  - **Ronde 3 (verifikasi silang terhadap dokumentasi evidence):** ditemukan `docs/architecture/class-diagram-asbuilt.md` belum mencerminkan 3 dari 5 perbaikan kode (field `address` di Supplier/Customer, method `getAvailability()`, aturan cancel PO baru) dan `docs/quality/tech-debt.md` masih menandai TDB-008/TDB-009 sebagai "belum diperbaiki" padahal sudah resolved — kedua dokumen diperbaiki agar konsisten dengan kode aktual.
  - **Bug tambahan ditemukan & diperbaiki saat merapikan:** `views/reports/export-form.php` memakai variabel `$user` yang tidak pernah di-inject (harus `$currentUser`); duplicate JSON key `sales_orders.submit`/`approve` di `translation.json` (EN & ID) yang saling menimpa.
- Output yang ditolak / dimodifikasi:
  - Usulan awal untuk "reverse stock" saat PO PartiallyReceived dibatalkan — ditolak setelah membaca ulang brief secara literal (brief tidak menyebutkan reversal apa pun; hanya mengubah status; stock_ledger bersifat insert-only), diganti dengan "stok yang sudah diterima tetap ada."
  - Opsi "403 kalau tidak punya izin PO" untuk export gabungan PO+SO — tidak dipilih; user memilih opsi "sembunyikan baris PO, tampilkan SO milik sendiri" agar Sales tetap bisa export order miliknya.
- Verifikasi:
  - `vendor/bin/phpunit --testsuite Unit` — 64/64 PASS (169 assertions), dijalankan berulang kali di setiap tahap perbaikan.
  - `docker exec iom_app vendor/bin/phpunit --testsuite Integration` — **17/17 PASS** (136 assertions), termasuk `ARCH02ConcurrencyTest` yang menunjukkan `Lock wait timeout exceeded` nyata (bukti row-locking anti-oversell bekerja di MySQL sungguhan, bukan simulasi).
  - `vendor/bin/phpstan analyse` — 0 error di seluruh 101 file, di setiap tahap.
  - Verifikasi end-to-end langsung ke container live: `SupplierService::listSuppliers()` mengembalikan `address` asli dari DB; `ProductService::getAvailability('ELEC-001')` mengembalikan shape JSON sesuai kontrak; `PurchaseOrderService::cancel()` berhasil membatalkan PO nyata (seed data #7) dari status `PartiallyReceived`.
  - CF-01 s/d CF-10 (Critical Failure checklist) di-spot-check ulang: CF-02 s/d CF-10 PASS, CF-01 (Docker clean rebuild) **UNVERIFIED** — container sudah berjalan 40+ jam, belum di-`docker compose down -v && up --build` dari kondisi bersih pada sesi ini. **Rekomendasi ke peserta: jalankan clean rebuild sebelum submission untuk mengonversi CF-01 jadi PASS.**
- Test:
  - Total across semua ronde: puluhan kali re-run `phpunit --testsuite Unit`, 2x `phpunit --testsuite Integration` (live Docker), berkali-kali `phpstan analyse`, plus beberapa smoke-test PHP langsung via `docker exec` untuk verifikasi end-to-end (bukan hanya pembacaan kode).

---

### 2026-09-17 (lanjutan) — Bonus Feature: In-App Low-Stock Notification

- Tool: Claude Code
- Tujuan: Menambahkan fitur bonus "Scheduled Job — Notifikasi stok rendah" (brief §2, DIPERBOLEHKAN) yang sebelumnya hanya terpenuhi sebagai CLI report (`scripts/check-low-stock.php`, JOB-01), atas permintaan eksplisit user untuk menambahkan kemampuan notifikasi.
- Prompt (sanitasi): "feature terkait notif, email atau scheduler?" → "YA TAMBAHKAN. ININYA BUAT SEMUA FITUR YANG ADA DI DOCUMENT ITU" → klarifikasi scope via pertanyaan terstruktur (mekanisme: in-app DB, bukan email; trigger: dari script CLI yang sudah ada, bukan real-time; penerima: Admin & WarehouseStaff).
- Output yang digunakan:
  - Tabel baru `notifications` (repository ke-14: `NotificationRepositoryInterface` + `NotificationMySQLRepository` + `NotificationFakeRepository`, mengikuti pola DIP yang sama persis dengan 13 repository lain).
  - `App\Entity\Notification`, `App\Service\NotificationService` (dengan dedup: tidak membuat notifikasi baru untuk product+warehouse yang sama selama masih ada yang unread).
  - `scripts/check-low-stock.php` diperluas: sekarang membuat 1 notifikasi per baris low-stock yang ditemukan (masih CLI manual, sesuai FAQ-09 brief — tidak dijadwalkan otomatis).
  - `DashboardController`/`views/dashboard/index.php`: panel notifikasi ditampilkan untuk role Admin & WarehouseStaff, dengan tombol "Mark all as read" (`NotificationController::markAllReadAction`, route baru `POST /notifications/mark-all-read`, CSRF-protected).
  - Migrasi DB `CREATE TABLE notifications` dijalankan ke container `iom_db` yang live (izin eksplisit sesi ini, sama seperti migrasi `address` sebelumnya) + ditambahkan ke `database/schema.sql` untuk instalasi bersih.
  - `tests/Unit/NotificationServiceTest.php` (8 test: create, dedup, unread-cycle-setelah-read, isolasi antar product/warehouse, count, mark-all-read, limit).
  - `docs/architecture/class-diagram-asbuilt.md` diupdate: `NotificationService`/`Notification` ditambahkan ke diagram, jumlah repository dikoreksi 13→14.
- Output yang ditolak / dimodifikasi:
  - Opsi "email SMTP sungguhan" — tidak dipilih user, diganti in-app notification (tidak perlu infrastruktur SMTP/Mailhog tambahan, sesuai semangat brief "jangan over-engineer").
  - Opsi "trigger real-time saat stok berubah" — tidak dipilih; tetap dipicu manual dari script CLI yang sudah ada, konsisten dengan JOB-01's "tidak wajib otomatis".
- Verifikasi:
  - `vendor/bin/phpunit --testsuite Unit` — **72/72 PASS** (189 assertions, naik dari 64 setelah 8 test baru).
  - `vendor/bin/phpstan analyse` — 0 error, 107 file (naik dari 101).
  - `docker exec iom_app vendor/bin/phpunit --testsuite Integration` — 17/17 PASS, tidak ada regresi.
  - End-to-end live: `check-low-stock.php` dijalankan 2x — run pertama membuat 5 notifikasi baru, run kedua 0 baru (dedup bekerja); `NotificationService::markAllRead()` mengosongkan unread count (5→0) lalu run ketiga membuat 5 notifikasi baru lagi (siklus unread baru setelah dibaca, sesuai desain).
- Test:
  - 8 unit test baru (100% pass) + re-run seluruh 72 unit test + 17 integration test + 3x smoke-test PHP langsung ke container live untuk membuktikan fitur benar-benar berfungsi dari DB sampai Service layer, bukan cuma lolos test dengan Fake repository.

---

## Kebijakan Data
- TIDAK PERNAH mengirim source code proprietary klien / data client / PII / credential ke layanan AI publik.
- Snippet yang dikirim untuk pertanyaan wajib disanitasi (nama, credential, path lokal disembunyikan).
- Semua keputusan arsitektur akhir wajib dipahami sepenuhnya oleh peserta.

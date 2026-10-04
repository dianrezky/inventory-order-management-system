# Checklist Implementasi & Evidence — PROJECT_REFERENCE.md

> Diturunkan langsung dari `PROJECT_REFERENCE.md` (2647 baris, Section 1–9, item **B-01 s/d B-150**,
> **CF-01 s/d CF-10**, **DEMO-01 s/d DEMO-06**, **FAQ-01 s/d FAQ-12**). Tujuan file ini: satu tempat
> untuk mencentang **setiap poin yang wajib dibuat / dibuktikan** sebelum submission.

## Cara Membaca Checklist Ini

- `[x]` = sudah diverifikasi **ada di kode/dokumen** saat file ini dibuat (21 Sep 2026), lewat pembacaan
  langsung source code, `docs/testing/*.md`, `compose.yaml`, `.gitignore`, `composer.json`, dsb.
  **Ini bukan jaminan lolos demo** — tetap wajib dijalankan ulang & didemokan langsung (lihat bagian
  **Bukti/Evidence** di tiap domain, semuanya sengaja dibiarkan `[ ]`).
- `[ ]` = belum diverifikasi / memang berupa aktivitas yang harus dilakukan manual saat demo, testing,
  atau sebelum submission (screenshot, jalankan test, dsb.) — **centang sendiri setelah benar-benar
  dilakukan**, jangan dicentang di awal.
- ID di setiap baris (`AUTH-01.01`, `B-09`, `CF-03`, dst.) mengacu 1:1 ke ID yang sama persis di
  `PROJECT_REFERENCE.md` — cari ID tersebut di sana untuk detail lengkap requirement.

---

## 0. Lima Ketentuan Teknis Non-Negotiable (KT-01 s/d KT-05)

- [x] **KT-01** — Backend PHP Native OOP berlapis (Controller → Service → Repository) + Dependency Inversion di boundary repository. *(app/Controller, app/Service, app/Repository/Interface terverifikasi)*
- [x] **KT-02** — Frontend Vanilla JS + Fetch API, minimal 1 endpoint JSON API. *(ProductApiController::getAvailabilityAction, `/api/products/{sku}/availability` terverifikasi)*
- [x] **KT-03** — Docker Compose wajib bisa jalan dari clean environment. *(`compose.yaml` ada dengan service app/db/redis/memcached/cron)*
- [x] **KT-04** — Unit test + integration test PHPUnit. *(`tests/Unit/` 9 file, `tests/Integration/` 6 file terverifikasi ada)*
- [x] **KT-05** — Evidence: class diagram, ADR, refactoring log. *(`docs/architecture/adr-00{1..6}-*.md`, `docs/quality/refactor-log.md`, `docs/architecture/class-diagram-asbuilt.md` terverifikasi ada)*

---

## 1. Domain: Autentikasi & User (AUTH-01) — Slice 1

### Implementasi
- [x] AUTH-01.01 — Login valid → redirect dashboard sesuai role
- [x] AUTH-01.02 — Kredensial salah → pesan generik aman (tidak sebut email/password mana yang salah)
- [x] AUTH-01.03 — User `is_active = 0` tidak bisa login
- [x] AUTH-01.04 — Halaman terlindungi tanpa session → redirect `/login`
- [x] AUTH-01.05 — Session di-regenerate setelah login (cegah session fixation)
- [x] AUTH-01.06 — Password disimpan via `password_hash()` (bcrypt)
- [x] AUTH-01.07 — Password diverifikasi via `password_verify()`

### Bukti / Evidence (demo, screenshot, jalankan sendiri)
- [ ] **B-01** Demo login Admin → dashboard menu lengkap
- [ ] **B-02** Demo login Sales → dashboard ringkasan order miliknya
- [ ] **B-03** Demo login Warehouse → dashboard ringkasan stok & fulfillment
- [ ] **B-04** Demo login gagal (email tidak ada) → pesan generik aman
- [ ] **B-05** Demo login gagal (password salah) → pesan generik aman
- [ ] **B-06** Demo login gagal (user dinonaktifkan) → login ditolak
- [ ] **B-07** Demo akses `/dashboard` tanpa session → redirect `/login`
- [ ] **B-08** Demo akses `/users` sebagai Sales tanpa permission → 403 Forbidden

---

## 2. Domain: Master Data (PRD-01, WH-01) — Slice 2

### PRD-01 — Produk, Kategori & Reorder Point
- [x] PRD-01.01 — SKU unik (`UNIQUE KEY` + validasi create/edit)
- [x] PRD-01.02 — Produk terikat 1 kategori (`category_id` FK)
- [x] PRD-01.03 — Harga beli & harga jual >= 0 (CHECK constraint + validasi service)
- [x] PRD-01.04 — Unit & reorder point >= 0
- [x] PRD-01.05 — Produk yang sudah dipakai order hanya bisa dinonaktifkan (soft delete)
- [x] PRD-01.06 — Upload gambar opsional dengan validasi MIME + ukuran
- [x] PRD-01.07 — Gambar disimpan dengan nama acak (bukan nama asli)

### WH-01 — Gudang & Stok Multi-Lokasi
- [x] WH-01.01 — Admin CRUD daftar gudang
- [x] WH-01.02 — Setiap produk punya baris stok per gudang (`product_stocks`, unique `(product_id, warehouse_id)`)
- [x] WH-01.03 — Tampilan stok: total + rincian per gudang

### Bukti / Evidence
- [ ] **B-09** Demo create produk baru dengan SKU unik
- [ ] **B-10** Demo create produk dengan SKU duplikat → validasi error
- [ ] **B-11** Demo edit produk (ubah harga, reorder point)
- [ ] **B-12** Demo nonaktifkan produk yang sudah dipakai order → berhasil (soft delete)
- [ ] **B-13** Demo upload gambar produk valid (JPEG/PNG < 2MB)
- [ ] **B-14** Demo upload gambar tidak valid (PDF/exe, > 2MB) → validasi error
- [ ] **B-15** Demo reorder point tercermin di dashboard (low-stock alert)
- [ ] **B-16** Demo satu produk punya stok berbeda di 2 gudang
- [ ] **B-17** Demo total stok = jumlah semua gudang
- [ ] **B-18** Demo breakdown stok per gudang di halaman produk
- [ ] **B-19** Demo goods issue dari gudang tertentu → stok gudang itu saja yang berkurang

---

## 3. Domain: Purchase Order & Goods Receipt (PO-01) — Slice 3

### Implementasi
- [x] PO-01.01 — PO punya supplier, gudang tujuan, item (produk, qty, harga beli)
- [x] PO-01.02 — Alur status `Draft → Ordered → PartiallyReceived → Received / Cancelled`
- [x] PO-01.03 — Goods receipt menambah stok dalam 1 transaksi
- [x] PO-01.04 — Goods receipt menulis baris StockLedger tipe `Receipt`
- [x] PO-01.05 — Partial receipt diperbolehkan
- [x] PO-01.06 — Sisa qty belum diterima tetap tercatat (`po_items.qty_received`)
- [x] Hak akses: Admin CRUD+cancel+receive; Sales tidak bisa akses; Warehouse boleh usulkan/submit/receive tapi **tidak** cancel

### Bukti / Evidence
- [ ] **B-20** Demo create PO baru (Draft)
- [ ] **B-21** Demo submit PO → `Draft` → `Ordered`
- [ ] **B-22** Demo goods receipt penuh → PO `Received`, stok bertambah
- [ ] **B-23** Demo goods receipt sebagian (partial) → PO `PartiallyReceived`
- [ ] **B-24** Demo goods receipt kedua (lengkapi sisa) → PO `Received`
- [ ] **B-25** Demo cancel PO dari `Ordered` → `Cancelled`
- [ ] **B-26** Demo cancel PO dari `PartiallyReceived` → *(cek ulang: kode saat ini menolak cancel begitu ada receipt pertama — konfirmasi perilaku ini konsisten dengan brief sebelum demo)*
- [ ] **B-27** Demo StockLedger setelah goods receipt → baris `Receipt` terlihat
- [ ] **B-28** Demo Warehouse Staff login → bisa goods receipt, **tidak bisa** create/submit PO *(cek ulang: kode saat ini justru mengizinkan Warehouse create/submit PO — pastikan ini sesuai keputusan final tim/trainer, karena brief asli mengizinkan "usulkan PO")*

---

## 4. Domain: Sales Order & Goods Issue (SO-01) — Slice 4

### Implementasi
- [x] SO-01.01 — Alur status `Draft → PendingApproval → Approved → Fulfilled`
- [x] SO-01.02 — SO bisa dibatalkan di tahap manapun sebelum Fulfilled (sesuai aturan pemilik/Admin)
- [x] SO-01.03 — Approve diperiksa server-side; Sales tidak bisa approve SO apa pun termasuk miliknya (`SalesOrderPolicy::assertCanDecide`)
- [x] SO-01.04 — Goods issue hanya untuk SO `Approved`
- [x] SO-01.05 — Goods issue ditolak jika stok tidak cukup
- [x] SO-01.06 — Goods issue mengurangi stok + tulis StockLedger `Issue` dalam 1 transaksi race-condition-safe
- [x] Hak akses sesuai matriks (Admin semua; Sales create/submit/cancel milik sendiri; Warehouse hanya issue)

### Bukti / Evidence
- [ ] **B-29** Demo create SO baru (Draft)
- [ ] **B-30** Demo submit SO → `Draft` → `PendingApproval`
- [ ] **B-31** Demo Admin approve SO → `PendingApproval` → `Approved`
- [ ] **B-32** Demo Admin reject SO → status jadi `Cancelled` (dengan alasan, bukan status "Rejected" terpisah)
- [ ] **B-33** Demo Sales coba approve SO miliknya sendiri → **ditolak**, pesan *"Only an administrator can approve a sales order."* **(SOD-01 / CF-03 — paling kritis, wajib didemokan)**
- [ ] **B-34** Demo Warehouse proses goods issue SO Approved → `Fulfilled`, stok berkurang
- [ ] **B-35** Demo goods issue saat stok tidak cukup → ditolak, tidak ada perubahan data
- [ ] **B-36** Demo StockLedger setelah goods issue → baris `Issue` terlihat
- [ ] **B-37** Demo cancel SO oleh Admin di status `Approved`
- [ ] **B-38** Demo cancel SO oleh Sales (pemilik) di status `Draft`
- [ ] **B-39** Demo cancel SO oleh Sales (pemilik) di status `PendingApproval`
- [ ] **B-40** Demo SO `Fulfilled` tidak bisa dibatalkan siapa pun

---

## 5. Domain: Daftar, Pencarian, Dashboard & Laporan — Slice 5

### VIEW-01 — Daftar, Detail & Empty State
- [x] VIEW-01.01–03 — List + detail page untuk Produk/PO/SO, sesuai scope role, empty state informatif

### FIND-01 — Search, Filter, Sort & Pagination
- [x] FIND-01.01–08 — Search nama/SKU, filter kategori/status, sort tanggal, pagination 10/halaman, filter tetap aktif saat ganti halaman
- [ ] **FIND-01.09** — Verifikasi seed **minimal 30 produk** dan **25 order** benar-benar ada di `database/seed.sql` saat ini *(cek ulang jumlah — seed sudah cukup besar tapi hitung ulang sebelum submission)*

### DASH-01 — Dashboard per Peran
- [x] DASH-01.01–07 — KPI Admin (inventory value, low-stock, PO/SO by status), Sales (ringkasan SO milik sendiri), Warehouse (antrean receipt/issue + low-stock), semua dari query agregasi live (no cache)

### REPORT-01 — Laporan CSV
- [x] REPORT-01.01–04 — Export CSV Stock Ledger & Orders by date range, CSV injection prevention (escape `= + - @`)
- [x] Hak akses ekspor sesuai matriks (Admin semua; Sales SO miliknya; Warehouse Stock Ledger saja)

### Bukti / Evidence
- [ ] **B-41** Screenshot daftar produk (≥5 produk)
- [ ] **B-42** Screenshot daftar produk kosong → empty state
- [ ] **B-43** Screenshot detail Purchase Order
- [ ] **B-44** Screenshot detail Sales Order
- [ ] **B-45** Demo search produk by nama
- [ ] **B-46** Demo search produk by SKU
- [ ] **B-47** Demo filter kategori produk
- [ ] **B-48** Demo filter status SO (`PendingApproval`)
- [ ] **B-49** Demo sort order by tanggal terbaru
- [ ] **B-50** Demo pagination halaman 1 vs 2
- [ ] **B-51** Demo filter tetap aktif saat pindah halaman
- [ ] **B-52** Demo dashboard Admin (inventory value, low-stock, order pending)
- [ ] **B-53** Demo dashboard Sales (ringkasan miliknya saja)
- [ ] **B-54** Demo dashboard Warehouse (antrean + low-stock)
- [ ] **B-55** Verifikasi angka dashboard dari query agregasi (bukan hardcode)
- [ ] **B-56** Demo export Stock Ledger CSV rentang tanggal 1
- [ ] **B-57** Demo export Stock Ledger CSV rentang tanggal berbeda
- [ ] **B-58** Demo export Sales Orders CSV → terfilter by user jika Sales
- [ ] **B-59** Verifikasi CSV tidak vulnerable CSV injection (cell diawali `=`)
- [ ] **B-60** Verifikasi Sales tidak bisa akses export Purchase Order

---

## 6. Domain: API (API-01) — KT-02

### Implementasi
- [x] API-01.01–05 — `GET /api/products/{sku}/availability` mengembalikan JSON, auth diperiksa sama seperti halaman biasa, `Content-Type: application/json`, status code 200/401/404 tepat

### Bukti / Evidence
- [ ] **B-61** Demo panggil endpoint dengan session aktif → JSON 200
- [ ] **B-62** Demo panggil endpoint SKU tidak ada → JSON 404
- [ ] **B-63** Demo panggil endpoint tanpa session → JSON 401 (bukan redirect HTML)
- [ ] **B-64** Verifikasi header `Content-Type: application/json`

---

## 7. Domain: Validation, Error Handling, UI, Database, Scheduled Job

### VAL-01 — Validation & Feedback
- [x] VAL-01.01–08 — Validasi frontend+backend, enum status, tanggal, FK, angka >= 0, backend sebagai source of truth, data tidak tersimpan jika gagal validasi, input dipertahankan (`$old`)

### ERR-01 — Error Handling
- [x] ERR-01.01–05 — Redirect login, 403 forbidden, 404 not found, DB exception disembunyikan, stack trace disembunyikan

### UI-01 — Responsive & Usability
- [x] UI-01.01–05 — Responsif 360px + desktop, navigasi/tabel tidak terpotong, label form, focus state, kontras WCAG AA (`docs/quality/wcag-contrast-audit.md`)

### DB-01 — Database Relasional & Transaksi
- [x] DB-01.01–06 — 14 tabel di `database/schema.sql`, PK/FK/CHECK constraint, index relevan, semua query PDO prepared statement, transaksi eksplisit multi-tabel, schema+seed jalan dari kosong

### JOB-01 — Script Terjadwal
- [x] JOB-01.01–04 — `scripts/check-low-stock.php` (CLI PHP standalone, tidak wajib auto-schedule)

### Bukti / Evidence
- [ ] **B-65** Demo submit SO tanpa customer → validasi error
- [ ] **B-66** Demo submit SO tanpa item → validasi error
- [ ] **B-67** Demo submit SO qty <= 0 → validasi error
- [ ] **B-68** Demo goods receipt melebihi qty dipesan → rollback + error
- [ ] **B-69** Demo input dipertahankan setelah validasi gagal
- [ ] **B-70** Demo akses `/dashboard` tanpa session → redirect `/login`
- [ ] **B-71** Demo akses `/users` sebagai Sales → 403
- [ ] **B-72** Demo akses ID SO tidak ada → 404
- [ ] **B-73** Demo internal error → stack trace TIDAK muncul
- [ ] **B-74** Screenshot login page desktop + mobile 360px
- [ ] **B-75** Screenshot dashboard desktop + mobile
- [ ] **B-76** Screenshot daftar produk desktop + mobile (tabel tidak terpotong)
- [ ] **B-77** Screenshot form create/edit — label & focus state terlihat
- [ ] **B-78** ERD 14 tabel dengan relasi FK
- [ ] **B-79** Schema SQL CHECK constraint qty >= 0
- [ ] **B-80** Penjelasan transaksi multi-tabel Goods Issue
- [ ] **B-81** Index `(product_id, warehouse_id)` di `product_stocks`
- [ ] **B-82** Semua query prepared statement (tidak ada concatenation)
- [ ] **B-83** Demo `docker compose exec app php scripts/check-low-stock.php` → output list produk
- [ ] **B-84** Output menunjukkan produk `stock < reorder_point`
- [ ] **B-85** Script berjalan tanpa error (exit code 0)

---

## 8. Arsitektur & Kualitas Desain

### ARCH-01 — Layered Architecture & Dependency Inversion
- [x] ARCH-01.01–06 — 3 layer Controller→Service→Repository, dependency searah, tiap repository punya interface + MySQL + Fake, constructor injection, tidak ada `new PDO()` di Service, unit test jalan tanpa DB

### ARCH-02 — Transaksi & Concurrency-Safe Stock Operation
- [x] ARCH-02.01–05 — Transaksi atomik ProductStock+StockLedger, pessimistic locking `SELECT ... FOR UPDATE`, tidak ada update saling menimpa, penjelasan skenario konkuren tersedia, ada test terkontrol (`ARCH02ConcurrencyTest`, `GoodsIssueConcurrencyTest`)

### DESIGN-01 — Class Diagram (Initial & As-Built)
- [x] DESIGN-01.01–04 — `docs/planning/class-diagram-initial.md` + `docs/architecture/class-diagram-asbuilt.md`, penjelasan perubahan initial→as-built, format Mermaid

### DESIGN-02 — Architecture Decision Record
- [x] DESIGN-02.01–03 — **6 ADR** ada (`adr-001` s/d `adr-006`), format Context/Decision/Consequences

### DESIGN-03 — Refactoring Log, SRP Audit, Tech-Debt Register
- [x] DESIGN-03.01–04 — `docs/quality/refactor-log.md`, `docs/quality/srp-audit.md`, `docs/quality/tech-debt.md` semua ada
- [ ] **DESIGN-03.05** — Verifikasi minimal 1 commit git dengan prefix `refactor:` ada di history (`git log --grep="^refactor:"`)

### DESIGN-04 — Critique Exercise
- [x] DESIGN-04.01–03 — `docs/quality/critique.md` ada dengan analisis tertulis

### Bukti / Evidence (Arsitektur)
- [ ] **B-86** Kode menunjukkan 14 interface repository (daftar lengkap)
- [ ] **B-87** Kode menunjukkan 14 implementasi MySQL + 14 Fake
- [ ] **B-88** Demo unit test Service jalan dengan Fake repository (tanpa DB)
- [ ] **B-89** `grep -r "new PDO()" app/Service/` → kosong
- [ ] **B-90** Penjelasan lisan: kenapa dependency Controller→Repository (bukan sebaliknya)
- [ ] **B-91** Penjelasan mekanisme kenapa `SELECT FOR UPDATE` dipilih
- [ ] **B-92** Tunjukkan `lockForUpdate()` di `ProductStockMySQLRepository`
- [ ] **B-93** Tunjukkan `beginTransaction()`/`commit()`/`rollBack()` di `GoodsIssueService`
- [ ] **B-94** Jalankan `ARCH02ConcurrencyTest` → request kedua ditolak
- [ ] **B-95** Penjelasan skenario konkuren yang dicegah (oversell)
- [ ] **B-96** Penjelasan kenapa bukan optimistic locking
- [ ] **B-97** `class-diagram-initial.md` ada & menunjukkan layer architecture
- [ ] **B-98** `class-diagram-asbuilt.md` ada & menunjukkan dependency ke interface
- [ ] **B-99** 2–3 kalimat perubahan initial→as-built tertulis
- [ ] **B-100** Minimal 2 ADR ada
- [ ] **B-101** Tiap ADR format Context/Decision/Consequences
- [ ] **B-102** ADR mencakup keputusan nyata (Repository pattern, Concurrency, dst.)
- [ ] **B-103** `refactor-log.md` minimal 3 entry
- [ ] **B-104** Tiap entry: smell, teknik, before/after
- [ ] **B-105** `srp-audit.md` ada — analisis pelanggaran SRP
- [ ] **B-106** `tech-debt.md` ada — register jujur
- [ ] **B-107** Commit `refactor:` ada di history
- [ ] **B-108** `critique.md` ada dengan analisis tertulis
- [ ] **B-109** Smell + SOLID violation teridentifikasi benar
- [ ] **B-110** Refactoring suggestion tertulis

---

## 9. Testing sebagai Bagian dari Desain

### TEST-01 — Unit Test Terisolasi
- [x] TEST-01.01–07 — Minimal 6 test case di ≥3 area logic, pakai Fake repository (bukan mock, bukan koneksi DB nyata). **Status verifikasi (2026-09-16):** `docs/testing/unit-test-results.md` mencatat **63 test, 157 assertions, 100% pass**

### TEST-02 — Integration Test
- [x] TEST-02.01–04 — Minimal 3 integration test menyentuh MySQL nyata di Docker, meliputi goods receipt end-to-end, ARCH-02 concurrency, dan SOD enforcement. **Status verifikasi (2026-09-16):** `docs/testing/integration-test-results.md` mencatat **17 test, 136 assertions, pass** (lock-wait-timeout warning di `ARCH02ConcurrencyTest` memang expected/by-design)

### TEST-03 — Static Analysis & FIRST Principles
- [x] TEST-03.01–04 — PHPStan level 5, FIRST principles (Fast/Independent/Repeatable/Self-validating/Timely). **Status verifikasi (2026-09-16):** `docs/testing/phpstan-results.md` mencatat **[OK] No errors**, 101 file dianalisis

### Bukti / Evidence
- [ ] **B-111** `tests/Unit/` minimal 6 test case di 3 area logic berbeda — **jalankan ulang sebelum submission** karena kode terus berubah sejak 16 Sep
- [ ] **B-112** Semua unit test jalan tanpa koneksi DB (`--testsuite Unit`)
- [ ] **B-113** `grep -r "new PDO()" tests/Unit/` → kosong
- [ ] **B-114** Hasil test tersimpan ulang di `docs/testing/unit-test-results.md` (re-run terbaru)
- [ ] **B-115** `tests/Integration/` minimal 3 test menyentuh MySQL nyata
- [ ] **B-116** Integration test terpisah dari unit test (`--testsuite Integration`)
- [ ] **B-117** Goods issue concurrency test membuktikan request kedua ditolak
- [ ] **B-118** Hasil test tersimpan ulang di `docs/testing/integration-test-results.md`
- [ ] **B-119** `docs/testing/phpstan-results.md` menunjukkan level 5, 0 critical error — **jalankan ulang**
- [ ] **B-120** Warning tersisa (jika ada) dijelaskan singkat
- [ ] **B-121** Tidak ada `sleep()`/network call nyata/urutan eksekusi di test suite
- [ ] **B-122** `vendor/bin/phpstan analyse` bisa dijalankan → `[OK] No errors`

---

## 10. Ketentuan Teknis Struktural (Folder Responsibility)

- [x] `public/` — entry point + static asset
- [x] `app/Controller/` — 14 controller (HTTP handling, auth guard)
- [x] `app/Service/` — 17 service (business logic, transaksi, kebijakan domain)
- [x] `app/Repository/{Interface,MySQL,Fake}/` — 42 file (14×3, DIP)
- [x] `app/Entity/` — 13 entity/value object
- [x] `views/` — template HTML+PHP
- [x] `config/` — `global.php` + `routes.php`
- [x] `database/` — `schema.sql` + `seed.sql`
- [x] `tests/Unit/` + `tests/Integration/` — terpisah sesuai jenis
- [x] `docs/planning/`, `docs/architecture/`, `docs/quality/`, `docs/testing/` — semua ada
- [ ] **Peringatan struktur** — pastikan tidak ada folder yang digabung tanpa alasan (mis. Controller+Model dalam satu file) — audit cepat sebelum submission

---

## 11. Security Minimum (SEC-01 s/d SEC-06)

- [x] SEC-01 — Prepared statement semua query; validasi backend; CSRF token (`requireCsrf`/`hash_equals`); output di-escape `htmlspecialchars()`
- [x] SEC-02 — `password_hash()`/`password_verify()` bcrypt; session regenerate setelah login; secret di `.env` (bukan hardcode); user nonaktif → session invalidated; halaman terlindungi redirect login
- [x] SEC-03 — Authorization server-side (`requirePermission`); SOD-01 di service layer (`SalesOrderPolicy`); Sales tidak bisa approve SO sendiri
- [x] SEC-04 — Stack trace & DB error disembunyikan; pesan login generik; HTTP status code tepat
- [x] SEC-05 — Validasi MIME type upload; ukuran file dibatasi; nama file acak; ekstensi dipaksa sesuai MIME (`.webp`)
- [x] SEC-06 — Stok tidak pernah negatif (CHECK constraint); Stock Ledger insert-only (tidak ada UPDATE/DELETE); soft delete Produk/Supplier/Customer; ARCH-02 row locking

### Bukti / Evidence
- [ ] **B-123** Tidak ada query concatenation dengan user input (audit ulang seluruh repository)
- [ ] **B-124** CSRF token ada di semua form state-changing
- [ ] **B-125** `password_hash()`/`password_verify()` — bukan MD5/SHA1
- [ ] **B-126** Session regenerate setelah login berhasil
- [ ] **B-127** Stack trace tidak muncul di halaman error production
- [ ] **B-128** SOD-01 ditegakkan di service layer (bukan hanya UI)
- [ ] **B-129** File upload: MIME divalidasi, nama file acak
- [ ] **B-130** Credential di `.env`, tidak hardcode di repo
- [ ] **B-131** Output HTML di-escape `htmlspecialchars()` di semua view
- [ ] **B-132** Race condition prevention (ARCH-02) terdokumentasi & teruji

---

## 12. Docker & Testing Environment (DOCK-01) — KT-03

- [x] DOCK-01.01 — Service `app` + `db` (plus `redis`, `memcached`, `cron` di project ini)
- [x] DOCK-01.02 — Build dari clean environment via Dockerfile
- [x] DOCK-01.03 — Konfigurasi via environment variable
- [x] DOCK-01.04 — `.env` tersedia
- [x] DOCK-01.05 — Schema & seed auto-load via `docker-entrypoint-initdb.d/`
- [x] DOCK-01.06 — Tidak ada absolute path hardcode

### Uji Sebelum Submission (WAJIB dijalankan ulang persis sebelum deadline)
- [ ] `docker compose down -v`
- [ ] `docker compose up --build -d` → semua container hijau
- [ ] `docker compose exec app ./vendor/bin/phpunit --testsuite Unit`
- [ ] `docker compose exec app ./vendor/bin/phpunit --testsuite Integration`
- [ ] Buka `http://localhost:8090` → login 3 role
- [ ] Demo alur penuh: Login → Create PO → Submit → Receive → Create SO → Submit → Approve → Issue

### Bukti / Evidence
- [ ] **B-133** `docker compose up --build` berhasil tanpa error
- [ ] **B-134** Database auto-initialized (schema + seed)
- [ ] **B-135** Login berfungsi (`admin@example.com` / `admin123` — **sesuaikan dengan kredensial seed terbaru**)
- [ ] **B-136** Unit tests pass
- [ ] **B-137** Integration tests pass
- [ ] **B-138** PHPStan clean
- [ ] **B-139** Alur PO: create → submit → receive → ledger updated
- [ ] **B-140** Alur SO: create → submit → approve → issue → fulfilled
- [ ] **B-141** SOD enforcement: Sales coba approve SO sendiri → ditolak

---

## 14. Proses Kerja: Git & Penggunaan AI

### PROC-01 — Git & Integritas Proses
- [x] PROC-01.01 — Repository individual
- [ ] **PROC-01.02** — Verifikasi commit message bermakna (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`) — bukan "update"/"fix bug" generik, audit `git log` sebelum submission
- [ ] **PROC-01.03** — Minimal 1 commit `refactor:` ada di history — cek `git log --grep="^refactor:"`
- [x] PROC-01.04 — `.env` ada di `.gitignore`, tidak ada credential/PII di repo
- [x] PROC-01.05 — `composer.json` mencantumkan semua dependency
- [ ] **PROC-01.06** — Urutan commit mengikuti vertical slice (Auth → Master Data → PO → SO → Dashboard → Polish) — audit `git log` sebelum submission

### AI-01 — Penggunaan AI
- [x] AI-01.01 — `ai-usage-log.md` ada dan mencatat penggunaan AI tool *(duplikat `docs/process/ai-usage-log.md` — salinan basi yang berhenti di 2 Sep — sudah dihapus 21 Sep 2026; root `ai-usage-log.md` sekarang satu-satunya acuan, lengkap s/d 17 Sep)*
- [ ] **AI-01.02–04** — Verifikasi setiap entry log memuat: tool, purpose, prompt (sanitized), output used/rejected, cara verifikasi, titik integrasi
- [ ] **AI-01.05** — Latihan: pastikan bisa menjelaskan SETIAP keputusan arsitektur saat defense **tanpa bantuan AI**
- [ ] **AI-01.06** — Audit ulang `ai-usage-log.md` — pastikan tidak ada credential/API key/PII asli yang ter-log

### Bukti / Evidence
- [ ] **B-142** Repository punya commit history bermakna
- [ ] **B-143** Minimal 1 commit `refactor:` ada
- [ ] **B-144** `.env` tidak ada di repo (ada di `.gitignore`)
- [ ] **B-145** Semua dependency tercantum di `composer.json`
- [ ] **B-146** Commit mencerminkan urutan vertical slice
- [ ] **B-147** `ai-usage-log.md` mencatat setiap penggunaan AI
- [ ] **B-148** Tiap entry lengkap (tool/purpose/prompt/output/verification)
- [ ] **B-149** Prompt tidak mengandung credential aktif/PII
- [ ] **B-150** Bisa jelaskan semua keputusan arsitektur tanpa AI saat defense

---

## 15. Critical Failure — WAJIB Dihindari (Jika Kena, Project Tidak Dinilai)

- [ ] **CF-01** Docker Compose gagal jalan dari kondisi bersih
- [ ] **CF-02** Unit atau integration test error/fail
- [ ] **CF-03** Segregation of Duties dilanggar (Sales bisa approve SO sendiri) — **paling kritis, sudah ada guard di kode (`SalesOrderPolicy`), tetap wajib didemokan ulang**
- [ ] **CF-04** Race condition tidak ditangani (2 goods issue bersamaan → stok negatif)
- [ ] **CF-05** SQL injection vulnerability (query concatenation)
- [ ] **CF-06** XSS vulnerability (output tidak di-escape)
- [ ] **CF-07** Password tidak di-hash (plain text/MD5/SHA1)
- [ ] **CF-08** Data loss risk (Produk/Supplier/Customer di-hard-delete)
- [ ] **CF-09** Teknologi terlarang terdeteksi (React/Vue/Angular/Laravel/CodeIgniter/Symfony/ORM)
- [ ] **CF-10** Tidak ada class diagram atau ADR (KT-05 evidence tidak ada)

> Semua kondisi di atas **sudah punya mitigasi di kode** berdasarkan pembacaan source saat file ini
> dibuat — tapi checklist ini sengaja dibiarkan `[ ]` karena **CF hanya boleh dianggap aman setelah
> didemokan ulang langsung**, bukan hanya karena kode "terlihat benar".

---

## 16. Deliverable Checklist (Final)

- [x] Source code lengkap sesuai teknologi yang ditentukan
- [x] Repository Git
- [ ] Evidence lengkap (dokumentasi, screenshot, test report — **banyak yang masih `[ ]` di atas, lengkapi dulu**)
- [x] Docker Compose setup
- [x] Unit tests (PHPUnit)
- [x] Integration tests (PHPUnit)
- [x] Static analysis report (PHPStan)
- [x] Class Diagram (KT-05)
- [x] Architecture Decision Record / ADR (KT-05)
- [x] Refactoring Log (KT-05)

---

## 17. Self-Checklist Sebelum Submission (Gate Terakhir)

```
[ ] docker compose down -v
[ ] docker compose up --build -d
[ ] docker compose exec app ./vendor/bin/phpunit --testsuite Unit          → 0 fail
[ ] docker compose exec app ./vendor/bin/phpunit --testsuite Integration   → 0 fail
[ ] docker compose exec app ./vendor/bin/phpstan analyse                   → [OK] No errors
[ ] Login admin (role Admin)     → dashboard tampil benar
[ ] Login sales (role Sales)     → dashboard tampil benar
[ ] Login warehouse (role Warehouse Staff) → dashboard tampil benar
[ ] grep -r "new PDO()" app/Service/                         → kosong
[ ] grep -r "password_hash\|PASSWORD_BCRYPT" app/Service/    → ada (bukan MD5/SHA1)
[ ] grep -r "htmlspecialchars" views/                         → ada di semua view yang echo user input
[ ] .env ada di .gitignore, TIDAK ter-commit
[ ] docs/architecture/adr-*.md ada (sudah 6 ADR — lebih dari minimal 2)
[ ] docs/architecture/class-diagram-*.md ada
[ ] docs/quality/refactor-log.md ada
```

> **Jika ada item yang GAGAL, perbaiki sebelum submission — jangan submit dengan checklist ini masih merah.**

---

## Ringkasan Status Cepat (per 21 Sep 2026, berdasarkan pembacaan kode)

| Kategori | Status Implementasi Kode | Status Evidence/Demo |
|---|---|---|
| KT-01 s/d KT-05 (non-negotiable) | ✅ Terverifikasi ada | ⏳ Wajib didemokan ulang |
| AUTH-01, PRD-01, WH-01, PO-01, SO-01 | ✅ Terverifikasi ada di controller/service | ⏳ B-01–B-40 belum dicentang |
| VIEW/FIND/DASH/REPORT/API | ✅ Terverifikasi ada | ⏳ B-41–B-64 belum dicentang |
| VAL/ERR/UI/DB/JOB | ✅ Terverifikasi ada | ⏳ B-65–B-85 belum dicentang |
| ARCH-01, ARCH-02, DESIGN-01–04 | ✅ Terverifikasi ada (6 ADR, class diagram, refactor log, SRP audit, tech-debt, critique) | ⏳ B-86–B-110 belum dicentang |
| TEST-01/02/03 | ✅ Hasil run 22 Sep 2026 (real, terverifikasi): **77 unit test pass**, **17 integration test pass** | ⚠️ **PHPStan: 13 error nyata ditemukan & diperbaiki, belum diverifikasi ulang** — jalankan `phpstan analyse` sekali lagi |
| Struktur folder (§4.1) | ✅ Semua folder wajib ada | — |
| Security SEC-01–06 | ✅ Terverifikasi ada di kode | ⏳ B-123–B-132 belum dicentang |
| Docker (DOCK-01) | ✅ `compose.yaml` lengkap (app/db/redis/memcached/cron) | ⏳ Wajib `up --build` ulang dari clean state |
| Git & AI Process | ⚠️ **Perlu audit manual**: commit message convention, ada/tidaknya commit `refactor:`. Duplikasi `ai-usage-log.md` ✅ **sudah dibereskan** (21 Sep 2026) | ⏳ |
| Critical Failure (CF-01–10) | ✅ Mitigasi ada di kode untuk semua 10 poin | ⏳ **Wajib dibuktikan ulang lewat demo langsung, terutama CF-03 & CF-04** |

**Catatan risiko yang perlu ditindaklanjuti:**

1. ✅ **SELESAI (21 Sep 2026)** — `ai-usage-log.md` yang dobel sudah dibereskan. `docs/process/ai-usage-log.md` (salinan basi, hanya sampai entry 2 Sep 2026, 18.435 bytes) **sudah dihapus**. Root `ai-usage-log.md` (30.255 bytes, lengkap s/d entry 17 Sep 2026 — termasuk fitur notifikasi low-stock) sekarang satu-satunya file acuan, tidak ada lagi risiko assessor membaca versi yang salah/basi.
2. ⚠️ **BELUM BISA DISELESAIKAN OTOMATIS — perlu dijalankan manual oleh Anda.** Sesi ini terhubung ke komputer Anda lewat sebuah shell sandbox terpisah yang **tidak punya Docker maupun PHP CLI ter-install**, dan tidak punya akses root untuk meng-install-nya (`apt-get`/`sudo` ditolak: *Permission denied* / *no new privileges*). Karena test integration butuh MySQL nyata di container Docker, saya tidak bisa menjalankan ulang test suite dari sini. **Jalankan sendiri di terminal Laragon/PowerShell Anda:**
   ```bash
   docker compose down -v
   docker compose up --build -d
   docker compose exec app ./vendor/bin/phpunit --testsuite Unit --testdox
   docker compose exec app ./vendor/bin/phpunit --testsuite Integration --testdox
   docker compose exec app ./vendor/bin/phpstan analyse
   ```
   Setelah itu, **tempelkan/kirimkan output-nya kembali ke saya** (atau minta saya baca ulang lewat `device_bash` bila hasilnya sudah tersimpan ke file) — saya akan langsung memperbarui `docs/testing/unit-test-results.md`, `docs/testing/integration-test-results.md`, dan `docs/testing/phpstan-results.md` dengan angka & tanggal terbaru, plus meng-update checklist ini (item **B-111** s/d **B-122**, **B-136–B-138**).
3. Aturan cancel Purchase Order di kode (`purchase_orders.cancel`, hanya Admin, hanya sebelum receipt pertama) dan siapa yang boleh **create/submit** PO (kode saat ini mengizinkan Warehouse Staff, bukan hanya Admin) — cocokkan sekali lagi dengan versi final requirement dari trainer sebelum demo, karena ada dua bagian di `PROJECT_REFERENCE.md` yang redaksinya sedikit berbeda soal ini (§1.1 vs §2.3 hak akses).
4. ✅ **Unit test dijalankan ulang (22 Sep 2026) — PASS.** `docker compose exec app ./vendor/bin/phpunit --testsuite Unit --testdox` → **77 tests, 203 assertions, semua hijau** (PHP 8.3.20, naik dari 63 test di snapshot lama). `docs/testing/unit-test-results.md` sudah diperbarui dengan hasil ini.
5. ✅ **SELESAI (22 Sep 2026) — Integration test dijalankan ulang setelah fix, semua hijau.** Dari 17 test, 3 gagal — semuanya di `SalesOrderApprovalPolicyTest` dengan pesan *"SO detail page should embed a CSRF token" / Failed asserting that null is not null*. Setelah ditelusuri, penyebabnya BUKAN bug di aplikasi, tapi dua hal:
   - `compose.yaml` tidak pernah meneruskan `ID_OBFUSCATION_KEY` dari `.env` ke container `app` (Compose tidak otomatis inject semua variabel `.env` — harus didaftarkan eksplisit di `environment:`). Akibatnya `config/global.php` diam-diam jatuh ke kunci kosong `''`, jadi `IdObfuscator` (yang meng-obfuscate ID di URL, mis. `/sales-orders/{id}`) berjalan tanpa kunci sama sekali.
   - Test lama mengekstrak ID dari header redirect pakai regex `\d+` (asumsi ID polos berupa angka desimal). Padahal ID di URL sekarang berupa token heksadesimal hasil `IdObfuscator::encode()` (`bin2hex(...)`) — regex `\d+` cuma menangkap potongan digit yang kebetulan ada di awal token hex tersebut, menghasilkan ID palsu yang salah, sehingga request berikutnya di test (submit/approve) selalu kena 404 (halaman tanpa form/CSRF token).

   **Perbaikan yang sudah diterapkan:**
   - `compose.yaml`: `ID_OBFUSCATION_KEY: ${ID_OBFUSCATION_KEY:-change_this_to_a_long_random_string}` ditambahkan ke `environment:` service `app`.
   - `tests/Integration/SalesOrderApprovalPolicyTest.php`: regex ekstraksi ID diganti jadi `[0-9a-f]+` (menangkap seluruh token hex), token dipakai langsung untuk membangun URL (persis seperti cara aplikasi bekerja), dan didekode balik ke ID integer asli (pakai `App\Core\IdObfuscator` + `ID_OBFUSCATION_KEY` yang sama) hanya saat perlu query status SO langsung ke DB.

   **Sudah diverifikasi dengan run nyata (22 Sep 2026, setelah `docker compose down -v && up --build -d`):** `docker compose exec app ./vendor/bin/phpunit --testsuite Integration --testdox` → **17/17 test pass, 143 assertions**, termasuk ketiga test `SalesOrderApprovalPolicyTest` yang sebelumnya gagal. `docs/testing/integration-test-results.md` sudah diperbarui dengan hasil run ini (warning STALE dihapus, diganti hasil terverifikasi).
6. ⚠️ **PHPStan: config hilang total (sudah dibuat ulang) + 13 error nyata ditemukan (sudah diperbaiki, BELUM diverifikasi ulang).** File `phpstan.neon` yang dirujuk di `docs/testing/phpstan-results.md` dan `docs/quality/refactor-log.md` ternyata tidak ada sama sekali di root project — makanya perintah gagal dengan *"At least one path must be specified to analyse."* Setelah dibuat ulang (`level: 5`, `paths: app/, public/, scripts/`, `excludePaths: app/Repository/Fake/`) dan dijalankan, hasilnya **BUKAN "No errors" seperti klaim di snapshot 16 Sep** — ditemukan **13 error nyata**:
   - **12x** "`Property ...::$db is never read, only written`" di hampir semua `*MySQLRepository` (Category, Customer, EventLog, Product, ProductStock, PurchaseOrderItem, PurchaseOrder, SalesOrder, StockLedger, Supplier, User, Warehouse) — properti `$db` di-assign di constructor tapi tidak pernah dipakai (semua query lewat `$queryBuilder`, yang sudah punya `$db` sendiri secara internal). **Diperbaiki:** properti `private $db;` dan assignment-nya dihapus dari ke-12 class tersebut; parameter constructor `Database $db` tetap dipertahankan (jadi semua pemanggilan dari `app/Core/Container.php` tidak perlu diubah).
   - **1x bug nyata**: `scripts/check-low-stock.php` baris 59 memanggil `new NotificationMySQLRepository($database)` dengan 1 argumen, padahal constructor-nya butuh 2 (`Database $db, QueryBuilder $queryBuilder`). Ini akan menyebabkan fatal `ArgumentCountError` pertama kali scheduled job (JOB-01) benar-benar menemukan produk low-stock dan mencoba membuat notifikasi. **Diperbaiki:** call diubah jadi mengoper `QueryBuilder($database)` juga.

   Semua fix sudah diverifikasi manual (grep memastikan tidak ada `$this->db` yang tersisa, cek jumlah `{`/`}` seimbang di tiap file) karena environment ini tidak bisa menjalankan `php`/`docker` langsung. `docs/testing/phpstan-results.md` sudah diperbarui dengan hasil run nyata + root cause + fix di atas. **Belum diverifikasi ulang** — jalankan `docker compose exec app ./vendor/bin/phpstan analyse` sekali lagi dan kirim hasilnya untuk konfirmasi `[OK] No errors`.

# Product Requirement Document (PRD)
# Inventory & Order Management System

**Version:** 1.0 (Draft)  
**Stage:** 2 / 9 (PRD)  
**Author:** Peserta Program Pengembangan Kompetensi Programmer  
**Date:** 2026-09-01  
**Status:** Untuk Review  
**Depends on:** `docs/planning/product-vision.md` v1.4+

---

## 0. Overview & Reading Guide

### 0.1 Tujuan Dokumen Ini
Menerjemahkan **Vision** (§5 Scope + §4 Value Proposition + §3 Personas) menjadi **22 requirement** yang dapat dibangun, diuji, dan dinilai.

Setiap requirement punya:
- **User Story** (siapa, apa, kenapa)
- **Functional Requirements** (apa yang harus dilakukan)
- **Business Rules Applied** (rujuk katalog §1)
- **Data Touched** (entity yang disentuh)
- **Authorization** (matriks per role)
- **Acceptance Criteria** (Given / When / Then)
- **Test Scenarios** (happy + edge)
- **Out of Scope** (pagar scope creep)

### 0.2 Sumber Requirement
| Sumber | Jumlah | Kategori |
|--------|--------|----------|
| Project Brief §2 (Requirement Wajib) | 16 | AUTH-01, AUTH-02, USR-01, PRD-01, WH-01, PO-01, SO-01, VIEW-01, FIND-01, DASH-01, REPORT-01, API-01, VAL-01, ERR-01, UI-01, DB-01, JOB-01 |
| Project Brief §3 (Arsitektur & Kualitas Desain) | 2 | ARCH-01, ARCH-02 |
| Vision Value Prop #5-#6 + Pillar Inclusivity | 4 | I18N-01, THEME-01, ASSET-01, IMAGE-01 |
| Project Brief §4.4 (Bonus) | 1 | I18N-02 (auto-translate LibreTranslate) |
| **TOTAL** | **23** | |

> **I18N-01, THEME-01, I18N-02 — OUT OF SCOPE (resolved 2026-09-08, CF-02).** `i18next` (I18N-01's
> library) is outside the brief's allowed frontend list (Fetch API + icon library only); THEME-01
> was dropped in the same decision. See `master-project-specification.md` §46 and
> `ux-ui-spec.md` v1.3. The shipped UI is English-only, light-theme-only. Kept below for traceability
> of the original requirement count, not as live scope.

### 0.3 Priority Legend
| Priority | Arti | Contoh |
|----------|------|--------|
| **P0** | Critical / Critical failure risk | ARCH-02, AUTH-01, PO-01, SO-01 |
| **P1** | Mandatory functional Brief | USR-01, PRD-01, WH-01, VIEW-01, DASH-01 |
| **P2** | Mandatory non-functional / Architecture | ARCH-01, DB-01, VAL-01 |
| **P3** | UX enhancement (Vision addition) — ~~I18N-01, THEME-01~~ OUT OF SCOPE (2026-09-08) | ASSET-01 |
| **P4** | Advanced UX enhancement | IMAGE-01 |
| **P7** | Bonus — ~~I18N-02~~ OUT OF SCOPE (2026-09-08, depended on I18N-01) | — |

### 0.4 Rekomendasi Membaca
- **Assessor:** Baca §0 Overview → §1 Business Rules → §12 Dependency Graph → skim per domain.
- **Peserta saat coding:** Baca §12 Sequencing dulu → kerjakan per requirement, buka detailnya saat implementasi.

---

## 1. Business Rules Catalog

Aturan bisnis eksplisit dengan **kode identifikasi**. Setiap PRD entry di bawah akan **merujuk** ke BR-xxx yang relevan; setiap test wajib uji minimal 1 BR.

| Kode | Business Rule | Enforcement Point Utama | Sumber |
|------|---------------|--------------------------|--------|
| **BR-001** | Sales user TIDAK BOLEH menyetujui SO miliknya sendiri (segregation of duties) | Server-side authorization di `SalesOrderService::approve()` | Brief §1.2, §2.4 SO-01 |
| **BR-002** | Kuantitas stok tidak boleh negatif (`quantity >= 0`) | DB constraint pada `product_stock.quantity` + Service validation | Brief §1.3 |
| **BR-003** | SKU produk harus unik | DB unique index pada `products.sku` + Repository | Brief §1.3, §2.2 PRD-01 |
| **BR-004** | Email user harus unik | DB unique index pada `users.email` + Repository | Brief §2.1 USR-01 |
| **BR-005** | Produk yang sudah dipakai order tidak boleh **dihapus** — hanya dinonaktifkan (soft-deactivate) | Service `ProductService::deactivate()` — cek referensi PO/SO Item | Brief §1.3, §2.2 PRD-01 |
| **BR-006** | Session ID diperbarui (`session_regenerate_id()`) setelah login sukses | `AuthService::login()` | Brief §4.2 Security |
| **BR-007** | Password disimpan sebagai hash (`password_hash()`) + verify dengan `password_verify()` | `AuthService` | Brief §2.1 AUTH-01 |
| **BR-008** | Goods Receipt (satu operasi) menulis **StockLedger** DAN update **ProductStock** dalam **satu transaksi DB** (all-or-nothing) | Service `GoodsReceiptService::process()` + PDO `beginTransaction()` | Brief §2.3 PO-01, §3.1 ARCH-02 |
| **BR-009** | Goods Issue (satu operasi) menulis **StockLedger** DAN update **ProductStock** dalam **satu transaksi DB** | Service `GoodsIssueService::process()` + PDO transaksi | Brief §2.4 SO-01, §3.1 ARCH-02 |
| **BR-010** | Dua Goods Issue simultan pada produk+gudang yang sama TIDAK BOLEH oversell — salah satu ditolak dengan aman | ARCH-02 concurrency mechanism (row lock `SELECT ... FOR UPDATE` atau optimistic version) | Brief §3.1 ARCH-02 |
| **BR-011** | SO status transisi valid: `Draft → PendingApproval → Approved → Fulfilled`; atau `Cancelled` dari state manapun sebelum `Fulfilled` | State machine di `SalesOrderService` | Brief §1.3, §2.4 SO-01 |
| **BR-012** | PO status transisi valid: `Draft → Ordered → PartiallyReceived / Received`; atau `Cancelled` dari state applicable sebelum `Received` | State machine di `PurchaseOrderService` | Brief §1.3, §2.3 PO-01 |
| **BR-013** | Goods Issue hanya dapat diproses untuk SO berstatus `Approved` | `GoodsIssueService::process()` guard | Brief §2.4 SO-01 |
| **BR-014** | Goods Receipt hanya dapat diproses untuk PO berstatus `Ordered` atau `PartiallyReceived` | `GoodsReceiptService::process()` guard | Brief §2.3 PO-01 |
| **BR-015** | Setiap pergerakan stok (Receipt / Issue / Adjustment) WAJIB menciptakan **satu baris StockLedger** dengan referensi ke PO/SO id + user id + timestamp | Service layer (di setiap stock-touching operation) | Brief §1.3, §2.3, §2.4 |
| **BR-016** | Semua SQL query yang menerima input user WAJIB menggunakan **PDO prepared statement** — tidak boleh string concatenation | Repository layer + PHP code review | Brief §2.7 DB-01 |
| **BR-017** | Authorization diperiksa di **server** untuk setiap request yang butuh permission (bukan hanya di UI) | Middleware / Guard di setiap Controller action | Brief §1.2, §4.2 Security |
| **BR-018** | Sales user hanya boleh melihat **SO miliknya sendiri** (bukan SO sales lain); Warehouse Staff melihat data gudang terkait; Admin melihat semua | Repository filter by `created_by` / scope + Service check | Brief §1.2, §2.4, §2.5 |
| **BR-019** | Master data yang dinonaktifkan (Product/Supplier/Customer) tidak boleh dipilih pada transaksi baru, tetapi tetap tampil pada transaksi historis | Repository & Service filter di endpoint create/edit transaksi | Brief §1.3 |
| **BR-020** | Semua label UI, pesan validasi, header laporan, empty state, dan error message wajib menggunakan i18n key (tidak boleh string hardcoded) | i18next + `translation.json` en/id lengkap | Vision §4.2 #5, §7 #9 |
| **BR-021** | Kontras rasio setiap kombinasi text/background di kedua tema (light + dark) wajib memenuhi WCAG AA (4.5:1 body, 3:1 large text) | CSS token dark/light + contrast validator saat dev | Vision §6.2 E10 |
| **BR-022** | Product image yang di-upload wajib divalidasi MIME dengan `finfo_file()`, di-resize (max 1200 px), dikonversi ke WebP, disimpan dengan nama acak (hash) | `ImageUploadService::process()` | Vision §5.1 IMAGE-01, Brief §2.2 PRD-01 |

Detail lengkap tiap BR akan diperjelas saat implementasi. Katalog ini akan di-update kalau ada BR baru muncul selama Stage 3-6.

---

## 2. High-Level Data Model

Referensi Project Brief §1.3. Entity minimum:

```
┌──────────┐          ┌─────────┐         ┌───────────┐
│  users   │          │  roles  │         │warehouses │
└────┬─────┘          └─────────┘         └─────┬─────┘
     │                                          │
     │ created_by                               │
     ├────────────────────────────┐             │
     ↓                            ↓             │
┌──────────┐              ┌──────────┐          │
│ sales_   │              │purchase_ │          │
│ orders   │              │ orders   │          │
└────┬─────┘              └────┬─────┘          │
     │ (items)                 │ (items)        │
     ↓                          ↓               │
┌──────────┐              ┌──────────┐          │
│ so_items │              │ po_items │          │
└────┬─────┘              └────┬─────┘          │
     │                         │                │
     │ product_id              │                │
     ↓                          ↓               │
                ┌───────────┐                   │
                │ products  ├──── categories    │
                └─────┬─────┘                   │
                      │                         │
                      ↓                         ↓
                ┌────────────────────────────────┐
                │        product_stocks          │
                │  (product_id, warehouse_id,    │
                │   quantity, updated_at)        │
                │  UNIQUE(product_id, warehouse) │
                │  CHECK(quantity >= 0)          │
                └────────────┬───────────────────┘
                             ↓
                ┌────────────────────────────────┐
                │        stock_ledger             │
                │  (product_id, warehouse_id,     │
                │   type[Receipt|Issue|Adjust],   │
                │   qty, ref_type, ref_id,        │
                │   done_by_user_id, done_at)     │
                └─────────────────────────────────┘

Master lain: suppliers, customers
```

**Enum values (Vision §9 Ubiquitous Language):**
- `Role`: `Admin` | `Sales` | `WarehouseStaff`
- `SalesOrder.status`: `Draft` | `PendingApproval` | `Approved` | `Fulfilled` | `Cancelled`
- `PurchaseOrder.status`: `Draft` | `Ordered` | `PartiallyReceived` | `Received` | `Cancelled`
- `StockLedger.type`: `Receipt` | `Issue` | `Adjustment`

Detail schema (kolom, constraint, index) akan dibuat di **Stage 4 (Technical Design)** dan file `database/schema.sql` di **Stage 6 (Implementation)**.

---

## 3. Requirement Template

Format konsisten untuk setiap requirement (§4 s/d §11).

```markdown
### REQ-ID — Title
- **Priority:** Px  |  **Depends on:** REQ-YY, REQ-ZZ

**User Story:**  
As a [Persona], I want [what], so that [why].

**Context / Rationale:**  
1–2 kalimat merujuk Vision.

**Functional Requirements:**
- FR-x.1 …
- FR-x.2 …

**Business Rules Applied:** BR-xxx, BR-yyy

**Data Touched:** entity1, entity2, ledger_x

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | ... |
| Sales | ... |
| WarehouseStaff | ... |

**Acceptance Criteria (Given / When / Then):**
- AC1: Given …, When …, Then …
- AC2: …

**Test Scenarios Minimum:**
- **Happy path:** …
- **Edge:** …
- **Error:** …

**UI/UX Notes:** *(detail di Stage 3)*  
Singkat: layout dominan, empty state, key interactions.

**Out of Scope untuk REQ ini:**
- …

**References:** Brief §X.X, Vision §Y.Y
```

---

## 4. Domain: Authentication & User Management

### AUTH-01 — Login & Session
- **Priority:** P0  |  **Depends on:** DB-01, ARCH-01

**User Story:**  
As a **Rita / Beni / Wawan / Grace**, I want to log in with my email and password, so that I get access to features according to my role.

**Context:**  
Setiap fitur di aplikasi terlindungi di balik authentication. Session sekali dan role menentukan tampilan sesuai persona (Vision §3).

**Functional Requirements:**
- FR-1.1 Form login menerima `email` + `password`.
- FR-1.2 Backend validasi email format & password non-empty.
- FR-1.3 Backend cari user aktif (`is_active = 1`) di DB; jika tidak ada → tolak.
- FR-1.4 Verifikasi password dengan `password_verify()` (**BR-007**).
- FR-1.5 Jika sukses: `session_regenerate_id(true)` (**BR-006**), simpan `user_id` + `role` + `locale` + `theme` di `$_SESSION`, redirect ke dashboard sesuai role.
- FR-1.6 Jika gagal: tampilkan pesan generik ("Email atau password salah") — tanpa membocorkan bagian mana yang salah.
- FR-1.7 User `is_active = 0` tidak dapat login (pesan sama seperti gagal).
- FR-1.8 Halaman terlindungi tidak dapat diakses tanpa session → redirect ke `/login`.

**Business Rules Applied:** BR-006, BR-007, BR-017, BR-020 (label i18n), BR-016 (prepared statement)

**Data Touched:** `users` (read)

**Authorization:**
| Role | Can Do |
|------|--------|
| Anonymous | Akses form login |
| Any authenticated | Redirect ke dashboard sesuai role (tidak bisa lihat form login lagi) |

**Acceptance Criteria:**
- **AC1:** Given user `beni@example.com` aktif dengan password valid, When submit form login, Then session dibuat + redirect ke `/dashboard` + `session_id` berbeda dari sebelum login.
- **AC2:** Given user tidak ada / password salah / user tidak aktif, When submit form, Then pesan generik "Email atau password salah" dan tetap di `/login`.
- **AC3:** Given user tidak authenticated, When akses `/products`, Then redirect ke `/login`.
- **AC4:** Given user login sebagai Sales, When cek header response, Then `Location: /dashboard` dan render halaman Dashboard Sales (bukan Admin).

**Test Scenarios:**
- **Happy:** Login 3 role berbeda → masing-masing dashboard.
- **Edge:** Email dengan spasi trailing → di-trim server-side.
- **Error:** User `is_active=0` login → pesan generik.
- **Security:** Percobaan SQL injection di email → prepared statement menolak.

**UI/UX Notes:**  
Layout: single-column card center. Empty label i18n, focus state jelas, tombol primary "Login" / "Sign In".

**Out of Scope:** Registrasi publik (Brief FAQ #11), Remember-me cookie, 2FA, magic-link.

**References:** Brief §2.1 AUTH-01, §4.2 Security; Vision §3.1–3.4, §5.1

---

### AUTH-02 — Logout
- **Priority:** P0  |  **Depends on:** AUTH-01

**User Story:**  
As any authenticated user, I want to explicitly log out, so that my session cannot be reused after I leave the device.

**Functional Requirements:**
- FR-2.1 Endpoint `POST /logout` yang memanggil `session_unset()` + `session_destroy()` + hapus cookie session di browser.
- FR-2.2 Setelah logout: redirect ke `/login`.
- FR-2.3 Akses ke halaman terlindungi setelah logout wajib redirect ke `/login`.

**Business Rules Applied:** BR-017

**Data Touched:** (tidak ada perubahan DB; hanya `$_SESSION`)

**Authorization:** Any authenticated user.

**Acceptance Criteria:**
- **AC1:** Given user login, When submit `POST /logout`, Then session dihapus + cookie session invalid + redirect ke `/login`.
- **AC2:** Given user baru logout, When akses halaman terlindungi (mis. `/products`), Then redirect ke `/login`.

**Test Scenarios:**
- **Happy:** Login → logout → coba akses `/products` → di-redirect.
- **Edge:** Double-logout tidak error.

**UI/UX Notes:** Tombol "Logout" / "Log Out" di header/menu user.

**Out of Scope:** Logout dari semua device sekaligus, session revocation dari admin panel.

**References:** Brief §2.1 AUTH-02

---

### USR-01 — User Management (3 Role)
- **Priority:** P1  |  **Depends on:** AUTH-01

**User Story:**  
As **Rita (Admin)**, I want to add, edit, activate/deactivate users of role Admin/Sales/WarehouseStaff, so that only authorized people can log in — without going through IT for SQL scripts.

**Context:**  
Salah satu frustration Rita (§3.1): user baru dibuat lewat SQL manual. USR-01 hilangkan itu.

**Functional Requirements:**
- FR-3.1 CRUD user dengan field: `name`, `email` (unik), `password` (hashed), `role` (enum), `is_active` (bool), `created_at`, `updated_at`.
- FR-3.2 Role saat create/edit hanya dari 3 pilihan: `Admin`, `Sales`, `WarehouseStaff`.
- FR-3.3 Email duplikat → tolak dengan pesan validasi.
- FR-3.4 **Deactivate**, bukan delete — user existing yang tidak aktif tidak bisa login tapi data terkait (SO yang dibuatnya) tetap tampil.
- FR-3.5 Password saat create wajib diisi; saat edit optional (kalau kosong → tidak diubah).
- FR-3.6 Password disimpan sebagai hash (**BR-007**).
- FR-3.7 Sales & WarehouseStaff tidak bisa akses halaman/endpoint USR-01 (403 / redirect).

**Business Rules Applied:** BR-004, BR-007, BR-017, BR-020

**Data Touched:** `users`

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | CRUD user, activate/deactivate |
| Sales | ❌ Tidak dapat mengakses menu/endpoint |
| WarehouseStaff | ❌ Tidak dapat mengakses menu/endpoint |

**Acceptance Criteria:**
- **AC1:** Given Admin login, When submit form new user valid, Then user baru muncul di daftar + bisa login.
- **AC2:** Given email `beni@example.com` sudah ada, When Admin coba buat user dengan email sama, Then error validasi "Email sudah digunakan".
- **AC3:** Given user Beni aktif, When Admin klik deactivate → Beni logout otomatis (atau login sesi berikutnya gagal); riwayat SO Beni tetap tampil di daftar SO Sales.
- **AC4:** Given Sales user login mencoba `GET /users`, Then response 403 / redirect ke dashboard.
- **AC5:** Given Admin edit user tanpa isi field password, Then password lama tidak berubah.

**Test Scenarios:**
- **Happy:** Admin buat 1 Sales + 1 Warehouse → keduanya bisa login.
- **Edge:** Email dengan case berbeda (`Beni@example.com` vs `beni@example.com`) → duplikat (normalisasi lowercase).
- **Security:** Sales user hit `POST /users` langsung ke server → ditolak 403.
- **Business rule:** Deactivate user → SO historis miliknya tetap tampil.

**UI/UX Notes:**  
Table: name / email / role / status active / actions. Form modal atau halaman terpisah. Toggle activate/deactivate dengan konfirmasi.

**Out of Scope:** Reset password oleh user sendiri, forgot password flow, role custom.

**References:** Brief §2.1 USR-01, §1.2

---

## 5. Domain: Master Data

### PRD-01 — Product & Category
- **Priority:** P1  |  **Depends on:** AUTH-01, USR-01

**User Story:**  
As **Rita (Admin)**, I want to manage a product catalog with SKU, category, prices, unit, and reorder point, so that Sales can browse them and Warehouse can process them consistently.

**Context:**  
Katalog produk adalah tulang punggung PO/SO. Sales lihat katalog + stok (Vision §3.2 Beni); Warehouse operasikan barang berdasarkan katalog ini.

**Functional Requirements:**
- FR-4.1 CRUD Category (field: `name`, `description`).
- FR-4.2 CRUD Product (field: `sku` (unik), `name`, `category_id` (FK), `unit`, `purchase_price`, `sale_price`, `reorder_point`, `image_path` (opsional), `is_active`, timestamps).
- FR-4.3 **SKU unik** — tolak duplikat (**BR-003**).
- FR-4.4 `reorder_point`, `purchase_price`, `sale_price` harus >= 0.
- FR-4.5 Product image opsional (implementasi detail di **IMAGE-01**).
- FR-4.6 Deactivate product (bukan delete) — produk sudah ada di PO/SO tidak dapat dihapus (**BR-005**, **BR-019**).
- FR-4.7 Product yang `is_active = 0` tidak muncul di form pilihan create SO/PO baru; tetap muncul di detail SO/PO lama.
- FR-4.8 Sales & Warehouse hanya bisa **melihat** katalog (read-only), tidak edit.

**Business Rules Applied:** BR-003, BR-005, BR-019, BR-020, BR-022 (untuk image)

**Data Touched:** `products`, `categories`, `product_stocks` (implicit di WH-01)

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | CRUD product & category, deactivate |
| Sales | Read-only katalog (nama, SKU, harga jual, stok tersedia) |
| WarehouseStaff | Read-only (nama, SKU, stok per gudang, low-stock flag) |

**Acceptance Criteria:**
- **AC1:** Given Admin buat product SKU `PROD-001`, When Admin coba buat lagi dengan SKU sama, Then error "SKU sudah digunakan".
- **AC2:** Given product X dipakai di 1 PO, When Admin coba delete, Then endpoint tolak; hanya deactivate yang diperbolehkan.
- **AC3:** Given product Y `is_active=0`, When Sales buka form buat SO baru, Then product Y tidak muncul di dropdown.
- **AC4:** Given Sales login, When akses `GET /products`, Then hanya lihat daftar (tidak ada tombol edit/delete di UI DAN endpoint edit ditolak server).
- **AC5:** Given Admin isi `reorder_point = -5`, Then form validasi tolak.

**Test Scenarios:**
- **Happy:** Admin buat 30 produk dengan variasi (§7.1 Brief data demo).
- **Edge:** Product dengan nama sama tapi SKU beda → boleh.
- **Edge:** Category dihapus → produk yang refer ke category itu tetap ada (FK RESTRICT / soft-delete category).
- **Security:** Sales user coba POST `/products` → 403.

**UI/UX Notes:**  
Table daftar produk (SKU / name / category / price / stock summary / active). Detail page dengan stock breakdown per warehouse. Form create/edit dengan validasi client-side + server-side.

**Out of Scope:** Product variant (size/color), bundle product, product bulk import CSV.

**References:** Brief §2.2 PRD-01, §1.3; Vision §5.1

---

### WH-01 — Warehouse & Multi-Location Stock
- **Priority:** P1  |  **Depends on:** PRD-01

**User Story:**  
As **Rita (Admin)**, I want to manage multiple warehouses, and as **Wawan (Warehouse)**, I want to see stock levels per warehouse — so we can plan restock and fulfillment per location.

**Context:**  
Vision §2 P1: "Stok tersebar di beberapa gudang". WH-01 hilangkan blind spot itu.

**Functional Requirements:**
- FR-5.1 CRUD Warehouse (field: `code`, `name`, `location`, `is_active`).
- FR-5.2 Setiap produk memiliki **satu baris `product_stocks`** per warehouse (composite unique `(product_id, warehouse_id)`).
- FR-5.3 Tampilan stok produk menunjukkan **total** (sum semua warehouse) DAN **rincian per warehouse**.
- FR-5.4 Stok TIDAK PERNAH di-edit langsung oleh UI/repository — hanya lewat service transaksional (GoodsReceipt / GoodsIssue / Adjustment). (**BR-002**, **BR-015**).
- FR-5.5 Untuk kepentingan **initial seed** (setup awal), diperbolehkan setup awal stok via seed script (bukan lewat UI).

**Business Rules Applied:** BR-002, BR-015, BR-017, BR-019

**Data Touched:** `warehouses`, `product_stocks`

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | CRUD warehouse; lihat stok semua warehouse |
| Sales | Lihat stok tersedia (tersedia = quantity > 0 di warehouse manapun) |
| WarehouseStaff | Lihat stok per warehouse (khususnya warehouse tempat dia bekerja — MVP: bisa lihat semua) |

**Acceptance Criteria:**
- **AC1:** Given warehouse Bandung (id=1) dan Jakarta (id=2), When Admin isi seed 100 unit Produk-A di Bandung + 25 di Jakarta, Then detail Produk-A menunjukkan total=125 + rincian per warehouse.
- **AC2:** Given produk dipindahkan lewat goods issue Bandung 10 unit, When Warehouse Staff lihat stok, Then Bandung=90, Jakarta=25, total=115.
- **AC3:** Given quantity = 0 di suatu warehouse, When Sales buka katalog, Then produk tetap tampil (stok summary "0 di Bandung, 25 di Jakarta").
- **AC4:** Given Admin coba `UPDATE product_stocks SET quantity = 999` lewat endpoint langsung (tidak ada), Then tidak ada endpoint UI untuk itu (harus lewat receipt/issue).

**Test Scenarios:**
- **Happy:** 1 produk, stok berbeda di 2 warehouse.
- **Edge:** Warehouse dinonaktifkan → stok masih tercatat, tapi tidak bisa dipilih untuk PO/SO baru.
- **Consistency:** Total dari agregasi = sum baris `product_stocks` (tidak ada cache statis).

**UI/UX Notes:**  
Halaman detail produk: kolom "Stock breakdown" dengan tabel warehouse × quantity. Halaman list warehouse untuk Admin (CRUD).

**Out of Scope:** Stock transfer antar-warehouse (mekanisme internal), warehouse zone/rack, capacity limit per warehouse.

**References:** Brief §2.2 WH-01, §1.3

---

## 6. Domain: Transactions

### PO-01 — Purchase Order & Goods Receipt
- **Priority:** P0  |  **Depends on:** PRD-01, WH-01, ARCH-01, DB-01

**User Story:**  
As **Rita (Admin)** or **Wawan (Warehouse)**, I want to create Purchase Orders to suppliers when stock is low, and when goods arrive, record the receipt so stock reflects the actual quantity — with an audit trail.

**Context:**  
Vision §2 P3-P4: race condition stok minus + perubahan stok tanpa jejak. PO-01 & Goods Receipt = separuh mekanisme yang mencegahnya.

**Functional Requirements:**

**PO Creation:**
- FR-6.1 CRUD Purchase Order dengan field header: `supplier_id`, `destination_warehouse_id`, `status`, `order_date`, `note`, `created_by`, `created_at`, `updated_at`.
- FR-6.2 Item PO (line): `product_id`, `qty_ordered`, `qty_received` (default 0), `purchase_price`.
- FR-6.3 PO baru dimulai dengan status `Draft`.
- FR-6.4 Admin/Warehouse dapat `submit` PO → status jadi `Ordered`.
- FR-6.5 Admin dapat cancel PO di state `Draft` atau `Ordered` (belum ada receipt) → `Cancelled`.

**Goods Receipt:**
- FR-6.6 PO status `Ordered` atau `PartiallyReceived` dapat diterima barangnya (**BR-014**).
- FR-6.7 Warehouse Staff (atau Admin) memproses goods receipt: input `qty_received` per line (bisa partial).
- FR-6.8 Goods receipt WAJIB satu transaksi DB yang:
  1. `beginTransaction()`
  2. Update `po_items.qty_received += input_qty` (per line)
  3. `INSERT INTO stock_ledger` (type=`Receipt`, ref_type=`PO`, ref_id=po_id, qty=input_qty, product_id, warehouse_id=destination, done_by=user_id, done_at=now)
  4. `UPDATE product_stocks SET quantity = quantity + input_qty WHERE product_id=? AND warehouse_id=?` (upsert kalau belum ada baris)
  5. Jika semua line `qty_received >= qty_ordered` → PO status jadi `Received`; jika sebagian → `PartiallyReceived`.
  6. `commit()` (atau `rollBack()` bila salah satu step gagal). (**BR-008**, **BR-015**)
- FR-6.9 Goods receipt tidak boleh melebihi `qty_ordered - qty_received` per line.
- FR-6.10 Produk & warehouse yang di-nonaktifkan tidak dapat dipilih di PO baru (**BR-019**), tetapi PO existing tetap dapat diproses receipt-nya.

**Business Rules Applied:** BR-002, BR-008, BR-012, BR-014, BR-015, BR-017, BR-019

**Data Touched:** `purchase_orders`, `po_items`, `product_stocks`, `stock_ledger`, `suppliers` (read), `products` (read), `warehouses` (read)

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | CRUD PO, submit, cancel, receive goods |
| Sales | ❌ Tidak dapat |
| WarehouseStaff | Boleh usulkan/buat PO (Brief §1.2), submit, process goods receipt |

**Acceptance Criteria:**
- **AC1:** Given PO Draft dengan 3 line, When submit, Then status = `Ordered`.
- **AC2:** Given PO Ordered dengan qty_ordered=100, When goods receipt qty=60, Then po_items.qty_received=60, product_stocks bertambah 60, stock_ledger tercatat 1 baris Receipt, PO status = `PartiallyReceived`.
- **AC3:** Given PO PartiallyReceived line1=(80/100), When goods receipt line1 qty=20, Then line1 = full (100/100); jika semua line full → PO = `Received`.
- **AC4:** Given user coba receipt qty=150 untuk line qty_ordered=100, Then validasi tolak (max 100).
- **AC5:** Given user coba cancel PO yang sudah `PartiallyReceived`, Then endpoint tolak (business rule: hanya cancel sebelum receipt pertama).
- **AC6:** Given user Sales coba `POST /purchase-orders`, Then 403.
- **AC7:** Given transaksi goods receipt gagal di step 3 (mis. constraint violation), Then rollBack, product_stocks & stock_ledger tidak berubah, po_items tidak berubah.

**Test Scenarios:**
- **Happy:** PO full receipt satu kali.
- **Happy:** PO partial receipt 2 tahap → status transition benar.
- **Edge:** Receipt qty melebihi ordered → tolak.
- **Edge:** Receipt saat PO `Cancelled` → tolak.
- **Integration test (TEST-02):** Goods receipt update stock+ledger di MySQL nyata → assert kedua tabel konsisten.
- **Consistency test:** Force rollback di tengah transaksi → tidak ada partial update.

**UI/UX Notes:**  
List PO dengan filter status. Detail PO menampilkan line items + progress bar receipt. Modal atau halaman goods receipt untuk input qty received per line.

**Out of Scope:** Return-to-supplier, PO approval workflow multi-level, price change history.

**References:** Brief §2.3 PO-01, §3.1 ARCH-02

---

### SO-01 — Sales Order, Approval & Goods Issue
- **Priority:** P0 (Critical — segregation of duties + concurrency)  |  **Depends on:** PRD-01, WH-01, ARCH-01, ARCH-02, DB-01

**User Story:**  
As **Beni (Sales)**, I want to create sales orders that Rita approves and Wawan fulfills, so that customer orders are processed with proper authorization and stock integrity.

**Context:**  
SO-01 memuat **dua Value Prop kritis** sekaligus: **Segregation of Duties** (§4.1) dan **Integritas Data pada Concurrency** (§4.1 + ARCH-02). Salah satu keliru → critical failure.

**Functional Requirements:**

**SO Creation (by Sales):**
- FR-7.1 Sales membuat SO dengan header: `customer_id`, `source_warehouse_id`, `status=Draft`, `order_date`, `note`, `created_by=session.user_id`, timestamps.
- FR-7.2 Item SO (line): `product_id`, `qty`, `sale_price`.
- FR-7.3 Sales dapat submit SO Draft → status jadi `PendingApproval`.
- FR-7.4 Sales dapat cancel SO **miliknya sendiri** di state Draft / PendingApproval (belum Approved) → `Cancelled`.

**Approval (by Admin only — Segregation of Duties):**
- FR-7.5 Admin approve SO PendingApproval → status jadi `Approved`.
- FR-7.6 Admin reject SO → status jadi `Cancelled` dengan reason optional.
- FR-7.7 **BR-001 Segregation of Duties: Server memeriksa `session.user_id != sales_order.created_by` sebelum approve** — meski Sales adalah pembuat SO, endpoint approve harus tolak dengan 403.
- FR-7.8 UI Sales tidak menampilkan tombol Approve (defense-in-depth), tetapi backend adalah otoritas.

**Goods Issue (by Warehouse Staff or Admin):**
- FR-7.9 Goods Issue hanya untuk SO status `Approved` (**BR-013**).
- FR-7.10 Goods Issue WAJIB satu transaksi DB yang:
  1. `beginTransaction()`
  2. **Lock row** `product_stocks WHERE product_id=? AND warehouse_id=?` dengan `SELECT ... FOR UPDATE` (atau optimistic version — pilih di ADR-002).
  3. Cek `product_stocks.quantity >= so_item.qty`. Jika tidak → `rollBack()` + reject dengan pesan "Stok tidak mencukupi".
  4. `UPDATE product_stocks SET quantity = quantity - so_item.qty WHERE ...`
  5. `INSERT INTO stock_ledger` (type=`Issue`, ref_type=`SO`, ref_id=so_id, qty=so_item.qty (negatif atau positif convention), product_id, warehouse_id, done_by, done_at).
  6. Update SO status = `Fulfilled` (kalau semua line issued).
  7. `commit()`. (**BR-009**, **BR-010**, **BR-015**)
- FR-7.11 **ARCH-02 Race Condition:** Dua request Goods Issue simultan untuk produk+warehouse yang sama tidak boleh oversell → satu sukses, satu ditolak "Stok tidak mencukupi".

**Business Rules Applied:** BR-001, BR-002, BR-009, BR-010, BR-011, BR-013, BR-015, BR-017, BR-018, BR-019

**Data Touched:** `sales_orders`, `so_items`, `product_stocks`, `stock_ledger`, `customers` (read), `products` (read), `warehouses` (read), `users` (untuk created_by/approved_by/issued_by)

**Authorization:**
| Role | Can Do |
|------|--------|
| Admin | CRUD SO (view all), approve, reject, cancel, process goods issue |
| Sales | Create SO, view **own SO** only (**BR-018**), submit for approval, cancel own before Approved. **CANNOT approve own SO (BR-001)**. |
| WarehouseStaff | View SO Approved, process goods issue |

**Acceptance Criteria:**
- **AC1:** Given Sales Beni buat SO Draft, When submit, Then SO = `PendingApproval` dan tampil di antrean Rita.
- **AC2:** Given Beni buat SO, When Beni coba `POST /sales-orders/{id}/approve`, Then **403** (backend cek `created_by == session.user_id`) — bahkan kalau Beni memanipulasi request langsung.
- **AC3:** Given Rita approve SO Beni, Then SO = `Approved`, `approved_by = Rita.id`, `approved_at = now`.
- **AC4:** Given SO Approved, When Wawan process goods issue → stok cukup, Then product_stocks berkurang, stock_ledger tercatat Issue, SO = `Fulfilled`.
- **AC5:** Given SO Approved untuk 20 unit Produk-A, tetapi stock Produk-A di source_warehouse hanya 10 unit, When Wawan process issue, Then reject dengan pesan "Stok tidak mencukupi"; SO tetap `Approved` (tidak berubah); tidak ada baris ledger tercatat.
- **AC6:** **ARCH-02:** Given stock Produk-A di Bandung = 10, When 2 request goods issue simultan (req A qty=7, req B qty=5) diproses dalam waktu bersamaan, Then salah satu sukses dan yang lain gagal (mustahil kedua-nya sukses karena 7+5=12 > 10). Akhirnya stock = 3 (kalau A sukses) atau 5 (kalau B sukses).
- **AC7:** Given Beni login, When akses list SO, Then hanya melihat SO buatannya sendiri (BR-018).
- **AC8:** Given SO status Draft, When Beni cancel, Then status = `Cancelled`.
- **AC9:** Given SO status `Fulfilled`, When siapa saja coba cancel, Then endpoint tolak.

**Test Scenarios:**
- **Happy:** SO Draft → PendingApproval → Approved → Fulfilled.
- **Segregation of Duties (BR-001):** Sales Beni buat SO → coba approve dengan cURL langsung → 403.
- **Concurrency (BR-010, ARCH-02):** Integration test — 2 thread PHP (atau simulasi terkontrol) issue simultan → assert satu ditolak, stok tetap valid (>= 0).
- **Edge:** Approve SO yang sudah Cancelled → tolak.
- **Edge:** Cancel SO Fulfilled → tolak.
- **View scope (BR-018):** Sales lain login → tidak melihat SO Beni.

**UI/UX Notes:**  
List SO dengan filter status. Detail SO dengan histori aksi (created, submitted, approved, issued). Tombol Approve/Reject hanya untuk Admin. Modal goods issue untuk Warehouse.

**Out of Scope:** Multi-level approval, discount/tax calculation, invoice generation, credit-check customer.

**References:** Brief §2.4 SO-01, §1.2 (SoD), §3.1 ARCH-02

---

## 7. Domain: Views & Reporting

### VIEW-01 — List, Detail & Empty State
- **Priority:** P1  |  **Depends on:** PRD-01, PO-01, SO-01

**User Story:**  
As **any authenticated user**, I want each entity (Product / PO / SO) to show a list view and a detail view, and when there is no data, I want a clear empty state — so I know what to do next.

**Functional Requirements:**
- FR-8.1 List page untuk Products, Purchase Orders, Sales Orders sesuai scope role.
- FR-8.2 Detail page (klik row → detail) untuk masing-masing entity.
- FR-8.3 **Empty state**: kalau tidak ada data — tampilkan ilustrasi + pesan i18n + CTA (mis. "Belum ada Purchase Order. Klik 'Buat PO' untuk memulai.").
- FR-8.4 Empty state khusus role: Sales melihat "Belum ada SO yang Anda buat"; Admin lihat "Belum ada SO".

**Business Rules Applied:** BR-018, BR-020

**Data Touched:** semua entity utama (read)

**Authorization:** sesuai scope tiap entity.

**Acceptance Criteria:**
- **AC1:** Given tidak ada produk, When buka `/products`, Then tampil empty state dengan ilustrasi + tombol CTA (Admin: "Tambah Produk", Sales: pesan info saja).
- **AC2:** Given ada 5 SO, When Beni buka `/sales-orders`, Then hanya 5 miliknya yang tampil.

**Test Scenarios:**
- **Happy:** List + detail semua entity.
- **Edge:** Empty state semua entity 3 role → 3 × 3 = 9 kombinasi harus tampil ilustrasi.

**UI/UX Notes:** Illustration dari SVG (ASSET-01). Empty state selalu punya heading, subtext, dan CTA (kalau relevan role).

**Out of Scope:** Custom empty state per warehouse.

**References:** Brief §2.5 VIEW-01

---

### FIND-01 — Search, Filter, Sort & Pagination
- **Priority:** P1  |  **Depends on:** VIEW-01, DB-01

**User Story:**  
As Sales/Warehouse/Admin, I want to search / filter / sort / paginate the lists, so that I can find data quickly even when there are hundreds of rows.

**Functional Requirements:**
- FR-9.1 **Product list:** search by `name` atau `sku`; filter by `category` + status stok (`low_stock` / `normal`); pagination 10/halaman.
- FR-9.2 **PO / SO list:** search by nomor + pihak terkait (supplier/customer); filter by `status`; sort by `order_date` (asc/desc); pagination 10/halaman.
- FR-9.3 Pagination **tetap aktif** saat pindah halaman (state URL query string atau server-side).
- FR-9.4 Seed data menjamin: minimal 30 produk + 25 order → memungkinkan >= 2 halaman untuk demo.
- FR-9.5 Query menggunakan **prepared statement** (**BR-016**) — tidak ada concat input user.
- FR-9.6 Index DB yang relevan: `products(sku)`, `products(category_id)`, `sales_orders(status, created_by)`, `purchase_orders(status)`, `stock_ledger(done_at, product_id)`.

**Business Rules Applied:** BR-016, BR-018 (Sales: only own SO), BR-020

**Data Touched:** products, purchase_orders, sales_orders (semua read)

**Authorization:** sesuai scope role.

**Acceptance Criteria:**
- **AC1:** Given 30 produk, When buka `/products?page=1`, Then 10 produk pertama tampil + link ke halaman 2, 3.
- **AC2:** Given search `"PROD-025"`, When ketik di search box, Then hasil filter menampilkan produk tersebut (case-insensitive).
- **AC3:** Given filter status `low_stock`, When apply, Then daftar hanya produk `stock_total < reorder_point`.
- **AC4:** Given klik sort `order_date desc`, When pindah halaman 2, Then sort tetap `desc`.
- **AC5:** Given SQL injection attempt di search (`' OR 1=1 --`), Then tidak ada leak; query tetap parameterized.

**Test Scenarios:**
- **Happy:** Cari produk + filter kategori + pagination halaman 2.
- **Edge:** Search 0 hasil → empty state "Tidak ada hasil".
- **Security:** SQL injection payload → tidak ada leak.

**UI/UX Notes:** Search bar + filter dropdown di atas tabel. Sort di header kolom. Pagination footer.

**Out of Scope:** Full-text search, saved filter presets, export filtered results (langsung dari FIND-01 — pakai REPORT-01).

**References:** Brief §2.5 FIND-01

---

### DASH-01 — Dashboard Per Role
- **Priority:** P1  |  **Depends on:** PO-01, SO-01, WH-01

**User Story:**  
As **Rita / Beni / Wawan**, I want a dashboard tailored to my role, so that my most-relevant KPIs are visible on login.

**Functional Requirements:**

**Admin Dashboard:**
- FR-10.1 Nilai inventori = `SUM(product_stocks.quantity × products.purchase_price)`.
- FR-10.2 Jumlah produk di bawah reorder point (`SUM(stock_by_product) < reorder_point`).
- FR-10.3 Order pending per status: `Draft`, `PendingApproval`, `Approved` (belum Fulfilled).

**Sales Dashboard:**
- FR-10.4 Ringkasan SO milik user login per status (`Draft`, `PendingApproval`, `Approved`, `Fulfilled`, `Cancelled`).

**Warehouse Dashboard:**
- FR-10.5 Antrean goods receipt (PO status Ordered/PartiallyReceived).
- FR-10.6 Antrean goods issue (SO status Approved).
- FR-10.7 Produk low-stock (< reorder point).

**Semua Role:**
- FR-10.8 Angka berasal dari query agregasi ke DB, **BUKAN** angka statis atau di-cache.
- FR-10.9 Label & tombol i18n (**BR-020**).

**Business Rules Applied:** BR-016, BR-017, BR-018, BR-020

**Data Touched:** semua tabel utama (aggregate read)

**Authorization:**
| Role | Dashboard Content |
|------|-------------------|
| Admin | Inventory value, produk low-stock, pending order per status |
| Sales | SO summary miliknya |
| WarehouseStaff | Goods queue + low-stock |

**Acceptance Criteria:**
- **AC1:** Given goods issue 5 unit terjadi, When Admin refresh dashboard, Then nilai inventori berkurang sesuai harga beli × 5.
- **AC2:** Given SO Approved bertambah 1, When Warehouse buka dashboard, Then antrean goods issue naik 1.
- **AC3:** Given Sales A login, When cek dashboard, Then hanya melihat summary SO miliknya (bukan Sales lain).
- **AC4:** Given angka dashboard berbeda dari sum di DB langsung, Then test integration fail — angka WAJIB dari query real-time.

**Test Scenarios:**
- **Happy:** 3 role login → 3 dashboard berbeda.
- **Consistency:** Trigger transaction → dashboard reflect < 1 detik (refresh).
- **Query aggregation:** Unit test tanpa DB (fake repo) untuk logic; integration test dengan DB untuk verifikasi query.

**UI/UX Notes:** Stat tile (KPI cards) + list singkat (top 5 low-stock, misalnya). Semua warna sesuai tema light/dark (THEME-01).

**Out of Scope:** Grafik interaktif (bisa masuk Bonus §4.4), custom KPI, drill-down klik.

**References:** Brief §2.5 DASH-01; Vision §4.3 Pillar Traceability

---

### REPORT-01 — CSV Export
- **Priority:** P1  |  **Depends on:** DASH-01

**User Story:**  
As **Rita** / **Grace** / **Wawan**, I want to export stock movements and order status by date range as CSV, so I can review or share with head office.

**Functional Requirements:**
- FR-11.1 CSV export **StockLedger** dengan filter rentang `done_at`.
- FR-11.2 CSV export **Order Status** (PO + SO) dengan filter rentang `order_date` + status.
- FR-11.3 Kolom CSV mengikuti locale user (header ID / EN) sesuai `session.locale` (**BR-020**).
- FR-11.4 CSV field di-escape aman (RFC 4180) — cegah CSV injection (values diawali `=`, `+`, `-`, `@` diprefix dengan `'`).
- FR-11.5 Data CSV berasal dari **query yang sama** dengan yang dipakai dashboard (single source of truth) — Vision Pillar Auditability.
- FR-11.6 Scope Sales: hanya export SO miliknya.

**Business Rules Applied:** BR-016, BR-017, BR-018, BR-020

**Data Touched:** stock_ledger, purchase_orders, sales_orders (read)

**Authorization:**
| Role | Can Export |
|------|-----------|
| Admin | Semua CSV |
| Sales | Order miliknya saja (BR-018) |
| WarehouseStaff | Stock ledger (semua warehouse) |

**Acceptance Criteria:**
- **AC1:** Given rentang 2026-01-01 s/d 2026-01-31, When export, Then file `.csv` download dengan header + rows sesuai.
- **AC2:** Given locale=en, Then header CSV: "Product", "Warehouse", "Type", "Quantity", "Reference", "By", "At".
- **AC3:** Given cell value dimulai `=SUM(...)`, When export, Then di-escape menjadi `'=SUM(...)` (cegah CSV injection).
- **AC4:** Given Sales A export order, Then CSV hanya berisi SO miliknya (bukan SO lain).

**Test Scenarios:**
- **Happy:** Export bulanan.
- **Security:** CSV injection payload → di-escape.
- **Consistency:** Export sum = dashboard sum untuk rentang yang sama.

**UI/UX Notes:** Modal / halaman export dengan date range picker + tombol Download. Nama file `{report_type}_{yyyy-mm-dd}_{yyyy-mm-dd}.csv`.

**Out of Scope:** Excel `.xlsx`, PDF, scheduled email export, custom column selection.

**References:** Brief §2.5 REPORT-01

---

### API-01 — Endpoint JSON
- **Priority:** P1  |  **Depends on:** AUTH-01, WH-01

**User Story:**  
As **a client** (bisa frontend JS Fetch API, atau integrasi eksternal masa depan), I want at least one JSON endpoint that returns clean data separately from HTML pages, so I know the app supports API contract.

**Functional Requirements:**
- FR-12.1 Endpoint: **`GET /api/products/{sku}/availability`** (representative dari Brief).
- FR-12.2 Response `Content-Type: application/json`.
- FR-12.3 Autentikasi sama dengan halaman HTML biasa (session-based di MVP; token/API-key bisa masuk v2.0).
- FR-12.4 HTTP status:
  - `200` sukses (body: `{"sku":"...","total":..., "warehouses":[{...}]}`)
  - `401` unauthenticated (body: `{"error":"unauthenticated"}`)
  - `404` SKU tidak ditemukan (body: `{"error":"not_found"}`)
- FR-12.5 **BUKAN** halaman HTML error — semua JSON.

**Business Rules Applied:** BR-016, BR-017

**Data Touched:** products, product_stocks (read)

**Authorization:** any authenticated user (semua role dapat cek availability).

**Acceptance Criteria:**
- **AC1:** Given SKU `PROD-001` ada + user login, When `GET /api/products/PROD-001/availability`, Then 200 + JSON body sesuai kontrak.
- **AC2:** Given tidak login, When call endpoint, Then 401 + JSON `{"error":"unauthenticated"}`.
- **AC3:** Given SKU tidak ada, Then 404 + JSON `{"error":"not_found"}`.
- **AC4:** Given `Accept: application/json`, When error, Then response tetap JSON (bukan HTML).

**Test Scenarios:**
- **Happy:** call authenticated → data sesuai.
- **Auth:** call tanpa session → 401 JSON.
- **NotFound:** SKU salah → 404 JSON.
- **Content-Type:** verify header `application/json`.

**UI/UX Notes:** Tidak ada UI (backend endpoint). Contoh pemakaian bisa didokumentasikan di `docs/architecture/api-contract.md` (Stage 4).

**Out of Scope:** OpenAPI spec formal, rate limiting, token/OAuth authentication.

**References:** Brief §2.6 API-01

---

## 8. Domain: Cross-Cutting Concerns

### VAL-01 — Validation & Feedback
- **Priority:** P2  |  **Depends on:** all forms

**Functional Requirements:**
- FR-13.1 Validasi frontend (client-side) untuk feedback cepat.
- FR-13.2 Validasi backend (server-side) sebagai **sumber kebenaran**.
- FR-13.3 Validasi field: required, enum, tanggal, foreign key valid, angka >= 0.
- FR-13.4 Input yang salah dipertahankan di form (tidak hilang).
- FR-13.5 Pesan validasi i18n (**BR-020**).

**Business Rules Applied:** BR-002, BR-003, BR-004, BR-016, BR-017, BR-020

**Acceptance Criteria:**
- **AC1:** Given form kosong, When submit, Then error validasi + input kosong tetap kosong (bukan default value).
- **AC2:** Given qty=-5, When submit backend, Then tolak dengan pesan "Kuantitas tidak boleh negatif".
- **AC3:** Given user bypass frontend (kirim langsung ke backend dengan data invalid), Then backend tetap validasi & tolak.

**Test Scenarios:**
- Unit: minimal 6 test validation (§32 CLAUDE.md), 3 area (product, order, user).
- Integration: submit form invalid → error response.

**Out of Scope:** Live validation on-blur setiap field (progressive enhancement post-MVP).

**References:** Brief §2.7 VAL-01

---

### ERR-01 — Error Handling
- **Priority:** P2  |  **Depends on:** all endpoints

**Functional Requirements:**
- FR-14.1 Unauth access → redirect `/login` (untuk HTML) atau `401` JSON (untuk `/api/*`).
- FR-14.2 Missing resource / URL tidak dikenal → `404` page (HTML) atau `404` JSON.
- FR-14.3 Forbidden operation → `403` page (HTML) atau `403` JSON.
- FR-14.4 DB exception & stack trace TIDAK PERNAH tampil ke user. Log ke file (rotate) atau `stderr` container.
- FR-14.5 Pesan user-facing generic + i18n (**BR-020**).

**Business Rules Applied:** BR-017, BR-020

**Acceptance Criteria:**
- **AC1:** Given URL `/no-such-page`, Then 404 page.
- **AC2:** Given DB error terjadi (mis. connection dropped), Then user lihat "Terjadi kesalahan sistem. Silakan coba lagi." + log tercatat.
- **AC3:** Given Sales `POST /users` (forbidden), Then 403.

**Test Scenarios:**
- Simulate DB down → user tetap dapat pesan generic.
- Access forbidden endpoint tiap role → response sesuai.

**Out of Scope:** Detailed error reporting UI, sentry-like remote logging.

**References:** Brief §2.7 ERR-01

---

### UI-01 — Responsive & Usability
- **Priority:** P2  |  **Depends on:** ASSET-01, THEME-01, I18N-01

**Functional Requirements:**
- FR-15.1 Halaman utama (Login, Dashboard, List, Detail, Form) berfungsi pada `360 px` (mobile) sampai desktop (`>= 1440 px`).
- FR-15.2 Navigasi & tabel **tidak terpotong** — scroll horizontal ada intentionally kalau perlu (mis. tabel wide), tapi body tidak overflow.
- FR-15.3 Form memiliki label, `:focus-visible` yang jelas, dan kontras dasar (target WCAG AA — **BR-021**).
- FR-15.4 Semua touch target ≥ 44 × 44 px pada breakpoint mobile (untuk Wawan).
- FR-15.5 Keyboard navigation (Tab / Enter / Esc) berfungsi untuk Rita.

**Business Rules Applied:** BR-020, BR-021

**Acceptance Criteria:**
- **AC1:** Given viewport 360 × 640, When buka Login, Then form muat tanpa scroll horizontal.
- **AC2:** Given desktop, When Tab dari email → password → submit → link forgot, Then focus state terlihat di setiap element.
- **AC3:** Given mode light dan dark, When cek text body vs background, Then rasio contrast >= 4.5:1.

**Test Scenarios:**
- Manual: screenshot 4 halaman utama di desktop + mobile.
- Automated: Chrome DevTools contrast checker.

**UI/UX Notes:** Detail di Stage 3 (UX/UI Spec).

**Out of Scope:** Full WCAG AAA, screen reader assessment lengkap (basic ARIA cukup).

**References:** Brief §2.7 UI-01; Vision §5.1

---

### DB-01 — Database Relational & Transaksi
- **Priority:** P2  |  **Depends on:** semua entity

**Functional Requirements:**
- FR-16.1 Skema mengikuti §1.3 Brief + Vision §9 Ubiquitous Language.
- FR-16.2 Primary key, foreign key, constraint (`quantity >= 0`), unique (`sku`, `email`).
- FR-16.3 Index pada kolom yang sering di-query: `products(sku)`, `sales_orders(status, created_by)`, `purchase_orders(status)`, `stock_ledger(done_at)`, `product_stocks(product_id, warehouse_id)`.
- FR-16.4 Semua query pakai **PDO prepared statement** (**BR-016**).
- FR-16.5 Multi-tabel operation (Goods Receipt / Goods Issue) dibungkus transaksi eksplisit (**BR-008, BR-009**).
- FR-16.6 `database/schema-and-seed.sql` (atau migration+seed setara) dapat membangun DB dari kosong.

**Business Rules Applied:** BR-002, BR-003, BR-004, BR-008, BR-009, BR-015, BR-016

**Acceptance Criteria:**
- **AC1:** Given DB kosong, When jalankan `schema-and-seed.sql`, Then semua tabel + data demo minimum ada (§7.1 Brief).
- **AC2:** Given SQL injection payload, When endpoint search, Then query tetap parameterized (log query menunjukkan `?` bukan konkat).
- **AC3:** Given force error tengah transaksi Goods Issue, Then rollBack — tidak ada partial update.

**Test Scenarios:**
- ERD lengkap (docs/architecture/erd.md atau erd.png).
- Penjelasan minimal 1 transaksi multi-tabel + 1 index (Brief PERINGATAN §2.7).

**Out of Scope:** Sharding, read-replica.

**References:** Brief §2.7 DB-01

---

### JOB-01 — Scheduled Script (Manual via Docker)
- **Priority:** P2  |  **Depends on:** PRD-01, WH-01

**Functional Requirements:**
- FR-17.1 Script `scripts/check-low-stock.php` yang query produk dengan `sum(stock) < reorder_point` dan output ringkasan (stdout atau file).
- FR-17.2 Dapat dijalankan dengan `docker compose exec app php scripts/check-low-stock.php`.
- FR-17.3 Script **mandiri** dari siklus HTTP request (tidak butuh session, tidak butuh browser).
- FR-17.4 Tidak wajib dijadwalkan otomatis di server penilaian (Brief §4.3, FAQ #9).

**Business Rules Applied:** BR-016

**Acceptance Criteria:**
- **AC1:** Given ada 5 produk low-stock, When jalankan script, Then output list 5 produk + qty + warehouse.
- **AC2:** Given tidak ada low-stock, When jalankan, Then output "No low-stock products".

**Test Scenarios:**
- Manual: jalankan lewat docker compose exec.
- Unit: logic filter low-stock (menggunakan fake repository).

**Out of Scope:** Cron actual, email/notification dari script.

**References:** Brief §2.7 JOB-01

---

## 9. Domain: Architecture (Non-Functional Requirements)

### ARCH-01 — Layered Architecture & Dependency Inversion
- **Priority:** P2 (foundational for all)  |  **Depends on:** none (foundational)

**Functional Requirements:**
- FR-18.1 3 layer pragmatis: **Controller** (HTTP/routing) → **Service** (business logic) → **Repository** (data access).
- FR-18.2 Minimal satu **Repository interface** dengan dua implementasi: MySQL (via PDO) + Fake/in-memory (untuk unit test).
- FR-18.3 Service menerima dependency lewat **constructor injection** (manual, tidak pakai DI container framework).
- FR-18.4 **Tidak ada `new PDO()`** di Service class (dependency inversion).
- FR-18.5 Business logic (perhitungan low-stock, validasi transisi status, ownership check) dapat di-unit-test tanpa koneksi DB.

**Business Rules Applied:** semua BR yang di-enforce di Service layer

**Acceptance Criteria:**
- **AC1:** Given `SalesOrderService`, When konstruksi, Then wajib inject `SalesOrderRepositoryInterface` + `SalesOrderPolicy` — no `new` di dalamnya.
- **AC2:** Given unit test `SalesOrderService::approve()`, When run, Then tidak butuh MySQL (pakai FakeSalesOrderRepository).
- **AC3:** Given `grep 'new PDO' app/Service/`, Then tidak ada match.

**Test Scenarios:**
- Unit test service dengan fake repo (BR-001, BR-011, BR-013 dapat diuji tanpa DB).
- Integration test dengan MySqlRepository (real DB).

**Out of Scope:** Onion / Hexagonal 4-ring (Brief FAQ #4 cukup 3-layer), DI container.

**References:** Brief §3.1 ARCH-01

---

### ARCH-02 — Transaksi & Concurrency-Safe Stock Operation
- **Priority:** P0 (CRITICAL — 0 kalau gagal)  |  **Depends on:** PO-01, SO-01, DB-01

**Functional Requirements:**
- FR-19.1 Setiap goods receipt / issue dibungkus `beginTransaction` / `commit` / `rollBack`.
- FR-19.2 Cek stok + update stok + insert ledger — semua dalam **satu unit transaksi**.
- FR-19.3 Mekanisme mencegah oversell pada concurrent goods issue — pilihan strategi didokumentasikan di **ADR-002**:
  - **Opsi A (default rekomendasi): Pessimistic row lock** — `SELECT quantity FROM product_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE` di dalam transaksi. Lock dilepas saat commit/rollback.
  - **Opsi B: Optimistic locking** — kolom `version` di `product_stocks`; `UPDATE ... SET quantity=?, version=version+1 WHERE version=?` — kalau `affected_rows=0` → retry / reject.
- FR-19.4 Bukti (§8.1 Brief demo): assessor dapat men-demo skenario 2 request paralel → salah satu ditolak dengan aman.

**Business Rules Applied:** BR-002, BR-008, BR-009, BR-010, BR-015

**Acceptance Criteria:**
- **AC1:** Given stock=10, When 2 goods issue simultan (qty=7 dan qty=5) diproses, Then tepat satu sukses; yang lain gagal dengan pesan "Stok tidak mencukupi"; stock akhir = 3 atau 5 (bukan -2).
- **AC2:** Given force error di tengah transaksi Goods Receipt, Then rollBack — `product_stocks` dan `stock_ledger` **tidak berubah**.
- **AC3:** Given stock_ledger dan product_stocks dilihat setelah operasi, Then jumlah pergerakan ledger + stok awal = stok akhir (konsistensi).

**Test Scenarios (WAJIB — sebagian di integration test):**
- **Integration:** 2 goroutine/thread PHP (via `pcntl_fork` atau simulasi kontrol) issue simultan → assert 1 sukses, 1 gagal.
- **Integration:** Rollback force → tabel tidak berubah.
- **Consistency invariant:** After N operations, `product_stocks.quantity == initial + sum(ledger.qty)` untuk product+warehouse.

**Out of Scope:** Distributed lock (Redis / Zookeeper), eventual consistency.

**References:** Brief §3.1 ARCH-02

---

## 10. Domain: UX Enhancements (Vision Addition)

### I18N-01 — Static UI Internationalization (i18next)
- **Priority:** P3  |  **Depends on:** UI-01

**User Story:**  
As **Rita / Beni / Wawan** (id) or **Grace** (en), I want the UI in my preferred language, so I don't misread labels and act on the wrong control.

**Functional Requirements:**
- FR-20.1 Library: **i18next-core** (UMD, ~15 KB) + **i18next-http-backend** (lazy-load JSON).
- FR-20.2 File translasi: `public/assets/locales/en/translation.json` (default) + `public/assets/locales/id/translation.json`.
- FR-20.3 Semua text UI di-render lewat `i18next.t('key')` — **tidak ada string hardcoded** (**BR-020**).
- FR-20.4 Language switch di header: toggle EN ↔ ID; preferensi tersimpan di `localStorage['locale']`.
- FR-20.5 **Default locale = `en`** (Vision §4.2 #5).
- FR-20.6 Backend juga menerima `Accept-Language` atau `session.locale` untuk render halaman awal (server-side) sehingga tidak flash-of-untranslated-text (FOUT).

**Business Rules Applied:** BR-020

**Acceptance Criteria:**
- **AC1:** Given default first visit, When buka Login, Then label "Sign In" (EN) tampil.
- **AC2:** Given switch ke ID, When refresh + tutup tab + buka lagi, Then locale tetap ID.
- **AC3:** Given grep di codebase, Then tidak ada string UI yang tidak lewat `t()` (E9 metric).
- **AC4:** Given locale=en, When export CSV, Then header CSV EN (integrasi ke REPORT-01).

**Test Scenarios:**
- Coverage i18n key: script scan semua HTML/JS untuk string suspicious.
- Switch language 3 halaman utama → semua ter-translate.

**UI/UX Notes:** Toggle di header dengan ikon globe + label locale.

**Out of Scope:** Auto-translate dynamic content (bonus I18N-02), RTL languages, plural forms kompleks (kalau dibutuhkan minimal).

**References:** Vision §4.2 #5, §5.1, §14

---

### THEME-01 — Tri-State Theme (Auto / Light / Dark)
- **Priority:** P3  |  **Depends on:** UI-01

**User Story:**  
As **Beni** (siang light, malam dark) or **Wawan** (gudang temaram, dark), I want a theme that respects my system preference and lets me override, so I can work without eye strain.

**Functional Requirements:**
- FR-21.1 Tri-state: `auto` (default) → ikut `prefers-color-scheme`; `light`; `dark`.
- FR-21.2 Toggle di header, siklus: `auto → light → dark → auto`.
- FR-21.3 Preferensi tersimpan di `localStorage['theme']`.
- FR-21.4 CSS pakai custom properties (design token) — dua set: `:root` (light) + `[data-theme="dark"]` (dark) + query `@media (prefers-color-scheme: dark)` untuk auto.
- FR-21.5 Kontras memenuhi WCAG AA (**BR-021**).
- FR-21.6 SVG icon otomatis ikut tema via `currentColor`.

**Business Rules Applied:** BR-021

**Acceptance Criteria:**
- **AC1:** Given first visit + OS dark, When buka aplikasi, Then theme dark otomatis.
- **AC2:** Given user pilih theme light manual, When tutup + buka lagi, Then tetap light.
- **AC3:** Given theme dark, When contrast checker berjalan, Then semua text body ≥ 4.5:1.
- **AC4:** Given switch theme, When cek, Then tidak ada flash / reload halaman.

**Test Scenarios:**
- Manual: switch tema 3 halaman utama → visual sesuai.
- Automated: contrast check dengan tool.

**UI/UX Notes:** Toggle icon: sun / moon / half-moon untuk state auto.

**Out of Scope:** Custom user theme (choose color), sepia mode.

**References:** Vision §4.2 #6, §5.1, §11 Risks

---

### ASSET-01 — Static UI Asset Strategy
- **Priority:** P3  |  **Depends on:** UI-01, THEME-01

**Functional Requirements:**
- FR-22.1 Icon: **Feather Icons SVG sprite** di `public/assets/img/icons/sprite.svg` (~30 KB).
- FR-22.2 Icon dipanggil via `<svg><use href="/assets/img/icons/sprite.svg#package"/></svg>`.
- FR-22.3 Logo: SVG buatan sendiri di `public/assets/img/logo.svg` (< 5 KB), `logo-mark.svg` untuk header sempit.
- FR-22.4 Illustration: SVG (flat, dari unDraw/Storyset dengan warna primary di-inline) atau WebP (kompleks).
- FR-22.5 Favicon: `favicon.svg` + `favicon.ico` + `apple-touch-icon.png`.
- FR-22.6 Total ukuran seluruh static UI asset target `< 100 KB` (E11).
- FR-22.7 Asset di-serve dengan cache header (Stage 4 detail).

**Business Rules Applied:** BR-020 (via alt-text i18n untuk aksesibilitas)

**Acceptance Criteria:**
- **AC1:** Given `ls -la public/assets/img/**/*`, Then total < 100 KB.
- **AC2:** Given theme switch, When cek warna icon, Then otomatis berubah (via `currentColor`).
- **AC3:** Given inspect sprite.svg, Then tidak ada JavaScript inline.

**Test Scenarios:**
- Bundle-size audit manual.
- Theme switch → icon color adaptif.

**Out of Scope:** Asset CDN, SVG optimization pipeline otomatis (SVGO manual saja).

**References:** Vision §5.1 Asset, §7 Guiding Principle #11

---

### IMAGE-01 — Product Image Upload & WebP Conversion
- **Priority:** P4  |  **Depends on:** PRD-01

**User Story:**  
As **Rita**, I want to upload a product image (optional), and the system stores it in an efficient format (WebP) with a random name, so mobile users load the catalog fast and the image can't be guessed.

**Functional Requirements:**
- FR-23.1 Endpoint POST multipart file upload (via form product).
- FR-23.2 Validasi MIME dengan `finfo_file()` — accepted: `image/jpeg`, `image/png`, `image/webp` (**BR-022**).
- FR-23.3 Validasi ukuran file <= 2 MB.
- FR-23.4 Validasi dimensi via `getimagesize()` — reject kalau parse gagal.
- FR-23.5 Resize dengan **PHP GD** — max dimensi 1200 × 1200 px (proporsional).
- FR-23.6 Convert ke WebP quality 82 (`imagewebp($image, $path, 82)`).
- FR-23.7 Nama file: `sha256(microtime(true).rand()).webp` — 16 karakter pertama.
- FR-23.8 Path: `public/uploads/products/YYYY/MM/{filename}.webp`.
- FR-23.9 Path relatif disimpan di `products.image_path`.
- FR-23.10 Dockerfile install `gd` extension + `libwebp-dev`.
- FR-23.11 Gambar produk lama masih dapat dilihat kalau format berbeda (backward compatible read).

**Business Rules Applied:** BR-022

**Acceptance Criteria:**
- **AC1:** Given upload JPG 1920×1080 3 MB, When submit, Then tolak (>= 2 MB) atau resize + convert (kalau <=2 MB) → tersimpan sebagai `.webp` ≤ 1200 × 675.
- **AC2:** Given nama file tersimpan, When cek, Then random hash (tidak bisa ditebak); path folder YYYY/MM.
- **AC3:** Given file bukan gambar (mis. PDF di-rename), When upload, Then MIME check tolak.
- **AC4:** Given container start, When `php -m | grep gd`, Then `gd` ada.
- **AC5:** Given upload 100 produk dengan image ~1 MB, Then rata-rata file tersimpan < 200 KB (E12).

**Test Scenarios:**
- Unit test `ImageUploadService::process()` dengan fake filesystem.
- Integration: upload real image via HTTP → cek file di disk + record DB.

**UI/UX Notes:** Preview image di form + tombol "Ganti / Hapus". Fallback icon placeholder kalau tidak ada image.

**Out of Scope:** Multiple images per product, crop editor, image CDN, watermark.

**References:** Brief §2.2 PRD-01; Vision §5.1 IMAGE-01, §11 Risks (GD extension)

---

## 11. Domain: Bonus (Optional)

### I18N-02 — Auto-Translate Dynamic Content via LibreTranslate
- **Priority:** P7 (Bonus)  |  **Depends on:** I18N-01, all master data

**User Story:**  
As **Grace** viewing product catalog, I want product names/descriptions (originally in ID) auto-translated to EN on-the-fly, so I don't need to ask a colleague to translate every item.

**Functional Requirements:**
- FR-24.1 Container terpisah `libretranslate` di `compose.yml` (`libretranslate/libretranslate:latest`).
- FR-24.2 Service PHP `TranslationService` yang call `POST http://libretranslate:5000/translate` dengan body `{q, source, target}`.
- FR-24.3 Cache hasil translasi (in-memory atau tabel `translation_cache(source_text_hash, source_lang, target_lang, translated_text)`) untuk hindari repeat call.
- FR-24.4 Fallback: jika LibreTranslate tidak reachable, tampilkan text asli + badge kecil "not translated".
- FR-24.5 Diaktifkan lewat env `TRANSLATION_ENABLED=true` (`.env`).
- FR-24.6 Tidak call layanan publik (Google Translate) — self-hosted saja.

**Business Rules Applied:** BR-020 (fallback ke i18n key kalau service down)

**Acceptance Criteria:**
- **AC1:** Given LibreTranslate container up, When Grace lihat katalog di locale=en, Then nama produk berbahasa ID di-translate ke EN inline.
- **AC2:** Given LibreTranslate container down, When Grace refresh, Then produk tetap tampil dengan text asli + badge fallback — tidak crash.
- **AC3:** Given cache hit, When request kedua text sama, Then tidak call LibreTranslate lagi.

**Test Scenarios:**
- Manual: start compose + libretranslate → lihat translasi.
- Manual: stop libretranslate → fallback tetap jalan.

**Out of Scope:** Human-verified translation, glossary/dictionary overrides.

**References:** Vision §5.3 Bonus, §14

---

## 12. Requirement Dependency Graph & Vertical Slice Sequencing

### 12.1 Dependency Graph (High-Level)

```
DB-01 ────────────────────────┐
      ▼                       ▼
   ARCH-01 ──────► AUTH-01 ──► USR-01 ──► PRD-01 ──► WH-01 ──► PO-01 ──► SO-01
                              │                                        ▲    ▲
                              └► AUTH-02                    ARCH-02────┘    │
                                                                            │
                              VAL-01, ERR-01, DB-01, UI-01 ────► (all forms & endpoints)
                              I18N-01 + THEME-01 + ASSET-01 ────► (all UI)
                              IMAGE-01 ────► PRD-01
                              VIEW-01, FIND-01, DASH-01, REPORT-01, API-01 ────► (after entities exist)
                              JOB-01 ────► PRD-01 + WH-01
                              I18N-02 (bonus) ────► after all above stable
```

### 12.2 Vertical Slice Sequencing (Recommended Build Order)

Berdasarkan §2 Brief ("Urutan pembangunan: vertical slice dahulu"):

1. **Slice 1 — Foundation** (Wk 2 Day 1-2)
   - DB-01 skeleton (`schema.sql` v1)
   - ARCH-01 skeleton (Controller/Service/Repository dir)
   - AUTH-01 + AUTH-02 (login/logout minimal, 1 user seeded)
   - I18N-01 skeleton (i18next-core + JSON EN/ID minimal)
   - THEME-01 skeleton (CSS token light/dark)
   - ASSET-01 skeleton (logo + sprite Feather)
   - VAL-01 + ERR-01 basic

2. **Slice 2 — Master Data** (Wk 2 Day 3-4)
   - USR-01 (Admin CRUD user)
   - PRD-01 + WH-01 (Product + Warehouse + Category + Supplier + Customer)
   - IMAGE-01 (upload image dengan WebP)

3. **Slice 3 — Purchase Flow** (Wk 2 Day 5 - Wk 3 Day 1)
   - PO-01 (PO Draft → Ordered → Goods Receipt full & partial)
   - Update DB-01: transaksi, ledger, stock_ledger

4. **Slice 4 — Sales Flow + CRITICAL Concurrency** (Wk 3 Day 2-3)
   - SO-01 (SO Draft → PendingApproval → Approved → Goods Issue → Fulfilled)
   - **ARCH-02** (concurrency-safe stock — row lock / optimistic; ADR-002)
   - **BR-001 Segregation of Duties test** (Sales tidak approve own SO)

5. **Slice 5 — Discovery & Reporting** (Wk 3 Day 4-5)
   - VIEW-01 + FIND-01 (List, search, filter, sort, pagination + empty state)
   - DASH-01 (dashboard 3 role, query agregasi)
   - REPORT-01 (CSV export)
   - API-01 (JSON endpoint)
   - JOB-01 (scheduled script check-low-stock)

6. **Slice 6 — Quality & Docs** (Wk 4 Day 1-3)
   - UI-01 polish, responsive final, WCAG check
   - Semua unit test (min 6) + integration test (min 3, termasuk ARCH-02)
   - Static analysis (PHPStan level 5)
   - Class diagram as-built (DESIGN-01)
   - ADRs (DESIGN-02): Repository pattern, Concurrency
   - Refactor log (DESIGN-03) + tech debt + SRP audit + critique
   - README lengkap, ai-usage-log.md finalisasi

7. **Slice 7 — Bonus (opsional)** (Wk 4 Day 4-5, kalau waktu cukup)
   - I18N-02 (LibreTranslate)
   - Mailhog email simulation
   - Audit trail master data
   - Dashboard grafik SVG

**Aturan pacing:** Jangan mulai slice N+1 sebelum slice N functionally complete (bisa demo end-to-end).

---

## 13. Definition of Done — Stage 2 (PRD)

- [x] Business Rules Catalog (BR-001 s/d BR-022) selesai
- [x] High-level data model didokumentasikan
- [x] 22 requirement fungsional/arsitektur/UX ter-catalog dengan template konsisten
- [x] Setiap requirement punya: User Story, FR, BR, Data, Authorization, AC, Test, Out of Scope
- [x] Dependency graph & vertical slice sequencing tersedia
- [ ] **Direview & disetujui peserta sebelum masuk Stage 3 (UX/UI Spec)**

---

## 14. References
- **Vision Document:** `docs/planning/product-vision.md` v1.4
- **Project Brief:** `Project Brief - Programmer.pdf`
- **CLAUDE.md:** root proyek

---

## 15. Next Stage — Preview UX/UI Spec (Stage 3)

Stage 3 akan menerjemahkan PRD ini ke spesifikasi visual & interaksi:
- Wireframe halaman utama (Login, Dashboard 3 role, List, Detail, Form, Empty state)
- Design token (color, typography, spacing) dark/light
- Component spec (button, input, table, modal, empty state, loader)
- User journey per persona (Rita/Beni/Wawan/Grace)
- Accessibility checklist per komponen

---

## Changelog
- **1.0 · 2026-09-01** — Draft awal PRD dari Vision v1.4. 22 requirement dengan template konsisten + Business Rules Catalog (BR-001 s/d BR-022) + Dependency Graph + Vertical Slice Sequencing.

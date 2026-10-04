# Dokumentasi Role & Hak Akses — Inventory & Order Management System

Dokumen ini adalah indeks untuk dokumentasi per-role. Setiap role punya file MD
sendiri yang berisi: daftar menu yang bisa diakses, penjelasan detail tiap
fitur, dan SOP (Standard Operating Procedure) langkah demi langkah.

Sumber kebenaran (source of truth) dokumen ini diambil langsung dari kode:
- `app/Entity/Role.php` — daftar role yang valid (enum kolom `users.role`)
- `database/seed.sql` (tabel `role_permissions`) — matriks permission_key → role
- `app/Service/PermissionService.php` — cara sistem mengecek hak akses
- `app/Controller/BaseController.php` — `requirePermission()` / `requirePermissionWithCsrf()`
- `views/layouts/main.php` — logika tampil/sembunyi menu sidebar (`$hasPermission(...)`)
- `docs/planning/prd.md` — matriks otorisasi resmi per requirement (FR/BR/AC)

## 1. Role yang Tersedia

| Role (nilai kolom DB) | Label UI | File Dokumentasi |
|---|---|---|
| `Admin` | Admin | [ROLE-ADMIN.md](./ROLE-ADMIN.md) |
| `Sales` | Sales | [ROLE-SALES.md](./ROLE-SALES.md) |
| `WarehouseStaff` | Warehouse Staff | [ROLE-WAREHOUSE-STAFF.md](./ROLE-WAREHOUSE-STAFF.md) |

Hanya ada 3 role. Role bersifat tetap/enum (tidak ada "custom role") — ini
sesuai FR-3.2: "Role saat create/edit hanya dari 3 pilihan: Admin, Sales,
WarehouseStaff."

## 2. Matriks Permission Key → Role (as-built, dari `database/seed.sql`)

| Permission Key | Fungsi | Admin | Sales | WarehouseStaff |
|---|---|:---:|:---:|:---:|
| `users.manage` | CRUD user, activate/deactivate | ✅ | ❌ | ❌ |
| `suppliers.manage` | CRUD master Supplier | ✅ | ❌ | ❌ |
| `products.manage` | CRUD Produk (create/edit/deactivate/activate) | ✅ | ❌ | ❌ |
| `categories.manage` | CRUD Kategori Produk | ✅ | ❌ | ❌ |
| `warehouses.manage` | CRUD master Gudang | ✅ | ❌ | ❌ |
| `customers.manage` | CRUD master Pelanggan | ✅ | ❌ | ❌ |
| `customers.view` | Melihat daftar & detail Pelanggan (`/customers`) — WarehouseStaff mendapat 403 | ✅ | ✅ | ❌ |
| `master_data.menu` | Menampilkan grup menu Categories/Warehouses/Suppliers/Customers di sidebar | ✅ | ❌ | ❌ |
| `purchase_orders.manage` | Lihat, buat, terima (Goods Receipt) Purchase Order | ✅ | ❌ | ✅ |
| `purchase_orders.submit` | Submit Purchase Order (Draft → Ordered) | ✅ | ❌ | ❌ |
| `purchase_orders.cancel` | Membatalkan Purchase Order | ✅ | ❌ | ❌ |
| `sales_orders.menu` | Menampilkan menu "Sales Orders" di sidebar; juga guard server-side `/sales-dashboard` (WarehouseStaff → 403) | ✅ | ✅ | ❌ |
| `sales_orders.approve` | Approve / Reject Sales Order | ✅ | ❌ | ❌ |
| `sales_orders.issue` | Memproses Goods Issue (kirim barang) untuk SO berstatus Approved | ✅ | ❌ | ✅ |
| `stock_ledger.view` | Melihat menu & halaman Stock Ledger | ✅ | ❌ | ✅ |
| `reports.stock_ledger.view` | Export CSV Stock Ledger | ✅ | ❌ | ✅ |
| `reports.sales_orders.view` | Export CSV Sales Order | ✅ | ✅ | ❌ |
| `reports.purchase_orders.view` | Export CSV Purchase Order | ✅ | ❌ | ❌ |

Catatan penting:
- Menu **Dashboard**, **Products (lihat saja)**, **My Profile** tidak butuh
  permission key apapun — tersedia untuk *semua* user yang sudah login
  (`requireAuth()`), tapi isi/kontennya berbeda per role (lihat file masing-masing role).
- **Categories / Warehouses / Suppliers / Customers (lihat saja)** juga masuk
  kategori "tidak butuh permission key" untuk **melihat** daftarnya — sama
  seperti Products. `master_data.menu` di baris atas hanya dipakai untuk
  menampilkan/menyembunyikan link-nya di sidebar (`views/layouts/main.php`);
  `*Controller::indexAction()`-nya sendiri hanya memanggil `requireAuth()`.
  Jadi Sales/WarehouseStaff **bisa** membuka `/categories` dkk. langsung lewat
  URL (HTTP 200, read-only) meski link menunya tidak tampil. Yang benar-benar
  dijaga server-side untuk keempat modul ini adalah aksi **kelola**-nya
  (`categories.manage`, dst.) — lihat §2 di `ROLE-SALES.md` /
  `ROLE-WAREHOUSE-STAFF.md` dan `tests/e2e/categories.spec.js`.
- **Customers** adalah pengecualian dari poin di atas: daftar/detailnya dijaga
  `customers.view` di server, jadi WarehouseStaff mendapat **403** di `/customers`.
- **Reports** (`/reports`): role tanpa `reports.stock_ledger.view` (Sales) hanya
  mendapat form rentang tanggal + tombol *Export Orders* (order miliknya); valuasi,
  analitik stok, dan ledger tidak dihitung maupun dirender untuk role tersebut.
- **Users**: Admin tidak bisa menonaktifkan akunnya sendiri atau mengubah role-nya
  sendiri (`UserService` menolak, tombolnya juga tidak ditampilkan) — mencegah
  Admin terakhir mengunci sistem.
- Bel **Notifikasi** (low-stock alert) hanya tampil untuk **Admin** dan
  **WarehouseStaff** (`DashboardController`, `NotificationController`) — Sales tidak melihatnya.
- Semua permission key **lain** di tabel di atas **selalu divalidasi ulang di server**
  (`BaseController::requirePermission()`), bukan cuma disembunyikan di UI — sesuai aturan
  proyek: *"Authorization ALWAYS enforced server-side (UI hiding is NOT authorization)"* dan
  BR-017. Pengecualiannya adalah `master_data.menu` itu sendiri (baris di atas), yang murni
  kontrol tampilan menu, bukan guard akses.

## 3. Prinsip Segregation of Duties (Pemisahan Tugas)

Aturan bisnis inti proyek ini (lihat `app/Service/SalesOrderPolicy.php`, BR-001):

> **Sales TIDAK PERNAH boleh approve/reject Sales Order — bahkan Sales Order
> miliknya sendiri.** Hanya Admin yang boleh approve/reject. Aturan ini adalah
> penolakan berbasis ROLE, bukan perbandingan "siapa pembuatnya" — jadi Admin
> yang membuat SO-nya sendiri **tetap boleh** menyetujui SO tersebut (karena Admin
> memang berperan sebagai approver dalam sistem), sedangkan Sales tidak boleh
> menyetujui SO apa pun, termasuk buatan sendiri.

Lihat detail per role di masing-masing file untuk penjelasan lengkap alur kerja
(SOP) dan aturan status order (state machine).

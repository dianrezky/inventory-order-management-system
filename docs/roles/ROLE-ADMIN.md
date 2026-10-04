# Role: Admin

> Sumber: `app/Entity/Role.php` (`Role::Admin`), `database/seed.sql` (role_permissions),
> `views/layouts/main.php`, seluruh Controller di `app/Controller/`, `docs/planning/prd.md`.

## 1. Ringkasan Role

Admin adalah role dengan **hak akses penuh** (superuser) dalam sistem. Admin
adalah satu-satunya role yang boleh mengelola data master (produk, kategori,
gudang, supplier, pelanggan, dan user), satu-satunya yang boleh **approve/reject**
Sales Order, dan satu-satunya yang boleh **membatalkan Purchase Order**.
Persona contoh di dokumen requirement: **Rita (Admin)**.

## 2. Daftar Menu Sidebar yang Terlihat

Semua permission key bernilai `true` untuk Admin, sehingga **seluruh menu
sidebar tampil**:

| Grup Menu | Item Menu | URL | Permission Key |
|---|---|---|---|
| — | Dashboard | `/dashboard` | *(auth saja)* |
| Master Data | Products | `/products` | *(lihat: auth saja; kelola: `products.manage`)* |
| Master Data | Categories | `/categories` | `master_data.menu` *(sidebar link only)* + `categories.manage` *(create/edit/delete/(de)activate)* |
| Master Data | Warehouses | `/warehouses` | `master_data.menu` *(sidebar link only)* + `warehouses.manage` *(create/edit/delete/(de)activate)* |
| Master Data | Suppliers | `/suppliers` | `master_data.menu` *(sidebar link only)* + `suppliers.manage` *(create/edit/delete/(de)activate)* |
| Master Data | Customers | `/customers` | `master_data.menu` *(sidebar link only)* + `customers.manage` *(create/edit/delete/(de)activate)* |
| Procurement | Purchase Orders | `/purchase-orders` | `purchase_orders.manage` (+ `purchase_orders.submit` untuk tombol Submit, `purchase_orders.cancel` untuk tombol Cancel — keduanya Admin-only) |
| Sales | Sales Orders | `/sales-orders` | `sales_orders.menu` (+ `sales_orders.approve`, `sales_orders.issue`) |
| Inventory | Stock Ledger | `/stock-ledger` | `stock_ledger.view` |
| Reports | Reports | `/reports` | `reports.stock_ledger.view` / `reports.sales_orders.view` / `reports.purchase_orders.view` |
| Administration | Users | `/users` | `users.manage` |
| Akun (pojok kanan atas) | My Profile | `/my-profile` | *(auth saja)* |
| Header | Notification bell (low stock) | — | tampil untuk Admin & WarehouseStaff |

## 3. Penjelasan Detail Setiap Fitur

### 3.1 Dashboard (`/dashboard`)
KPI ringkasan untuk seluruh operasional, diambil live dari database (tanpa cache):
- **Inventory Value** — total nilai seluruh stok di semua gudang.
- **Low Stock Count + daftar produk** — produk yang stoknya ≤ reorder point (SKU, nama, total stok, reorder point).
- **Purchase Orders by Status** — jumlah PO per status (Draft/Ordered/PartiallyReceived/Received/Cancelled).
- **Sales Orders by Status** — jumlah SO per status, **untuk semua Sales** (Admin tidak dibatasi hanya melihat SO miliknya sendiri).
- **Notifikasi stok rendah** (bel di header) — daftar notifikasi belum dibaca, bisa "mark all as read".

### 3.2 Master Data
#### Products (`/products`)
- List: pencarian nama/SKU, filter kategori & gudang, pagination.
- Detail: breakdown stok per gudang + pergerakan stok terakhir per gudang.
- **Create/Edit** (`products.manage`): field `sku` (unik, ditolak jika duplikat), `name`,
  `category_id`, `unit`, `purchase_price` (≥0), `sale_price` (≥0), `reorder_point` (≥0),
  gambar produk (opsional, otomatis dikonversi ke WebP saat upload).
- **Deactivate/Activate**: produk **tidak pernah dihapus** (hard delete) karena sudah
  terhubung ke histori PO/SO — hanya dinonaktifkan. Produk nonaktif tidak muncul lagi
  di dropdown pembuatan PO/SO baru, tapi tetap tampil di detail order lama.

#### Categories, Warehouses, Suppliers, Customers
Pola CRUD yang identik untuk keempatnya (`create`, `edit`, `deactivate`, `activate` —
tidak ada hard delete, sama seperti Products), masing-masing dijaga oleh
permission key sendiri (`categories.manage`, `warehouses.manage`, `suppliers.manage`,
`customers.manage`). Warehouse tambahan bisa melihat total stok di **semua gudang**
sekaligus (tidak dibatasi per lokasi).

> **Catatan teknis:** `master_data.menu` (dipunyai Admin saja) hanya menentukan
> apakah link-nya tampil di sidebar — bukan guard di server. Halaman list
> (`GET /categories`, `/warehouses`, `/suppliers`, `/customers`) hanya
> memerlukan login (`requireAuth()`), jadi Sales/WarehouseStaff tetap bisa
> membukanya langsung lewat URL dalam mode read-only (tanpa tombol
> Add/Edit/Delete). Lihat `docs/roles/ROLE-SALES.md` §2 dan
> `docs/roles/ROLE-WAREHOUSE-STAFF.md` §2 untuk detail, dan
> `tests/e2e/categories.spec.js` untuk buktinya.

### 3.3 Procurement → Purchase Orders (`/purchase-orders`)
- List dengan filter status, pencarian, filter gudang tujuan, pagination.
- Detail: total qty/nilai dipesan vs diterima ("Inbound Progress"), plus semua baris Stock Ledger yang terhubung ke PO tersebut (audit trail).
- **Create**: pilih supplier + gudang tujuan, tambahkan baris item (produk aktif, qty dipesan, harga beli) → status awal **Draft**.
- **Submit** (khusus Admin — `purchase_orders.submit`): Draft → **Ordered**. WarehouseStaff
  dapat membuat dan menerima PO (`purchase_orders.manage`) tapi tidak dapat submit — sesuai
  `PROJECT_REFERENCE.md` §2.3 PO-01 (diperbaiki 2026-09-24; sebelumnya submit ikut memakai
  `purchase_orders.manage` sehingga WarehouseStaff bisa submit juga).
- **Receive (Goods Receipt)**: hanya untuk status Ordered/PartiallyReceived. Input qty diterima per baris
  (boleh sebagian/partial, tidak boleh melebihi sisa `qty_ordered - qty_received`). Satu transaksi database
  yang: menambah `product_stocks`, mencatat baris `stock_ledger` (tipe `Receipt`), dan meng-update
  `po_items.qty_received`. Status otomatis menjadi **PartiallyReceived** atau **Received**.
- **Cancel** (khusus Admin — `purchase_orders.cancel`): hanya bisa dilakukan **sebelum ada receipt sama
  sekali** (status Draft/Ordered). PO yang sudah PartiallyReceived/Received tidak bisa dibatalkan.

### 3.4 Sales → Sales Orders (`/sales-orders`)
Admin melihat **SEMUA** Sales Order dari semua Sales (tidak dibatasi seperti role Sales).
- **Approve/Reject** (`sales_orders.approve`) — hanya untuk SO berstatus **PendingApproval**:
  - *Approve* → status **Approved**, tercatat `approved_by` & `approved_at`.
  - *Reject* → status berubah menjadi **Cancelled** (dengan alasan penolakan opsional yang disimpan
    di kolom `cancellation_reason`) — sistem ini tidak punya status "Rejected" terpisah, jadi Sales
    perlu membuat SO baru bila ingin mengajukan ulang.
  - **Catatan Segregation of Duties**: Admin **boleh** approve/reject SO yang ia buat sendiri —
    larangan self-approval hanya berlaku untuk role Sales, bukan Admin.
- **Cancel** — Admin boleh membatalkan SO milik siapa pun selama belum **Fulfilled**.
- **Process Goods Issue** (`sales_orders.issue`) — hanya untuk SO berstatus **Approved**. Mengurangi
  `product_stocks` di gudang asal, mencatat baris `stock_ledger` (tipe `Issue`), dan bila semua baris
  terkirim → status SO menjadi **Fulfilled**. Jika stok tidak cukup, proses ditolak dengan pesan
  "Stok tidak mencukupi" dan **tidak ada perubahan apa pun** (aman dari race condition —
  dua proses Goods Issue simultan pada produk+gudang yang sama tidak akan menyebabkan oversell).

### 3.5 Inventory → Stock Ledger (`/stock-ledger`)
Read-only log seluruh pergerakan stok (Receipt/Issue/Adjustment) di semua gudang. Filter: SKU, nama
produk, jenis pergerakan, gudang, rentang tanggal; bisa diurutkan & dipaginasi (25 baris/halaman,
mendukung pemuatan ulang sebagian via AJAX). *(Catatan: tipe "Adjustment" tersedia sebagai filter,
namun di aplikasi ini belum ada fitur input Adjustment manual — baris ledger hanya tercipta otomatis
dari proses Goods Receipt dan Goods Issue.)*

### 3.6 Reports (`/reports`)
- **Export Stock Ledger (CSV)** — seluruh gudang, filter sama seperti halaman Stock Ledger.
- **Export Orders (CSV)** — Admin mendapat gabungan **Sales Order dari semua Sales** + **semua Purchase
  Order**, difilter berdasarkan rentang tanggal (wajib isi tanggal mulai & akhir).

### 3.7 Administration → Users (`/users`)
- CRUD user penuh: `name`, `email` (unik — dicek case-insensitive, mis. `Beni@x.com` dianggap sama
  dengan `beni@x.com`), `password` (di-hash, wajib diisi saat create, opsional saat edit — jika
  dikosongkan password lama tidak berubah), `role` (harus salah satu dari Admin/Sales/WarehouseStaff).
- **Deactivate**, bukan delete — user nonaktif tidak bisa login lagi, tapi riwayat SO/PO yang pernah
  ia buat/approve tetap tampil apa adanya di sistem.
- **Activate** untuk mengaktifkan kembali user yang sempat dinonaktifkan.

### 3.8 My Profile (`/my-profile`)
Semua user (termasuk Admin) bisa mengubah `name` dan `email` miliknya sendiri. Tidak ada fitur ganti
password mandiri (self-service) — bila Admin perlu mengganti password user (termasuk dirinya sendiri),
dilakukan lewat menu Users → Edit.

## 4. Batasan yang Tetap Berlaku untuk Admin

Admin tidak dibatasi oleh menu, tapi tetap tunduk pada **aturan status/state machine** berikut (berlaku
untuk siapa pun, termasuk Admin):
- PO hanya bisa di-cancel sebelum ada receipt pertama.
- PO hanya bisa menerima barang saat status Ordered/PartiallyReceived.
- SO hanya bisa di-approve/reject saat status PendingApproval.
- SO hanya bisa diproses Goods Issue saat status Approved.
- SO berstatus Fulfilled **tidak bisa dibatalkan oleh siapa pun**, termasuk Admin.

## 5. Standard Operating Procedure (SOP)

### SOP-A1 — Login
1. Buka halaman `/login`.
2. Masukkan email dan password akun Admin yang aktif.
3. Jika kredensial valid & akun aktif → sistem membuat session baru dan mengarahkan ke `/dashboard`.
4. Jika salah/akun nonaktif → sistem menampilkan pesan error umum (tidak menyebutkan bagian mana yang
   salah, demi keamanan) dan tetap di halaman login.

### SOP-A2 — Membuat User Baru
1. Menu **Administration → Users** → klik tombol tambah user.
2. Isi Nama, Email (pastikan belum terdaftar), pilih Role (Admin/Sales/WarehouseStaff), isi Password awal.
3. Simpan. Jika email sudah dipakai, sistem menampilkan error validasi dan form tidak tersimpan — perbaiki lalu submit ulang.
4. User baru langsung bisa login dengan email & password yang diinput.

### SOP-A3 — Menonaktifkan / Mengaktifkan Kembali User
1. Menu **Users** → cari user yang dituju → klik **Deactivate**.
2. User tersebut tidak bisa login lagi mulai saat itu; seluruh riwayat transaksinya (SO/PO yang ia buat) tetap tampil di daftar terkait.
3. Untuk mengaktifkan kembali, buka detail/daftar user yang nonaktif → klik **Activate**.

### SOP-A4 — Mengelola Master Data (Produk / Kategori / Gudang / Supplier / Pelanggan)
1. Buka menu terkait di grup **Master Data**.
2. Klik tambah data baru, isi field wajib (untuk Produk: pastikan SKU unik dan angka harga/reorder point ≥ 0).
3. Simpan. Untuk mengubah data, buka baris data → Edit → Simpan.
4. Untuk "menghapus" data yang sudah pernah dipakai transaksi, gunakan **Deactivate** (bukan delete) agar histori transaksi lama tetap valid.

### SOP-A5 — Alur Purchase Order Lengkap (Create → Submit → Receive → Cancel)
1. Menu **Procurement → Purchase Orders** → klik buat PO baru.
2. Pilih Supplier dan Gudang Tujuan, tambahkan baris produk beserta qty dan harga beli, simpan → status **Draft**.
3. Buka detail PO → klik **Submit** → status berubah menjadi **Ordered** (PO resmi dikirim ke supplier).
4. Saat barang fisik datang di gudang, buka detail PO → klik **Receive** → isi qty yang benar-benar diterima per baris (boleh bertahap/partial) → simpan. Sistem otomatis menambah stok gudang tujuan, mencatat Stock Ledger, dan memperbarui status PO.
5. Jika supplier batal mengirim dan **belum ada barang yang diterima sama sekali**, buka detail PO → klik **Cancel**. Jika sudah ada receipt (PartiallyReceived/Received), PO tidak bisa dibatalkan lagi.

### SOP-A6 — Menyetujui / Menolak Sales Order
1. Menu **Sales → Sales Orders** → filter status **PendingApproval**.
2. Buka detail SO, periksa pelanggan, gudang asal, daftar item, dan ketersediaan stok.
3. Jika sesuai → klik **Approve** (status menjadi Approved, siap diproses gudang untuk pengiriman).
4. Jika tidak sesuai → klik **Reject**, isi alasan penolakan (opsional tapi disarankan) → status menjadi Cancelled dengan catatan alasan. Sales yang bersangkutan perlu membuat SO baru bila ingin mengajukan ulang.

### SOP-A7 — Memproses Goods Issue (Pengiriman Barang) sebagai Admin
1. Buka SO berstatus **Approved** yang stoknya sudah siap dikirim.
2. Klik **Issue / Ship**. Sistem memvalidasi stok tersedia di gudang asal.
3. Jika stok cukup → stok berkurang, Stock Ledger mencatat baris Issue, dan status SO menjadi Fulfilled bila semua baris sudah terkirim.
4. Jika stok tidak cukup → sistem menolak dengan pesan "Stok tidak mencukupi"; tidak ada perubahan data apa pun (SO tetap Approved).

### SOP-A8 — Mengekspor Laporan
1. Menu **Reports**.
2. Pilih rentang tanggal (wajib untuk export Orders).
3. Klik **Export Stock Ledger** untuk unduhan CSV seluruh pergerakan stok, atau **Export Orders** untuk unduhan CSV gabungan seluruh Sales Order + Purchase Order pada rentang tanggal tersebut.

### SOP-A9 — Memperbarui Profil Sendiri
1. Klik nama/avatar di pojok kanan atas → **My Profile**.
2. Ubah Nama dan/atau Email → Simpan.

## 6. Catatan Keamanan & Aturan Bisnis Penting

- Setiap aksi tulis (create/update/cancel/approve/dll.) selalu melalui pengecekan **CSRF token** dan
  **permission server-side** (`requirePermissionWithCsrf`), bukan hanya disembunyikan di tampilan.
- Perubahan stok **tidak pernah** dilakukan langsung lewat query biasa — selalu lewat service transaksional
  (`GoodsReceiptService` / `GoodsIssueService`) yang membungkus perubahan `product_stocks` +
  `stock_ledger` dalam satu transaksi database (commit/rollback bersama).
- Dua permintaan Goods Issue yang bersamaan pada produk & gudang yang sama tidak akan menyebabkan
  stok minus (oversell) — salah satu akan ditolak secara aman (ARCH-02).
- Prinsip Segregation of Duties **tidak berlaku untuk Admin** (Admin boleh approve SO buatannya
  sendiri) — ini memang didesain demikian karena Admin berperan sebagai otoritas tertinggi/pengawas,
  berbeda dari Sales yang memang dilarang menyetujui order apa pun.

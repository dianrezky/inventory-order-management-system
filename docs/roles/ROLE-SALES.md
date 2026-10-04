# Role: Sales

> Sumber: `app/Entity/Role.php` (`Role::Sales`), `database/seed.sql` (role_permissions),
> `views/layouts/main.php`, `app/Controller/SalesOrderController.php`,
> `app/Service/SalesOrderPolicy.php`, `docs/planning/prd.md`.

## 1. Ringkasan Role

Sales adalah role yang bertugas **membuat dan mengelola Sales Order milik
sendiri**, mulai dari input pesanan pelanggan sampai mengajukannya untuk
disetujui Admin. Sales **tidak bisa mengelola data master, tidak bisa
menyetujui order (termasuk order miliknya sendiri), dan tidak bisa memproses
pengiriman barang secara fisik** — semua itu wewenang Admin/WarehouseStaff.
Persona contoh di dokumen requirement: **Beni (Sales)**.

## 2. Daftar Menu Sidebar yang Terlihat

| Grup Menu | Item Menu | URL | Permission Key |
|---|---|---|---|
| — | Dashboard | `/dashboard` | *(auth saja — konten khusus Sales)* |
| Master Data | Products *(read-only)* | `/products` | *(auth saja; tanpa tombol create/edit karena tidak punya `products.manage`)* |
| Sales | Sales Orders | `/sales-orders` | `sales_orders.menu` ✅ |
| Reports | Reports | `/reports` | `reports.sales_orders.view` ✅ |
| Akun | My Profile | `/my-profile` | *(auth saja)* |

**Menu yang TIDAK muncul di sidebar** untuk Sales (karena tidak punya permission key-nya):
- Master Data → Categories / Warehouses / Suppliers / Customers (butuh `master_data.menu`)
- Procurement → Purchase Orders (butuh `purchase_orders.manage`)
- Inventory → Stock Ledger (butuh `stock_ledger.view`)
- Administration → Users (butuh `users.manage`)
- Bel notifikasi stok rendah (hanya untuk Admin & WarehouseStaff)

> **Catatan teknis penting — jangan disamaratakan:** `master_data.menu` hanya
> mengontrol tampil/tidaknya link di sidebar (`views/layouts/main.php`), **bukan**
> guard di server. Jika Sales membuka `/categories`, `/warehouses`, `/suppliers`,
> atau `/customers` langsung lewat URL, `*Controller::indexAction()` hanya
> memanggil `requireAuth()` — halaman tetap tampil (**HTTP 200**, **read-only**,
> tanpa tombol Add/Edit/Delete), sama seperti pola Products di §3.2 di bawah.
> Yang benar-benar ditolak dengan **403 Forbidden** bila diakses langsung adalah
> endpoint yang memang dijaga permission spesifik: Purchase Orders
> (`purchase_orders.manage`), Stock Ledger (`stock_ledger.view`), Users
> (`users.manage`), dan aksi kelola master data itu sendiri — create/update/
> delete/activate/deactivate (`categories.manage`, `warehouses.manage`, dst.).
> Diverifikasi lewat `tests/e2e/categories.spec.js`.

## 3. Penjelasan Detail Setiap Fitur

### 3.1 Dashboard (`/dashboard`)
Konten dashboard Sales **berbeda** dari Admin/WarehouseStaff — hanya menampilkan **ringkasan Sales
Order milik Sales yang login** (jumlah per status: Draft/PendingApproval/Approved/Fulfilled/Cancelled).
Sales **tidak** melihat inventory value, low-stock list, ataupun status PO — itu bukan bagian
tugasnya. Bel notifikasi juga tidak tampil untuk Sales.

### 3.2 Products (`/products`) — Read Only
Sales bisa melihat katalog produk (nama, SKU, harga jual, stok yang tersedia) untuk keperluan
membuat penawaran/pesanan ke pelanggan, tapi **tidak ada tombol tambah/edit/nonaktifkan** — tombol
tersebut disembunyikan di UI **dan** endpoint create/edit/deactivate akan menolak dengan 403 kalau
diakses langsung, karena Sales tidak punya `products.manage`. Hanya produk yang **aktif** yang muncul
di dropdown saat membuat Sales Order baru.

### 3.3 Sales Orders (`/sales-orders`) — Fitur Utama Sales
- **List**: Sales **hanya melihat Sales Order miliknya sendiri** (BR-018) — bukan milik Sales lain.
  Filter tersedia by status, pencarian, gudang, pengurutan.
- **Create** (`/sales-orders/create`): pilih Pelanggan, Gudang Asal (sumber pengiriman), tambahkan
  baris item (produk aktif, qty, harga jual) → tersimpan dengan status awal **Draft**.
- **Detail**: Sales hanya bisa membuka detail SO miliknya sendiri — mencoba membuka SO milik Sales
  lain akan ditolak (**403 Forbidden**), bahkan dengan memanipulasi URL/ID secara langsung.
- **Submit for Approval**: Draft → **PendingApproval**. Hanya pembuat SO itu sendiri yang boleh
  submit SO-nya.
- **Cancel**: Sales boleh membatalkan **SO miliknya sendiri**, tapi **hanya selama masih berstatus
  Draft atau PendingApproval**. Begitu SO sudah **Approved**, Sales **tidak bisa** membatalkannya lagi
  (harus minta Admin). SO berstatus **Fulfilled** tidak bisa dibatalkan oleh siapa pun.
- **Approve / Reject** — ❌ **Sales tidak memiliki hak ini sama sekali**, bahkan untuk SO buatannya
  sendiri (lihat bagian Segregation of Duties di bawah).
- **Process Goods Issue (pengiriman fisik)** — ❌ Sales tidak memiliki `sales_orders.issue`, jadi
  tidak bisa memproses pengiriman barang. Setelah SO di-approve Admin, proses pengiriman dilakukan
  oleh WarehouseStaff/Admin.

### 3.4 Reports (`/reports`)
Sales hanya bisa **Export Sales Order CSV** — dan hasilnya **dibatasi hanya order miliknya sendiri**
(BR-018), untuk rentang tanggal yang dipilih. Sales **tidak bisa** export Stock Ledger maupun
Purchase Order (tombol/opsi tersebut memang tidak relevan/tidak tersedia untuknya, dan endpoint akan
menolak baris data yang bukan haknya).

### 3.5 My Profile (`/my-profile`)
Sales bisa mengubah `name` dan `email` miliknya sendiri. Tidak ada fitur ganti password mandiri.

## 4. Fitur yang TIDAK Bisa Diakses Sales (Ringkasan)

| Fitur | Alasan |
|---|---|
| **Kelola** Master Data (create/edit/deactivate/activate/delete Produk/Kategori/Gudang/Supplier/Pelanggan) | Tidak punya `*.manage` — ditolak 403 di server, bukan hanya tombol disembunyikan. **Melihat** daftarnya (read-only, tanpa tombol kelola) tetap bisa diakses langsung lewat URL — lihat catatan teknis di §2. |
| Purchase Order (lihat maupun kelola) | Tidak punya `purchase_orders.manage` |
| Approve/Reject Sales Order | Dilarang oleh Segregation of Duties (BR-001), termasuk SO miliknya sendiri |
| Process Goods Issue | Tidak punya `sales_orders.issue` |
| Melihat Sales Order milik Sales lain | Dibatasi per-kepemilikan (BR-018) |
| Membatalkan SO yang sudah Approved | Hanya boleh cancel SO sendiri saat masih Draft/PendingApproval |
| Stock Ledger (lihat maupun export) | Tidak punya `stock_ledger.view` / `reports.stock_ledger.view` |
| Export Purchase Order | Tidak punya `reports.purchase_orders.view` |
| Manajemen User | Tidak punya `users.manage` |

## 5. Standard Operating Procedure (SOP)

### SOP-S1 — Login
1. Buka `/login`, masukkan email dan password akun Sales yang aktif.
2. Berhasil → diarahkan ke `/dashboard` yang menampilkan ringkasan SO milik sendiri per status.

### SOP-S2 — Membuat Sales Order Baru
1. Menu **Sales → Sales Orders** → klik buat SO baru.
2. Pilih Pelanggan dan Gudang Asal pengiriman.
3. Tambahkan baris item: pilih produk (hanya produk aktif yang muncul), isi qty, dan harga jual.
4. Simpan → SO tersimpan dengan status **Draft** (masih bisa diedit ulang/dibatalkan sebelum diajukan — sesuai alur di sistem, revisi dilakukan selagi berstatus Draft).

### SOP-S3 — Mengajukan Sales Order untuk Persetujuan (Submit)
1. Buka detail SO berstatus Draft yang sudah dicek ulang kebenarannya (pelanggan, item, qty, harga).
2. Klik **Submit for Approval** → status berubah menjadi **PendingApproval**.
3. SO akan masuk ke antrean Admin untuk di-approve/reject. Sales **tidak perlu dan tidak bisa**
   menyetujui SO ini sendiri — tunggu keputusan Admin.

### SOP-S4 — Membatalkan Sales Order Milik Sendiri
1. Buka detail SO milik sendiri.
2. Jika status masih **Draft** atau **PendingApproval** → klik **Cancel**.
3. Jika status sudah **Approved** atau **Fulfilled** → tombol Cancel tidak berlaku / akan ditolak
   sistem; hubungi Admin bila pesanan perlu dibatalkan setelah disetujui.

### SOP-S5 — Memantau Status Sales Order
1. Menu **Sales Orders** → gunakan filter status untuk memantau SO mana yang masih Draft,
   PendingApproval, sudah Approved (menunggu diproses gudang), sudah Fulfilled (selesai terkirim),
   atau Cancelled (termasuk yang ditolak Admin beserta alasannya bila diisi).
2. Jika SO ditolak Admin (menjadi Cancelled dengan alasan penolakan), buat Sales Order **baru**
   untuk mengajukan ulang pesanan (tidak ada mekanisme "kirim ulang" SO yang sama).

### SOP-S6 — Mengekspor Laporan Sales Order Milik Sendiri
1. Menu **Reports** → pilih rentang tanggal.
2. Klik **Export Orders** → file CSV berisi hanya Sales Order milik akun Sales yang sedang login.

### SOP-S7 — Memperbarui Profil Sendiri
1. Klik nama/avatar di pojok kanan atas → **My Profile** → ubah Nama/Email → Simpan.

## 6. Catatan Penting (Segregation of Duties)

Aturan pemisahan tugas (BR-001) memastikan **tidak ada Sales yang bisa menyetujui pesanannya
sendiri**, walaupun ia mencoba mengirim request langsung ke server (bukan lewat tombol UI) — validasi
dilakukan di server (`SalesOrderPolicy::assertCanDecide()`), bukan hanya di tampilan. Ini adalah
kontrol anti-fraud standar: pembuat order dan penyetuju order harus orang/role yang berbeda.

# Role: Warehouse Staff

> Sumber: `app/Entity/Role.php` (`Role::WarehouseStaff`), `database/seed.sql` (role_permissions),
> `views/layouts/main.php`, `app/Controller/PurchaseOrderController.php`,
> `app/Controller/SalesOrderController.php`, `docs/planning/prd.md`.

## 1. Ringkasan Role

Warehouse Staff bertanggung jawab atas **operasional fisik gudang**: menerima
barang dari supplier (Goods Receipt) dan mengirim/mengeluarkan barang untuk
pesanan pelanggan yang sudah disetujui (Goods Issue), serta mengawasi
ketersediaan stok. Warehouse Staff **boleh mengusulkan/membuat Purchase Order**
sendiri (mis. saat stok menipis), tapi **tidak boleh membatalkan PO** dan
**tidak boleh mengelola data master maupun menyetujui Sales Order**.
Persona contoh di dokumen requirement: **Wawan (Warehouse Staff)**.

## 2. Daftar Menu Sidebar yang Terlihat

| Grup Menu | Item Menu | URL | Permission Key |
|---|---|---|---|
| — | Dashboard | `/dashboard` | *(auth saja — konten khusus Warehouse)* |
| Master Data | Products *(read-only)* | `/products` | *(auth saja; tanpa `products.manage`)* |
| Procurement | Purchase Orders | `/purchase-orders` | `purchase_orders.manage` ✅ *(tanpa `purchase_orders.cancel`)* |
| Inventory | Stock Ledger | `/stock-ledger` | `stock_ledger.view` ✅ |
| Reports | Reports | `/reports` | `reports.stock_ledger.view` ✅ |
| Akun | My Profile | `/my-profile` | *(auth saja)* |
| Header | Bel Notifikasi (stok rendah) | — | ✅ tampil untuk role ini |

**Menu yang TIDAK muncul di sidebar** (karena tidak punya permission key-nya):
- Master Data → Categories / Warehouses / Suppliers / Customers (butuh `master_data.menu`)
- **Sales Orders** (butuh `sales_orders.menu` — link menu ini tidak tampil untuk Warehouse Staff)
- Administration → Users

> **Catatan teknis penting — jangan disamaratakan:** `master_data.menu` hanya
> mengontrol tampil/tidaknya link di sidebar, **bukan** guard di server. Jika
> Warehouse Staff membuka `/categories`, `/warehouses`, `/suppliers`, atau
> `/customers` langsung lewat URL, halaman tetap tampil (**HTTP 200**,
> **read-only**, tanpa tombol Add/Edit/Delete) — sama seperti pola Products
> di §3.2. Yang benar-benar ditolak **403 Forbidden** adalah Administration →
> Users (`users.manage`) dan aksi kelola master data itu sendiri —
> create/update/delete/activate/deactivate (`categories.manage`,
> `warehouses.manage`, dst.). Diverifikasi lewat `tests/e2e/categories.spec.js`.

> **Catatan teknis penting:** meskipun link menu "Sales Orders" tidak tampil di sidebar untuk
> Warehouse Staff, sesuai desain sistem (lihat matriks otorisasi SO-01 di `docs/planning/prd.md`)
> Warehouse Staff **tetap perlu membuka halaman detail Sales Order berstatus Approved** untuk
> menjalankan tombol **Process Goods Issue** (`sales_orders.issue`, permission yang memang
> dimiliki role ini) — biasanya diakses lewat tautan langsung dari antrean pengiriman/notifikasi,
> bukan lewat menu utama. Warehouse Staff **tidak** memiliki `sales_orders.approve`, jadi tidak bisa
> menyetujui/menolak SO dalam kondisi apa pun.

## 3. Penjelasan Detail Setiap Fitur

### 3.1 Dashboard (`/dashboard`)
Konten dashboard difokuskan ke **antrean kerja gudang**:
- **PO Receipt Queue** — jumlah Purchase Order berstatus Ordered + PartiallyReceived (barang yang
  masih ditunggu kedatangannya dan perlu diterima).
- **SO Issue Queue** — jumlah Sales Order berstatus Approved (siap dikirim/diproses Goods Issue).
- **Low Stock Count + daftar produk** — sama seperti Admin, produk yang stoknya ≤ reorder point.
- Bel **Notifikasi** stok rendah tampil dan bisa ditandai "sudah dibaca".

### 3.2 Products (`/products`) — Read Only
Melihat katalog produk berikut **stok per gudang** dan status low-stock, untuk menentukan produk
mana yang perlu direstock. Tidak ada tombol tambah/edit/nonaktifkan produk — endpoint terkait akan
menolak (403) karena tidak punya `products.manage`.

### 3.3 Procurement → Purchase Orders (`/purchase-orders`)
- **List & Detail**: sama seperti Admin — bisa melihat semua PO, filter status/pencarian/gudang,
  detail progres penerimaan barang + audit trail Stock Ledger.
- **Create**: Warehouse Staff **boleh mengusulkan/membuat PO baru** sendiri (mis. ketika melihat
  produk berstatus low-stock di dashboard) — pilih supplier, gudang tujuan, dan baris item.
- **Submit** — ❌ **Warehouse Staff tidak bisa submit PO** (Draft → Ordered) sejak
  2026-09-24: sebelumnya submit berbagi permission `purchase_orders.manage` dengan
  create/receive, sehingga Warehouse Staff ikut bisa submit — ini diperbaiki agar
  sesuai `PROJECT_REFERENCE.md` §2.3 PO-01 (Submit hanya untuk Admin). Submit kini
  dijaga permission terpisah `purchase_orders.submit` (Admin-only); tombol "Submit
  Order" tidak tampil untuk role ini, dan endpoint `/purchase-orders/{id}/submit`
  menolak dengan 403 bila diakses langsung.
- **Receive (Goods Receipt)** — **tugas inti role ini**: saat barang fisik tiba, buka PO
  berstatus Ordered/PartiallyReceived → input qty yang benar-benar diterima per baris (boleh
  bertahap/partial, tidak boleh melebihi sisa qty yang dipesan). Sistem otomatis menambah stok
  gudang tujuan dan mencatat baris Stock Ledger (tipe Receipt) dalam satu transaksi.
- **Cancel PO** — ❌ **tidak tersedia untuk Warehouse Staff** (`purchase_orders.cancel` hanya untuk
  Admin). Jika PO perlu dibatalkan, harus diminta ke Admin.

### 3.4 Sales Order — Hanya untuk Proses Goods Issue
- **Tidak muncul di menu**, dan Warehouse Staff **tidak melihat daftar semua SO** sebagai menu utama.
- **Process Goods Issue** (`sales_orders.issue`): untuk SO yang sudah **Approved** oleh Admin, Warehouse
  Staff membuka detail SO tersebut dan memproses pengiriman barang. Sistem memvalidasi stok di gudang
  asal; bila cukup → stok berkurang, Stock Ledger mencatat baris Issue, dan bila seluruh baris item
  sudah terkirim maka status SO otomatis menjadi **Fulfilled**. Bila stok tidak cukup → ditolak dengan
  pesan "Stok tidak mencukupi" tanpa ada perubahan data (SO tetap Approved).
- **Tidak bisa** approve/reject SO maupun membuat SO baru sebagai bagian dari alur kerja standarnya.

### 3.5 Inventory → Stock Ledger (`/stock-ledger`)
Melihat seluruh riwayat pergerakan stok (Receipt dari PO, Issue dari SO) di semua gudang, dengan
filter SKU/nama produk/jenis pergerakan/gudang/tanggal — berguna untuk audit & rekonsiliasi fisik vs sistem.

### 3.6 Reports (`/reports`)
Hanya bisa **Export Stock Ledger (CSV)** untuk semua gudang (`reports.stock_ledger.view`). Warehouse
Staff **tidak bisa** export laporan Sales Order maupun Purchase Order (tidak punya
`reports.sales_orders.view` / `reports.purchase_orders.view`) — bila kedua permission ini sama-sama
tidak dimiliki, akses ke `Export Orders` ditolak sepenuhnya (403).

### 3.7 My Profile (`/my-profile`)
Bisa mengubah `name` dan `email` miliknya sendiri. Tidak ada fitur ganti password mandiri.

## 4. Fitur yang TIDAK Bisa Diakses Warehouse Staff (Ringkasan)

| Fitur | Alasan |
|---|---|
| **Kelola** Master Data (create/edit/deactivate/activate/delete Produk/Kategori/Gudang/Supplier/Pelanggan) | Tidak punya `*.manage` — ditolak 403 di server. **Melihat** daftarnya (read-only) tetap bisa diakses langsung lewat URL — lihat catatan teknis di §2. |
| Submit Purchase Order (Draft → Ordered) | Tidak punya `purchase_orders.submit` (khusus Admin) |
| Membatalkan Purchase Order | Tidak punya `purchase_orders.cancel` (khusus Admin) |
| Approve/Reject Sales Order | Tidak punya `sales_orders.approve` |
| Membuat Sales Order baru | Bukan bagian tugas role ini (menu tidak tersedia) |
| Melihat menu utama daftar Sales Order | Tidak punya `sales_orders.menu` |
| Export Sales Order / Purchase Order | Tidak punya `reports.sales_orders.view` / `reports.purchase_orders.view` |
| Manajemen User | Tidak punya `users.manage` |

## 5. Standard Operating Procedure (SOP)

### SOP-W1 — Login
1. Buka `/login`, masukkan email dan password akun Warehouse Staff yang aktif.
2. Berhasil → diarahkan ke `/dashboard` yang menampilkan antrean penerimaan PO, antrean pengiriman SO,
   dan daftar produk low-stock.

### SOP-W2 — Mengusulkan/Membuat Purchase Order (Restock)
1. Cek daftar produk low-stock di Dashboard atau menu Products.
2. Menu **Procurement → Purchase Orders** → klik buat PO baru.
3. Pilih Supplier dan Gudang Tujuan, tambahkan baris item beserta qty dan harga beli → simpan (status Draft).
4. PO tetap berstatus **Draft** sampai di-submit — Warehouse Staff **tidak bisa** melakukan langkah
   ini sendiri (lihat §3.3); teruskan ke Admin untuk klik **Submit** agar PO resmi dikirim ke supplier
   dan berstatus **Ordered**.

### SOP-W3 — Menerima Barang dari Supplier (Goods Receipt)
1. Menu **Purchase Orders** → filter status **Ordered** / **PartiallyReceived** untuk melihat PO yang barangnya masih ditunggu.
2. Buka detail PO yang barangnya baru tiba → klik **Receive**.
3. Cocokkan fisik barang dengan daftar baris item, isi qty yang **benar-benar diterima** per baris (boleh sebagian jika barang datang bertahap — tidak boleh melebihi sisa qty yang dipesan).
4. Simpan. Sistem otomatis menambah stok di gudang tujuan dan mencatat baris Stock Ledger (Receipt). Status PO otomatis berubah menjadi **PartiallyReceived** (bila belum semua baris lunas) atau **Received** (bila semua baris sudah diterima penuh).
5. Jika ada selisih/kekurangan pengiriman dari supplier, lakukan penerimaan sesuai qty fisik yang benar-benar diterima terlebih dahulu — sisa kekurangan tetap "outstanding" pada PO tersebut (Partial) sampai supplier mengirim sisanya, atau eskalasikan ke Admin bila perlu tindakan lain (mis. pembatalan).

### SOP-W4 — Memproses Pengiriman Barang untuk Sales Order (Goods Issue)
1. Terima informasi Sales Order yang sudah **Approved** dan siap dikirim (mis. dari antrean "SO Issue Queue" di dashboard, atau tautan yang diberikan Admin/Sales).
2. Buka detail Sales Order tersebut.
3. Siapkan barang fisik sesuai daftar item, lalu klik **Process Goods Issue / Ship**.
4. Sistem memvalidasi ketersediaan stok di gudang asal:
   - Jika cukup → stok berkurang otomatis, tercatat di Stock Ledger (tipe Issue), dan status SO menjadi **Fulfilled** bila seluruh baris sudah terkirim.
   - Jika tidak cukup → sistem menolak dengan pesan "Stok tidak mencukupi"; koordinasikan dengan Admin/Sales (mis. kirim sebagian dulu bila memang didukung, atau tunggu restock) — tidak ada data yang berubah pada percobaan yang gagal.

### SOP-W5 — Memantau Stock Ledger untuk Audit/Rekonsiliasi
1. Menu **Inventory → Stock Ledger**.
2. Gunakan filter SKU/nama produk/jenis pergerakan/gudang/tanggal untuk menelusuri histori pergerakan stok tertentu, misalnya saat melakukan stock opname/rekonsiliasi fisik vs sistem.

### SOP-W6 — Mengekspor Laporan Stock Ledger
1. Menu **Reports** → pilih rentang tanggal & filter yang diperlukan.
2. Klik **Export Stock Ledger** → file CSV berisi seluruh pergerakan stok pada gudang yang difilter.

### SOP-W7 — Memperbarui Profil Sendiri
1. Klik nama/avatar di pojok kanan atas → **My Profile** → ubah Nama/Email → Simpan.

## 6. Catatan Penting

- Setiap perubahan stok (Receipt maupun Issue) **selalu tercatat otomatis** di Stock Ledger dalam satu
  transaksi database bersama perubahan `product_stocks` — Warehouse Staff tidak pernah mengedit angka
  stok secara langsung.
- Dua proses Goods Issue yang dijalankan bersamaan pada produk & gudang yang sama tidak akan
  menyebabkan stok menjadi minus (oversell); salah satu proses akan ditolak secara aman oleh sistem.
- Bila PO perlu dibatalkan (mis. supplier gagal kirim sama sekali), Warehouse Staff **tidak bisa**
  melakukannya sendiri — harus meminta Admin untuk membatalkan (dan hanya bisa dibatalkan Admin bila
  belum ada barang yang diterima sama sekali).

# Test Case per Role — Inventory & Order Management System

**Dokumen:** `docs/qa/test-cases-by-role.md`
**Dibuat:** 2026-10-03
**Pelengkap dari:** `docs/qa/qa-plan.md` (98 check, disusun per fitur/stage gate) dan
`docs/roles/README.md` + `ROLE-ADMIN.md` + `ROLE-SALES.md` + `ROLE-WAREHOUSE-STAFF.md`
(sumber kebenaran permission & SOP, diambil dari kode).

## Tujuan & Perbedaan dengan `qa-plan.md`

`qa-plan.md` disusun **per fitur** untuk gate kelulusan Stage 8. Dokumen ini disusun
**per role** (Admin, Sales, Warehouse Staff) — cocok dipakai untuk:
- UAT (User Acceptance Test) bersama pemilik proses per role.
- Regression test terarah saat ada perubahan di satu role tertentu.
- Verifikasi otorisasi silang (role A mencoba mengakses fitur role B).

Setiap test case merujuk ke BR/FR/permission key yang relevan sehingga tetap bisa
ditelusuri balik ke `docs/planning/prd.md` dan kode sumber
(`app/Service/PermissionService.php`, `database/seed.sql` tabel `role_permissions`).

## Lingkungan & Data Uji

```bash
docker compose down -v
docker compose up --build -d
docker compose exec app php database/seed.php
```

| Persona | Role | Email | Password |
|---|---|---|---|
| Rita | Admin | `admin@example.com` | `admin123` |
| Beni | Sales | `sales1@example.com` | `sales123` |
| Grace | Sales (pembanding kepemilikan BR-018) | `sales2@example.com` | `grace123` |
| Wawan | WarehouseStaff | `warehouse@example.com` | `wh123` |

## Legenda

- **Prioritas**: `P0` = kritikal (keamanan/otorisasi/integritas data/ARCH-02, wajib lulus),
  `P1` = fungsional inti, `P2` = sekunder (validasi tambahan/kosmetik laporan).
- **Hasil Diharapkan** memakai status HTTP eksplisit (200/302/403/400/404) bila relevan,
  karena otorisasi di sistem ini **selalu ditegakkan di server**, bukan hanya UI hiding (BR-017).
- Simbol `❌` pada kolom Referensi menandakan test case negatif (membuktikan suatu
  akses **ditolak**, bukan dites agar berhasil).

---

# BAGIAN A — ROLE: ADMIN (Rita)

## A1. Autentikasi & Sesi

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-AUTH-01 | Login Admin dengan kredensial valid | Akun `admin@example.com` aktif | 1) Buka `/login` 2) Isi email & password 3) Submit | `admin@example.com` / `admin123` | Redirect 302 ke `/dashboard`, session dibuat, nama "Rita" tampil di header | P0 | BR-017 |
| ADM-AUTH-02 | Login dengan password salah | — | Isi email benar, password salah → submit | `admin@example.com` / `salahpassword` | Tetap di `/login`, pesan error generik (tidak menyebut "password salah" secara spesifik) | P0 | Keamanan |
| ADM-AUTH-03 | Login dengan akun yang dinonaktifkan | User Admin lain dalam status `is_active=0` | Login dengan akun nonaktif tsb | email nonaktif / password benar | Login ditolak, pesan error generik yang sama seperti kredensial salah (tidak membocorkan status akun) | P0 | ROLE-ADMIN §SOP-A1 |
| ADM-AUTH-04 | Logout menghapus session | Sudah login sebagai Rita | Klik **Logout** | — | Redirect ke `/login`; mengakses `/dashboard` setelahnya → redirect 302 ke `/login` | P0 | BR-017 |
| ADM-AUTH-05 | Akses halaman terproteksi tanpa login | Browser belum login (session kosong) | Buka `/dashboard`, `/users`, `/products` langsung | — | Semua redirect 302 ke `/login` | P0 | BR-017 |

## A2. Dashboard

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-DASH-01 | Inventory Value tampil benar | Login Admin, data stok ada | Buka `/dashboard` | — | Nilai inventory value > 0, sesuai `SUM(quantity * purchase_price)` di DB | P1 | DASH-01 |
| ADM-DASH-02 | Low Stock Count & daftar produk | Ada produk dengan `quantity <= reorder_point` | Buka `/dashboard` | — | Jumlah & daftar produk low-stock sesuai query DB (SKU, nama, stok, reorder point) | P1 | DASH-01 |
| ADM-DASH-03 | PO & SO count per status | — | Buka `/dashboard` | — | Jumlah PO per status (Draft/Ordered/PartiallyReceived/Received/Cancelled) dan SO per status, mencakup **semua Sales** (tidak dibatasi per-user) | P1 | DASH-01, ROLE-ADMIN §3.1 |
| ADM-DASH-04 | Notifikasi low-stock + mark all read | Ada notifikasi belum dibaca | Klik bel notifikasi → **Mark all as read** | — | Badge unread hilang; `POST /notifications/mark-all-read` mengembalikan sukses | P2 | NotificationController |
| ADM-DASH-05 | Dashboard menampilkan data live (tanpa cache) | — | Ubah data (mis. buat PO baru) → refresh `/dashboard` tanpa logout | — | Angka berubah mengikuti perubahan terbaru (tidak stale/cache) | P1 | DASH-01 |

## A3. Administration → Users

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-USER-01 | List & search user | Ada ≥2 user | Buka `/users`, cari by nama/email | — | Tabel user tampil, pencarian memfilter hasil | P1 | `users.manage` |
| ADM-USER-02 | Buat user baru valid | — | `/users/create` → isi Nama, Email unik, Role, Password → simpan | Nama "Dedi", email `dedi@example.com`, role `Sales`, password `dedi123` | User tersimpan, redirect ke list/detail, user baru bisa login | P1 | FR-3.2, ROLE-ADMIN §SOP-A2 |
| ADM-USER-03 | Email duplikat ditolak (case-insensitive) | User `Beni@x... ` sudah ada dgn email `sales1@example.com` | Buat user baru dengan email `SALES1@EXAMPLE.COM` | email kapital dari email existing | Ditolak validasi uniqueness, form tidak tersimpan | P1 | ROLE-ADMIN §3.7 |
| ADM-USER-04 | Role harus salah satu dari 3 pilihan | — | Coba kirim POST `/users` dengan `role=SuperAdmin` (manipulasi request) | role tidak valid | Ditolak validasi (400/error), tidak membuat user dengan role asing | P1 | FR-3.2 |
| ADM-USER-05 | Edit user — kosongkan password tidak mengubah password lama | User existing | Edit user, kosongkan field password → simpan | — | Password lama tetap berlaku untuk login setelah edit | P1 | ROLE-ADMIN §3.7 |
| ADM-USER-06 | Deactivate user lain | User "Dedi" aktif | Klik **Deactivate** pada user Dedi | — | `is_active=0`; Dedi tidak bisa login lagi; riwayat SO/PO Dedi (bila ada) tetap tampil apa adanya | P0 | ROLE-ADMIN §SOP-A3 |
| ADM-USER-07 | Activate kembali user nonaktif | User Dedi nonaktif | Klik **Activate** | — | `is_active=1`; Dedi bisa login kembali | P1 | ROLE-ADMIN §SOP-A3 |
| ADM-USER-08 | Admin tidak bisa menonaktifkan akun sendiri | Login sebagai Rita | Buka profil/detail akun sendiri di `/users/{id}` (id Rita) | — | Tombol **Deactivate** tidak tampil; bila endpoint dipanggil langsung → ditolak (tidak boleh mengunci Admin terakhir) | P0 | ROLE-ADMIN §2 catatan |
| ADM-USER-09 | Admin tidak bisa mengubah role akun sendiri | Login sebagai Rita | Edit akun sendiri, coba ubah Role ke `Sales` | role baru | Ditolak / field role terkunci untuk akun sendiri | P0 | ROLE-ADMIN §2 catatan |

## A4. Master Data → Products

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-PROD-01 | List, search by SKU/nama, filter kategori & gudang | Produk existing | Buka `/products`, gunakan search & filter | — | Hasil terfilter benar, pagination berfungsi | P1 | PRD-01 |
| ADM-PROD-02 | Buat produk baru valid (dengan gambar) | — | `/products/create` → isi semua field wajib + upload gambar < 2MB | SKU unik, harga ≥0, reorder point ≥0 | Produk tersimpan; gambar otomatis dikonversi ke WebP dan tampil di detail | P1 | PRD-01, BR-019 |
| ADM-PROD-03 | SKU duplikat ditolak | Produk dengan SKU `ELEC-001` sudah ada | Buat produk baru dengan SKU `ELEC-001` | SKU duplikat | Validasi gagal, pesan error SKU sudah dipakai | P1 | PRD-01 |
| ADM-PROD-04 | Harga/reorder point negatif ditolak | — | Isi `purchase_price=-10` atau `reorder_point=-1` | nilai negatif | Validasi gagal (field harus ≥0) | P1 | BR-019 |
| ADM-PROD-05 | Upload gambar > 2MB ditolak | — | Upload file gambar berukuran 3MB | file > 2MB | Ditolak dengan pesan error ukuran file | P2 | PRD-01 |
| ADM-PROD-06 | Deactivate produk | Produk aktif tanpa transaksi pending | Klik **Deactivate** pada produk | — | Produk hilang dari list aktif & dari dropdown Create PO/SO; tetap tampil di detail order lama yang sudah memakainya | P1 | ROLE-ADMIN §3.2 |
| ADM-PROD-07 | Activate kembali produk nonaktif | Produk nonaktif | Klik **Activate** | — | Produk muncul lagi di list & dropdown | P1 | ROLE-ADMIN §3.2 |
| ADM-PROD-08 | Produk tidak pernah hard-delete | — | Cek UI/endpoint produk | — | Tidak ada tombol/endpoint "Delete" permanen untuk Produk (hanya deactivate) | P2 | ROLE-ADMIN §3.2 |

## A5. Master Data → Categories / Warehouses / Suppliers / Customers

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-MD-01 | CRUD Category lengkap (create/edit/deactivate/activate) | — | Ulangi pola create→edit→deactivate→activate untuk Category | Nama kategori baru | Setiap langkah sukses sesuai pola Produk | P1 | `categories.manage` |
| ADM-MD-02 | Category: hard delete | Category tanpa produk terkait | `POST /categories/{id}/delete` | — | Category benar-benar terhapus dari DB (berbeda dari deactivate) | P2 | routes.php (`delete` khusus Category) |
| ADM-MD-03 | Category: export CSV | — | `GET /categories/export` | — | File CSV kategori terunduh | P2 | routes.php |
| ADM-MD-04 | CRUD Warehouse lengkap + lihat stok semua gudang | — | create→edit→deactivate→activate Warehouse; buka detail salah satu gudang | Nama/lokasi gudang baru | Operasi sukses; Admin bisa melihat breakdown stok di **semua gudang**, tidak dibatasi 1 lokasi | P1 | `warehouses.manage` |
| ADM-MD-05 | CRUD Supplier lengkap | — | create→edit→deactivate→activate Supplier | Data supplier baru | Operasi sukses | P1 | `suppliers.manage` |
| ADM-MD-06 | CRUD Customer lengkap | — | create→edit→deactivate→activate Customer | Data customer baru | Operasi sukses | P1 | `customers.manage` |

## A6. Procurement → Purchase Orders

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-PO-01 | List PO dengan filter status/gudang/pencarian | ≥1 PO per status | Buka `/purchase-orders`, terapkan filter | — | Hasil terfilter benar sesuai kombinasi filter | P1 | PO-01 |
| ADM-PO-02 | Buat PO baru → status Draft | Supplier & produk aktif tersedia | `/purchase-orders/create` → pilih supplier, gudang tujuan, tambah baris item → simpan | ≥1 baris item, qty & harga beli valid | PO tersimpan dengan status **Draft** | P1 | BR-003 |
| ADM-PO-03 | Submit PO Draft → Ordered | PO status Draft | Buka detail PO → klik **Submit** | — | Status berubah ke **Ordered** | P1 | BR-003, `purchase_orders.submit` |
| ADM-PO-04 | Submit PO yang bukan Draft ditolak | PO status Ordered/Cancelled | Panggil `POST /purchase-orders/{id}/submit` pada PO non-Draft | — | Ditolak (400), status tidak berubah | P1 | BR-003 ❌ |
| ADM-PO-05 | Receive goods — partial | PO status Ordered, qty dipesan 100 | Buka **Receive**, isi qty diterima 60 dari 100 | qty_received=60 | Status → **PartiallyReceived**; `product_stocks` bertambah 60; 1 baris `stock_ledger` tipe Receipt tercatat | P0 | BR-008, BR-015 |
| ADM-PO-06 | Receive goods — full (lanjutan partial) | Lanjutan ADM-PO-05, sisa 40 | Receive lagi sisa 40 | qty_received=40 | Status → **Received**; stok total bertambah 100 (akumulasi); `po_items.qty_received`=100 | P0 | BR-008, BR-015 |
| ADM-PO-07 | Receive melebihi sisa qty ditolak | PO sisa qty_remaining=10 | Input qty diterima = 20 | qty > sisa | Ditolak (400/validasi), **tidak ada perubahan stok maupun ledger** (rollback) | P0 | BR-015 ❌ |
| ADM-PO-08 | Stock ledger invariant setelah Receive | Setelah beberapa kali receive | Query DB: `product_stocks.quantity` vs `SUM(stock_ledger.qty)` untuk produk+gudang terkait | — | Kedua nilai sama persis (konsistensi ledger) | P0 | BR-015 |
| ADM-PO-09 | Cancel PO Draft/Ordered (belum ada receipt) | PO status Draft atau Ordered | Klik **Cancel** | — | Status → **Cancelled** | P1 | `purchase_orders.cancel` |
| ADM-PO-10 | Cancel PO yang sudah PartiallyReceived/Received ditolak | PO sudah pernah menerima barang (sebagian/penuh) | Panggil `POST /purchase-orders/{id}/cancel` | — | Ditolak (400), status tidak berubah menjadi Cancelled | P0 | ROLE-ADMIN §4 ❌ |
| ADM-PO-11 | Detail PO menampilkan Inbound Progress & audit trail | PO dengan histori receive | Buka detail PO | — | Menampilkan qty/nilai dipesan vs diterima, dan semua baris Stock Ledger terkait PO tsb | P2 | ROLE-ADMIN §3.3 |

## A7. Sales → Sales Orders (Approval & Fulfillment)

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-SO-01 | List SO menampilkan SEMUA Sales (tidak dibatasi kepemilikan) | SO dibuat oleh Beni & Grace | Login Rita, buka `/sales-orders` | — | SO milik Beni **dan** Grace sama-sama tampil | P1 | ROLE-ADMIN §3.4 |
| ADM-SO-02 | Admin membuat SO sendiri | — | `/sales-orders/create` → isi pelanggan, gudang asal, item → simpan | — | SO tersimpan status **Draft**, `created_by` = Rita | P2 | FR SO-01 |
| ADM-SO-03 | Approve SO berstatus PendingApproval | SO status PendingApproval (mis. milik Beni) | Buka detail → klik **Approve** | — | Status → **Approved**, `approved_by`/`approved_at` tercatat | P0 | BR-001 |
| ADM-SO-04 | Reject SO berstatus PendingApproval dengan alasan | SO status PendingApproval | Klik **Reject**, isi alasan | alasan penolakan | Status → **Cancelled**, `cancellation_reason` tersimpan | P1 | ROLE-ADMIN §3.4 |
| ADM-SO-05 | Approve/Reject SO yang bukan PendingApproval ditolak | SO status Draft/Approved/Fulfilled/Cancelled | `POST /sales-orders/{id}/approve` pada status tsb | — | Ditolak (400), status tidak berubah | P1 | BR-001 ❌ |
| ADM-SO-06 | **Admin boleh approve SO buatan sendiri** (tidak kena SoD) | SO dibuat Rita sendiri, status PendingApproval | Klik **Approve** pada SO milik sendiri | — | Approve **berhasil** (SoD hanya berlaku untuk role Sales, bukan Admin) | P0 | BR-001, DEC-012 |
| ADM-SO-07 | Admin cancel SO milik Sales lain (Draft/PendingApproval) | SO milik Beni status Draft | Klik **Cancel** pada SO milik Beni | — | Status → **Cancelled** (Admin boleh cancel SO siapa pun selama belum Fulfilled) | P1 | ROLE-ADMIN §3.4 |
| ADM-SO-08 | Cancel SO berstatus Approved ditolak | SO status Approved | `POST /sales-orders/{id}/cancel` | — | Ditolak (400) | P0 | ROLE-ADMIN §4 ❌ |
| ADM-SO-09 | Cancel SO berstatus Fulfilled ditolak (berlaku untuk siapa pun) | SO status Fulfilled | `POST /sales-orders/{id}/cancel` | — | Ditolak (400) — tidak ada role yang bisa cancel SO Fulfilled | P0 | ROLE-ADMIN §4 ❌ |
| ADM-SO-10 | Process Goods Issue — stok cukup | SO status Approved, stok gudang asal mencukupi | Buka detail → klik **Issue/Ship** | — | Stok berkurang sesuai qty; `stock_ledger` tipe Issue tercatat; status SO → **Fulfilled** bila semua baris terkirim | P0 | BR-013, ARCH-02 |
| ADM-SO-11 | Process Goods Issue — stok tidak cukup | SO status Approved, stok < qty diminta | Klik **Issue/Ship** | — | Ditolak dengan pesan "Stok tidak mencukupi"; **tidak ada perubahan apa pun** (SO tetap Approved, stok tetap) | P0 | ARCH-02 ❌ |
| ADM-SO-12 | Issue pada SO yang belum Approved ditolak | SO status Draft/PendingApproval | `POST /sales-orders/{id}/issue` | — | Ditolak (400) | P0 | BR-013 ❌ |

## A8. Inventory → Stock Ledger & A9. Reports

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-STK-01 | Lihat Stock Ledger semua gudang | Ada histori Receipt & Issue | Buka `/stock-ledger` | — | Semua baris pergerakan stok tampil (Receipt & Issue), tidak dibatasi per gudang | P1 | ROLE-ADMIN §3.5 |
| ADM-STK-02 | Filter Stock Ledger (SKU/produk/tipe/gudang/tanggal) | — | Terapkan kombinasi filter | — | Hasil sesuai filter; sort & pagination (25/halaman) berfungsi | P1 | ROLE-ADMIN §3.5 |
| ADM-RPT-01 | Export Stock Ledger CSV | — | `/reports` → **Export Stock Ledger** | rentang tanggal | File CSV terunduh, header EN sesuai kolom (Product, Warehouse, Type, Quantity, ...) | P1 | REPORT-01 |
| ADM-RPT-02 | Export Orders CSV — gabungan semua SO + semua PO | Ada SO milik Beni & Grace, serta PO | **Export Orders** dengan rentang tanggal | tanggal mulai & akhir wajib | CSV berisi SO dari **semua Sales** + **semua PO** dalam rentang tsb | P1 | ROLE-ADMIN §3.6 |
| ADM-RPT-03 | Export Orders tanpa mengisi rentang tanggal ditolak | — | Submit export tanpa tanggal | — | Validasi gagal, export tidak berjalan | P2 | REPORT-01 |
| ADM-RPT-04 | CSV formula-injection di-escape | Data mengandung nilai seperti `=HYPERLINK(...)`, `+5-10`, `=CMD|...` | Export CSV yang memuat data tsb, buka file mentah | nilai berbahaya pada salah satu field teks | Nilai diawali prefix `'` sehingga tidak dieksekusi sebagai formula oleh Excel/Sheets | P0 | Keamanan, OWASP |

## A10. Profile

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| ADM-PROF-01 | Update nama & email sendiri | — | `/my-profile` → ubah nama/email → simpan | nama baru, email baru unik | Perubahan tersimpan, tercermin di header | P2 | ROLE-ADMIN §3.8 |
| ADM-PROF-02 | Tidak ada fitur ganti password mandiri | — | Cek halaman `/my-profile` | — | Tidak ada field/form ganti password di halaman ini | P2 | ROLE-ADMIN §3.8 |

---

# BAGIAN B — ROLE: SALES (Beni)

## B1. Autentikasi

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-AUTH-01 | Login Sales valid | — | Login dengan akun Beni | `sales1@example.com` / `sales123` | Redirect ke `/dashboard`, konten khusus Sales | P0 | ROLE-SALES §SOP-S1 |
| SAL-AUTH-02 | Login dengan password salah | — | Password salah | — | Ditolak, pesan error generik | P0 | Keamanan |
| SAL-AUTH-03 | Logout | Sudah login | Klik Logout | — | Redirect ke `/login`, session berakhir | P0 | BR-017 |

## B2. Dashboard

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-DASH-01 | Dashboard hanya tampilkan ringkasan SO milik sendiri | Beni & Grace sama-sama punya SO | Login Beni, buka `/dashboard` | — | Jumlah SO per status yang ditampilkan **hanya milik Beni**, bukan Grace | P0 | BR-018 |
| SAL-DASH-02 | Dashboard Sales tidak menampilkan data Admin/Warehouse | — | Buka `/dashboard` | — | Tidak ada Inventory Value, Low Stock, PO status, atau bel notifikasi | P1 | ROLE-SALES §3.1 |

## B3. Products (Read-Only)

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-PROD-01 | Lihat katalog produk | — | Buka `/products` | — | HTTP 200, daftar produk tampil (nama, SKU, harga jual, stok tersedia) | P1 | ROLE-SALES §3.2 |
| SAL-PROD-02 | Tombol create/edit/deactivate tidak tampil | — | Buka `/products` | — | Tidak ada tombol Add/Edit/Deactivate di UI | P1 | ROLE-SALES §3.2 |
| SAL-PROD-03 | Akses langsung endpoint create produk ditolak | — | `GET /products/create` langsung via URL | — | HTTP 403 Forbidden (tidak punya `products.manage`) | P0 | ROLE-SALES §3.2 ❌ |

## B4. Sales Orders (Fitur Utama)

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-SO-01 | List SO hanya menampilkan milik sendiri (BR-018) | Grace juga punya SO | Login Beni, buka `/sales-orders` | — | Hanya SO `created_by = Beni` yang tampil | P0 | BR-018 |
| SAL-SO-02 | Buat SO baru valid | Pelanggan & produk aktif tersedia | `/sales-orders/create` → pilih pelanggan, gudang asal, tambah item → simpan | ≥1 item, qty & harga jual valid | SO tersimpan status **Draft**, `created_by` = Beni | P1 | ROLE-SALES §SOP-S2 |
| SAL-SO-03 | Validasi field wajib saat create SO | — | Submit form tanpa pelanggan/tanpa item | field kosong | Validasi gagal, form tidak tersimpan | P2 | ROLE-SALES §3.3 |
| SAL-SO-04 | Lihat detail SO milik sendiri | SO milik Beni | Buka `/sales-orders/{id}` milik sendiri | — | HTTP 200, detail tampil lengkap | P1 | ROLE-SALES §3.3 |
| SAL-SO-05 | **Akses detail SO milik Sales lain ditolak** (manipulasi URL/ID) | SO milik Grace diketahui ID-nya | Login Beni, buka `/sales-orders/{id-milik-Grace}` langsung | ID SO Grace | **HTTP 403 Forbidden** | P0 | BR-018 ❌ |
| SAL-SO-06 | Submit SO Draft → PendingApproval | SO milik sendiri, status Draft | Buka detail → klik **Submit for Approval** | — | Status → **PendingApproval** | P1 | ROLE-SALES §SOP-S3 |
| SAL-SO-07 | Cancel SO milik sendiri saat Draft | SO status Draft | Klik **Cancel** | — | Status → **Cancelled** | P1 | ROLE-SALES §SOP-S4 |
| SAL-SO-08 | Cancel SO milik sendiri saat PendingApproval | SO status PendingApproval | Klik **Cancel** | — | Status → **Cancelled** | P1 | ROLE-SALES §SOP-S4 |
| SAL-SO-09 | Cancel SO milik sendiri yang sudah Approved ditolak | SO status Approved | `POST /sales-orders/{id}/cancel` | — | Ditolak (400) — harus minta Admin | P0 | ROLE-SALES §SOP-S4 ❌ |
| SAL-SO-10 | **Sales mencoba Approve SO (bahkan milik sendiri) ditolak — SoD** | SO milik Beni sendiri, status PendingApproval | `POST /sales-orders/{id}/approve` langsung (bypass UI, tombol memang tidak ada) | — | **HTTP 403 Forbidden**, pesan "Only an administrator can approve a sales order." | P0 | **BR-001 ❌ (kritikal)** |
| SAL-SO-11 | Sales mencoba Reject SO ditolak — SoD | SO PendingApproval | `POST /sales-orders/{id}/reject` langsung | — | HTTP 403 Forbidden | P0 | BR-001 ❌ |
| SAL-SO-12 | Sales mencoba Process Goods Issue ditolak | SO status Approved | `POST /sales-orders/{id}/issue` langsung | — | HTTP 403 Forbidden (tidak punya `sales_orders.issue`) | P0 | ❌ |
| SAL-SO-13 | SO ditolak Admin → harus buat SO baru | SO berstatus Cancelled hasil reject Admin, ada `cancellation_reason` | Cek SO tsb, cari opsi "kirim ulang" | — | Tidak ada tombol resubmit; alasan penolakan tampil di detail; Sales harus membuat SO baru | P2 | ROLE-SALES §SOP-S5 |

## B5. Reports

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-RPT-01 | Export SO CSV hanya berisi milik sendiri | Beni & Grace sama-sama punya SO | `/reports` → **Export Orders**, rentang tanggal | tanggal | CSV hanya berisi SO `created_by = Beni`, tidak ada SO Grace maupun data PO | P0 | BR-018 |
| SAL-RPT-02 | Export Stock Ledger ditolak | — | `POST /reports/export/stock-ledger` langsung | — | HTTP 403 (tidak punya `reports.stock_ledger.view`) | P0 | ❌ |
| SAL-RPT-03 | Opsi export Stock Ledger/PO tidak tampil di UI Reports | — | Buka `/reports` | — | Hanya form Export Orders (SO) yang tersedia | P1 | ROLE-SALES §3.4 |

## B6. Profile & Akses yang Ditolak (Negative Access)

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| SAL-PROF-01 | Update nama/email sendiri | — | `/my-profile` → ubah → simpan | data baru | Tersimpan | P2 | ROLE-SALES §3.5 |
| SAL-NEG-01 | Akses `/users` ditolak | — | Buka `/users` | — | HTTP 403 | P0 | ❌ |
| SAL-NEG-02 | Akses `/purchase-orders` (apapun action) ditolak | — | Buka `/purchase-orders` dan `/purchase-orders/create` | — | HTTP 403 (tidak punya `purchase_orders.manage`) | P0 | ❌ |
| SAL-NEG-03 | Akses `/stock-ledger` ditolak | — | Buka `/stock-ledger` | — | HTTP 403 (tidak punya `stock_ledger.view`) | P0 | ❌ |
| SAL-NEG-04 | Lihat (read-only) `/categories`, `/warehouses`, `/suppliers` tetap bisa diakses | — | Buka ketiga URL langsung | — | HTTP 200, read-only, **tanpa** tombol Add/Edit/Delete (bukan 403 — sesuai desain `master_data.menu` hanya kontrol sidebar) | P1 | docs/roles/README §2 |
| SAL-NEG-05 | Aksi kelola master data (create/update/deactivate) tetap ditolak walau list bisa dilihat | — | `POST /categories`, `POST /warehouses/{id}/update`, dsb. langsung | — | HTTP 403 pada setiap aksi tulis (tidak punya `*.manage`) | P0 | ❌ |
| SAL-NEG-06 | Customers — list & detail ditolak (pengecualian dari SAL-NEG-04) | — | Buka `/customers` | — | **HTTP 403** (dijaga `customers.view` di server, beda dari 3 modul master data lain) | P0 | docs/roles/README §2 ❌ |

---

# BAGIAN C — ROLE: WAREHOUSE STAFF (Wawan)

## C1. Autentikasi & C2. Dashboard

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-AUTH-01 | Login Warehouse Staff valid | — | Login dengan akun Wawan | `warehouse@example.com` / `wh123` | Redirect ke `/dashboard` | P0 | ROLE-WAREHOUSE §SOP-W1 |
| WH-AUTH-02 | Login password salah | — | Password salah | — | Ditolak, pesan generik | P0 | Keamanan |
| WH-DASH-01 | PO Receipt Queue (Ordered + PartiallyReceived) | Ada PO status tsb | Buka `/dashboard` | — | Jumlah sesuai `COUNT(status IN (Ordered, PartiallyReceived))` | P1 | ROLE-WAREHOUSE §3.1 |
| WH-DASH-02 | SO Issue Queue (Approved) | Ada SO Approved | Buka `/dashboard` | — | Jumlah sesuai `COUNT(status = Approved)` | P1 | ROLE-WAREHOUSE §3.1 |
| WH-DASH-03 | Low Stock Count + bel notifikasi tampil | Produk ≤ reorder point | Buka `/dashboard` | — | Daftar low-stock tampil; bel notifikasi terlihat (tidak seperti Sales) | P1 | ROLE-WAREHOUSE §3.1 |

## C3. Products (Read-Only)

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-PROD-01 | Lihat katalog produk + stok per gudang | — | Buka `/products`, buka detail salah satu | — | Stok per gudang & status low-stock tampil | P1 | ROLE-WAREHOUSE §3.2 |
| WH-PROD-02 | Endpoint create/edit produk ditolak | — | `GET /products/create` langsung | — | HTTP 403 | P0 | ❌ |

## C4. Procurement → Purchase Orders

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-PO-01 | Lihat semua PO (tidak dibatasi pembuat) | PO dibuat Admin & Wawan sendiri | Buka `/purchase-orders` | — | Semua PO tampil, filter status/gudang berfungsi | P1 | ROLE-WAREHOUSE §3.3 |
| WH-PO-02 | Buat/usulkan PO baru | Supplier & produk aktif tersedia | `/purchase-orders/create` → isi → simpan | ≥1 item | PO tersimpan status **Draft**, `created_by` = Wawan | P1 | ROLE-WAREHOUSE §SOP-W2 |
| WH-PO-03 | **Submit PO ditolak** (Admin-only sejak 2026-09-24) | PO status Draft (milik Wawan sendiri) | `POST /purchase-orders/{id}/submit` langsung | — | **HTTP 403 Forbidden** (tidak punya `purchase_orders.submit`) | P0 | PO-01 ❌ |
| WH-PO-04 | Receive goods — partial | PO status Ordered | Buka **Receive**, isi qty sebagian | qty < qty_ordered | Status → **PartiallyReceived**; stok & ledger tercatat dalam 1 transaksi | P0 | BR-008, BR-015 |
| WH-PO-05 | Receive goods — full | PO status Ordered/PartiallyReceived, sisa penuh | Isi qty = sisa qty | qty = qty_remaining | Status → **Received** | P0 | BR-008, BR-015 |
| WH-PO-06 | Receive melebihi sisa qty ditolak | — | Isi qty diterima > qty_remaining | qty berlebih | Ditolak, tidak ada perubahan stok/ledger | P0 | BR-015 ❌ |
| WH-PO-07 | **Cancel PO ditolak** (Admin-only) | PO status Draft/Ordered | `POST /purchase-orders/{id}/cancel` langsung | — | **HTTP 403 Forbidden** (tidak punya `purchase_orders.cancel`) | P0 | ❌ |
| WH-PO-08 | Receive pada PO status Draft/Cancelled/Received ditolak | PO bukan Ordered/PartiallyReceived | Buka halaman **Receive** / submit receive | — | Ditolak (400) — hanya Ordered/PartiallyReceived yang bisa menerima barang | P1 | ROLE-WAREHOUSE §3.3 ❌ |

## C5. Sales Order — Goods Issue Saja

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-SO-01 | Menu "Sales Orders" tidak tampil di sidebar | — | Cek sidebar setelah login Wawan | — | Tidak ada link menu utama Sales Orders | P2 | ROLE-WAREHOUSE §2 |
| WH-SO-02 | Buka detail SO Approved via tautan langsung | SO status Approved, ID diketahui (mis. dari notifikasi) | Buka `/sales-orders/{id}` langsung | — | HTTP 200, detail tampil dengan tombol **Process Goods Issue** | P1 | ROLE-WAREHOUSE §3.4 |
| WH-SO-03 | Process Goods Issue — stok cukup | SO Approved, stok gudang asal cukup | Klik **Process Goods Issue** | — | Stok berkurang, `stock_ledger` tipe Issue tercatat, status → **Fulfilled** bila semua baris terkirim | P0 | ARCH-02 |
| WH-SO-04 | Process Goods Issue — stok tidak cukup | SO Approved, stok < qty | Klik **Process Goods Issue** | — | Ditolak "Stok tidak mencukupi", tidak ada perubahan data | P0 | ARCH-02 ❌ |
| WH-SO-05 | Issue pada SO yang belum Approved ditolak | SO Draft/PendingApproval | `POST /sales-orders/{id}/issue` langsung | — | HTTP 400/403 | P0 | ❌ |
| WH-SO-06 | **Warehouse Staff mencoba Approve/Reject SO ditolak** | SO PendingApproval | `POST /sales-orders/{id}/approve` dan `/reject` langsung | — | HTTP 403 Forbidden (tidak punya `sales_orders.approve`) | P0 | ❌ |
| WH-SO-07 | Warehouse Staff mencoba membuat SO baru ditolak | — | `GET /sales-orders/create` langsung | — | HTTP 403 (tidak punya `sales_orders.menu`) | P0 | ❌ |

## C6. Stock Ledger & C7. Reports

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-STK-01 | Lihat Stock Ledger semua gudang + filter | Ada histori Receipt & Issue | Buka `/stock-ledger`, terapkan filter | — | Semua pergerakan stok tampil, filter berfungsi | P1 | ROLE-WAREHOUSE §3.5 |
| WH-RPT-01 | Export Stock Ledger CSV | — | `/reports` → **Export Stock Ledger** | rentang tanggal | File CSV terunduh untuk semua gudang | P1 | ROLE-WAREHOUSE §3.6 |
| WH-RPT-02 | Export Orders (SO/PO) ditolak sepenuhnya | — | `POST /reports/export/orders` langsung | — | HTTP 403 (tidak punya `reports.sales_orders.view` maupun `reports.purchase_orders.view`) | P0 | ROLE-WAREHOUSE §3.6 ❌ |

## C8. Profile & Akses yang Ditolak

| ID | Test Case | Prasyarat | Langkah Pengujian | Data Uji | Hasil Diharapkan | Prioritas | Referensi |
|---|---|---|---|---|---|---|---|
| WH-PROF-01 | Update nama/email sendiri | — | `/my-profile` → ubah → simpan | data baru | Tersimpan | P2 | ROLE-WAREHOUSE §3.7 |
| WH-NEG-01 | Akses `/users` ditolak | — | Buka `/users` | — | HTTP 403 | P0 | ❌ |
| WH-NEG-02 | Lihat (read-only) `/categories`, `/warehouses`, `/suppliers`, `/customers` tetap bisa diakses | — | Buka ke-4 URL langsung | — | HTTP 200, read-only tanpa tombol kelola (termasuk `/customers` — WarehouseStaff **juga** tidak punya `customers.view`, verifikasi apakah ini 200 read-only atau 403; lihat catatan di bawah) | P1 | docs/roles/README §2 |
| WH-NEG-03 | Aksi kelola master data ditolak | — | `POST /categories`, `/warehouses/{id}/update`, dsb. | — | HTTP 403 pada semua aksi tulis | P0 | ❌ |

> **Catatan untuk WH-NEG-02:** permission matrix di `docs/roles/README.md` §2 mencantumkan
> `customers.view` hanya `✅` untuk Admin & Sales (❌ untuk WarehouseStaff), berbeda dari
> Categories/Warehouses/Suppliers yang "lihat" tidak butuh permission key sama sekali. Perlu
> verifikasi eksplisit saat eksekusi: WarehouseStaff membuka `/customers` kemungkinan mendapat
> **403**, bukan 200 read-only seperti 3 modul master data lainnya — catat hasil aktual sebagai
> bukti, karena ini satu-satunya baris di tabel permission yang polanya berbeda untuk role ini juga.

---

# BAGIAN D — LINTAS ROLE / SYSTEM-LEVEL

Pengujian yang sifatnya lintas role atau level sistem (tidak spesifik satu role), tetap wajib
dieksekusi karena berisi aturan bisnis paling kritikal (ARCH-02, Segregation of Duties, keamanan).

## D1. Matriks Segregation of Duties (Konsolidasi BR-001)

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-SOD-01 | Sales approve SO milik sendiri | Login Beni, approve SO Draft/PendingApproval miliknya sendiri | **Ditolak 403** — role denial, bukan cek kepemilikan | P0 |
| SYS-SOD-02 | Sales approve SO milik Sales lain | Login Beni, approve SO milik Grace | **Ditolak 403** | P0 |
| SYS-SOD-03 | Admin approve SO milik sendiri | Login Rita, approve SO yang dibuat Rita sendiri | **Berhasil** — Admin dikecualikan dari SoD | P0 |
| SYS-SOD-04 | Admin approve SO milik Sales lain | Login Rita, approve SO Beni | **Berhasil** | P1 |
| SYS-SOD-05 | WarehouseStaff approve SO (apapun) | Login Wawan, `POST /sales-orders/{id}/approve` | **Ditolak 403** | P0 |

## D2. ARCH-02 — Concurrency / Anti-Oversell pada Goods Issue (Kritikal)

> Jalankan test otomatis di dalam container (butuh MySQL asli):
> `docker compose exec app vendor/bin/phpunit --testsuite Integration tests/Integration/ARCH02ConcurrencyTest.php`

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-ARCH02-01 | Test integrasi otomatis ARCH-02 lulus | Jalankan `ARCH02ConcurrencyTest.php` | Semua assertion hijau | P0 |
| SYS-ARCH02-02 | Dua request Goods Issue bersamaan pada SO/produk/gudang yang sama, stok hanya cukup untuk satu | Jalankan kedua request paralel (test harness) | Request pertama **sukses** (Fulfilled), request kedua **gagal** (`InsufficientStockException`, lock timeout) | P0 |
| SYS-ARCH02-03 | Stok tidak pernah negatif setelah percobaan konkuren | Query `product_stocks.quantity` setelah SYS-ARCH02-02 | `quantity >= 0` | P0 |
| SYS-ARCH02-04 | Hanya 1 baris `stock_ledger` Issue per SO tercatat | Query DB setelah SYS-ARCH02-02 | `COUNT(*) = 1` | P0 |
| SYS-ARCH02-05 | Simulasi manual via browser: issue habiskan stok → issue lagi | Dua tab/sesi berbeda melakukan Issue berurutan cepat pada SO yang sama | Permintaan kedua mendapat error "Stok tidak mencukupi" | P1 |

## D3. Keamanan (CSRF, Injection, Session)

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-SEC-01 | POST tanpa CSRF token ditolak | Kirim POST ke endpoint write (mis. `/products`) tanpa field `_csrf_token` | Ditolak (400/403) | P0 |
| SYS-SEC-02 | POST dengan CSRF token yang dimanipulasi ditolak | Ubah/acak nilai `_csrf_token` | Ditolak — `hash_equals` gagal | P0 |
| SYS-SEC-03 | SQL injection pada field pencarian | Input `' OR 1=1 --` pada search SKU/nama | Diperlakukan sebagai string literal, tidak mengubah query (prepared statement) | P0 |
| SYS-SEC-04 | Tidak ada `new PDO()` di luar Repository/Container | Code review `app/Service/*.php` | Semua akses DB lewat Repository Interface, bukan `new PDO()` langsung | P0 |
| SYS-SEC-05 | Semua query parameterized | Code review `app/Repository/MySQL/*.php` | Tidak ada concatenation string user-input ke SQL | P0 |
| SYS-SEC-06 | Output di-escape (anti-XSS) | Input nama produk `<script>alert(1)</script>` → cek render di halaman list | Tampil sebagai teks literal (ter-`htmlspecialchars`), tidak dieksekusi | P0 |
| SYS-SEC-07 | Session cookie `HttpOnly` + `Secure` | DevTools → Application → Cookies setelah login | Kedua flag aktif | P1 |
| SYS-SEC-08 | Tidak ada secret hardcoded | Review `.env` / config | Tidak ada password/API key tertanam di source | P1 |

## D4. API Product Availability

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-API-01 | SKU valid, user terautentikasi | `GET /api/products/{sku}/availability` dengan session login | HTTP 200, JSON `{sku, name, total_quantity, warehouses}` | P1 |
| SYS-API-02 | SKU tidak ditemukan | `GET /api/products/NOTEXIST/availability` | HTTP 404, `{"error":"not_found", ...}` (JSON, bukan halaman 404 HTML) | P1 |
| SYS-API-03 | Tanpa autentikasi | Request tanpa session/cookie | HTTP 401, `{"error":"unauthenticated", ...}` | P0 |
| SYS-API-04 | Format SKU tidak valid (melanggar constraint regex) | `GET /api/products/../../etc/availability` atau SKU dengan karakter aneh | HTTP 400, `{"error":"bad_request", ...}` | P1 |
| SYS-API-05 | `total_quantity` konsisten dengan DB | Bandingkan response dengan `SUM(product_stocks.quantity)` untuk SKU tsb | Sama persis | P1 |

## D5. Low-Stock CLI Job

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-JOB-01 | Script berjalan tanpa error | `php scripts/check-low-stock.php` | Exit code 0 | P1 |
| SYS-JOB-02 | Produk ≤ reorder point ditampilkan | Jalankan dengan data yang memiliki produk low-stock | Produk tsb muncul di output | P1 |
| SYS-JOB-03 | Produk di atas reorder point tidak muncul | — | Tidak ada di output | P1 |
| SYS-JOB-04 | Konsisten saat dijalankan via Docker | `docker compose exec app php scripts/check-low-stock.php` | Output sama dengan eksekusi lokal | P2 |

## D6. Konvensi "No Query String" (Filter/Sort/Pagination)

| ID | Test Case | Langkah Pengujian | Hasil Diharapkan | Prioritas |
|---|---|---|---|---|
| SYS-QS-01 | Filter/sort/pagination tidak mengubah URL | Di halaman list manapun (Products/PO/SO/Stock Ledger/Users), terapkan filter & pindah halaman | URL tetap bersih (tanpa query string `?page=...&sort=...`), state dikirim via `POST .../search` | P2 |
| SYS-QS-02 | Refresh halaman tidak kehilangan proteksi CSRF pada form filter | Submit filter berulang kali | Tidak ada error CSRF palsu akibat token stale pada form filter | P2 |

## D7. Cross-Flow End-to-End (Happy Path Lintas Role)

> Skenario lengkap dari login sampai SO Fulfilled, melibatkan ketiga role berurutan.

```
Rita (Admin)  : buat Produk baru → buat PO → terima barang (stok bertambah)
Beni (Sales)  : buat SO untuk pelanggan → submit for approval
Rita (Admin)  : approve SO
Wawan (WH)    : process Goods Issue → SO Fulfilled
```

| ID | Test Case | Hasil Diharapkan | Prioritas |
|---|---|---|---|
| SYS-E2E-01 | Stok akhir = stok awal − qty issued | Query DB cocok dengan perhitungan manual | P0 |
| SYS-E2E-02 | `stock_ledger` punya 1 baris Receipt (dari PO) dan 1 baris Issue (dari SO) untuk produk tsb | Kedua baris ada dan nilainya benar | P0 |
| SYS-E2E-03 | Status akhir SO = Fulfilled, status akhir PO = Received | Kedua status sesuai | P0 |

---

# Ringkasan Jumlah Test Case

| Bagian | Jumlah TC |
|---|---|
| A. Admin | 64 |
| B. Sales | 31 |
| C. Warehouse Staff | 29 |
| D. Lintas Role / System-Level | 32 |
| **Total** | **156** |

# Traceability ke Test Otomatis yang Sudah Ada

Jangan jalankan ulang manual untuk hal yang sudah dicover test otomatis berikut — gunakan hasil
jalannya sebagai bukti, fokuskan waktu manual pada UI/UX dan skenario yang belum ter-cover:

| Area | Test Otomatis |
|---|---|
| SoD / BR-001 | `tests/Unit/SalesOrderPolicyTest.php`, `tests/Integration/SalesOrderApprovalPolicyTest.php`, `tests/Integration/BR001SegregationTest.php` |
| ARCH-02 Concurrency | `tests/Integration/ARCH02ConcurrencyTest.php`, `tests/Integration/GoodsIssueConcurrencyTest.php` |
| Goods Receipt | `tests/Integration/GoodsReceiptTest.php`, `tests/Unit/GoodsReceiptServiceTest.php` |
| Permission matrix | `tests/Unit/PermissionServiceTest.php` |
| Auth | `tests/Unit/AuthServiceTest.php` |
| CSV export (injection escape) | `tests/Unit/CsvExportServiceTest.php` |
| User creation | `tests/Integration/UserCreationTest.php` |
| Dashboard data | `tests/Unit/DashboardServiceTest.php` |
| E2E per modul (browser) | `tests/e2e/*.spec.js` (dashboard, suppliers, customers, purchase-orders, sales-orders, users, warehouses, categories, stock-ledger, reports, csv-export, no-query-string, responsive, regressions, crawl) |

# Catatan Eksekusi

1. Jalankan `docker compose down -v && docker compose up --build -d` sebelum siklus pengujian
   baru agar data konsisten dengan tabel kredensial di atas.
2. Semua test case dengan Prioritas `P0` **wajib PASS 100%** sebelum rilis (zero tolerance untuk
   kegagalan kritikal, konsisten dengan `qa-plan.md`).
3. Setiap kegagalan dicatat memakai **Bug Report Template** yang sama seperti di `qa-plan.md`
   (Severity, Section/ID test case, Steps to reproduce, Expected, Actual, Evidence, Root cause, Fix).
4. ID test case di dokumen ini (`ADM-*`, `SAL-*`, `WH-*`, `SYS-*`) independen dari penomoran di
   `qa-plan.md` (`#1.1`, dst.) — jika memakai keduanya bersamaan, catat kedua ID pada laporan bug
   agar mudah ditelusuri dari dua arah.

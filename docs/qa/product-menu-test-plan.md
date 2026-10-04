# Test Plan — Menu Products

**Dokumen:** `docs/qa/product-menu-test-plan.md`
**Modul:** Master Data → Products (`/products`)
**Acuan:** `PROJECT_REFERENCE.md` — §1.2 (Matriks Hak Akses), §2.2 PRD-01 (Produk), §2.2 WH-01 (Stok Multi-Gudang), §2.5 FIND-01 (Search/Filter/Pagination), §2.7 VAL-01/ERR-01 (Validasi & Error Handling)
**Related:** `docs/qa/qa-plan.md` §2 (versi ringkas, sudah digantikan detailnya oleh dokumen ini)

---

## 1. Ruang Lingkup

Menu Products mencakup:

| Area | Deskripsi |
|---|---|
| Daftar Produk | Tabel produk dengan KPI bar (Total/Normal/Low/Out of Stock), filter, pagination |
| Filter & Pencarian | Product SKU, Product Name, Category (multi-select), Stock Status — POST-based (§2), 2 field per baris |
| Tambah Produk | Form create — SKU, nama, kategori, unit, harga beli/jual, reorder point, gambar (opsional) |
| Edit Produk | Form edit — field sama seperti create, gambar dapat diganti |
| Activate / Deactivate | Toggle status aktif (soft delete — PRD-DATA-02) via AJAX |
| Row Actions | Menu dropdown per baris (View / Edit / Activate-Deactivate) — 1 tombol trigger per baris |

**Di luar ruang lingkup dokumen ini:** detail halaman Product Detail (`/products/{id}`), API `/api/products/{sku}/availability` (lihat API-01 di `PROJECT_REFERENCE.md`).

---

## 2. Matriks Hak Akses (User Access Matrix)

Ditegakkan di **server-side** (`ProductController`), bukan hanya UI hiding (BR-017):

| Aksi | Admin | Sales | Warehouse Staff | Enforcement |
|---|:---:|:---:|:---:|---|
| Lihat daftar produk (`GET /products`) | ✅ | ✅ | ✅ | `requireAuth()` — semua role login bisa lihat |
| Lihat detail produk (`GET /products/{id}`) | ✅ | ✅ | ✅ | `requireAuth()` |
| Filter / cari / paginasi | ✅ | ✅ | ✅ | Sama seperti lihat daftar |
| Tambah produk (`GET/POST /products/create`, `/products`) | ✅ | ❌ 403 | ❌ 403 | `requirePermission('products.manage')` |
| Edit produk (`GET/POST /products/{id}/edit`, `/update`) | ✅ | ❌ 403 | ❌ 403 | `requirePermission('products.manage')` + CSRF |
| Activate / Deactivate produk | ✅ | ❌ 403 | ❌ 403 | `requirePermissionWithCsrf('products.manage')` |
| Upload gambar produk | ✅ | ❌ 403 | ❌ 403 | Sama seperti Tambah/Edit (bagian dari form yang sama) |

**Catatan penting:**
- `products.manage` adalah satu-satunya permission key yang mengatur Products (lihat `database/seed.sql` → `role_permissions`) — hanya `Admin` yang memilikinya.
- Tombol **Add Product**, **Edit**, dan **Activate/Deactivate** di UI disembunyikan untuk non-Admin (`$isAdmin` check di `views/master/products/list.php`), **tapi** ini bukan authorization — kalau non-Admin memanggil endpoint-nya langsung (curl/Postman/browser devtools), server **tetap menolak dengan 403**, bukan mengizinkan. Ini yang diuji di TC-ACL-* di bawah.
- Tidak ada hard-delete untuk Product — hanya Activate/Deactivate (PRD-DATA-02 di `PROJECT_REFERENCE.md`).

---

## 3. Diagram Alur (Flow Diagram)

### 3.1 Alur Utama — Lihat & Filter Produk (semua role)

```
Login
  │
  ▼
Sidebar → "Products"  (GET /products)
  │
  ▼
requireAuth() ──── belum login ───▶ 302 redirect → /login
  │ (login OK)
  ▼
Tampilkan:
  - KPI bar (Total / Normal / Low / Out of Stock SKU)
  - Form filter: Product SKU | Product Name  (baris 1, 2 kolom)
                 Category    | Stock Status  (baris 2, 2 kolom)
  - Tabel produk (10 baris / halaman) + kolom Row Actions (⋮)
  │
  ├──▶ [Isi filter] → Search (POST /products/search)
  │        │
  │        ▼
  │     Tabel ter-filter, filter tetap terisi (tidak hilang saat pindah halaman)
  │        │
  │        ├──▶ [Klik Reset] → GET /products (filter bersih)
  │        └──▶ [Klik halaman 2] → POST /products/search (page=2, filter dipertahankan)
  │
  └──▶ [Klik ⋮ di satu baris] → Dropdown terbuka:
           - View          (semua role)
           - Edit          (Admin saja, hidden utk non-Admin)
           - Activate/Deactivate (Admin saja, hidden utk non-Admin)
```

### 3.2 Alur Tambah / Edit Produk (Admin)

```
Admin klik "Add Product" (atau Edit dari row-actions menu)
  │
  ▼
requirePermission('products.manage') ──── bukan Admin ───▶ 403 Forbidden (styled error page)
  │ (Admin OK)
  ▼
Form Create/Edit Produk ditampilkan
  │
  ▼
Isi field (SKU, nama, kategori, unit, harga, reorder point, [gambar opsional])
  │
  ▼
Submit (POST /products  atau  POST /products/{id}/update)
  │
  ▼
requirePermissionWithCsrf('products.manage') ──── CSRF invalid ───▶ 400 Bad Request (styled error page)
  │ (CSRF OK)
  ▼
Ada file gambar? ──── ya ──▶ ImageUploadService::process()
  │                              │
  │                              ├── gagal (tipe salah / >2MB / MinIO error)
  │                              │      │
  │                              │      ▼
  │                              │   Result::CODE_VALIDATION
  │                              │      │
  │                              │      ▼
  │                              │   Form di-render ULANG dengan:
  │                              │     - Banner error inline
  │                              │     - Popup App.report('failure', ...) [FIX: dulu raw JSON, sekarang styled]
  │                              │     - Input yang sudah diisi TETAP ada ($old)
  │                              │      │
  │                              │      ▼
  │                              │   [User perbaiki gambar] ──▶ kembali ke Submit
  │                              │
  │                              └── berhasil → lanjut
  │ (tidak ada file / upload sukses)
  ▼
ProductService::createProduct() / updateProduct()
  │
  ├── validasi gagal (SKU duplikat, field kosong, harga < 0, dst.)
  │      │
  │      ▼
  │   Form di-render ulang dengan error + input dipertahankan (VAL-01.07/08)
  │
  └── sukses
         │
         ▼
      Redirect → /products (produk baru/terupdate muncul di daftar)
```

### 3.3 Alur Activate / Deactivate (AJAX)

```
Admin klik ⋮ → klik "Deactivate" (atau "Activate")
  │
  ▼
JS: tombol disabled, teks "…"
  │
  ▼
fetch POST /products/{id}/deactivate (atau /activate) + CSRF token
  │
  ├── sukses → toast sukses → reload halaman → badge status berubah
  ├── gagal (validasi/403) → toast error → tombol kembali aktif
  └── exception jaringan → catch → toast error → tombol kembali aktif
        (tombol TIDAK PERNAH stuck di "…" selamanya — try/catch/finally)
```

---

## 4. Test Cases (Alur Pengecekan)

Format: **TC-ID** | Role penguji | Precondition | Steps | Expected Result | Referensi

### 4.1 Daftar, Filter & Pagination

| TC-ID | Role | Precondition | Steps | Expected Result | Ref |
|---|---|---|---|---|---|
| TC-PRD-01 | Semua role | Login sukses | Buka `/products` | Daftar produk tampil, KPI bar terisi angka agregat (bukan hardcoded) | VIEW-01, FIND-01 |
| TC-PRD-02 | Semua role | ≥1 produk ada | Isi field **Product SKU** dengan SKU valid → Search | Hanya baris dengan SKU tsb yang tampil | FIND-01.01 |
| TC-PRD-03 | Semua role | — | Isi field **Product Name** (partial match) → Search | Hasil ter-filter sesuai nama (case-insensitive, partial) | FIND-01.01 |
| TC-PRD-04 | Semua role | ≥1 kategori ada | Pilih 1+ **Category** di multi-select → Search | Hanya produk di kategori terpilih yang tampil | FIND-01.02 |
| TC-PRD-05 | Semua role | — | Pilih **Stock Status** = "Low Stock" → Search | Hanya produk dengan stok ≤ reorder point yang tampil | FIND-01.03 |
| TC-PRD-06 | Semua role | Filter sedang aktif | Klik **Reset** | Semua filter kosong, daftar kembali menampilkan semua produk | FIND-01 |
| TC-PRD-07 | Semua role | Filter aktif, hasil > 10 | Klik halaman 2 di pagination | Filter tetap terisi di form, data halaman 2 yang tampil (bukan reset ke halaman 1 kosong filter) | FIND-01.08 |
| TC-PRD-08 | Semua role | — | Ganti **Rows per page** ke 25/50/100 | Jumlah baris per halaman berubah, filter aktif tetap dipertahankan | FIND-01 |
| TC-PRD-09 | Semua role | Filter tidak match apa pun | Isi SKU yang tidak ada → Search | Empty state: *"No products match your search or filter criteria."* — bukan halaman kosong/error | VIEW-01.03 |
| TC-PRD-10 | Semua role | — | Cek URL browser setelah Search/pindah halaman | URL tetap `/products` — parameter filter **tidak muncul** di address bar (POST-based) | (Perbaikan sesi ini) |

### 4.2 Row Actions Dropdown

| TC-ID | Role | Precondition | Steps | Expected Result | Ref |
|---|---|---|---|---|---|
| TC-PRD-11 | Semua role | Daftar tampil | Klik ikon ⋮ di satu baris | Dropdown terbuka: **View** (semua role); **Edit**, **Activate/Deactivate** (Admin saja) | Row-actions component |
| TC-PRD-12 | Admin | Dropdown terbuka | Klik di luar dropdown | Dropdown tertutup | Row-actions JS |
| TC-PRD-13 | Admin | Dropdown terbuka | Tekan `Escape` | Dropdown tertutup | Row-actions JS |
| TC-PRD-14 | Admin | Buka dropdown di baris terakhir tabel (dekat footer) | Klik ⋮ | Menu tetap terlihat penuh (tidak terpotong), posisi otomatis membalik ke atas jika perlu | `position: fixed` fix |
| TC-PRD-15 | Sales / Warehouse | Login sbg non-Admin | Klik ⋮ di satu baris | Hanya **View** yang muncul di menu; **Edit** dan **Activate/Deactivate** tidak ada | Server + view `$isAdmin` gate |

### 4.3 Tambah Produk

| TC-ID | Role | Precondition | Steps | Expected Result | Ref |
|---|---|---|---|---|---|
| TC-PRD-16 | Admin | — | Klik **Add Product**, isi semua field valid, submit | Produk baru tersimpan, redirect ke `/products`, muncul di daftar | PRD-01.01–04 |
| TC-PRD-17 | Admin | — | Submit form tanpa mengisi field wajib (nama/SKU/dll) | Form re-render dengan pesan error, input yang sudah diisi tetap ada | VAL-01.07/08 |
| TC-PRD-18 | Admin | SKU "OFC-001" sudah ada | Buat produk baru dengan SKU "OFC-001" | Error: SKU duplikat, produk tidak tersimpan | PRD-01.01, PRD-DATA-01 |
| TC-PRD-19 | Admin | — | Upload gambar JPEG/PNG/WebP valid, < 2MB | Gambar tersimpan, tampil di halaman detail | PRD-01.06 |
| TC-PRD-20 | Admin | — | Upload file non-gambar (mis. `.txt` diganti ekstensi `.jpg`) | Form re-render dengan error *"Only JPEG, PNG, or WebP images are allowed."* **+ popup report muncul**; field lain yang sudah diisi tetap ada; **bukan** halaman JSON mentah | (Perbaikan sesi ini — bug asli) |
| TC-PRD-21 | Admin | — | Upload gambar > 2MB | Form re-render dengan error ukuran file, popup report muncul, input lain dipertahankan | PRD-01.06 |
| TC-PRD-22 | Admin | Simulasikan MinIO tidak terjangkau (opsional, environment test) | Submit form dengan gambar valid saat MinIO down | Setelah timeout (maks 15 detik), form re-render dengan error, popup report muncul — **bukan** halaman gantung/JSON mentah | (Perbaikan sesi ini) |

### 4.4 Edit Produk

| TC-ID | Role | Precondition | Steps | Expected Result | Ref |
|---|---|---|---|---|---|
| TC-PRD-23 | Admin | Produk ada | Buka Edit dari row-actions, ubah harga jual, submit | Perubahan tersimpan, tampil di detail/daftar | PRD-01 |
| TC-PRD-24 | Admin | Produk edit sedang dibuka | Ganti gambar dengan file tidak valid | Sama seperti TC-PRD-20 — form re-render, popup report, gambar lama tidak ter-replace | (Perbaikan sesi ini) |
| TC-PRD-25 | Admin | Produk dipakai di PO/SO item | Nonaktifkan produk tsb via Edit atau Activate/Deactivate | Berhasil dinonaktifkan (soft delete), **bukan** hard delete — riwayat PO/SO tetap utuh | PRD-01.05, PRD-DATA-02 |

### 4.5 Activate / Deactivate

| TC-ID | Role | Precondition | Steps | Expected Result | Ref |
|---|---|---|---|---|---|
| TC-PRD-26 | Admin | Produk aktif | Klik ⋮ → **Deactivate** | Tombol berubah "…" sesaat → toast sukses → halaman reload → badge status "Inactive" | DATA-01 |
| TC-PRD-27 | Admin | Produk nonaktif | Klik ⋮ → **Activate** | Badge kembali "Active" | DATA-01 |
| TC-PRD-28 | Admin | Matikan koneksi jaringan (DevTools offline) sesaat sebelum klik | Klik ⋮ → **Deactivate** | Toast error muncul, tombol **kembali bisa diklik** (tidak stuck di "…") | try/catch/finally fix |

### 4.6 Kontrol Akses (Access Control — server-side enforcement)

> Semua TC di bagian ini **wajib** dilakukan lewat panggilan langsung ke endpoint (curl/Postman) — bukan cuma cek tombol hilang di UI — karena UI hiding bukan authorization (BR-017).

| TC-ID | Role | Steps | Expected Result | Ref |
|---|---|---|---|---|
| TC-ACL-01 | Sales | `GET /products/create` langsung via URL | 403 Forbidden (styled error page, bukan teks polos) | `requirePermission` |
| TC-ACL-02 | Warehouse Staff | `GET /products/create` langsung via URL | 403 Forbidden | `requirePermission` |
| TC-ACL-03 | Sales | `POST /products` (curl, dengan CSRF token valid milik Sales) | 403 Forbidden — produk **tidak** tersimpan | `requirePermission('products.manage')` |
| TC-ACL-04 | Sales | `POST /products/{id}/deactivate` langsung | 403 Forbidden — status produk tidak berubah | `requirePermissionWithCsrf` |
| TC-ACL-05 | Tidak login | `GET /products` tanpa session | 302 redirect ke `/login` | `requireAuth` |
| TC-ACL-06 | Admin | `POST /products` dengan `_csrf_token` salah/kosong | 400 Bad Request (styled error page) | `requireCsrf` |
| TC-ACL-07 | Semua role login | `GET /products/{id-tidak-ada}` (ID valid format tapi record tidak ada) | 404 Not Found (styled error page dengan ikon + tombol "Go to Dashboard") | ERR-01.03 |
| TC-ACL-08 | Semua role login | `GET /products/xxx-invalid-format` (ID gagal match pattern) | 404 Not Found (styled error page dari router, bukan teks polos) | (Perbaikan sesi ini) |

---

## 5. Definition of Done

Semua baris di §4 (TC-PRD-01 s.d. TC-ACL-08 — total 36 test case) berstatus **PASS** sebelum menu Products dianggap selesai diverifikasi.

| Kategori | Jumlah TC |
|---|---|
| Daftar, Filter & Pagination | 10 |
| Row Actions Dropdown | 5 |
| Tambah Produk | 7 |
| Edit Produk | 3 |
| Activate/Deactivate | 3 |
| Kontrol Akses | 8 |
| **Total** | **36** |

**Catatan:** TC-PRD-20, TC-PRD-21, TC-PRD-22, TC-PRD-24, TC-ACL-07, TC-ACL-08 secara khusus meregresi bug "response tiba-tiba stuck/raw" yang ditemukan dan diperbaiki dalam sesi ini — jangan dilewati saat regression testing rilis berikutnya.

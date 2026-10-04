# Product Vision — Inventory & Order Management System

**Version:** 1.4 (Draft)  
**Stage:** 1 / 9 (Product Vision)  
**Author:** Peserta Program Pengembangan Kompetensi Programmer  
**Date:** 2026-09-01  
**Status:** Untuk Review

> **Amendment (2026-09-08, CF-02):** i18n (ID/EN) and dark/light theming, referenced throughout this
> document as differentiators, were declared **OUT OF SCOPE** for the current release —
> `i18next` sits outside the brief's allowed frontend list (Fetch API + icon library only). See
> `master-project-specification.md` §46 and `ux-ui-spec.md` v1.3. This document is kept unedited below
> as the original (pre-amendment) vision; the shipped product is English-only, light-theme-only.

---

## 1. Vision Statement & Positioning

### 1.1 Vision Statement (Internal Compass)
> **"Menghadirkan sistem manajemen inventory & order berbasis web yang aman, dapat ditelusuri (auditable), menegakkan pemisahan tanggung jawab, dan ramah untuk siapa pun — dua bahasa, dua tema — sehingga tim gudang dan sales dapat mempercayai setiap angka stok dan setiap transaksi yang dicatatnya, bahkan ketika dua aksi terjadi bersamaan."**

Aplikasi ini bukan sekadar CRUD produk & order. Aplikasi ini adalah **bukti nyata** bahwa peserta memahami rekayasa perangkat lunak menengah: arsitektur berlapis, keputusan desain yang bisa dipertahankan, integritas data pada kondisi konkuren, pengujian sebagai bagian dari desain, dan **pengalaman pengguna yang inklusif** (multi-bahasa, mode terang & gelap).

### 1.3 Positioning Statement (Formal)
```
FOR         Tim operasional gudang & sales di perusahaan menengah
WHO         membutuhkan pencatatan stok multi-warehouse dengan integritas
            data terjaga, otorisasi yang tegas, dan UI yang nyaman
            digunakan oleh staf berbahasa Indonesia maupun Inggris
THE PRODUCT Inventory & Order Management System berbasis PHP Native OOP
PROVIDES    integritas transaksional, jejak audit lewat StockLedger,
            segregasi tugas server-side, i18n (ID/EN), dark/light mode,
            dan arsitektur berlapis yang mudah diuji
UNLIKE      solusi berbasis Laravel/Symfony/ORM atau template admin
            siap pakai, atau aplikasi single-language / fixed theme
OUR PRODUCT membuktikan bahwa clean code, safe concurrency, i18n,
            dan aksesibilitas tema dapat dibangun murni dengan PHP
            Native + Vanilla JS + custom CSS
```

---

## 2. Konteks & Masalah yang Dipecahkan

### 2.1 Konteks Bisnis (dari Project Brief §1)
Tim gudang dan sales pada organisasi menengah membutuhkan satu aplikasi untuk:

- Mencatat produk & mengelola stok di **beberapa gudang**.
- Memproses **pembelian dari supplier** (Purchase Order → Goods Receipt).
- Memproses **penjualan ke customer** (Sales Order → Approval → Goods Issue).
- Memastikan **angka stok selalu bisa dipertanggungjawabkan**.
- Mendukung **operator berbahasa Indonesia maupun Inggris**.
- Nyaman digunakan pada **shift siang (mode terang)** maupun **shift malam (mode gelap)**.

### 2.2 Masalah yang Dipecahkan
| # | Masalah Nyata | Konsekuensi Bila Tidak Dipecahkan |
|---|---------------|-----------------------------------|
| P1 | Stok tersebar di beberapa gudang, sulit dilihat totalnya | Salah janji ke customer, salah restock |
| P2 | Satu orang bisa membuat & menyetujui order sekaligus | Fraud, kesalahan otorisasi, kerugian finansial |
| P3 | Dua transaksi bersamaan → stok minus (oversell) | Janji tidak bisa dipenuhi, komplain customer |
| P4 | Perubahan stok tidak ada jejaknya | Selisih stok tidak bisa ditelusuri, audit sulit |
| P5 | Data master tidak boleh dihapus mentah karena masih dirujuk | FK error, histori hilang |
| P6 | Stok di bawah reorder point tidak segera terlihat | Kehabisan stok, penjualan hilang |
| **P7** | **Staf berbahasa Inggris kesulitan memahami label ID** | **Salah input, error entry** |
| **P8** | **UI terang menyilaukan saat shift malam / kondisi cahaya redup** | **Kelelahan mata, kesalahan visual, ergonomi buruk** |

### 2.3 Konteks Pembelajaran
Selain nilai bisnis, produk ini adalah **assessment authority** dari program Intermediate Programmer PT Neuronworks Indonesia.

- **Sisi Bisnis:** menyelesaikan P1–P8.
- **Sisi Rekayasa:** membuktikan Clean Code, Clean Architecture, Testing sebagai bagian desain, **dan kemampuan menghadirkan i18n + theming tanpa CSS framework**.

---

## 3. Target Users (Personas) & Stakeholder Map

### 3.0 Persona Priority Ranking

Kalau waktu / trade-off memaksa memilih siapa yang dilayani lebih dulu — ini urutannya:

| Rank | Persona | Alasan | Konsekuensi Bila Gagal Melayani |
|------|---------|--------|----------------------------------|
| **P0 — Critical** | **Rita (Admin)** | Tanpa Admin, tidak ada master data → sistem tidak bisa jalan | Sistem mati total |
| **P0 — Critical** | **Wawan (Warehouse)** | Tanpa Warehouse, stok tidak bergerak → tidak ada revenue | Bisnis tidak jalan |
| **P0 — Critical** | **Beni (Sales)** | Tanpa Sales, tidak ada order → tidak ada Value | Tidak ada penjualan |
| **P1 — Important** | **Grace (Expat)** | Pendukung pillar Inclusivity; kalau i18n `en` bolong, Grace terhambat tapi P0 tetap bisa kerja | Nilai Inclusivity pillar turun |

**Aturan main:** Kalau ada trade-off waktu, **P0 selalu menang**. Grace boleh menerima UI EN yang belum 100% coverage; Rita/Beni/Wawan **tidak boleh** menerima UI yang setengah jadi.

---

### 3.1 Persona A — **Rita, Admin Operasional** *(Priority: P0)*

**Ringkasan Identitas**
- **Role sistem:** `Admin`
- **Bahasa:** ID · **Tema:** Light (kantor pagi)
- **Umur / latar:** ~45 tahun, sudah 10 tahun di operasional; familiar Excel & aplikasi kantor

**Environment (Di Mana, Kapan, Pakai Apa)**
- Lokasi: Kantor lantai 2, meja tetap
- Device: PC Desktop 1920×1080
- Koneksi: LAN kantor (stabil, cepat)
- Waktu aktif: 08:00 – 17:00 (jam kantor)

**Tech Literacy:** **Menengah** — pakai Excel, aplikasi internal 10 tahun. Familiar shortcut `Ctrl+F`, `Ctrl+C/V`. Belum familiar Vim-style / power tools.

**Kebutuhan Utama**
- Mengelola master data (produk, gudang, supplier, customer, user)
- Menyetujui/menolak Sales Order
- Menerbitkan Purchase Order ke supplier
- Memantau nilai inventori & order pending

**Frustrations (Sakit yang Sudah Terasa Sekarang)**
- 😤 Setiap approval SO lewat WhatsApp — kalau ditanya kembali 2 minggu lalu, tidak ada bukti resmi.
- 😤 User baru (Sales / Warehouse) dibuatkan lewat SQL manual oleh IT — lambat & error-prone.
- 😤 Tidak ada satu tempat untuk melihat kesehatan gudang secara agregat.
- 😤 Data supplier lama tidak boleh dihapus (masih dirujuk order), tapi tidak ada mekanisme deaktivasi.

**Success Quote (Kalimat Pertama Saat Merasa Sistem Berhasil)**
> *"Akhirnya semua approval saya tercatat lengkap dengan siapa-kapan-kenapa, dalam 1 klik — tanpa buka WhatsApp."*

**Test Proxy — Dipakai Saat Code Review UI**
> *"Kalau Rita membuka halaman ini dari desktop 1920×1080 dan hanya pakai keyboard (Tab / Shift+Tab / Enter / Esc), apakah dia bisa menyelesaikan alur utama tanpa harus pegang mouse? Apakah semua tombol punya `:focus-visible` yang terlihat jelas?"*

**Day-in-the-Life (Satu Hari Rita)**
- **08:00** — Login, buka Dashboard Admin. Cek nilai inventori & 5 produk di bawah reorder point.
- **08:30** — Review antrean SO PendingApproval (3 order dari Beni). Verifikasi harga & stok. **Approve** yang valid, **Reject** yang salah customer.
- **10:00** — Terima notifikasi dari Wawan: stok Produk-A kritis. Buat **Purchase Order** ke supplier, kirim.
- **13:00** — Tambah user baru (Sales trainee). Isi form, set role `Sales`, aktifkan.
- **15:00** — Update harga jual 5 produk (kategori: elektronik). Cek dashboard, semua konsisten.
- **17:00** — Logout. Semua aksi tersimpan di audit trail bonus (kalau v1.1 aktif).

---

### 3.2 Persona B — **Beni, Sales** *(Priority: P0)*

**Ringkasan Identitas**
- **Role sistem:** `Sales`
- **Bahasa:** ID · **Tema:** Light siang, Dark malam
- **Umur / latar:** ~32 tahun, sudah 5 tahun sales; pernah pakai CRM (Salesforce Lite)

**Environment (Di Mana, Kapan, Pakai Apa)**
- Lokasi: Kantor (pagi) + Mobile (di jalan ke customer)
- Device: Laptop 13" 1366×768 (kantor) + HP Android 6.1" (mobile)
- Koneksi: Mixed — WiFi kantor cepat, 4G mobile kadang lemah (mall / basement)
- Waktu aktif: 08:00 – 20:00 (fleksibel)

**Tech Literacy:** **Menengah-Tinggi** — familiar CRM, cepat adaptasi UI baru. Butuh UI clean dan cepat, tidak butuh hand-holding.

**Kebutuhan Utama**
- Melihat katalog produk **dengan info stok tersedia real-time**
- Membuat Sales Order untuk customer
- Mengajukan approval, melihat status order-nya
- Melihat riwayat order sendiri (bukan order sales lain)

**WAJIB DIJAGA (Aturan Bisnis)**
- 🚫 Beni **TIDAK BOLEH** menyetujui order-nya sendiri — meski tombol di UI-nya tersembunyi, aturan ditegakkan **di server** (§9 CLAUDE.md, BR-001).

**Frustrations**
- 😤 Membuat komitmen ke customer tanpa tahu stok real — ternyata stok habis di gudang lain.
- 😤 Tidak bisa lihat riwayat ordernya sendiri secara mandiri.
- 😤 Approval SO lewat WhatsApp ke Rita — kadang di-read tanpa jawaban 1 hari.
- 😤 Tidak tahu SO mana yang sudah Fulfilled dan mana yang belum di-issue.

**Success Quote**
> *"Saya bisa janji ke customer dengan yakin — stok cukup, tinggal tunggu Rita approve. Tanpa telepon gudang."*

**Test Proxy — Dipakai Saat Code Review UI**
> *"Kalau Beni membuka katalog produk dari HP dengan koneksi 3G (2-4 Mbps) di mall (latensi 500 ms), apakah halaman utama load & interaktif dalam < 3 detik? Apakah pagination bisa dipakai tanpa reload full page? Apakah tombol 'Buat SO' selalu terlihat tanpa scroll berlebihan?"*

**Day-in-the-Life (Satu Hari Beni)**
- **08:00** — Login dari laptop. Cek Dashboard Sales: 5 SO Fulfilled minggu ini, 2 SO PendingApproval.
- **09:00** — Meeting customer di kantor. Buka katalog (mode `light`), cek stok Produk-X di gudang Bandung. Buat SO Draft di depan customer.
- **11:30** — Di jalan ke customer B (HP, koneksi 4G tidak stabil). Lanjut edit SO Draft, submit approval.
- **14:00** — Kembali ke kantor. Cek notifikasi: SO tadi sudah Approved oleh Rita. Kirim konfirmasi ke customer.
- **18:00** — Selesai jam kantor tapi ingin cek 1 SO tertunda (mode `dark` mata sudah lelah). Verifikasi statusnya "Fulfilled".
- **20:00** — Logout.

---

### 3.3 Persona C — **Wawan, Warehouse Staff** *(Priority: P0)*

**Ringkasan Identitas**
- **Role sistem:** `WarehouseStaff`
- **Bahasa:** ID (occasional EN untuk term teknis) · **Tema:** Dark (gudang temaram)
- **Umur / latar:** ~28 tahun, 3 tahun di gudang, sebelumnya sopir. Familiar HP untuk WA/Instagram, bukan power user aplikasi kantor.

**Environment (Di Mana, Kapan, Pakai Apa)**
- Lokasi: Gudang (pencahayaan sedang / temaram di rak dalam)
- Device: HP Android 5.5" 720×1440 (device utama) + Tablet 10" kantor gudang
- Koneksi: WiFi gudang **lemah & putus-nyambung** (repeater kurang di rak dalam)
- Waktu aktif: 07:30 – 16:30 (shift pagi), rekan lain shift malam
- Kondisi fisik: Tangan kadang kotor / pakai sarung tangan tipis; berdiri / berjalan sambil pakai aplikasi

**Tech Literacy:** **Rendah-Menengah** — familiar HP untuk WA & sosmed, tapi belum familiar keyboard shortcut / power tools. Butuh instruksi visual besar & konfirmasi 2-step untuk aksi destructive.

**Kebutuhan Utama**
- Melihat produk & stok di gudangnya
- Memproses **Goods Receipt** untuk PO yang barangnya datang
- Memproses **Goods Issue** untuk SO yang sudah Approved
- Melihat antrean pekerjaannya & produk low-stock

**Frustrations**
- 😤 Manual mencatat pemasukan/pengeluaran di buku — hilang / terhapus / lupa update sistem.
- 😤 Stok fisik dan sistem sering **selisih 3× seminggu**, dia yang disalahkan.
- 😤 Tidak ada peringatan produk low-stock — baru sadar pas kosong.
- 😤 Kalau salah input di aplikasi lama, sulit di-undo — malah bikin selisih baru.

**Success Quote**
> *"Antrean pekerjaan hari ini jelas di layar; selisih stok fisik vs sistem sekarang < 1× seminggu. Kalau salah, saya tahu di mana lihat riwayatnya."*

**Test Proxy — Dipakai Saat Code Review UI**
> *"Kalau Wawan membuka halaman ini di gudang temaram (contrast rendah alami) dengan HP 360×640 px sambil pakai sarung tangan tipis (touch target ≥ 44 px), apakah tombol utama tetap tapable? Apakah angka stok terbaca dari jarak 40 cm? Apakah aksi destructive (issue / receipt) punya konfirmasi 2-step supaya tidak salah tap?"*

**Day-in-the-Life (Satu Hari Wawan)**
- **07:30** — Datang gudang, login dari HP (`dark` mode + `id` locale). Buka Dashboard Warehouse: **antrean hari ini** — 3 PO tunggu receipt, 2 SO tunggu issue.
- **08:00** — Cek prioritas: SO deadline hari ini didahulukan.
- **10:00** — Truk supplier datang bawa PO. Buka PO, scan item, input **Goods Receipt partial** (60 dari 100 unit, sisa datang minggu depan). Sistem otomatis update stok + tulis ledger.
- **12:00** — Istirahat.
- **14:00** — Issue SO ke armada pengiriman. Cek stok cukup, konfirmasi 2-step, sistem approve — barang keluar, ledger tercatat.
- **16:00** — Cek dashboard low-stock. Ada 5 produk di bawah reorder point. **Usulkan** ke Rita via note (bukan bikin PO langsung — bukan wewenang).
- **16:30** — Logout, laporkan ke shift malam via WA.

---

### 3.4 Persona D — **Grace, Expat Warehouse Supervisor** *(Priority: P1 — pendukung `default: en`)*

**Ringkasan Identitas**
- **Role sistem:** `Admin` atau `WarehouseStaff`
- **Bahasa:** EN (native) · **Tema:** Dark (preferensi visual + gudang)
- **Umur / latar:** ~38 tahun, 15 tahun di supply chain enterprise (Australia, Singapore). Ditugaskan Head Office 6 bulan untuk setup gudang di Indonesia.

**Environment (Di Mana, Kapan, Pakai Apa)**
- Lokasi: Kantor + Gudang (bergantian)
- Device: Laptop 14" 1440×900 + iPad kantor
- Koneksi: Kantor cepat, gudang WiFi lemah (sama seperti Wawan)
- Waktu aktif: 09:00 – 18:00

**Tech Literacy:** **Tinggi** — familiar SAP, Oracle NetSuite, Zoho Inventory. Ekspektasi UI dense-information + keyboard shortcut. Bisa membaca dokumentasi teknis EN dengan cepat.

**Rasional Persona Ini Ada**
Aplikasi ini memilih **default locale `en`**, bukan `id`. Grace menjelaskan **siapa** yang mewakili keputusan itu — staf ekspat / supervisor internasional yang perlu label EN sejak halaman pertama untuk menghindari salah baca (`Reorder Point` vs `Titik Pesan Ulang`).

**Kebutuhan Utama**
- Monitor kinerja gudang (nilai inventori, low-stock, order pending)
- Coaching Wawan & tim gudang lokal
- Report ke Head Office (butuh CSV EN header + angka konsisten)

**Frustrations**
- 😤 Aplikasi lokal cuma berbahasa Indonesia — dia sering Google-translate button label, kadang **approve tombol yang salah**.
- 😤 CSV export header dalam ID — susah masukkan ke report Head Office.
- 😤 Term teknis berbeda antar aplikasi lokal (Stok / Persediaan / Inventaris) — bingung mana yang benar.

**Success Quote**
> *"This feels like a system I'd use back home — same clarity, same speed, same terminology. I can complete a full Goods Issue workflow in English & dark mode without asking a colleague to translate any label."*

**Test Proxy — Dipakai Saat Code Review UI**
> *"Kalau Grace membuka halaman ini dalam mode `en` + `dark`, apakah semua label (form, tombol, header tabel, empty state, error message) sudah diterjemahkan? Apakah tidak ada string ID yang bocor? Apakah kontras cukup untuk membaca angka stok kecil di gudang temaram (≥ WCAG AA)? Apakah CSV export punya header EN saat locale=en?"*

**Day-in-the-Life (Satu Hari Grace)**
- **09:00** — Login dari laptop (auto locale=`en`, theme=`auto` → `dark` karena OS-nya dark). Buka Dashboard Admin.
- **09:30** — Meeting dengan Rita: review low-stock trend minggu ini. Screen-share, Rita lihat `id`, Grace lihat `en` — **data identik**, cuma bahasa berbeda.
- **11:00** — Ke gudang, coaching Wawan proses Goods Receipt. Grace lihat panduan step di layar dalam `en`, jelaskan ke Wawan dalam ID.
- **14:00** — Export CSV order Q3 → header EN, kirim ke Head Office.
- **16:00** — Review sistem: catat feedback UX untuk trainer Neuronworks.
- **18:00** — Logout.

---

### 3.5 Non-Users & Anti-Persona (Siapa yang JANGAN Dilayani)

Anti-persona = **pagar scope creep**. Kalau muncul fitur yang melayani anti-persona → tolak.

| Anti-Persona | Peran (Kalau Ada) | Kenapa Ditolak |
|--------------|-------------------|-----------------|
| **Rudi, Public Visitor** | Orang umum yang mau register akun | §11 FAQ Brief: **NO public registration**. Akun hanya dibuat Admin. |
| **Bu Sinta, Auditor Eksternal** | Auditor pajak/keuangan yang butuh view-only ke ledger | Tidak ada "portal auditor" — kalau butuh, akses lewat CSV export saja. |
| **Pak Hendra, CEO** | Eksekutif yang mau dashboard interaktif dengan grafik executive | Bukan MVP scope — bisa dipertimbangkan v2.0 post-assessment. |
| **Customer eksternal** | End-buyer yang mau tracking order sendiri | Customer adalah **data master**, bukan user. Tracking lewat komunikasi Sales. |
| **Supplier** | Supplier yang mau update PO status sendiri | Supplier adalah **data master**. PO status di-update Warehouse (goods receipt). |

---

### 3.6 Stakeholder Map (Bukan User Aplikasi, Tapi Peduli dengan Proyeknya)

| Stakeholder | Peran | Yang Mereka Pedulikan | Cara Komunikasi |
|-------------|-------|------------------------|-------------------|
| **Peserta (Anda)** | Owner + Developer | Nilai ≥ 80, portofolio, pembelajaran engineering matang | Self-review setiap milestone |
| **Trainer Neuronworks** | Mentor, checkpoint | Progres, konsultasi ambiguitas requirement | Checkpoint terjadwal + catat di `docs/planning/decisions.md` |
| **Assessor** | Penilai defense | Bisa ditelusuri, dijelaskan, sesuai brief | Docs + demo + defense |
| **Persona Simulasi (Rita/Beni/Wawan/Grace)** | User bayangan | Alur bisnis lancar, UI usable, bahasa & tema sesuai preferensi | Test proxy di code review + demo data |

---

## 4. Value Proposition & Strategic Pillars

### 4.1 Empat Nilai Inti

1. **🔐 Integritas Data yang Terbukti** — StockLedger + ARCH-02 + integration test MySQL Docker.
2. **⚖️ Segregation of Duties yang Ditegakkan Server** — Authorization di server, bukan UI.
3. **🔎 Auditability Bawaan** — Dashboard & laporan dihitung dari data ledger, bukan angka statis.
4. **🧱 Arsitektur yang Dapat Dijelaskan** — Controller → Service → Repository interface + ADR.

### 4.2 Nilai Tambah Pengalaman Pengguna *(baru)*

5. **🌐 Bilingual (EN / ID) — Bahasa Bukan Penghalang**
   - **Default bahasa: `en`** (English) — mendukung staf ekspat seperti Grace + pembaca internasional (assessor, portofolio publik).
   - Semua label UI, pesan validasi, header laporan, tombol, dan pesan error tersedia dalam dua bahasa.
   - Library: **i18next-core** (framework-agnostic, ~15 KB) + **i18next-http-backend** (lazy-load JSON per locale).
   - Struktur file: `public/assets/locales/en/translation.json` + `public/assets/locales/id/translation.json`.
   - **Auto-translate opsional (bonus)** untuk konten dinamis (nama produk, deskripsi) via LibreTranslate service di Docker.

6. **🌗 Tri-State Theme (Auto / Light / Dark) — Nyaman di Segala Kondisi**
   - **Default: `auto`** (ikut `prefers-color-scheme` sistem operasi user — hormati preferensi OS).
   - Toggle manual 3-state: `auto` → `light` → `dark` → `auto` (siklus).
   - Mode terang untuk shift siang / kantor pencahayaan baik.
   - Mode gelap untuk shift malam / gudang temaram (kurangi kelelahan mata).
   - Dibangun tanpa CSS framework — CSS custom properties + `prefers-color-scheme` + preferensi `localStorage`.

### 4.3 Strategic Pillars — Setiap Fitur Harus Mendukung Salah Satu

| Pillar | Definisi | Fitur Pendukung |
|--------|----------|------------------|
| 🔐 **Trust** | Angka stok dapat dipercaya | ARCH-02, StockLedger, transaksi eksplisit |
| ⚖️ **Control** | Aturan bisnis tidak bisa dilanggar | Segregation of Duties, Authorization server, Validation |
| 🔎 **Traceability** | Setiap perubahan bisa dilacak | Ledger, Audit trail bonus, Refactor log |
| 🧱 **Craftsmanship** | Kode & desain layak dipertahankan | Layered arch, Testing, ADR, Class diagram |
| 🌍 **Inclusivity** *(baru)* | Bahasa & tema bukan penghalang | i18n ID/EN, Dark/Light mode, WCAG contrast |

Fitur yang **tidak mendukung salah satu Pillar** → risiko scope creep; harus dipertanyakan.

---

## 5. Product Scope (High-Level)

### 5.1 Termasuk (In Scope) — MVP
- Authentication (login/logout, session)
- User Management (3 role) — hanya Admin yang membuat
- Master Data: Produk (+ kategori, reorder point, gambar opsional), Warehouse, Supplier, Customer
- Multi-warehouse Stock (ProductStock per gudang)
- Purchase Order + Goods Receipt (full & partial)
- Sales Order + Approval + Goods Issue (segregation of duties)
- Stock Ledger (audit trail)
- Search, Filter, Sort, Pagination (10/halaman)
- Dashboard per role (Admin / Sales / Warehouse)
- CSV export (StockLedger, order per tanggal)
- Minimal 1 JSON API endpoint
- Validasi frontend + backend (backend authoritative)
- Error handling aman
- **Responsive UI (360 px – desktop)**
- **🌐 i18n Static UI: Bahasa Indonesia + English** — label form, tombol, header tabel, pesan validasi, empty state, error message
- **🌗 Dark / Light theme dengan toggle + auto-detect `prefers-color-scheme`** — preferensi disimpan di `localStorage`
- **🎨 Static UI Assets format-appropriate:** SVG untuk icon & logo (mendukung dark/light via `currentColor`), WebP untuk illustration kompleks, PNG/ICO untuk favicon fallback. Icon library: **Feather Icons** (SVG sprite ~30 KB, MIT license). Ilustrasi gratis dari unDraw / Storyset (CC0/MIT). Total asset UI < 100 KB, cache-friendly
- Manual scheduled script (low-stock checker via Docker Compose)

### 5.2 Tidak Termasuk (Out of Scope) — MVP
(Sesuai §4.3 Project Brief dan Rule #40 CLAUDE.md)

- Microservices, message queue, real-time notification
- Cloud deployment, CI/CD, Kubernetes
- Mobile application native
- Automated end-to-end test infrastructure
- Cron scheduler otomatis di server penilaian
- Public registration untuk customer
- ORM / framework backend / framework frontend / CSS framework
- **RTL (Right-to-Left) language support** — tidak dibutuhkan karena ID & EN sama-sama LTR
- **Bahasa selain ID & EN** (mis. Mandarin, Arab) — bisa ditambah post-assessment

### 5.3 Bonus (Dievaluasi Setelah Wajib Stabil) — §4.4 Project Brief + Tambahan Peserta
- Email notification simulasi (Mailhog di Docker)
- Audit trail perubahan master data
- Dashboard grafik SVG/canvas buatan sendiri
- Integration test tambahan
- **🤖 Auto-translate Dynamic Content** via **LibreTranslate** service di Docker Compose — untuk terjemahkan nama produk / deskripsi / kategori otomatis saat user memilih bahasa berbeda. Backend `TranslationService` (dengan cache) memanggil LibreTranslate REST API.

> ⚠️ Rule #39 CLAUDE.md: Bonus **TIDAK BOLEH** menutupi requirement wajib yang belum berfungsi.

---

## 6. Success Metrics

### 6.0 🌟 North Star Metric
> **"Nilai project ≥ 80 dengan 0 critical failure — semua alur inti (Login → PO → SO → Ledger → Dashboard) dapat didemokan sepenuhnya dalam 2 bahasa & 2 tema tanpa error dari Docker bersih."**

Semua keputusan trade-off HARUS mendukung North Star ini.

### 6.1 Metrik Assessment (Autoritatif — Project Brief)
| # | Metrik | Target |
|---|--------|--------|
| M1 | Nilai project | **≥ 80** |
| M2 | Critical failure (§8.2 Brief) | **0** |
| M3 | Demo produk (12–15 mnt) dari Docker bersih | ✅ Lulus |
| M4 | Engineering evidence (5–7 mnt) lengkap & jelas | ✅ Lulus |

### 6.2 Metrik Rekayasa
| # | Metrik | Target |
|---|--------|--------|
| E1 | Unit test | ≥ 6 test di ≥ 3 area |
| E2 | Integration test | ≥ 3 test menyentuh MySQL Docker |
| E3 | Static analysis critical error | **0** |
| E4 | ADR | 2–3 dokumen |
| E5 | Refactoring log entries | ≥ 3 entry + ≥ 1 commit `refactor:` |
| E6 | Class diagram initial & as-built | Keduanya ada + sesuai kode |
| E7 | Race condition test (ARCH-02) | Terbukti tidak oversell |
| E8 | Segregation of duties | Teruji di **server**, bukan hanya UI |
| **E9** | **Coverage i18n key** | **100% label UI di JSON file (tidak ada string hardcoded di HTML/JS)** |
| **E10** | **Contrast rasio dark & light theme** | **≥ WCAG AA (4.5:1 body text, 3:1 large text)** |
| **E11** | **Total ukuran static UI asset** | **< 100 KB** (icon sprite + logo + illustration + favicon) |
| **E12** | **Product image tersimpan** | Rata-rata **< 200 KB** per gambar (setelah convert WebP + resize) |

### 6.3 Metrik Fungsional
| # | Metrik | Target |
|---|--------|--------|
| F1 | Demo data | 1 Admin + 2 Sales + 2 Warehouse + 2 gudang + 30 produk + 25 order |
| F2 | Alur end-to-end | Login → PO → Receipt → SO → Approve → Issue → Ledger |
| F3 | Pagination aktif | ≥ 2 halaman pada daftar utama |
| F4 | Dashboard 3 role | Beda tampilan + angka dari query agregasi |
| **F5** | **Language switch** | **Berpindah ID ↔ EN pada semua halaman utama tanpa reload / refresh state hilang** |
| **F6** | **Theme switch** | **Berpindah Light ↔ Dark instan; preferensi tersimpan antar-session** |

---

## 7. Guiding Principles

1. **Backend adalah Sumber Kebenaran.** Frontend membantu UX; validasi & authorization otoritatif di server.
2. **Stok Tidak Pernah Diubah Tanpa Ledger.**
3. **Interface Sebelum Implementasi.** Repository boundary punya interface.
4. **Test adalah Bagian Desain.**
5. **Dokumentasi Menyatakan Alasan.** ADR menjawab "mengapa".
6. **Utang Teknis Dicatat Jujur.**
7. **Diagram = Kode.**
8. **Anti Over-Engineering.**
9. **UI Inklusif dari Awal, Bukan Belakangan.** *(baru)* Tidak boleh ada string hardcoded, dan tidak boleh ada warna hardcoded — semua lewat i18n key + CSS custom property.
10. **Pilih Library dengan Alasan.** *(baru)* Setiap library eksternal (i18n / LibreTranslate) wajib dijelaskan alasannya di ADR — kenapa bukan solusi custom / kenapa bukan library lain.
11. **Format Asset Sesuai Jenis.** *(baru)* **SVG** untuk vector (icon, logo, ilustrasi flat) karena scaleable & mendukung tema via `currentColor`; **WebP** untuk raster (foto, ilustrasi kompleks) karena 25–35% lebih kecil dari JPG/PNG; **PNG/ICO** hanya untuk favicon fallback. **Tidak boleh** commit PNG/JPG besar untuk hal yang cukup dengan SVG.
12. **Asset Statis Harus Cache-Friendly.** *(baru)* Semua static asset UI (icon sprite, logo, illustration) disajikan dari `public/assets/` dengan nama yang stabil selama development, dan siap-hash saat production. Total ukuran seluruh static UI asset < 100 KB (target).

---

## 8. Non-Goals & Anti-Vision

### 8.1 Yang SENGAJA Tidak Dikejar (Non-Goals)
- Tidak membuat framework UI sendiri.
- Tidak membuat 4-ring Clean Architecture (FAQ #4 Brief).
- Tidak pakai DI container framework (FAQ #2 Brief).
- Tidak menulis 100+ unit test — 6 test berkualitas > 100 trivial.
- Tidak membangun cron otomatis di server.
- Tidak menambah field/entity di luar §1.3 Brief kecuali dibutuhkan requirement.
- **Tidak pakai CSS framework (Tailwind, Bootstrap) meski ada dark mode plugin bawaan** — kita buktikan bisa dengan CSS custom property.
- **Tidak pakai i18n framework yang terikat React/Vue** (react-intl, vue-i18n) — pakai `i18next-core` UMD (framework-agnostic) yang bisa dipanggil dari Vanilla JS.
- **Tidak call Google Translate API dari frontend** — leak data, butuh API key, tidak self-hostable.
- **Tidak pakai PNG/JPG untuk icon UI** — SVG lebih ringan, scaleable, dan otomatis mengikuti tema via `currentColor`.
- **Tidak commit gambar/foto besar > 100 KB per file di repo** — kalau butuh ilustrasi, pakai SVG (flat) atau WebP quality 82.

### 8.2 Anti-Vision — Aplikasi Ini BUKAN...
- ❌ **BUKAN** produk yang berlomba fitur dengan Odoo/MYOB.
- ❌ **BUKAN** template admin dashboard "cantik gampang jadi" — kami sengaja bangun dari nol.
- ❌ **BUKAN** demo Laravel/framework — buktinya di kode PHP native yang tulus.
- ❌ **BUKAN** aplikasi yang dinilai dari jumlah fitur — kualitas > kuantitas.
- ❌ **BUKAN** aplikasi single-language / fixed theme — inklusivitas adalah pillar.
- ❌ **BUKAN** aplikasi yang tergantung service pihak ketiga untuk berjalan — LibreTranslate self-hosted, tidak call Google.

---

## 9. Ubiquitous Language (Core Terms)

Istilah yang **wajib konsisten** di kode, DB, UI (ID & EN), ADR, test.

| Term (ID / EN) | Definisi | Jangan Sebut |
|----------------|----------|--------------|
| **Stok / Stock** | Kuantitas produk **per gudang** | "Inventory", "Persediaan" (ambigu) |
| **Ledger / Ledger** | Baris histori pergerakan stok immutable | "Log", "History" |
| **Goods Receipt** (id: Penerimaan Barang) | Penerimaan barang dari supplier untuk PO | "Barang Masuk" (di UI EN saja) |
| **Goods Issue** (id: Pengeluaran Barang) | Pengeluaran barang untuk SO | "Barang Keluar" |
| **Purchase Order / PO** | Pesanan pembelian ke supplier | "Order Pembelian" (di ADR/kode → PO) |
| **Sales Order / SO** | Pesanan penjualan ke customer | "Order Penjualan" (di ADR/kode → SO) |
| **Approval / Persetujuan** | Persetujuan SO oleh Admin | "Confirm", "Verify" |
| **Reorder Point / Titik Pesan Ulang** | Ambang stok pemicu low-stock alert | "Min Stock", "Threshold" |
| **Role** | `Admin` / `Sales` / `WarehouseStaff` (huruf besar di kode) | "Peran" (di ADR/kode selalu Role) |
| **Warehouse / Gudang** | Lokasi fisik penyimpanan | "Storage", "Lokasi" |
| **Theme / Tema** | `auto` (default) \| `light` \| `dark`; disimpan di `localStorage['theme']` | "Skin", "Mode" (kecuali istilah umum "dark mode") |
| **Static Asset** | Asset UI bawaan aplikasi di `public/assets/` (icon SVG, logo SVG, illustration SVG/WebP, favicon) — bukan gambar produk upload user | "Image" (ambigu — bisa produk) |
| **Product Image** | Gambar produk yang di-upload user, disimpan sebagai `.webp` di `public/uploads/products/` dengan nama acak | "Product Photo", "Foto Produk" (di UI EN) |
| **Icon Sprite** | Satu file `sprite.svg` berisi semua icon (dari Feather Icons), dipanggil via `<use href="...#name"/>` | "Icon set" (ambigu) |
| **Locale** | `en` (default) \| `id` (kode ISO 639-1); disimpan di `localStorage['locale']` | "Language" (di kode selalu `locale`) |

Detail lengkap → `docs/planning/glossary.md` (dibuat di Stage 2 PRD).

---

## 10. High-Level Roadmap

| Version | Cakupan | Timeline |
|---------|---------|----------|
| **v1.0 — Final Submission** | Semua mandatory Brief + i18n static EN(default)/ID via i18next + Auto/Light/Dark theme + testing + docs | Deadline peserta |
| **v1.1 — Bonus (opsional)** | LibreTranslate auto-translate dynamic + Mailhog email + audit trail + grafik SVG | Setelah v1.0 hijau |
| **v2.0 — Post-Assessment** | Cron nyata, multi-currency, notifikasi real-time, bahasa tambahan (ZH/AR + RTL) | Tidak dikerjakan sekarang |

---

## 11. Risks & Assumptions

### 11.1 Risiko Teknis
| Risk | Dampak | Mitigasi |
|------|--------|----------|
| Race condition tidak tertangani → oversell | **Critical failure** (§8.2 Brief) | ADR concurrency + integration test |
| Business logic kopling PDO → sulit diuji | Gagal ARCH-01 | Repository interface + Fake dari awal |
| Diagram tidak sesuai kode saat defense | Critical failure | Update as-built tiap milestone besar |
| Framework masuk lewat composer | Larangan §3 CLAUDE.md | Review composer.json setiap PR |
| **Library i18n `i18next-core` dianggap "framework"** | Bisa gagal §3 constraint (Brief melarang framework, bukan library) | ADR-003 jelaskan bahwa `i18next-core` adalah **library UMD framework-agnostic** (bukan React/Vue framework); ~15 KB gzipped; tanpa dependency tree besar; dipilih karena matang & didokumentasikan luas untuk defense |
| **LibreTranslate container tidak start di komputer assessor** | Fitur bonus mati | Bonus, jadi tidak fatal; sediakan graceful fallback ke bahasa asli |
| **Contrast dark theme kurang → gagal WCAG AA** | E10 metric miss | Pakai token warna primitif + semantic; test dengan Chrome DevTools contrast checker |
| **Feather Icons dianggap "framework" oleh assessor** | Bisa gagal §3 constraint | ADR-004 jelaskan: Feather = **static SVG file library** (MIT), bukan framework/CSS/JS runtime; total sprite ~30 KB; tidak ada dependency JS/CSS |
| **PHP GD extension tidak enabled saat build image** | Product image upload gagal | Dockerfile eksplisit `docker-php-ext-install gd` + `RUN apt-get install -y libwebp-dev`; verifikasi `php -m \| grep gd` di image |

### 11.2 Risiko Proses
| Risk | Dampak | Mitigasi |
|------|--------|----------|
| Waktu habis di i18n/theme, mandatory belum stabil | Nilai < 80 | i18n & theme = Enhanced MVP, kerjakan **setelah** vertical slice §2 Brief stabil |
| AI usage tidak tercatat | Critical failure | `ai-usage-log.md` diupdate setiap sesi |
| Tidak bisa jelaskan sendiri | Critical failure | ADR ditulis sendiri, kode dibaca ulang |

### 11.3 Asumsi
- Docker Desktop tersedia di komputer assessor & compatible dengan `compose.yml`.
- PHP 8.2+ berjalan di container.
- Tidak ada requirement multi-currency / multi-timezone (default WIB / IDR).
- Data demo boleh fiktif.
- **Assessor OK dengan default locale=`en`** (bisa switch ke `id` lewat tombol UI dalam <5 detik) — didokumentasikan di README bagian "First Time Setup".
- **Assessor OK dengan default theme=`auto`** (ikut prefers-color-scheme OS) — toggle manual tersedia di header untuk `light` / `dark` eksplisit.
- **LibreTranslate image publik masih tersedia di Docker Hub saat build.**

---

## 12. Definition of Done — Stage 1 (Product Vision)

- [x] Vision statement, elevator pitch, positioning statement, metaphor
- [x] Konteks & masalah bisnis (P1–P8)
- [x] Personas (4 persona) + Stakeholder Map
- [x] Value proposition + 5 Strategic Pillars
- [x] Scope MVP / Out-of-Scope / Bonus eksplisit
- [x] Success metrics 3 lapis + North Star
- [x] Guiding principles (10 prinsip)
- [x] Non-Goals + Anti-Vision
- [x] Ubiquitous Language (core terms)
- [x] High-Level Roadmap (v1.0 → v2.0)
- [x] Risks & assumptions
- [ ] **Direview & disetujui peserta sebelum masuk Stage 2 (PRD)**

---

## 13. Referensi

- **Project Brief** — `Project Brief - Programmer.pdf` (Edisi 1.0, Oktober 2026)
- **CLAUDE.md** — root project
- Lifecycle: Rule #2 CLAUDE.md
- Struktur folder: Rule #24 CLAUDE.md
- Critical failures: §8.2 Project Brief
- **i18n library candidate**: [i18next.js](https://www.i18next.com/) atau custom vanilla ~2 KB
- **Auto-translate service candidate (bonus)**: [LibreTranslate](https://libretranslate.com/) (self-hosted Docker)

---

## 14. Preview Stage 2 (PRD)

Stage 2 akan menerjemahkan §5 Scope + §4.2 UX values menjadi requirement per fitur, ditambah:

- **I18N-01** — Static UI i18n (i18next-core + i18next-http-backend, JSON files)
- **THEME-01** — Tri-state theme Auto/Light/Dark (CSS custom properties + `prefers-color-scheme` + `localStorage`)
- **ASSET-01** — Static UI Asset Strategy (SVG icon sprite Feather Icons, SVG logo, WebP illustration, favicon multi-format)
- **IMAGE-01** — Product Image Upload & WebP Conversion (server-side convert via PHP GD, resize, random name, WebP-only storage)
- **I18N-02** *(bonus)* — Auto-translate dynamic content via LibreTranslate

Setiap requirement akan punya: Acceptance Criteria, Data yang disentuh, Test scenario, Authorization rules.

---

## Changelog
- **1.0 · 2026-09-01** — Draft awal.
- **1.1 · 2026-09-01** — Tambah **9 seksi Vision-level** (Elevator Pitch, Positioning Statement, Metaphor, Persona Grace, Stakeholder Map, Strategic Pillars, North Star, Ubiquitous Language, Roadmap, Anti-Vision) + **Fitur i18n ID/EN & Dark/Light theme**. Positioning i18n static = MVP; auto-translate dynamic = Bonus (LibreTranslate).
- **1.2 · 2026-09-01** — Konfirmasi keputusan peserta: **Library i18n = `i18next-core` + `i18next-http-backend`** (bukan custom vanilla); **default locale = `en`** (bukan `id`); **default theme = `auto`** (ikut OS, dengan siklus toggle `auto → light → dark`); **Persona Grace dipertahankan** dengan rasional lebih tegas sebagai pembenar default `en`.
- **1.3 · 2026-09-01** — **Persona diperkaya total** dengan 8 lapisan per persona: Environment Context, Tech Literacy, Frustrations eksplisit, Success Quote (first-use), Test Proxy (dipakai code review), Day-in-the-Life (skenario harian); ditambah **Persona Priority Ranking** (P0/P1) untuk panduan trade-off waktu; ditambah **Anti-Persona** (Rudi/Bu Sinta/Pak Hendra/Customer/Supplier) sebagai pagar scope creep.
- **1.4 · 2026-09-01** — **Static UI Asset Strategy** ditambahkan: **SVG** (icon Feather Icons sprite + logo + illustration flat) dan **WebP** (illustration kompleks + product image upload converted server-side via GD). Ditambah 2 guiding principle (#11 Format Asset Sesuai Jenis, #12 Cache-Friendly), 2 metric (E11 total asset < 100 KB, E12 product image < 200 KB), 3 term Ubiquitous Language (Static Asset, Product Image, Icon Sprite), 2 risk baru (Feather sebagai "framework", PHP GD extension), dan 2 requirement PRD baru (ASSET-01, IMAGE-01).

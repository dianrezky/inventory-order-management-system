# SESSION HANDOFF — Inventory & Order Management System
**PT Neuronworks Indonesia — Intermediate Programmer Final Project**

---

**File ini berisi ringkasan lengkap sesi kerja awal proyek supaya sesi Claude berikutnya (atau developer lain) bisa langsung melanjutkan tanpa membaca ulang percakapan yang panjang.**

- **Tanggal Handoff:** 2026-09-01 (updated post-Slice 1)
- **Model:** claude-opus-4-8 (Claude Cowork) + claude-sonnet-5 (Stage 3 lanjutan + Stage 6 Slice 1)
- **Status Proyek:** Stage 8 QA in progress — siap Stage 9 Release
- **Root Folder Proyek:** `C:\laragon\www\portfolio-apps\inventory-order-management-system\`
- **App URL (dev):** http://localhost:8090 (bukan 8080 — port 8080 dipakai container lain di mesin ini, lihat `compose.yaml` / `.env`)

---

## 📌 1. TL;DR untuk Sesi Baru

**Sudah selesai:**
- ✅ **Stage 1 — Product Vision** — `docs/planning/product-vision.md` (v1.4, 583 baris)
- ✅ **Stage 2 — PRD** — `docs/planning/prd.md` (v1.0, 1.261 baris, 22 requirement + 22 BR)
- ✅ **Stage 4 — Technical Design** — `docs/architecture/` (4 ADR + class diagram + DB schema + API contract + sequence diagrams)
- ✅ **Stage 5 — Delivery Plan** — `docs/planning/delivery-plan.md` (7 slice × 2-minggu timeline)
- ✅ **Stage 6 Slice 1 Foundation** — Docker, composer, schema, seed, Auth, i18n, theme, CSS tokens, layout, navigation
  — ⚠️ **i18n (EN/ID) dan theme toggle di baris ini declared OUT OF SCOPE 2026-09-08 (CF-02, lihat
  `master-project-specification.md` §46 dan `ux-ui-spec.md` v1.3).** Kode i18next/theme lama tidak
  dihapus tapi bukan requirement yang dinilai; UI yang jadi English-only, light-theme-only.
- ✅ **Stage 6 Slice 2 Master Data** — CRUD user/product/warehouse/supplier/customer + Image upload WebP
- ✅ **Stage 6 Slice 3 Purchase Flow** — PO + Goods Receipt transactional + ledger
- ✅ **Stage 6 Slice 4 Sales Flow + ARCH-02 + BR-001** — SO + GoodsIssue dengan `SELECT FOR UPDATE` + SalesOrderPolicy segregation + 12 SO seed
- ✅ **Stage 6 Slice 5 Discovery** — Dashboard 3-role KPI + CSV Export + Product API + Low-stock CLI + Pagination Product/PO/SO
- ✅ **Stage 6 Slice 6 Quality & Docs** — Unit tests (SalesOrderPolicy, CsvExport, Dashboard), Integration tests (BR-001, ARCH-02), PHPStan level 5 clean ✅, WCAG AA dark theme fix, 4 quality docs (refactor-log, tech-debt, srp-audit, architecture-critique), README updated
- ✅ File dasar: `CLAUDE.md`, `README.md`, `.gitignore`, `ai-usage-log.md`

**Belum dikerjakan (Next):**
- 🔄 **Stage 8 QA** — Jalankan `docs/qa/qa-plan.md` (103 checks, 100% pass required)
- ⬜ **Stage 7 — Code Review** (parallel dengan QA)
- ⬜ **Stage 9 — Release**

---

## 📚 2. Konteks Proyek

### 2.1 Sumber Kebenaran
- **Project Brief** (otoritas assessment): `Project Brief - Programmer.pdf` (19 halaman, Edisi 1.0, Oktober 2026)
- **CLAUDE.md** (aturan operasional): root proyek — 53 rule
- **Vision** (arah produk): `docs/planning/product-vision.md`
- **PRD** (requirement detail): `docs/planning/prd.md`

### 2.2 Deskripsi Produk
Aplikasi web **Inventory & Order Management System** untuk perusahaan menengah dengan multi-warehouse, 3 role (Admin / Sales / WarehouseStaff), lengkap dengan Purchase Order, Sales Order, Stock Ledger, Dashboard per role, dan CSV export.

**Alur inti:** Login → Master Data → Purchase Order → Goods Receipt → Stock → Sales Order → Approval → Goods Issue → Stock Ledger → Dashboard → Logout.

### 2.3 Tech Stack (WAJIB — non-negotiable)
| Layer | Wajib | Dilarang |
|-------|-------|----------|
| Frontend | HTML, CSS, Vanilla JS, Fetch API | React, Vue, Angular, admin template, CSS framework |
| Backend | PHP 8.2+ Native OOP, Controller→Service→Repository | Laravel, Symfony, CodeIgniter, ORM, DI container framework |
| Database | MySQL 8, PDO prepared statement, transaksi eksplisit | NoSQL, query concatenation |
| Deploy | Docker + Docker Compose + Redis + Memcached | Setup yang bergantung mesin lokal peserta |

### 2.4 Nilai & Kelulusan
- **Target nilai:** ≥ 80
- **Critical failure = 0** (jika terjadi 1 saja, nilai jadi 0)
- **North Star Metric:** "Nilai ≥ 80 dengan 0 critical failure — semua alur inti dapat didemokan sepenuhnya dalam 2 bahasa & 2 tema tanpa error dari Docker bersih."

---

## 🗂️ 3. Struktur Folder yang Sudah Dibuat

```
C:\laragon\www\portfolio-apps\inventory-order-management-system\
├── CLAUDE.md                              (2.9 KB — konstitusi ringkas)
├── README.md                              (1.4 KB — placeholder)
├── .gitignore                             (513 B)
├── ai-usage-log.md                        (1.9 KB — log AI transparansi)
├── SESSION-HANDOFF.md                     (FILE INI)
├── app/
│   ├── Controller/                        (kosong — Stage 6)
│   ├── Service/                           (kosong — Stage 6)
│   ├── Repository/                        (kosong — Stage 6)
│   ├── Entity/                            (kosong — Stage 6)
│   └── Core/                              (kosong — Stage 6)
├── config/                                (kosong — Stage 6)
├── database/
│   ├── migrations/                        (kosong — Stage 6)
│   └── seeds/                             (kosong — Stage 6)
├── decisions/                             (kosong — cadangan)
├── design/                                (kosong — Stage 3)
├── docker/
│   ├── php/                               (Dockerfile PHP — Stage 6)
│   ├── mysql/                             (init script — Stage 6)
│   └── nginx/                             (config — Stage 6)
├── docs/
│   ├── planning/
│   │   ├── product-vision.md              ✅ 583 baris (v1.4)
│   │   └── prd.md                         ✅ 1.261 baris (v1.0)
│   ├── architecture/                      (kosong — Stage 4)
│   │   ├── (adr-001-i18n.md)              — to create
│   │   ├── (adr-002-concurrency.md)       — to create
│   │   ├── (adr-003-image-webp.md)        — to create
│   │   ├── (adr-004-asset-strategy.md)    — to create
│   │   ├── (class-diagram-initial.md)     — to create Stage 4
│   │   └── (class-diagram-asbuilt.md)     — to create Stage 6
│   ├── quality/                           (kosong — Stage 6)
│   │   ├── (refactor-log.md)              — to create Stage 6
│   │   ├── (tech-debt.md)                 — to create Stage 6
│   │   ├── (critique.md)                  — to create defense
│   │   └── (srp-audit.md)                 — to create Stage 6
│   └── testing/                           (kosong — Stage 6)
├── public/
│   └── assets/
│       ├── css/                           (kosong — Stage 6)
│       ├── js/                            (kosong — Stage 6)
│       └── img/                           (kosong — Stage 3/6)
├── scripts/                               (kosong — check-low-stock.php Stage 6)
├── tests/
│   ├── Unit/                              (kosong — Stage 6)
│   └── Integration/                       (kosong — Stage 6)
└── views/
    ├── layouts/                           (kosong — Stage 6)
    ├── partials/                          (kosong — Stage 6)
    ├── auth/                              (kosong — Stage 6)
    ├── master/                            (kosong — Stage 6)
    ├── purchase/                          (kosong — Stage 6)
    ├── sales/                             (kosong — Stage 6)
    └── dashboard/                         (kosong — Stage 6)
```

---

## 🎯 4. Keputusan Kunci yang Sudah Diambil User

Selama sesi ini, user memberi 4 kelompok keputusan yang **wajib diikuti** sesi berikutnya:

### 4.1 Fitur i18n (Internationalization)
| # | Pertanyaan | Keputusan User |
|---|-----------|----------------|
| 1 | Library i18n | **`i18next-core` + `i18next-http-backend`** (UMD framework-agnostic) |
| 2 | Default bahasa | **`en`** (English) — Grace jadi persona pembenar |
| 3 | Bahasa didukung | `en` + `id` saja (RTL/lain-lain: post-assessment) |
| 4 | Bonus | Auto-translate dynamic content via LibreTranslate (self-hosted Docker) |

### 4.2 Fitur Theme (Dark/Light)
| # | Pertanyaan | Keputusan User |
|---|-----------|----------------|
| 1 | Mode | **Tri-state: `auto` / `light` / `dark`** |
| 2 | Default | **`auto`** (ikut `prefers-color-scheme` OS) |
| 3 | Toggle | Siklus: `auto → light → dark → auto` |
| 4 | Storage preferensi | `localStorage['theme']` |

### 4.3 Fitur Static Asset Strategy
| # | Aspek | Keputusan User |
|---|-------|----------------|
| 1 | Icon | **Feather Icons** (SVG sprite ~30 KB, MIT) |
| 2 | Logo | SVG buatan sendiri |
| 3 | Illustration | SVG (flat) atau WebP (kompleks) — sumber: unDraw, Storyset |
| 4 | Product Image | Convert-on-upload ke WebP via **PHP GD** (server-side) |
| 5 | Total UI asset | < 100 KB (metric E11) |
| 6 | Product image tersimpan | < 200 KB rata-rata (metric E12) |

### 4.4 Persona & Vision
| # | Aspek | Keputusan User |
|---|-------|----------------|
| 1 | Jumlah persona | **4 persona:** Rita (Admin), Beni (Sales), Wawan (Warehouse), **Grace (Expat)** |
| 2 | Grace tetap dipertahankan | Ya — sebagai pembenar default locale `en` |
| 3 | Semua persona diperkaya | Environment, Tech Literacy, Frustrations, Quote, Test Proxy, Day-in-the-Life |
| 4 | Anti-persona | 5 entry: Rudi (Public Visitor), Bu Sinta (Auditor), Pak Hendra (CEO), Customer, Supplier |
| 5 | Priority Ranking | Rita/Beni/Wawan = P0; Grace = P1 |

---

## 📊 5. Ringkasan Isi Vision v1.4 (583 baris)

### Seksi Utama
| # | Seksi | Highlight |
|---|-------|-----------|
| 1 | Vision + Elevator Pitch + Positioning + Metaphor | 4-lapis identitas produk |
| 2 | Konteks & Masalah (P1–P8) | 8 pain point termasuk P7 (bahasa) & P8 (kondisi cahaya) |
| 3 | 4 Persona + Anti-Persona + Stakeholder Map | Persona P0/P1 dengan 8 lapisan detail |
| 4 | Value Prop (6) + 5 Strategic Pillars | Pillar Inclusivity ditambah |
| 5 | Scope MVP / Out / Bonus | i18n & theme MVP; auto-translate bonus |
| 6 | North Star + 3 Lapis Metric | E9-E12 metric baru untuk i18n & asset |
| 7 | 12 Guiding Principles | #9-#12 baru untuk UI inklusif & asset |
| 8 | Non-Goals + Anti-Vision | 6 anti-vision "aplikasi ini BUKAN..." |
| 9 | Ubiquitous Language | Term Stock/Ledger/Locale/Theme/Static Asset/Product Image |
| 10 | High-Level Roadmap | v1.0 → v1.1 → v2.0 |
| 11 | Risks & Assumptions | 3 kategori (teknis, proses, asumsi) |
| 12 | Definition of Done Stage 1 | Checklist |
| 13-14 | References + Preview Stage 2 | Link Brief & CLAUDE.md |

### Changelog Vision
- 1.0 → Draft awal
- 1.1 → +9 seksi Vision-level + i18n & theme
- 1.2 → Konfirmasi keputusan library/default
- 1.3 → Persona overhaul (8 lapisan × 4 persona)
- 1.4 → Static UI asset strategy (SVG + WebP)

---

## 📋 6. Ringkasan Isi PRD v1.0 (1.261 baris)

### 22 Requirement dalam 8 Domain

| Domain | Requirement | Priority |
|--------|-------------|----------|
| Auth & User | AUTH-01 (Login), AUTH-02 (Logout), USR-01 (User mgmt) | P0, P0, P1 |
| Master Data | PRD-01 (Product & Category), WH-01 (Warehouse & Multi-Stock) | P1, P1 |
| Transactions | **PO-01** (PO & Goods Receipt), **SO-01** (SO, Approval, Goods Issue) | P0, P0 |
| Views & Reporting | VIEW-01 (List/Detail/Empty), FIND-01 (Search/Filter/Sort/Pagination), DASH-01 (Dashboard/role), REPORT-01 (CSV), API-01 (JSON) | P1 x 5 |
| Cross-Cutting | VAL-01 (Validation), ERR-01 (Error), UI-01 (Responsive), DB-01 (DB & Transaksi), JOB-01 (Scheduled Script) | P2 x 5 |
| Architecture | ARCH-01 (Layered + DI), **ARCH-02 (Concurrency)** | P2, **P0** |
| UX Enhancement | I18N-01, THEME-01, ASSET-01, IMAGE-01 | P3, P3, P3, P4 |
| Bonus | I18N-02 (LibreTranslate auto-translate) | P7 |

### 22 Business Rules Highlights
- **BR-001** Sales TIDAK BOLEH approve SO sendiri (server enforcement) — segregation of duties
- **BR-002** `quantity >= 0` — DB constraint + service
- **BR-008/009** Goods Receipt & Issue transaksional (stock + ledger 1 transaksi)
- **BR-010** Dua goods issue simultan TIDAK BOLEH oversell — ARCH-02
- **BR-016** Semua SQL WAJIB prepared statement
- **BR-017** Authorization server-side (UI hiding bukan authorization)
- **BR-018** Sales scope: hanya lihat SO miliknya
- **BR-020** Semua UI text via i18n key (no hardcoded string)
- **BR-021** Kontras WCAG AA di kedua tema
- **BR-022** Product image via `finfo_file()` + resize + WebP + hash name

### Vertical Slice Sequencing (7 Slice untuk ~4 Minggu)
1. **Slice 1** Foundation (AUTH + i18n/theme/asset skeleton)
2. **Slice 2** Master Data (USR, PRD, WH, IMAGE)
3. **Slice 3** Purchase Flow (PO + Goods Receipt)
4. **Slice 4** Sales Flow + **CRITICAL ARCH-02** + **BR-001 test**
5. **Slice 5** Discovery (VIEW, FIND, DASH, REPORT, API, JOB)
6. **Slice 6** Quality & Docs (tests, static analysis, ADR, class diagram, refactor log)
7. **Slice 7** Bonus (LibreTranslate, Mailhog) — opsional

**Aturan pacing:** Jangan mulai Slice N+1 sebelum Slice N functionally complete.

---

## 🚀 7. Next Actions untuk Sesi Berikutnya

### 7.1 Prioritas Segera (Stage 3 — UX/UI Spec)
Buat di `docs/planning/ux-ui-spec.md` atau folder `design/`:

1. **Design Token** (dark + light):
   - Palette primitive (color scale)
   - Semantic tokens (primary, secondary, error, success, warning, info)
   - Typography scale (font-family, sizes, weights, line-height)
   - Spacing scale (4/8-based)
   - Border radius, shadow

2. **Component Spec** (~10-12 komponen):
   - Button (primary, secondary, tertiary, destructive)
   - Input (text, number, select, textarea, date)
   - Label + Helper text + Error text
   - Table (dengan sort/pagination)
   - Modal / Dialog
   - Empty state
   - Loader / Skeleton
   - Toast / Notification
   - Card / Stat tile
   - Badge / Chip

3. **Wireframe Halaman Utama** (Mermaid / ASCII acceptable):
   - Login
   - Dashboard Admin, Sales, Warehouse (3 varian)
   - List Product, PO, SO
   - Detail PO, SO
   - Form Create/Edit PO, SO
   - Goods Receipt modal
   - Goods Issue modal
   - Report CSV

4. **User Journey per Persona** (4 journey):
   - Rita: login → approve SO → buat PO
   - Beni: login → buat SO → submit approval → cek status
   - Wawan: login → cek antrean → goods receipt partial → goods issue
   - Grace: login (en+dark) → screen-share dengan Rita → export CSV EN

5. **Accessibility Checklist per komponen** (BR-021):
   - Contrast rasio WCAG AA
   - Keyboard nav
   - Focus visible
   - Touch target 44 px

### 7.2 Stage 4 — Technical Design (setelah Stage 3)
Buat di `docs/architecture/`:

1. **Class Diagram Initial** (Mermaid): Controller → Service → Repository → Entity
2. **Detailed DB Schema** (kolom, tipe, index, FK, constraint) → nanti jadi `database/schema.sql`
3. **ADR-001** Repository Pattern
4. **ADR-002** Concurrency Strategy (pilih Opsi A pessimistic `SELECT FOR UPDATE` vs Opsi B optimistic version) — **KEPUTUSAN PENDING**
5. **ADR-003** i18n Library Choice (justifikasi `i18next-core`)
6. **ADR-004** Product Image WebP Strategy
7. **API Contract** untuk API-01 (JSON schema)
8. **Sequence Diagram** untuk goods receipt & goods issue (menunjukkan transaksi)

### 7.3 Stage 5 — Delivery Plan
Buat di `docs/planning/delivery-plan.md`:
- Backlog task per Slice (dari §12.2 PRD)
- Estimasi waktu per task
- Definition of Done per task
- Dependency task-level

---

## ❓ 8. Open Questions untuk User

Sesi baru **wajib** menanyakan ini dulu ke user:

1. **Timeline aktual:** Deadline final submission peserta kapan? (Sesi ini asumsi 4 minggu — perlu konfirmasi.)
2. **ARCH-02 strategy:** Pilih **Opsi A (pessimistic row lock `SELECT FOR UPDATE`)** atau **Opsi B (optimistic version column)**? (Rekomendasi saya: Opsi A karena lebih mudah dijelaskan & sudah teruji di MySQL InnoDB. Detail keputusan masuk ADR-002.)
3. **Composer libraries yang boleh:** i18next di frontend adalah CDN/npm. Untuk backend PHP, ada library kecil yang mau dipakai? Rekomendasi minimal: composer autoload (PSR-4), PHPUnit, PHPStan. Confirmed?
4. **Design system inspiration:** Ada preferensi visual (minimal / material-like / Feather-Icons-native)? Ini pengaruh ke Stage 3 wireframe.
5. **Deployment target:** Docker Compose lokal saja (Brief), atau ada rencana push ke cloud?

---

## 📜 9. Sejarah Percakapan (Ringkasan Per Giliran)

| # | Giliran User (ringkas) | Tindakan Claude (ringkas) |
|---|-------------------------|---------------------------|
| 1 | Upload PDF Brief, "pahami dulu" | Baca 19 halaman, ringkas keseluruhan brief |
| 2 | Provide full CLAUDE.md rules (53 pasal) | Konfirmasi pemahaman rules |
| 3 | "Mulai dari Stage 1" | Buat struktur folder + Vision v1.0 (265 baris) |
| 4 | "Jelaskan tiap poin Vision" | Jelaskan 12 seksi Vision dengan bahasa mudah |
| 5 | "Apa yang perlu ditambahkan di Vision?" | Rekomendasikan 15 item potensial di Vision level |
| 6 | "Masih bahas vision dulu, kira2 apa yang perlu ditambahkan?" | Filter jadi 9 item Vision-specific |
| 7 | "Boleh tambahkan i18n + dark/light theme" | Analisis constraint + Update Vision v1.1 dengan i18n + theme + 9 seksi baru |
| 8 | Jawab: 1=i18next-core, 2=en, 3=auto, 4=belum paham persona | Update Vision v1.2 + jelaskan fungsi persona |
| 9 | "Saran anda apa untuk persona?" | 8 saran (Environment, Tech Literacy, Frustrations, Priority, Anti-Persona, Quote, Test Proxy, Day-in-Life) |
| 10 | "Masukin semua saran" | Update Vision v1.3 dengan persona overhaul total (383 → 568 baris) |
| 11 | "Bagaimana kalau gambar asset webp?" | Analisis WebP untuk product upload |
| 12 | "Oh maksudnya asset UI awal apps" | Klarifikasi + rekomendasi Feather Icons SVG + WebP illustration |
| 13 | "Masukin ke vision" | Update Vision v1.4 dengan Static UI Asset strategy (2 principle, 2 metric, 3 term, 2 risk, 2 PRD baru) |
| 14 | "Lanjut PRD" | Buat PRD v1.0 (1.261 baris, 22 requirement + 22 BR + dependency graph) |
| 15 | "Taro semua chat dalam 1 file MD" | **File ini** — SESSION-HANDOFF.md |

---

## 🎯 10. Cara Sesi Baru Melanjutkan

### Petunjuk untuk Sesi Claude Berikutnya

1. **Baca urutan dokumen ini:**
   - `SESSION-HANDOFF.md` (ini) — konteks lengkap
   - `CLAUDE.md` — 53 aturan operasional
   - `docs/planning/product-vision.md` v1.4 — arah produk
   - `docs/planning/prd.md` v1.0 — 22 requirement + 22 BR
   - `Project Brief - Programmer.pdf` — otoritas assessment (baca ulang bila ragu)

2. **Sebelum mulai bekerja, konfirmasi ke user:**
   - "Saya sudah baca SESSION-HANDOFF.md — status Stage 2 selesai, siap Stage 3. Betul?"
   - Tanyakan 5 open questions di §8 di atas kalau belum terjawab.
   - Tanyakan mau langsung Stage 3 atau ada revisi Vision/PRD dulu.

3. **Ikuti Rule #45 CLAUDE.md — Implementation Priority:**
   P0 Critical → P1 Mandatory → P2 Architecture → P3 Security → P4 Testing → P5 Documentation → P6 UX Polish → P7 Bonus

4. **Setiap deliverable wajib update:**
   - `ai-usage-log.md` — catatan penggunaan AI
   - Changelog di dokumen yang bersangkutan
   - `SESSION-HANDOFF.md` ini (kalau sesi berakhir dengan progress signifikan)

5. **Jangan lupa:**
   - Rule #52 Stop Conditions — ajukan pertanyaan kalau ambigu, jangan menebak
   - Rule #53 Golden Rule — Correctness > Compliance > Security > Data Integrity > Testability > Maintainability > UX > Bonus
   - Rule #40 Out of Scope — jangan tambah microservices, framework, ORM, cron, dsb.

### Petunjuk untuk Developer Manusia

Kalau seorang developer (bukan Claude) melanjutkan proyek ini:

1. **Buka folder proyek:** `C:\laragon\www\portfolio-apps\inventory-order-management-system\`
2. **Baca urutan:** `SESSION-HANDOFF.md` (ini) → `CLAUDE.md` → `docs/planning/product-vision.md` → `docs/planning/prd.md` → `Project Brief - Programmer.pdf`
3. **Cek keputusan yang sudah diambil** di §4 di atas — jangan ubah kecuali ada alasan kuat & sudah didokumentasikan di ADR.
4. **Ikuti vertical slice** di §12.2 PRD — jangan lompat langsung ke SO-01 sebelum PO-01 stable.
5. **Update `ai-usage-log.md`** setiap sesi bantuan AI, sesuai §6.2 Brief.

---

## 📎 11. Referensi Lengkap

- **Project Brief:** `Project Brief - Programmer.pdf` (Edisi 1.0, Oktober 2026, 19 halaman, 93.1 KB)
- **CLAUDE.md:** root proyek
- **Vision v1.4:** `docs/planning/product-vision.md` (583 baris)
- **PRD v1.0:** `docs/planning/prd.md` (1.261 baris)
- **Stage 4 Bundle:** `docs/architecture/` (README + 4 ADR + class diagram + DB schema + API contract + sequence diagrams)
- **Stage 5 Delivery Plan:** `docs/planning/delivery-plan.md` (7 slice × 2-minggu timeline)
- **Add-on Stage 4 docs:**
  - `docs/architecture/README.md` — overview & highlight keputusan
  - `docs/architecture/adr-001-repository-pattern.md`
  - `docs/architecture/adr-002-concurrency-strategy.md` ← ARCH-02 P0
  - `docs/architecture/adr-003-i18n-library.md`
  - `docs/architecture/adr-004-image-webp-strategy.md`
  - `docs/architecture/class-diagram-initial.md`
  - `docs/architecture/db-schema-design.md`
  - `docs/architecture/api-contract.md`
  - `docs/architecture/sequence-diagrams.md`
- **i18n library:** [i18next.js](https://www.i18next.com/) + [i18next-http-backend](https://github.com/i18next/i18next-http-backend)
- **Icon library:** [Feather Icons](https://feathericons.com/) (MIT license)
- **Illustration source:** [unDraw](https://undraw.co/), [Storyset](https://storyset.com/)
- **LibreTranslate (Bonus):** [libretranslate.com](https://libretranslate.com/) — self-hosted Docker

---

## 🚀 12. Status Update Post-Stage 4 (2026-09-01)

### 12.1 Keputusan Tambahan Sesi Ini

| # | Topik | Keputusan |
|---|-------|-----------|
| 1 | Timeline | **~2 minggu** (TIGHT — fokus ruthless) |
| 2 | Stage 3 UX/UI Spec | **Di-skip dulu** (timeline ketat, fokus implementasi) |
| 3 | ARCH-02 Concurrency | **Opsi A — Pessimistic `SELECT FOR UPDATE`** (dokumentasi di ADR-002) |
| 4 | Composer libs | autoload PSR-4 + PHPUnit + PHPStan + PhpDotEnv + Faker |
| 5 | Design system | Hybrid (feather-like + material cards + functional) |
| 6 | Deployment | Cloudflare Pages / no VM (backend tetap Docker Compose) |

### 12.2 Deliverable Stage 4

1. **4 ADR** (Architecture Decision Records)
   - ADR-001 — Repository Pattern (3 lapis + interface + DI manual)
   - ADR-002 — Concurrency Strategy (Pessimistic FOR UPDATE) ← **P0 Critical**
   - ADR-003 — i18n Library (i18next-core UMD + PHP shared source)
   - ADR-004 — Image WebP Strategy (PHP GD pipeline)

2. **Class Diagram Initial** — Mermaid classDiagram untuk Controller / Service / Repository / Entity + relationships. Akan di-update ke `class-diagram-asbuilt.md` di akhir Stage 6.

3. **DB Schema Design** — 12 tabel MySQL 8 InnoDB dengan:
   - FK (semua dengan nama eksplisit)
   - Unique & composite index untuk query patterns
   - CHECK constraints (BR-002 quantity>=0, dll.)
   - ENUM untuk status & role
   - Demo seed plan (4 users, 30 products, 2-3 warehouses, dll.)

4. **API Contract** — `GET /api/products/{sku}/availability` dengan full request/response spec (200/400/401/404).

5. **Sequence Diagrams** — Goods Receipt, Goods Issue, SoD scenarios dengan Mermaid (termasuk ARCH-02 concurrent scenario).

### 12.3 Highlight untuk Assessor

**Dua critical-failure-critical items sudah terdokumentasi dengan baik:**

1. **ARCH-02** — `SELECT ... FOR UPDATE` di dalam transaction; sequence diagram menunjukkan 2-thread concurrent issue.
2. **BR-001 Segregation of Duties** — `SalesOrderPolicy::assertCanBeApprovedBy()` cek `actor !== createdBy`; sequence diagram menunjukkan Beni cURL approve SO sendiri → 403.

### 12.4 Next Actions (Stage 6 Slice 5+)

**Already complete (this session):**
- ✅ Slice 1: Foundation (Docker, composer, schema, Auth, i18n, theme)
- ✅ Slice 2: Master Data (CRUD user/product/warehouse/supplier/customer + image upload)
- ✅ Slice 3: Purchase Flow (PO + Goods Receipt transactional)
- ✅ Slice 4: Sales Flow + ARCH-02 + BR-001 (SO + GoodsIssue `SELECT FOR UPDATE`)

**Next to implement:**
1. **Slice 5 — Discovery** (H9, ~10 jam) ✅ DONE
   - FIND-01: search + filter + sort + pagination ✅
   - DASH-01: dashboard 3 role dengan real-time KPI query ✅
   - REPORT-01: CSV export EN/ID header ✅
   - API-01: `GET /api/products/{sku}/availability` ✅
   - JOB-01: `scripts/check-low-stock.php` ✅

2. **Slice 6 — Quality & Docs** (H10, ~10 jam)
   - 6+ unit tests + 3+ integration tests (ARCH-02 concurrency + BR-001)
   - PHPStan level 5 clean
   - WCAG AA contrast check
   - 4 quality docs (refactor-log, tech-debt, srp-audit, critique)
   - README final + ai-usage-log.md finalisasi
   - **DoD:** Clean Docker rebuild → demo end-to-end
   - 6+ unit tests + 3+ integration tests (ARCH-02 concurrency + BR-001)
   - PHPStan level 5 clean
   - WCAG AA contrast check
   - 4 quality docs (refactor-log, tech-debt, srp-audit, critique)
   - README final + ai-usage-log.md finalisasi
   - **DoD:** Clean Docker rebuild → demo end-to-end
   - Setup Docker, composer, schema.sql, seed.sql
   - `app/Core/` (Database, Container, SessionManager, LocaleResolver, Translator)
   - `app/Repository/{Interface,MySQL,Fake}/UserRepository*`
   - `app/Service/AuthService` + `app/Controller/AuthController` + login view
   - i18next-core vendor + locales EN/ID
   - Theme toggle + CSS tokens light/dark
   - **DoD:** Login 3 role → dashboard placeholder masing-masing

2. **Slice 2 — Master Data** (Hari 3-4, ~16-20 jam)
   - CRUD user, product, category, warehouse, supplier, customer
   - Image upload pipeline (WebP via GD)
   - Authorization guard (Sales/Warehouse 403)
   - **DoD:** Admin dapat CRUD master, Sales/Warehouse read-only

3. **Slice 3 — Purchase Flow** (Hari 5-6, ~16-20 jam)
   - PO Draft → Ordered → Goods Receipt (full & partial)
   - Stock + ledger transactional
   - **DoD:** PO end-to-end + invariant test pass

4. **Slice 4 — Sales Flow + CRITICAL** (Hari 7-8, ~16-20 jam) ⚠️
   - SO Draft → PendingApproval → Approved → Goods Issue → Fulfilled
   - **ARCH-02: `SELECT FOR UPDATE` + concurrency test**
   - **BR-001: Sales cannot approve own SO (server-side)**
   - **DoD:** 3 integration tests passing + demo ready

5. **Slice 5 — Discovery & Reporting** (Hari 9, ~10 jam)
   - Search/filter/sort/pagination
   - Dashboard 3 role (real-time query)
   - CSV export (EN/ID header)
   - JSON API endpoint
   - `scripts/check-low-stock.php`

6. **Slice 6 — Quality & Docs** (Hari 10, ~10 jam)
   - 6+ unit tests + 3+ integration tests
   - PHPStan level 5
   - WCAG AA contrast check
   - 4 quality docs (refactor-log, tech-debt, srp-audit, critique)
   - README final + ai-usage-log.md finalisasi

7. **Slice 7 — Bonus** (Hari 11+, opsional)
   - LibreTranslate (I18N-02), Mailhog, audit trail, dashboard charts

8. **Stage 8 QA** (Hari 11-12, ~8 jam) ← **NEXT**
   - Jalankan `docs/qa/qa-plan.md` — 103 checks, 100% pass required
   - Critical: BR-001 self-approve → 403, ARCH-02 stock never negative
   - Security: SQL/CSV injection, CSRF, XSS
   - Full E2E demo: clean Docker rebuild → Login Rita → PO → GR → SO Beni → Rita approve → Wawan issue

**Detail per task:** Lihat `docs/planning/delivery-plan.md` §8 + `docs/qa/qa-plan.md`.

**Critical path:**
S1 → S2 → S3 → S4 (jeda jika S4 belum pass ARCH-02/BR-001) → S5 → S6 → **Stage 8 QA → Stage 9 Release**

**Environment note (Laragon Windows):**
- `pcntl_fork` mungkin tidak tersedia — pakai sequential simulation untuk ARCH-02 test
- Docker Desktop wajib untuk setup container

---

## 🎨 14. Status Update — Stage 3 Selesai (2026-09-01)

Stage 3 sebelumnya di-skip (§12.1), lalu diminta ulang oleh user dan diselesaikan penuh dengan pendekatan **hybrid Figma + Markdown**.

### 14.1 Kenapa hybrid
Awalnya full Stage 3 dikerjakan via agent di Figma MCP (`use_figma`). Design Tokens dan 8 dari 12 komponen selesai dan lolos verifikasi visual, lalu file Figma (plan **Starter**) kena **hard rate limit tool-call** — dikonfirmasi bukan cooldown sementara (di-retry setelah jeda waktu nyata, tetap gagal dengan error yang sama). Sisanya (Wireframes, User Journeys, Accessibility Checklist) dipindah ke dokumen Markdown supaya Stage 3 tuntas tanpa tergantung upgrade plan Figma.

### 14.2 Deliverable
1. **Figma file:** https://www.figma.com/design/mYFuerq3bu6tWbjAHsM4in
   - Page `0:1` "01 - Foundations" → Section "00 - Overview" (`2:6`) ✅, "01 - Design Tokens" (`3:13`) ✅, "02 - Components" (`3:120`) 🟡 8/12 (Button, Badge/Chip, Input, Form Field, Stat Tile, Table, Modal/Dialog, Empty State — **belum**: Toast/Notification, Skeleton/Loader)
   - Page `2:4` "02 - Wireframes" — kosong (digantikan dokumen Markdown, lihat poin 2)
   - Page `2:5` "03 - Journeys & Accessibility" — kosong (digantikan dokumen Markdown)
   - Variable collections: `Color Tokens - Light` (`2:23`), `Color Tokens - Dark` (`2:24`), `Spacing` (`2:47`), `Radius` (`2:55`) — plan Starter membatasi 1 mode/collection, jadi light & dark dipisah jadi 2 collection, bukan 1 collection 2-mode
2. **`docs/planning/ux-ui-spec.md`** (v1.1, **single source of truth** — tidak lagi bergantung ke Figma sama sekali):
   - §1 Design Tokens (nilai hex final, sudah diverifikasi ulang dengan hitungan WCAG relative-luminance — 3 warna status light-theme awal gagal kontras 3.19–3.30:1, sudah diganti jadi shade lebih gelap sampai lolos ≥4.5:1, angka lama sengaja dicatat di dokumen supaya tidak diganti balik)
   - §2 Component Spec — **semua 12 komponen fully specced langsung di sini** (bukan cuma rujukan ke Figma lagi — 8 yang tadinya "reference Figma" sudah ditarik penuh jadi tabel CSS-ready, karena Figma dikonfirmasi masih rate-limited saat dicoba lagi)
   - §3 Wireframe 14 layar (ASCII, desktop-first 1440px canvas)
   - §4 User Journey 4 persona (Mermaid flowchart)
   - §5 Accessibility Checklist per komponen (BR-021)

### 14.3 Catatan untuk Stage 6
- Nilai token final ada di `docs/planning/ux-ui-spec.md` §1 — pakai ini sebagai sumber kebenaran untuk CSS variables (bukan re-derive dari Figma, karena Figma belum lengkap).
- BR-001 (Sales tidak boleh approve SO sendiri) sudah didetailkan di wireframe §3.9 — tombol Approve disabled + tooltip untuk viewer=creator, TAPI backend WAJIB tetap reject independen (403) sesuai ADR-002/sequence diagram Stage 4.
- Kalau plan Figma di-upgrade nanti, Toast + Skeleton component dan 2 page kosong (Wireframes, Journeys & Accessibility) di Figma bisa diselesaikan sebagai transkripsi opsional dari dokumen Markdown — tidak wajib untuk lanjut implementasi.

---

## 🏗️ 15. Status Update — Stage 6 Slice 1 (Foundation) Selesai (2026-09-01)

### 15.1 Ringkasan
Slice 1 selesai penuh dan terverifikasi end-to-end via `docker compose up` clean rebuild — bukan cuma ditulis, tapi benar-benar dijalankan dan dites (curl login 4 user, cek DB, PHPStan, PHPUnit). Detail lengkap ada di `ai-usage-log.md` (perlu ditambahkan entry) dan riwayat sesi; ringkasan teknis di bawah.

### 15.2 File yang dibuat (per area)
- **Docker/infra:** `compose.yaml` (+ Redis 7-alpine + Memcached 1.6-alpine containers), `Dockerfile` (+ PECL redis + memcached extensions), `docker/mysql/README.md`, `.env`
- **Composer/config:** `composer.json` (PSR-4 `App\`→`app/`), `phpstan.neon` (level 5), `phpunit.xml`
- **Schema:** `database/schema.sql` (13 tabel lengkap FK/CHECK/index), `database/seed.sql` (4 user, 30 products, 12 SO, warehouse/customer/supplier seed)
- **Core:** `app/Core/{Database,Container,SessionManager,LocaleResolver,Translator,CacheService}.php`
- **Entity:** `app/Entity/User.php`, `app/Entity/Role.php` (native PHP enum)
- **Infrastructure cache:** Redis 7 — PHP sessions; Memcached 1.6 — translation JSON + application data
- **Repository:** `app/Repository/{Interface,MySQL,Fake}/UserRepository*`
- **Service:** `app/Service/AuthService.php` (tidak ada `new PDO()` — sudah di-grep, 0 hasil, sesuai ARCH-01)
- **Controller:** `app/Controller/{BaseController,AuthController,DashboardController}.php`
- **Views:** `views/layouts/main.php`, `views/auth/login.php`, `views/dashboard/index.php`
- **Front controller:** `public/index.php` (router manual)
- **Assets:** `public/assets/css/{tokens,main}.css` (token persis dari `ux-ui-spec.md` §1.1), `public/assets/js/{theme,i18n-init}.js`, `public/assets/vendor/{i18next.min.js,i18nextHttpBackend.min.js}` (build npm asli via jsdelivr — bukan substitusi), `public/assets/locales/{en,id}/translation.json`, `public/assets/img/icons.svg` (Feather Icons asli, 12 symbol)
- **Tests:** `tests/Unit/AuthServiceTest.php` (6 test, semua pass)

### 15.3 Definition of Done — hasil
Semua item DoD Slice 1 di `delivery-plan.md` §3 **PASS**, kecuali:
- [ ] Commit + tag `slice-1-foundation` — **belum dibuat**, sengaja tidak di-commit otomatis (aturan: hanya commit kalau diminta eksplisit). **Next session/user perlu commit manual** kalau mau checkpoint di git.

Theme toggle & locale toggle client-side (`localStorage`) sudah diimplementasi tapi baru diverifikasi statis/via curl (`?lang=id` works), **belum dites di browser sungguhan** (siklus auto→light→dark, WCAG focus state) — jadi TODO ringan sebelum Slice 6 QA.

### 15.4 Deviasi dari spec (semua beralasan, sudah didokumentasikan agent)
1. Docker: 1 container PHP built-in server (`php -S`, bukan php-fpm+nginx) untuk reliability di Windows/Laragon sesuai saran delivery-plan.md §10. MySQL init pakai native `docker-entrypoint-initdb.d` mount (bukan `docker/mysql/init.sql` terpisah).
2. **Port aplikasi 8090, bukan 8080** — 8080 sudah dipakai container Airflow lain di mesin ini. Override via `APP_PORT` env kalau perlu.
3. Bug ditemukan & diperbaiki saat verifikasi: PHP built-in server dengan router script me-routing SEMUA request (termasuk static asset) ke `index.php` → 404 semua CSS/JS/SVG. Fix: cek `PHP_SAPI === 'cli-server'` + `file_exists` di awal `index.php`, return `false` supaya built-in server serve file langsung. Sudah diverifikasi ulang, semua asset 200.

### 15.5 Kredensial seed (dev only — jangan pernah dipakai di luar demo)

| Email | Password | Role |
|---|---|---|
| rita@example.com | admin123 | Admin |
| beni@example.com | sales123 | Sales |
| wawan@example.com | wh123 | WarehouseStaff |
| grace@example.com | grace123 | Sales |

### 15.6 Catatan untuk Slice 2
- `Container.php` baru wiring User/Auth — perlu diperluas untuk Product/Warehouse/Supplier/Customer/Category repo+service sesuai ADR-001.
- Sebelum `docker compose up`, copy `.env` → `.env` dulu (README final di Slice 6 wajib sebut ini eksplisit).
- Browser-test theme/locale toggle (auto→light→dark cycle, focus ring WCAG) belum dilakukan — masukkan ke checklist Slice 6 §6.1 UI-01 polish kalau belum tercakup.

---

## 🏗️ 16. Status Update — Stage 6 Slice 2 (Master Data) Selesai (2026-09-01)

### 16.1 Ringkasan
CRUD lengkap untuk User/Product/Warehouse/Supplier/Customer, upload gambar produk ke WebP, authorization 403 server-side untuk Sales/Warehouse (bukan cuma sembunyiin tombol), dan seed data lengkap (30 produk, 5 supplier, 5 customer, stock+ledger 60 baris, invariant BR-015 terverifikasi 0 mismatch).

### 16.2 File yang dibuat (per area)
- **Entity:** `app/Entity/{Category,Product,Warehouse,Supplier,Customer}.php`
- **Repository:** Interface+MySQL+Fake untuk 5 entity baru; `UserRepository*` diperluas (`findAll`, `emailExists`, `create`, `update`, `updatePassword`, `setActive`)
- **Service:** `app/Service/{UserService,CategoryService,ProductService,WarehouseService,SupplierService,CustomerService,ImageUploadService}.php` + `ImageUploadResult.php` + `Exception/InvalidImageException.php`
- **Controller:** `app/Controller/{UserController,ProductController,WarehouseController,SupplierController,CustomerController}.php`
- **Wiring:** `Container.php` diperluas; `public/index.php` router di-generalisasi untuk path `{id}`
- **Views:** `views/master/{users,products,warehouses,suppliers,customers}/{list,form,detail}.php` (15 file), nav role-aware di `layouts/main.php`
- **i18n:** kedua locale (`en`/`id`) diperluas sinkron (diverifikasi programatis, 0 key hilang di kedua arah)
- **Data:** `database/seed.sql` — 4 kategori, 30 produk, 5 supplier, 5 customer, 60 `product_stocks` + 60 `stock_ledger` (idempotent)
- **Tests:** `tests/Integration/UserCreationTest.php` (HTTP real ke Docker + MySQL nyata)

### 16.3 Definition of Done — hasil
Semua item DoD Slice 2 (`delivery-plan.md` §4) **PASS**: CRUD Admin, image→WebP, Sales/Warehouse read-only + 403 server-side (verified via curl, bukan cuma UI hiding), empty state, seed sesuai spec, PHPUnit (8 test) + PHPStan level 5 clean.

### 16.4 Verifikasi nyata yang dilakukan
- curl RBAC 403 test untuk products/warehouses/users sebagai Sales & WarehouseStaff
- Full cycle create→edit→deactivate→reactivate untuk kelima entity
- Upload gambar asli → WebP 300×200 valid (194 bytes); negative test: file `.jpg` palsu ditolak server-side, tidak ada row DB tercipta
- BR-019 (produk nonaktif hilang dari dropdown create) dikonfirmasi via service call langsung
- Invariant stok (`product_stocks.quantity == SUM(stock_ledger.qty)`) dikonfirmasi 0 mismatch di 60 baris

### 16.5 Deviasi dari spec
- Tidak ada `CategoryController`/UI standalone — sesuai instruksi awal (kategori dikelola via seed, dropdown active-only di form produk, BR-019).
- `ImageUploadService` ikut ADR-004 persis (1200px/2MB/quality 82), bukan estimasi "800px" di ringkasan task — ADR jadi sumber kebenaran.

### 16.6 Catatan penting untuk sesi berikutnya
- Lanjut ke Slice 3 — Purchase Flow (PO + Goods Receipt).

---

## 🔒 17. Git Commit & Security Fix (2026-09-01)

### 17.1 Commit pertama proyek + insiden `.gitignore`
Sesi ini membuat **commit pertama** proyek (sebelumnya seluruh folder untracked sejak Stage 1 — risiko kehilangan kerja). Saat commit, ketahuan root `.gitignore` monorepo (`portfolio-apps/.gitignore`) punya rule global `*.md` dan `*.sql` yang meng-ignore SEMUA dokumen planning/architecture + `database/schema.sql`/`seed.sql` proyek ini — persis insiden yang sudah pernah terjadi untuk project `xyra-code` (dicatat di komentar `.gitignore` itu sendiri). Ditambal dengan pengecualian sempit `!inventory-order-management-system/**/*.md` dan `!inventory-order-management-system/**/*.sql`, lalu docs+schema di-commit terpisah.

Juga ada 2 file sampah (`nul`, `0`, dan kemudian `get('_csrf_token')`) yang sempat kebuat dari command shell yang salah escape saat sesi verifikasi berjalan — semua sudah dibersihkan sebelum commit final.

### 17.2 Security review otomatis setelah commit
Hook review keamanan otomatis jalan setelah commit pertama, temukan 2 celah nyata (diverifikasi manual, bukan asumsi):

1. **Stale session setelah deactivation** — `AuthService::currentUser()` tidak cek `isActive`, jadi user yang di-nonaktifkan Admin tetap punya akses penuh sampai session expired natural. **Fixed**: sekarang cek `isActive`, `destroy()` session kalau user sudah nonaktif.
2. **CSRF token tidak ada** — semua form POST (login, create/edit/deactivate 5 entity) tidak ada proteksi CSRF token (cuma `SameSite=Lax` cookie, proteksi parsial). **Fixed**: token per-session (`BaseController::csrfToken()`/`requireCsrf()`, `hash_equals()`), di semua form + controller action, token baru di-generate ulang tiap login. Diverifikasi nyata: POST tanpa token → 400 + tidak ada row DB tercipta; POST dengan token benar → sukses.

Kedua fix sudah di-commit (`144d5805`), PHPStan level 5 tetap clean, PHPUnit tetap pass (8 test, 22 assertion setelah update test CSRF).

### 17.3 Catatan untuk Slice 3+
- **Setiap form POST baru di Slice 3-5 (PO, Goods Receipt, SO, Goods Issue, dll.) WAJIB pakai `$this->requireCsrf()` + hidden input `_csrf_token`** — ikuti pola yang sudah ada di controller/view Slice 2, jangan lupa ini saat bikin form baru.
- Commit setelah setiap slice selesai — jangan biarkan menumpuk uncommitted lagi.

---

## 📝 13. Changelog Handoff

- **1.0 · 2026-09-01** — Draft awal. Ringkas seluruh sesi pertama proyek — dari upload Brief sampai selesai PRD Stage 2.
- **1.1 · 2026-09-01** — Update post-Stage 4. Tambah keputusan tambahan (timeline 2-minggu, ARCH-02 Opsi A, skip Stage 3). Tambah referensi ke `docs/architecture/*`. Tambah §12 status update.
- **1.2 · 2026-09-01** — Update post-Stage 5. Tambah `docs/planning/delivery-plan.md`. Next: Stage 6 Implementation mulai dari Slice 1.
- **1.3 · 2026-09-01** — Stage 6 Slice 4 selesai. Sales Order + ARCH-02 `SELECT FOR UPDATE` + BR-001 server-side segregation. Next: Slice 5 Discovery.
- **1.4 · 2026-09-01** — Stage 6 Slice 5 selesai. Dashboard 3-role KPI (Admin/Sales/Warehouse), CSV Export (REPORT-01 RFC 4180), Product Availability API (API-01), Low-stock CLI (JOB-01), Pagination (FIND-01) untuk Products/Purchase Orders/Sales Orders.
- **1.3 · 2026-09-01** — Stage 3 selesai (hybrid Figma + Markdown) setelah sebelumnya di-skip. Tambah `docs/planning/ux-ui-spec.md` dan §14. Next: Stage 6 Implementation mulai dari Slice 1 — semua open question §8 sudah terjawab.
- **1.4 · 2026-09-01** — Stage 6 Slice 1 (Foundation) selesai & terverifikasi end-to-end (Docker clean rebuild, login 4 role, PHPStan clean, PHPUnit pass). Tambah §15. Next: Slice 2 — Master Data.
- **1.5 · 2026-09-01** — Stage 6 Slice 2 (Master Data) selesai & terverifikasi (CRUD 5 entity, image→WebP, RBAC 403 server-side, seed 30 produk). Tambah §16. **Peringatan: proyek belum pernah di-commit ke git.** Next: Slice 3 — Purchase Flow (setelah commit).

---

*Dokumen ini adalah **kontrak antar-sesi**. Setiap perubahan besar pada arah proyek (vision, scope, keputusan tech) WAJIB direfleksikan di sini agar sesi berikutnya tidak salah langkah.*

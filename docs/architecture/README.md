# Technical Design — Stage 4 Overview

- **Status:** In Progress
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Owner:** Peserta Program Pengembangan Kompetensi Programmer

---

## Tujuan Stage 4

Menerjemahkan PRD §22 requirement + 22 Business Rules menjadi **blueprint teknis** yang siap di-implement. Output: ADRs, class diagram, DB schema, API contract, sequence diagrams.

## Daftar Dokumen dalam Stage 4

### ADR (Architecture Decision Records)

| File | Topik | Status |
|------|-------|--------|
| [adr-001-repository-pattern.md](./adr-001-repository-pattern.md) | 3-lapis Controller→Service→Repository + interface + DI manual | Accepted |
| [adr-002-concurrency-strategy.md](./adr-002-concurrency-strategy.md) | ARCH-02 — Pessimistic `SELECT ... FOR UPDATE` | Accepted |
| [adr-003-i18n-library.md](./adr-003-i18n-library.md) | i18next-core + http-backend UMD + shared PHP/JS source | Superseded — I18N-01 OUT OF SCOPE (2026-09-08, CF-02); no EN/ID toggle shipped |
| [adr-004-image-webp-strategy.md](./adr-004-image-webp-strategy.md) | PHP GD + imagewebp quality 82 + 16-char hash name | Accepted |

### Diagrams & Specs

| File | Topik |
|------|-------|
| ~~`class-diagram-initial.md`~~ | Moved to `../planning/class-diagram-initial.md` (per brief §9 DESIGN-01 AC1) |
| [class-diagram-asbuilt.md](./class-diagram-asbuilt.md) | Mermaid classDiagram — final implementation snapshot (2026-09-16) |
| [db-schema-design.md](./db-schema-design.md) | 12-tabel MySQL 8 InnoDB schema dengan FK, index, CHECK constraints |
| [api-contract.md](./api-contract.md) | API-01 — `GET /api/products/{sku}/availability` JSON contract |
| [sequence-diagrams.md](./sequence-diagrams.md) | Goods Receipt / Goods Issue / SoD scenarios dengan Mermaid sequence |

### To Be Created (Stage 6 → Stage 9)

- ~~`class-diagram-asbuilt.md`~~ — **CREATED** (2026-09-02, updated 2026-09-16)
- Migrations version logs (di `database/migrations/`)

---

## Keputusan Paling Kritis (Highlight untuk Assessor)

### 1. ARCH-02 Concurrency (P0 — Critical Failure if wrong)

**Dipilih: Pessimistic `SELECT ... FOR UPDATE`** (Opsi A).

Mekanisme singkat:
1. `BEGIN TRANSACTION`
2. `SELECT quantity FROM product_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE`
3. Validate quantity cukup
4. `UPDATE product_stocks SET quantity = quantity - N WHERE ...`
5. `INSERT INTO stock_ledger (type='Issue', qty=-N, ref=SO/...)`
6. `UPDATE sales_orders SET status='Fulfilled' WHERE id=?`
7. `COMMIT`

Demo ke assessor (Brief §8.1): 2 request paralel → 1 sukses, 1 gagal, stok akhir valid.

Lihat: [adr-002-concurrency-strategy.md](./adr-002-concurrency-strategy.md) + [sequence-diagrams.md §2](./sequence-diagrams.md#2-goods-issue-so--stock-out).

### 2. BR-001 Segregation of Duties (P0 — Critical Failure if wrong)

**Sales TIDAK BOLEH approve SO miliknya sendiri, enforced SERVER-side.**

Mekanisme: `SalesOrderPolicy::assertCanDecide(bool $isActorAdmin)` — throws `SalesApprovalForbiddenException`
jika bukan Admin. Admin boleh approve order miliknya sendiri. Sales tidak pernah boleh approve order apapun.
Authorization check via `PermissionService` (`sales_orders.approve` key), tidak ada raw role check di controller.

Bukti: unit test BR-001 + Beni dengan cURL ke endpoint approve → 403.

Lihat: [../planning/class-diagram-initial.md SalesOrderPolicy](../planning/class-diagram-initial.md) + [sequence-diagrams.md §2.3](./sequence-diagrams.md).

### 3. ARCH-01 Layered + Dependency Inversion

**3 lapis**: Controller → Service → Repository. Service TIDAK BOLEH `new PDO()`. Repositories punya interface + 2 implementasi (MySQL + Fake).

Enforcement: `grep 'new PDO' app/Service/` harus 0 hits. Composition root di `Container.php`.

Lihat: [adr-001-repository-pattern.md](./adr-001-repository-pattern.md).

### 4. Transaksional Multi-Tabel (BR-008/009)

Goods Receipt & Goods Issue selalu all-or-nothing via explicit `PDO::beginTransaction()` + `commit()` / `rollBack()`. Stock + ledger + parent status update → SATU transaksi.

---

## Hubungan ke PRD

| PRD § | Menjadi di Stage 4 |
|-------|---------------------|
| §1 BR-001 s/d BR-022 | Enforcement point di ADR-001 + service diagram |
| §2 High-Level Data Model | [db-schema-design.md](./db-schema-design.md) full detail |
| §9 ARCH-01 | [adr-001](./adr-001-repository-pattern.md) + [../planning/class-diagram-initial.md](../planning/class-diagram-initial.md) |
| §9 ARCH-02 | [adr-002](./adr-002-concurrency-strategy.md) + [sequence-diagrams](./sequence-diagrams.md) |
| §10 I18N-01 | [adr-003](./adr-003-i18n-library.md) |
| §10 IMAGE-01 | [adr-004](./adr-004-image-webp-strategy.md) |
| §7 API-01 | [api-contract.md](./api-contract.md) |
| §6 PO-01 + §6 SO-01 | [sequence-diagrams.md](./sequence-diagrams.md) |

---

## Risks & Open Questions

### Risks (dari Vision yang masuk di sini)

| Risk | Mitigation |
|------|-----------|
| `SELECT FOR UPDATE` lock contention saat simultaneous issue | Lock hold < 50ms = throughput aman (lihat §4 sequence-diagrams); tested |
| WebP via GD preservation EXIF | Lost metadata acceptable untuk product image (Vision §11) |
| i18next FOUT flash | Pre-set `<html lang>` + JS hydration, flash < 100ms |
| Big image upload memory spike | Size limit 2MB pre-DB; GD load into resource ~8MB max |

### Resolved Open Questions (dari sesi sebelumnya)

| Question | Resolution |
|----------|-----------|
| ARCH-02 strategy? | **Opsi A — Pessimistic SELECT FOR UPDATE** (user confirm 2026-09-01) |
| Composer libs? | autoload + PHPUnit + PHPStan + PhpDotEnv + Faker |
| Design inspiration? | Hybrid (feather + material + functional) — UI/UX di-skip dulu |
| Deployment target? | Cloudflare Pages or no VM (backend tetap Docker) — untuk MVP: Docker lokal |
| i18n library? | i18next-core + http-backend (Vision session) |

### Deferred to Stage 6

- UI/UX Spec (Stage 3) — di-skip untuk menghemat waktu di timeline 2-minggu.
- Concurrency di distributed system — di luar scope.
- Pengujian sebenarnya — dilakukan setelah Slice 4 (SO-01 + ARCH-02) untuk hit critical path.

---

## Peta Stage

| Stage | Output | Status |
|-------|--------|--------|
| 1 | Product Vision | ✅ v1.4 |
| 2 | PRD | ✅ v1.0 |
| 3 | UX/UI Spec | ⏸️ Skipped untuk MVP 2-minggu |
| **4** | **Technical Design (this)** | **🟢 In Progress** |
| 5 | Delivery Plan | ⏳ Next |
| 6 | Implementation | ⏳ After 5 |
| 7 | Code Review | ⏳ After 6 |
| 8 | QA + Testing | ⏳ After 7 |
| 9 | Release | ⏳ After 8 |

---

## Changelog

- **1.0 · 2026-09-01** — Initial Stage 4 bundle. 4 ADR + class diagram + DB schema + API contract + sequence diagrams.
- **1.1 · 2026-09-15** — `class-diagram-initial.md` moved to `../planning/` per brief §9 DESIGN-01 AC1. Initial diagram belongs in `docs/planning/` (pre-coding design artifact).

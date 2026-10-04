# Rencana Perapihan Struktur Kode & Penulisan — IOMS

**Proyek:** Inventory & Order Management System (PT Neuronworks Indonesia — Final Project)
**Disusun:** 2026-09-12 (revisi terakhir: keputusan Opsi A untuk migrasi `Result` dikonfirmasi user)
**Konteks:** Proyek sudah berstatus "Stage 9 Release — v1.0-mvp" (README.md), dengan 37/37 PHPUnit ✅ dan PHPStan level 5 0 errors ✅. Sudah ada beberapa dokumen audit kualitas (`docs/quality/tech-debt.md`, `srp-audit.md`, `architecture-critique.md`, `refactor-log.md`) tertanggal 2026-09-01/02. Rencana ini merangkum temuan yang sudah tercatat di dokumen-dokumen tersebut, menambah observasi baru soal kerapihan folder/berkas dan error handling, dan menyusunnya jadi batch yang bisa dikerjakan bertahap dengan jeda review — mengikuti pola yang sama seperti rencana migrasi CSS sebelumnya.

Beberapa file di `app/` terakhir diubah 2026-09-09, yaitu **setelah** tanggal audit-audit di atas (2026-09-01/02). Jadi sebagian temuan di bawah perlu dikonfirmasi ulang terhadap kode saat ini sebelum dieksekusi, bukan langsung dianggap masih 100% berlaku.

---

## Batch 0 — Housekeeping folder (cepat, risiko rendah)

1. **`_to_delete/` berisi 4 file sampah**: `0)`, `3`, `ASCII`, `II'` — semuanya 0 byte, kemungkinan besar sisa command `rm`/`mv` yang pecah karena path bertanda kutip/spasi tidak di-escape dengan benar. Aman dihapus setelah dikonfirmasi tidak ada isinya.
2. **Root proyek bercampur antara file konfigurasi dan dokumen proses berukuran besar**: `AGENT.md` (47 KB), `SESSION-HANDOFF.md` (42 KB), `ai-usage-log.md` (18 KB), `css_migration_progress.md` (21 KB) semuanya duduk di root, sejajar dengan `composer.json`, `compose.yaml`, `phpunit.xml`, dll. Ini membuat root sulit dipindai sekilas. Usul: pindahkan dokumen proses/AI-session ke `docs/process/` (folder baru), sisakan di root hanya `README.md` dan `CLAUDE.md` (instruksi AI) plus file konfigurasi standar.
   - **Perlu dikonfirmasi dulu ke user**: apakah `AGENT.md` / `SESSION-HANDOFF.md` / `ai-usage-log.md` adalah bukti kerja wajib yang harus tetap terlihat di root untuk keperluan penilaian akademik. Kalau ya, batch ini cukup dilewati untuk ketiga file tsb.
3. **`css_migration_progress.md` ada di dua tempat**: sebagai file root proyek ini, dan sebagai dokumen terpisah di project Claude ("TASK PROGRAMMER"). Perlu ditentukan satu sumber kebenaran supaya kontennya tidak saling menyimpang seiring waktu.

---

## Batch 1 — Tech debt prioritas tinggi (sudah tercatat di `tech-debt.md`)

Konsolidasi dari `docs/quality/tech-debt.md`, dikelompokkan menurut akar masalah yang sama:

1. **TDB-001 / TDB-003 — `User::$role` bertipe `string`, bukan `Role` enum.** `Role` enum sudah ada tapi cuma dipakai sebagai type-hint di `requireRole()`. Kerjakan sekali untuk kedua item: migrasikan `User::$role` ke `Role`, update `fromArray()`, dan ganti semua perbandingan `$user->role === 'Admin'` jadi `$user->role === Role::Admin`. Harus diikuti pengecekan ulang di semua view yang membaca `$currentUserRole` (ini sumber bug TDB-R04 yang sebelumnya sudah pernah pecah karena inkonsistensi tipe ini).
2. **TDB-010 — Tiga controller melewati Service layer untuk read path**: `ProductController::index()`, `PurchaseOrderController::index()`, `ProductApiController::getAvailability()` memanggil repository langsung, padahal `ProductService::listProducts()` sudah ada tapi tidak dipakai (akar masalah yang sama dengan TDB-007). Rapikan supaya ketiga action ini konsisten lewat Service, sejalan dengan semua write-path lain di aplikasi.
3. **TDB-009 / TDB-011 — `ReportController` menjalankan raw SQL langsung via `Database::pdo()` (2 tempat), dan `CsvExportService` jadi tidak punya dependency repository sama sekali** karena semua fetch data nyasar ke controller. Pindahkan query itu ke `ReportService`/repository, baru `CsvExportService` (atau pemanggilnya) bergantung ke service tsb — ini pelanggaran paling jelas terhadap invariant ARCH-01 ("Service layer owns business logic + persistence, no SQL in Controller") yang didokumentasikan di CLAUDE.md §4 dan `docs/architecture/class-diagram-asbuilt.md`.
4. **TDB-008 — `GoodsIssueService` inject `Database` konkret dan jalankan raw SQL inline**, berbeda dari `GoodsReceiptService` (saudara kembarnya) yang sudah lewat abstraksi transaksi/repository. Refactor supaya keduanya konsisten — ini juga akan membuat `GoodsIssueService` lebih mudah di-unit-test.

Setelah setiap perubahan di batch ini: jalankan `vendor/bin/phpunit` (Unit + Integration) dan `vendor/bin/phpstan analyse --level=5`, karena semua item ini menyentuh jalur bisnis inti (approve/reject SO, stock issue).

**Catatan urutan:** `GoodsIssueService` (item 4) dan `PurchaseOrderService`/`SalesOrderService` yang disentuh item 1–3 semuanya juga masuk cakupan migrasi `Result` di Batch 1b di bawah. Supaya tidak menulis ulang exception-handling dua kali, kerjakan Batch 1b **bersamaan** dengan Batch 1 untuk kelas-kelas yang tumpang tindih (`GoodsIssueService`, `PurchaseOrderService`, `SalesOrderService`), bukan berurutan.

---

## Batch 1b — Migrasi penuh pola output Service ke `$result = new Result();` (Opsi A — dikonfirmasi user)

**Keputusan (dikonfirmasi 2026-09-12):** semua method Service yang bisa gagal karena alasan bisnis (validasi, transisi status tidak valid, stok tidak cukup, kredensial salah, dsb.) dimigrasikan ke pola `Result`, dikerjakan sekaligus sebagai satu batch — bukan bertahap per modul. `throw` tetap dipakai untuk error sistem yang benar-benar tidak terduga (DB down, bug), yang ditangkap jaring pengaman `catch (\Throwable)` di level router (lihat Batch 3, poin 2) — jadi item itu tetap dikerjakan meski Batch 1b ini jalan penuh.

### Kelas `Result`

```php
final class Result
{
    private function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(mixed $data = null): self
    {
        return new self(true, $data);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }
}
```

Lokasi yang wajar: `app/Core/Result.php` (dipakai lintas layer, sama seperti `Database`/`Container` yang juga di `Core/`).

### Pola di Service (contoh `PurchaseOrderService::submit()`)

```php
public function submit(int $id): Result
{
    $po = $this->purchaseOrderRepo->find($id);

    if ($po === null || !$po->canSubmit()) {
        return Result::failure('validation.po_submit_invalid_state');
    }

    // ... proses submit ...

    return Result::success($po);
}
```

### Pola di Controller

```php
$result = $this->container->getPurchaseOrderService()->submit($id);
if (!$result->success) {
    http_response_code(400);
    echo $this->t($result->errorMessage);
    return;
}
```

### Cakupan (semua 15 kelas Service di `srp-audit.md`)

Karena Opsi A dipilih, migrasi mencakup seluruh Service, tapi tidak semua Service perlu berubah — hanya yang memang punya jalur kegagalan bisnis yang selama ini dilempar sebagai exception ke controller:

| Service | Perlu migrasi ke `Result`? | Catatan |
|---|---|---|
| `AuthService` | Ya | Login gagal (kredensial salah) — perlu dicek dulu bagaimana ini disinyalkan saat ini (belum diverifikasi baris persisnya) sebelum ditulis ulang. |
| `ProductService` | Ya | Validasi CRUD produk. |
| `PurchaseOrderService` | Ya | Semua `throw new InvalidArgumentException(...)` di file ini (submit/cancel/create/item validation) → `Result::failure(...)`. Ini sekaligus menyelesaikan temuan "semantik exception tidak konsisten" di Batch 3 tanpa perlu ganti nama exception dulu — exception-nya hilang, diganti `Result`. |
| `SalesOrderService` | Ya | `InvalidArgumentException`, `InvalidStateException`, dan hasil `SalesOrderPolicy::assertCanDecide()` (lihat baris di bawah) semua jadi `Result::failure(...)`. |
| `GoodsIssueService` | Ya | `InsufficientStockException`, `InvalidStateException` → `Result::failure(...)`. Dikerjakan bersamaan dengan TDB-008 (Batch 1). |
| `GoodsReceiptService` | Ya | Perlu verifikasi exception apa saja yang dilempar saat ini (belum dibaca detail) sebelum ditulis ulang. |
| `ImageUploadService` | Ya | `InvalidImageException` → `Result::failure(...)`. |
| `WarehouseService` / `SupplierService` / `CustomerService` / `UserService` / `CategoryService` | Ya | CRUD, pola validasinya seragam — kandidat paling mudah untuk migrasi massal karena bentuknya mirip satu sama lain. |
| `SalesOrderPolicy` | **Tidak langsung** | Ini kelas domain policy murni (dipanggil dari dalam `SalesOrderService`, bukan langsung dari controller). Tetap boleh `throw SalesApprovalForbiddenException` secara internal; `SalesOrderService` yang membungkus hasil policy itu jadi `Result::failure(...)` di titik dia dipanggil, supaya boundary Service→Controller tetap konsisten `Result` di mana pun, tanpa perlu menulis ulang class policy yang sudah teruji (`SalesOrderPolicyTest`, `BR001SegregationTest`). |
| `DashboardService` | Tidak perlu | Query read-only, tidak punya jalur "kegagalan bisnis" untuk direpresentasikan sebagai `Result`. |
| `CsvExportService` | Tidak perlu | Setelah TDB-009/011 (Batch 1) selesai dan fetching-nya pindah ke service/repository yang benar, method-nya murni format data — tidak ada kegagalan bisnis untuk dibungkus `Result`. |

Baris di atas untuk `AuthService` dan `GoodsReceiptService` ditandai "perlu verifikasi" karena belum dibaca detail isinya saat rencana ini disusun — sebelum menulis kode, konfirmasi dulu exception apa saja yang benar-benar dilempar di kedua file itu (`grep 'throw new' app/Service/AuthService.php app/Service/GoodsReceiptService.php`) supaya tidak ada jalur kegagalan yang terlewat saat dikonversi.

### Efek ke Controller

Semua controller yang punya blok `catch (InvalidArgumentException|InvalidStateException|InsufficientStockException|SalesApprovalForbiddenException|InvalidImageException $e) { ...; return; }` diganti jadi pengecekan `if (!$result->success) { ...; return; }` — ini akan menyusutkan `SalesOrderController`, `PurchaseOrderController`, `ProductController`, `UserController`, `WarehouseController`, `SupplierController`, `CustomerController`, `AuthController` secara serentak, dan sekaligus menghapus sebagian besar duplikasi catch-block yang dicatat di Batch 3 poin 3.

### Verifikasi

Karena ini menyentuh jalur bisnis inti di hampir semua modul: jalankan `vendor/bin/phpunit` (Unit + Integration, termasuk `ARCH02ConcurrencyTest`, `BR001SegregationTest`, `GoodsIssueConcurrencyTest`, `GoodsReceiptTest`, `SalesOrderApprovalPolicyTest`, `UserCreationTest`) dan `vendor/bin/phpstan analyse --level=5` setelah setiap kelas selesai dimigrasi — bukan hanya di akhir batch — karena kesalahan konversi (misalnya lupa mengganti satu `throw` jadi `return Result::failure()`) akan langsung ketahuan lewat test yang sudah ada, tanpa perlu menulis test baru.

---

## Batch 2 — Tech debt prioritas menengah/rendah

1. **TDB-004** — `Database::transaction()` tidak punya timeout eksplisit; setidaknya tambahkan dokumentasi komentar soal default `innodb_lock_wait_timeout` (50s), atau tambahkan `DB_LOCK_TIMEOUT` ke `.env`.
2. **TDB-012** — Kontras border `.input` di bawah ambang WCAG 1.4.11 (3:1) di kedua tema. Perlu keputusan desain: gelapkan `--color-border`, atau beri `.input` background sendiri (`--color-bg-surface`) supaya border bukan satu-satunya penanda batas.
3. **TDB-005** — CSRF token tidak di-rotate saat role user berubah di tengah sesi.
4. **TDB-006** — `/api/products/{sku}/availability` tidak punya rate limiting.
5. **TDB-007** — `ProductService::listProducts()` belum menerima parameter pagination (satu paket dengan TDB-010 di Batch 1, karena akar masalahnya sama).

---

## Batch 3 — Konsistensi penulisan kode & error handling

1. **Konsistensi vs. `references/coding-conventions.md`** (32 KB, belum pernah di-cross-check ulang terhadap kode setelah audit terakhir). Karena filenya besar, sebaiknya dilakukan sebagai spot-check terarah: ambil beberapa aturan kunci (penamaan method Service/Repository, urutan constructor-injection, gaya exception — sudah berubah sejak Batch 1b, jadi bagian gaya "return `Result`" perlu ditambahkan ke dokumen konvensi ini juga), lalu grep pola yang menyimpang di `app/Controller/`, `app/Service/`, `app/Repository/`.
2. **Jaring pengaman `catch (\Throwable)` di level teratas.** `public/index.php` memanggil `$handler(...$args);` di baris terakhir tanpa dibungkus try/catch apa pun. Kalau ada exception yang benar-benar tidak terduga lolos (error koneksi DB, bug), PHP akan menampilkan error bawaan yang berpotensi membocorkan stack trace/path internal — berlawanan dengan komentar `DomainException.php` sendiri ("no internal details leaked (ERR-01/BR-020)"). **Fix:** bungkus `$handler(...$args)` dengan `try { ... } catch (\Throwable $e) { /* log */ http_response_code(500); echo $translator->t('common.internal_error'); }`. Ini tetap perlu dikerjakan meskipun Batch 1b (migrasi `Result`) sudah selesai, karena `Result` hanya menangani kegagalan bisnis yang diperkirakan, bukan error sistem.
3. **Verifikasi ulang naming mismatch CSS** yang pernah ditemukan (`--color-brand-primary` vs `--color-primary`, TDB-R07) — sudah diperbaiki di token, tapi baik untuk memastikan tidak ada var CSS lain yang dipakai di `main.css`/`components.css` tanpa didefinisikan di `tokens.css` (diff semua `var(--…)` terhadap token yang ada, seperti yang sudah pernah dilakukan Slice 6).

*(Item duplikasi catch-block yang sebelumnya dicatat di sini sudah tertangani otomatis oleh migrasi `Result` di Batch 1b — tidak perlu dikerjakan terpisah.)*

---

## Batch 4 — Perapihan dokumentasi (`docs/`)

`docs/planning/` dan `docs/design/` menyimpan dokumen historis yang sangat besar: `master-project-specification.md` (216 KB), `phase1-baseline.md` (234 KB), `phase2-blueprint.md` (201 KB), `phase3-specification.md` (248 KB), `pre-coding-analysis.md` (123 KB), `ioms-ui-design.md` (287 KB), plus `prd.md` (67 KB), `product-vision.md` (38 KB), `delivery-plan.md` (24 KB), `ux-ui-spec.md` (23 KB). Ini tampaknya arsip pipeline "Product Vision → PRD → UX/UI Spec → Technical Design → Delivery Plan" yang disebut CLAUDE.md §2 — bukti proses, bukan dokumen yang perlu dibaca ulang sehari-hari.

Usul: pisahkan **living docs** (README, CLAUDE.md, ADR-001..004, `api-contract.md`, `coding-conventions.md`, checklist keamanan/verifikasi) dari **historical planning artifacts**, dengan memindahkan yang kedua ke `docs/archive/planning/` dan `docs/archive/design/`. Tidak ada yang dihapus — hanya dipindah — supaya `docs/` yang aktif lebih mudah dinavigasi tanpa kehilangan bukti kerja untuk penilaian.

---

## Batch 5 — Verifikasi akhir

- Untuk setiap perubahan **kode** (Batch 1, 1b, 2, 3): `vendor/bin/phpunit` (Unit + Integration) dan `vendor/bin/phpstan analyse --level=5` harus tetap hijau sebelum lanjut ke batch berikutnya.
- Untuk perubahan **struktur folder/dokumen** (Batch 0, Batch 4): cek tidak ada link relatif antar-dokumen markdown yang menunjuk ke path lama (mis. README.md yang mereferensikan `docs/quality/...`), dan pastikan `docker compose up --build` tetap berjalan bersih dari awal (clean rebuild) karena beberapa file yang dipindah bisa saja disentuh oleh script/CI.

---

## Urutan pengerjaan yang disarankan

Batch 0 → **Batch 1 + Batch 1b bersamaan** (per kelas Service, agar tech debt dan migrasi `Result` tidak menulis ulang exception-handling dua kali) → Batch 2 → Batch 3 → Batch 4 → Batch 5, masing-masing dengan jeda review dari user sebelum lanjut ke batch berikutnya — bukan dikerjakan sekaligus secara otonom.

## Pertanyaan yang masih perlu dijawab user sebelum eksekusi dimulai

1. Apakah `AGENT.md` / `SESSION-HANDOFF.md` / `ai-usage-log.md` wajib tetap terlihat di root proyek (bukti penilaian), atau boleh dipindah ke `docs/process/`?
2. Apakah dokumen perencanaan raksasa di `docs/planning/` dan `docs/design/` boleh diarsipkan (dipindah ke subfolder `archive/`, bukan dihapus)?
3. Mulai dari mana: housekeeping folder (Batch 0, cepat & rendah risiko) dulu, atau langsung ke Batch 1 + 1b (dampaknya lebih besar ke penilaian arsitektur)?

*(Pertanyaan soal cakupan migrasi `Result` sudah terjawab: Opsi A — migrasi penuh semua Service, dikerjakan sebagai Batch 1b.)*

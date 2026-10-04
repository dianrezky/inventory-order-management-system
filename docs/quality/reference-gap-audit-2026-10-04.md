[Project README](../../README.md) / Quality / Reference Gap Audit

# Audit Gap terhadap Repository Acuan — 2026-10-04

## Acuan, scope, dan batas bukti

Audit ini membandingkan checkout `C:\laragon\www\inventory-order-management-system`, branch `main`, HEAD `239a98e`, dengan [repository acuan](https://github.com/anfazi/a6ef2c7a-46fc-47f5-a9d5-6c04f9078ec5), khususnya [BLUEPRINT-IOMS.md](https://github.com/anfazi/a6ef2c7a-46fc-47f5-a9d5-6c04f9078ec5/blob/main/docs/planning/BLUEPRINT-IOMS.md) dan README-nya. Blueprint dibaca sebagai acuan requirement; nama class, struktur folder, jumlah container, dan nama field implementasi contoh tidak otomatis menjadi kewajiban.

Spesifikasi lokal [master-project-specification.md](../planning/master-project-specification.md) menyebut brief PDF sebagai sumber tertinggi. Pada audit awal, PDF asli belum tersedia. Dalam loop perbaikan, pengguna memberikan `Project Brief - Programmer.pdf`; bagian role/permission, FIND, REPORT, ARCH, DESIGN, TEST, batas teknologi, dan penggunaan AI telah ditinjau sebagai acuan utama (halaman 3–4, 7–8, 10–12, 15, 18). PDF privat tidak disalin ke repository. Perbedaan keputusan lokal dengan blueprint acuan ditandai sebagai perbedaan yang perlu rekonsiliasi, bukan langsung dianggap bug.

Audit menelusuri route → controller → service → repository → schema serta view/JS, konfigurasi, test, dan dokumentasi yang relevan. Bagian temuan awal mempertahankan bukti sebelum perbaikan. Pengguna kemudian meminta perbaikan dan kolaborasi antar sesi; kode/test/dokumentasi kini diubah dalam working tree. Perubahan README/screenshot pengguna dipertahankan; schema/data database developer dan production tidak diubah. Schema/seed serta fixture SQL dijalankan hanya pada stack integration terisolasi setelah izin eksplisit pengguna. Laporan dan remediation belum di-commit.

**Level bukti:** source trace, syntax/static analysis, regresi in-memory, dan hasil PHPUnit Unit serta MySQL/HTTP Integration terisolasi pada image baru dari workspace ini. PHPUnit tidak dijalankan pada audit awal; pengguna kemudian meminta kedua suite secara eksplisit di sesi kolaborasi. Build dari clean clone, demo lengkap per role, dan seluruh skenario race baru belum dibuktikan.

## Ringkasan

Fondasi aplikasi tersedia: native PHP, layer service/repository, interface dengan MySQL/Fake, authentication, permission server-side, master data, multi-warehouse, partial receipt, goods issue, dashboard, CSV, API, schema/seed, job low-stock, ADR, dan test terpisah.

Gap terbesar ada pada race transisi status, validasi input yang mengubah nilai invalid menjadi valid, quality gate statis yang belum bersih, edit Draft SO, sort produk, isolasi pengujian, portabilitas object storage, dan dokumentasi yang tidak sesuai implementasi. Status SonarQube Passed tidak membuktikan semua requirement atau checkout terbaru telah diverifikasi.

## Environment: checkout yang diuji harus benar

`docker inspect iom_app --format '{{json .Mounts}}'` menunjukkan bind mount ke `C:\laragon\www\portfolio-apps\inventory-order-management-system`, bukan workspace audit ini. Setelah normalisasi LF/CRLF, `SalesOrderService`, `ImageUploadService`, dan `UserService` sama, tetapi `GoodsIssueService` dan `GoodsReceiptService` berbeda. Pencarian `usort` menemukan sorting di kedua service workspace ini dan tidak menemukannya di kedua service checkout yang dipakai container.

**Dampak:** menjalankan demo atau test melalui container aktif tidak memverifikasi perbaikan lock ordering HEAD `239a98e`. Jangan memindahkan container atau menjalankan migrasi tanpa task terpisah. Pastikan checkout target, mount, revision, dan source test sama sebelum mengambil bukti release.

## Status setelah loop perbaikan

| Temuan | Status source saat perbaikan | Batas bukti |
|---|---|---|
| F01 | Conditional status update dengan expected status + affected rows tersedia pada SQL/Fake dan callsites PO/SO | Regresi interleaving fake; belum eksekusi MySQL overlap |
| F02 | Format/rentang qty, product id, reorder point, price, tanggal nyata, dan periode ekspor diperketat | Edge NUL yang ditemukan pada loop tambahan diverifikasi kembali; lihat hasil final di bawah |
| F03 | Definite assignment/control flow diperbaiki; PHPStan level 5 bersih pada verifikasi source | Bukan bukti build/runtime target |
| F04 | Edit Draft SO tersedia dengan ownership/Admin, CSRF, validasi, lock/recheck, dan atomic persistence | Brief asli tidak mewajibkan edit Draft secara eksplisit; fitur ini tambahan lokal dibanding minimum |
| F05 | Sorting produk di-allowlist dan dipertahankan saat pagination | Brief asli mewajibkan sort tanggal order, tidak secara eksplisit sort produk; tambahan lokal |
| F06 | Pencarian #id dan PO-/SO- padded nomor cocok di SQL/fake serta count/list | Regresi fake dan tinjauan predikat; demo browser belum |
| F07 | API membedakan missing/dependency failure; kontrak aktual diperbarui; export failure tidak menjadi CSV kosong sukses | Controller/service smoke dan source front-controller mapping |
| F08 | Auth/cache unit fixtures memakai in-memory subclass; tidak native session atau socket Memcached | Regresi boundary dan Unit 132 test/404 assertion lulus |
| F09 | Semua koneksi integration/worker wajib TEST_DB_NAME berbeda dari DB_NAME; HTTP target eksplisit; profile memakai stack E2E isolated existing | Guard/config/typecheck serta Integration 17 test/143 assertion lulus pada DB/app test terpisah |
| F10 | MinIO external pada VPS adalah implementasi yang valid; panduan integrasi ditambahkan | Endpoint/bucket/public image/runtime koneksi masih menunggu verifikasi pemilik |
| F11 | Report memakai stock aggregate existing untuk scoped current valuation; desimal tidak di-truncate | Regresi shared scope/nilai pecahan; CSV detail dan KPI tetap berbeda bentuk rekapan |
| F12 | As-built dari deklarasi aktual, kontrak API/testing/debt/AI log direkonsiliasi | Hasil historis tetap diberi tanggal; bukan fresh evidence |
| F13 | Test lock-order GI dan GR tersedia, fixture benar reverse [5,3], hasil lock [3,5] serta mapping qty diuji | Runner dan Unit lulus; Integration concurrency existing lulus, tetapi schedule overlap multibarang khusus belum |
| F14 | Kredit/license asset ditambahkan berbasis 37 exact Feather geometry matches dan dua adapted Lucide-style symbols | Original import version tidak diketahui; interpretasi blueprint lebih ketat adalah pilihan yang belum diputuskan, bukan gap wajib yang terbukti; assessor snippet masih pending |

### Rekonsiliasi dengan brief asli

- Role-based SoD: Sales tidak boleh approve; Admin boleh approve termasuk order sendiri. Warehouse boleh mengusulkan PO; tidak ada kewajiban eksplisit agar Warehouse submit ke Ordered.
- Controlled concurrency scenario diizinkan sebagai bukti; transaksi paralel nyata tetap berguna untuk validasi database.
- Composer disebut untuk autoload dan dev dependency. Blueprint referensi lebih ketat terhadap runtime dependency; brief tidak secara eksplisit melarang seluruh runtime dependency. Menghapus phpdotenv belum diperlakukan sebagai perbaikan wajib; interpretasi blueprint yang lebih ketat perlu keputusan pemilik sebelum mengubah kontrak bootstrap.
- DESIGN-04 meminta cuplikan dari assessor. critique.md saat ini hypothetical; provenance tidak boleh direkayasa.
- Brief meminta ekspor CSV dari rekapan/agregasi konsisten dengan dashboard. Current valuation sudah berbagi aggregate; detail CSV perlu diverifikasi terhadap scope/reconciliation aktual saat demo, bukan diklaim memakai semua query literal yang identik.

## Temuan awal (sebelum remediation)


### F01 — P1: transisi status belum aman terhadap race

**Requirement:** PO-01, SO-01, ARCH-02.

[PurchaseOrderService::cancel](../../app/Service/PurchaseOrderService.php) dan [SalesOrderService::cancel](../../app/Service/SalesOrderService.php) membaca status terlebih dahulu, memeriksa apakah cancel diizinkan, lalu memperbarui status. [PurchaseOrderMySQLRepository::updateStatus](../../app/Repository/MySQL/PurchaseOrderMySQLRepository.php) dan [SalesOrderMySQLRepository::updateStatus](../../app/Repository/MySQL/SalesOrderMySQLRepository.php) hanya memakai filter `id`; tidak ada expected-status condition atau recheck setelah mendapatkan lock di jalur cancel.

Interleaving yang bermasalah:

1. Cancel membaca PO Ordered atau SO Approved.
2. Receipt/issue mengunci header lalu menyelesaikan transaksi menjadi Received/Fulfilled.
3. Cancel yang sudah lolos validasi melanjutkan UPDATE berdasarkan id dan menimpa status menjadi Cancelled.

Reproduksi dengan fake repository menyisipkan perubahan PO menjadi Received setelah pembacaan snapshot Ordered. `PurchaseOrderService::cancel()` tetap menghasilkan `code=0`, status akhir Cancelled. Ini bukti interleaving logis, bukan eksekusi konkurensi MySQL. SQL aktual mendukung race tersebut karena UPDATE tidak memeriksa status sebelumnya.

Approve/reject/submit juga memakai pola read-check-update; dua keputusan serentak dapat saling menimpa. Lock pada jalur issue/receipt melindungi mutasi stok tetapi belum membuat seluruh state machine aman.

**Rekomendasi:** jadikan transisi bersyarat terhadap status awal dan periksa affected rows, atau gunakan mekanisme lock/recheck existing pada workflow terkait. Tambahkan pengujian cancel vs receipt/issue dan approve vs reject. Implementasi perbaikan memerlukan task tersendiri.

### F02 — P1: validasi qty dan tanggal belum menolak nilai malformed

**Requirement:** VAL-01, PO-01, SO-01.

[SalesOrderService::normalizeAndValidateItems](../../app/Service/SalesOrderService.php) dan [PurchaseOrderService::normalizeAndValidateItems](../../app/Service/PurchaseOrderService.php) melakukan cast integer sebelum validasi. Reproduksi pada kedua normalizer:

| Input qty | Hasil |
|---|---|
| `1.9` | diterima sebagai `1` |
| `2abc` | diterima sebagai `2` |
| `abc` | ditolak sebagai qty tidak positif |

Public workflow `PurchaseOrderService::create()` dengan fake repository menerima `2abc`, mengembalikan `code=0`, dan menyimpan qty `2`. Controller juga tidak menerapkan validasi format integer sebelum service. Frontend tidak cukup untuk menjaga request yang dibypass.

`headerInvalidInfo()` pada PO/SO hanya memeriksa apakah `DateTime::createFromFormat()` menghasilkan false. PHP dapat menormalisasi tanggal yang tidak ada. Reproduksi public create PO dengan `2026-02-31` menghasilkan `code=0` dan tanggal tersebut tersimpan di fake. Pada MySQL, penolakan DATE bisa muncul sebagai error persistence, bukan pesan validasi yang tepat.

[GoodsReceiptService::buildValidatedLines](../../app/Service/GoodsReceiptService.php) juga cast qty sebelum validasi. [ProductService](../../app/Service/ProductService.php) cast reorder point sebelum memeriksa non-negatif. Jalur tersebut perlu audit input mentah yang sama; tidak seluruhnya direproduksi.

**Rekomendasi:** validasi format/rentang sebelum cast; validasi tanggal dengan hasil format balik atau warning parser. [ReportService::validDate](../../app/Service/ReportService.php) sudah menggunakan pemeriksaan format balik yang dapat dijadikan referensi lokal.

### F03 — P1: PHPStan saat ini belum bersih

**Requirement:** TEST-03.

Static analysis yang benar-benar dijalankan terhadap workspace ini:

```text
php C:/laragon/www/portfolio-apps/inventory-order-management-system/vendor/phpstan/phpstan/phpstan analyse --configuration=C:/laragon/www/inventory-order-management-system/phpstan.neon --no-progress --memory-limit=512M
Exit code: 1
[ERROR] Found 10 errors
```

Executable PHPStan dipinjam dari dependency checkout lain karena workspace ini tidak memiliki `vendor/autoload.php`. CWD dan configuration menunjuk workspace audit; path analisis berasal dari `phpstan.neon` lokal. Runtime host PHP 8.3.13. Ini bukti static analysis source lokal, bukan verifikasi build/container target.

| File | Baris | Error |
|---|---|---|
| `app/Service/ImageUploadService.php` | 56 | `$height`, `$width` might not be defined |
| sama | 57 | `$height`, `$image`, `$width` might not be defined |
| sama | 59 | `$image` might not be defined |
| sama | 65 | `$image` might not be defined |
| sama | 66 | `$image` might not be defined |
| sama | 68 | `$image` might not be defined |
| `app/Service/UserService.php` | 150 | `$name` might not be defined |

Error ini menunjukkan masalah pembuktian definite assignment pada rangkaian guard; tidak otomatis membuktikan semua variabel tersebut undefined pada request normal. Namun, klaim current PHPStan 0 errors tidak berlaku.

**Rekomendasi:** rapikan assignment/control flow sesuai style lokal, verifikasi ulang, lalu perbarui [phpstan-results.md](../testing/phpstan-results.md). Jangan menghapus error dengan ignore blanket.

### F04 — P2: edit/update Draft Sales Order belum tersedia

**Requirement:** SO-01, user story US-13 pada acuan.

[Routes PO/SO](../../config/routes.php) secara eksplisit tidak memiliki edit/update untuk order. [SalesOrderController](../../app/Controller/SalesOrderController.php) dan [SalesOrderService](../../app/Service/SalesOrderService.php) menyediakan create, submit, approve, reject, cancel, issue, tetapi tidak menyediakan perubahan header/item Draft.

**Dampak:** Sales yang salah mengisi Draft harus membatalkan dan membuat SO baru. Kriteria acuan tentang mengedit order sendiri belum lengkap. PO Draft juga tidak memiliki edit, tetapi blueprint yang dibaca tidak menyatakan kewajiban edit PO sejelas edit SO.

**Rekomendasi:** alur edit Draft SO dengan ownership server-side, batas status, validasi header/item, dan konsistensi multi-tabel.

### F05 — P2: sort produk masih fixed

**Requirement:** FIND-01.

[ProductController::indexAction](../../app/Controller/ProductController.php) membaca search/filter/page/per_page tetapi tidak membaca sort. [ProductMySQLRepository::findAll](../../app/Repository/MySQL/ProductMySQLRepository.php) selalu mengirim `p.name ASC` ke QueryBuilder. [Daftar produk](../../views/master/products/list.php) tidak menyediakan kontrol untuk mengganti sort.

Search nama/SKU, kategori, stok, dan pagination sudah ada. Sort tanggal PO/SO sudah ada. Gap adalah pilihan sort produk, bukan keseluruhan FIND-01.

**Rekomendasi:** expose pilihan sort yang di-allowlist dari controller hingga repository dan pertahankan nilainya saat pagination.

### F06 — P2: pencarian nomor order belum menerima format yang ditampilkan

**Requirement:** FIND-01.

[Daftar PO](../../views/purchase/list.php) dan [daftar SO](../../views/sales/list.php) menampilkan nomor sebagai `#<id>`. Dashboard memakai format PO-/SO- dengan zero padding. Namun, `orderSearchFilters()` pada repository PO/SO mencari input mentah terhadap `CAST(id AS CHAR)`.

Input `1` dapat cocok; `#1` atau `PO-0001` tidak cocok terhadap string database `1`. Ini hasil penelusuran format producer/consumer dan predikat query, belum demo browser terhadap database workspace ini.

**Rekomendasi:** tetapkan format nomor canonical dan normalisasi input pencarian agar nomor yang disalin dari UI dapat dicari.

### F07 — P2: API menyamarkan kegagalan dependency dan kontraknya drift

**Requirement:** API-01, ERR-01.

[ProductService::getAvailability](../../app/Service/ProductService.php) mengembalikan null untuk lookup product yang gagal maupun product tidak ditemukan. [ProductApiController](../../app/Controller/ProductApiController.php) menerjemahkan null menjadi 404. Reproduksi dengan fake repository yang mengembalikan CODE_INTERNAL membuktikan service return null, sehingga cabang controller adalah 404. Kegagalan stock lookup juga menjadi array kosong lalu total stok 0, sehingga dapat menghasilkan 200 dengan informasi stok menyesatkan.

Kontrak lokal [api-contract.md](../architecture/api-contract.md) mendokumentasikan `total_quantity` dan `warehouses`; kode mengembalikan `total_stock` dan `availability`. Blueprint acuan memakai contoh `total` dan `warehouses`. Perbedaan contoh acuan sendiri belum otomatis bug karena requirement mengizinkan endpoint JSON contoh; kontrak lokal yang berbeda dari response nyata adalah drift yang pasti.

**Rekomendasi:** pisahkan not-found dari internal failure dan documentasikan payload aktual. Pertahankan compatibility bagi JS existing yang membaca `available`, `total_stock`, dan `unit`.

### F08 — P2: unit test belum seluruhnya terisolasi

**Requirement:** TEST-01, TEST-03/FIRST.

[AuthServiceTest](../../tests/Unit/AuthServiceTest.php) menggunakan SessionManager nyata, `useFileSessions()`, `start()`, dan `session_start()`. File session menghilangkan dependency Redis tetapi masih menyentuh session global/I/O PHP.

[PermissionServiceTest](../../tests/Unit/PermissionServiceTest.php) dan [FileValidationServiceTest](../../tests/Unit/FileValidationServiceTest.php) membuat `CacheService('127.0.0.1', 1)`. [CacheService::connect](../../app/Core/CacheService.php) melakukan `Memcached::getStats()` ketika extension tersedia; sengaja memilih port gagal tetap merupakan usaha koneksi network nyata.

**Rekomendasi:** fake session/cache pada boundary yang relevan. Pertahankan test integration khusus untuk session/cache nyata bila diperlukan. Unit harus tidak bergantung pada ketersediaan extension, socket, file session, atau global state.

### F09 — P2: database integration test belum dipisahkan dari database aplikasi

**Requirement:** TEST-02; perbandingan dengan hygiene test repository acuan.

Integration tests lokal membaca `DB_NAME` atau default `inventory_order_management`, memakai seed user aplikasi, serta melakukan insert/delete fixture pada database tersebut. [phpunit.xml](../../phpunit.xml) tidak memberi test database tersendiri, dan [Compose](../../docker-compose.yaml) hanya menginisialisasi schema/seed database aplikasi. Contoh: [GoodsReceiptTest](../../tests/Integration/GoodsReceiptTest.php), [ARCH02ConcurrencyTest](../../tests/Integration/ARCH02ConcurrencyTest.php).

Repository acuan menyediakan database test terpisah. Ini perbaikan keselamatan dan repeatability yang belum tersedia lokal, bukan bukti bahwa minimum tiga test MySQL tidak terpenuhi.

**Rekomendasi:** test database eksplisit dan guard penolakan database aplikasi; fixture dan cleanup milik masing-masing skenario. Jangan menjalankan suite sebelum target DB dipastikan.

### F10 — P2: panduan koneksi ke external MinIO belum lengkap

**Requirement:** environment Docker/portability, PRD-01 upload.

**Klarifikasi pemilik proyek (2026-10-04):** MinIO memang sudah diimplementasikan dan dioperasikan di VPS. Deployment external merupakan keputusan proyek yang valid; tidak adanya container MinIO lokal bukan gap implementasi fitur upload.

Gap yang terkonfirmasi adalah panduan koneksi dan komentar `.env.example` yang sebelumnya menyatakan MinIO merupakan service Compose lokal. Panduan [MinIO VPS integration](../ops/minio-vps-integration.md) sekarang menjelaskan endpoint S3, URL browser, credentials, bucket/prefix, permissions, penerapan environment, dan verifikasi. README serta komentar template environment diselaraskan.

**Status:** panduan integrasi tersedia; koneksi, permissions dan upload aktual ke VPS belum diverifikasi. Endpoint dan nama bucket deployment belum diberikan. Tidak ada perubahan deployment atau bucket policy yang dieksekusi.

### F11 — P2: sumber agregasi dashboard/report belum terpusat; presisi berbeda

**Requirement:** REPORT-01.

[DashboardService](../../app/Service/DashboardService.php) menggunakan ProductStockRepository dan repository order untuk nilai/status. [ReportService](../../app/Service/ReportService.php) menggunakan ReportRepository untuk KPI report, sedangkan [export controller](../../app/Controller/ReportController.php) memakai StockLedgerService dan service PO/SO. Feature export tersedia tetapi persyaratan acuan tentang reuse sumber agregasi belum sepenuhnya tercermin.

Contoh drift konkret: `ProductStockMySQLRepository::totalInventoryValue()` mengembalikan string nilai DECIMAL, sementara `ReportMySQLRepository::inventoryValuation()` melakukan cast integer. Nilai `10.50` dapat dipotong menjadi `10` pada report. Nilai rupiah tanpa pecahan pada UI tidak mengubah fakta bahwa schema mengizinkan dua desimal.

**Rekomendasi:** sepakati basis, scope, dan presisi agregasi; reuse mekanisme existing yang jelas tanggung jawab domainnya. Tambahkan pemeriksaan konsistensi dashboard dan report/CSV untuk scope yang sama.

### F12 — P2: diagram as-built dan bukti kualitas belum mengikuti kode

**Requirement:** DESIGN-01, DESIGN-03, TEST-03.

[class-diagram-asbuilt.md](../architecture/class-diagram-asbuilt.md) menggambar `Result::ok()`, `validationError()`, `getOrThrow()`, property `message`, dan API fluent `QueryBuilder::select()/where()/fetchAll()`. [Result aktual](../../app/Core/Result.php) hanya punya code/info/data dan constructor; [QueryBuilder aktual](../../app/Repository/MySQL/QueryBuilder.php) memakai findAll/findOne/countAll/scalar serta method persistence. Bagian delta diagram juga belum menggunakan suffix Action seperti controller aktual.

[docs/testing/README.md](../testing/README.md) menyebut Admin tidak boleh approve order sendiri, sedangkan policy dan test saat ini justru mengizinkannya. Dokumen test menyebut 101 unit test; inventory source sekarang menemukan **113 method bernama test pada 12 file unit**, dan **17 method pada 6 file integration**. Hitungan source ini bukan jumlah test/assertion hasil runner.

[tech-debt.md](tech-debt.md) belum memiliki TDB-014/R13 dari ringkasan percakapan sebelumnya. Klaim debt session role tidak diperbarui juga perlu ditinjau karena `AuthService::currentUser()` sekarang membaca user DB dan menyinkronkan role.

**Rekomendasi:** perbarui as-built dari signature/relationship nyata, selaraskan policy/test docs, dan sertakan revision+tanggal pada evidence. Jangan mengganti angka historis dengan hitungan statis seolah-olah itu hasil PHPUnit.

### F13 — P2: regression evidence lock ordering belum tersedia

**Requirement:** ARCH-02, TEST-01/02.

Sorting ascending `productId` tersedia di service GI/GR workspace ini. Pencarian file test tidak menemukan `GoodsIssueServiceLockOrderTest.php` maupun test receipt khusus deterministic ordering. Test concurrency existing menguji overselling/lock pada satu produk atau skenario lain, sehingga belum membuktikan urutan lock `[3,5]` untuk input `[5,3]` pada kedua service.

SonarQube screenshot menunjukkan Passed, Overall coverage 11.1%, duplications 8.5%, New Code coverage 0%, dan 17 lines to cover. Tidak ada bukti revision scanner pada screenshot yang menghubungkan scan dengan HEAD workspace ini. Coverage 80% bukan minimum unit-test requirement yang ditemukan pada blueprint; tetap merupakan target yang ditampilkan dashboard Sonar. Screenshot Passed tidak boleh diterjemahkan sebagai seluruh perubahan teruji.

**Rekomendasi:** regression test pada GI dan GR, termasuk reversed repository order dan input multibarang, lalu jalankan suite di checkout target dengan izin eksplisit. Rekam revision analisis Sonar/test sebelum memperbarui status release.

### F14 — P3: kredit asset dan kepatuhan dependency perlu dipertegas

**Requirement:** batas teknologi dan provenance asset pada acuan.

README belum mencantumkan sumber/versi/lisensi icon sprite. SVG yang ditemukan tidak memberikan metadata sumber; audit tidak mengasumsikan kepemilikan atau lisensinya. Jika memakai asset pihak ketiga, kredit harus ditambahkan; jika buatan sendiri, nyatakan asalnya.

`composer.json` memuat runtime dependency `vlucas/phpdotenv`, sedangkan blueprint acuan membatasi Composer ke autoload/dev dependencies. Ini perbedaan kepatuhan yang perlu diselesaikan terhadap brief asli/keputusan trainer, bukan sekadar fitur yang hilang. Hand-written `Container` lokal adalah manual wiring dan tidak otomatis setara DI container framework yang dilarang. PHP 8.3, Redis/Memcached, dan cron sudah merupakan keputusan proyek lokal; tidak perlu menyalin semua teknologi repository acuan.

PHPCS bukan gap wajib tersendiri bila PHPStan level 5 memenuhi pilihan static-analysis pada requirement. File i18next yang tersisa tidak membuktikan library tersebut aktif; pencarian script views/JS aktif tidak menemukan pemakaian i18next/Chart.js.

## Matriks baseline 26 requirement (sebelum remediation)

Matriks ini mencatat kondisi audit awal agar bukti sebelum/sesudah tidak hilang. Status source terkini ada pada tabel remediation di atas. **Ada** berarti jalur source/dokumen ditemukan, bukan seluruh AC lulus runtime.

| Requirement | Hasil audit | Evidence/gap utama |
|---|---|---|
| AUTH-01 | Ada; evidence parsial | AuthService, SessionManager; hash, active-user check, session regeneration |
| AUTH-02 | Ada | AuthService::logout, session destruction, guarded pages |
| USR-01 | Ada; quality gap | UserController/UserService/schema; F03 |
| PRD-01 | Ada; quality/setup gap | ProductService/ImageUploadService; F02/F03/F10 |
| WH-01 | Ada | ProductStockRepository, product/warehouse detail, unique product+warehouse schema |
| PO-01 | Parsial | Lifecycle, receipt, partial/over-receipt; F01/F02 |
| SO-01 | Parsial | Create/approval/issue/ownership; F01/F02/F04 |
| VIEW-01 | Ada; runtime AC belum lengkap diverifikasi | List/detail/empty state source; screenshot existing |
| FIND-01 | Parsial | Search/filter/page 10/order date sort; F05/F06 |
| DASH-01 | Ada; consistency gap | Role-scoped aggregates and queues; F11 |
| REPORT-01 | Parsial | CSV/date/scope ada; aggregation reuse/presisi F11 |
| API-01 | Ada; error/contract gap | Availability JSON/auth; F07 |
| VAL-01 | Parsial | FE+BE tersedia tetapi malformed qty/date lolos; F02 |
| ERR-01 | Ada; API exception-classification gap | Router 403/404/500/logging; F07 |
| UI-01 | Ada struktur; belum terbukti lengkap | Responsive CSS/viewport; belum demo semua halaman wajib 360px pada checkout ini |
| DB-01 | Ada schema/seed; runtime belum diverifikasi ulang | PK/FK/unique/check/index/InnoDB/prepared parameters; tidak execute SQL |
| JOB-01 | Ada; output belum direkam ulang | Standalone check-low-stock.php; job tidak dijalankan karena menulis notification |
| ARCH-01 | Ada | Controller→Service→Repository interface; MySQL/Fake constructor wiring |
| ARCH-02 | Parsial evidence; state-race gap | Stock transaction/header+stock locks/sorting ada; F01/F13 |
| DESIGN-01 | Parsial | Initial+as-built ada tetapi as-built drift; F12 |
| DESIGN-02 | Ada | ADR-001 sampai ADR-006 |
| DESIGN-03 | Ada; register/audit perlu update | Refactor-log, SRP audit, tech-debt, refactor commits; F12 |
| DESIGN-04 | Ada dokumen; provenance snippet belum terkonfirmasi | critique.md menyebut hypothetical snippet, belum bukti snippet diberikan assessor |
| TEST-01 | Ada minimum struktur; isolation gap | 12 file unit/113 method; F08; tidak menjalankan runner |
| TEST-02 | Ada minimum struktur; isolation/evidence gap | 6 file integration/17 method; F09/F13 |
| TEST-03 | Belum bersih | PHPStan 10 error; FIRST F08; laporan historis bukan hasil current |

## Perbedaan acuan pada audit awal

- Acuan mengizinkan Warehouse membuat PO sampai Ordered. Seed permission lokal memberi Warehouse `purchase_orders.manage`, tetapi `purchase_orders.submit` hanya Admin. Warehouse bisa membuat Draft, sedangkan submit ke supplier memerlukan Admin. Ini pilihan hak akses yang perlu diselaraskan dengan brief asli, bukan langsung ditambah permission.
- Filter/pagination lokal menggunakan POST agar state tidak masuk URL; blueprint mencontohkan query-string persistence. Form existing meneruskan filter saat Next/Prev, sehingga fungsi mempertahankan filter sudah tersedia. Jangan mengubah ke GET semata-mata untuk meniru contoh.
- API boleh memiliki payload sendiri bila requirement hanya menetapkan contoh endpoint; dokumentasi lokal tetap harus cocok dengan implementasinya.
- Partial PO cancellation tersedia dan unit source memuat kasusnya; jangan memakai catatan checklist lama yang mengatakan fitur tersebut belum diizinkan.
- Admin boleh approve order yang dibuatnya sendiri menurut policy existing dan acuan; larangan Sales tetap diberlakukan untuk semua order.

## Verifikasi audit awal (historis)

| Pemeriksaan | Hasil |
|---|---|
| Git status/history read-only | main, HEAD 239a98e; README dirty dan screenshot untracked dipertahankan |
| php -l pada seluruh app/**/*.php | 127 file, 0 syntax failure |
| PHPStan level 5 terhadap source workspace | exit 1, 10 error |
| Normalizer PO/SO dengan fake ProductRepository | `1.9→1`, `2abc→2` diterima |
| Public PO create dengan fake repositories | qty `2abc` diterima, saved qty 2 |
| Public PO create tanggal invalid | `2026-02-31` diterima di fake |
| PO cancel interleaving dengan fake | snapshot Ordered, intervening Received, final Cancelled |
| API dependency failure dengan fake | service null, controller 404 branch |
| Docker mount/source inspection | aktif memakai checkout portfolio-apps; GI/GR tanpa sorting terbaru |
| PHPUnit | Tidak dijalankan: user tidak meminta eksplisit |
| SQL/build/clean-clone/demo mobile | Tidak dijalankan; hasil tidak diklaim verified |

Urutan pekerjaan yang disarankan: F01/F02 → F03 → F04/F05/F06/F07 → F08/F09/F10/F11 → F12/F13/F14. Pastikan target checkout/container terlebih dahulu. Audit ini tidak menetapkan skor akhir assessor. Brief PDF asli sekarang tersedia dan telah ditinjau; runtime/bukti submission lengkap belum diverifikasi.

## Verifikasi final remediation — 2026-10-04

| Pemeriksaan | Hasil dan batas |
|---|---|
| Standalone runner | 19 skenario, 153 assertion, exit 0; dependency in-memory |
| PHPUnit Unit | 132 test, 404 assertion, exit 0 |
| PHPUnit Integration | 17 test, 143 assertion, exit 0; MySQL/HTTP pada stack ioms-e2e |
| Runtime suite | PHP 8.3.20 / PHPUnit 10.5.64, image baru dari workspace dengan dependency composer.lock |
| PHPStan level 5 | 0 error, exit 0, termasuk verifikasi pada image yang sama; Fake excluded sesuai config |
| Syntax PHP | 52 file PHP berubah/baru, seluruhnya lulus |
| TypeScript/ESLint/Compose | typecheck, integration CLI lint, render profile: exit 0 |
| Git whitespace | git diff --check exit 0; warning LF/CRLF adalah warning Git |

Image yang diverifikasi: `sha256:12c35c978219ea15d25d30a1cde183b58469d3aaef0f224d31a1479e188b417f`. Integration menggunakan `integration-app` tanpa published port/app bind mount dan database test khusus. Ini bukan bukti clean clone atau validasi semua feature browser/MinIO. Stack Playwright lengkap sempat terhambat pull minio/mc; profile PHP Integration sengaja hanya memerlukan dependency yang digunakan suite.

Sisa bukti: schedule MySQL khusus cancellation race/lock overlap multibarang, demo per role/mobile, rekonsiliasi CSV saat demo, job CLI, upload/public image MinIO VPS, clean-clone build, scan Sonar sesuai revisi, dan cuplikan DESIGN-04 dari assessor. TDB-014 tetap terbuka untuk duplikasi normalisasi PO/SO. Tidak ada klaim seluruh gap assessment telah tertutup.

[Back to README](../../README.md) · [Tech Debt](tech-debt.md) · [Test Evidence](../testing/README.md)

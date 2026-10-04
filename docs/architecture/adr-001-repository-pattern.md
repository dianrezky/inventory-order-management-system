# ADR-001: Repository Pattern (Layered Architecture)

- **Status:** Accepted
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related Requirements:** ARCH-01 (PRD §9), semua requirement yang menyentuh entity
- **Related BR:** Semua BR yang enforcement terjadi di Service layer

---

## Context (Konteks)

Brief §3.1 mewajibkan **arsitektur 3 lapis** (Controller → Service → Repository) dengan **dependency inversion**: Service class tidak boleh meng-instantiate apa pun yang terkait I/O (DB, session, file). Tujuannya:

1. **Testability** — Business logic harus bisa di-unit-test tanpa DB nyata.
2. **Substitutability** — Repository MySQL bisa diganti Fake/InMemory saat pengujian.
3. **Clean separation** — HTTP routing, domain logic, dan persistence punya peran masing-masing.
4. **Memudahkan penjelasan ke assessor** — Layer terlihat jelas, tidak ada "magic" yang tersembunyi.

Constraint dari CLAUDE.md Rule #11: **Tidak ada `new PDO()` di Service class**. Ini adalah invariant yang harus dijaga otomatis atau melalui code review.

---

## Decision (Keputusan)

Kami mengadopsi pola **3 lapis pragmatis** persis seperti yang disarankan Brief §3.1 / FAQ #4:

```
   ┌──────────────────┐
HTTP │   Controller     │  Routing, auth guard, render view / JSON response
req  └─────────┬────────┘
              │  depend on
              ▼
   ┌──────────────────┐
     │     Service       │  Business rules, validasi, orkestrasi transaksi
   └─────────┬────────┘
              │  depend on
              ▼
   ┌──────────────────┐
     │   Repository      │  PDO + SQL prepared statements, return Entity/array
   └──────────────────┘
   (interface di domain, implementasi di infrastructure)
```

### 1. Interface Repository di domain

Setiap repository punya interface (`app/Repository/Interface/*RepositoryInterface.php`) yang **murni signature**, tanpa implementasi. Contoh:

```php
namespace App\Repository\Interface;

use App\Entity\SalesOrder;

interface SalesOrderRepositoryInterface
{
    public function findById(int $id): ?SalesOrder;
    /** @return list<SalesOrder> */
    public function findAllByCreator(int $userId, array $filter): array;
    public function save(SalesOrder $so): int;
    public function updateStatus(int $id, string $newStatus, array $extras = []): void;
}
```

### 2. Implementasi MySQL ada di infrastructure

`app/Repository/MySQL/SalesOrderMySQLRepository.php` meng-implement interface, meng-extend `BaseRepository` yang menyediakan PDO.

> **Catatan struktur:** Nama folder di root struktur awal menggunakan `app/Repository/`. Saat implementasi Slice 1, akan dibuat sub-folder `MySQL/` dan `Fake/` agar interface dan implementasi tidak tercampur di namespace yang sama. Ini adalah detail refactor minor yang aman dilakukan di Slice 1.

### 3. Fake Repository untuk testing

`app/Repository/Fake/SalesOrderFakeRepository.php` menyimpan di array/ in-memory. Dipakai oleh unit test Service (tanpa DB).

### 4. Dependency Injection manual

Service menerima dependency via constructor (tidak pakai DI container framework):

```php
final class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepositoryInterface $repo,
        private readonly ProductStockRepositoryInterface $stockRepo,
        private readonly SalesOrderPolicy $policy,
    ) {}
}
```

Wiring manual di **`app/Core/Container.php`** — composition root, satu titik instantiate semua dependency.

### 5. Database Connection Holder

PDO di-instantiate di `app/Core/Database.php` (satu tempat) lalu di-pass ke BaseRepository. **Tidak ada `new PDO()` di Service atau Controller.**

---

## Consequences (Konsekuensi)

### Positif
- **Unit test bersih** — Service diuji pakai FakeRepository, tidak butuh MySQL. ARCH-01 AC2 ✓.
- **Code review invariant sederhana** — `grep -r 'new PDO' app/Service/` harusnya kosong.
- **Sesuai Brief & CLAUDE.md** — tidak ada framework ORM, tidak ada DI container, tidak ada runtime annotation.
- **Mudah dijelaskan ke assessor** — Layer terlihat jelas di class diagram.

### Negatif / Trade-off yang kami terima
- **Lebih banyak file** — 1 entity = 1 interface + 2 implementasi + 1 service. ~15-20 entity × 4 file = 60-80 file. Trade-off yang worth it untuk testability.
- **Tanpa DI container** — wiring manual di Container.php akan tumbuh besar. Kami tolerir: tetap < 250 baris, dependency graph terlihat eksplisit.
- **Konvensi namespace** — perlu diingat: `App\Repository\Interface\Xxx` untuk interface, `App\Repository\MySQL\Xxx` untuk implementasi.

---

## Alternatives Considered (Alternatif yang Dipertimbangkan)

### Opsi A: Tanpa Repository (Controller → Service → raw PDO via global)
- **Pro:** Lebih sedikit file.
- **Kon:** Service jadi tergantung global state, tidak bisa diunit-test tanpa DB, sulit di-inversion.
- **Ditolak:** Melanggar ARCH-01.

### Opsi B: Pakai ORM (Eloquent / Doctrine)
- **Pro:** Lebih cepat develop.
- **Kon:** Melanggar CLAUDE.md (ORM framework dilarang) + Brief §3.1 (Native PHP OOP).
- **Ditolak.**

### Opsi C: Pakai DI container (Symfony DI / Pimple / Laravel Container)
- **Pro:** Wiring otomatis.
- **Kon:** Brief FAQ #4 + CLAUDE.md: tidak boleh pakai DI container framework.
- **Ditolak.**

---

## Implementation Notes

1. **Naming convention:**
   - Interface: `{Entity}RepositoryInterface` di namespace `App\Repository\Interface`
   - Implementasi MySQL: `{Entity}MySQLRepository` di `App\Repository\MySQL`
   - Implementasi Fake: `{Entity}FakeRepository` di `App\Repository\Fake`
   - Base: `App\Repository\BaseRepository` (Protected $db)

2. **Composer (PSR-4 autoload) di composer.json:**
   ```json
   "autoload": {
     "psr-4": {
       "App\\": "app/"
     }
   }
   ```

3. **Anti-corruption:** Service TIDAK BOLEH import class PDO, mysqli, atau bahkan `BaseRepository`. Hanya via interface.

4. **Static analysis:** PHPStan rule custom `app/phpstan/extension/no-pdo-in-service.neon` — IDE/CLI check `new PDO(` di `app/Service/` dan di-fail.

5. **Convention PR-review:** Tiap PR di Bagian Service wajib ada minimal 1 unit test. Code owner block merge kalau tidak ada.

---

## Validation / How to Verify

- [x] **ARCH-01 AC1:** Service wajib inject interface, bukan `new`.
- [x] **ARCH-01 AC2:** Unit test `SalesOrderService::approve()` jalan tanpa MySQL.
- [x] **ARCH-01 AC3:** `grep 'new PDO' app/Service/` → 0 hits.
- [x] CLAUDE.md Rule #11: Tidak ada `new PDO()` di Service class.

---

## References
- `docs/planning/prd.md` §9 ARCH-01
- `CLAUDE.md` Rule #11 (no new PDO() di Service)
- `Project Brief - Programmer.pdf` §3.1, FAQ #4
- Pattern referensi: Pattern-Oriented Software Architecture Vol 1: Repository Pattern (Fowler)

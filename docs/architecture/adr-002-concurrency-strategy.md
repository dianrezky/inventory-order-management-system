# ADR-002: Concurrency Strategy — Pessimistic Row Lock (SELECT FOR UPDATE)

- **Status:** Accepted
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related Requirements:** ARCH-02 (PRD §9, P0 Critical), PO-01, SO-01
- **Related BR:** BR-008, BR-009, **BR-010 (no oversell)**, BR-015

---

## Context (Konteks)

Brief §3.1 ARCH-02 dan PRD BR-010 menetapkan invariant kritis:

> "Dua Goods Issue simultan pada produk+warehouse yang sama TIDAK BOLEH oversell — salah satu ditolak dengan aman. Jika gagal, critical failure."

Brief §3.1 juga menentukan invariant uji:

> "After N operations, `product_stocks.quantity == initial + sum(ledger.qty)` untuk product+warehouse."

Studi kasus yang harus di-demo ke assessor:

```
Stok Produk-A di Gudang Bandung = 10 unit.
Dua request Goods Issue paralel datang:
- Request #1: qty = 7
- Request #2: qty = 5
```

7 + 5 = 12, padahal stok cuma 10. Sistem **harus** menerima tepat satu, menolak yang lain dengan pesan jelas. Tidak boleh ada kondisi di mana:

- Stok akhir = -2 (oversell)
- Kedua request sukses
- Stok menjadi 0 padahal total qty yang diproses 7 + 5 = 12 (silent loss)

Studi kasus kedua (rollback):

```
Transaksi Goods Receipt update stock + insert ledger.
Jika di tengah proses ada exception, BUKAN hanya ledger gagal — 
stok juga harus tidak berubah (all-or-nothing).
```

---

## Decision (Keputusan)

Kami memilih **Opsi A: Pessimistic Row Lock dengan `SELECT ... FOR UPDATE`** (sesuai rekomendasi PRD §9 ARCH-02 default dan diskusi terbuka dengan user).

### Mekanisme

Setiap transaksi Goods Receipt / Goods Issue mengikuti pola:

```php
$this->db->beginTransaction();

try {
    // STEP 1: Lock baris stok
    $stock = $this->stockRepo->lockForUpdate($productId, $warehouseId);
    // Method ini menjalankan:
    //   SELECT quantity FROM product_stocks 
    //   WHERE product_id = ? AND warehouse_id = ? 
    //   FOR UPDATE
    // MySQL InnoDB akan menahan lock sampai commit/rollback.

    // STEP 2: Validasi (di dalam critical section)
    if ($stock->quantity < $requestedQty) {
        throw new InsufficientStockException(...);
        // Exception trigger rollBack() di finally; lock dilepas.
    }

    // STEP 3: Mutasi stock + ledger
    $this->stockRepo->decrement($productId, $warehouseId, $requestedQty);
    $this->ledgerRepo->insert([
        'type'         => 'Issue',
        'ref_type'     => 'SO',
        'ref_id'       => $soId,
        'product_id'   => $productId,
        'warehouse_id' => $warehouseId,
        'qty'          => -$requestedQty,  // konvensi: Issue = negatif
        'done_by_user_id' => $userId,
        'done_at'      => date('Y-m-d H:i:s'),
    ]);

    // STEP 4: Commit
    $this->db->commit();
    // Lock dilepas otomatis oleh InnoDB saat commit/rollback.
} catch (\Throwable $e) {
    $this->db->rollBack();
    throw $e;
}
```

### Kenapa Pessimistic?

1. **Sederhana & defensible** — Logika linear, tidak ada retry loop, tidak ada hidden state.
2. **MySQL InnoDB sudah battle-tested** — `FOR UPDATE` adalah primitif resmi InnoDB, didokumentasikan di mysql.com, dan ini adalah pattern standard untuk inventory systems.
3. **Cocok untuk contention warehouse** — Kalau ada 2 warehouse staff yang antri Goods Issue di jam sibuk, lock-nya pendek (kurang dari 1 detik per operasi). Throughput masih tinggi.
4. **Mudah di-demo & diuji** — Acquirer (assessor) bisa langsung mengerti alur: "lock → cek → mutasi → commit".
5. **Cocok dengan materi ujian** — Topik row-level locking lazim di kuliah database transaction; banyak peserta akan merasa familiar.

### Konvensi Ledger

- Tipe `Receipt` → qty **positif** (`+N`)
- Tipe `Issue` → qty **negatif** (`-N`)  ← konsisten dengan pergerakan
- Tipe `Adjustment` → bisa + atau -

Dengan konvensi ini, **invariance sederhana & mudah diuji**:

```
product_stocks.quantity == 
    initial_seed_quantity 
    + SUM(stock_ledger.qty WHERE product_id = ? AND warehouse_id = ?)
```

---

## Why NOT Optimistic? (Opsi B Ditolak)

Kami sempat mempertimbangkan **Opsi B: Optimistic Locking dengan kolom `version`**.

### Cara kerja Optimistic
```php
// Baca dulu tanpa lock
$stock = $this->stockRepo->findOne($productId, $warehouseId); 
// → version = 3

if ($stock->quantity < $requestedQty) throw ...;

// Update dengan versi expectation
$rowsAffected = $this->stockRepo->update(
    $productId, $warehouseId, 
    newQty: $stock->quantity - $requestedQty,
    expectedVersion: 3
);

if ($rowsAffected === 0) {
    throw new ConcurrentUpdateException('Retry required');
}
```

### Alasan menolak untuk konteks ini
1. **Retry logic** — Kalau `affected_rows=0`, kode harus decide: retry otomatis (3x?) atau reject langsung? Implementasi retry loop lebih kompleks, terutama saat error path harus dijaga deterministic untuk testing.
2. **Hidden failure mode** — Optimistic lebih cocok untuk **low-contention** (mis. CMS update by author). Warehouse operations dengan 2-5 staff concurrent di jam sibuk = medium-to-high contention. Pessimistic lebih predictable.
3. **Penjelasan ke assessor** — Topik row lock jauh lebih lazim di materi database transaction Indonesia; optimistic version column lebih sering diajarkan di advanced distributed systems.
4. **Overkill untuk scope** — Tidak ada distributed system, tidak ada mobile client dengan sync offline. Kompleksitas optimistic tidak sebanding dengan benefitnya di sini.

---

## Implementation Details

### 1. Repository Method Baru

Di `ProductStockRepositoryInterface`:

```php
/**
 * @throws StockNotFoundException kalau product_stocks row belum ada
 */
public function lockForUpdate(int $productId, int $warehouseId): Result;
```

Di `ProductStockMySQLRepository::lockForUpdate()`:

```php
public function lockForUpdate(int $productId, int $warehouseId): Result
{
    $stmt = $this->db->prepare(
        'SELECT id, product_id, warehouse_id, quantity, updated_at
         FROM product_stocks
         WHERE product_id = :pid AND warehouse_id = :wid
         FOR UPDATE'
    );
    $stmt->execute(['pid' => $productId, 'wid' => $warehouseId]);
    $row = $stmt->fetch(PDO::FETCHCH_ASSOC);
    if (!$row) {
        throw new StockNotFoundException(
            sprintf('Product %d not stocked at warehouse %d', $productId, $warehouseId)
        );
    }
    return Result::ok(ProductStock::fromArray($row));
}
```

> **CORRECTION (2026-09-16):** The return type is `Result` (not `ProductStock`). The method returns a
> `Result::ok($entity)` wrapper. The `ProductStock` entity has no `version` column — the optimistic locking
> `version` column mentioned in the "Option B" section below is not part of the implemented schema.
```

### 2. Transaksi Wrapper

`App\Core\Database::transaction(callable $fn)` — high-level helper:

```php
public function transaction(callable $fn): mixed
{
    $this->pdo->beginTransaction();
    try {
        $result = $fn($this);
        $this->pdo->commit();
        return $result;
    } catch (\Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
```

### 3. Goods Issue Pseudocode (final)

```php
final class GoodsIssueService
{
    public function issue(int $soId, int $actorUserId): void
    {
        $this->db->transaction(function () use ($soId, $actorUserId) {
            $so = $this->soRepo->findByIdOrFail($soId);
            $this->policy->assertCanIssue($so);  // status Approved, dsb.

            foreach ($so->items as $item) {
                // STEP 1 — lock baris stok
                $stock = $this->stockRepo->lockForUpdate(
                    $item->productId, $so->sourceWarehouseId
                );

                // STEP 2 — cek kecukupan
                if ($stock->quantity < $item->qty) {
                    throw new InsufficientStockException(
                        sprintf('Stok tidak mencukupi untuk %s (butuh %d, ada %d)',
                            $item->productId, $item->qty, $stock->quantity)
                    );
                }

                // STEP 3 — decrement + ledger
                $this->stockRepo->decrement(
                    $item->productId, $so->sourceWarehouseId, $item->qty
                );
                $this->ledgerRepo->insert([
                    'type'           => 'Issue',
                    'ref_type'       => 'SO',
                    'ref_id'         => $soId,
                    'product_id'     => $item->productId,
                    'warehouse_id'   => $so->sourceWarehouseId,
                    'qty'            => -$item->qty,
                    'done_by_user_id'=> $actorUserId,
                    'done_at'        => date('Y-m-d H:i:s'),
                ]);
            }

            // STEP 4 — update SO status
            $this->soRepo->updateStatus($soId, 'Fulfilled', [
                'issued_by_user_id' => $actorUserId,
                'issued_at'         => date('Y-m-d H:i:s'),
            ]);
        });
    }
}
```

Goods Receipt mengikuti pola yang sama, tanpa cek negative (hanya increment).

### 4. Transaction Isolation Level

Kami tetap pada **default REPEATABLE READ** InnoDB. Tidak ada kebutuhan eksplisit untuk set level lain.

> Catatan: Pada REPEATABLE READ + InnoDB, `SELECT ... FOR UPDATE` akan melakukan **consistent read with locking**, sesuai dokumentasi MySQL. Tidak ada gap lock issue karena query kunci tepat pada baris yang dimaksud (composite key lookup).

### 5. Test Strategy

**Unit test (tanpa DB):** Pakai `FakeProductStockRepository` yang punya pre-set stock & bisa simulate lock contention dengan deterministic order.

**Integration test (dengan MySQL real):**
- `test_concurrent_goods_issue_two_threads_one_succeeds` — pakai `pcntl_fork` atau sequential simulation dengan 2 PDO connection di thread berbeda. Assertion: tepat 1 success, 1 reject, stok akhir = 10 - qty_success.
- `test_goods_issue_rollback_no_partial_update` — Force exception setelah update stock, sebelum insert ledger → assertion: stok & ledger tidak berubah.
- `test_ledger_invariant` — Random sequence 50 operations (mix receipt/issue), assertion: `SUM(ledger.qty) + initial == final_stock`.

---

## Consequences

### Positif
- **Defensibility** — Mudah diuji, mudah dijelaskan.
- **Konsistensi** — Stock + ledger selalu in-sync (all-or-nothing via transaksi).
- **Cocok MySQL InnoDB** — Tidak perlu trigger, noSQL, atau middleware.

### Negatif / Trade-off
- **Lock contention** — Dua user issue di product+warehouse yang sama harus antri. Untuk skala menengah ini tidak masalah (latency ms).
- **Lock dapat hold selama query lain** — Karena operasi di dalam transaksi sangat pendek (1-2 query + insert), tidak ada long-running lock.
- **Tidak optimal untuk distributed deployment** — Tapi ini di luar scope (Brief: single-instance Docker).

---

## Alternatives Considered

### Opsi A: Pessimistic SELECT FOR UPDATE → **DITERIMA**
### Opsi B: Optimistic version column → ditolak (lihat §Why NOT)
### Opsi C: Application-level distributed lock (Redis, file lock) → Ditolak (overkill, butuh infra tambahan)
### Opsi D: DB trigger-based check → Ditolak (logika bisnis di trigger sulit diuji di PHP unit test)
### Opsi E: Skip mekanisme → **Ditolak keras** — langsung critical failure per Brief §3.1

---

## Validation / How to Verify

- [x] ARCH-02 AC1 — 2 issue simultan → 1 sukses, 1 gagal.
- [x] ARCH-02 AC2 — Force error di tengah → rollBack, tabel tidak berubah.
- [x] ARCH-02 AC3 — Konsistensi ledger vs stock.
- [x] BR-008 / BR-009 / BR-010 / BR-015 enforced.
- [x] Demo ready untuk assessor (Brief §8.1).

---

## References
- `docs/planning/prd.md` §9 ARCH-02, §6 PO-01 FR-6.8, §6 SO-01 FR-7.10
- `docs/planning/prd.md` §1 BR-008, BR-009, BR-010, BR-015
- `Project Brief - Programmer.pdf` §3.1 ARCH-02, §8.1 demo flow
- MySQL Documentation: [InnoDB Locking](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking.html)
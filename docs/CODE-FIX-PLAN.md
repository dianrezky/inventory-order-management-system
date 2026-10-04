# Code Fix Plan — 2 Temuan dari Analisis Presentasi

> **Status: PLAN SAJA.** Perubahan kode adalah `HUMAN-OWNED` (AGENT.md). Dokumen ini merinci *apa* dan *bagaimana*, bukan menerapkannya. Terapkan sendiri atau beri instruksi eksplisit "implementasikan".
>
> Konvensi proyek yang relevan: komentar `//` saja (bukan docblock); pesan sebagai literal di tempat; assignment hasil pakai `if/else` eksplisit, bukan ternary.

---

## Temuan #1 — `PurchaseOrderService::create` non-transaksional (P1, standard tier)

### Masalah [Verified]
`app/Service/PurchaseOrderService.php:137-152` menyisipkan header lalu item sebagai **dua panggilan terpisah** tanpa transaksi:
```php
$createResult = $this->purchaseOrderRepository->create([... header ...]);   // :137
// ...
$itemsResult = $this->createItems($createResult->data, $normalizedItems);    // :149 (loop insert)
```
Jika salah satu `createItems()` gagal di tengah loop, header PO sudah tersimpan dengan item parsial → PO rusak (tanpa kompensasi/rollback). Jalur Goods Receipt/Issue sudah transaksional; hanya `create` PO (dan—lihat catatan—SO) yang belum.

### Akar penyebab
`PurchaseOrderService` **tidak** meng-inject `TransactionManagerInterface` (konstruktor `:27-41` hanya menerima 5 repo + eventLog). Jadi service tidak punya cara memulai transaksi.

### Rencana perubahan
1. **`app/Service/PurchaseOrderService.php`**
   - Tambah dependency `TransactionManagerInterface $transactionManager` di konstruktor (ikuti pola `GoodsReceiptService`/`GoodsIssueService`). Simpan ke properti `$this->transactionManager`.
   - Di `create()`, bungkus header+item:
     ```php
     $this->transactionManager->beginTransaction();
     // create header; if fail -> rollBack(); return
     // createItems(); if fail -> rollBack(); return
     $this->transactionManager->commit();
     ```
   - `catch (\Throwable)` yang sudah ada (`:167-172`) tambahkan `rollBack()` di awal blok (aman karena `Database::rollBack()` sudah di-guard `inTransaction()`).
   - `logEvent()` tetap **setelah** commit (konsisten dengan service lain).
2. **`app/Core/Container.php`** — `getPurchaseOrderService()` (`:343-353`): tambahkan `$this->getDatabase()` sebagai argumen transaction manager (urutan sesuai konstruktor baru).

### Dampak & kompatibilitas
- Konstruktor berubah → **semua test yang mengonstruksi `PurchaseOrderService` langsung** harus diperbarui (tambah argumen). Buat argumen transaction manager **nullable default null**? → TIDAK disarankan untuk transaksi (harus selalu ada di produksi). Alternatif aman: pakai `FakeTransactionManager` (`app/Core/FakeTransactionManager.php`) di test — sudah tersedia untuk ini.
- Perilaku sukses tidak berubah; hanya menambah atomicity pada kegagalan.

### Verifikasi
- `composer stan` (PHPStan level 5) harus lolos.
- `composer test` — perbarui `PurchaseOrderServiceTest` (konstruksi + mungkin 1 test baru: simulasikan `createItems` gagal → assert header TIDAK tersisa). Butuh fake repo yang bisa dipaksa gagal di item ke-N.
- Integration (opsional): test transaksi PO create rollback mirip `GoodsReceiptTest`.

### Risiko / rollback
- Risiko rendah; perubahan terlokalisasi di 1 service + 1 wiring. Rollback = revert kedua file.
- Risk tier: **standard** (perubahan perilaku pada jalur kegagalan). Bukan high-risk (tidak menyentuh ARCH-02/SOD).

### Catatan terkait (opsional, pertimbangkan sekalian)
`SalesOrderService::create` (`app/Service/SalesOrderService.php:135-142`) punya pola serupa: memanggil `salesOrderRepository->create($header, $items)` yang me-loop insert item **di dalam satu method repo** tapi **juga tanpa transaksi**. Kalau ingin konsisten, bungkus juga dengan transaction manager (perubahan sejenis: inject TM ke `SalesOrderService`, update Container `getSalesOrderService`). Nilai + biaya mirip Temuan #1.

---

## Temuan #2 — Blok duplikat di `SalesOrderMySQLRepository::ownerAndStatusFilters` (P6, lightweight tier)

### Masalah [Verified]
`app/Repository/MySQL/SalesOrderMySQLRepository.php:497-503` menulis blok yang sama dua kali:
```php
if ($userId !== null) {
    $result['so.created_by'] = $userId;
}

if ($userId !== null) {           // <-- duplikat, idempoten (tidak berbahaya)
    $result['so.created_by'] = $userId;
}
```

### Dampak
Tidak ada bug fungsional (assignment idempoten) — murni kerapian/keterbacaan. Ownership filter tetap benar.

### Rencana perubahan
1. **`app/Repository/MySQL/SalesOrderMySQLRepository.php`** — hapus blok `if` kedua (`:501-503`), sisakan satu.

### Verifikasi
- `composer stan` lolos.
- `composer test` — tidak ada perubahan perilaku; test ownership/BR-018 yang ada (`SalesOrderApprovalPolicyTest`, filter list) tetap hijau sebagai regресi guard.

### Risiko / rollback
- Hampir nol. Risk tier: **lightweight** (inert, 1 file, tanpa dampak perilaku). Rollback = revert 1 file.

---

## Urutan eksekusi yang disarankan
1. Temuan #2 dulu (trivial, cepat hijau) — commit terpisah.
2. Temuan #1 (PO transaksional) + perbarui test — commit terpisah.
3. (Opsional) konsistensi SO create — commit terpisah.

Masing-masing commit kecil & fokus agar mudah di-review dan di-rollback. Jalankan `composer stan && composer test` sebelum tiap commit.

> Ingatkan: **Git state changes & penerapan SQL juga HUMAN-OWNED.** Saya bisa menulis diff/patch persis jika Anda minta "implementasikan Temuan #X", tapi Anda yang menjalankan commit/test.

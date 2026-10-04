# IOMS — Kartu Kilat Q&A (Hafalan Cepat)

> Baca 15 menit sebelum masuk ruang sidang. Format: **T** (tanya) → **J** (jawab singkat & padat).
> Semua jawaban terverifikasi dari kode. Detail panjang ada di `PRESENTATION-DEFENSE-GUIDE.md`.

---

## 🔴 WAJIB HAFAL (paling sering ditanya)

**T1. Bagaimana aplikasi mencegah overselling?**
J: Transaksi DB + `SELECT … FOR UPDATE`. Di `GoodsIssueService`: kunci baris SO (re-cek status Approved) → kunci baris `product_stocks` → cek stok cukup → kurangi → tulis ledger → commit. Transaksi kedua menunggu lock, lalu baca stok terbaru; kalau kurang → rollback. Stok tak pernah minus.

**T2. Apa yang terjadi kalau 2 Goods Issue jalan bersamaan (stok 10, minta 8 & 7)?**
J: A dapat lock, kurangi jadi 2, commit. B menunggu lock, setelah A commit baca stok = 2, `2 < 7` → `InsufficientStockException` → rollback. Hasil akhir stok = 2, bukan -5.

**T3. Bagaimana Segregation of Duties diterapkan?**
J: `SalesOrderPolicy::assertCanDecide($isActorAdmin)` — penolakan berbasis **role**, bukan perbandingan pembuat. Sales tidak pernah approve. Admin boleh approve (termasuk SO buatannya sendiri). Dijaga 2 lapis: permission `sales_orders.approve` di controller + policy di service.

**T4. Kenapa menyembunyikan tombol di frontend tidak cukup?**
J: Tombol hanya HTML. Penyerang bisa `POST /sales-orders/{id}/approve` via curl/Postman tanpa UI. Server adalah satu-satunya titik yang tak bisa dilewati. UI hiding = UX, bukan security.

**T5. Bagaimana memastikan perubahan stok atomik?**
J: `product_stocks` + `stock_ledger` dalam satu transaksi (`Database.php` begin/commit/rollBack). Kalau ledger gagal setelah stok berhasil → rollback keduanya. DB tak pernah setengah jadi.

**T6. Kenapa Controller → Service → Repository?**
J: Separation of concerns. Controller tipis (guard + mapping), logic di service (teruji tanpa HTTP), SQL terpusat di repository. Memudahkan test & maintenance.

---

## 🟡 ARSITEKTUR

**T7. DI tanpa framework?** J: `Container.php` manual, tiap `get*()` memoize 1 instance/request (`??= new`).
**T8. Apa itu `Result`?** J: Objek hasil seragam: code (0 sukses/1 validasi/2 internal), info, data.
**T9. Peran Policy?** J: Aturan domain murni (SOD/transisi), tanpa DB/HTTP, bisa unit-test.
**T10. Routing?** J: Array konfigurasi bersarang → regex match → controller/action (`routes.php`, `index.php`).
**T11. Kenapa filter via POST?** J: Agar state tak masuk URL; `requestParam()` baca `$_POST` saja.
**T12. Repository Fake untuk apa?** J: Unit-test service tanpa MySQL.

## 🟡 DATABASE

**T13. Current stock disimpan di mana?** J: `product_stocks`, 1 baris per product+warehouse (UNIQUE).
**T14. Kenapa simpan stok padahal ada ledger?** J: Baca O(1) tanpa `SUM` tiap request; invariant `stock = SUM(ledger)` dijaga transaksi & diuji.
**T15. Kenapa ledger dipisah?** J: Audit trail immutable (append-only), menunjuk balik ke PO/SO.
**T16. FK strategy?** J: Master data `RESTRICT`; item→header `CASCADE`; audit→user `SET NULL`.
**T17. ENUM status PO?** J: Draft, Ordered, PartiallyReceived, Received, Cancelled.
**T18. ENUM status SO?** J: Draft, PendingApproval, Approved, Fulfilled, Cancelled.
**T19. Kenapa MySQL?** J: Relasional, ACID, FK, row lock untuk integritas transaksi.

## 🟡 SECURITY

**T20. Password?** J: `password_hash(..., PASSWORD_BCRYPT)` + `password_verify`. Tak pernah plaintext.
**T21. SQL injection?** J: Prepared statement + bind semua nilai; `EMULATE_PREPARES=false`.
**T22. CSRF?** J: Token per-session, embed di form, cek `hash_equals` tiap POST.
**T23. XSS?** J: `htmlspecialchars(ENT_QUOTES)` di semua output.
**T24. Session?** J: HttpOnly + SameSite=Lax + Secure (prod), regenerate saat login, idle timeout.
**T25. IDOR?** J: ID di URL ter-obfuscate (`IdObfuscator`, hex) + authz/ownership server-side.
**T26. Upload aman?** J: MIME via `finfo`+`getimagesize`, max 2MB, nama acak (anti traversal), re-encode WebP.
**T27. CSV injection?** J: Nilai diawali `= + - @` diberi prefix `'` (`escapeCsvField`).

## 🟡 AUTHENTICATION & AUTHORIZATION

**T28. Flow login?** J: `AuthController::loginAction` (cek CSRF) → `AuthService::login` → `findByEmail` → `password_verify` → session regenerate + simpan user_id/role.
**T29. User dinonaktifkan saat login?** J: Request berikut `currentUser()` lihat `is_active=false` → session dihancurkan.
**T30. Role→permission di mana?** J: Tabel `role_permissions` via `PermissionService` (cache Memcached 1 jam).
**T31. Sales lihat SO orang lain?** J: 3 lapis — list filter `created_by`, detail `forbidden()`, query scoping. + ID obfuscated.

## 🟡 CONCURRENCY

**T32. Kenapa `FOR UPDATE`?** J: Kunci baris stok → transaksi lain menunggu → cek-lalu-kurangi aman.
**T33. Kenapa lock baris SO juga?** J: Menutup race double-issue/issue-vs-cancel (re-cek status di locking read).
**T34. Kenapa lock hanya di Issue/Receipt?** J: Hanya di situ ada pola cek-lalu-ubah stok; operasi lain tak ubah stok.
**T35. Dibuktikan bagaimana?** J: `GoodsIssueConcurrencyTest` jalankan 2 proses PHP OS paralel via `proc_open`; asert tepat 1 sukses, 1 gagal, stok ≥ 0, 1 baris ledger.

## 🟡 DOCKER

**T36. Service apa saja?** J: app (php -S:8080), cron (low-stock 15 menit), db (mysql:8.0), redis (session), memcached (cache); MinIO eksternal.
**T37. Boot dari nol?** J: `docker compose up --build` → db init schema+seed → app & cron jalan setelah healthy.
**T38. Redis vs Memcached?** J: Redis = session store; Memcached = cache aplikasi (permission).

## 🟡 LAIN-LAIN

**T39. Low stock?** J: Level produk — `SUM(product_stocks.quantity) < reorder_point` (total lintas gudang), konsisten di dashboard/filter Produk/cron; cron buat notifikasi ter-dedup.
**T40. API?** J: `GET /api/products/{sku}/availability` (butuh auth) → JSON 200/404/401.
**T41. Frontend?** J: HTML/CSS/Vanilla JS + Fetch; AJAX nyata di Stock Ledger (POST filter). i18next EN/ID.
**T42. Testing?** J: PHPUnit (Unit+Integration) 16 file/118 method, PHPStan level 5, Playwright e2e (standalone).
**T43. Kenapa native PHP?** J: Requirement + tunjukkan pemahaman fundamental tanpa bersembunyi di framework.
**T44. Kenapa soft delete?** J: Jaga integritas histori. Category boleh hard delete hanya jika tak ada produk (FK RESTRICT backstop).

---

## ⚠️ KALAU DITANYA KELEMAHAN (jawab jujur)
- PO create **non-transaksional** (header + loop item) → bisa tertinggal parsial. Sudah saya catat sebagai improvement.
- `barcode` disimpan tanpa uniqueness.
- e2e Playwright "not run in CI" (skrip standalone).
- `php -S` bukan untuk produksi (idealnya nginx+fpm).

> **Prinsip emas:** kalau tidak yakin, bilang "perlu saya cek di kode" — jangan mengarang. Reviewer menghargai kejujuran teknis.

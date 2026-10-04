# IOMS — Kerangka Slide Presentasi

> Siap dipindah ke PowerPoint/Google Slides. Tiap slide: **Judul · Bullet · Catatan pembicara**.
> Estimasi 18 slide ≈ 15–20 menit. Sesuaikan durasi.

---

### Slide 1 — Judul
- **Inventory & Order Management System (IOMS)**
- Nama Anda · PT Neuronworks Indonesia · Intermediate Programmer Final Project
- *Catatan:* perkenalkan diri + satu kalimat tujuan: "sistem internal untuk mengelola stok & pesanan dengan integritas data terjamin."

### Slide 2 — Masalah Bisnis
- Stok tidak akurat → kerugian
- Overselling (jual melebihi stok)
- Perubahan stok tanpa jejak audit
- Tanggung jawab antar-user tercampur
- *Catatan:* tekankan ini masalah nyata bisnis yang mengelola barang fisik.

### Slide 3 — Solusi & Pengguna
- Solusi: stok akurat real-time, anti-overselling, audit trail, pemisahan peran
- 3 role: Admin · Sales · Warehouse Staff
- *Catatan:* sebut tanggung jawab tiap role dalam 1 kalimat.

### Slide 4 — Alur Bisnis (Big Picture)
- PO → Goods Receipt (stok +) → SO → Approval → Goods Issue (stok −) → selesai
- *Catatan:* ini peta besar; modul lain mendukung alur ini.

### Slide 5 — Tech Stack
- PHP 8.3 native OOP (tanpa framework) · MySQL 8 · HTML/CSS/Vanilla JS + Fetch · Docker
- *Catatan:* tegaskan "native" = pemahaman fundamental, bukan keterbatasan.

### Slide 6 — Arsitektur Layered
- Diagram: Browser → Router → Controller → Service → Repository → MySQL (+ Entity, Policy, Core)
- DI: Container manual (memoized)
- *Catatan:* jelaskan tanggung jawab tiap layer singkat; "controller tipis, logic di service, SQL di repository."

### Slide 7 — Kenapa Arsitektur Ini?
- Testable (logic tanpa HTTP/DB) · SQL terpusat · reusable · maintainable
- Trade-off: lebih banyak file, dibayar dengan kualitas
- *Catatan:* siapkan jawaban "kenapa tak langsung controller→repository".

### Slide 8 — Database Design
- Diagram ERD ringkas (users, products, product_stocks, PO/SO + items, stock_ledger, role_permissions)
- `product_stocks`: UNIQUE(product, warehouse); `stock_ledger`: append-only
- *Catatan:* tunjuk FK RESTRICT vs CASCADE.

### Slide 9 — Authentication
- bcrypt · session regenerate (anti-fixation) · HttpOnly/SameSite cookie · idle timeout · reload user tiap request
- *Catatan:* demo login + jelaskan apa yang terjadi di balik layar.

### Slide 10 — Authorization (server-side, 3 lapis)
- Controller permission (`role_permissions`) → Service/Policy → Query ownership
- "UI hiding BUKAN authorization"
- *Catatan:* ini slide penting; hubungkan ke Slide 11 & 12.

### Slide 11 — Segregation of Duties (BR-001)
- `assertCanDecide($isAdmin)` — role-based denial
- Sales NEVER approve · Admin boleh approve (termasuk SO sendiri)
- *Catatan:* antisipasi trick question "Admin approve SO sendiri".

### Slide 12 — Anti-Overselling (ARCH-02) ⭐
- Transaksi + `SELECT … FOR UPDATE` (lock SO + lock product_stocks)
- Sekuens: lock → cek → kurangi → ledger → commit; gagal → rollback
- Contoh: stok 10, A=8 & B=7 → B ditolak, stok = 2
- *Catatan:* INI slide andalan. Pelan-pelan, pakai contoh angka.

### Slide 13 — Atomicity Transaksi
- `product_stocks` + `stock_ledger` dalam 1 transaksi
- Gagal salah satu → rollback keduanya
- Invariant: `stock = SUM(ledger)`
- *Catatan:* jawab "bagaimana kalau ledger sukses tapi stok gagal".

### Slide 14 — Keamanan (ringkas)
- Prepared statements · CSRF `hash_equals` · XSS escaping · ID obfuscation · upload MIME+WebP · CSV injection guard
- *Catatan:* sebut cepat sebagai checklist; detail di Q&A.

### Slide 15 — Docker & Environment
- 5 service: app · cron · db · redis · memcached (+ MinIO eksternal)
- `docker compose up` → db auto-init schema+seed
- *Catatan:* tekankan "environment konsisten, sekali up".

### Slide 16 — Testing & QA
- PHPUnit 16 file/118 method · PHPStan level 5 · Playwright e2e
- Highlight: concurrency (2 proses OS) & SOD (3 lapis)
- *Catatan:* tunjukkan test anti-oversell kalau sempat.

### Slide 17 — Design Decisions & Trade-offs
- Tabel ringkas: FOR UPDATE, stok+ledger, authz server-side, native PHP, soft delete
- *Catatan:* pilih 3–4 yang paling mungkin ditanya.

### Slide 18 — Keterbatasan & Rencana Pengembangan
- Jujur: PO create non-transaksional, `php -S` dev-only, e2e belum di CI
- Future: dynamic RBAC, inventory reservation, rate limiting, observability, API token
- *Catatan:* tutup dengan sikap reflektif — tahu batasan = engineer dewasa.

### (Opsional) Slide 19 — Terima Kasih / Q&A
- Kontak + "siap menerima pertanyaan"

---

**Tips penyampaian:**
- Slide 12 (anti-oversell) dapat porsi waktu terbesar — itu nilai jual utama.
- Siapkan 1 layar kode terbuka: `GoodsIssueService.php` untuk ditunjuk live.
- Kalau waktu mepet, gabungkan Slide 14 & 17 ke pembahasan Q&A.

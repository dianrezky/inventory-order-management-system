# Panduan Presentasi Docker

Inventory & Order Management System — PT Neuronworks Indonesia.
Diverifikasi langsung di mesin pada 2026-10-02 (Docker 26.0.0, Compose v2.26.1).

> Cara pakai dokumen ini saat presentasi:
> - **BAGIAN A** = 7 poin wajib demo. Ikuti urutannya dari atas ke bawah.
> - **BAGIAN B** = 8 best practice. Baca kalau penguji bertanya soal kualitas.
> - **BAGIAN C** = cheat sheet perintah, untuk ditempel di sebelah layar.

---

# BAGIAN A — 7 Poin Wajib Demo

## Ringkasan status

| # | Poin wajib | Status |
|---|------------|--------|
| 1 | Docker terpasang | ✅ |
| 2 | Tunjukkan Dockerfile | ✅ |
| 3 | Jelaskan tiap instruksi | ✅ |
| 4 | build, run, Up, browser, log | ✅ |
| 5 | Jalankan via Docker Compose | ✅ |
| 6 | compose pakai `build: .` | ✅ |
| 7 | Database sebagai service + terhubung | ✅ |

---

## Poin 1 — Docker terpasang

```powershell
docker --version          # Docker version 26.0.0, build 2ae903e
docker compose version    # Docker Compose version v2.26.1-desktop.1
docker ps                 # daemon aktif, menampilkan container berjalan
```

**Kalimat untuk diucapkan:** "Docker Desktop sudah terpasang dan daemon-nya aktif, terbukti dari versi dan daftar container yang berjalan."

---

## Poin 2 — Tunjukkan Dockerfile

Buka file [`Dockerfile`](../Dockerfile) di root proyek (satu folder dengan `compose.yaml`).

**Kalimat untuk diucapkan:** "Dockerfile ada di root repository, memakai base image resmi PHP 8.3."

---

## Poin 3 — Jelaskan tiap instruksi Dockerfile

**Konsep dasar (ucapkan sebelum masuk tabel).** Docker membangun image secara bertumpuk. Tiap instruksi membuat satu **layer** yang di-cache; selama instruksi & input-nya tidak berubah, layer dipakai ulang (build cepat). Kalau satu layer berubah, semua layer **di bawahnya** ikut dibangun ulang. Karena itu **urutan penting**: yang jarang berubah di atas, kode (sering berubah) di bawah.

Baca kolom kanan sambil menunjuk baris di editor.

| Lokasi | Instruksi | Fungsi singkat | Penjelasan untuk dibacakan |
|--------|-----------|-----------------|----------------------------|
| [`Dockerfile:1`](../Dockerfile#L1) | `FROM php:8.3.20-cli` | Base image resmi PHP 8.3 (syarat PHP 8.2+), dipin sampai patch agar reproducible. | Titik awal image — kita menumpang image resmi PHP, bukan mulai dari nol. Varian `-cli` = PHP command-line (tanpa Apache bawaan) karena kita pakai server bawaan PHP sendiri. Versi dipin sampai patch (`8.3.20`) supaya setiap build dapat PHP persis sama (reproducible). Memenuhi syarat brief "PHP 8.2+". |
| [`Dockerfile:4-14`](../Dockerfile#L4-L14) | `RUN apt-get install ... cron` | Pasang library sistem untuk GD (png/jpeg/webp), zip, curl, git, dan `cron` (job low-stock). | `RUN` = jalankan perintah shell saat build. Memasang library **sistem** (level OS Debian): prasyarat agar GD bisa dikompilasi (png, jpeg, webp, freetype), plus `libzip`, `curl`, `unzip`, `git`, dan `cron` (penjadwal job low-stock). `update` + `install` + `rm -rf .../apt/lists/*` sengaja digabung dalam **satu `RUN`** supaya cache apt tidak tersimpan di layer → image tetap kecil. |
| [`Dockerfile:17-24`](../Dockerfile#L17-L24) | `pecl install memcached` | Ekstensi PHP Memcached (cache). | Ekstensi **Memcached** (cache aplikasi). `pecl install` mengunduh & meng-compile ekstensi dari repositori PECL; butuh `libsasl2-dev`/`libssl-dev` agar `configure`-nya berhasil. `docker-php-ext-enable` mendaftarkan ekstensi ke konfigurasi PHP. |
| [`Dockerfile:27-28`](../Dockerfile#L27-L28) | `pecl install redis` | Ekstensi PHP Redis (session). | Sama polanya, ekstensi **Redis** — dipakai untuk menyimpan **session**. |
| [`Dockerfile:31-32`](../Dockerfile#L31-L32) | `docker-php-ext-install gd pdo_mysql opcache curl` | `pdo_mysql` untuk koneksi DB, `gd` untuk gambar, `opcache` untuk performa, `curl` untuk MinIO. | `docker-php-ext-install` = helper untuk ekstensi **bawaan** PHP (beda dari pecl yang untuk pihak ketiga). Yang dipasang: `pdo_mysql` (**wajib**, koneksi MySQL via PDO), `gd` (gambar), `opcache` (cache bytecode → cepat), `curl` (klien MinIO). `-j"$(nproc)"` = compile paralel pakai semua core CPU. |
| [`Dockerfile:35-41`](../Dockerfile#L35-L41) | `RUN { echo 'opcache...' }` | Tulis konfigurasi OPcache dan `memory_limit`. | Menulis file konfigurasi OPcache ke folder `conf.d` PHP (mengaktifkan OPcache + mengatur memori & jumlah file yang di-cache). Tuning performa. |
| [`Dockerfile:44`](../Dockerfile#L44) | `RUN echo 'memory_limit=512M'` | Naikkan memory_limit CLI untuk PHPStan. | Menaikkan batas memori PHP CLI jadi 512M, khusus supaya PHPStan tidak kehabisan memori. |
| [`Dockerfile:47`](../Dockerfile#L47) | `COPY --from=composer:2` | Ambil binary Composer dari image lain (multi-stage). | Teknik **multi-stage**: menyalin binary Composer langsung dari image resmi `composer:2` (`--from=...` = ambil file dari image lain). Lebih bersih daripada install manual. |
| [`Dockerfile:49`](../Dockerfile#L49) | `WORKDIR /var/www/html` | Direktori kerja aplikasi. | Menetapkan direktori kerja di dalam image; semua `RUN`/`COPY`/`CMD` sesudahnya relatif ke folder ini (seperti `cd` permanen). |
| [`Dockerfile:53-54`](../Dockerfile#L53-L54) | `COPY composer.json composer.lock ./` lalu `composer install` | Dependency disalin **sebelum** kode, agar layer cache tetap valid saat kode berubah. | Inti **optimasi cache**: hanya file dependency disalin dulu, baru install. Selama `composer.json`/`composer.lock` tidak berubah, layer install tetap dari cache walau kode berubah. Flag: `--no-scripts`, `--no-interaction`, `--no-progress`, `--prefer-dist` (ambil versi dist, lebih cepat). |
| [`Dockerfile:56`](../Dockerfile#L56) | `COPY . .` | Salin source aplikasi ke image. | Baru di sini seluruh source code disalin ke image. Ditaruh paling akhir karena paling sering berubah. |
| [`Dockerfile:58`](../Dockerfile#L58) | `composer dump-autoload --optimize` | Buat autoload PSR-4 teroptimasi. | Membangun ulang autoload PSR-4 mode optimized (classmap statis) supaya loading class lebih cepat di runtime. |
| [`Dockerfile:61-62`](../Dockerfile#L61-L62) | `COPY docker/cron/low-stock.cron` | Daftarkan jadwal cron (dipakai service `cron`). | Mendaftarkan jadwal **cron** job low-stock. File di `/etc/cron.d/` wajib permission `0644` agar cron mau membacanya. |
| [`Dockerfile:65-66`](../Dockerfile#L65-L66) | `COPY ... entrypoint.sh` | Entrypoint memasang `vendor/` saat start pertama pada clone bersih. | Menyalin skrip **entrypoint**. `sed` membuang carriage-return Windows (CRLF) agar jalan di Linux; `chmod +x` membuatnya executable. Entrypoint memasang `vendor/` saat start pertama pada clone bersih. |
| [`Dockerfile:67`](../Dockerfile#L67) | `ENTRYPOINT ["iom-entrypoint"]` | Selalu jalan sebelum command utama. | Perintah yang **selalu dijalankan lebih dulu** setiap container start, sebelum `CMD`. Cocok untuk langkah inisialisasi. |
| [`Dockerfile:69`](../Dockerfile#L69) | `EXPOSE 8080` | Dokumentasi: aplikasi mendengarkan port 8080. | **Dokumentasi** bahwa aplikasi mendengarkan di port 8080. Tidak otomatis membuka port ke host (publikasi tetap lewat `-p` di run atau `ports:` di compose). |
| [`Dockerfile:71`](../Dockerfile#L71) | `CMD ["php","-S","0.0.0.0:8080","-t","public","public/index.php"]` | Command default: PHP built-in server dengan document root `public/`. | Perintah **default** saat container jalan: web server bawaan PHP di `0.0.0.0:8080`, document root `public/`, router `public/index.php`. `0.0.0.0` = menerima koneksi dari luar container. |

### Perbedaan yang sering ditanya penguji

- **`ENTRYPOINT` vs `CMD`** — ENTRYPOINT selalu jalan (fixed), CMD adalah argumen default yang mudah ditimpa. Keduanya digabung: entrypoint inisialisasi → lalu menjalankan CMD.
- **`RUN` vs `CMD`** — `RUN` dieksekusi **saat build** (membentuk image), `CMD` dieksekusi **saat container start** (runtime).
- **`pecl install` vs `docker-php-ext-install`** — pecl untuk ekstensi pihak ketiga (redis, memcached); docker-php-ext-install untuk ekstensi bawaan PHP (gd, pdo_mysql, opcache, curl).

---

## Poin 4 — Jalankan langsung dari Dockerfile

Jalankan berurutan dari root proyek.

```powershell
# a. docker build
docker build -t iom-app:demo .

# b. docker run (pakai network Compose agar bisa akses db/redis/memcached)
docker run -d --name iom_demo -p 8091:8080 `
  --network inventory-order-management-system_iom_net `
  -v "${PWD}:/var/www/html" `
  -e DB_HOST=db -e DB_PORT=3306 -e DB_NAME=inventory_order_management `
  -e DB_USER=iom_app -e DB_PASSWORD=change_me_in_local_env `
  -e REDIS_HOST=redis -e MEMCACHED_HOST=memcached `
  iom-app:demo

# c. container berstatus Up
docker ps --filter name=iom_demo
#   iom_demo   Up ...   0.0.0.0:8091->8080/tcp

# d. akses browser
#   http://localhost:8091/login   -> HTTP 200

# e. log tanpa fatal error
docker logs iom_demo | Select-String -Pattern "fatal" -CaseSensitive:$false   # kosong

# bersihkan
docker rm -f iom_demo
```

**Checklist yang harus terlihat saat demo:**
- [x] build selesai tanpa error
- [x] `docker ps` menampilkan status **Up**
- [x] browser membuka halaman login (HTTP 200)
- [x] `docker logs` tidak memuat kata "fatal"

**Catatan jujur (untuk jaga-jaga ditanya):** log bisa memuat `Failed to poll event`. Itu pesan worker dari `php -S` saat `PHP_CLI_SERVER_WORKERS` > 1, **bukan** fatal error, dan tidak mengganggu respons.

---

## Poin 5 — Jalankan dengan Docker Compose

```powershell
docker compose up --build -d
docker compose ps
```

Hasil yang diverifikasi:

```
NAME            SERVICE     STATUS
iom_app         app         Up (healthy)
iom_cron        cron        Up
iom_db          db          Up (healthy)
iom_redis       redis       Up (healthy)
iom_memcached   memcached   Up (healthy)
```

Browser: `http://localhost:8090` → redirect ke `/login` (HTTP 200).

---

## Poin 6 — compose membangun dari Dockerfile (`build: .`)

Buka [`compose.yaml`](../compose.yaml), tunjuk baris ini:

```yaml
services:
  app:
    build: .          # membangun dari Dockerfile di root, bukan image jadi
  cron:
    build: .          # service cron memakai image yang sama
```

**Kalimat untuk diucapkan:** "File bernama `compose.yaml`, dan service aplikasi dibangun dengan `build: .`, persis sesuai ketentuan."

---

## Poin 7 — Database sebagai service dan bukti koneksi

Service `db` di `compose.yaml`:

```yaml
db:
  image: mysql:8.0
  volumes:
    - iom_db_data:/var/lib/mysql                              # data persisten
    - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro
    - ./database/seed.sql:/docker-entrypoint-initdb.d/02-seed.sql:ro
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", ...]
```

Aplikasi baru start setelah DB sehat (`depends_on: condition: service_healthy`) dan menjangkau DB lewat hostname `db`.

**Bukti koneksi (jalankan saat demo):**

```powershell
docker exec iom_app php -r '$p=new PDO(\"mysql:host=db;dbname=\".getenv(\"DB_NAME\"),getenv(\"DB_USER\"),getenv(\"DB_PASSWORD\")); echo \"tables=\".$p->query(\"show tables\")->rowCount();'
# tables=15
```

> **Catatan PowerShell:** tanda kutip ganda di dalam argumen harus di-escape dengan `\"` (bukan `"` polos), karena PowerShell punya bug lama dalam meneruskan kutip ganda ke program native (`docker.exe`) — kutip polos bisa "hilang" saat diteruskan dan memicu `Parse error` di PHP. Command di atas sudah diverifikasi jalan dan menghasilkan `tables=15`.

Schema + seed dimuat otomatis dari `database/schema.sql` dan `database/seed.sql` saat pertama start.

---

# BAGIAN B — 8 Best Practice (dijelaskan satu per satu)

## Ringkasan status

| # | Best practice | Status |
|---|---------------|--------|
| 1 | Base image bertag versi jelas | ✅ |
| 2 | Urutan layer efisien | ✅ |
| 3 | `.dockerignore` | ✅ |
| 4 | Konfigurasi via environment variable | ✅ |
| 5 | Tidak ada secret di image/repo | ✅ |
| 6 | App + DB via Compose | ✅ |
| 7 | Volume untuk data persisten | ✅ |
| 8 | Healthcheck | ✅ |

> Setiap poin punya struktur sama: **Bukti → Alasan → Risiko → Optimasi**.

---

## Best Practice 1 — Base image bertag versi jelas ✅

- **Bukti:** `php:8.3.20-cli`, `mysql:8.0`, `redis:7-alpine`, `memcached:1.6-alpine`. Tidak ada `latest`.
- **Alasan:** tag dipin supaya build selalu memakai versi sama. `php` dipin paling ketat (sampai patch).
- **Catatan:** `composer:2` hanya dipin mayor; `mysql:8.0` / `redis:7` masih bisa naik patch.
- **Optimasi lanjut:** pin `composer:2.8`; untuk reproduksi penuh pakai digest `@sha256:...`.

## Best Practice 2 — Urutan layer efisien ✅

- **Bukti:** `composer.json` + `composer.lock` disalin **sebelum** `COPY . .`, jadi ubah kode tidak memicu install dependency ulang. `rm -rf /var/lib/apt/lists/*` ada di `RUN` yang sama supaya layer kecil.
- **Alasan:** memaksimalkan cache build; ubah kode tidak membangun ulang dependency.
- **Sudah diperbaiki (2026-10-02):**
  - Build bersih (`docker build --no-cache`) kini **berhasil** — `vendor/` di image berisi dependency nyata (termasuk phpunit), bukan autoloader kosong. (Layer 26 B sebelumnya hanyalah cache basi di satu mesin.)
  - `composer.lock` disinkronkan dengan `composer update --lock` (hanya hash, **versi paket identik**), menghapus peringatan "lock file not up to date".
  - Fallback `|| echo WARNING` dihapus, jadi build **gagal keras** bila `composer install` gagal — memastikan image berdiri sendiri.
- **Optimasi lanjut:** multi-stage build `--no-dev` agar image produksi lebih kecil tanpa git/unzip/Composer.

## Best Practice 3 — `.dockerignore` ✅

- **Bukti:** file [`.dockerignore`](../.dockerignore) ada. Diperiksa di dalam image: `.env`, `.git`, `tests/`, dan `docs/` **tidak ada**. Dev-only (`tests/` 26 MB, `docs/` 3,9 MB, `*.md`) dikecualikan, jadi build context turun ke ~12 KB dan image dari 695 MB → 664 MB.
- **Alasan:** mencegah secret dan file besar masuk image; image runtime lebih ramping.
- **Catatan:** `tests/` tetap bisa dijalankan via `docker compose exec` karena bind mount menyediakannya dari host saat runtime.
- **Optimasi lanjut:** multi-stage `--no-dev` untuk buang Composer/git dari image akhir.

## Best Practice 4 — Konfigurasi via environment variable ✅

- **Bukti:** semua setting dibaca dari environment di `compose.yaml` — konfigurasi non-rahasia pakai `${VAR:-default}`, sedangkan secret pakai `${VAR:?...}` (wajib dari `.env`).
- **Alasan:** satu image bisa dipakai di banyak lingkungan tanpa ubah kode.
- **Risiko:** —
- **Optimasi:** sudah memadai.

## Best Practice 5 — Tidak ada secret di image/repo ✅

- **Bukti:** `.env` tidak ter-track git (dikonfirmasi `git ls-files`), ada di `.gitignore`, dan tidak ada di image.
- **Alasan:** credential nyata tetap di luar repository.
- **Sudah diperbaiki (2026-10-02):** semua secret di `compose.yaml` kini **wajib dari `.env`** memakai pola `${VAR:?pesan}` (`DB_PASSWORD`, `DB_ROOT_PASSWORD`, `MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, `ID_OBFUSCATION_KEY`). Tidak ada lagi password literal di file. Diverifikasi: tanpa `.env`, Compose **menolak** start dengan pesan "required variable ... is missing"; dengan `.env`, jalan normal (semua healthy, login 200, DB 15 tabel).
- **Catatan demo:** file `.env` harus ada di mesin (sudah ada, di-gitignore). Pada clone bersih, buat `.env` dulu sebelum `docker compose up` — panduan lengkap + blok `.env` siap-salin ada di [`README.md`](../README.md) bagian "Konfigurasi `.env`".
- **Optimasi lanjut:** untuk produksi pakai Docker secrets / secret manager, bukan file `.env`.

## Best Practice 6 — App + DB via Compose ✅

- **Bukti:** `docker compose ps` menampilkan `app`, `cron`, `db`, `redis`, `memcached` semua Up. Koneksi app→db terbukti (`tables=15`).
- **Alasan:** seluruh sistem berdiri dengan satu perintah dari kondisi bersih.
- **Risiko:** —
- **Optimasi:** sudah memadai.

## Best Practice 7 — Volume untuk data persisten ✅

- **Bukti:** `docker volume ls` menampilkan `iom_db_data` (MySQL) dan `iom_redis_data` (Redis). Named volume bertahan setelah `docker compose down`.
- **Alasan:** data tidak hilang saat container dibuat ulang.
- **Risiko:** hanya `down -v` yang menghapusnya; belum ada backup.
- **Optimasi:** tambahkan `mysqldump` terjadwal.

## Best Practice 8 — Healthcheck ✅

- **Bukti runtime:** `iom_app`, `iom_db`, `iom_redis`, `iom_memcached` semua berstatus **healthy**. `app` memakai `depends_on: condition: service_healthy`, jadi urutan start benar.
- **Alasan:** aplikasi baru start setelah dependensinya benar-benar siap, bukan sekadar "container start".
- **Risiko:** `cron` sengaja tanpa healthcheck (tidak melayani HTTP).
- **Optimasi:** bisa tambah healthcheck proses untuk `cron`.

---

## Kesimpulan best practice

- **Terpenuhi penuh (8/8):** semua poin ✅ setelah perbaikan 2026-10-02 (build composer berhasil + lock sinkron, secret wajib dari `.env`, image diramping, dan container `app` kini **non-root** `www-data`).
- **Keamanan user (sudah diperbaiki):** service `app` jalan sebagai `www-data` (uid 33) via `USER www-data` di Dockerfile; service `cron` override ke `root` karena daemon cron membutuhkannya. Diverifikasi: `app` → `uid=33(www-data)`, `cron` → `uid=0(root)`, semua tetap healthy.
- **Optimasi lanjut (opsional, bukan kekurangan):** pin `composer:2.8` + digest, multi-stage build `--no-dev`, Nginx + PHP-FPM untuk produksi, backup volume MySQL.

---

# BAGIAN C — Cheat Sheet Urutan Demo

```
1. docker --version  &&  docker compose version
2. Buka Dockerfile  -> jelaskan tabel Poin 3
3. docker build -t iom-app:demo .
4. docker run ...  ->  docker ps  ->  buka browser  ->  docker logs
5. docker rm -f iom_demo
6. docker compose up --build -d  ->  docker compose ps  ->  buka http://localhost:8090
7. Buka compose.yaml  -> tunjuk "build: ."
8. Tunjuk service db  ->  docker exec iom_app php -r '... PDO ...'
```

**Jika ditanya kualitas/best practice** → buka BAGIAN B, jawab per poin dengan pola Bukti → Alasan → Risiko → Optimasi.

---

# BAGIAN D — Penjelasan Lengkap Semua Command Demo

Daftar command yang sama persis dengan [`DOCKER-COMMANDS.md`](DOCKER-COMMANDS.md), tapi di sini tiap command dijelaskan: buat apa, dan apa yang terjadi saat dijalankan.

| # | Command | Penjelasan untuk dibacakan |
|---|---------|------------------------------|
| 1 | `docker --version` | Menampilkan versi **Docker Engine** yang terpasang di mesin. Membuktikan Docker benar-benar sudah ter-install, bukan hanya ter-klaim. |
| 2 | `docker compose version` | Menampilkan versi **Docker Compose** (plugin `compose` v2). Dipakai nanti untuk menjalankan banyak service sekaligus (app, db, redis, memcached, cron) dari satu file `compose.yaml`. |
| 3 | `docker ps` | Menampilkan daftar **container yang sedang berjalan** saat ini. Kalau perintah ini berhasil keluar tabel (bukan error "cannot connect"), artinya **Docker daemon aktif**. |
| 4 | `docker build -t iom-app:demo .` | Membangun **image** dari `Dockerfile` di direktori saat ini (`.` = build context). Docker membaca instruksi di Dockerfile baris per baris, menjalankannya, lalu menyimpan hasilnya sebagai image baru dengan nama/tag `iom-app:demo`. Ini proses yang menghasilkan "cetakan" aplikasi siap jalan — belum menjalankan container apa pun. |
| 5 | `docker run -d --name iom_demo -p 8091:8080 --network ... -v "${PWD}:/var/www/html" -e DB_HOST=db ... iom-app:demo` | Menjalankan **container baru** dari image `iom-app:demo`. Rincian tiap flag: `-d` = jalan di background (detached); `--name iom_demo` = nama container biar gampang dirujuk; `-p 8091:8080` = memetakan port 8080 di dalam container ke port 8091 di komputer host, supaya bisa diakses lewat browser; `--network ...` = menyambungkan container ini ke jaringan Docker Compose yang sama supaya bisa mengakses service `db`/`redis`/`memcached`; `-v "${PWD}:/var/www/html"` = **bind mount**, memetakan folder project di host ke dalam container, supaya perubahan kode langsung terlihat tanpa build ulang; `-e DB_HOST=...` dst = mengatur **environment variable** (host, port, kredensial) yang dibaca aplikasi saat runtime, bukan di-hardcode. |
| 6 | `docker ps --filter name=iom_demo` | Sama seperti `docker ps`, tapi difilter hanya menampilkan container bernama `iom_demo`. Dipakai untuk **membuktikan container berstatus `Up`** (berjalan, tidak crash). |
| 7 | `docker logs iom_demo \| Select-String -Pattern "fatal" ...` | Menampilkan **log output** dari dalam container, lalu menyaring baris yang mengandung kata "fatal". Kalau hasilnya kosong, artinya aplikasi **jalan tanpa fatal error** sejak start. |
| 8 | *(buka browser)* `http://localhost:8091/login` | Mengakses aplikasi lewat port yang sudah dipetakan (`8091` → `8080` di container). Kalau halaman login muncul dengan HTTP 200, berarti web server di dalam container benar-benar merespons request dari luar. |
| 9 | `docker rm -f iom_demo` | Menghapus container `iom_demo` secara paksa (`-f` = force, walau masih berjalan). Dipakai untuk **bersih-bersih** setelah demo manual (Poin 4) selesai, sebelum lanjut ke demo Docker Compose (Poin 5). |
| 10 | `docker compose up --build -d` | Membaca `compose.yaml`, **membangun ulang image** (`--build`) dari `Dockerfile` (`build: .`), lalu menjalankan **semua service sekaligus** (`app`, `cron`, `db`, `redis`, `memcached`) sebagai satu stack yang saling terhubung. `-d` = jalan di background. Ini satu perintah yang menghidupkan seluruh sistem dari kondisi bersih. |
| 11 | `docker compose ps` | Menampilkan status semua service dalam stack Compose: nama, service, dan status (`Up`, `Up (healthy)`, dll). Dipakai untuk **membuktikan seluruh sistem (app + db + redis + memcached + cron) benar-benar jalan**, bukan cuma sebagian. |
| 12 | *(buka browser)* `http://localhost:8090` | Mengakses aplikasi yang dijalankan lewat Docker Compose (port berbeda dari demo manual). Kalau redirect ke `/login` dengan HTTP 200, berarti stack lengkap (app + dependensinya) berfungsi normal. |
| 13 | `docker exec iom_app php -r '$p=new PDO(\"mysql:host=db;...\"); echo \"tables=\"...'` | Menjalankan **perintah PHP langsung di dalam container `iom_app`** yang sedang berjalan (`docker exec`). Skrip ini membuka koneksi **PDO ke MySQL** memakai hostname `db` (nama service, bukan IP — bukti Docker DNS internal bekerja) dan environment variable yang sama dipakai aplikasi. Kalau keluar `tables=15`, berarti **aplikasi benar-benar bisa menjangkau database** sebagai service terpisah, dan schema+seed sudah ter-load. Di PowerShell, tanda kutip ganda di dalam command **wajib** di-escape (`\"`), kalau tidak PowerShell menghilangkannya saat diteruskan ke `docker.exe` dan memicu `Parse error`. |
| 14 | `docker volume ls` | Menampilkan daftar **named volume** yang dikelola Docker, termasuk `iom_db_data` dan `iom_redis_data`. Membuktikan data MySQL/Redis disimpan di volume **persisten** — tidak hilang walau container dihapus dan dibuat ulang (beda dengan data di dalam container biasa yang hilang saat container dihapus). |
| 15 | `docker compose down` | Mematikan dan **menghapus semua container** dalam stack Compose (tapi **volume tetap ada**, kecuali ditambah `-v`). Dipakai untuk membersihkan environment setelah sesi demo selesai, tanpa kehilangan data yang sudah di-generate. |

---

# BAGIAN E — `compose.yaml`: Penjelasan Rinci per Service

Bagian D sudah membahas `Dockerfile` baris per baris. Bagian ini melengkapi dengan isi
[`compose.yaml`](../compose.yaml) — file yang mengorkestrasi 5 container (`app`, `cron`, `db`, `redis`,
`memcached`) agar bisa dijalankan sekaligus dengan satu perintah `docker compose up`, sesuai
ketentuan mandatory di `CLAUDE.md` §3: "Docker + Docker Compose, runnable from clean environment".

## Ringkasan 5 Service

| Service | Image / Build | Peran Utama |
|---|---|---|
| `app` | `build: .` (Dockerfile di root) | Aplikasi PHP utama (web server bawaan PHP, port 8080) |
| `cron` | `build: .` (image sama dengan `app`) | Menjalankan job terjadwal, mis. notifikasi low-stock |
| `db` | `mysql:8.0` (image resmi) | Database utama — MySQL 8, sumber kebenaran data |
| `redis` | `redis:7-alpine` (image resmi) | Cache & session store |
| `memcached` | `memcached:1.6-alpine` (image resmi) | Cache tambahan/general purpose |

Kelimanya digabung dalam satu custom bridge network, `iom_net`, sehingga antar service bisa saling
memanggil memakai **nama service sebagai hostname** (mis. `app` konek ke `db:3306`, bukan ke IP atau
`localhost`) — ini kemampuan DNS internal bawaan Docker Compose.

## Service `app` ([`compose.yaml:1-51`](../compose.yaml#L1-L51))

| Field | Nilai / Konfigurasi | Penjelasan |
|---|---|---|
| Build | `build: .` | Dibangun dari `Dockerfile` di root project, bukan pull image jadi |
| Container name | `iom_app` | Nama container tetap, memudahkan `docker exec`/`docker logs` |
| Port | `${APP_PORT:-8090}:8080` | Host:container. Default host `8090`, bisa dioverride via `.env` |
| Volume | `.:/var/www/html` | Bind mount source code — perubahan kode langsung terlihat tanpa rebuild |
| Depends on | `db`, `redis`, `memcached` — semua `condition: service_healthy` | `app` baru **start** setelah ketiga dependency itu benar-benar **sehat**, bukan sekadar "container jalan" — mencegah race condition koneksi gagal di awal |
| Healthcheck | `GET /login` setiap 15s, timeout 5s, 5x retry, `start_period` 60s | Docker mendeteksi otomatis apakah container `app` "siap melayani", dipakai juga oleh `depends_on` service lain yang bergantung padanya |
| Extra hosts | `host.docker.internal:host-gateway` | Jembatan agar container bisa akses service yang jalan di **host** (dipakai untuk MinIO, lihat `MINIO_ENDPOINT`) |
| Environment kunci | `DB_*`, `REDIS_*`, `MEMCACHED_*`, `MINIO_*`, `SESSION_*`, `ID_OBFUSCATION_KEY`, dll | Semua konfigurasi aplikasi di-inject lewat environment variable — tidak ada yang di-hardcode (lihat tabel Environment & Secret di bawah) |
| Network | `iom_net` | Network bridge bersama seluruh service lain |

## Service `cron` ([`compose.yaml:53-71`](../compose.yaml#L53-L71))

| Field | Nilai / Konfigurasi | Penjelasan |
|---|---|---|
| Build | `build: .` | Image **persis sama** dengan `app` (satu Dockerfile, dua service) |
| Container name | `iom_cron` | — |
| Command override | `["cron", "-f"]` | Mengganti `CMD` default Dockerfile (`php -S ...`) dengan daemon `cron` di foreground (`-f` wajib di container — tanpa itu `cron` jalan di background lalu container langsung exit) |
| Restart policy | `unless-stopped` | Otomatis restart kalau crash, kecuali memang dihentikan manual |
| Depends on | `db` — `condition: service_healthy` | Job cron baru jalan setelah DB siap (job low-stock butuh query ke DB) |
| Volume | `.:/var/www/html` | Sama dengan `app`, perlu source code & file jadwal cron yang sama |
| Environment | Subset dari `app`: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Hanya yang dibutuhkan untuk query DB — service ini tidak melayani HTTP jadi tidak perlu `SESSION_*`/`MINIO_*` |
| Healthcheck | Tidak ada | `cron` tidak melayani HTTP sehingga tidak ada endpoint untuk dicek; ini desain yang disengaja, bukan kekurangan |
| Network | `iom_net` | — |

## Service `db` ([`compose.yaml:73-94`](../compose.yaml#L73-L94))

| Field | Nilai / Konfigurasi | Penjelasan |
|---|---|---|
| Image | `mysql:8.0` | Image resmi MySQL 8 — sesuai requirement `CLAUDE.md` §3 ("MySQL 8"), bukan custom build |
| Container name | `iom_db` | — |
| Restart policy | `unless-stopped` | — |
| Port | `3307:3306` | Host port **sengaja beda** (`3307`) dari port default MySQL (`3306`) supaya tidak bentrok dengan MySQL lokal yang mungkin sudah jalan di mesin developer |
| Environment | `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` — semua wajib dari `.env` | Kredensial database; tidak ada default untuk password (lihat tabel Secret) |
| Volume data | `iom_db_data:/var/lib/mysql` | **Named volume** — data MySQL persisten, tidak hilang saat container dihapus/dibuat ulang (beda dari bind mount) |
| Auto-init | `database/schema.sql` → `01-schema.sql`, `database/seed.sql` → `02-seed.sql`, di-mount ke `docker-entrypoint-initdb.d/` (read-only) | Image resmi MySQL otomatis menjalankan file `.sql` di folder ini **hanya saat volume data masih kosong** (pertama kali container dibuat) — urutan nama file (`01-`, `02-`) menentukan urutan eksekusi |
| Healthcheck | `mysqladmin ping` setiap 5s, retry 20x | Dipakai `app` dan `cron` lewat `depends_on: condition: service_healthy` agar tidak connect sebelum MySQL benar-benar siap menerima koneksi |
| Network | `iom_net` | — |

## Service `redis` & `memcached` ([`compose.yaml:96-123`](../compose.yaml#L96-L123))

| Aspek | `redis` | `memcached` |
|---|---|---|
| Image | `redis:7-alpine` | `memcached:1.6-alpine` |
| Container name | `iom_redis` | `iom_memcached` |
| Port | Tidak di-expose ke host (hanya internal `iom_net`) | `${MEMCACHED_HOST_PORT:-11211}:11211` (diexpose ke host, mis. untuk debugging) |
| Command | Default image | `memcached -m 64` — batasi alokasi memori ke 64 MB |
| Volume | `iom_redis_data:/data` — named volume, persisten | Tidak ada — murni in-memory, data hilang saat container restart (memang desain memcached) |
| Healthcheck | `redis-cli ping` setiap 5s | `nc -z localhost 11211` setiap 10s |
| Fungsi di aplikasi | Session store + cache (dipakai lewat ekstensi PHP Redis) | Cache tambahan/general purpose (ekstensi PHP Memcached) |
| Network | `iom_net` | `iom_net` |

## Environment Variable & Secret

Ada dua pola penulisan variabel di `compose.yaml`:

- **`${VAR:-default}`** — opsional, punya nilai default kalau tidak diisi di `.env`.
- **`${VAR:?pesan error}`** — **wajib** diisi di `.env`; kalau kosong, `docker compose up` langsung
  gagal dengan pesan error yang jelas ("`DB_PASSWORD must be set in .env`"), bukan jalan dengan
  password kosong/default yang tidak aman.

| Variable | Wajib di `.env`? | Fungsi |
|---|---|---|
| `DB_PASSWORD` | **Ya** | Password user MySQL untuk `app`/`cron`; dipakai juga untuk membuat user di service `db` |
| `DB_ROOT_PASSWORD` | **Ya** | Password root MySQL; dipakai healthcheck (`mysqladmin ping -p...`) |
| `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` | **Ya** | Kredensial akses object storage MinIO (upload gambar produk, dll) |
| `ID_OBFUSCATION_KEY` | **Ya** | Key untuk meng-obfuscate ID (mis. hashid) — mencegah enumerasi ID numerik lewat URL |
| `APP_PORT`, `DB_NAME`, `DB_USER`, `APP_ENV`, `APP_DEBUG`, `DEFAULT_LOCALE`, `SESSION_NAME`, `SESSION_LIFETIME`, dll | Tidak | Sudah punya nilai default yang masuk akal lewat `${VAR:-default}`, bisa dioverride kalau perlu |
| `MINIO_PUBLIC_URL` | Tidak | Default `http://localhost:9000` — URL yang dipakai **browser** (bukan container) untuk memuat gambar |

Tidak ada satu pun secret yang di-hardcode di `compose.yaml` — selaras dengan Best Practice 5 di
Bagian B.

## Mekanisme Pendukung: Network, Volume, Healthcheck

| Mekanisme | Fungsi |
|---|---|
| Bridge network `iom_net` | Isolasi jaringan + DNS internal antar container — service saling memanggil pakai nama service, bukan IP |
| Named volume (`iom_db_data`, `iom_redis_data`) | Persistensi data — bertahan melewati `docker compose down` (hanya `down -v` yang menghapusnya) |
| `depends_on` + `condition: service_healthy` | Startup ordering — mencegah `app`/`cron` mencoba konek ke `db`/`redis`/`memcached` sebelum service itu benar-benar siap |
| `healthcheck` per service | Deteksi otomatis status "sehat" vs "sakit"; terlihat langsung di `docker compose ps` (`Up (healthy)`) |
| `extra_hosts: host.docker.internal:host-gateway` | Jembatan ke service di **luar** stack compose ini — dipakai `app` untuk akses MinIO yang jalan terpisah di host (`portfolio-minio`) |

## Kesimpulan

`compose.yaml` adalah **single source of truth** untuk mereproduksi seluruh environment project ini
(app + cron + database + dua layer cache) dengan satu perintah, dari clean environment sekalipun —
memenuhi requirement mandatory `CLAUDE.md` §3. Satu hal yang **sengaja tidak** didefinisikan di sini:
MinIO — komentar di `compose.yaml:125-126` menjelaskan bahwa MinIO reuse container `portfolio-minio`
yang sudah berjalan terpisah di monorepo (`docker compose -f ../deploy/minio-docker-compose.yml up -d`).

---

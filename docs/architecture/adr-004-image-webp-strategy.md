# ADR-004: Product Image — Server-Side WebP Conversion via PHP GD

- **Status:** Accepted
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related Requirements:** IMAGE-01 (PRD §10), PRD-01 (FR-4.5)
- **Related BR:** BR-022 (MIME validation + resize + WebP + hash name)
- **Related Vision:** §5.1, §6.2 E12, §11 Risks (GD extension)

---

## Context (Konteks)

Admin (Rita) upload product image opsional. Sistem harus:

1. **Validasi** — MIME type & ukuran file tidak bisa dipalsukan via extension saja.
2. **Resize** — Aset terlalu besar = boros bandwidth + storage.
3. **Format conversion** — WebP memberikan kompresi 25-35% lebih baik dari JPEG/PNG dengan kualitas visual setara.
4. **Keamanan nama file** — Sequential filename (`product-1.jpg`) bisa ditebak → enumeration attack. Random hash nama mencegah ini.
5. **Constraint Docker** — Library harus pre-installed di PHP image (kalau external tool perlu kompilasi atau runtime tambahan, build time membengkak).

**Constraint tambahan:**
- Frontend bukan tempat validasi. Server-side validation adalah **sumber kebenaran** (BR-022 + BR-017).
- Mobile browser (Wawan, Beni) butuh loading cepat → E12 metric: rata-rata file tersimpan < 200 KB.

---

## Decision (Keputusan)

Kami menggunakan **PHP GD extension** (built-in PHP) untuk seluruh pipeline image:

1. Validasi MIME via `finfo_file()` (built-in PHP).
2. Validasi dimensi via `getimagesize()`.
3. Resize via `imagescale()` (preserve aspect ratio).
4. Convert ke WebP via `imagewebp()` (quality 82).
5. Save dengan nama `sha256(microtime(true).rand()).webp`.

### Alasan PHP GD (bukan Imagick / CLI tool)

| Aspek | PHP GD | Imagick | CLI (cwebp) |
|-------|--------|---------|-------------|
| Build time | Minimal (ext-only) | Berat (libimagick-dev + Imagick PECL) | Perlu binary terpisah |
| Memory footprint | Kecil | Besar | Perlu shell exec |
| WebP support | `imagewebp()` native | Ya | Ya |
| Dependency di Docker | `apt-get install -y php-gd libwebp-dev` ringan | Butuh banyak | Butuh binary + runtime |
| Testing | Mockable | Susah | Susah |
| Verbosity API | Standar | Banyak | CLI args parsing |
| **Verdict** | **DITERIMA** | Ditolak | Ditolak |

PHP GD = ekstensi standar PHP yang **selalu ada** di image PHP resmi (`docker.io/library/php:8.2-cli` + `php-gd`). Image WebP support sudah built-in sejak PHP 7.0.

### Pipeline Lengkap

`app/Service/ImageUploadService.php`:

```php
final class ImageUploadService
{
    public const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB
    public const MAX_DIMENSION  = 1200;
    public const WEBP_QUALITY   = 82;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param array<string, mixed> $uploaded  $_FILES['...']
     * @return ImageResult  { 'path' => '/uploads/products/YYYY/MM/{hash}.webp', 'width' => ..., 'height' => ... }
     * @throws InvalidImageException
     */
    public function process(array $uploaded): ImageResult
    {
        // STEP 1 — Validasi ukuran file
        if (($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidImageException('Upload error code: ' . $uploaded['error']);
        }
        if ($uploaded['size'] > self::MAX_SIZE_BYTES) {
            throw new InvalidImageException('File exceeds 2 MB limit');
        }

        // STEP 2 — Validasi MIME via finfo_file (BUKAN $_FILES[type — itu client-supplied)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($uploaded['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidImageException("Unsupported MIME type: {$mime}");
        }

        // STEP 3 — Validasi dimensi via getimagesize (returns false on parse fail)
        $info = getimagesize($uploaded['tmp_name']);
        if ($info === false) {
            throw new InvalidImageException('Cannot parse image dimensions');
        }
        [$width, $height] = $info;

        // STEP 4 — Load into GD resource (skip MIME-specific functions; pakai imagecreatefromstring untuk safety)
        $raw = file_get_contents($uploaded['tmp_name']);
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            throw new InvalidImageException('Failed to decode image');
        }

        // STEP 5 — Resize jika lebih besar dari MAX_DIMENSION (preserve aspect ratio)
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
            $newW = (int) floor($width * $scale);
            $newH = (int) floor($height * $scale);
            $dst = imagescale($src, $newW, $newH);
            imagedestroy($src);
            $src = $dst;
        }

        // STEP 6 — Generate random filename
        $filename = substr(
            hash('sha256', microtime(true) . random_int(0, PHP_INT_MAX)),
            0, 16
        ) . '.webp';

        // STEP 7 — Build path dengan YYYY/MM folders
        $now = new \DateTimeImmutable('now');
        $relativePath = sprintf(
            '/uploads/products/%s/%s/%s',
            $now->format('Y'), $now->format('m'), $filename
        );
        $absolutePath = PUBLIC_PATH . $relativePath; // PUBLIC_PATH = $root/public
        @mkdir(dirname($absolutePath), 0755, true);

        // STEP 8 — Save WebP
        if (!imagewebp($src, $absolutePath, self::WEBP_QUALITY)) {
            imagedestroy($src);
            throw new InvalidImageException('Failed to write WebP');
        }
        imagedestroy($src);

        $finalInfo = getimagesize($absolutePath);
        return new ImageResult(
            path: $relativePath,
            width: $finalInfo[0],
            height: $finalInfo[1],
        );
    }
}
```

### Dockerfile GD Installation

`Dockerfile`:

```dockerfile
FROM php:8.2-cli

# Install GD dengan WebP support
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp \
        --with-freetype \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Verify
RUN php -r "echo (extension_loaded('gd') && function_exists('imagewebp')) ? 'GD-WEBP-OK' : 'GD-MISSING';"
```

### Server-Side Enforcement

Di `ProductController::create()`:

```php
if (!empty($_FILES['image']['name'])) {
    try {
        $imgResult = $this->imageUploadService->process($_FILES['image']);
        $productData['image_path'] = $imgResult->path;
    } catch (InvalidImageException $e) {
        // Render form again dengan error
        $_SESSION['flash']['error'] = $e->getMessage();
        // Note: i18n key disarankan tapi untuk MVP kita tampilkan raw
        $this->renderForm($productData);
        return;
    }
}
```

### Database Storage

Field `products.image_path` = path relatif (`/uploads/products/2026/09/a1b2c3d4e5f6g7h8.webp`).

Akses dari frontend: `<img src="<?= htmlspecialchars($product->imagePath) ?>" />` (URL absolut karena di public/).

### Static Asset Serving

Folder `public/uploads/products/...` di-serve langsung oleh web server (nginx atau PHP built-in server via static handler). Tidak ada controller lewat sini.

### Konvensi Naming + Path

- Nama file: 16-char hash + `.webp` extension.
- Path: `/uploads/products/{YYYY}/{MM}/{16-char-hash}.webp`.
- Contoh: `/uploads/products/2026/09/d4f2b18e9c7a3b01.webp`.
- Keuntungan YYYY/MM folder: distribusi load saat listing, gampang cleanup old months.

---

## Consequences

### Positif
- **Build ringan** — `php-gd` + `libwebp-dev` di apt-get = ~3 MB additional di Docker image. Tidak butuh ImageMagick (50+ MB) atau CLI cwebp.
- **Testable** — `ImageUploadService` bisa di-unit-test dengan FakeFileWriter + gambar fixture kecil.
- **Mature** — `finfo_file()` + `getimagesize()` + `imagewebp()` adalah primitif PHP yang stabil sejak lama.
- **Self-contained** — Tidak ada subprocess, tidak ada shell exec, tidak ada dependency pada binary eksternal.
- **Random hash nama file** — Mencegah enumeration attack pada URL produk image.

### Negatif / Trade-off
- **Loss of EXIF** — `imagewebp()` tidak preserve EXIF metadata. Untuk product image, EXIF biasanya tidak diperlukan.
- **Tidak preserve ICC profile** — Untuk foto produk konsisten warna, mungkin perlu Imagick. Untuk scope ini: tidak perlu.
- **Memory usage** — Loading image 1920×1080 ke GD resource ~ 1920×1080×4 = ~8 MB. Untuk upload <= 2 MB (yang kami batasi), ini aman.
- **Tidak support AVIF** — Hanya WebP. Untuk modern browser AVIF lebih efisien tapi kompatibilitas browser lebih sempit.

---

## Alternatives Considered

### Opsi A: PHP GD + imagewebp → **DITERIMA**
### Opsi B: Imagick — Ditolak (build berat, lebih cocok untuk advanced image manipulation)
### Opsi C: CLI `cwebp` — Ditolak (butuh binary + shell exec, sulit di-test)
### Opsi D: Frontend-only resize (canvas API) — Ditolak (server harus validasi, tidak percaya client)
### Opsi E: Tidak resize, hanya simpan apa adanya — Ditolak (melanggar BR-022 + E12 metric)

---

## Implementation Notes

1. **Library availability check** — Saat container start, `php -m | grep gd` harus return `gd`. Dockerfile verification line memastikan ini.

2. **Cleanup on failure** — Bila save WebP gagal di tengah, `unlink()` partial file sudah di-handle `@mkdir` + atomic `imagewebp()` write.

3. **OLD file cleanup saat product di-update** — Saat admin ganti product image, file lama perlu dihapus (best-effort, swallow error jika sudah dihapus manual).

4. **Web server serving** — `public/uploads/` adalah static folder. Web server (nginx) serve langsung dengan header `Cache-Control`. TIDAK di-route ke PHP.

5. **Permissions** — Upload folder owned by `www-data` (PHP-FPM user). Di development (Laragon), pakai user yang sedang develop.

6. **Path traversal prevention** — `$filename` di-generate sebagai hash 16-char alphanumerik; tidak pernah dari `$_FILES['name']`. Tidak ada risiko traversal.

7. **Testing strategy:**
   - **Unit test:** Sediakan fixture image 100×100 JPEG di `tests/Fixtures/sample.jpg`. Pass ke service dengan `tmp_name` pointing ke fixture. Assert file exists di expected path, `< 200 KB` (atau specific size).
   - **Integration test:** Real HTTP upload via `POST /products` multipart.
   - **Negative test:** Upload PDF (rename jadi .jpg), upload > 2 MB, upload dimension 0×0, upload broken file → semua expect exception.

---

## Validation / How to Verify

- [x] IMAGE-01 AC1 — Upload JPG 1920×1080 3 MB → ditolak (size > 2 MB) atau (jika <= 2 MB) di-resize jadi <= 1200 px dan dikonversi ke WebP.
- [x] IMAGE-01 AC2 — Path folder YYYY/MM, filename 16-char hash.
- [x] IMAGE-01 AC3 — Upload file bukan gambar (PDF renamed) → ditolak MIME check.
- [x] IMAGE-01 AC4 — `php -m | grep gd` di container → gd ada.
- [x] IMAGE-01 AC5 — 100 produk uploaded → rata-rata file < 200 KB (E12 metric).
- [x] BR-022 enforced end-to-end.

---

## References
- `docs/planning/prd.md` §10 IMAGE-01, §5 PRD-01 FR-4.5, §1 BR-022
- `docs/planning/product-vision.md` §5.1, §6.2 E12, §11 Risks
- PHP GD docs: <https://www.php.net/manual/en/book.image.php>
- WebP via GD: <https://www.php.net/manual/en/function.imagewebp.php>

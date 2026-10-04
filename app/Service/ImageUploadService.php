<?php

namespace App\Service;

use App\Core\MinioClient;
use App\Core\Result;
use DateTimeImmutable;
use finfo;
use GdImage;

class ImageUploadService
{
    public const MESSAGE_FAILED_FUNCTION = 'The image could not be processed. Please try a different file.';
    public const MESSAGE_INVALID_TYPE = 'Only JPEG, PNG, or WebP images are allowed.';

    public const MAX_SIZE_BYTES = 2 * 1024 * 1024;
    public const MAX_DIMENSION = 1200;
    public const WEBP_QUALITY = 82;

    private $minioClient;

    public function __construct(MinioClient $minioClient)
    {
        $this->minioClient = $minioClient;
    }

    public function process($uploaded)
    {
        $result = new Result();

        try {
            $tmpName = (string) ($uploaded['tmp_name'] ?? '');

            $uploadInfo = $this->uploadInvalidInfo($uploaded, $tmpName);
            if ($uploadInfo !== '') {
                $result->code = Result::CODE_VALIDATION;
                $result->info = $uploadInfo;
                $result->data = null;

                return $result;
            }

            [$width, $height] = getimagesize($tmpName);
            $image = $this->loadGdImage($tmpName);

            if ($image instanceof Result) {
                return $image;
            }

            if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                $image = $this->scaleDown($image, $width, $height);
            }

            if ($image instanceof Result) {
                return $image;
            }

            $finalWidth = imagesx($image);
            $finalHeight = imagesy($image);
            $objectKey = $this->buildObjectKey();
            $writeCheck = $this->writeWebp($image, $objectKey);

            if ($writeCheck instanceof Result) {
                return $writeCheck;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The image has been uploaded.';
            $result->data = $this->buildImageUploadResult($objectKey, $finalWidth, $finalHeight);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function delete($storedPath)
    {
        if ($storedPath === null || $storedPath === '') {
            return;
        }

        $key = $this->minioClient->extractObjectKey($storedPath);
        if ($key !== null) {
            $this->minioClient->deleteObject($key);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function uploadInvalidInfo($uploaded, $tmpName): string
    {
        $error = $uploaded['error'] ?? UPLOAD_ERR_NO_FILE;
        $size = (int) ($uploaded['size'] ?? 0);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        // PHP rejects an over-limit file before it reaches us (upload_max_filesize / MAX_FILE_SIZE); report that as the size error, not a generic failure
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'File size exceeds the maximum allowed.';
        }

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            return 'The image could not be uploaded. Please try again.';
        }

        if ($size <= 0 || $size > self::MAX_SIZE_BYTES) {
            return 'File size exceeds the maximum allowed.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);

        if ($mime === false
            || !in_array($mime, $allowedMimes, true)
            || getimagesize($tmpName) === false
        ) {
            return self::MESSAGE_INVALID_TYPE;
        }

        return '';
    }

    private function loadGdImage($tmpName)
    {
        $raw = file_get_contents($tmpName);
        $image = $raw === false ? false : @imagecreatefromstring($raw);

        if (!$image instanceof GdImage) {
            $result = new Result();
            $result->code = Result::CODE_VALIDATION;
            $result->info = self::MESSAGE_INVALID_TYPE;
            $result->data = null;

            return $result;
        }

        return $image;
    }

    private function scaleDown($image, $width, $height)
    {
        $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
        $newWidth = max(1, (int) floor($width * $scale));
        $newHeight = max(1, (int) floor($height * $scale));
        $resized = imagescale($image, $newWidth, $newHeight);
        imagedestroy($image);

        if (!$resized instanceof GdImage) {
            $result = new Result();
            $result->code = Result::CODE_VALIDATION;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        return $resized;
    }

    // Object key inside the MinIO bucket. Uses the shared ioms/ namespace
    // prefix to isolate IOMS objects from other apps in the portfolio-uploads bucket.
    // Pattern: ioms/products/{YYYY}/{MM}/{sha256-timestamp}.webp
    private function buildObjectKey(): string
    {
        $now = new DateTimeImmutable('now');
        $entropy = hash('sha256', microtime(true) . random_int(0, PHP_INT_MAX));
        $filename = substr($entropy, 0, 16) . '.webp';

        return sprintf(
            '%sproducts/%s/%s/%s',
            MinioClient::IOMS_PREFIX,
            $now->format('Y'),
            $now->format('m'),
            $filename
        );
    }

    // Encodes GD image to WebP in memory and uploads it to MinIO.
    // Returns null on success; returns Result on validation or transport failure.
    private function writeWebp($image, string $objectKey)
    {
        ob_start();
        $saved = imagewebp($image, null, self::WEBP_QUALITY);
        $binary = ob_get_clean();
        imagedestroy($image);

        if (!$saved || $binary === false || $binary === '') {
            $result = new Result();
            $result->code = Result::CODE_VALIDATION;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        try {
            $this->minioClient->putObject($objectKey, $binary, 'image/webp');

            return null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());

            $result = new Result();
            $result->code = Result::CODE_VALIDATION;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }
    }

    private function buildImageUploadResult(string $objectKey, int $width, int $height): ImageUploadResult
    {
        return new ImageUploadResult(
            $this->minioClient->publicUrl($objectKey),
            $width,
            $height
        );
    }
}

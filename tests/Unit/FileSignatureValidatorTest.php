<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Result;
use App\Service\FileSignatureValidator;
use PHPUnit\Framework\TestCase;

final class FileSignatureValidatorTest extends TestCase
{
    private function rules(): array
    {
        return [
            'jpg'  => ['extension' => 'jpg',  'mime_type' => 'image/jpeg', 'header_hex' => 'FFD8FF',   'footer_hex' => 'FFD9',             'read_bytes' => 3],
            'jpeg' => ['extension' => 'jpeg', 'mime_type' => 'image/jpeg', 'header_hex' => 'FFD8FF',   'footer_hex' => 'FFD9',             'read_bytes' => 3],
            'png'  => ['extension' => 'png',  'mime_type' => 'image/png',  'header_hex' => '89504E47', 'footer_hex' => '49454E44AE426082', 'read_bytes' => 8],
            'webp' => ['extension' => 'webp', 'mime_type' => 'image/webp', 'header_hex' => '52494646', 'footer_hex' => null,               'read_bytes' => 4],
        ];
    }

    // Minimal byte strings whose magic bytes match each configured signature.
    private function jpegBytes(): string
    {
        return hex2bin('FFD8FF') . 'payload' . hex2bin('FFD9');
    }

    private function pngBytes(): string
    {
        return hex2bin('89504E470D0A1A0A') . 'payload' . hex2bin('49454E44AE426082');
    }

    private function webpBytes(): string
    {
        return 'RIFF' . '____' . 'WEBPpayload';
    }

    public function testValidJpegPasses(): void
    {
        $result = FileSignatureValidator::validate('product.jpg', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testValidPngPasses(): void
    {
        $result = FileSignatureValidator::validate('product.png', $this->pngBytes(), $this->rules());

        self::assertSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testValidWebpPassesWithoutFooterCheck(): void
    {
        $result = FileSignatureValidator::validate('product.webp', $this->webpBytes(), $this->rules());

        self::assertSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testDottedButLegitimateNamePasses(): void
    {
        $result = FileSignatureValidator::validate('my.product.photo.jpg', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testDisallowedExtensionRejected(): void
    {
        $result = FileSignatureValidator::validate('document.pdf', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_VALIDATION, $result->code);
        self::assertSame(FileSignatureValidator::MESSAGE_INVALID_TYPE, $result->info);
    }

    public function testDangerousDoubleExtensionRejected(): void
    {
        // Final extension is allow-listed (jpg) and the content is a real JPEG,
        // but the inner ".php" segment must still get the upload rejected.
        $result = FileSignatureValidator::validate('evil.php.jpg', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_VALIDATION, $result->code);
        self::assertSame(FileSignatureValidator::MESSAGE_DOUBLE_EXTENSION, $result->info);
    }

    public function testHeaderMismatchRejected(): void
    {
        // A .png name carrying JPEG bytes — extension/content inconsistency.
        $result = FileSignatureValidator::validate('spoof.png', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_VALIDATION, $result->code);
        self::assertSame(FileSignatureValidator::MESSAGE_SIGNATURE_MISMATCH, $result->info);
    }

    public function testMissingFooterRejected(): void
    {
        $noFooter = hex2bin('FFD8FF') . 'payload-without-the-jpeg-end-marker';

        $result = FileSignatureValidator::validate('product.jpg', $noFooter, $this->rules());

        self::assertSame(Result::CODE_VALIDATION, $result->code);
        self::assertSame(FileSignatureValidator::MESSAGE_SUSPICIOUS_FOOTER, $result->info);
    }

    public function testEmptyRulesRejectsEverything(): void
    {
        $result = FileSignatureValidator::validate('product.jpg', $this->jpegBytes(), []);

        self::assertSame(Result::CODE_VALIDATION, $result->code);
        self::assertSame(FileSignatureValidator::MESSAGE_RULES_UNAVAILABLE, $result->info);
    }

    public function testExtensionIsCaseInsensitive(): void
    {
        $result = FileSignatureValidator::validate('PRODUCT.JPG', $this->jpegBytes(), $this->rules());

        self::assertSame(Result::CODE_SUCCESS, $result->code);
    }
}

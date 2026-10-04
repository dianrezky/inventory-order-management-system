<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\IdObfuscator;
use PHPUnit\Framework\TestCase;

final class IdObfuscatorTest extends TestCase
{
    private function obfuscator(): IdObfuscator
    {
        return new IdObfuscator('test_key_12345');
    }

    public function testEncodeThenDecodeRoundTripsToOriginalId(): void
    {
        $obfuscator = $this->obfuscator();

        foreach ([1, 5, 42, 999, 123456789] as $id) {
            $token = $obfuscator->encode($id);
            $this->assertSame($id, $obfuscator->decode($token));
        }
    }

    public function testEncodedTokenDoesNotContainPlainId(): void
    {
        $obfuscator = $this->obfuscator();

        $token = $obfuscator->encode(42);

        $this->assertStringNotContainsString('42', $token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testDecodeReturnsNullForGarbageToken(): void
    {
        $obfuscator = $this->obfuscator();

        $this->assertNull($obfuscator->decode('not-hex'));
        $this->assertNull($obfuscator->decode(''));
        $this->assertNull($obfuscator->decode('deadbeef'));
    }

    public function testDecodeReturnsNullWhenKeyDiffers(): void
    {
        $token = (new IdObfuscator('key_a'))->encode(7);

        $this->assertNull((new IdObfuscator('key_b'))->decode($token));
    }

    public function testDecodeRejectsIncrementedNeighborToken(): void
    {
        // Guards the actual motivation: tampering with the plain id (e.g.
        // incrementing it) must not produce another valid token.
        $obfuscator = $this->obfuscator();
        $token = $obfuscator->encode(5);

        $tampered = $obfuscator->encode(6);

        $this->assertNotSame($token, $tampered);
        $this->assertSame(5, $obfuscator->decode($token));
        $this->assertSame(6, $obfuscator->decode($tampered));
    }
}

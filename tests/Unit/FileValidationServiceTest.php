<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheService;
use App\Repository\Fake\FileValidationFakeRepository;
use App\Service\FileValidationService;
use PHPUnit\Framework\TestCase;

final class FileValidationServiceTest extends TestCase
{
    private function makeService(array $rules): FileValidationService
    {
        $repo = new FileValidationFakeRepository($rules);

        // Host with no listener, so Memcached is unavailable and the service
        // falls back to the repository — same pattern as PermissionServiceTest.
        $cache = new CacheService('127.0.0.1', 1);

        return new FileValidationService($repo, $cache);
    }

    public function testGetRulesReturnsRepositoryData(): void
    {
        $rules = [
            'jpg' => ['extension' => 'jpg', 'mime_type' => 'image/jpeg', 'header_hex' => 'FFD8FF', 'footer_hex' => 'FFD9', 'read_bytes' => 3],
        ];

        $service = $this->makeService($rules);

        self::assertSame($rules, $service->getRules());
    }

    public function testGetRulesReturnsEmptyArrayWhenNoRules(): void
    {
        $service = $this->makeService([]);

        self::assertSame([], $service->getRules());
    }
}

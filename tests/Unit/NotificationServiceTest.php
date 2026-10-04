<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Result;
use App\Entity\Notification;
use App\Repository\Fake\NotificationFakeRepository;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    private function makeService(array $notifications = []): NotificationService
    {
        return new NotificationService(new NotificationFakeRepository($notifications));
    }

    public function testNotifyLowStockCreatesNotification(): void
    {
        $service = $this->makeService();

        $result = $service->notifyLowStock(10, 1, 'Widget at Jakarta Warehouse is below reorder point.');

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertNotNull($result->data);

        $unread = $service->getUnreadForDashboard();
        self::assertCount(1, $unread);
        self::assertSame('Widget at Jakarta Warehouse is below reorder point.', $unread[0]->message);
        self::assertFalse($unread[0]->isRead());
    }

    public function testNotifyLowStockDeduplicatesUnreadNotification(): void
    {
        $service = $this->makeService();

        $service->notifyLowStock(10, 1, 'First message');
        $second = $service->notifyLowStock(10, 1, 'Second message — should be skipped');

        self::assertSame(Result::CODE_SUCCESS, $second->code);
        self::assertNull($second->data);

        $unread = $service->getUnreadForDashboard();
        self::assertCount(1, $unread);
        self::assertSame('First message', $unread[0]->message);
    }

    public function testNotifyLowStockAllowsNewNotificationAfterPreviousOneIsRead(): void
    {
        $service = $this->makeService();

        $service->notifyLowStock(10, 1, 'First message');
        $service->markAllRead();
        $service->notifyLowStock(10, 1, 'Second message — new unread cycle');

        $unread = $service->getUnreadForDashboard();
        self::assertCount(1, $unread);
        self::assertSame('Second message — new unread cycle', $unread[0]->message);
    }

    public function testDifferentProductWarehousePairsDoNotDeduplicateAgainstEachOther(): void
    {
        $service = $this->makeService();

        $service->notifyLowStock(10, 1, 'Product 10 at warehouse 1');
        $service->notifyLowStock(11, 1, 'Product 11 at warehouse 1');
        $service->notifyLowStock(10, 2, 'Product 10 at warehouse 2');

        self::assertCount(3, $service->getUnreadForDashboard());
    }

    public function testCountUnreadMatchesGetUnreadForDashboard(): void
    {
        $service = $this->makeService();

        $service->notifyLowStock(10, 1, 'A');
        $service->notifyLowStock(11, 1, 'B');

        self::assertSame(2, $service->countUnread());
    }

    public function testMarkAllReadClearsUnreadCount(): void
    {
        $service = $this->makeService();

        $service->notifyLowStock(10, 1, 'A');
        $service->notifyLowStock(11, 1, 'B');
        self::assertSame(2, $service->countUnread());

        $result = $service->markAllRead();

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame(0, $service->countUnread());
        self::assertCount(0, $service->getUnreadForDashboard());
    }

    public function testGetUnreadForDashboardRespectsLimit(): void
    {
        $service = $this->makeService();

        for ($i = 1; $i <= 5; $i++) {
            $service->notifyLowStock($i, 1, "Product $i low");
        }

        self::assertCount(3, $service->getUnreadForDashboard(3));
        self::assertCount(5, $service->getUnreadForDashboard(10));
    }

    public function testTypeConstantIsLowStock(): void
    {
        self::assertSame('low_stock', Notification::TYPE_LOW_STOCK);
    }
}

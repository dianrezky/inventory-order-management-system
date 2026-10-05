<?php

namespace App\Core;

use App\Repository\Interface\CategoryRepositoryInterface;
use App\Repository\Interface\CustomerRepositoryInterface;
use App\Repository\Interface\EventLogRepositoryInterface;
use App\Repository\Interface\FileValidationRepositoryInterface;
use App\Repository\Interface\NotificationRepositoryInterface;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\ProductStockRepositoryInterface;
use App\Repository\Interface\PermissionRepositoryInterface;
use App\Repository\Interface\PurchaseOrderItemRepositoryInterface;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;
use App\Repository\Interface\ReportRepositoryInterface;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;
use App\Repository\Interface\SalesOrderRepositoryInterface;
use App\Repository\Interface\StockLedgerRepositoryInterface;
use App\Repository\Interface\SupplierRepositoryInterface;
use App\Repository\Interface\UserRepositoryInterface;
use App\Repository\Interface\WarehouseRepositoryInterface;
use App\Repository\MySQL\CategoryMySQLRepository;
use App\Repository\MySQL\CustomerMySQLRepository;
use App\Repository\MySQL\EventLogMySQLRepository;
use App\Repository\MySQL\FileValidationMySQLRepository;
use App\Repository\MySQL\NotificationMySQLRepository;
use App\Repository\MySQL\PermissionMySQLRepository;
use App\Repository\MySQL\ProductMySQLRepository;
use App\Repository\MySQL\ProductStockMySQLRepository;
use App\Repository\MySQL\PurchaseOrderItemMySQLRepository;
use App\Repository\MySQL\PurchaseOrderMySQLRepository;
use App\Repository\MySQL\QueryBuilder;
use App\Repository\MySQL\ReportMySQLRepository;
use App\Repository\MySQL\SalesOrderItemMySQLRepository;
use App\Repository\MySQL\SalesOrderMySQLRepository;
use App\Repository\MySQL\StockLedgerMySQLRepository;
use App\Repository\MySQL\SupplierMySQLRepository;
use App\Repository\MySQL\UserMySQLRepository;
use App\Repository\MySQL\WarehouseMySQLRepository;
use App\Service\AuthService;
use App\Service\CategoryService;
use App\Service\StockLedgerService;
use App\Service\CustomerService;
use App\Service\CsvExportService;
use App\Service\DashboardService;
use App\Service\EventLogService;
use App\Service\FileValidationService;
use App\Service\GoodsReceiptService;
use App\Service\GoodsIssueService;
use App\Service\ImageUploadService;
use App\Service\LowStockService;
use App\Service\NotificationService;
use App\Service\PermissionService;
use App\Service\ProductService;
use App\Service\ProductStockService;
use App\Service\PurchaseOrderService;
use App\Service\ReportService;
use App\Service\SalesDashboardService;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use App\Service\SupplierService;
use App\Service\UserService;
use App\Service\WarehouseService;

// Manual array-based composition root; no DI framework (CLAUDE.md / ADR-001) and every get*() memoizes one instance per request.
class Container // NOSONAR
{
    private $config;
    private $instances = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function config($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function getDatabase()
    {
        return $this->instances[Database::class] ??= new Database(
            (string) $this->config['db']['host'],
            (int) $this->config['db']['port'],
            (string) $this->config['db']['name'],
            (string) $this->config['db']['user'],
            (string) $this->config['db']['password'],
        );
    }

    public function getQueryBuilder()
    {
        return $this->instances[QueryBuilder::class] ??= new QueryBuilder(
            $this->getDatabase(),
        );
    }

    public function getSessionManager()
    {
        return $this->instances[SessionManager::class] ??= new SessionManager(
            (string) $this->config['session']['name'],
            (int) $this->config['session']['lifetime'],
            (string) $this->config['redis']['host'],
            (int) $this->config['redis']['port'],
        );
    }

    public function getCacheService()
    {
        return $this->instances[CacheService::class] ??= new CacheService(
            (string) $this->config['memcached']['host'],
            (int) $this->config['memcached']['port'],
        );
    }

    // Redis-backed cache, used for role permissions (separate logical DB from sessions).
    public function getRedisCacheService()
    {
        return $this->instances[RedisCacheService::class] ??= new RedisCacheService(
            (string) $this->config['redis']['host'],
            (int) $this->config['redis']['port'],
            (int) $this->config['redis']['cache_database'],
        );
    }

    public function getClock()
    {
        return $this->instances[ClockInterface::class] ??= new SystemClock();
    }

    public function getIdObfuscator()
    {
        return $this->instances[IdObfuscator::class] ??= new IdObfuscator(
            (string) $this->config['id_obfuscation']['key'],
        );
    }

    public function getUserRepository()
    {
        return $this->instances[UserRepositoryInterface::class] ??= new UserMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getAuthService()
    {
        return $this->instances[AuthService::class] ??= new AuthService(
            $this->getUserRepository(),
            $this->getSessionManager(),
            $this->getEventLogService(),
            $this->getClock(),
        );
    }

    public function getUserService()
    {
        return $this->instances[UserService::class] ??= new UserService(
            $this->getUserRepository(),
            $this->getEventLogService(),
        );
    }

    public function getCategoryRepository()
    {
        return $this->instances[CategoryRepositoryInterface::class] ??= new CategoryMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getCategoryService()
    {
        return $this->instances[CategoryService::class] ??= new CategoryService(
            $this->getCategoryRepository(),
            $this->getEventLogService(),
        );
    }

    public function getProductRepository()
    {
        return $this->instances[ProductRepositoryInterface::class] ??= new ProductMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getProductService()
    {
        return $this->instances[ProductService::class] ??= new ProductService(
            $this->getProductRepository(),
            $this->getCategoryRepository(),
            $this->getProductStockRepository(),
            $this->getCacheService(),
            $this->getEventLogService(),
        );
    }

    public function getWarehouseRepository()
    {
        return $this->instances[WarehouseRepositoryInterface::class] ??= new WarehouseMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getWarehouseService()
    {
        return $this->instances[WarehouseService::class] ??= new WarehouseService(
            $this->getWarehouseRepository(),
            $this->getEventLogService(),
        );
    }

    public function getProductStockService()
    {
        return $this->instances[ProductStockService::class] ??= new ProductStockService(
            $this->getProductStockRepository(),
        );
    }

    public function getPermissionRepository()
    {
        return $this->instances[PermissionRepositoryInterface::class] ??= new PermissionMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getReportRepository()
    {
        return $this->instances[ReportRepositoryInterface::class] ??= new ReportMySQLRepository(
            $this->getDatabase(),
            $this->getProductStockRepository(),
        );
    }

    public function getReportService()
    {
        return $this->instances[ReportService::class] ??= new ReportService(
            $this->getReportRepository(),
            $this->getClock(),
        );
    }

    public function getNotificationRepository()
    {
        return $this->instances[NotificationRepositoryInterface::class] ??= new NotificationMySQLRepository(
            $this->getDatabase(),
            $this->getQueryBuilder(),
        );
    }

    public function getEventLogRepository()
    {
        return $this->instances[EventLogRepositoryInterface::class] ??= new EventLogMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getEventLogService()
    {
        return $this->instances[EventLogService::class] ??= new EventLogService(
            $this->getEventLogRepository(),
        );
    }

    public function getNotificationService()
    {
        return $this->instances[NotificationService::class] ??= new NotificationService(
            $this->getNotificationRepository(),
        );
    }

    public function getPermissionService()
    {
        return $this->instances[PermissionService::class] ??= new PermissionService(
            $this->getPermissionRepository(),
            $this->getRedisCacheService(),
        );
    }

    public function getSupplierRepository()
    {
        return $this->instances[SupplierRepositoryInterface::class] ??= new SupplierMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getSupplierService()
    {
        return $this->instances[SupplierService::class] ??= new SupplierService(
            $this->getSupplierRepository(),
            $this->getEventLogService(),
        );
    }

    public function getCustomerRepository()
    {
        return $this->instances[CustomerRepositoryInterface::class] ??= new CustomerMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getCustomerService()
    {
        return $this->instances[CustomerService::class] ??= new CustomerService(
            $this->getCustomerRepository(),
            $this->getEventLogService(),
        );
    }

    public function getMinioClient()
    {
        return $this->instances[MinioClient::class] ??= new MinioClient(
            (string) $this->config['minio']['endpoint'],
            (string) $this->config['minio']['region'],
            (string) $this->config['minio']['access_key'],
            (string) $this->config['minio']['secret_key'],
            (string) $this->config['minio']['bucket'],
            (string) $this->config['minio']['public_base_url'],
        );
    }

    public function getFileValidationRepository()
    {
        return $this->instances[FileValidationRepositoryInterface::class] ??= new FileValidationMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getFileValidationService()
    {
        return $this->instances[FileValidationService::class] ??= new FileValidationService(
            $this->getFileValidationRepository(),
            $this->getCacheService(),
        );
    }

    public function getImageUploadService()
    {
        return $this->instances[ImageUploadService::class] ??= new ImageUploadService(
            $this->getMinioClient(),
            $this->getFileValidationService(),
        );
    }

    public function getPurchaseOrderRepository()
    {
        return $this->instances[PurchaseOrderRepositoryInterface::class] ??= new PurchaseOrderMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getPurchaseOrderItemRepository()
    {
        return $this->instances[PurchaseOrderItemRepositoryInterface::class] ??= new PurchaseOrderItemMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getProductStockRepository()
    {
        return $this->instances[ProductStockRepositoryInterface::class] ??= new ProductStockMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getStockLedgerRepository()
    {
        return $this->instances[StockLedgerRepositoryInterface::class] ??= new StockLedgerMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getStockLedgerService()
    {
        return $this->instances[StockLedgerService::class] ??= new StockLedgerService(
            $this->getStockLedgerRepository(),
            $this->getProductStockRepository(),
            $this->getDatabase(),
        );
    }

    public function getPurchaseOrderService()
    {
        return $this->instances[PurchaseOrderService::class] ??= new PurchaseOrderService(
            $this->getPurchaseOrderRepository(),
            $this->getPurchaseOrderItemRepository(),
            $this->getSupplierRepository(),
            $this->getWarehouseRepository(),
            $this->getProductRepository(),
            $this->getEventLogService(),
            $this->getDatabase(),
        );
    }

    public function getGoodsReceiptService()
    {
        return $this->instances[GoodsReceiptService::class] ??= new GoodsReceiptService(
            $this->getDatabase(),
            $this->getPurchaseOrderRepository(),
            $this->getPurchaseOrderItemRepository(),
            $this->getProductStockRepository(),
            $this->getStockLedgerRepository(),
            $this->getPurchaseOrderService(),
            $this->getEventLogService(),
        );
    }

    public function getSalesOrderRepository()
    {
        return $this->instances[SalesOrderRepositoryInterface::class] ??= new SalesOrderMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getSalesOrderItemRepository()
    {
        return $this->instances[SalesOrderItemRepositoryInterface::class] ??= new SalesOrderItemMySQLRepository(
            $this->getQueryBuilder(),
        );
    }

    public function getSalesOrderPolicy()
    {
        return $this->instances[SalesOrderPolicy::class] ??= new SalesOrderPolicy();
    }

    public function getSalesOrderService()
    {
        return $this->instances[SalesOrderService::class] ??= new SalesOrderService(
            $this->getSalesOrderRepository(),
            $this->getSalesOrderItemRepository(),
            $this->getCustomerRepository(),
            $this->getWarehouseRepository(),
            $this->getProductRepository(),
            $this->getSalesOrderPolicy(),
            $this->getEventLogService(),
            $this->getDatabase(),
        );
    }

    public function getGoodsIssueService()
    {
        return $this->instances[GoodsIssueService::class] ??= new GoodsIssueService(
            $this->getDatabase(),
            $this->getSalesOrderRepository(),
            $this->getSalesOrderItemRepository(),
            $this->getProductStockRepository(),
            $this->getStockLedgerRepository(),
            $this->getSalesOrderPolicy(),
            $this->getEventLogService(),
            $this->getClock(),
        );
    }

    public function getDashboardService()
    {
        return $this->instances[DashboardService::class] ??= new DashboardService(
            $this->getProductRepository(),
            $this->getProductStockRepository(),
            $this->getPurchaseOrderRepository(),
            $this->getSalesOrderRepository(),
            $this->getEventLogRepository(),
        );
    }

    public function getCsvExportService()
    {
        return $this->instances[CsvExportService::class] ??= new CsvExportService();
    }

    public function getSalesDashboardService()
    {
        return $this->instances[SalesDashboardService::class] ??= new SalesDashboardService(
            $this->getSalesOrderRepository(),
        );
    }

    public function getLowStockService()
    {
        return $this->instances[LowStockService::class] ??= new LowStockService(
            $this->getProductRepository(),
            $this->getProductStockRepository(),
            $this->getNotificationService(),
        );
    }
}

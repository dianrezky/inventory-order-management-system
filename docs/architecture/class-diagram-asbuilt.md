# Class Diagram — As-Built (Post Stage 6)

- **Status:** As-Built — reflects the actual code in `app/` as of this audit
- **Date:** 2026-09-16 (updated: missing classes added, SalesOrderPolicy corrected, theme removal noted, new deviations documented)
- **Stage:** 6 (Implementation) complete for Slices 1-4; this document supersedes `class-diagram-initial.md`
- **Method:** Read every file under `app/Entity`, `app/Repository/{Interface,MySQL,Fake}`, `app/Service`, `app/Controller`, `app/Core`, and `public/index.php`. No signatures were guessed.
- **Related:** ADR-001 (Repository Pattern), ADR-002 (Pessimistic Row Lock), PRD §9 ARCH-01

---

## Changes from Initial to As-Built

Dependency inversion ended up applied more strictly than the initial sketch assumed: `GoodsIssueService` and `GoodsReceiptService` now both depend on `TransactionManagerInterface` rather than the concrete `Database` class, closing the gap the initial design left open. The repository set grew from the ~10 interfaces sketched initially to 14 (`Category`, `Product`, `ProductStock`, `Warehouse`, `Supplier`, `Customer`, `PurchaseOrder`, `PurchaseOrderItem`, `SalesOrder`, `SalesOrderItem`, `StockLedger`, `Permission`, `User`, `Notification`) as order-line, permission/audit, and notification features were added, each with full Interface+MySQL+Fake parity. Controllers no longer reach into repositories or run raw SQL directly — `ProductController`, `PurchaseOrderController`, `ProductApiController`, and `ReportController` were refactored (commit `f59854ae`) to go through their respective Service layers instead. On 2026-09-17, a bonus in-app notification feature (`NotificationService`) was added per brief §2 "Scheduled Job — Notifikasi stok rendah" (DIPERBOLEHKAN): `scripts/check-low-stock.php` now writes a deduplicated notification row per low-stock product+warehouse, surfaced on the Admin and WarehouseStaff dashboards.

---

## Diagram

```mermaid
classDiagram
    %% ============== CORE LAYER ==============
    class Database {
        -pdo PDO
        +__construct(string host, int port, string dbName, string user, string password)
        +pdo() PDO
        +transaction(callable fn) mixed
        +lastInsertId() string
    }

    class TransactionManagerInterface {
        <<interface>>
        +transaction(callable fn) mixed
    }

    class FakeTransactionManager {
        +transaction(callable fn) mixed
    }

    class Container {
        -config array
        -instances array
        +config(string key, mixed default) mixed
        +getDatabase() Database
        +getSessionManager() SessionManager
        +getLocaleResolver() LocaleResolver
        +getTranslator() Translator
        +getPermissionService() PermissionService
        +getStockLedgerService() StockLedgerService
        +get*Repository() ...Interface
        +get*Service() ...Service
    }

    class SessionManager {
        -name string
        -lifetime int
        +start() void
        +regenerate() void
        +get(string key, mixed default) mixed
        +set(string key, mixed value) void
        +has(string key) bool
        +remove(string key) void
        +destroy() void
    }

    class LocaleResolver {
        -session SessionManager
        +resolve() string
        +supportedLocales() list~string~
    }

    class Translator {
        -localesPath string
        -locale string
        -fallbackLocale string
        +locale() string
        +t(string key, array params) string
    }

    %% --- NEW in 2026-09-16 ---
    class Result {
        <<final, immutable DTO>>
        +int code
        +mixed data
        +string message
        +bool ok
        +bool validationError
        +bool internalError
        +isNotFound() bool
        +getOrThrow() mixed
        +CODE_SUCCESS 0
        +CODE_VALIDATION 1
        +CODE_INTERNAL 2
        +CODE_NOT_FOUND 3
        +ok(mixed data, string message) Result
        +validationError(string message, array errors) Result
        +internalError(string message) Result
        +notFound(string message) Result
    }

    class Response {
        <<final>>
        +int statusCode
        +array headers
        +string|false body
        +json(array data, int statusCode) Response
        +redirect(string url) Response
        +text(string body, int statusCode) Response
        +withHeader(string key, string value) Response
        +send() void
    }

    class ViewModel {
        <<final, immutable>>
        +array data
        +string|false layout
        +string|false section
        +with(string key, mixed val) ViewModel
        +withLayout(string layout) ViewModel
        +withSection(string section) ViewModel
        +layout(string|false v) ViewModel
    }
    %% --- END NEW ---

    class QueryBuilder {
        <<final, used by all MySQL repositories>>
        -PDO pdo
        -string table
        -array select
        -array join
        -?string where
        -array whereParams
        -?string orderBy
        -?int limit
        -?int offset
        +select(string ...$cols) self
        +join(string table, string cond) self
        +where(string cond, mixed ...$params) self
        +orderBy(string col, string dir) self
        +limit(int n) self
        +offset(int n) self
        +fetchAll() array
        +fetchOne() ?array
        +count() int
        +insert(array data) int
        +update(array data) int
        +delete() int
    }

    Database ..|> TransactionManagerInterface
    FakeTransactionManager ..|> TransactionManagerInterface
    LocaleResolver --> SessionManager

    %% ============== ENTITIES (plain readonly-property DTOs) ==============
    class User {
        +int id
        +string name
        +string email
        +string passwordHash
        +Role role
        +bool isActive
        +?DateTimeImmutable createdAt
        +?DateTimeImmutable updatedAt
        +fromArray(array row)$ User
        +toArray() array
    }

    class Category {
        +int id
        +string name
        +?string description
        +bool isActive
        +?DateTimeImmutable createdAt
        +?DateTimeImmutable updatedAt
    }

    class Product {
        +int id
        +string sku
        +string name
        +int categoryId
        +string unit
        +string purchasePrice
        +string salePrice
        +int reorderPoint
        +?string imagePath
        +bool isActive
        +?DateTimeImmutable createdAt
        +?DateTimeImmutable updatedAt
        +?string categoryName
    }

    class ProductStock {
        +int id
        +int productId
        +int warehouseId
        +int quantity
        +?DateTimeImmutable updatedAt
    }

    class Warehouse {
        +int id
        +string code
        +string name
        +?string location
        +bool isActive
    }

    class Supplier {
        +int id
        +string name
        +?string contactPerson
        +?string phone
        +?string email
        +?string address
        +bool isActive
    }

    class Customer {
        +int id
        +string name
        +?string contactPerson
        +?string phone
        +?string email
        +?string address
        +bool isActive
    }

    class PurchaseOrder {
        +const STATUS_DRAFT
        +const STATUS_ORDERED
        +const STATUS_PARTIALLY_RECEIVED
        +const STATUS_RECEIVED
        +const STATUS_CANCELLED
        +int id
        +int supplierId
        +int destinationWarehouseId
        +string status
        +string orderDate
        +?string note
        +int createdBy
        +list~PurchaseOrderItem~ items
        +withItems(array items) self
        +canBeCancelled() bool
        +canReceiveGoods() bool
    }
    note for PurchaseOrder "canBeCancelled() allows Draft, Ordered, AND PartiallyReceived (brief §2.3: cancellable at any stage before Received) — only Received/Cancelled block it. Already-received stock is not reversed on cancel."

    class PurchaseOrderItem {
        +int id
        +int purchaseOrderId
        +int productId
        +int qtyOrdered
        +int qtyReceived
        +string purchasePrice
        +?string productName
        +?string productSku
        +?string productUnit
        +qtyRemaining() int
        +isFullyReceived() bool
    }

    class SalesOrder {
        +const STATUS_DRAFT
        +const STATUS_PENDING_APPROVAL
        +const STATUS_APPROVED
        +const STATUS_FULFILLED
        +const STATUS_CANCELLED
        +int id
        +int customerId
        +int sourceWarehouseId
        +string status
        +string orderDate
        +?string note
        +int createdBy
        +?int approvedBy
        +?DateTimeImmutable approvedAt
        +?int issuedBy
        +?DateTimeImmutable issuedAt
        +?string cancellationReason
        +list~SalesOrderItem~ items
        +withItems(array items) self
        +canBeCancelledByCreator() bool
        +canIssueGoods() bool
        +canEditItems() bool
    }

    class SalesOrderItem {
        +int id
        +int salesOrderId
        +int productId
        +int qty
        +string salePrice
        +?string productName
        +?string productSku
        +?string productUnit
    }

    class StockLedgerEntry {
        +const TYPE_RECEIPT
        +const TYPE_ISSUE
        +const TYPE_ADJUSTMENT
        +const REF_PO
        +const REF_SO
        +const REF_ADJUSTMENT
        +int id
        +int productId
        +int warehouseId
        +string type
        +int qty
        +?string refType
        +?int refId
        +?string note
        +int doneByUserId
        +?DateTimeImmutable doneAt
    }

    class Role {
        <<enum>>
        Admin
        Sales
        WarehouseStaff
    }

    %% ============== SERVICE EXCEPTIONS ==============
    class DomainException {
        <<abstract, extends php RuntimeException>>
    }
    class SalesApprovalForbiddenException
    class InsufficientStockException
    class InvalidStateException
    class InvalidImageException {
        <<extends php RuntimeException directly, not DomainException>>
    }

    SalesApprovalForbiddenException --|> DomainException
    InsufficientStockException --|> DomainException
    InvalidStateException --|> DomainException

    %% ============== REPOSITORY INTERFACES (12 — as-built) ==============
    class UserRepositoryInterface {
        <<interface>>
        +findById(int id) ?User
        +findByEmail(string email) ?User
        +findAll(?string search) list~User~
        +emailExists(string email, ?int excludeId) bool
        +create(array data) int
        +update(int id, array data) void
        +updatePassword(int id, string hash) void
        +setActive(int id, bool active) void
    }

    class ProductRepositoryInterface {
        <<interface>>
        +findById(int id) ?Product
        +findBySku(string sku) ?Product
        +findAll(?string search, int limit, int offset) list~Product~
        +countAll(?string search) int
        +findLowStock() list~Product~
        +findAllActive() list~Product~
        +skuExists(string sku, ?int excludeId) bool
        +create(array data) int
        +update(int id, array data) void
        +updateImagePath(int id, ?string path) void
        +setActive(int id, bool active) void
    }

    class ProductStockRepositoryInterface {
        <<interface>>
        +find(int productId, int warehouseId) ?ProductStock
        +lockForUpdate(int productId, int warehouseId) Result
        +create(int productId, int warehouseId, int quantity) int
        +incrementQuantity(int productId, int warehouseId, int delta) void
        +totalInventoryValue() string
        +sumByProductId(int productId) int
        +findAllWithProduct(int productId) array
    }

    class PurchaseOrderRepositoryInterface {
        <<interface>>
        +findById(int id) ?PurchaseOrder
        +findAll(?string status, int limit, int offset) list~PurchaseOrder~
        +countAll(?string status) int
        +countByStatus() array
        +create(array data) int
        +updateStatus(int id, string status) void
    }

    class PurchaseOrderItemRepositoryInterface {
        <<interface>>
        +findById(int id) ?PurchaseOrderItem
        +findByPurchaseOrderId(int poId) list~PurchaseOrderItem~
        +create(array data) int
        +incrementQtyReceived(int id, int qty) void
    }

    class SalesOrderRepositoryInterface {
        <<interface>>
        +findById(int id) ?SalesOrder
        +findAll(?int userId, array filters, int limit, int offset) list~SalesOrder~
        +countAll(?int userId, array filters) int
        +countByStatus(?int userId) array
        +create(array header, array items) int
        +updateStatus(int id, string status, array extras) void
        +delete(int id) void
        +lockForUpdate(int id) void
    }

    class SalesOrderItemRepositoryInterface {
        <<interface>>
        +findBySalesOrderId(int soId) array
        +findBySalesOrderIdWithProduct(int soId) list~SalesOrderItem~
    }

    class StockLedgerRepositoryInterface {
        <<interface>>
        +insert(array data) int
        +listByProductWarehouse(int productId, int warehouseId) list~StockLedgerEntry~
        +sumByProductWarehouse(int productId, int warehouseId) int
    }

    class WarehouseRepositoryInterface {
        <<interface>>
        +findById(int id) ?Warehouse
        +findAll(?string search) list~Warehouse~
        +findAllActive() list~Warehouse~
        +codeExists(string code, ?int excludeId) bool
        +create(array data) int
        +update(int id, array data) void
        +setActive(int id, bool active) void
    }

    class SupplierRepositoryInterface {
        <<interface>>
        +findById(int id) ?Supplier
        +findAll(?string search) list~Supplier~
        +findAllActive() list~Supplier~
        +create(array data) int
        +update(int id, array data) void
        +setActive(int id, bool active) void
    }

    class CustomerRepositoryInterface {
        <<interface>>
        +findById(int id) ?Customer
        +findAll(?string search) list~Customer~
        +findAllActive() list~Customer~
        +create(array data) int
        +update(int id, array data) void
        +setActive(int id, bool active) void
    }

    class CategoryRepositoryInterface {
        <<interface>>
        +findById(int id) ?Category
        +findAll(?string search) list~Category~
        +findAllActive() list~Category~
        +nameExists(string name, ?int excludeId) bool
        +create(array data) int
        +update(int id, array data) void
        +setActive(int id, bool active) void
    }

    class PermissionRepositoryInterface {
        <<interface>>
        +findByRole(string role) array
        +findAll() array
    }

    %% ============== REPOSITORY IMPLEMENTATIONS (12 MySQL + 12 Fake — full parity) ==============
    class UserMySQLRepository
    class ProductMySQLRepository
    class ProductStockMySQLRepository
    class PurchaseOrderMySQLRepository
    class PurchaseOrderItemMySQLRepository
    class SalesOrderMySQLRepository
    class SalesOrderItemMySQLRepository
    class StockLedgerMySQLRepository
    class WarehouseMySQLRepository
    class SupplierMySQLRepository
    class CustomerMySQLRepository
    class CategoryMySQLRepository

    class CategoryMySQLRepository
    class PermissionMySQLRepository
    class PermissionFakeRepository
    class UserFakeRepository
    class ProductFakeRepository
    class ProductStockFakeRepository
    class PurchaseOrderFakeRepository
    class PurchaseOrderItemFakeRepository
    class SalesOrderFakeRepository
    class SalesOrderItemFakeRepository
    class StockLedgerFakeRepository
    class WarehouseFakeRepository
    class SupplierFakeRepository
    class CustomerFakeRepository
    class CategoryFakeRepository

    UserMySQLRepository ..|> UserRepositoryInterface
    UserFakeRepository ..|> UserRepositoryInterface
    ProductMySQLRepository ..|> ProductRepositoryInterface
    ProductFakeRepository ..|> ProductRepositoryInterface
    ProductStockMySQLRepository ..|> ProductStockRepositoryInterface
    ProductStockFakeRepository ..|> ProductStockRepositoryInterface
    PurchaseOrderMySQLRepository ..|> PurchaseOrderRepositoryInterface
    PurchaseOrderFakeRepository ..|> PurchaseOrderRepositoryInterface
    PurchaseOrderItemMySQLRepository ..|> PurchaseOrderItemRepositoryInterface
    PurchaseOrderItemFakeRepository ..|> PurchaseOrderItemRepositoryInterface
    SalesOrderMySQLRepository ..|> SalesOrderRepositoryInterface
    SalesOrderFakeRepository ..|> SalesOrderRepositoryInterface
    SalesOrderItemMySQLRepository ..|> SalesOrderItemRepositoryInterface
    SalesOrderItemFakeRepository ..|> SalesOrderItemRepositoryInterface
    StockLedgerMySQLRepository ..|> StockLedgerRepositoryInterface
    StockLedgerFakeRepository ..|> StockLedgerRepositoryInterface
    WarehouseMySQLRepository ..|> WarehouseRepositoryInterface
    WarehouseFakeRepository ..|> WarehouseRepositoryInterface
    SupplierMySQLRepository ..|> SupplierRepositoryInterface
    SupplierFakeRepository ..|> SupplierRepositoryInterface
    CustomerMySQLRepository ..|> CustomerRepositoryInterface
    CustomerFakeRepository ..|> CustomerRepositoryInterface
    CategoryMySQLRepository ..|> CategoryRepositoryInterface
    CategoryFakeRepository ..|> CategoryRepositoryInterface
    PermissionMySQLRepository ..|> PermissionRepositoryInterface
    PermissionFakeRepository ..|> PermissionRepositoryInterface

    UserMySQLRepository --> Database
    ProductMySQLRepository --> Database
    ProductStockMySQLRepository --> Database
    PurchaseOrderMySQLRepository --> Database
    PurchaseOrderItemMySQLRepository --> Database
    SalesOrderMySQLRepository --> Database
    SalesOrderItemMySQLRepository --> Database
    StockLedgerMySQLRepository --> Database
    WarehouseMySQLRepository --> Database
    SupplierMySQLRepository --> Database
    CustomerMySQLRepository --> Database
    CategoryMySQLRepository --> Database
    PermissionMySQLRepository --> Database

    %% ============== SERVICE LAYER ==============
    class AuthService {
        -userRepository UserRepositoryInterface
        -session SessionManager
        +login(string email, string password) User
        +logout() void
        +currentUser() ?User
    }

    class UserService {
        -userRepository UserRepositoryInterface
        +listUsers(?string search) list~User~
        +findById(int id) ?User
        +createUser(array input) User
        +updateUser(int id, array input) User
        +updateProfile(int id, string name, string email) User
        +updatePassword(int id, string passwordHash) void
        +setActive(int id, bool active) void
    }

    class PermissionService {
        -permissionRepository PermissionRepositoryInterface
        -cache CacheService
        +has(string role, string key) bool
        +grantedKeysForRole(string role) list~string~
        -buildPermissionMap() array
    }

    class StockLedgerService {
        -stockLedgerRepo StockLedgerRepositoryInterface
        +findFiltered(mixed filters, int limit, int offset) Result
        +findForExport(mixed filters) Result
    }

    class CategoryService {
        -categoryRepository CategoryRepositoryInterface
        +listCategories(?string search) list~Category~
        +listActiveCategories() list~Category~
        +createCategory(array input) Category
        +updateCategory(int id, array input) Category
        +setActive(int id, bool active) void
    }

    class WarehouseService {
        -warehouseRepository WarehouseRepositoryInterface
        +listWarehouses(?string search) list~Warehouse~
        +listActiveWarehouses() list~Warehouse~
        +createWarehouse(array input) Warehouse
        +updateWarehouse(int id, array input) Warehouse
        +setActive(int id, bool active) void
    }

    class SupplierService {
        -supplierRepository SupplierRepositoryInterface
        +listSuppliers(?string search) list~Supplier~
        +listActiveSuppliers() list~Supplier~
        +createSupplier(array input) Supplier
        +updateSupplier(int id, array input) Supplier
        +setActive(int id, bool active) void
    }

    class CustomerService {
        -customerRepository CustomerRepositoryInterface
        +listCustomers(?string search) list~Customer~
        +listActiveCustomers() list~Customer~
        +createCustomer(array input) Customer
        +updateCustomer(int id, array input) Customer
        +setActive(int id, bool active) void
    }

    class ProductService {
        -productRepository ProductRepositoryInterface
        -categoryRepository CategoryRepositoryInterface
        +listProducts(?string search) list~Product~
        +listActiveProducts() list~Product~
        +createProduct(array input, ?string imagePath) Product
        +updateProduct(int id, array input) Product
        +updateImagePath(int id, ?string imagePath) void
        +setActive(int id, bool active) void
        +getAvailability(string sku) ?array
    }
    note for ProductService "getAvailability() returns {sku, product_id, name, unit, available, total_stock, availability: [{warehouse_id, warehouse_code, warehouse_name, quantity}]} — matches PROJECT_REFERENCE.md API-01 contract exactly (product_id/warehouse_id/availability key were added; name/unit/available kept as extras consumed by public/assets/js/products.js)."

    class ImageUploadService {
        -publicPath string
        +process(array uploaded) ImageUploadResult
        +delete(?string relativePath) void
    }

    class ImageUploadResult {
        <<DTO, not a service>>
        +string path
        +int width
        +int height
    }

    class PurchaseOrderService {
        -purchaseOrderRepository PurchaseOrderRepositoryInterface
        -purchaseOrderItemRepository PurchaseOrderItemRepositoryInterface
        -supplierRepository SupplierRepositoryInterface
        -warehouseRepository WarehouseRepositoryInterface
        -productRepository ProductRepositoryInterface
        +listPurchaseOrders(?string status) list~PurchaseOrder~
        +findById(int id) ?PurchaseOrder
        +create(array input, array items, int createdByUserId) PurchaseOrder
        +submit(int id) PurchaseOrder
        +cancel(int id) PurchaseOrder
        +recomputeStatus(int id) string
    }

    class GoodsReceiptService {
        -transactionManager TransactionManagerInterface
        -purchaseOrderRepository PurchaseOrderRepositoryInterface
        -purchaseOrderItemRepository PurchaseOrderItemRepositoryInterface
        -productStockRepository ProductStockRepositoryInterface
        -stockLedgerRepository StockLedgerRepositoryInterface
        -purchaseOrderService PurchaseOrderService
        +process(int purchaseOrderId, array receiptLines, int userId) PurchaseOrder
    }

    class SalesOrderPolicy {
        <<policy - pure, no DB/HTTP>>
        +assertCanDecide(bool isActorAdmin) void
        +assertCanCancel(SalesOrder so, int actorId, bool isAdmin) void
        +assertCanIssue(SalesOrder so) void
    }
    note for SalesOrderPolicy "As of 2026-09-08: assertCanDecide() checks ROLE ONLY (BR-SOD-02 total denial).
Admin may approve their own orders. Sales may never approve any order."

    class SalesOrderService {
        -soRepository SalesOrderRepositoryInterface
        -soItemRepository SalesOrderItemRepositoryInterface
        -customerRepository CustomerRepositoryInterface
        -warehouseRepository WarehouseRepositoryInterface
        -productRepository ProductRepositoryInterface
        -policy SalesOrderPolicy
        +listSalesOrders(?int userId, array filters, int limit, int offset) list~SalesOrder~
        +countSalesOrders(?int userId, array filters) int
        +findById(int id) ?SalesOrder
        +create(array input, array items, int createdByUserId) SalesOrder
        +submitForApproval(int id, int actorId) SalesOrder
        +approve(int id, int actorId) SalesOrder
        +reject(int id, int actorId, ?string reason) SalesOrder
        +cancel(int id, int actorId, bool isAdmin) SalesOrder
    }

    class GoodsIssueService {
        -transactionManager TransactionManagerInterface
        -soRepository SalesOrderRepositoryInterface
        -soItemRepository SalesOrderItemRepositoryInterface
        -stockRepository ProductStockRepositoryInterface
        -ledgerRepository StockLedgerRepositoryInterface
        -policy SalesOrderPolicy
        +issue(int soId, int actorUserId) SalesOrder
    }
    note for GoodsIssueService "RESOLVED: now injects TransactionManagerInterface (same as GoodsReceiptService) — see Deviations #1"

    class DashboardService {
        -stockRepo ProductStockRepositoryInterface
        -productRepo ProductRepositoryInterface
        -poRepo PurchaseOrderRepositoryInterface
        -soRepo SalesOrderRepositoryInterface
        +getAdminStats() array
        +getSalesStats(int userId) array
        +getWarehouseStats() array
    }

    class NotificationService {
        -notificationRepository NotificationRepositoryInterface
        +notifyLowStock(int productId, int warehouseId, string message) Result
        +getUnreadForDashboard(int limit) list~Notification~
        +countUnread() int
        +markAllRead() Result
    }
    note for NotificationService "Bonus feature (brief §2 'Scheduled Job — Notifikasi stok rendah', DIPERBOLEHKAN). Called from scripts/check-low-stock.php (JOB-01) and DashboardController for Admin/WarehouseStaff only — not wired into any real-time stock-mutation path. Dedup via existsUnreadFor() prevents re-notifying while an unread entry for the same product+warehouse already exists."

    class Notification {
        +int id
        +string type
        +string message
        +?int productId
        +?int warehouseId
        +?string productSku
        +?string productName
        +?string warehouseName
        +?DateTimeImmutable readAt
        +DateTimeImmutable createdAt
        +isRead() bool
    }

    NotificationService --> NotificationRepositoryInterface
    NotificationRepositoryInterface <|.. NotificationMySQLRepository : implements
    NotificationRepositoryInterface <|.. NotificationFakeRepository : implements
    NotificationMySQLRepository --> Database

    class CsvExportService {
        +exportStockLedger(array rows, string locale) string
        +exportOrderStatus(array rows, string locale) string
    }
    note for CsvExportService "DEVIATION: takes pre-fetched row arrays, no repository dependencies at all — see Deviations #4"

    AuthService --> UserRepositoryInterface
    AuthService --> SessionManager
    UserService --> UserRepositoryInterface
    PermissionService --> PermissionRepositoryInterface
    StockLedgerService --> StockLedgerRepositoryInterface
    CategoryService --> CategoryRepositoryInterface
    WarehouseService --> WarehouseRepositoryInterface
    SupplierService --> SupplierRepositoryInterface
    CustomerService --> CustomerRepositoryInterface
    ProductService --> ProductRepositoryInterface
    ProductService --> ProductStockRepositoryInterface
    ProductService --> CategoryRepositoryInterface
    ProductService --> ProductStockRepositoryInterface
    PurchaseOrderService --> PurchaseOrderRepositoryInterface
    PurchaseOrderService --> PurchaseOrderItemRepositoryInterface
    PurchaseOrderService --> SupplierRepositoryInterface
    PurchaseOrderService --> WarehouseRepositoryInterface
    PurchaseOrderService --> ProductRepositoryInterface
    GoodsReceiptService --> TransactionManagerInterface
    GoodsReceiptService --> PurchaseOrderRepositoryInterface
    GoodsReceiptService --> PurchaseOrderItemRepositoryInterface
    GoodsReceiptService --> ProductStockRepositoryInterface
    GoodsReceiptService --> StockLedgerRepositoryInterface
    GoodsReceiptService --> PurchaseOrderService
    SalesOrderService --> SalesOrderRepositoryInterface
    SalesOrderService --> SalesOrderItemRepositoryInterface
    SalesOrderService --> CustomerRepositoryInterface
    SalesOrderService --> WarehouseRepositoryInterface
    SalesOrderService --> ProductRepositoryInterface
    SalesOrderService --> SalesOrderPolicy
    GoodsIssueService --> TransactionManagerInterface
    GoodsIssueService --> SalesOrderRepositoryInterface
    GoodsIssueService --> SalesOrderItemRepositoryInterface
    GoodsIssueService --> ProductStockRepositoryInterface
    GoodsIssueService --> StockLedgerRepositoryInterface
    GoodsIssueService --> SalesOrderPolicy
    DashboardService --> ProductStockRepositoryInterface
    DashboardService --> ProductRepositoryInterface
    DashboardService --> PurchaseOrderRepositoryInterface
    DashboardService --> SalesOrderRepositoryInterface
    ImageUploadService --> ImageUploadResult

    %% ============== CONTROLLERS ==============
    class BaseController {
        <<abstract>>
        #container Container
        #translator Translator
        #t(string key, array params) string
        #csrfToken() string
        #requireCsrf() void
        #render(string view, array data, string layout) void
        #redirect(string url) void
        #json(array data, int statusCode) void
        #getJsonBody() array
        #currentUser() ?User
        #requireAuth() User
        #requireRole(Role...roles) User
        #requirePermission(string key) void
        #requirePermissionWithCsrf(string key) void
        #requireAuthWithCsrf() User
        #isXhr() bool
        #searchTerm(?string param) ?string
        #parseItemsFromRequest() array
        #view(string view, array data, ?string section) ViewModel
        #getPermissionService() PermissionService
    }
    note for BaseController "requireAuth()/requireRole() return User (not void). All authorization checks route through PermissionService (not raw role strings). view() returns ViewModel with currentUser, csrfToken, and grantedPermissions auto-injected."

    class AuthController {
        +showLogin() void
        +login() void
        +logout() void
    }

    class DashboardController {
        +index() void
    }

    class UserController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +editForm(int id) void
        +update(int id) void
        +deactivate(int id) void
        +activate(int id) void
    }

    class ProductController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +editForm(int id) void
        +update(int id) void
        +deactivate(int id) void
        +activate(int id) void
    }
    note for ProductController "DEVIATION: index() calls Container::getProductRepository() directly, bypassing ProductService — see Deviations #2"

    class WarehouseController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +editForm(int id) void
        +update(int id) void
        +deactivate(int id) void
        +activate(int id) void
    }

    class SupplierController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +editForm(int id) void
        +update(int id) void
        +deactivate(int id) void
        +activate(int id) void
    }

    class CustomerController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +editForm(int id) void
        +update(int id) void
        +deactivate(int id) void
        +activate(int id) void
    }

    class PurchaseOrderController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +submit(int id) void
        +cancel(int id) void
        +receiveForm(int id) void
        +receive(int id) void
    }
    note for PurchaseOrderController "DEVIATION: index() calls Container::getPurchaseOrderRepository() directly, bypassing PurchaseOrderService"

    class SalesOrderController {
        +index() void
        +show(int id) void
        +createForm() void
        +store() void
        +submit(int id) void
        +approve(int id) void
        +reject(int id) void
        +cancel(int id) void
        +issue(int id) void
    }

    class ReportController {
        +showForm() void
        +exportStockLedger() void
        +exportOrders() void
        -dateParam(string primaryKey, string fallbackKey) string
        -sendCsv(string csv, string filename) void
    }
    note for ReportController "RESOLVED (f59854ae): now delegates to StockLedgerService/SalesOrderService/PurchaseOrderService findForExport() — no raw SQL in the Controller. See Deviations #3."

    class ProductApiController {
        +getAvailability(string sku) void
    }
    note for ProductApiController "RESOLVED (f59854ae): no longer calls Container::getProductRepository()/getProductStockRepository() directly. See Deviations #2."

    class ProfileController {
        +index() void
        +updateProfile() void
        +updatePassword() void
    }

    class StockLedgerController {
        +index() void
    }
    note for StockLedgerController "AJAX pagination/filtering via renderPartial() — returns ViewModel with tbody HTML injected into the page"

    AuthController --|> BaseController
    DashboardController --|> BaseController
    UserController --|> BaseController
    ProductController --|> BaseController
    WarehouseController --|> BaseController
    SupplierController --|> BaseController
    CustomerController --|> BaseController
    PurchaseOrderController --|> BaseController
    SalesOrderController --|> BaseController
    ReportController --|> BaseController
    ProductApiController --|> BaseController
    ProfileController --|> BaseController
    StockLedgerController --|> BaseController

    AuthController --> AuthService
    DashboardController --> DashboardService
    UserController --> UserService
    ProductController --> ProductService
    ProductController --> CategoryService
    ProductController --> ImageUploadService
    WarehouseController --> WarehouseService
    SupplierController --> SupplierService
    CustomerController --> CustomerService
    PurchaseOrderController --> PurchaseOrderService
    PurchaseOrderController --> GoodsReceiptService
    PurchaseOrderController --> SupplierService
    PurchaseOrderController --> WarehouseService
    PurchaseOrderController --> ProductService
    SalesOrderController --> SalesOrderService
    SalesOrderController --> GoodsIssueService
    SalesOrderController --> CustomerService
    SalesOrderController --> WarehouseService
    SalesOrderController --> ProductService
    ReportController --> CsvExportService
    ProductApiController --> AuthService
    ProductApiController --> ProductService
    ProfileController --> UserService
    StockLedgerController --> StockLedgerService
    StockLedgerController --> PermissionService
```

---

## Design Notes

### Layer Boundaries

| Layer | Boleh Depend Pada | TIDAK Boleh Depend Pada | Status as-built |
|-------|-------------------|--------------------------|------------------|
| **Controller** | Service, Container/Base (Session, Translator) | Repository langsung, PDO, SQL | **Resolved** — historical breach documented in Deviations #2 and #3, fixed by the QueryBuilder/Service refactor in commit `f59854ae`; verified 2026-09-17 via `grep -rn "getRepository\|Repository" app/Controller/*.php` → 0 direct repository calls |
| **Service** | Repository interface, Database/TransactionManagerInterface, Entity, Policy | Controller, View, Request superglobals | **Holds (resolved)** — historical breach documented in Deviations #1 (GoodsIssueService injected the concrete `Database` instead of `TransactionManagerInterface`), fixed by type-hinting the constructor to the interface; all services now depend on abstractions only |
| **Repository** | Database (PDO), Entity | Service, Controller | Holds |
| **Entity** | (pure value object — no dependencies) | * | Holds — entities are `final` classes with `public readonly` properties, `fromArray()`/`toArray()`, no setters |

### Anti-corruption Invariants (ARCH-01)

1. **Tidak ada `new PDO()` di Service atau Controller** — Holds. `App\Core\Database` is the only place `new PDO(...)` appears (`grep -rn "new PDO" app/Service app/Controller` → 0 hits).
2. **Tidak ada `new MySQLRepository()` di Service** — Holds. All `MySQLRepository` instantiation happens in `Container`.
3. **Tidak ada HTTP-aware code (header, session_start, exit) di Service** — Holds. `header()`/`exit`/`session_start()` are confined to `BaseController`, `SessionManager`, and `ReportController::sendCsv()`.
4. **Tidak ada SQL query di Controller** — **Holds (resolved).** Historically breached: `ReportController::fetchLedgerRows()`/`fetchOrderRows()` used to build and execute raw SQL directly via `$this->container->getDatabase()->pdo()->prepare(...)`. As of the `f59854ae` refactor, `ReportController` fetches export rows via `getStockLedgerService()->findForExport()`, `getSalesOrderService()->findForExport()`, and `getPurchaseOrderService()->findForExport()` — no `pdo(`/`prepare(`/raw SQL string remains in the controller (verified 2026-09-17). See Deviations #3.

### Key Patterns

| Pattern | Lokasi Contoh |
|---------|---------------|
| **Repository** | `XxxRepositoryInterface` (14) + `XxxMySQLRepository` (14) + `XxxFakeRepository` (14) — full 1:1:1 parity, no gaps |
| **Service Layer** | Business logic in `app/Service/*` (16 files, incl. 2 non-service DTOs — see below) |
| **Policy** | `SalesOrderPolicy` — pure authorization/state-machine class, used by both `SalesOrderService` and `GoodsIssueService` |
| **Transaction wrapper** | `Database::transaction(callable)`, abstracted for testability via `TransactionManagerInterface` + `FakeTransactionManager` — both `GoodsReceiptService` and `GoodsIssueService` now depend on the interface (see Deviations #1, resolved) |
| **Composition root** | `Container::get*()` — manual DI, single wiring point, one memoized instance per class per request |
| **Entity as immutable DTO** | Every entity is `final`, `public readonly` properties, `fromArray()`/`toArray()`, no setters — `PurchaseOrder`/`SalesOrder` add an immutable `withItems()` wither instead of a setter |
| **Domain exceptions** | `App\Service\Exception\DomainException` (abstract, extends `RuntimeException`) with 3 concrete subclasses (`SalesApprovalForbiddenException`, `InsufficientStockException`, `InvalidStateException`) + a standalone `InvalidImageException` that extends `RuntimeException` directly (not `DomainException`) |
| **Status/type as string constants, not enums** | `PurchaseOrder::STATUS_*`, `SalesOrder::STATUS_*`, `StockLedgerEntry::TYPE_*`/`REF_*` are `public const string` on the entity itself, not PHP 8.1 backed enums — see Deviations #5 |

### Out of Scope (unchanged from initial)

- No UseCase/Command layer, no Event bus/Observer, no DI container framework, no ORM/Active Record, no CQRS/Event Sourcing. All hold true as-built.

---

## Deviations from Initial Design

Ordered most → least significant.

### 1. HISTORICAL — `GoodsIssueService` used to bypass the transaction abstraction (resolved)

The initial design showed both `GoodsReceiptService` and `GoodsIssueService` depending on `Database` (the initial diagram itself only listed `Database`, before `TransactionManagerInterface` existed). For a period as-built, the two sibling services diverged:

- `GoodsReceiptService` depended on `TransactionManagerInterface` (injected as `Database` in production via `Container::getGoodsReceiptService()`, swappable for `FakeTransactionManager` in tests) — clean.
- `GoodsIssueService` depended on the **concrete** `Database` class directly instead of the interface — an ARCH-01-style DIP breach in the Service layer, since `TransactionManagerInterface` (and its `FakeTransactionManager` test double) exist in `app/Core` specifically to let services be unit-tested without a real PDO connection.

**Resolved:** `GoodsIssueService`'s constructor now type-hints `TransactionManagerInterface $transactionManager` (matching `GoodsReceiptService`), so both services depend on the abstraction consistently. `Container::getGoodsIssueService()` still passes `$this->getDatabase()` in production (which implements the interface) and tests still use `FakeTransactionManager`. The row-locking logic itself (`SELECT ... FOR UPDATE` via the repository's `lockForUpdate()`) was already unchanged and remains ARCH-02-compliant — only the constructor's dependency type changed.

### 2. HISTORICAL — Two controllers used to call repositories directly, skipping the Service layer (resolved by `f59854ae`)

Initial design's layer table forbids Controller → Repository. As of the 2026-09-16 audit this was true:
- `ProductController::index()` called `Container::getProductRepository()->countAll()/findAll()` directly instead of going through `ProductService` (which had no `listProducts`-with-pagination method — only `listProducts(?search)` with no `limit`/`offset`).
- `PurchaseOrderController::index()` called `Container::getPurchaseOrderRepository()->countAll()/findAll()` directly for the same reason — `PurchaseOrderService::listPurchaseOrders()` had no pagination parameters.
- `ProductApiController::getAvailability()` called `Container::getProductRepository()->findBySku()` and `Container::getProductStockRepository()->findAllWithProduct()` directly — there was no dedicated service for the stock-availability API endpoint.

**Resolved:** commit `f59854ae` ("Refactor MySQL repositories to use QueryBuilder for all SELECT queries") introduced the pagination-aware Service methods these controllers were missing. Verified 2026-09-17: `grep -rn "getRepository(" app/Controller/*.php` → 0 hits; all three controllers now go through their Service layer.

### 3. HISTORICAL — `ReportController` used to execute raw SQL directly (resolved by `f59854ae`)

Previously, `ReportController::fetchLedgerRows()` and `fetchOrderRows()` built multi-line SQL strings with JOINs and `GROUP BY` and executed them via `$this->container->getDatabase()->pdo()->prepare($sql)` — directly inside the Controller, violating the stated invariant "Tidak ada SQL query di Controller." No `ReportRepository` or equivalent existed; `CsvExportService` only formatted rows already fetched by the controller (see #4).

**Resolved:** `ReportController` now delegates data fetching to `getStockLedgerService()->findForExport()`, `getSalesOrderService()->findForExport()`, and `getPurchaseOrderService()->findForExport()`; `CsvExportService` still only formats pre-fetched rows (see #4). Verified 2026-09-17: no `pdo(`, `->query(`, or raw `SELECT` string remains in `app/Controller/ReportController.php`.

### 4. `CsvExportService` has no repository dependencies at all (design assumption was wrong)

The initial diagram gave `CsvExportService` three repository dependencies (`StockLedgerRepositoryInterface`, `SalesOrderRepositoryInterface`, `PurchaseOrderRepositoryInterface`) and two methods taking `Date`/locale args. As-built, `CsvExportService` has a zero-argument constructor and takes pre-fetched `array` rows plus a `locale` string — it is a pure formatter (CSV-injection-safe field escaping + locale-aware headers/labels), and the actual data fetching was pushed into `ReportController` (raw SQL, per #3) rather than into the service. Net effect: the service is more narrowly scoped than designed, and the fetching responsibility landed in the wrong layer.

### 5. No `PoStatus`, `SoStatus`, or `LedgerType` backed enums exist — status/type are string constants on the entity

The initial diagram specified three PHP 8.1 backed enums (`PoStatus`, `SoStatus`, `LedgerType`). As-built, the only real enum in the codebase is `App\Entity\Role` (`enum Role: string`). `PurchaseOrder::status`, `SalesOrder::status`, and `StockLedgerEntry::type`/`refType` are plain `string` properties, validated/compared against `public const string` class constants defined on the entity itself (e.g. `PurchaseOrder::STATUS_DRAFT`, `StockLedgerEntry::TYPE_RECEIPT`). This still gives type-safety at the "known value" level via `match`/`in_array(..., true)` checks in the Services, but callers can pass an arbitrary string where a real enum would be statically rejected by PHPStan/IDE.

### 6. Theme — implemented client-side, then fully removed (2026-09-15)

`public/index.php`'s route table has no `/theme/switch` route, and no `ThemeController` file exists.

**Previous state (2026-09-02):** A client-side theme feature existed: `views/layouts/main.php` rendered a
tri-state toggle button (`data-theme-toggle`, `onclick="toggleTheme()"`), backed by `public/assets/js/theme.js`,
which cycled `auto → light → dark`, persisted to `localStorage['theme']`, applied via `data-theme` on `<html>`.

**Correction (2026-09-15):** Dark theme entirely removed. Light theme only per Stitch design specification.
`public/assets/js/theme.js` deleted. Dark token block in `tokens.css` deleted. Theme toggle buttons removed
from all layouts. `<html>` no longer has `data-theme`. `grep 'data-theme\|toggleTheme\|dark' → 0 hits`.
Application is light-theme only.

### 7. Repository interface set expanded from 10 to 14, with full Fake parity

The initial diagram listed 10 repository interfaces. Two more (`PurchaseOrderItemRepositoryInterface`,
`SalesOrderItemRepositoryInterface`) were added in the initial build pass, bringing the count to 12. One more
added in the 2026-09-16 audit: `PermissionRepositoryInterface` (reads `role_permissions` table, cached in
Memcached), bringing the total to 13. One more added 2026-09-17 for the bonus low-stock notification feature:
`NotificationRepositoryInterface` (reads/writes the `notifications` table), bringing the confirmed total to 14
(`app/Repository/Interface/*.php`: Category, Product, ProductStock, Warehouse, Supplier, Customer,
PurchaseOrder, PurchaseOrderItem, SalesOrder, SalesOrderItem, StockLedger, Permission, User, Notification). All
14 interfaces have both MySQL and Fake implementations (14/14/14). This is a positive deviation. (A prior
revision of this document miscounted 13 repositories as 14 before Notification existed; both the count and the
underlying repository list are now independently correct.)

### 8. `SalesOrderPolicy::assertCanDecide()` replaces the initial `canApprove(User, SalesOrder)` design

The initial design showed `canApprove(User $actor, SalesOrder $order): bool` checking both role and creator
identity. As-built (2026-09-08 correction): `assertCanDecide(bool $isActorAdmin)` — role denial only.
Admin may approve any order including their own. Sales may never approve any order. The creator identity check
was removed per DEC-012 alignment with the brief's SoD rule interpretation. Tests confirm: `SalesOrderPolicyTest`
covers both cases, `BR001SegregationTest` confirms at the HTTP level.

### 9. `BaseController` expanded with PermissionService integration and ViewModel return type

`requireCsrf()` and `csrfToken()` were added (Slice 5 CSRF fix). `requirePermission(string $key)` routes all
authorization through `PermissionService` (not raw role strings). `isXhr()`, `searchTerm()`,
`parseItemsFromRequest()` added as AJAX helpers. `view()` returns `ViewModel` (not `void`) — a read-only
immutable data-bag injected into every page: `currentUser`, `csrfToken`, and `grantedPermissions` (the
permission keys the current user may exercise) are injected automatically so views never need to call services
for basic authorization state.

### 10. Controller action names differ from the initial diagram

The initial diagram used `create()`/`edit()`/`processReceipt()`; as-built every controller uses
`createForm()`/`editForm()` for the GET form-display action (reserving `store()`/`update()` for the POST
handler, matching Rails-style naming) and `PurchaseOrderController::receive()` (not `processReceipt()`)
for the POST goods-receipt handler, with a separate `receiveForm()` GET action not present in the initial
diagram at all. `AuthController::handleLogin()`/`handleLogout()` are simply `login()`/`logout()` as-built.

### 11. New classes not in initial diagram (now added, 2026-09-16)

The following classes existed in the codebase but were absent from this diagram (identified 2026-09-16):
`Result`, `Response`, `ViewModel`, `QueryBuilder`, `PermissionService`, `PermissionRepositoryInterface`,
`PermissionMySQLRepository`, `PermissionFakeRepository`, `StockLedgerService`, `ProfileController`,
`StockLedgerController`. Added to this diagram as of 2026-09-16.

---

## References
- `docs/architecture/class-diagram-initial.md` (superseded by this document)
- `docs/architecture/adr-001-repository-pattern.md`
- `docs/architecture/adr-002-concurrency-strategy.md`
- `docs/planning/master-project-specification.md`
- `docs/quality/refactor-log.md`

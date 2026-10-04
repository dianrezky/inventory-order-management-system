# SRP Audit — Service Layer

**Document:** `docs/quality/srp-audit.md`
**Project:** Inventory & Order Management System
**Purpose:** Verify each service class has a single, well-defined responsibility.
**Standard:** Single Responsibility Principle (Robert C. Martin) — a class should have only one reason to change.

---

## Service Classes — Audit Table

| Class | Responsibility | SRP Status | Lines | Reason to Change |
|---|---|---|---|---|
| `AuthService` | Session + password verification | ✅ Single | ~50 | Auth rules change |
| `DashboardService` | KPI query orchestration | ✅ Single | ~120 | New KPI / changed metric |
| `CsvExportService` | CSV generation (RFC 4180) | ✅ Single | ~147 | New export format / locale |
| `SalesOrderPolicy` | SO authorization + state rules | ✅ Single | ~85 | New business rule |
| `SalesOrderService` | SO lifecycle (create/submit/approve/reject/cancel) | ✅ Single | ~248 | SO workflow changes |
| `GoodsIssueService` | Goods issue: stock decrement + ledger | ✅ Single | ~120 | Issue workflow changes |
| `GoodsReceiptService` | Goods receipt: stock increment + ledger | ✅ Single | ~80 | Receipt workflow changes |
| `ProductService` | Product CRUD logic | ✅ Single | ~175 | Product data rules |
| `PurchaseOrderService` | PO lifecycle (create/submit/cancel/recompute) | ✅ Single | ~150 | PO workflow changes |
| `ImageUploadService` | Image validation + WebP conversion | ✅ Single | ~140 | Upload/format rules |
| `WarehouseService` | Warehouse CRUD | ✅ Single | ~100 | Warehouse data rules |
| `SupplierService` | Supplier CRUD | ✅ Single | ~80 | Supplier data rules |
| `CustomerService` | Customer CRUD | ✅ Single | ~80 | Customer data rules |
| `UserService` | User CRUD + password | ✅ Single | ~120 | User management rules |
| `CategoryService` | Category CRUD | ✅ Single | ~100 | Category data rules |

---

## Controllers — Audit Table

| Class | Responsibilities | SRP Concern? |
|---|---|---|
| `BaseController` | HTTP rendering + auth + CSRF | ✅ Single — infrastructure concern |
| `AuthController` | Login form + session | ✅ Single |
| `DashboardController` | Role dispatch + render | ✅ Single |
| `ProductController` | Product HTTP + image handling | ✅ Single |
| `PurchaseOrderController` | PO HTTP + form | ⚠️ Handles both PO form AND goods receipt flow |
| `SalesOrderController` | SO HTTP + form | ⚠️ Handles SO HTTP + SO form + issue flow |
| `ReportController` | Report form + CSV export | ⚠️ Dual: form + download logic |
| `ProductApiController` | JSON API response | ✅ Single |
| `UserController` | User HTTP + form | ✅ Single |
| `WarehouseController` | Warehouse HTTP | ✅ Single |
| `SupplierController` | Supplier HTTP | ✅ Single |
| `CustomerController` | Customer HTTP | ✅ Single |

---

## Concerns: Controllers with Dual Responsibility

### `PurchaseOrderController`

**Issue:** `receiveForm()` and `receive()` handle goods receipt (which is logically part of the PO workflow but belongs to the Warehouse domain). This mixes:
1. PO management HTTP
2. Goods receipt HTTP

**Verdict:** Acceptable for MVP. In a larger system, goods receipt HTTP would be a separate `GoodsReceiptController`. The actual business logic lives in `GoodsReceiptService`, which is correctly isolated.

### `SalesOrderController`

**Issue:** `issueForm()` and `issue()` handle goods issue (warehouse domain concern).

**Verdict:** Same as PO — acceptable for this project scope. Business logic is in `GoodsIssueService`.

### `ReportController`

**Issue:** `showForm()` renders a form; `exportStockLedger()` and `exportOrders()` return raw CSV downloads. These are two different response types (HTML vs file download) mixed in one controller.

**Verdict:** Acceptable for MVP. A separate `ReportDownloadController` would be cleaner but adds routing complexity disproportionate to the benefit.

---

## Repository Classes — Audit

All repository classes are single-responsibility by design:
- `*MySQLRepository` — persistence to MySQL
- `*FakeRepository` — in-memory for testing
- Each implements exactly one `*RepositoryInterface`

No SRP violations found in the repository layer.

---

## Overall Verdict

**✅ The codebase is SRP-clean at the service layer.**

All service classes have a single, well-defined reason to change. Controller concerns (dual HTTP response types) are acceptable trade-offs for this project scope and do not violate the service layer SRP contract.

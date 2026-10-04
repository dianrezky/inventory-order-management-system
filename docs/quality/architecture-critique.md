# Architecture Critique

**Document:** `docs/quality/architecture-critique.md`
**Project:** Inventory & Order Management System
**Date:** 2026-09-01
**Stage:** Post-Implementation (Slice 6 Quality)

---

## Overall Architecture Assessment

The system follows a **Controller → Service → Repository** (3-layer) architecture with:
- **PHP 8.2+ Native** (no framework, no ORM)
- **MySQL 8** with PDO prepared statements
- **Docker Compose** for deployment
- **Pure vanilla JS/CSS** for frontend

**Verdict: Well-suited for the project scope.** The architecture correctly implements ADR-001 through ADR-004 without over-engineering for a single-developer 2-week project.

---

## What Worked Well

### 1. Controller → Service → Repository Separation

Each layer has a clear, single job:
- **Controller:** HTTP parsing + response — no business logic
- **Service:** Business logic + orchestration — no HTTP, no SQL
- **Repository:** Data access — no business logic, no HTTP

This made unit testing natural: `SalesOrderPolicyTest` uses only domain entities, no DB. `DashboardServiceTest` uses Fake repositories. Controllers are thin and easy to reason about.

### 2. Domain Policy Classes

`SalesOrderPolicy` is an excellent example: a single class that enforces BR-001 and BR-011/B-012 state transitions. Pure domain logic, fully unit-testable, no dependencies.

### 3. ARCH-002 Pessimistic Locking

`SELECT ... FOR UPDATE` inside a `Database::transaction()` callback is the right choice:
- Simple to understand and audit
- No external lock service needed
- MySQL InnoDB handles lock release correctly on commit/rollback
- Integration test `ARCH02ConcurrencyTest.php` proves it works

### 4. Two-Phase Validation

GoodsReceiptService validates stock availability BEFORE opening the transaction (fast fail) AND re-validates inside the transaction (defense-in-depth). This pattern is correct and the integration tests prove the rollback works.

### 5. BR-017 Server-Side Enforcement

Authorization is enforced in the Service layer (`SalesOrderPolicy`, `requireRole()`). UI hiding is explicitly labeled as "defense-in-depth only." This is the right priority order.

---

## What Could Be Improved

### 1. Lack of Value Object Pattern for Money

All prices (`purchase_price`, `sale_price`) are stored as `DECIMAL(15,2)` in MySQL and `string` in PHP. Arithmetic is done with native PHP float operations (`number_format((float) $price, 2)`) which can introduce rounding errors.

**Better:** A `Money` value object using `bcmath` functions:
```php
final readonly class Money {
    public function __construct(public string $amount, public string $currency = 'IDR') {}
    public function add(Money $other): Money { ... }
    public function multiply(int|float $factor): Money { ... }
}
```
This was deliberately excluded to keep scope manageable, but the current approach is acceptable for this project's scale.

### 2. No Domain Events

State transitions (e.g., SO approved, goods issued) fire no domain events. This means:
- No audit log outside the `stock_ledger`
- No async notifications (email on approval, etc.)
- No replay capability

For a 2-week project this is fine. For production: add an `EventDispatcher` + `DomainEvent` hierarchy.

### 3. Container is a Simple Service Locator

`Container.php` is a hand-written service locator with `getXxx()` factory methods. This works correctly but:
- No compile-time DI verification
- No lazy instantiation by default
- Constructor signatures not enforced by a framework

**Acceptable for this project.** A DI container framework (PHP-DI) was explicitly forbidden by the project constraints.

### 4. No CQRS

All reads and writes go through the same repositories. For a system of this scale, CQRS is overkill. However, if the system grows:
- Dashboard queries (aggregations) would benefit from read models/materialized views
- Write side (stock decrement) would stay as-is

### 5. Session-Based Auth — Redis-Backed

`AuthService` uses PHP sessions with Redis as the session save handler. For horizontal scaling with multiple containers, sessions are shared via Redis (already configured). No changes needed for multi-instance deployment.

### 6. No Request/Response DTOs

Controllers pass raw `$_GET`/`$_POST` arrays to services. This creates implicit coupling between HTTP and service layer.

**Better:** Explicit DTOs:
```php
final readonly class CreateSalesOrderRequest {
    public function __construct(
        public int $customerId,
        public int $warehouseId,
        public string $orderDate,
        public list<SalesOrderItemRequest> $items,
    ) {}
}
```

This was deliberately excluded to keep the project moving. Acceptable as technical debt.

### 7. `SalesOrderPolicy` Does Not Know About Roles

The policy only checks `actorId !== createdBy`. It does not know about `Role` or `isAdmin`. This is actually **correct** — it means the controller decides who can call approve() and passes the actor ID. The policy is purely about the segregation rule.

However, this means the policy can't enforce "only Admin can approve" if that were a requirement. The current design correctly implements the actual BR-001 rule.

---

## ADR Compliance Check

| ADR | Implemented? | Notes |
|---|---|---|
| ADR-001 (Architecture) | ✅ | Controller→Service→Repository |
| ADR-002 (Concurrency — ARCH-02) | ✅ | `SELECT FOR UPDATE` + transaction |
| ADR-003 (Transactions) | ✅ | All stock changes in transaction |
| ADR-004 (Stock Invariant) | ✅ | `product_stocks` + `stock_ledger` |

---

## Security Checklist

| Requirement | Implemented? | Evidence |
|---|---|---|
| FR-11.1 SQL Injection | ✅ | All queries use PDO prepared statements |
| FR-11.2 CSRF | ✅ | `_csrf_token` on all POST/PUT/DELETE |
| FR-11.3 XSS | ✅ | `htmlspecialchars()` on all output |
| FR-11.4 CSV Injection | ✅ | `CsvExportService` prefixes `=+-\t` with `'` |
| FR-11.5 File Upload | ✅ | `finfo_file()` + size check + WebP conversion |
| BR-017 AuthZ server-side | ✅ | `requireRole()`, `SalesOrderPolicy` |

---

## Final Verdict

**The architecture is appropriate for the project constraints and implements all mandatory requirements correctly.**

The main architectural trade-offs (no DI container, no value objects, session-based auth) are deliberate choices matching the "native PHP, no framework" constraint. The critical business invariants (ARCH-02 concurrency, BR-001 segregation, stock ledger invariant) are correctly implemented and tested.

The codebase is clean enough to demonstrate sound engineering practices and defend during technical assessment.

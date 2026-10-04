# Code Critique

**Document:** `docs/quality/critique.md`
**Project:** Inventory & Order Management System
**Brief reference:** §9 DESIGN-04
**Date:** 2026-09-15
**Purpose:** Critically examine a deliberately flawed code snippet; name smells, SOLID violations, and the refactoring direction. *Implementing the fix is not required.*


**Assessment provenance review (2026-10-04):** this document analyzes a hypothetical example, as its source label states. The original brief's DESIGN-04 requires a snippet supplied by the assessor. No evidence that this example is that supplied snippet has been found. Keep the analysis as practice material; replace or supplement it with the actual assessor snippet and attribution before claiming DESIGN-04 completion.

---

## Flawed Code Under Review

The following `SalesOrderService::create()` method was extracted from a hypothetical poorly-structured implementation:

```php
<?php
// Hypothetical flawed implementation — DO NOT USE
class SalesOrderService {

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): array {
        // --- HTTP & input parsing (Controller's job) ---
        $customerId = (int) ($data['customer_id'] ?? 0);
        $items      = json_decode($data['items'] ?? '[]', true);
        $userId     = (int) ($_SESSION['user_id'] ?? 0);   // direct superglobal access

        // --- Input validation (Service's job — borderline OK, but...) ---
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Invalid customer');
        }
        if (count($items) === 0) {
            throw new InvalidArgumentException('No items');
        }
        foreach ($items as $item) {
            if (($item['qty'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Invalid qty');
            }
        }

        // --- Stock sufficiency check (correct business logic) ---
        $stmt = $this->db->prepare(
            'SELECT ps.product_id, ps.warehouse_id, ps.quantity
             FROM product_stocks ps
             JOIN products p ON p.id = ps.product_id
             WHERE p.sku = :sku AND ps.warehouse_id = :wid'
        );
        foreach ($items as $item) {
            $stmt->execute(['sku' => $item['sku'], 'wid' => $data['warehouse_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['quantity'] < $item['qty']) {
                throw new InvalidArgumentException('Insufficient stock');
            }
        }

        // --- Persistence (Repository's job — VIOLATION of ARCH-01) ---
        $this->db->beginTransaction();
        $stmt = $this->db->prepare(
            'INSERT INTO sales_orders (customer_id, created_by, status, created_at)
             VALUES (:cid, :uid, :status, NOW())'
        );
        $stmt->execute([
            'cid'    => $customerId,
            'uid'    => $userId,
            'status' => 'Draft',
        ]);
        $orderId = (int) $this->db->lastInsertId();

        $stmtItem = $this->db->prepare(
            'INSERT INTO sales_order_items (order_id, product_id, qty, unit_price)
             VALUES (:oid, :pid, :qty, :price)'
        );
        foreach ($items as $item) {
            $stmtItem->execute([
                'oid'   => $orderId,
                'pid'   => $item['product_id'],
                'qty'   => $item['qty'],
                'price' => $item['unit_price'],
            ]);
        }

        // --- Notification side-effect (cross-cutting concern) ---
        $stmtCustomer = $this->db->prepare('SELECT email FROM customers WHERE id = :cid');
        $stmtCustomer->execute(['cid' => $customerId]);
        $customer = $stmtCustomer->fetch(PDO::FETCH_ASSOC);

        $stmtUser = $this->db->prepare('SELECT email FROM users WHERE id = :uid');
        $stmtUser->execute(['uid' => $userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Directly composing and sending email — no abstraction
        $subject = 'Order #' . $orderId . ' Created';
        $body    = "Your order has been created.\n";
        $headers = 'From: system@example.com';
        mail($customer['email'], $subject, $body, $headers);   // blocking I/O
        mail($user['email'],     $subject, $body, $headers);    // second blocking call

        // --- Audit log persistence ---
        $stmtLog = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, created_at)
             VALUES (:uid, :act, :ent, :eid, NOW())'
        );
        $stmtLog->execute([
            'uid' => $userId,
            'act' => 'create_sales_order',
            'ent' => 'sales_orders',
            'eid' => $orderId,
        ]);

        $this->db->commit();

        // --- View/data shaping (Controller's job) ---
        return [
            'id'         => $orderId,
            'status'     => 'Draft',
            'customer'   => $customer['email'],
            'items'      => count($items),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
```

---

## Critique

### Smells Identified

| # | Smell | Location | Severity |
|---|-------|----------|----------|
| 1 | **God Method** | Entire `create()` — 90+ lines | Critical |
| 2 | **Direct Use of Superglobal** | `$_SESSION['user_id']` directly in service | High |
| 3 | **Direct PDO Instantiation** | `new PDO()` in constructor — violates ARCH-01 | Critical |
| 4 | **SQL in Service Layer** | 5 separate `prepare()`/`execute()` calls in service | High |
| 5 | **Side Effect: Email Sending** | Two `mail()` calls inside transactional logic | High |
| 6 | **Side Effect: Audit Log** | Audit insert inside the same transaction as the order | Medium |
| 7 | **Duplicated Query Logic** | `customers` and `users` fetched with identical query pattern | Low |
| 8 | **Hardcoded Magic Values** | `'Draft'`, `'create_sales_order'`, `'sales_orders'` are string literals | Low |
| 9 | **Blocking I/O in Transaction** | `mail()` called inside `beginTransaction()`/`commit()` | Critical |
| 10 | **No Constructor Injection** | Dependencies passed as raw `PDO`, not abstractions | High |

---

### SOLID Principles Violated

**S — Single Responsibility Principle (VIOLATED)**
The `create()` method does at least six distinct jobs:
1. Parses raw HTTP input (`$_SESSION`, `$data['items']`)
2. Validates input
3. Checks stock availability
4. Persists the order and its items
5. Sends notification emails
6. Writes an audit log entry

A method that does six things is a "God Method." Each job should be its own class or at minimum its own method with a single reason to change.

**O — Open/Closed Principle (VIOLATED)**
To add a new notification channel (SMS, push notification, Slack), the `create()` method must be modified directly. The email logic is hardcoded.

**L — Liskov Substitution Principle (NOT DIRECTLY VIOLATED)**
No inheritance hierarchy is present, so LSP cannot be evaluated. If a `NotificationService` interface were introduced, LSP would apply.

**I — Interface Segregation Principle (NOT DIRECTLY VIOLATED, but implied)**
The service depends on `PDO` directly, which has many methods. A narrow `OrderPersistenceInterface` would be better.

**D — Dependency Inversion Principle (VIOLATED)**
`SalesOrderService` depends on the concrete `PDO` class, not on an abstraction. Per project brief ARCH-01: *"no `new PDO()` inside a Service"* — this pattern would fail that rule. The service should depend on `SalesOrderRepositoryInterface`, not `PDO`.

---

### Additional Design Problems

**Transactional boundary is wrong.** `mail()` is a blocking, potentially slow I/O operation that executes *inside* the database transaction. If the mail server is unreachable, the transaction is held open longer than necessary, and if it fails mid-way, the rollback logic is not in place for the email step. Email (and audit logging) should happen *after* `commit()`, or use an outbox pattern / event queue.

**The `mail()` function is untestable.** Calling `mail()` directly means it fires on every test run. There is no way to test the business logic without an actual mail server. Email dispatch should go through a `MailerInterface`.

**Audit log inside the transaction couples two unrelated concerns.** If the audit write fails, the entire order creation fails. Audit log is infrastructure; it should be decoupled (e.g., via domain events).

**Stock check and order creation are not atomic from the application's perspective.** The stock check happens outside the transaction, meaning another request could consume the stock between the check and the insert. The current code mitigates this partially because it opens a transaction only for the insert, but the check-and-decrement should both be inside `SELECT … FOR UPDATE` + transaction (as implemented in the actual `GoodsIssueService`).

---

### Refactoring Direction

The method should be decomposed into at least three responsibilities:

1. **Extract a `SalesOrderRepositoryInterface`** — handles `INSERT INTO sales_orders` and `sales_order_items`. Service never calls `PDO` directly.
2. **Extract a `StockCheckerService`** — runs the availability query. Used by the service to decide whether to proceed.
3. **Extract a `NotificationService` (interface) + `PhpMailerAdapter`** — handles email dispatch. Called *after* the transaction commits. Test double replaces it in unit tests.
4. **Extract an `AuditLogger`** — fires a domain event (`SalesOrderCreated`) after commit. Notification and audit both subscribe to this event.

The refactored `SalesOrderService::create()` would then look approximately like:

```php
// Refactored direction (not implemented — per DESIGN-04 requirement)
public function create(CreateSalesOrderRequest $request, int $actorId): SalesOrder
{
    $this->stockChecker->assertAllAvailable($request->items, $request->warehouseId);

    $order = SalesOrder::create($request->customerId, $actorId, $request->warehouseId, $request->items);
    $this->orders->save($order);            // transaction inside repository
    $this->eventDispatcher->dispatch(new SalesOrderCreated($order));
    // email + audit handled by event listeners, outside the transaction
    return $order;
}
```

This satisfies:
- SRP: each class has one reason to change
- OCP: new notification channels via new `Transport` implementations
- DIP: service depends on `RepositoryInterface`, `StockCheckerInterface`, `EventDispatcherInterface`
- Testable: fake implementations of all interfaces; `mail()` never actually fires in tests

---

### Verdict

The flawed snippet is a textbook example of a **God Method** that mixes infrastructure (`PDO`, `mail()`), domain logic, and presentation concerns into one 90-line function. Its primary failure is **violating ARCH-01** (direct PDO in service) and **violating SRP** (six jobs in one method). The corrective path is straightforward: separate the three layers, introduce interfaces, and defer side effects (email, audit) to post-commit event handlers.

The actual codebase correctly implements this refactoring: `SalesOrderService` depends on repository interfaces, stock operations are wrapped in `SELECT FOR UPDATE` transactions, and email is handled through a `NotificationService` abstraction.

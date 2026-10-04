# Sequence Diagram — Goods Receipt & Goods Issue (Transaksional)

- **Status:** Initial — to be verified against implementation in Stage 6
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related:** PRD §6 PO-01, §6 SO-01, §9 ARCH-02, ADR-002

---

## 1. Goods Receipt (PO → Stock)

### Skenario Normal
PO status `Ordered` atau `PartiallyReceived`, Warehouse Staff memproses receipt barang: input qty received per line.

```mermaid
sequenceDiagram
    autonumber
    participant U as Wawan (Browser)
    participant Ctl as PurchaseOrderController
    participant Svc as GoodsReceiptService
    participant PoRepo as PurchaseOrderRepository
    participant StkRepo as ProductStockRepository
    participant LdgRepo as StockLedgerRepository
    participant DB as MySQL InnoDB

    U->>Ctl: POST /purchase-orders/{id}/receipt {qty per item}
    Ctl->>Ctl: BaseController::requireRole(Warehouse|Admin)
    Ctl->>Svc: process(poId, receipts, actorUserId)
    activate Svc

    Svc->>DB: BEGIN TRANSACTION
    Svc->>PoRepo: findByIdForUpdate(poId)
    PoRepo->>DB: SELECT * FROM purchase_orders WHERE id=? FOR UPDATE
    DB-->>PoRepo: row (status=Ordered)
    PoRepo-->>Svc: PurchaseOrder
    Note over Svc: if (!$purchaseOrder->canReceiveGoods()) throw InvalidStateException
    Svc->>Svc: assert qty_received + receipts[i] <= qty_ordered

    loop For each PO item with receipts[i] > 0
        Svc->>StkRepo: lockForUpdate(productId, warehouseId)
        StkRepo->>DB: SELECT quantity FROM product_stocks<br/>WHERE product_id=? AND warehouse_id=?<br/>FOR UPDATE
        DB-->>StkRepo: row { quantity: N }
        StkRepo-->>Svc: ProductStock

        Svc->>Svc: assert qty_received + receipts[i] <= qty_ordered

        Svc->>StkRepo: increment(productId, warehouseId, receipts[i])
        StkRepo->>DB: UPDATE product_stocks SET quantity=quantity+? WHERE product_id=? AND warehouse_id=?

        Svc->>LdgRepo: insert(Receipt, qty, ref=PO/poId, doneBy=actor)
        LdgRepo->>DB: INSERT INTO stock_ledger (type='Receipt', qty=+N, ref_type='PO', ref_id=po_id, ...)

        Svc->>PoRepo: updatePoItemQtyReceived(poItemId, qty_received)
        PoRepo->>DB: UPDATE purchase_order_items SET qty_received=qty_received+? WHERE id=?
    end

    Svc->>Svc: recomputePoStatus(po) → Ordered|PartiallyReceived|Received
    Svc->>PoRepo: updateStatus(poId, newStatus)
    PoRepo->>DB: UPDATE purchase_orders SET status=? WHERE id=?

    Svc->>DB: COMMIT
    deactivate Svc
    Svc-->>Ctl: void (success)
    Ctl-->>U: 302 Redirect to /purchase-orders/{id} (flash: success)
```

### Alur Error — Insufficient Quota (qty > qty_ordered − qty_received)

```mermaid
sequenceDiagram
    autonumber
    participant Svc as GoodsReceiptService
    participant DB as MySQL InnoDB

    Svc->>DB: BEGIN TRANSACTION
    Note over Svc: ... lock PO & validate qty ...
    Svc->>Svc: assert  qty_input <= qty_ordered - qty_received ❌
    Svc->>Svc: throw QuantityExceedsOrderedException
    Svc->>DB: ROLLBACK
    Note over DB: All locks released. Stock + ledger + po_items unchanged.
    Svc-->>Svc: exception propagates to Controller
```

### Alur Error — Random DB Exception mid-transaction

```mermaid
sequenceDiagram
    autonumber
    participant Svc as GoodsReceiptService
    participant DB as MySQL InnoDB

    Svc->>DB: BEGIN TRANSACTION
    Note over Svc: stock locked + step 1 (UPDATE product_stocks) success
    Svc->>DB: INSERT INTO stock_ledger ...
    DB-->>Svc: ❌ Duplicate key error (simulated)
    Svc->>DB: ROLLBACK
    Note over DB: product_stocks UPDATE already reverted. Ledger row not present.
    Note over DB: ✅ BR-008 satisfied (all-or-nothing).
```

---

## 2. Goods Issue (SO → Stock Out)

### Skenario Normal — Stok Cukup

```mermaid
sequenceDiagram
    autonumber
    participant U as Wawan (Browser)
    participant Ctl as SalesOrderController
    participant Svc as GoodsIssueService
    participant Pol as SalesOrderPolicy
    participant SoRepo as SalesOrderRepository
    participant StkRepo as ProductStockRepository
    participant LdgRepo as StockLedgerRepository
    participant DB as MySQL InnoDB

    U->>Ctl: POST /sales-orders/{id}/issue
    Ctl->>Ctl: requireRole(Warehouse|Admin)
    Ctl->>Svc: issue(soId, actorUserId)
    activate Svc

    Svc->>DB: BEGIN TRANSACTION

    Svc->>SoRepo: findByIdForUpdate(soId)
    SoRepo->>DB: SELECT * FROM sales_orders WHERE id=? FOR UPDATE
    DB-->>SoRepo: status=Approved
    SoRepo-->>Svc: SalesOrder

    Svc->>Pol: assertCanIssue(so) → 200 OK if status=Approved
    Pol-->>Svc: ✓

    loop For each SO item
        Svc->>StkRepo: lockForUpdate(productId, sourceWarehouseId)
        StkRepo->>DB: SELECT quantity FROM product_stocks<br/>WHERE product_id=? AND warehouse_id=?<br/>FOR UPDATE
        DB-->>StkRepo: { quantity: N }
        StkRepo-->>Svc: ProductStock

        alt Svc: quantity >= item.qty
            Svc->>StkRepo: decrement(productId, sourceWarehouseId, item.qty)
            StkRepo->>DB: UPDATE product_stocks SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?
            Svc->>LdgRepo: insert(Issue, qty=-N, ref=SO/soId, doneBy=actor)
            LdgRepo->>DB: INSERT INTO stock_ledger (type='Issue', qty=-N, ref_type='SO', ref_id=so_id, ...)
        else quantity < item.qty
            Svc->>Svc: throw InsufficientStockException("Stok tidak mencukupi untuk Product X")
            Svc->>DB: ROLLBACK
            Svc-->>Ctl: exception
            Ctl-->>U: 400 Bad Request (flash error)
            Note over DB: All changes reverted. SO status remains Approved.
        end
    end

    Svc->>SoRepo: updateStatus(soId, Fulfilled, {issued_by, issued_at})
    SoRepo->>DB: UPDATE sales_orders SET status='Fulfilled', issued_by=?, issued_at=NOW() WHERE id=?
    Svc->>DB: COMMIT

    deactivate Svc
    Svc-->>Ctl: void (success)
    Ctl-->>U: 302 Redirect to /sales-orders/{id}
```

### Skenario CRITICAL — ARCH-02 Concurrent Goods Issue (BR-010)

Dua request Goods Issue simultan untuk product+warehouse yang sama. Stok = 10. Req A qty=7, Req B qty=5. Salah satu harus sukses, yang lain ditolak.

```mermaid
sequenceDiagram
    autonumber
    participant UA as User A Thread
    participant UB as User B Thread
    participant SvcA as GoodsIssueService A
    participant SvcB as GoodsIssueService B
    participant StkRepoA as StockRepo A
    participant StkRepoB as StockRepo B
    participant DB as MySQL InnoDB (single instance)

    par Concurrent Request
        UA->>SvcA: issue(reqSoA, qty=7)
        UA->>SvcB: ... (parallel sibling)
    and
        UA->>SvcB: issue(reqSoB, qty=5)
    end

    Note over DB: 🟢 T0 — both BEGIN TRANSACTION start
    SvcA->>DB: BEGIN
    SvcB->>DB: BEGIN

    Note over DB: 🟢 T1 — both attempt lock on same row
    SvcA->>StkRepoA: lockForUpdate(productId, whId)
    StkRepoA->>DB: SELECT ... FOR UPDATE
    DB-->>StkRepoA: ✓ Lock acquired (Thread A)<br/>row { quantity: 10 }

    SvcB->>StkRepoB: lockForUpdate(productId, whId)
    StkRepoB->>DB: SELECT ... FOR UPDATE
    Note over DB: 🔴 T2 — Thread B BLOCKED waiting for Thread A's lock
    DB--xStkRepoB: ⏳ waiting...

    Note over SvcA: T3 — Thread A: 10 >= 7 ✓
    SvcA->>StkRepoA: decrement(productId, whId, 7)
    StkRepoA->>DB: UPDATE product_stocks SET quantity=3 WHERE ...
    SvcA->>DB: INSERT stock_ledger type=Issue qty=-7
    SvcA->>DB: UPDATE sales_orders SET status='Fulfilled' WHERE ...
    SvcA->>DB: COMMIT
    Note over DB: 🟢 T4 — Lock released
    DB-->>SvcA: ✓ success
    SvcA-->>UA: HTTP 200 success

    Note over DB: 🟢 T5 — Thread B lock granted now
    DB-->>StkRepoB: row { quantity: 3 } (current after A's update)
    Note over SvcB: T6 — 3 < 5 ✗ FAIL
    SvcB->>SvcB: throw InsufficientStockException("Stok tidak mencukupi")
    SvcB->>DB: ROLLBACK
    DB-->>SvcB: ✓ rolled back cleanly
    SvcB-->>UB: HTTP 400 "Stok tidak mencukupi" (or A's req returned 200 if order reversed)

    Note over DB: 🟢 T7 — Final state: stock=3, ledger has 1 Issue(-7) entry, 1 SO Fulfilled, 1 SO still Approved

    rect rgb(220, 252, 231)
        Note over DB: ✅ INVARIANT PRESERVED:<br/>product_stocks.quantity = 3 (not -2)<br/>stock_ledger consistent with stock<br/>no oversell
    end
```

**Catatan urutan (yang mana duluan sukses):**
- Bisa A dulu baru B (skenario di atas), atau B dulu baru A.
- Hasil akhir: stock = 3 (kalau A sukses) atau stock = 5 (kalau B sukses).
- TIDAK PERNAH kedua sukses.
- Yang ditolak return HTTP 400 dengan pesan jelas "Stok tidak mencukupi".

### Skenario Approval — Segregation of Duties (BR-001)

```mermaid
sequenceDiagram
    autonumber
    participant Beni as Beni (Sales)
    participant API as SalesOrderController
    participant Svc as SalesOrderService
    participant Pol as SalesOrderPolicy
    participant SoRepo as SalesOrderRepository
    participant DB as MySQL

    Note over Beni: Beni membuat SO #42, status Draft → PendingApproval

    Beni->>API: POST /sales-orders/42/approve<br/>(manipulasi request langsung)
    API->>Svc: approve(soId=42, actorId=Beni.id, isActorAdmin=false)
    Svc->>SoRepo: findById(42) → { created_by: Beni.id, status: PendingApproval }
    Svc->>Pol: assertCanDecide(isActorAdmin=false)

    Note over Pol: Beni is NOT Admin → BR-SOD-02 VIOLATION
    Pol-->>Svc: throw SalesApprovalForbiddenException
    Svc-->>API: exception
    API-->>Beni: HTTP 403 Forbidden "Only an administrator can approve a sales order."
```

---

## 3. Transaksi Lifecycle Checklist

Per transaksi multi-tabel, selalu confirm:

| Step | Goods Receipt | Goods Issue |
|------|---------------|-------------|
| 1. Lock parent doc | `SELECT * FROM purchase_orders WHERE id=? FOR UPDATE` | `SELECT * FROM sales_orders WHERE id=? FOR UPDATE` |
| 2. Assert state | status in ['Ordered', 'PartiallyReceived'] | status = 'Approved' |
| 3. Lock + read stock per line | `SELECT quantity FROM product_stocks WHERE ... FOR UPDATE` | `SELECT quantity FROM product_stocks WHERE ... FOR UPDATE` |
| 4. Check quota (for issue only) | n/a | if quantity < item.qty → throw + ROLLBACK |
| 5. Mutate stock | UPDATE product_stocks SET quantity = quantity + N | UPDATE product_stocks SET quantity = quantity - N |
| 6. Write ledger | INSERT stock_ledger (type='Receipt', qty=+N) | INSERT stock_ledger (type='Issue', qty=-N) |
| 7. Update line | UPDATE po_items SET qty_received += N | (no qty on SO items) |
| 8. Update parent status | recompute status | UPDATE sales_orders SET status='Fulfilled' |
| 9. Commit | COMMIT | COMMIT |

Setiap langkah 3-7 terjadi **dalam transaksi yang sama**. Jika ada exception, SEMUA rollback.

---

## 4. Lock Duration Budget

Estimasi waktu kritis antara `BEGIN` dan `COMMIT`:

| Operation | Steps | Expected Duration |
|-----------|-------|-------------------|
| Goods Receipt, 10 line items | 10 × (1 SELECT + 1 UPDATE + 1 INSERT + 1 UPDATE) = 40 query | < 100 ms |
| Goods Issue, 5 line items | 5 × (1 SELECT + 1 UPDATE + 1 INSERT) + 1 UPDATE parent = 16 query | < 50 ms |
| Approval only (no stock op) | 1 SELECT + 1 UPDATE | < 10 ms |

**Throughput:** Single user lock hold < 100 ms = aman untuk 10-50 req/sec per entity+warehouse pair (lebih dari cukup untuk scope mid-warehouse).

**Tidak ada long-running lock.**

---

## 5. Failure Modes & Recovery

| Failure | Mode | Recovery |
|---------|------|----------|
| DB connection lost mid-transaction | InnoDB rollback otomatis | Client sees exception; retry safe (idempotent check via service) |
| Network timeout to PHP | Depends on driver | Default `PDO::ATTR_TIMEOUT` ensures PHP detects |
| Application exception in service | `try/catch` → manual ROLLBACK | Application-level recovery |
| Deadlock detected by InnoDB | InnoDB rolls back loser | Application should retry (rare with our query pattern) |
| Power outage | InnoDB recovery on restart | WAL log applies pending; rolled back txns cleaned |

> Idempotency untuk Goods Issue: client retry dengan SO id yang sama, service cek status — kalau sudah Fulfilled, return success tanpa reprocess.

---

## References
- `docs/planning/prd.md` §6 PO-01 FR-6.8, §6 SO-01 FR-7.10, §9 ARCH-02
- `docs/architecture/adr-002-concurrency-strategy.md`
- `docs/architecture/db-schema-design.md` (lock-related fields)
- MySQL InnoDB locking: <https://dev.mysql.com/doc/refman/8.0/en/innodb-locking.html>

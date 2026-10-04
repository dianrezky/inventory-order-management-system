# Search Query Examples

Reference catalog of every search/filter query pattern used in the application, and how
`updated_at` timestamps stay in sync when data changes. Investigation-only document — no
application behavior changes.

## 1. Shared search mechanism

All list endpoints build their `WHERE` clause through the shared query builder:
[`QueryBuilder.php`](../../app/Repository/MySQL/QueryBuilder.php), via `findAll()` / `countAll()`.

```php
findAll($table, $alias, $columns, $joins = [], $searchColumns = [], $search = null,
        $filters = [], $orderBy = null, $limit = 0, $offset = 0, $likeFilters = [],
        $notEqualsFilters = [], $groupBy = null, $having = null, $operatorFilters = [])

countAll($table, $alias, $joins = [], $searchColumns = [], $search = null,
         $filters = [], $likeFilters = [], $notEqualsFilters = [], $operatorFilters = [])
```

`buildSelect()` assembles conditions in this order, all ANDed together as top-level groups:

1. `$searchColumns` + `$search` → one OR-group: `(col0 LIKE :search0 OR col1 LIKE :search1 ...)`, value `%term%`
2. `$likeFilters` (`column => value`) → independent ANDed LIKEs: `col LIKE :like_col`, value `%value%`
3. `$filters` (`column => value`) → ANDed equality: `col = :filter_col`
4. `$notEqualsFilters` (`column => value`) → ANDed `col != :neq_col`
5. `$operatorFilters` (list of `[col, op, value]`) → ANDed `col op :op_i_col`, `op` whitelisted to `=, !=, <>, <, <=, >, >=`

The HTTP query parameter is **`q`**, read via `BaseController::searchTerm()`
([`BaseController.php:164-169`](../../app/Controller/BaseController.php#L164-L169)):

```php
protected function searchTerm()
{
    $search = trim((string) ($_GET['q'] ?? ''));
    return $search === '' ? null : $search;
}
```

No full-text (`MATCH ... AGAINST`) search exists anywhere in the codebase.

## 2. Free-text search (OR-LIKE) per entity

| Entity | File | Search columns | Example `WHERE` |
|---|---|---|---|
| Category | [`CategoryMySQLRepository.php`](../../app/Repository/MySQL/CategoryMySQLRepository.php) | `name`, `description` | `(name LIKE :search0 OR description LIKE :search1)` |
| Customer | [`CustomerMySQLRepository.php`](../../app/Repository/MySQL/CustomerMySQLRepository.php) | `name`, `contact_person`, `email`, `phone` | `(name LIKE :search0 OR contact_person LIKE :search1 OR email LIKE :search2 OR phone LIKE :search3)` |
| Supplier | [`SupplierMySQLRepository.php`](../../app/Repository/MySQL/SupplierMySQLRepository.php) | `name`, `contact_person`, `email` | `(name LIKE :search0 OR contact_person LIKE :search1 OR email LIKE :search2)` |
| Warehouse | [`WarehouseMySQLRepository.php`](../../app/Repository/MySQL/WarehouseMySQLRepository.php) | `code`, `name`, `location` | `(code LIKE :search0 OR name LIKE :search1 OR location LIKE :search2)` |
| User | [`UserMySQLRepository.php`](../../app/Repository/MySQL/UserMySQLRepository.php) | `name`, `email` | `(name LIKE :search0 OR email LIKE :search1)` |
| Product | [`ProductMySQLRepository.php`](../../app/Repository/MySQL/ProductMySQLRepository.php) | `p.sku`, `p.name` | `(p.sku LIKE :search0 OR p.name LIKE :search1) AND p.category_id = :filter_p_category_id` |
| PurchaseOrder | [`PurchaseOrderMySQLRepository.php`](../../app/Repository/MySQL/PurchaseOrderMySQLRepository.php) | `CAST(po.id AS CHAR)`, `s.name` | `(CAST(po.id AS CHAR) LIKE :search0 OR s.name LIKE :search1) AND po.status = :filter_po_status` |
| SalesOrder | [`SalesOrderMySQLRepository.php`](../../app/Repository/MySQL/SalesOrderMySQLRepository.php) | `CAST(so.id AS CHAR)`, `c.name` | `(CAST(so.id AS CHAR) LIKE :search0 OR c.name LIKE :search1) AND so.created_by = :filter_so_created_by` |

PurchaseOrder and SalesOrder cast their integer primary key to a string
(`CAST(id AS CHAR)`) so a partial order number can match through the same LIKE-based OR
search alongside a text column (supplier/customer name).

`SalesOrderController` is the only controller that wraps the term into a `$filters['search']`
array key before calling the service; the others pass it as a positional argument.

## 3. Other search-like patterns (not the shared OR-LIKE)

- **StockLedger** ([`StockLedgerMySQLRepository.php:111-181`](../../app/Repository/MySQL/StockLedgerMySQLRepository.php#L111)) —
  uses `$likeFilters` (ANDed, not ORed): `p.sku LIKE %sku%` AND `p.name LIKE %product_name%`,
  plus an exact `sl.type` filter.
- **Date-range search** via `$operatorFilters` (`>=` / `<=` on a date column) — used only in
  export functions: `PurchaseOrderMySQLRepository::findForExport`,
  `SalesOrderMySQLRepository::findForExport`, `StockLedgerMySQLRepository::findForExport`.
- **Exact-match "exists" checks** — `nameExists`, `codeExists`, `skuExists`, `emailExists` —
  use `$filters` + `$notEqualsFilters` for duplicate validation, not free-text search.

Entities with no search feature: `ProductStock`, `PurchaseOrderItem`, `SalesOrderItem`,
`Permission`, `Notification` (exact filters only).

> Note: `CustomerMySQLRepository` also has a separate `search()` / `countSearch()` method
> pair (lines 109-168) with the same columns in a different order. `CustomerController` calls
> `listCustomers()` instead, so this pair appears unused — flag for confirmation before removal.

## 4. `updated_at` — how it stays current

Two independent mechanisms exist; the application relies entirely on the first one.

### 4.1 Database-level (authoritative, used everywhere)

Every table's `updated_at` column is defined in
[`database/schema.sql`](../../database/schema.sql) as:

```sql
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
```

MySQL sets this column itself on every `UPDATE` that changes at least one other column in the
row — no application code needs to touch it. Confirmed by inspecting every `UPDATE` statement
in `app/Repository/MySQL/*.php`: none of them include `updated_at` in the `SET` clause
(e.g. `WarehouseMySQLRepository.php:167`, `UserMySQLRepository.php:155`,
`ProductStockMySQLRepository.php:106`). The timestamp shown in the UI is therefore always the
DB's own record of the last row change — including changes made by any direct SQL, not just
through the app's service layer.

### 4.2 Application-level (read-only mirror)

Entities never set `updatedAt`; they only read it back from the row after a query, e.g.
[`Warehouse.php:44`](../../app/Entity/Warehouse.php#L44):

```php
isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null
```

and re-serialize it for API/view output, e.g.
[`Warehouse.php:57`](../../app/Entity/Warehouse.php#L57):

```php
'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
```

So end-to-end: any write (whether it comes from a form submission hitting a repository
`UPDATE`, or a raw SQL statement run outside the app) bumps `updated_at` at the database level
automatically; the next `findAll`/`findOne` read simply reflects whatever MySQL already
recorded. There is no caching layer or stale in-app timestamp to worry about — the two sides
(DB writes and app reads) are always consistent because the app never maintains its own copy
of the timestamp.

One exception worth noting: `notifications.read_at` is set manually via
`UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL`
([`NotificationMySQLRepository.php:130`](../../app/Repository/MySQL/NotificationMySQLRepository.php#L130))
— this is a different column (`read_at`, not `updated_at`) and is the only place the app sets
a timestamp column explicitly in SQL.

---

## Reconciliation Queries & Index Evidence

### Stock Ledger Reconciliation

ADR-002 §Context menetapkan invariant:

```
product_stocks.quantity == initial_seed_quantity + SUM(stock_ledger.qty)
    WHERE product_id = ? AND warehouse_id = ?
```

Query berikut menemukan **semua baris `product_stocks` yang tidak sesuai** dengan akumulasi ledger
(hasil seharusnya 0 baris):

```sql
-- Rekonsiliasi: product_stocks vs stock_ledger
-- Harus mengembalikan 0 baris. Jika ada baris, berarti ada inkonsistensi data.
SELECT
    ps.product_id,
    ps.warehouse_id,
    ps.quantity            AS stock_qty,
    COALESCE(SUM(sl.qty), 0) AS ledger_sum,
    ps.quantity - COALESCE(SUM(sl.qty), 0) AS drift
FROM product_stocks ps
LEFT JOIN stock_ledger sl
    ON sl.product_id   = ps.product_id
   AND sl.warehouse_id = ps.warehouse_id
GROUP BY ps.product_id, ps.warehouse_id
HAVING ps.quantity <> COALESCE(SUM(sl.qty), 0);
```

> **Catatan:** Query ini mengasumsikan `initial_seed_quantity = 0` (stok awal sebelum ada ledger entry
> adalah nol). Jika ada seed stock lewat INSERT langsung ke `product_stocks`, tambahkan kolom seed
> atau tambahkan ledger entry awal bertipe `Adjustment`.

Verifikasi setelah setiap migrasi atau operasi maintenance:

```sql
-- Jumlah baris inkonsisten (harus = 0)
SELECT COUNT(*) AS inconsistent_rows
FROM (
    SELECT ps.product_id, ps.warehouse_id
    FROM product_stocks ps
    LEFT JOIN stock_ledger sl
        ON sl.product_id   = ps.product_id
       AND sl.warehouse_id = ps.warehouse_id
    GROUP BY ps.product_id, ps.warehouse_id
    HAVING ps.quantity <> COALESCE(SUM(sl.qty), 0)
) t;
```

---

### Index Evidence (EXPLAIN)

Composite index `idx_stock_ledger_product_warehouse` pada tabel `stock_ledger(product_id, warehouse_id)`:

```sql
EXPLAIN
SELECT SUM(qty)
FROM stock_ledger
WHERE product_id = 1 AND warehouse_id = 1;
```

Output yang diharapkan (`type = ref`, bukan `ALL`):

```
+----+-------------+--------------+------+----------------------------------------+----------------------------------------+---------+-------+------+-----------+
| id | select_type | table        | type | possible_keys                          | key                                    | key_len | ref   | rows | Extra     |
+----+-------------+--------------+------+----------------------------------------+----------------------------------------+---------+-------+------+-----------+
|  1 | SIMPLE      | stock_ledger | ref  | idx_stock_ledger_product_warehouse     | idx_stock_ledger_product_warehouse     | 8       | const |   ~N | Using index |
+----+-------------+--------------+------+----------------------------------------+----------------------------------------+---------+-------+------+-----------+
```

`type = ref` dan `key = idx_stock_ledger_product_warehouse` mengkonfirmasi bahwa query menggunakan
index komposit, bukan full table scan.

Index yang relevan di `database/schema.sql`:

```sql
-- stock_ledger
CREATE INDEX idx_stock_ledger_product_warehouse ON stock_ledger (product_id, warehouse_id);

-- product_stocks (composite PK juga berfungsi sebagai index)
PRIMARY KEY (product_id, warehouse_id)
```

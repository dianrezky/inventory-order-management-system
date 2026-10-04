-- ============================================================================
-- Inventory & Order Management System — Full Schema
-- Source of truth: docs/architecture/db-schema-design.md
-- Engine: MySQL 8.0+, InnoDB, utf8mb4/utf8mb4_unicode_ci
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ----------------------------------------------------------------------------
-- 1. users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)    NOT NULL,
    email         VARCHAR(255)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('Admin','Sales','WarehouseStaff') NOT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_by    BIGINT UNSIGNED NULL,
    updated_by    BIGINT UNSIGNED NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_active (is_active),
    KEY idx_users_name (name),
    KEY idx_users_created_by (created_by),
    KEY idx_users_updated_by (updated_by),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. categories
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)     NOT NULL,
    name        VARCHAR(100)    NOT NULL,
    description VARCHAR(500)    NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_by  BIGINT UNSIGNED NULL,
    updated_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_categories_name (name),
    UNIQUE KEY uniq_categories_code (code),
    KEY idx_categories_active (is_active),
    KEY idx_categories_created_by (created_by),
    KEY idx_categories_updated_by (updated_by),
    CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. products
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku             VARCHAR(50)     NOT NULL,
    barcode         VARCHAR(50)     NULL,
    name            VARCHAR(150)    NOT NULL,
    description     VARCHAR(500)    NULL,
    category_id     BIGINT UNSIGNED NOT NULL,
    unit            VARCHAR(20)     NOT NULL,
    purchase_price  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    sale_price      DECIMAL(15,2)   NOT NULL DEFAULT 0,
    reorder_point   INT UNSIGNED    NOT NULL DEFAULT 0,
    image_path      VARCHAR(255)    NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_products_sku (sku),
    KEY idx_products_barcode (barcode),
    KEY idx_products_category (category_id),
    KEY idx_products_active (is_active),
    -- SORT INDEXING: every product list/search sorts by name ASC
    -- (ProductMySQLRepository::findAll/findAllActive/countAll); also the
    -- sort target in StockLedgerMySQLRepository::findFiltered().
    KEY idx_products_name (name),
    KEY idx_products_created_by (created_by),
    KEY idx_products_updated_by (updated_by),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_products_purchase_price CHECK (purchase_price >= 0),
    CONSTRAINT chk_products_sale_price CHECK (sale_price >= 0),
    CONSTRAINT chk_products_reorder_point CHECK (reorder_point >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. warehouses
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS warehouses (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(20)     NOT NULL,
    name       VARCHAR(100)    NOT NULL,
    location   VARCHAR(255)    NULL,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_warehouses_code (code),
    KEY idx_warehouses_active (is_active),
    -- SORT INDEXING: `w.name` is a selectable sort column in
    -- StockLedgerMySQLRepository::findFiltered() (sort_col=warehouse).
    KEY idx_warehouses_name (name),
    KEY idx_warehouses_created_by (created_by),
    KEY idx_warehouses_updated_by (updated_by),
    CONSTRAINT fk_warehouses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_warehouses_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. product_stocks — SELECT ... FOR UPDATE target (ARCH-02)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_stocks (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id   BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    quantity     INT UNSIGNED    NOT NULL DEFAULT 0,
    created_by   BIGINT UNSIGNED NULL,
    updated_by   BIGINT UNSIGNED NULL,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_product_warehouse (product_id, warehouse_id),
    KEY idx_stocks_product (product_id),
    KEY idx_stocks_warehouse (warehouse_id),
    KEY idx_stocks_created_by (created_by),
    KEY idx_stocks_updated_by (updated_by),
    CONSTRAINT fk_stocks_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stocks_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stocks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stocks_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_stocks_quantity CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. suppliers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    contact_person  VARCHAR(100)    NULL,
    phone           VARCHAR(30)     NULL,
    email           VARCHAR(255)    NULL,
    address         VARCHAR(255)    NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppliers_active (is_active),
    -- SORT INDEXING: SupplierMySQLRepository::findAll() always sorts name
    -- ASC; also matched by PurchaseOrderMySQLRepository::findAll()'s
    -- joined `s.name` search (PO list search box).
    KEY idx_suppliers_name (name),
    KEY idx_suppliers_created_by (created_by),
    KEY idx_suppliers_updated_by (updated_by),
    CONSTRAINT fk_suppliers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_suppliers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. customers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    contact_person  VARCHAR(100)    NULL,
    phone           VARCHAR(30)     NULL,
    email           VARCHAR(255)    NULL,
    address         VARCHAR(255)    NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_active (is_active),
    -- SORT INDEXING: CustomerMySQLRepository::findAll() always sorts name
    -- ASC; also matched by SalesOrderMySQLRepository::findAll()'s joined
    -- `c.name` search (SO list search box).
    KEY idx_customers_name (name),
    KEY idx_customers_created_by (created_by),
    KEY idx_customers_updated_by (updated_by),
    CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_customers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. purchase_orders
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id                 BIGINT UNSIGNED NOT NULL,
    destination_warehouse_id    BIGINT UNSIGNED NOT NULL,
    status                      ENUM('Draft','Ordered','PartiallyReceived','Received','Cancelled') NOT NULL DEFAULT 'Draft',
    order_date                  DATE NOT NULL,
    note                        VARCHAR(500) NULL,
    created_by                  BIGINT UNSIGNED NOT NULL,
    updated_by                  BIGINT UNSIGNED NULL,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_po_status (status),
    KEY idx_po_order_date (order_date),
    KEY idx_po_supplier (supplier_id),
    KEY idx_po_dest_wh (destination_warehouse_id),
    KEY idx_po_updated_by (updated_by),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_dest_warehouse FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. purchase_order_items
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id   BIGINT UNSIGNED NOT NULL,
    product_id          BIGINT UNSIGNED NOT NULL,
    qty_ordered         INT UNSIGNED NOT NULL,
    qty_received        INT UNSIGNED NOT NULL DEFAULT 0,
    purchase_price      DECIMAL(15,2) NOT NULL,
    created_by          BIGINT UNSIGNED NULL,
    updated_by          BIGINT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_poi_po (purchase_order_id),
    KEY idx_poi_product (product_id),
    KEY idx_poi_created_by (created_by),
    KEY idx_poi_updated_by (updated_by),
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_poi_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_poi_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_poi_qty_ordered CHECK (qty_ordered > 0),
    CONSTRAINT chk_poi_qty_received CHECK (qty_received >= 0),
    CONSTRAINT chk_poi_purchase_price CHECK (purchase_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. sales_orders
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales_orders (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id           BIGINT UNSIGNED NOT NULL,
    source_warehouse_id   BIGINT UNSIGNED NOT NULL,
    status                ENUM('Draft','PendingApproval','Approved','Fulfilled','Cancelled') NOT NULL DEFAULT 'Draft',
    order_date            DATE NOT NULL,
    note                  VARCHAR(500) NULL,
    created_by            BIGINT UNSIGNED NOT NULL,
    approved_by           BIGINT UNSIGNED NULL,
    approved_at           DATETIME NULL,
    issued_by             BIGINT UNSIGNED NULL,
    issued_at             DATETIME NULL,
    cancellation_reason   VARCHAR(500) NULL,
    updated_by            BIGINT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- `idx_so_status_creator`'s leftmost column (status) already covers any
    -- plain `WHERE status = ?` query (SalesOrderMySQLRepository::ownerAndStatusFilters()
    -- always goes through this one path), so a separate single-column index
    -- on status alone would be redundant write overhead with no query benefit.
    KEY idx_so_status_creator (status, created_by),
    KEY idx_so_order_date (order_date),
    KEY idx_so_customer (customer_id),
    KEY idx_so_source_wh (source_warehouse_id),
    KEY idx_so_updated_by (updated_by),
    CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_source_warehouse FOREIGN KEY (source_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. sales_order_items
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales_order_items (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    product_id     BIGINT UNSIGNED NOT NULL,
    qty            INT UNSIGNED NOT NULL,
    sale_price     DECIMAL(15,2) NOT NULL,
    created_by     BIGINT UNSIGNED NULL,
    updated_by     BIGINT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_soi_so (sales_order_id),
    KEY idx_soi_product (product_id),
    KEY idx_soi_created_by (created_by),
    KEY idx_soi_updated_by (updated_by),
    CONSTRAINT fk_soi_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_soi_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_soi_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_soi_qty CHECK (qty > 0),
    CONSTRAINT chk_soi_sale_price CHECK (sale_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. stock_ledger — insert-only, immutable
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_ledger (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id       BIGINT UNSIGNED NOT NULL,
    warehouse_id     BIGINT UNSIGNED NOT NULL,
    type             ENUM('Receipt','Issue','Adjustment') NOT NULL,
    qty              INT NOT NULL,
    ref_type         ENUM('PO','SO','Adjustment') NULL,
    ref_id           BIGINT UNSIGNED NULL,
    note             VARCHAR(255) NULL,
    done_by_user_id  BIGINT UNSIGNED NOT NULL,
    done_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ledger_product_wh_done_at (product_id, warehouse_id, done_at),
    KEY idx_ledger_done_at (done_at),
    KEY idx_ledger_ref (ref_type, ref_id),
    -- FILTER INDEXING: columns used as exact-match WHERE filters in
    -- StockLedgerMySQLRepository::findFiltered() (movement_type dropdown filter)
    KEY idx_ledger_type (type),
    CONSTRAINT fk_ledger_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ledger_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ledger_done_by FOREIGN KEY (done_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. role_permissions — DB-backed replacement for hardcoded Role checks in controllers/views
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_key  VARCHAR(60)     NOT NULL,
    role            ENUM('Admin','Sales','WarehouseStaff') NOT NULL,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_role_permission (permission_key, role),
    KEY idx_role_permissions_role (role),
    KEY idx_role_permissions_created_by (created_by),
    KEY idx_role_permissions_updated_by (updated_by),
    CONSTRAINT fk_role_permissions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. notifications — bonus feature (§2 "Scheduled Job — Notifikasi stok
-- rendah", DIPERBOLEHKAN). In-app only, populated by scripts/check-low-stock.php
-- (JOB-01), read by Admin/WarehouseStaff dashboards. read_at NULL = unread.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type            VARCHAR(50)     NOT NULL,
    message         VARCHAR(255)    NOT NULL,
    product_id      BIGINT UNSIGNED NULL,
    warehouse_id    BIGINT UNSIGNED NULL,
    read_at         DATETIME        NULL,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- FILTER + SORT INDEXING: replaces a plain read_at index — covers both
    -- the WHERE read_at IS NULL predicate AND the ORDER BY created_at DESC
    -- in one pass for NotificationMySQLRepository::findUnread()/countUnread()
    -- (the notification bell, polled on every authenticated page).
    KEY idx_notifications_unread_created (read_at, created_at),
    -- FILTER INDEXING: covers the exact-match dedupe lookup in
    -- NotificationMySQLRepository::existsUnreadFor(), called once per
    -- low-stock product on every scheduled-job run (JOB-01).
    -- `idx_notifications_dedupe`'s leftmost column (type) already covers any
    -- plain `WHERE type = ?` lookup, so a separate single-column index on
    -- type would be redundant write overhead with no additional query benefit.
    KEY idx_notifications_dedupe (type, product_id, warehouse_id, read_at),
    KEY idx_notifications_created_by (created_by),
    KEY idx_notifications_updated_by (updated_by),
    CONSTRAINT fk_notifications_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. event_logs — insert-only, immutable general activity/event log.
--
-- Separate from `stock_ledger`: stock_ledger is the authoritative, INV-2/INV-3
-- financial-grade record of stock quantity movements only (Receipt/Issue/
-- Adjustment) and must never be conflated with general activity logging.
-- event_logs is a broader, best-effort trail of actions across the whole
-- system (auth events, master-data CRUD, PO/SO lifecycle transitions,
-- exports, etc.) for traceability/defense — it is NEVER a source of truth
-- for business state and NEVER read by authorization or stock logic.
--
-- Like stock_ledger, this table has no UPDATE or DELETE path in the
-- application: entries are appended once and never mutated (see AGENT.md/
-- CLAUDE.md — data-loss/hard-delete is out of bounds for this project).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NULL,       -- NULL = system/unauthenticated actor (e.g. a failed login before identity is known, or the scheduled job)
    action        VARCHAR(60)     NOT NULL,   -- e.g. login, login_failed, logout, create, update, deactivate, approve, reject, receive, issue, export
    entity_type   VARCHAR(60)     NULL,       -- e.g. 'Product', 'SalesOrder', 'User' — NULL for non-entity events (login/logout)
    entity_id     BIGINT UNSIGNED NULL,       -- affected row's id; NULL when entity_type is NULL
    description   VARCHAR(500)    NOT NULL,   -- human-readable summary, already resolved at write time (project convention: literal messages, no i18n registry for log text)
    ip_address    VARCHAR(45)     NULL,       -- IPv4 or IPv6
    user_agent    VARCHAR(255)    NULL,
    metadata      JSON            NULL,       -- optional structured context (e.g. changed fields); informational only, never parsed for business/authorization decisions
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- FILTER INDEXING: "show me everything this user did" (defense/incident review)
    KEY idx_event_logs_user (user_id),
    -- FILTER INDEXING: "show me every X action" (e.g. all approve/reject events)
    KEY idx_event_logs_action (action),
    -- FILTER INDEXING: "show me the history of this one record" — the
    -- most common activity-log read pattern (per-entity audit trail)
    KEY idx_event_logs_entity (entity_type, entity_id),
    -- SORT/RANGE INDEXING: activity feeds and date-range exports always
    -- order/filter by created_at DESC
    KEY idx_event_logs_created_at (created_at),
    CONSTRAINT fk_event_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. file_validation_rules — DB-backed upload allow-list (mirrors the DMS
-- FileValidationTrait concept). ImageUploadService reads this once per cache
-- TTL via FileValidationService (Memcached) and rejects any product image
-- whose extension is not listed or whose magic bytes do not match. Reference/
-- near-static data: no UPDATE/DELETE path in the application, managed via
-- migrations/seed only (same convention as role_permissions).
--   header_hex/footer_hex: uppercase hex; NULL skips that check.
--   read_bytes:            leading bytes to read for the header comparison.
--   is_active:             0 disables a rule without deleting the row.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS file_validation_rules (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    extension   VARCHAR(10)       NOT NULL,   -- lowercase, no dot (e.g. 'jpg')
    mime_type   VARCHAR(100)      NOT NULL,   -- expected finfo MIME (e.g. 'image/jpeg')
    header_hex  VARCHAR(32)       NULL,       -- magic-bytes prefix, uppercase hex; NULL = skip
    footer_hex  VARCHAR(32)       NULL,       -- magic-bytes suffix, uppercase hex; NULL = skip
    read_bytes  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_file_validation_extension (extension)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

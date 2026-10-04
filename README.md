# Inventory & Order Management System

Final Project - Intermediate Programmer
**PT Neuronworks Indonesia** · September 2026

A web-based inventory and order management application with multi-warehouse support, three roles (Admin / Sales / Warehouse Staff), Purchase Order, Sales Order, Stock Ledger, KPI Dashboard, CSV Reporting, and a Product Availability API. Product images are stored in MinIO (S3-compatible). English UI with a single light theme.

## Tech Stack (Required)
- **Backend:** PHP 8.3 Native OOP (brief: 8.2+), Controller → Service → Repository
- **Frontend:** HTML, CSS, Vanilla JavaScript + Fetch API
- **Database:** MySQL 8, PDO prepared statements, InnoDB transactions
- **Container:** Docker + Docker Compose (5 services: `app` — PHP 8.3, `cron` — low-stock check, `db` — MySQL 8, `redis` — sessions, `memcached` — product & permission cache). Object storage uses an external MinIO instance (`portfolio-minio`, see `MINIO_*` in `compose.yaml`).
- **Testing:** PHPUnit (Unit + Integration), PHPStan level 5 ✅

## `.env` Configuration (required before start)

All credentials are read from a `.env` file and are **not** stored in the
repository (secrets in `compose.yaml` use the `${VAR:?}` pattern, so Compose
refuses to start if `.env` is missing). On a clean clone, create a `.env`
file at the project root — copy the block below (the values below are safe
for local/demo use, **change them for production**):

```dotenv
# Application
APP_ENV=local
APP_DEBUG=true
APP_PORT=8090
APP_URL=http://localhost:8090
DEFAULT_LOCALE=en

# Database (MySQL)
DB_HOST=db
DB_PORT=3306
DB_NAME=inventory_order_management
DB_USER=iom_app
DB_PASSWORD=change_me_in_local_env
DB_ROOT_PASSWORD=change_me_root_password

# Session
SESSION_NAME=iom_session
SESSION_LIFETIME=3600

# Redis & Memcached
REDIS_HOST=redis
REDIS_PORT=6379
MEMCACHED_HOST=memcached
MEMCACHED_PORT=11211

# Object storage (external MinIO — portfolio-minio)
MINIO_ENDPOINT=http://host.docker.internal:9000
MINIO_PUBLIC_URL=http://localhost:9000
MINIO_REGION=us-east-1
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin123
MINIO_BUCKET=portfolio-uploads

# Security
ID_OBFUSCATION_KEY=change_this_to_a_long_random_string
```

> If `.env` has not been created, `docker compose up` intentionally fails
> with `required variable ... is missing` — that is expected security
> behavior, not a bug.

## Quick Start

```bash
# 1. Create the .env file first (see ".env Configuration" above), then start
#    5 containers: app + cron + db + redis + memcached (MinIO runs separately)
docker compose up --build -d

# Schema + seed data load automatically on first start via MySQL's
# docker-entrypoint-initdb.d (database/schema.sql, database/seed.sql).
# No manual seed step needed. To reseed from scratch:
#   docker compose down -v && docker compose up -d --build
# database/seed.sql is idempotent: re-running it adds no duplicate rows and
# never resets stock (product_stocks stays equal to SUM(stock_ledger.qty)).

# On a clean clone vendor/ doesn't exist yet (the project is bind-mounted over
# the image's copy); docker/php/entrypoint.sh runs `composer install` on the
# first start — watch `docker logs -f iom_app`. If it fails with "SSL
# certificate problem", an antivirus/proxy is intercepting TLS (e.g. Avast
# Web Shield): disable HTTPS scanning, or run `composer install` on the host.

# 2. Login (seed data)
# Admin:     admin@example.com     / admin123
# Sales:     sales1@example.com    / sales123
# Warehouse: warehouse@example.com / wh123
# Sales (EN default persona): sales2@example.com / grace123

# 3. Open browser
# App runs on host port 8090 by default (override with APP_PORT in .env
# if 8090 is already taken on your machine)
open http://localhost:8090
```

## Architecture

```
Controller ──► Service ──► Repository (MySQL / Fake)
     │            │
     │            └── SalesOrderPolicy (BR-001 segregation)
     │            └── DashboardService (per-role KPI)
     │            └── CsvExportService (RFC 4180)
     │
     └── AuthService + SessionManager (Redis-backed, CSRF)
     └── CacheService (Memcached — product-by-SKU & permission cache)
     └── ImageUploadService + MinioClient (product images)
```

## Key Business Rules

| Rule | Implementation |
|------|--------------|
| ARCH-02 (No overselling) | `SELECT FOR UPDATE` + DB transaction in `GoodsIssueService` |
| BR-001 (Segregation of duties) | `SalesOrderPolicy::assertCanDecide()` — server-side |
| BR-018 (Sales scope) | BR-018 filter in `SalesOrderRepository` |
| Stock invariant | `product_stocks` = SUM(`stock_ledger.qty`) |
| CSV injection prevention | RFC 4180, prefix `=+-@\t` with `'` |

## Testing

```bash
# Run all tests
docker compose exec app vendor/bin/phpunit

# Unit tests only
docker compose exec app vendor/bin/phpunit --testsuite Unit

# Integration tests (requires DB)
docker compose exec app vendor/bin/phpunit --testsuite Integration

# Static analysis
docker compose exec app vendor/bin/phpstan analyse --level=5

# Low-stock CLI
docker compose exec app php scripts/check-low-stock.php
```

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/products/{sku}/availability` | JSON stock availability |
| GET | `/reports/export/stock-ledger` | CSV stock ledger |
| GET | `/reports/export/orders?type=po\|so` | CSV orders |

## Folder Structure

```
app/
  Controller/     — HTTP layer (thin, no business logic)
  Service/       — Business logic + domain policies
  Repository/    — Data access (MySQL + Fake for tests)
  Entity/        — Immutable DTOs
  Core/          — Database, Container, SessionManager, CacheService, MinioClient, IdObfuscator
public/
  index.php      — Router
  assets/        — CSS, JS, icons
views/           — PHP templates
database/        — schema.sql, seed.sql
scripts/         — check-low-stock.php (CLI)
tests/
  Unit/          — Policy tests, Service tests
  Integration/   — Real MySQL transactions
docs/
  architecture/  — ADR-001 to ADR-006
  planning/      — product-vision, PRD, delivery-plan
  quality/       — refactor-log, tech-debt, srp-audit, wcag-contrast-audit, architecture-critique
```

## Seed Data

| User | Email | Role |
|------|-------|------|
| Rita | admin@example.com | Admin |
| Beni | sales1@example.com | Sales |
| Grace | sales2@example.com | Sales |
| Wawan | warehouse@example.com | Warehouse Staff |

## Status
**Stage 9 Release** — v1.0-mvp
All 6 slices complete. Docker clean rebuild verified. PHPUnit Unit 101/101 + Integration 17/17 ✅ · PHPStan level 5 0 errors ✅ (re-verified 2026-09-25) · QA 103 checks pass ✅

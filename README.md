# Inventory & Order Management System

Final Project - Intermediate Programmer
**PT Neuronworks Indonesia** · September 2026

An inventory and order management web application supporting multiple warehouses, three roles (Admin / Sales / Warehouse Staff), Purchase Orders, Sales Orders, a Stock Ledger, KPI dashboards, CSV reporting, and a Product Availability API. Product images are stored in MinIO (S3-compatible). The UI uses English and a single light theme.

## Tech Stack (Required)
- **Backend:** PHP 8.3 Native OOP (brief: 8.2+), Controller → Service → Repository
- **Frontend:** HTML, CSS, Vanilla JavaScript + Fetch API
- **Database:** MySQL 8, PDO prepared statements, InnoDB transactions
- **Container:** Docker + Docker Compose (5 services: `app` — PHP 8.3, `cron` — low-stock check, `db` — MySQL 8, `redis` — sessions, `memcached` — product & permission cache). Object storage uses external MinIO (`portfolio-minio`; see `MINIO_*` in `docker-compose.yaml`).
- **Testing:** PHPUnit (Unit + Integration), PHPStan level 5 ✅

## `.env` Configuration (Required Before Startup)

All credentials are read from `.env` and are **not** stored in the repository.
Secrets in `docker-compose.yaml` use the `${VAR:?}` pattern, so Compose refuses to start
when the required variables are missing. On a clean clone, create `.env` in the
project root using the block below (values are for local/demo use; **replace them for production**):

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

> If `.env` has not been created, `docker compose up` intentionally fails with
> `required variable ... is missing`. This is expected security behavior.

## MinIO on an existing VPS

External MinIO is supported through the existing S3 client. Set `MINIO_ENDPOINT` and `MINIO_PUBLIC_URL` to your S3 API domain, configure the existing bucket and application credentials, and allow browser reads for the public product-image prefix. Follow the [VPS integration guide](docs/ops/minio-vps-integration.md) for configuration, permissions and verification.

## Quick Start

```bash
# 1. Create .env first (see the configuration section above), then start
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
Controller â”€â”€â–º Service â”€â”€â–º Repository (MySQL / Fake)
     â”‚            â”‚
     â”‚            â””â”€â”€ SalesOrderPolicy (BR-001 segregation)
     â”‚            â””â”€â”€ DashboardService (per-role KPI)
     â”‚            â””â”€â”€ CsvExportService (RFC 4180)
     â”‚
     â””â”€â”€ AuthService + SessionManager (Redis-backed, CSRF)
     â””â”€â”€ CacheService (Memcached — product-by-SKU & permission cache)
     â””â”€â”€ ImageUploadService + MinioClient (product images)
```

## Key Business Rules

| Rule | Implementation |
|------|--------------|
| ARCH-02 (No overselling) | `SELECT FOR UPDATE` + DB transaction in `GoodsIssueService` |
| Deterministic stock lock order | `GoodsIssueService::applyIssuanceLines()` and `GoodsReceiptService::applyLinesInTransaction()` sort items by `productId` in ascending order before acquiring stock locks to prevent deadlocks caused by reversed product lock order. See [ADR-002](docs/architecture/adr-002-concurrency-strategy.md#multi-item-deadlock-prevention). |
| BR-001 (Segregation of duties) | `SalesOrderPolicy::assertCanDecide()` — server-side |
| BR-018 (Sales scope) | BR-018 filter in `SalesOrderRepository` |
| Stock invariant | `product_stocks` = SUM(`stock_ledger.qty`) |
| CSV injection prevention | RFC 4180, prefix `=+-@\t` with `'` |

## Testing

```bash
# Dependency-free public workflow regressions (no services or PHPUnit required)
php tests/Regression/run-reference-gaps.php

# Full isolated unit suite
php vendor/bin/phpunit --testsuite Unit

# Integration suite using the existing disposable E2E stack
cd tests/playwright
npm run test:integration

# Static analysis from the repository root
php vendor/bin/phpstan analyse --level=5
```

The integration command reuses the dedicated `ioms-e2e` database and test app; it does not target the normal application database. It requires the E2E environment and dependencies documented in [test instructions](docs/testing/README.md). Direct integration runs must explicitly configure `TEST_DB_NAME` different from `DB_NAME`, plus the matching test app URL/database. No application database is selected by default.

On 2026-10-04, the standalone runner passed 19 scenarios/153 assertions, PHPUnit Unit passed 132 tests/404 assertions, and isolated Integration passed 17 tests/143 assertions against an image built from this workspace. The release results below remain historical. See [current verification and limitations](docs/testing/README.md).

### PHPUnit Output

Screenshots below display saved output from the verified 2026-10-04 runs; no tests were rerun to produce these images. See [test evidence and raw output](docs/testing/README.md#phpunit-output-screenshots--2026-10-04) for capture context and expected injected-failure logs.

![PHPUnit Unit: 132 tests, 404 assertions passed](docs/testing/screenshots/phpunit-unit-2026-10-04.png)

![PHPUnit isolated Integration: 17 tests, 143 assertions passed](docs/testing/screenshots/phpunit-integration-2026-10-04.png)

## SonarQube Results

The screenshots and measurement context are also documented in [SonarQube dashboard evidence](docs/quality/sonarqube-results-2026-10-04.md).

Screenshots were captured on **2026-10-04** from the dashboard for project `ioms-apps`, version **1.1**. The Quality Gate shows **Passed**, and the dashboard reports warnings for the latest analysis. The screenshots reflect the analysis available on the server; no new scan was run against the current checkout.

### Overall Code

Security, Reliability, and Maintainability each have **0 open issues** and an **A** rating. Coverage is **11.1%**, duplications are **8.5%**, and there are **0 Security Hotspots**.

![SonarQube Overall Code — Quality Gate Passed, coverage 11.1%, duplications 8.5%](docs/quality/screenshots/sonarqube-overall-code-2026-10-04.png)

### New Code

The New Code baseline is **Since 1.0**. There are **0 new issues**, **0 accepted issues**, **0.0% coverage** across **17 lines to cover**, **0.0% duplications** across **37 new lines**, and **0 Security Hotspots**. The Passed status does not mean coverage has reached the **80%** target shown on the dashboard.

![SonarQube New Code — 0 new issues, coverage 0%, duplications 0%](docs/quality/screenshots/sonarqube-new-code-2026-10-04.png)

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
  Entity/        — Domain entities and data objects
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

**Remediation update (2026-10-04):** conditional order transitions prevent stale status overwrites; quantity/date/reorder validation rejects malformed and out-of-range input; Draft Sales Orders can be edited by their creator or an Admin; product sorting and displayed order-number search are available; availability dependency errors return 500; report valuation retains cents and shares the stock-value aggregate. Lock-order regressions now cover both Goods Issue and Goods Receipt. PHPStan level 5 passed against this working tree. Current Unit and isolated MySQL/HTTP Integration passed. A dedicated MySQL cancellation-race case, clean-clone build, complete role/mobile demo, VPS image-storage verification, and revision-matched Sonar evidence remain pending.

The 2026-09-25 release/build/test results above are historical snapshots and do not verify the current uncommitted remediation. Sonar screenshots are preserved with their original measurement context. The [reference audit](docs/quality/reference-gap-audit-2026-10-04.md) records current source fixes and remaining evidence or requirement decisions.

Architecture notes are available in [ADR-002 — Concurrency Strategy](docs/architecture/adr-002-concurrency-strategy.md); outstanding technical debt is tracked in [Tech Debt](docs/quality/tech-debt.md).


## Asset Credits

The [SVG sprite](public/assets/img/icons.svg) includes Feather icon geometry (MIT, Cole Bemis) and two adapted Lucide-style symbols (ISC, Lucide Contributors; inherited Feather portions use MIT). Geometry was compared against [Feather v4.29.2](https://github.com/feathericons/feather/tree/v4.29.2/icons) and [Lucide 0.468.0](https://github.com/lucide-icons/lucide/tree/0.468.0/icons); these are verification references, not a claim about the original import version. [Attribution details](public/assets/img/NOTICE.txt), [Feather license](public/assets/img/feather-license.txt), and [Lucide license](public/assets/img/lucide-license.txt) are distributed with the assets.

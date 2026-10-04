# Static Analysis Results — PHPStan

> ✅ **STATUS AS OF 2026-09-25: VERIFIED — 0 errors.** See the current run below. The 22 Sep run
> further down found 13 errors; those were fixed the same day.

---

## Current run — 2026-09-25 (verified)

**Date:** 2026-09-25
**Command:** `docker compose exec app ./vendor/bin/phpstan analyse`
**Configuration:** `phpstan.neon` (level 5, paths: `app/`, `public/`, `scripts/`, excludes `app/Repository/Fake/`)
**Result:** ✅ **[OK] No errors**

```
Note: Using configuration file /var/www/html/phpstan.neon.
 [OK] No errors
```

Two things had to be fixed before this run was possible:

1. `phpstan.neon` in the project root had become an **empty directory**, and the real config was
   sitting in `phpstan.neon.NEW` (an extension PHPStan refuses to load). As a result, `phpstan analyse`
   failed with *"At least one path must be specified to analyse."* **Fixed:** removed the empty directory
   and renamed `phpstan.neon.NEW` → `phpstan.neon`.
2. 3 errors in `public/index.php` `renderErrorPage()`: *"Anonymous function has an unused use
   $isLoggedIn / $message / $statusCode"*. The variables were used only by the `require`d view, which
   PHPStan cannot see. **Fixed:** they are now passed as a `$data` array and `extract()`ed inside the
   closure, following the same pattern as `renderViewModel()`. The page renders the same as before;
   verified by checking that an unknown path still returns 404 with the "Go to Login" link.

---

## Run — 2026-09-22 (real, pre-fix)

**Date:** 2026-09-22
**Command:** `docker compose exec app ./vendor/bin/phpstan analyse`
**Configuration:** `phpstan.neon` (level 5, paths: `app/`, `public/`, `scripts/`) — **recreated this
run; the file was missing entirely from the project** (a prior run's "[OK] No errors" could not have
come from this configuration, since PHPStan refuses to run without a `paths` list at all — see the
16 Sep snapshot's caveat below).
**Result:** ❌ **[ERROR] Found 13 errors**

```
Note: Using configuration file /var/www/html/phpstan.neon.
 99/99 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ------ -----------------------------------------------------------------------------------------
  Line   app/Repository/MySQL/CategoryMySQLRepository.php
 ------ -----------------------------------------------------------------------------------------
  12     Property App\Repository\MySQL\CategoryMySQLRepository::$db is never read, only written.
 ------ -----------------------------------------------------------------------------------------

(same "never read, only written" error, one each, for:)
  - CustomerMySQLRepository.php
  - EventLogMySQLRepository.php
  - ProductMySQLRepository.php
  - ProductStockMySQLRepository.php
  - PurchaseOrderItemMySQLRepository.php
  - PurchaseOrderMySQLRepository.php
  - SalesOrderMySQLRepository.php
  - StockLedgerMySQLRepository.php
  - SupplierMySQLRepository.php
  - UserMySQLRepository.php
  - WarehouseMySQLRepository.php

 ------ ----------------------------------------------------------------------------------------------------------
  Line   scripts/check-low-stock.php
 ------ ----------------------------------------------------------------------------------------------------------
  59     Class App\Repository\MySQL\NotificationMySQLRepository constructor invoked with 1 parameter, 2 required.
 ------ ----------------------------------------------------------------------------------------------------------

 [ERROR] Found 13 errors
```

**Root cause and fix for each error group:**

1. **12x "`$db` is never read, only written"`** (all `*MySQLRepository` classes except `QueryBuilder`
   itself). Every one of these repositories takes `Database $db` and `QueryBuilder $queryBuilder` in
   its constructor, but only ever queries through `$queryBuilder` (which already owns its own `$db`
   internally) — the repository's own `$this->db` was assigned in the constructor and then never
   used anywhere else in the class. This is dead state, not a functional bug, but it is exactly what
   PHPStan level 5's "always-read, always-written properties" rule is designed to catch.
   **Fixed:** removed the unused `private $db;` property and its assignment from all 12 repository
   classes. The constructor still accepts `Database $db` (so every call site in `app/Core/Container.php`
   is untouched and still valid) — the parameter is simply no longer stored.

2. **1x real bug** — `scripts/check-low-stock.php` line 59 instantiated
   `new App\Repository\MySQL\NotificationMySQLRepository($database)` with only one constructor
   argument, but `NotificationMySQLRepository::__construct(Database $db, QueryBuilder $queryBuilder)`
   requires two. This would have caused a fatal `ArgumentCountError` the first time the scheduled
   low-stock job actually tried to create a notification (i.e. the first time it found a low-stock
   row) — PHPStan caught this before it could fail at runtime.
   **Fixed:** the call now also constructs and passes a `QueryBuilder($database)`:
   ```php
   new App\Repository\MySQL\NotificationMySQLRepository(
       $database,
       new App\Repository\MySQL\QueryBuilder($database)
   )
   ```

All 13 fixes were verified by manual inspection (grep for stray `$this->db` references — none remain
in any of the 12 repositories; brace-count check on each file — all balanced) since this environment
cannot run `php`/`docker` directly. A fresh `docker compose exec app ./vendor/bin/phpstan analyse` run
is needed to confirm the fixes produce a clean `[OK] No errors` result — see the action item above.

---

## Historical snapshot — 2026-09-16 (superseded, unverifiable)

**Date:** 2026-09-16
**Command:** `docker compose exec app ./vendor/bin/phpstan analyse`
**Result claimed:** ✅ "[OK] No errors"

> **Caveat added 2026-09-22:** this snapshot cannot be trusted as-is. Investigation on 22 Sep found
> `phpstan.neon` entirely missing from the project directory — PHPStan refuses to run at all without a
> `paths` config (`At least one path must be specified to analyse.`), so either the config existed and
> was later deleted, or this snapshot was not produced by an actual run against this codebase. Treat
> the 2026-09-22 run above as the first trustworthy PHPStan result for this project, pending final
> re-verification.

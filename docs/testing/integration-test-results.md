# Integration Test Results

**Current remediation run (2026-10-04): PASS — 17 tests, 143 assertions.** PHP 8.3.20 / PHPUnit 10.5.64 in a fresh workspace image; see [current environment and evidence](README.md). Everything below retains its historical date and output.

✅ **STATUS AS OF 2026-09-22: VERIFIED — 17/17 pass.** The 3 failures found earlier the same day
(all in `SalesOrderApprovalPolicyTest`, "SO detail page should embed a CSRF token") are now fixed and
confirmed with a real, fresh test run below.

**Root cause (fixed and re-verified 2026-09-22):**
1. `compose.yaml`'s `app` service never passed `ID_OBFUSCATION_KEY` through to the container
   (only `.env` had it — Compose does not auto-inject `.env` vars into a container, only variables
   explicitly listed under `environment:`). `config/global.php` silently fell back to an empty key,
   so `IdObfuscator` ran with no key at all. **Fixed:** `ID_OBFUSCATION_KEY` added to the `app`
   service's `environment:` block.
2. `SalesOrderApprovalPolicyTest` extracted the SO id from the redirect `Location` header with
   `preg_match('#/sales-orders/(\d+)#', ...)`, assuming a plain decimal id. IDs in URLs are actually
   `IdObfuscator`-encoded hex tokens (`bin2hex(...)`, e.g. `4f2a9c…`) — `\d+` only grabbed a
   meaningless leading digit run out of that hex string, so every subsequent request in the test hit
   a 404 page with no CSRF form. **Fixed:** the test now captures the full hex token
   (`[0-9a-f]+`), uses it directly to build URLs (as the real app does), and decodes it back to the
   real integer id (via the same `App\Core\IdObfuscator` + `ID_OBFUSCATION_KEY`) only when it needs
   to query `sales_orders` by id directly for status assertions.

---

## Current run — 2026-09-22 (verified, post-fix)

**Date:** 2026-09-22
**Command:** `docker compose exec app ./vendor/bin/phpunit --testsuite Integration --testdox`
**Runtime:** PHP 8.3.20 / PHPUnit 10.5.64
**Result:** ✅ OK (17 tests, 143 assertions)

> **Re-run 2026-09-25:** ✅ OK (17 tests, 143 assertions) — same result, 00:23.575.
>
> **Note on lock timeout warning:** `GoodsIssueConcurrencyTest::testSecondConnectionBlocksOnRowLockHeldByFirst`
> intentionally produces this lock-wait-timeout error. Connection A holds the `SELECT ... FOR UPDATE`
> row lock that `GoodsIssueService::issue()` takes; connection B is given
> `innodb_lock_wait_timeout = 1` and must block on that lock until it times out — proving that ARCH-02
> (pessimistic locking) serializes concurrent goods issues. The test passes; the error text is the
> expected, logged outcome of connection B's timeout, not a failure.

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.20
Configuration: /var/www/html/phpunit.xml

....../var/www/html/app/Repository/MySQL/QueryBuilder.php:81 SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction
...........                                                 17 / 17 (100%)

Time: 00:27.095, Memory: 10.00 MB
```

---

### Historical snapshot — 2026-09-16 (superseded)

**Date:** 2026-09-16
**Command:** `docker compose exec app ./vendor/bin/phpunit --testsuite Integration --testdox`
**Runtime:** PHP 8.2.33 / PHPUnit 10.5.64
**Result:** ✅ OK (17 tests, 136 assertions) — superseded by the 2026-09-22 run above

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.33
Configuration: /var/www/html/phpunit.xml

.......docker : /var/www/html/app/Repository/MySQL/QueryBuilder.php:85 SQLSTATE[HY000]: General error: 1205 Lock wait timeout
exceeded; try restarting transaction

..........                                                 17 / 17 (100%)

Time: 00:37.160, Memory: 10.00 MB
```

---

## ARCH02 Concurrency (Tests\Integration\ARCH02Concurrency)

 ✔ Second concurrent issue fails when stock exhausted by first
 ✔ Both issuing full amount only one succeeds

---

## BR001 Segregation (Tests\Integration\BR001Segregation)

 ✔ Admin can approve sales user so
 ✔ Sales cannot approve own so
 ✔ Different sales cannot approve other sales so
 ✔ Admin can approve their own self created so

---

## Goods Issue Concurrency (Tests\Integration\GoodsIssueConcurrency)

 ✔ Second connection blocks on row lock held by first
 ✔ Concurrent goods issue only one succeeds
 ✔ Insufficient stock rolls back cleanly leaving so approved and no ledger entry

---

## Goods Receipt (Tests\Integration\GoodsReceipt)

 ✔ Happy path receipt updates stock ledger and po consistently
 ✔ Rollback leaves no partial update when one line over receives
 ✔ Rollback via mid transaction concurrent over receipt is atomic

---

## Sales Order Approval Policy (Tests\Integration\SalesOrderApprovalPolicy)

 ✔ Sales cannot approve own sales order via http
 ✔ Admin can approve another users sales order via http
 ✔ Admin can approve their own self created sales order via http

---

## User Creation (Tests\Integration\UserCreation)

 ✔ Admin can create user via http post
 ✔ Non admin cannot create user via http post

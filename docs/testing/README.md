# Test Evidence — docs/testing/

**PURPOSE:** This directory contains executable proof of TEST-01, TEST-02, and TEST-03 from the
Project Brief. All tests were run against the **actual Docker environment** (`docker compose`).

---

## Running the tests

All tests run from the project root:

```bash
# Unit tests (no DB needed)
docker compose exec app ./vendor/bin/phpunit --testsuite Unit

# Integration tests (requires running MySQL in Docker)
docker compose exec app ./vendor/bin/phpunit --testsuite Integration

# Static analysis
docker compose exec app ./vendor/bin/phpstan analyse
```

---

## Test Coverage Summary

| Suite | Tests | Assertions | Status |
|-------|-------|------------|--------|
| Unit | 101 | 234 | ✅ OK |
| Integration | 17 | 143 | ✅ OK |
| PHPStan (level 5) | `app/`, `public/`, `scripts/` | 0 errors | ✅ OK (2026-09-25) |

---

## Files

| File | Description |
|------|-------------|
| `unit-test-results.md` | Full `--testdox` output of all 101 unit tests |
| `integration-test-results.md` | Full `--testdox` output of all 17 integration tests |
| `phpstan-results.md` | PHPStan level 5 analysis result |

---

## Key Coverage Areas

### TEST-01 (Unit — ≥6 cases, ≥3 logic areas)

1. **Auth logic** (6 cases) — `AuthServiceTest`
2. **CSV export** (18 cases) — `CsvExportServiceTest` — CSV-injection hardening, RFC 4180 escaping, locale headers
3. **Dashboard aggregation** (7 cases) — `DashboardServiceTest` — role-scoped KPIs, no hardcoded numbers
4. **Goods receipt** (5 cases) — `GoodsReceiptServiceTest` — partial/full receipt, rollback
5. **Permission service** (4 cases) — `PermissionServiceTest` — role-key lookup
6. **Purchase order lifecycle** (8 cases) — `PurchaseOrderServiceTest` — status machine transitions
7. **Sales order policy** (16 cases) — `SalesOrderPolicyTest` — SoD, state transitions, cancellation
8. **ID obfuscation** (5 cases) — `IdObfuscatorTest` — URL id encode/decode round-trip
9. **Notifications** (8 cases) — `NotificationServiceTest`
10. **Object storage client** (24 cases) — `MinioClientTest` — MinIO (S3-compatible) product image storage

### TEST-02 (Integration — real MySQL in Docker)

1. **ARCH02Concurrency** — `SELECT FOR UPDATE` prevents oversell under concurrent goods issues
2. **BR001Segregation** — Sales cannot approve, Admin can approve another's order, Admin CANNOT approve own
3. **GoodsIssueConcurrency** — second request blocked on row lock, no phantom stock decrement
4. **GoodsReceipt** — atomic stock + ledger write, full rollback on over-receipt
5. **SalesOrderApprovalPolicy** — HTTP-level policy enforcement
6. **UserCreation** — Admin creates user, non-Admin blocked

### TEST-03 (Static Analysis)

PHPStan level 5 across `app/`, `public/`, `scripts/` — **0 errors**.

---

## FIRST Principles Compliance

| Principle | Status | Evidence |
|-----------|--------|---------|
| **F**ast | ✅ | Unit: ~2.5s, Integration: ~24s (includes a deliberate 1s lock-wait timeout) |
| **I**solated | ✅ | Each test creates its own scenario; no shared state |
| **R**epeatable | ✅ | Same seed data; deterministic IDs via `$conn->lastInsertId()` |
| **S**elf-verifying | ✅ | `assertTrue()` / `assertEquals()` — green = pass |
| **T**imely | ✅ | Tests run on every change (manual verification) |
| No `sleep()` | ✅ | grep `sleep(` across tests/ → 0 hits |
| No real network | ✅ | No external HTTP calls in tests |
| No order dependency | ✅ | Tests use `setUp()` per test class |

---

## Note on Lock-Timeout Warning in Integration Tests

`ARCH02ConcurrencyTest` intentionally produces a MySQL lock-wait-timeout on `QueryBuilder.php:85`.
This is **expected behaviour** — the test proves that when two transactions contend on the same
`product_stocks` row, one is blocked until its lock times out, demonstrating that `SELECT FOR UPDATE`
prevents the race condition from producing oversell. All 17 tests still pass.

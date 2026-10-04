# Unit Test Results

**Current remediation run (2026-10-04): PASS — 132 tests, 404 assertions.** PHP 8.3.20 / PHPUnit 10.5.64 in a fresh workspace image; see [current environment and evidence](README.md). Everything below retains its historical date and output.

[Current PHPUnit output screenshot](screenshots/phpunit-unit-2026-10-04.png) · [Saved output](screenshots/phpunit-unit-2026-10-04.txt)

**Date:** 2026-09-25
**Command:** `docker compose exec app ./vendor/bin/phpunit --testsuite Unit --testdox`
**Runtime:** PHP 8.3.20 / PHPUnit 10.5.64
**Result:** ✅ OK (101 tests, 234 assertions)

> Re-run 25 Sep 2026: the 22 Sep snapshot (77 tests, 203 assertions) had drifted out of date as
> new test cases were added (including `MinioClientTest`). Output below is the real, current run.

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.20
Configuration: /var/www/html/phpunit.xml

...............................................................  63 / 101 ( 62%)
......................................                          101 / 101 (100%)

Time: 00:02.420, Memory: 10.00 MB

OK (101 tests, 234 assertions)
```


---

## Auth Service (Tests\Unit\AuthService)

 ✔ Login succeeds with valid credentials
 ✔ Login fails with wrong password
 ✔ Login fails for unknown email
 ✔ Login fails for inactive user
 ✔ Current user returns null when not logged in
 ✔ Logout clears session

---

## Csv Export Service (Tests\Unit\CsvExportService)

 ✔ Export stock ledger headers in english
 ✔ Export stock ledger headers in indonesian
 ✔ Export stock ledger with rows
 ✔ Type translation receipt
 ✔ Type translation issue
 ✔ Export order status headers in english
 ✔ Export order status headers in indonesian
 ✔ Export order status with rows
 ✔ Status translation
 ✔ Equals prefix injection is escaped
 ✔ Plus prefix injection is escaped
 ✔ Minus prefix injection is escaped
 ✔ At sign prefix injection is escaped
 ✔ Tab prefix injection is escaped
 ✔ Double quote inside field is escaped
 ✔ Comma inside field is quoted
 ✔ Empty rows produce headers only
 ✔ Crlf line endings used

---

## Dashboard Service (Tests\Unit\DashboardService)

 ✔ Get admin stats returns inventory value
 ✔ Get admin stats empty counts return zero
 ✔ Get sales stats returns status counts for user
 ✔ Get sales stats empty returns empty array
 ✔ Get warehouse stats returns queue counts
 ✔ Get warehouse stats receipt queue sums ordered and partially received
 ✔ Get warehouse stats issue queue counts approved s os

---

## Goods Receipt Service (Tests\Unit\GoodsReceiptService)

 ✔ Full receipt marks po received and increments stock
 ✔ Partial receipt twice reaches received and invariant holds
 ✔ Receipt rejects qty exceeding remaining
 ✔ Receipt rejected when po not ordered or partially received
 ✔ Mixed batch with one invalid line rejects whole batch

---

## Id Obfuscator (Tests\Unit\IdObfuscator)

 ✔ Encode then decode round trips to original id
 ✔ Encoded token does not contain plain id
 ✔ Decode returns null for garbage token
 ✔ Decode returns null when key differs
 ✔ Decode rejects incremented neighbor token

---

## Minio Client (Tests\Unit\MinioClient)

 ✔ Construct succeeds with all required values
 ✔ Construct throws when endpoint is empty
 ✔ Construct throws when access key is empty
 ✔ Construct throws when secret key is empty
 ✔ Construct throws when bucket is empty
 ✔ Ioms prefix is correct namespace
 ✔ Build object key prepends ioms prefix
 ✔ Build object key strips leading slash from path
 ✔ Build object key handles plain filename
 ✔ Extract object key from ioms url returns key
 ✔ Extract object key from ioms url with encoded chars
 ✔ Extract object key from legacy products url returns null
 ✔ Extract object key from unrelated url returns null
 ✔ Extract object key from empty string returns null
 ✔ Public url formats correctly
 ✔ Public url encodes path segments
 ✔ Get presigned url contains signature
 ✔ Get presigned url contains expiration
 ✔ Get presigned url uses algorithm aws 4 hmac sha 256
 ✔ Get presigned url contains credential scope
 ✔ Get presigned url clamps expiration to minimum 60 seconds
 ✔ Get presigned url clamps expiration to maximum 7 days
 ✔ Get presigned url uses default 3600 seconds
 ✔ Get presigned url contains signed host header

---

## Notification Service (Tests\Unit\NotificationService)

 ✔ Notify low stock creates notification
 ✔ Notify low stock deduplicates unread notification
 ✔ Notify low stock allows new notification after previous one is read
 ✔ Different product warehouse pairs do not deduplicate against each other
 ✔ Count unread matches get unread for dashboard
 ✔ Mark all read clears unread count
 ✔ Get unread for dashboard respects limit
 ✔ Type constant is low stock

---

## Permission Service (Tests\Unit\PermissionService)

 ✔ Report permissions are split by role
 ✔ Warehouse staff has purchase orders manage
 ✔ Unknown key returns false
 ✔ Granted keys for role returns all matching keys

---

## Purchase Order Service (Tests\Unit\PurchaseOrderService)

 ✔ Create starts in draft with items
 ✔ Create rejects empty item list
 ✔ Submit transitions draft to ordered
 ✔ Submit rejects non draft state
 ✔ Cancel allowed from draft and ordered
 ✔ Cancel allowed after partial receipt
 ✔ Cancel rejected after fully received
 ✔ Recompute status becomes partially received then received

---

## Sales Order Policy (Tests\Unit\SalesOrderPolicy)

 ✔ Admin can approve
 ✔ Non admin cannot approve even someone elses so
 ✔ Admin can approve their own self created so
 ✔ Warehouse staff cannot approve
 ✔ Approval forbidden exception carries the new message key
 ✔ Creator can cancel own draft so
 ✔ Creator can cancel own pending approval so
 ✔ Approved so cannot be cancelled by its non admin creator
 ✔ Admin can cancel an approved so
 ✔ Fulfilled so cannot be cancelled
 ✔ Admin can cancel any non fulfilled so
 ✔ Non creator sales cannot cancel others so
 ✔ Only approved so can be issued
 ✔ Draft so cannot be issued
 ✔ Pending approval so cannot be issued
 ✔ Cancelled so cannot be issued


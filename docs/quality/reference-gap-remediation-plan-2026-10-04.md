# Reference gap remediation — 2026-10-04

Goal: repair the confirmed reference gaps using existing service/repository boundaries, and repeat source and static verification after each batch.

Approved scope: the findings and recommendations in reference-gap-audit-2026-10-04.md, requested for remediation by the project owner.

Architecture: conditional status updates with affected-row checks; raw-input validation before conversion; Draft SO editing under the existing transaction manager; allowlisted sorting; explicit availability errors; isolated test doubles and integration environment; external VPS storage integration guidance; accurate documentation.

Constraints: preserve existing work, no developer/production database changes, no deployment, no commit or push. Initial restrictions on PHPUnit and SQL execution were superseded by explicit user authorization for Unit and isolated Integration testing on 2026-10-04; test fixtures/schema are limited to the disposable stack. Existing HTTP success payloads and permissions remain compatible. PHP functions must have at most three explicit returns and complexity at most 15.

Sequence:
1. Reproduce and repair status races, malformed input, static-analysis failures and API errors.
2. Complete Draft SO editing, product sorting and displayed order-number search.
3. Add stock-lock regressions; isolate test dependencies and integration database targets.
4. Prepare external VPS storage integration documentation and reconcile aggregation and documentation.
5. Repeat PHP syntax, static analysis, standalone fake-repository regressions, contract/style checks and reference matrix review. Record pending runtime evidence explicitly.

Review focus: ownership and status checks under concurrency; no partial header/item writes; parameterized SQL; deterministic lock order; no fabricated runtime or Sonar results.


Coordination: application code is owned by Verifikasi GoodsReceiptService (2), tests/documentation by Verifikasi GoodsReceiptService, and MinIO VPS setup documentation by Verifikasi GoodsReceiptService (3). Overlapping edits were reconciled before verification. The original project brief is now available and takes precedence over example implementation choices in the reference blueprint. The user explicitly authorized Unit and isolated Integration in session (2). Both passed in an image built from this workspace; remaining external/runtime evidence is tracked in the audit and testing documents.

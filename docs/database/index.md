[Docs](../) / Database

# Database scripts

One-off SQL scripts that are not schema migrations (those live in `database/migrations/`). Per `AGENT.md` §8.3, SQL written by an agent is stored here. Each script's header lists its target tables, whether it is idempotent, and when to run it.

| Script | Purpose |
|---|---|
| [cleanup-arch02-test-fixtures.sql](cleanup-arch02-test-fixtures.sql) | Removes the `ARCH02-1` / `ARCH02-2` products that `ARCH02ConcurrencyTest` leaves in the shared dev DB, together with their `stock_ledger` rows that point at sales orders that no longer exist. Idempotent; dev DB only. Executed on 2026-09-25. |

[Back to docs](../)

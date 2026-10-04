# ADR-005: Redis (Session) + Memcached (Read Cache) — Separation of Concerns, TTL 3600

- **Status:** Accepted
- **Date:** 2026-09-18 (written post-implementation; the decision itself was made and implemented at
  Slice 1/6 — this ADR records it formally, closing the gap flagged at C-09/DESIGN-02 in
  `docs/planning/master-project-specification.md` §30)
- **Stage:** 9 / 9 (Release — retroactive documentation of an already-implemented decision)
- **Related Requirements:** SESSION-01, CACHE-01 (instruction §8, §9 — not brief-mandated)
- **Related BR:** BR-SES-01..04, BR-CCH-01..04
- **Related:** ADR-002 (concurrency — MySQL remains sole source of truth for stock), §22–§25 of
  `docs/planning/master-project-specification.md`

---

## Context (Konteks)

Brief §5.1 requires only an application service and a MySQL service — Redis and Memcached are **not**
in the brief. They were added as a rank-2 project decision (instruction §8/§9) to externalize session
state and to demonstrate a cache-aside read pattern. Brief §0 explicitly warns that unjustified
complexity is scored **negatively** ("*Solusi yang lebih rumit tanpa alasan jelas … dinilai negatif
pada area code quality*"), so adding two extra services needs an explicit rationale and hard
boundaries — otherwise it is indefensible at review.

Two separate concerns were at risk of being blurred into one store:

1. **Session state** — short-lived, per-user, must survive nothing if lost except an active login
   (the user simply re-authenticates).
2. **Read cache** — regeneratable, derived from MySQL, must never be a second source of truth and must
   never cache anything MySQL couldn't recompute (especially stock, which is under ARCH-02's
   concurrency contract).

Putting both in the same store invites exactly the failure this ADR exists to prevent: someone later
"just caches stock too" because the store is already there for sessions, silently breaking ARCH-02.

## Decision (Keputusan)

- **Redis** is used for **temporary authentication/session state only**. TTL is **3600 seconds**
  (`SessionManager` constructor default and `compose.yaml`'s `SESSION_LIFETIME` fallback, both
  set to `3600` — see TD-02/C-01, resolved 2026-09-17). Session data holds no business data; it is
  fully disposable.
- **Memcached** is used for **regeneratable application read cache only**, narrowed in practice to
  `product:<sku>` (see ADR discussion in §23.5 of the master spec and TD-03/C-08, resolved
  2026-09-17). It is never write-through and never a source of truth.
- **MySQL remains the sole source of truth for all business data, including and especially stock.**
  Stock is never cached in Memcached and never gated through Redis. Redis is never used as a
  concurrency mechanism — that role belongs exclusively to `SELECT … FOR UPDATE` on `product_stocks`
  (ADR-002).
- **Both services must degrade gracefully.** If Redis is unreachable, `SessionManager::start()` probes
  it (`fsockopen()`, 0.5s timeout) and falls back to file-based sessions with a logged warning — login
  keeps working (§22.7, C-02, resolved 2026-09-17). If Memcached is unreachable, `CacheService` marks
  itself unavailable and every read goes straight to MySQL — the application is fully functional with
  Memcached stopped.

## Alternatives (Alternatif)

- **(a) MySQL only, no Redis/Memcached.** Simplest option, fully brief-compliant, and the honest
  baseline this decision must be defensible against. Rejected only because the instruction set
  (rank 2, above the brief's silence on the topic) asked for externalized sessions and a read cache —
  not because MySQL-only would have failed any requirement.
- **(b) Redis for both session and cache.** Fewer moving services, but blurs the session/cache
  boundary and creates a standing temptation to cache stock or other business data in the same store
  that gates authentication. Rejected — the boundary is worth one extra service.
- **(c) Memcached for sessions.** Memcached has no native TTL-driven expiry semantics or persistence
  worth relying on for authentication state; Redis's TTL and durability characteristics are the better
  fit for sessions. Rejected.

## Consequences (Konsekuensi)

**Positive:**
- Session storage is externalized with real TTL semantics (3600s, matching the fixed spec value).
- The one genuinely hot read path (`product:<sku>`, used by API-01 and every order-line render) is
  cheap without adding invalidation surface to lookups that are read-rarely and change-rarely.
- The boundary between "disposable session state" and "regeneratable cache" is structural, not just
  documented — they live in different services with different client classes
  (`SessionManager` vs `CacheService`), so a future change that tries to cache stock in `CacheService`
  cannot accidentally also gate a login.

**Negative / trade-off:**
- Two extra services to run, operate and defend at review that the brief does not require.
- Cache invalidation is a real (if narrow) concern now — handled by post-commit invalidation of
  `product:<SKU>` on every product write path (`ProductService`).
- **The honest consequence is that neither service is required — the project
  would still satisfy every brief requirement without them.** This ADR's job is to make sure that,
  having chosen to add them anyway, neither can become a critical-failure vector (§22.7) or start
  quietly holding business data MySQL should own.

## Validation / How to Verify

- [x] `SessionManager` constructor default and `compose.yaml` `SESSION_LIFETIME` are `3600`.
- [x] `SessionManager::start()` falls back to file sessions on a Redis-reachability probe failure —
      verified: PHPStan level 5 clean, Unit suite 72/72 pass. (Automated R-6 integration test not yet
      written — see `docs/quality/tech-debt.md` TDB-R09.)
- [x] `ProductService::findBySku()` reads `product:<SKU>` cache-aside with post-commit invalidation on
      every product write path; stock lookups (`getAvailability()`,
      `getStockBreakdownByProduct()`) always hit `ProductStockRepositoryInterface` live, never the cache.
- [x] `CacheService` degrades to no-op when Memcached is unavailable — every cache read then falls
      through to MySQL with no application-visible failure.

## References

- `docs/planning/master-project-specification.md` §22 (Redis), §23 (Memcached), §24 (invalidation),
  §25 (failure handling), §30.1 (this ADR's original outline)
- `docs/quality/tech-debt.md` TDB-R09 (C-01/C-02), TDB-R10 (C-08)
- `app/Core/SessionManager.php`, `app/Core/CacheService.php`, `app/Service/ProductService.php`
- ADR-002 (concurrency strategy — the boundary this ADR protects)

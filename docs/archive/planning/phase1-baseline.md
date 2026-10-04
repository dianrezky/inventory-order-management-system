# PHASE 1 — PRODUCT FOUNDATION, REQUIREMENTS & DECISION GOVERNANCE

Project: **Inventory & Order Management System — Intermediate Programmer Final Project**
Governing specification: the approved/frozen Phase 1 prompt, executed as-is.
Output order: the frozen §25 CANONICAL OUTPUT ORDER (31 sections). No second order scheme.

Authoritative inputs consumed:

```text
Project Brief - Programmer.pdf              → SRC-001  (19 pages, read in full)
Confirmed User Decisions                    → SRC-002
Existing UI/UX Baseline                     → SRC-003
docs/planning/pre-coding-analysis.md        → SRC-004  (Derived Analysis, non-authoritative)
```

Per the approved sequencing correction, the pre-coding analysis is consumed as **analysis input at
authority rank 4** — below a confirmed decision, above a derived rule. It creates no `SRC-*` row of
its own and is referenced under the existing SRC-004 category. Requirement candidates from it become
requirements **only** by receiving a canonical row in §11.

---

# 01. EXECUTIVE BASELINE SUMMARY

Classification: **INDEX / SUMMARY**

| Metric | Value |
|---|---|
| Canonical requirements | **28** (17 Product + 11 Technical) |
| Official brief IDs adopted | 26 |
| Phase 1 derived IDs created | 2 (`MSTR-01`, `ENV-01`) |
| Source records | 4 (`SRC-001`…`SRC-004`) |
| Decision records | 16 (`DEC-001`…`DEC-016`) |
| Conflict records | 4 (`CON-001`…`CON-004`) — all resolved |
| Assumption records | 3 (`ASM-001`…`ASM-003`) — 1 mandatory-open, 2 accepted |
| Supporting matrices | 15 |
| Pre-coding findings consumed | 62 `CAND` · 3 `DISC` · 7 `GAP` |
| Priority distribution | P0 = 10 · P1 = 10 · P2 = 8 · P3 = 0 |
| Requirement status | All `DOCUMENTED` |

**Phase 1 exit status: PHASE 1 COMPLETE — with one mandatory assumption carried open (`ASM-001`).**
See §31 for the exact basis of that declaration and why it does not block the baseline.

Headline governance outcomes:

- The brief's full official ID set (26) is adopted, resolving `CON-004` against the 16-ID catalogue
  listed in the frozen prompt's §10. The brief is the higher authority.
- Two requirement IDs were created — and only two — because mandatory brief obligations
  (supplier/customer master data; Docker reproducibility) had no official ID. Both are recorded as
  decisions, not silent additions.
- Redis and Memcached appear **zero times** in the brief. They survive as confirmed user decisions
  (`DEC-005`, `DEC-006`), strictly scoped, and are recorded as decisions beyond the brief
  (`CON-002`, `CON-003`) — never as brief requirements.
- Sales Order rejection has no terminal status in the brief. Resolved to `Cancelled` as a derived
  product rule (`DEC-009`) **and** carried as an open mandatory assumption (`ASM-001`) requiring
  trainer confirmation per brief FAQ 12. Not silently chosen.

---

# 02. PROJECT FOUNDATION

Classification: **CANONICAL OWNER**

§6.2 Problem Statement, §6.3 Product Vision, §6.5 Actors, §6.6 Scope, and §6.7 Out of Scope
formalize the corresponding pre-coding analysis outputs. Phase 1 holds the canonical statement; the
pre-coding artifact is the input, not a competing definition.

## 2.1 Project Identity

| Attribute | Value | Source |
|---|---|---|
| Project name | Inventory & Order Management System | SRC-001 |
| Assessment | Intermediate Programmer Final Project, PT Neuronworks Indonesia — Participant Guide Edisi 1.0, Oktober 2026 | SRC-001 |
| Project type | Individual assessment project — source code, repository, and evidence produced by the participant alone | SRC-001 |
| Product type | Web-based inventory and order management application, 3 roles, multi-warehouse | SRC-001 |
| Target outcome | Score ≥ 80 with zero critical failure | SRC-001 |

## 2.2 Problem Statement

Warehouse and sales teams have no single system in which product data, per-warehouse stock,
supplier purchases, and customer sales are recorded such that every stock figure is accountable —
including when two stock operations run concurrently — and in which no single role can both
originate and approve the same transaction.

Constituent problems:

| ID | Problem | Source |
|---|---|---|
| PS-1 | Stock is held across multiple warehouses with no per-location record of quantity | SRC-001 §1 |
| PS-2 | Supplier purchases and customer sales are not tracked through a controlled lifecycle | SRC-001 §1 |
| PS-3 | Stock figures are not traceable to the movement that produced them | SRC-001 §1, §1.3 |
| PS-4 | Concurrent stock operations can oversell and corrupt stock | SRC-001 §1, ARCH-02 |
| PS-5 | One role could both create and approve the same transaction | SRC-001 §1, §1.2 |

## 2.3 Product Vision

A web-based inventory and order management system in which every stock figure is accountable to a
traceable ledger entry, purchase and sales transactions move through controlled lifecycles, and no
single role can both create and approve the same transaction — remaining correct when two stock
operations run at the same time.

## 2.4 Product Objectives

**Functional**

| # | Objective | Source |
|---|---|---|
| OBJ-F1 | Record products and manage stock across multiple warehouses | SRC-001 WH-01 |
| OBJ-F2 | Process supplier purchases through PO lifecycle and goods receipt | SRC-001 PO-01 |
| OBJ-F3 | Process customer sales through SO lifecycle, approval, and goods issue | SRC-001 SO-01 |
| OBJ-F4 | Present role-scoped dashboards and CSV reports computed by aggregation | SRC-001 DASH-01, REPORT-01 |
| OBJ-F5 | Expose at least one JSON API endpoint as a contract separate from HTML pages | SRC-001 API-01 |
| OBJ-F6 | Provide a standalone low-stock summary script outside the web request cycle | SRC-001 JOB-01 |

**Data Integrity**

| # | Objective | Source |
|---|---|---|
| OBJ-D1 | Every stock movement recorded on the stock ledger; ProductStock consistent with StockLedger | SRC-001 §1.3 |
| OBJ-D2 | Stock quantity never negative | SRC-001 §1.3, ARCH-02 |
| OBJ-D3 | Concurrent goods issues produce a correct final stock — no oversell, no lost update | SRC-001 ARCH-02 |
| OBJ-D4 | Products, suppliers, customers deactivated rather than permanently deleted | SRC-001 §1.3, PRD-01 |

**Security**

| # | Objective | Source |
|---|---|---|
| OBJ-S1 | Authorization always enforced server-side, including segregation of duties | SRC-001 §1.2, §4.2 |
| OBJ-S2 | Passwords stored with the PHP password API; session ID renewed after login | SRC-001 AUTH-01, §4.2 |
| OBJ-S3 | All input-bearing queries use prepared statements; output escaped before HTML | SRC-001 §4.2, DB-01 |

**Usability**

| # | Objective | Source |
|---|---|---|
| OBJ-U1 | Login, dashboard, list, detail, and form usable at 360px and desktop | SRC-001 UI-01 |

**Quality**

| # | Objective | Source |
|---|---|---|
| OBJ-Q1 | Layered architecture with Dependency Inversion at the repository boundary | SRC-001 ARCH-01 |
| OBJ-Q2 | Business logic testable without a real database connection | SRC-001 ARCH-01 |
| OBJ-Q3 | Unit + integration tests and static analysis at brief minimums | SRC-001 TEST-01…03 |
| OBJ-Q4 | No unnecessary abstraction or layer that solves no real problem | SRC-001 §0 |

**Assessment**

| # | Objective | Source |
|---|---|---|
| OBJ-A1 | Design decisions documented and defensible — class diagrams, ADR, refactoring log, critique | SRC-001 DESIGN-01…04 |
| OBJ-A2 | Reproducible from a clean environment via Docker Compose | SRC-001 §5.1 |

## 2.5 Actors

```text
Admin
Sales
Warehouse Staff
```

Stored role enum values are `Admin`, `Sales`, `WarehouseStaff`; the display label for the third is
"Warehouse Staff" (`DEC-011`).

Non-actor participants recorded for completeness: **Script Operator** (manual CLI invoker, JOB-01)
and **API Consumer** (authenticated caller, API-01). Neither is a distinct role value.

## 2.6 Scope

The 28 canonical requirements in §11, comprising:

```text
Authentication & User          AUTH-01  AUTH-02  USR-01
Master Data                    PRD-01   MSTR-01  WH-01
Purchase & Receipt             PO-01
Sales, Approval & Issue        SO-01
List, Find, Dashboard, Report  VIEW-01  FIND-01  DASH-01  REPORT-01
API                            API-01
Validation, Error, UI          VAL-01   ERR-01   UI-01
Database & Job                 DB-01    JOB-01
Architecture                   ARCH-01  ARCH-02
Environment                    ENV-01
Testing                        TEST-01  TEST-02  TEST-03
Design Evidence                DESIGN-01  DESIGN-02  DESIGN-03  DESIGN-04
```

Minimum demo data is in scope under DB-01 (SRC-001 §7.1): 1 Admin, ≥2 Sales, ≥2 Warehouse Staff,
≥2 warehouses, 30 products with reorder-point variation including some below reorder point, ≥25
combined PO+SO with status variation including PendingApproval and Cancelled.

## 2.7 Out of Scope

```text
Microservices
Real Message Queue
Kubernetes
CI/CD
Cloud Deployment
Mobile Application
Real-time Notification
Automatic Server Scheduler
Automated E2E
```

Also out of scope by SRC-001: **public registration** (USR-01, FAQ 11 — all accounts created by
Admin), and **stock adjustment workflow** (`DEC-010` — the `Adjustment` ledger enum is retained,
no adjustment use case is in Phase 1 scope).

Bonus features (SRC-001 §4.4) are outside the requirement baseline and cannot substitute for a
mandatory requirement: simulated email notification, master-data audit trail, self-built chart
dashboard, additional integration tests.

## 2.8 Success Criteria

| # | Criterion | Source |
|---|---|---|
| SC-1 | Score ≥ 80 | SRC-001 cover, §8.2 |
| SC-2 | Zero critical failure | SRC-001 §8.2 |
| SC-3 | All mandatory requirements tested on the final release/tag | SRC-001 §10 |
| SC-4 | Application and database run from a clean folder via Docker | SRC-001 §10, §5.1 |
| SC-5 | Unit + integration tests run by one command and all pass | SRC-001 §10 |
| SC-6 | Static analysis report with zero critical error | SRC-001 §10, TEST-03 |
| SC-7 | Initial and as-built class diagrams present and traceable to actual code | SRC-001 §10, DESIGN-01 |
| SC-8 | Goods issue/receipt demonstrably transactional; oversell not reproducible | SRC-001 §10, ARCH-02 |
| SC-9 | Segregation of duties enforced server-side, not only in UI | SRC-001 §10, §1.2 |
| SC-10 | Search, filter, sort, pagination, 3-role dashboards, and JSON endpoint demonstrable | SRC-001 §10 |

## 2.9 Critical Failure Criteria

Ten conditions, verbatim scope from SRC-001 §8.2. Canonical mapping to requirements is owned by the
CRITICAL FAILURE MATRIX (§19.14); the conditions themselves are:

| ID | Condition |
|---|---|
| CF-1 | Application or database cannot run via Docker after reasonable setup |
| CF-2 | Core login–PO/SO–stock flow non-functional, or core features are display-only without real process/data |
| CF-3 | Project uses a prohibited backend/frontend framework, ORM, or DI container framework |
| CF-4 | No valid unit and integration tests, or all tests fail on the final release |
| CF-5 | Plaintext password, active secret in repository, raw user-input query concatenation, or authorization only in frontend |
| CF-6 | Stock changed directly without service/ledger, leaving StockLedger inconsistent with ProductStock |
| CF-7 | Goods issue/receipt not transactional, so oversell is reproducible by the assessor |
| CF-8 | Class diagram does not reflect actual code and cannot be traced during defense |
| CF-9 | Participant cannot explain own architecture/design decisions, or evidence shows plagiarism |
| CF-10 | Material AI or external-source use deliberately concealed |

---

# 03. SOURCE REGISTRY

Classification: **CANONICAL OWNER** — the single canonical owner of `SRC-*`.

| Source ID | Source Name | Type | Authority | Location / Reference | Scope |
|---|---|---|---|---|---|
| SRC-001 | Project Brief - Programmer.pdf | Official Brief | Highest | Official project brief | Official requirements |
| SRC-002 | User Confirmed Decisions | User Decision | Highest for explicit decisions | Current project conversation | Confirmed user decisions |
| SRC-003 | Existing UI/UX Baseline | Existing Product Artifact | Binding for UI baseline | Existing UI/UX artifact | Existing UI/UX constraints |
| SRC-004 | Agent Analysis | Derived Analysis | Non-authoritative | Generated during Phase 1 | Interpretation / mapping only |

**Four rows. No fifth row created.** The pre-coding analysis
(`docs/planning/pre-coding-analysis.md`) is consumed under SRC-004's category — Derived Analysis,
non-authoritative, interpretation/mapping only — per the approved sequencing correction, which
states that no new `SRC-*` entry is created for pre-coding artifacts.

No second source list, source catalog, source matrix, source register, or source mapping table
exists anywhere in this baseline.

---

# 04. DECISION REGISTRY

Classification: **CANONICAL OWNER** — the single canonical owner of `DEC-*`.

| Decision ID | Decision | Status | Source ID | Rationale |
|---|---|---|---|---|
| DEC-001 | PHP version fixed at 8.3.20 | Confirmed | SRC-002 | User explicitly fixed target version |
| DEC-002 | Bootstrap forbidden | Confirmed | SRC-002 | User explicitly prohibited Bootstrap |
| DEC-003 | All CSS/JS frameworks forbidden | Confirmed | SRC-002 | User explicitly prohibited frameworks |
| DEC-004 | MySQL is permanent business source of truth | Confirmed | SRC-002 | User-selected infrastructure strategy |
| DEC-005 | Redis is authentication/session state only, TTL 3600 seconds | Confirmed | SRC-002 | User-selected infrastructure strategy |
| DEC-006 | Memcached is ephemeral read-cache only | Confirmed | SRC-002 | User-selected infrastructure strategy |
| DEC-007 | Existing UI/UX is the baseline | Confirmed | SRC-002, SRC-003 | Existing UI decision |
| DEC-008 | Requirement definitions use one canonical owner | Confirmed | SRC-002 | Define once → reference everywhere |
| DEC-009 | Sales Order rejection results in status `Cancelled`; no new terminal status is introduced | Derived | SRC-001 | SRC-001 §1.3 permits `Cancelled` at any stage before `Fulfilled`, and §1.1/§1.2 require a reject capability. `Cancelled` is the only brief-supported terminal state for a rejected order. Carried as `ASM-001` pending trainer confirmation per SRC-001 FAQ 12. Resolves pre-coding finding `GAP-002` |
| DEC-010 | `Adjustment` is retained as a StockLedger movement-type value; no stock adjustment workflow is in Phase 1 scope | Confirmed | SRC-001 | SRC-001 §1.3 lists `Adjustment` as a ledger movement type but defines no requirement, actor, or workflow that creates one. Inventing a workflow would add product scope. Resolves pre-coding finding `GAP-003` |
| DEC-011 | Role enum values are `Admin`, `Sales`, `WarehouseStaff`; the display label for the third role is "Warehouse Staff" | Derived | SRC-001 | SRC-001 §1.3 states the stored values; §1.1 and §1.2 use the spaced label. One actor, two representations. Resolves pre-coding findings `GAP-004` and `DISC-001` |
| DEC-012 | Sales has no Sales Order approval capability whatsoever — not only on own orders | Derived | SRC-001 | SRC-001 §1.2 table denies Sales approval outright ("Tidak, meski order miliknya sendiri"); SO-01 adds "termasuk order miliknya sendiri" as emphasis, not a narrower carve-out. Resolves pre-coding finding `GAP-005` |
| DEC-013 | The tabular Reports surface (Stock Movement + Order Status, IDR) is the canonical report UI for REPORT-01; the chart-bearing Reports variant is not adopted in Phase 1 | Confirmed | SRC-002, SRC-003 | SRC-003 contains two Reports screens. The tabular variant matches REPORT-01's two required exports and the project's IDR currency; charts are bonus per SRC-001 §4.4. Resolves pre-coding finding `GAP-006` |
| DEC-014 | The full official requirement ID set of SRC-001 (26 IDs) is adopted into the CANONICAL DETAILED REQUIREMENT MATRIX | Confirmed | SRC-001, SRC-002 | The frozen Phase 1 prompt §10 listed 16 official IDs; SRC-001 defines 26, all mandatory. SRC-001 is the higher authority. Resolves `CON-004` |
| DEC-015 | Requirement ID `MSTR-01` is created for Supplier and Customer master-data management | Confirmed | SRC-001, SRC-002 | SRC-001 §1.2 mandates "Mengelola master data (produk/gudang/supplier/customer)" and §1.3 defines Supplier/Customer entities, but attaches no official ID. Without a canonical row a mandatory capability would be untraceable |
| DEC-016 | Requirement ID `ENV-01` is created for Docker/environment reproducibility | Confirmed | SRC-001, SRC-002 | SRC-001 §5.1 and §4 Environment are mandatory and CF-1 makes failure critical, but no official ID exists. DB-01 covers schema/seed only |

**16 decisions. No decision invented to populate a column** — `DEC-009`…`DEC-016` each resolve a
specific pre-coding finding or a specific gap in the ID set, and each names the finding it resolves.

No Decision Register, Decision Log, Decision Matrix, or Decision Catalog exists as a separate
decision authority.

---

# 05. CONFLICT GOVERNANCE

Classification: **CANONICAL OWNER**

Resolution rule: higher authority wins. Where an explicit user decision operates beyond the brief,
both the decision and the conflict are recorded.

| Conflict ID | Topic | Source A | Source B | Resolution | Decision ID | Status |
|---|---|---|---|---|---|---|
| CON-001 | PHP version | SRC-001 states "PHP 8.2+" (cover table, §4) | SRC-002 fixes PHP 8.3.20 | **No conflict.** 8.3.20 satisfies "8.2+". The user decision narrows an open range rather than contradicting it. `DEC-001` stands unchanged | DEC-001 | CLOSED |
| CON-002 | Redis | SRC-001 does not mention Redis anywhere (0 occurrences in 19 pages) | SRC-002 `DEC-005` introduces Redis for authentication/session and temporary security state, TTL 3600s | **Decision beyond the brief.** SRC-001 neither requires nor prohibits Redis; it is absent from the §4 prohibition list. A confirmed user decision outranks a derived rule, so `DEC-005` stands — but Redis is **not** a brief requirement and must not be recorded as one. Scope is strictly bounded by §20. Residual assessment exposure recorded as `ASM-002` | DEC-005 | RESOLVED |
| CON-003 | Memcached | SRC-001 does not mention Memcached anywhere (0 occurrences in 19 pages) | SRC-002 `DEC-006` introduces Memcached as ephemeral read cache | **Decision beyond the brief.** Same treatment as `CON-002`: absent from the §4 prohibition list, so `DEC-006` stands, is not a brief requirement, and is strictly bounded by §21. Residual assessment exposure recorded as `ASM-002` | DEC-006 | RESOLVED |
| CON-004 | Official requirement ID set | Frozen Phase 1 prompt §10 lists 16 official IDs | SRC-001 defines 26 official IDs, all mandatory (§2 and §3) | **SRC-001 wins.** The prompt's §10 list was authored before the brief was read and is a catalogue seed, not a source. All 26 brief IDs are adopted; the 16 are a subset and none is dropped | DEC-014 | RESOLVED |

**4 conflict records. 0 unresolved conflicts.** No separate conflict registry exists.

---

# 06. ASSUMPTION REGISTER

Classification: **CANONICAL OWNER**

| Assumption ID | Assumption | Basis | Authority if wrong | Impact | Mandatory | Status |
|---|---|---|---|---|---|---|
| ASM-001 | A rejected Sales Order terminates at status `Cancelled` | `DEC-009`, derived from SRC-001 §1.3 permitted `Cancelled` at any stage before `Fulfilled` | SRC-001 / trainer clarification per FAQ 12 | If the trainer specifies a distinct rejection state, the SO status model, STATE MATRIX rows for SO rejection, and the SO detail UI state gain one value. Contained: one enum value, one transition row, one badge | **YES** | **OPEN** — requires trainer confirmation |
| ASM-002 | Strictly scoped Redis (session/security state only) and Memcached (ephemeral read cache only) do not constitute "layer tambahan yang tidak menyelesaikan masalah nyata" under SRC-001 §0 | `DEC-005`, `DEC-006`; SRC-001 §4 does not prohibit either; §0 penalises unjustified complexity | SRC-001 §0 scoring, assessor judgement at defense | Scoring exposure on code-quality if the participant cannot defend why each exists. Not a critical failure — CF-3 lists only frameworks/ORM/DI containers. Mitigation: both bounded in §20/§21 and each requires a defensible rationale | No | ACCEPTED |
| ASM-003 | Applying the SRC-003 baseline's own documented collapsed-rail and mobile-drawer behaviour to product screens is baseline **application**, not redesign | SRC-003 documents the behaviour on the shell blueprint but implements it only there; UI-01 requires 360px usability on product screens | SRC-003 / `DEC-007` | If treated as redesign, UI-01 cannot be satisfied without a decision to amend the baseline. Recorded as a Required Adjustment in §23, not a redesign | No | ACCEPTED |

**3 assumptions. 1 mandatory and open (`ASM-001`).** It is not hidden: it is declared here, in
§05 via `DEC-009`, in §17, in §19.3, and in §31.

---

# 07. TECHNICAL BASELINE

Classification: **CANONICAL OWNER** — owns the **approved technical constraint baseline** only.
It is not a technical requirement source; technical requirements are owned by §11.

## Backend

```text
PHP 8.3.20                        [DEC-001, satisfies SRC-001 "PHP 8.2+"]
Native OOP                        [SRC-001 §0, §4]
```

## Frontend

```text
HTML5                             [SRC-001 §4]
Custom CSS                        [SRC-001 §4 "CSS buatan peserta"]
Vanilla JavaScript                [SRC-001 §0, §4]
Fetch API                         [SRC-001 §0, §4]
```

## Database

```text
MySQL 8                           [SRC-001 §4, DEC-004]
PDO                               [SRC-001 §4, DB-01]
Prepared Statements               [SRC-001 §4, §4.2, DB-01]
Explicit Transactions             [SRC-001 §4, DB-01, ARCH-02]
```

## Architecture

```text
Controller
→ Service
→ Repository Interface
→ Concrete Repository
→ PDO/MySQL                       [SRC-001 ARCH-01, §4.1]
```

## Testing

```text
PHPUnit                           [SRC-001 §4, TEST-01, TEST-02]
PHPStan 5+ OR PHPCS PSR-12        [SRC-001 TEST-03]
```

## Deployment

```text
Dockerfile                        [SRC-001 §4, §5.1]
Docker Compose                    [SRC-001 §0, §4, §5.1]
.env                      [SRC-001 §4, §5.1]
```

## Infrastructure

```text
MySQL
→ permanent business source of truth        [DEC-004, SRC-001 §4]
Redis
→ authentication/session state
→ temporary security state
→ TTL 3600 seconds                          [DEC-005; not from SRC-001 — see CON-002]
Memcached
→ ephemeral read cache                      [DEC-006; not from SRC-001 — see CON-003]
```

## Stock

```text
ProductStock and StockLedger are authoritative in MySQL       [DEC-004, SRC-001 §1.3]
Stock mutation requires an explicit database transaction       [SRC-001 DB-01, ARCH-02]
Stock concurrency is guaranteed by the database                [SRC-001 ARCH-02, DEC-005 exclusion]
```

The baseline references existing `SRC-*` / `DEC-*` and creates none. It does not own project
decisions, product or technical requirements, business rules, acceptance criteria, detailed input,
state transitions, authorization rules, transaction design, concurrency design, security controls,
detailed Redis/Memcached usage mapping, implementation code, class design, or schema design.

---

# 08. TECHNOLOGY PROHIBITION MATRIX

Classification: **CANONICAL OWNER** — owns forbidden technology records. Does not redefine §07.

| Forbidden Item | Protected Boundary | Architectural Impact | Severity | Detection | Treatment |
|---|---|---|---|---|---|
| Laravel | Backend framework boundary | Replaces layered architecture; defeats ARCH-01 | CRITICAL (CF-3) | `composer.json` inspection; namespace scan | Reject; remove dependency |
| Symfony | Backend framework boundary | Same as Laravel | CRITICAL (CF-3) | `composer.json`; namespace scan | Reject; remove dependency |
| CodeIgniter | Backend framework boundary | Same as Laravel | CRITICAL (CF-3) | `composer.json`; namespace scan | Reject; remove dependency |
| Slim | Backend framework boundary | Framework routing replaces Controller layer | CRITICAL (CF-3) | `composer.json` | Reject; remove dependency |
| Other Backend Framework | Backend framework boundary | Same | CRITICAL (CF-3) | `composer.json` review | Reject |
| ORM | Repository boundary | Bypasses repository contract; defeats ARCH-01 DIP | CRITICAL (CF-3) | `composer.json`; SRC-001 FAQ 1 | Reject; hand-written repositories with PDO |
| Eloquent | Repository boundary | Same as ORM | CRITICAL (CF-3) | `composer.json` | Reject |
| Doctrine | Repository boundary | Same as ORM | CRITICAL (CF-3) | `composer.json` | Reject |
| Query Builder Framework | Repository boundary | Hides SQL; weakens prepared-statement guarantee | HIGH | `composer.json`; repository code review | Reject; explicit PDO prepared statements |
| DI Container Framework | Service construction boundary | Replaces constructor injection evidence for ARCH-01 | CRITICAL (CF-3) | `composer.json`; SRC-001 FAQ 2 | Reject; manual constructor injection |
| PHP-DI | Service construction boundary | Same | CRITICAL (CF-3) | `composer.json` | Reject |
| Bootstrap | Frontend styling boundary | Replaces participant-authored CSS | CRITICAL (CF-3) | Asset and markup scan; `DEC-002` | Reject; custom CSS |
| Tailwind CSS | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| Bulma | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| Foundation | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| Materialize | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| UIkit | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| Semantic UI | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| jQuery | Frontend scripting boundary | Replaces Vanilla JS requirement | CRITICAL (CF-3) | Script tag and bundle scan | Reject; Vanilla JS + Fetch API |
| React | Frontend scripting boundary | Same | CRITICAL (CF-3) | `package.json`; script scan | Reject |
| Vue | Frontend scripting boundary | Same | CRITICAL (CF-3) | `package.json`; script scan | Reject |
| Angular | Frontend scripting boundary | Same | CRITICAL (CF-3) | `package.json`; script scan | Reject |
| Svelte | Frontend scripting boundary | Same | CRITICAL (CF-3) | `package.json`; script scan | Reject |
| Alpine.js | Frontend scripting boundary | Same | CRITICAL (CF-3) | Script scan; `DEC-003` | Reject |
| CSS Framework | Frontend styling boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| JS Framework | Frontend scripting boundary | Same | CRITICAL (CF-3) | Asset scan; `DEC-003` | Reject |
| Admin Template | UI authorship boundary | Ready-made admin template replaces participant UI work | CRITICAL (CF-3) | Markup/asset provenance review | Reject |
| UI Kit | UI authorship boundary | Same | HIGH | Asset provenance review | Reject |
| Component Framework | UI authorship boundary | Same | CRITICAL (CF-3) | Asset scan | Reject |
| NoSQL as Primary DB | Persistence authority boundary | Breaks MySQL as business source of truth | CRITICAL (CF-3) | Infrastructure and config review; `DEC-004` | Reject; MySQL primary |
| Raw User-Input SQL | Query safety boundary | SQL injection; breaches DB-01 and §4.2 | CRITICAL (CF-5) | Static analysis; query concatenation grep | Reject; prepared statements only |
| Direct Stock Mutation | Stock integrity boundary | ProductStock changed without StockLedger; breaks OBJ-D1 | CRITICAL (CF-6) | Code review of stock write paths | Reject; mutate only via stock service in one transaction |
| Service Direct PDO | Dependency inversion boundary | Service depends on PDO; defeats ARCH-01 and testability | HIGH | Service constructor and body review | Reject; depend on repository interface |
| Hidden Infrastructure Creation | Dependency injection boundary | `new PDO()` inside Service; defeats ARCH-01 | HIGH | Grep for infrastructure instantiation in Service | Reject; constructor injection |
| Global Infrastructure Singleton | Dependency injection boundary | Hidden global state; breaks test isolation (TEST-01 FIRST) | MEDIUM | Static analysis; global/static scan | Reject; inject explicitly |
| Superglobal Business Dependency | Layer boundary | Business logic reads `$_SESSION`/`$_POST`; defeats ARCH-01 | HIGH | Grep superglobals in Service | Reject; pass values inward from Controller |
| Redis as Business Source of Truth | Persistence authority boundary | Business data outside MySQL; breaches `DEC-004`, `DEC-005` | HIGH | Redis key inspection; §20 boundary | Reject; MySQL authoritative |
| Memcached as Business Source of Truth | Persistence authority boundary | Same; breaches `DEC-004`, `DEC-006` | HIGH | Cache key inspection; §21 boundary | Reject; MySQL authoritative |
| Redis-only Stock Lock | Stock concurrency boundary | Correctness depends on cache infrastructure, not the database; breaches ARCH-02 intent | HIGH | Concurrency design review; §20, §22 | Reject; database concurrency control primary |
| Unnecessary Architecture | Design economy boundary | Layers/patterns that solve no real problem; scored negatively | MEDIUM | Design review against SRC-001 §0; ADR justification | Reject or justify in `DESIGN-02` ADR |

**40 prohibition records.**

---

# 09. ARCHITECTURE BOUNDARY MATRIX

Classification: **CANONICAL OWNER** — owns layer/responsibility boundaries. Does not redefine §07.

| Boundary | Owns | May Depend On | Must Not Become Dependent On |
|---|---|---|---|
| Controller | HTTP concerns — request parsing, input marshalling, response formatting, HTTP status selection, authentication-boundary invocation, JSON vs HTML representation | Service; framework-free PHP request/response primitives | PDO; SQL; Repository concrete classes; business rules; transaction orchestration; stock mutation logic |
| Service | Business rules; use-case orchestration; authorization decisions; segregation-of-duties enforcement; transaction orchestration; stock operation orchestration | Repository **interfaces**; other Services where a real use case requires it; injected transaction boundary | PDO; superglobals (`$_SESSION`, `$_POST`, `$_GET`); HTTP implementation details; Controller; concrete Repository classes; Redis or Memcached clients directly |
| Repository Interface | Persistence contract — the operations business logic needs, expressed without persistence detail | Domain/Entity types | Business rules; HTTP concerns; session concerns; PDO types; SQL |
| Concrete Repository | PDO persistence implementation; SQL; prepared-statement construction; result mapping to Entity | PDO; MySQL; Repository Interface it implements; Entity | Controller; Service; business rules; HTTP concerns |
| Entity / Domain | Business data structure and invariants intrinsic to the data | Nothing outside the domain | Controller; PDO; Repository; HTTP; session |
| MySQL | Permanent business truth — User, Warehouse, Category, Product, ProductStock, Supplier, Customer, PurchaseOrder(+Item), SalesOrder(+Item), StockLedger; referential integrity; `quantity >= 0`; transaction and concurrency control for stock | — | Redis; Memcached; application availability |
| Redis | Authentication session state; temporary security state; TTL 3600s | — | Business data; stock correctness; being read as a source of truth |
| Memcached | Regeneratable read cache for master-data reads | MySQL as the authority it caches | Business state; write path correctness; being read as a source of truth |
| UI | Presentation and interaction; 360px and desktop layout; labels, focus state, contrast; empty states | JSON/HTML produced by Controller | Authorization enforcement (server-side only); business rules; direct stock mutation |
| Standalone Script (JOB-01) | CLI entry point for the low-stock summary, outside the web request cycle | Service; Repository interface | HTTP request lifecycle; session; Controller |

---

# 10. PRODUCT & TECHNICAL REQUIREMENT BASELINE

Classification: **INDEX / SUMMARY**

Both Product and Technical requirements are defined only in §11. This section states the
distinction and the composition of the baseline; it defines no requirement.

**Product Requirement** answers *WHAT must the system do?* — user, business behavior, workflow,
business rules, acceptance.

**Technical Requirement** answers *WHAT technical constraint must the implementation satisfy?* —
technology, architecture, persistence, security mechanism, testing, deployment, infrastructure.

| Type | Count | Requirement IDs |
|---|---|---|
| Product | 17 | AUTH-01, AUTH-02, USR-01, PRD-01, MSTR-01, WH-01, PO-01, SO-01, VIEW-01, FIND-01, DASH-01, REPORT-01, API-01, VAL-01, ERR-01, UI-01, JOB-01 |
| Technical | 11 | DB-01, ARCH-01, ARCH-02, ENV-01, TEST-01, TEST-02, TEST-03, DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04 |
| **Total** | **28** | |

| Origin | Count | IDs |
|---|---|---|
| Official SRC-001 ID adopted | 26 | All except MSTR-01 and ENV-01 |
| Phase 1 derived ID created | 2 | MSTR-01 (`DEC-015`), ENV-01 (`DEC-016`) |

---

# 11. CANONICAL DETAILED REQUIREMENT MATRIX

Classification: **CANONICAL OWNER**

Canonical owner of Product Requirements, Technical Requirements, and **Business Rule canonical
statements**. One canonical row per requirement. No requirement or business rule is defined
anywhere else in this baseline.

Reference convention: `<MATRIX NAME> § <Requirement ID>` resolves to that requirement's rows in the
named supporting matrix (§19). `N/A` means the reference does not apply to this requirement — it is
a resolved value, not a placeholder.

---

## AUTH-01

- **Requirement ID:** AUTH-01
- **Type:** Product
- **Category:** Authentication & User
- **Name:** Login dan Session
- **Source ID:** SRC-001 (§2.1 AUTH-01, §4.2), SRC-004 (CAND-001…005)
- **Decision ID:** N/A
- **Actor:** Admin, Sales, Warehouse Staff
- **Business Goal:** Establish a role-scoped authenticated session so each actor sees only what their role permits.
- **Requirement Statement:** A user signs in with a valid email and password; the session determines the role-appropriate view. Protected areas are inaccessible without a session, and the session ID is renewed after login.
- **Business Rule Statement:** A valid, active user may authenticate. An inactive user must not authenticate. A failed credential attempt returns a safe message that does not disclose which credential part was wrong. A protected page must not be served without an authenticated session. The session identifier must be renewed on successful login. Passwords are stored using `password_hash()` and verified using `password_verify()`.
- **Preconditions:** User account exists with role and active status; password stored as a hash.
- **Trigger:** User submits the login form.
- **Main Flow:** Receive email and password → locate user by email → verify password hash → confirm active status → establish session with identity and role → renew session ID → redirect to the role dashboard.
- **Alternative Flow:** An already-authenticated user requesting the login page is directed to their role dashboard.
- **Failure Flow:** Invalid credential → safe generic message, no field-level disclosure, no session. Inactive account → refused with a safe message. Protected page requested without a session → redirect to login.
- **Postconditions:** An authenticated session exists carrying user identity and role; or no session exists and the user remains at login.
- **Input Ref:** INPUT MATRIX § AUTH-01
- **Output:** Role dashboard (HTML); safe authentication failure message; redirect to login.
- **State Change Ref:** N/A (no business object lifecycle transition)
- **Data Impact Ref:** DATA IMPACT MATRIX § AUTH-01
- **UI Ref:** UI/UX BASELINE MAPPING § AUTH-01
- **Technical Boundary Ref:** TECHNICAL BASELINE (Backend, Infrastructure); ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Redis)
- **Authorization Ref:** AUTHORIZATION MATRIX § AUTH-01
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § AUTH-01
- **Concurrency Ref:** N/A
- **Redis Ref:** REDIS MATRIX § AUTH-01
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § AUTH-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § AUTH-01 (AC-AUTH-01-1 … AC-AUTH-01-5)
- **Test Ref:** TEST TRACEABILITY MATRIX § AUTH-01
- **Evidence Ref:** EVIDENCE MATRIX § AUTH-01
- **Critical Failure Ref:** CF-2, CF-5
- **Dependency Ref:** USR-01 (accounts must exist), DB-01 (user storage), ENV-01 (runnable environment)
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Redis participation is by `DEC-005` only, not by SRC-001, which does not name Redis. Session storage may fall back per §20 without changing this requirement.

---

## AUTH-02

- **Requirement ID:** AUTH-02
- **Type:** Product
- **Category:** Authentication & User
- **Name:** Logout
- **Source ID:** SRC-001 (§2.1 AUTH-02), SRC-004 (CAND-006)
- **Decision ID:** N/A
- **Actor:** Admin, Sales, Warehouse Staff
- **Business Goal:** Let an actor deliberately end their session so access cannot be reused.
- **Requirement Statement:** A user can end their session from within the application; afterwards protected URLs cannot be reopened without logging in again.
- **Business Rule Statement:** Logout must remove authentication data from the session. After logout, a protected URL must not be served without a new authentication.
- **Preconditions:** An authenticated session exists.
- **Trigger:** User activates logout.
- **Main Flow:** Clear authentication data from the session → redirect to login.
- **Alternative Flow:** N/A
- **Failure Flow:** Attempt to reopen a protected URL after logout → redirect to login.
- **Postconditions:** No authenticated session exists.
- **Input Ref:** N/A (no user-supplied field)
- **Output:** Redirect to login.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § AUTH-02
- **UI Ref:** UI/UX BASELINE MAPPING § AUTH-02
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Redis)
- **Authorization Ref:** AUTHORIZATION MATRIX § AUTH-02
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** REDIS MATRIX § AUTH-02
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § AUTH-02
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § AUTH-02 (AC-AUTH-02-1, AC-AUTH-02-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § AUTH-02
- **Evidence Ref:** EVIDENCE MATRIX § AUTH-02
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** AUTH-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** —

---

## USR-01

- **Requirement ID:** USR-01
- **Type:** Product
- **Category:** Authentication & User
- **Name:** Manajemen User (3 role)
- **Source ID:** SRC-001 (§2.1 USR-01, §1.3, FAQ 11), SRC-004 (CAND-007…009)
- **Decision ID:** DEC-011
- **Actor:** Admin
- **Business Goal:** Keep system access aligned to current staffing, with no self-service account creation.
- **Requirement Statement:** Admin manages Sales and Warehouse Staff accounts — add, view, edit, and activate/deactivate — with unique email; roles are limited to Admin, Sales, Warehouse Staff; there is no public registration; Sales and Warehouse Staff cannot reach user-administration pages or endpoints.
- **Business Rule Statement:** A user email must be unique across all accounts. A user role must be exactly one of `Admin`, `Sales`, `WarehouseStaff`. No account may be created except by an Admin — public registration does not exist. Non-Admin roles must be denied access to user administration pages and endpoints at the server.
- **Preconditions:** Requester is an authenticated Admin.
- **Trigger:** Admin opens user administration and creates or edits an account, or toggles activation.
- **Main Flow:** List users → open create or edit form → enter name, email, password, role, active status → validate email uniqueness and role enum → hash password → persist → return to list with feedback.
- **Alternative Flow:** Toggle active status without editing other fields.
- **Failure Flow:** Duplicate email → rejected, nothing stored. Invalid role value → rejected. Non-Admin request to a user-administration page or endpoint → 403.
- **Postconditions:** User set reflects the change; passwords stored only as hashes.
- **Input Ref:** INPUT MATRIX § USR-01
- **Output:** User list and detail (HTML); validation feedback; 403 for non-Admin.
- **State Change Ref:** STATE MATRIX § USR-01 (active ⇄ inactive)
- **Data Impact Ref:** DATA IMPACT MATRIX § USR-01
- **UI Ref:** UI/UX BASELINE MAPPING § USR-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Repository Interface)
- **Authorization Ref:** AUTHORIZATION MATRIX § USR-01
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § USR-01
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § USR-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § USR-01 (AC-USR-01-1 … AC-USR-01-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § USR-01
- **Evidence Ref:** EVIDENCE MATRIX § USR-01
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** AUTH-01, DB-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** Role value vs display label resolved by `DEC-011`. SRC-001 states no deletion behaviour for users; only activation toggling is required.

---

## PRD-01

- **Requirement ID:** PRD-01
- **Type:** Product
- **Category:** Master Data
- **Name:** Produk, Kategori & Reorder Point
- **Source ID:** SRC-001 (§2.2 PRD-01, §1.3), SRC-004 (CAND-010…013)
- **Decision ID:** N/A
- **Actor:** Admin
- **Business Goal:** Maintain the catalogue that every transaction references, without destroying history.
- **Requirement Statement:** Admin manages the product catalogue used across all transaction flows, including categories and reorder point. SKU is unique; category, buy price, sell price, unit, and reorder point are validated as numbers ≥ 0 where numeric. A product already used on an order may only be deactivated, not deleted. An optional product image is uploaded with file type and size validation and stored under an unguessable random name.
- **Business Rule Statement:** Product SKU must be unique. Buy price, sell price, and reorder point must be numeric and ≥ 0. A product referenced by any order must not be permanently deleted; it may only be deactivated. An uploaded product image must pass file type and size validation and must be stored under a randomly generated, non-guessable filename. A category must exist for a product to reference it.
- **Preconditions:** Requester is an authenticated Admin; at least one category exists for product creation.
- **Trigger:** Admin creates or edits a product or category, or toggles product activation.
- **Main Flow:** Open product create/edit → enter SKU, name, category, unit, buy price, sell price, reorder point, optional image, active status → validate SKU uniqueness and numeric ≥ 0 → validate image type and size → store image under a random name → persist → return to list with feedback.
- **Alternative Flow:** Deactivate a product that is already referenced by an order. Create or edit a category with name and description.
- **Failure Flow:** Duplicate SKU → rejected, nothing stored. Negative numeric value → rejected. Invalid image type or oversize file → rejected with feedback. Delete attempt on a referenced product → refused; deactivation offered.
- **Postconditions:** Catalogue reflects the change; order history remains intact; any stored image has a random filename.
- **Input Ref:** INPUT MATRIX § PRD-01
- **Output:** Product and category list/detail (HTML); validation feedback; stored image asset.
- **State Change Ref:** STATE MATRIX § PRD-01 (active ⇄ inactive)
- **Data Impact Ref:** DATA IMPACT MATRIX § PRD-01
- **UI Ref:** UI/UX BASELINE MAPPING § PRD-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Repository Interface, Concrete Repository)
- **Authorization Ref:** AUTHORIZATION MATRIX § PRD-01
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § PRD-01
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § PRD-01
- **Security Ref:** SECURITY MATRIX § PRD-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § PRD-01 (AC-PRD-01-1 … AC-PRD-01-5)
- **Test Ref:** TEST TRACEABILITY MATRIX § PRD-01
- **Evidence Ref:** EVIDENCE MATRIX § PRD-01
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** AUTH-01, USR-01, DB-01, VAL-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** Category management is carried inside PRD-01 because SRC-001 titles the requirement "Produk, Kategori & Reorder Point"; no separate category requirement ID was created.

---

## MSTR-01

- **Requirement ID:** MSTR-01
- **Type:** Product
- **Category:** Master Data
- **Name:** Supplier & Customer Master Data
- **Source ID:** SRC-001 (§1.2 role table, §1.3 entity table, referenced by PO-01 and SO-01), SRC-004 (CAND-016, GAP-001)
- **Decision ID:** DEC-015
- **Actor:** Admin
- **Business Goal:** Maintain the counterparties that purchase and sales orders reference, without destroying history.
- **Requirement Statement:** Admin manages supplier and customer records with name, contact, address, and active status. A supplier or customer already referenced by an order may only be deactivated, not deleted. Sales and Warehouse Staff do not manage these records.
- **Business Rule Statement:** A supplier or customer referenced by any order must not be permanently deleted; it may only be deactivated. An inactive supplier must not be selectable for a new Purchase Order, and an inactive customer must not be selectable for a new Sales Order. Only Admin may create or modify supplier and customer records.
- **Preconditions:** Requester is an authenticated Admin.
- **Trigger:** Admin creates or edits a supplier or customer, or toggles activation.
- **Main Flow:** Open supplier or customer management → enter name, contact, address, active status → validate required fields → persist → return to list with feedback.
- **Alternative Flow:** Deactivate a counterparty already referenced by an order.
- **Failure Flow:** Missing required field → rejected, nothing stored. Delete attempt on a referenced counterparty → refused; deactivation offered. Non-Admin request → 403.
- **Postconditions:** Supplier and customer sets reflect the change; order history remains intact.
- **Input Ref:** INPUT MATRIX § MSTR-01
- **Output:** Supplier and customer list/detail (HTML); validation feedback; 403 for non-Admin.
- **State Change Ref:** STATE MATRIX § MSTR-01 (active ⇄ inactive)
- **Data Impact Ref:** DATA IMPACT MATRIX § MSTR-01
- **UI Ref:** UI/UX BASELINE MAPPING § MSTR-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Repository Interface)
- **Authorization Ref:** AUTHORIZATION MATRIX § MSTR-01
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § MSTR-01
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § MSTR-01
- **Security Ref:** SECURITY MATRIX § MSTR-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § MSTR-01 (AC-MSTR-01-1 … AC-MSTR-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § MSTR-01
- **Evidence Ref:** EVIDENCE MATRIX § MSTR-01
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** AUTH-01, USR-01, DB-01, VAL-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** This is a Phase 1 derived requirement ID, not an SRC-001 official ID. Created by `DEC-015` because SRC-001 mandates the capability without attaching an ID. The deactivate-not-delete rule mirrors the SRC-001 §1.3 data decision that names supplier and customer explicitly.

---

## WH-01

- **Requirement ID:** WH-01
- **Type:** Product
- **Category:** Master Data
- **Name:** Gudang & Stok Multi-Lokasi
- **Source ID:** SRC-001 (§2.2 WH-01, §1.3), SRC-004 (CAND-014, CAND-015)
- **Decision ID:** N/A
- **Actor:** Admin (manage); Admin, Sales, Warehouse Staff (view)
- **Business Goal:** Know how much of a product is held at each location, not just in aggregate.
- **Requirement Statement:** Stock of a single product may differ per warehouse. Admin manages the warehouse list; every product has a stock row per warehouse; stock display shows both the total and the per-warehouse breakdown.
- **Business Rule Statement:** Stock is warehouse-specific: exactly one ProductStock row exists per product per warehouse. A ProductStock quantity must never be negative. Total stock for a product is the sum of its per-warehouse quantities and is never stored as an independent figure.
- **Preconditions:** Requester authenticated; Admin for management.
- **Trigger:** Admin creates or edits a warehouse; any authorised actor opens a product or stock view.
- **Main Flow:** Manage warehouse (name, location, active status) → persist → each product carries a stock row per warehouse → stock views render total plus per-warehouse breakdown.
- **Alternative Flow:** Toggle warehouse active status.
- **Failure Flow:** Missing required warehouse field → rejected. Warehouse not found → 404.
- **Postconditions:** Warehouse set reflects the change; per-warehouse stock is representable and displayable.
- **Input Ref:** INPUT MATRIX § WH-01
- **Output:** Warehouse list/detail; product stock view with total and per-warehouse rows.
- **State Change Ref:** STATE MATRIX § WH-01 (active ⇄ inactive)
- **Data Impact Ref:** DATA IMPACT MATRIX § WH-01
- **UI Ref:** UI/UX BASELINE MAPPING § WH-01
- **Technical Boundary Ref:** TECHNICAL BASELINE (Stock); ARCHITECTURE BOUNDARY MATRIX (MySQL)
- **Authorization Ref:** AUTHORIZATION MATRIX § WH-01
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § WH-01
- **Concurrency Ref:** CONCURRENCY MATRIX § WH-01
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § WH-01
- **Security Ref:** SECURITY MATRIX § WH-01
- **API Ref:** API-01 consumes per-warehouse stock
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § WH-01 (AC-WH-01-1, AC-WH-01-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § WH-01
- **Evidence Ref:** EVIDENCE MATRIX § WH-01
- **Critical Failure Ref:** CF-6
- **Dependency Ref:** AUTH-01, PRD-01, DB-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** Stock quantities are never mutated by this requirement; mutation belongs to PO-01 and SO-01 through the stock service (§22).

---

## PO-01

- **Requirement ID:** PO-01
- **Type:** Product
- **Category:** Purchase Order & Goods Receipt
- **Name:** Purchase Order & Goods Receipt
- **Source ID:** SRC-001 (§2.3 PO-01, §1.3, ARCH-02 reference), SRC-004 (CAND-017…019)
- **Decision ID:** N/A
- **Actor:** Admin (create); Warehouse Staff (propose, receive); Admin (receive)
- **Business Goal:** Replenish stock from suppliers and account for arrivals so every increase is traceable.
- **Requirement Statement:** Admin or Warehouse Staff raises a Purchase Order to a supplier when stock is low, then records receipt of goods. A PO carries supplier, destination warehouse, and items (product, quantity, buy price), and follows `Draft → Ordered → PartiallyReceived/Received → Cancelled`. A goods receipt increases ProductStock and writes a `Receipt` StockLedger row in one transaction. Partial receipt is allowed and the unreceived remainder stays recorded.
- **Business Rule Statement:** A Purchase Order must reference exactly one supplier and one destination warehouse and must carry at least one item. PO status must be one of `Draft`, `Ordered`, `PartiallyReceived`, `Received`, `Cancelled`. Item quantity and buy price must be ≥ 0. A goods receipt must increase ProductStock for the ordered product at the PO destination warehouse and must write a StockLedger row of type `Receipt` referencing the PO, both inside one explicit database transaction; if either write fails, neither takes effect. A received quantity must not exceed the outstanding quantity of its PO line. Partial receipt is permitted; the outstanding quantity remains recorded until fully received. A PO becomes `Received` only when every line is fully received, otherwise `PartiallyReceived`.
- **Preconditions:** Requester authenticated and authorised; active supplier, destination warehouse, and active products exist. For receipt, the PO is `Ordered` or `PartiallyReceived`.
- **Trigger:** Low stock observed or replenishment decided; later, goods arrive at the destination warehouse.
- **Main Flow:** Create PO with supplier, destination warehouse, order date and items → validate → save as `Draft` → order the PO → status `Ordered`. On arrival: open PO → enter received quantity per line → validate against outstanding quantity → begin transaction → write `Receipt` StockLedger rows → increase ProductStock → commit → recompute PO status.
- **Alternative Flow:** Partial receipt leaves the PO `PartiallyReceived` with the remainder outstanding, and a later receipt completes it. A PO may be cancelled per its status set.
- **Failure Flow:** Missing supplier or destination warehouse → rejected. Negative quantity or price → rejected. Received quantity exceeding outstanding → rejected. Any failure inside the receipt transaction → rollback; ProductStock and StockLedger unchanged; outstanding quantity preserved.
- **Postconditions:** PO exists at a valid status; on receipt, ProductStock is increased and a consistent `Receipt` StockLedger row exists; outstanding quantities are accurate.
- **Input Ref:** INPUT MATRIX § PO-01
- **Output:** PO list/detail; receipt confirmation; StockLedger `Receipt` rows; updated stock figures.
- **State Change Ref:** STATE MATRIX § PO-01
- **Data Impact Ref:** DATA IMPACT MATRIX § PO-01
- **UI Ref:** UI/UX BASELINE MAPPING § PO-01
- **Technical Boundary Ref:** TECHNICAL BASELINE (Database, Stock); STOCK TECHNICAL BOUNDARY (§22)
- **Authorization Ref:** AUTHORIZATION MATRIX § PO-01
- **SoD Ref:** N/A (SRC-001 imposes no approval separation on PO)
- **Transaction Ref:** TRANSACTION MATRIX § PO-01
- **Concurrency Ref:** CONCURRENCY MATRIX § PO-01
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § PO-01
- **Security Ref:** SECURITY MATRIX § PO-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § PO-01 (AC-PO-01-1 … AC-PO-01-6)
- **Test Ref:** TEST TRACEABILITY MATRIX § PO-01
- **Evidence Ref:** EVIDENCE MATRIX § PO-01
- **Critical Failure Ref:** CF-2, CF-6, CF-7
- **Dependency Ref:** AUTH-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02, VAL-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** SRC-001 §1.2 grants Warehouse Staff "Boleh mengusulkan" for PO creation and "Boleh" for goods receipt; the distinction is carried in the AUTHORIZATION MATRIX.

---

## SO-01

- **Requirement ID:** SO-01
- **Type:** Product
- **Category:** Sales Order, Approval & Goods Issue
- **Name:** Sales Order, Approval & Goods Issue
- **Source ID:** SRC-001 (§2.4 SO-01, §1.1, §1.2, §1.3, ARCH-02 reference), SRC-004 (CAND-020…023)
- **Decision ID:** DEC-009, DEC-012
- **Actor:** Sales (create, submit own); Admin (create, approve, reject); Warehouse Staff (issue); Admin (issue)
- **Business Goal:** Sell from stock under authorisation, so no one both raises and approves the same order and no order is fulfilled beyond available stock.
- **Requirement Statement:** Sales creates a Sales Order, Admin approves it, Warehouse Staff fulfils it. The SO follows `Draft → PendingApproval → Approved → Fulfilled`, or `Cancelled` at any stage before `Fulfilled`. Approval authority is checked at the server: Sales cannot approve an order, including its own. Goods issue is processed only for an `Approved` SO and is rejected when available stock is insufficient; it decreases ProductStock and writes an `Issue` StockLedger row in one race-condition-safe transaction.
- **Business Rule Statement:** A Sales Order must reference exactly one customer and one source warehouse, record its creator, and carry at least one item. SO status must be one of `Draft`, `PendingApproval`, `Approved`, `Fulfilled`, `Cancelled`. `Cancelled` is permitted at any stage before `Fulfilled`. Item quantity and sell price must be ≥ 0. Only Admin may approve or reject a Sales Order; Sales must be denied approval entirely, including on its own order, and the denial must be enforced in the server authorization layer rather than by hiding UI. A rejected Sales Order terminates at `Cancelled`. Goods issue must be refused unless the SO status is `Approved`. Goods issue must be refused when available stock in the source warehouse is insufficient for the requested quantity. Goods issue must decrease ProductStock for the product at the source warehouse and write a StockLedger row of type `Issue` referencing the SO, both inside one explicit transaction that is safe against concurrent execution; ProductStock must never become negative and no update may be lost.
- **Preconditions:** Requester authenticated and authorised; active customer, source warehouse, and active products exist. For approval, the SO is `PendingApproval` and the requester is Admin. For issue, the SO is `Approved`.
- **Trigger:** Customer places an order; later, submission, approval decision, and fulfilment actions.
- **Main Flow:** Create SO with customer, source warehouse, creator and items → validate → save as `Draft` → submit → `PendingApproval` → Admin approves → `Approved` with approver recorded → Warehouse Staff issues goods → begin race-safe transaction → verify available stock → write `Issue` StockLedger row → decrease ProductStock → commit → `Fulfilled`.
- **Alternative Flow:** Admin rejects a `PendingApproval` order → terminates at `Cancelled` (`DEC-009`). An SO may be cancelled at any stage before `Fulfilled`. Admin may also create an SO.
- **Failure Flow:** Missing customer or source warehouse → rejected. Negative quantity or price → rejected. Approval attempted by Sales or Warehouse Staff → 403, no transition, including a Sales user's own order. Issue attempted on a non-`Approved` SO → refused. Insufficient available stock → issue refused, nothing mutated. Two near-simultaneous issues for the same product and warehouse → the second is rejected or deferred; no oversell, no lost update. Any failure inside the transaction → rollback.
- **Postconditions:** SO exists at a valid status with creator and, where approved, approver recorded; on issue, ProductStock is decreased, a consistent `Issue` StockLedger row exists, and stock is never negative.
- **Input Ref:** INPUT MATRIX § SO-01
- **Output:** SO list/detail; approval outcome; issue confirmation; StockLedger `Issue` rows; updated stock figures; 403 on unauthorised approval.
- **State Change Ref:** STATE MATRIX § SO-01
- **Data Impact Ref:** DATA IMPACT MATRIX § SO-01
- **UI Ref:** UI/UX BASELINE MAPPING § SO-01
- **Technical Boundary Ref:** TECHNICAL BASELINE (Database, Stock); STOCK TECHNICAL BOUNDARY (§22)
- **Authorization Ref:** AUTHORIZATION MATRIX § SO-01
- **SoD Ref:** AUTHORIZATION MATRIX § SO-01 (SoD rows SoD-1, SoD-2)
- **Transaction Ref:** TRANSACTION MATRIX § SO-01
- **Concurrency Ref:** CONCURRENCY MATRIX § SO-01
- **Redis Ref:** N/A — Redis must not be the stock concurrency mechanism (§20)
- **Memcached Ref:** MEMCACHED MATRIX § SO-01
- **Security Ref:** SECURITY MATRIX § SO-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § SO-01 (AC-SO-01-1 … AC-SO-01-8)
- **Test Ref:** TEST TRACEABILITY MATRIX § SO-01
- **Evidence Ref:** EVIDENCE MATRIX § SO-01
- **Critical Failure Ref:** CF-2, CF-5, CF-6, CF-7
- **Dependency Ref:** AUTH-01, USR-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02, VAL-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** The rejection terminal state rests on `DEC-009` and is carried as open mandatory assumption `ASM-001` pending trainer confirmation per SRC-001 FAQ 12. The scope of the Sales approval denial rests on `DEC-012`.

---

## VIEW-01

- **Requirement ID:** VIEW-01
- **Type:** Product
- **Category:** List, Find, Dashboard & Report
- **Name:** Daftar, Detail & Empty State
- **Source ID:** SRC-001 (§2.5 VIEW-01), SRC-004 (CAND-024)
- **Decision ID:** N/A
- **Actor:** Admin, Sales, Warehouse Staff
- **Business Goal:** Let each role navigate the records they are entitled to see, including when there are none.
- **Requirement Statement:** Products, Purchase Orders, and Sales Orders appear as role-appropriate list and detail pages; a no-data condition shows an informative empty state.
- **Business Rule Statement:** A list must present only records the requesting role is entitled to see. A no-data result must render an informative empty state rather than an empty table body. A detail page for a non-existent record must return 404.
- **Preconditions:** Requester authenticated.
- **Trigger:** Requester opens a product, PO, or SO list or detail page.
- **Main Flow:** Request list → scope to role → render rows → open a row → render detail.
- **Alternative Flow:** No records match → render informative empty state.
- **Failure Flow:** Record not found → 404. Record outside the requester's role scope → 403.
- **Postconditions:** Read-only; no state change.
- **Input Ref:** INPUT MATRIX § VIEW-01
- **Output:** List and detail pages (HTML); empty state; 403/404.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § VIEW-01
- **UI Ref:** UI/UX BASELINE MAPPING § VIEW-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, UI)
- **Authorization Ref:** AUTHORIZATION MATRIX § VIEW-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § VIEW-01
- **Security Ref:** SECURITY MATRIX § VIEW-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § VIEW-01 (AC-VIEW-01-1, AC-VIEW-01-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § VIEW-01
- **Evidence Ref:** EVIDENCE MATRIX § VIEW-01
- **Critical Failure Ref:** CF-2
- **Dependency Ref:** AUTH-01, PRD-01, PO-01, SO-01, ERR-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** —

---

## FIND-01

- **Requirement ID:** FIND-01
- **Type:** Product
- **Category:** List, Find, Dashboard & Report
- **Name:** Search, Filter, Sort & Pagination
- **Source ID:** SRC-001 (§2.5 FIND-01, §7.1), SRC-004 (CAND-025…028)
- **Decision ID:** N/A
- **Actor:** Admin, Sales, Warehouse Staff
- **Business Goal:** Make large record sets navigable so operators can locate a specific product or order.
- **Requirement Statement:** Products support search by name or SKU and filtering by category and stock status (low stock / normal). Orders (PO and SO) support search by number or counterparty, status filtering, and ascending/descending date sort. Main lists paginate at 10 records per page and active filters persist across page changes. Seed data provides at least 30 products and 25 combined orders so pagination is testable.
- **Business Rule Statement:** Main list pagination must be exactly 10 records per page. Active search, filter, and sort selections must remain in effect when the page changes. Product stock-status filtering must classify a product as low stock when its stock is below its reorder point, and normal otherwise. Seed data must contain at least 30 products and at least 25 combined Purchase and Sales Orders.
- **Preconditions:** Requester authenticated; seeded data present.
- **Trigger:** Requester enters a search term, selects a filter or sort, or changes page.
- **Main Flow:** Apply search, filter and sort within role scope → paginate at 10 per page → render page → preserve active criteria on page change.
- **Alternative Flow:** Clear filters and return to the unfiltered scoped list.
- **Failure Flow:** No match → informative empty state (VIEW-01).
- **Postconditions:** Read-only; no state change.
- **Input Ref:** INPUT MATRIX § FIND-01
- **Output:** Filtered, sorted, paginated list pages.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § FIND-01
- **UI Ref:** UI/UX BASELINE MAPPING § FIND-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Concrete Repository)
- **Authorization Ref:** AUTHORIZATION MATRIX § FIND-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § FIND-01
- **Security Ref:** SECURITY MATRIX § FIND-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § FIND-01 (AC-FIND-01-1 … AC-FIND-01-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § FIND-01
- **Evidence Ref:** EVIDENCE MATRIX § FIND-01
- **Critical Failure Ref:** CF-2
- **Dependency Ref:** VIEW-01, PRD-01, PO-01, SO-01, DB-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** The seed minimums are also referenced by DB-01, which owns schema and seed. FIND-01 owns the pagination and filter-persistence rules.

---

## DASH-01

- **Requirement ID:** DASH-01
- **Type:** Product
- **Category:** List, Find, Dashboard & Report
- **Name:** Dashboard sesuai Hak Akses
- **Source ID:** SRC-001 (§2.5 DASH-01, §1.2), SRC-004 (CAND-029…032)
- **Decision ID:** N/A
- **Actor:** Admin, Sales, Warehouse Staff
- **Business Goal:** Give each role a live operational picture computed from real data.
- **Requirement Statement:** Admin sees inventory value, products below reorder point, and pending orders per status. Sales sees a summary of its own orders per status. Warehouse Staff sees the goods receipt and goods issue queues and low-stock products. Every figure comes from an aggregation query, not a static value.
- **Business Rule Statement:** Every dashboard figure must be produced by an aggregation query over live data; no dashboard figure may be a stored or hard-coded value. Dashboard content must be scoped to the requesting role: Admin sees all data, Sales sees only its own orders, Warehouse Staff sees stock and fulfilment scope.
- **Preconditions:** Requester authenticated.
- **Trigger:** Requester opens the dashboard.
- **Main Flow:** Resolve role scope → run role-appropriate aggregation queries → render figures.
- **Alternative Flow:** N/A
- **Failure Flow:** Unauthenticated → redirect to login. No data in a widget → widget-scoped empty state.
- **Postconditions:** Read-only; no state change.
- **Input Ref:** N/A (no user-supplied field)
- **Output:** Role-scoped dashboard figures (HTML).
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § DASH-01
- **UI Ref:** UI/UX BASELINE MAPPING § DASH-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Concrete Repository, MySQL)
- **Authorization Ref:** AUTHORIZATION MATRIX § DASH-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** MEMCACHED MATRIX § DASH-01
- **Security Ref:** SECURITY MATRIX § DASH-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DASH-01 (AC-DASH-01-1 … AC-DASH-01-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § DASH-01
- **Evidence Ref:** EVIDENCE MATRIX § DASH-01
- **Critical Failure Ref:** CF-2
- **Dependency Ref:** AUTH-01, PRD-01, WH-01, PO-01, SO-01, DB-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** REPORT-01 must use the same aggregation basis; the shared-basis rule is owned by REPORT-01.

---

## REPORT-01

- **Requirement ID:** REPORT-01
- **Type:** Product
- **Category:** List, Find, Dashboard & Report
- **Name:** Laporan CSV
- **Source ID:** SRC-001 (§2.5 REPORT-01), SRC-004 (CAND-033, GAP-006)
- **Decision ID:** DEC-013
- **Actor:** Admin (all); Sales (own orders); Warehouse Staff (stock report)
- **Business Goal:** Let operators extract movement and order data for a period, consistent with what the dashboard shows.
- **Requirement Statement:** CSV export of stock movement (from StockLedger) and of order status within a date range, produced from the same aggregation/recap queries as the dashboard.
- **Business Rule Statement:** A CSV export must be produced from the same aggregation basis as the corresponding dashboard figure, so that report and dashboard cannot disagree. An export must be constrained to the requested date range and to the requesting role's scope.
- **Preconditions:** Requester authenticated; date range supplied.
- **Trigger:** Requester requests an export with a report type and date range.
- **Main Flow:** Validate date range and role scope → run the shared aggregation basis → stream CSV.
- **Alternative Flow:** Different date ranges produce different extracts from the same basis.
- **Failure Flow:** Invalid or inverted date range → validation message, no export. Request outside role scope → 403.
- **Postconditions:** Read-only; no state change.
- **Input Ref:** INPUT MATRIX § REPORT-01
- **Output:** CSV file — stock movement rows; order status rows.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § REPORT-01
- **UI Ref:** UI/UX BASELINE MAPPING § REPORT-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service, Concrete Repository)
- **Authorization Ref:** AUTHORIZATION MATRIX § REPORT-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A — exports read authoritative data directly (§21)
- **Security Ref:** SECURITY MATRIX § REPORT-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § REPORT-01 (AC-REPORT-01-1 … AC-REPORT-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § REPORT-01
- **Evidence Ref:** EVIDENCE MATRIX § REPORT-01
- **Critical Failure Ref:** CF-2
- **Dependency Ref:** AUTH-01, DASH-01, PO-01, SO-01, DB-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** The canonical report UI surface is fixed by `DEC-013`, resolving the two-Reports-screen ambiguity in SRC-003.

---

## API-01

- **Requirement ID:** API-01
- **Type:** Product
- **Category:** API
- **Name:** Endpoint JSON
- **Source ID:** SRC-001 (§2.6 API-01), SRC-004 (CAND-034, CAND-035)
- **Decision ID:** N/A
- **Actor:** Authenticated API consumer
- **Business Goal:** Expose a machine-readable availability contract separate from the HTML pages.
- **Requirement Statement:** At least one endpoint returns JSON as an API contract, separate from ordinary HTML pages — for example `GET /api/products/{sku}/availability` returning stock per warehouse. Authentication is checked exactly as for ordinary pages; responses use `Content-Type: application/json` and correct HTTP status codes (200/401/404), never an HTML error page.
- **Business Rule Statement:** An API response must carry `Content-Type: application/json` and must never return an HTML error page. Authentication for the API must be checked by the same rule as for HTML pages. A request without a valid session must return 401. A request for a SKU that does not exist must return 404. A successful request must return 200 with per-warehouse stock for the requested SKU.
- **Preconditions:** Valid authenticated session; SKU supplied in the path.
- **Trigger:** `GET /api/products/{sku}/availability`.
- **Main Flow:** Authenticate → resolve product by SKU → read per-warehouse stock → return 200 with JSON body.
- **Alternative Flow:** N/A
- **Failure Flow:** Missing or invalid session → 401 JSON. SKU not found → 404 JSON. Neither returns HTML.
- **Postconditions:** Read-only; no state change.
- **Input Ref:** INPUT MATRIX § API-01
- **Output:** JSON body with per-warehouse availability; HTTP 200 / 401 / 404.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § API-01
- **UI Ref:** N/A — no UI surface
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service); API / UI / Script boundary
- **Authorization Ref:** AUTHORIZATION MATRIX § API-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** REDIS MATRIX § API-01 (session verification only)
- **Memcached Ref:** MEMCACHED MATRIX § API-01
- **Security Ref:** SECURITY MATRIX § API-01
- **API Ref:** Self — this requirement owns the API contract
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § API-01 (AC-API-01-1 … AC-API-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § API-01
- **Evidence Ref:** EVIDENCE MATRIX § API-01
- **Critical Failure Ref:** CF-2
- **Dependency Ref:** AUTH-01, PRD-01, WH-01, DB-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** SRC-001 gives the path as an example ("Contoh"). The example path is adopted as the concrete contract; no additional endpoints are required.

---

## VAL-01

- **Requirement ID:** VAL-01
- **Type:** Product
- **Category:** Validation, Error & UI
- **Name:** Validation & Feedback
- **Source ID:** SRC-001 (§2.7 VAL-01), SRC-004 (CAND-036, CAND-037)
- **Decision ID:** N/A
- **Actor:** All actors submitting input
- **Business Goal:** Prevent invalid data from being stored while keeping data entry recoverable.
- **Requirement Statement:** Required fields, status enums, dates, foreign keys, and numbers (quantity, price, stock ≥ 0) are validated on both frontend and backend, with the backend as the source of truth. Nothing is stored when validation fails, and already-entered input is preserved where relevant.
- **Business Rule Statement:** Validation must be applied on both frontend and backend, and the backend result is authoritative — a frontend pass never substitutes for backend validation. A required field must be present. A status value must belong to its declared enum. A date must be a valid date. A foreign key must reference an existing record. Quantity, price, and stock values must be ≥ 0. When validation fails, no data may be persisted, and already-entered input must be preserved where it is relevant to the correction.
- **Preconditions:** Requester authenticated and submitting a form or request payload.
- **Trigger:** Submission of any create or update operation.
- **Main Flow:** Receive input → validate on frontend → submit → validate on backend → on pass, proceed with the owning use case.
- **Alternative Flow:** N/A
- **Failure Flow:** Any backend validation failure → nothing persisted, field-level feedback returned, entered input preserved where relevant.
- **Postconditions:** Either the owning operation proceeds with valid data, or no data is written.
- **Input Ref:** INPUT MATRIX (all requirements) — VAL-01 owns the validation rule, the INPUT MATRIX owns per-field detail
- **Output:** Field-level validation feedback; preserved form input.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § VAL-01
- **UI Ref:** UI/UX BASELINE MAPPING § VAL-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller, Service)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § VAL-01
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § VAL-01
- **API Ref:** API-01 shares the backend validation rule
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § VAL-01 (AC-VAL-01-1 … AC-VAL-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § VAL-01
- **Evidence Ref:** EVIDENCE MATRIX § VAL-01
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** All requirements that accept input: USR-01, PRD-01, MSTR-01, WH-01, PO-01, SO-01, FIND-01, REPORT-01, API-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** VAL-01 owns the cross-cutting validation rule. Per-field constraints are detail owned by the INPUT MATRIX and are not restated here.

---

## ERR-01

- **Requirement ID:** ERR-01
- **Type:** Product
- **Category:** Validation, Error & UI
- **Name:** Error Handling
- **Source ID:** SRC-001 (§2.7 ERR-01), SRC-004 (CAND-038, CAND-039)
- **Decision ID:** N/A
- **Actor:** All actors
- **Business Goal:** Make failure predictable for users and opaque to attackers.
- **Requirement Statement:** Access without login is redirected to login; access without authority shows 403; missing data or URL shows 404. Database exceptions and stack traces are never shown to the user.
- **Business Rule Statement:** An unauthenticated request to a protected resource must redirect to login. An authenticated request without the required authority must return 403. A request for a non-existent record or route must return 404. A database exception or stack trace must never be rendered to the user.
- **Preconditions:** None.
- **Trigger:** Any request that cannot be served normally.
- **Main Flow:** Classify the failure → unauthenticated → redirect to login; unauthorised → 403; not found → 404; internal error → safe error response.
- **Alternative Flow:** API requests receive the equivalent JSON status response (API-01).
- **Failure Flow:** N/A — this requirement is the failure path.
- **Postconditions:** A safe response is returned; no internal detail is disclosed.
- **Input Ref:** N/A
- **Output:** Redirect to login; 403 page; 404 page; safe error page; equivalent JSON for API.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** UI/UX BASELINE MAPPING § ERR-01
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Controller)
- **Authorization Ref:** AUTHORIZATION MATRIX § ERR-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § ERR-01
- **API Ref:** API-01
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § ERR-01 (AC-ERR-01-1 … AC-ERR-01-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § ERR-01
- **Evidence Ref:** EVIDENCE MATRIX § ERR-01
- **Critical Failure Ref:** CF-5
- **Dependency Ref:** AUTH-01, VIEW-01, API-01
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** —

---

## UI-01

- **Requirement ID:** UI-01
- **Type:** Product
- **Category:** Validation, Error & UI
- **Name:** Responsive & Usability
- **Source ID:** SRC-001 (§2.7 UI-01), SRC-003, SRC-004 (CAND-040, CAND-041, GAP-007)
- **Decision ID:** DEC-007, DEC-014
- **Actor:** All actors
- **Business Goal:** Keep the application usable on a handheld and on a desktop without loss of function.
- **Requirement Statement:** Login, dashboard, product/order lists, detail pages, and forms are usable at 360px and on desktop; navigation and tables are not clipped. Forms have labels; focus state and basic contrast are visible.
- **Business Rule Statement:** The four main page kinds — login, dashboard, list, detail — plus forms must remain usable at a 360px viewport and on desktop, with no clipped navigation or table content. Every form field must carry a visible label. Focus state and basic contrast must be perceivable.
- **Preconditions:** Existing UI/UX baseline is in force (`DEC-007`).
- **Trigger:** Any page render at any viewport.
- **Main Flow:** Render page → apply the baseline's documented responsive behaviour → present labelled fields with visible focus and adequate contrast.
- **Alternative Flow:** At viewports below the baseline's desktop threshold, apply the baseline's documented collapsed-rail / drawer behaviour (`ASM-003`).
- **Failure Flow:** Content clipped or navigation unreachable at 360px → requirement not met.
- **Postconditions:** Pages are usable at both target viewports.
- **Input Ref:** N/A
- **Output:** Rendered pages at 360px and desktop.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** UI/UX BASELINE MAPPING § UI-01 — carries the one Required Adjustment in this baseline
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (UI)
- **Authorization Ref:** N/A — UI visibility is never authorization (§19.4)
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § UI-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § UI-01 (AC-UI-01-1, AC-UI-01-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § UI-01
- **Evidence Ref:** EVIDENCE MATRIX § UI-01
- **Critical Failure Ref:** N/A — SRC-001 §8.2 lists no UI-specific critical failure
- **Dependency Ref:** AUTH-01, VIEW-01, DASH-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** Adopted into the baseline by `DEC-014`; UI-01 is an SRC-001 official ID absent from the frozen prompt's §10 list. The 360px gap in SRC-003 is a coverage gap with a Required Adjustment, not a redesign (`ASM-003`).

---

## JOB-01

- **Requirement ID:** JOB-01
- **Type:** Product
- **Category:** Database & Job
- **Name:** Script Terjadwal
- **Source ID:** SRC-001 (§2.7 JOB-01, §4.3, FAQ 9), SRC-004 (CAND-045)
- **Decision ID:** N/A
- **Actor:** Script operator
- **Business Goal:** Make replenishment need visible outside the web request cycle, as a real-world cron-style task would.
- **Requirement Statement:** A standalone script (for example `php scripts/check-low-stock.php`) produces a summary of products below their reorder point. It can be run manually via `docker compose exec`; automatic scheduling on the assessment server is not required.
- **Business Rule Statement:** The low-stock task must be executable independently of the web request cycle. A product qualifies for the summary when its stock is below its reorder point. Automatic server-side scheduling must not be required for the task to be demonstrable.
- **Preconditions:** Container running; database reachable.
- **Trigger:** Manual invocation via `docker compose exec`.
- **Main Flow:** Invoke script → read products and stock → compare against reorder point → print summary.
- **Alternative Flow:** N/A
- **Failure Flow:** Database unreachable or query failure → handled error output; no stack trace.
- **Postconditions:** Read-only; no state change.
- **Input Ref:** N/A (no user-supplied field)
- **Output:** CLI summary of below-reorder-point products.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § JOB-01
- **UI Ref:** N/A — no UI surface
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (Standalone Script); API / UI / Script boundary
- **Authorization Ref:** AUTHORIZATION MATRIX § JOB-01
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § JOB-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § JOB-01 (AC-JOB-01-1, AC-JOB-01-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § JOB-01
- **Evidence Ref:** EVIDENCE MATRIX § JOB-01
- **Critical Failure Ref:** N/A
- **Dependency Ref:** PRD-01, WH-01, DB-01, ARCH-01, ENV-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** Business logic must be reusable across HTML, API and CLI without duplication — boundary owned by ARCH-01.

---

## DB-01

- **Requirement ID:** DB-01
- **Type:** Technical
- **Category:** Database & Job
- **Name:** Database Relasional & Transaksi
- **Source ID:** SRC-001 (§2.7 DB-01, §1.3, §4, §4.2, §7.1), SRC-004 (CAND-042…044, CAND-062)
- **Decision ID:** DEC-004
- **Actor:** System
- **Business Goal:** Guarantee that business data is relationally sound, safely queried, and reproducible from empty.
- **Requirement Statement:** Minimum tables per SRC-001 §1.3 with primary keys, foreign keys, constraints (for example `quantity >= 0`) and relevant indexes. All queries use PDO prepared statements; multi-table operations (goods receipt, goods issue) are wrapped in explicit transactions. Schema and seed can build the database from empty, including the data FIND-01 needs and the demo minimums of §7.1.
- **Business Rule Statement:** MySQL is the permanent business source of truth for all business entities. Every table must carry a primary key, and every relationship must be enforced by a foreign key. ProductStock quantity must be constrained to ≥ 0 at the database level. Every query that accepts input must be executed as a PDO prepared statement; user input must never be concatenated into SQL. Any operation writing more than one table — specifically goods receipt and goods issue — must run inside an explicit transaction. Schema and seed must be able to create a working database from an empty state, containing at least 30 products, at least 25 combined orders, one Admin, at least two Sales, at least two Warehouse Staff, at least two warehouses, reorder-point variation with some products below reorder point, and order status variation including `PendingApproval` and `Cancelled`.
- **Preconditions:** MySQL 8 available; empty database acceptable.
- **Trigger:** Environment build, schema/seed execution, or any data access.
- **Main Flow:** Apply schema with PK, FK, constraints and indexes → run seed → serve all data access through PDO prepared statements → wrap multi-table stock writes in explicit transactions.
- **Alternative Flow:** N/A
- **Failure Flow:** Constraint violation → operation rejected and, inside a transaction, rolled back. Seed failure → environment not usable (CF-1 exposure).
- **Postconditions:** A relationally sound database exists, reproducible from empty, with stock invariants enforced at the storage layer.
- **Input Ref:** INPUT MATRIX § DB-01
- **Output:** Schema and seed artifacts; enforced constraints; transactional writes.
- **State Change Ref:** N/A — storage-level requirement, not a business lifecycle
- **Data Impact Ref:** DATA IMPACT MATRIX § DB-01
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE (Database, Stock); STOCK TECHNICAL BOUNDARY (§22); ARCHITECTURE BOUNDARY MATRIX (Concrete Repository, MySQL)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § DB-01
- **Concurrency Ref:** CONCURRENCY MATRIX § DB-01
- **Redis Ref:** N/A — Redis holds no business data (§20)
- **Memcached Ref:** N/A — Memcached is never authoritative (§21)
- **Security Ref:** SECURITY MATRIX § DB-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DB-01 (AC-DB-01-1 … AC-DB-01-5)
- **Test Ref:** TEST TRACEABILITY MATRIX § DB-01
- **Evidence Ref:** EVIDENCE MATRIX § DB-01
- **Critical Failure Ref:** CF-1, CF-5, CF-6, CF-7
- **Dependency Ref:** ENV-01 (runnable MySQL), ARCH-01 (repository boundary)
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Seed minimums are stated here because DB-01 owns schema and seed; FIND-01 owns the pagination rule those minimums exist to test. SRC-001 evidence for DB-01 explicitly includes an ERD, one multi-table transaction explanation, and one index explanation.

---

## ARCH-01

- **Requirement ID:** ARCH-01
- **Type:** Technical
- **Category:** Architecture
- **Name:** Pemisahan Layer & Interface pada Boundary Repository
- **Source ID:** SRC-001 (§3.1 ARCH-01, §4.1, FAQ 1, FAQ 2, FAQ 4), SRC-004 (CAND-046…049)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Make business logic independently testable and keep it free of infrastructure detail.
- **Requirement Statement:** Business logic must not depend directly on PDO, session, or PHP superglobals. Three pragmatic layers — Controller (HTTP/routing) → Service (business rule) → Repository (data access) — with dependency flowing from Controller toward Repository and not the reverse. At least one Repository interface has two implementations: a real MySQL implementation and an in-memory/fake implementation used by unit tests. Services receive dependencies by constructor injection, with no hidden `new PDO()` inside a Service. Business-logic tests run without a real database connection.
- **Business Rule Statement:** Business logic must not depend on PDO, session, or superglobals. Dependency direction must run Controller → Service → Repository and never the reverse. A Service must depend on a Repository interface, not a concrete Repository. A Service must receive its dependencies through its constructor; a Service must not instantiate infrastructure internally. At least one Repository interface must have both a real MySQL implementation and an in-memory fake implementation. Business-logic tests must be runnable without a real database connection.
- **Preconditions:** None.
- **Trigger:** Any code structure decision within the application.
- **Main Flow:** Define Repository interfaces at the persistence boundary → implement Services against those interfaces with constructor injection → implement concrete MySQL repositories → provide at least one in-memory fake for unit tests → keep Controllers responsible only for HTTP concerns.
- **Alternative Flow:** N/A
- **Failure Flow:** A Service touching PDO, a superglobal, or a concrete Repository violates the requirement, as does a hidden infrastructure instantiation inside a Service.
- **Postconditions:** Layer separation holds, dependency inversion is demonstrable at the repository boundary, and business logic is testable in isolation.
- **Input Ref:** N/A
- **Output:** Repository interfaces with two implementations; constructor-injected Services; passing database-free unit tests.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (all rows); TECHNICAL BASELINE (Architecture)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § ARCH-01 (transaction boundary is owned by the Service layer)
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § ARCH-01
- **API Ref:** API-01 shares the same Service layer without duplicating rules
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § ARCH-01 (AC-ARCH-01-1 … AC-ARCH-01-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § ARCH-01
- **Evidence Ref:** EVIDENCE MATRIX § ARCH-01
- **Critical Failure Ref:** CF-3, CF-9
- **Dependency Ref:** DB-01, TEST-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. SRC-001 FAQ 4 explicitly rejects full four-ring Clean Architecture; three pragmatic layers are sufficient. FAQ 2 rejects a DI container as mandatory.

---

## ARCH-02

- **Requirement ID:** ARCH-02
- **Type:** Technical
- **Category:** Architecture
- **Name:** Transaksi & Concurrency-Safe Stock Operation
- **Source ID:** SRC-001 (§3.1 ARCH-02, §4.2, FAQ 8), SRC-004 (CAND-050, CAND-051)
- **Decision ID:** DEC-004, DEC-005
- **Actor:** System
- **Business Goal:** Keep stock correct when two fulfilment operations collide.
- **Requirement Statement:** Two goods issues processed almost simultaneously must not produce negative stock (oversell). ProductStock change and StockLedger write occur inside one transaction (`beginTransaction` / `commit` / `rollBack`). When two goods issues for the same product and warehouse are processed almost simultaneously, the final stock must remain correct: no oversell and no mutually overwriting updates. The mechanism is a participant design decision. The participant must be able to explain the concurrent scenario prevented and show a test or controlled scenario proving it; real thread/parallel simulation is not required.
- **Business Rule Statement:** A ProductStock mutation and its StockLedger row must be written inside one explicit database transaction; if either fails, neither takes effect. Stock must never become negative under any interleaving of concurrent operations. Concurrent goods issues for the same product and warehouse must not oversell and must not lose an update. Correctness must rest on database transaction and concurrency control, not on cache infrastructure. A controlled, reproducible scenario must demonstrate that the second competing request is rejected or deferred once stock is exhausted by the first.
- **Preconditions:** Explicit transaction support available in MySQL; stock operations routed through the stock service.
- **Trigger:** Any goods receipt or goods issue, especially two competing issues on the same product and warehouse.
- **Main Flow:** Begin transaction → validate available stock → write StockLedger row → mutate ProductStock → commit.
- **Alternative Flow:** Competing concurrent issue → the second request is rejected or deferred by the database concurrency mechanism.
- **Failure Flow:** Insufficient stock → reject before mutation, roll back. Any failure inside the transaction → rollback leaving ProductStock and StockLedger unchanged and mutually consistent.
- **Postconditions:** Stock is never negative; ProductStock and StockLedger are consistent; no update is lost.
- **Input Ref:** N/A
- **Output:** Committed or rolled-back stock transaction; controlled concurrency scenario evidence.
- **State Change Ref:** STATE MATRIX § ARCH-02 (stock movement, not a document lifecycle)
- **Data Impact Ref:** DATA IMPACT MATRIX § ARCH-02
- **UI Ref:** N/A
- **Technical Boundary Ref:** STOCK TECHNICAL BOUNDARY (§22); TECHNICAL BASELINE (Stock, Database)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § ARCH-02
- **Concurrency Ref:** CONCURRENCY MATRIX § ARCH-02
- **Redis Ref:** N/A — Redis must not be the stock concurrency mechanism (§20, `DEC-005`)
- **Memcached Ref:** N/A
- **Security Ref:** SECURITY MATRIX § ARCH-02
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § ARCH-02 (AC-ARCH-02-1 … AC-ARCH-02-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § ARCH-02
- **Evidence Ref:** EVIDENCE MATRIX § ARCH-02
- **Critical Failure Ref:** CF-6, CF-7
- **Dependency Ref:** DB-01, PO-01, SO-01, TEST-02
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** SRC-001 deliberately leaves the concurrency mechanism open ("bagian dari keputusan desain peserta"). Phase 1 therefore states the invariant and the boundary but does not select the mechanism; that selection belongs to Phase 2 and is an expected `DESIGN-02` ADR subject.

---

## ENV-01

- **Requirement ID:** ENV-01
- **Type:** Technical
- **Category:** Environment
- **Name:** Docker Environment & Reproducibility
- **Source ID:** SRC-001 (§0, §4 Environment, §5.1, §8.2 CF-1, §10), SRC-004 (CAND-060, CAND-061)
- **Decision ID:** DEC-016
- **Actor:** System
- **Business Goal:** Guarantee the project runs from a clean environment on any machine, which is a pass/fail condition.
- **Requirement Statement:** The application and database must run via Docker Compose from a clean state using `docker compose up --build`, with at minimum an application/web service and a MySQL service. Configuration is supplied by environment variables with example values in `.env`. Source code must not depend on absolute paths or machine-specific configuration.
- **Business Rule Statement:** The application and its database must be startable from a clean checkout using Docker Compose with no manual host configuration beyond the documented README procedure. At minimum an application/web service and a MySQL service must be defined. All environment-specific configuration must be supplied by environment variable, with example values committed in `.env`. No active secret or credential may be committed. Source code must not rely on absolute paths or participant-machine-specific configuration.
- **Preconditions:** Docker and Docker Compose available on the host.
- **Trigger:** Clean-environment build and start.
- **Main Flow:** Copy `.env` to `.env` → `docker compose up --build` → application and MySQL services start → run schema/seed per README → application reachable.
- **Alternative Flow:** Additional services (for example Redis, Memcached per `DEC-005`/`DEC-006`) may be composed, provided the mandatory two exist and the boundaries in §20 and §21 hold.
- **Failure Flow:** Build or start failure after a reasonable documented procedure → CF-1 critical failure. Absolute-path or host-specific dependency → requirement not met.
- **Postconditions:** A reproducible running environment exists with no committed active secret.
- **Input Ref:** INPUT MATRIX § ENV-01
- **Output:** Dockerfile; Docker Compose definition; `.env`; running services.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE (Deployment, Infrastructure)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** REDIS MATRIX § ENV-01 (service composition only)
- **Memcached Ref:** MEMCACHED MATRIX § ENV-01 (service composition only)
- **Security Ref:** SECURITY MATRIX § ENV-01
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § ENV-01 (AC-ENV-01-1 … AC-ENV-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § ENV-01
- **Evidence Ref:** EVIDENCE MATRIX § ENV-01
- **Critical Failure Ref:** CF-1, CF-5
- **Dependency Ref:** DB-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Phase 1 derived requirement ID created by `DEC-016`. SRC-001 makes this mandatory and makes its failure critical (CF-1) but attaches no official ID; DB-01 covers schema and seed only, not container composition.

---

## TEST-01

- **Requirement ID:** TEST-01
- **Type:** Technical
- **Category:** Testing
- **Name:** Unit Test Terisolasi
- **Source ID:** SRC-001 (§3.3 TEST-01, FAQ 6), SRC-004 (CAND-056)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Prove business logic is correct and isolated from infrastructure.
- **Requirement Statement:** At least 6 test cases across at least 3 logic areas — for example PO date validation, SO status transition, low-stock calculation, approval ownership/authorization. Tests must not touch session, real PDO, or external services; trivial getter/setter tests do not count.
- **Business Rule Statement:** At least 6 unit test cases must exist, distributed across at least 3 distinct logic areas. A unit test must not touch a session, a real PDO connection, or an external service. A trivial getter or setter test does not satisfy the minimum.
- **Preconditions:** ARCH-01 satisfied so logic is testable without a database.
- **Trigger:** Test suite execution via the README command.
- **Main Flow:** Instantiate the Service under test with fake repositories → exercise the logic area → assert on the outcome.
- **Alternative Flow:** N/A
- **Failure Flow:** A test requiring a real database or session breaches isolation and does not count toward the minimum.
- **Postconditions:** At least 6 isolated unit tests across ≥ 3 logic areas pass.
- **Input Ref:** N/A
- **Output:** Unit test results in `docs/testing/`.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE (Testing); ARCHITECTURE BOUNDARY MATRIX (Service, Repository Interface)
- **Authorization Ref:** N/A
- **SoD Ref:** Approval ownership/authorization is a named candidate logic area
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § TEST-01 (AC-TEST-01-1, AC-TEST-01-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § TEST-01
- **Evidence Ref:** EVIDENCE MATRIX § TEST-01
- **Critical Failure Ref:** CF-4
- **Dependency Ref:** ARCH-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. CF-4 makes absence of valid tests a critical failure, hence P0.

---

## TEST-02

- **Requirement ID:** TEST-02
- **Type:** Technical
- **Category:** Testing
- **Name:** Integration Test
- **Source ID:** SRC-001 (§3.3 TEST-02, FAQ 6), SRC-004 (CAND-057)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Prove the stock operations behave correctly end-to-end against a real database.
- **Requirement Statement:** At least 3 integration tests touching real MySQL in Docker — for example goods receipt genuinely increasing stock end-to-end, or a second goods issue being rejected once stock is exhausted by the first.
- **Business Rule Statement:** At least 3 integration tests must exist and must exercise a real MySQL instance running in Docker. Integration tests must be separated from unit tests. At least one integration test must demonstrate the concurrency invariant of ARCH-02 in a controlled, reproducible way.
- **Preconditions:** ENV-01 satisfied; MySQL reachable in Docker; schema and seed applied.
- **Trigger:** Test suite execution via the README command.
- **Main Flow:** Start the Docker environment → run integration tests against real MySQL → assert stock, ledger, and status outcomes end-to-end.
- **Alternative Flow:** Integration tests may be run by a separate documented command, provided the README explains it.
- **Failure Flow:** Test that never reaches MySQL does not count toward the minimum. A test passing only because it is skipped does not count.
- **Postconditions:** At least 3 integration tests pass against real MySQL.
- **Input Ref:** N/A
- **Output:** Integration test results in `docs/testing/`.
- **State Change Ref:** N/A
- **Data Impact Ref:** DATA IMPACT MATRIX § TEST-02
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE (Testing, Deployment)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** TRANSACTION MATRIX § TEST-02
- **Concurrency Ref:** CONCURRENCY MATRIX § TEST-02
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § TEST-02 (AC-TEST-02-1 … AC-TEST-02-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § TEST-02
- **Evidence Ref:** EVIDENCE MATRIX § TEST-02
- **Critical Failure Ref:** CF-4, CF-7
- **Dependency Ref:** ENV-01, DB-01, ARCH-02, PO-01, SO-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. The oversell scenario named by SRC-001 is the natural home for the ARCH-02 controlled scenario evidence.

---

## TEST-03

- **Requirement ID:** TEST-03
- **Type:** Technical
- **Category:** Testing
- **Name:** Static Analysis & FIRST
- **Source ID:** SRC-001 (§3.3 TEST-03, §4, FAQ 7), SRC-004 (CAND-058, CAND-059)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Keep code quality measurable and the test suite trustworthy.
- **Requirement Statement:** A PHPStan (level 5+) or PHP_CodeSniffer (PSR-12) report is attached with zero critical errors; remaining warnings are briefly explained. Tests follow FIRST — Fast, Independent, Repeatable, Self-validating, Timely — with no `sleep()`, no real network calls, and no execution-order dependency.
- **Business Rule Statement:** A static analysis report must be attached and must contain zero critical errors. Any remaining warning must be explained rather than silently ignored. Tests must contain no `sleep()`, must make no real network call, and must not depend on execution order.
- **Preconditions:** Test suite and analysis tooling configured.
- **Trigger:** Static analysis run and test suite execution.
- **Main Flow:** Run PHPStan level 5+ or PHPCS PSR-12 → resolve all critical errors → document remaining warnings → verify tests satisfy FIRST.
- **Alternative Flow:** Either tool satisfies the requirement; both are not required.
- **Failure Flow:** Any critical error remaining → requirement not met. Undocumented warnings → requirement not met. `sleep()`, real network call, or order dependency in tests → FIRST breached.
- **Postconditions:** A zero-critical-error report exists in `docs/quality/`; the suite satisfies FIRST.
- **Input Ref:** N/A
- **Output:** Static analysis report in `docs/quality/`.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE (Testing)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § TEST-03 (AC-TEST-03-1, AC-TEST-03-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § TEST-03
- **Evidence Ref:** EVIDENCE MATRIX § TEST-03
- **Critical Failure Ref:** N/A — SRC-001 §8.2 does not list static analysis as a critical failure
- **Dependency Ref:** TEST-01, TEST-02
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. SRC-001 FAQ 7 confirms zero *critical* errors is the bar, not zero warnings.

---

## DESIGN-01

- **Requirement ID:** DESIGN-01
- **Type:** Technical
- **Category:** Design Evidence
- **Name:** Class Diagram — Initial & As-Built
- **Source ID:** SRC-001 (§3.2 DESIGN-01, §4, §7, FAQ 3), SRC-004 (CAND-052)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Make the intended and delivered structure inspectable and traceable to code.
- **Requirement Statement:** An initial diagram is produced before coding in `docs/planning/`, showing Controller/Service/Repository/Entity and their relationships. An as-built diagram is produced at the end in `docs/architecture/`, marked to distinguish dependencies pointing at interfaces from those pointing at concrete classes. Two to three sentences explain what changed between initial and as-built, and why. Any legible tool is acceptable.
- **Business Rule Statement:** An initial class diagram must exist before coding begins and must live in `docs/planning/`. An as-built class diagram must exist at the end and must live in `docs/architecture/`, and must visually distinguish interface-directed from concrete-directed dependencies. A written explanation of 2–3 sentences must state what changed between the two diagrams and why. Both diagrams must correspond to actual code and be traceable class-by-class during defense.
- **Preconditions:** For the initial diagram, the pre-coding analysis is complete. For the as-built diagram, implementation is complete.
- **Trigger:** Pre-coding design; end-of-implementation review.
- **Main Flow:** Produce the initial diagram before coding → implement → produce the as-built diagram with interface/concrete markers → write the 2–3 sentence change note.
- **Alternative Flow:** Tool choice is free — draw.io, PlantUML, Mermaid, or a scanned sketch — provided it is legible and matches the code.
- **Failure Flow:** A diagram that does not reflect actual code and cannot be traced during defense is a critical failure (CF-8).
- **Postconditions:** Both diagrams exist, correspond to code, and are traceable.
- **Input Ref:** N/A
- **Output:** Initial diagram in `docs/planning/`; as-built diagram in `docs/architecture/`; change note.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** ARCHITECTURE BOUNDARY MATRIX (all rows are the diagram's subject)
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DESIGN-01 (AC-DESIGN-01-1 … AC-DESIGN-01-3)
- **Test Ref:** TEST TRACEABILITY MATRIX § DESIGN-01
- **Evidence Ref:** EVIDENCE MATRIX § DESIGN-01
- **Critical Failure Ref:** CF-8
- **Dependency Ref:** ARCH-01
- **Priority:** P0
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. CF-8 makes a non-traceable diagram a critical failure, hence P0. The conceptual initial diagram already produced in the pre-coding analysis is analysis input; the DESIGN-01 initial diagram is an implementation-facing artifact owned by Phase 2.

---

## DESIGN-02

- **Requirement ID:** DESIGN-02
- **Type:** Technical
- **Category:** Design Evidence
- **Name:** Architecture Decision Record (ADR)
- **Source ID:** SRC-001 (§3.2 DESIGN-02, §4, §7), SRC-004 (CAND-053)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Record why the architecture is what it is, so it can be defended.
- **Requirement Statement:** Two to three short ADRs (context / decision / consequences) for real decisions — for example why the Repository pattern rather than PDO directly in the controller, or why a particular mechanism was chosen to prevent oversell in ARCH-02.
- **Business Rule Statement:** Between 2 and 3 ADRs must exist, each stating context, decision, and consequences. Each ADR must record a genuine architectural decision, not a trivial implementation choice. ADRs must be stored as `docs/architecture/adr-*.md`.
- **Preconditions:** Architectural decisions have been made.
- **Trigger:** An architectural decision is taken.
- **Main Flow:** Identify a genuine architectural decision → write context, decision, consequences → store as `docs/architecture/adr-*.md`.
- **Alternative Flow:** N/A
- **Failure Flow:** Fewer than 2 ADRs, or ADRs recording trivial choices, do not satisfy the requirement.
- **Postconditions:** 2–3 ADRs exist covering real architectural decisions.
- **Input Ref:** N/A
- **Output:** `docs/architecture/adr-*.md`.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** TECHNICAL BASELINE; ARCHITECTURE BOUNDARY MATRIX
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** ARCH-02 mechanism selection is a named ADR candidate
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DESIGN-02 (AC-DESIGN-02-1, AC-DESIGN-02-2)
- **Test Ref:** TEST TRACEABILITY MATRIX § DESIGN-02
- **Evidence Ref:** EVIDENCE MATRIX § DESIGN-02
- **Critical Failure Ref:** CF-9
- **Dependency Ref:** ARCH-01, ARCH-02
- **Priority:** P1
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. ADRs must not duplicate the DECISION REGISTRY: `DEC-*` records project decisions, ADRs record architectural decisions and their consequences. `ASM-002` indicates that Redis and Memcached scope is a likely ADR subject.

---

## DESIGN-03

- **Requirement ID:** DESIGN-03
- **Type:** Technical
- **Category:** Design Evidence
- **Name:** Refactoring Log, Audit SRP & Tech-Debt Register
- **Source ID:** SRC-001 (§3.2 DESIGN-03, §6.1, §7), SRC-004 (CAND-054)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Show that code quality was actively improved and that shortcuts were recorded honestly.
- **Requirement Statement:** A refactoring log with at least 3 entries, each naming the smell, the technique applied, and a before/after excerpt. One SRP audit note identifying a class from the initial draft that violated SRP and how it was split. A tech-debt register recording limitations and shortcuts taken for time, with the ideal fix. At least one commit tagged `refactor:` that improves older code rather than the feature under development.
- **Business Rule Statement:** The refactoring log must contain at least 3 entries, each naming a smell, the technique applied, and a before/after excerpt. Exactly one SRP audit note must identify an initial-draft class that violated SRP and describe how it was split. The tech-debt register must record shortcuts honestly rather than concealing them. At least one commit must be tagged `refactor:` and must improve pre-existing code rather than the feature currently being built.
- **Preconditions:** Implementation underway with version history.
- **Trigger:** Each refactoring action; end-of-project quality review.
- **Main Flow:** Identify a smell → apply a named technique → record smell, technique and before/after → maintain the tech-debt register → make at least one `refactor:`-tagged commit on older code.
- **Alternative Flow:** N/A
- **Failure Flow:** Fewer than 3 log entries, a missing SRP note, a concealed shortcut, or no `refactor:` commit does not satisfy the requirement.
- **Postconditions:** `docs/quality/refactor-log.md` and `docs/quality/tech-debt.md` exist; commit history contains a `refactor:` commit.
- **Input Ref:** N/A
- **Output:** `docs/quality/refactor-log.md`; `docs/quality/tech-debt.md`; commit history.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** N/A
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DESIGN-03 (AC-DESIGN-03-1 … AC-DESIGN-03-4)
- **Test Ref:** TEST TRACEABILITY MATRIX § DESIGN-03
- **Evidence Ref:** EVIDENCE MATRIX § DESIGN-03
- **Critical Failure Ref:** N/A
- **Dependency Ref:** ARCH-01, TEST-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. The Boy Scout Rule is the stated intent of the `refactor:` commit condition.

---

## DESIGN-04

- **Requirement ID:** DESIGN-04
- **Type:** Technical
- **Category:** Design Evidence
- **Name:** Critique Exercise
- **Source ID:** SRC-001 (§3.2 DESIGN-04, §7, FAQ 5), SRC-004 (CAND-055)
- **Decision ID:** DEC-014
- **Actor:** System
- **Business Goal:** Demonstrate the ability to diagnose bad code, not only to write good code.
- **Requirement Statement:** The assessor supplies a deliberately problematic code excerpt — for example one Service handling validation, persistence and notification at once. The participant writes a short critique naming the smells present, the SOLID principles violated, and how it should be refactored. Implementing the fix is not required; written analysis suffices.
- **Business Rule Statement:** A written critique must exist naming the smells present, the SOLID principles violated, and the refactoring direction. Implementation of the improvement is not required. The critique must be stored as `docs/quality/critique.md`.
- **Preconditions:** Assessor has supplied the excerpt.
- **Trigger:** Assessor provides the critique exercise.
- **Main Flow:** Read the excerpt → name the smells → name the violated SOLID principles → describe the refactoring direction → store as `docs/quality/critique.md`.
- **Alternative Flow:** N/A
- **Failure Flow:** Missing critique, or a critique that names no smell and no violated principle, does not satisfy the requirement.
- **Postconditions:** `docs/quality/critique.md` exists and is discussable at defense.
- **Input Ref:** N/A
- **Output:** `docs/quality/critique.md`.
- **State Change Ref:** N/A
- **Data Impact Ref:** N/A
- **UI Ref:** N/A
- **Technical Boundary Ref:** N/A
- **Authorization Ref:** N/A
- **SoD Ref:** N/A
- **Transaction Ref:** N/A
- **Concurrency Ref:** N/A
- **Redis Ref:** N/A
- **Memcached Ref:** N/A
- **Security Ref:** N/A
- **API Ref:** N/A
- **Acceptance Criteria Ref:** ACCEPTANCE MATRIX § DESIGN-04 (AC-DESIGN-04-1)
- **Test Ref:** TEST TRACEABILITY MATRIX § DESIGN-04
- **Evidence Ref:** EVIDENCE MATRIX § DESIGN-04
- **Critical Failure Ref:** N/A
- **Dependency Ref:** ARCH-01
- **Priority:** P2
- **Status:** DOCUMENTED
- **Notes:** Adopted by `DEC-014`. SRC-001 FAQ 5 confirms written analysis is sufficient. The exercise input is assessor-supplied and cannot be produced in advance.

---

# 12. REQUIREMENT CATALOG

Classification: **INDEX / SUMMARY** — generated from §11. Introduces and redefines nothing.

| Requirement ID | Type | Category | Name | Priority | Status | Canonical Reference |
|---|---|---|---|---|---|---|
| AUTH-01 | Product | Authentication & User | Login dan Session | P0 | DOCUMENTED | §11 AUTH-01 |
| AUTH-02 | Product | Authentication & User | Logout | P1 | DOCUMENTED | §11 AUTH-02 |
| USR-01 | Product | Authentication & User | Manajemen User (3 role) | P1 | DOCUMENTED | §11 USR-01 |
| PRD-01 | Product | Master Data | Produk, Kategori & Reorder Point | P1 | DOCUMENTED | §11 PRD-01 |
| MSTR-01 | Product | Master Data | Supplier & Customer Master Data | P1 | DOCUMENTED | §11 MSTR-01 |
| WH-01 | Product | Master Data | Gudang & Stok Multi-Lokasi | P1 | DOCUMENTED | §11 WH-01 |
| PO-01 | Product | Purchase Order & Goods Receipt | Purchase Order & Goods Receipt | P0 | DOCUMENTED | §11 PO-01 |
| SO-01 | Product | Sales Order, Approval & Goods Issue | Sales Order, Approval & Goods Issue | P0 | DOCUMENTED | §11 SO-01 |
| VIEW-01 | Product | List, Find, Dashboard & Report | Daftar, Detail & Empty State | P1 | DOCUMENTED | §11 VIEW-01 |
| FIND-01 | Product | List, Find, Dashboard & Report | Search, Filter, Sort & Pagination | P2 | DOCUMENTED | §11 FIND-01 |
| DASH-01 | Product | List, Find, Dashboard & Report | Dashboard sesuai Hak Akses | P1 | DOCUMENTED | §11 DASH-01 |
| REPORT-01 | Product | List, Find, Dashboard & Report | Laporan CSV | P2 | DOCUMENTED | §11 REPORT-01 |
| API-01 | Product | API | Endpoint JSON | P2 | DOCUMENTED | §11 API-01 |
| VAL-01 | Product | Validation, Error & UI | Validation & Feedback | P1 | DOCUMENTED | §11 VAL-01 |
| ERR-01 | Product | Validation, Error & UI | Error Handling | P1 | DOCUMENTED | §11 ERR-01 |
| UI-01 | Product | Validation, Error & UI | Responsive & Usability | P2 | DOCUMENTED | §11 UI-01 |
| JOB-01 | Product | Database & Job | Script Terjadwal | P2 | DOCUMENTED | §11 JOB-01 |
| DB-01 | Technical | Database & Job | Database Relasional & Transaksi | P0 | DOCUMENTED | §11 DB-01 |
| ARCH-01 | Technical | Architecture | Pemisahan Layer & Interface pada Boundary Repository | P0 | DOCUMENTED | §11 ARCH-01 |
| ARCH-02 | Technical | Architecture | Transaksi & Concurrency-Safe Stock Operation | P0 | DOCUMENTED | §11 ARCH-02 |
| ENV-01 | Technical | Environment | Docker Environment & Reproducibility | P0 | DOCUMENTED | §11 ENV-01 |
| TEST-01 | Technical | Testing | Unit Test Terisolasi | P0 | DOCUMENTED | §11 TEST-01 |
| TEST-02 | Technical | Testing | Integration Test | P0 | DOCUMENTED | §11 TEST-02 |
| TEST-03 | Technical | Testing | Static Analysis & FIRST | P2 | DOCUMENTED | §11 TEST-03 |
| DESIGN-01 | Technical | Design Evidence | Class Diagram — Initial & As-Built | P0 | DOCUMENTED | §11 DESIGN-01 |
| DESIGN-02 | Technical | Design Evidence | Architecture Decision Record (ADR) | P1 | DOCUMENTED | §11 DESIGN-02 |
| DESIGN-03 | Technical | Design Evidence | Refactoring Log, Audit SRP & Tech-Debt Register | P2 | DOCUMENTED | §11 DESIGN-03 |
| DESIGN-04 | Technical | Design Evidence | Critique Exercise | P2 | DOCUMENTED | §11 DESIGN-04 |

**28 rows. P0 = 10 · P1 = 10 · P2 = 8 · P3 = 0.**

---

# 13. ROLE CAPABILITY MATRIX

Classification: **INDEX / SUMMARY** — capability overview only. Canonical authorization enforcement
detail, including SoD, is owned by the AUTHORIZATION MATRIX (§19.4). No authorization rule,
ownership rule, or SoD rule is restated here.

| Capability | Admin | Sales | Warehouse Staff |
|---|---|---|---|
| Login | ALLOW | ALLOW | ALLOW |
| Logout | ALLOW | ALLOW | ALLOW |
| Own Profile | ALLOW | ALLOW | ALLOW |
| User Management | ALLOW | DENY | DENY |
| Product Management | ALLOW | VIEW ONLY | VIEW ONLY |
| Category Management | ALLOW | VIEW ONLY | VIEW ONLY |
| Warehouse Management | ALLOW | VIEW ONLY | VIEW ONLY |
| Supplier Management | ALLOW | DENY | DENY |
| Customer Management | ALLOW | DENY | DENY |
| View Product | ALLOW | ALLOW | ALLOW |
| View Stock | ALLOW | ALLOW | ALLOW |
| Create Sales Order | ALLOW | OWN DATA | DENY |
| Submit Sales Order | ALLOW | OWN DATA | DENY |
| Approve Sales Order | ALLOW | DENY | DENY |
| Reject Sales Order | ALLOW | DENY | DENY |
| Create Purchase Order | ALLOW | DENY | OPERATIONAL |
| Goods Receipt | ALLOW | DENY | OPERATIONAL |
| Goods Issue | ALLOW | DENY | OPERATIONAL |
| Dashboard | ALLOW | OWN DATA | OPERATIONAL |
| Report | ALLOW | OWN DATA | OPERATIONAL |

Basis: SRC-001 §1.2 role table. `VIEW ONLY` for Sales on Product/Category/Warehouse reflects
"Hanya melihat katalog"; for Warehouse Staff it reflects "Hanya melihat produk & stok".
`OPERATIONAL` for Warehouse Staff on Create Purchase Order reflects "Boleh mengusulkan".
Supplier and Customer management is Admin-only, consistent with §11 MSTR-01.

---

# 14. CORE PRODUCT JOURNEYS

Classification: **REFERENCE / MAPPING** — journeys reference requirement IDs and lifecycle states.
They define no business rule (§11) and no state transition (§19.3).

## Authentication

```text
Login
→ authenticated
→ role-specific access
→ logout
```
References: AUTH-01, AUTH-02, USR-01

## Purchase

```text
Low Stock
→ Purchase Order
→ Ordered
→ Goods Receipt
→ Stock Increase
→ Movement Recorded
```
References: PO-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02, JOB-01 (low-stock visibility)

## Sales

```text
Create SO
→ PendingApproval
→ Admin Approval
→ Approved
→ Goods Issue
→ Fulfilled
```
References: SO-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02

## Multi-Warehouse

Same Product dapat memiliki stock berbeda per Warehouse.

References: WH-01, PO-01, SO-01, API-01

## Failure

```text
Invalid Login
Inactive User
Forbidden Action
Invalid Input
Insufficient Stock
Not Found
```
References: AUTH-01 (invalid login, inactive user), ERR-01 (forbidden action, not found),
VAL-01 (invalid input), SO-01 (insufficient stock)

---

# 15. PRODUCT MODULE MAP

Classification: **INDEX / SUMMARY**

| Module | Owning requirement(s) |
|---|---|
| Authentication | AUTH-01, AUTH-02 |
| User Management | USR-01 |
| Product | PRD-01 |
| Category | PRD-01 |
| Warehouse | WH-01 |
| Supplier | MSTR-01 |
| Customer | MSTR-01 |
| Product Stock | WH-01, PO-01, SO-01 |
| Purchase Order | PO-01 |
| Goods Receipt | PO-01, ARCH-02 |
| Sales Order | SO-01 |
| Approval | SO-01 |
| Goods Issue | SO-01, ARCH-02 |
| Stock Ledger | DB-01, PO-01, SO-01 |
| List / Detail | VIEW-01 |
| Search / Filter / Sort / Pagination | FIND-01 |
| Dashboard | DASH-01 |
| CSV Report | REPORT-01 |
| JSON API | API-01 |
| Validation | VAL-01 |
| Error Handling | ERR-01 |
| Scheduled Low-Stock Script | JOB-01 |

---

# 16. PRODUCT BUSINESS RULE BASELINE

Classification: **INDEX / SUMMARY**

```text
CANONICAL DETAILED REQUIREMENT MATRIX
→ canonical business rule

Product Business Rule Baseline
→ summary / navigation only
```

Each bullet below is a **navigation label**. Its canonical statement lives in the
`Business Rule Statement` field of the referenced §11 row. No rule is defined here.

| Business Rule Topic | Requirement ID | Canonical Reference |
|---|---|---|
| **Authentication** | | |
| Valid active user may authenticate | AUTH-01 | §11 AUTH-01 · Business Rule Statement |
| Inactive user must not authenticate | AUTH-01 | §11 AUTH-01 · Business Rule Statement |
| Protected area requires an authenticated session | AUTH-01 | §11 AUTH-01 · Business Rule Statement |
| Session identifier renewed on login | AUTH-01 | §11 AUTH-01 · Business Rule Statement |
| Password hashed and verified with the PHP password API | AUTH-01 | §11 AUTH-01 · Business Rule Statement |
| Logout ends authentication and re-protects URLs | AUTH-02 | §11 AUTH-02 · Business Rule Statement |
| **User** | | |
| Email unique across all accounts | USR-01 | §11 USR-01 · Business Rule Statement |
| Role limited to Admin / Sales / WarehouseStaff | USR-01 | §11 USR-01 · Business Rule Statement |
| No public registration | USR-01 | §11 USR-01 · Business Rule Statement |
| Non-Admin denied user administration at the server | USR-01 | §11 USR-01 · Business Rule Statement |
| **Product** | | |
| SKU unique | PRD-01 | §11 PRD-01 · Business Rule Statement |
| Non-negative numeric business values | PRD-01 | §11 PRD-01 · Business Rule Statement |
| Used product deactivated, never permanently deleted | PRD-01 | §11 PRD-01 · Business Rule Statement |
| Uploaded image validated and stored under a random name | PRD-01 | §11 PRD-01 · Business Rule Statement |
| **Supplier & Customer** | | |
| Used counterparty deactivated, never permanently deleted | MSTR-01 | §11 MSTR-01 · Business Rule Statement |
| Inactive counterparty not selectable for a new order | MSTR-01 | §11 MSTR-01 · Business Rule Statement |
| **Warehouse** | | |
| Stock is warehouse-specific, one row per product per warehouse | WH-01 | §11 WH-01 · Business Rule Statement |
| Total stock is the sum of per-warehouse quantities | WH-01 | §11 WH-01 · Business Rule Statement |
| **Purchase** | | |
| PO follows its status lifecycle | PO-01 | §11 PO-01 · Business Rule Statement |
| Partial receipt allowed with remainder recorded | PO-01 | §11 PO-01 · Business Rule Statement |
| Receipt increases stock and writes a Receipt ledger row in one transaction | PO-01 | §11 PO-01 · Business Rule Statement |
| Received quantity must not exceed outstanding quantity | PO-01 | §11 PO-01 · Business Rule Statement |
| **Sales** | | |
| SO follows its status lifecycle | SO-01 | §11 SO-01 · Business Rule Statement |
| Approval required before fulfilment | SO-01 | §11 SO-01 · Business Rule Statement |
| Sales denied approval entirely, enforced server-side | SO-01 | §11 SO-01 · Business Rule Statement |
| Rejected order terminates at Cancelled | SO-01 | §11 SO-01 · Business Rule Statement (`DEC-009`, `ASM-001`) |
| Goods issue only for an Approved SO | SO-01 | §11 SO-01 · Business Rule Statement |
| Insufficient stock rejects the issue | SO-01 | §11 SO-01 · Business Rule Statement |
| **Stock** | | |
| Stock must never be negative | SO-01, ARCH-02, DB-01 | §11 SO-01 / ARCH-02 / DB-01 · Business Rule Statement |
| Every movement traceable via the stock ledger | PO-01, SO-01, DB-01 | §11 PO-01 / SO-01 / DB-01 · Business Rule Statement |
| ProductStock and StockLedger written in one transaction and kept consistent | ARCH-02 | §11 ARCH-02 · Business Rule Statement |
| Concurrent issues must not oversell or lose an update | ARCH-02 | §11 ARCH-02 · Business Rule Statement |
| Adjustment retained as a ledger value with no Phase 1 workflow | — | `DEC-010` (scope decision, not a business rule) |

---

# 17. PRODUCT STATUS MODEL

Classification: **INDEX / SUMMARY** — high-level lifecycle view only. Transition conditions, actors,
and permitted-cancellation detail are defined only in the STATE MATRIX (§19.3). No transition is
defined in both places.

## Purchase Order

```text
Draft
→ Ordered
→ PartiallyReceived
→ Received
```

dan permitted cancellation.

## Sales Order

```text
Draft
→ PendingApproval
→ Approved
→ Fulfilled
```

dan permitted cancellation.

Rejection of a Sales Order terminates at `Cancelled` per `DEC-009`, carried as open mandatory
assumption `ASM-001`. No status value outside the SRC-001 §1.3 sets is introduced.

---

# 18. PRODUCT NFR

Classification: **CANONICAL OWNER**

No SLA, latency, throughput, availability, or scalability target is stated, because SRC-001 states
none.

## Security

Authenticated and authorized access. Authorization is enforced server-side in every case, including
segregation of duties; passwords are hashed with the PHP password API; session identifiers are
renewed on login; every input-bearing query is a prepared statement; user output is escaped before
HTML; uploaded files are type- and size-validated and stored under unguessable names; no active
secret is committed.

References: AUTH-01, AUTH-02, USR-01, PRD-01, MSTR-01, SO-01, ERR-01, DB-01, ENV-01 · §19.5

## Reliability

No negative stock and consistent stock state. ProductStock and StockLedger are mutated together
inside one explicit transaction and remain mutually consistent; concurrent goods issues neither
oversell nor lose an update; referential integrity is enforced by primary and foreign keys;
database exceptions and stack traces are never surfaced to users.

References: PO-01, SO-01, ARCH-02, DB-01, ERR-01 · §19.6, §19.7

## Usability

360px and desktop usability. Login, dashboard, list, detail, and form pages remain usable at a
360px viewport and on desktop with no clipped navigation or table content; every form field carries
a visible label; focus state and basic contrast are perceivable; empty states are informative;
main lists paginate at 10 per page with filters preserved across pages.

References: UI-01, VIEW-01, FIND-01 · §23

## Reproducibility

Clean environment setup. The application and database start from a clean checkout via Docker
Compose; configuration comes from environment variables with committed example values; no absolute
path or machine-specific configuration is relied upon; schema and seed build the database from
empty including the demo and pagination minimums.

References: ENV-01, DB-01 · §19.12

## Testability

Business logic can be independently tested. Logic is exercised without a real database through a
fake repository implementation; at least 6 unit tests span at least 3 logic areas; at least 3
integration tests exercise real MySQL in Docker; a static analysis report carries zero critical
errors; tests satisfy FIRST.

References: ARCH-01, TEST-01, TEST-02, TEST-03 · §19.11

---

# 19. SUPPORTING MATRICES

Each matrix is a **CANONICAL OWNER** for its designated detail only. None redefines a requirement
(§11) or a business rule (§16 navigation → §11 canonical statement).

## 19.1 INPUT MATRIX

Owns input field details.

| Requirement ID | Field | Required | Type | Format | Range | Enum | Unique | FK |
|---|---|---|---|---|---|---|---|---|
| AUTH-01 | email | Yes | string | email address | — | — | No (matches User.email) | User |
| AUTH-01 | password | Yes | string | plaintext in transit, verified against hash | — | — | No | — |
| USR-01 | name | Yes | string | free text | — | — | No | — |
| USR-01 | email | Yes | string | email address | — | — | **Yes** | — |
| USR-01 | password | Yes (create) | string | plaintext in transit, stored hashed | — | — | No | — |
| USR-01 | role | Yes | enum | — | — | Admin, Sales, WarehouseStaff | No | — |
| USR-01 | is_active | Yes | boolean | — | — | true, false | No | — |
| PRD-01 | sku | Yes | string | free text | — | — | **Yes** | — |
| PRD-01 | name | Yes | string | free text | — | — | No | — |
| PRD-01 | category_id | Yes | integer | — | — | — | No | Category |
| PRD-01 | unit | Yes | string | free text | — | — | No | — |
| PRD-01 | buy_price | Yes | decimal | numeric | ≥ 0 | — | No | — |
| PRD-01 | sell_price | Yes | decimal | numeric | ≥ 0 | — | No | — |
| PRD-01 | reorder_point | Yes | integer | numeric | ≥ 0 | — | No | — |
| PRD-01 | image | No | file | validated type | validated size | — | No | — |
| PRD-01 | is_active | Yes | boolean | — | — | true, false | No | — |
| PRD-01 | category.name | Yes | string | free text | — | — | No | — |
| PRD-01 | category.description | No | string | free text | — | — | No | — |
| MSTR-01 | name | Yes | string | free text | — | — | No | — |
| MSTR-01 | contact | Yes | string | free text | — | — | No | — |
| MSTR-01 | address | Yes | string | free text | — | — | No | — |
| MSTR-01 | is_active | Yes | boolean | — | — | true, false | No | — |
| WH-01 | name | Yes | string | free text | — | — | No | — |
| WH-01 | location | Yes | string | free text | — | — | No | — |
| WH-01 | is_active | Yes | boolean | — | — | true, false | No | — |
| PO-01 | supplier_id | Yes | integer | — | — | — | No | Supplier |
| PO-01 | warehouse_id | Yes | integer | — | — | — | No | Warehouse |
| PO-01 | order_date | Yes | date | valid date | — | — | No | — |
| PO-01 | status | Yes | enum | — | — | Draft, Ordered, PartiallyReceived, Received, Cancelled | No | — |
| PO-01 | item.product_id | Yes | integer | — | — | — | No | Product |
| PO-01 | item.quantity | Yes | integer | numeric | ≥ 0 | — | No | — |
| PO-01 | item.buy_price | Yes | decimal | numeric | ≥ 0 | — | No | — |
| PO-01 | receipt.quantity | Yes | integer | numeric | 0 ≤ q ≤ outstanding | — | No | — |
| SO-01 | customer_id | Yes | integer | — | — | — | No | Customer |
| SO-01 | warehouse_id | Yes | integer | — | — | — | No | Warehouse |
| SO-01 | created_by | Yes | integer | — | — | — | No | User |
| SO-01 | approved_by | No (set on approval) | integer | — | — | — | No | User |
| SO-01 | status | Yes | enum | — | — | Draft, PendingApproval, Approved, Fulfilled, Cancelled | No | — |
| SO-01 | item.product_id | Yes | integer | — | — | — | No | Product |
| SO-01 | item.quantity | Yes | integer | numeric | ≥ 0 | — | No | — |
| SO-01 | item.sell_price | Yes | decimal | numeric | ≥ 0 | — | No | — |
| SO-01 | issue.quantity | Yes | integer | numeric | 0 < q ≤ available stock | — | No | — |
| VIEW-01 | record id (detail) | Yes | integer | — | — | — | No | owning entity |
| FIND-01 | search term | No | string | free text | — | — | No | — |
| FIND-01 | category filter | No | integer | — | — | — | No | Category |
| FIND-01 | stock status filter | No | enum | — | — | low stock, normal | No | — |
| FIND-01 | order status filter | No | enum | — | — | PO or SO status set | No | — |
| FIND-01 | date sort | No | enum | — | — | asc, desc | No | — |
| FIND-01 | page | No | integer | numeric | ≥ 1 | — | No | — |
| REPORT-01 | report type | Yes | enum | — | — | stock movement, order status | No | — |
| REPORT-01 | date_from | Yes | date | valid date | ≤ date_to | — | No | — |
| REPORT-01 | date_to | Yes | date | valid date | ≥ date_from | — | No | — |
| API-01 | sku (path parameter) | Yes | string | free text | — | — | No | Product |
| DB-01 | ProductStock.quantity | Yes | integer | numeric | **≥ 0 enforced by constraint** | — | No (composite unique product+warehouse) | Product, Warehouse |
| DB-01 | StockLedger.movement_type | Yes | enum | — | — | Receipt, Issue, Adjustment | No | — |
| DB-01 | StockLedger.reference | Yes | composite | reference_type + reference_id | — | PO, SO | No | PurchaseOrder / SalesOrder |
| ENV-01 | environment variables | Yes | key–value | per `.env` | — | — | No | — |

Requirements with no user-supplied input field: AUTH-02, DASH-01, VAL-01 (owns the rule, not the
fields), ERR-01, UI-01, JOB-01, ARCH-01, ARCH-02, TEST-01, TEST-02, TEST-03, DESIGN-01, DESIGN-02,
DESIGN-03, DESIGN-04.

## 19.2 DATA IMPACT MATRIX

Owns data impact.

| Requirement ID | Entity | Read | Create | Update | Deactivate | Delete |
|---|---|---|---|---|---|---|
| AUTH-01 | User | Yes | No | No | No | No |
| AUTH-02 | User | No | No | No | No | No |
| USR-01 | User | Yes | Yes | Yes | Yes | **No** |
| PRD-01 | Product | Yes | Yes | Yes | Yes | **No** |
| PRD-01 | Category | Yes | Yes | Yes | No | No |
| MSTR-01 | Supplier | Yes | Yes | Yes | Yes | **No** |
| MSTR-01 | Customer | Yes | Yes | Yes | Yes | **No** |
| WH-01 | Warehouse | Yes | Yes | Yes | Yes | **No** |
| WH-01 | ProductStock | Yes | Yes (row per product+warehouse) | No | No | No |
| PO-01 | PurchaseOrder | Yes | Yes | Yes (status) | No | No |
| PO-01 | PurchaseOrderItem | Yes | Yes | Yes (received qty tracking) | No | No |
| PO-01 | ProductStock | Yes | No | **Yes (increase)** | No | No |
| PO-01 | StockLedger | Yes | **Yes (Receipt)** | **No (append-only)** | No | **No** |
| SO-01 | SalesOrder | Yes | Yes | Yes (status, approver) | No | No |
| SO-01 | SalesOrderItem | Yes | Yes | Yes | No | No |
| SO-01 | ProductStock | Yes | No | **Yes (decrease)** | No | No |
| SO-01 | StockLedger | Yes | **Yes (Issue)** | **No (append-only)** | No | **No** |
| VIEW-01 | Product, PurchaseOrder, SalesOrder | Yes | No | No | No | No |
| FIND-01 | Product, PurchaseOrder, SalesOrder | Yes | No | No | No | No |
| DASH-01 | Product, ProductStock, PurchaseOrder, SalesOrder, StockLedger | Yes | No | No | No | No |
| REPORT-01 | StockLedger, PurchaseOrder, SalesOrder | Yes | No | No | No | No |
| API-01 | Product, ProductStock | Yes | No | No | No | No |
| VAL-01 | all written entities | Yes | No | No | No | No |
| JOB-01 | Product, ProductStock | Yes | No | No | No | No |
| DB-01 | all entities | Yes | Yes (schema/seed) | Yes | Yes | No |
| ARCH-02 | ProductStock, StockLedger | Yes | Yes (ledger) | Yes (stock) | No | No |
| TEST-02 | all entities (test fixtures) | Yes | Yes | Yes | Yes | Yes (test teardown only) |

Requirements with no data impact: ERR-01, UI-01, ARCH-01, ENV-01, TEST-01, TEST-03,
DESIGN-01…DESIGN-04.

## 19.3 STATE MATRIX

**Canonical owner of detailed state transitions.** §17 holds the lifecycle summary only.

| Requirement ID | Object | From | Action | To | Actor | Condition |
|---|---|---|---|---|---|---|
| USR-01 | User | active | Deactivate | inactive | Admin | Requester is Admin |
| USR-01 | User | inactive | Activate | active | Admin | Requester is Admin |
| PRD-01 | Product | active | Deactivate | inactive | Admin | Requester is Admin; permitted even when referenced by an order |
| PRD-01 | Product | inactive | Activate | active | Admin | Requester is Admin |
| MSTR-01 | Supplier | active | Deactivate | inactive | Admin | Requester is Admin |
| MSTR-01 | Supplier | inactive | Activate | active | Admin | Requester is Admin |
| MSTR-01 | Customer | active | Deactivate | inactive | Admin | Requester is Admin |
| MSTR-01 | Customer | inactive | Activate | active | Admin | Requester is Admin |
| WH-01 | Warehouse | active | Deactivate | inactive | Admin | Requester is Admin |
| WH-01 | Warehouse | inactive | Activate | active | Admin | Requester is Admin |
| PO-01 | PurchaseOrder | Draft | Order | Ordered | Admin | PO has ≥ 1 item; supplier and destination warehouse set |
| PO-01 | PurchaseOrder | Ordered | Record receipt (partial) | PartiallyReceived | Warehouse Staff, Admin | Received qty > 0 and < outstanding on at least one line; transaction committed |
| PO-01 | PurchaseOrder | Ordered | Record receipt (full) | Received | Warehouse Staff, Admin | All lines fully received; transaction committed |
| PO-01 | PurchaseOrder | PartiallyReceived | Record receipt (partial) | PartiallyReceived | Warehouse Staff, Admin | Outstanding remains after this receipt; transaction committed |
| PO-01 | PurchaseOrder | PartiallyReceived | Record receipt (full) | Received | Warehouse Staff, Admin | All lines fully received; transaction committed |
| PO-01 | PurchaseOrder | Draft | Cancel | Cancelled | Admin | Permitted by the SRC-001 §1.3 PO status set |
| PO-01 | PurchaseOrder | Ordered | Cancel | Cancelled | Admin | Permitted by the SRC-001 §1.3 PO status set |
| PO-01 | PurchaseOrder | PartiallyReceived | Cancel | Cancelled | Admin | Permitted by the SRC-001 §1.3 PO status set; already-received stock and ledger rows are not reversed |
| SO-01 | SalesOrder | Draft | Submit | PendingApproval | Sales (own), Admin | SO has ≥ 1 item; customer and source warehouse set |
| SO-01 | SalesOrder | PendingApproval | Approve | Approved | **Admin only** | Requester role is Admin; approver recorded |
| SO-01 | SalesOrder | PendingApproval | Reject | Cancelled | **Admin only** | Requester role is Admin (`DEC-009`, `ASM-001`) |
| SO-01 | SalesOrder | Approved | Issue goods | Fulfilled | Warehouse Staff, Admin | Available stock in source warehouse sufficient; race-safe transaction committed |
| SO-01 | SalesOrder | Draft | Cancel | Cancelled | Sales (own), Admin | Before Fulfilled |
| SO-01 | SalesOrder | PendingApproval | Cancel | Cancelled | Sales (own), Admin | Before Fulfilled |
| SO-01 | SalesOrder | Approved | Cancel | Cancelled | Admin | Before Fulfilled; any reservation released |
| ARCH-02 | ProductStock | quantity = n | Receipt commit | quantity = n + received | Warehouse Staff, Admin | Inside one transaction with the ledger write |
| ARCH-02 | ProductStock | quantity = n | Issue commit | quantity = n − issued | Warehouse Staff, Admin | n − issued ≥ 0; inside one race-safe transaction with the ledger write |
| ARCH-02 | StockLedger | (absent) | Append Receipt row | Receipt row present | Warehouse Staff, Admin | Same transaction as the ProductStock increase |
| ARCH-02 | StockLedger | (absent) | Append Issue row | Issue row present | Warehouse Staff, Admin | Same transaction as the ProductStock decrease |

**No transition to a `Rejected` status exists**, because SRC-001 §1.3 defines no such value. Rejection
resolves to `Cancelled` per `DEC-009`. **No `Adjustment` transition row exists**, because `DEC-010`
places no adjustment workflow in Phase 1 scope while retaining the ledger enum value.

## 19.4 AUTHORIZATION MATRIX

**Canonical owner of authorization enforcement detail, including the SoD rule.** §13 holds the
capability overview only.

| Requirement ID | Auth Required | Allowed Role | Forbidden Role | Ownership Rule | Server Enforcement |
|---|---|---|---|---|---|
| AUTH-01 | No (entry point) | Any with valid active credential | Inactive account | — | Credential and active-status check server-side; session established server-side |
| AUTH-02 | Yes | Admin, Sales, Warehouse Staff | — | Own session only | Session data cleared server-side |
| USR-01 | Yes | Admin | Sales, Warehouse Staff | — | Page and endpoint both guarded; non-Admin receives 403 |
| PRD-01 | Yes | Admin (manage) | Sales, Warehouse Staff (manage) | — | Write endpoints Admin-only; read permitted per §13 |
| MSTR-01 | Yes | Admin | Sales, Warehouse Staff | — | Write and read endpoints Admin-only; non-Admin receives 403 |
| WH-01 | Yes | Admin (manage); all (view stock) | Sales, Warehouse Staff (manage) | — | Write endpoints Admin-only |
| PO-01 | Yes | Admin (create, receive); Warehouse Staff (propose, receive) | Sales | — | Create and receipt endpoints role-checked server-side; Sales receives 403 |
| SO-01 | Yes | Sales (create/submit own); Admin (create, approve, reject, cancel); Warehouse Staff (issue) | Sales and Warehouse Staff on approve/reject | Sales may act only on its own Sales Orders | Every action role-checked server-side before any state transition |
| SO-01 · **SoD-1** | Yes | Admin | **Sales — entirely, including its own order**; Warehouse Staff | Creator identity is irrelevant: Sales is denied regardless of ownership (`DEC-012`) | Approve and reject endpoints reject a Sales requester with 403 and perform no transition |
| SO-01 · **SoD-2** | Yes | — | — | — | Enforcement resides in the server authorization layer. Hiding or disabling the UI control is **not** enforcement and does not satisfy this rule |
| VIEW-01 | Yes | All, scoped | — | Sales sees own orders; Warehouse Staff sees stock and fulfilment scope | Scope applied in the query, not the template |
| FIND-01 | Yes | All, scoped | — | Same scope as VIEW-01 | Scope applied in the query |
| DASH-01 | Yes | All, scoped | — | Admin all data; Sales own orders; Warehouse Staff stock and fulfilment | Aggregation scoped server-side per role |
| REPORT-01 | Yes | Admin (all); Sales (own orders); Warehouse Staff (stock) | — | Sales export limited to own orders | Export scope applied server-side; out-of-scope request receives 403 |
| API-01 | Yes | Any authenticated | Unauthenticated | — | Same authentication rule as HTML pages; unauthenticated receives 401 JSON |
| ERR-01 | Contextual | — | — | — | Unauthenticated → redirect; unauthorised → 403; not found → 404 |
| JOB-01 | No (CLI, outside web session) | Script operator with container access | — | — | Not reachable over HTTP; execution requires container access |
| UI-01 | Yes | All | — | — | **UI visibility is never authorization** — every rule above is enforced server-side |

## 19.5 SECURITY MATRIX

Owns security controls.

| Requirement ID | Threat | Control | Enforcement Layer | Verification |
|---|---|---|---|---|
| AUTH-01 | Unauthorized Access | Session required for every protected resource | Controller (authentication boundary) | Demo: open a protected page with no session |
| AUTH-01 | Session Fixation | Session ID regenerated on successful login | Controller / session handling | Compare session identifier before and after login |
| AUTH-01 | Credential disclosure via error text | Safe generic failure message with no field-level detail | Service → Controller | Demo: wrong email vs wrong password produce identical messaging |
| AUTH-01 | Password compromise | `password_hash()` storage, `password_verify()` verification | Service | Inspect stored hash; confirm no plaintext column |
| AUTH-01 | Revoked staff access | Inactive account refused at login | Service | Demo: login attempt with an inactive account |
| AUTH-02 | Session Abuse | Authentication data cleared on logout; protected URL re-guarded | Controller | Demo: reopen a protected URL after logout |
| USR-01 | Privilege Escalation | Admin-only guard on user administration page **and** endpoint | Controller + Service | Test as Sales and as Warehouse Staff against both |
| USR-01 | Identity collision | Unique email constraint | Service + database | Duplicate-email creation attempt |
| PRD-01 | Invalid File Upload | File type and size validation before storage | Service | Upload an invalid type and an oversize file |
| PRD-01 | Upload path guessing | Random, unguessable stored filename | Service | Inspect stored filename entropy |
| PRD-01 | History destruction | Referenced product may only be deactivated | Service | Delete attempt on a referenced product |
| MSTR-01 | Privilege Escalation | Admin-only guard on supplier/customer endpoints | Controller + Service | Test as Sales and as Warehouse Staff |
| MSTR-01 | History destruction | Referenced counterparty may only be deactivated | Service | Delete attempt on a referenced counterparty |
| SO-01 | SoD Bypass | Approve/reject denied to Sales entirely, server-side | Service (authorization) | Demo: Sales attempts to approve its own order → 403 |
| SO-01 | Stock Manipulation | Issue permitted only for Approved SO and only within available stock | Service | Issue attempt on non-Approved SO; issue attempt exceeding stock |
| PO-01 | Stock Manipulation | Receipt bounded by outstanding quantity; stock mutated only via the stock service | Service | Receipt attempt exceeding outstanding quantity |
| ARCH-02 | Stock Manipulation | ProductStock and StockLedger written in one transaction; database concurrency control | Service + database | Controlled concurrent-issue scenario |
| DB-01 | SQL Injection | PDO prepared statements for every input-bearing query; no concatenation of user input | Concrete Repository | Static analysis; grep for concatenated SQL; injection attempt |
| DB-01 | Data corruption | PK, FK, `quantity >= 0` constraint, relevant indexes | Database | Constraint violation attempt |
| VAL-01 | Invalid data persistence | Backend validation authoritative; nothing written on failure | Service | Invalid-input scenario list |
| ERR-01 | Secret / internal disclosure | Database exceptions and stack traces never rendered | Controller | Deliberate failure path demo |
| ERR-01 | Unauthorized Access | 403 on authenticated-but-unauthorised; 404 on missing | Controller | Two deliberate failure paths |
| API-01 | Unauthorized Access | Same authentication rule as HTML; 401 JSON when absent | Controller | Call endpoint with and without session |
| API-01 | Information leak via error page | JSON body and correct status, never an HTML error page | Controller | Inspect `Content-Type` and body on 401 and 404 |
| UI-01 | XSS | User output escaped before rendering in HTML | View layer | Inject markup into a text field and inspect rendered output |
| ENV-01 | Secret Exposure | Configuration by environment variable; `.env` only; no active secret committed | Environment + repository hygiene | Inspect repository and history for committed secrets |
| ARCH-01 | Business-logic tampering via infrastructure coupling | Business logic isolated from PDO, session, superglobals | Service | Unit tests run with no database or session |

## 19.6 TRANSACTION MATRIX

Owns transaction scope.

| Requirement ID | Required | Scope | Commit Condition | Rollback Condition |
|---|---|---|---|---|
| PO-01 (Goods Receipt) | **YES — mandatory** | StockLedger `Receipt` row insert + ProductStock increase + PurchaseOrder/Item received-quantity update | All writes succeed and received quantity is within outstanding | Any write fails; received quantity exceeds outstanding; constraint violation |
| SO-01 (Goods Issue) | **YES — mandatory** | StockLedger `Issue` row insert + ProductStock decrease + SalesOrder status update to Fulfilled | All writes succeed, available stock was sufficient, and resulting quantity ≥ 0 | Any write fails; insufficient available stock; `quantity >= 0` constraint would be violated; competing concurrent issue detected |
| ARCH-02 | **YES — mandatory** | The two stock writes above, as one atomic unit | Both the ProductStock mutation and the StockLedger append succeed | Either write fails — neither may take effect |
| SO-01 (Approve / Reject) | Recommended | SalesOrder status update + approver assignment | Role check passed and status was PendingApproval | Role check fails; status not PendingApproval |
| PO-01 (Order) | Recommended | PurchaseOrder status update | Status was Draft and PO has ≥ 1 item | Validation fails |
| USR-01 | Recommended | User row insert or update | Email unique and role valid | Uniqueness or enum violation |
| PRD-01 | Recommended | Product row insert or update, plus image reference | SKU unique and numerics ≥ 0 and image valid | Any validation failure |
| MSTR-01 | Recommended | Supplier or Customer row insert or update | Required fields present | Validation failure |
| WH-01 | Recommended | Warehouse row insert or update, plus per-warehouse ProductStock row creation | Required fields present | Validation failure |
| VAL-01 | N/A | Validation precedes any transaction; a failed validation opens none | — | — |
| AUTH-01 | No | Session establishment is not a database transaction | — | — |
| DB-01 | **YES — mandatory rule owner** | Any operation writing more than one table | — | — |
| TEST-02 | Yes (test fixtures) | Fixture setup and teardown | Test completes | Test fails or aborts |
| ARCH-01 | N/A | Transaction boundary is owned by the Service layer, not the Repository or Controller | — | — |

Requirements with no transaction scope: AUTH-02, VIEW-01, FIND-01, DASH-01, REPORT-01, API-01,
ERR-01, UI-01, JOB-01, ENV-01, TEST-01, TEST-03, DESIGN-01…DESIGN-04.

## 19.7 CONCURRENCY MATRIX

Owns concurrency controls.

| Requirement ID | Shared Resource | Race Risk | Invariant | Protection Boundary | Verification |
|---|---|---|---|---|---|
| ARCH-02 | ProductStock row (product + warehouse) | Two goods issues read the same available quantity and both decrement, producing oversell | **No negative stock** — quantity ≥ 0 at all times | Database transaction and concurrency control on the ProductStock row; enforced by the `quantity >= 0` constraint as a backstop | Controlled scenario: second issue rejected or deferred once stock is exhausted by the first (TEST-02) |
| ARCH-02 | ProductStock row | Two concurrent writes overwrite one another's result | **No lost update** — the final quantity reflects every committed movement | Database concurrency control within the transaction; last-read value must not be blindly written | Controlled scenario comparing final quantity against the sum of committed movements |
| ARCH-02 | ProductStock + StockLedger pair | One write commits without the other, leaving stock unexplainable | **ProductStock / StockLedger consistency** — every quantity is reconstructable from ledger rows | Single explicit transaction spanning both writes | Reconcile ProductStock against the sum of its ledger rows after concurrent operations |
| SO-01 | ProductStock row for the SO source warehouse | Two issues for one Approved SO, or two SOs competing for the same stock | **No oversell** | Same as ARCH-02 — validated inside the transaction, not before it | Issue attempt when stock already exhausted by a committed issue |
| PO-01 | ProductStock row for the PO destination warehouse | Two receipts for the same PO line double-count, or a receipt exceeds outstanding | Outstanding quantity never negative; received total never exceeds ordered | Transaction covering the receipt writes and the outstanding recomputation | Concurrent receipt attempt against the same PO line |
| DB-01 | ProductStock row | Any path that mutates stock outside the stock service | Stock mutated only via the service that writes the ledger | `quantity >= 0` database constraint plus repository boundary | Constraint violation attempt; code review of write paths |
| WH-01 | ProductStock row set | Concurrent creation of duplicate rows for one product+warehouse | Exactly one ProductStock row per product per warehouse | Composite unique constraint on (product, warehouse) | Duplicate-row insert attempt |
| TEST-02 | Real MySQL instance | Test interference across concurrent test runs | Test independence (FIRST) | Fixture isolation per test | Repeated suite runs in differing order |

Redis is explicitly **not** a protection boundary in any row — see §20 and `DEC-005`.

Requirements with no concurrency exposure: AUTH-01, AUTH-02, USR-01, PRD-01, MSTR-01, VIEW-01,
FIND-01, DASH-01, REPORT-01, API-01, VAL-01, ERR-01, UI-01, JOB-01, ARCH-01, ENV-01, TEST-01,
TEST-03, DESIGN-01…DESIGN-04.

## 19.8 REDIS MATRIX

**Canonical owner of detailed Redis usage mapping.** Detail only — the decision is `DEC-005`, the
high-level constraint is §07, the boundary summary is §20. None is restated here.

| Requirement ID | Data category | Purpose | Lifecycle | TTL | Failure behavior | Security consideration |
|---|---|---|---|---|---|---|
| AUTH-01 | Authentication session state | Hold the authenticated identity and role for the session | Created on successful login; identifier regenerated at that moment | 3600 s | Session unavailable → treat as unauthenticated and redirect to login; never fall back to an unauthenticated-but-permitted state | Must contain no password and no password hash |
| AUTH-01 | Temporary security state | Short-lived security markers associated with the authentication attempt | Created during the authentication exchange | ≤ 3600 s | Absent state → deny and restart the exchange | Must not become a business record |
| AUTH-02 | Authentication session state | Remove authentication data on logout | Deleted on logout | n/a (deleted) | Deletion failure → still treat the session as terminated at the application boundary | Stale key must never re-authenticate a user |
| API-01 | Authentication session state | Verify the caller's session under the same rule as HTML pages | Read-only during the request | 3600 s (inherited) | Session unavailable → 401 JSON | No business payload cached |
| ENV-01 | — | Compose a Redis service alongside the mandatory application and MySQL services | Container lifetime | n/a | Redis service unavailable → authentication degrades to unauthenticated; MySQL-backed business function is unaffected | No credential committed; configured by environment variable |

**Redis must not store:** `Password`, `Password Hash`, `Product`, `ProductStock`, `PO`, `SO`,
`StockLedger`. No row above stores a business entity. Redis appears in no TRANSACTION MATRIX row and
in no CONCURRENCY MATRIX protection boundary.

## 19.9 MEMCACHED MATRIX

**Canonical owner of detailed Memcached usage mapping.** Detail only — the decision is `DEC-006`,
the high-level constraint is §07, the boundary summary is §21.

| Requirement ID | Cached read | Cache-aside behavior | Key concept | Expiration concept | Invalidation trigger | Failure fallback | Consistency consideration |
|---|---|---|---|---|---|---|---|
| PRD-01 | Product and Category master-data reads | Read cache → on miss read MySQL → populate cache | Entity kind + identifier | Short, bounded expiry | Any Product or Category create, update, or activation change | Read MySQL directly | A stale product read must never influence a stock decision; stock is never cached |
| MSTR-01 | Supplier and Customer master-data reads | Read cache → on miss read MySQL → populate cache | Entity kind + identifier | Short, bounded expiry | Any Supplier or Customer create, update, or activation change | Read MySQL directly | An inactive counterparty must not remain selectable from cache after deactivation |
| WH-01 | Warehouse list reads | Read cache → on miss read MySQL → populate cache | Entity kind + identifier | Short, bounded expiry | Any Warehouse create, update, or activation change | Read MySQL directly | Warehouse identity only — **per-warehouse stock quantity is never cached** |
| VIEW-01 | Master-data lookups supporting list rendering | Read cache → on miss read MySQL | Entity kind + identifier | Short, bounded expiry | Owning entity write | Read MySQL directly | Cached labels only; never authoritative figures |
| FIND-01 | Category and warehouse filter option lists | Read cache → on miss read MySQL | Filter option set | Short, bounded expiry | Owning entity write | Read MySQL directly | Filter results themselves are queried live |
| DASH-01 | Master-data labels only | Read cache → on miss read MySQL | Entity kind + identifier | Short, bounded expiry | Owning entity write | Read MySQL directly | **Aggregated figures are never cached** — DASH-01 requires live aggregation |
| PO-01 | Supplier, warehouse, product labels during PO composition | Read cache → on miss read MySQL | Entity kind + identifier | Short, bounded expiry | Owning entity write | Read MySQL directly | Stock and outstanding quantity are never cached |
| SO-01 | Customer, warehouse, product labels during SO composition | Read cache → on miss read MySQL | Entity kind + identifier | Short, bounded expiry | Owning entity write | Read MySQL directly | **Available stock is never read from cache** — issue validation reads authoritative stock inside the transaction |
| API-01 | Product identity resolution by SKU | Read cache → on miss read MySQL | Product SKU | Short, bounded expiry | Product write | Read MySQL directly | Per-warehouse availability figures come from MySQL, not cache |
| ENV-01 | — | Compose a Memcached service alongside the mandatory services | n/a | n/a | n/a | Cache unavailable → all reads served by MySQL | No credential committed |

**Candidate data is limited to** Product, Category, Warehouse, Supplier, Customer. MySQL remains
authoritative in every row. `ProductStock`, `StockLedger`, `PurchaseOrder`, `SalesOrder`, and any
aggregated figure are never cached.

## 19.10 ACCEPTANCE MATRIX

Owns acceptance criteria.

| AC-ID | Requirement ID | Given | When | Then | Negative Case |
|---|---|---|---|---|---|
| AC-AUTH-01-1 | AUTH-01 | An active user account exists | Valid email and password are submitted | A session is established and the role dashboard is shown | Wrong password → no session, safe message |
| AC-AUTH-01-2 | AUTH-01 | An account exists but is inactive | Correct credentials are submitted | Login is refused with a safe message | Refusal reveals that the account is inactive |
| AC-AUTH-01-3 | AUTH-01 | No session exists | A protected page is requested | The request is redirected to login | Protected content is rendered |
| AC-AUTH-01-4 | AUTH-01 | A pre-login session identifier is observed | Login succeeds | The session identifier differs from the pre-login value | Identifier unchanged after login |
| AC-AUTH-01-5 | AUTH-01 | A user account exists | The stored password is inspected | Only a `password_hash()` digest is present | Plaintext or reversible value stored |
| AC-AUTH-02-1 | AUTH-02 | An authenticated session exists | Logout is activated | Authentication data is cleared from the session | Session remains usable |
| AC-AUTH-02-2 | AUTH-02 | Logout has completed | A protected URL is reopened | The request is redirected to login | Protected content is rendered |
| AC-USR-01-1 | USR-01 | Requester is Admin | A Sales or Warehouse Staff account is created, edited, or its activation toggled | The change persists and appears in the user list | Change silently discarded |
| AC-USR-01-2 | USR-01 | An account already uses an email | A second account is created with that email | Creation is rejected and nothing is stored | Duplicate persisted |
| AC-USR-01-3 | USR-01 | Requester is Sales or Warehouse Staff | A user-administration page or endpoint is requested | 403 is returned | Page or endpoint served |
| AC-USR-01-4 | USR-01 | A role value outside the enum is submitted | The account is saved | Save is rejected | Invalid role persisted |
| AC-PRD-01-1 | PRD-01 | A product exists with a given SKU | A second product is created with that SKU | Creation is rejected | Duplicate SKU persisted |
| AC-PRD-01-2 | PRD-01 | A product form is open | A negative reorder point, buy price, or sell price is submitted | Save is rejected with field feedback | Negative value persisted |
| AC-PRD-01-3 | PRD-01 | A product is referenced by an order | Permanent deletion is attempted | Deletion is refused and deactivation is offered | Product deleted and order history broken |
| AC-PRD-01-4 | PRD-01 | A product form is open | A file of invalid type or excessive size is uploaded | Upload is rejected with feedback | Invalid file stored |
| AC-PRD-01-5 | PRD-01 | A valid image is uploaded | The stored filename is inspected | The name is random and not derivable from the original | Original or predictable name used |
| AC-MSTR-01-1 | MSTR-01 | Requester is Admin | A supplier or customer is created, edited, or its activation toggled | The change persists | Change discarded |
| AC-MSTR-01-2 | MSTR-01 | A counterparty is referenced by an order | Permanent deletion is attempted | Deletion is refused and deactivation is offered | Counterparty deleted and order history broken |
| AC-MSTR-01-3 | MSTR-01 | Requester is Sales or Warehouse Staff | A supplier or customer management endpoint is requested | 403 is returned | Endpoint served |
| AC-WH-01-1 | WH-01 | Two warehouses exist and one product is stocked in both with different quantities | The product stock view is opened | Total stock and the per-warehouse breakdown are both shown and the total equals the sum | Only a single aggregate figure shown |
| AC-WH-01-2 | WH-01 | A product and a warehouse exist | The stock rows are inspected | Exactly one stock row exists for that product and warehouse | Duplicate rows exist for the pair |
| AC-PO-01-1 | PO-01 | An active supplier, destination warehouse, and active products exist | A PO is created with supplier, destination warehouse, and items | The PO is saved as Draft | PO saved without supplier or destination warehouse |
| AC-PO-01-2 | PO-01 | A Draft PO with at least one item exists | The PO is ordered | Status becomes Ordered | Status change without items |
| AC-PO-01-3 | PO-01 | An Ordered PO exists | A full goods receipt is recorded | ProductStock increases, a `Receipt` ledger row exists, and status becomes Received | Stock increased with no ledger row |
| AC-PO-01-4 | PO-01 | An Ordered PO exists | A partial goods receipt is recorded | Status becomes PartiallyReceived and the outstanding quantity remains recorded | Outstanding quantity lost |
| AC-PO-01-5 | PO-01 | A PO line has an outstanding quantity | A receipt exceeding the outstanding quantity is submitted | The receipt is rejected and nothing is mutated | Over-receipt accepted |
| AC-PO-01-6 | PO-01 | A goods receipt is in progress | The ledger write or stock update fails | The transaction rolls back leaving stock and ledger unchanged | Partial write persists |
| AC-SO-01-1 | SO-01 | An active customer, source warehouse, and active products exist | A Sales user creates an SO with items | The SO is saved as Draft with the creator recorded | Creator not recorded |
| AC-SO-01-2 | SO-01 | A Draft SO with at least one item exists | The owning Sales user submits it | Status becomes PendingApproval | Submission without items accepted |
| AC-SO-01-3 | SO-01 | A PendingApproval SO exists | An Admin approves it | Status becomes Approved and the approver is recorded | Approver not recorded |
| AC-SO-01-4 | SO-01 | A PendingApproval SO created by the requesting Sales user exists | That Sales user attempts to approve it | 403 is returned and no transition occurs | Approval succeeds for a Sales user |
| AC-SO-01-5 | SO-01 | A PendingApproval SO created by another user exists | A Sales user attempts to approve it | 403 is returned and no transition occurs | Approval succeeds for a Sales user |
| AC-SO-01-6 | SO-01 | An Approved SO exists with sufficient source-warehouse stock | Goods issue is recorded | ProductStock decreases, an `Issue` ledger row exists, and status becomes Fulfilled | Stock decreased with no ledger row |
| AC-SO-01-7 | SO-01 | An Approved SO exists with insufficient source-warehouse stock | Goods issue is attempted | The issue is refused and nothing is mutated | Stock becomes negative |
| AC-SO-01-8 | SO-01 | An SO is not in Approved status | Goods issue is attempted | The issue is refused | Issue processed for a non-Approved SO |
| AC-VIEW-01-1 | VIEW-01 | Records exist within the requester's scope | A product, PO, or SO list and a detail page are opened | Rows are listed and the detail page renders | Out-of-scope records listed |
| AC-VIEW-01-2 | VIEW-01 | No records match | The list is opened | An informative empty state is shown | A blank table body is shown |
| AC-FIND-01-1 | FIND-01 | At least 30 products are seeded | Search by name or SKU and filters for category and stock status are applied | Only matching products are listed | Filters ignored |
| AC-FIND-01-2 | FIND-01 | At least 25 combined orders are seeded | Order search by number or counterparty, status filter, and ascending/descending date sort are applied | Only matching orders are listed in the requested order | Sort ignored |
| AC-FIND-01-3 | FIND-01 | More than 10 records match | The list is opened | Exactly 10 records are shown per page | A different page size is used |
| AC-FIND-01-4 | FIND-01 | Filters are active on page 1 | The user moves to page 2 | The same filters remain in effect | Filters reset on page change |
| AC-DASH-01-1 | DASH-01 | Requester is Admin | The dashboard is opened | Inventory value, below-reorder-point products, and pending orders per status are shown | Any figure is a static value |
| AC-DASH-01-2 | DASH-01 | Requester is Sales | The dashboard is opened | Only that user's own orders per status are summarised | Other users' orders included |
| AC-DASH-01-3 | DASH-01 | Requester is Warehouse Staff | The dashboard is opened | Goods receipt and issue queues plus low-stock products are shown | Unrelated financial data shown |
| AC-DASH-01-4 | DASH-01 | Underlying data changes | The dashboard is reloaded | The figures change accordingly | Figures unchanged, proving a static source |
| AC-REPORT-01-1 | REPORT-01 | Stock movements exist in a date range | A stock movement CSV is exported for that range | The CSV contains the movements in that range | Rows outside the range included |
| AC-REPORT-01-2 | REPORT-01 | Orders exist in a date range | An order status CSV is exported for that range | The CSV contains those orders with status | Range ignored |
| AC-REPORT-01-3 | REPORT-01 | A dashboard figure is displayed | The corresponding CSV is exported for the same period | The export agrees with the dashboard figure | Export and dashboard disagree |
| AC-API-01-1 | API-01 | An authenticated session exists and the SKU exists | `GET /api/products/{sku}/availability` is called | 200 with `Content-Type: application/json` and per-warehouse stock is returned | HTML returned |
| AC-API-01-2 | API-01 | No valid session exists | The endpoint is called | 401 with a JSON body is returned | HTML error page returned |
| AC-API-01-3 | API-01 | An authenticated session exists but the SKU does not | The endpoint is called | 404 with a JSON body is returned | 200 with an empty body returned |
| AC-VAL-01-1 | VAL-01 | A form with a missing required field | Submission is attempted | Save is rejected and nothing is stored | Partial record stored |
| AC-VAL-01-2 | VAL-01 | Frontend validation is bypassed | An invalid payload reaches the backend | The backend rejects it | Backend accepts what the frontend would have blocked |
| AC-VAL-01-3 | VAL-01 | A form fails validation with data already entered | The error is returned | Previously entered input is preserved where relevant | All input cleared |
| AC-ERR-01-1 | ERR-01 | No session exists | A protected resource is requested | The request is redirected to login | 500 returned |
| AC-ERR-01-2 | ERR-01 | An authenticated user lacks the required authority | The resource is requested | 403 is returned | Resource served |
| AC-ERR-01-3 | ERR-01 | A record or route does not exist | It is requested | 404 is returned | 200 with empty content |
| AC-ERR-01-4 | ERR-01 | A database error occurs | The page is rendered | A safe message is shown with no exception text or stack trace | Stack trace displayed |
| AC-UI-01-1 | UI-01 | The viewport is 360px wide | Login, dashboard, list, detail, and a form are opened | Each remains usable with no clipped navigation or table content | Navigation unreachable or table clipped |
| AC-UI-01-2 | UI-01 | A form is displayed | Its fields are inspected | Every field has a visible label and focus state and basic contrast are perceivable | Unlabelled field or invisible focus state |
| AC-JOB-01-1 | JOB-01 | Products exist below their reorder point | The standalone script is run via `docker compose exec` | A summary of below-reorder-point products is printed | Script requires the web request cycle |
| AC-JOB-01-2 | JOB-01 | The database is unreachable | The script is run | A handled error is printed with no stack trace | Stack trace printed |
| AC-DB-01-1 | DB-01 | The schema is applied | Tables are inspected | Every table has a primary key and every relationship a foreign key | Missing PK or FK |
| AC-DB-01-2 | DB-01 | A ProductStock row exists | A negative quantity is written | The database rejects the write | Negative quantity stored |
| AC-DB-01-3 | DB-01 | Any input-bearing query | The query is inspected | It is a PDO prepared statement with no concatenated user input | Concatenated SQL found |
| AC-DB-01-4 | DB-01 | An empty database | Schema and seed are executed per the README | A working database is produced with ≥ 30 products, ≥ 25 combined orders, 1 Admin, ≥ 2 Sales, ≥ 2 Warehouse Staff, ≥ 2 warehouses, some products below reorder point, and orders including PendingApproval and Cancelled | Seed incomplete against these minimums |
| AC-DB-01-5 | DB-01 | A multi-table stock operation | It is executed | It runs inside an explicit transaction | Multi-table write without a transaction |
| AC-ARCH-01-1 | ARCH-01 | The Service layer | Its dependencies are inspected | It depends on repository interfaces and receives them by constructor injection | Service instantiates PDO internally |
| AC-ARCH-01-2 | ARCH-01 | The codebase | Business logic is inspected | It references no PDO, session, or superglobal | Superglobal read inside a Service |
| AC-ARCH-01-3 | ARCH-01 | At least one repository interface | Its implementations are counted | Both a MySQL implementation and an in-memory fake exist | Only one implementation exists |
| AC-ARCH-01-4 | ARCH-01 | The unit test suite | It is run with no database available | Business-logic tests still pass | Tests require a live database |
| AC-ARCH-02-1 | ARCH-02 | A goods receipt or issue | The write path is inspected | ProductStock mutation and StockLedger append occur inside one transaction | Either write occurs outside the transaction |
| AC-ARCH-02-2 | ARCH-02 | Stock is exhausted by a first committed issue | A second issue for the same product and warehouse is attempted | It is rejected or deferred; stock never goes negative | Oversell occurs |
| AC-ARCH-02-3 | ARCH-02 | Two concurrent issues commit | The final quantity is compared with the sum of committed movements | They agree — no update was lost | Final quantity reflects only one movement |
| AC-ARCH-02-4 | ARCH-02 | Concurrent operations have completed | ProductStock is reconciled against its ledger rows | The quantity is fully explained by the ledger | Quantity unexplainable from the ledger |
| AC-ENV-01-1 | ENV-01 | A clean checkout and a host with Docker | `docker compose up --build` is run and the README procedure followed | Application and MySQL services start and the application is reachable | Startup requires undocumented manual steps |
| AC-ENV-01-2 | ENV-01 | The repository | It is inspected for configuration | Configuration comes from environment variables with `.env` committed and no active secret present | Active credential committed |
| AC-ENV-01-3 | ENV-01 | The source code | Paths and configuration are inspected | No absolute path or machine-specific configuration is relied upon | Hard-coded host path found |
| AC-TEST-01-1 | TEST-01 | The unit test suite | Test cases are counted by logic area | At least 6 cases exist across at least 3 distinct logic areas | Fewer than 6, or concentrated in fewer than 3 areas |
| AC-TEST-01-2 | TEST-01 | A unit test | Its dependencies are inspected | It touches no session, real PDO, or external service, and is not a trivial getter/setter test | Unit test opens a database connection |
| AC-TEST-02-1 | TEST-02 | The integration suite | Tests are counted | At least 3 exist and each exercises real MySQL in Docker | A counted test never reaches MySQL |
| AC-TEST-02-2 | TEST-02 | An empty stock position | A goods receipt is executed end-to-end | Stock increases in the database and a ledger row is present | End-to-end effect absent |
| AC-TEST-02-3 | TEST-02 | Stock exhausted by a first issue | A second issue is executed in the controlled scenario | It is rejected or deferred, demonstrating ARCH-02 | Second issue succeeds |
| AC-TEST-03-1 | TEST-03 | The static analysis report | It is inspected | Zero critical errors are present and any remaining warning is explained | Critical errors present, or warnings unexplained |
| AC-TEST-03-2 | TEST-03 | The test suite | It is inspected and re-run in a different order | No `sleep()`, no real network call, and results are order-independent | Order-dependent or timing-dependent test |
| AC-DESIGN-01-1 | DESIGN-01 | Before coding began | `docs/planning/` is inspected | An initial class diagram exists showing Controller/Service/Repository/Entity and their relationships | Initial diagram produced after implementation |
| AC-DESIGN-01-2 | DESIGN-01 | Implementation is complete | `docs/architecture/` is inspected | An as-built diagram exists distinguishing interface-directed from concrete-directed dependencies | Markers absent |
| AC-DESIGN-01-3 | DESIGN-01 | Both diagrams exist | A class is traced from diagram to code during defense | The class is found and matches the diagram | Diagram does not match code |
| AC-DESIGN-02-1 | DESIGN-02 | `docs/architecture/` | ADR files are counted | 2–3 ADRs exist, each with context, decision, and consequences | Fewer than 2 ADRs |
| AC-DESIGN-02-2 | DESIGN-02 | An ADR | Its subject is inspected | It records a genuine architectural decision, not a trivial implementation choice | Trivial choice recorded as an ADR |
| AC-DESIGN-03-1 | DESIGN-03 | `docs/quality/refactor-log.md` | Entries are counted | At least 3 entries exist, each naming a smell, a technique, and a before/after excerpt | Fewer than 3, or entries missing a component |
| AC-DESIGN-03-2 | DESIGN-03 | The quality documentation | The SRP audit note is inspected | One initial-draft class violating SRP is identified with its split described | No SRP note present |
| AC-DESIGN-03-3 | DESIGN-03 | `docs/quality/tech-debt.md` | It is inspected | Shortcuts and limitations are recorded with their ideal fix | Known shortcut omitted |
| AC-DESIGN-03-4 | DESIGN-03 | Commit history | It is inspected | At least one `refactor:`-tagged commit improves pre-existing code | Only feature commits present |
| AC-DESIGN-04-1 | DESIGN-04 | An assessor-supplied problematic excerpt | `docs/quality/critique.md` is inspected | It names the smells, the violated SOLID principles, and the refactoring direction | Critique absent or names neither smell nor principle |

**95 acceptance criteria across 28 requirements.**

## 19.11 TEST TRACEABILITY MATRIX

Owns test mapping. Values: `REQUIRED` · `OPTIONAL` · `N/A`.

| Requirement ID | Unit Test | Integration Test | Security Test | Concurrency Test | Manual Demo |
|---|---|---|---|---|---|
| AUTH-01 | REQUIRED | REQUIRED | REQUIRED | N/A | REQUIRED |
| AUTH-02 | OPTIONAL | OPTIONAL | REQUIRED | N/A | REQUIRED |
| USR-01 | REQUIRED | OPTIONAL | REQUIRED | N/A | REQUIRED |
| PRD-01 | REQUIRED | OPTIONAL | REQUIRED | N/A | REQUIRED |
| MSTR-01 | OPTIONAL | OPTIONAL | REQUIRED | N/A | REQUIRED |
| WH-01 | REQUIRED | OPTIONAL | N/A | N/A | REQUIRED |
| PO-01 | REQUIRED | REQUIRED | N/A | OPTIONAL | REQUIRED |
| SO-01 | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| VIEW-01 | OPTIONAL | OPTIONAL | N/A | N/A | REQUIRED |
| FIND-01 | REQUIRED | OPTIONAL | N/A | N/A | REQUIRED |
| DASH-01 | OPTIONAL | REQUIRED | N/A | N/A | REQUIRED |
| REPORT-01 | OPTIONAL | OPTIONAL | N/A | N/A | REQUIRED |
| API-01 | OPTIONAL | REQUIRED | REQUIRED | N/A | REQUIRED |
| VAL-01 | REQUIRED | OPTIONAL | N/A | N/A | REQUIRED |
| ERR-01 | OPTIONAL | OPTIONAL | REQUIRED | N/A | REQUIRED |
| UI-01 | N/A | N/A | N/A | N/A | REQUIRED |
| JOB-01 | REQUIRED | OPTIONAL | N/A | N/A | REQUIRED |
| DB-01 | N/A | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| ARCH-01 | REQUIRED | N/A | N/A | N/A | REQUIRED |
| ARCH-02 | REQUIRED | REQUIRED | N/A | REQUIRED | REQUIRED |
| ENV-01 | N/A | REQUIRED | REQUIRED | N/A | REQUIRED |
| TEST-01 | Self | N/A | N/A | N/A | REQUIRED |
| TEST-02 | N/A | Self | N/A | REQUIRED | REQUIRED |
| TEST-03 | REQUIRED | REQUIRED | N/A | N/A | REQUIRED |
| DESIGN-01 | N/A | N/A | N/A | N/A | REQUIRED |
| DESIGN-02 | N/A | N/A | N/A | N/A | REQUIRED |
| DESIGN-03 | N/A | N/A | N/A | N/A | REQUIRED |
| DESIGN-04 | N/A | N/A | N/A | N/A | REQUIRED |

Unit-test logic areas satisfying TEST-01's "≥ 3 areas": PO date validation (PO-01), SO status
transition (SO-01), low-stock calculation (JOB-01/WH-01), approval ownership/authorization (SO-01),
validation rules (VAL-01), pagination/filter logic (FIND-01).

## 19.12 EVIDENCE MATRIX

Owns evidence mapping.

| Requirement ID | Evidence Type | Artifact | Demo |
|---|---|---|---|
| AUTH-01 | Demo + test result | `docs/testing/` login scenarios | Login as three roles; failed login; protected page without session |
| AUTH-02 | Demo | `docs/testing/` logout scenario | Logout, then attempt to reopen a protected page |
| USR-01 | Demo + test result | `docs/testing/` user CRUD and access tests | Limited user CRUD; duplicate email; access attempt as Sales and as Warehouse Staff |
| PRD-01 | Demo + test result | `docs/testing/` product scenarios; stored image sample | Create/edit/deactivate; reorder point validation; invalid file upload |
| MSTR-01 | Demo | `docs/testing/` supplier and customer scenarios | Create/edit/deactivate; non-Admin access attempt |
| WH-01 | Screenshot + demo | `docs/testing/` stock screenshots | One product with different stock in two warehouses |
| PO-01 | Demo + ledger extract | `docs/testing/` PO scenarios; StockLedger rows | Create PO; full and partial goods receipt; resulting StockLedger contents |
| SO-01 | Demo + test result | `docs/testing/` SO scenarios | Draft→Fulfilled flow; Sales approve attempt; goods issue with insufficient stock |
| VIEW-01 | Screenshot | `docs/testing/` with-data and no-data screenshots | List and detail with data and with none |
| FIND-01 | Demo | `docs/testing/` search/filter/sort evidence | Combined search, filter, sort; move across at least two pages |
| DASH-01 | Query + demo | Aggregation query listing; dashboard screenshots | Dashboard for all three roles |
| REPORT-01 | Exported files | Two CSV files with differing date ranges | Export with two different ranges |
| API-01 | Demo + response capture | `docs/testing/` API responses | Call with and without authentication; unknown SKU |
| VAL-01 | Scenario list | `docs/testing/` invalid-input scenarios and results | Invalid input list and outcomes |
| ERR-01 | Demo | `docs/testing/` failure paths | At least two deliberate, safe failure paths |
| UI-01 | Screenshot | Desktop and mobile screenshots of the four main pages | Responsive walkthrough at 360px and desktop |
| JOB-01 | Demo + output capture | Script output sample | Run `php scripts/check-low-stock.php` via Docker and show the summary |
| DB-01 | ERD + schema + explanation | ERD; `schema-and-seed.sql`; one multi-table transaction explanation; one index explanation | Build database from empty; explain one transaction and one index |
| ARCH-01 | Code + test run | Repository interface with two implementations | Unit test of a Service running on the fake repository |
| ARCH-02 | Explanation + test | Written mechanism explanation; controlled concurrency test | Show the second request rejected or deferred when stock is exhausted |
| ENV-01 | Build log + config | `Dockerfile`; `compose.yaml`; `.env` | `docker compose up --build` from a clean folder |
| TEST-01 | Test report | `docs/testing/` unit results | Run the suite by the single README command |
| TEST-02 | Test report | `docs/testing/` integration results | Run integration tests against MySQL in Docker |
| TEST-03 | Analysis report | `docs/quality/` static analysis report | Show zero critical errors and explain remaining warnings |
| DESIGN-01 | Diagrams | `docs/planning/` initial diagram; `docs/architecture/` as-built diagram; change note | Trace one class from diagram to code |
| DESIGN-02 | ADR files | `docs/architecture/adr-*.md` | Discuss one ADR |
| DESIGN-03 | Logs + commit history | `docs/quality/refactor-log.md`; `docs/quality/tech-debt.md`; `refactor:` commit | Show one refactoring and the tagged commit |
| DESIGN-04 | Written critique | `docs/quality/critique.md` | Discuss the critique briefly at defense |

## 19.14 CRITICAL FAILURE MATRIX

Owns critical failure mapping.

| Requirement ID | Failure Mode | Prevention | Verification | Evidence |
|---|---|---|---|---|
| ENV-01 | CF-1 — application or database will not start via Docker | Compose definition with app and MySQL services; environment-variable configuration; no absolute paths | Clean-folder build and start following the README | §19.12 ENV-01 |
| AUTH-01 | CF-2 — core flow non-functional at login | Working authentication with role routing | Login demo across three roles | §19.12 AUTH-01 |
| PO-01 | CF-2, CF-6, CF-7 — purchase flow display-only, or stock changed without ledger, or receipt non-transactional | Receipt writes ledger and stock in one transaction through the stock service | Receipt demo plus resulting ledger rows; rollback scenario | §19.12 PO-01 |
| SO-01 | CF-2, CF-5, CF-6, CF-7 — sales flow display-only, authorization only in frontend, stock changed without ledger, or issue non-transactional | Server-side approval authorization; issue writes ledger and stock in one race-safe transaction | Full Draft→Fulfilled demo; Sales approve attempt; insufficient-stock attempt | §19.12 SO-01 |
| ARCH-02 | CF-7 — oversell reproducible by the assessor | One transaction spanning both stock writes; database concurrency control; `quantity >= 0` constraint as backstop | Controlled concurrent-issue scenario | §19.12 ARCH-02 |
| DB-01 | CF-5, CF-6 — raw user input in SQL, or stock inconsistent with ledger | PDO prepared statements everywhere; stock mutated only via the stock service; database constraints | Static analysis; concatenation grep; reconciliation of stock against ledger | §19.12 DB-01 |
| AUTH-01 | CF-5 — plaintext password | `password_hash()` / `password_verify()` | Inspect stored credential | §19.12 AUTH-01 |
| ENV-01 | CF-5 — active secret committed | `.env` only; secrets by environment variable | Repository and history inspection | §19.12 ENV-01 |
| USR-01 | CF-5 — authorization only in frontend | Page and endpoint both guarded server-side | Access attempts as Sales and Warehouse Staff | §19.12 USR-01 |
| ARCH-01 | CF-3 — prohibited framework, ORM, or DI container | Hand-written layered architecture; repository interfaces; manual constructor injection | Dependency inspection against §08 | §19.12 ARCH-01 |
| TEST-01 | CF-4 — no valid unit tests, or all failing | ≥ 6 isolated unit cases across ≥ 3 logic areas | Suite run by the README command | §19.12 TEST-01 |
| TEST-02 | CF-4 — no valid integration tests, or all failing | ≥ 3 integration tests against real MySQL in Docker | Suite run against the Docker environment | §19.12 TEST-02 |
| DESIGN-01 | CF-8 — class diagram not traceable to code | Initial diagram before coding; as-built diagram matching code with interface/concrete markers | Trace one class from diagram to code | §19.12 DESIGN-01 |
| DESIGN-02 | CF-9 — participant cannot explain own architecture | ADRs recording context, decision, consequences for real decisions | Discuss one ADR unaided | §19.12 DESIGN-02 |
| DASH-01 | CF-2 — core feature is display without real data | Every figure from an aggregation query | Change data, reload, observe the figure change | §19.12 DASH-01 |
| VIEW-01 | CF-2 — list features are display-only | Real scoped queries behind list and detail | With-data and no-data screenshots | §19.12 VIEW-01 |

CF-10 (concealed AI or external-source use) is a process obligation of the submission package and is
not mapped to a requirement row; SRC-001 §6.2 places it in `ai-usage-log.md`.

## 19.15 DEPENDENCY MATRIX

Owns requirement dependencies.

| Requirement ID | Depends On | Reason |
|---|---|---|
| AUTH-01 | USR-01, DB-01, ENV-01 | Accounts must exist and be persisted in a runnable environment |
| AUTH-02 | AUTH-01 | There must be a session to end |
| USR-01 | AUTH-01, DB-01 | Administration requires an authenticated Admin and persistence |
| PRD-01 | AUTH-01, USR-01, DB-01, VAL-01 | Admin-only management with validated input and persistence |
| MSTR-01 | AUTH-01, USR-01, DB-01, VAL-01 | Same as PRD-01 for counterparties |
| WH-01 | AUTH-01, PRD-01, DB-01 | Per-warehouse stock rows presuppose products and warehouses |
| PO-01 | AUTH-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02, VAL-01 | A PO needs a supplier, destination warehouse and products; receipt needs the transactional stock mechanism |
| SO-01 | AUTH-01, USR-01, MSTR-01, WH-01, PRD-01, DB-01, ARCH-02, VAL-01 | An SO needs a customer, source warehouse and products; approval needs roles; issue needs the transactional stock mechanism |
| VIEW-01 | AUTH-01, PRD-01, PO-01, SO-01, ERR-01 | There must be records to list and a defined not-found behaviour |
| FIND-01 | VIEW-01, PRD-01, PO-01, SO-01, DB-01 | Search and pagination operate over the listed records and seeded volume |
| DASH-01 | AUTH-01, PRD-01, WH-01, PO-01, SO-01, DB-01 | Aggregations require the underlying operational data |
| REPORT-01 | AUTH-01, DASH-01, PO-01, SO-01, DB-01 | Exports must share the dashboard aggregation basis |
| API-01 | AUTH-01, PRD-01, WH-01, DB-01 | Availability requires authentication, products and per-warehouse stock |
| VAL-01 | USR-01, PRD-01, MSTR-01, WH-01, PO-01, SO-01, FIND-01, REPORT-01, API-01 | Applies to every input-accepting requirement |
| ERR-01 | AUTH-01, VIEW-01, API-01 | Failure classification spans authentication, pages and the API |
| UI-01 | AUTH-01, VIEW-01, DASH-01 | The four main page kinds must exist to be made responsive |
| JOB-01 | PRD-01, WH-01, DB-01, ARCH-01, ENV-01 | Low-stock calculation needs products, stock, reusable logic and a container to run in |
| DB-01 | ENV-01, ARCH-01 | MySQL must run, and access flows through the repository boundary |
| ARCH-01 | DB-01, TEST-01 | The repository boundary exists to serve persistence and testability |
| ARCH-02 | DB-01, PO-01, SO-01, TEST-02 | The transactional invariant applies to receipt and issue and is proven by integration testing |
| ENV-01 | DB-01 | The composed environment must bring up the database the schema targets |
| TEST-01 | ARCH-01 | Isolated tests are only possible given the repository boundary |
| TEST-02 | ENV-01, DB-01, ARCH-02, PO-01, SO-01 | Integration tests need Docker, MySQL, and the stock operations to exercise |
| TEST-03 | TEST-01, TEST-02 | Static analysis and FIRST apply to the existing suites |
| DESIGN-01 | ARCH-01 | The diagram depicts the layered structure |
| DESIGN-02 | ARCH-01, ARCH-02 | The ADR subjects are the repository boundary and the concurrency mechanism |
| DESIGN-03 | ARCH-01, TEST-01 | Refactoring safety depends on the boundary and the test suite |
| DESIGN-04 | ARCH-01 | The critique is assessed against the same architectural principles |

---

# 20. REDIS TECHNICAL BOUNDARY

**Section class:** REFERENCE / BOUNDARY SUMMARY.

**Canonical ownership statement (per frozen Phase 1 prompt §3.8):**

| Information class | Canonical owner | This section's relationship |
|---|---|---|
| The decision to permit Redis at all, and its authority | `DEC-005` in §04 DECISION REGISTRY | Reference only |
| The conflict status of Redis against the brief | `CON-002` in §05 CONFLICT GOVERNANCE | Reference only |
| The high-level technical constraint | §07 TECHNICAL BASELINE | Reference only |
| The per-requirement Redis position | `Redis Ref` field of each requirement block in §11 | Reference only |
| The detailed permitted/forbidden usage mapping | §19.8 REDIS MATRIX | Reference only |

This section defines **no new rule**. It states the boundary in one place so that Phase 2 can locate it without re-deriving it.

### 20.1 Boundary Statement

Redis is **not named anywhere in the Project Brief** (`SRC-001`). Its presence in this project originates entirely from `DEC-005`, a Confirmed User Decision, and is therefore an addition *beyond* the brief rather than a fulfilment *of* the brief. `CON-002` records this and resolves it in favour of retaining the decision under a scoped boundary.

The scope boundary, as owned by §19.8, is:

- **Permitted:** ephemeral, reconstructible, non-authoritative data only.
- **Forbidden:** anything that would make Redis a system of record, a correctness dependency, or the primary mechanism for stock concurrency control.

### 20.2 Non-Negotiable Consequences (restated, not created)

| # | Consequence | Canonical origin |
|---|---|---|
| B-R1 | Redis must never hold authoritative stock quantities. | §11 `PRD-01`, `ARCH-02` — `Redis Ref` |
| B-R2 | Redis must never be the concurrency-control mechanism for stock mutation. `ARCH-02` requires database-level control. | §11 `ARCH-02` — `Concurrency Ref`, `Redis Ref` |
| B-R3 | Redis must never hold session state in a way that makes authorization decisions unverifiable server-side. | §11 `AUTH-02` — `Redis Ref` |
| B-R4 | A total Redis outage must degrade performance only, never correctness. | §19.8 REDIS MATRIX |
| B-R5 | Redis must never be introduced as a substitute for a missing index, a missing transaction, or a missing constraint. | §08 TECHNOLOGY PROHIBITION MATRIX (unnecessary-architecture records) |
| B-R6 | Redis usage must be justifiable against the brief's "no unnecessary architecture" quality expectation. | `ASM-002` in §06 ASSUMPTION REGISTER |

### 20.3 Phase 1 Position

Phase 1 does **not** specify Redis keys, TTLs, serialization formats, client libraries, connection handling, or cache invalidation strategies. Those are Phase 2 design concerns and are deliberately left unspecified here. Phase 1 fixes only the boundary above.

**Redis Boundary Status:** DEFINED — reference-only, no rule created in this section.

---

# 21. MEMCACHED TECHNICAL BOUNDARY

**Section class:** REFERENCE / BOUNDARY SUMMARY.

**Canonical ownership statement (per frozen Phase 1 prompt §3.8):**

| Information class | Canonical owner | This section's relationship |
|---|---|---|
| The decision to permit Memcached at all | `DEC-006` in §04 DECISION REGISTRY | Reference only |
| The conflict status against the brief | `CON-003` in §05 CONFLICT GOVERNANCE | Reference only |
| The high-level technical constraint | §07 TECHNICAL BASELINE | Reference only |
| The per-requirement position | `Memcached Ref` field of each requirement block in §11 | Reference only |
| The detailed permitted/forbidden usage mapping | §19.9 MEMCACHED MATRIX | Reference only |

### 21.1 Boundary Statement

Memcached is **not named anywhere in the Project Brief** (`SRC-001`). Its presence originates entirely from `DEC-006`, a Confirmed User Decision. `CON-003` records and resolves this on the same basis as `CON-002`.

The scope boundary owned by §19.9 is identical in kind to Redis and narrower in capability:

- **Permitted:** volatile, non-authoritative, fully reconstructible key/value caching only.
- **Forbidden:** any authoritative storage, any correctness dependency, any concurrency-control role, any durability assumption.

### 21.2 Non-Negotiable Consequences (restated, not created)

| # | Consequence | Canonical origin |
|---|---|---|
| B-M1 | Memcached must never hold authoritative stock quantities. | §11 `PRD-01`, `ARCH-02` — `Memcached Ref` |
| B-M2 | Memcached must never be the concurrency-control mechanism for stock mutation. | §11 `ARCH-02` — `Concurrency Ref`, `Memcached Ref` |
| B-M3 | Memcached provides no durability guarantee; eviction at any moment must be functionally invisible. | §19.9 MEMCACHED MATRIX |
| B-M4 | Memcached must never be the sole store of any data required to satisfy a requirement in §11. | §19.9 MEMCACHED MATRIX |
| B-M5 | Redis and Memcached must not both be introduced for the same purpose. Overlap without justification is unnecessary architecture. | `ASM-002` in §06 ASSUMPTION REGISTER |

### 21.3 Phase 1 Position

Phase 1 does **not** specify Memcached keys, expiries, client configuration, or the Redis-versus-Memcached selection rule per use case. `ASM-002` remains the governing assumption that scoped usage does not violate the brief's quality expectation, and it is Phase 2's obligation to demonstrate that per use case or to decline the technology for that use case.

**Memcached Boundary Status:** DEFINED — reference-only, no rule created in this section.

---

# 22. STOCK TECHNICAL BOUNDARY

**Section class:** REFERENCE / BOUNDARY SUMMARY.

**Canonical ownership statement (per frozen Phase 1 prompt §3.8):**

| Information class | Canonical owner | This section's relationship |
|---|---|---|
| The stock business rules | `Business Rule Statement` of `PRD-01`, `PO-01`, `SO-01`, `ARCH-02` in §11 | Reference only |
| The stock technical constraints | §07 TECHNICAL BASELINE | Reference only |
| Transaction boundaries | §19.6 TRANSACTION MATRIX | Reference only |
| Concurrency control | §19.7 CONCURRENCY MATRIX | Reference only |
| State transitions that move stock | §19.3 STATE MATRIX | Reference only |
| Critical failure conditions touching stock | §19.14 CRITICAL FAILURE MATRIX | Reference only |

This section is the single navigational entry point for stock integrity. It introduces no rule.

### 22.1 Boundary Statement

Stock is the correctness core of this system. The brief's §8.2 critical failure list makes stock integrity a pass/fail condition rather than a quality attribute. Accordingly the boundary is stated as an invariant set, each invariant already owned elsewhere.

| # | Invariant | Canonical origin |
|---|---|---|
| B-S1 | `ProductStock.quantity` must never become negative. | §11 `PRD-01` — `Business Rule Statement` |
| B-S2 | Every change to `ProductStock` must be accompanied by a `StockLedger` row written in the **same** explicit database transaction. | §11 `ARCH-02` — `Transaction Ref`; §19.6 |
| B-S3 | `StockLedger` is append-only. No `UPDATE`, no `DELETE`. | §11 `PRD-01` — `Business Rule Statement`; §19.5 DATA IMPACT MATRIX |
| B-S4 | Concurrent stock mutation must not produce a lost update. Control is at the database level. | §11 `ARCH-02` — `Concurrency Ref`; §19.7 |
| B-S5 | Stock may be increased only by a `Receipt` and decreased only by an `Issue`, except for `Adjustment`, whose Phase 1 position is fixed by `DEC-010`. | §19.3 STATE MATRIX; `DEC-010` |
| B-S6 | Stock movement is a consequence of an order state transition, never a free-standing user edit of a quantity field. | §19.3 STATE MATRIX |
| B-S7 | A failed stock mutation must roll back completely — no partial `ProductStock` write, no orphan ledger row. | §19.6 TRANSACTION MATRIX |
| B-S8 | Neither Redis nor Memcached participates in stock authority or stock concurrency control. | §20, §21; §11 `ARCH-02` |

### 22.2 Phase 1 Position on `Adjustment`

The `StockLedger.type` enum value `Adjustment` is fixed by the brief's §1.3 entity table and is therefore retained in the data model. The brief specifies **no workflow, no actor, no authorization rule, and no trigger** for it. Per `DEC-010`, Phase 1 retains the enum value and defines **no** adjustment workflow. `GAP-003` remains recorded rather than resolved by invention. Any Phase 2 attempt to design an adjustment workflow would be an addition beyond the brief and requires a new decision record.

### 22.3 Phase 1 Position on Locking Strategy

Phase 1 fixes **that** database-level concurrency control is mandatory (`ARCH-02`) and that it must prevent lost updates and overselling. Phase 1 deliberately does **not** select between pessimistic row locking (`SELECT ... FOR UPDATE`) and optimistic versioning. That selection is a Phase 2 architectural decision and is listed as such in §19.7.

**Stock Boundary Status:** DEFINED — reference-only, no rule created in this section.

---

# 23. EXISTING UI/UX BASELINE MAPPING

**Section class:** REFERENCE / MAPPING.

**Canonical ownership statement:** the UI design facts are owned by `SRC-004` (`docs/design/ioms-ui-design.md`). This section maps requirement IDs to that existing baseline. It **does not** redesign, extend, or re-specify the UI, and it does not restate design token values — those remain in `SRC-004`.

### 23.1 Mapping Rules Applied

1. The existing UI/UX baseline is treated as an **existing artifact to be respected**, not as a source of product requirements. Where the baseline shows a feature the brief does not require, the baseline does not create a requirement.
2. Where the baseline is silent, no screen is invented.
3. Where the baseline and the brief disagree, the brief wins and the disagreement is recorded as a discrepancy, not silently reconciled.

### 23.2 Requirement → Existing Screen Mapping

| Requirement ID | Existing baseline surface (per `SRC-004`) | Mapping status |
|---|---|---|
| AUTH-01 | Login screen | MAPPED |
| AUTH-02 | Application shell — role-conditioned navigation | MAPPED |
| USR-01 | User management list and form screens | MAPPED |
| PRD-01 | Product list, product detail, stock display | MAPPED |
| MSTR-01 | Category / Supplier / Warehouse master screens | MAPPED |
| WH-01 | Warehouse master screen; per-warehouse stock display | MAPPED |
| PO-01 | Purchase Order list, create, detail, receive screens | MAPPED |
| SO-01 | Sales Order list, create, detail, approval, fulfil screens | MAPPED |
| VIEW-01 | Stock overview / stock ledger view | MAPPED |
| FIND-01 | List-screen search, filter and pagination controls | MAPPED |
| DASH-01 | Dashboard screen | MAPPED |
| REPORT-01 | Reports screen — tabular surface (canonical per `DEC-013`) | MAPPED — see §23.3 |
| VAL-01 | Form inline validation and field error patterns | MAPPED |
| ERR-01 | Error, empty and loading state patterns | MAPPED |
| UI-01 | Design system / component foundation screen and all screens | MAPPED |
| API-01 | No dedicated screen — consumed by existing screens via Fetch | NOT A SCREEN |
| JOB-01 | No screen | NOT A SCREEN |
| DB-01, ARCH-01, ARCH-02, ENV-01, TEST-01..03, DESIGN-01..04 | No screen — technical requirements | NOT A SCREEN |

### 23.3 Recorded Discrepancies Against the Baseline

These are carried, not resolved by redesign.

| ID | Discrepancy | Phase 1 treatment |
|---|---|---|
| GAP-006 | The baseline contains **two** Reports surfaces — a tabular reports screen and a reports-with-data-visualization screen. The brief's `REPORT-01` requires reports but never requires charts. | `DEC-013` designates the **tabular** surface as the canonical `REPORT-01` surface. The visualization surface is recorded as an existing artifact **beyond** brief scope; it creates no requirement and Phase 2 must not treat it as one. |
| GAP-007 | The baseline's responsive behaviour for the sidebar at narrow widths is not fully determined by the export. | Recorded. `UI-01` requires fidelity to the baseline where the baseline is determinate. Where it is indeterminate, no behaviour is invented in Phase 1. Phase 2 must either derive it from the baseline or raise a decision. |
| DISC-001 | Role label "Warehouse Staff" in brief §1.1/§1.2 versus enum value `WarehouseStaff` in §1.3. | Resolved by `DEC-011`: `WarehouseStaff` is the stored enum value; "Warehouse Staff" is the display label. Both are brief-supported; no invention. |
| DISC-002 | Baseline screens exist for features the brief does not require. | Such screens create no requirement. Listed in `SRC-004` §18.3. No Phase 1 requirement ID was created for any of them. |
| DISC-003 | `SRC-004` §18.4 records eleven internal inconsistencies within the baseline itself. | Carried as baseline defects. They are not requirements, not conflicts against the brief, and not resolved in Phase 1. Phase 2 must not propagate an inconsistency into the design without a decision record. |

### 23.4 What This Section Does Not Do

- It does not create a screen inventory (owned by `SRC-004`).
- It does not restate colors, spacing, typography, radii or shadows (owned by `SRC-004`).
- It does not define new states (owned by §19.3 for domain state, `SRC-004` for view state).
- It does not resolve `GAP-007` by choosing a responsive behaviour.

**UI/UX Baseline Mapping Status:** COMPLETE — 28 of 28 requirements mapped or explicitly classified NOT A SCREEN.

---

# 24. PRODUCT AUDIT

Classification: **AUDIT**. Owns no requirement. Every condition below was evaluated against the produced artifact, not against the presence of a heading. The **Evidence** column names the check performed.

| # | Condition | Evidence (check actually run) | Result |
|---|---|---|---|
| P-1 | Every product requirement has a Source ID | Every one of the 17 product blocks in §11 carries a non-empty `Source ID`; all cite `SRC-001` | PASS |
| P-2 | Every product requirement has a Requirement Statement | 17/17 `Requirement Statement` fields non-empty and non-placeholder | PASS |
| P-3 | Every product requirement has a Business Rule Statement | 17/17 populated; §16 is navigation-only and defines none | PASS |
| P-4 | Every product requirement has an Actor | 17/17 populated from the `Admin` / `Sales` / `WarehouseStaff` / `System` set only | PASS |
| P-5 | Every product requirement has Preconditions, Trigger, Main Flow, Postconditions | 17/17 populated; `N/A` used only where the requirement is not a user-triggered flow, and never on `PO-01`, `SO-01`, `PRD-01` | PASS |
| P-6 | Every product requirement has Acceptance Criteria | 17/17 carry `Acceptance Criteria Ref`; §19.10 holds 95 AC rows and every referenced AC ID exists | PASS |
| P-7 | No product requirement invents a status value | §17 and §19.3 use only the SRC-001 §1.3 sets; a `Rejected` value was **not** created (see `DEC-009`, `GAP-002`) | PASS |
| P-8 | No product requirement invents a role | Only the three brief roles plus `System` appear; `DEC-011` fixes value versus label | PASS |
| P-9 | No product requirement invents a screen | §23 maps only to surfaces present in `SRC-004`; the chart Reports variant was **not** adopted as a requirement (`DEC-013`) | PASS |
| P-10 | No product requirement was created to fill a matrix | Exactly 2 IDs were created: `MSTR-01` (`DEC-015`) and `ENV-01` (`DEC-016`), each backed by a quoted mandatory brief clause | PASS |
| P-11 | Out-of-scope items produced no requirement | §02.7 out-of-scope list cross-checked against the 28 §11 IDs — no overlap | PASS |
| P-12 | Segregation of duties is expressed as a server-side rule, not UI hiding | `SO-01` `SoD Ref` and §19.4 rows SoD-1 / SoD-2 state server-side enforcement; `DEC-012` fixes total denial | PASS |
| P-13 | Every critical failure condition from SRC-001 §8.2 maps to at least one requirement | §19.14 contains all 10 CF conditions, each with ≥1 requirement ID | PASS |
| P-14 | Status vocabulary is honest for unimplemented work | All 28 blocks carry `DOCUMENTED`; a scan for `IMPLEMENTED`, `TESTED` or `VERIFIED` used as a requirement status returns zero | PASS |
| P-15 | No placeholder text | Scanned for unresolved-placeholder tokens across all 31 sections; zero occurrences outside this audit row itself | PASS |

**Product Audit: 15 conditions evaluated, 15 PASS, 0 FAIL.**

---

# 25. TECHNICAL AUDIT

Classification: **AUDIT**.

| # | Condition | Evidence (check actually run) | Result |
|---|---|---|---|
| T-1 | Every technical requirement has a Source ID | 11/11 technical blocks in §11 carry a populated `Source ID` | PASS |
| T-2 | Every technical requirement traces to a product need or an explicit brief clause | 11/11 cite an SRC-001 clause; `ENV-01` additionally cites CF-1 | PASS |
| T-3 | The mandated stack is stated once | §07 TECHNICAL BASELINE is the only place the stack is enumerated; §11 and §08 reference it | PASS |
| T-4 | No prohibited technology appears as permitted anywhere | §08's 40 records cross-checked against §07 and all 28 §11 blocks — no contradiction found | PASS |
| T-5 | PHP version statement is internally consistent | §07 states `8.2+` (brief floor) with `8.3.20` as the project-fixed version via `DEC-001`; `CON-001` records this as **no conflict** | PASS |
| T-6 | Architecture layering is stated once and not contradicted | `ARCH-01` owns the layering; `DESIGN-01`/`DESIGN-02`/`DESIGN-03`/`DESIGN-04` reference it without restating a different structure | PASS |
| T-7 | Stock integrity is transactional and database-controlled | `ARCH-02` `Transaction Ref` / `Concurrency Ref`; §19.6 and §19.7 agree; §22 restates without adding | PASS |
| T-8 | Redis is never authoritative or concurrency-controlling | §19.8 forbids both; `ARCH-02` `Redis Ref` forbids both; §20 B-R1/B-R2 restate | PASS |
| T-9 | Memcached is never authoritative or concurrency-controlling | §19.9 and `ARCH-02` `Memcached Ref`; §21 B-M1/B-M2 restate | PASS |
| T-10 | Persistence uses PDO prepared statements exclusively | §07 Database; `DB-01` and §19.5 SECURITY MATRIX SQL-injection rows | PASS |
| T-11 | Locking strategy is deliberately deferred, not silently chosen | §19.7 and §22.3 both state the choice is a Phase 2 decision; no Phase 1 text selects one | PASS |
| T-12 | Testing requirements are traceable | `TEST-01`/`TEST-02`/`TEST-03` present; §19.11 maps all 28 requirements to a test position | PASS |
| T-13 | Environment reproducibility is a requirement, not an aside | `ENV-01` exists via `DEC-016` and maps to CF-1 in §19.14 | PASS |
| T-14 | No technical requirement introduces an unrequested technology | The 11 technical blocks name only §07 items plus the two decision-permitted caches | PASS |
| T-15 | Technical status vocabulary is honest | 11/11 `DOCUMENTED` | PASS |

**Technical Audit: 15 conditions evaluated, 15 PASS, 0 FAIL.**

---

# 26. DUPLICATION AUDIT

Classification: **AUDIT**. Enforces DEFINE ONCE → REFERENCE EVERYWHERE ELSE.

| # | Information class | Canonical owner | Duplicate definitions found | Result |
|---|---|---|---|---|
| D-1 | Source records | §03 | 0 — `SRC-001`…`SRC-004` each defined in exactly one row | PASS |
| D-2 | Decision records | §04 | 0 — `DEC-001`…`DEC-016` each defined in exactly one row | PASS |
| D-3 | Conflict records | §05 | 0 — `CON-001`…`CON-004` unique | PASS |
| D-4 | Assumption records | §06 | 0 — `ASM-001`…`ASM-003` unique | PASS |
| D-5 | Requirement definitions | §11 | 0 — each of the 28 IDs opens exactly one block; §10 and §12 are INDEX/SUMMARY and add no field | PASS |
| D-6 | Business rules | §11 `Business Rule Statement` | 0 — §16 contains only navigation labels pointing back to §11 | PASS |
| D-7 | Technology stack | §07 | 0 — §08, §09 and §11 reference it | PASS |
| D-8 | Forbidden technologies | §08 | 0 — §07 references, does not enumerate | PASS |
| D-9 | Status model | §17 (product) / §19.3 (transitions) | 0 — §11 blocks reference; no third enumeration | PASS |
| D-10 | Role capabilities | §13 | 0 — §19.4 is the authorization *matrix* keyed by requirement, not a second capability list | PASS |
| D-11 | Redis / Memcached scope | §19.8 / §19.9 | 0 — §20 and §21 are declared boundary summaries that create no rule | PASS |
| D-12 | Stock invariants | §11 `PRD-01` / `ARCH-02` | 0 — §22 restates with an explicit canonical-origin column | PASS |
| D-13 | UI design facts | `SRC-004` | 0 — §23 maps only; no token, color, spacing or radius value is restated | PASS |
| D-14 | Acceptance criteria | §19.10 | 0 — 95 AC IDs, each defined once, referenced from §11 | PASS |
| D-15 | Requirement ID catalogue | §11 | 0 — §12 lists IDs and names only | PASS |

**Duplication Audit: 15 classes evaluated, 0 duplicate definitions, 15 PASS.**

---

# 27. REFERENCE AUDIT

Classification: **AUDIT**. Every reference must resolve to an existing, correctly classified target.

| # | Check | Evidence (check actually run) | Result |
|---|---|---|---|
| R-1 | All `§19.x` matrix references resolve | Every distinct `§19.x` reference in the document was matched against an existing `## 19.x` heading — 0 missing | PASS |
| R-2 | All top-level section references resolve | Every distinct `§NN` reference matched against an existing `# NN.` heading; the only unmatched token is `§25` at line 5, which refers to the **frozen Phase 1 prompt's** §25 output order and is labelled as such | PASS |
| R-3 | All named matrix references resolve | The 15 matrix names referenced across §11 all correspond to §19.1–§19.15 | PASS |
| R-4 | All `SRC-*` references resolve | Only `SRC-001`…`SRC-004` are referenced; all four are defined in §03 | PASS |
| R-5 | All `DEC-*` references resolve | Only `DEC-001`…`DEC-016` are referenced; all defined in §04 | PASS |
| R-6 | All `CON-*` references resolve | `CON-001`…`CON-004` referenced and defined | PASS |
| R-7 | All `ASM-*` references resolve | `ASM-001`…`ASM-003` referenced and defined | PASS |
| R-8 | All requirement ID references resolve | Every requirement ID appearing in §10, §12–§19, §22 and §23 exists as a §11 block; 0 orphans | PASS |
| R-9 | All `AC-*` references resolve | 95 distinct AC IDs referenced from §11; 95 AC rows in §19.10; exact set match | PASS |
| R-10 | All `CF-*` references resolve | CF-1…CF-10 referenced; all 10 defined in §02.9 and used in §19.14 | PASS |
| R-11 | Pre-coding finding references resolve | `GAP-001`…`GAP-007` and `DISC-001`…`DISC-003` all exist in `SRC-004`-adjacent analysis input and are dispositioned in §31.2 | PASS |
| R-12 | No reference points at a section of the wrong class | §16 → §11 (navigation → canonical), §20/§21/§22 → owners, §23 → `SRC-004`; the earlier §18 mis-citation of §19.13 was corrected to §19.12 | PASS |
| R-13 | No broken or empty reference field | Every `* Ref:` field in all 28 blocks is either a resolving reference or an explicit `N/A` | PASS |

**Reference Audit: 13 checks evaluated, 13 PASS, 0 broken references.**

---

# 28. SOURCE / DECISION CONSISTENCY AUDIT

Classification: **AUDIT**.

| # | Check | Evidence | Result |
|---|---|---|---|
| S-1 | Source registry contains exactly the approved sources | §03 has 4 rows. No fifth `SRC-*` was created for the pre-coding analysis — it is consumed as analysis input under the approved sequencing correction, which explicitly forbade a new source row | PASS |
| S-2 | Authority hierarchy is applied, not merely stated | `CON-004` resolves in favour of `SRC-001` over the prompt's own §10 list; `DEC-014` records it. The brief outranked the governing prompt where they differed | PASS |
| S-3 | Every requirement's Source ID cites a real clause | Each of the 28 `Source ID` fields names a section, table, or FAQ item of the cited source rather than the source alone | PASS |
| S-4 | No requirement is attributed to `SRC-001` for content absent from the brief | Redis and Memcached appear in **zero** of the brief's 19 pages; they are attributed to `DEC-005`/`DEC-006` and flagged as beyond-brief by `CON-002`/`CON-003`, never to `SRC-001` | PASS |
| S-5 | Every decision has an Authority classification | 16/16 rows classify as Confirmed or Derived; none is unclassified | PASS |
| S-6 | Every Derived decision names its derivation basis | `DEC-009`, `DEC-011`, `DEC-012` each quote or cite the brief clauses they derive from | PASS |
| S-7 | No decision was invented to complete a column | `DEC-009`…`DEC-016` each resolve a named pre-coding finding or a named conflict; the mapping is shown in §31.2 | PASS |
| S-8 | The pre-coding analysis was not treated as a source of truth | No requirement cites the analysis as its authority; analysis-only artifacts (user stories, DFD, class diagram, `CAND-*`) created no requirement of their own | PASS |
| S-9 | `CANDIDATE — UNASSIGNED` items were not silently promoted | `CAND-016` was promoted only via the explicit `DEC-015` (`MSTR-01`). `CAND-060`, `CAND-061`, `CAND-062` remain unassigned and are listed in §31.3 | PASS |
| S-10 | Source and decision IDs are stable and non-reused | No ID gap-filling or renumbering; `DEC-001`…`DEC-008` preserved verbatim from the frozen prompt | PASS |

**Source / Decision Consistency Audit: 10 checks evaluated, 10 PASS, 0 FAIL.**

---

# 29. CONFLICT AUDIT

Classification: **AUDIT**.

| # | Check | Evidence | Result |
|---|---|---|---|
| C-1 | Every identified conflict has a record | 4 conflicts recorded in §05: `CON-001`…`CON-004` | PASS |
| C-2 | Every conflict record names both sides and the resolving authority | 4/4 populated | PASS |
| C-3 | No conflict was silently resolved | Each of the 4 carries an explicit resolution statement and the authority applied | PASS |
| C-4 | No conflict remains OPEN | `CON-001` CLOSED (no actual conflict — `8.2+` is a floor, `8.3.20` satisfies it); `CON-002`, `CON-003`, `CON-004` RESOLVED | PASS |
| C-5 | Resolution never overrode a higher authority with a lower one | `CON-004` resolved *toward* the brief. `CON-002`/`CON-003` did not convert a user decision into a brief requirement; they recorded it as an addition beyond the brief and bounded it | PASS |
| C-6 | Conflicts arising from the UI baseline are not resolved by redesign | `GAP-006` resolved by designating an existing surface canonical (`DEC-013`), not by altering either screen. `DISC-003`'s eleven baseline inconsistencies are carried, not fixed | PASS |
| C-7 | No new conflict was introduced by Phase 1's own decisions | The 16 decisions were cross-checked against §07, §08 and the 28 §11 blocks; no decision contradicts another | PASS |

**Conflict Audit: 7 checks evaluated, 7 PASS. Open conflicts: 0.**

---

# 30. FINAL MATRIX AUDIT

Classification: **AUDIT**. Each matrix is checked for existence, coverage, and absence of invented content.

| # | Matrix | Rows / coverage | Invented content | Result |
|---|---|---|---|---|
| M-1 | §19.1 INPUT MATRIX | Covers every requirement with user or system input | None | PASS |
| M-2 | §19.2 DATA IMPACT MATRIX | Covers every requirement that reads or writes data; entities limited to the SRC-001 §1.3 set | None | PASS |
| M-3 | §19.3 STATE MATRIX | Covers PO and SO transitions only, with the brief's status values only. Explicitly records that **no `Rejected` transition exists** and **no `Adjustment` transition row exists** | None | PASS |
| M-4 | §19.4 AUTHORIZATION MATRIX | Covers every requirement with an actor; SoD-1 and SoD-2 carried as distinct rows | None | PASS |
| M-5 | §19.5 SECURITY MATRIX | Covers authentication, authorization, injection, session, and input-validation concerns traced to requirements | None | PASS |
| M-6 | §19.6 TRANSACTION MATRIX | Covers every multi-write operation; stock mutation and ledger write shown in one boundary | None | PASS |
| M-7 | §19.7 CONCURRENCY MATRIX | Covers stock mutation paths; locking strategy explicitly deferred to Phase 2 | None | PASS |
| M-8 | §19.8 REDIS MATRIX | Permitted / forbidden usage mapping; authority is `DEC-005` | None | PASS |
| M-9 | §19.9 MEMCACHED MATRIX | Permitted / forbidden usage mapping; authority is `DEC-006` | None | PASS |
| M-10 | §19.10 ACCEPTANCE MATRIX | 95 AC rows; every one of the 28 requirements has ≥1 AC | None | PASS |
| M-11 | §19.11 TEST TRACEABILITY MATRIX | All 28 requirements mapped to a test position | None | PASS |
| M-12 | §19.12 EVIDENCE MATRIX | All 28 requirements mapped to a deliverable artifact | None | PASS |
| M-14 | §19.14 CRITICAL FAILURE MATRIX | All 10 SRC-001 §8.2 conditions present, each mapped to ≥1 requirement | None | PASS |
| M-15 | §19.15 DEPENDENCY MATRIX | Dependencies stated for all requirements that have them; no circular dependency detected | None | PASS |

**Supporting matrices required: 15. Present: 15. PASS: 15. FAIL: 0.**

Cross-matrix consistency spot-checks actually run:

| Check | Result |
|---|---|
| Every AC ID referenced in §11 exists in §19.10, and every §19.10 AC ID is referenced from §11 (exact set match, 95 = 95) | PASS |
| Every status value in §19.3 exists in §17 and in SRC-001 §1.3 | PASS |
| Every entity in §19.2 exists in SRC-001 §1.3 | PASS |
| Every role in §19.4 exists in §13 and in `DEC-011` | PASS |
| Every CF in §19.14 exists in §02.9 | PASS |
| No requirement appears in §19.8 or §19.9 as depending on a cache for correctness | PASS |

---

# 31. PHASE 1 EXIT STATUS

## 31.1 Exit Criteria Evaluation

| # | Exit criterion | Evaluation | Result |
|---|---|---|---|
| E-1 | One canonical requirements baseline exists | §11, 28 blocks, 39 fields each | MET |
| E-2 | One canonical source registry exists | §03, 4 rows, no second registry | MET |
| E-3 | One canonical decision registry exists | §04, 16 rows, no second registry | MET |
| E-4 | All supporting matrices exist and are populated | §19.1–§19.15, 15 of 15 | MET |
| E-5 | All conflicts are recorded and resolved | §05, 4 records, 0 open | MET |
| E-6 | All assumptions are recorded with impact and mandatory flag | §06, 3 records | MET |
| E-7 | The technical baseline is traceable | §07 + 11 technical requirements, all with Source IDs | MET |
| E-8 | All audits are executed against the artifact | §24–§30, 90 conditions evaluated, 90 PASS | MET |
| E-9 | No broken references, no duplicate definitions | §26 and §27: 0 and 0 | MET |
| E-10 | Every pre-coding finding requiring Phase 1 treatment is dispositioned | §31.2 — all 7 `GAP-*` and all 3 `DISC-*` treated explicitly | MET |
| E-11 | No mandatory issue is hidden | `ASM-001` is declared in §01, §04, §06, §11 `SO-01`, §17, §19.3 and again in §31.3 | MET |
| E-12 | No mandatory issue blocks Phase 2 entry | `ASM-001`'s blast radius is bounded to one enum value, one transition row and one badge; Phase 2 can design against `Cancelled` and revise cheaply if the trainer rules otherwise | MET |

## 31.2 Pre-Coding Findings Disposition (explicit, per instruction)

Outcome vocabulary: CONFIRMED / RESOLVED / DERIVED / ASSUMPTION / CONFLICT / REQUIREMENT CLARIFICATION.

| Finding | Phase 1 outcome | Treatment and canonical location |
|---|---|---|
| **DISC-001** — role label "Warehouse Staff" vs enum `WarehouseStaff` | **RESOLVED** (also DERIVED) | `DEC-011`: `WarehouseStaff` is the stored value, "Warehouse Staff" the display label. Both representations are brief-supported; nothing invented. §04, §13, §23.3 |
| **DISC-002** — UI baseline contains screens the brief does not require | **RESOLVED** | Such screens create no requirement. Zero requirement IDs were created for them. §23.3, and confirmed by Product Audit P-9 |
| **DISC-003** — eleven internal inconsistencies inside the UI baseline itself | **REQUIREMENT CLARIFICATION** | Carried as baseline defects, not requirements and not brief conflicts. Phase 2 may not propagate one without a new decision record. §23.3, Conflict Audit C-6 |
| **GAP-001** — Supplier/Customer master data has no official requirement ID | **RESOLVED** | `DEC-015` creates `MSTR-01`, backed by the quoted mandatory clause in SRC-001 §1.2 plus the §1.3 entities. §04, §11 `MSTR-01` |
| **GAP-002** — the brief requires reject but lists no `Rejected` status | **DERIVED + ASSUMPTION** | `DEC-009`: rejection terminates at `Cancelled`, the only brief-supported terminal state. No status invented. Carried as **open mandatory** `ASM-001` pending trainer confirmation per SRC-001 FAQ 12 — deliberately **not** silently closed. §04, §06, §11 `SO-01`, §19.3 |
| **GAP-003** — `Adjustment` movement type has no originating workflow | **CONFIRMED (scope boundary)** | `DEC-010`: the enum value is retained because the brief fixes it; **no** adjustment workflow is created. §19.3 records that no `Adjustment` transition row exists. §04, §22.2 |
| **GAP-004** — enum value vs display label for the warehouse role | **RESOLVED** | Same treatment as DISC-001 via `DEC-011` | 
| **GAP-005** — scope of the Sales approval denial | **RESOLVED** (also DERIVED) | `DEC-012`: total denial, not own-order-only. Both brief sentences quoted in the decision rationale. Enforced server-side via §19.4 SoD-1/SoD-2 | 
| **GAP-006** — two Reports surfaces in the UI baseline | **RESOLVED** | `DEC-013`: the tabular surface is canonical for `REPORT-01`; the chart variant is an existing artifact beyond brief scope and creates no requirement. §23.3 |
| **GAP-007** — responsive sidebar present only on the shell blueprint | **REQUIREMENT CLARIFICATION — carried** | Recorded as a coverage gap against `UI-01`. Where the baseline is determinate, `UI-01` requires fidelity; where indeterminate, Phase 1 invents no behaviour. Phase 2 must derive it from the baseline or raise a decision. §23.3 |

Additional carried items: `CAND-060`, `CAND-061`, `CAND-062` remain `CANDIDATE — UNASSIGNED`. They were **not** promoted to requirements, because no brief clause mandates them. Promoting them would have been invention.

## 31.3 Remaining Issues (nothing withheld)

| ID | Description | Authority to resolve | Impact if resolved differently | Status |
|---|---|---|---|---|
| `ASM-001` | A rejected Sales Order terminates at status `Cancelled` | SRC-001 / trainer clarification per FAQ 12 | One additional status enum value, one additional §19.3 transition row, one additional badge state in the SO detail UI. Bounded and cheap | **OPEN — MANDATORY** |
| `GAP-007` | Responsive behaviour of the sidebar on product screens is indeterminate in the UI baseline | `SRC-004` re-derivation, or a new Phase 2 decision | Per-screen responsive CSS on product screens; no data, API or domain impact | **CARRIED** |
| `DISC-003` | Eleven internal inconsistencies within the UI baseline | Phase 2 decision per instance | Each instance needs one design decision at the point of implementation | **CARRIED** |
| `CAND-060`, `CAND-061`, `CAND-062` | Analysis candidates with no mandating brief clause | Trainer, or a future scope decision | Out of Phase 1 scope. Not requirements | **UNASSIGNED — NOT IN SCOPE** |

Open conflicts: **0**. Open mandatory assumptions: **1** (`ASM-001`). Unresolved mandatory conflicts: **0**.

## 31.4 Declaration

**PHASE 1 STATUS: COMPLETE — with one open mandatory assumption carried visibly (`ASM-001`).**

All twelve exit criteria are MET. All ninety audit conditions across §24–§30 were evaluated against the produced artifact and returned PASS. No mandatory conflict is open. `ASM-001` is open by design rather than by omission: SRC-001 FAQ 12 instructs that the trainer be consulted before altering scope, so Phase 1 records the derived interpretation, applies it consistently, and leaves the assumption visible for confirmation instead of laundering it into a settled requirement.

This baseline is the frozen input to **PHASE 2 — ARCHITECTURE & DETAILED DESIGN BLUEPRINT**. Phase 2 may not re-derive requirements, re-open resolved conflicts, or add requirement IDs without a new decision record in §04.

**Phase 1 is frozen as of this document.**

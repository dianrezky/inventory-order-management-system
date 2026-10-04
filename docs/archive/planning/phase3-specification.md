# PHASE 3 — PRD & IMPLEMENTATION SPECIFICATION

Project: **Inventory & Order Management System — Intermediate Programmer Final Project**
Governing specification: the approved Phase 3 prompt, executed as-is.
Output structure: the approved §27 structure (28 sections). No other output order.

Prerequisites verified before execution:

```text
PRE-CODING PRODUCT & SYSTEM ANALYSIS = COMPLETE   docs/planning/pre-coding-analysis.md
PHASE 1                              = COMPLETE / FROZEN   docs/planning/phase1-baseline.md
PHASE 2                              = COMPLETE / FROZEN   docs/planning/phase2-blueprint.md
```

Authority applied:

```text
Project Brief - Programmer.pdf   → assessment authority          (SRC-001)
phase1-baseline.md               → canonical requirements / decisions
phase2-blueprint.md              → approved architecture / design
pre-coding-analysis.md           → analytical context             (SRC-004)
```

## Reading Conventions

| Notation | Resolves to |
|---|---|
| `AUTH-01` … `DESIGN-04` | A canonical requirement row in `phase1-baseline.md` §11 |
| `DEC-001` … `DEC-016` | `phase1-baseline.md` §04 DECISION REGISTRY |
| `CON-001` … `CON-004` | `phase1-baseline.md` §05 |
| `ASM-001` … `ASM-003` | `phase1-baseline.md` §06 |
| `P1 §19.x` | A supporting matrix in `phase1-baseline.md` |
| `P2 §nn` | A section of `phase2-blueprint.md` |
| `AC-*` | An acceptance criterion in `phase1-baseline.md` §19.10 (95 rows) |
| `TX-1`, `TX-2` · `CC-1`…`CC-5` · `SD-*` · `SEC-*` · `RD-*` · `MC-*` · `DG-*` · `ADR-*` | Phase 2 design identifiers |
| `US-001` … `US-046` | User stories, finalized in §05 of this document |
| `UC-A1` … `UC-G2` | Use cases, adopted verbatim from `P2 §05` |

## What Phase 3 Does and Does Not Own

Phase 3 owns **no** canonical requirement, **no** business rule, **no** decision, **no** design
element, and **no** registry. It owns the *implementation-ready specification* of what Phase 1 and
Phase 2 already fixed. Concretely:

| Information class | Canonical owner | Phase 3 relationship |
|---|---|---|
| Product and technical requirements | `phase1-baseline.md` §11 | Specified in implementation terms; never redefined |
| Business rule statements | `phase1-baseline.md` §11 `Business Rule Statement` | Formalized as numbered rules that **cite** the canonical statement |
| Sources, decisions, conflicts, assumptions | `phase1-baseline.md` §03–§06 | Referenced only. **No new registry is created** |
| Input fields, states, authorization, data impact, transactions, concurrency, Redis, Memcached, acceptance, tests, evidence, critical failures, dependencies | `phase1-baseline.md` §19.1–§19.15 | Referenced as the canonical detailed source |
| Architecture, components, contracts, schema, ERD, transactions, concurrency, security, API, dashboards, reports, CLI, class diagram, ADRs | `phase2-blueprint.md` | Translated into implementation constraints; never redesigned |
| UI/UX facts | `SRC-003` / `SRC-004` §design reference | Mapped only. **No redesign** |

**Zero requirements created. Zero requirement IDs created. Zero decisions created. Zero registries
created.**

---

# 01. PRD EXECUTIVE SUMMARY

| Metric | Value |
|---|---|
| Canonical requirements specified | **28** (17 Product + 11 Technical) — all of Phase 1 |
| New requirements created | **0** |
| New requirement IDs created | **0** |
| New decisions created | **0** |
| New registries created | **0** |
| User stories finalized | 46 (`US-001`…`US-046`) |
| User journeys specified | 5 (the Phase 1 §14 journey set) |
| Use cases specified | 19 (`UC-A1`…`UC-G2`, adopted verbatim from Phase 2) |
| Functional requirement specifications | 28 |
| Business rules formalized | 78 across 12 mandated domains |
| Workflows specified | 10 |
| Entities specified | 12 |
| Validation records | 61 field records (referencing `P1 §19.1`) |
| Acceptance criteria referenced | 95 (`P1 §19.10`) — **none duplicated, none created** |
| Design gaps preserved as design findings | 6 (`DG-01`…`DG-06`) — **0 promoted to requirements** |
| Open mandatory assumptions | 1 — `ASM-001`, **OPEN**, referenced in 9 specification locations |
| Forbidden technologies introduced | **0** |
| Product scope added | **0** |
| Completeness Check | PASS (18 of 18 conditions) |

**PHASE 3 STATUS: COMPLETE.** See §28.

## 1.1 What This Document Is For

An implementer — a person or an agent — should be able to build this system from this document plus
the two frozen baselines, without needing to re-derive a rule, guess a status value, invent a
validation, or decide an authorization question. Where a decision is genuinely still open, this
document says so by name (`ASM-001`) rather than deciding it.

## 1.2 The Six Things That Must Not Be Got Wrong

These are the system's pass/fail points, drawn from SRC-001 §8.2 via `P1 §19.14`. Every one is
specified in full below.

| # | Non-negotiable | Specified in |
|---|---|---|
| 1 | Stock never goes negative, and two concurrent goods issues never oversell or lose an update | §14, §10, `P2 TX-2`, `P2 CC-1`, `CC-2` |
| 2 | `ProductStock` and `StockLedger` are always written in one transaction; the ledger is append-only | §14, §10, `P2 SD-1`…`SD-7` |
| 3 | Sales can never approve a Sales Order — not even its own — and the denial is enforced on the server | §13, §10 (Approval, Rejection), `P2 §12.5` |
| 4 | Every query taking input is a PDO prepared statement; no user input is concatenated into SQL | §21, §22, `P2 SEC-11` |
| 5 | Business logic depends on no PDO, session, or superglobal, and is unit-testable without a database | §22, `P2 ADR-001` |
| 6 | The application and database start from a clean checkout via Docker Compose | §22, `P2 §02.5`, `P1 ENV-01` |

## 1.3 Deliverable Map

| Deliverable | Requirement | Location fixed by |
|---|---|---|
| Initial class diagram | DESIGN-01 | `docs/planning/` — produced in `P2 §20` |
| As-built class diagram + 2–3 sentence delta note | DESIGN-01 | `docs/architecture/` — implementation phase |
| 2–3 ADRs | DESIGN-02 | `docs/architecture/adr-*.md` — produced in `P2 §21` |
| Refactoring log, SRP audit note, tech-debt register | DESIGN-03 | `docs/quality/` — implementation phase |
| Critique of a supplied excerpt | DESIGN-04 | `docs/quality/critique.md` — assessor-triggered |
| Static analysis report | TEST-03 | Implementation phase |
| Low-stock script | JOB-01 | `scripts/check-low-stock.php` — specified in §19 |

---

# 02. PRODUCT CONTEXT

## 2.1 Problem

A distribution business holding stock in more than one warehouse cannot answer three questions
reliably from spreadsheets and message threads: *how much of this product do we actually have, and
where*; *who authorised this shipment*; and *why did the number change*. The failure modes are
concrete — stock recorded once and shipped twice, a sales order fulfilled that nobody approved, a
quantity that no document explains, and two people processing the same shipment at the same moment
leaving a figure that is wrong in a way nobody notices until a stock count.

## 2.2 Vision

One system in which every stock figure is per-warehouse, authoritative, and explainable by an
append-only movement history; in which every stock change is the consequence of an authorised order
transition rather than someone editing a number; and in which the separation between raising an
order and approving it is enforced by the server, not by convention.

## 2.3 Objectives

| # | Objective | Requirement references |
|---|---|---|
| OBJ-1 | Every user reaches only the capabilities their role permits, verified at the server | AUTH-01, AUTH-02, USR-01, ERR-01 |
| OBJ-2 | Master data — products, categories, warehouses, suppliers, customers — is complete and correct enough for every transaction to reference valid records | PRD-01, MSTR-01, WH-01, VAL-01 |
| OBJ-3 | Stock is warehouse-specific, never negative, and every change is traceable to an order and an actor | WH-01, PO-01, SO-01, ARCH-02, DB-01 |
| OBJ-4 | Procurement runs end-to-end: raise a PO, order it, receive goods fully or partially, with the remainder tracked | PO-01 |
| OBJ-5 | Sales runs end-to-end with an approval gate that Sales itself cannot pass | SO-01 |
| OBJ-6 | Operational state is visible live — dashboards, lists with search and pagination, CSV exports that cannot disagree with the dashboard | DASH-01, VIEW-01, FIND-01, REPORT-01 |
| OBJ-7 | The system is programmatically queryable through a JSON contract and operable outside the web cycle | API-01, JOB-01 |
| OBJ-8 | The implementation is layered, testable without a database, statically clean, and reproducible from a clean checkout | ARCH-01, ARCH-02, TEST-01, TEST-02, TEST-03, ENV-01, DB-01 |
| OBJ-9 | Design intent is documented and defensible | DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04 |

## 2.4 Major Capabilities

Module ownership is fixed by `P1 §15`; this table adds the Phase 2 component that realises each.

| Capability | Requirement(s) | Phase 2 component |
|---|---|---|
| Authentication and session | AUTH-01, AUTH-02 | C-01 |
| User administration | USR-01 | C-02 |
| Product and category master data | PRD-01 | C-03, C-04 |
| Warehouse master data | WH-01 | C-05 |
| Supplier and customer master data | MSTR-01 | C-06, C-07 |
| Per-warehouse product stock | WH-01, PO-01, SO-01 | C-08 |
| Purchase order lifecycle | PO-01 | C-09 |
| Goods receipt | PO-01, ARCH-02 | C-10 |
| Sales order lifecycle | SO-01 | C-11 |
| Approval and rejection with SoD | SO-01 | C-12 |
| Goods issue | SO-01, ARCH-02 | C-13 |
| Stock ledger | DB-01, PO-01, SO-01 | C-14 |
| Lists, detail pages, empty states | VIEW-01 | C-03, C-09, C-11 |
| Search, filter, sort, pagination | FIND-01 | C-03, C-09, C-11 |
| Role-scoped dashboards | DASH-01 | C-15 |
| CSV exports | REPORT-01 | C-16 |
| JSON availability contract | API-01 | C-17 |
| Low-stock CLI summary | JOB-01 | C-18 |
| Validation | VAL-01 | Service layer, all components |
| Error handling | ERR-01 | `Http` error handler |
| Responsive UI on the existing baseline | UI-01 | View layer |

## 2.5 Functional Expectations (narrative)

The system is a server-rendered PHP application. A user signs in and lands on a dashboard shaped by
their role. Admin maintains every master record and is the only role that can approve or reject a
sales order. Sales composes sales orders and submits its own for approval. Warehouse Staff proposes
purchase orders, receives goods against them, and issues goods against approved sales orders. Every
stock movement is written together with a ledger row inside one database transaction, so the current
quantity and its explanation can never disagree. Lists paginate at ten rows and keep their filters.
One JSON endpoint exposes per-warehouse availability by SKU. One CLI script reports products below
their reorder point.

## 2.6 Business Expectations

| # | Expectation | Basis |
|---|---|---|
| BE-1 | A stock figure is trustworthy enough to ship against without a physical check | ARCH-02, DB-01, WH-01 |
| BE-2 | An approval is attributable: the order records who created it and who approved it | SO-01 |
| BE-3 | A quantity change is always explainable from the ledger | PRD-01, PO-01, SO-01, DB-01 |
| BE-4 | History is never destroyed: a referenced product, supplier, or customer is deactivated, not deleted | PRD-01, MSTR-01, USR-01 |
| BE-5 | Reported figures and displayed figures agree, because they come from one aggregation basis | REPORT-01, DASH-01, `P2 ADR-003` |
| BE-6 | A failed operation leaves nothing behind | VAL-01, `P1 §19.6` |

## 2.7 NFR Expectations

Canonical owner: `phase1-baseline.md` §18. No SLA, latency, throughput, availability, or scalability
target exists, because SRC-001 states none. Phase 3 adds none.

| Category | Expectation | Specified in |
|---|---|---|
| Security | Server-side authorization in every case including SoD; hashed passwords; renewed session id; prepared statements; escaped output; validated uploads under unguessable names; no committed secret | §21 |
| Reliability | No negative stock; `ProductStock`/`StockLedger` consistency; no oversell; no lost update; referential integrity; no leaked exception | §14, §10, §20 |
| Usability | 360px and desktop usability for login, dashboard, list, detail and forms; labelled fields; visible focus and contrast; informative empty states; 10-per-page with preserved filters | §15, §23 |
| Reproducibility | Clean-checkout start via Docker Compose; environment-variable configuration; schema and seed from empty | §22 |
| Testability | Business logic exercisable with no database; ≥ 6 unit tests over ≥ 3 areas; ≥ 3 integration tests on real MySQL; static analysis with zero critical errors; FIRST | §22 |

---

# 03. ACTORS & ROLES

Role vocabulary is fixed by `DEC-011`: the **stored enum values** are `Admin`, `Sales`,
`WarehouseStaff`; the **display label** for the third is "Warehouse Staff". Both representations are
brief-supported; there is no third representation and no additional role.

| Actor | Enum value | Display label | Description | Requirement references |
|---|---|---|---|---|
| Administrator | `Admin` | Admin | Full access. Sole approver and rejecter of Sales Orders. Sole manager of users, products, categories, warehouses, suppliers and customers | AUTH-01, USR-01, PRD-01, MSTR-01, WH-01, PO-01, SO-01, DASH-01, REPORT-01 |
| Sales | `Sales` | Sales | Creates and submits Sales Orders, limited to its own records for submit and cancel. Views the product catalogue read-only. **Cannot approve or reject any Sales Order** | SO-01, VIEW-01, FIND-01, DASH-01, REPORT-01 |
| Warehouse Staff | `WarehouseStaff` | Warehouse Staff | Proposes Purchase Orders, records goods receipts, issues goods for Approved Sales Orders. Views products and stock read-only | PO-01, SO-01, WH-01, VIEW-01, DASH-01, REPORT-01 |
| System | — | — | Non-human actor for technical requirements that no person triggers | DB-01, ARCH-01, ARCH-02, ENV-01, TEST-01…03, DESIGN-01…04 |
| Script Operator | — | — | A person with container access running the low-stock script. **Not an HTTP role**, and not reachable over HTTP | JOB-01 |
| API Consumer | — | — | Any authenticated caller of the JSON contract, authenticating by the same rule as an HTML page | API-01 |

## 3.1 Role Capability Summary

Canonical detail: `P1 §13` (capability overview) and `P1 §19.4` (enforcement detail, including SoD).
Reproduced here as a navigation aid; enforcement design is `P2 §12.3`.

| Capability | Admin | Sales | Warehouse Staff |
|---|---|---|---|
| Login / Logout | ALLOW | ALLOW | ALLOW |
| User management | ALLOW | DENY | DENY |
| Product / Category / Warehouse management | ALLOW | VIEW ONLY | VIEW ONLY |
| Supplier / Customer management | ALLOW | DENY | DENY |
| View product / view stock | ALLOW | ALLOW | ALLOW |
| Create / submit Sales Order | ALLOW | OWN DATA | DENY |
| **Approve / Reject Sales Order** | ALLOW | **DENY** | DENY |
| Create Purchase Order | ALLOW | DENY | OPERATIONAL (propose) |
| Goods receipt / Goods issue | ALLOW | DENY | OPERATIONAL |
| Dashboard / Report | ALLOW (all) | OWN DATA | OPERATIONAL |

---

# 04. SCOPE & OUT OF SCOPE

## 4.1 In Scope

Exactly the 28 canonical requirements of `phase1-baseline.md` §11 — 26 official SRC-001 IDs plus
`MSTR-01` (`DEC-015`) and `ENV-01` (`DEC-016`). Nothing is added.

| Type | Count | Requirement IDs |
|---|---|---|
| Product | 17 | AUTH-01, AUTH-02, USR-01, PRD-01, MSTR-01, WH-01, PO-01, SO-01, VIEW-01, FIND-01, DASH-01, REPORT-01, API-01, VAL-01, ERR-01, UI-01, JOB-01 |
| Technical | 11 | DB-01, ARCH-01, ARCH-02, ENV-01, TEST-01, TEST-02, TEST-03, DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04 |

## 4.2 Out of Scope

Canonical owner: `phase1-baseline.md` §02.7 (from SRC-001 §4.3). Restated for implementer clarity;
nothing is added to or removed from that list.

| Out of scope | Consequence for implementation |
|---|---|
| Anything not required by the 28 canonical requirements | Do not build it, even if the UI baseline shows a screen for it (`DISC-002`) |
| A stock adjustment workflow | `DEC-010` — the `Adjustment` ledger value exists; **no workflow, actor, trigger, or authorization rule is designed or built** (§14.3) |
| A `Rejected` Sales Order status | `DEC-009` / `ASM-001` — no status value outside SRC-001 §1.3 is created (§10.7) |
| Stock reservation | No Phase 1 requirement; no `reserved_quantity` field (`P2 §07.2`) |
| Partial goods issue | SRC-001 §1.3 defines no partial-issue status; an issue is all-or-nothing (§14.2) |
| Login lockout / rate limiting as a business rule | `DG-04` — Phase 1 defines no threshold; the attempt marker is inert (§26) |
| Charts on the Reports surface | `DEC-013` — the tabular surface is canonical; charts are bonus per SRC-001 §4.4 |
| Notifications, audit log beyond the ledger, policy engine, JWT/OAuth, 2FA, automatic scheduling | No Phase 1 requirement (`P2 §04.1`, §13.3, JD-1) |
| UI redesign | `DEC-007` — the existing baseline is binding (§23) |

## 4.3 Scope Change Rule

Any need that is not satisfiable within the 28 requirements requires a new decision record in
`phase1-baseline.md` §04 **before** implementation, per SRC-001 FAQ 12. Phase 3 creates no decision
and grants no implementer discretion to add scope.

---

# 05. USER STORIES

Forty-six finalized stories, taken from the pre-coding analysis and traced to canonical requirement
IDs. **No official requirement ID is invented.** The one story the analysis left as
`CANDIDATE — UNASSIGNED` (`US-017`) now traces to `MSTR-01`, which Phase 1 created under `DEC-015` —
so the trace uses an existing ID rather than a new one.

| US ID | Story | Requirement References | Status |
|---|---|---|---|
| US-001 | As a User, I want to log in with email and password, so that I can access the features for my role. | AUTH-01 | FINAL |
| US-002 | As a User, I want a safe error message on failed login, so that no attacker learns which credential part was wrong. | AUTH-01 | FINAL |
| US-003 | As the System Owner, I want inactive accounts refused at login, so that revoked staff cannot enter. | AUTH-01 | FINAL |
| US-004 | As the System Owner, I want protected pages unreachable without a session, so that data is not exposed. | AUTH-01, ERR-01 | FINAL |
| US-005 | As the System Owner, I want the session ID renewed after login, so that session fixation is prevented. | AUTH-01 | FINAL |
| US-006 | As a User, I want to log out, so that my session cannot be reused on a shared machine. | AUTH-02 | FINAL |
| US-007 | As an Admin, I want to create, view, edit, activate and deactivate Sales and Warehouse Staff accounts, so that access matches current staffing. | USR-01 | FINAL |
| US-008 | As an Admin, I want duplicate emails rejected, so that identity stays unique. | USR-01, VAL-01 | FINAL |
| US-009 | As the System Owner, I want Sales and Warehouse Staff blocked from user-administration pages and endpoints, so that privilege cannot escalate. | USR-01, ERR-01 | FINAL |
| US-010 | As an Admin, I want to manage products with unique SKU, category, unit, buy/sell price and reorder point, so that the catalogue drives all transactions. | PRD-01 | FINAL |
| US-011 | As an Admin, I want numeric product values validated as ≥ 0, so that impossible prices and thresholds cannot be stored. | PRD-01, VAL-01 | FINAL |
| US-012 | As an Admin, I want a product already used on an order to be deactivatable but not deletable, so that order history stays intact. | PRD-01 | FINAL |
| US-013 | As an Admin, I want to upload an optional product image with type and size validation stored under an unguessable random name, so that upload cannot be abused. | PRD-01 | FINAL |
| US-014 | As an Admin, I want to manage categories, so that products can be classified and filtered. | PRD-01, FIND-01 | FINAL |
| US-015 | As an Admin, I want to manage warehouses, so that stock can be held per location. | WH-01 | FINAL |
| US-016 | As a Warehouse Staff, I want to see total stock and the per-warehouse breakdown for a product, so that I know where the goods are. | WH-01 | FINAL |
| US-017 | As an Admin, I want to manage suppliers and customers with active status, so that orders reference valid counterparties. | **MSTR-01** (`DEC-015`) | FINAL — was `CANDIDATE — UNASSIGNED`; now traced to an existing ID |
| US-018 | As an Admin or Warehouse Staff, I want to create a Purchase Order with supplier, destination warehouse and items, so that low stock can be replenished. | PO-01 | FINAL |
| US-019 | As a Warehouse Staff, I want to record a goods receipt that increases stock and writes a `Receipt` ledger row in one transaction, so that arrivals are accounted for. | PO-01, ARCH-02 | FINAL |
| US-020 | As a Warehouse Staff, I want to record a partial receipt with the outstanding quantity still tracked, so that split deliveries are handled. | PO-01 | FINAL |
| US-021 | As a Sales, I want to create a Draft Sales Order from the catalogue and available stock, so that a customer order can be prepared. | SO-01 | FINAL |
| US-022 | As a Sales, I want to submit my Draft order so it becomes `PendingApproval`, so that it enters review. | SO-01 | FINAL |
| US-023 | As an Admin, I want to approve or reject a `PendingApproval` order, so that only authorised orders proceed. | SO-01, **ASM-001** (rejection terminal state) | FINAL |
| US-024 | As the System Owner, I want the server to refuse approval by Sales — including the creator's own order, so that duties stay segregated. | SO-01 (`DEC-012`) | FINAL |
| US-025 | As a Warehouse Staff, I want to issue goods only for `Approved` orders, so that unauthorised fulfilment is impossible. | SO-01 | FINAL |
| US-026 | As a Warehouse Staff, I want a goods issue refused when available stock is insufficient, so that stock never goes negative. | SO-01, ARCH-02 | FINAL |
| US-027 | As the System Owner, I want two near-simultaneous goods issues on the same product and warehouse to leave a correct final stock with no oversell and no lost update, so that concurrent operations are safe. | ARCH-02 | FINAL |
| US-028 | As an Admin, I want every stock movement recorded on the ledger with type, quantity, reference and actor, so that any figure can be traced. | DB-01, PO-01, SO-01 | FINAL |
| US-029 | As a User, I want products, POs and SOs as role-scoped lists and detail pages with an informative empty state, so that I can navigate data of any size. | VIEW-01 | FINAL |
| US-030 | As a User, I want product search by name/SKU and filters for category and stock status, so that I can find items quickly. | FIND-01 | FINAL |
| US-031 | As a User, I want order search by number/counterparty, status filter and date sort, so that I can locate orders. | FIND-01 | FINAL |
| US-032 | As a User, I want 10-per-page pagination that keeps filters active across pages, so that large lists stay usable. | FIND-01 | FINAL |
| US-033 | As an Admin, I want a dashboard showing inventory value, products below reorder point and pending orders per status, so that I can see operational state. | DASH-01 | FINAL |
| US-034 | As a Sales, I want a dashboard summarising my own orders per status, so that I can track my pipeline. | DASH-01 | FINAL |
| US-035 | As a Warehouse Staff, I want a dashboard showing goods receipt/issue queues and low-stock products, so that I know today's work. | DASH-01 | FINAL |
| US-036 | As the System Owner, I want every dashboard figure produced by an aggregation query rather than a static value, so that the display reflects reality. | DASH-01 | FINAL |
| US-037 | As a User, I want to export stock movement and order status CSV for a date range, so that data can be analysed outside the app. | REPORT-01 | FINAL |
| US-038 | As an API Consumer, I want `GET /api/products/{sku}/availability` returning per-warehouse stock as JSON with correct 200/401/404 status, so that other software can query availability. | API-01 | FINAL |
| US-039 | As a User, I want required fields, enums, dates, foreign keys and numbers validated on both frontend and backend with the backend authoritative, so that bad data cannot be stored. | VAL-01 | FINAL |
| US-040 | As a User, I want unauthenticated access redirected to login, unauthorised access to return 403 and missing data to return 404, so that failures are predictable. | ERR-01 | FINAL |
| US-041 | As the System Owner, I want database exceptions and stack traces hidden from users, so that internals are not leaked. | ERR-01 | FINAL |
| US-042 | As a User, I want login, dashboard, lists, detail and forms usable at 360px and desktop with labelled fields and visible focus/contrast, so that the app is usable on any screen. | UI-01 | FINAL |
| US-043 | As the System Owner, I want a relational schema with PK, FK, `quantity >= 0` constraint and relevant indexes, all access via prepared statements and multi-table stock operations in explicit transactions, so that data integrity holds. | DB-01 | FINAL |
| US-044 | As the System Owner, I want schema and seed able to build the database from empty including FIND-01 test data, so that the environment is reproducible. | DB-01, ENV-01 | FINAL |
| US-045 | As a Script Operator, I want a standalone script summarising products below reorder point, runnable manually via Docker, so that low stock can be checked outside the web cycle. | JOB-01 | FINAL |
| US-046 | As the System Owner, I want business logic independent of PDO, session and superglobals with a repository interface having real and fake implementations, so that logic is testable without a database. | ARCH-01, TEST-01 | FINAL |

## 5.1 Requirements With No Story, and Why

`ARCH-02` is covered by `US-027` alone at the concurrency level and by `US-019`/`US-026` at the
behavioural level. `TEST-02`, `TEST-03`, `DESIGN-01`…`DESIGN-04` have **no user story**, because
they are assessment-evidence requirements with no user-facing capability. Writing a story for them
would fabricate a user need. Their specification lives in §08 and §22, and their acceptance criteria
in `P1 §19.10`.

## 5.2 Candidates Deliberately Not Promoted

`CAND-060`, `CAND-061`, `CAND-062` remain `CANDIDATE — UNASSIGNED` from the pre-coding analysis. No
story, requirement, or specification was written for them, because no brief clause mandates them.
Promoting them would be inventing scope.

---

# 06. USER JOURNEYS

Five journeys — the `P1 §14` set, unchanged. A journey defines **no** business rule and **no** state
transition; those live in `P1 §11` and `P1 §19.3`.

## 6.1 `JRN-1` — Authentication

```text
Login form → credential check → session established (id renewed) → role dashboard → Logout → login form
```

| Step | Actor | Specified in | References |
|---|---|---|---|
| Submit credentials | Admin / Sales / Warehouse Staff | §10.1, `UC-A1` | AUTH-01, `US-001`…`US-005` |
| Land on role dashboard | same | §16 | DASH-01 |
| End session | same | §10.2, `UC-A2` | AUTH-02, `US-006` |

Failure branches: invalid credential, inactive account, no session on a protected page — all in §20.

## 6.2 `JRN-2` — Purchase

```text
Low stock observed → PO created (Draft) → Ordered → Goods Receipt → stock increased → movement recorded on ledger
                                                        ↘ partial receipt → PartiallyReceived → further receipt → Received
```

| Step | Actor | Specified in | References |
|---|---|---|---|
| Observe low stock | Warehouse Staff / Admin | §16, §19 | DASH-01, JOB-01 |
| Create PO | Admin (create) / Warehouse Staff (propose) | §10.3, `UC-D1` | PO-01, MSTR-01, WH-01, PRD-01 |
| Order the PO | Admin | §10.3, `UC-D2` | PO-01 |
| Receive goods | Warehouse Staff / Admin | §14.1, §10.4, `UC-D3`, `P2 TX-1` | PO-01, ARCH-02, DB-01 |
| Movement recorded | System | §14.4 | DB-01 |

## 6.3 `JRN-3` — Sales

```text
SO created (Draft) → Submit → PendingApproval → Admin Approve → Approved → Goods Issue → Fulfilled
                                             ↘ Admin Reject → rejection terminal status  [ASM-001]
                              ↘ Cancel (any stage before Fulfilled) → Cancelled
```

| Step | Actor | Specified in | References |
|---|---|---|---|
| Create SO | Sales / Admin | §10.5, `UC-E1` | SO-01, MSTR-01, WH-01, PRD-01 |
| Submit | Sales (own) / Admin | §10.5, `UC-E2` | SO-01 |
| Approve | **Admin only** | §10.6, `UC-E3`, `P2 §12.5` | SO-01, `DEC-012` |
| Reject | **Admin only** | §10.7, `UC-E4` | SO-01, `DEC-009`, **`ASM-001`** |
| Issue goods | Warehouse Staff / Admin | §14.2, §10.8, `UC-E5`, `P2 TX-2` | SO-01, ARCH-02, DB-01 |

The rejection branch terminates at the status `ASM-001` covers. **`ASM-001` is OPEN and
MANDATORY** — see §10.7 and §10 of the Phase 3 prompt.

## 6.4 `JRN-4` — Multi-Warehouse

```text
One product → one stock row per warehouse → different quantity per warehouse
            → total = sum of per-warehouse quantities (never stored independently)
            → receipt affects the PO destination warehouse only
            → issue affects the SO source warehouse only
            → API returns the per-warehouse breakdown
```

References: WH-01, PO-01, SO-01, API-01. Specified in §11.5, §14, §18.

## 6.5 `JRN-5` — Failure

```text
Invalid login · Inactive user · Forbidden action · Invalid input · Insufficient stock · Not found
```

Every branch is specified in §20, with its status code and its "nothing was mutated" guarantee.
References: AUTH-01 (invalid login, inactive user), ERR-01 (forbidden, not found), VAL-01 (invalid
input), SO-01 (insufficient stock).

---

# 07. USE CASES

Nineteen use cases. The identifiers `UC-A1`…`UC-G2` are **adopted verbatim from `P2 §05`** so that
no third use-case ID namespace is created. Business rules referenced here have their canonical
statements in `P1 §11`; none is redefined.

### `UC-A1` — Login

| Field | Specification |
|---|---|
| **Use Case ID** | UC-A1 |
| **Name** | Login |
| **Actor** | Admin, Sales, Warehouse Staff |
| **Goal** | Obtain an authenticated session appropriate to the actor's role |
| **Preconditions** | No authenticated session, or an existing one being replaced. An account exists for the submitted email |
| **Trigger** | Credentials submitted on the login form |
| **Main Flow** | 1. Look up the account by email · 2. Refuse if the account is inactive · 3. Verify the password with `password_verify()` against the stored `password_hash()` digest · 4. Establish the session and **regenerate the session identifier** · 5. Redirect to the role dashboard |
| **Alternative Flow** | An already-authenticated user submitting credentials again receives a new session identifier; the previous session state is discarded |
| **Failure Flow** | Unknown email, wrong password, or inactive account → one indistinguishable failure with no field-level detail, no session, and no indication of which credential part failed. Session store unreachable → treated as an authentication failure (fail closed) |
| **Postconditions** | Success: a session exists carrying user id and role, with a new identifier. Failure: no session exists and nothing is persisted |
| **Related Requirement IDs** | AUTH-01, VAL-01, ERR-01 · `P1 §19.4`, `§19.5` · `P2 §05 UC-A1`, `SEC-01`…`SEC-06`, `RD-1` · AC-AUTH-01-1…5 |

### `UC-A2` — Logout

| Field | Specification |
|---|---|
| **Use Case ID** | UC-A2 |
| **Name** | Logout |
| **Actor** | Admin, Sales, Warehouse Staff |
| **Goal** | End the session so protected URLs cannot be reopened |
| **Preconditions** | An authenticated session exists |
| **Trigger** | Logout activated from within the application |
| **Main Flow** | 1. Delete the authentication state from the session store · 2. Clear the session cookie · 3. Redirect to the login page |
| **Alternative Flow** | Logout with an already-expired session completes normally and still returns the user to login |
| **Failure Flow** | Session-store deletion failure → the session is still treated as terminated at the application boundary; a stale identifier must never re-authenticate |
| **Postconditions** | No authentication data remains associated with the identifier; a protected URL is no longer served |
| **Related Requirement IDs** | AUTH-02, ERR-01 · `P1 §19.4`, `§19.5` · `P2 §05 UC-A2`, `SEC-07`, `SEC-08`, `RD-1` · AC-AUTH-02-1, AC-AUTH-02-2 |

### `UC-B1` — Create User

| Field | Specification |
|---|---|
| **Use Case ID** | UC-B1 |
| **Name** | Create User |
| **Actor** | Admin |
| **Goal** | Add a Sales or Warehouse Staff account so access matches staffing |
| **Preconditions** | Requester is authenticated as Admin |
| **Trigger** | User-creation form submitted |
| **Main Flow** | 1. Assert the requester is Admin · 2. Validate name, email, password, role, active flag per `P1 §19.1` · 3. Assert the email is unique across all accounts · 4. Hash the password with `password_hash()` · 5. Persist the account · 6. Show it in the user list |
| **Alternative Flow** | An Admin account may also be created; the role enum permits it and SRC-001 §1.3 fixes the three values |
| **Failure Flow** | Non-Admin requester → 403 and no write · missing required field → rejected · role outside `{Admin, Sales, WarehouseStaff}` → rejected · duplicate email → rejected. In every case nothing is persisted |
| **Postconditions** | Success: one active account exists with a hashed password. Failure: no account row exists |
| **Related Requirement IDs** | USR-01, VAL-01, ERR-01, AUTH-01 · `P1 §19.1`, `§19.4`, `§19.5` · `P2 §05 UC-B1`, `SEC-09`, `SEC-23` · AC-USR-01-1, AC-USR-01-2, AC-USR-01-4 |

### `UC-B2` — Deactivate User

| Field | Specification |
|---|---|
| **Use Case ID** | UC-B2 |
| **Name** | Deactivate User (and reactivate) |
| **Actor** | Admin |
| **Goal** | Revoke or restore an account's ability to authenticate without destroying history |
| **Preconditions** | Requester is Admin; the target account exists |
| **Trigger** | Activation toggle submitted for a user |
| **Main Flow** | 1. Assert the requester is Admin · 2. Load the account · 3. Set the active flag · 4. Persist |
| **Alternative Flow** | Reactivation follows the same flow in the opposite direction |
| **Failure Flow** | Non-Admin requester → 403 · unknown user → 404. **Permanent deletion is not offered** (`P1 §19.2` marks `Delete: No`) |
| **Postconditions** | A deactivated account can no longer authenticate (`UC-A1` step 2). No row is removed |
| **Related Requirement IDs** | USR-01, AUTH-01, ERR-01 · `P1 §19.2`, `§19.3`, `§19.4` · `P2 §05 UC-B2`, R-01 · AC-USR-01-1, AC-AUTH-01-2 |

### `UC-C1` — Create Product

| Field | Specification |
|---|---|
| **Use Case ID** | UC-C1 |
| **Name** | Create Product |
| **Actor** | Admin |
| **Goal** | Add a catalogue item usable by purchase and sales flows |
| **Preconditions** | Requester is Admin; at least one category exists |
| **Trigger** | Product-creation form submitted |
| **Main Flow** | 1. Assert Admin · 2. Validate sku, name, category, unit, buy price, sell price, reorder point per `P1 §19.1` · 3. Assert SKU uniqueness · 4. Assert buy price, sell price, reorder point are numeric and ≥ 0 · 5. Assert the referenced category exists · 6. If an image is supplied, validate its type and size and store it under a randomly generated non-guessable filename · 7. Persist the product · 8. Invalidate the product and category read cache **after commit** |
| **Alternative Flow** | Created without an image — the image field is optional (`P1 §19.1`) |
| **Failure Flow** | Non-Admin → 403 · duplicate SKU → rejected · negative or non-numeric price or reorder point → rejected with field feedback · unknown category → rejected · invalid image type or oversize → rejected. Nothing is persisted, and a stored image file is removed if the row write fails |
| **Postconditions** | One active product exists. Per-warehouse stock rows are provisioned so exactly one row exists per warehouse (`WH-01`) |
| **Related Requirement IDs** | PRD-01, VAL-01, ERR-01, WH-01 · `P1 §19.1`, `§19.2`, `§19.5` · `P2 §05 UC-C1`, `SEC-15`, `MC-1` · AC-PRD-01-1, 2, 4, 5 |

### `UC-C2` — Update Product

| Field | Specification |
|---|---|
| **Use Case ID** | UC-C2 |
| **Name** | Update Product |
| **Actor** | Admin |
| **Goal** | Correct or revise catalogue data |
| **Preconditions** | Requester is Admin; the product exists |
| **Trigger** | Product-edit form submitted |
| **Main Flow** | Same rule set as `UC-C1`, with SKU uniqueness evaluated **excluding the product itself**; persist; invalidate cache after commit |
| **Alternative Flow** | Replacing the image validates and stores the new file, then the old file is removed after commit |
| **Failure Flow** | As `UC-C1`, plus unknown product → 404 |
| **Postconditions** | The product reflects the submitted values. **No stock quantity is touched** — quantity is not a product field (`P2 §07` T-04) |
| **Related Requirement IDs** | PRD-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.2` · `P2 §05 UC-C2`, R-02 · AC-PRD-01-1, AC-PRD-01-2 |

### `UC-C3` — Deactivate Product

| Field | Specification |
|---|---|
| **Use Case ID** | UC-C3 |
| **Name** | Deactivate Product (and reactivate) |
| **Actor** | Admin |
| **Goal** | Withdraw a product from new orders without destroying order history |
| **Preconditions** | Requester is Admin; the product exists |
| **Trigger** | Activation toggle submitted for a product |
| **Main Flow** | 1. Assert Admin · 2. Set the active flag · 3. Persist · 4. Invalidate cache after commit |
| **Alternative Flow** | Reactivation follows the same flow. Deactivation is permitted **even when the product is referenced by an order** (`P1 §19.3`) |
| **Failure Flow** | Non-Admin → 403 · unknown product → 404 · **permanent deletion is refused and deactivation is offered instead** |
| **Postconditions** | The product is excluded from new order composition. Existing orders and ledger rows are unaffected |
| **Related Requirement IDs** | PRD-01, VIEW-01, ERR-01 · `P1 §19.2`, `§19.3`, `§19.5` · `P2 §05 UC-C3`, `SEC-20` · AC-PRD-01-3 |

### `UC-D1` — Create Purchase Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-D1 |
| **Name** | Create Purchase Order |
| **Actor** | Admin (create); Warehouse Staff (propose) |
| **Goal** | Raise a replenishment order against a supplier for a destination warehouse |
| **Preconditions** | Requester is Admin or Warehouse Staff; an active supplier and an active destination warehouse exist; at least one product exists |
| **Trigger** | Purchase-order form submitted |
| **Main Flow** | 1. Assert the requester is Admin or Warehouse Staff (**Sales denied**) · 2. Assert exactly one supplier and one destination warehouse · 3. Assert at least one item · 4. Assert each item quantity and buy price are numeric and ≥ 0 · 5. Assert the supplier is active and each product exists · 6. Persist header and items as one aggregate in status `Draft`, with outstanding quantity equal to ordered quantity |
| **Alternative Flow** | Warehouse Staff creating the PO is a *proposal* in business terms; it produces the same `Draft` record. Only Admin may subsequently move it to `Ordered` (`UC-D2`) |
| **Failure Flow** | Sales requester → 403 · no items → rejected · inactive or unknown supplier → rejected · unknown warehouse or product → rejected · negative quantity or price → rejected. Header and items are written in one unit, so no orphan header or item survives |
| **Postconditions** | One `Draft` PO exists with ≥ 1 item. **No stock moves** |
| **Related Requirement IDs** | PO-01, MSTR-01, WH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.3`, `§19.4`, `§19.6` · `P2 §05 UC-D1`, `§10.3`, `MC-4` · AC-PO-01-1 |

### `UC-D2` — Order Purchase Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-D2 |
| **Name** | Order Purchase Order |
| **Actor** | Admin |
| **Goal** | Commit the PO to the supplier, making it receivable |
| **Preconditions** | Requester is Admin; PO status is exactly `Draft`; PO has ≥ 1 item, a supplier, and a destination warehouse |
| **Trigger** | "Order" action on a `Draft` PO |
| **Main Flow** | 1. Assert Admin · 2. Assert status is `Draft` · 3. Assert composition preconditions · 4. Transition to `Ordered` |
| **Alternative Flow** | None |
| **Failure Flow** | Non-Admin → 403 · status not `Draft` → rejected · empty item list → rejected. No transition occurs |
| **Postconditions** | Status is `Ordered`. **No stock moves** — ordering is not a stock movement |
| **Related Requirement IDs** | PO-01, VAL-01, ERR-01 · `P1 §19.3`, `§19.4`, `§19.6` · `P2 §05 UC-D2`, `§10.3` · AC-PO-01-2 |

### `UC-D3` — Receive Goods

| Field | Specification |
|---|---|
| **Use Case ID** | UC-D3 |
| **Name** | Receive Goods (goods receipt) |
| **Actor** | Warehouse Staff, Admin |
| **Goal** | Record an arrival so stock increases and the movement is explainable |
| **Preconditions** | Requester is Warehouse Staff or Admin; PO status is `Ordered` or `PartiallyReceived`; at least one line has outstanding quantity |
| **Trigger** | Goods-receipt form submitted with per-line received quantities |
| **Main Flow** | 1. Assert role · 2. Open **one transaction** (`TX-1`) · 3. Re-read the PO and its lines **under a row lock**, in ascending `product_id` then `warehouse_id` order · 4. Assert each submitted line belongs to this PO, its received quantity is > 0, and it is ≤ that line's outstanding quantity · 5. Per line, increase `ProductStock` at the PO destination warehouse and append a `Receipt` ledger row referencing the PO — both inside this transaction · 6. Increase `quantity_received` on the line · 7. Recompute PO status: `Received` when every line is fully received, otherwise `PartiallyReceived` · 8. Commit |
| **Alternative Flow** | Partial receipt: at least one line remains outstanding, status becomes `PartiallyReceived`, and the remainder stays recorded until a later receipt completes it |
| **Failure Flow** | Non-authorised role → 403, no transaction opened · PO not in a receivable status → rejected · received quantity ≤ 0 → rejected · received quantity exceeds outstanding → rejected · line not on this PO → rejected · any write failure or constraint violation → **full rollback, so neither stock, nor ledger, nor received quantity changes** |
| **Postconditions** | Stock increased, one `Receipt` ledger row per received line, outstanding reduced, status recomputed — all atomically. `ProductStock` remains reconcilable against the ledger |
| **Related Requirement IDs** | PO-01, ARCH-02, DB-01, VAL-01, WH-01, ERR-01 · `P1 §19.3`, `§19.6`, `§19.7` · `P2 §05 UC-D3`, `TX-1`, `CC-5`, `SD-1`…`SD-7` · AC-PO-01-3, 4, 5, 6 |

### `UC-E1` — Create Sales Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-E1 |
| **Name** | Create Sales Order |
| **Actor** | Sales, Admin |
| **Goal** | Prepare a customer order against a source warehouse |
| **Preconditions** | Requester is Sales or Admin; an active customer and an active source warehouse exist |
| **Trigger** | Sales-order form submitted |
| **Main Flow** | 1. Assert the requester is Sales or Admin (**Warehouse Staff denied**) · 2. Assert exactly one customer and one source warehouse · 3. Assert at least one item · 4. Assert each item quantity and sell price are numeric and ≥ 0 · 5. Assert the customer is active · 6. **Record the creator from the authenticated session, never from request input** · 7. Persist header and items as one aggregate in status `Draft` |
| **Alternative Flow** | Admin may create an order on behalf of the business; the creator recorded is the Admin |
| **Failure Flow** | Warehouse Staff requester → 403 · no items → rejected · inactive or unknown customer → rejected · unknown warehouse or product → rejected · negative quantity or price → rejected |
| **Postconditions** | One `Draft` SO exists with its creator recorded. **No stock is reserved** — Phase 1 defines no reservation |
| **Related Requirement IDs** | SO-01, MSTR-01, WH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.3`, `§19.4` · `P2 §05 UC-E1`, `MC-5`, OW-1 · AC-SO-01-1 |

### `UC-E2` — Submit Sales Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-E2 |
| **Name** | Submit Sales Order |
| **Actor** | Sales (own order), Admin |
| **Goal** | Send a draft order into approval review |
| **Preconditions** | SO status is `Draft` with ≥ 1 item, a customer, and a source warehouse |
| **Trigger** | "Submit" action on a `Draft` SO |
| **Main Flow** | 1. Assert the requester is Admin, **or** Sales **and** the recorded creator of this order · 2. Assert status is `Draft` · 3. Transition to `PendingApproval` |
| **Alternative Flow** | None |
| **Failure Flow** | Sales requester who is not the creator → 403 · Warehouse Staff → 403 · status not `Draft` → rejected · empty item list → rejected. No transition occurs |
| **Postconditions** | Status is `PendingApproval`; the order awaits an Admin decision |
| **Related Requirement IDs** | SO-01, VAL-01, ERR-01 · `P1 §19.3`, `§19.4` · `P2 §05 UC-E2`, OW-1, OW-2 · AC-SO-01-2 |

### `UC-E3` — Approve Sales Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-E3 |
| **Name** | Approve Sales Order |
| **Actor** | **Admin only** |
| **Goal** | Authorise fulfilment of a pending order and record who authorised it |
| **Preconditions** | SO status is `PendingApproval` |
| **Trigger** | "Approve" action on a `PendingApproval` SO |
| **Main Flow** | 1. **Assert the requester's role is exactly `Admin`** — this is the first action taken, before any read-for-write, any transaction, and any state change · 2. Assert status is `PendingApproval` · 3. Record the approver's user id and the approval timestamp · 4. Transition to `Approved` |
| **Alternative Flow** | None. **The creator's identity is not consulted**: Sales is denied regardless of ownership (`DEC-012`) |
| **Failure Flow** | Sales requester → **403 with no transition, status unchanged, approver unset** — including on the requester's own order · Warehouse Staff → 403 · status not `PendingApproval` → rejected · unknown SO → 404 |
| **Postconditions** | Status is `Approved`; the approver is recorded for audit; the order becomes issuable |
| **Related Requirement IDs** | SO-01 (SoD-1, SoD-2), ERR-01, AUTH-01 · `P1 §19.3`, `§19.4`, `§19.5`, `§19.14` CF-8 · `P2 §05 UC-E3`, `§12.5` SoD-A…SoD-I · AC-SO-01-3, 4, 5 |

### `UC-E4` — Reject Sales Order

| Field | Specification |
|---|---|
| **Use Case ID** | UC-E4 |
| **Name** | Reject Sales Order |
| **Actor** | **Admin only** |
| **Goal** | Decline a pending order and terminate it |
| **Preconditions** | SO status is `PendingApproval` |
| **Trigger** | "Reject" action on a `PendingApproval` SO, optionally with a reason |
| **Main Flow** | 1. **Assert the requester's role is exactly `Admin`** — identical assertion and identical position to `UC-E3` · 2. Assert status is `PendingApproval` · 3. Record the deciding user and the optional reason · 4. Transition to the **rejection terminal status**, which per `DEC-009` is `Cancelled` and is carried as the **open mandatory assumption `ASM-001`** |
| **Alternative Flow** | Rejection without a reason is permitted; the reason is optional and carries no lifecycle meaning |
| **Failure Flow** | Sales requester → 403 with no transition · Warehouse Staff → 403 · status not `PendingApproval` → rejected · unknown SO → 404 |
| **Postconditions** | The order is terminated at the status `ASM-001` covers. **No status value outside SRC-001 §1.3 is created.** The terminal value has exactly one definition site, so a trainer ruling changes one enum value, one transition, and one badge |
| **Related Requirement IDs** | SO-01, ERR-01 · `DEC-009` · **`ASM-001` — OPEN / MANDATORY** · `P1 §19.3`, `§19.4` · `P2 §05 UC-E4`, `§12.6` AS-1…AS-4 · AC-SO-01-3 (approval path), §10.7 |

### `UC-E5` — Issue Goods

| Field | Specification |
|---|---|
| **Use Case ID** | UC-E5 |
| **Name** | Issue Goods (goods issue) |
| **Actor** | Warehouse Staff, Admin |
| **Goal** | Fulfil an approved order, decreasing stock safely under concurrency |
| **Preconditions** | Requester is Warehouse Staff or Admin; SO status is exactly `Approved` |
| **Trigger** | "Issue goods" action on an `Approved` SO |
| **Main Flow** | 1. Assert role · 2. Open **one transaction** (`TX-2`) · 3. Re-read the SO **under a row lock** and assert status is `Approved` **inside** the transaction · 4. Per line, in ascending `product_id` then `warehouse_id` order, read the `ProductStock` row for (product, SO source warehouse) **`FOR UPDATE`** · 5. Assert `current − requested >= 0` for that line · 6. Decrease the stock as a **relative delta** and append an `Issue` ledger row referencing the SO, recording the resulting quantity · 7. Transition the SO to `Fulfilled` · 8. Commit |
| **Alternative Flow** | None. **There is no partial issue** — SRC-001 §1.3 defines no partial-issue status, so a multi-line order is fulfilled entirely or not at all |
| **Failure Flow** | Non-authorised role → 403, no transaction opened · Sales → 403 · SO status not `Approved` → refused · **insufficient available stock on any line → the whole issue is refused and rolled back** · a competing concurrent issue exhausted the stock first → refused · `quantity >= 0` constraint violation → rollback · any write failure → rollback. In every failure case stock, ledger, and SO status are unchanged |
| **Postconditions** | Stock decreased, one `Issue` ledger row per line, status `Fulfilled` — atomically, with no negative stock, no oversell, and no lost update. `ProductStock` equals the sum of its signed ledger movements |
| **Related Requirement IDs** | SO-01, ARCH-02, DB-01, WH-01, ERR-01 · `P1 §19.3`, `§19.6`, `§19.7`, `§19.14` CF-6/CF-7 · `P2 §05 UC-E5`, `TX-2`, `CC-1`, `CC-2`, `CC-3`, `ADR-002` · AC-SO-01-6, 7, 8; AC-ARCH-02-1…4 |

### `UC-F1` — View Dashboard

| Field | Specification |
|---|---|
| **Use Case ID** | UC-F1 |
| **Name** | View Dashboard |
| **Actor** | Admin, Sales, Warehouse Staff |
| **Goal** | See live, role-appropriate operational figures |
| **Preconditions** | Authenticated session |
| **Trigger** | Dashboard page requested |
| **Main Flow** | 1. Build the role scope from the session · 2. Select the role's metric set (§16) · 3. Execute each figure as an aggregation query with the scope bound as a parameter · 4. Render |
| **Alternative Flow** | A role with no matching data still renders its metric set, showing zero values rather than an error |
| **Failure Flow** | Unauthenticated → redirect to login · aggregation failure → generic error page, no exception text or stack trace |
| **Postconditions** | Figures reflect current data. **No figure is stored, cached, or hard-coded** |
| **Related Requirement IDs** | DASH-01, VIEW-01, AUTH-01, ERR-01 · `P1 §19.2`, `§19.4` · `P2 §05 UC-F1`, `§17.1`, R-11, `ADR-003` · AC-DASH-01-1…4 |

### `UC-F2` — Generate CSV Report

| Field | Specification |
|---|---|
| **Use Case ID** | UC-F2 |
| **Name** | Generate CSV Report |
| **Actor** | Admin (all data); Sales (own orders); Warehouse Staff (stock report) |
| **Goal** | Export stock movement or order status for a date range |
| **Preconditions** | Authenticated session; a valid date range where `date_from <= date_to` |
| **Trigger** | Export requested with a report type and a date range |
| **Main Flow** | 1. Validate the report type against `{stock movement, order status}` and validate both dates · 2. Build the role scope · 3. Call **the same aggregation methods the dashboard uses** · 4. Stream rows as CSV |
| **Alternative Flow** | An empty result streams a header row with no data rows rather than failing |
| **Failure Flow** | Invalid or inverted date range → rejected, nothing streamed · Sales requesting another user's orders → 403 before any query · unauthenticated → redirect · aggregation failure → generic error |
| **Postconditions** | A CSV whose figures cannot disagree with the dashboard, constrained to the range and the requester's scope |
| **Related Requirement IDs** | REPORT-01, DASH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.4` · `P2 §05 UC-F2`, `§17.2`, `ADR-003` · AC-REPORT-01-1, 2, 3 |

### `UC-G1` — Check Product Availability (API)

| Field | Specification |
|---|---|
| **Use Case ID** | UC-G1 |
| **Name** | Check Product Availability |
| **Actor** | Authenticated API Consumer |
| **Goal** | Obtain per-warehouse availability for one SKU as JSON |
| **Preconditions** | A valid session is presented on the request |
| **Trigger** | `GET /api/products/{sku}/availability` |
| **Main Flow** | 1. Resolve the session by **the same rule as an HTML page** · 2. Resolve the product by SKU · 3. Read per-warehouse quantities **from MySQL** · 4. Return 200 with `Content-Type: application/json` |
| **Alternative Flow** | A product with zero stock in a warehouse returns that warehouse with quantity `0` rather than omitting it |
| **Failure Flow** | No valid session → **401 JSON** · unknown or malformed SKU → **404 JSON** · wrong method → 405 JSON · internal failure → 500 JSON with a generic message. **Never an HTML error page and never an HTML login redirect** |
| **Postconditions** | Read-only; nothing is mutated |
| **Related Requirement IDs** | API-01, AUTH-01, ERR-01, WH-01, PRD-01 · `P1 §19.1`, `§19.4`, `§19.5` · `P2 §05 UC-G1`, `§16.1`, `SEC-19` · AC-API-01-1, 2, 3 |

### `UC-G2` — Check Low Stock (CLI)

| Field | Specification |
|---|---|
| **Use Case ID** | UC-G2 |
| **Name** | Check Low Stock |
| **Actor** | Script Operator (container access) |
| **Goal** | Report products below their reorder point without the web request cycle |
| **Preconditions** | Database reachable; environment configuration present. **No HTTP request and no session** |
| **Trigger** | `docker compose exec app php scripts/check-low-stock.php` |
| **Main Flow** | 1. Construct the database connection from environment variables · 2. Call **the same low-stock aggregation the dashboard uses** · 3. Print the summary · 4. Exit `0` |
| **Alternative Flow** | No product below reorder point → prints a zero-count summary and still exits `0`, because that is a successful result |
| **Failure Flow** | Database unreachable or query failure → one message on standard error, exit `1`, no stack trace · missing environment configuration → message on standard error, exit `2` |
| **Postconditions** | Read-only; nothing is mutated, no transaction opened |
| **Related Requirement IDs** | JOB-01, PRD-01, DASH-01, FIND-01, ENV-01 · `P1 §19.4` · `P2 §05 UC-G2`, `§18`, `ADR-003` · AC-JOB-01-1, AC-JOB-01-2 |

## 7.1 Use-Case Coverage

| Mandated use case (Phase 3 §6 list) | Use Case ID |
|---|---|
| Login | UC-A1 |
| Logout | UC-A2 |
| Create User | UC-B1 |
| Deactivate User | UC-B2 |
| Create Product | UC-C1 |
| Update Product | UC-C2 |
| Deactivate Product | UC-C3 |
| Create Purchase Order | UC-D1 |
| Order Purchase Order | UC-D2 |
| Receive Goods | UC-D3 |
| Create Sales Order | UC-E1 |
| Submit Sales Order | UC-E2 |
| Approve Sales Order | UC-E3 |
| Reject Sales Order | UC-E4 |
| Issue Goods | UC-E5 |
| View Dashboard | UC-F1 |
| Generate CSV Report | UC-F2 |
| Check Product Availability | UC-G1 |
| Check Low Stock | UC-G2 |

**19 of 19 mandated use cases specified.** Master-data use cases for Category, Warehouse, Supplier
and Customer follow the `UC-C1`/`UC-C2`/`UC-C3` pattern exactly — Admin-only, validate per
`P1 §19.1`, deactivate rather than delete where `P1 §19.2` says `Delete: No`, invalidate cache after
commit — and are specified as workflows in §10.3 rather than duplicated as separate use cases.

---

# 08. FUNCTIONAL REQUIREMENTS

Twenty-eight specifications — one per canonical requirement. Each is the **implementation view** of a
`P1 §11` row. The `Business Rule References` field always points at the canonical
`Business Rule Statement`; **no business rule is restated as a new rule here**, and no requirement is
redefined.

`N/A` means the requirement genuinely has no such aspect (as recorded in the corresponding Phase 1
matrix), not that it is unspecified.

## 8.1 Product Requirements

### FR — AUTH-01

- **Requirement ID:** AUTH-01
- **Name:** Login & Session
- **Purpose:** Establish an authenticated, role-bearing session and keep protected areas unreachable without one.
- **Actor:** Admin, Sales, Warehouse Staff
- **Preconditions:** An account exists for the submitted email; no valid session, or one being replaced.
- **Trigger:** Credential submission on the login form.
- **Main Flow:** Look up by email → refuse if inactive → `password_verify()` against the stored hash → establish the session → **regenerate the session identifier** → redirect to the role dashboard.
- **Alternative Flow:** An already-authenticated user re-authenticating receives a new identifier and the prior session state is discarded.
- **Failure Flow:** Unknown email, wrong password, and inactive account produce **one indistinguishable failure** with no field-level detail and no session. Session store unreachable → fail closed, treated as unauthenticated. Protected page without a session → redirect to login.
- **Postconditions:** Success: a session carrying user id and role, under a new identifier, with TTL 3600 s. Failure: no session, nothing persisted.
- **Business Rule References:** `P1 §11` AUTH-01 · Business Rule Statement
- **Input References:** `P1 §19.1` AUTH-01 (email, password)
- **State References:** N/A — authentication establishes no entity state transition
- **Authorization References:** `P1 §19.4` AUTH-01
- **Data Impact References:** `P1 §19.2` AUTH-01 (User: read only)
- **Acceptance Criteria References:** AC-AUTH-01-1 … AC-AUTH-01-5
- **Phase 2 Design References:** `P2` C-01, `UC-A1`, `SEC-01`…`SEC-06`, `RD-1`, `RD-3`, R-01

### FR — AUTH-02

- **Requirement ID:** AUTH-02
- **Name:** Logout
- **Purpose:** End a session so protected URLs are re-guarded.
- **Actor:** Admin, Sales, Warehouse Staff
- **Preconditions:** An authenticated session exists.
- **Trigger:** Logout activated in the application.
- **Main Flow:** Delete the authentication state → clear the session cookie → redirect to login.
- **Alternative Flow:** Logout on an already-expired session completes normally.
- **Failure Flow:** Session-store deletion failure → the session is still terminated at the application boundary; a stale identifier must never re-authenticate.
- **Postconditions:** No authentication data remains for the identifier; protected URLs are not served.
- **Business Rule References:** `P1 §11` AUTH-02 · Business Rule Statement
- **Input References:** N/A — `P1 §19.1` lists no input field for AUTH-02
- **State References:** N/A
- **Authorization References:** `P1 §19.4` AUTH-02 (own session only)
- **Data Impact References:** `P1 §19.2` AUTH-02 (no data impact)
- **Acceptance Criteria References:** AC-AUTH-02-1, AC-AUTH-02-2
- **Phase 2 Design References:** `P2` C-01, `UC-A2`, `SEC-07`, `SEC-08`, `RD-1`

### FR — USR-01

- **Requirement ID:** USR-01
- **Name:** User Management
- **Purpose:** Let Admin maintain Sales and Warehouse Staff accounts, with no public registration.
- **Actor:** Admin
- **Preconditions:** Requester authenticated as Admin.
- **Trigger:** User create, edit, or activation-toggle submission; user list or detail request.
- **Main Flow:** Assert Admin → validate name, email, password, role, active flag → assert email uniqueness (excluding self on edit) → hash the password → persist → list reflects the change.
- **Alternative Flow:** Activation toggling follows the same authorization with only the active flag changing.
- **Failure Flow:** Non-Admin → **403 on both the page and the endpoint** · missing required field → rejected · role outside the enum → rejected · duplicate email → rejected · unknown user → 404. Nothing persisted on any failure.
- **Postconditions:** The account set reflects the change. **No account is ever deleted** — deactivation is the only removal.
- **Business Rule References:** `P1 §11` USR-01 · Business Rule Statement
- **Input References:** `P1 §19.1` USR-01 (name, email, password, role, is_active)
- **State References:** `P1 §19.3` USR-01 (active ⇄ inactive)
- **Authorization References:** `P1 §19.4` USR-01
- **Data Impact References:** `P1 §19.2` USR-01 (User: read/create/update/deactivate; **Delete: No**)
- **Acceptance Criteria References:** AC-USR-01-1 … AC-USR-01-4
- **Phase 2 Design References:** `P2` C-02, `UC-B1`, `UC-B2`, R-01, T-01, `SEC-09`, `SEC-23`

### FR — PRD-01

- **Requirement ID:** PRD-01
- **Name:** Product & Category Management
- **Purpose:** Maintain the catalogue every transaction flow references, including the reorder point that drives low-stock behaviour.
- **Actor:** Admin (manage); all roles (view)
- **Preconditions:** Requester Admin for writes; at least one category exists before a product references one.
- **Trigger:** Product or category create, edit, or activation-toggle submission.
- **Main Flow:** Assert Admin → validate all fields → assert SKU uniqueness → assert buy price, sell price and reorder point are numeric and ≥ 0 → assert the category exists → if an image is supplied, validate type and size and store under a random unguessable filename → persist → invalidate the read cache **after commit**.
- **Alternative Flow:** Image omitted (optional). Deactivation is permitted even when the product is referenced by an order.
- **Failure Flow:** Non-Admin → 403 · duplicate SKU → rejected · negative or non-numeric numeric field → rejected with field feedback · unknown category → rejected · invalid image type or size → rejected · **permanent deletion of a referenced product → refused, deactivation offered** · unknown product → 404. A stored image is removed if the row write fails.
- **Postconditions:** The catalogue reflects the change; per-warehouse stock rows exist for every product; order history is intact.
- **Business Rule References:** `P1 §11` PRD-01 · Business Rule Statement
- **Input References:** `P1 §19.1` PRD-01 (sku, name, category_id, unit, buy_price, sell_price, reorder_point, image, is_active, category.name, category.description)
- **State References:** `P1 §19.3` PRD-01 (active ⇄ inactive)
- **Authorization References:** `P1 §19.4` PRD-01
- **Data Impact References:** `P1 §19.2` PRD-01 (Product: read/create/update/deactivate, **Delete: No**; Category: read/create/update)
- **Acceptance Criteria References:** AC-PRD-01-1 … AC-PRD-01-5
- **Phase 2 Design References:** `P2` C-03, C-04, `UC-C1`…`UC-C3`, R-02, R-03, T-03, T-04, `SEC-15`, `SEC-20`, `MC-1`, `MC-2`

### FR — MSTR-01

- **Requirement ID:** MSTR-01 *(Phase 1 derived ID, created by `DEC-015` — not an SRC-001 official ID)*
- **Name:** Supplier & Customer Master Data
- **Purpose:** Ensure every order references a valid, active counterparty without destroying history.
- **Actor:** Admin
- **Preconditions:** Requester authenticated as Admin.
- **Trigger:** Supplier or customer create, edit, or activation-toggle submission.
- **Main Flow:** Assert Admin → validate name, contact, address, active flag → persist → invalidate the read cache after commit.
- **Alternative Flow:** Deactivation is permitted at any time; the record remains referenced by historic orders.
- **Failure Flow:** Non-Admin → **403 on read and write endpoints alike** · missing required field → rejected · **permanent deletion of a referenced counterparty → refused, deactivation offered** · unknown record → 404.
- **Postconditions:** An inactive supplier is not selectable for a new PO and an inactive customer is not selectable for a new SO. Historic orders keep their counterparty.
- **Business Rule References:** `P1 §11` MSTR-01 · Business Rule Statement
- **Input References:** `P1 §19.1` MSTR-01 (name, contact, address, is_active)
- **State References:** `P1 §19.3` MSTR-01 (Supplier and Customer: active ⇄ inactive)
- **Authorization References:** `P1 §19.4` MSTR-01
- **Data Impact References:** `P1 §19.2` MSTR-01 (Supplier, Customer: read/create/update/deactivate; **Delete: No**)
- **Acceptance Criteria References:** AC-MSTR-01-1, AC-MSTR-01-2, AC-MSTR-01-3
- **Phase 2 Design References:** `P2` C-06, C-07, R-05, R-06, T-06, T-07, `MC-4`, `MC-5`, MI-4, `SEC-20`

### FR — WH-01

- **Requirement ID:** WH-01
- **Name:** Multi-Warehouse Stock
- **Purpose:** Make stock warehouse-specific, so quantity always answers "how much, and where".
- **Actor:** Admin (manage warehouses); Admin, Sales, Warehouse Staff (view stock)
- **Preconditions:** Requester Admin for warehouse writes; authenticated for stock views.
- **Trigger:** Warehouse create/edit/toggle; product stock view; new product or new warehouse created.
- **Main Flow:** Assert Admin for writes → validate name, location, active flag → persist → provision exactly one stock row per product per warehouse → stock views show the total **and** the per-warehouse breakdown.
- **Alternative Flow:** Creating a warehouse provisions a stock row for every existing product; creating a product provisions a row for every existing warehouse.
- **Failure Flow:** Non-Admin write → 403 · missing required field → rejected · a duplicate (product, warehouse) row is prevented by the composite unique constraint and treated as already-provisioned rather than as an error.
- **Postconditions:** Exactly one stock row per product per warehouse; quantity never negative; **total stock is never stored as an independent figure** — it is always a sum.
- **Business Rule References:** `P1 §11` WH-01 · Business Rule Statement
- **Input References:** `P1 §19.1` WH-01 (name, location, is_active)
- **State References:** `P1 §19.3` WH-01 (active ⇄ inactive)
- **Authorization References:** `P1 §19.4` WH-01
- **Data Impact References:** `P1 §19.2` WH-01 (Warehouse: read/create/update/deactivate, **Delete: No**; ProductStock: read/create row per product+warehouse)
- **Acceptance Criteria References:** AC-WH-01-1, AC-WH-01-2
- **Phase 2 Design References:** `P2` C-05, C-08, R-04, R-07, T-02, T-05 (composite unique key), `CC-4`, `MC-3`

### FR — PO-01

- **Requirement ID:** PO-01
- **Name:** Purchase Order & Goods Receipt
- **Purpose:** Replenish stock through an auditable order-to-receipt cycle, including partial deliveries.
- **Actor:** Admin (create, order, receive); Warehouse Staff (propose, receive)
- **Preconditions:** Requester Admin or Warehouse Staff; an active supplier and an active destination warehouse exist.
- **Trigger:** PO create; "Order" action; goods-receipt submission.
- **Main Flow:** **Create** — assert role → assert one supplier, one destination warehouse, ≥ 1 item → validate quantity and buy price ≥ 0 → persist as `Draft`. **Order** — Admin only, from `Draft` → `Ordered`. **Receive** — open one transaction → locked re-read → assert each received quantity is > 0 and within outstanding → increase stock at the destination warehouse and append a `Receipt` ledger row referencing the PO → increase received quantity → recompute status → commit.
- **Alternative Flow:** Partial receipt → `PartiallyReceived` with the remainder recorded; a later receipt completing every line → `Received`. Cancellation from `Draft`, `Ordered` or `PartiallyReceived` → `Cancelled` (Admin); **already-received stock and ledger rows are not reversed**.
- **Failure Flow:** Sales requester → 403 · no items → rejected · inactive/unknown supplier, unknown warehouse or product → rejected · negative quantity or price → rejected · **received quantity exceeding outstanding → rejected, nothing mutated** · non-`Draft` order attempt → rejected · any write failure inside the receipt → **full rollback of stock, ledger and received quantity**.
- **Postconditions:** Stock increased only via a committed receipt; every increase has a matching `Receipt` ledger row; outstanding quantity always equals ordered minus received and is never negative.
- **Business Rule References:** `P1 §11` PO-01 · Business Rule Statement
- **Input References:** `P1 §19.1` PO-01 (supplier_id, warehouse_id, order_date, status, item.product_id, item.quantity, item.buy_price, receipt.quantity)
- **State References:** `P1 §19.3` PO-01 (8 transition rows: Draft→Ordered, Ordered/PartiallyReceived→PartiallyReceived/Received, and the three cancellation rows)
- **Authorization References:** `P1 §19.4` PO-01
- **Data Impact References:** `P1 §19.2` PO-01 (PurchaseOrder, PurchaseOrderItem: read/create/update; ProductStock: **update — increase**; StockLedger: **create `Receipt`, append-only, no update, no delete**)
- **Acceptance Criteria References:** AC-PO-01-1 … AC-PO-01-6
- **Phase 2 Design References:** `P2` C-09, C-10, `UC-D1`…`UC-D3`, R-09, T-08, T-09, **`TX-1`**, `CC-5`, `SD-1`…`SD-7`

### FR — SO-01

- **Requirement ID:** SO-01
- **Name:** Sales Order, Approval & Goods Issue
- **Purpose:** Fulfil customer demand only after an authorised approval, and only within available stock.
- **Actor:** Sales (create, submit own, cancel own); Admin (create, approve, reject, cancel); Warehouse Staff (issue); Admin (issue)
- **Preconditions:** Requester's role permits the specific action; an active customer and an active source warehouse exist.
- **Trigger:** SO create; "Submit"; "Approve"; "Reject"; "Issue goods"; "Cancel".
- **Main Flow:** **Create** — assert Sales or Admin → assert one customer, one source warehouse, ≥ 1 item → validate quantity and sell price ≥ 0 → **record the creator from the session** → persist as `Draft`. **Submit** — Admin, or Sales and the creator → `PendingApproval`. **Approve** — **assert role is exactly `Admin` first** → record approver → `Approved`. **Reject** — same assertion → record the decider and optional reason → transition to the rejection terminal status (`DEC-009`, **`ASM-001`**). **Issue** — open one transaction → locked re-read of the SO asserting `Approved` → per line locked stock read → assert `current − requested >= 0` → decrease as a delta and append an `Issue` ledger row → `Fulfilled` → commit.
- **Alternative Flow:** Cancellation is permitted at any stage **before** `Fulfilled` — Sales on its own `Draft`/`PendingApproval`, Admin at any pre-`Fulfilled` stage.
- **Failure Flow:** **Sales attempting approve or reject → 403 with no transition, including on its own order** · Warehouse Staff attempting approve, reject, create or submit → 403 · Sales submitting another user's order → 403 · issue on a non-`Approved` SO → refused · **insufficient stock on any line → whole issue refused and rolled back** · concurrent issue that exhausted the stock first → refused · any write failure → rollback · unknown SO → 404.
- **Postconditions:** Stock decreases only via a committed issue on an `Approved` order; every decrease has a matching `Issue` ledger row; stock never negative; no lost update; approver recorded.
- **Business Rule References:** `P1 §11` SO-01 · Business Rule Statement
- **Input References:** `P1 §19.1` SO-01 (customer_id, warehouse_id, created_by, approved_by, status, item.product_id, item.quantity, item.sell_price, issue.quantity)
- **State References:** `P1 §19.3` SO-01 (7 transition rows) — **no transition to a `Rejected` status exists**
- **Authorization References:** `P1 §19.4` SO-01, **SoD-1**, **SoD-2**
- **Data Impact References:** `P1 §19.2` SO-01 (SalesOrder, SalesOrderItem: read/create/update; ProductStock: **update — decrease**; StockLedger: **create `Issue`, append-only**)
- **Acceptance Criteria References:** AC-SO-01-1 … AC-SO-01-8
- **Phase 2 Design References:** `P2` C-11, C-12, C-13, `UC-E1`…`UC-E5`, R-10, T-10, T-11, **`TX-2`**, `CC-1`…`CC-3`, `§12.5`, `§12.6`, `ADR-002`
- **Assumption Reference:** **`ASM-001` — OPEN / MANDATORY** (rejection terminal status)

### FR — VIEW-01

- **Requirement ID:** VIEW-01
- **Name:** Role-Scoped Lists & Detail Pages
- **Purpose:** Let each role navigate the records it is entitled to see, with informative empty states.
- **Actor:** Admin, Sales, Warehouse Staff
- **Preconditions:** Authenticated session.
- **Trigger:** A product, PO or SO list or detail page is requested.
- **Main Flow:** Build the role scope from the session → apply the scope **in the query predicate** → paginate → render rows, or an informative empty state when none match.
- **Alternative Flow:** A detail page for a record inside scope renders fully; master-data labels may be served from the read cache while figures come from the database.
- **Failure Flow:** Unauthenticated → redirect to login · record outside the requester's scope → treated as not found → **404** · non-existent record or route → 404 · no matching rows → **informative empty state, never a blank table body**.
- **Postconditions:** Only in-scope records are presented; read-only, nothing mutated.
- **Business Rule References:** `P1 §11` VIEW-01 · Business Rule Statement
- **Input References:** `P1 §19.1` VIEW-01 (record id for detail)
- **State References:** N/A — viewing changes no state
- **Authorization References:** `P1 §19.4` VIEW-01 (scope applied in the query, not the template)
- **Data Impact References:** `P1 §19.2` VIEW-01 (Product, PurchaseOrder, SalesOrder: read only)
- **Acceptance Criteria References:** AC-VIEW-01-1, AC-VIEW-01-2
- **Phase 2 Design References:** `P2` R-02, R-05, R-06, R-09, R-10 `paginate()`, OW-5, `MC-6`

### FR — FIND-01

- **Requirement ID:** FIND-01
- **Name:** Search, Filter, Sort & Pagination
- **Purpose:** Keep large lists usable and reproducible.
- **Actor:** Admin, Sales, Warehouse Staff
- **Preconditions:** Authenticated session; seed data provides ≥ 30 products and ≥ 25 combined orders so pagination is testable.
- **Trigger:** A search term, filter, sort, or page change is submitted on a list.
- **Main Flow:** Bind the search term, filters, sort direction and page number as parameters → apply the role scope → execute one count query and one page query → return **exactly 10 records per page** → render controls carrying the active selections.
- **Alternative Flow:** No criteria supplied → the unfiltered first page in the default order. Product stock-status filter classifies a product as **low stock when its stock is below its reorder point**, otherwise normal.
- **Failure Flow:** Page number below 1 or beyond the last page → clamped to a valid page rather than erroring · unknown filter value → treated as no filter · sort direction outside `{asc, desc}` → default direction. **Sort and filter values never reach SQL as concatenated text** — column and direction come from a closed allow-list.
- **Postconditions:** Active search, filter and sort selections **remain in effect when the page changes**; read-only.
- **Business Rule References:** `P1 §11` FIND-01 · Business Rule Statement
- **Input References:** `P1 §19.1` FIND-01 (search term, category filter, stock status filter, order status filter, date sort, page)
- **State References:** N/A
- **Authorization References:** `P1 §19.4` FIND-01 (same scope as VIEW-01, applied in the query)
- **Data Impact References:** `P1 §19.2` FIND-01 (Product, PurchaseOrder, SalesOrder: read only)
- **Acceptance Criteria References:** AC-FIND-01-1 … AC-FIND-01-4
- **Phase 2 Design References:** `P2` R-02 `ProductFilter`, R-09/R-10 `OrderFilter`, T-04/T-08/T-10 indexes, DR-3, `SEC-11`, `§15` of this document

### FR — DASH-01

- **Requirement ID:** DASH-01
- **Name:** Role-Scoped Dashboard
- **Purpose:** Show live operational state per role, computed rather than stored.
- **Actor:** Admin, Sales, Warehouse Staff
- **Preconditions:** Authenticated session.
- **Trigger:** Dashboard requested.
- **Main Flow:** Build the role scope → select the role's metric set → execute each figure as an **aggregation query** with the scope bound → render.
- **Alternative Flow:** A role with no data renders zeros, not an error.
- **Failure Flow:** Unauthenticated → redirect · aggregation failure → generic error page with no exception text.
- **Postconditions:** Every figure reflects current data. **No figure is a stored, cached, or hard-coded value**; reloading after a data change changes the figure.
- **Business Rule References:** `P1 §11` DASH-01 · Business Rule Statement
- **Input References:** N/A — `P1 §19.1` lists no user-supplied input for DASH-01
- **State References:** N/A
- **Authorization References:** `P1 §19.4` DASH-01 (Admin all; Sales own orders; Warehouse Staff stock and fulfilment)
- **Data Impact References:** `P1 §19.2` DASH-01 (Product, ProductStock, PurchaseOrder, SalesOrder, StockLedger: read only)
- **Acceptance Criteria References:** AC-DASH-01-1 … AC-DASH-01-4
- **Phase 2 Design References:** `P2` C-15, `UC-F1`, R-11, `§17.1`, DR-1…DR-5, `ADR-003`, MI-5

### FR — REPORT-01

- **Requirement ID:** REPORT-01
- **Name:** CSV Reports
- **Purpose:** Export stock movement and order status for a date range, from the same basis as the dashboard.
- **Actor:** Admin (all); Sales (own orders); Warehouse Staff (stock report)
- **Preconditions:** Authenticated session; `date_from <= date_to`.
- **Trigger:** Export requested with report type and date range.
- **Main Flow:** Validate the report type against `{stock movement, order status}` → validate both dates → build the role scope → call **the same aggregation methods the dashboard calls** → stream rows as CSV.
- **Alternative Flow:** Empty result → header row only.
- **Failure Flow:** Invalid or inverted range → rejected, nothing streamed · **Sales requesting another user's orders → 403 before any query** · unauthenticated → redirect · aggregation failure → generic error.
- **Postconditions:** The export is constrained to the range and to the requester's scope, and **cannot disagree with the corresponding dashboard figure**.
- **Business Rule References:** `P1 §11` REPORT-01 · Business Rule Statement
- **Input References:** `P1 §19.1` REPORT-01 (report type, date_from, date_to)
- **State References:** N/A
- **Authorization References:** `P1 §19.4` REPORT-01
- **Data Impact References:** `P1 §19.2` REPORT-01 (StockLedger, PurchaseOrder, SalesOrder: read only)
- **Acceptance Criteria References:** AC-REPORT-01-1, AC-REPORT-01-2, AC-REPORT-01-3
- **Phase 2 Design References:** `P2` C-16, `UC-F2`, R-11, `§17.2` `RP-1`/`RP-2`, RR2-1…RR2-7, `ADR-003`, `DEC-013`

### FR — API-01

- **Requirement ID:** API-01
- **Name:** JSON Availability Contract
- **Purpose:** Expose per-warehouse availability by SKU as a machine contract distinct from HTML pages.
- **Actor:** Authenticated API Consumer
- **Preconditions:** A valid session is presented on the request.
- **Trigger:** `GET /api/products/{sku}/availability`.
- **Main Flow:** Resolve the session **by the same rule as an HTML page** → resolve the product by SKU → read per-warehouse quantities **from MySQL** → return 200 with `Content-Type: application/json` and the per-warehouse breakdown plus the computed total.
- **Alternative Flow:** A warehouse with zero stock is returned with quantity `0` rather than omitted.
- **Failure Flow:** No valid session → **401 JSON** · unknown or malformed SKU → **404 JSON** · wrong method → 405 JSON · internal failure → 500 JSON with a generic message. **Never an HTML error page, never an HTML login redirect.**
- **Postconditions:** Read-only; nothing mutated. Availability figures come from the database, never from cache.
- **Business Rule References:** `P1 §11` API-01 · Business Rule Statement
- **Input References:** `P1 §19.1` API-01 (sku path parameter)
- **State References:** N/A
- **Authorization References:** `P1 §19.4` API-01 (any authenticated role; unauthenticated → 401 JSON)
- **Data Impact References:** `P1 §19.2` API-01 (Product, ProductStock: read only)
- **Acceptance Criteria References:** AC-API-01-1, AC-API-01-2, AC-API-01-3
- **Phase 2 Design References:** `P2` C-17, `UC-G1`, `§16.1`, AP-1…AP-5, `SEC-19`, `RD-1`, `MC-1`

### FR — VAL-01

- **Requirement ID:** VAL-01
- **Name:** Input Validation
- **Purpose:** Prevent invalid data from being stored, with the backend as the authority.
- **Actor:** All actors submitting input
- **Preconditions:** A form or endpoint receives input.
- **Trigger:** Any create or update submission.
- **Main Flow:** Frontend validates for convenience → **backend re-validates authoritatively** for required fields, types, formats, ranges, enum membership, uniqueness and foreign-key existence → only a fully valid payload proceeds to persistence.
- **Alternative Flow:** Domain-state validation that depends on a locked read (available stock, outstanding quantity) runs **inside** the transaction, not before it.
- **Failure Flow:** Any validation failure → **nothing is persisted, no transaction is opened**, field-level feedback is returned, and **already-entered input is preserved where relevant to the correction**. A frontend pass never substitutes for the backend pass.
- **Postconditions:** Persisted data satisfies every rule in `P1 §19.1` and the relevant `P1 §11` business rule.
- **Business Rule References:** `P1 §11` VAL-01 · Business Rule Statement
- **Input References:** **`P1 §19.1` in full — the canonical detailed source for every field**
- **State References:** N/A — validation precedes a transition
- **Authorization References:** `P1 §19.4` — authorization is asserted before validation-dependent writes
- **Data Impact References:** `P1 §19.2` VAL-01 (all written entities: read)
- **Acceptance Criteria References:** AC-VAL-01-1, AC-VAL-01-2, AC-VAL-01-3
- **Phase 2 Design References:** `P2` SR-2, SR-3, TD-3, `SEC-14`, T-04/T-09/T-11 `CHECK` constraints, `§12` of this document

### FR — ERR-01

- **Requirement ID:** ERR-01
- **Name:** Error Handling
- **Purpose:** Make failures predictable and non-disclosing.
- **Actor:** All actors
- **Preconditions:** A request fails authentication, authorization, lookup, validation, or execution.
- **Trigger:** Any failure condition.
- **Main Flow:** A typed domain exception is raised in the Service → the Controller maps it to the correct HTTP status → a safe message is rendered (HTML) or returned (JSON) → the detail is logged server-side only.
- **Alternative Flow:** API failures follow the JSON contract of `P2 §16.1`; CLI failures write one line to standard error with a non-zero exit code.
- **Failure Flow:** Unauthenticated → **redirect to login** (HTML) or **401** (API) · authenticated without authority → **403** · missing record or route → **404** · unexpected failure → **500 with a generic message**. **A database exception, SQL text, or stack trace is never rendered to a user.**
- **Postconditions:** The user sees an actionable, non-disclosing message; no partial state survives a failed operation.
- **Business Rule References:** `P1 §11` ERR-01 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** `P1 §19.4` ERR-01 (contextual)
- **Data Impact References:** `P1 §19.2` — ERR-01 has no data impact
- **Acceptance Criteria References:** AC-ERR-01-1 … AC-ERR-01-4
- **Phase 2 Design References:** `P2` `SEC-17`, `SEC-18`, `SEC-19`, SR-4, `§16.1` failure table, `§20` of this document

### FR — UI-01

- **Requirement ID:** UI-01
- **Name:** Responsive, Labelled UI on the Existing Baseline
- **Purpose:** Keep the approved screens usable at 360px and on desktop.
- **Actor:** All actors
- **Preconditions:** The existing UI/UX baseline is binding (`DEC-007`).
- **Trigger:** Any page render.
- **Main Flow:** Render the existing baseline screens → keep login, dashboard, list, detail and form pages usable at a 360px viewport and on desktop with no clipped navigation or table content → give every form field a visible label → keep focus state and basic contrast perceivable → escape all user-originated output.
- **Alternative Flow:** Where the baseline documents a collapsed rail or mobile drawer, that documented behaviour is **applied** to product screens — baseline application, not redesign (`ASM-003`).
- **Failure Flow:** Where the baseline is indeterminate, **no behaviour is invented** — the gap is carried as `DG-06` and requires a decision rather than an improvisation. **Hiding a UI control is never authorization** (`SoD-2`).
- **Postconditions:** Every page kind is usable at both viewports; no rule and no authorization decision lives in the view.
- **Business Rule References:** `P1 §11` UI-01 · Business Rule Statement
- **Input References:** N/A — UI-01 owns no input field
- **State References:** N/A — view state is owned by the UI baseline, not `P1 §19.3`
- **Authorization References:** `P1 §19.4` UI-01 — **UI visibility is never authorization**
- **Data Impact References:** `P1 §19.2` — UI-01 has no data impact
- **Acceptance Criteria References:** AC-UI-01-1, AC-UI-01-2
- **Phase 2 Design References:** `P2 §03` view-layer row, SoD-G, `SEC-12`, `DG-06`; `§23` of this document
- **Assumption Reference:** `ASM-003` (ACCEPTED)

### FR — JOB-01

- **Requirement ID:** JOB-01
- **Name:** Low-Stock Standalone Script
- **Purpose:** Report products below reorder point independently of the web request cycle.
- **Actor:** Script Operator with container access
- **Preconditions:** Database reachable; environment configuration present. No HTTP request, no session.
- **Trigger:** `docker compose exec app php scripts/check-low-stock.php`.
- **Main Flow:** Construct the connection from environment variables → call **the same low-stock aggregation the dashboard uses** → print the summary → exit `0`.
- **Alternative Flow:** Optional `--warehouse=CODE` narrows scope and `--format=text|csv` selects output shape; the script runs correctly with no arguments. Zero results still exit `0`.
- **Failure Flow:** Database unreachable or query failure → one line on standard error, exit `1`, **no stack trace** · missing environment configuration → message on standard error, exit `2`.
- **Postconditions:** Read-only; nothing mutated; no transaction opened. **No automatic scheduling exists or is required.**
- **Business Rule References:** `P1 §11` JOB-01 · Business Rule Statement
- **Input References:** N/A — `P1 §19.1` lists no input field for JOB-01; the CLI flags are design conveniences (`P2 §18`)
- **State References:** N/A
- **Authorization References:** `P1 §19.4` JOB-01 — not reachable over HTTP; container access is the boundary
- **Data Impact References:** `P1 §19.2` JOB-01 (Product, ProductStock: read only)
- **Acceptance Criteria References:** AC-JOB-01-1, AC-JOB-01-2
- **Phase 2 Design References:** `P2` C-18, `UC-G2`, `§18`, JD-1…JD-6, R-11, `ADR-003`

## 8.2 Technical Requirements

### FR — DB-01

- **Requirement ID:** DB-01
- **Name:** Relational Schema, Prepared Statements & Transactions
- **Purpose:** Make MySQL the reliable, constrained, reproducible source of business truth.
- **Actor:** System
- **Preconditions:** MySQL 8 available; environment configuration present.
- **Trigger:** Schema application; any query; any multi-table write; seed execution.
- **Main Flow:** Apply the 12-table schema with primary keys, foreign keys, `CHECK` constraints including `product_stock.quantity >= 0`, unique keys and the indexes listed in `P2 §07` → execute every input-bearing query as a **PDO prepared statement with bound parameters** → wrap **every operation writing more than one table**, specifically goods receipt and goods issue, in an **explicit transaction** → run schema and seed to build a working database **from empty**.
- **Alternative Flow:** Seed is idempotent and separate from schema, so either can be re-run.
- **Failure Flow:** A negative `product_stock.quantity` write → **rejected by the database** · missing PK or FK → schema defect · concatenated user input in SQL → forbidden (`P1 §08` "Raw User-Input SQL", CF-5) · a multi-table write outside a transaction → forbidden · a failed transaction → complete rollback.
- **Postconditions:** Schema and seed produce ≥ 30 products, ≥ 25 combined orders, 1 Admin, ≥ 2 Sales, ≥ 2 Warehouse Staff, ≥ 2 warehouses, reorder-point variation with some products below reorder point, and order status variation including `PendingApproval` and `Cancelled`.
- **Business Rule References:** `P1 §11` DB-01 · Business Rule Statement
- **Input References:** `P1 §19.1` DB-01 (ProductStock.quantity, StockLedger.movement_type, StockLedger.reference)
- **State References:** `P1 §19.3` — DB-01 owns no transition; it enforces the constraints the transitions rely on
- **Authorization References:** `P1 §19.4` — no HTTP authorization; schema privileges only
- **Data Impact References:** `P1 §19.2` DB-01 (all entities: read/create/update/deactivate; **Delete: No**)
- **Acceptance Criteria References:** AC-DB-01-1 … AC-DB-01-5
- **Phase 2 Design References:** `P2 §07` T-01…T-12 and `§07.1` seed design, `SEC-11`, `SEC-24`, TD-1…TD-6, `TX-1`, `TX-2`

### FR — ARCH-01

- **Requirement ID:** ARCH-01
- **Name:** Layered Architecture & Dependency Inversion
- **Purpose:** Keep business logic independent of infrastructure so it is correct, reviewable and testable.
- **Actor:** System
- **Preconditions:** None — this is a structural constraint on all code.
- **Trigger:** Any code written in the application.
- **Main Flow:** Dependencies flow **Controller → Service → Repository Interface → Concrete Repository → PDO/MySQL** and never the reverse → a Service depends on repository **interfaces** and receives them by **constructor injection** → at least one repository interface has **two implementations**, a real MySQL one and an in-memory fake → business-logic tests run **with no database connection**.
- **Alternative Flow:** The transaction boundary is owned by the Service and reached through `TransactionManagerInterface`, so a Service orchestrates a transaction **without naming PDO**.
- **Failure Flow:** A `PDO` type, SQL string, or `use App\Persistence\` import inside `src/Service/` → violation · a superglobal read inside a Service → violation · `new PDO()` inside a Service → violation · a global infrastructure singleton → violation. Each is detectable by grep (`P2` BR-3…BR-8).
- **Postconditions:** `ProductStockRepositoryInterface` has both `MySqlProductStockRepository` and `InMemoryProductStockRepository`; the unit suite passes with no database available.
- **Business Rule References:** `P1 §11` ARCH-01 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** `P1 §19.4` — ARCH-01 makes server-side authorization *possible* by isolating the Service; it grants nothing
- **Data Impact References:** `P1 §19.2` — no data impact
- **Acceptance Criteria References:** AC-ARCH-01-1 … AC-ARCH-01-4
- **Phase 2 Design References:** `P2 §02.1`, `§02.2`, `§03`, AI-1…AI-8, BR-1…BR-8, R-07, `§06.1`, **`ADR-001`**

### FR — ARCH-02

- **Requirement ID:** ARCH-02
- **Name:** Stock Atomicity & Concurrency Safety
- **Purpose:** Guarantee that stock is never wrong, even under simultaneous operations.
- **Actor:** System
- **Preconditions:** A stock-mutating operation is authorised and about to execute.
- **Trigger:** Goods receipt or goods issue.
- **Main Flow:** Open one explicit transaction → read the `ProductStock` row **`FOR UPDATE`** → assert the resulting quantity would be ≥ 0 → write the change as a **relative delta** → append the corresponding `StockLedger` row in the **same** transaction, recording the resulting quantity → commit. Multi-line operations acquire locks in ascending `product_id` then `warehouse_id` order.
- **Alternative Flow:** The `quantity >= 0` database constraint stands as a backstop if an assertion were ever bypassed. A deadlock surfaces as an explicit retryable failure; **no automatic retry loop exists**, because the required outcome is refusal.
- **Failure Flow:** Either write failing → **neither takes effect** · a second concurrent issue after stock is exhausted → **rejected or deferred, never oversold** · two concurrent writes → **no lost update**, because the pre-write value comes from a locked read and the write is a delta · stock unexplainable from the ledger → a defect, detectable by reconciliation.
- **Postconditions:** `product_stock.quantity` equals the sum of its signed `stock_ledger.quantity_change` rows at all times; a controlled, reproducible scenario demonstrates the second competing request being refused.
- **Business Rule References:** `P1 §11` ARCH-02 · Business Rule Statement
- **Input References:** N/A — ARCH-02 constrains how the PO-01/SO-01 inputs are processed
- **State References:** `P1 §19.3` ARCH-02 (4 rows: ProductStock receipt/issue commits, StockLedger `Receipt`/`Issue` appends)
- **Authorization References:** `P1 §19.4` PO-01 / SO-01 — the operations ARCH-02 protects
- **Data Impact References:** `P1 §19.2` ARCH-02 (ProductStock: update; StockLedger: create)
- **Acceptance Criteria References:** AC-ARCH-02-1 … AC-ARCH-02-4
- **Phase 2 Design References:** `P2 §09` SD-1…SD-7, **`TX-1`**, **`TX-2`**, `§11` `CC-1`…`CC-5`, `§11.2` lock ordering, **`ADR-002`**, T-05 `CHECK`

### FR — ENV-01

- **Requirement ID:** ENV-01 *(Phase 1 derived ID, created by `DEC-016`)*
- **Name:** Docker & Environment Reproducibility
- **Purpose:** Let anyone start the system from a clean checkout without machine-specific setup.
- **Actor:** System
- **Preconditions:** A host with Docker and a clean checkout.
- **Trigger:** `docker compose up --build`.
- **Main Flow:** Compose defines **at minimum an application/web service and a MySQL service** (plus the Redis and Memcached services `DEC-005`/`DEC-006` introduce) → all environment-specific configuration comes from **environment variables** with example values committed in `.env` → the application becomes reachable following only the documented README procedure.
- **Alternative Flow:** Redis or Memcached unavailable → authentication degrades to unauthenticated and all reads go to MySQL; **no business function becomes incorrect**.
- **Failure Flow:** Startup requiring undocumented manual steps → failure · an active secret committed → failure · reliance on an absolute path or participant-machine configuration → failure.
- **Postconditions:** A working application and database from a clean state; `.env` is git-ignored; no active credential is in the repository or its history.
- **Business Rule References:** `P1 §11` ENV-01 · Business Rule Statement
- **Input References:** `P1 §19.1` ENV-01 (environment variables per `.env`)
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** `P1 §19.2` — no data impact (schema/seed impact is DB-01's)
- **Acceptance Criteria References:** AC-ENV-01-1, AC-ENV-01-2, AC-ENV-01-3
- **Phase 2 Design References:** `P2 §02.5`, `RD-4`, `MC-7`, `SEC-16`, JD-6, `§07.1`

### FR — TEST-01

- **Requirement ID:** TEST-01
- **Name:** Unit Tests
- **Purpose:** Prove business logic is correct and infrastructure-independent.
- **Actor:** System
- **Preconditions:** The layered structure of ARCH-01 exists.
- **Trigger:** Test suite execution.
- **Main Flow:** At least **6 test cases** across at least **3 distinct logic areas** — for example PO date validation, SO status transition, low-stock calculation, approval authorization — executed against Services wired with `InMemoryProductStockRepository`, `NullTransactionManager`, `NullCache` and a frozen clock.
- **Alternative Flow:** The SoD case is a unit test: `approve()` with a Sales context throws and the repository's approval write is never called.
- **Failure Flow:** A unit test touching a session, a real PDO connection, or an external service → does not count · a trivial getter/setter test → does not count · fewer than 6 cases, or cases concentrated in fewer than 3 areas → requirement unmet.
- **Postconditions:** The unit suite passes with **no database available**.
- **Business Rule References:** `P1 §11` TEST-01 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** `P1 §19.2` — no production data impact
- **Acceptance Criteria References:** AC-TEST-01-1, AC-TEST-01-2
- **Phase 2 Design References:** `P2` R-07, `§06.1` (`NullTransactionManager`, `NullCache`, `FrozenClock`), SoD-I, RU-5, `ADR-001`

### FR — TEST-02

- **Requirement ID:** TEST-02
- **Name:** Integration Tests
- **Purpose:** Prove the end-to-end stock behaviour and the concurrency invariant against real MySQL.
- **Actor:** System
- **Preconditions:** MySQL running in Docker; fixtures loadable.
- **Trigger:** Integration suite execution.
- **Main Flow:** At least **3 integration tests** exercising real MySQL in Docker, separated from the unit suite — including a goods receipt genuinely increasing stock with its ledger row present, and a second goods issue being **rejected once stock is exhausted by the first**, plus reconciliation of `product_stock.quantity` against the summed signed ledger movements.
- **Alternative Flow:** Fixture setup and teardown run inside a transaction so tests remain independent.
- **Failure Flow:** A counted test that never reaches MySQL → does not count · an order-dependent or timing-dependent test → violates `TEST-03` · a concurrency test relying on `sleep()` → forbidden; the second connection's lock blocking is the synchronisation point.
- **Postconditions:** The `ARCH-02` invariant is demonstrated in a controlled, reproducible way.
- **Business Rule References:** `P1 §11` TEST-02 · Business Rule Statement
- **Input References:** N/A
- **State References:** `P1 §19.3` ARCH-02 rows — the transitions under test
- **Authorization References:** N/A
- **Data Impact References:** `P1 §19.2` TEST-02 (all entities in fixtures; delete permitted **in test teardown only**)
- **Acceptance Criteria References:** AC-TEST-02-1, AC-TEST-02-2, AC-TEST-02-3
- **Phase 2 Design References:** `P2 §11` `CC-1`…`CC-5` verification scenarios, `§09.5` reconciliation, `§10.3` fixture row

### FR — TEST-03

- **Requirement ID:** TEST-03
- **Name:** Static Analysis & FIRST
- **Purpose:** Keep the codebase statically clean and the suite trustworthy.
- **Actor:** System
- **Preconditions:** A test suite and a static analysis tool exist.
- **Trigger:** Static analysis run; test suite run.
- **Main Flow:** Produce a **PHPStan level 5+ or PHP_CodeSniffer PSR-12 report** with **zero critical errors**, briefly explaining any remaining warning → keep tests Fast, Independent, Repeatable, Self-validating and Timely.
- **Alternative Flow:** Either tool satisfies the requirement; both may be run.
- **Failure Flow:** Critical errors present → unmet · warnings left unexplained → unmet · any `sleep()`, any real network call, or any execution-order dependency in the suite → unmet.
- **Postconditions:** The report is attached; re-running the suite in a different order produces the same result.
- **Business Rule References:** `P1 §11` TEST-03 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** N/A
- **Acceptance Criteria References:** AC-TEST-03-1, AC-TEST-03-2
- **Phase 2 Design References:** `P2` BR-1…BR-8 as statically visible boundaries, `ClockInterface`, `CC-1` lock-based synchronisation

### FR — DESIGN-01

- **Requirement ID:** DESIGN-01
- **Name:** Class Diagrams (Initial and As-Built)
- **Purpose:** Show intended structure before coding and actual structure after, with the delta explained.
- **Actor:** System
- **Preconditions:** The Phase 2 design exists (initial); implementation is complete (as-built).
- **Trigger:** Start of implementation (initial); end of implementation (as-built).
- **Main Flow:** An **initial** class diagram in `docs/planning/` showing Controller, Service, Repository and Entity with their relationships — **already produced in `P2 §20`** → an **as-built** diagram in `docs/architecture/` distinguishing **interface-directed** from **concrete-directed** dependencies → a **2–3 sentence** note stating what changed between them and why.
- **Alternative Flow:** Any legible tool is acceptable.
- **Failure Flow:** An initial diagram produced after implementation → unmet · missing interface-versus-concrete markers on the as-built → unmet · a diagram that does not match the code when traced class-by-class at defense → unmet.
- **Postconditions:** Both diagrams exist in their required locations and are traceable to code.
- **Business Rule References:** `P1 §11` DESIGN-01 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** N/A
- **Acceptance Criteria References:** AC-DESIGN-01-1, AC-DESIGN-01-2, AC-DESIGN-01-3
- **Phase 2 Design References:** **`P2 §20`** (initial diagram, three views, provenance stated); as-built deferred to implementation by design

### FR — DESIGN-02

- **Requirement ID:** DESIGN-02
- **Name:** Architecture Decision Records
- **Purpose:** Record genuine architectural decisions with their reasoning.
- **Actor:** System
- **Preconditions:** Architectural decisions have been made.
- **Trigger:** A material architectural decision.
- **Main Flow:** **2–3 ADRs**, each stating context, decision and consequences, stored as `docs/architecture/adr-*.md` — **already produced in `P2 §21`**: `ADR-001` repository interfaces plus a transaction-manager abstraction, `ADR-002` pessimistic row locking, `ADR-003` one canonical aggregation repository.
- **Alternative Flow:** Each ADR also records the alternatives considered and its status, which exceeds the minimum without adding scope.
- **Failure Flow:** Fewer than 2 ADRs → unmet · an ADR recording a trivial implementation choice → unmet · an ADR duplicating the DECISION REGISTRY → a governance violation.
- **Postconditions:** Three ADRs exist, each naming the `DEC-*` entry it operates under rather than creating one.
- **Business Rule References:** `P1 §11` DESIGN-02 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** N/A
- **Acceptance Criteria References:** AC-DESIGN-02-1, AC-DESIGN-02-2
- **Phase 2 Design References:** **`P2 §21`** `ADR-001`, `ADR-002`, `ADR-003`

### FR — DESIGN-03

- **Requirement ID:** DESIGN-03
- **Name:** Refactoring Log, SRP Audit & Tech-Debt Register
- **Purpose:** Demonstrate deliberate quality improvement rather than first-draft code.
- **Actor:** System
- **Preconditions:** Code exists that can be improved.
- **Trigger:** Implementation and refactoring work.
- **Main Flow:** A refactoring log with **at least 3 entries**, each naming the smell, the technique applied, and a before/after excerpt → **exactly one** SRP audit note identifying an initial-draft class that violated SRP and how it was split → a tech-debt register recording shortcuts honestly with the ideal fix → **at least one commit tagged `refactor:`** improving pre-existing code rather than the current feature.
- **Alternative Flow:** The SRP note has a natural candidate already visible in the design: the split of `GoodsReceiptService`/`GoodsIssueService` from the order services and of `SalesOrderApprovalService` from `SalesOrderService` (`P2` C-10, C-12, C-13).
- **Failure Flow:** Fewer than 3 entries, or an entry missing a component → unmet · no SRP note → unmet · a known shortcut omitted from the register → unmet · only feature commits present → unmet.
- **Postconditions:** `docs/quality/` contains the log, the SRP note and the tech-debt register.
- **Business Rule References:** `P1 §11` DESIGN-03 · Business Rule Statement
- **Input References:** N/A
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** N/A
- **Acceptance Criteria References:** AC-DESIGN-03-1 … AC-DESIGN-03-4
- **Phase 2 Design References:** `P2 §04` C-10/C-12/C-13 split, `§04.1` components deliberately not created, BR-1…BR-8 as reviewable smells

### FR — DESIGN-04

- **Requirement ID:** DESIGN-04
- **Name:** Code Critique Exercise
- **Purpose:** Demonstrate the ability to diagnose bad code, not only to write good code.
- **Actor:** System
- **Preconditions:** The assessor has supplied a deliberately problematic excerpt.
- **Trigger:** The assessor provides the exercise.
- **Main Flow:** Read the excerpt → name the smells present → name the SOLID principles violated → describe the refactoring direction → store as `docs/quality/critique.md`.
- **Alternative Flow:** Implementing the fix is **not required**; written analysis suffices (SRC-001 FAQ 5).
- **Failure Flow:** Critique absent → unmet · a critique naming neither a smell nor a violated principle → unmet.
- **Postconditions:** `docs/quality/critique.md` exists and is discussable at defense.
- **Business Rule References:** `P1 §11` DESIGN-04 · Business Rule Statement
- **Input References:** N/A — the excerpt is assessor-supplied and cannot be produced in advance
- **State References:** N/A
- **Authorization References:** N/A
- **Data Impact References:** N/A
- **Acceptance Criteria References:** AC-DESIGN-04-1
- **Phase 2 Design References:** `P2 §03` responsibility matrix and `§03.1` prohibitions as the critique yardstick

**28 of 28 canonical requirements specified. 0 new requirements. 0 new requirement IDs.**

---

# 09. BUSINESS RULES

Seventy-eight formalized rules across the twelve mandated domains. **This is not a second business
rule authority.** Every rule below formalizes the canonical `Business Rule Statement` in the
referenced `P1 §11` row, plus the supporting matrix that owns its detail. Where a rule's detail is
owned by a matrix, the matrix is cited and the detail is not restated.

The `BR-<domain>-<n>` label is a **reference label for this document only**. It creates no registry
and confers no authority.

## 9.1 Authentication

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-AUTH-1 | A valid, active user may authenticate | AUTH-01 | `P1 §11` AUTH-01 |
| BR-AUTH-2 | An inactive user must not authenticate | AUTH-01 | `P1 §11` AUTH-01 |
| BR-AUTH-3 | A failed credential attempt returns a safe message that does not disclose which credential part was wrong | AUTH-01 | `P1 §11` AUTH-01 · `P1 §19.5` |
| BR-AUTH-4 | A protected page must not be served without an authenticated session | AUTH-01 | `P1 §11` AUTH-01 |
| BR-AUTH-5 | The session identifier must be renewed on successful login | AUTH-01 | `P1 §11` AUTH-01 · `P1 §19.5` session fixation |
| BR-AUTH-6 | Passwords are stored with `password_hash()` and verified with `password_verify()` | AUTH-01 | `P1 §11` AUTH-01 |
| BR-AUTH-7 | Logout must remove authentication data from the session | AUTH-02 | `P1 §11` AUTH-02 |
| BR-AUTH-8 | After logout, a protected URL must not be served without a new authentication | AUTH-02 | `P1 §11` AUTH-02 |
| BR-AUTH-9 | A stale session identifier must never re-authenticate a user | AUTH-02 | `P1 §19.8` AUTH-02 row |

## 9.2 Users

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-USR-1 | A user email must be unique across all accounts | USR-01 | `P1 §11` USR-01 · `P1 §19.1` |
| BR-USR-2 | A user role must be exactly one of `Admin`, `Sales`, `WarehouseStaff` | USR-01 | `P1 §11` USR-01 · `DEC-011` |
| BR-USR-3 | No account may be created except by an Admin — **public registration does not exist** | USR-01 | `P1 §11` USR-01 |
| BR-USR-4 | Non-Admin roles must be denied user administration **at the server**, on both pages and endpoints | USR-01 | `P1 §11` USR-01 · `P1 §19.4` |
| BR-USR-5 | A user account is deactivated, never deleted | USR-01 | `P1 §19.2` USR-01 (`Delete: No`) · `P1 §19.3` |

## 9.3 Products

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-PRD-1 | Product SKU must be unique | PRD-01 | `P1 §11` PRD-01 · `P1 §19.1` |
| BR-PRD-2 | Buy price, sell price and reorder point must be numeric and ≥ 0 | PRD-01 | `P1 §11` PRD-01 · `P1 §19.1` |
| BR-PRD-3 | A product referenced by any order must not be permanently deleted; it may only be deactivated | PRD-01 | `P1 §11` PRD-01 · `P1 §19.2` |
| BR-PRD-4 | An uploaded product image must pass type and size validation and be stored under a randomly generated, non-guessable filename | PRD-01 | `P1 §11` PRD-01 · `P1 §19.5` |
| BR-PRD-5 | A category must exist for a product to reference it | PRD-01 | `P1 §11` PRD-01 |
| BR-PRD-6 | A product carries no quantity of its own; quantity exists only per warehouse | WH-01 | `P1 §11` WH-01 · `P2 §07` T-04 |
| BR-PRD-7 | A product is **low stock** when its stock is below its reorder point, otherwise normal — one definition shared by the filter, the dashboard and the CLI job | FIND-01, DASH-01, JOB-01 | `P1 §11` FIND-01 · `P2 ADR-003` |

## 9.4 Warehouses

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-WH-1 | Stock is warehouse-specific: **exactly one `ProductStock` row per product per warehouse** | WH-01 | `P1 §11` WH-01 · `P2 §07` T-05 unique key |
| BR-WH-2 | A `ProductStock` quantity must never be negative | WH-01, ARCH-02, DB-01 | `P1 §11` WH-01 / ARCH-02 / DB-01 |
| BR-WH-3 | Total stock for a product is the sum of its per-warehouse quantities and **is never stored as an independent figure** | WH-01 | `P1 §11` WH-01 |
| BR-WH-4 | A warehouse is deactivated, never deleted | WH-01 | `P1 §19.2` WH-01 (`Delete: No`) |

## 9.5 Suppliers & Customers

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-MSTR-1 | A supplier or customer referenced by any order must not be permanently deleted; it may only be deactivated | MSTR-01 | `P1 §11` MSTR-01 |
| BR-MSTR-2 | An inactive supplier must not be selectable for a new Purchase Order | MSTR-01, PO-01 | `P1 §11` MSTR-01 |
| BR-MSTR-3 | An inactive customer must not be selectable for a new Sales Order | MSTR-01, SO-01 | `P1 §11` MSTR-01 |
| BR-MSTR-4 | Only Admin may create or modify supplier and customer records | MSTR-01 | `P1 §11` MSTR-01 · `P1 §19.4` |
| BR-MSTR-5 | Selectability is re-validated against the database inside the write path, so a stale cached list can never persist an inactive counterparty | MSTR-01 | `P2` MI-4 — design guarantee for BR-MSTR-2/3 |

## 9.6 Purchase Orders

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-PO-1 | A Purchase Order must reference **exactly one supplier and one destination warehouse** and carry **at least one item** | PO-01 | `P1 §11` PO-01 |
| BR-PO-2 | PO status must be one of `Draft`, `Ordered`, `PartiallyReceived`, `Received`, `Cancelled` | PO-01 | `P1 §11` PO-01 · SRC-001 §1.3 |
| BR-PO-3 | Item quantity and buy price must be ≥ 0 | PO-01 | `P1 §11` PO-01 · `P1 §19.1` |
| BR-PO-4 | Only Admin may transition a PO from `Draft` to `Ordered` | PO-01 | `P1 §19.3` PO-01 row |
| BR-PO-5 | Cancellation is permitted from `Draft`, `Ordered` and `PartiallyReceived`, by Admin | PO-01 | `P1 §19.3` PO-01 cancellation rows |
| BR-PO-6 | Cancelling a `PartiallyReceived` PO **does not reverse** already-received stock or ledger rows | PO-01 | `P1 §19.3` cancellation row condition |
| BR-PO-7 | Ordering a PO moves no stock | PO-01 | `P1 §19.3` — no stock row for the `Order` action |

## 9.7 Goods Receipt

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-GR-1 | A goods receipt must increase `ProductStock` for the ordered product **at the PO destination warehouse** | PO-01 | `P1 §11` PO-01 |
| BR-GR-2 | A goods receipt must write a `StockLedger` row of type `Receipt` **referencing the PO** | PO-01 | `P1 §11` PO-01 |
| BR-GR-3 | Both writes occur **inside one explicit database transaction**; if either fails, neither takes effect | PO-01, ARCH-02, DB-01 | `P1 §11` PO-01 / ARCH-02 · `P1 §19.6` |
| BR-GR-4 | A received quantity must not exceed the outstanding quantity of its PO line | PO-01 | `P1 §11` PO-01 · `P2 §07` T-09 `CHECK` |
| BR-GR-5 | Partial receipt is permitted; the outstanding quantity remains recorded until fully received | PO-01 | `P1 §11` PO-01 |
| BR-GR-6 | A PO becomes `Received` only when **every** line is fully received; otherwise `PartiallyReceived` | PO-01 | `P1 §11` PO-01 · `P1 §19.3` |
| BR-GR-7 | Received quantity is stored; outstanding is **computed** as ordered minus received and never stored | PO-01 | `P2 §07` T-09 note |

## 9.8 Sales Orders

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-SO-1 | A Sales Order must reference **exactly one customer and one source warehouse**, **record its creator**, and carry **at least one item** | SO-01 | `P1 §11` SO-01 |
| BR-SO-2 | SO status must be one of `Draft`, `PendingApproval`, `Approved`, `Fulfilled`, `Cancelled` | SO-01 | `P1 §11` SO-01 · SRC-001 §1.3 |
| BR-SO-3 | `Cancelled` is permitted at **any stage before `Fulfilled`** | SO-01 | `P1 §11` SO-01 · `P1 §19.3` |
| BR-SO-4 | Item quantity and sell price must be ≥ 0 | SO-01 | `P1 §11` SO-01 · `P1 §19.1` |
| BR-SO-5 | The creator is taken from the authenticated session, never from request input | SO-01 | `P1 §19.4` ownership rule · `P2` OW-1 |
| BR-SO-6 | Sales may submit or cancel only its **own** order; Admin is not subject to the ownership rule | SO-01 | `P1 §19.4` SO-01 ownership rule |
| BR-SO-7 | Warehouse Staff may not create, submit, approve, reject or cancel a Sales Order | SO-01 | `P1 §13`, `P1 §19.4` |
| BR-SO-8 | There is **no partial fulfilment** — SRC-001 §1.3 defines no partial-issue status | SO-01 | `P1 §11` SO-01 · `P2 §07` T-11 note |
| BR-SO-9 | No stock is reserved at any point before goods issue | SO-01 | `P2 §07.2` — Phase 1 defines no reservation |

## 9.9 Approval / Segregation of Duties

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-SOD-1 | **Only Admin may approve or reject a Sales Order** | SO-01 | `P1 §11` SO-01 · `P1 §19.3` (Admin only) |
| BR-SOD-2 | **Sales must be denied approval entirely, including on its own order** — the creator's identity is irrelevant to the decision | SO-01 | `P1 §11` SO-01 · `DEC-012` · `P1 §19.4` SoD-1 |
| BR-SOD-3 | The denial must be **enforced in the server authorization layer**. Hiding or disabling a UI control is **not** enforcement | SO-01 | `P1 §19.4` SoD-2 |
| BR-SOD-4 | A denied approval or rejection performs **no transition**: status unchanged, no approver recorded | SO-01, ERR-01 | `P1 §19.4` SoD-1 · `P1 §19.5` SoD Bypass |
| BR-SOD-5 | Approval is permitted only from status `PendingApproval` | SO-01 | `P1 §19.3` approve row |
| BR-SOD-6 | The approving user is recorded on the order | SO-01 | `P1 §19.3` approve row condition |
| BR-SOD-7 | Warehouse Staff is denied approval and rejection by the same rule | SO-01 | `P1 §19.4` forbidden roles |
| BR-SOD-8 | **A rejected Sales Order terminates at `Cancelled`** — per `DEC-009`, carried as the open mandatory assumption **`ASM-001`**. No status outside SRC-001 §1.3 is introduced | SO-01 | `P1 §11` SO-01 · `DEC-009` · **`ASM-001` OPEN** |

## 9.10 Goods Issue

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-GI-1 | Goods issue must be **refused unless the SO status is `Approved`** | SO-01 | `P1 §11` SO-01 |
| BR-GI-2 | Goods issue must be **refused when available stock in the source warehouse is insufficient** | SO-01 | `P1 §11` SO-01 |
| BR-GI-3 | Goods issue must decrease `ProductStock` for the product **at the SO source warehouse** | SO-01 | `P1 §11` SO-01 |
| BR-GI-4 | Goods issue must write a `StockLedger` row of type `Issue` **referencing the SO** | SO-01 | `P1 §11` SO-01 |
| BR-GI-5 | Both writes occur inside **one explicit transaction that is safe against concurrent execution** | SO-01, ARCH-02 | `P1 §11` SO-01 / ARCH-02 · `P1 §19.6`, `§19.7` |
| BR-GI-6 | `ProductStock` must never become negative and **no update may be lost** | SO-01, ARCH-02 | `P1 §11` SO-01 / ARCH-02 |
| BR-GI-7 | The sufficiency check is evaluated **inside** the transaction on a locked read; a pre-transaction check is advisory only | ARCH-02 | `P1 §19.7` ("validated inside the transaction, not before it") |
| BR-GI-8 | A successful issue transitions the SO to `Fulfilled` | SO-01 | `P1 §19.3` issue row |

## 9.11 Stock

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-STK-1 | `ProductStock` and `StockLedger` are **authoritative in MySQL**; neither Redis nor Memcached holds stock | DB-01, ARCH-02 | `P1 §07`, `§20`, `§21`, `§22` B-S8 · `DEC-004` |
| BR-STK-2 | Stock must never be negative under **any** interleaving of concurrent operations | ARCH-02 | `P1 §11` ARCH-02 · `P1 §22` B-S1 |
| BR-STK-3 | A `ProductStock` mutation and its `StockLedger` row are always written in one transaction | ARCH-02 | `P1 §11` ARCH-02 · `P1 §22` B-S2 |
| BR-STK-4 | Concurrent goods issues for the same product and warehouse must not oversell and must not lose an update | ARCH-02 | `P1 §11` ARCH-02 · `P1 §22` B-S4 |
| BR-STK-5 | Correctness rests on **database transaction and concurrency control**, not on cache infrastructure | ARCH-02 | `P1 §11` ARCH-02 · `P1 §19.7` |
| BR-STK-6 | Stock movement is a **consequence of an order state transition**, never a free-standing edit of a quantity field | PO-01, SO-01 | `P1 §22` B-S6 · `P1 §19.3` |
| BR-STK-7 | A failed stock mutation rolls back completely — no partial stock write, no orphan ledger row | ARCH-02 | `P1 §22` B-S7 · `P1 §19.6` |
| BR-STK-8 | Stock is mutated in exactly one place in the code; every other component delegates to it | ARCH-02, DB-01 | `P1 §08` "Direct Stock Mutation" · `P2` AI-5, SD-1…SD-7 |
| BR-STK-9 | `product_stock.quantity` must equal the sum of its signed `stock_ledger.quantity_change` rows | ARCH-02 | `P1 §19.7` consistency row · `P2 §09.5` |

## 9.12 Stock Ledger

| Rule ID | Rule | Requirement | Canonical owner |
|---|---|---|---|
| BR-LDG-1 | `StockLedger` is **append-only**: no `UPDATE`, no `DELETE` | PRD-01, PO-01, SO-01, DB-01 | `P1 §11` PRD-01 · `P1 §19.2` (`Update: No`, `Delete: No`) · `P1 §22` B-S3 |
| BR-LDG-2 | Movement type must be one of `Receipt`, `Issue`, `Adjustment` | DB-01 | `P1 §19.1` DB-01 · SRC-001 §1.3 |
| BR-LDG-3 | Every movement records the type, the quantity change, the reference and the acting user | DB-01 | `P1 §19.1` DB-01 reference row |
| BR-LDG-4 | A `Receipt` row references a Purchase Order; an `Issue` row references a Sales Order | PO-01, SO-01, DB-01 | `P1 §11` PO-01 / SO-01 · `P2 §07` T-12 `chk_ledger_reference` |
| BR-LDG-5 | Stock may be **increased only by a `Receipt`** and **decreased only by an `Issue`** | PO-01, SO-01 | `P1 §22` B-S5 |
| BR-LDG-6 | Every stock figure must be reconstructable from ledger rows | ARCH-02 | `P1 §19.7` consistency row |

## 9.13 Adjustment

| Rule ID | Rule | Requirement / Decision | Canonical owner |
|---|---|---|---|
| BR-ADJ-1 | `Adjustment` is a **retained `StockLedger.type` enum value**, because SRC-001 §1.3 fixes the enum | `DEC-010` | `P1 §04` `DEC-010` · `P1 §19.1` DB-01 |
| BR-ADJ-2 | **No adjustment workflow is in scope**: no requirement, no actor, no trigger, no authorization rule, and no state transition exists for it | `DEC-010` | `P1 §04` `DEC-010` · `P1 §19.3` ("No `Adjustment` transition row exists") |
| BR-ADJ-3 | Therefore **no adjustment service, controller, route, or repository operation is specified or built** | `DEC-010` | `P2 §09.4`, `P2 §04.1` |
| BR-ADJ-4 | `GAP-003` remains as Phase 1 left it. Any future adjustment feature requires a **new decision record in `phase1-baseline.md` §04 first** | `DEC-010`, `GAP-003` | `P1 §31.2` · `P2 §09.4` |

**Note on BR-ADJ.** These four are *scope boundary statements*, not a workflow. They exist so an
implementer cannot mistake the retained enum value for a licence to build a feature. `DEC-010` is a
scope decision, not a business rule, and `P1 §16` records it as such.

---

# 10. WORKFLOW & STATE SPECIFICATION

Ten workflows. Every transition comes from `P1 §19.3` (canonical owner of state transitions) and
every mechanism from the Phase 2 design. **No transition is created, removed, or altered.**

## 10.1 Login

| Field | Specification |
|---|---|
| **Start State** | No valid session, or one being replaced |
| **Actor** | Admin, Sales, Warehouse Staff |
| **Action** | Submit email and password |
| **Validation** | Email present and well-formed; password present (`P1 §19.1` AUTH-01) |
| **Authorization** | None — entry point. The **active-status check is a business rule**, not authorization (`P1 §19.4` AUTH-01) |
| **Transition** | No entity transition. Session state is created |
| **Side Effect** | Session record created with user id and role, TTL 3600 s; **session identifier regenerated**; login-attempt marker cleared |
| **Failure Condition** | Unknown email · wrong password · inactive account · session store unreachable → one indistinguishable failure, no session |
| **End State** | Authenticated session on a new identifier, or no session at all |
| **Requirement Reference** | AUTH-01 · `P1 §19.4`, `§19.5` |
| **Design Reference** | `P2 UC-A1`, C-01, `SEC-01`…`SEC-06`, `RD-1`, `RD-3` |

## 10.2 Logout

| Field | Specification |
|---|---|
| **Start State** | Authenticated session |
| **Actor** | Admin, Sales, Warehouse Staff |
| **Action** | Activate logout |
| **Validation** | None beyond the presence of a session |
| **Authorization** | Own session only (`P1 §19.4` AUTH-02) |
| **Transition** | No entity transition. Session state is destroyed |
| **Side Effect** | Session record deleted; cookie cleared; CSRF token invalidated with the session |
| **Failure Condition** | Store deletion failure → the session is still treated as terminated; a stale identifier must never re-authenticate |
| **End State** | No session; protected URLs re-guarded |
| **Requirement Reference** | AUTH-02 · `P1 §19.4` |
| **Design Reference** | `P2 UC-A2`, `SEC-07`, `SEC-08`, `RD-1` |

## 10.3 Master Data (User, Product, Category, Warehouse, Supplier, Customer)

One workflow shape applied to six entity families. Only Admin performs any of it.

| Field | Specification |
|---|---|
| **Start State** | Entity absent (create), or `active` / `inactive` (update, toggle) |
| **Actor** | **Admin only** for every write. Sales and Warehouse Staff have `VIEW ONLY` on Product/Category/Warehouse and `DENY` on User/Supplier/Customer (`P1 §13`) |
| **Action** | Create · Update · Deactivate · Activate |
| **Validation** | Per `P1 §19.1` for the entity: required fields, types, formats, ranges, enum membership, uniqueness (`users.email`, `products.sku`), foreign-key existence (`products.category_id`) |
| **Authorization** | `P1 §19.4` USR-01 / PRD-01 / MSTR-01 / WH-01 — asserted in the Service **before any write**; non-Admin receives 403 on both page and endpoint |
| **Transition** | `absent → active` (create) · `active → inactive` (deactivate) · `inactive → active` (activate) — the ten `P1 §19.3` rows for USR-01, PRD-01, MSTR-01 and WH-01 |
| **Side Effect** | Password hashed (User) · image validated and stored under a random name (Product) · **one `ProductStock` row provisioned per product per warehouse** (Product or Warehouse create) · read cache invalidated **after commit** |
| **Failure Condition** | Non-Admin → 403 · validation failure → rejected, nothing persisted · duplicate email or SKU → rejected · unknown FK → rejected · unknown record → 404 · **permanent deletion of a referenced record → refused, deactivation offered** |
| **End State** | Entity `active` or `inactive`. **No entity is ever deleted** — `P1 §19.2` records `Delete: No` for User, Product, Supplier, Customer and Warehouse |
| **Requirement Reference** | USR-01, PRD-01, MSTR-01, WH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.2`, `§19.3`, `§19.4` |
| **Design Reference** | `P2 UC-B1`, `UC-B2`, `UC-C1`…`UC-C3`, C-02…C-07, R-01…R-06, T-01…T-07, `MC-1`…`MC-5`, MI-1…MI-4 |

## 10.4 Purchase Order

| Field | Specification |
|---|---|
| **Start State** | `absent` → `Draft` → `Ordered` → `PartiallyReceived` / `Received`; `Cancelled` from `Draft`, `Ordered` or `PartiallyReceived` |
| **Actor** | Admin (create, order, cancel, receive); Warehouse Staff (propose, receive) |
| **Action** | Create Draft · Order · Cancel |
| **Validation** | Exactly one supplier and one destination warehouse; ≥ 1 item; item quantity and buy price numeric and ≥ 0; supplier active; products exist; for `Order`, status is exactly `Draft` |
| **Authorization** | `P1 §19.4` PO-01 — **Sales receives 403**. `Order` and `Cancel` are Admin-only per `P1 §19.3` |
| **Transition** | `Draft → Ordered` (Admin) · `Draft → Cancelled` · `Ordered → Cancelled` · `PartiallyReceived → Cancelled` (all Admin) |
| **Side Effect** | Header and items written as one unit; outstanding quantity initialised to ordered quantity. **No stock movement on any of these transitions** |
| **Failure Condition** | Sales requester → 403 · no items → rejected · inactive/unknown supplier, unknown warehouse or product → rejected · negative quantity or price → rejected · `Order` from a non-`Draft` status → rejected |
| **End State** | A PO in a valid status. Cancelling a `PartiallyReceived` PO **does not reverse** received stock or ledger rows (`P1 §19.3` row condition) |
| **Requirement Reference** | PO-01, MSTR-01, WH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.3`, `§19.4`, `§19.6` |
| **Design Reference** | `P2 UC-D1`, `UC-D2`, C-09, R-09, T-08, T-09, `§10.3` |

## 10.5 Goods Receipt

| Field | Specification |
|---|---|
| **Start State** | PO in `Ordered` or `PartiallyReceived` with at least one line outstanding |
| **Actor** | Warehouse Staff, Admin |
| **Action** | Submit per-line received quantities |
| **Validation** | **Inside the transaction, after a locked re-read**: PO is in a receivable status; each submitted line belongs to this PO; each received quantity > 0; each received quantity ≤ that line's outstanding quantity |
| **Authorization** | `P1 §19.4` PO-01 — asserted **before the transaction opens**; Sales → 403 with no transaction |
| **Transition** | `Ordered → PartiallyReceived` · `Ordered → Received` · `PartiallyReceived → PartiallyReceived` · `PartiallyReceived → Received` — the four `P1 §19.3` receipt rows. `Received` **only** when every line is fully received |
| **Side Effect** | Per line, **in one transaction**: `ProductStock` increased at the PO destination warehouse; a `Receipt` `StockLedger` row appended referencing the PO and recording the resulting quantity; `quantity_received` increased |
| **Failure Condition** | Non-authorised role → 403 · PO not receivable → rejected · received quantity ≤ 0 → rejected · **received quantity exceeds outstanding → rejected** · line not on this PO → rejected · any write failure or constraint violation → **full rollback: stock, ledger and received quantity all unchanged** |
| **End State** | Stock increased and fully explained by the ledger; outstanding reduced; PO status recomputed; `product_stock.quantity` still equals the sum of its signed ledger rows |
| **Requirement Reference** | PO-01, ARCH-02, DB-01, VAL-01, WH-01 · `P1 §19.3`, `§19.6`, `§19.7` |
| **Design Reference** | `P2 UC-D3`, C-10, **`TX-1`**, `CC-5`, SD-1…SD-7, T-09 `chk_poi_received_le_ordered` |

## 10.6 Sales Order

| Field | Specification |
|---|---|
| **Start State** | `absent` → `Draft` → `PendingApproval`; `Cancelled` at any stage before `Fulfilled` |
| **Actor** | Sales (create, submit own, cancel own); Admin (create, submit, cancel) |
| **Action** | Create Draft · Submit · Cancel |
| **Validation** | Exactly one customer and one source warehouse; ≥ 1 item; item quantity and sell price numeric and ≥ 0; customer active; for `Submit`, status is exactly `Draft` |
| **Authorization** | `P1 §19.4` SO-01 — Warehouse Staff → 403. **Sales may act only on its own Sales Orders**, evaluated against the stored creator, never a request parameter |
| **Transition** | `Draft → PendingApproval` (Sales own, Admin) · `Draft → Cancelled` (Sales own, Admin) · `PendingApproval → Cancelled` (Sales own, Admin) · `Approved → Cancelled` (**Admin only**, before `Fulfilled`) |
| **Side Effect** | Header and items written as one unit; **creator recorded from the session**. The `Approved → Cancelled` row releases any reservation — and since Phase 1 defines none, there is nothing to release |
| **Failure Condition** | Warehouse Staff → 403 · Sales acting on another user's order → 403 · no items → rejected · inactive/unknown customer, unknown warehouse or product → rejected · negative quantity or price → rejected · `Submit` from a non-`Draft` status → rejected |
| **End State** | An SO in a valid status. **No stock has moved and none is reserved** |
| **Requirement Reference** | SO-01, MSTR-01, WH-01, VAL-01, ERR-01 · `P1 §19.1`, `§19.3`, `§19.4` |
| **Design Reference** | `P2 UC-E1`, `UC-E2`, C-11, R-10, T-10, T-11, OW-1…OW-5 |

## 10.7 Approval and Rejection

Both actions share one authorization assertion and one decision point. **`ASM-001` is referenced
explicitly and remains OPEN and MANDATORY.**

### 10.7.1 Approval

| Field | Specification |
|---|---|
| **Start State** | SO in `PendingApproval` |
| **Actor** | **Admin only** |
| **Action** | Approve |
| **Validation** | Status is exactly `PendingApproval` — checked **after** the role assertion |
| **Authorization** | `P1 §19.4` SO-01, **SoD-1** and **SoD-2**. The role assertion is the **first action taken**, before any read-for-write, any transaction, and any state change. **Sales is denied unconditionally — the creator's identity is never consulted** (`DEC-012`). Warehouse Staff is denied by the same assertion. Enforcement is in the server authorization layer; **hiding the UI control is not enforcement** |
| **Transition** | `PendingApproval → Approved` (`P1 §19.3` SO-01 approve row, **Admin only**) |
| **Side Effect** | Approver user id and approval timestamp recorded on the order |
| **Failure Condition** | **Sales requester → 403, no transition, status unchanged, approver unset — including on the requester's own order** · Warehouse Staff → 403 · status not `PendingApproval` → rejected · unknown SO → 404 |
| **End State** | `Approved` and issuable, with the approver attributable; or unchanged with a 403 |
| **Requirement Reference** | SO-01 (SoD-1, SoD-2), ERR-01 · `P1 §19.3`, `§19.4`, `§19.5`, `§19.14` CF-8 |
| **Design Reference** | `P2 UC-E3`, C-12, `§12.5` SoD-A…SoD-I |

### 10.7.2 Rejection

| Field | Specification |
|---|---|
| **Start State** | SO in `PendingApproval` |
| **Actor** | **Admin only** |
| **Action** | Reject, optionally with a reason |
| **Validation** | Status is exactly `PendingApproval` — checked after the role assertion |
| **Authorization** | **Identical assertion, in the identical position, as 10.7.1.** Sales and Warehouse Staff are denied; ownership is irrelevant |
| **Transition** | `PendingApproval → Cancelled` — the `P1 §19.3` SO-01 reject row, whose condition cites `DEC-009` and **`ASM-001`**. **The rejection terminal status is the subject of `ASM-001`, which is OPEN and MANDATORY.** It is resolved from a single definition point, so no other specification statement depends on the literal value |
| **Side Effect** | Deciding user recorded; optional reason stored. The stored reason **carries no lifecycle meaning** and participates in no transition condition |
| **Failure Condition** | Sales requester → 403 with no transition · Warehouse Staff → 403 · status not `PendingApproval` → rejected · unknown SO → 404 |
| **End State** | The order is terminated at the status `ASM-001` covers. **No `Rejected` status value exists or is created** — the SO enum is exactly the five SRC-001 §1.3 values |
| **Requirement Reference** | SO-01, ERR-01 · `DEC-009` · **`ASM-001` — OPEN / MANDATORY** · `P1 §19.3`, `§19.4` |
| **Design Reference** | `P2 UC-E4`, C-12, `§12.6` AS-1…AS-4, R-10 `recordRejection()`, T-10 |

**`ASM-001` handling rule for implementation.** The terminal status must be obtained from the single
definition point the design provides, never written as a literal at each call site. If the trainer
subsequently specifies a distinct rejection state, the change is: one enum value, one return value
at that definition point, one `P1 §19.3` transition row, one UI badge — and **that change is a
Phase 1 governance action** (a decision record in `phase1-baseline.md` §04), not an implementer's
choice. Until then the assumption stays **OPEN**; nothing in this document resolves it, renames it,
reinterprets it, or hides it.

## 10.8 Goods Issue

| Field | Specification |
|---|---|
| **Start State** | SO in `Approved` |
| **Actor** | Warehouse Staff, Admin |
| **Action** | Issue goods |
| **Validation** | **Inside the transaction**: SO status is `Approved`, asserted on a **locked re-read**; per line, `current stock − requested >= 0`, asserted on a **`FOR UPDATE`** read of the `ProductStock` row for (product, SO source warehouse). A pre-transaction availability check is **advisory only** and never substitutes for this |
| **Authorization** | `P1 §19.4` SO-01 — asserted **before the transaction opens**; Sales → 403 with no transaction |
| **Transition** | `Approved → Fulfilled` (`P1 §19.3` SO-01 issue row: available stock sufficient, race-safe transaction committed) |
| **Side Effect** | Per line, **in one transaction**: `ProductStock` decreased as a **relative delta**; an `Issue` `StockLedger` row appended referencing the SO and recording the resulting quantity. Multi-line locks acquired in ascending `product_id` then `warehouse_id` order |
| **Failure Condition** | Non-authorised role → 403 · SO not `Approved` → refused · **insufficient stock on any line → the entire issue refused and rolled back; there is no partial fulfilment** · a competing concurrent issue that exhausted the stock first → refused · `quantity >= 0` constraint violation → rollback · deadlock → explicit retryable failure, **no automatic retry** · any write failure → rollback |
| **End State** | Stock decreased and fully explained by the ledger; SO `Fulfilled`; **no negative stock, no oversell, no lost update**; `product_stock.quantity` equals the sum of its signed ledger rows |
| **Requirement Reference** | SO-01, ARCH-02, DB-01, WH-01, ERR-01 · `P1 §19.3`, `§19.6`, `§19.7`, `§19.14` CF-6/CF-7 |
| **Design Reference** | `P2 UC-E5`, C-13, **`TX-2`**, `CC-1`, `CC-2`, `CC-3`, `§11.2`, **`ADR-002`**, T-05 `CHECK` |

## 10.9 Stock Movement

The only two movement paths, stated together so the invariants are visible at once.

| Field | Specification |
|---|---|
| **Start State** | `ProductStock.quantity = n` for a (product, warehouse) pair |
| **Actor** | Warehouse Staff, Admin — **never a direct actor on stock**; a movement is always a consequence of a goods receipt or a goods issue |
| **Action** | Receipt commit (increase) · Issue commit (decrease) |
| **Validation** | Increase: bounded by the PO line's outstanding quantity. Decrease: `n − issued >= 0`, on a locked read inside the transaction |
| **Authorization** | Inherited from the owning operation (§10.5, §10.8). Stock has no authorization surface of its own |
| **Transition** | `quantity = n → n + received` · `quantity = n → n − issued` · `StockLedger (absent) → Receipt row present` · `StockLedger (absent) → Issue row present` — the four `P1 §19.3` ARCH-02 rows |
| **Side Effect** | The paired ledger append, in the same transaction, recording the resulting quantity |
| **Failure Condition** | Either write failing → **neither takes effect** · a decrease that would go negative → refused, with the database `CHECK` as a backstop · a concurrent write → serialised by the row lock and applied as a delta, so no update is lost |
| **End State** | A quantity that is non-negative and equal to the sum of its signed ledger movements |
| **Requirement Reference** | ARCH-02, PO-01, SO-01, DB-01, WH-01 · `P1 §19.3`, `§19.6`, `§19.7`, `§22` B-S1…B-S8 |
| **Design Reference** | `P2 §09` SD-1…SD-7, `TX-1`, `TX-2`, `CC-1`…`CC-3`, `§09.5` reconciliation |

**`Adjustment` has no workflow.** `P1 §19.3` records that no `Adjustment` transition row exists, and
`DEC-010` places no adjustment workflow in scope. The enum value is retained; nothing writes it
(§14.3, BR-ADJ-1…4).

## 10.10 State Transition Summary

Reproduced from `P1 §19.3` for implementer convenience. **`P1 §19.3` remains the canonical owner**;
nothing here is added.

```text
User / Product / Supplier / Customer / Warehouse
    active  <->  inactive                                       [Admin]

PurchaseOrder
    Draft --Order--> Ordered                                    [Admin]
    Ordered --receipt(partial)--> PartiallyReceived             [WarehouseStaff, Admin]
    Ordered --receipt(full)--> Received                         [WarehouseStaff, Admin]
    PartiallyReceived --receipt(partial)--> PartiallyReceived    [WarehouseStaff, Admin]
    PartiallyReceived --receipt(full)--> Received               [WarehouseStaff, Admin]
    Draft | Ordered | PartiallyReceived --Cancel--> Cancelled   [Admin]

SalesOrder
    Draft --Submit--> PendingApproval                           [Sales(own), Admin]
    PendingApproval --Approve--> Approved                       [ADMIN ONLY]
    PendingApproval --Reject--> Cancelled                       [ADMIN ONLY]  (DEC-009, ASM-001 OPEN)
    Approved --Issue goods--> Fulfilled                         [WarehouseStaff, Admin]
    Draft | PendingApproval --Cancel--> Cancelled               [Sales(own), Admin]
    Approved --Cancel--> Cancelled                              [Admin]

ProductStock / StockLedger                                      [inside one transaction]
    quantity n --Receipt commit--> n + received
    quantity n --Issue commit----> n - issued        (n - issued >= 0, race-safe)
    (absent)   --Append Receipt--> Receipt row present
    (absent)   --Append Issue----> Issue row present

NO Rejected status.   NO Adjustment transition.   NO partial-issue status.
```

---

# 11. DATA SPECIFICATION

Twelve entities — the SRC-001 §1.3 minimum set. Physical design is owned by `P2 §07`; this section
states the **business meaning and lifecycle** of each. **No speculative field is introduced.**

## 11.0 Requirement vs Design Detail vs Design Gap

Phase 2 contains design elements with no Phase 1 origin. The distinction is preserved and **no
design detail is promoted to a product requirement**:

| Element | Classification | Basis |
|---|---|---|
| Every field named in `P1 §19.1` | **Requirement** | Canonical input matrix |
| Every entity, key, FK, unique key, index and `CHECK` in `P2 §07` | **Design Detail** implementing DB-01 | `DB-01` mandates PKs, FKs, constraints and indexes; the specific set is design |
| `stock_ledger.quantity_after` | **Design Gap `DG-02`** — declared design addition, verification aid | `P2 §24`; not a business field |
| `sales_orders.rejection_reason` | **Design Gap `DG-03`** — declared design addition, no lifecycle meaning | `P2 §24` |
| `po_number`, `so_number` | **Design Detail** — human-readable identifiers implementing the `FIND-01` order search by number | `P1 §11` FIND-01 |
| `created_at` / `updated_at` | **Design Detail** — no requirement names them; they carry no business rule | `P2 §07` conventions |

## 11.1 `User`

| Field | Specification |
|---|---|
| **Purpose** | Authentication identity and role assignment |
| **Important Fields** | `name`, `email`, `password_hash`, `role`, `is_active` |
| **Business Meaning** | A person who may sign in. `role` decides every capability; `is_active` decides whether authentication is permitted at all. `password_hash` holds only a `password_hash()` digest — **there is no plaintext column** |
| **Relationships** | 1:N `SalesOrder.created_by` · 1:N `SalesOrder.approved_by` · 1:N `PurchaseOrder.created_by` · 1:N `StockLedger.created_by` |
| **Constraints** | `email` unique · `role` ∈ `{Admin, Sales, WarehouseStaff}` · `password_hash` NOT NULL · `is_active` NOT NULL |
| **Lifecycle** | `absent → active → inactive → active …` **Never deleted** |
| **Phase 1 References** | AUTH-01, AUTH-02, USR-01, SO-01 · `P1 §19.1`, `§19.2`, `§19.3` · `DEC-011` |
| **Phase 2 References** | T-01, R-01, C-01, C-02 |

## 11.2 `Warehouse`

| Field | Specification |
|---|---|
| **Purpose** | Stock location identity |
| **Important Fields** | `code`, `name`, `address`, `is_active` |
| **Business Meaning** | A physical location that holds stock. Every product has a stock row at every warehouse. A PO names its **destination** warehouse; an SO names its **source** warehouse |
| **Relationships** | 1:N `ProductStock` · 1:N `PurchaseOrder.destination_warehouse_id` · 1:N `SalesOrder.source_warehouse_id` · 1:N `StockLedger` |
| **Constraints** | `code` unique · `is_active` NOT NULL |
| **Lifecycle** | `absent → active → inactive → active …` **Never deleted** |
| **Phase 1 References** | WH-01, PO-01, SO-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3` |
| **Phase 2 References** | T-02, R-04, C-05, `MC-3` |

## 11.3 `Category`

| Field | Specification |
|---|---|
| **Purpose** | Product classification used by `PRD-01` and the `FIND-01` category filter |
| **Important Fields** | `name`, `is_active` |
| **Business Meaning** | The classification a product must reference; also the vocabulary of the product-list category filter |
| **Relationships** | 1:N `Product` |
| **Constraints** | `name` unique and NOT NULL |
| **Lifecycle** | `absent → active → inactive`. `P1 §19.2` records create and update but **no deactivate row for Category**; the design provides `is_active` as a **Design Detail** for filter hygiene and it carries no business rule |
| **Phase 1 References** | PRD-01, FIND-01 · `P1 §19.1`, `§19.2` |
| **Phase 2 References** | T-03, R-03, C-04, `MC-2` |

## 11.4 `Product`

| Field | Specification |
|---|---|
| **Purpose** | The catalogue item every transaction flow references |
| **Important Fields** | `sku`, `name`, `category_id`, `unit`, `buy_price`, `sell_price`, `reorder_point`, `image_path`, `is_active` |
| **Business Meaning** | What is bought and sold. `reorder_point` is the threshold that defines "low stock". `buy_price` values inventory. **The product holds no quantity** — quantity exists only per warehouse |
| **Relationships** | N:1 `Category` · 1:N `ProductStock` · 1:N `PurchaseOrderItem` · 1:N `SalesOrderItem` · 1:N `StockLedger` |
| **Constraints** | `sku` unique · `buy_price >= 0` · `sell_price >= 0` · `reorder_point >= 0` · `category_id` NOT NULL |
| **Lifecycle** | `absent → active → inactive → active …` **Never deleted**, and deactivation is permitted even when referenced by an order |
| **Phase 1 References** | PRD-01, FIND-01, VIEW-01, API-01, JOB-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3` |
| **Phase 2 References** | T-04, R-02, C-03, `MC-1`, `SEC-15`, `SEC-20` |

## 11.5 `ProductStock`

| Field | Specification |
|---|---|
| **Purpose** | **The authoritative per-product-per-warehouse quantity** |
| **Important Fields** | `product_id`, `warehouse_id`, `quantity` |
| **Business Meaning** | The single answer to "how much of this product is at this warehouse". It is the figure a goods issue is validated against and the figure the API reports. **Total stock for a product is the sum of these rows and is never stored** |
| **Relationships** | N:1 `Product` · N:1 `Warehouse` |
| **Constraints** | **`quantity >= 0`** (database `CHECK`) · **unique `(product_id, warehouse_id)`** · `quantity` NOT NULL default 0 |
| **Lifecycle** | Row provisioned when a product or a warehouse is created; `quantity` changes **only** through a committed goods receipt or goods issue, as a relative delta, under a row lock. The row is never deleted |
| **Phase 1 References** | WH-01, PRD-01, PO-01, SO-01, ARCH-02, DB-01, API-01, JOB-01 · `P1 §19.1`, `§19.2`, `§19.3`, `§19.7` |
| **Phase 2 References** | T-05, R-07 (**the two-implementation interface**), C-08, `TX-1`, `TX-2`, `CC-1`, `CC-2`, `CC-4` |

## 11.6 `Supplier`

| Field | Specification |
|---|---|
| **Purpose** | Purchase counterparty |
| **Important Fields** | `name`, `contact`, `address`, `is_active` |
| **Business Meaning** | Who goods are bought from. An **inactive** supplier is not selectable for a new PO but remains attached to historic POs |
| **Relationships** | 1:N `PurchaseOrder` |
| **Constraints** | `name` unique and NOT NULL · `is_active` NOT NULL |
| **Lifecycle** | `absent → active → inactive → active …` **Never deleted** |
| **Phase 1 References** | MSTR-01, PO-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3` · `DEC-015` |
| **Phase 2 References** | T-06, R-05, C-06, `MC-4`, MI-4 |

## 11.7 `Customer`

| Field | Specification |
|---|---|
| **Purpose** | Sales counterparty |
| **Important Fields** | `name`, `contact`, `address`, `is_active` |
| **Business Meaning** | Who goods are sold to. An **inactive** customer is not selectable for a new SO but remains attached to historic SOs |
| **Relationships** | 1:N `SalesOrder` |
| **Constraints** | `name` unique and NOT NULL · `is_active` NOT NULL |
| **Lifecycle** | `absent → active → inactive → active …` **Never deleted** |
| **Phase 1 References** | MSTR-01, SO-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3` · `DEC-015` |
| **Phase 2 References** | T-07, R-06, C-07, `MC-5`, MI-4 |

## 11.8 `PurchaseOrder`

| Field | Specification |
|---|---|
| **Purpose** | Purchase order header and lifecycle state |
| **Important Fields** | `po_number`, `supplier_id`, `destination_warehouse_id`, `created_by`, `status`, `order_date` |
| **Business Meaning** | A commitment to buy from one supplier, delivered into one warehouse. `status` records how far the order has progressed toward full receipt |
| **Relationships** | N:1 `Supplier`, `Warehouse`, `User` · 1:N `PurchaseOrderItem` (**at least one**) · 1:N `StockLedger` (as `Receipt` reference) |
| **Constraints** | `po_number` unique · `status` ∈ `{Draft, Ordered, PartiallyReceived, Received, Cancelled}` · `supplier_id`, `destination_warehouse_id` NOT NULL |
| **Lifecycle** | `Draft → Ordered → PartiallyReceived ⇄ → Received`; `Cancelled` from `Draft`, `Ordered` or `PartiallyReceived`. Never deleted |
| **Phase 1 References** | PO-01, VIEW-01, FIND-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3` |
| **Phase 2 References** | T-08, R-09, C-09, C-10, `TX-1` |

## 11.9 `PurchaseOrderItem`

| Field | Specification |
|---|---|
| **Purpose** | PO lines with ordered and received quantities — the outstanding-quantity basis |
| **Important Fields** | `purchase_order_id`, `product_id`, `quantity_ordered`, `quantity_received`, `buy_price` |
| **Business Meaning** | What was ordered, at what price, and how much of it has arrived. **Outstanding = ordered − received**, computed rather than stored, so it cannot drift |
| **Relationships** | N:1 `PurchaseOrder` (part of its aggregate) · N:1 `Product` |
| **Constraints** | `quantity_ordered >= 0` · `quantity_received >= 0` · **`quantity_received <= quantity_ordered`** · `buy_price >= 0` · unique `(purchase_order_id, product_id)` |
| **Lifecycle** | Created with its order; `quantity_received` increases only inside a committed goods receipt; deleted only with its order aggregate |
| **Phase 1 References** | PO-01, VAL-01, DB-01 · `P1 §19.1`, `§19.2` |
| **Phase 2 References** | T-09, R-09 (no separate item repository), `TX-1` |

## 11.10 `SalesOrder`

| Field | Specification |
|---|---|
| **Purpose** | Sales order header, lifecycle state, and the identities segregation of duties depends on |
| **Important Fields** | `so_number`, `customer_id`, `source_warehouse_id`, `created_by`, `approved_by`, `status`, `order_date`, `approved_at`, `rejection_reason` |
| **Business Meaning** | A commitment to sell to one customer from one warehouse. `created_by` is the ownership basis for the Sales own-order rule; `approved_by` is the approval attribution SoD evidence depends on |
| **Relationships** | N:1 `Customer`, `Warehouse` · N:1 `User` **twice** (`created_by` mandatory, `approved_by` nullable) · 1:N `SalesOrderItem` (**at least one**) · 1:N `StockLedger` (as `Issue` reference) |
| **Constraints** | `so_number` unique · `status` ∈ `{Draft, PendingApproval, Approved, Fulfilled, Cancelled}` — **there is no `Rejected` value** · `customer_id`, `source_warehouse_id`, `created_by` NOT NULL · `approved_by` NULL until a decision is recorded |
| **Lifecycle** | `Draft → PendingApproval → Approved → Fulfilled`; `Cancelled` at any stage before `Fulfilled`; a rejection terminates at the status **`ASM-001`** covers, obtained from one definition point. Never deleted |
| **Phase 1 References** | SO-01, VIEW-01, FIND-01, DASH-01, DB-01 · `P1 §19.1`, `§19.2`, `§19.3`, `§19.4` SoD-1/SoD-2 · `DEC-009`, `DEC-012` · **`ASM-001` OPEN** |
| **Phase 2 References** | T-10, R-10, C-11, C-12, C-13, `TX-2`, `§12.5`, `§12.6` |

## 11.11 `SalesOrderItem`

| Field | Specification |
|---|---|
| **Purpose** | SO lines |
| **Important Fields** | `sales_order_id`, `product_id`, `quantity`, `sell_price` |
| **Business Meaning** | What is being sold, at what price. There is **no `quantity_issued` column**, because SRC-001 §1.3 defines no partial-issue status — an order is fulfilled entirely or not at all |
| **Relationships** | N:1 `SalesOrder` (part of its aggregate) · N:1 `Product` |
| **Constraints** | `quantity >= 0` · `sell_price >= 0` · unique `(sales_order_id, product_id)` |
| **Lifecycle** | Created with its order; unchanged by the issue; deleted only with its order aggregate |
| **Phase 1 References** | SO-01, VAL-01, DB-01 · `P1 §19.1`, `§19.2` |
| **Phase 2 References** | T-11, R-10 (no separate item repository), `TX-2` |

## 11.12 `StockLedger`

| Field | Specification |
|---|---|
| **Purpose** | **Append-only** record of every stock movement; the reconstruction basis for every stock figure and the source of the stock-movement CSV |
| **Important Fields** | `product_id`, `warehouse_id`, `type`, `quantity_change`, `quantity_after`, `purchase_order_id`, `sales_order_id`, `created_by`, `moved_at` |
| **Business Meaning** | The explanation of every quantity. `quantity_change` is **signed** — positive for `Receipt`, negative for `Issue` — so `SUM(quantity_change)` must equal `ProductStock.quantity`. Each row names the order that caused it and the user who recorded it |
| **Relationships** | N:1 `Product`, `Warehouse`, `User` · N:1 `PurchaseOrder` (optional) · N:1 `SalesOrder` (optional) |
| **Constraints** | `type` ∈ `{Receipt, Issue, Adjustment}` · `quantity_after >= 0` · `quantity_change <> 0` · a `Receipt` row carries a PO reference and no SO reference; an `Issue` row carries an SO reference and no PO reference; an `Adjustment` row carries neither |
| **Lifecycle** | **Insert only.** No `UPDATE`, no `DELETE` — enforced by the repository contract exposing neither, by the absence of any code path, and by `P1 §19.2` recording `Update: No` / `Delete: No`. `Adjustment` is a permitted value with **no writing code path** |
| **Phase 1 References** | PRD-01, PO-01, SO-01, ARCH-02, REPORT-01, DB-01, VIEW-01 · `P1 §19.1`, `§19.2`, `§19.3`, `§19.7` · `DEC-010` |
| **Phase 2 References** | T-12, R-08, C-14, `TX-1`, `TX-2`, `§09.5` reconciliation · `DG-02` for `quantity_after` |

---

# 12. VALIDATION SPECIFICATION

**Canonical detailed source: `P1 §19.1` INPUT MATRIX.** This section states the layer at which each
rule is applied and the failure behaviour. It adds no field and changes no rule.

## 12.1 Layer Responsibilities

| Layer | Role | Authority |
|---|---|---|
| **Frontend** | Convenience only — `required`, `type`, `min`, `pattern`, immediate feedback | **None.** A frontend pass never substitutes for the backend pass |
| **Backend (Service)** | **Authoritative.** Required fields, types, formats, ranges, enum membership, uniqueness, foreign-key existence, business-state preconditions | **Source of truth** (`VAL-01`) |
| **Database** | Final backstop — PK, FK, unique keys, `CHECK` constraints | Catches what must never happen; a constraint violation is a defect signal, not the primary control |

## 12.2 Global Failure Behaviour

| # | Rule |
|---|---|
| VF-1 | On any validation failure **nothing is persisted and no transaction is opened** |
| VF-2 | Field-level feedback is returned for each failing field |
| VF-3 | **Already-entered input is preserved** where relevant to the correction |
| VF-4 | A uniqueness race that slips past the Service check is caught by the database unique key and surfaced as the same field-level failure |
| VF-5 | Domain-state validation that depends on a locked read (available stock, outstanding quantity) runs **inside** the transaction |
| VF-6 | Validation messages never disclose internals — no SQL, no constraint name, no stack trace |

## 12.3 Field Records

Layers: `F` frontend · `B` backend (authoritative) · `D` database.

| Requirement | Field | Required | Type | Format | Range | Enum | Unique | FK | Layers | Failure Behaviour |
|---|---|---|---|---|---|---|---|---|---|---|
| AUTH-01 | email | Yes | string | email address | — | — | matches `User.email` | User | F,B | Generic auth failure, no field-level disclosure (`SEC-03`) |
| AUTH-01 | password | Yes | string | verified against hash | — | — | No | — | F,B | Generic auth failure |
| USR-01 | name | Yes | string | free text | — | — | No | — | F,B | Field error |
| USR-01 | email | Yes | string | email address | — | — | **Yes** | — | F,B,D | Field error "already in use"; nothing stored |
| USR-01 | password | Yes (create) | string | stored hashed | — | — | No | — | F,B | Field error |
| USR-01 | role | Yes | enum | — | — | `Admin`, `Sales`, `WarehouseStaff` | No | — | F,B,D | Rejected; invalid role never stored |
| USR-01 | is_active | Yes | boolean | — | — | true, false | No | — | F,B,D | Rejected |
| PRD-01 | sku | Yes | string | free text | — | — | **Yes** | — | F,B,D | Field error "SKU already exists" |
| PRD-01 | name | Yes | string | free text | — | — | No | — | F,B | Field error |
| PRD-01 | category_id | Yes | integer | — | — | — | No | **Category** | F,B,D | Rejected — unknown category |
| PRD-01 | unit | Yes | string | free text | — | — | No | — | F,B | Field error |
| PRD-01 | buy_price | Yes | decimal | numeric | **≥ 0** | — | No | — | F,B,D | Field error; negative never stored |
| PRD-01 | sell_price | Yes | decimal | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| PRD-01 | reorder_point | Yes | integer | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| PRD-01 | image | No | file | validated type | validated size | — | No | — | F,B | Rejected with feedback; nothing stored, no file kept |
| PRD-01 | is_active | Yes | boolean | — | — | true, false | No | — | F,B,D | Rejected |
| PRD-01 | category.name | Yes | string | free text | — | — | Yes (design) | — | F,B,D | Field error |
| PRD-01 | category.description | No | string | free text | — | — | No | — | F,B | — |
| MSTR-01 | name | Yes | string | free text | — | — | Yes (design) | — | F,B,D | Field error |
| MSTR-01 | contact | Yes | string | free text | — | — | No | — | F,B | Field error |
| MSTR-01 | address | Yes | string | free text | — | — | No | — | F,B | Field error |
| MSTR-01 | is_active | Yes | boolean | — | — | true, false | No | — | F,B,D | Rejected |
| WH-01 | name | Yes | string | free text | — | — | No | — | F,B | Field error |
| WH-01 | location | Yes | string | free text | — | — | No | — | F,B | Field error |
| WH-01 | is_active | Yes | boolean | — | — | true, false | No | — | F,B,D | Rejected |
| PO-01 | supplier_id | Yes | integer | — | — | — | No | **Supplier** | F,B,D | Rejected — unknown or **inactive** supplier |
| PO-01 | warehouse_id | Yes | integer | — | — | — | No | **Warehouse** | F,B,D | Rejected |
| PO-01 | order_date | Yes | date | valid date | — | — | No | — | F,B | Rejected — invalid date |
| PO-01 | status | Yes | enum | — | — | `Draft`, `Ordered`, `PartiallyReceived`, `Received`, `Cancelled` | No | — | B,D | Rejected; set only by a guarded transition, never by direct input |
| PO-01 | item.product_id | Yes | integer | — | — | — | No | **Product** | F,B,D | Rejected |
| PO-01 | item.quantity | Yes | integer | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| PO-01 | item.buy_price | Yes | decimal | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| PO-01 | receipt.quantity | Yes | integer | numeric | **0 ≤ q ≤ outstanding** | — | No | — | F,B,D | **Validated inside the transaction on a locked read**; over-receipt rejected and rolled back |
| SO-01 | customer_id | Yes | integer | — | — | — | No | **Customer** | F,B,D | Rejected — unknown or **inactive** customer |
| SO-01 | warehouse_id | Yes | integer | — | — | — | No | **Warehouse** | F,B,D | Rejected |
| SO-01 | created_by | Yes | integer | — | — | — | No | **User** | B,D | **Taken from the session, never from request input**; a supplied value is ignored |
| SO-01 | approved_by | No (set on approval) | integer | — | — | — | No | **User** | B,D | Set only by a guarded approval or rejection |
| SO-01 | status | Yes | enum | — | — | `Draft`, `PendingApproval`, `Approved`, `Fulfilled`, `Cancelled` | No | — | B,D | Rejected; set only by a guarded transition. **No `Rejected` value exists** |
| SO-01 | item.product_id | Yes | integer | — | — | — | No | **Product** | F,B,D | Rejected |
| SO-01 | item.quantity | Yes | integer | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| SO-01 | item.sell_price | Yes | decimal | numeric | **≥ 0** | — | No | — | F,B,D | Field error |
| SO-01 | issue.quantity | Yes | integer | numeric | **0 < q ≤ available stock** | — | No | — | B,D | **Validated inside the transaction on a `FOR UPDATE` read**; insufficient stock refuses the whole issue |
| VIEW-01 | record id (detail) | Yes | integer | — | — | — | No | owning entity | B | Unknown or out-of-scope → **404** |
| FIND-01 | search term | No | string | free text | — | — | No | — | B | Bound as a parameter; never concatenated |
| FIND-01 | category filter | No | integer | — | — | — | No | **Category** | F,B | Unknown value → treated as no filter |
| FIND-01 | stock status filter | No | enum | — | — | low stock, normal | No | — | F,B | Unknown value → no filter |
| FIND-01 | order status filter | No | enum | — | — | PO or SO status set | No | — | F,B | Unknown value → no filter |
| FIND-01 | date sort | No | enum | — | — | asc, desc | No | — | F,B | Outside the set → default direction; **column and direction come from a closed allow-list** |
| FIND-01 | page | No | integer | numeric | **≥ 1** | — | No | — | F,B | Out of range → clamped to a valid page |
| REPORT-01 | report type | Yes | enum | — | — | stock movement, order status | No | — | F,B | Rejected |
| REPORT-01 | date_from | Yes | date | valid date | **≤ date_to** | — | No | — | F,B | Rejected; nothing streamed |
| REPORT-01 | date_to | Yes | date | valid date | **≥ date_from** | — | No | — | F,B | Rejected; nothing streamed |
| API-01 | sku (path parameter) | Yes | string | free text | — | — | No | **Product** | B | Unknown or malformed → **404 JSON** |
| DB-01 | ProductStock.quantity | Yes | integer | numeric | **≥ 0 enforced by constraint** | — | composite unique (product+warehouse) | Product, Warehouse | B,**D** | Database rejects a negative write |
| DB-01 | StockLedger.movement_type | Yes | enum | — | — | `Receipt`, `Issue`, `Adjustment` | No | — | B,D | Rejected |
| DB-01 | StockLedger.reference | Yes | composite | reference type + id | — | PO, SO | No | PurchaseOrder / SalesOrder | B,D | Rejected — a `Receipt` without a PO or an `Issue` without an SO |
| ENV-01 | environment variables | Yes | key–value | per `.env` | — | — | No | — | B | Missing configuration → startup failure with a safe message |

**61 field records.** Requirements with no user-supplied input field, per `P1 §19.1`: AUTH-02,
DASH-01, VAL-01 (owns the rule, not the fields), ERR-01, UI-01, JOB-01, ARCH-01, ARCH-02, TEST-01,
TEST-02, TEST-03, DESIGN-01…DESIGN-04.

---

# 13. AUTHORIZATION & SoD SPECIFICATION

Canonical owner: `P1 §19.4`. Design: `P2 §12`. Nothing here weakens, widens, or reinterprets a rule.

## 13.1 Authentication Boundary

| # | Specification |
|---|---|
| AS-1 | Every protected route resolves the session **before** a Controller action runs; there is no route that reaches a Controller without traversing the boundary |
| AS-2 | The boundary yields an immutable identity value carrying **only** user id and role, built from **server-side session state, never from request input** |
| AS-3 | No valid session → HTML **redirect to login**, API **401 JSON** |
| AS-4 | Session store unreachable → **fail closed**, treated as unauthenticated. There is no authenticated-by-default path |
| AS-5 | Role is never client-modifiable, because the cookie carries only an opaque identifier into server-side state |

## 13.2 Role → Capability → Enforcement

`ALLOW` permitted · `OWN` only on records the requester created · `VIEW` read permitted, write denied · `DENY` 403.

| Capability | Admin | Sales | Warehouse Staff | Authorization Condition | Server Enforcement | Failure Response |
|---|---|---|---|---|---|---|
| Login / Logout | ALLOW | ALLOW | ALLOW | Valid active credential (login); own session (logout) | Credential and active check server-side | Generic auth failure |
| User management | ALLOW | DENY | DENY | Role = Admin | Asserted in the Service before any write; **page and endpoint both guarded** | **403** |
| Product write | ALLOW | DENY | DENY | Role = Admin | Service assertion | 403 |
| Product / Category / Warehouse read | ALLOW | VIEW | VIEW | Authenticated | Read endpoints open to all roles | Redirect / 401 |
| Category write | ALLOW | DENY | DENY | Role = Admin | Service assertion | 403 |
| Warehouse write | ALLOW | DENY | DENY | Role = Admin | Service assertion | 403 |
| Supplier management | ALLOW | DENY | DENY | Role = Admin | **Read and write both Admin-only** | 403 |
| Customer management | ALLOW | DENY | DENY | Role = Admin | Read and write both Admin-only | 403 |
| View stock | ALLOW | ALLOW | ALLOW | Authenticated | — | Redirect / 401 |
| Create Sales Order | ALLOW | OWN | DENY | Role ∈ {Admin, Sales} | Service assertion; creator taken from the session | 403 |
| Submit Sales Order | ALLOW | OWN | DENY | Admin, **or** Sales **and** the stored creator | Ownership compared against the persisted `created_by` | 403 |
| **Approve Sales Order** | ALLOW | **DENY** | DENY | **Role = Admin, unconditionally** | **First assertion in the method, before any read-for-write, transaction, or state change** | **403, no transition** |
| **Reject Sales Order** | ALLOW | **DENY** | DENY | **Role = Admin, unconditionally** | Identical assertion, identical position | **403, no transition** |
| Cancel Sales Order | ALLOW | OWN (pre-`Fulfilled`) | DENY | Admin at any pre-`Fulfilled` stage; Sales on own `Draft`/`PendingApproval` | Service assertion + ownership | 403 |
| Create Purchase Order | ALLOW | DENY | ALLOW (propose) | Role ∈ {Admin, WarehouseStaff} | Service assertion | 403 |
| Order Purchase Order | ALLOW | DENY | DENY | Role = Admin | Service assertion | 403 |
| Goods receipt | ALLOW | DENY | ALLOW | Role ∈ {Admin, WarehouseStaff}; asserted **before the transaction opens** | Service assertion | 403, no transaction |
| Goods issue | ALLOW | DENY | ALLOW | Role ∈ {Admin, WarehouseStaff}; asserted before the transaction | Service assertion | 403, no transaction |
| Dashboard | ALLOW (all) | OWN orders | Operational scope | Authenticated; scope by role | **Scope applied in the SQL predicate as a bound parameter, not in the template** | Redirect |
| Report | ALLOW (all) | OWN orders | Stock report | Authenticated; scope by role | Scope applied server-side **before any query** | 403 for out-of-scope |
| API availability | ALLOW | ALLOW | ALLOW | Authenticated by the **same rule** as an HTML page | Same authentication object | **401 JSON** |
| Low-stock script | Container access only | — | — | Not reachable over HTTP | Lives outside the web root | N/A |

## 13.3 Ownership Specification

| # | Rule |
|---|---|
| OWN-1 | Ownership applies to exactly one entity: the Sales Order, through `created_by` |
| OWN-2 | Ownership is evaluated against the **persisted** creator id, never against a request parameter |
| OWN-3 | Ownership **grants** Sales the ability to submit or cancel its own pre-`Fulfilled` order. It **never** grants approve or reject |
| OWN-4 | Admin is not subject to the ownership rule |
| OWN-5 | Warehouse Staff has no ownership relationship to a Sales Order and is denied create, submit and cancel outright |
| OWN-6 | Read scoping for Sales is a bound SQL predicate on `created_by`, applied in the query |

## 13.4 Segregation of Duties Specification

**Preserved exactly as Phase 1 interprets it: `Sales → cannot approve Sales Orders`. This is a total
prohibition, not an own-order-only restriction.**

| # | Specification | Origin |
|---|---|---|
| SOD-1 | Only Admin may approve or reject a Sales Order | `P1 §19.3`, `§19.4` |
| SOD-2 | **Sales is denied entirely, including on its own order.** The stored creator identity plays **no part** in the decision | `DEC-012`, `P1 §19.4` SoD-1 |
| SOD-3 | The guard is a role assertion (`role = Admin`), **not** a creator comparison. A creator comparison would wrongly permit a *different* Sales user to approve | `DEC-012` |
| SOD-4 | Enforcement is in the **server authorization layer**, executed before any read-for-write, transaction, or state change | `P1 §19.4` SoD-2 |
| SOD-5 | **Hiding or disabling a UI control is not enforcement.** The UI may hide the control for a better experience, but removing the button changes nothing about the outcome of a direct request | `P1 §19.4` SoD-2, `P2` SoD-F, SoD-G |
| SOD-6 | A denied attempt returns **403** and performs **no transition**: status unchanged, approver unset | `P1 §19.5` SoD Bypass |
| SOD-7 | Warehouse Staff is denied by the same single assertion; no separate rule exists | `P1 §19.4` |
| SOD-8 | The approve and reject actions live in their own service so the denial is one auditable method pair, and the Sales-facing order service **has no approve or reject method at all** | `P2` SoD-B, SoD-C |
| SOD-9 | The demonstration is a **direct request as a Sales user to the approve endpoint for its own order**, expecting 403 and an unchanged status | `P1 §19.5`, CF-8 |
| SOD-10 | A unit test asserts that approval with a Sales identity throws and that the approval write is never reached — runnable with no database | `P2` SoD-I, `TEST-01` |

---

# 14. STOCK SPECIFICATION

Canonical rules: `P1 §11` PO-01 / SO-01 / ARCH-02 / DB-01 and `P1 §22` B-S1…B-S8. Design: `P2 §09`,
`§10`, `§11`. Preserved invariants: **no negative stock · no oversell · `ProductStock`/`StockLedger`
consistency · movement traceability.**

## 14.1 Goods Receipt

| Field | Specification |
|---|---|
| **Trigger** | A goods-receipt submission against a PO in `Ordered` or `PartiallyReceived` |
| **Preconditions** | Requester is Warehouse Staff or Admin; the PO exists and is receivable; at least one line has outstanding quantity |
| **Validation** | **Inside the transaction, after a locked re-read**: PO status is receivable; each submitted line belongs to this PO; each received quantity > 0; **each received quantity ≤ that line's outstanding quantity** (`ordered − received`) |
| **Authorization** | Role asserted **before the transaction opens**; Sales → 403 with no transaction (`P1 §19.4` PO-01) |
| **Stock Calculation** | `new = current + received`, applied as a **relative delta**, at the PO **destination** warehouse |
| **ProductStock Mutation** | Increase for (line product, PO destination warehouse) |
| **StockLedger Mutation** | One `Receipt` row per received line: signed **positive** `quantity_change`, resulting quantity recorded, **PO reference set**, SO reference null, acting user recorded |
| **Transaction Requirement** | **MANDATORY** — one explicit transaction spanning the stock increase, the ledger append, the `quantity_received` increase and the PO status recomputation (`P1 §19.6`, `P2 TX-1`) |
| **Failure Behaviour** | Non-authorised role → 403, no transaction · PO not receivable → refused · received quantity ≤ 0 → refused · **over-receipt → refused** · line not on this PO → refused · any write failure or constraint violation → **full rollback: stock, ledger and received quantity all unchanged** |
| **Result** | Stock increased; movement fully explained by the ledger; outstanding reduced; PO `Received` only when every line is complete, otherwise `PartiallyReceived`; `quantity` still equals the sum of signed ledger rows |
| **Requirement Reference** | PO-01, ARCH-02, DB-01, VAL-01, WH-01 · `P1 §19.3`, `§19.6`, `§19.7` |
| **Design Reference** | `P2 TX-1`, C-10, `CC-5`, SD-1…SD-7, T-09 `CHECK` |

## 14.2 Goods Issue

| Field | Specification |
|---|---|
| **Trigger** | An issue action on an SO in `Approved` |
| **Preconditions** | Requester is Warehouse Staff or Admin; SO status is **exactly** `Approved` |
| **Validation** | **Inside the transaction**: SO status asserted `Approved` on a **locked re-read**; per line, the `ProductStock` row for (product, SO **source** warehouse) read **`FOR UPDATE`** and `current − requested >= 0` asserted. **A pre-transaction availability check is advisory only** |
| **Authorization** | Role asserted **before the transaction opens**; Sales → 403 with no transaction |
| **Stock Calculation** | `new = current − requested`, applied as a **relative delta**, only if `new >= 0` |
| **ProductStock Mutation** | Decrease for (line product, SO source warehouse) |
| **StockLedger Mutation** | One `Issue` row per line: signed **negative** `quantity_change`, resulting quantity recorded, **SO reference set**, PO reference null, acting user recorded |
| **Transaction Requirement** | **MANDATORY** — one explicit, race-safe transaction spanning the locked reads, the stock decreases, the ledger appends and the status transition (`P1 §19.6`, `§19.7`, `P2 TX-2`) |
| **Failure Behaviour** | Non-authorised role → 403, no transaction · SO not `Approved` → refused · **insufficient stock on any line → the entire issue refused and rolled back; there is no partial fulfilment** · a competing concurrent issue that exhausted the stock first → refused · `quantity >= 0` violation → rollback · deadlock → explicit retryable failure, **no automatic retry** · any write failure → rollback |
| **Result** | Stock decreased; SO `Fulfilled`; **no negative stock, no oversell, no lost update**; `quantity` equals the sum of signed ledger rows |
| **Requirement Reference** | SO-01, ARCH-02, DB-01, WH-01, ERR-01 · `P1 §19.3`, `§19.6`, `§19.7`, `§19.14` CF-6/CF-7 |
| **Design Reference** | `P2 TX-2`, C-13, `CC-1`, `CC-2`, `CC-3`, `§11.2`, `ADR-002` |

## 14.3 Adjustment

| Field | Specification |
|---|---|
| **Trigger** | **None specified.** `P1 §19.3` records that no `Adjustment` transition row exists |
| **Preconditions** | Not applicable — no workflow exists |
| **Validation** | Not applicable |
| **Authorization** | **No authorization rule exists**, because no action exists to authorize (`P1 §19.4` has no `Adjustment` row) |
| **Stock Calculation** | Not specified |
| **ProductStock Mutation** | **None.** No code path writes an adjustment |
| **StockLedger Mutation** | The `Adjustment` **enum value is retained** because SRC-001 §1.3 fixes the enum. **Nothing writes it.** The reference-shape constraint defines an `Adjustment` row as carrying neither a PO nor an SO reference, so the constraint is total — not so a workflow is implied |
| **Transaction Requirement** | Not applicable |
| **Failure Behaviour** | Not applicable |
| **Result** | No adjustment feature exists |
| **Requirement Reference** | `DEC-010` (scope decision) · `P1 §19.1` DB-01 movement-type enum · `P1 §19.3` explicit absence · `GAP-003` carried |
| **Design Reference** | `P2 §09.4`, `§04.1` (`StockAdjustmentService` deliberately not created), BR-ADJ-1…4 |

**No adjustment workflow is invented.** `DEC-010` placed none in scope, and any future adjustment
feature requires a new decision record in `phase1-baseline.md` §04 first.

## 14.4 Preserved Invariants

| # | Invariant | How the specification guarantees it |
|---|---|---|
| SI-1 | **No negative stock** | Locked read + `current − requested >= 0` assertion inside the transaction, plus the `quantity >= 0` database `CHECK` as a backstop |
| SI-2 | **No oversell** | The row lock serialises competing issues, so the second reads the post-first quantity and is refused |
| SI-3 | **`ProductStock` / `StockLedger` consistency** | Both writes occur in one transaction, with no code path performing one without the other; verified by `quantity == SUM(quantity_change)` |
| SI-4 | **Movement traceability** | Every row records type, signed change, resulting quantity, order reference and acting user; the ledger is append-only |
| SI-5 | **No lost update** | The pre-write quantity comes from a locked read **and** the write is a relative delta, so a stale figure cannot overwrite a concurrent movement |
| SI-6 | **Single mutation point** | Only the stock service writes stock or ledger; every other component delegates to it |
| SI-7 | **No cache in the stock path** | Neither Redis nor Memcached holds stock, participates in a transaction, or acts as a concurrency boundary |

---

# 15. SEARCH / FILTER / SORT / PAGINATION

Canonical rules: `P1 §11` FIND-01 and `P1 §19.1` FIND-01. **No extra search capability is invented.**

## 15.1 Product List

| Aspect | Specification |
|---|---|
| **Search fields** | Product **name** or **SKU** — one search input matching either |
| **Filter fields** | **Category** (from the active category list) · **Stock status** ∈ `{low stock, normal}`, where low stock means summed stock **below** the product's reorder point |
| **Sort options** | Default list order. `FIND-01` mandates a date sort for **orders** only; no product sort option is invented |
| **Pagination** | **Exactly 10 records per page** |
| **Persistent filters** | Active search, category and stock-status selections **remain in effect when the page changes** |
| **Scope** | All roles may read the catalogue (`P1 §13`) |
| **Requirement Reference** | FIND-01, VIEW-01, PRD-01 · `P1 §19.1` FIND-01 |
| **Design Reference** | `P2` R-02 `ProductFilter`/`Page`, T-04 indexes (`idx_products_name`, `uq_products_sku`, `idx_products_category`, `idx_products_reorder_point`), DR-3 |

## 15.2 Purchase Order List

| Aspect | Specification |
|---|---|
| **Search fields** | PO **number** or **counterparty** (supplier name) |
| **Filter fields** | **Status** ∈ `{Draft, Ordered, PartiallyReceived, Received, Cancelled}` |
| **Sort options** | **Date, ascending or descending** |
| **Pagination** | Exactly 10 records per page |
| **Persistent filters** | Search, status filter and sort direction persist across page changes |
| **Scope** | Warehouse Staff sees its operational scope; Admin sees all; Sales has no PO capability (`P1 §13`) |
| **Requirement Reference** | FIND-01, VIEW-01, PO-01 · `P1 §19.1` FIND-01 |
| **Design Reference** | `P2` R-09 `OrderFilter`, T-08 indexes (`idx_po_status`, `idx_po_order_date`, `idx_po_supplier`) |

## 15.3 Sales Order List

| Aspect | Specification |
|---|---|
| **Search fields** | SO **number** or **counterparty** (customer name) |
| **Filter fields** | **Status** ∈ `{Draft, PendingApproval, Approved, Fulfilled, Cancelled}` |
| **Sort options** | Date, ascending or descending |
| **Pagination** | Exactly 10 records per page |
| **Persistent filters** | Search, status filter and sort direction persist across page changes |
| **Scope** | **Sales sees only its own orders** — applied as a bound SQL predicate on `created_by`, not a template filter; Admin sees all; Warehouse Staff sees its fulfilment scope |
| **Requirement Reference** | FIND-01, VIEW-01, SO-01 · `P1 §19.1`, `§19.4` |
| **Design Reference** | `P2` R-10 `OrderFilter`, T-10 indexes (`idx_so_status`, `idx_so_order_date`, `idx_so_created_by`), OW-5 |

## 15.4 Cross-Cutting Rules

| # | Rule | Origin |
|---|---|---|
| SFP-1 | Page size is **exactly 10** on every main list | `P1 §11` FIND-01 |
| SFP-2 | Active selections persist across page changes | `P1 §11` FIND-01 |
| SFP-3 | Search terms and filter values are **bound parameters**, never concatenated into SQL | `P1 §11` DB-01, `P2 SEC-11` |
| SFP-4 | Sort **column and direction come from a closed allow-list mapped to literals in code**, never interpolated from request input | `P2 SEC-11` |
| SFP-5 | Role scope is applied in the query predicate, never in the template | `P1 §19.4` |
| SFP-6 | A page number out of range is clamped to a valid page rather than erroring | `P1 §19.1` FIND-01 (`page ≥ 1`) |
| SFP-7 | A no-match result renders an **informative empty state**, not a blank table body | `P1 §11` VIEW-01 |
| SFP-8 | Seed data supplies ≥ 30 products and ≥ 25 combined orders so pagination is demonstrable | `P1 §11` FIND-01, DB-01 |
| SFP-9 | **No additional search field, filter, or sort option is added** beyond those listed above | `P1 §19.1` FIND-01 |

---

# 16. DASHBOARD SPECIFICATION

Canonical rule: `P1 §11` DASH-01 — **every figure is produced by an aggregation query over live
data; no figure may be a stored or hard-coded value.** Design: `P2 §17.1`, `ADR-003`.

## 16.1 Admin Metrics

| Metric | Source | Filter | Aggregation | Role | Expected Meaning | Requirement | Design Reference |
|---|---|---|---|---|---|---|---|
| **Inventory Value** | `ProductStock` × `Product` | Active products | `SUM(quantity × buy_price)` across all warehouses | Admin | The capital currently held as stock, valued at purchase price, in IDR | DASH-01, PRD-01, WH-01 | `P2` R-11 `inventoryValue`, `§17.1` |
| **Below Reorder Point** | `ProductStock` × `Product` | Active products | `COUNT` of products whose **summed** quantity across scoped warehouses is `< reorder_point` | Admin | How many catalogue items need replenishing now | DASH-01, PRD-01, JOB-01 | `P2` R-11 `lowStockProducts`, DR-3 |
| **Pending Orders by Status** | `PurchaseOrder`, `SalesOrder` | None | `COUNT(*) GROUP BY status` | Admin | How much work sits at each stage of both order pipelines | DASH-01, PO-01, SO-01 | `P2` R-11 `orderCountsByStatus`, `§17.1` |

## 16.2 Sales Metrics

| Metric | Source | Filter | Aggregation | Role | Expected Meaning | Requirement | Design Reference |
|---|---|---|---|---|---|---|---|
| **Own Orders by Status** | `SalesOrder` | **`created_by = <requesting user>`**, applied as a bound SQL predicate | `COUNT(*) GROUP BY status` | Sales | The requester's own pipeline. **Other users' orders are never included** | DASH-01, SO-01, VIEW-01 | `P2` R-11 `orderCountsByStatus` with `RoleScope`, OW-5 |

## 16.3 Warehouse Staff Metrics

| Metric | Source | Filter | Aggregation | Role | Expected Meaning | Requirement | Design Reference |
|---|---|---|---|---|---|---|---|
| **Goods Receipt Queue** | `PurchaseOrder` | `status ∈ {Ordered, PartiallyReceived}` | `COUNT` and list | Warehouse Staff | Deliveries awaiting receipt | DASH-01, PO-01 | `P2` R-11 `receiptQueue` |
| **Goods Issue Queue** | `SalesOrder` | `status = Approved` | `COUNT` and list | Warehouse Staff | Approved orders awaiting fulfilment | DASH-01, SO-01 | `P2` R-11 `issueQueue` |
| **Low Stock** | `ProductStock` × `Product` | Active products | The **same** below-reorder-point query as the Admin count, returned as a list | Warehouse Staff | What to replenish, item by item | DASH-01, PRD-01, JOB-01 | `P2` R-11 `lowStockProducts`, DR-3 |

## 16.4 Dashboard Rules

| # | Rule | Origin |
|---|---|---|
| DS-1 | **No static value, no counter column, no summary table, no cached figure.** Every number is the return value of a query executed on request | `P1 §11` DASH-01 explicit rule |
| DS-2 | Reloading after a data change **must** change the affected figure | AC-DASH-01-4 |
| DS-3 | Role scope is a bound SQL parameter, never a template condition and never concatenated | `P1 §19.4`, `P2 SEC-11` |
| DS-4 | "Below reorder point" has **exactly one definition**, shared by the Admin count, the Warehouse Staff list, the `FIND-01` filter and the CLI script | `P2 ADR-003`, DR-3 |
| DS-5 | Money is aggregated as a decimal type and never accumulated as a float; currency is IDR | `DEC-013`, `P2` DR-4 |
| DS-6 | A role with no matching data renders zeros, not an error | `P1 §11` VIEW-01 empty-state rule |
| DS-7 | The dashboard performs no write and opens no transaction | `P1 §19.6` |
| DS-8 | **Aggregated figures are never cached in Memcached** | `P1 §19.9` DASH-01 row, `P2` MI-5 |

---

# 17. REPORT SPECIFICATION

Canonical rule: `P1 §11` REPORT-01 — **a CSV export must be produced from the same aggregation basis
as the corresponding dashboard figure, so that report and dashboard cannot disagree.** Design:
`P2 §17.2`, `ADR-003`. `DEC-013` makes the tabular surface canonical; **no chart is specified.**

## 17.1 Stock Movement CSV

| Aspect | Specification |
|---|---|
| **Data Source** | **`StockLedger`**, joined to `Product`, `Warehouse`, `User`, and to `PurchaseOrder` / `SalesOrder` for the reference number |
| **Date Range** | `moved_at` within `[date_from, date_to]`, both required, `date_from <= date_to` |
| **Filters** | Date range; role scope. Warehouse Staff receives the stock report; Sales is limited to its own scope; Admin sees all |
| **Output Columns** | `moved_at`, `type`, `product_sku`, `product_name`, `warehouse_code`, `quantity_change` (signed), `quantity_after`, `reference_type`, `reference_number`, `recorded_by` |
| **Aggregation** | One row per ledger entry in range, ordered by `moved_at` — the canonical stock-movement query of the shared aggregation contract |
| **Authorization** | Authenticated; scope applied **server-side before any query**; an out-of-scope request receives **403** |
| **Failure Behaviour** | Invalid or inverted range → rejected, **nothing streamed** · out-of-scope request → 403 · unauthenticated → redirect · empty result → header row only · query failure → generic error, no SQL text |
| **Requirement Reference** | REPORT-01, PRD-01, PO-01, SO-01, ARCH-02, VIEW-01 · `P1 §19.1` REPORT-01 |
| **Design Reference** | `P2 §17.2` `RP-1`, R-11 `stockMovementRows`, `ADR-003` |

## 17.2 Order Status CSV

| Aspect | Specification |
|---|---|
| **Data Source** | `PurchaseOrder` and `SalesOrder` with their counterparties and warehouses |
| **Date Range** | `order_date` within `[date_from, date_to]`, both required, `date_from <= date_to` |
| **Filters** | Date range; role scope — **Sales receives only orders where it is the recorded creator** |
| **Output Columns** | `order_type`, `order_number`, `order_date`, `counterparty`, `warehouse_code`, `status`, `created_by`, `approved_by`, `total_value` |
| **Aggregation** | One row per order in range; the status counts come from **the same method the dashboard calls** for pending-orders-by-status |
| **Authorization** | Authenticated; scope server-side; out-of-scope → 403 |
| **Failure Behaviour** | As 17.1 |
| **Requirement Reference** | REPORT-01, PO-01, SO-01, DASH-01, VIEW-01 · `P1 §19.1`, `§19.4` |
| **Design Reference** | `P2 §17.2` `RP-2`, R-11 `orderStatusRows` / `orderCountsByStatus`, `ADR-003` |

## 17.3 Report Rules

| # | Rule | Origin |
|---|---|---|
| RS-1 | Both exports read the **same aggregation contract** the dashboard reads — and, for status counts, the **same method** — so report and dashboard **cannot disagree** | `P1 §11` REPORT-01, `P2 ADR-003` |
| RS-2 | **No second aggregation definition is introduced anywhere** | `P2 ADR-003` |
| RS-3 | Stock movement originates from `StockLedger`, never from a recomputation of `ProductStock` history | `P1 §11` REPORT-01 |
| RS-4 | Out-of-scope export → 403 **before** any query runs | `P1 §19.4` REPORT-01 |
| RS-5 | Rows are streamed; no temporary table and no file on disk | `P2` RR2-4 |
| RS-6 | CSV fields are escaped, and a text field beginning `=`, `+`, `-` or `@` is neutralised so a spreadsheet does not evaluate it | `P2` RR2-5 |
| RS-7 | Currency is IDR | `DEC-013` |
| RS-8 | **No chart, no graph, no visualisation is specified** — the tabular surface is canonical and charts are bonus per SRC-001 §4.4 | `DEC-013`, `GAP-006`, `P2` RR2-7 |

---

# 18. API SPECIFICATION

## 18.1 `GET /api/products/{sku}/availability`

| Aspect | Specification |
|---|---|
| **Authentication** | **The same rule and the same mechanism as an HTML page.** No separate API key and no separate token scheme |
| **Authorization** | Any authenticated role — Admin, Sales, Warehouse Staff. No further role restriction |
| **Path Parameter** | `sku` — a product SKU, **bound as a prepared-statement parameter**, never interpolated |
| **Request Contract** | `GET`, no body, no query parameters. `Accept` is irrelevant; the endpoint always answers JSON |
| **Data Source** | Product identity resolved from the product store (optionally via the read cache); **per-warehouse quantities read from MySQL — availability is never served from cache** |
| **Transaction** | None — read-only |

### 200 Response

```text
HTTP/1.1 200 OK
Content-Type: application/json
```

```json
{
  "sku": "PRD-0007",
  "name": "Kabel HDMI 2.0 3m",
  "unit": "pcs",
  "is_active": true,
  "total_available": 42,
  "warehouses": [
    { "warehouse_code": "WH-JKT", "warehouse_name": "Gudang Jakarta", "quantity": 30 },
    { "warehouse_code": "WH-BDG", "warehouse_name": "Gudang Bandung", "quantity": 12 }
  ]
}
```

`total_available` is the **computed sum** of the listed warehouse quantities and is not stored
anywhere. A warehouse holding zero is returned with `0` rather than omitted, so a consumer can
distinguish "none here" from "warehouse unknown".

### 401 Response

```text
HTTP/1.1 401 Unauthorized
Content-Type: application/json
```

```json
{ "error": "unauthenticated", "message": "Authentication required." }
```

Returned when no valid session is presented, **and** when the session store is unreachable (fail
closed). **Never an HTML login redirect.**

### 404 Response

```text
HTTP/1.1 404 Not Found
Content-Type: application/json
```

```json
{ "error": "not_found", "message": "Product not found." }
```

Returned for an unknown SKU **and** for a malformed one, so the endpoint is not a catalogue
enumeration oracle.

### Failure Behaviour

| Condition | Status | Body |
|---|---|---|
| No valid session, or session store unreachable | **401** | JSON `unauthenticated` |
| Unknown or malformed SKU | **404** | JSON `not_found` |
| Unknown route under the API prefix | 404 | JSON `not_found` |
| Wrong HTTP method | 405 | JSON `method_not_allowed` |
| Database or internal failure | 500 | JSON `internal_error`, generic message; detail logged server-side only |
| Read cache unavailable | 200 | Unaffected — identity resolution falls back to the database |

**Every status, including 500, carries a JSON body. An HTML error page is never returned.**

| Aspect | Value |
|---|---|
| **Requirement Reference** | API-01, AUTH-01, ERR-01, WH-01, PRD-01 · `P1 §19.1`, `§19.4`, `§19.5` |
| **Phase 2 Design Reference** | `P2 §16.1`, C-17, `UC-G1`, AP-1…AP-5, `SEC-19`, `RD-1` |

## 18.2 API Rules

| # | Rule | Origin |
|---|---|---|
| AR-1 | The API reuses the **same service, the same repositories and the same authentication mechanism** as the web application. **No business logic is duplicated for the API** | `P1 §11` API-01, ARCH-01, `P2` AP-1 |
| AR-2 | The status code is selected by the HTTP layer from a typed domain outcome; the business layer never names an HTTP status | `P2` AP-2, SR-4 |
| AR-3 | **No additional endpoint is specified.** `API-01` requires at least one; more would be unrequested scope | `P2` AP-3 |
| AR-4 | The contract is **read-only**; no write endpoint exists | `P2` AP-4 |
| AR-5 | Response field names carry no internal database ids, so the schema is not leaked | `P2` AP-5 |
| AR-6 | **The API is not implemented in this phase** — this is the contract | Phase 3 §18 |

---

# 19. LOW-STOCK SCRIPT SPECIFICATION

| Aspect | Specification |
|---|---|
| **Entry Point** | `scripts/check-low-stock.php` — a standalone CLI entry point outside the web root and outside the web request cycle. It constructs its own database connection from environment variables, constructs the aggregation repository and the low-stock service, and calls it. It creates **no session, no cache client, and no controller** |
| **Input** | **None required.** Optional `--warehouse=CODE` narrows the summary to one warehouse; optional `--format=text\|csv` selects the output shape. Both are conveniences with defaults; the script runs correctly with no arguments |
| **Data Source** | The **shared aggregation contract's** low-stock query — the **same query** the dashboard and the `FIND-01` low-stock filter use, reading `ProductStock` × `Product` from MySQL |
| **Calculation** | A product qualifies when its **summed** stock across the scoped warehouses is **below** its `reorder_point`, considering **active products only**. The comparison lives in the shared query, not in the script |
| **Output** | A text table on standard output: SKU, name, current quantity, reorder point, shortfall, and per-warehouse breakdown; then a total count line. Exit code **`0` on success, including when nothing is below reorder point** — "no low stock" is a successful result |
| **Failure Behaviour** | Database unreachable or query failure → one line on **standard error**, exit **`1`**, **no stack trace** · missing environment configuration → message on standard error, exit **`2`** · the script performs **no write** and opens no transaction |
| **Manual Invocation** | `docker compose exec app php scripts/check-low-stock.php` |
| **Requirement Reference** | JOB-01, PRD-01, DASH-01, FIND-01, ENV-01 · `P1 §19.4` JOB-01 row |
| **Phase 2 Design Reference** | `P2 §18`, C-18, `UC-G2`, R-11 `lowStockProducts`, JD-1…JD-6, `ADR-003` |

## 19.1 Script Rules

| # | Rule | Origin |
|---|---|---|
| JS-1 | **No automatic scheduling.** No cron entry, no scheduler service, no daemon, no timer. `JOB-01` states automatic scheduling on the assessment server is not required, and adding one would be unrequested scope | `P1 §11` JOB-01, `P2` JD-1 |
| JS-2 | The script performs no write and opens no transaction | `P2` JD-2 |
| JS-3 | The low-stock definition is **not reimplemented** in the script — reimplementation would create a second definition able to disagree with the dashboard | `P2 ADR-003`, JD-3 |
| JS-4 | The script is **not reachable over HTTP**; it lives outside the public directory | `P1 §19.4` JOB-01, `P2` JD-4 |
| JS-5 | It touches **no session and no superglobal**, which is what makes it evidence that business logic is independent of the web request cycle | `P1 §11` ARCH-01, JOB-01, `P2` JD-5 |
| JS-6 | Configuration comes from the same environment variables the web entry point uses; **no absolute path and no machine-specific value is embedded** | `P1 §11` ENV-01, `P2` JD-6 |

---

# 20. ERROR SPECIFICATION

Canonical rule: `P1 §11` ERR-01. Design: `P2 SEC-17`, `SEC-18`, `SEC-19`, `§16.1`.

## 20.1 Error Behaviour Matrix

| Condition | HTML Behaviour | API Behaviour | CLI Behaviour | State Guarantee | Requirement |
|---|---|---|---|---|---|
| **Unauthenticated** | **Redirect to login** | **401** JSON `unauthenticated` | N/A — container access is the boundary | Nothing mutated | ERR-01, AUTH-01, API-01 |
| **Unauthorized** (authenticated, insufficient authority) | **403** with a safe page | **403** JSON `forbidden` | N/A | **Nothing mutated; no transition performed; no transaction opened** | ERR-01, USR-01, MSTR-01, SO-01 SoD |
| **Not Found** (record or route) | **404** with a safe page | **404** JSON `not_found` | N/A | Nothing mutated | ERR-01, VIEW-01, API-01 |
| **Validation Error** | Form re-rendered with field-level errors and **previously entered input preserved** | **422** JSON with field errors, or 404 where the input identifies no resource | Message on standard error | **Nothing persisted; no transaction opened** | ERR-01, VAL-01 |
| **Business Rule Violation** (wrong status for the action, over-receipt, inactive counterparty, duplicate email or SKU) | Safe message naming the rule that failed, without internals | **422** or **409** JSON with a stable error code | Message on standard error | **Nothing persisted; transaction rolled back if one had opened** | ERR-01, PO-01, SO-01, PRD-01, MSTR-01, USR-01 |
| **Insufficient Stock** | Safe message stating the issue was refused for insufficient stock | **409** JSON `insufficient_stock` | N/A | **Whole issue refused and rolled back; no partial fulfilment; stock and ledger unchanged** | SO-01, ARCH-02, ERR-01 |
| **Database Failure** | Generic safe page. **No SQL text, no exception message, no stack trace** | **500** JSON `internal_error`, generic message | One line on standard error, exit `1`, no stack trace | Transaction rolled back; nothing partial survives | ERR-01, DB-01 |
| **Unexpected Error** | Generic safe page | **500** JSON `internal_error` | Exit non-zero with a safe message | Rolled back | ERR-01 |
| **Deadlock** (concurrency) | Safe retryable message | **409** JSON | N/A | Transaction rolled back; **no automatic retry** | ARCH-02, `P2 §11.2` |
| **Wrong HTTP method** | 405 | **405** JSON `method_not_allowed` | N/A | Nothing mutated | ERR-01 |

## 20.2 Non-Disclosure Rules

| # | Rule | Origin |
|---|---|---|
| ER-1 | **A database exception, SQL statement, constraint name, or stack trace is never rendered to a user** in any channel | `P1 §11` ERR-01, `P1 §19.5` |
| ER-2 | Detail is logged **server-side only**; the response carries a generic message and, where useful, a stable machine-readable error code | `P2 SEC-17` |
| ER-3 | Display of PHP errors is off in the container's runtime configuration | `P2 SEC-17` |
| ER-4 | No credential, secret, or internal filesystem path appears in any response body or user-visible message | `P2 SEC-16`, `SEC-17` |
| ER-5 | Every deny path is observable in the **response status**, so the `ERR-01` and SoD demonstrations are reproducible | `P2` SEC-R5 |
| ER-6 | A record outside the requester's scope is reported as **404**, not 403, so scope membership is not disclosed | `P1 §11` VIEW-01 |
| ER-7 | The API error handler is installed **before any output**, so even an unexpected failure answers JSON | `P2 SEC-19`, BR-8 |
| ER-8 | Authentication failures are **indistinguishable** across unknown email, wrong password and inactive account | `P1 §19.5` AUTH-01, `P2 SEC-03` |

---

# 21. SECURITY SPECIFICATION

Canonical owner: `P1 §19.5` SECURITY MATRIX (27 rows). Design: `P2 §13` (24 controls). Every control
below traces to a matrix row, **except `SS-13` CSRF**, which is declared as design gap `DG-01` in
§26 rather than absorbed as a requirement. **No unrelated security infrastructure is introduced.**

| # | Concern | Specification | Enforcement Layer | Origin |
|---|---|---|---|---|
| SS-01 | **Password handling** | Stored as a `password_hash()` digest and verified with `password_verify()`. **No plaintext column exists.** The hash never enters a session, a cache, a log, or a response | Service + schema | `P1 §19.5` AUTH-01 |
| SS-02 | **Session security** | An opaque, cryptographically random identifier in an `HttpOnly`, `SameSite=Lax`, `Secure`-when-HTTPS cookie, indexing server-side state. Role is **not** carried client-side and is therefore not tamperable | Session boundary | `P1 §19.5` AUTH-01/AUTH-02, `DEC-005` |
| SS-03 | **Session fixation** | The identifier is **regenerated on successful login**, and the pre-login key is deleted | Authentication service | `P1 §19.5` AUTH-01 |
| SS-04 | **Session expiry** | TTL **3600 seconds**, refreshed on each authenticated request. Expiry is a **safe-deny** state | Session store | `DEC-005`, `P1 §19.8` |
| SS-05 | **Session abuse after logout** | Logout deletes the server-side state **and** clears the cookie. A stale identifier resolves to nothing and is treated as unauthenticated | Authentication service | `P1 §19.5` AUTH-02 |
| SS-06 | **Authentication boundary** | Every protected route traverses the boundary; there is no route that reaches a controller action without it. Store unreachable → **fail closed** | HTTP middleware | `P1 §19.5` AUTH-01 |
| SS-07 | **Inactive-account access** | The active-status check runs **before** the session is established | Service | `P1 §19.5` AUTH-01 |
| SS-08 | **Credential disclosure via error text** | One indistinguishable failure for unknown email, wrong password and inactive account | Service → HTTP | `P1 §19.5` AUTH-01 |
| SS-09 | **Server-side authorization** | Every state-changing operation asserts role capability by **throwing**, not by returning a boolean a caller could ignore, before any read-for-write or write. Read scoping is a bound SQL predicate | Service | `P1 §19.5` USR-01/MSTR-01, `P1 §19.4` |
| SS-10 | **Segregation of duties** | `role = Admin` asserted as the **first action** of approve and reject; **creator identity never consulted**; **Sales denied entirely** | Service | `P1 §19.5` SO-01 SoD Bypass, `DEC-012` |
| SS-11 | **SQL injection prevention** | **Every** input-bearing query is a prepared statement with bound parameters. No repository operation accepts a SQL fragment. **Sort columns and directions come from a closed allow-list mapped to literals in code** | Concrete repository | `P1 §19.5` DB-01 |
| SS-12 | **XSS prevention** | All user-originated output is escaped at render time with an HTML-entity encoder configured for quotes and UTF-8. Templates escape by default; there is no unescaped-output path | View layer | `P1 §19.5` UI-01 |
| SS-13 | **CSRF where applicable** | A per-session token, held as **temporary security state** under the session TTL, embedded in every state-changing HTML form and verified **before** the request reaches a business service. A mismatch returns **403** and reaches no service | HTTP middleware | **Phase 2 prompt §16 + `DEC-005`. Not a `P1 §19.5` row — declared as `DG-01` in §26** |
| SS-14 | **Input validation** | Backend validation is authoritative; the frontend pass is convenience only. **Nothing is persisted on failure and no transaction opens** | Service | `P1 §19.5` VAL-01 |
| SS-15 | **Upload safety** | MIME type checked against an allow-list and size against a cap **before** storage; stored under a **cryptographically random, non-guessable filename** with a derived extension; written outside the executable path and served as static content, never included or executed | Service + storage | `P1 §19.5` PRD-01 (both rows) |
| SS-16 | **Secret protection** | All credentials come from environment variables; `.env` carries placeholders only; `.env` is version-control-ignored; **no active secret is committed** | Environment + repository hygiene | `P1 §19.5` ENV-01 |
| SS-17 | **Error disclosure** | One top-level handler catches every throwable, logs detail server-side, and renders a generic message. Error display is off in the runtime configuration | HTTP error handler | `P1 §19.5` ERR-01 |
| SS-18 | **Status-code correctness** | Unauthenticated → redirect or 401 · unauthorised → **403** · missing → **404** · unexpected → 500. Mapping happens in the HTTP layer from typed domain outcomes | HTTP layer | `P1 §19.5` ERR-01, API-01 |
| SS-19 | **API information leak** | JSON content type set before any output and a JSON error handler registered, so **every** status including 500 carries a JSON body | API entry point | `P1 §19.5` API-01 |
| SS-20 | **History destruction** | Product, supplier and customer persistence contracts expose **no delete**, and the corresponding foreign keys restrict deletion, so a referenced record cannot be destroyed at either layer | Contracts + schema | `P1 §19.5` PRD-01/MSTR-01 |
| SS-21 | **Stock manipulation** | Stock is mutated **only** by the stock service, **only** inside the mandatory transactions, **only** as a delta, **only** after a locked read, with `quantity >= 0` as a database backstop | Service + database | `P1 §19.5` SO-01/PO-01/ARCH-02 |
| SS-22 | **Identity collision** | A uniqueness assertion in the service **plus** a database unique key, so a race resolves at the database rather than creating a duplicate | Service + schema | `P1 §19.5` USR-01 |
| SS-23 | **Data corruption** | Primary keys, foreign keys, unique keys and `CHECK` constraints as specified in §11 | Database | `P1 §19.5` DB-01 |
| SS-24 | **Business-logic tampering via infrastructure coupling** | Business logic is isolated from the database driver, the session and superglobals; unit tests run with neither database nor session | Service structure | `P1 §19.5` ARCH-01 |

## 21.1 Security Rules

| # | Rule |
|---|---|
| SR2-1 | **Deny by default**: a route not explicitly public is protected, and a capability not explicitly granted in §13.2 is denied |
| SR2-2 | **Fail closed**: an unreachable session store makes the request unauthenticated, never authenticated |
| SR2-3 | **No secret, password, or password hash is ever written to the session store, the read cache, a log, or a response** |
| SR2-4 | No security decision is taken in the view layer |
| SR2-5 | Every deny path is observable in the response status |

## 21.2 Deliberately Not Introduced

| Not introduced | Why |
|---|---|
| JWT, OAuth, 2FA | No Phase 1 requirement; `AUTH-01` describes a session login |
| **Login lockout or rate limiting as a business rule** | **Phase 1 defines no threshold and no release condition.** Inventing either would create a product rule — see `DG-04` in §26 |
| Encryption at rest beyond password hashing | No Phase 1 requirement |
| WAF, IDS, scanner service | Outside `P1 §18` Security |
| A permission or policy engine | Three roles and a closed capability table do not justify one |

---

# 22. TECHNICAL IMPLEMENTATION SPECIFICATION

Translation of the Phase 2 architecture into implementation constraints. **Nothing is redesigned and
nothing is implemented here.** Each boundary states what the implementer must and must not do, and
how a reviewer detects a violation.

| # | Boundary | Must | Must Not | Detection |
|---|---|---|---|---|
| TI-01 | **Controller Responsibility** | Parse and marshal HTTP input; invoke **one** service method; select the HTTP status from a typed outcome; select representation (HTML or JSON); render | Contain a business rule, a domain-state comparison, a role table, a transaction, or any stock logic; reach the database | A controller action containing a domain conditional or a status comparison |
| TI-02 | **Service Responsibility** | Own business rules, use-case orchestration, **authorization decisions**, **segregation of duties**, **transaction orchestration**, and authoritative validation | Depend on the database driver, on superglobals, on HTTP status codes, on a controller, or on a concrete repository; instantiate infrastructure | Grep for the driver type, SQL keywords, superglobals, and concrete-persistence imports under the service directory |
| TI-03 | **Repository Interface** | Express the persistence contract in **domain terms** — the operations business logic needs | Encode a business rule in a method name or body; accept a role; accept a SQL fragment; expose `delete` where `P1 §19.2` says `Delete: No`; expose `update` or `delete` on the ledger | A method such as `approveIfAdmin`; a `delete` on the product, supplier, customer, user or ledger contract |
| TI-04 | **Concrete Repository** | Implement persistence with **prepared statements and bound parameters**; map rows to domain objects; provide the locked reads the contract declares | Contain a business rule, an authorization decision, or an HTTP concern; open, commit or roll back a transaction; concatenate user input into SQL | Grep for string concatenation in SQL; grep for transaction calls in the persistence directory |
| TI-05 | **Transaction Manager Boundary** | Provide one abstraction that runs a unit of work inside one explicit transaction, **committing on normal return and rolling back on any throwable**. The **service layer** calls it | Be bypassed by a service opening a raw transaction; be nested; be called from a controller or a repository | Grep for direct transaction calls outside the transaction-manager implementation |
| TI-06 | **Database Boundary** | Be the **only** authoritative store of business state; enforce primary keys, foreign keys, unique keys and `CHECK` constraints including `quantity >= 0`; carry the specified indexes | Be reached from a controller; hold a business figure that duplicates a computed one; be bypassed by a cache read on a correctness path | Schema inspection; grep for database access outside the persistence directory |
| TI-07 | **Redis Boundary** | Hold **only** authentication session state and temporary security state, **every key with a TTL, 3600 s for sessions** | Hold any business entity, any stock quantity, any aggregated figure, **any password or password hash**; participate in any transaction; act as a **concurrency mechanism**; be a fallback source of truth | Key inspection; grep for the client outside the session implementation; confirm absence from every transaction and lock path |
| TI-08 | **Memcached Boundary** | Cache **only** product, category, warehouse, supplier and customer reads, with a bounded expiry, cache-aside, and **invalidation after commit** | Cache `ProductStock`, `StockLedger`, orders, or **any aggregated figure**; be a write-through store; be required for correctness; hold an authorization decision | Key inspection; grep for the client outside the cache implementation; confirm the system is correct with caching disabled |
| TI-09 | **Stock Boundary** | Concentrate **every** stock and ledger write in one service, which **requires an already-open transaction**, always performs the paired writes, always reads `FOR UPDATE` before a decrease, and always writes a **relative delta** | Permit any other component to write `ProductStock` or `StockLedger`; accept an absolute target quantity; expose an unlocked read to a mutating path; write a ledger row without its stock change or vice versa | Grep for callers of the stock mutation operations; grep for `product_stock` and `stock_ledger` writes outside the stock service |
| TI-10 | **API Boundary** | Reuse the **same** authentication mechanism, services and repositories as the web application; answer **JSON for every status**; select the status in the HTTP layer | Duplicate a business rule; return HTML on any path; expose a write endpoint; add an endpoint beyond the one specified | Grep the API entry point for the HTML content type; compare the services the API and web roots construct |
| TI-11 | **CLI Boundary** | Reach business rules through the **same** service and repository contracts; live outside the web root; touch no session and no superglobal; read configuration from environment variables | Reimplement the low-stock definition; perform a write; open a transaction; introduce scheduling; be reachable over HTTP | Inspect the script's dependencies and its location; confirm it calls the shared aggregation contract |
| TI-12 | **Testing Boundary** | Keep unit tests free of database, network and session, using the in-memory persistence implementation and a null transaction manager; keep integration tests on the containerised database; give at least **6 unit cases over ≥ 3 logic areas** and at least **3 integration tests**; demonstrate the concurrency invariant | Use `sleep()`; make a real network call; depend on execution order; count a trivial accessor test; count a "unit" test that opens a connection | Inspect test dependencies; re-run the suite in a different order |
| TI-13 | **Docker Boundary** | Define **at minimum an application service and a database service** (plus the session and cache services the decisions introduce); start from a clean checkout with `docker compose up --build`; take all configuration from environment variables with committed example values | Require an undocumented manual step; commit an active secret; rely on an absolute path or machine-specific configuration; add infrastructure no decision introduced | Clean-checkout startup test; repository and history secret scan |

## 22.1 Structural Prohibitions

Restated from `P1 §08` (40 records) and `P2 §03.1`. **All remain prohibited; none is relaxed.**

```text
Backend framework        Laravel · Symfony · CodeIgniter · Slim · any other
Data mapping             ORM · Eloquent · Doctrine · query-builder framework
Wiring                   DI container framework · global infrastructure singleton
Frontend styling         Bootstrap · Tailwind · Bulma · Foundation · Materialize ·
                         UIkit · Semantic UI · any CSS framework · admin template · UI kit
Frontend scripting       jQuery · React · Vue · Angular · Svelte · Alpine · any JS framework
Persistence authority    NoSQL as primary store · Redis or Memcached as business truth
Query safety             raw user-input SQL · concatenated user input
Layering                 service depending on the database driver · controller reaching the
                         database · business logic in a controller or a repository ·
                         infrastructure instantiated inside a service · superglobal read in a service
Stock integrity          stock mutated outside the stock service · a non-transactional stock
                         mutation · a Redis-based stock lock
Design economy           unnecessary architecture — a layer or pattern solving no real problem
```

---

# 23. UI / UX SPECIFICATION BOUNDARY

The existing UI/UX baseline is binding (`DEC-007`). **No screen is redesigned, no screen is
invented, and no implementation detail becomes a product requirement.** Canonical UI facts remain
owned by the baseline artifact; this section maps requirements to it.

| Requirement | Existing Screen | Required Behaviour | Existing Coverage | Required Adjustment |
|---|---|---|---|---|
| AUTH-01 | Login | Submit credentials; generic failure message; redirect to the role dashboard | Present | None |
| AUTH-02 | Application shell | A reachable logout control on every authenticated page | Present | None |
| USR-01 | User list, user form | Admin-only list, create, edit, activation toggle; field errors; **no delete control** | Present | Ensure no delete affordance is offered |
| PRD-01 | Product list, product detail, product form | Create, edit, activation toggle; SKU and numeric field errors; optional image upload; **no delete control** | Present | Ensure no delete affordance; ensure the image field shows type and size feedback |
| MSTR-01 | Supplier and customer list and form screens | Admin-only management; **no delete control**; inactive records not offered in order composition | Present | Ensure no delete affordance; ensure order-composition pickers list active records only |
| WH-01 | Warehouse screen; stock display | Warehouse management; stock display showing **total and per-warehouse breakdown** | Present | None |
| PO-01 | PO list, PO create, PO detail, receive screen | Compose with supplier, destination warehouse and lines; order action; receipt entry with per-line quantities; status badges for all five values | Present | Ensure a `PartiallyReceived` badge and an outstanding-quantity column are shown |
| SO-01 | SO list, SO create, SO detail, approval, fulfil screens | Compose with customer, source warehouse and lines; submit; **approve and reject controls visible to Admin only**; issue action; status badges for all five values | Present | **Presentational hiding of approve/reject for non-Admin — hiding is never the enforcement (SOD-5)** |
| VIEW-01 | All list and detail screens | Role-scoped rows; **informative empty state** rather than a blank table body; 404 for a missing record | Present | Ensure every list has an empty state |
| FIND-01 | List-screen search, filter, pagination controls | Search input, filter selects, sort toggle, pager at **10 per page**, with **selections preserved across pages** | Present | Ensure controls carry the active selections into page links |
| DASH-01 | Dashboard | Role-specific metric set from live figures | Present | Ensure the three role variants render the metrics of §16 |
| REPORT-01 | Reports — **tabular surface** | Report type, date range, export action | Present (`DEC-013` makes the tabular surface canonical) | **The chart-bearing Reports variant is not adopted; no chart is implemented** |
| VAL-01 | All forms | Inline field errors; **previously entered input preserved** on failure | Present | Ensure repopulation on validation failure |
| ERR-01 | Error, empty and loading states | Safe 403 and 404 pages; generic failure page; **never an exception or stack trace** | Present | None |
| UI-01 | All screens | Usable at **360px** and desktop, no clipped navigation or table content; every field labelled; visible focus and contrast; output escaped | Partially present | **Apply the baseline's own documented collapsed-rail and mobile-drawer behaviour to product screens** — baseline **application**, not redesign (`ASM-003`) |
| API-01, JOB-01 | No screen | — | N/A | None |
| DB-01, ARCH-01, ARCH-02, ENV-01, TEST-01…03, DESIGN-01…04 | No screen | — | N/A | None |

## 23.1 Carried Non-Mandatory UI Findings

Carried forward **unchanged** from Phase 1 and Phase 2. Neither is resolved here, and neither is
promoted to a requirement.

| ID | Finding | Impact | Status |
|---|---|---|---|
| `DG-05` (`DISC-003`) | Eleven internal inconsistencies inside the UI baseline itself | One design decision per instance at implementation. No data, API, authorization, transaction or stock impact. **The implementation propagates none of them and defines none of them away** | **CARRIED — non-mandatory** |
| `DG-06` (`GAP-007`) | The baseline's responsive sidebar behaviour is determinate only on the shell blueprint, not on product screens | Per-screen responsive CSS only. `ASM-003` (ACCEPTED) records that applying the baseline's own documented behaviour is application, not redesign — so a resolution path exists without inventing UI | **CARRIED — non-mandatory** |

## 23.2 UI Rules

| # | Rule |
|---|---|
| UR-1 | **No screen is redesigned and no screen is invented.** Baseline screens the brief does not require create no requirement (`DISC-002`) |
| UR-2 | Where the baseline is indeterminate, **no behaviour is invented** — the gap is carried (`DG-06`) |
| UR-3 | **UI visibility is never authorization.** Hiding a control is presentational only; the server decides |
| UR-4 | No CSS or JS framework, no admin template, no UI kit |
| UR-5 | All user-originated output is escaped before rendering |
| UR-6 | **No implementation detail in this section becomes a product requirement** — the "Required Adjustment" column states what implementing an existing requirement demands of an existing screen, nothing more |

---

# 24. ACCEPTANCE SPECIFICATION

**Canonical owner: `phase1-baseline.md` §19.10 ACCEPTANCE MATRIX — 95 criteria across all 28
requirements, each already stated as `AC-ID | Requirement ID | Given | When | Then | Negative Case`.**

Phase 3 therefore **does not restate them**. Duplicating 95 Given/When/Then rows in a second
location would create two canonical acceptance sources able to drift — precisely what the Phase 3
prompt forbids ("Do not duplicate acceptance criteria in multiple canonical locations") and what
`DEC-008` (define once → reference everywhere) prohibits. This section instead certifies coverage
and observability.

## 24.1 Coverage Certification

| Requirement | AC IDs | Count | Mandatory | Observable & Testable |
|---|---|---|---|---|
| AUTH-01 | AC-AUTH-01-1 … 5 | 5 | Yes (P0) | Yes |
| AUTH-02 | AC-AUTH-02-1, 2 | 2 | Yes | Yes |
| USR-01 | AC-USR-01-1 … 4 | 4 | Yes | Yes |
| PRD-01 | AC-PRD-01-1 … 5 | 5 | Yes | Yes |
| MSTR-01 | AC-MSTR-01-1 … 3 | 3 | Yes | Yes |
| WH-01 | AC-WH-01-1, 2 | 2 | Yes | Yes |
| PO-01 | AC-PO-01-1 … 6 | 6 | Yes (P0) | Yes |
| SO-01 | AC-SO-01-1 … 8 | 8 | Yes (P0) | Yes |
| VIEW-01 | AC-VIEW-01-1, 2 | 2 | Yes | Yes |
| FIND-01 | AC-FIND-01-1 … 4 | 4 | Yes | Yes |
| DASH-01 | AC-DASH-01-1 … 4 | 4 | Yes | Yes |
| REPORT-01 | AC-REPORT-01-1 … 3 | 3 | Yes | Yes |
| API-01 | AC-API-01-1 … 3 | 3 | Yes | Yes |
| VAL-01 | AC-VAL-01-1 … 3 | 3 | Yes | Yes |
| ERR-01 | AC-ERR-01-1 … 4 | 4 | Yes | Yes |
| UI-01 | AC-UI-01-1, 2 | 2 | Yes | Yes |
| JOB-01 | AC-JOB-01-1, 2 | 2 | Yes | Yes |
| DB-01 | AC-DB-01-1 … 5 | 5 | Yes (P0) | Yes |
| ARCH-01 | AC-ARCH-01-1 … 4 | 4 | Yes (P0) | Yes |
| ARCH-02 | AC-ARCH-02-1 … 4 | 4 | Yes (P0) | Yes |
| ENV-01 | AC-ENV-01-1 … 3 | 3 | Yes (P0) | Yes |
| TEST-01 | AC-TEST-01-1, 2 | 2 | Yes (P0) | Yes |
| TEST-02 | AC-TEST-02-1 … 3 | 3 | Yes (P0) | Yes |
| TEST-03 | AC-TEST-03-1, 2 | 2 | Yes | Yes |
| DESIGN-01 | AC-DESIGN-01-1 … 3 | 3 | Yes (P0) | Yes |
| DESIGN-02 | AC-DESIGN-02-1, 2 | 2 | Yes | Yes |
| DESIGN-03 | AC-DESIGN-03-1 … 4 | 4 | Yes | Yes |
| DESIGN-04 | AC-DESIGN-04-1 | 1 | Yes | Yes |
| **Total** | | **95** | **28 of 28 requirements covered** | **95 of 95** |

## 24.2 Observability Certification

Every criterion is observable through at least one of these channels, so none requires an
unverifiable judgement:

| Channel | Criteria verified this way |
|---|---|
| **HTTP status and response body** | Every authorization, not-found, validation and API criterion — AC-USR-01-3, AC-MSTR-01-3, AC-SO-01-4, AC-SO-01-5, AC-API-01-1…3, AC-ERR-01-1…4 |
| **Database state after the operation** | Every persistence and stock criterion — AC-PO-01-3…6, AC-SO-01-6, 7, AC-ARCH-02-1…4, AC-DB-01-1, 2, 4, AC-WH-01-2 |
| **Rendered page content** | AC-VIEW-01-1, 2, AC-FIND-01-1…4, AC-DASH-01-1…4, AC-UI-01-1, 2, AC-VAL-01-3 |
| **Exported file content** | AC-REPORT-01-1, 2, 3 |
| **Automated test outcome** | AC-TEST-01-1, 2, AC-TEST-02-1…3, AC-ARCH-01-4 |
| **Code or schema inspection** | AC-AUTH-01-5, AC-ARCH-01-1…3, AC-DB-01-3, 5, AC-ENV-01-2, 3, AC-PRD-01-5 |
| **CLI output and exit code** | AC-JOB-01-1, 2 |
| **Tool report** | AC-TEST-03-1, 2 |
| **Artifact presence and content** | AC-DESIGN-01-1…3, AC-DESIGN-02-1, 2, AC-DESIGN-03-1…4, AC-DESIGN-04-1 |
| **Session-identifier comparison** | AC-AUTH-01-4 |
| **Clean-checkout startup** | AC-ENV-01-1 |

## 24.3 Acceptance Rules

| # | Rule |
|---|---|
| AK-1 | **No acceptance criterion is created, altered, renumbered or removed by Phase 3** |
| AK-2 | **No criterion is duplicated here** — `P1 §19.10` remains the single canonical location |
| AK-3 | No criterion in this document conflicts with `P1 §19.10`; the coverage table above is an index, not a restatement |
| AK-4 | Each criterion's negative case is part of the canonical row and is the basis of the corresponding failure specification in §20 |
| AK-5 | The `ASM-001`-dependent behaviour has **no separate acceptance criterion**, because Phase 1 created none — a criterion asserting the terminal value would silently resolve the open assumption. The behaviour is covered by AC-SO-01-3's approval path and by §10.7.2's explicit specification |

---

# 25. IMPLEMENTATION TRACEABILITY

Every major specification item traces to its origin. **No new source, decision, or requirement
registry is created**; every identifier below already exists in Phase 1 or Phase 2.

## 25.1 Requirement Traceability

| Requirement ID | Decision ID | Assumption ID | Phase 2 Design Reference | Supporting Matrix Reference | Acceptance Criteria Reference |
|---|---|---|---|---|---|
| AUTH-01 | `DEC-005` (session store) | — | C-01, `UC-A1`, `SEC-01`…`SEC-06`, `RD-1`, `RD-3` | `P1 §19.1`, `§19.4`, `§19.5`, `§19.8` | AC-AUTH-01-1 … 5 |
| AUTH-02 | `DEC-005` | — | C-01, `UC-A2`, `SEC-07`, `SEC-08` | `P1 §19.4`, `§19.5`, `§19.8` | AC-AUTH-02-1, 2 |
| USR-01 | — | — | C-02, `UC-B1`, `UC-B2`, R-01, T-01 | `P1 §19.1`, `§19.2`, `§19.3`, `§19.4`, `§19.5` | AC-USR-01-1 … 4 |
| PRD-01 | `DEC-006` (read cache) | — | C-03, C-04, `UC-C1`…`UC-C3`, R-02, R-03, T-03, T-04, `MC-1`, `MC-2` | `P1 §19.1`, `§19.2`, `§19.3`, `§19.5`, `§19.9` | AC-PRD-01-1 … 5 |
| MSTR-01 | **`DEC-015`**, `DEC-006` | — | C-06, C-07, R-05, R-06, T-06, T-07, `MC-4`, `MC-5` | `P1 §19.1`, `§19.2`, `§19.3`, `§19.4`, `§19.9` | AC-MSTR-01-1 … 3 |
| WH-01 | `DEC-006` | — | C-05, C-08, R-04, R-07, T-02, T-05, `CC-4`, `MC-3` | `P1 §19.1`, `§19.2`, `§19.3`, `§19.7`, `§19.9` | AC-WH-01-1, 2 |
| PO-01 | `DEC-004` | — | C-09, C-10, `UC-D1`…`UC-D3`, R-09, T-08, T-09, **`TX-1`**, `CC-5` | `P1 §19.1`, `§19.3`, `§19.4`, `§19.6`, `§19.7` | AC-PO-01-1 … 6 |
| SO-01 | `DEC-009`, **`DEC-012`**, `DEC-004` | **`ASM-001`** | C-11, C-12, C-13, `UC-E1`…`UC-E5`, R-10, T-10, T-11, **`TX-2`**, `CC-1`…`CC-3`, `§12.5`, `§12.6` | `P1 §19.1`, `§19.3`, `§19.4` (SoD-1, SoD-2), `§19.5`, `§19.6`, `§19.7`, `§19.14` | AC-SO-01-1 … 8 |
| VIEW-01 | `DEC-007` | — | R-02, R-05, R-06, R-09, R-10, OW-5, `MC-6` | `P1 §19.2`, `§19.4` | AC-VIEW-01-1, 2 |
| FIND-01 | — | — | R-02 `ProductFilter`, R-09/R-10 `OrderFilter`, T-04/T-08/T-10 indexes, DR-3 | `P1 §19.1`, `§19.2`, `§19.4` | AC-FIND-01-1 … 4 |
| DASH-01 | — | — | C-15, `UC-F1`, R-11, `§17.1`, **`ADR-003`** | `P1 §19.2`, `§19.4`, `§19.9` | AC-DASH-01-1 … 4 |
| REPORT-01 | **`DEC-013`** | — | C-16, `UC-F2`, R-11, `RP-1`, `RP-2`, **`ADR-003`** | `P1 §19.1`, `§19.2`, `§19.4` | AC-REPORT-01-1 … 3 |
| API-01 | `DEC-005` | — | C-17, `UC-G1`, `§16.1`, `SEC-19` | `P1 §19.1`, `§19.4`, `§19.5`, `§19.8`, `§19.9` | AC-API-01-1 … 3 |
| VAL-01 | — | — | SR-2, SR-3, TD-3, `SEC-14`, T-04/T-09/T-11 `CHECK` | **`P1 §19.1`** (canonical), `§19.6` | AC-VAL-01-1 … 3 |
| ERR-01 | — | — | `SEC-17`, `SEC-18`, `SEC-19`, SR-4, `§16.1` | `P1 §19.4`, `§19.5` | AC-ERR-01-1 … 4 |
| UI-01 | **`DEC-007`** | `ASM-003` | `P2 §03` view row, SoD-G, `SEC-12`, `DG-06` | `P1 §19.4` (UI is never authorization) | AC-UI-01-1, 2 |
| JOB-01 | — | — | C-18, `UC-G2`, `§18`, R-11, **`ADR-003`** | `P1 §19.2`, `§19.4` | AC-JOB-01-1, 2 |
| DB-01 | **`DEC-004`** | — | `P2 §07` T-01…T-12, `§07.1`, `SEC-11`, `SEC-24`, `TX-1`, `TX-2` | `P1 §19.1`, `§19.2`, `§19.5`, `§19.6`, `§19.7` | AC-DB-01-1 … 5 |
| ARCH-01 | **`DEC-008`** | — | `P2 §02`, `§03`, AI-1…AI-8, BR-1…BR-8, R-07, `§06.1`, **`ADR-001`** | `P1 §19.5`, `§19.6` | AC-ARCH-01-1 … 4 |
| ARCH-02 | `DEC-005` (exclusion), `DEC-004` | — | `P2 §09` SD-1…SD-7, **`TX-1`**, **`TX-2`**, `CC-1`…`CC-5`, `§11.2`, **`ADR-002`** | `P1 §19.3`, `§19.6`, `§19.7`, `§19.14` | AC-ARCH-02-1 … 4 |
| ENV-01 | **`DEC-016`**, `DEC-005`, `DEC-006` | — | `P2 §02.5`, `RD-4`, `MC-7`, `SEC-16`, JD-6 | `P1 §19.1`, `§19.8`, `§19.9`, `§19.12` | AC-ENV-01-1 … 3 |
| TEST-01 | — | — | R-07 in-memory implementation, `§06.1`, SoD-I, RU-5 | `P1 §19.11` | AC-TEST-01-1, 2 |
| TEST-02 | — | — | `CC-1`…`CC-5` verification scenarios, `§09.5` | `P1 §19.11`, `§19.7` | AC-TEST-02-1 … 3 |
| TEST-03 | — | — | BR-1…BR-8 as static boundaries, clock abstraction | `P1 §19.11` | AC-TEST-03-1, 2 |
| DESIGN-01 | — | — | **`P2 §20`** (initial diagram produced) | `P1 §19.12` | AC-DESIGN-01-1 … 3 |
| DESIGN-02 | — | — | **`P2 §21`** `ADR-001`, `ADR-002`, `ADR-003` | `P1 §19.12` | AC-DESIGN-02-1, 2 |
| DESIGN-03 | — | — | C-10/C-12/C-13 SRP split, `§04.1` | `P1 §19.12` | AC-DESIGN-03-1 … 4 |
| DESIGN-04 | — | — | `P2 §03`, `§03.1` prohibitions as the yardstick | `P1 §19.12` | AC-DESIGN-04-1 |

**28 of 28 requirements traced. Every reference resolves to an existing Phase 1 or Phase 2
identifier.**

## 25.2 Decision → Technical Constraint → Specification

| Decision ID | Technical Constraint | Specified in |
|---|---|---|
| `DEC-001` | PHP 8.3.20; native language features, no polyfill, no framework | §22 TI-02, §22.1 |
| `DEC-002` | Bootstrap forbidden | §22.1, §23 UR-4 |
| `DEC-003` | All CSS and JS frameworks forbidden | §22.1, §23 UR-4 |
| `DEC-004` | MySQL is the permanent business source of truth | §11, §22 TI-06, TI-07, TI-08 |
| `DEC-005` | Redis = session + temporary security state, TTL 3600 s | §21 SS-02…SS-05, §22 TI-07 |
| `DEC-006` | Memcached = ephemeral read cache only | §22 TI-08, §16 DS-8, §17 |
| `DEC-007` | Existing UI/UX is the baseline | §23 in full |
| `DEC-008` | One canonical owner per information class | This document references rather than restates; §24 AK-2 |
| `DEC-009` | SO rejection → `Cancelled`, from one definition point | §10.7.2, §13.4 SOD-8, §26 |
| `DEC-010` | `Adjustment` enum retained, **no workflow** | §14.3, §9.13 BR-ADJ-1…4 |
| `DEC-011` | Role enum values vs display label | §03, §12.3 (role enum), §21 |
| `DEC-012` | Sales denied approval **entirely** | §13.4 SOD-2, SOD-3; §10.7 |
| `DEC-013` | Tabular Reports canonical; no chart | §17.3 RS-8, §23 (REPORT-01 row) |
| `DEC-014` | All 26 brief IDs adopted | §04.1, §08, §25.1 |
| `DEC-015` | `MSTR-01` exists | §08 FR-MSTR-01, §11.6, §11.7 |
| `DEC-016` | `ENV-01` exists | §08 FR-ENV-01, §22 TI-13 |

## 25.3 Assumption Traceability

| Assumption ID | Status | Where it appears in this document |
|---|---|---|
| **`ASM-001`** | **OPEN — MANDATORY** | §01 (1.2 context), §05 `US-023`, §07 `UC-E4`, §08 FR-SO-01, §09 BR-SOD-8, §10.7.2 **and its dedicated handling rule**, §11.10, §26, §27, §28.3 — **nine specification locations** |
| `ASM-002` | ACCEPTED | §22 TI-07, TI-08 (bounded usage), §21.2 |
| `ASM-003` | ACCEPTED | §08 FR-UI-01, §23 (UI-01 row), §23.1 `DG-06` |

## 25.4 Traceability Rules

| # | Rule |
|---|---|
| TR-1 | Every specification statement in §07–§23 carries a Requirement Reference or is explicitly declared in §26 as a design finding |
| TR-2 | **No new requirement ID, decision ID, source ID, or assumption ID is created** for implementation tracking |
| TR-3 | The `BR-*`, `US-*`, `VF-*`, `SS-*`, `TI-*`, `DS-*`, `RS-*`, `AR-*`, `JS-*`, `ER-*`, `SI-*` and `SFP-*` labels are **reference labels for this document only** and constitute no registry |
| TR-4 | Every reference resolves: `P1 §11`, `P1 §19.1`–`§19.15`, `P1 §04`–`§06`, `P2 §02`–`§21` |
| TR-5 | Traceability runs both ways: requirement → specification → design, and design → requirement or declared gap |

---

# 26. DESIGN GAP HANDLING

The Phase 2 blueprint records six design findings. **All six remain identifiable as design
findings.** None is converted into a product requirement, and none is resolved by Phase 3.

| ID | Classification | Finding | Phase 3 treatment | Status |
|---|---|---|---|---|
| **`DG-01`** | **Design without Phase 1 origin** | CSRF protection is designed (`P2 SEC-13`, `RD-2`), but `P1 §19.5` contains no CSRF row | Specified in §21 as **`SS-13`, and `SS-13` is the only §21 control whose Origin column does not name a `P1 §19.5` row.** Its stated origin is the Phase 2 prompt §16 mandate plus `DEC-005`'s temporary-security-state allowance. It is bounded to state-changing HTML form submissions; it adds **no product rule, no status, no role, no entity, no field**. **It is not a requirement**, and no requirement ID, source, or decision is created for it. If Phase 1 governance later prefers it recorded as a `§19.5` row, that is an amendment under existing governance — not a Phase 3 act | **DESIGN FINDING — declared** |
| **`DG-02`** | **Design without Phase 1 origin** | `stock_ledger.quantity_after` is a physical column no requirement names | Specified in §11.0 and §11.12 **explicitly as a Design Detail / declared design addition**, and in §14 as the resulting-quantity record. It is **not a business field**: it makes a lost update visible in history and supports the `P1 §19.7` reconciliation verification. It creates no rule and no workflow | **DESIGN FINDING — declared** |
| **`DG-03`** | **Design without Phase 1 origin** | `sales_orders.rejection_reason` is a nullable column no requirement names | Specified in §11.0, §11.10 and §10.7.2 **as a Design Detail carrying no lifecycle meaning**. It participates in **no transition condition** and is **not a status**. **It does not resolve or weaken `ASM-001`** — the terminal status still comes from the single definition point (§10.7.2) | **DESIGN FINDING — declared** |
| **`DG-04`** | **Design without Phase 1 origin** | The login-attempt marker is designed as temporary security state (`P2 RD-3`) | Specified in §10.1 (side effect: marker cleared) and §14/§21 as an **inert mechanism**. **It must not become a login lockout requirement**, because **Phase 1 defines no lockout threshold and no release condition**, and inventing either would create a product rule. §21.2 records lockout and rate limiting explicitly under "Deliberately Not Introduced". The marker **never blocks a login**; if Phase 1 governance prefers it removed, removing it changes no requirement | **DESIGN FINDING — declared, deliberately inert** |
| **`DG-05`** | **Coverage limited by a carried Phase 1 finding** (`DISC-003`) | Eleven internal inconsistencies inside the UI baseline | Carried **unchanged** in §23.1. The specification propagates none of them into a behaviour and defines none of them away. Resolution is one design decision per instance at implementation, recorded in `phase1-baseline.md` §04 if it changes anything Phase 1 owns | **CARRIED — non-mandatory** |
| **`DG-06`** | **Coverage limited by a carried Phase 1 finding** (`GAP-007`) | The baseline's responsive sidebar behaviour is determinate only on the shell blueprint | Carried **unchanged** in §23.1, and §23's UI-01 row states the only permitted move: **apply the baseline's own documented behaviour** (`ASM-003`, ACCEPTED) rather than invent UI | **CARRIED — non-mandatory** |

## 26.1 Non-Promotion Certification

| # | Certification |
|---|---|
| NP-1 | **No `DG-*` finding appears in §08 as a functional requirement.** §08 contains exactly the 28 canonical requirement IDs and no others |
| NP-2 | **No `DG-*` finding appears in §09 as a business rule.** The 78 `BR-*` rules each cite a `P1 §11` `Business Rule Statement` or a `DEC-*` scope decision |
| NP-3 | **No `DG-*` finding appears in §24 as an acceptance criterion.** §24 references only the 95 existing `P1 §19.10` criteria and creates none |
| NP-4 | **`DG-04` is not a lockout requirement.** §21.2 states the absence explicitly, and the mechanism is specified as inert |
| NP-5 | **`DG-03` does not resolve `ASM-001`.** §10.7.2 keeps the terminal status behind the single definition point and keeps the assumption **OPEN** |
| NP-6 | Each finding is named by its `DG-` id wherever it is specified, so it stays identifiable as a design finding rather than dissolving into prose |
| NP-7 | **No requirement ID, source ID, decision ID, or assumption ID was created** for any finding |

---

# 27. PRD / SPECIFICATION COMPLETENESS CHECK

Each condition is evaluated against the produced specification, not against the presence of a
section.

| # | Condition | Evidence | Result |
|---|---|---|---|
| CC-01 | Every mandatory requirement is represented | §08 contains 28 functional specifications — one per canonical requirement, each with all 17 mandated fields | **PASS** |
| CC-02 | Every requirement remains traceable to Phase 1 | §25.1 traces all 28 to `P1 §11` plus their supporting matrices; §08 cites the canonical `Business Rule Statement` for each | **PASS** |
| CC-03 | Every technical specification is traceable to Phase 2 | §22's 13 boundaries each name their Phase 2 origin; §25.1's Phase 2 Design Reference column is non-empty for all 28 | **PASS** |
| CC-04 | Every mandatory workflow is documented | §10 documents all 10 mandated workflows (Login, Logout, Master Data, Purchase Order, Goods Receipt, Sales Order, Approval, Rejection, Goods Issue, Stock Movement) with all 10 mandated fields | **PASS** |
| CC-05 | Every business rule is covered | §09 covers all 12 mandated domains with 78 rules, each citing its canonical owner | **PASS** |
| CC-06 | Every required state transition is covered | §10.10 reproduces the complete `P1 §19.3` transition set — 10 master-data rows, 8 PO rows, 7 SO rows, 4 stock/ledger rows — with **no transition added or removed** | **PASS** |
| CC-07 | Every required validation is covered | §12.3 carries 61 field records against `P1 §19.1`'s 61 field rows, plus layer assignment and failure behaviour | **PASS** |
| CC-08 | Every authorization / SoD rule is covered | §13.2 covers all 22 capabilities; §13.3 the 6 ownership rules; §13.4 the 10 SoD rules including **SoD-1 and SoD-2 as distinct** | **PASS** |
| CC-09 | Every stock invariant is covered | §14.4 covers all 8 `P1 §22` invariants (B-S1…B-S8) as SI-1…SI-7 plus the single-mutation-point rule, and §14.1/§14.2 specify both mandatory transactions | **PASS** |
| CC-10 | Every API requirement is covered | §18 specifies the endpoint with authentication, authorization, path parameter, request contract, 200/401/404 bodies, the full failure table, data source and references | **PASS** |
| CC-11 | Every dashboard requirement is covered | §16 specifies all 7 metrics across the 3 roles with source, filter, aggregation, meaning and references; DS-1 forbids any static value | **PASS** |
| CC-12 | Every report requirement is covered | §17 specifies both CSV exports with date range, filters, columns, aggregation, authorization and failure behaviour; stock movement originates from `StockLedger` | **PASS** |
| CC-13 | Every acceptance criterion is observable and testable | §24.1 certifies 95 of 95 covered; §24.2 assigns each an observation channel | **PASS** |
| CC-14 | **`ASM-001` remains explicit and visible** | Present in **9 specification locations** (§25.3), status stated as **OPEN — MANDATORY** at each, with a dedicated handling rule in §10.7.2. **No substitute status invented; no `Rejected` value exists anywhere** | **PASS** |
| CC-15 | **No design gap was silently promoted to a requirement** | §26 preserves all six classifications; NP-1…NP-7 certify absence from §08, §09 and §24; `DG-04` is explicitly not a lockout requirement | **PASS** |
| CC-16 | No forbidden technology was introduced | §22.1 restates all `P1 §08` prohibition classes; no framework, ORM, query builder, DI container, CSS framework, JS framework, admin template, UI kit, or NoSQL primary store appears anywhere in this document; Redis is excluded from every transaction and lock path (§22 TI-07) | **PASS** |
| CC-17 | No product scope was added | 28 requirements in, 28 out. §04.2 restates the out-of-scope set; §09.13, §14.3, §17.3 RS-8, §19.1 JS-1, §21.2, §23.2 UR-1 and §11.0 each record what is deliberately **not** specified | **PASS** |
| CC-18 | No duplicate canonical definition and no new registry | §00 reading conventions and the ownership table fix every canonical owner; §24 references the acceptance matrix rather than restating it; TR-3 declares every local label non-authoritative; **0 registries created** | **PASS** |

**18 conditions evaluated. 18 PASS. 0 FAIL.**

## 27.1 Additional Cross-Checks Actually Run

| Check | Result |
|---|---|
| Every status value named in this document belongs to a SRC-001 §1.3 set | **PASS** — PO 5, SO 5, ledger 3, roles 3 |
| The token `Rejected` appears only in statements asserting its **absence** | **PASS** |
| Every entity named belongs to the 12-entity set | **PASS** |
| Every requirement ID used exists in `P1 §11` | **PASS** — 28 distinct, no others |
| Every `AC-*` referenced exists in `P1 §19.10` | **PASS** — 95, exact set match |
| Every `DEC-*` referenced exists in `P1 §04` | **PASS** — `DEC-001`…`DEC-016` |
| Every `DG-*` referenced exists in `P2 §24` | **PASS** — `DG-01`…`DG-06` |
| No adjustment workflow, actor, trigger, or authorization rule is specified | **PASS** — §14.3, BR-ADJ-1…4 |
| No partial-issue behaviour is specified | **PASS** — §11.11, BR-SO-8, §14.2 |
| No stock reservation is specified | **PASS** — BR-SO-9, §11.0 |
| No login lockout rule is specified | **PASS** — §21.2, `DG-04` |
| No chart or visualisation is specified | **PASS** — RS-8 |
| No automatic scheduling is specified | **PASS** — JS-1 |
| No placeholder token anywhere in the document | **PASS** — zero occurrences |

---

# 28. PHASE 3 EXIT STATUS

## 28.1 Exit Criteria Evaluation

| # | Mandatory item | Where | Evaluation | Result |
|---|---|---|---|---|
| X-01 | PRD complete | §01, §02, §03, §04 | Context, problem, vision, 9 objectives, 6 actors, 21 major capabilities, functional/business/NFR expectations, in-scope and out-of-scope sets. **Does not duplicate the canonical requirement matrix** | COMPLETE |
| X-02 | User Stories complete | §05 | 46 stories in the mandated form, each traced to existing requirement IDs. `US-017` finalized against `MSTR-01`. **No official requirement ID invented** | COMPLETE |
| X-03 | User Journeys complete | §06 | All 5 `P1 §14` journeys with per-step actor, specification location and references | COMPLETE |
| X-04 | Use Cases complete | §07 | All 19 mandated use cases with all 11 mandated fields, using the Phase 2 identifiers so no third namespace is created | COMPLETE |
| X-05 | Functional Requirements complete | §08 | 28 specifications, each with all 17 mandated fields, every statement traced to Phase 1 | COMPLETE |
| X-06 | Business Rules complete | §09 | 78 rules across all 12 mandated domains, each citing its canonical owner. **No second business rule authority** | COMPLETE |
| X-07 | Workflow / State Specification complete | §10 | 10 workflows with all 10 mandated fields, plus the complete transition summary from `P1 §19.3` | COMPLETE |
| X-08 | Data Specification complete | §11 | All 12 entities with all 8 mandated fields, plus the Requirement / Design Detail / Design Gap distinction preserved in §11.0. **No speculative field** | COMPLETE |
| X-09 | Validation Specification complete | §12 | Frontend / backend / database layers with the backend authoritative; 61 field records with all 10 mandated columns | COMPLETE |
| X-10 | Authorization / SoD complete | §13 | Authentication, role, capability, ownership, condition, server enforcement, failure response and SoD — with **Sales denied approval entirely, not own-order-only** | COMPLETE |
| X-11 | Stock Specification complete | §14 | Goods Receipt, Goods Issue and Adjustment with all 11 mandated fields; all 4 named invariants preserved; **no adjustment workflow invented** | COMPLETE |
| X-12 | Search / Filter / Sort / Pagination complete | §15 | Product, PO and SO with search fields, filters, sort options, pagination and persistent filters. **No extra capability invented** | COMPLETE |
| X-13 | Dashboard Specification complete | §16 | All 7 role-specific metrics with all 8 mandated fields. **No static values** | COMPLETE |
| X-14 | Report Specification complete | §17 | Both CSV reports with date range, filters, columns, aggregation, authorization and failure behaviour; stock movement from `StockLedger`; **one aggregation definition** | COMPLETE |
| X-15 | API Specification complete | §18 | All 10 mandated aspects; JSON on every status. **Not implemented** | COMPLETE |
| X-16 | Low-Stock Script Specification complete | §19 | All 9 mandated aspects. **No automatic scheduling** | COMPLETE |
| X-17 | Error Specification complete | §20 | All 8 mandated conditions plus deadlock and wrong-method, across HTML, API and CLI, with the required baseline behaviours and 8 non-disclosure rules | COMPLETE |
| X-18 | Security Specification complete | §21 | All 11 mandated concerns as 24 controls, each traced to `P1 §19.5` except `SS-13`, declared as `DG-01`. **No unrelated control invented** | COMPLETE |
| X-19 | Technical Implementation Specification complete | §22 | All 13 mandated boundaries with must / must not / detection, plus the full prohibition set. **Nothing implemented, nothing redesigned** | COMPLETE |
| X-20 | UI / UX Specification Boundary complete | §23 | All 28 requirements mapped to existing screen, required behaviour, existing coverage and required adjustment; both non-mandatory findings carried. **No redesign** | COMPLETE |
| X-21 | Acceptance Specification complete | §24 | 95 of 95 criteria certified covered and observable, **referenced not duplicated**, none created or altered | COMPLETE |
| X-22 | Implementation Traceability complete | §25 | All 28 requirements, all 16 decisions, all 3 assumptions traced with design and matrix references. **No new registry** | COMPLETE |
| X-23 | Design Gap Handling complete | §26 | All 6 findings preserved with their classification; 7 non-promotion certifications | COMPLETE |
| X-24 | Completeness Check PASS | §27 | 18 of 18 conditions PASS, plus 14 cross-checks | **PASS** |

**24 mandatory items. 24 complete. 0 failed.**

## 28.2 Non-Expansion Confirmation

| Constraint from Phase 3 §30 | Confirmation |
|---|---|
| Do not rewrite Phase 1 or Phase 2 | Both were read, neither modified. Requirement count, decision count, transitions, statuses, roles and business rules are unchanged |
| Do not execute implementation | No application code, no migration, no test file, no Dockerfile, no compose file, no UI asset was written |
| Do not invent requirements, requirement IDs or decisions | **0 requirements, 0 requirement IDs, 0 decisions, 0 sources, 0 assumptions created** |
| **Do not silently resolve `ASM-001`** | **OPEN — MANDATORY**, in 9 locations, with a dedicated handling rule and no substitute status |
| **Do not silently promote design gaps into requirements** | All 6 preserved as design findings; NP-1…NP-7 certify absence from the requirement, business-rule and acceptance sections |
| Do not add product scope | §04.2 plus eight explicit "not specified" records |
| Do not create duplicate canonical definitions or duplicate registries | §00 ownership table; §24 references rather than restates; TR-3 declares local labels non-authoritative |
| Do not leave broken references | §27.1 verified every requirement, `AC-*`, `DEC-*`, `DG-*` and matrix reference resolves |
| Do not leave a placeholder | Zero placeholder tokens |
| Do not stop halfway | All 28 sections produced |

## 28.3 Remaining Issues (nothing withheld)

| ID | Reference | Description | Impact | Status |
|---|---|---|---|---|
| **`ASM-001`** | SO-01, `DEC-009`, `P1 §06` | A rejected Sales Order terminates at `Cancelled` | One enum value, one return value at the single definition point, one `P1 §19.3` transition row, one UI badge. The specification confines it to exactly this | **OPEN — MANDATORY** |
| `DG-01` | `P2 §24`, §21 `SS-13` | CSRF control originates from the Phase 2 prompt §16 and `DEC-005`, not from `P1 §19.5` | None to product scope; bounded to state-changing HTML forms | DESIGN FINDING — declared |
| `DG-02`, `DG-03`, `DG-04` | `P2 §24`, §11.0, §26 | Three design additions no requirement names | None; a verification aid, an optional column, and an inert marker | DESIGN FINDING — declared |
| `DG-05` | `DISC-003`, §23.1 | Eleven UI-baseline inconsistencies | UI only; one decision per instance at implementation | CARRIED — non-mandatory |
| `DG-06` | `GAP-007`, `ASM-003`, §23.1 | Responsive sidebar coverage on product screens | Per-screen CSS only | CARRIED — non-mandatory |
| `CAND-060`, `CAND-061`, `CAND-062` | `P1 §31.3` | Pre-coding candidates with no mandating brief clause | None — no story, requirement, or specification was written for them | UNASSIGNED — out of scope |

Open mandatory specification gaps: **0**. Open mandatory assumptions: **1** (`ASM-001`, carried from
Phase 1, not created here).

## 28.4 Declaration

**PHASE 3 STATUS: COMPLETE.**

All 24 mandatory exit items are complete. The Completeness Check passes on 18 of 18 conditions plus
14 cross-checks. No Phase 1 requirement, business rule, role, scope, status or decision was changed;
no Phase 2 architecture element was changed; and no requirement, requirement ID, decision, source,
assumption or registry was created.

`ASM-001` remains **OPEN and MANDATORY**, exactly as Phase 1 left it and Phase 2 carried it. The
specification uses it, names it at every point of use, and confines it to a single definition point,
so a trainer ruling costs precisely the blast radius Phase 1 recorded. All six Phase 2 design
findings remain identifiable as design findings; `DG-04` in particular is specified as an inert
mechanism and explicitly **not** a login lockout requirement.

This document is the frozen, implementation-ready specification. Implementation may not add a
requirement, change a business rule, widen an authorization rule, introduce a prohibited technology,
invent an adjustment workflow, or resolve `ASM-001` on its own authority. Any such need is a new
decision record in `phase1-baseline.md` §04 first.

**Phase 3 is frozen as of this document.**

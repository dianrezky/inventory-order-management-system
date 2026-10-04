# PRE-CODING PRODUCT & SYSTEM ANALYSIS

Project: **Inventory & Order Management System — Intermediate Programmer Final Project**
Primary source: **`Project Brief - Programmer.pdf`** (Participant Guide Edisi 1.0, Oktober 2026, 19 pages, read in full)
Phase: **PRE-CODING PRODUCT & SYSTEM ANALYSIS** — predecessor to Phase 1
Status vocabulary used: `CANDIDATE` · `CANDIDATE — UNASSIGNED` · `MAPPED`

This phase **discovers and models**. It freezes nothing, creates no registry, and assigns no final
approved requirement status. Where the brief is silent, no requirement is invented; the absence is
recorded in §18 Analysis Gap Review. Where interpretation was necessary it is marked
`[ANALYSIS INTERPRETATION]`.

Requirement IDs quoted below (`AUTH-01`, `PO-01`, `ARCH-02`, …) are **the brief's own official
IDs**, read from §2 and §3 of the source document. No ID was invented.

---

# 01. PRODUCT CONTEXT

## 1.1 Source-established context

| Attribute | Value (from brief) |
|---|---|
| Product | Aplikasi web Inventory & Order Management System, 3 peran, multi-gudang |
| Owning organisation | PT Neuronworks Indonesia |
| Document | Participant Guide, Edisi 1.0, Program Pengembangan Kompetensi Programmer, Oktober 2026 |
| Work model | Individual — source code, repository, dan evidence dibuat sendiri |
| Core technology | HTML, CSS, Vanilla JS + Fetch API, PHP 8.2+ Native OOP berlapis, MySQL 8, Docker Compose, PHPUnit, static analysis |
| Core flow | Login → Master Data → Purchase Order → Sales Order → Stock Ledger → Dashboard/Laporan → Logout |
| Pass condition | Nilai minimal 80 dan tidak terkena critical failure |

## 1.2 Problem statement

Quoted basis (brief §1): *"Tim gudang dan sales membutuhkan aplikasi untuk mencatat produk,
mengelola stok di beberapa gudang, memproses pembelian dari supplier serta penjualan ke customer,
dan memastikan angka stok selalu bisa dipertanggungjawabkan — termasuk saat dua proses berjalan
bersamaan."*

Decomposed into four distinct problems:

| # | Problem | Consequence if unsolved |
|---|---|---|
| P-1 | Stock is held across multiple warehouses with no single record of per-warehouse quantity | Stock figures cannot be trusted per location |
| P-2 | Purchases from suppliers and sales to customers are not tracked through a controlled lifecycle | Orders cannot be audited or reconciled |
| P-3 | Stock numbers are not accountable — no traceable link between a movement and the resulting quantity | Discrepancies cannot be explained |
| P-4 | Two processes running concurrently can corrupt stock (oversell) | Physical and recorded stock diverge |

The brief adds a fifth, organisational problem (§1): *"tiga peran dengan tanggung jawab yang sengaja
dipisah agar tidak ada satu peran yang bisa membuat sekaligus menyetujui transaksi yang sama."*

| # | Problem | Consequence if unsolved |
|---|---|---|
| P-5 | One person could both create and approve the same transaction | Transaction flow can be abused by a single actor |

## 1.3 Assessment context

This is a competency-assessment product. The brief states the assessment intent explicitly (§1,
"Tujuan"): to prove the participant can design a testable layered architecture, preserve data
integrity under concurrent operations, apply Clean Code and Clean Architecture in substance
*"bukan sekadar istilah di README"*, and communicate design decisions clearly.

Two brief-stated postures shape the analysis and are carried forward:

- **Anti over-engineering** (brief §0 PERINGATAN): *"Solusi yang lebih rumit tanpa alasan jelas
  (pattern yang dipasang tanpa dipahami, layer tambahan yang tidak menyelesaikan masalah nyata)
  dinilai negatif pada area code quality, sama seperti kode yang berantakan."*
- **Build order** (brief §2): vertical slice first — login → master data → purchase order → sales
  order → dashboard/laporan; then API, image upload, scheduled job.

## 1.4 Critical failure context

The brief §8.2 defines ten critical failure conditions. Recorded here as product context because
they constrain what "done" means; they are not restated as requirements.

| # | Critical failure condition (brief §8.2) |
|---|---|
| CF-1 | Aplikasi atau database tidak dapat dijalankan lewat Docker setelah prosedur setup yang wajar |
| CF-2 | Alur inti login–PO/SO–stok tidak berfungsi, atau fitur inti hanya tampilan tanpa proses/data nyata |
| CF-3 | Project menggunakan framework backend/frontend, ORM, atau DI container framework yang dilarang |
| CF-4 | Tidak ada unit test dan integration test yang valid, atau seluruh test gagal pada release final |
| CF-5 | Password plaintext, secret aktif masuk repository, query menggabungkan input user secara mentah, atau authorization hanya di frontend |
| CF-6 | Stok diubah langsung tanpa melalui service/ledger, sehingga StockLedger tidak konsisten dengan ProductStock |
| CF-7 | Goods issue/receipt tidak transaksional, sehingga oversell dapat direproduksi assessor pada saat defense |
| CF-8 | Class diagram tidak mencerminkan kode aktual dan tidak dapat ditelusuri saat defense |
| CF-9 | Peserta tidak mampu menjelaskan arsitektur/keputusan desain sendiri, atau evidence menunjukkan plagiarisme |
| CF-10 | Penggunaan AI atau sumber eksternal yang material sengaja disembunyikan |

## 1.5 Success criteria

| Criterion | Source |
|---|---|
| Nilai minimal 80 | brief cover table, §8.2 "Status project" |
| Tidak terdapat critical failure | brief §8.2 |
| Seluruh requirement wajib §2 diuji pada release/tag final | brief §10 |
| Aplikasi dan database dapat dijalankan lewat Docker dari folder bersih | brief §10, §5.1 |
| Unit + integration test dapat dijalankan dengan satu perintah dan seluruhnya lulus | brief §10 |
| Static analysis report nol critical error | brief §10, TEST-03 |
| Class diagram initial dan as-built tersedia dan sesuai kode aktual | brief §10, DESIGN-01 |
| Goods issue/receipt terbukti transaksional; skenario oversell tidak dapat direproduksi | brief §10, ARCH-02 |
| Segregation of duties teruji di server, bukan hanya UI | brief §10, §1.2 |
| Bonus tidak dapat menutup requirement wajib yang tidak berfungsi | brief §4.4, FAQ 10 |

---

# 02. PRODUCT VISION & OBJECTIVES

## 2.1 Product vision

> A web-based inventory and order management system in which every stock figure is accountable to a
> traceable ledger entry, purchase and sales transactions move through controlled lifecycles, and no
> single role can both create and approve the same transaction — remaining correct when two stock
> operations run at the same time.

Derived from brief §1 and §1.3 "Keputusan data". No capability is asserted that the brief does not state.

## 2.2 Product objectives

| Group | Objective | Brief basis |
|---|---|---|
| Functional | Record products and manage stock across multiple warehouses | §1, WH-01 |
| Functional | Process supplier purchases through PO lifecycle and goods receipt | §1, PO-01 |
| Functional | Process customer sales through SO lifecycle, approval, and goods issue | §1, SO-01 |
| Functional | Present role-appropriate dashboards and CSV reports computed from data | §1 item 6, DASH-01, REPORT-01 |
| Functional | Expose at least one JSON API endpoint as a contract separate from HTML pages | API-01 |
| Functional | Provide a standalone low-stock summary script outside the web request cycle | JOB-01 |
| Data Integrity | Every stock movement is recorded on the stock ledger; ProductStock stays consistent with StockLedger | §1 item 6, §1.3, CF-6 |
| Data Integrity | Stock quantity never negative | §1.3 (`quantity >= 0`), ARCH-02 |
| Data Integrity | Concurrent goods issues must not oversell or lose updates | ARCH-02 |
| Data Integrity | Products, suppliers, and customers are deactivated, not permanently deleted | §1.3 "Keputusan data", PRD-01 |
| Security | Authorization always enforced server-side, including segregation of duties | §1.2, §4.2, CF-5 |
| Security | Passwords stored with PHP password API; session ID renewed after login | AUTH-01, §4.2 |
| Security | All input-bearing queries use prepared statements; output escaped before HTML | §4.2, DB-01, CF-5 |
| Usability | Login, dashboard, list, detail, and form usable at 360px and desktop | UI-01 |
| Quality | Layered architecture with Dependency Inversion at the repository boundary | ARCH-01 |
| Quality | Business logic testable without a real database connection | ARCH-01 |
| Quality | Unit + integration tests and static analysis at brief minimums | TEST-01, TEST-02, TEST-03 |
| Assessment | Design decisions documented and defensible — class diagrams, ADR, refactoring log | DESIGN-01…DESIGN-04 |
| Assessment | Reproducible from a clean environment via Docker Compose | §5.1 |

---

# 03. ACTORS

## 3.1 Actor catalogue

Three human actors. The brief fixes the role values (§1.3): `Admin / Sales / WarehouseStaff`.

| Actor | Brief role value | Responsibility summary (brief §1.1, §1.2) |
|---|---|---|
| Admin | `Admin` | Manages users and all master data; reviews and approves/rejects Sales Orders; creates Purchase Orders; may process goods receipt and goods issue; sees all dashboard data |
| Sales | `Sales` | Creates and submits own Sales Orders from catalogue and available stock; views catalogue; sees own-order summary; downloads own-order report |
| Warehouse Staff | `WarehouseStaff` | Processes goods receipt (PO) and goods issue (SO); may propose Purchase Orders; views products and stock; sees stock and fulfillment summary; downloads stock report |

`[ANALYSIS INTERPRETATION]` The brief writes the role **value** as `WarehouseStaff` (§1.3, no space)
and the role **label** as "Warehouse Staff" (§1.1, §1.2, with space). Treated here as one actor with
a display label and a stored enum value. Recorded as GAP-004 for Phase 1 to fix canonically.

## 3.2 Non-human actors

| Actor | Nature | Brief basis |
|---|---|---|
| Scheduled Script Operator | Manual invoker of the standalone low-stock script via `docker compose exec` | JOB-01 |
| API Consumer | Authenticated caller of the JSON endpoint | API-01 |

`[ANALYSIS INTERPRETATION]` The brief names no external system integration. The API consumer is
modelled as an actor because API-01 requires authentication *"diperiksa sama seperti halaman biasa"*
— i.e. it acts with a user session, not as an anonymous third-party system.

## 3.3 Segregation of duties — actor constraint

The brief states this as a process decision (§1.2, "Keputusan proses"):

> *"Sales yang membuat order tidak boleh menyetujui order yang sama, meskipun endpoint approve tetap
> tersedia di aplikasi untuk digunakan peran Admin. Aturan ini wajib ditegakkan pada authorization
> layer di server, bukan hanya disembunyikan lewat UI."*

Two distinct constraints follow, both carried into §08 and §16:

- **SoD-1** — Sales cannot approve any Sales Order, *including its own* (§1.2 table: "Tidak, meski order miliknya sendiri"; SO-01: "Sales tidak dapat menyetujui order — termasuk order miliknya sendiri").
- **SoD-2** — Enforcement lives in the server authorization layer, not UI visibility.

`[ANALYSIS INTERPRETATION]` The brief's §1.2 table denies Sales approval outright, and SO-01 adds
"termasuk order miliknya sendiri". Read together, Sales has **no** approve capability at all — the
own-order clause is an emphasis, not a narrower carve-out. Recorded as GAP-005 for Phase 1 to state
canonically.

---

# 04. SCOPE & OUT OF SCOPE

## 4.1 In scope — mandatory requirement areas (brief §2, §3)

| Area | Brief IDs |
|---|---|
| Authentication & User | AUTH-01, AUTH-02, USR-01 |
| Master Data | PRD-01, WH-01 |
| Purchase Order & Goods Receipt | PO-01 |
| Sales Order, Approval & Goods Issue | SO-01 |
| List, Search, Dashboard & Report | VIEW-01, FIND-01, DASH-01, REPORT-01 |
| API | API-01 |
| Validation, Error, UI, Database, Job | VAL-01, ERR-01, UI-01, DB-01, JOB-01 |
| Architecture & Quality | ARCH-01, ARCH-02 |
| Design evidence | DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04 |
| Testing | TEST-01, TEST-02, TEST-03 |

**26 official IDs** total, read from the brief.

## 4.2 Out of scope (brief §4.3, verbatim list)

```text
microservices
message queue sungguhan
cloud deployment
CI/CD
Kubernetes
real-time notification
mobile application
cron scheduler otomatis
automated end-to-end test
```

Additional brief-stated exclusion: **no public registration** — *"Tidak ada public registration;
seluruh akun dibuat oleh Admin"* (USR-01, FAQ 11).

## 4.3 Bonus — assessed only after mandatory requirements are stable (brief §4.4)

```text
notifikasi email simulasi (mis. Mailhog di Docker)
audit trail perubahan master data
dashboard grafik (SVG/canvas buatan sendiri)
integration test tambahan
```

*"Bonus tidak dapat menutup requirement wajib yang tidak berfungsi."*

## 4.4 Technology constraints (brief §4)

| Area | Wajib | Diperbolehkan | Tidak diperbolehkan |
|---|---|---|---|
| Frontend | HTML semantik, CSS buatan peserta, Vanilla JS | Fetch API, library icon yang dicantumkan | React, Vue, Angular, jQuery, framework CSS, template admin siap pakai |
| Backend | PHP 8.2+ Native, OOP berlapis, DIP pada boundary repository | Composer untuk autoload & dev dependency | Laravel, CodeIgniter, Symfony, Slim, ORM, generator CRUD, DI container framework |
| Database | MySQL 8, relasi, constraint, index, PDO prepared statement, transaksi eksplisit | Migration/seed buatan peserta | NoSQL sebagai penyimpanan utama, query concatenation input user |
| Environment | Dockerfile, Docker Compose, .env | Apache/Nginx sesuai rancangan | Setup yang hanya berjalan di komputer peserta |
| Testing | PHPUnit: unit + integration test, static analysis report | Test tambahan lain | Test trivial getter/setter, test yang hanya lulus karena di-skip |
| Desain | Class diagram initial & as-built, ADR, refactoring log | Tool diagram apa pun yang legible | Diagram yang tidak sesuai kode aktual |

## 4.5 Application structure (brief §4.1)

Folder names may differ; the separation must hold:

```text
public/                        entry point & static asset
app/Controller
app/Service
app/Repository
app/Entity                     four separate responsibilities per ARCH-01
views/                         template antarmuka
config/                        loader environment
database/                      schema, seed
tests/Unit
tests/Integration              explicitly separated
docs/planning/
docs/architecture/
docs/quality/
docs/testing/
```

## 4.6 Minimum demo data (brief §7.1)

```text
1 Admin account
>= 2 Sales accounts
>= 2 Warehouse Staff accounts
>= 2 warehouses
30 products with reorder-point variation, some below reorder point
>= 25 combined orders (PO+SO) with status variation, including PendingApproval and Cancelled
```

FIND-01 restates the seed floor: *"minimal 30 produk dan 25 order gabungan agar pagination dapat diuji."*

---

# 05. PRD

Product-level narrative. Not the Phase 1 canonical requirement baseline.

## 5.1 Product purpose

Give warehouse and sales teams one system in which product data, multi-warehouse stock, supplier
purchases, and customer sales are recorded so that every stock figure can be justified against a
ledger entry, and no single role can both originate and approve the same transaction.

## 5.2 Users / actors

Per §03: Admin, Sales, Warehouse Staff. Accounts are provisioned by Admin only.

## 5.3 Core capabilities

| # | Capability | Actors |
|---|---|---|
| C-01 | Authenticate and hold a role-scoped session | All |
| C-02 | Terminate session | All |
| C-03 | Manage user accounts and activation | Admin |
| C-04 | Manage product catalogue, categories, reorder points, optional images | Admin |
| C-05 | Manage warehouses | Admin |
| C-06 | Manage suppliers and customers | Admin |
| C-07 | View products and per-warehouse stock | All (scoped) |
| C-08 | Create Purchase Orders | Admin; Warehouse Staff may propose |
| C-09 | Record goods receipt, full or partial | Admin, Warehouse Staff |
| C-10 | Create and submit Sales Orders | Sales (own), Admin |
| C-11 | Approve or reject Sales Orders | Admin only |
| C-12 | Record goods issue against approved Sales Orders | Admin, Warehouse Staff |
| C-13 | Inspect stock ledger movements | Admin; Warehouse Staff (stock scope) |
| C-14 | View role-scoped dashboard computed by aggregation | All (scoped) |
| C-15 | Export CSV of stock movement and order status by date range | All (scoped) |
| C-16 | Call JSON availability endpoint | Authenticated |
| C-17 | Run standalone low-stock summary script | Script operator |
| C-18 | List, detail, search, filter, sort, paginate | All (scoped) |

## 5.4 Major user needs

| Need | Actor | Brief basis |
|---|---|---|
| Know how much of a product is in each warehouse | Warehouse Staff, Sales, Admin | WH-01 |
| Raise a purchase when stock is low | Admin, Warehouse Staff | §1.1 item 5, JOB-01 |
| Record partial deliveries without losing the outstanding remainder | Warehouse Staff | PO-01 |
| Submit a customer order and know its approval state | Sales | SO-01 |
| Approve or reject an order under review | Admin | SO-01 |
| Fulfil an approved order without oversell | Warehouse Staff | SO-01, ARCH-02 |
| Explain any stock figure from its movement history | Admin | §1.3, CF-6 |
| See a role-appropriate operational summary | All | DASH-01 |
| Extract movement and order data for a period | All (scoped) | REPORT-01 |
| Query availability programmatically | API consumer | API-01 |

## 5.5 Major business problems addressed

P-1…P-5 per §1.2.

## 5.6 Product scope / out of scope

Per §04.

## 5.7 Major workflows

Per §07 User Journeys and §09 Business Flows. Brief core flow:
`Login → Master Data → Purchase Order → Sales Order → Stock Ledger → Dashboard/Laporan → Logout`.

## 5.8 Business expectations

| Expectation | Brief basis |
|---|---|
| Stock is never edited directly from the UI — only through the service that writes StockLedger then updates ProductStock in one transaction | §1.3 "Keputusan data" |
| Products/suppliers/customers used by orders are deactivated, never hard-deleted | §1.3, PRD-01 |
| Dashboard and report figures come from aggregation queries, never static values | §1.1 item 6, DASH-01, REPORT-01 |
| Report and dashboard use the same aggregation/recap basis | REPORT-01 |
| Partial receipt is allowed and the unreceived remainder stays recorded | PO-01 |
| Goods issue is rejected when available stock is insufficient | SO-01 |
| Approval authority is checked at the server | §1.2, SO-01, §4.2 |
| Failed validation stores nothing; already-entered input is preserved where relevant | VAL-01 |
| Backend is the source of truth for validation | VAL-01 |
| Credential errors give a safe message that does not reveal which part was wrong | AUTH-01 |
| Inactive users cannot log in | AUTH-01 |
| Database exceptions and stack traces are never shown to users | ERR-01 |

## 5.9 Initial NFR expectations

Per §15. Summary: security, reliability/data integrity, usability at 360px + desktop,
maintainability, testability, reproducibility, deployment via Docker Compose. The brief sets **no**
latency, throughput, availability, or scalability target, so none is asserted.

---

# 06. USER STORIES

`US-###` are analysis identifiers. The "Brief ID" column records correspondence to an official brief
requirement where the brief itself establishes it; `CANDIDATE — UNASSIGNED` marks a story with no
single official ID.

| ID | Story | Brief ID | Status |
|---|---|---|---|
| US-001 | As a User, I want to log in with email and password, so that I can access the features for my role. | AUTH-01 | MAPPED |
| US-002 | As a User, I want a safe error message on failed login, so that no attacker learns which credential part was wrong. | AUTH-01 | MAPPED |
| US-003 | As the System Owner, I want inactive accounts refused at login, so that revoked staff cannot enter. | AUTH-01 | MAPPED |
| US-004 | As the System Owner, I want protected pages unreachable without a session, so that data is not exposed. | AUTH-01 | MAPPED |
| US-005 | As the System Owner, I want the session ID renewed after login, so that session fixation is prevented. | AUTH-01 | MAPPED |
| US-006 | As a User, I want to log out, so that my session cannot be reused on a shared machine. | AUTH-02 | MAPPED |
| US-007 | As an Admin, I want to create, view, edit, activate and deactivate Sales and Warehouse Staff accounts, so that access matches current staffing. | USR-01 | MAPPED |
| US-008 | As an Admin, I want duplicate emails rejected, so that identity stays unique. | USR-01 | MAPPED |
| US-009 | As the System Owner, I want Sales and Warehouse Staff blocked from user-administration pages and endpoints, so that privilege cannot escalate. | USR-01 | MAPPED |
| US-010 | As an Admin, I want to manage products with unique SKU, category, unit, buy/sell price and reorder point, so that the catalogue drives all transactions. | PRD-01 | MAPPED |
| US-011 | As an Admin, I want numeric product values validated as >= 0, so that impossible prices and thresholds cannot be stored. | PRD-01, VAL-01 | MAPPED |
| US-012 | As an Admin, I want a product already used on an order to be deactivatable but not deletable, so that order history stays intact. | PRD-01 | MAPPED |
| US-013 | As an Admin, I want to upload an optional product image with type and size validation stored under an unguessable random name, so that upload cannot be abused. | PRD-01 | MAPPED |
| US-014 | As an Admin, I want to manage categories, so that products can be classified and filtered. | PRD-01 | MAPPED |
| US-015 | As an Admin, I want to manage warehouses, so that stock can be held per location. | WH-01 | MAPPED |
| US-016 | As a Warehouse Staff, I want to see total stock and the per-warehouse breakdown for a product, so that I know where the goods are. | WH-01 | MAPPED |
| US-017 | As an Admin, I want to manage suppliers and customers with active status, so that orders reference valid counterparties. | §1.3 | CANDIDATE — UNASSIGNED |
| US-018 | As an Admin or Warehouse Staff, I want to create a Purchase Order with supplier, destination warehouse and items, so that low stock can be replenished. | PO-01 | MAPPED |
| US-019 | As a Warehouse Staff, I want to record a goods receipt that increases stock and writes a Receipt ledger row in one transaction, so that arrivals are accounted for. | PO-01, ARCH-02 | MAPPED |
| US-020 | As a Warehouse Staff, I want to record a partial receipt with the outstanding quantity still tracked, so that split deliveries are handled. | PO-01 | MAPPED |
| US-021 | As a Sales, I want to create a Draft Sales Order from the catalogue and available stock, so that a customer order can be prepared. | SO-01 | MAPPED |
| US-022 | As a Sales, I want to submit my Draft order so it becomes PendingApproval, so that it enters review. | SO-01 | MAPPED |
| US-023 | As an Admin, I want to approve or reject a PendingApproval order, so that only authorised orders proceed. | SO-01 | MAPPED |
| US-024 | As the System Owner, I want the server to refuse approval by Sales — including the creator's own order, so that duties stay segregated. | SO-01, §1.2 | MAPPED |
| US-025 | As a Warehouse Staff, I want to issue goods only for Approved orders, so that unauthorised fulfilment is impossible. | SO-01 | MAPPED |
| US-026 | As a Warehouse Staff, I want a goods issue refused when available stock is insufficient, so that stock never goes negative. | SO-01, ARCH-02 | MAPPED |
| US-027 | As the System Owner, I want two near-simultaneous goods issues on the same product and warehouse to leave a correct final stock with no oversell and no lost update, so that concurrent operations are safe. | ARCH-02 | MAPPED |
| US-028 | As an Admin, I want every stock movement recorded on the ledger with type, quantity, reference and actor, so that any figure can be traced. | §1.3, DB-01 | MAPPED |
| US-029 | As a User, I want products, POs and SOs as role-scoped lists and detail pages with an informative empty state, so that I can navigate data of any size. | VIEW-01 | MAPPED |
| US-030 | As a User, I want product search by name/SKU and filters for category and stock status, so that I can find items quickly. | FIND-01 | MAPPED |
| US-031 | As a User, I want order search by number/counterparty, status filter and date sort, so that I can locate orders. | FIND-01 | MAPPED |
| US-032 | As a User, I want 10-per-page pagination that keeps filters active across pages, so that large lists stay usable. | FIND-01 | MAPPED |
| US-033 | As an Admin, I want a dashboard showing inventory value, products below reorder point and pending orders per status, so that I can see operational state. | DASH-01 | MAPPED |
| US-034 | As a Sales, I want a dashboard summarising my own orders per status, so that I can track my pipeline. | DASH-01 | MAPPED |
| US-035 | As a Warehouse Staff, I want a dashboard showing goods receipt/issue queues and low-stock products, so that I know today's work. | DASH-01 | MAPPED |
| US-036 | As the System Owner, I want every dashboard figure produced by aggregation query rather than a static value, so that the display reflects reality. | DASH-01 | MAPPED |
| US-037 | As a User, I want to export stock movement and order status CSV for a date range, so that data can be analysed outside the app. | REPORT-01 | MAPPED |
| US-038 | As an API Consumer, I want `GET /api/products/{sku}/availability` returning per-warehouse stock as JSON with correct 200/401/404 status, so that other software can query availability. | API-01 | MAPPED |
| US-039 | As a User, I want required fields, enums, dates, foreign keys and numbers validated on both frontend and backend with the backend authoritative, so that bad data cannot be stored. | VAL-01 | MAPPED |
| US-040 | As a User, I want unauthenticated access redirected to login, unauthorised access to return 403 and missing data to return 404, so that failures are predictable. | ERR-01 | MAPPED |
| US-041 | As the System Owner, I want database exceptions and stack traces hidden from users, so that internals are not leaked. | ERR-01 | MAPPED |
| US-042 | As a User, I want login, dashboard, lists, detail and forms usable at 360px and desktop with labelled fields and visible focus/contrast, so that the app is usable on any screen. | UI-01 | MAPPED |
| US-043 | As the System Owner, I want a relational schema with PK, FK, `quantity >= 0` constraint and relevant indexes, all access via prepared statements and multi-table stock operations in explicit transactions, so that data integrity holds. | DB-01 | MAPPED |
| US-044 | As the System Owner, I want schema and seed able to build the database from empty including FIND-01 test data, so that the environment is reproducible. | DB-01 | MAPPED |
| US-045 | As a Script Operator, I want a standalone script summarising products below reorder point, runnable manually via Docker, so that low stock can be checked outside the web cycle. | JOB-01 | MAPPED |
| US-046 | As the System Owner, I want business logic independent of PDO, session and superglobals with a repository interface having real and fake implementations, so that logic is testable without a database. | ARCH-01 | MAPPED |

46 stories. All trace to the brief; one (`US-017`) has no single official ID because suppliers and
customers appear in the §1.3 data table and are referenced by PO-01/SO-01 without their own
requirement ID — recorded as GAP-001.

---

# 07. USER JOURNEYS

Twelve journeys, per the required investigation list.

## J-01 Authentication

| Field | Value |
|---|---|
| Actor | Admin / Sales / Warehouse Staff |
| Entry point | Login page |
| Preconditions | Account exists and is active |
| Main steps | Open login → enter email + password → submit → server verifies credential and active status → session established, session ID renewed → redirect to role dashboard |
| Decision points | Credential valid? · Account active? · Session present on protected page? |
| Success outcome | Role-appropriate dashboard displayed |
| Failure outcome | Safe generic message; no indication which part was wrong; inactive account refused; protected page without session redirected to login |
| Exit state | Authenticated session with role, or unauthenticated at login |

## J-02 Master Data

| Field | Value |
|---|---|
| Actor | Admin (manage); Sales and Warehouse Staff (view per §1.2) |
| Entry point | Master data list (product / category / warehouse / supplier / customer) |
| Preconditions | Authenticated as Admin for management |
| Main steps | Open list → search/filter → open detail or create form → enter data → validate frontend then backend → persist → return to list with feedback |
| Decision points | Unique SKU? · Unique email (user)? · Numeric values >= 0? · Entity already used by an order? · Image type/size valid? |
| Success outcome | Master record created, updated, or activation toggled |
| Failure outcome | Validation errors shown, nothing stored, entered input preserved where relevant; used entity may be deactivated but not deleted |
| Exit state | Master data reflects the change |

## J-03 Purchase Order

| Field | Value |
|---|---|
| Actor | Admin (create); Warehouse Staff (propose) |
| Entry point | Purchase Order list → create |
| Preconditions | Authenticated; active supplier, destination warehouse, and active products exist |
| Main steps | Detect low stock → create PO with supplier, destination warehouse, order date, items (product, qty, buy price) → save as Draft → order the PO → status Ordered |
| Decision points | Supplier selected? · Destination warehouse selected? · Item qty and price >= 0? · Cancel before completion? |
| Success outcome | PO exists at Ordered, awaiting receipt |
| Failure outcome | Validation blocks save; PO may be Cancelled |
| Exit state | PO status ∈ {Draft, Ordered, Cancelled} |

## J-04 Goods Receipt

| Field | Value |
|---|---|
| Actor | Warehouse Staff; Admin |
| Entry point | PO detail → record receipt |
| Preconditions | PO is Ordered or PartiallyReceived |
| Main steps | Open PO → enter received qty per line → validate against outstanding qty → single transaction: write StockLedger `Receipt` row + increase ProductStock → commit → PO status recomputed |
| Decision points | Received qty within outstanding? · All lines complete or partial? |
| Success outcome | Stock increased; ledger rows written; PO becomes PartiallyReceived or Received |
| Failure outcome | Transaction rolled back; stock and ledger unchanged; outstanding qty preserved |
| Exit state | PO status ∈ {PartiallyReceived, Received} |

## J-05 Sales Order

| Field | Value |
|---|---|
| Actor | Sales (own); Admin |
| Entry point | Sales Order list → create |
| Preconditions | Authenticated; active customer, source warehouse, active products exist |
| Main steps | Create SO with customer, source warehouse, items (product, qty, sell price) → save as Draft → submit → status PendingApproval |
| Decision points | Customer selected? · Source warehouse selected? · Qty and price >= 0? · Submit now or keep Draft? · Cancel? |
| Success outcome | SO at PendingApproval awaiting Admin review |
| Failure outcome | Validation blocks save; SO may be Cancelled before Fulfilled |
| Exit state | SO status ∈ {Draft, PendingApproval, Cancelled} |

## J-06 Sales Approval

| Field | Value |
|---|---|
| Actor | Admin only |
| Entry point | SO detail at PendingApproval |
| Preconditions | Authenticated as Admin; SO is PendingApproval |
| Main steps | Review SO → decide → approve (status Approved, approver recorded) or reject |
| Decision points | Requester role is Admin? (server-checked) · Approve or reject? |
| Success outcome | SO Approved and released to warehouse queue, or rejected |
| Failure outcome | Non-Admin attempt refused server-side with 403 — including a Sales user acting on their own order |
| Exit state | SO status ∈ {Approved, Cancelled, PendingApproval} |

## J-07 Goods Issue

| Field | Value |
|---|---|
| Actor | Warehouse Staff; Admin |
| Entry point | SO detail at Approved → issue goods |
| Preconditions | SO is Approved; source warehouse stock available |
| Main steps | Open Approved SO → confirm issue quantities → validate available stock in source warehouse → single race-safe transaction: write StockLedger `Issue` row + decrease ProductStock → commit → SO becomes Fulfilled |
| Decision points | SO status is Approved? · Available stock sufficient? · Concurrent issue in progress? |
| Success outcome | Stock decreased; ledger rows written; SO Fulfilled |
| Failure outcome | Insufficient stock → issue rejected, nothing mutated. Concurrent second issue → rejected or deferred; never oversell, never lost update |
| Exit state | SO status ∈ {Approved, Fulfilled} |

## J-08 Stock Inquiry

| Field | Value |
|---|---|
| Actor | Admin; Warehouse Staff; Sales (catalogue/stock view per §1.2) |
| Entry point | Product list or product detail |
| Preconditions | Authenticated |
| Main steps | Open product → view total stock and per-warehouse breakdown → optionally inspect ledger movements |
| Decision points | Below reorder point? |
| Success outcome | Total and per-warehouse quantities displayed, traceable to ledger rows |
| Failure outcome | Product not found → 404; no data → informative empty state |
| Exit state | Read-only; no state change |

## J-09 Dashboard

| Field | Value |
|---|---|
| Actor | All three roles, scoped |
| Entry point | Post-login landing |
| Preconditions | Authenticated |
| Main steps | Open dashboard → server runs aggregation queries scoped to role → render figures |
| Decision points | Which role scope applies? |
| Success outcome | Admin sees inventory value, below-reorder-point products, pending orders per status; Sales sees own orders per status; Warehouse Staff sees receipt/issue queues and low-stock |
| Failure outcome | Unauthenticated → login; no data → empty state |
| Exit state | Read-only |

## J-10 Report

| Field | Value |
|---|---|
| Actor | Admin (all); Sales (own orders); Warehouse Staff (stock report) |
| Entry point | Report page |
| Preconditions | Authenticated |
| Main steps | Choose report type and date range → server runs the same aggregation basis as the dashboard → stream CSV |
| Decision points | Report type? · Date range valid? · Role scope? |
| Success outcome | CSV downloaded, consistent with dashboard figures |
| Failure outcome | Invalid range → validation message; unauthorised scope → 403 |
| Exit state | Read-only |

## J-11 Product Availability API

| Field | Value |
|---|---|
| Actor | API Consumer (authenticated) |
| Entry point | `GET /api/products/{sku}/availability` |
| Preconditions | Valid session, as for HTML pages |
| Main steps | Send request with SKU → server authenticates → look up product by SKU → return per-warehouse stock as JSON |
| Decision points | Authenticated? · SKU exists? |
| Success outcome | `200` with `Content-Type: application/json` and per-warehouse stock |
| Failure outcome | `401` unauthenticated; `404` SKU not found — JSON body, never an HTML error page |
| Exit state | Read-only |

## J-12 Low-Stock Check

| Field | Value |
|---|---|
| Actor | Script Operator |
| Entry point | `php scripts/check-low-stock.php` via `docker compose exec` |
| Preconditions | Container running; database reachable |
| Main steps | Invoke script → read products and stock → compare aggregate/warehouse stock against reorder point → print summary |
| Decision points | Which products are below reorder point? |
| Success outcome | Summary of below-reorder-point products printed |
| Failure outcome | Handled error output; no stack trace |
| Exit state | Read-only; no automatic scheduling |

---

# 08. USE CASES

`UC-###` identifiers are used for use cases only. `REQ-*`, `SRC-*`, `DEC-*` are not reused.

## UC-001 Login

| Field | Value |
|---|---|
| Actor | Admin / Sales / Warehouse Staff |
| Goal | Obtain a role-scoped authenticated session |
| Preconditions | Account exists and is active |
| Trigger | Submit login form |
| Main flow | Receive email+password → find user by email → verify with `password_verify()` → check active status → establish session and renew session ID → redirect to role dashboard |
| Alternative flow | Already authenticated → go to dashboard |
| Failure flow | Invalid credential → safe generic message, no field disclosure. Inactive account → refused. Protected page without session → redirect to login |
| Postconditions | Session holds user identity and role |
| Business outcome | Role-appropriate access granted |
| Related story | US-001, US-002, US-003, US-004, US-005 |

## UC-002 Logout

| Field | Value |
|---|---|
| Actor | Any authenticated user |
| Goal | End the session |
| Preconditions | Authenticated |
| Trigger | Activate logout |
| Main flow | Clear authentication data from session → redirect to login |
| Alternative flow | — |
| Failure flow | Protected URL reopened after logout → redirect to login |
| Postconditions | No authenticated session |
| Business outcome | Session cannot be reused |
| Related story | US-006 |

## UC-003 Manage User

| Field | Value |
|---|---|
| Actor | Admin |
| Goal | Provision and control Sales / Warehouse Staff accounts |
| Preconditions | Authenticated as Admin |
| Trigger | Open user administration |
| Main flow | List users → create or edit with name, email, password, role, active status → validate email uniqueness and role enum → persist with hashed password |
| Alternative flow | Toggle active status instead of editing |
| Failure flow | Duplicate email → rejected. Invalid role → rejected. Non-Admin access to page or endpoint → 403 |
| Postconditions | User set reflects the change |
| Business outcome | Access matches staffing; no public registration |
| Related story | US-007, US-008, US-009 |

## UC-004 Manage Product

| Field | Value |
|---|---|
| Actor | Admin |
| Goal | Maintain the catalogue used by all transactions |
| Preconditions | Authenticated as Admin; category exists |
| Trigger | Open product create/edit |
| Main flow | Enter SKU, name, category, unit, buy price, sell price, reorder point, optional image, active status → validate SKU uniqueness and numeric >= 0 → validate image type and size, store under random name → persist |
| Alternative flow | Deactivate a product already used on an order |
| Failure flow | Duplicate SKU → rejected. Negative number → rejected. Invalid image → rejected. Delete attempt on used product → refused, deactivation offered |
| Postconditions | Catalogue reflects the change; history intact |
| Business outcome | Transactions reference valid products |
| Related story | US-010, US-011, US-012, US-013 |

## UC-005 Manage Category

| Field | Value |
|---|---|
| Actor | Admin |
| Goal | Maintain product classification |
| Preconditions | Authenticated as Admin |
| Trigger | Open category management |
| Main flow | Enter name and description → validate → persist |
| Alternative flow | Edit existing category |
| Failure flow | Validation failure → nothing stored |
| Postconditions | Category set reflects the change |
| Business outcome | Products can be classified and filtered |
| Related story | US-014 |

## UC-006 Manage Warehouse

| Field | Value |
|---|---|
| Actor | Admin |
| Goal | Maintain stock-holding locations |
| Preconditions | Authenticated as Admin |
| Trigger | Open warehouse management |
| Main flow | Enter name, location, active status → validate → persist → each product gains a stock row per warehouse |
| Alternative flow | Toggle active status |
| Failure flow | Validation failure → nothing stored |
| Postconditions | Warehouse set reflects the change |
| Business outcome | Stock can be held and reported per location |
| Related story | US-015, US-016 |

## UC-007 Manage Supplier / Customer

| Field | Value |
|---|---|
| Actor | Admin |
| Goal | Maintain counterparties referenced by orders |
| Preconditions | Authenticated as Admin |
| Trigger | Open supplier or customer management |
| Main flow | Enter name, contact, address, active status → validate → persist |
| Alternative flow | Deactivate a counterparty used by an order |
| Failure flow | Delete attempt on used counterparty → refused, deactivation offered |
| Postconditions | Counterparty set reflects the change |
| Business outcome | Orders reference valid parties; history intact |
| Related story | US-017 |

## UC-008 Create Purchase Order

| Field | Value |
|---|---|
| Actor | Admin; Warehouse Staff (propose) |
| Goal | Commit a replenishment order to a supplier |
| Preconditions | Authenticated; active supplier, destination warehouse, active products exist |
| Trigger | Low stock observed or replenishment decided |
| Main flow | Create PO with supplier, destination warehouse, order date → add items (product, qty, buy price) → validate → save as Draft |
| Alternative flow | Order the Draft → status Ordered |
| Failure flow | Missing supplier or warehouse → rejected. Negative qty/price → rejected |
| Postconditions | PO exists at Draft or Ordered |
| Business outcome | Inbound commitment recorded |
| Related story | US-018 |

## UC-009 Record Goods Receipt

| Field | Value |
|---|---|
| Actor | Warehouse Staff; Admin |
| Goal | Record arrival and increase stock accountably |
| Preconditions | PO is Ordered or PartiallyReceived |
| Trigger | Goods arrive at the destination warehouse |
| Main flow | Open PO → enter received qty per line → validate against outstanding qty → begin transaction → write StockLedger `Receipt` rows → increase ProductStock → commit → recompute PO status |
| Alternative flow | Partial receipt → PO becomes PartiallyReceived, remainder tracked |
| Failure flow | Qty exceeds outstanding → rejected. Any step fails → rollback; stock and ledger unchanged |
| Postconditions | ProductStock increased and StockLedger consistent; PO PartiallyReceived or Received |
| Business outcome | Arrivals traceable to ledger rows |
| Related story | US-019, US-020, US-028 |

## UC-010 Create Sales Order

| Field | Value |
|---|---|
| Actor | Sales (own); Admin |
| Goal | Capture a customer order |
| Preconditions | Authenticated; active customer, source warehouse, active products exist |
| Trigger | Customer places an order |
| Main flow | Create SO with customer, source warehouse, creator → add items (product, qty, sell price) → validate → save as Draft |
| Alternative flow | Keep as Draft for later submission |
| Failure flow | Missing customer or warehouse → rejected. Negative qty/price → rejected |
| Postconditions | SO exists at Draft |
| Business outcome | Customer demand recorded |
| Related story | US-021 |

## UC-011 Submit Sales Order

| Field | Value |
|---|---|
| Actor | Sales (own); Admin |
| Goal | Move a Draft order into review |
| Preconditions | SO is Draft |
| Trigger | Submit action |
| Main flow | Validate SO completeness → transition Draft → PendingApproval |
| Alternative flow | — |
| Failure flow | Incomplete SO → rejected, stays Draft |
| Postconditions | SO at PendingApproval |
| Business outcome | Order queued for authorisation |
| Related story | US-022 |

## UC-012 Approve Sales Order

| Field | Value |
|---|---|
| Actor | Admin only |
| Goal | Authorise fulfilment |
| Preconditions | SO is PendingApproval; requester is Admin |
| Trigger | Approve action |
| Main flow | Server checks requester role → transition PendingApproval → Approved → record approver |
| Alternative flow | — |
| Failure flow | Requester is Sales or Warehouse Staff → 403, no transition. Applies to a Sales user's own order |
| Postconditions | SO Approved with approver recorded |
| Business outcome | Only authorised orders reach the warehouse |
| Related story | US-023, US-024 |

## UC-013 Reject Sales Order

| Field | Value |
|---|---|
| Actor | Admin only |
| Goal | Refuse an order under review |
| Preconditions | SO is PendingApproval; requester is Admin |
| Trigger | Reject action |
| Main flow | Server checks requester role → record rejection outcome |
| Alternative flow | — |
| Failure flow | Non-Admin requester → 403 |
| Postconditions | SO is not Approved |
| Business outcome | Unsuitable orders stopped before fulfilment |
| Related story | US-023 |

`[ANALYSIS INTERPRETATION]` The brief names the capability "menyetujui / menolak Sales Order" (§1.2)
and "Admin menyetujui atau menolak" (§1.1 item 3) but lists only five SO statuses
(Draft/PendingApproval/Approved/Fulfilled/Cancelled) with no `Rejected` value. The terminal state of
a rejection is therefore not established by the brief. Recorded as **GAP-002**; no status invented.

## UC-014 Record Goods Issue

| Field | Value |
|---|---|
| Actor | Warehouse Staff; Admin |
| Goal | Fulfil an approved order and decrease stock safely |
| Preconditions | SO is Approved; source warehouse holds sufficient stock |
| Trigger | Fulfilment action |
| Main flow | Open Approved SO → confirm quantities → begin race-safe transaction → verify available stock → write StockLedger `Issue` rows → decrease ProductStock → commit → SO becomes Fulfilled |
| Alternative flow | — |
| Failure flow | SO not Approved → refused. Insufficient stock → refused, nothing mutated. Concurrent competing issue → second request rejected or deferred; no oversell, no lost update. Any step fails → rollback |
| Postconditions | ProductStock decreased and StockLedger consistent; SO Fulfilled; stock never negative |
| Business outcome | Fulfilment traceable and stock correct under concurrency |
| Related story | US-025, US-026, US-027, US-028 |

## UC-015 View List and Detail

| Field | Value |
|---|---|
| Actor | All roles, scoped |
| Goal | Navigate products, POs, SOs |
| Preconditions | Authenticated |
| Trigger | Open a list |
| Main flow | Request list scoped to role → render rows → open detail |
| Alternative flow | No data → informative empty state |
| Failure flow | Record not found → 404. Out-of-scope record → 403 |
| Postconditions | Read-only |
| Business outcome | Data is navigable per role |
| Related story | US-029 |

## UC-016 Search, Filter, Sort, Paginate

| Field | Value |
|---|---|
| Actor | All roles, scoped |
| Goal | Locate records in large sets |
| Preconditions | Authenticated |
| Trigger | Enter search term, filter, sort, or change page |
| Main flow | Apply product search (name/SKU) with category and stock-status filters, or order search (number/counterparty) with status filter and date sort → paginate at 10 per page → preserve active filters across pages |
| Alternative flow | Clear filters |
| Failure flow | No match → empty state |
| Postconditions | Read-only |
| Business outcome | Records findable; pagination testable against seeded volume |
| Related story | US-030, US-031, US-032 |

## UC-017 View Dashboard

| Field | Value |
|---|---|
| Actor | All roles, scoped |
| Goal | See operational state for the role |
| Preconditions | Authenticated |
| Trigger | Open dashboard |
| Main flow | Run role-scoped aggregation queries → render figures |
| Alternative flow | — |
| Failure flow | Unauthenticated → login; no data → empty state |
| Postconditions | Read-only |
| Business outcome | Decisions based on live figures, not static values |
| Related story | US-033, US-034, US-035, US-036 |

## UC-018 Generate CSV Report

| Field | Value |
|---|---|
| Actor | Admin (all); Sales (own orders); Warehouse Staff (stock) |
| Goal | Export movement and order data for a period |
| Preconditions | Authenticated |
| Trigger | Request export with date range |
| Main flow | Validate range and role scope → run the same aggregation basis as the dashboard → stream CSV |
| Alternative flow | Different date ranges produce different extracts |
| Failure flow | Invalid range → validation message. Out-of-scope request → 403 |
| Postconditions | Read-only |
| Business outcome | Data reusable outside the application, consistent with dashboard |
| Related story | US-037 |

## UC-019 Query Product Availability API

| Field | Value |
|---|---|
| Actor | API Consumer (authenticated) |
| Goal | Retrieve per-warehouse availability for a SKU as JSON |
| Preconditions | Valid session |
| Trigger | `GET /api/products/{sku}/availability` |
| Main flow | Authenticate → resolve SKU → return per-warehouse stock, `200`, `Content-Type: application/json` |
| Alternative flow | — |
| Failure flow | No/invalid session → `401` JSON. SKU not found → `404` JSON. Never an HTML error page |
| Postconditions | Read-only |
| Business outcome | Machine-readable availability contract |
| Related story | US-038 |

## UC-020 Run Low-Stock Script

| Field | Value |
|---|---|
| Actor | Script Operator |
| Goal | Summarise products below reorder point outside the web request cycle |
| Preconditions | Container running; database reachable |
| Trigger | Manual `docker compose exec` invocation |
| Main flow | Load products and stock → compare stock against reorder point → print summary |
| Alternative flow | — |
| Failure flow | Handled error output; no stack trace |
| Postconditions | Read-only |
| Business outcome | Replenishment need visible without the web UI |
| Related story | US-045 |

---

# 09. BUSINESS FLOWS

Business behaviour only. Statuses used are exactly those the brief establishes (§1.3).

## 9.1 Purchase Flow

```text
Low stock observed
    → Purchase Order created (Draft)
    → Purchase Order ordered (Ordered)
    → Goods arrive
    → Goods Receipt recorded
    → [partial]  PartiallyReceived  → further receipt → Received
    → [complete] Received
```
Cancellation is permitted per the brief's PO status set: `Draft / Ordered / PartiallyReceived / Received / Cancelled`.

## 9.2 Sales Flow

```text
Sales Order created (Draft)
    → Submitted (PendingApproval)
    → Admin decision
    → [approved] Approved
    → Goods Issue recorded
    → Fulfilled
```
Brief §1.3: *"Draft → PendingApproval → Approved → Fulfilled (atau Cancelled pada tahap manapun sebelum Fulfilled)."*

## 9.3 Approval Flow

```text
Sales Order at PendingApproval
    → Server checks requester role
    → [requester = Admin]           → approve or reject
    → [requester = Sales]           → 403, no transition (incl. own order)
    → [requester = Warehouse Staff] → 403, no transition
```
Enforcement is server-side (§1.2, §4.2). Rejection terminal state: see GAP-002.

## 9.4 Goods Receipt Flow

```text
PO at Ordered or PartiallyReceived
    → received qty entered per line
    → validate qty within outstanding
    → BEGIN TRANSACTION
        → write StockLedger row (type Receipt, reference PO)
        → increase ProductStock for product + destination warehouse
      COMMIT
    → recompute PO status → PartiallyReceived or Received
```
Failure at any step → ROLLBACK; stock and ledger unchanged; outstanding qty preserved.

## 9.5 Goods Issue Flow

```text
SO at Approved
    → issue quantities confirmed
    → BEGIN TRANSACTION (race-safe)
        → verify available stock in source warehouse
        → [insufficient] → reject, ROLLBACK
        → write StockLedger row (type Issue, reference SO)
        → decrease ProductStock for product + source warehouse
      COMMIT
    → SO status → Fulfilled
```
Concurrent second issue on the same product+warehouse → rejected or deferred. Never oversell, never
lost update (ARCH-02).

## 9.6 Stock Flow

```text
Stock change originates ONLY from a service operation:

    Goods Receipt  → StockLedger(Receipt)    + ProductStock increase   [one transaction]
    Goods Issue    → StockLedger(Issue)      + ProductStock decrease   [one transaction]
    Adjustment     → StockLedger(Adjustment) + ProductStock change     [one transaction]

Invariants:
    quantity >= 0 always
    every ProductStock value explainable from its StockLedger rows
    UI never mutates stock directly
```
Brief basis: §1.3 "Keputusan data", §1.3 StockLedger row (`Receipt/Issue/Adjustment`), CF-6.

`[ANALYSIS INTERPRETATION]` The brief lists `Adjustment` as a StockLedger movement type (§1.3) but
defines no requirement, actor, or workflow that creates one. Modelled as a ledger-supported movement
type with no originating use case. Recorded as **GAP-003**; no adjustment workflow invented.

---

# 10. SYSTEM CONTEXT

## 10.1 System boundary

```text
                    ┌─────────────────────────────────────────┐
   Admin ──────────►│                                         │
                    │   INVENTORY & ORDER MANAGEMENT SYSTEM   │
   Sales ──────────►│                                         │
                    │   (web application + relational store)  │
   Warehouse ──────►│                                         │
   Staff            └─────────────────────────────────────────┘
                          ▲                        │
   Script Operator ───────┘                        ▼
   (manual CLI)                            CSV file output
                                           JSON API response
   API Consumer ◄──────────────────────────────────┘
   (authenticated)
```

## 10.2 External actors

| External actor | Interaction | Direction |
|---|---|---|
| Admin | Credentials, user and master data, PO creation, SO approval, receipt/issue, report requests | In / Out |
| Sales | Credentials, own SO creation and submission, catalogue and stock queries, report requests | In / Out |
| Warehouse Staff | Credentials, PO proposals, goods receipt and issue entries, stock queries, report requests | In / Out |
| Script Operator | Manual script invocation | In / Out |
| API Consumer | Authenticated availability request | In / Out |

## 10.3 External systems

**None.** The brief names no external system integration. Out of scope per §4.3: message queue,
cloud deployment, CI/CD, Kubernetes, real-time notification, mobile application, automatic cron
scheduler. Bonus item "notifikasi email simulasi (Mailhog)" is explicitly bonus and not modelled.

## 10.4 Inputs and outputs

| Inputs | Outputs |
|---|---|
| Login credentials | Role-scoped HTML pages |
| User account data | JSON API response (`200/401/404`) |
| Product, category, warehouse, supplier, customer data | CSV report file |
| Optional product image file | Low-stock CLI summary |
| Purchase Order header and items | Validation and error feedback (`403/404`, safe messages) |
| Goods receipt quantities | Dashboard aggregate figures |
| Sales Order header and items | Stock ledger movement history |
| Approval / rejection decision | |
| Goods issue quantities | |
| Search, filter, sort, pagination, date-range parameters | |
| Manual script invocation | |

## 10.5 Boundary note

This is a system-context model, not deployment architecture. Docker Compose, web server choice, and
container topology are environment concerns (brief §4, §5.1) and are not modelled here.

---

# 11. DFD

Information flow only. No PHP classes, controllers, services, repositories, cache keys, or containers.

## 11.1 Context Diagram (DFD Level 0 context)

```text
  ┌───────────┐   credentials, master data, PO, SO approval, receipt/issue, report req
  │   Admin   │────────────────────────────────────────────────────────┐
  └───────────┘                                                        │
  ┌───────────┐   credentials, own SO, catalogue/stock query           │
  │   Sales   │───────────────────────────────────────────────────────►│
  └───────────┘                                                        │
  ┌───────────┐   credentials, receipt/issue entries, stock query      │   ┌──────────────────────┐
  │ Warehouse │───────────────────────────────────────────────────────►│──►│                      │
  │   Staff   │                                                        │   │   INVENTORY & ORDER  │
  └───────────┘                                                        │   │  MANAGEMENT SYSTEM   │
  ┌───────────┐   manual invocation                                    │   │        (0)           │
  │  Script   │───────────────────────────────────────────────────────►│──►│                      │
  │ Operator  │◄─────────── low-stock summary ────────────────────────┼───│                      │
  └───────────┘                                                        │   └──────────────────────┘
  ┌───────────┐   authenticated availability request                   │             │
  │    API    │───────────────────────────────────────────────────────►│             │
  │ Consumer  │◄─────────── JSON availability response ───────────────┼─────────────┘
  └───────────┘                                                        │
       Admin / Sales / Warehouse Staff ◄── pages, dashboards, CSV ─────┘
```

## 11.2 DFD Level 0 — processes and data stores

Data stores:

| Store | Contents |
|---|---|
| D1 User | User accounts, roles, active status |
| D2 Master Data | Category, Warehouse, Supplier, Customer |
| D3 Product | Product catalogue incl. reorder point |
| D4 ProductStock | Per product per warehouse quantity |
| D5 PurchaseOrder | PO headers and items |
| D6 SalesOrder | SO headers and items |
| D7 StockLedger | Immutable stock movement rows |

Processes:

| Process | Name | Reads | Writes |
|---|---|---|---|
| P1 | Manage Authentication | D1 | — (session state) |
| P2 | Manage Users | D1 | D1 |
| P3 | Manage Master Data | D2, D3 | D2, D3 |
| P4 | Manage Purchase Order | D3, D2, D5 | D5 |
| P5 | Process Goods Receipt | D5, D4 | D4, D7, D5 |
| P6 | Manage Sales Order | D3, D2, D4, D6 | D6 |
| P7 | Approve Sales Order | D6, D1 | D6 |
| P8 | Process Goods Issue | D6, D4 | D4, D7, D6 |
| P9 | Produce Dashboard & Report | D3, D4, D5, D6, D7 | — |
| P10 | Serve Availability API | D3, D4 | — |
| P11 | Check Low Stock | D3, D4 | — |

Level 0 flow map:

```text
Admin/Sales/Warehouse ──credentials──► P1 ──reads──► D1
                                        │
                                        └──session/role──► (all protected processes)

Admin ──user data──► P2 ◄──► D1
Admin ──master data──► P3 ◄──► D2, D3

Admin/Warehouse ──PO data──► P4 ──► D5
                              └──reads──► D3, D2

Warehouse/Admin ──received qty──► P5 ──reads──► D5, D4
                                   ├──writes──► D7 (Receipt)
                                   ├──writes──► D4 (increase)
                                   └──writes──► D5 (status)

Sales/Admin ──SO data──► P6 ──► D6
                          └──reads──► D3, D2, D4

Admin ──approve/reject──► P7 ──reads──► D6, D1 ──writes──► D6 (status, approver)

Warehouse/Admin ──issue qty──► P8 ──reads──► D6, D4
                                ├──writes──► D7 (Issue)
                                ├──writes──► D4 (decrease)
                                └──writes──► D6 (Fulfilled)

All roles ──dashboard/report req──► P9 ──reads──► D3, D4, D5, D6, D7 ──► figures, CSV

API Consumer ──SKU──► P10 ──reads──► D3, D4 ──► JSON

Script Operator ──invoke──► P11 ──reads──► D3, D4 ──► summary
```

## 11.3 DFD Level 1 — decomposition where justified

Decomposed: **P5**, **P8**, **P7**, **P9**. These carry transaction, authorization, or aggregation
behaviour the brief treats as mandatory. Other processes are CRUD-shaped and not decomposed, per the
brief's anti-over-engineering posture.

### P5 Process Goods Receipt

| Sub-process | Name | Reads | Writes |
|---|---|---|---|
| P5.1 | Validate receipt input against outstanding qty | D5 | — |
| P5.2 | Write Receipt ledger row | — | D7 |
| P5.3 | Increase ProductStock | D4 | D4 |
| P5.4 | Recompute PO status | D5, D7 | D5 |

```text
receipt qty ──► P5.1 ──[valid]──► ┌ P5.2 ──► D7 ┐
                  │               │             │ one transaction
                  │               └ P5.3 ──► D4 ┘
                  │                     │
                  │                     └──► P5.4 ──► D5
                  └──[invalid]──► rejection feedback
```

### P8 Process Goods Issue

| Sub-process | Name | Reads | Writes |
|---|---|---|---|
| P8.1 | Verify SO status is Approved | D6 | — |
| P8.2 | Verify available stock in source warehouse | D4 | — |
| P8.3 | Write Issue ledger row | — | D7 |
| P8.4 | Decrease ProductStock | D4 | D4 |
| P8.5 | Set SO Fulfilled | D6 | D6 |

```text
issue qty ──► P8.1 ──[not Approved]──► rejection
                │
                └──[Approved]──► P8.2 ──[insufficient]──► rejection
                                   │
                                   └──[sufficient]──► ┌ P8.3 ──► D7 ┐
                                                      │             │ one race-safe transaction
                                                      └ P8.4 ──► D4 ┘
                                                            │
                                                            └──► P8.5 ──► D6
```

### P7 Approve Sales Order

| Sub-process | Name | Reads | Writes |
|---|---|---|---|
| P7.1 | Resolve requester role from session | D1 | — |
| P7.2 | Enforce approval authority (SoD) | — | — |
| P7.3 | Apply approval decision | D6 | D6 |

```text
approve/reject ──► P7.1 ──► P7.2 ──[role = Admin]──► P7.3 ──► D6
                                     │
                                     └──[role = Sales | Warehouse Staff]──► 403
```

### P9 Produce Dashboard & Report

| Sub-process | Name | Reads | Writes |
|---|---|---|---|
| P9.1 | Resolve role scope | D1 | — |
| P9.2 | Aggregate inventory value and low-stock | D3, D4 | — |
| P9.3 | Aggregate order counts by status | D5, D6 | — |
| P9.4 | Aggregate stock movement by date range | D7 | — |
| P9.5 | Format output (screen figures or CSV) | — | — |

```text
request ──► P9.1 ──► ┌ P9.2 ◄── D3, D4 ┐
                     │ P9.3 ◄── D5, D6 ├──► P9.5 ──► dashboard figures | CSV
                     └ P9.4 ◄── D7     ┘
```
P9.5 uses one aggregation basis for both outputs (REPORT-01: *"query agregasi/rekap yang sama dengan dashboard"*).

---

# 12. ERD

Logical model. No SQL, no speculative entities.

```text
┌──────────────┐         ┌──────────────────┐         ┌──────────────┐
│    User      │         │    Category      │         │  Warehouse   │
│──────────────│         │──────────────────│         │──────────────│
│ PK id        │         │ PK id            │         │ PK id        │
│    name      │         │    name          │         │    name      │
│    email  U  │         │    description   │         │    location  │
│    password  │         └──────────────────┘         │    is_active │
│    role      │                  │ 1                └──────────────┘
│    is_active │                  │                       1 │      │ 1
└──────────────┘                  │ 0..*                    │      │
   1 │  1 │  1 │           ┌──────────────┐                 │      │
     │    │    │           │   Product    │                 │      │
     │    │    │           │──────────────│                 │      │
     │    │    │           │ PK id        │                 │      │
     │    │    │           │    sku    U  │                 │      │
     │    │    │           │    name      │                 │      │
     │    │    │           │ FK category  │                 │      │
     │    │    │           │    unit      │                 │      │
     │    │    │           │    buy_price │                 │      │
     │    │    │           │    sell_price│                 │      │
     │    │    │           │    reorder_pt│                 │      │
     │    │    │           │    image     │                 │      │
     │    │    │           │    is_active │                 │      │
     │    │    │           └──────────────┘                 │      │
     │    │    │              1 │    │ 1                    │      │
     │    │    │                │    │        ┌─────────────┘      │
     │    │    │                │    │        │                    │
     │    │    │         ┌──────▼────▼────────▼──┐                 │
     │    │    │         │     ProductStock      │                 │
     │    │    │         │───────────────────────│                 │
     │    │    │         │ PK id                 │                 │
     │    │    │         │ FK product_id     ┐U  │                 │
     │    │    │         │ FK warehouse_id   ┘   │                 │
     │    │    │         │    quantity  (>= 0)   │                 │
     │    │    │         │    updated_at         │                 │
     │    │    │         └───────────────────────┘                 │
     │    │    │                                                   │
     │    │    │  ┌──────────────┐        ┌──────────────┐         │
     │    │    │  │   Supplier   │        │   Customer   │         │
     │    │    │  │──────────────│        │──────────────│         │
     │    │    │  │ PK id        │        │ PK id        │         │
     │    │    │  │    name      │        │    name      │         │
     │    │    │  │    contact   │        │    contact   │         │
     │    │    │  │    address   │        │    address   │         │
     │    │    │  │    is_active │        │    is_active │         │
     │    │    │  └──────────────┘        └──────────────┘         │
     │    │    │        1 │                     │ 1                │
     │    │    │          │ 0..*                │ 0..*             │
     │    │    │  ┌───────▼────────┐    ┌───────▼────────┐         │
     │    │    └─►│ PurchaseOrder  │    │  SalesOrder    │◄────────┤
     │    │       │────────────────│    │────────────────│         │
     │    │       │ PK id          │    │ PK id          │         │
     │    │       │ FK supplier_id │    │ FK customer_id │         │
     │    │       │ FK warehouse_id│◄───┤ FK created_by  │─────────┤
     │    │       │    status      │    │ FK approved_by │         │
     │    │       │    order_date  │    │ FK warehouse_id│         │
     │    │       └────────────────┘    │    status      │         │
     │    │           1 │              └────────────────┘         │
     │    │             │ 1..*             1 │                     │
     │    │    ┌────────▼──────────┐         │ 1..*                │
     │    │    │ PurchaseOrderItem │  ┌──────▼──────────┐          │
     │    │    │───────────────────│  │ SalesOrderItem  │          │
     │    │    │ PK id             │  │─────────────────│          │
     │    │    │ FK po_id          │  │ PK id           │          │
     │    │    │ FK product_id     │  │ FK so_id        │          │
     │    │    │    quantity       │  │ FK product_id   │          │
     │    │    │    buy_price      │  │    quantity     │          │
     │    │    └───────────────────┘  │    sell_price   │          │
     │    │                           └─────────────────┘          │
     │    │                                                        │
     │    │             ┌──────────────────────────┐               │
     │    └────────────►│      StockLedger         │◄──────────────┘
     │                  │──────────────────────────│
     │                  │ PK id                    │
     │                  │ FK product_id            │
     │                  │ FK warehouse_id          │
     │                  │    movement_type         │  Receipt/Issue/Adjustment
     │                  │    quantity              │
     │                  │    reference_type        │  PO / SO
     │                  │    reference_id          │
     │                  │ FK performed_by          │
     │                  │    created_at            │
     └─────────────────►└──────────────────────────┘

  U = unique constraint (single or composite)
```

## 12.1 Relationships and cardinality

| Relationship | Cardinality | Basis |
|---|---|---|
| Category → Product | 1 : 0..* | §1.3 Product has kategori |
| Product → ProductStock | 1 : 0..* | WH-01 "setiap produk memiliki baris stok per gudang" |
| Warehouse → ProductStock | 1 : 0..* | WH-01 |
| Product + Warehouse → ProductStock | composite unique | one stock row per product per warehouse |
| Supplier → PurchaseOrder | 1 : 0..* | §1.3 PO has supplier |
| Warehouse → PurchaseOrder | 1 : 0..* | §1.3 PO has gudang tujuan |
| PurchaseOrder → PurchaseOrderItem | 1 : 1..* | §1.3 PO + Item |
| Product → PurchaseOrderItem | 1 : 0..* | §1.3 item (produk, qty, harga beli) |
| Customer → SalesOrder | 1 : 0..* | §1.3 SO has customer |
| Warehouse → SalesOrder | 1 : 0..* | §1.3 SO has gudang asal |
| User → SalesOrder (created_by) | 1 : 0..* | §1.3 "dibuat oleh" |
| User → SalesOrder (approved_by) | 1 : 0..* | §1.3 "disetujui oleh" |
| SalesOrder → SalesOrderItem | 1 : 1..* | §1.3 SO + Item |
| Product → SalesOrderItem | 1 : 0..* | §1.3 item (produk, qty, harga jual) |
| Product → StockLedger | 1 : 0..* | §1.3 StockLedger has produk |
| Warehouse → StockLedger | 1 : 0..* | §1.3 StockLedger has gudang |
| User → StockLedger (performed_by) | 1 : 0..* | §1.3 "dilakukan oleh" |
| PO/SO → StockLedger (reference) | 1 : 0..* polymorphic | §1.3 "referensi (PO/SO id)" |

`[ANALYSIS INTERPRETATION]` The brief specifies StockLedger *"referensi (PO/SO id)"* — one reference
field covering two order types. Modelled logically as `reference_type` + `reference_id`. The physical
representation is a Phase 2 design decision, not fixed here.

## 12.2 Capability verification

| Required capability | Supported by |
|---|---|
| Multi-warehouse stock | ProductStock with composite unique (product, warehouse) |
| Purchase Orders | PurchaseOrder + PurchaseOrderItem, supplier and destination warehouse FKs |
| Sales Orders | SalesOrder + SalesOrderItem, customer, source warehouse, created_by, approved_by |
| Stock movement | StockLedger with type, quantity, reference, performer, timestamp |
| Goods receipt | PurchaseOrderItem outstanding vs StockLedger `Receipt` rows; ProductStock increase |
| Goods issue | ProductStock decrease guarded by `quantity >= 0`; StockLedger `Issue` rows |
| Historical traceability | StockLedger rows never mutated; each ProductStock value explainable from its rows |
| Referential integrity | FKs on every relationship above |
| Segregation of duties evidence | `created_by` ≠ `approved_by` recorded on SalesOrder |

## 12.3 Entities considered and excluded

| Considered | Decision | Reason |
|---|---|---|
| `Rejection` / `SalesOrderRejection` | **Excluded** | Brief defines no `Rejected` status or rejection entity — see GAP-002 |
| `StockAdjustment` | **Excluded** | `Adjustment` is a ledger movement type, not a separate entity (§1.3) — see GAP-003 |
| `Role` table | **Excluded** | Brief fixes role as a value on User: `Admin / Sales / WarehouseStaff` (§1.3) |
| `GoodsReceipt` / `GoodsIssue` headers | **Excluded** | Brief models receipt and issue as operations producing StockLedger rows referencing PO/SO, not as separate documents (§1.3) |
| `AuditTrail` | **Excluded** | Bonus only (§4.4) |
| `Notification` | **Excluded** | Out of scope (§4.3); email simulation is bonus |

---

# 13. LOGICAL DATA MODEL

## 13.1 User

| Field | Value |
|---|---|
| Purpose | Application account with role and activation state |
| Important attributes | name, email, password, role, active status, timestamps (§1.3) |
| Relationships | creates SalesOrder; approves SalesOrder; performs StockLedger movements |
| Business meaning | Identity and authority in the system |
| Important constraints | email unique (USR-01); role ∈ {Admin, Sales, WarehouseStaff} (§1.3); password stored via `password_hash()` (AUTH-01) |
| Lifecycle | Created by Admin → active ⇄ inactive. No public registration (USR-01, FAQ 11). No deletion stated |

## 13.2 Warehouse

| Field | Value |
|---|---|
| Purpose | Physical stock-holding location |
| Important attributes | name, location, active status (§1.3) |
| Relationships | holds ProductStock; destination of PurchaseOrder; source of SalesOrder; scope of StockLedger |
| Business meaning | Where stock physically sits |
| Important constraints | Active status governs operational availability |
| Lifecycle | Created by Admin → active ⇄ inactive |

## 13.3 Category

| Field | Value |
|---|---|
| Purpose | Product classification |
| Important attributes | name, description (§1.3) |
| Relationships | classifies Product |
| Business meaning | Grouping used for catalogue filtering (FIND-01) |
| Important constraints | Referenced by Product |
| Lifecycle | Created by Admin. No active status stated in §1.3 |

## 13.4 Product

| Field | Value |
|---|---|
| Purpose | Catalogue item used across all transactions |
| Important attributes | SKU (unique), name, category, unit, buy price, sell price, reorder point, image (optional), active status (§1.3) |
| Relationships | classified by Category; stocked as ProductStock per Warehouse; referenced by PO items, SO items, StockLedger |
| Business meaning | The thing bought, stocked, and sold |
| Important constraints | SKU unique (PRD-01); buy price, sell price, reorder point numeric >= 0 (PRD-01); image type and size validated, stored under random name (PRD-01); product used on an order may be deactivated, not deleted (PRD-01, §1.3) |
| Lifecycle | Created by Admin → active ⇄ inactive. Never hard-deleted once referenced |

## 13.5 ProductStock

| Field | Value |
|---|---|
| Purpose | Current quantity of one product in one warehouse |
| Important attributes | product, warehouse, quantity, updated_at (§1.3) |
| Relationships | belongs to Product and Warehouse; explained by StockLedger rows |
| Business meaning | The authoritative current stock figure per location |
| Important constraints | `quantity >= 0` (§1.3); one row per product per warehouse; mutated only by a service that writes StockLedger in the same transaction (§1.3, CF-6); never mutated directly by the UI |
| Lifecycle | Row exists per product-warehouse pair; quantity changes only via Receipt / Issue / Adjustment operations |

## 13.6 Supplier

| Field | Value |
|---|---|
| Purpose | Counterparty goods are purchased from |
| Important attributes | name, contact, address, active status (§1.3) |
| Relationships | supplies PurchaseOrder |
| Business meaning | Source of inbound goods |
| Important constraints | Deactivated, not deleted, once used (§1.3) |
| Lifecycle | Created by Admin → active ⇄ inactive |

## 13.7 Customer

| Field | Value |
|---|---|
| Purpose | Counterparty goods are sold to |
| Important attributes | name, contact, address, active status (§1.3) |
| Relationships | receives SalesOrder |
| Business meaning | Destination of outbound goods |
| Important constraints | Deactivated, not deleted, once used (§1.3) |
| Lifecycle | Created by Admin → active ⇄ inactive |

## 13.8 PurchaseOrder

| Field | Value |
|---|---|
| Purpose | Inbound purchase commitment to a supplier |
| Important attributes | supplier, destination warehouse, status, order date, items (§1.3) |
| Relationships | belongs to Supplier and Warehouse; has PurchaseOrderItem(s); referenced by StockLedger Receipt rows |
| Business meaning | What was ordered, from whom, to which warehouse |
| Important constraints | status ∈ {Draft, Ordered, PartiallyReceived, Received, Cancelled} (§1.3); partial receipt allowed with remainder tracked (PO-01) |
| Lifecycle | `Draft → Ordered → PartiallyReceived → Received`, with `Cancelled` permitted per the brief's status set |

## 13.9 PurchaseOrderItem

| Field | Value |
|---|---|
| Purpose | One product line on a purchase order |
| Important attributes | product, quantity, buy price (§1.3) |
| Relationships | belongs to PurchaseOrder; references Product |
| Business meaning | What quantity of which product at which purchase price |
| Important constraints | quantity and buy price >= 0 (VAL-01, DB-01); outstanding quantity derivable for partial receipt (PO-01) |
| Lifecycle | Created with the PO; consumed progressively by receipts |

## 13.10 SalesOrder

| Field | Value |
|---|---|
| Purpose | Outbound sales commitment to a customer |
| Important attributes | customer, created by, approved by, source warehouse, status, items (§1.3) |
| Relationships | belongs to Customer and Warehouse; created_by and approved_by reference User; has SalesOrderItem(s); referenced by StockLedger Issue rows |
| Business meaning | What was sold, to whom, from which warehouse, by whom, approved by whom |
| Important constraints | status ∈ {Draft, PendingApproval, Approved, Fulfilled, Cancelled} (§1.3); Cancelled permitted at any stage before Fulfilled (§1.3); approver must not be the creator's role — Sales cannot approve (§1.2, SO-01); goods issue only when Approved (SO-01) |
| Lifecycle | `Draft → PendingApproval → Approved → Fulfilled`, or `Cancelled` before Fulfilled |

## 13.11 SalesOrderItem

| Field | Value |
|---|---|
| Purpose | One product line on a sales order |
| Important attributes | product, quantity, sell price (§1.3) |
| Relationships | belongs to SalesOrder; references Product |
| Business meaning | What quantity of which product at which selling price |
| Important constraints | quantity and sell price >= 0 (VAL-01, DB-01); quantity must be satisfiable from source warehouse stock at issue time (SO-01) |
| Lifecycle | Created with the SO; consumed at goods issue |

## 13.12 StockLedger

| Field | Value |
|---|---|
| Purpose | Immutable record of every stock movement |
| Important attributes | product, warehouse, movement type, quantity, reference (PO/SO id), performed by, timestamp (§1.3) |
| Relationships | references Product, Warehouse, User; references PurchaseOrder or SalesOrder |
| Business meaning | The audit trail that makes every stock figure explainable |
| Important constraints | movement type ∈ {Receipt, Issue, Adjustment} (§1.3); written in the same transaction as the ProductStock change (§1.3, ARCH-02); rows are historical and not revised (§1.3 "menjaga riwayat") |
| Lifecycle | Append-only. Written by receipt, issue, and adjustment operations |

---

# 14. INITIAL CLASS DIAGRAM

Conceptual pre-coding model, derived from the brief's mandated three-layer separation
(ARCH-01, §4.1). **Not** derived from the existing code in the project folder — per the phase rule,
existing implementation is not the source of intended architecture.

```text
LEGEND
  ──────►  depends on (constructor injection)
  ┈┈┈┈┈►  implements
  《I》    interface

┌─────────────────────── CONTROLLER LAYER (HTTP / entry boundary) ───────────────────────┐
│  AuthController      UserController        ProductController    WarehouseController     │
│  CategoryController  SupplierController    CustomerController                           │
│  PurchaseOrderController   SalesOrderController   StockLedgerController                 │
│  DashboardController       ReportController                                             │
│  ProductAvailabilityApiController        (JSON contract, API-01)                        │
│  CheckLowStockScript                     (CLI entry, JOB-01 — not HTTP)                 │
└────────────────────────────────────────┬───────────────────────────────────────────────┘
                                         │ depends on
                                         ▼
┌─────────────────────── SERVICE LAYER (business rules, use cases) ──────────────────────┐
│  AuthService            → UC-001, UC-002                                               │
│  UserService            → UC-003                                                       │
│  ProductService         → UC-004                                                       │
│  CategoryService        → UC-005                                                       │
│  WarehouseService       → UC-006                                                       │
│  SupplierService        → UC-007                                                       │
│  CustomerService        → UC-007                                                       │
│  PurchaseOrderService   → UC-008                                                       │
│  GoodsReceiptService    → UC-009   [transaction boundary]                               │
│  SalesOrderService      → UC-010, UC-011                                                │
│  ApprovalService        → UC-012, UC-013   [SoD enforcement]                             │
│  GoodsIssueService      → UC-014   [transaction + concurrency boundary]                  │
│  StockQueryService      → UC-015, UC-016 (stock views)                                  │
│  DashboardService       → UC-017                                                        │
│  ReportService          → UC-018                                                        │
│  AvailabilityService    → UC-019                                                        │
│  LowStockService        → UC-020                                                        │
└────────────────────────────────────────┬───────────────────────────────────────────────┘
                                         │ depends on 《I》 only  (Dependency Inversion)
                                         ▼
┌─────────────────── REPOSITORY INTERFACE LAYER (persistence contract) ──────────────────┐
│  《I》UserRepositoryInterface           《I》ProductRepositoryInterface                  │
│  《I》CategoryRepositoryInterface       《I》WarehouseRepositoryInterface                │
│  《I》SupplierRepositoryInterface       《I》CustomerRepositoryInterface                 │
│  《I》ProductStockRepositoryInterface   《I》StockLedgerRepositoryInterface              │
│  《I》PurchaseOrderRepositoryInterface  《I》SalesOrderRepositoryInterface               │
│  《I》TransactionManagerInterface       (explicit transaction boundary, DB-01/ARCH-02)   │
└──────────────┬──────────────────────────────────────────────┬─────────────────────────┘
               ┊ implements                                   ┊ implements
               ▼                                              ▼
┌──────────────────────────────────┐        ┌──────────────────────────────────────────┐
│  CONCRETE REPOSITORY (MySQL)     │        │  FAKE REPOSITORY (in-memory, unit test)  │
│  MySqlUserRepository             │        │  InMemoryProductStockRepository          │
│  MySqlProductRepository          │        │  InMemory… (as needed by unit tests)     │
│  MySqlCategoryRepository         │        │                                          │
│  MySqlWarehouseRepository        │        │  ARCH-01: at least one interface has     │
│  MySqlSupplierRepository         │        │  BOTH a real MySQL and a fake impl       │
│  MySqlCustomerRepository         │        └──────────────────────────────────────────┘
│  MySqlProductStockRepository     │
│  MySqlStockLedgerRepository      │
│  MySqlPurchaseOrderRepository    │
│  MySqlSalesOrderRepository       │
│  PdoTransactionManager           │
└─────────────┬────────────────────┘
              │ depends on
              ▼
┌──────────────────────────────────┐
│  INFRASTRUCTURE                  │
│  PDO / MySQL 8                   │
│  Session (auth state)            │
│  Filesystem (product image)      │
└──────────────────────────────────┘

┌─────────────────────── DOMAIN / ENTITY (app/Entity per §4.1) ──────────────────────────┐
│  User   Warehouse   Category   Product   ProductStock   Supplier   Customer            │
│  PurchaseOrder   PurchaseOrderItem   SalesOrder   SalesOrderItem   StockLedger         │
│  (referenced by Service and Repository layers; no dependency on Controller or PDO)      │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

## 14.1 Dependency direction rules represented

| Rule | Brief basis |
|---|---|
| Controller → Service → Repository; never the reverse | ARCH-01 |
| Service depends on repository **interfaces**, not concrete classes | ARCH-01 (DIP at repository boundary) |
| Service receives dependencies via constructor injection; no hidden `new PDO()` inside Service | ARCH-01 |
| Business logic does not depend on PDO, session, or superglobals | ARCH-01 |
| At least one repository interface has two implementations — MySQL and in-memory fake | ARCH-01 |
| Business-logic tests run without a real database | ARCH-01 |
| Entities carry no Controller or PDO dependency | §4.1 four-way separation |
| Transaction boundary is explicit and owned at the service layer | DB-01, ARCH-02 |

## 14.2 Deliberate omissions

`GoodsReceiptService` and `GoodsIssueService` are shown as distinct from `PurchaseOrderService` and
`SalesOrderService` because the brief treats receipt and issue as transactional stock operations with
their own invariants (PO-01, SO-01, ARCH-02). No further layers, no DI container (FAQ 2 — manual
constructor injection suffices), no ORM (FAQ 1), no four-ring Clean Architecture (FAQ 4 — three
pragmatic layers are adequate). No as-built diagram is produced here (DESIGN-01 places it at the end).

---

# 15. INITIAL NFR

Only NFRs the brief supports. **No** latency, throughput, availability, or scalability target is
asserted — the brief states none.

| ID | Category | Expectation | Brief basis |
|---|---|---|---|
| NFR-01 | Security | Passwords stored with `password_hash()`, verified with `password_verify()` | AUTH-01, §4.2 |
| NFR-02 | Security | Session managed safely; session ID renewed after login | AUTH-01, §4.2 |
| NFR-03 | Security | Authorization always enforced server-side, including SoD | §1.2, §4.2, CF-5 |
| NFR-04 | Security | All input-bearing queries use PDO prepared statements | DB-01, §4.2, CF-5 |
| NFR-05 | Security | User output escaped before HTML rendering | §4.2 |
| NFR-06 | Security | Uploaded images validated for type and size, stored under an unguessable random name | PRD-01 |
| NFR-07 | Security | Secrets and credentials never stored in the repository | §4.2, §6.1, CF-5 |
| NFR-08 | Security | Failed login gives a safe message that does not disclose which part was wrong | AUTH-01 |
| NFR-09 | Reliability / Data Integrity | Stock quantity never negative | §1.3, ARCH-02 |
| NFR-10 | Reliability / Data Integrity | ProductStock and StockLedger stay consistent; stock changes only via the service that writes both in one transaction | §1.3, CF-6 |
| NFR-11 | Reliability / Data Integrity | Multi-table stock operations wrapped in explicit transactions | DB-01, ARCH-02 |
| NFR-12 | Reliability / Data Integrity | Concurrent goods issues produce a correct final stock — no oversell, no lost update | ARCH-02, CF-7 |
| NFR-13 | Reliability / Data Integrity | Referential integrity via PK, FK, and relevant constraints and indexes | DB-01 |
| NFR-14 | Reliability | Database exceptions and stack traces never shown to users | ERR-01 |
| NFR-15 | Usability | Login, dashboard, list, detail, and form usable at 360px and desktop; navigation and tables not clipped | UI-01 |
| NFR-16 | Usability | Forms have labels; focus state and basic contrast visible | UI-01 |
| NFR-17 | Usability | Empty states are informative | VIEW-01 |
| NFR-18 | Usability | Main lists paginate at 10 per page with filters preserved across pages | FIND-01 |
| NFR-19 | Maintainability | Three-layer separation Controller / Service / Repository with DIP at the repository boundary | ARCH-01, §4.1 |
| NFR-20 | Maintainability | No unnecessary abstraction or layer that solves no real problem | §0 PERINGATAN |
| NFR-21 | Maintainability | Design decisions recorded as ADR, refactoring log, SRP audit, tech-debt register | DESIGN-02, DESIGN-03 |
| NFR-22 | Testability | Business logic testable without a real database, via a fake repository implementation | ARCH-01 |
| NFR-23 | Testability | Minimum 6 unit tests across at least 3 logic areas, excluding trivial getters/setters | TEST-01 |
| NFR-24 | Testability | Minimum 3 integration tests touching real MySQL in Docker | TEST-02 |
| NFR-25 | Testability | Tests follow FIRST — no `sleep()`, no real network calls, no execution-order dependency | TEST-03 |
| NFR-26 | Testability | Static analysis report (PHPStan level 5+ or PHP_CodeSniffer PSR-12) with zero critical errors; remaining warnings explained | TEST-03 |
| NFR-27 | Reproducibility | Application and database run from clean state via `docker compose up --build` | §5.1 |
| NFR-28 | Reproducibility | Configuration via environment variables with example values in `.env` | §5.1 |
| NFR-29 | Reproducibility | No dependence on absolute paths or participant-machine-specific configuration | §5.1 |
| NFR-30 | Reproducibility | Schema and seed build the database from empty, including FIND-01 test data | DB-01 |
| NFR-31 | Deployment | At minimum an application/web service and a MySQL service under Docker Compose | §5.1 |
| NFR-32 | Deployment | Scheduled task runs manually via `docker compose exec`; no automatic server scheduling required | JOB-01, §4.3 |

---

# 16. CANDIDATE REQUIREMENT ANALYSIS

`CAND-###` are temporary analysis identifiers. They are **not** Phase 1 requirement IDs. The
"Brief ID" column records the brief's own official ID where the brief establishes correspondence.

| Candidate ID | Type | Description | Actor | Origin | Brief ID | Status |
|---|---|---|---|---|---|---|
| CAND-001 | Functional | Authenticate by email + password; session determines role-scoped view | All | brief §2.1 | AUTH-01 | CANDIDATE |
| CAND-002 | Functional | Refuse login for inactive accounts | System | brief §2.1 | AUTH-01 | CANDIDATE |
| CAND-003 | Security | Safe credential-failure message with no field disclosure | System | brief §2.1 | AUTH-01 | CANDIDATE |
| CAND-004 | Security | Protected pages unreachable without session; session ID renewed after login | System | brief §2.1, §4.2 | AUTH-01 | CANDIDATE |
| CAND-005 | Security | Password stored via `password_hash()`, verified via `password_verify()` | System | brief §2.1, §4.2 | AUTH-01 | CANDIDATE |
| CAND-006 | Functional | Terminate session; protected URLs unreachable afterwards | All | brief §2.1 | AUTH-02 | CANDIDATE |
| CAND-007 | Functional | Admin CRUD + activation on Sales / Warehouse Staff accounts; email unique | Admin | brief §2.1 | USR-01 | CANDIDATE |
| CAND-008 | Functional | Role restricted to Admin / Sales / WarehouseStaff; no public registration | System | brief §2.1, §1.3 | USR-01 | CANDIDATE |
| CAND-009 | Security | Sales and Warehouse Staff blocked from user-administration pages and endpoints | System | brief §2.1 | USR-01 | CANDIDATE |
| CAND-010 | Functional | Product catalogue management with unique SKU and validated numerics >= 0 | Admin | brief §2.2 | PRD-01 | CANDIDATE |
| CAND-011 | Functional | Product used on an order may be deactivated, not deleted | Admin | brief §2.2, §1.3 | PRD-01 | CANDIDATE |
| CAND-012 | Security | Product image upload validated for type and size, stored under random name | Admin | brief §2.2 | PRD-01 | CANDIDATE |
| CAND-013 | Functional | Category management | Admin | brief §1.3, §2.2 | PRD-01 | CANDIDATE |
| CAND-014 | Functional | Warehouse management; one stock row per product per warehouse | Admin | brief §2.2 | WH-01 | CANDIDATE |
| CAND-015 | Functional | Stock display shows total and per-warehouse breakdown | All | brief §2.2 | WH-01 | CANDIDATE |
| CAND-016 | Functional | Supplier and customer management with active status | Admin | brief §1.3 | — | CANDIDATE — UNASSIGNED |
| CAND-017 | Functional | Purchase Order with supplier, destination warehouse, items; status lifecycle | Admin, Warehouse Staff | brief §2.3 | PO-01 | CANDIDATE |
| CAND-018 | Functional | Goods receipt increases ProductStock and writes StockLedger `Receipt` in one transaction | Warehouse Staff, Admin | brief §2.3 | PO-01, ARCH-02 | CANDIDATE |
| CAND-019 | Functional | Partial receipt permitted; outstanding quantity remains recorded | Warehouse Staff | brief §2.3 | PO-01 | CANDIDATE |
| CAND-020 | Functional | Sales Order lifecycle Draft → PendingApproval → Approved → Fulfilled, Cancelled before Fulfilled | Sales, Admin | brief §2.4, §1.3 | SO-01 | CANDIDATE |
| CAND-021 | Security | Approval authority checked server-side; Sales cannot approve, including own order | System | brief §2.4, §1.2 | SO-01 | CANDIDATE |
| CAND-022 | Functional | Goods issue only for Approved SO; rejected when available stock insufficient | Warehouse Staff, Admin | brief §2.4 | SO-01 | CANDIDATE |
| CAND-023 | Technical | Goods issue decreases ProductStock and writes StockLedger `Issue` in one race-safe transaction | System | brief §2.4, §3.1 | SO-01, ARCH-02 | CANDIDATE |
| CAND-024 | Functional | Role-scoped list and detail pages for products, POs, SOs, with informative empty state | All | brief §2.5 | VIEW-01 | CANDIDATE |
| CAND-025 | Functional | Product search by name/SKU; filters for category and stock status | All | brief §2.5 | FIND-01 | CANDIDATE |
| CAND-026 | Functional | Order search by number/counterparty; status filter; date sort ascending/descending | All | brief §2.5 | FIND-01 | CANDIDATE |
| CAND-027 | Functional | Pagination 10 per page with filters preserved across pages | All | brief §2.5 | FIND-01 | CANDIDATE |
| CAND-028 | Technical | Seed provides at least 30 products and 25 combined orders for pagination testing | System | brief §2.5, §7.1 | FIND-01, DB-01 | CANDIDATE |
| CAND-029 | Functional | Admin dashboard: inventory value, below-reorder-point products, pending orders per status | Admin | brief §2.5 | DASH-01 | CANDIDATE |
| CAND-030 | Functional | Sales dashboard: own orders per status | Sales | brief §2.5 | DASH-01 | CANDIDATE |
| CAND-031 | Functional | Warehouse dashboard: goods receipt/issue queues and low-stock products | Warehouse Staff | brief §2.5 | DASH-01 | CANDIDATE |
| CAND-032 | Technical | All dashboard figures from aggregation queries, never static values | System | brief §2.5 | DASH-01 | CANDIDATE |
| CAND-033 | Functional | CSV export of stock movement and order status by date range, same aggregation basis as dashboard | All (scoped) | brief §2.5 | REPORT-01 | CANDIDATE |
| CAND-034 | Functional | JSON endpoint `GET /api/products/{sku}/availability` returning per-warehouse stock | API Consumer | brief §2.6 | API-01 | CANDIDATE |
| CAND-035 | Technical | API authentication as for HTML pages; `Content-Type: application/json`; status 200/401/404, never an HTML error page | System | brief §2.6 | API-01 | CANDIDATE |
| CAND-036 | Functional | Validate required fields, enums, dates, FKs, numerics >= 0 on frontend and backend; backend authoritative | System | brief §2.7 | VAL-01 | CANDIDATE |
| CAND-037 | Functional | Nothing stored when validation fails; entered input preserved where relevant | System | brief §2.7 | VAL-01 | CANDIDATE |
| CAND-038 | Functional | Unauthenticated → login; unauthorized → 403; not found → 404 | System | brief §2.7 | ERR-01 | CANDIDATE |
| CAND-039 | Security | Database exceptions and stack traces never shown to users | System | brief §2.7 | ERR-01 | CANDIDATE |
| CAND-040 | Functional | Login, dashboard, list, detail, form usable at 360px and desktop; nothing clipped | All | brief §2.7 | UI-01 | CANDIDATE |
| CAND-041 | Functional | Form labels present; focus state and basic contrast visible | All | brief §2.7 | UI-01 | CANDIDATE |
| CAND-042 | Technical | Relational schema with PK, FK, `quantity >= 0` constraint, relevant indexes | System | brief §2.7 | DB-01 | CANDIDATE |
| CAND-043 | Technical | All queries via PDO prepared statements; multi-table stock operations in explicit transactions | System | brief §2.7, §4.2 | DB-01 | CANDIDATE |
| CAND-044 | Technical | Schema and seed create the database from empty | System | brief §2.7 | DB-01 | CANDIDATE |
| CAND-045 | Functional | Standalone script summarising below-reorder-point products, runnable manually via Docker | Script Operator | brief §2.7 | JOB-01 | CANDIDATE |
| CAND-046 | Technical | Three-layer Controller → Service → Repository with dependency direction inward | System | brief §3.1 | ARCH-01 | CANDIDATE |
| CAND-047 | Technical | At least one repository interface with a real MySQL and an in-memory fake implementation | System | brief §3.1 | ARCH-01 | CANDIDATE |
| CAND-048 | Technical | Service receives dependencies by constructor injection; no hidden `new PDO()` in Service | System | brief §3.1 | ARCH-01 | CANDIDATE |
| CAND-049 | Technical | Business-logic tests runnable without a real database connection | System | brief §3.1 | ARCH-01 | CANDIDATE |
| CAND-050 | Technical | Concurrent goods issues must not oversell or lose updates; mechanism is a participant design decision | System | brief §3.1 | ARCH-02 | CANDIDATE |
| CAND-051 | Technical | Controlled, reproducible concurrency scenario demonstrating the second request is rejected or deferred | System | brief §3.1, FAQ 8 | ARCH-02 | CANDIDATE |
| CAND-052 | Documentation | Initial class diagram before coding in `docs/planning/`; as-built at the end in `docs/architecture/` with interface-vs-concrete markers and a 2–3 sentence change note | System | brief §3.2 | DESIGN-01 | CANDIDATE |
| CAND-053 | Documentation | 2–3 ADRs (context / decision / consequences) for real decisions | System | brief §3.2 | DESIGN-02 | CANDIDATE |
| CAND-054 | Documentation | Refactoring log ≥ 3 entries, SRP audit note, tech-debt register, ≥ 1 `refactor:` commit | System | brief §3.2 | DESIGN-03 | CANDIDATE |
| CAND-055 | Documentation | Written critique of an assessor-supplied code snippet — smell, SOLID violation, refactor direction | System | brief §3.2 | DESIGN-04 | CANDIDATE |
| CAND-056 | Testing | ≥ 6 unit tests across ≥ 3 logic areas, no session/PDO/external service, no trivial getters | System | brief §3.3 | TEST-01 | CANDIDATE |
| CAND-057 | Testing | ≥ 3 integration tests touching real MySQL in Docker | System | brief §3.3 | TEST-02 | CANDIDATE |
| CAND-058 | Testing | Static analysis report with zero critical errors; remaining warnings explained | System | brief §3.3 | TEST-03 | CANDIDATE |
| CAND-059 | Testing | Tests follow FIRST — no `sleep()`, no real network, no order dependency | System | brief §3.3 | TEST-03 | CANDIDATE |
| CAND-060 | Technical | Docker Compose with at least app/web and MySQL services; clean build via `docker compose up --build` | System | brief §5.1 | — | CANDIDATE — UNASSIGNED |
| CAND-061 | Technical | Configuration by environment variable with `.env`; no absolute paths or machine-specific config | System | brief §5.1 | — | CANDIDATE — UNASSIGNED |
| CAND-062 | Functional | Minimum demo data: 1 Admin, ≥2 Sales, ≥2 Warehouse Staff, ≥2 warehouses, 30 products, ≥25 orders incl. PendingApproval and Cancelled | System | brief §7.1 | — | CANDIDATE — UNASSIGNED |

62 candidates. Four are `CANDIDATE — UNASSIGNED` (CAND-016, CAND-060, CAND-061, CAND-062) — the
brief states the obligation but attaches no dedicated requirement ID. No official ID was invented to
close them; Phase 1 decides where they land.

---

# 17. ANALYSIS TRACEABILITY

Reference-oriented. No requirement definition is copied between artifacts.

## 17.1 Source → PRD → Story → Use Case → Flow → Candidate

| Brief ID | PRD capability | User Story | Use Case | Journey / Flow | Candidate |
|---|---|---|---|---|---|
| AUTH-01 | C-01 | US-001…US-005 | UC-001 | J-01 | CAND-001…CAND-005 |
| AUTH-02 | C-02 | US-006 | UC-002 | J-01 | CAND-006 |
| USR-01 | C-03 | US-007, US-008, US-009 | UC-003 | J-02 | CAND-007, CAND-008, CAND-009 |
| PRD-01 | C-04 | US-010…US-014 | UC-004, UC-005 | J-02 | CAND-010…CAND-013 |
| WH-01 | C-05, C-07 | US-015, US-016 | UC-006 | J-02, J-08 | CAND-014, CAND-015 |
| §1.3 (supplier/customer) | C-06 | US-017 | UC-007 | J-02 | CAND-016 |
| PO-01 | C-08, C-09 | US-018, US-019, US-020 | UC-008, UC-009 | J-03, J-04, §9.1, §9.4 | CAND-017, CAND-018, CAND-019 |
| SO-01 | C-10, C-11, C-12 | US-021…US-026 | UC-010…UC-014 | J-05, J-06, J-07, §9.2, §9.3, §9.5 | CAND-020, CAND-021, CAND-022, CAND-023 |
| VIEW-01 | C-18 | US-029 | UC-015 | J-08 | CAND-024 |
| FIND-01 | C-18 | US-030, US-031, US-032 | UC-016 | J-08 | CAND-025…CAND-028 |
| DASH-01 | C-14 | US-033…US-036 | UC-017 | J-09 | CAND-029…CAND-032 |
| REPORT-01 | C-15 | US-037 | UC-018 | J-10 | CAND-033 |
| API-01 | C-16 | US-038 | UC-019 | J-11 | CAND-034, CAND-035 |
| VAL-01 | — (cross-cutting) | US-039 | UC-003…UC-014 | J-02…J-07 | CAND-036, CAND-037 |
| ERR-01 | — (cross-cutting) | US-040, US-041 | UC-015, UC-019 | J-01, J-08, J-11 | CAND-038, CAND-039 |
| UI-01 | — (cross-cutting) | US-042 | UC-015, UC-017 | J-01, J-08, J-09 | CAND-040, CAND-041 |
| DB-01 | C-13 | US-043, US-044 | UC-009, UC-014 | §9.4, §9.5, §9.6 | CAND-042, CAND-043, CAND-044 |
| JOB-01 | C-17 | US-045 | UC-020 | J-12 | CAND-045 |
| ARCH-01 | — (architecture) | US-046 | all services | §14 | CAND-046…CAND-049 |
| ARCH-02 | — (architecture) | US-027 | UC-009, UC-014 | §9.4, §9.5, §9.6 | CAND-050, CAND-051 |
| DESIGN-01…04 | — (evidence) | — | — | — | CAND-052…CAND-055 |
| TEST-01…03 | — (evidence) | — | — | — | CAND-056…CAND-059 |
| §5.1 (Docker) | — (environment) | — | — | — | CAND-060, CAND-061 |
| §7.1 (demo data) | — (environment) | — | — | — | CAND-062 |

## 17.2 Candidate → DFD process → ERD entity → Class diagram element

| Candidate | DFD process | ERD entity | Initial class diagram element |
|---|---|---|---|
| CAND-001…006 | P1 | User | AuthController, AuthService, UserRepositoryInterface |
| CAND-007…009 | P2 | User | UserController, UserService, UserRepositoryInterface |
| CAND-010…013 | P3 | Product, Category | ProductController, ProductService, CategoryService, ProductRepositoryInterface, CategoryRepositoryInterface |
| CAND-014, 015 | P3, P6, P10 | Warehouse, ProductStock | WarehouseService, StockQueryService, ProductStockRepositoryInterface |
| CAND-016 | P3 | Supplier, Customer | SupplierService, CustomerService, SupplierRepositoryInterface, CustomerRepositoryInterface |
| CAND-017 | P4 | PurchaseOrder, PurchaseOrderItem | PurchaseOrderController, PurchaseOrderService, PurchaseOrderRepositoryInterface |
| CAND-018, 019 | P5 (P5.1…P5.4) | PurchaseOrder, PurchaseOrderItem, ProductStock, StockLedger | GoodsReceiptService, ProductStockRepositoryInterface, StockLedgerRepositoryInterface, TransactionManagerInterface |
| CAND-020 | P6 | SalesOrder, SalesOrderItem | SalesOrderController, SalesOrderService, SalesOrderRepositoryInterface |
| CAND-021 | P7 (P7.1…P7.3) | SalesOrder (created_by, approved_by), User | ApprovalService |
| CAND-022, 023 | P8 (P8.1…P8.5) | SalesOrder, ProductStock, StockLedger | GoodsIssueService, ProductStockRepositoryInterface, StockLedgerRepositoryInterface, TransactionManagerInterface |
| CAND-024…027 | P4, P6, P9 | Product, PurchaseOrder, SalesOrder | ProductController, PurchaseOrderController, SalesOrderController |
| CAND-028, 044, 062 | — (seed) | all | — (database/ per §4.1) |
| CAND-029…032 | P9 (P9.1…P9.5) | Product, ProductStock, PurchaseOrder, SalesOrder, StockLedger | DashboardController, DashboardService |
| CAND-033 | P9 (P9.4, P9.5) | StockLedger, PurchaseOrder, SalesOrder | ReportController, ReportService |
| CAND-034, 035 | P10 | Product, ProductStock | ProductAvailabilityApiController, AvailabilityService |
| CAND-036, 037 | P2…P8 | all written entities | Service layer (all) |
| CAND-038, 039 | P1…P10 | — | Controller layer (all) |
| CAND-040, 041 | — (presentation) | — | views/ per §4.1 |
| CAND-042, 043 | P5, P8 | ProductStock, StockLedger | PdoTransactionManager, MySql* repositories |
| CAND-045 | P11 | Product, ProductStock | CheckLowStockScript, LowStockService |
| CAND-046…049 | all | all | Repository interface layer + fake implementations |
| CAND-050, 051 | P8 | ProductStock, StockLedger | GoodsIssueService, TransactionManagerInterface |
| CAND-052…059 | — (evidence) | — | §14 initial diagram; docs/ per §4.1 |
| CAND-060, 061 | — (environment) | — | — |

Example chain in the required form:

```text
US-027
→ CAND-050
CAND-050
→ DFD P8 (P8.2, P8.3, P8.4)
CAND-050
→ ERD ProductStock, StockLedger
CAND-050
→ Initial Class Diagram GoodsIssueService, TransactionManagerInterface
```

## 17.3 Existing UI/UX coverage mapping

Existing UI/UX is the approved baseline and is not redesigned. Coverage is assessed against the
26-screen documented baseline (`docs/design/ioms-ui-design.md`, produced earlier in this project).

| Candidate area | Existing screen | Coverage | Gap |
|---|---|---|---|
| CAND-001…006 Authentication | Login | FULLY SUPPORTED | — |
| CAND-007…009 User management | Users, User Create/Edit | FULLY SUPPORTED | — |
| CAND-010…013 Product & category | Products list, Product Detail, Product Create/Edit, Categories | FULLY SUPPORTED | — |
| CAND-014, 015 Warehouse & stock | Warehouses, Warehouse Stock Detail | FULLY SUPPORTED | — |
| CAND-016 Supplier & customer | Suppliers, Customers | FULLY SUPPORTED | — |
| CAND-017 Purchase Order | Purchase Orders, PO Detail, PO Create/Edit | FULLY SUPPORTED | — |
| CAND-018, 019 Goods receipt | Goods Receipt | FULLY SUPPORTED | — |
| CAND-020 Sales Order | Sales Orders, SO Detail, SO Create/Edit | FULLY SUPPORTED | — |
| CAND-021 Approval / SoD | SO Detail (approval actions, `Self-Approval Restricted` state) | FULLY SUPPORTED | — |
| CAND-022, 023 Goods issue | Goods Issue | FULLY SUPPORTED | — |
| CAND-024…027 List / find | All list screens (search, filter, sort select, pagination) | FULLY SUPPORTED | — |
| CAND-029…032 Dashboard | Admin / Sales / Warehouse dashboards | FULLY SUPPORTED | — |
| CAND-033 CSV report | Reports (tabular) | PARTIALLY SUPPORTED | Two Reports screens exist in the baseline with different report vocabularies and currencies; which is canonical is undetermined |
| CAND-034, 035 JSON API | — | NOT APPLICABLE | API has no UI surface |
| CAND-036…039 Validation & error | Validation and 403/404 states across screens | FULLY SUPPORTED | — |
| CAND-040, 041 Responsive | 360px specification present; sidebar collapse only demonstrated on the shell blueprint | PARTIALLY SUPPORTED | Product screens carry no responsive sidebar rule |
| CAND-045 Low-stock script | — | NOT APPLICABLE | CLI output, no UI surface |
| CAND-013 Stock ledger view | Stock Ledger | FULLY SUPPORTED | — |

Two `PARTIALLY SUPPORTED` findings are carried into §18 as GAP-006 and GAP-007. No redesign is
proposed here and no second UI specification is created.

## 17.4 Existing code — existing-state evidence only

Per the phase rule, existing implementation is **not** a source of product intent. Recorded solely as
existing-state evidence for later reconciliation:

```text
app/  database/  docker/  views/  public/  scripts/  tests/
composer.json  composer.lock  phpstan.neon  phpunit.xml  compose.yaml
.env  .env  README.md  SESSION-HANDOFF.md  ai-usage-log.md
```

No requirement, entity, class, or flow in this analysis was derived from these files. Reconciliation
between the approved design and the existing implementation belongs to a later phase.

---

# 18. ANALYSIS GAP REVIEW

Seven gaps. None invented a resolution; each is resolvable or is explicitly deferred to Phase 1.

| Gap ID | Description | Affected artifact | Impact | Resolution status |
|---|---|---|---|---|
| GAP-001 | Supplier and Customer appear in the §1.3 data table and are required by PO-01/SO-01, but have no dedicated official requirement ID | US-017, UC-007, CAND-016 | Master-data management for counterparties has no requirement ID to trace to | **RESOLVED IN ANALYSIS** — modelled as `CANDIDATE — UNASSIGNED`; Phase 1 assigns canonical placement. No ID invented |
| GAP-002 | The brief requires approve **or reject** (§1.1 item 3, §1.2) but the SO status set contains no `Rejected` value (§1.3) | UC-013, §9.3, SalesOrder lifecycle | Terminal state after rejection is undefined | **DEFERRED TO PHASE 1** — no status invented. Phase 1 must decide whether rejection maps to `Cancelled`, returns to `Draft`, or requires a status the brief does not list. Brief FAQ 12 directs asking the trainer before changing scope |
| GAP-003 | `Adjustment` is a StockLedger movement type (§1.3) with no requirement, actor, or workflow that creates one | §9.6, StockLedger, D7 | An enumerated movement type has no originating use case | **DEFERRED TO PHASE 1** — modelled as ledger-supported with no workflow. No adjustment feature invented |
| GAP-004 | Role value is written `WarehouseStaff` (§1.3) while the label is "Warehouse Staff" (§1.1, §1.2) | Actors, User entity, authorization | Enum value vs display label ambiguity | **RESOLVED IN ANALYSIS** — treated as one actor with a stored value and a display label; Phase 1 states it canonically |
| GAP-005 | §1.2 denies Sales approval outright; SO-01 adds "termasuk order miliknya sendiri", which could be read as a narrower own-order-only restriction | SoD-1, UC-012, §9.3 | Scope of the Sales approval denial | **RESOLVED IN ANALYSIS** — read as a total denial with the own-order clause as emphasis; both source sentences quoted in §3.3 so Phase 1 can confirm |
| GAP-006 | The existing UI/UX baseline contains two Reports screens with different report vocabularies and currencies (IDR vs USD) | §17.3 CAND-033 coverage | Which report surface is canonical is undetermined | **DEFERRED TO PHASE 1** — UI baseline mapping decides; no redesign proposed and no screen discarded here |
| GAP-007 | UI-01 requires 360px usability; the baseline specifies 360px behaviour and a collapsed rail but implements the responsive sidebar only on the shell blueprint, not on product screens | §17.3 CAND-040 coverage | Responsive completeness of product screens | **DEFERRED TO PHASE 1** — recorded as a coverage gap against UI-01; no UI redesign performed |

## 18.1 Discrepancies against prior project context

Three differences between the brief and context previously established in this project. Recorded, not
resolved — resolving them would require changing a confirmed decision, which this phase must not do.

| # | Topic | Brief states | Prior project context | Status |
|---|---|---|---|---|
| DISC-001 | PHP version | **PHP 8.2+** (cover table, §4) | PHP 8.3.20 fixed (DEC-001) | **NOT A CONFLICT** — 8.3.20 satisfies "8.2+". Recorded for Phase 1 confirmation |
| DISC-002 | Redis | **Not mentioned anywhere** in 19 pages (0 occurrences) | Redis for auth/session state, TTL 3600 (DEC-005) | **DECISION BEYOND THE BRIEF** — Phase 1 must record this as a user decision that adds infrastructure the brief neither requires nor prohibits, and confirm it does not breach §0's anti-over-engineering warning |
| DISC-003 | Memcached | **Not mentioned anywhere** in 19 pages (0 occurrences) | Memcached as ephemeral read cache (DEC-006) | **DECISION BEYOND THE BRIEF** — same treatment as DISC-002 |

`[ANALYSIS INTERPRETATION]` DISC-002 and DISC-003 are material. The brief's §0 warning states that
*"layer tambahan yang tidak menyelesaikan masalah nyata"* is scored negatively, and §8.2 CF-3 makes
prohibited infrastructure a critical failure — though neither Redis nor Memcached is on the
prohibited list (§4). This analysis neither adopts nor rejects them: they are confirmed user
decisions, and Phase 1's DECISION REGISTRY and conflict governance own the outcome.

---

# 19. ANALYSIS COMPLETENESS CHECK

| Check | Result | Evidence |
|---|---|---|
| Project context complete | **PASS** | §01 — problem statement decomposed to P-1…P-5, assessment context, CF-1…CF-10, success criteria |
| PRD complete | **PASS** | §05 — all 11 required PRD elements present |
| Actors identified | **PASS** | §03 — 3 human actors with brief role values, 2 non-human, SoD constraint |
| Scope identified | **PASS** | §04 — 26 official IDs in scope, §4.3 exclusions verbatim, bonus, technology constraints, structure, demo data |
| User stories complete | **PASS** | §06 — 46 stories, all traced, 1 `CANDIDATE — UNASSIGNED` |
| Major journeys complete | **PASS** | §07 — all 12 required journeys, 8 fields each |
| Major use cases complete | **PASS** | §08 — 20 use cases, 11 fields each, `UC-###` namespace only |
| Major business flows complete | **PASS** | §09 — all 6 required flows using only brief-established statuses |
| System context complete | **PASS** | §10 — boundary, 5 external actors, external systems = none, I/O, boundary note |
| DFD complete | **PASS** | §11 — context diagram, Level 0 (11 processes, 7 stores), Level 1 for P5/P7/P8/P9 |
| ERD complete | **PASS** | §12 — 12 entities, PK/FK/cardinality, capability verification, 6 exclusions justified |
| Logical data model complete | **PASS** | §13 — all 12 entities with 6 fields each |
| Initial class diagram complete | **PASS** | §14 — 4 layers, dependency rules, deliberate omissions; not derived from existing code |
| Initial NFR complete | **PASS** | §15 — 32 NFRs across 8 categories, all brief-supported |
| Candidate requirements identified | **PASS** | §16 — 62 candidates, 4 `CANDIDATE — UNASSIGNED` |
| Traceability established | **PASS** | §17 — source→PRD→story→use case→flow→candidate, plus candidate→DFD→ERD→class |
| UI baseline considered | **PASS** | §17.3 — 18 coverage rows, 2 gaps raised, no redesign |
| Existing code not treated as requirements | **PASS** | §17.4 — listed as existing-state evidence only; §14 explicitly not code-derived |
| No invented official requirement IDs | **PASS** | All 26 IDs verified present in the brief; unmapped items marked `CANDIDATE — UNASSIGNED` |
| No competing source hierarchy | **PASS** | Brief is authoritative; no `SRC-*` created |
| No competing governance system | **PASS** | No registry created; Phase 1 retains SOURCE REGISTRY and DECISION REGISTRY |
| No requirement frozen | **PASS** | All items are `CANDIDATE` / `CANDIDATE — UNASSIGNED` |
| No product scope added | **PASS** | Scope is the brief's 26 IDs; bonus kept separate; exclusions preserved |
| No prohibited technology introduced | **PASS** | §4.4 prohibitions carried verbatim; none introduced |
| No TBD / TODO | **PASS** | Zero occurrences; open items carry explicit gap IDs with resolution status |

**Result: 25 / 25 PASS.**

---

# 20. PRE-CODING PHASE EXIT STATUS

| Required artifact | Status |
|---|---|
| PRD | COMPLETE |
| User Stories | COMPLETE (46) |
| User Journeys | COMPLETE (12) |
| Use Cases | COMPLETE (20) |
| Business Flows | COMPLETE (6) |
| System Context | COMPLETE |
| DFD | COMPLETE (context + Level 0 + Level 1 ×4) |
| ERD | COMPLETE (12 entities) |
| Logical Data Model | COMPLETE (12 entities) |
| Initial Class Diagram | COMPLETE |
| Initial NFR | COMPLETE (32) |
| Analysis Traceability | COMPLETE |
| Analysis Completeness Check | **PASS** (25/25) |

## Declaration

> # PRE-CODING PRODUCT & SYSTEM ANALYSIS COMPLETE

All required artifacts exist and the completeness check passes.

## Carried forward to Phase 1

Not blockers — items Phase 1 owns by design:

```text
GAP-002   Sales Order rejection terminal state undefined by the brief
GAP-003   Adjustment movement type has no originating workflow
GAP-006   Two Reports screens in the UI baseline; canonical surface undetermined
GAP-007   Responsive sidebar implemented only on the shell blueprint

DISC-001  PHP 8.2+ (brief) vs 8.3.20 (DEC-001) — compatible, confirm
DISC-002  Redis absent from the brief; DEC-005 adds it
DISC-003  Memcached absent from the brief; DEC-006 adds it

4 candidates marked CANDIDATE — UNASSIGNED: CAND-016, CAND-060, CAND-061, CAND-062
```

Phase 1 was not executed. Phase 2 was not executed. No code, migration, test, or UI change was made.

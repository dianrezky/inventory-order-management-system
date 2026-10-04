# IOMS Design Audit

**Date:** 2026-09-07 · **Pass:** final pre-implementation gate
**Method:** the Stitch archive was extracted and every `code.html`, `DESIGN.md` and PNG header was read
programmatically. Every claim below is backed by a value read out of a file, not inferred.

---

## Audit Status

**FAIL**

Implementation must not begin. Nine blocking findings remain (B-01 … B-09), of which four are
requirement/design contradictions and one (B-01) means the audit's own token baseline cannot be
certified, because no approved artifact defines it.

---

## Scope

Audited:

- the approved requirement chain (Product Vision → PRD → UX/UI Spec) as the requirement authority;
- the architecture record (ADR-001…004, api-contract, db-schema, class diagrams);
- the Google Stitch export `stitch_remix_of_remix_of_enterprise_ioms_design_system_fix`
  (33 folders / 31 `code.html` / 31 PNG / 1 `DESIGN.md`), read from the archive itself;
- `docs/design/ioms-ui-design.md` (2,926 lines), cross-checked against that export;
- the implemented front-end surface (`public/assets/css/tokens.css`, `main.css`, `theme.js`,
  `i18n-init.js`, `views/**`).

Not audited (do not exist — see F-01): `docs/design/common-states.md`, `docs/design/reports.md`.

Nothing was redesigned. No state, mode, alias, taxonomy or lifecycle model was created. No requirement
was reinterpreted. The only file written is this report.

---

## Source Inventory

### Requirement & architecture authority

| File | Role |
|---|---|
| `docs/planning/prd.md` | 22 requirements, BR-001…BR-022. Requirement authority. |
| `docs/planning/ux-ui-spec.md` | Stage 3 v1.1, self-declared **"single source of truth"** for tokens, 12 components, 14 wireframes, accessibility checklist. |
| `docs/planning/product-vision.md`, `delivery-plan.md` | Scope and sequencing. |
| `docs/architecture/adr-001…004`, `api-contract.md`, `db-schema-design.md`, `class-diagram-*.md`, `sequence-diagrams.md` | Technical decisions. |
| `docs/quality/wcag-contrast-audit.md` | Prior contrast record. |

### Design authority

| File | Role |
|---|---|
| `…/operational_enterprise_ioms/DESIGN.md` (12,225 B, 250 lines) | Stitch token + style manifest. |
| 31 × `…/code.html` (1,789,051 B total) | Generated screens. |
| 31 × `…/screen.png` | Rendered specimens. |
| `docs/design/ioms-ui-design.md` (280,394 B) | Markdown description of the export. |

### Implemented surface

`public/assets/css/tokens.css` (123 L) · `public/assets/css/main.css` (628 L) ·
`public/assets/img/icons.svg` · `public/assets/js/theme.js` · `public/assets/js/i18n-init.js` ·
`public/assets/locales/{en,id}/translation.json` · `views/**` (26 PHP views).

---

## Requirement ↔ Design Findings

### R-01 — No agreed design-token baseline exists; five vocabularies are in play

- **Classification:** TOKEN_GAP (blocking)
- **Source:** audit brief §G · `ux-ui-spec.md` §1.1 · `tokens.css` · `DESIGN.md` · app-lineage `tailwind.config` · login-lineage `tailwind.config`
- **Finding:** five mutually incompatible palettes claim canonical status:
  1. **`ux-ui-spec.md` §1.1 / `tokens.css`** — the *approved* set, and the one actually implemented:
     primary `#2563EB`, surface `#F5F6F8`, text `#1A1D23`, border `#E1E4E8`, warning `#B45309`,
     danger `#DC2626`, info `#0E7490`, radius 4/8/12, body 14/20, **with a full dark theme**.
  2. **Stitch app-lineage `tailwind.config`** — Material-3 names: primary `#00236f`, surface `#f8f9ff`,
     error `#ba1a1a`, `borderRadius.full = 0.75rem`.
  3. **Stitch `DESIGN.md` prose** — slate/navy: primary `#1e3a8a`, success `#059669`, warning `#d97706`,
     danger `#dc2626`, info `#0284c7`, default radius 4px, **pill shapes explicitly prohibited**.
  4. **Stitch login-lineage `tailwind.config`** — `brand`/`surface`/`neutral`/`semantic` names.
  5. **The token list in this audit's own brief §G** — see R-02.
- **Impact:** "token consistency" is not decidable. Any implementation choice silently contradicts
  three of the five.
- **Required resolution:** a single named token set must be designated authoritative by decision record
  before implementation. On the evidence, `ux-ui-spec.md` §1.1 is the only candidate that is approved,
  contrast-verified, dark-theme-complete and already implemented. This audit does not make that decision.

### R-02 — Three of the brief's canonical token values are attested in no source at all

- **Classification:** TOKEN_GAP (blocking)
- **Source:** audit brief §G, cross-checked against every `code.html`, `DESIGN.md`, `ioms-ui-design.md`, `tokens.css`, `main.css`
- **Finding:** literal-string search across all sources:

  | Brief token | Value | Stitch export | Design doc | Repo CSS |
  |---|---|---|---|---|
  | Primary hover | `#1D4ED8` | **0** | **0** | **0** |
  | Warning | `#A16207` | **0** | **0** | **0** |
  | Warning background | `#FEFCE8` | **0** | **0** | **0** |
  | Success / bg | `#15803D` / `#F0FDF4` | 1 — a single specimen badge (`design_system_component_foundation` L873) | 2 | 2 / 0 |
  | Danger | `#B91C1C` | 1 — `DESIGN.md` destructive *border*, not fill | 2 | 0 |
  | Primary | `#2563EB` | 4 — but as `brand.hover`, **not** as primary | 9 | 2 |
  | Primary active | `#1E40AF` | 1 — as button **hover**, not active | 1 | 0 |

  Additionally: **Body `14px / 22px`** — the string `22px` occurs only as `text-[22px]` on Material
  Symbols glyphs; no 22px line-height exists anywhere. Both the export (`body-md`) and the approved
  spec define body as **14 / 20**. **Control heights `32 / 40 / 48`** — the measured control ladder is
  `h-7`(28) / `h-8`(32, 313 occurrences) / `h-9`(36) / `h-10`(40) / `h-11`(44); `h-12`/`h-14`/`h-16`
  occur only on header bars and avatar circles, never on a control. **Badge radius `999px`** — the app
  config redefines `rounded-full` to `0.75rem` (12px), and `DESIGN.md` prohibits pill shapes outright.
- **Impact:** the brief's list is a sixth vocabulary. Auditing "token compliance" against it would
  manufacture a baseline rather than verify one.
- **Required resolution:** withdraw or re-derive the brief's token list from whichever set R-01 designates.

### R-03 — The design omits two approved mandatory features: locale switching and theme switching

- **Classification:** REQUIREMENT_GAP (blocking)
- **Source:** PRD `I18N-01`, `THEME-01`; `ux-ui-spec.md` §3 (`[Header]` = "logo, **locale toggle, theme toggle**, user menu"); all 31 `code.html`
- **Finding:** no Stitch screen contains a locale switcher or a theme switcher. Every `locale` match in
  the export is `Number.prototype.toLocaleString()`. `darkMode:"class"` is declared in the config but
  **zero `dark:` utilities exist** across all 31 files (the two `dark:` string matches are the config
  key `brand.dark`). The approved header in `ux-ui-spec.md` §3 has both controls, and the repository
  already implements both (`theme.js` tri-state auto/light/dark, `i18n-init.js` with `en`/`id` bundles,
  full dark palette in `tokens.css`).
- **Impact:** building the shell to the Stitch header drops two approved requirements and discards
  working implemented functionality. Wireframe §3 and the Stitch shell cannot both be built.
- **Required resolution:** decide which header is authoritative and record it. If Stitch, `I18N-01` and
  `THEME-01` need an approved design surface; they cannot simply disappear.

**AMENDMENT (2026-09-08 — Product Scope Correction, see `docs/design/DESIGN_DECISION_RECORD_B01_B09.md`
§B-02):** R-03 is superseded for gate purposes. Locale switching (EN/ID) and theme switching
(Auto/Light/Dark) are declared **out of scope** for the current IOMS release by product-scope
decision, not by a new design finding — `I18N-01`/`THEME-01` are withdrawn as mandatory requirements
unless a future, separately-approved product requirement reinstates them. B-02 is renarrowed to
shared authenticated shell/header consistency only (one shell reused across screens, no arbitrary
per-screen variant). The historical finding above is preserved for audit-trail purposes; it no
longer blocks the gate as a locale/theme omission. See the Blocking Findings and Final Gate tables
below, marked accordingly.

### R-04 — Report data visualization has no requirement basis

- **Classification:** REQUIREMENT_GAP (blocking)
- **Source:** PRD `REPORT-01`; `ux-ui-spec.md` §3.14; `reports_with_data_visualization_…/code.html`
- **Finding:** `REPORT-01` is titled *CSV Export*. Its FRs cover exactly two reports (StockLedger by
  `done_at`; Order Status PO+SO by `order_date` + status), locale-dependent headers, RFC 4180 escaping,
  and Sales own-SO scoping. Its **Out of Scope** line names xlsx, PDF, scheduled email and custom column
  selection. The words *chart*, *graph*, *visuali(s|z)ation* appear **zero times** in `prd.md`,
  `ux-ui-spec.md` and `product-vision.md`. The approved wireframe §3.14 is a filter row, a Preview
  button, an Export CSV button and a 20-row table preview — no visualization. The Stitch reports-viz
  screen adds four KPI tiles, a three-tab analytics block, an inline SVG dual-axis chart, a CSS
  stacked-bar distribution and CSS allocation cards.
- **Impact:** a large share of that screen implements unrequested scope, on a P1 requirement, in a
  final-project context where mandatory requirements outrank bonus features (CLAUDE.md §1, §5).
- **Required resolution:** either add an approved requirement for report visualization, or scope the
  visualization block out. Do not implement it on visual precedent alone. Note that the constraint
  *"visualization must not silently replace tabular data"* is **satisfied** — see Report Audit.

### R-05 — Reports design contradicts approved report vocabulary, scope and currency

- **Classification:** DESIGN_GAP (blocking)
- **Source:** PRD `REPORT-01` FR-11.1/11.2 · both Reports screens
- **Finding:** `REPORT-01` defines exactly two report types. The export ships **two independent,
  non-interchangeable Reports screens**:
  - `reports_…` — tabs `Stock Movement` / `Order Status`, IDR data. Aligns with FR-11.1/11.2.
  - `reports_with_data_visualization_…` — a three-option selector `STOCK_VALUATION` /
    `SALES_FULFILLMENT` / `INVENTORY_MOVEMENTS`, **USD** data (`$1,482,950.00`), plus a
    `Product Category` filter and a `Columns (9)` control.

  None of `Stock Valuation & Turnover`, `Sales & Order Fulfillment Performance` or
  `Inventory Movements & Audit Ledger` is an approved report type. USD contradicts the IDR domain used
  on the other 24 screens. `Columns (9)` implies custom column selection, which `REPORT-01` explicitly
  places **out of scope**.
- **Impact:** the design asserts a report catalogue and an export capability the system will not have.
- **Required resolution:** designate one Reports screen as authoritative and reconcile its report types
  to FR-11.1/FR-11.2, its currency to IDR, and remove the column-selection affordance.

### R-06 — "My Profile" has no requirement backing and implies unsupported self-service

- **Classification:** DESIGN_GAP (non-blocking, scope decision)
- **Source:** `my_profile_…/code.html`; `prd.md` (string `profile` occurs **0** times)
- **Finding:** the screen ships DATA (clean/dirty/two other roles), VALIDATION_ERROR, ACTION_LOADING,
  MUTATION_SUCCESS, CONFIRMATION (unsaved), LOADING, SERVER_ERROR and a **MUTATION_ERROR for an email
  conflict** — implying users may change their own email. `AUTH-01` and `USR-01` grant no self-service
  profile mutation.
- **Impact:** an unrequested 27th screen with an unauthorised mutation path.
- **Required resolution:** add a requirement or drop the screen. Do not implement on design precedent.

### R-07 — Design terminology asserts an architecture the system does not have

- **Classification:** DESIGN_GAP (non-blocking, copy-level)
- **Source:** `system_states_…/code.html` state registry; various screens
- **Finding:** user-facing copy names `JWT / Refresh token revocation`, `record UUID`, `core backend
  microservice`, `tenant inventory register`, `upstream inventory ledger service`, `handheld scanner
  Wi-Fi`, `warehouse mesh access point`, `Contact Systems SRE`, `bin locations`, `Initiate
  Inter-Warehouse Transfer`, `credit hold by corporate treasury`. The approved architecture is a single
  PHP 8.2 native monolith with PDO, **session** auth (ADR-001), integer PKs, no multi-tenancy, no bins,
  no transfers, no credit control.
- **Impact:** copied verbatim, the UI would describe a system that does not exist — an assessment risk
  under CLAUDE.md §6 ("explainable, and defensible during technical assessment").
- **Required resolution:** treat all state-registry copy as placeholder; re-author against the
  Ubiquitous Language in `product-vision.md` §9 during implementation.

### R-08 — Approved 14-wireframe set vs 26-screen design, with no reconciliation record

- **Classification:** REQUIREMENT_GAP (non-blocking, traceability)
- **Source:** `ux-ui-spec.md` §3 (14 wireframes, "single source of truth") · export §1.1 (26 product screens)
- **Finding:** two approved design descriptions of the same product exist at different granularity and
  in different visual languages, and neither supersedes the other in writing. `ux-ui-spec.md` still
  self-declares as the sole source of truth; `ioms-ui-design.md` §19 declares itself a description of the
  export only and explicitly proposes no requirements. Nothing states which governs implementation.
- **Impact:** no defensible traceability from requirement to screen.
- **Required resolution:** record the supersession decision (or the split of authority) explicitly.

---

## Stitch ↔ Markdown Findings

`docs/design/ioms-ui-design.md` is, overall, unusually faithful. Verification confirmed exactly: all 31
PNG dimensions; the byte-identical `login` ⇄ `inventory_order_management_system_flow` duplicate (md5
`3ca51a95…`, both 20,825 B); the two-and-only-two `<title>` tags; the absence of any `2xl:` prefix; the
absence of `dark:` utilities; the complete reports-viz state machine including the 700 ms timer, pip
colours and export labels; and the `pl-64` claim on all 28 shell screens. The §18.4 inconsistency
register is genuine and self-critical. The findings below are the exceptions.

### M-01 — The `tailwind.config` is **not** identical across app screens; Goods Issue is missing the entire type scale

- **Classification:** DESIGN_GAP (blocking) + DOCUMENTATION_GAP
- **Source:** `ioms-ui-design.md` §3.1, §4.2, §5.1 headings vs `goods_issue_…/code.html`
- **Finding:** the doc asserts the config is "identical in all 26 app screens" (§3.1), the `fontSize`
  tokens are "identical in `DESIGN.md` and every app-lineage config" (§4.2), and the spacing tokens are
  "identical in `DESIGN.md` and every app config" (§5.1). Brace-matched extraction of every config gives
  **three** groups, not two: 27 screens share one config (3,240 B); login + its duplicate share another
  (1,274 B); and **`goods_issue` has a third, 2,766 B**. Diffed, Goods Issue carries the same `colors`,
  `borderRadius` and `fontFamily` but **omits `fontSize` and `spacing` entirely**. Consequently every
  `text-body-md`, `text-label-xs`, `text-headline-xl` … class on that screen is undefined and generates
  no utility — all typography on Goods Issue falls back to browser default.
- **Impact:** Goods Issue is a P1 transactional screen (`SO-01`, and the ARCH-02 overselling path). Its
  rendered PNG is not a faithful specimen of the type system, and three documentation claims an
  implementer would rely on are false.
- **Required resolution:** correct §3.1/§4.2/§5.1 to record the divergence, add it to the §18.4 register,
  and decide explicitly whether Goods Issue inherits the standard type scale.

### M-02 — `th scope="col"` inventory is wrong in both directions

- **Classification:** DOCUMENTATION_GAP (accessibility-relevant)
- **Source:** `ioms-ui-design.md` §11.10 vs all `code.html`
- **Finding:** the doc states `scope="col"` is "present on Products list, Suppliers, Customers and
  Warehouses tables **only**". Measured counts: `products_list` 9, `goods_receipt` 8, `sales_dashboard` 7,
  `warehouse_stock_detail` 7, `sales_order_detail` 6, `suppliers` 5, `warehouses` 4, `categories` 3 — and
  **`customers` has zero**. The doc names one screen that has none and omits four that do.
- **Impact:** an implementer using §11.10 as the accessibility baseline would under-scope four tables and
  wrongly assume Customers is compliant.
- **Required resolution:** replace the §11.10 sentence with the measured eight-screen list.

### M-03 — Export/folder/file counts are wrong in §0 and §19

- **Classification:** DOCUMENTATION_GAP
- **Source:** `ioms-ui-design.md` §0 opening line, §19 provenance table
- **Finding:** §0 says "**31 generated folders**"; §19 says "Folders **31** (29 with `screen.png`, 28 with
  `code.html`, 1 `DESIGN.md`)" and "~1.83 MB across **28** files". Measured: **33 folders, 31 `screen.png`,
  31 `code.html`, 1 `DESIGN.md`, 1,789,051 B (1.71 MiB) across 31 files**. The §0 *table* is correct and
  enumerates all 33 rows with correct per-folder contents — only the summary counts and the provenance
  row are wrong.
- **Impact:** low, but §19 is the provenance record an assessor would check first.
- **Required resolution:** correct both counts to 33 / 31 / 31 / 1 and the size to ~1.71 MiB across 31 files.

### M-04 — §10.3 says "only on 4 screens" then lists five

- **Classification:** DOCUMENTATION_GAP
- **Source:** `ioms-ui-design.md` §10.3, row "Tables → stacked cards"
- **Finding:** the cell reads "**Only on 4 screens**: Users, Stock Ledger, PO create/edit, Goods Receipt,
  SO create/edit (5 total)" — self-contradictory in one sentence. §10.5 correctly lists five.
- **Impact:** low.
- **Required resolution:** change "4 screens" to "5 screens".

### M-05 — Cross-reference error: state registry cited as §13.2, is §14.2

- **Classification:** DOCUMENTATION_GAP
- **Source:** `ioms-ui-design.md` §11.10, final sentence
- **Finding:** points the reader to "the system-states registry (§13.2)"; §13.2 is the reports-viz
  visualization block header. The registry is §14.2.
- **Impact:** low.
- **Required resolution:** change §13.2 → §14.2.

### M-06 — The reports-viz EMPTY/NO_RESULTS misclassification is not in the §18.4 register

- **Classification:** DOCUMENTATION_GAP (see S-02 for the underlying design defect)
- **Source:** `ioms-ui-design.md` §13.7, §14.6 vs §14.4
- **Finding:** §13.7 and §14.6 faithfully record that reports-viz ships a state keyed `EMPTY`, and §14.4
  faithfully reproduces the rule that `/reports` permits only STALE_DATA · LOADING · DATA · NO_RESULTS.
  The document therefore contains both halves of a contradiction, verbatim and correctly, but never
  registers it in §18.4 where its other eleven contradictions are collected.
- **Impact:** the most consequential state defect in the design is the one an implementer is least likely
  to notice, because §18.4 is the section that exists to surface exactly this.
- **Required resolution:** add S-02 and S-03 to the §18.4 register.

---

## Common State Audit

**Result: the 16 canonical states are internally consistent as a registry, and are not carried into the
design's own screens.**

The registry exists, complete and unpolluted, on `system_states_inventory_order_management` only: all 16
present, ids `01_LOADING` … `16_ACTION_LOADING`, **no 17th state, no alias, no mode, no report-specific
state**. The header rule "No aliases permitted" is printed with the matrix.

Against that, the 30 other screens implement their states through **51 distinct ad-hoc local keys** passed
to `setAppState()` / `setScenario()` / `setViewState()`:

`404 · BLANK · DATA · EMPTY · LOADING · STALE · active · admin · admin-default · auth-error · clean ·
conflict · create · default · dirty · edit · empty · empty-catalog · empty-search · empty-stock · error ·
filter-changed · forbidden · freshIntake · fullReceipt · inactive · ineligible · initial · loading ·
low-stock · modal · no-results · nomatch · normal · nostock · not-found · operationError ·
order-generated · partiallyReceived · processing · readonly · sales · saving · skeleton · staff ·
staleData · stock-generated · success · toast · validation · validationErrors`

Only three (`DATA`, `EMPTY`, `LOADING`) are canonical names. Eleven of the sixteen canonical names appear
on **zero** product screens.

| State | Design (registry) | Design (product screens) | Documentation | Requirement | Result |
|---|---|---|---|---|---|
| LOADING | ✅ `01_LOADING` | via `loading` / `skeleton` / `processing` (6 screens) | §14.1/.2/.5 ✅ | ERR-01, VIEW-01 | **NO_ISSUE** (naming only) |
| DATA | ✅ `02_DATA` | via `normal` / `default` / `active` / role keys | ✅ | VIEW-01 FR-8.1/8.2 | **NO_ISSUE** |
| EMPTY | ✅ `03_EMPTY` | `empty` — **but used for filter-zero on both Reports screens** | ✅ recorded, contradiction unflagged | VIEW-01 FR-8.3/8.4 | **STATE_GAP** (S-02) |
| NO_RESULTS | ✅ `04_NO_RESULTS` | `no-results` / `nomatch` / `empty-search`; **absent from both Reports screens** | ✅ recorded | FIND-01 "Search 0 hasil" | **STATE_GAP** (S-02) |
| VALIDATION_ERROR | ✅ | `validation` / `validationErrors` | ✅ | VAL-01 | **NO_ISSUE** (naming only) |
| FORBIDDEN | ✅ | `forbidden` (2 screens) | ✅ | ERR-01 FR-14.3 | **STATE_GAP** (S-03) |
| NOT_FOUND | ✅ | `404` / `not-found` | ✅ | ERR-01 FR-14.2 | **NO_ISSUE** (naming only) |
| SESSION_EXPIRED | ✅ | **no screen ships it** | ✅ §14.6 states this explicitly | AUTH-01 session timeout | **STATE_GAP** (S-04) |
| SERVER_ERROR | ✅ | `error` | ✅ | ERR-01 FR-14.4/14.5 | **STATE_GAP** (S-03) |
| NETWORK_ERROR | ✅ | `error` on 2 dashboards | ✅ | — (no PRD requirement) | **REQUIREMENT_GAP** (minor, F-04) |
| MUTATION_SUCCESS | ✅ | `success` / `toast` | ✅ | VAL-01 | **NO_ISSUE** (naming only) |
| MUTATION_ERROR | ✅ | `operationError` / `conflict` / `auth-error` | ✅ | VAL-01, ERR-01 | **NO_ISSUE** (naming only) |
| BUSINESS_ERROR | ✅ | Goods Issue insufficient-stock; login inactive-account | ✅ | BR-001, ARCH-02 | **NO_ISSUE** |
| STALE_DATA | ✅ | `STALE` / `staleData` / `filter-changed` | ✅ | — (no PRD requirement) | **REQUIREMENT_GAP** (minor, F-04) |
| CONFIRMATION | ✅ | native `<dialog>` + JS modals (7 screens) | ✅ | SO-01, BR-001 | **NO_ISSUE** |
| ACTION_LOADING | ✅ | `saving` / `processing` | ✅ | VAL-01 | **NO_ISSUE** (naming only) |

### S-01 — 51 undeclared local state keys against a registry that forbids aliases

- **Classification:** STATE_GAP (blocking)
- **Finding:** the registry prints "Strict definition table. **No aliases permitted.**" while the screens
  it governs use 48 aliases for the 16 states.
- **Impact:** without a mapping, implementation will reproduce the aliases and the 16-state model becomes
  documentation only.
- **Required resolution:** produce a one-way mapping table (51 local keys → 16 canonical states) before
  implementation. This introduces no new state; it records which of the existing 16 each existing key
  already means. Two keys need an explicit decision rather than a mechanical mapping: `BLANK` (see S-02)
  and `ineligible` (Goods Receipt / Goods Issue), which as generated is neither FORBIDDEN (not a 403) nor
  BUSINESS_ERROR.

### S-02 — Both Reports screens use EMPTY where the registry defines NO_RESULTS

- **Classification:** STATE_GAP / REPORT_GAP (blocking)
- **Source:** `reports_…` `case 'empty'`; `reports_with_data_visualization_…` `state === 'EMPTY'`; registry §14.1 rows 03/04; §14.4 Reports row
- **Finding:** the registry defines EMPTY as "Dataset has exactly **0 total records** in tenant domain"
  and NO_RESULTS as "**Active search filters** return 0 records from populated dataset". Both Reports
  screens key their zero-result view `empty`/`EMPTY`, yet the copy is unambiguously filter-scoped —
  "**Zero Records Match Your Parameters** … No inventory transactions or turnover ledgers **matched the
  selected filter configuration** for this timeframe" — and the tabular screen's own export tooltip reads
  "No records found for **current criteria**". Meanwhile §14.4 permits NO_RESULTS on `/reports` and does
  **not** permit EMPTY, and NO_RESULTS is shipped on neither screen. The design doc's own §18.5 fidelity
  checklist insists "Keep EMPTY and NO_RESULTS as separate components with different icons and different
  primary actions" — the Reports screens break the rule the same document sets.
- **Impact:** the canonical distinction that FIND-01 and VIEW-01 both depend on is inverted on the screen
  where it is most load-bearing.
- **Required resolution:** reclassify both zero-result Reports views to NO_RESULTS. This creates no new
  state — it applies an existing one. Register the change in §18.4.

### S-03 — Reports screens ship three states the screen→state map does not permit

- **Classification:** STATE_GAP (blocking)
- **Source:** §14.4 Reports row vs `reports_…/code.html` `setAppState` cases
- **Finding:** §14.4 permits `/reports`: STALE_DATA · LOADING · DATA · NO_RESULTS. The tabular Reports
  screen ships `empty`, `error` (SERVER_ERROR) and `forbidden` (FORBIDDEN) in addition, all three with
  full QA-harness buttons. §14.6 records them without flagging the conflict.
- **Impact:** the screen→state map, which the design presents as normative, is contradicted by the
  design's own screen. Either the map or the screen is wrong; nothing says which.
- **Required resolution:** decide explicitly. Note that FORBIDDEN on Reports is *required* by
  `REPORT-01`'s authorization matrix (Sales may export only their own orders) and by ERR-01 FR-14.3, so
  the map is the more likely error — but this audit does not amend the map.

### S-04 — SESSION_EXPIRED is defined and permitted but never designed

- **Classification:** STATE_GAP (non-blocking)
- **Source:** §14.4 (permitted on `/login`), §14.6 ("No screen ships a SESSION_EXPIRED view")
- **Finding:** exists only as a specimen. `AUTH-01` requires session handling and ERR-01 FR-14.1 requires
  unauthenticated access to redirect to `/login`.
- **Impact:** an implementer has a specimen but no screen placement.
- **Required resolution:** confirm the specimen is the design of record for the `/login` re-auth card.

---

## Report Audit

| Aspect | Tabular Reports | Reports-viz | Verdict |
|---|---|---|---|
| **Generation** | 8 keys; `handleGenerateClick()` → `loading` → 450 ms → `stock-generated`/`order-generated`. Tab switch resets to `initial`. | 5 keys; `triggerGenerateSequence()` → `LOADING` → 700 ms → `DATA`. | **REPORT_GAP** — two incompatible generation models, two timings, two vocabularies, both "present in the design" per §1.3. |
| **Pre-generation** | `initial`, export disabled, reason "Generate a report before exporting." | `BLANK`, "No Report Generated Yet" + `Run Default Report`. | Consistent with §14.4 "Pre-generation is normal DATA" — but `BLANK` uses the EMPTY anatomy (icon + action) while being classified DATA. Flag for the S-01 mapping. |
| **DATA** | Two generated tables (Stock Movement / Order Status), IDR. | KPI tiles + 3-tab viz + 9-column table, USD, 124 records, 25 pages. | **REPORT_GAP** (R-05): USD and the report-type catalogue are unapproved. |
| **NO_RESULTS** | **Not implemented** — filter-zero is keyed `empty`. | **Not implemented** — filter-zero is keyed `EMPTY`. | **REPORT_GAP** (S-02), blocking. |
| **STALE_DATA** | `handleFilterInputChanged()` → `filter-changed`; stale banner + table dimmed `opacity-60 blur-[0.5px]`; export disabled. | `onFilterChange()` → `STALE`; stale banner; **table and charts stay fully legible**; export disabled. | Semantics correct and consistent on both (criteria change post-generation ⇒ STALE_DATA). Treatment differs; the viz screen's is the more accessible. **NO_ISSUE** on semantics; minor inconsistency on treatment. |
| **CSV export — enabled** | Only in `stock-generated` / `order-generated`. | Only in `DATA`; `handleExportClick()` returns early otherwise. | Consistent rule: **export requires a current generated result**. **NO_ISSUE**. |
| **CSV export — disabled** | Disabled in `initial`, `filter-changed`, `empty`, `loading`, `error`, `forbidden`, each with a distinct tooltip reason. | Disabled in `STALE`, `BLANK`, `LOADING`, `EMPTY`; encoded by pip colour **and** label suffix (`Export CSV (Stale)`, `Export CSV (0)`). | Rules agree; **presentation does not** — one uses a hover tooltip, the other a coloured pip. See A-03: the tooltip is hover-only. **REPORT_GAP**, non-blocking. |
| **Visualization** | None. | One hand-authored inline SVG (`viewBox="0 0 900 240"`, `role="img"`, `aria-label="Stock valuation and movements over last 6 months"`) plus two CSS-only charts. No charting library is loaded anywhere in the export — no Chart.js, D3, ECharts or `<canvas>`. | **REQUIREMENT_GAP** (R-04). Technically: static geometry, no scale function, no tooltip, no hover, `preserveAspectRatio="none"` (stretches non-uniformly), and no right-hand axis despite being described as dual-axis. |
| **Viz ↔ data relationship** | n/a | The 9-column table is a **sibling** of the viz block, not a replacement: it renders inside `#state-data` alongside the charts and is visible in DATA and STALE. | **NO_ISSUE — the brief's key constraint is satisfied.** The visualization complements and never silently replaces the tabular data. |
| **Responsive** | `grid-cols-2 md:grid-cols-4` KPIs; `sm:grid-cols-5` filter row; table `overflow-x-auto`. | `sm:grid-cols-2 lg:grid-cols-4` KPIs and legend cards; `md:grid-cols-3` allocation cards; viz header `lg:flex-row`. | Blocks reflow correctly **in isolation**, but both screens inherit the unconditional `pl-64` — see E-01. **RESPONSIVE_GAP**, blocking. |

---

## Responsive Audit

### Desktop — consistent

Sidebar 256px fixed, header 56px fixed at `left-64 right-0`, content offset `pt-14`, z-index ladder
50/40/30/50. Content caps: `max-w-5xl`, `max-w-7xl`, `max-w-[1600px]`, `max-w-[1720px]` or uncapped.
Tables at 32px headers with horizontal scroll rather than column hiding. Only Tailwind's default
`sm`/`md`/`lg`/`xl` breakpoints are used; **no `2xl:` anywhere**; no custom breakpoints; no container
queries. **NO_ISSUE** — with the caveat that row heights vary `h-9`/`h-10`/`h-11`/`h-12` for the same
table role (already recorded in §18.4 #9).

### E-01 — 360px is structurally broken on all 28 shell screens

- **Classification:** RESPONSIVE_GAP (blocking)
- **Source:** every shell-bearing `code.html`
- **Finding:** measured across all 31 files: `pl-64` occurs on 28 screens, and the count of any responsive
  override (`sm:`/`md:`/`lg:`/`xl:` + `pl-64`/`pl-0`) is **zero on every single one**. The 256px sidebar is
  never dismissed. At a 360px viewport the content column is **104px wide**. The mobile drawer that would
  fix this exists **only** on the `authenticated_application_shell` blueprint, behind a JS mode switcher
  (`setShellMode('mobile')`), and the `lg:hidden` hamburger exists only there too. The blueprint's drawer
  specimen is additionally a **reduced** menu — Warehouses, Suppliers, Customers, Reports and Users are
  absent from it.
- **Impact:** directly violates `UI-01` FR-15.1 ("Login, Dashboard, List, Detail, Form berfungsi pada
  360px"), FR-15.2 ("body tidak overflow") and AC1 ("form muat tanpa scroll horizontal"). Guaranteed
  horizontal overflow on every screen at 360px — the brief's stated hard constraint. The design doc states
  this consequence plainly in §10.7 and correctly declines to invent a fix.
- **Required resolution:** the drawer behaviour must be promoted from blueprint to an approved pattern
  applied to all shell screens, and the drawer's nav set completed, before implementation.

### E-02 — Documented mobile adaptation rules are largely unimplemented

- **Classification:** RESPONSIVE_GAP (non-blocking; correctly documented)
- **Finding:** of the foundation screen's three adaptation rules, "tables → stacked cards" is implemented
  on **5 of 26** screens (Users, Stock Ledger, PO create/edit, Goods Receipt, SO create/edit); "sidebar →
  64px rail below `lg`" on **0**; "expandable rows on tablet" on **0**; "frozen/sticky columns" on **0**.
  Bottom-pinned action bars exist but as `sticky bottom-0` at *all* viewports, not mobile-only. Everywhere
  else, tables simply scroll horizontally.
- **Impact:** 21 screens have no mobile table treatment. The 44px tap-target rule (`UI-01` FR-15.4) is
  honoured only inside the 5 card implementations and the 360px specimen.
- **Required resolution:** none for the audit — §10.3 records all of this accurately. Carry it into the
  delivery plan as known work.

---

## Accessibility Audit

Measured attribute census across all 31 files:

| Signal | Product screens (30) | Specimen states screen |
|---|---|---|
| `<h1>` | 1 per screen — **30/30 ✅** | 1 |
| `aria-live` | **0** | 9 |
| `aria-busy` | **0** | 4 |
| `role="…"` | 4 total (reports-viz ×2, sales dashboard ×1, stock ledger ×1) | 15 |
| `aria-invalid` | **0** | present |
| `th scope="col"` | 8 screens (see M-02) | — |

### A-01 — Live-region behaviour is specified and shipped nowhere

- **Classification:** ACCESSIBILITY_GAP (blocking)
- **Source:** §14.2 registry ARIA contracts vs all product screens
- **Finding:** the registry assigns a precise ARIA contract to each of the 16 states —
  `role="status"`/`aria-live="polite"` for LOADING, NO_RESULTS, MUTATION_SUCCESS, STALE_DATA;
  `role="alert"`/`aria-live="assertive"` for SERVER_ERROR, NETWORK_ERROR, MUTATION_ERROR, BUSINESS_ERROR;
  `aria-invalid`/`aria-describedby` for VALIDATION_ERROR; `role="alertdialog"` for SESSION_EXPIRED;
  `aria-busy`/`aria-disabled` for ACTION_LOADING. **Not one** is applied on any product screen. Every
  state change — including validation failures, stock-shortfall business errors and mutation results — is
  silent to assistive technology.
- **Impact:** violates `UI-01` FR-15.3 / BR-021 and the `ux-ui-spec.md` §5 baseline. The design doc records
  the absence honestly (§18.3, §11.10), so this is a design defect, not a documentation one.
- **Required resolution:** the registry's ARIA column must be treated as binding on implementation.

### A-02 — No focus trap, and `:focus-visible` is never expressed

- **Classification:** ACCESSIBILITY_GAP (blocking)
- **Finding:** §11.9 confirms `Escape` closes modals on Categories and Suppliers, and native `<dialog>`
  gives it free on Warehouses / Goods Receipt / SO detail. But there is **no custom focus trap anywhere**,
  no focus-return-to-trigger, no arrow-key table navigation. The login lineage declares a `.focus-ring`
  class in its `<style>` block and **never applies it** (§18.3). Focus styling across the export is
  Tailwind ring utilities only, with no `focus-visible` distinction.
- **Impact:** violates `ux-ui-spec.md` §5 ("Focus trapped inside modal; Esc closes; focus returns to
  trigger element on close") and `UI-01` FR-15.3/FR-15.5.
- **Required resolution:** adopt the `ux-ui-spec.md` §5 modal contract as binding.

### A-03 — The CSV-export disabled reason is hover-only and unreachable

- **Classification:** ACCESSIBILITY_GAP (blocking — the clearest requirement contradiction in the audit)
- **Source:** `reports_…/code.html` `#export-tooltip`
- **Finding:** the disabled-export reason is written into
  `<div class="… hidden group-hover:block …" id="export-tooltip">` — revealed **only** on pointer hover.
  `updateExportButtonState()` sets `tooltip.innerText` but the element carries no `aria-describedby`, no
  `role`, no live region, and the button it describes is `disabled` and therefore cannot receive focus. A
  keyboard or screen-reader user is told the export button is unavailable and is given **no way at all**
  to learn why — across all six disabled reasons.
- **Impact:** `ux-ui-spec.md` §5 states the requirement in exactly these terms: "tooltip content also
  exposed via `aria-describedby` for screen readers, **not hover-only**". The design does the prohibited
  thing verbatim.
- **Required resolution:** bind the reason via `aria-describedby` on a focusable element, per §5.

### A-04 — Verified as consistent

- **Semantic headings:** every screen has exactly one `<h1>`; heading hierarchy is coherent. Minor: H1
  weight is `font-bold` on most screens but `font-semibold` on Products list, Sales dashboard and
  Categories (§18.4 #8).
- **Colour is not the only meaning carrier:** verified. Every status badge pairs colour with an uppercase
  text label and a pip/icon; §18.5 codifies this as a fidelity rule. The one partial exception is the
  reports-viz export pip, where `BLANK` and `DATA` share the label "Export CSV" and differ only by pip
  colour — but the button's `disabled` state provides a second, non-colour cue. **NO_ISSUE.**
- **Contrast:** the foundation screen prints measured ratios per swatch (Primary Base `#00236f` 7.8:1,
  Primary Container `#1e3a8a` 8.1:1, Action Hover `#2563eb` 4.6:1, Primary Fixed `#dce1ff` 14.2:1) and the
  states screen carries a `COMPLIANCE WCAG 2.1 AA` counter. These are consistent with
  `wcag-contrast-audit.md`. **NO_ISSUE** for the tokens actually measured — but note this covers the
  Stitch palette, not whichever palette R-01 resolves to.
- **Icon-only controls:** `aria-label` present on password toggles and breadcrumbs; not audited
  exhaustively per-control because A-01 already blocks.

---

## Token Audit

| Brief token | Value | Attested? | Verdict |
|---|---|---|---|
| Page background | `#F8FAFC` | login lineage `surface.page` ✅ · but `DESIGN.md` calls this **Subtle / table header**; its canvas is `#f1f5f9` | **TOKEN_GAP** — brief inverts page-bg and subtle |
| Surface | `#FFFFFF` | ✅ all sources | NO_ISSUE |
| Subtle | `#F1F5F9` | ✅ present, but as the **canvas** in `DESIGN.md` | **TOKEN_GAP** (inverted, as above) |
| Border | `#CBD5E1` | ✅ `neutral.border`, `DESIGN.md` control border | NO_ISSUE |
| Muted border | `#E2E8F0` | ✅ `surface.border`, `DESIGN.md` table divider | NO_ISSUE |
| Primary text | `#0F172A` | ✅ | NO_ISSUE |
| Secondary text | `#475569` | ✅ | NO_ISSUE |
| Muted text | `#64748B` | ✅ | NO_ISSUE |
| **Disabled text** | `#475569` | identical to secondary text; defined as "disabled" in no source | **TOKEN_GAP** — a disabled state indistinguishable from secondary text is not a token |
| Primary | `#2563EB` | present, but as `brand.hover` / "Action Hover" | **TOKEN_GAP** — role mismatch; actual primary is `#1e3a8a` (`DESIGN.md`) or `#00236f` (app config) |
| Primary hover | `#1D4ED8` | **0 occurrences in any source** | **TOKEN_GAP** (blocking) |
| Primary active | `#1E40AF` | 1 occurrence — as button **hover** in `DESIGN.md` | **TOKEN_GAP** — role mismatch |
| Success / bg | `#15803D` / `#F0FDF4` | 1 specimen badge only | **TOKEN_GAP** — `DESIGN.md` success is `#059669`/`#ecfdf5` |
| Warning / bg | `#A16207` / `#FEFCE8` | **0 occurrences in any source** | **TOKEN_GAP** (blocking) |
| Danger | `#B91C1C` | 1 occurrence — as destructive *border* | **TOKEN_GAP** — `DESIGN.md` danger is `#dc2626` |
| Danger bg | `#FEF2F2` | ✅ | NO_ISSUE |
| Info / bg | `#0369A1` / `#F0F9FF` | bg ✅; `#0369a1` is a *badge text* colour; `DESIGN.md` info is `#0284c7` | **TOKEN_GAP** (partial) |
| State title desktop | 24/32/600 | ✅ `headline-xl` | NO_ISSUE |
| State title mobile | 20/28/600 | ✅ `headline-lg` | NO_ISSUE |
| Body | 14/**22**/400 | 14/**20**/400 in the export **and** in `ux-ui-spec.md`; `22px` exists only as `text-[22px]` icon glyphs | **TOKEN_GAP** |
| Large body | 16/24/**400** | `headline-md` is 16/24/**600** | **TOKEN_GAP** (weight) |
| Button | 14/20/500 | `DESIGN.md` button text is **13px**; `label-md` is 13/18/500 | **TOKEN_GAP** |
| Control radius | 8px | app `rounded-xl` = 0.5rem ✅; but `DEFAULT` = 2px and `DESIGN.md` default = 4px | **TOKEN_GAP** |
| Card/dialog radius | 12px | app `rounded-full` = 0.75rem; `DESIGN.md` caps cards at 6–8px | **TOKEN_GAP** |
| Badge radius | 999px | app config redefines `full` to 12px; `DESIGN.md` **prohibits pill shapes** | **TOKEN_GAP** (direct contradiction) |
| Border | 1px | ✅ universal | NO_ISSUE |
| Control heights | 32/40/**48** | measured ladder is 28/32/36/40/44; `h-12`/`h-14`/`h-16` are header bars and avatars, never controls | **TOKEN_GAP** |
| State icon | 32px | ✅ Standalone Center Anatomy | NO_ISSUE |

**Net: 9 of 30 brief tokens verify cleanly.** Given R-01 and R-02, token compliance cannot be certified
until a single baseline is designated.

---

## Final Findings

### Blocking Findings

| ID | Class | Finding |
|---|---|---|
| **B-01** | TOKEN_GAP | R-01 + R-02 — five competing token vocabularies, and three of the brief's canonical values (`#1D4ED8`, `#A16207`, `#FEFCE8`) exist in no source. No baseline to audit against. |
| **B-02** | REQUIREMENT_GAP → **narrowed 2026-09-08** | R-03 — historical finding: the design omits the locale switcher (`I18N-01`) and theme switcher (`THEME-01`). **Superseded:** locale/theme switching is now out of scope by product decision; B-02 is renarrowed to shared shell/header consistency (see amendment above). |
| **B-03** | REQUIREMENT_GAP | R-04 — report data visualization has no requirement basis; `REPORT-01` is CSV export only. |
| **B-04** | DESIGN_GAP | R-05 — two incompatible Reports screens; unapproved report types; USD instead of IDR; a `Columns (9)` control for an explicitly out-of-scope capability. |
| **B-05** | RESPONSIVE_GAP | E-01 — `pl-64` unconditional on all 28 shell screens, zero responsive overrides ⇒ 104px of content at 360px. Violates `UI-01` FR-15.1/15.2/AC1. |
| **B-06** | DESIGN_GAP | M-01 — Goods Issue's `tailwind.config` omits `fontSize` and `spacing`; every typography token on that P1 screen is undefined. |
| **B-07** | STATE_GAP | S-01 + S-02 + S-03 — 51 undeclared local state keys against an alias-forbidding registry; EMPTY used where NO_RESULTS is defined on both Reports screens; three states shipped on Reports that §14.4 does not permit. |
| **B-08** | ACCESSIBILITY_GAP | A-01 + A-02 — zero `aria-live`/`aria-busy`/`aria-invalid` on all 30 product screens; no focus trap; `.focus-ring` declared and never applied. |
| **B-09** | ACCESSIBILITY_GAP | A-03 — the CSV-export disabled reason is hover-only, on a `disabled` (unfocusable) button, with no `aria-describedby` — the exact pattern `ux-ui-spec.md` §5 prohibits by name. |

### Non-Blocking Documentation Findings

| ID | Class | Fix |
|---|---|---|
| **F-01** | DOCUMENTATION_GAP | `docs/design/common-states.md` and `docs/design/reports.md`, named as audit sources, **do not exist**. Their content lives in `ioms-ui-design.md` §14 and §13. Either create them as extracts or correct the source list — do not author new content. |
| **F-02** | DOCUMENTATION_GAP | M-03 — §0 and §19 counts: 33 folders / 31 `code.html` / 31 PNG / 1 `DESIGN.md` / ~1.71 MiB across 31 files. |
| **F-03** | DOCUMENTATION_GAP | M-02 — replace the §11.10 `scope="col"` sentence with the measured eight-screen list; Customers has none. |
| **F-04** | REQUIREMENT_GAP | NETWORK_ERROR and STALE_DATA are canonical states with no PRD requirement behind them. Record the gap; do not delete the states and do not invent requirements. |
| **F-05** | DOCUMENTATION_GAP | M-04 — §10.3 "4 screens" → "5 screens". |
| **F-06** | DOCUMENTATION_GAP | M-05 — §11.10 cross-reference §13.2 → §14.2. |
| **F-07** | DOCUMENTATION_GAP | M-06 — add S-02 and S-03 to the §18.4 register; add M-01 as a twelfth entry. |
| **F-08** | DESIGN_GAP | R-06 — "My Profile" has no requirement; its email-conflict path implies unauthorised self-service mutation. |
| **F-09** | DESIGN_GAP | R-07 — state-registry copy asserts JWT, UUIDs, microservices, multi-tenancy, bins and inter-warehouse transfers, none of which the approved architecture has. Treat as placeholder copy. |
| **F-10** | REQUIREMENT_GAP | R-08 — no written record of whether `ux-ui-spec.md` (14 wireframes) or the Stitch export (26 screens) governs implementation. |
| **F-11** | ASSET_GAP | The logo exists as a 312-byte inline SVG in `ioms_enterprise_logo/code.html`, but **every screen uses a hosted `lh3.googleusercontent.com` raster instead**; avatars use three coexisting strategies; `data-alt` is used in place of real `alt`. `public/assets/img/` currently holds only `icons.svg`. Localise all assets and convert `data-alt` → `alt` (flagged in §18.2). |
| **F-12** | DOCUMENTATION_GAP | `.gitignore` at the monorepo root ignores `*.md` globally, so `docs/design/ioms-ui-design.md` — the design of record — **is not version-controlled**. Confirmed via `git check-ignore`. Add an exception before relying on it as an assessment artifact. |

### Verified Areas

Cross-checked against the files and found consistent — no finding:

- **Export inventory** — all 31 PNG dimensions match §0 exactly; `login` ⇄ `inventory_order_management_system_flow` is genuinely byte-identical (md5 `3ca51a95…`); exactly two `<title>` tags, both on the login pair.
- **The 16-state registry itself** — complete, ids `01_LOADING`…`16_ACTION_LOADING`, **no 17th state, no alias, no mode, no report-specific state**. The 5 anatomies and the screen→state map are reproduced verbatim and correctly in §14.
- **Visualization does not replace tabular data** — the reports-viz 9-column table is a sibling of the chart block inside `#state-data`, present in both DATA and STALE. The brief's central report constraint is satisfied.
- **CSV export gating rule** — both Reports screens independently enforce "export only from a current generated result", and `handleExportClick()` hard-guards it in code rather than relying on the disabled attribute alone.
- **STALE_DATA trigger semantics** — both screens correctly transition generated → stale on any criteria change, and both offer a re-generate affordance.
- **Desktop layout** — 256px sidebar / 56px header / `pt-14` / z-ladder 50-40-30-50 is consistent across all 28 shell screens; only default Tailwind breakpoints; no `2xl:`; no container queries; no `dark:` utilities (the two `dark:` string matches are the `brand.dark` config key, not variants).
- **Semantic headings** — exactly one `<h1>` on all 30 content screens.
- **Colour is never the sole meaning carrier** — every badge is dual-coded colour + uppercase label + pip/icon, codified in §18.5.
- **Contrast** — the printed ratios on the foundation screen are consistent with `wcag-contrast-audit.md`.
- **Documentation fidelity overall** — every §13.7 value (state keys, 700 ms timer, pip colours, export labels, early-return guard), the §10.3 responsive claims, the §10.7 360px consequence and the §18.3/§18.4 registers were verified against source and found accurate. `ioms-ui-design.md` is a trustworthy description of the export; its defects are the six listed above, all narrow.

---

## Final Gate

| Gate condition | Status |
|---|---|
| No blocking design gaps | ❌ B-04, B-06 |
| No requirement/design contradictions | ❌ B-03, B-05, B-09 (B-02 narrowed 2026-09-08 — no longer a contradiction, see amendment) |
| 16 canonical states consistent | ❌ B-07 (registry ✅, screens ❌) |
| Report visualization behaviour documented | ✅ documented (§13) — ❌ but unauthorised (B-03) |
| CSV export rules consistent | ⚠️ rules agree; presentation and accessibility do not (B-09) |
| Desktop behaviour consistent | ✅ |
| 360px behaviour consistent | ❌ B-05 |
| Accessibility requirements represented | ❌ B-08, B-09 |
| Design tokens consistent | ❌ B-01 |
| Markdown reflects the approved design | ⚠️ accurate except M-01…M-06 |

**Gate: CLOSED.** Re-audit after B-01 … B-09 are resolved.

# IOMS Design Decision Record — B-01 … B-09

**Status:** DRAFT — awaiting Project Owner approval (see §Approval below)
**Date:** 2026-09-08
**Author:** Claude (Cowork), on explicit instruction: resolve the Stitch design-audit blocking findings
**Input:** `docs/design/design-audit.md` (Gate: CLOSED, B-01…B-09), `docs/planning/master-project-specification.md`
§16–17 (UI-GAP-01…13, CF-01…10, ADR-005/006 placeholders), `docs/design/ioms-ui-design.md`
**Scope:** This record makes no code change and does not reopen the audit gate. It exists to be
approved, after which the amendments it specifies are carried out, the Stitch design is patched
(`STITCH_PATCH_SCOPE_B01_B09.md`), and a re-audit is run against `RE_AUDIT_ACCEPTANCE_CRITERIA.md`.

## Source-of-truth ranking applied throughout

1. Explicitly approved requirement/specification — `docs/planning/prd.md`, `phase1-baseline.md`,
   `ux-ui-spec.md` (self-declared single source of truth for tokens/components/accessibility).
2. Currently implemented canonical application behavior — `public/assets/css/tokens.css`/`main.css`,
   `views/layouts/main.php`, `app/**`.
3. Existing design documentation — `docs/design/ioms-ui-design.md`, `docs/design/design-audit.md`,
   `docs/planning/master-project-specification.md` §16–17/§46 (this is the most advanced prior
   synthesis and already carries most of this record's evidence trail under IDs UI-GAP-01…13 /
   CF-01…10 — this record adopts and formalizes those findings rather than re-deriving them,
   and renumbers them to the audit's B-01…B-09 for traceability).
4. The Stitch-generated export (`docs/design/ioms-ui-design.md` description of
   `stitch_remix_of_remix_of_enterprise_ioms_design_system_fix`) — treated as a **visual reference
   only**, never as an implementation source (see B-01/CF-01 below — this is itself a decision, not
   an assumption).
5. Audit-brief-only assumptions — used only where nothing above resolves a value; every such use is
   flagged explicitly.

Where two sources at the same or adjacent rank disagree, the disagreement is recorded, not silently
resolved (see B-09's exact-quote correspondence).

---

## B-01 — Canonical design tokens

- **Problem.** Five mutually incompatible token vocabularies each claim canonical status: (1)
  `ux-ui-spec.md` §1.1 / `tokens.css`, (2) Stitch app-lineage Tailwind config (Material-3 names), (3)
  `DESIGN.md` prose (slate/navy, pill shapes prohibited), (4) Stitch login-lineage Tailwind config
  (`brand`/`surface`/`neutral`/`semantic`), (5) the audit brief's own token list. Three of the brief's
  canonical values — primary-hover `#1D4ED8`, warning `#A16207`, warning-bg `#FEFCE8` — occur in
  **zero** source files.
- **Evidence.** `docs/design/design-audit.md` R-01/R-02 and Token Audit table; independently
  reconfirmed in this pass by reading `ux-ui-spec.md` §1.1 (lines 1–80) and `public/assets/css/tokens.css`
  (lines 1–40) side by side: every value in `tokens.css` (`--color-bg-primary:#FFFFFF`,
  `--color-brand-primary:#2563EB`, `--color-status-error:#DC2626`, `--color-status-success:#15803D`,
  `--color-status-warning:#B45309`, `--color-status-info:#0E7490`) matches `ux-ui-spec.md` §1.1 exactly,
  in both light and dark rows, including the WCAG-AA contrast table (all pairs ≥4.5:1 / ≥3:1).
- **Canonical source.** `docs/planning/ux-ui-spec.md` §1 ("this document is the single source of
  truth") — rank 1 — already carried into rank-2 implemented behavior.
- **Final decision.** `ux-ui-spec.md` §1 (Color §1.1, Typography §1.2, Spacing §1.3, Radius/elevation
  §1.4) is the **one canonical token vocabulary**, in both light and dark theme. It is already fully
  implemented in `tokens.css`; no code change is required by this decision.
- **Rationale.** It is the only candidate that is simultaneously (a) explicitly approved
  (§0 self-declaration, never contradicted elsewhere), (b) contrast-verified against WCAG AA with
  numbers recomputed independently (see `refactor-log.md` #8, which found and fixed a real dark-theme
  contrast bug against these exact tokens), (c) dark-theme-complete, and (d) already the live
  implementation — adopting anything else would mean discarding working, verified code to chase a
  visual export that itself does not agree with its own three internal vocabularies.
- **Deprecated / non-canonical:** Stitch app-lineage Material-3 tokens (`primary:#00236f`,
  `surface:#f8f9ff`, `error:#ba1a1a`, `rounded-full:0.75rem`); `DESIGN.md` prose palette
  (`#1e3a8a`/`#059669`/`#d97706`/`#dc2626`/`#0284c7`); Stitch login-lineage `brand`/`surface`/`neutral`/
  `semantic` config; the audit brief's token list in full, including the three values attested
  nowhere. None of these may be reintroduced into implementation or into a future Stitch regeneration
  as competing "canonical" values — they may appear only as historical reference in
  `docs/design/design-audit.md`.
- **Required design/spec amendment.** None to `ux-ui-spec.md` itself. Add one line to
  `docs/design/ioms-ui-design.md` §0 (or a new §0.1) stating explicitly: "This document describes the
  Stitch export as a visual/layout reference. Its token values are non-canonical; canonical tokens are
  `ux-ui-spec.md` §1." This closes F-10 (no written record of which document governs) as it applies to
  tokens specifically.
- **Required Stitch amendment.** See `STITCH_PATCH_SCOPE_B01_B09.md` §1 — all 31 screens' color/
  typography/radius values must be regenerated against `ux-ui-spec.md` §1, replacing all three
  Stitch-internal vocabularies.
- **Required implementation impact.** None — `tokens.css`/`main.css` already conform. No PHP, view, or
  CSS file changes required by B-01 alone.
- **Re-audit acceptance criteria.** A grep across all Stitch-regenerated `code.html` for any hex value
  not present in `ux-ui-spec.md` §1.1/§1.4 returns zero matches, other than pure grayscale/transparent
  utility values with no semantic role.

---

## B-02 — Shared authenticated shell/header consistency (amended 2026-09-08 — locale/theme switching removed from scope)

*Amended 2026-09-08. This section originally required a mandatory locale toggle and theme toggle.
Per an explicit product-scope decision, EN/ID locale switching and Auto/Light/Dark theme switching
are no longer mandatory product requirements for the current 4-week IOMS release — this must not
become an implementation or audit blocker. The original problem/evidence is retained below for
audit-trail purposes; Final decision onward reflects the corrected, narrower scope.*

- **Original problem (historical).** No Stitch screen contains a locale switcher or theme switcher,
  although `ux-ui-spec.md` §3 (pre-amendment) defined `[Header]` as "logo, locale toggle, theme
  toggle, user menu" and both `I18N-01` and `THEME-01` were listed in the PRD.
- **Evidence.** `ux-ui-spec.md` §3 (now amended — see below); `docs/design/design-audit.md` R-03 (now
  marked superseded); the running app currently still renders a locale toggle and theme toggle in
  `views/layouts/main.php` (`toggleLocale()`, `toggleTheme()`), backed by `theme.js`/`i18n-init.js` —
  this existing implementation is **not required to be removed** by this amendment (no production
  code change is authorized here); it simply is no longer a **mandatory** gate/audit requirement
  going forward.
- **Canonical source.** Product-scope decision (this amendment), superseding the prior
  `ux-ui-spec.md` §3 / PRD `I18N-01`/`THEME-01` reading.
- **Final decision.** Locale switching (EN/ID) and theme switching (Auto/Light/Dark) are **out of
  scope** for the current IOMS release, unless a future, separately-approved product requirement
  reinstates them. B-02 is renarrowed to validate only: (a) the shared authenticated shell/header
  remains consistent across all authenticated screens; (b) navigation/header structure is
  consistent; (c) no arbitrary per-screen shell variants are introduced; (d) the existing shared
  shell is reused rather than duplicated per screen. No new control, button, screen, or setting is
  introduced by this decision.
- **Rationale.** Locale/theme switching was unnecessary product scope for the current 4-week
  implementation target and must not become an implementation or audit blocker. This is a scope
  correction, not a new design finding — nothing about the Stitch export or the running app changed;
  only what the gate requires changed.
- **Required design/spec amendment.** `ux-ui-spec.md` §3 header shorthand, the Login wireframe note
  (§3.1), and the Accessibility Checklist general rules (§5) are amended to remove the
  locale-toggle/theme-toggle requirement language; a changelog entry records the change (`ux-ui-spec.md`
  §6, version 1.3).
- **Required Stitch amendment.** None. Do not add a locale toggle or theme toggle to any screen
  (`STITCH_PATCH_SCOPE_B01_B09.md` §2 updated accordingly — it now asks only for shell/header
  consistency, not new controls).
- **Required implementation impact.** None. No production code is added, removed, or otherwise
  required to change by this decision.
- **Open dependency — no longer applicable.** `docs/planning/master-project-specification.md` §46
  CF-02 (i18next library compliance) was previously tracked as a dependency of this finding. Since
  locale switching is no longer a mandatory requirement, CF-02 is no longer a dependency *of B-02* —
  whether the existing i18next-based implementation is kept, replaced, or removed is a separate
  implementation/technology decision outside this design-audit's scope, to be made independently by
  the project owner.
- **Re-audit acceptance criteria.** Every authenticated screen shares one consistent shell/header
  structure (same nav placement, same header composition) with no per-screen shell variant; no
  re-audit criterion requires the presence of a locale control or a theme control.

---

## B-03 + B-04 — Reports: scope and duplicate screens

- **Problem.** (a) A Reports-with-visualization screen exists with no requirement basis, since
  `REPORT-01` specifies CSV export only. (b) Two Reports screens exist that are not variants of one
  another — different report types, different state models, and the visualization screen uses **USD**
  while the rest of the app is IDR, plus a `Columns (9)` custom-column-selection control that is out
  of scope.
- **Evidence.** `docs/planning/prd.md` line 705 "### REPORT-01 — CSV Export" (no chart/graph/
  visualization requirement anywhere in `prd.md`, confirmed by grep); `docs/planning/phase1-baseline.md`
  line 287, **DEC-013**: *"The tabular Reports surface (Stock Movement + Order Status, IDR) is the
  canonical report UI for REPORT-01; the chart-bearing Reports variant is not adopted in Phase 1...
  charts are bonus per SRC-001 §4.4."* This decision already exists, at rank 1, and directly resolves
  both B-03 and B-04. `master-project-specification.md` UI-GAP-05/06 and CF-07/CF-08 (lines 1793–1794,
  3519–3521) independently reach the same conclusion.
- **Canonical source.** `phase1-baseline.md` DEC-013 (rank 1) — this **is already an approved
  decision**, not a new one; this record formalizes it into the design-audit closure chain.
- **Final decision.** The **tabular** Reports screen (`reports_inventory_order_management`) is the
  one canonical Reports experience for `REPORT-01`. The visualization screen
  (`reports_with_data_visualization_inventory_order_management`) is **not adopted** as a required
  screen. A chart may be added later **only** as an explicitly-labeled bonus (per brief §4.4,
  self-built SVG/canvas, never a charting library, never replacing the tabular data, never gating
  CSV export) — it is out of scope for this audit closure. All currency throughout Reports is **IDR**,
  consistent with `DEC-013` and the other 24 screens. The `Columns (9)` custom-column-selection
  control is removed — it is explicitly out of `REPORT-01` scope.
- **Rationale.** A prior, already-approved Phase 1 decision (DEC-013) settles this; inventing a new
  resolution would contradict an approved artifact, which the source-of-truth rule forbids.
- **Required design/spec amendment.** None — `DEC-013` already states this. Add a one-line
  cross-reference in `docs/design/ioms-ui-design.md` §1.3 pointing to `DEC-013` so a future reader
  does not re-open this question from the Stitch description alone.
- **Required Stitch amendment.** Retire `reports_with_data_visualization_inventory_order_management`
  from the canonical screen set (keep it archived as a bonus-chart reference only, clearly labeled
  non-canonical); regenerate the tabular Reports screen's currency formatting to IDR; remove the
  `Columns (9)` control.
- **Required implementation impact.** None — the currently implemented `ReportController`/CSV export
  is already tabular-only and IDR (per `DEC-013`/DR-4). No change required.
- **Re-audit acceptance criteria.** Exactly one canonical Reports screen in the regenerated Stitch set;
  zero USD currency strings; zero `Columns (9)`-style custom column pickers.

---

## B-05 — Responsive shell (360px)

- **Problem.** All 28 shell-bearing screens hard-code a 256px sidebar offset (`pl-64`) with zero
  responsive overrides at any breakpoint. At 360px viewport width the content column is 104px wide,
  violating the hard requirement that Login/Dashboard/List/Detail/Form work at 360px with no body
  overflow.
- **Evidence.** `docs/design/design-audit.md` E-01 (measured: `pl-64` on 28/28 screens, zero responsive
  override matches); `docs/planning/prd.md` lines 851–855, **FR-15.1** ("Halaman utama... berfungsi
  pada 360px... sampai desktop >=1440px"), **FR-15.2** ("tidak terpotong... body tidak overflow"),
  **FR-15.4** (44×44px touch targets), **FR-15.5** (keyboard nav). No breakpoint/tablet/mobile/drawer
  section exists in `ux-ui-spec.md` itself (confirmed absent by grep) — the *behavior* is required by
  the PRD, but its *pattern* is not yet specified anywhere at rank 1 or rank 2, so this decision
  establishes the pattern rather than merely selecting among existing ones.
- **Canonical source.** `prd.md` FR-15.1/.2/.4/.5 (rank 1, behavior only) + the Stitch shell blueprint's
  drawer concept (`authenticated_application_shell`, rank 4, pattern candidate only — its own nav set
  is incomplete and must be corrected, not merely adopted).
- **Final decision.** One canonical responsive shell pattern, three breakpoints:
  - **Desktop (≥1024px):** persistent sidebar, 256px, exactly as built today.
  - **Tablet (768–1023px):** collapsible sidebar rail, 64px collapsed width, icon-only, expandable on
    hover/click, content area recovers the freed width. (This tier does not exist in any current
    source; it is specified here because FR-15.1 spans "360px sampai desktop" and a direct 256px→drawer
    jump at 1023px would itself be an undocumented cliff.)
  - **Mobile (<768px, hard floor 360px):** the sidebar becomes a drawer/overlay, hidden by default,
    opened by a header hamburger, closing on: selecting a nav item, pressing Escape, or tapping the
    scrim. The drawer **must carry the complete canonical navigation set** — Dashboard, Products,
    Categories, Warehouses, Suppliers, Customers, Purchase Orders, Sales Orders, Reports, Users
    (Admin only) — identical to the desktop sidebar's item list. No reduced/partial nav variant is
    permitted (this directly overrules the blueprint's reduced drawer, which drops Warehouses,
    Suppliers, Customers, Reports, Users).
  - Content never overflows horizontally at 360px; tables that do not fit switch to the existing
    stacked-card pattern (already implemented on 5 screens per `design-audit.md` E-02) rather than
    horizontal scroll, for the specific case of the primary list/detail screens named in FR-15.1
    (Login, Dashboard, List, Detail, Form). Secondary/incidental tables may still scroll horizontally.
- **Rationale.** FR-15.1/.2 are hard, approved, P0 requirements with zero tolerance for the measured
  104px failure; the blueprint's drawer is the only existing candidate pattern and is adopted in
  principle, but its incomplete nav set is a defect that must not be carried forward, per the
  requirement itself ("Navigasi... tidak terpotong").
- **Required design/spec amendment.** Add a new §3.x "Responsive Shell" section to `ux-ui-spec.md`
  formalizing the three breakpoints and the full-nav-drawer rule above (this is new content the spec
  currently lacks, and per the source-of-truth rule this gap is recorded rather than silently patched
  only in Stitch).
- **Required Stitch amendment.** Apply the corrected, full-nav drawer pattern to all 28 shell screens
  (not just the blueprint); add the tablet collapsed-rail tier, currently absent everywhere.
- **Required implementation impact.** `public/assets/css/main.css` needs the three-tier breakpoint CSS
  and drawer/rail markup added to `views/layouts/main.php`; `public/assets/js/theme.js` or a new small
  script needs the drawer open/close/Escape/scrim behavior. This is real implementation work, tracked
  as a follow-up task, not performed by this decision record.
- **Re-audit acceptance criteria.** Screenshots at 360px, 768px, and 1440px for Login, one Dashboard,
  one List, one Detail, and one Form show zero horizontal body overflow; the mobile drawer, when
  opened, lists all 9–10 nav items (role-appropriate); Lighthouse/axe reports zero touch-target
  violations at 360px.

---

## B-06 — Goods Issue typography/spacing

- **Problem.** The Goods Issue screen's Tailwind config omits `fontSize` and `spacing` entirely, so
  every typography token on this P0 screen renders at browser default rather than the intended scale.
- **Evidence.** `docs/design/design-audit.md` M-01 (referenced in B-06 finding); confirmed no
  page-specific typography exists anywhere else in the export for other screens (all other 25 screens
  do define `fontSize`/`spacing`).
- **Canonical source.** `ux-ui-spec.md` §1.2 (Typography scale) and §1.3 (Spacing scale) — the same
  global tokens as every other screen.
- **Final decision.** Goods Issue inherits the **same global token system** as every other screen — no
  page-specific typography or spacing token is created. Specifically: Display/H1/H2/H3/Body/Body
  Small/Caption/Label per §1.2, and `space-1`…`space-12` per §1.3.
- **Rationale.** No requirement or prior decision justifies a page-specific scale; the omission is a
  generation defect in one file, not evidence of an intentional design variant.
- **Required design/spec amendment.** None.
- **Required Stitch amendment.** Regenerate Goods Issue's `tailwind.config` to include the same
  `fontSize`/`spacing` block used by the other 25 product screens, mapped to §1.2/§1.3.
- **Required implementation impact.** None — the implemented Goods Issue view already inherits
  `tokens.css`/`main.css` globally (PHP views do not carry per-page Tailwind configs at all); this
  finding is Stitch-artifact-only and has no code footprint.
- **Re-audit acceptance criteria.** Regenerated Goods Issue `code.html` prints the same measured
  type ladder (32/28/22/18/14/13/12/12) as any other screen; no `text-[Npx]` arbitrary values remain
  where a scale step exists.

---

## B-07 — State registry alignment

- **Problem.** The 16-state canonical registry (`docs/design/ioms-ui-design.md` §14, ids
  `01_LOADING`…`16_ACTION_LOADING`) explicitly forbids aliases, but the Stitch screens carry 51
  undeclared local state keys, and the two Reports screens key their zero-filter-result view as
  `EMPTY` when the registry defines that exact case as `NO_RESULTS`.
- **Evidence.** `docs/design/design-audit.md` S-01/S-02/S-03, B-07; `ioms-ui-design.md` lines 2277–2292
  (state definitions) — `03_EMPTY` = *"Dataset has exactly 0 total records in tenant domain"*;
  `04_NO_RESULTS` = *"Active search filters return 0 records from populated dataset"*; line 2345/2354
  confirms Reports' actual copy ("No purchase orders or stock transfers match query... within selected
  warehouse scope") describes a **filtered** result, i.e. the `NO_RESULTS` case, not `EMPTY`.
  `prd.md` FIND-01 requires "Search 0 hasil → empty state 'Tidak ada hasil'" as a *distinct* case from
  a genuinely empty dataset. `master-project-specification.md` UI-GAP-04/CF-10 already reaches the same
  conclusion (lines 1782–1786, 3522).
- **Canonical source.** The 16-state registry itself (rank 3, but explicitly adopted at rank 1 by
  `VIEW-01`/`FIND-01`'s EMPTY/NO_RESULTS distinction in the PRD) is the **authoritative state
  vocabulary**. No new state name may be introduced.
- **Final decision.** (a) Reclassify both Reports screens' filter-zero view from `EMPTY` to
  `NO_RESULTS` — no new state is created, an existing one is correctly applied. (b) Every one of the
  51 ad-hoc local state keys found across the export must be mapped onto one of the 16 canonical
  states before implementation; **no new state name, alias, or mode may be introduced** to
  accommodate any of the 51. This record does not itself enumerate the full 51→16 mapping table — that
  is a mechanical cross-reference exercise against the regenerated Stitch export (each local key's
  triggering condition determines its canonical bucket per the registry's own "Trigger" column,
  `ioms-ui-design.md` lines 2277–2292) and is listed as a required follow-up artifact, not invented
  here without the source material in front of the mapper.
- **Rationale.** The registry is explicit that aliasing is forbidden; the Reports EMPTY/NO_RESULTS
  conflation is a straightforward misclassification against the registry's own trigger definitions,
  correctable without any new concept.
- **Required design/spec amendment.** None to the registry. Add the produced 51→16 mapping table as a
  new appendix to `ioms-ui-design.md` once produced.
- **Required Stitch amendment.** Regenerate both Reports screens' zero-filter-result state key from
  `EMPTY`/`empty` to `NO_RESULTS`; regenerate every other screen's local state keys to the mapped
  canonical name (pending the mapping table).
- **Required implementation impact.** Whatever local state naming the PHP/JS implementation uses for
  Reports' filter-zero case must also read `NO_RESULTS`, not `EMPTY`, for consistency with the design
  and with FIND-01's own required copy.
- **Re-audit acceptance criteria.** A grep of all regenerated `code.html` state keys against the
  registry's 16 ids finds zero non-canonical keys; the mapping table appendix exists and accounts for
  all 51 original keys.

---

## B-08 — Accessibility: live regions and focus trap

- **Problem.** The registry defines a precise ARIA contract per state, but zero product screens apply
  any of it: no `aria-live`, no `aria-busy`, no `aria-invalid` anywhere across 30 screens. No custom
  focus trap exists in any modal; the login lineage declares a `.focus-ring` class and never applies
  it; no `:focus-visible` styling is expressed anywhere.
- **Evidence.** `docs/design/design-audit.md` A-01/A-02; `ioms-ui-design.md` lines 2303–2318 gives the
  exact contract per state (`LOADING` → `aria-busy="true"`/`aria-live="polite"`/`role="status"`;
  `NO_RESULTS`/`EMPTY` → `role="status"`/`aria-live="polite"`; by extension from the same table,
  server/network/business errors → `role="alert"`/`aria-live="assertive"`; `ACTION_LOADING` →
  `aria-busy="true"`/`aria-disabled="true"`). `ux-ui-spec.md` line 321: *"Focus trapped inside modal;
  Esc closes; focus returns to trigger element on close."*
- **Canonical source.** `ux-ui-spec.md` §5 (rank 1, accessibility checklist) + the registry's own ARIA
  column (rank 3, but it is describing an already-approved accessibility requirement, `UI-01` AC2 /
  `BR-021`, not inventing one).
- **Final decision.** The following contract is binding on every product screen and every future
  Stitch regeneration:
  - `LOADING`, `NO_RESULTS`, `MUTATION_SUCCESS`, `STALE_DATA` → `role="status"` `aria-live="polite"`.
  - `SERVER_ERROR`, `NETWORK_ERROR`, `MUTATION_ERROR`, `BUSINESS_ERROR` → `role="alert"`
    `aria-live="assertive"`.
  - `VALIDATION_ERROR` → `aria-invalid="true"` on the offending field plus `aria-describedby` pointing
    to its error message element.
  - `SESSION_EXPIRED` → `role="alertdialog"`.
  - `ACTION_LOADING` → `aria-busy="true"` `aria-disabled="true"` on the triggering control.
  - Every modal/dialog: focus moves to the first focusable element on open; Tab/Shift-Tab is trapped
    inside the dialog; Escape closes it; focus returns to the element that opened it.
  - `:focus-visible` (not just `:focus`) must carry a visible ring on every interactive element,
    consistently — reusing the existing but unapplied `.focus-ring` class as the base, extended to
    every control, not only the login lineage.
- **Rationale.** This is not a new requirement — `ux-ui-spec.md` §5 and `BR-021` already mandate it;
  the design simply never implemented what it was told to. Restating it here as binding closes the
  gap between "specified" and "applied."
- **Required design/spec amendment.** None — `ux-ui-spec.md` §5 already states the modal contract; the
  per-state ARIA table should be copied from `ioms-ui-design.md` into `ux-ui-spec.md` §5 as well, so it
  is not only reachable via the Stitch description document.
- **Required Stitch amendment.** Every regenerated screen must carry the ARIA attributes above on its
  state containers; every modal must be regenerated with real focus-trap markup/behavior notes (Stitch
  output is static HTML, so this is a behavioral note attached to the screen, implemented in code).
- **Required implementation impact.** Real work: add the ARIA attributes and a focus-trap utility to
  the PHP views / `main.css` / a small new JS module. Tracked as a follow-up implementation task, not
  performed here.
- **Re-audit acceptance criteria.** axe-core (or equivalent) run against each of the 30 screens reports
  zero `aria-*` violations for the states listed; a manual Tab-cycle test on every modal confirms trap
  + return-to-trigger.

---

## B-09 — Disabled CSV export explanation

- **Problem.** The reason a CSV export button is disabled is written into a `hidden group-hover:block`
  tooltip element, revealed only on pointer hover; the button itself is `disabled` and therefore
  cannot receive keyboard focus — so a keyboard or screen-reader user is told the button is disabled
  and given no way at all to learn why.
- **Evidence.** `docs/design/design-audit.md` A-03, quoting `ux-ui-spec.md` line 326 verbatim:
  *"disabled buttons excluded from Tab order per native `disabled`; tooltip content also exposed via
  `aria-describedby` for screen readers, not hover-only."* The Stitch export does the exact opposite of
  what its own governing spec requires, in the same words.
- **Canonical source.** `ux-ui-spec.md` line 326 (rank 1) — this is the clearest case in the whole
  audit: the canonical source already states the required pattern in the same terminology used to
  describe the defect.
- **Final decision.** The canonical accessible-disabled-reason pattern, binding wherever a control is
  disabled for a reason the user needs to know (CSV export and any future case): the explanatory text
  lives in a normal (non-hidden) DOM element with a stable `id`; the control carries
  `aria-describedby="that-id"` at all times, regardless of hover state; if the control must remain a
  native `disabled` button (excluded from Tab order, as `ux-ui-spec.md` line 326 also requires), the
  description is still programmatically associated via `aria-describedby` so it is announced when the
  screen reader's virtual cursor reaches it or when the user inspects the control by other means; the
  visible/hover tooltip may remain as a *supplementary* sighted-mouse-user convenience but must never
  be the sole channel.
- **Rationale.** This is the single most literal contradiction in the whole audit — the design does
  the named-and-prohibited thing. No interpretation is required, only enforcement of what
  `ux-ui-spec.md` already says.
- **Required design/spec amendment.** None.
- **Required Stitch amendment.** Regenerate the CSV export control (and any other disabled-with-reason
  control found during the 51-state mapping in B-07) to remove `hidden group-hover:block` as the sole
  delivery mechanism and add a persistent, `aria-describedby`-linked description element.
- **Required implementation impact.** `ReportController`/its view must render the disabled-reason text
  in a normal element with a stable id and wire `aria-describedby` on the export button; the six
  documented disabled reasons must each have such an element.
- **Re-audit acceptance criteria.** The export button, in every disabled state, exposes its reason via
  the accessibility tree (verified with a screen reader or axe's accessible-description check) with no
  dependency on `:hover`/`:focus` CSS state.

---

## Amendment Log

| Date | Change | Reason |
|---|---|---|
| 2026-09-08 | B-02 narrowed from "mandatory locale + theme toggle" to "shared authenticated shell/header consistency"; locale switching (EN/ID) and theme switching (Auto/Light/Dark) declared out of scope for the current release. | Product-scope correction — unnecessary scope for the current 4-week implementation target; must not block implementation or audit. Applied consistently to `docs/design/design-audit.md`, `docs/design/STITCH_PATCH_SCOPE_B01_B09.md`, `docs/design/RE_AUDIT_ACCEPTANCE_CRITERIA.md`, and `docs/planning/ux-ui-spec.md`. |

All other findings (B-01, B-03…B-09) are unchanged by this amendment.

---

## Approval

| Role | Decision | Date |
|---|---|---|
| Project Owner | ☐ Approved ☐ Approved with changes ☐ Rejected | — |

Until signed off here, the gate remains **CLOSED**. Approval of this record authorizes: (1) the
listed spec amendments to `ux-ui-spec.md` / `ioms-ui-design.md`, (2) issuing
`STITCH_PATCH_SCOPE_B01_B09.md` as the regeneration brief, (3) re-audit against
`RE_AUDIT_ACCEPTANCE_CRITERIA.md`. It does **not** itself authorize any change to `app/`, `views/`,
`public/assets/`, or any test file — those changes, where listed above as "Required implementation
impact", are separate follow-up work items.

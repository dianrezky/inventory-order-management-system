# Re-Audit Acceptance Criteria — Closing Gate B-01…B-09

**Status:** FINAL
**Date:** 2026-09-08
**Purpose:** Define the mandatory acceptance criteria for the fresh, independent design re-audit required to close the B-01…B-09 gate.

This document is an acceptance checklist, not a substitute for the independent audit method. The re-audit must independently re-derive its findings from the current artifacts and must not mark an item PASS merely because a patch, decision record, or previous document claims compliance.

---

## Gate Rule

The gate remains **CLOSED** until all of the following occur, in order:

1. `DESIGN_DECISION_RECORD_B01_B09.md` is approved by the Project Owner.
2. All required amendments to `ux-ui-spec.md` and `ioms-ui-design.md` are actually made.
3. The Stitch design is regenerated/patched according to `STITCH_PATCH_SCOPE_B01_B09.md`.
4. A fresh independent re-audit is executed against the current artifacts.
5. Every B-01…B-09 criterion below passes.
6. The independent regression sweep finds no unresolved blocking finding.
7. The re-audit result is **PASS — eligible for Gate OPEN**.
8. Only the Project Owner may change the recorded gate status in `docs/design/design-audit.md` from CLOSED to OPEN.

No individual engineer, auditor, author, or automation run may declare the gate OPEN outside this sequence.

---

# B-01 — Canonical Design Tokens

* [ ] Every regenerated `code.html` and `DESIGN.md` uses only semantic color values defined by `ux-ui-spec.md` §1.1, including the approved light and dark values, except pure grayscale/transparent utility values with no semantic role.
* [ ] No deprecated Stitch Material-3 palette, Stitch `DESIGN.md` slate/navy palette, login-lineage `brand`/`surface`/`neutral`/`semantic` vocabulary, or audit-brief-only token vocabulary is introduced as a competing canonical token source.
* [ ] Typography sizes, weights, and line-heights match `ux-ui-spec.md` §1.2 exactly: Display 32/40/700, H1 28/36/700, H2 22/30/600, H3 18/26/600, Body 14/20/400, Body Small 13/18/400, Caption 12/16/400, Label 12/16/600.
* [ ] No arbitrary `text-[Npx]` value substitutes for an existing defined typography scale step.
* [ ] Radius values are limited to 4px, 8px, and 12px according to the defined semantic role.
* [ ] No `rounded-full` or equivalent pill/999px shape is used.
* [ ] Spacing uses the approved 4/8-based scale from `ux-ui-spec.md` §1.3.
* [ ] `public/assets/css/tokens.css` still matches `ux-ui-spec.md` §1 after any incidental implementation changes.
* [ ] Token compliance is audited against the amended/current `ux-ui-spec.md`, not against the old audit-brief token list.

---

# B-02 — Shared Authenticated Shell/Header Consistency (amended 2026-09-08)

*Locale switching (EN/ID) and theme switching (Auto/Light/Dark) were removed from B-02's mandatory
scope by product-scope decision on 2026-09-08 — see `docs/design/DESIGN_DECISION_RECORD_B01_B09.md`
§B-02 (amended). They are OUT OF SCOPE for the current IOMS release unless a future,
separately-approved product requirement reinstates them. This is a scope correction, not a new
feature: no new button, control, screen, or setting is required, and none of the criteria below asks
for one.*

* [ ] Every authenticated screen uses the same shared application shell (header + navigation structure) — no arbitrary per-screen shell variant.
* [ ] Header/navigation composition (logo, nav, user menu/logout placement) is consistent across all authenticated screens.
* [ ] No screen introduces a duplicated or one-off copy of the shell instead of reusing the shared one.
* [ ] No re-audit criterion in this section requires the presence of a locale control or a theme control.
* [ ] If a locale or theme control still exists in the running app (pre-existing implementation), its presence is not treated as a failure and its absence is not treated as a failure — B-02 does not gate on it either way.
* [ ] `CF-02` (i18next/library compliance) is recorded as no longer a dependency of B-02, since locale switching is no longer a mandatory requirement; any decision on the existing i18next implementation is tracked separately, outside this checklist.

---

# B-03 + B-04 — Reports Scope and Canonical Reports Design

* [ ] Exactly one Reports experience is identified as the canonical Reports design for `REPORT-01`.
* [ ] The visualization Reports variant is either absent from the canonical set or explicitly retained only as a non-canonical/bonus reference.
* [ ] The non-canonical visualization variant is never presented as equal to the canonical Reports experience.
* [ ] The canonical Reports screen contains only the approved report types defined by `REPORT-01`/`DEC-013`.
* [ ] No unapproved report catalogue is introduced.
* [ ] No `$` or USD-formatted monetary values remain in the canonical Reports design.
* [ ] Monetary values in the canonical Reports design use IDR (`Rp`), thousands separators, and no decimal places.
* [ ] No `Columns (9)` or equivalent custom column-selection control remains.
* [ ] The canonical Reports screen retains the approved CSV-generation/export behavior.

---

# B-05 — Responsive Shell

## Desktop

* [ ] At viewport width ≥1024px, the sidebar remains persistent at 256px and preserves the approved desktop behavior.

## Tablet

* [ ] At 768–1023px, the sidebar collapses to a 64px icon-only rail.
* [ ] The 64px rail can be expanded by the approved hover/click interaction.
* [ ] Content width recovers the space previously occupied by the 256px sidebar.

## Mobile

* [ ] At viewport width <768px and down to the hard floor of 360px, the sidebar becomes a hidden slide-in drawer.
* [ ] The drawer is opened through a header hamburger control.
* [ ] The drawer has a scrim behind it.
* [ ] At 360px, the drawer contains the complete role-appropriate navigation set.
* [ ] Non-Admin navigation contains 9 items.
* [ ] Admin navigation contains 10 items, including Users.
* [ ] No drawer navigation item is omitted relative to the canonical desktop navigation.
* [ ] Selecting a navigation item closes the drawer.
* [ ] Pressing Escape closes the drawer.
* [ ] Activating/tapping the scrim closes the drawer.

## 360px Overflow

At 360px, verify by measurement rather than visual inspection:

* [ ] Login has no horizontal body overflow.
* [ ] Each of the three Dashboard screens has no horizontal body overflow.
* [ ] At least one representative List screen has no horizontal body overflow.
* [ ] At least one representative Detail screen has no horizontal body overflow.
* [ ] At least one representative Form screen has no horizontal body overflow.
* [ ] For each sample, `document.body.scrollWidth === window.innerWidth`.

## Touch Targets

* [ ] Required sampled interactive controls have a rendered bounding box of at least 44×44px at 360px.
* [ ] Sampling includes at minimum: primary navigation item, primary CTA, one table-row action, and one form input/control.
* [ ] Touch-target compliance is established by actual DOM measurement or an equivalent reliable measurement method, not by relying on screenshots alone.

---

# B-06 — Goods Issue Typography and Spacing

* [ ] Goods Issue regenerated `tailwind.config` contains a `fontSize` block.
* [ ] Goods Issue regenerated `tailwind.config` contains a `spacing` block.
* [ ] Goods Issue `fontSize` values are identical to the standard product-screen configuration.
* [ ] Goods Issue `spacing` values are identical to the standard product-screen configuration.
* [ ] No Goods Issue-specific type scale or page-specific spacing scale exists.
* [ ] The rendered Goods Issue specimen uses the intended type ladder and does not rely on browser-default fallback sizing.
* [ ] No arbitrary typography value replaces an existing canonical scale step.

---

# B-07 — Canonical State Registry

The canonical registry consists of exactly these 16 state IDs:

```text
01_LOADING
02_DATA
03_EMPTY
04_NO_RESULTS
05_VALIDATION_ERROR
06_FORBIDDEN
07_NOT_FOUND
08_SESSION_EXPIRED
09_SERVER_ERROR
10_NETWORK_ERROR
11_MUTATION_SUCCESS
12_MUTATION_ERROR
13_BUSINESS_ERROR
14_STALE_DATA
15_CONFIRMATION
16_ACTION_LOADING
```

The re-audit must independently verify that this list matches the current authoritative registry source before using it as the audit baseline.

* [ ] The current authoritative registry contains exactly the 16 canonical IDs above and no additional canonical state ID.
* [ ] The canonical Reports screen's zero-filter-result state is `NO_RESULTS`, not `EMPTY`.
* [ ] No regenerated Reports markup classifies filter-zero results as `EMPTY`.
* [ ] No regenerated screen introduces a new state concept outside the canonical 16.
* [ ] Current regenerated screen state identifiers are canonicalized consistently with the approved mapping decision.
* [ ] The historical 51→16 mapping table exists as an appendix in `docs/design/ioms-ui-design.md`.
* [ ] The appendix accounts for all 51 original local state keys.
* [ ] Every original local key maps to exactly one canonical state.
* [ ] No original key is left unmapped.
* [ ] No new local state is invented merely to avoid mapping an existing key.
* [ ] The 51→16 appendix is treated as a traceability artifact for the original export; it does not authorize those historical aliases to remain as current canonical state identifiers.

---

# B-08 — Accessibility: Live Regions, Validation, Focus, and Dialogs

## State Announcements

For the corresponding active state containers:

* [ ] `LOADING` uses `role="status"` + `aria-live="polite"`.
* [ ] `EMPTY` uses `role="status"` + `aria-live="polite"`.
* [ ] `NO_RESULTS` uses `role="status"` + `aria-live="polite"`.
* [ ] `MUTATION_SUCCESS` uses `role="status"` + `aria-live="polite"`.
* [ ] `STALE_DATA` uses `role="status"` + `aria-live="polite"`.

For the corresponding active state containers:

* [ ] `SERVER_ERROR` uses `role="alert"` + `aria-live="assertive"`.
* [ ] `NETWORK_ERROR` uses `role="alert"` + `aria-live="assertive"`.
* [ ] `MUTATION_ERROR` uses `role="alert"` + `aria-live="assertive"`.
* [ ] `BUSINESS_ERROR` uses `role="alert"` + `aria-live="assertive"`.

## Validation

* [ ] Every active `VALIDATION_ERROR` field carries `aria-invalid="true"`.
* [ ] Every active `VALIDATION_ERROR` field carries `aria-describedby`.
* [ ] Each `aria-describedby` points to a real DOM element containing a non-empty validation message.

## Session Expiry

* [ ] `SESSION_EXPIRED` is surfaced using `role="alertdialog"`.

## Action Loading

* [ ] Every active `ACTION_LOADING` control carries `aria-busy="true"`.
* [ ] Every active `ACTION_LOADING` control carries `aria-disabled="true"`.

## Modal / Dialog Focus

For every modal-bearing screen found in the regenerated design, including at minimum Categories, Suppliers, Warehouses, Goods Receipt, and SO Detail:

* [ ] Focus automatically moves into the dialog when it opens.
* [ ] The initial focus lands on the first appropriate focusable element.
* [ ] Tab remains trapped within the dialog.
* [ ] Shift+Tab remains trapped within the dialog.
* [ ] Escape closes the dialog.
* [ ] Focus returns to the element that triggered the dialog after close.

## Focus Visibility

* [ ] A visible `:focus-visible` outline is applied to interactive elements.
* [ ] The focus indicator is visually observable on sampled interactive controls outside the login screen.
* [ ] A focus utility/class is not merely declared without being applied.

## Automated Accessibility Verification

* [ ] An automated accessibility scan (axe-core or equivalent) is run against each of the 30 product screens in the current rendered build.
* [ ] There are zero violations attributable to the B-08 acceptance conditions above.
* [ ] Any unrelated accessibility findings outside B-08 scope are separately recorded rather than silently reclassified as B-08 failures or ignored.

---

# B-09 — Disabled CSV Export Reason

For every disabled export state where the user needs to know why export is unavailable:

* [ ] The reason text exists in the DOM whenever the export control is disabled.
* [ ] The reason text is contained in a normal, non-hidden element.
* [ ] The reason is not dependent on `:hover`.
* [ ] The reason is not dependent on `:focus`.
* [ ] The export control carries `aria-describedby` while disabled.
* [ ] `aria-describedby` points to the actual reason element.
* [ ] The referenced reason element has a stable ID.
* [ ] The referenced reason element contains non-empty explanatory text.
* [ ] The explanation remains programmatically accessible without pointer hover.
* [ ] A supplementary visual tooltip may exist, but it is not the sole delivery mechanism.
* [ ] All six documented disabled-export reasons receive the same accessible treatment.
* [ ] Verification includes an accessibility-tree/accessibility-description inspection or an equivalent manual screen-reader check.

---

# Independent Regression Sweep

After completing B-01…B-09, perform an independent sweep for defects introduced by the regeneration/patching process, even if they are not listed above.

At minimum inspect:

* [ ] Navigation and shell consistency.
* [ ] Desktop/tablet/mobile responsive behavior.
* [ ] Modal/dialog interaction.
* [ ] Keyboard navigation and focus order.
* [ ] Forms and primary actions.
* [ ] Tables and table actions.
* [ ] State transitions and state semantics.
* [ ] Horizontal/vertical overflow.
* [ ] Missing or broken assets.
* [ ] Duplicate or contradictory canonical screens.
* [ ] New unapproved features or screens.
* [ ] New requirement/design contradictions.
* [ ] Accessibility regressions unrelated to the specific B-08 checks.
* [ ] Copy/terminology that newly contradicts the approved product/architecture vocabulary.

Any newly introduced **blocking** defect keeps the gate **CLOSED**, even when B-01…B-09 individually pass.

---

# Evidence Requirements

A PASS must be supported by concrete evidence from the current state of the project.

Evidence may include:

* Source/code inspection.
* Programmatic scans or grep results.
* DOM measurements.
* Runtime interaction tests.
* Accessibility-tree inspection.
* axe-core or equivalent scan results.
* Rendered screenshots or screen recordings where visual behavior is material.
* Structural/config diffs.
* Current documentation excerpts.

The auditor must distinguish:

1. **Source evidence**
2. **Runtime/DOM evidence**
3. **Visual evidence**
4. **Documentation evidence**

A previous audit result is not evidence that the current regenerated design still passes.
A patch prompt is not evidence.
An acceptance criterion is not evidence.

---

# Final Re-Audit Verdict

The re-audit result is:

## PASS — Eligible for Gate OPEN

only when all of the following are true:

```text
B-01 = PASS
B-02 = PASS
B-03/B-04 = PASS
B-05 = PASS
B-06 = PASS
B-07 = PASS
B-08 = PASS
B-09 = PASS
Independent Regression Sweep = PASS
Zero unresolved blocking findings
```

Otherwise:

## FAIL — Gate remains CLOSED

The re-audit must not modify the recorded gate status in `docs/design/design-audit.md`.
Only the Project Owner may change the recorded gate from **CLOSED** to **OPEN**.

---

# Sign-off

| Check                         | Result        | Evidence link | Date |
| ----------------------------- | ------------- | ------------- | ---- |
| B-01                          | ☐ Pass ☐ Fail |               |      |
| B-02                          | ☐ Pass ☐ Fail |               |      |
| B-03/B-04                     | ☐ Pass ☐ Fail |               |      |
| B-05                          | ☐ Pass ☐ Fail |               |      |
| B-06                          | ☐ Pass ☐ Fail |               |      |
| B-07                          | ☐ Pass ☐ Fail |               |      |
| B-08                          | ☐ Pass ☐ Fail |               |      |
| B-09                          | ☐ Pass ☐ Fail |               |      |
| Independent Regression Sweep  | ☐ Pass ☐ Fail |               |      |

**Gate:**
☐ remains **CLOSED**
☐ **may move to OPEN** after all criteria pass and Gate Rule steps 1–3 are independently confirmed complete.

**Project Owner:** ____________________
**Decision:** ☐ Approved to OPEN ☐ Remains CLOSED
**Date:** ____________________

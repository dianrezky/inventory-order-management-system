# Stitch Patch Scope — B-01…B-09

**Status:** DRAFT — derived ONLY from the approved decisions in `DESIGN_DECISION_RECORD_B01_B09.md`.
Do not action this until that record is approved. This document identifies affected Stitch
screens/artifacts and defines the regeneration brief; it does not itself decide anything.

**Note on source availability:** the raw Stitch export (`code.html`/`screen.png`/`DESIGN.md` files)
is not committed to this repository — only its description in `docs/design/ioms-ui-design.md` is.
"Patching Stitch" therefore means: (a) re-running the Stitch generation tool with the prompt below
against the original export, or (b) hand-editing the export outside this repo, then re-importing an
updated `docs/design/ioms-ui-design.md` description. Either way, the instructions below are the
deterministic brief for whichever path is used — they are not a set of file edits to apply directly
to this repository.

---

## 1. Affected screens/artifacts — full inventory

| Decision | Affected screens | Change type |
|---|---|---|
| B-01 tokens | All 31 exported artifacts (`operational_enterprise_ioms/DESIGN.md`, all 28 shell screens, `design_system_component_foundation`, `authenticated_application_shell`, `system_states_inventory_order_management`, both `login_*`) | Replace all color/typography/radius values |
| B-02 shell/header consistency (amended 2026-09-08) | All 28 shell-bearing screens | No new controls — verify shared shell/header consistency only; locale/theme switching is out of scope, see `DESIGN_DECISION_RECORD_B01_B09.md` §B-02 |
| B-03/B-04 Reports | `reports_inventory_order_management`, `reports_with_data_visualization_inventory_order_management` | Retire viz screen from canonical set; fix currency + remove column picker on tabular screen |
| B-05 responsive shell | All 28 shell-bearing screens + `authenticated_application_shell` blueprint | Full responsive rebuild |
| B-06 Goods Issue | `goods_issue_inventory_order_management` | Add missing Tailwind config sections |
| B-07 state vocabulary | All 26 product screens + `system_states_inventory_order_management` | Rename state keys |
| B-08 accessibility (live/focus) | All 30 product screens + any modal-bearing screen (Categories, Suppliers, Warehouses, Goods Receipt, SO detail) | Add ARIA + focus-trap behavior notes |
| B-09 CSV export reason | `reports_inventory_order_management` (and any other disabled-with-reason control found in B-07's full mapping) | Replace hover-only tooltip mechanism |

---

## 2. Deterministic patch prompt

Use the following as the instruction set for the next Stitch generation/regeneration pass. It is
written to be handed to the Stitch tool directly.

> Regenerate the existing "Inventory & Order Management System" design export
> (`stitch_remix_of_remix_of_enterprise_ioms_design_system_fix`), preserving every existing
> requirement, screen inventory, and functional behavior. Do not introduce a new visual concept,
> rebrand, or restructure the information architecture. Apply exactly the following corrections and
> nothing else:
>
> 1. **Tokens.** Replace every color, typography, radius, and spacing value across all screens and
>    `DESIGN.md` with the canonical set below (source: `ux-ui-spec.md` §1). Delete the app-lineage
>    Material-3 token names, the `DESIGN.md` slate/navy palette, and the login-lineage `brand`/
>    `surface`/`neutral`/`semantic` names — use one Tailwind config, shared by every screen, built
>    from this table:
>    - Background: `#FFFFFF` (dark `#14171C`). Surface: `#F5F6F8` (dark `#1E222A`).
>    - Text primary: `#1A1D23` (dark `#F5F6F8`). Text secondary: `#5B6472` (dark `#A8B0BD`).
>    - Border: `#E1E4E8` (dark `#2C313B`).
>    - Primary/brand: `#2563EB` (dark `#60A5FA`). Secondary/brand: `#7C3AED` (dark `#A78BFA`).
>    - Error/danger: `#DC2626` (dark `#F87171`). Success: `#15803D` (dark `#4ADE80`).
>    - Warning: `#B45309` (dark `#FBBF24`). Info: `#0E7490` (dark `#67E8F9`).
>    - Typography: Display 32/40/700, H1 28/36/700, H2 22/30/600, H3 18/26/600, Body 14/20/400,
>      Body Small 13/18/400, Caption 12/16/400, Label 12/16/600 uppercase. Font: Inter.
>    - Radius: 4px small (badges/inputs), 8px medium (cards/buttons), 12px large (modals). No pill
>      (999px/`rounded-full`) shapes anywhere.
>    - Spacing: 4/8/12/16/24/32/48px (4-and-8 base scale).
>    Every screen must share one Tailwind config with these values — no per-screen palette variation.
>
> 2. **Header/shell consistency (amended 2026-09-08 — locale/theme switching out of scope).** Do
>    NOT add a locale toggle or a theme toggle to any screen. Instead, ensure every shell-bearing
>    screen uses the same header/navigation structure — no arbitrary per-screen header variant — and
>    reuses one shared shell rather than duplicating it per screen.
>
> 3. **Reports.** Remove the data-visualization Reports screen from the canonical screen set (retain
>    only as an explicitly-labeled non-canonical bonus reference, never mixed into the main flow). On
>    the remaining tabular Reports screen: change all currency formatting from USD (`$`) to IDR
>    (`Rp`, thousands-separator, no decimal places); remove the "Columns (9)" custom column-selection
>    control entirely.
>
> 4. **Responsive shell — all shell-bearing screens.** Implement three breakpoints:
>    - ≥1024px: persistent 256px sidebar (existing desktop behavior, unchanged).
>    - 768–1023px: sidebar collapses to a 64px icon-only rail, expandable on hover/click.
>    - <768px (must remain fully functional and non-overflowing down to 360px): sidebar becomes a
>      slide-in drawer, hidden by default, opened by a header hamburger button, with a scrim behind
>      it. The drawer's navigation list must be **complete and identical to the desktop sidebar** —
>      Dashboard, Products, Categories, Warehouses, Suppliers, Customers, Purchase Orders, Sales
>      Orders, Reports, and (Admin role only) Users. Do not produce a reduced or partial nav list in
>      the drawer. Closing the drawer: selecting any nav item, pressing Escape, or tapping the scrim.
>    At 360px, verify zero horizontal body overflow on the Login, each of the three Dashboards, one
>    List screen, one Detail screen, and one Form screen.
>
> 5. **Goods Issue typography.** Add `fontSize` and `spacing` sections to the Goods Issue screen's
>    Tailwind config, identical in values to every other product screen (per the token table in
>    item 1). Do not create a page-specific scale.
>
> 6. **State vocabulary.** Use exactly these 16 canonical state ids and no others, no aliases:
>    `01_LOADING`, `02_DATA`, `03_EMPTY`, `04_NO_RESULTS`, `05_VALIDATION_ERROR`, `06`…`08_SESSION_EXPIRED`,
>    `09_SERVER_ERROR`, `10_NETWORK_ERROR`, `11`, `12_MUTATION_ERROR`, `13_BUSINESS_ERROR`,
>    `14_STALE_DATA`, `15_CONFIRMATION`, `16_ACTION_LOADING`. Specifically: on both Reports screens,
>    rename the zero-filter-result state from `EMPTY` to `NO_RESULTS` (dataset has records, but the
>    active filter matched none — that is the `NO_RESULTS` definition, not `EMPTY`). Do not introduce
>    any new local/ad-hoc state key anywhere; every screen-local state must map onto one of the 16.
>
> 7. **Accessibility — state announcements.** On every screen, attach to the relevant state container:
>    `role="status"` + `aria-live="polite"` for LOADING/EMPTY/NO_RESULTS/MUTATION_SUCCESS/STALE_DATA;
>    `role="alert"` + `aria-live="assertive"` for SERVER_ERROR/NETWORK_ERROR/MUTATION_ERROR/
>    BUSINESS_ERROR; `aria-invalid="true"` + `aria-describedby` pointing at the message element for
>    VALIDATION_ERROR fields; `role="alertdialog"` for SESSION_EXPIRED; `aria-busy="true"` +
>    `aria-disabled="true"` for ACTION_LOADING controls.
>
> 8. **Accessibility — focus.** Every modal/dialog on every screen: focus auto-moves to its first
>    focusable element on open; Tab/Shift-Tab must cycle only within the dialog while open; Escape
>    closes it; focus returns to whatever element opened it. Apply a visible `:focus-visible` outline
>    (2px, using the border/primary token) consistently to every interactive element on every screen —
>    do not declare a focus style class without applying it anywhere, as the current export does.
>
> 9. **CSV export disabled reason.** On the Reports screen's export control, and any other control
>    disabled with a reason the user must learn, remove the `hidden group-hover:block` tooltip
>    mechanism as the sole channel. Instead: render the reason text in a normal, non-hidden element
>    with a stable id, and set `aria-describedby` on the control to that id at all times the control
>    is disabled, regardless of hover/focus state. A supplementary visible tooltip on hover may remain,
>    but must not be the only way the reason is exposed.
>
> Apply items 1–9 uniformly across all 31 exported artifacts. Do not touch anything not named above:
> preserve existing screen inventory, existing copy/labels not mentioned here, existing layout
> structure beyond the shell/responsive change in item 4, and existing component set.

---

## 3. What this patch explicitly does NOT authorize

- No new screens, new report types, or new features beyond what is listed.
- No locale toggle, theme toggle, or any other new control — locale/theme switching is out of scope
  for the current release (see `DESIGN_DECISION_RECORD_B01_B09.md` §B-02, amended 2026-09-08).
- No change to `i18next` vs. vanilla-JS implementation choice (that is `CF-02`, tracked separately in
  `docs/planning/master-project-specification.md` §46 — a technology-compliance question, not a
  design-patch item).
- No re-litigating B-01's token choice, B-03/B-04's Reports scope decision, or any other item already
  closed in `DESIGN_DECISION_RECORD_B01_B09.md` — this prompt exists to execute those decisions, not
  to reopen them.
- No production code change — this document only defines the Stitch regeneration brief.

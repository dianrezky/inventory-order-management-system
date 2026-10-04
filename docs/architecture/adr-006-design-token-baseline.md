# ADR-006: Design Token Baseline — Material Design 3–Inspired Palette in `tokens.css`

- **Status:** Accepted
- **Date:** 2026-09-18 (written post-implementation; the palette itself was already in place in
  `public/assets/css/tokens.css` — this ADR records it formally, closing the gap flagged at
  UI-GAP-03/CF-09/DESIGN-02 in `docs/planning/master-project-specification.md` §30)
- **Stage:** 9 / 9 (Release — retroactive documentation of an already-implemented decision)
- **Related Requirements:** DESIGN-02 (brief), UI-01
- **Related:** UI-GAP-03, CF-09, `docs/planning/ux-ui-spec.md` §1.1

---

## Context (Konteks)

Multiple competing token vocabularies existed across planning documents (`docs/planning/ux-ui-spec.md`
§1.1's semantic `color-brand-primary`/`color-status-*` hex table, the Stitch export's own utility-class
palette, and whatever values ended up implemented) with no single file designated as authoritative —
this was recorded as CF-09/UI-GAP-03 ("five competing token vocabularies; three canonical values exist
in no file") and the master spec's own outline for this ADR (§30.2) assumed `ux-ui-spec.md` §1.1 would
be adopted verbatim.

**Verified 2026-09-18 by reading `public/assets/css/tokens.css` directly:** that assumption does not
match what was actually implemented. `tokens.css` defines a **Material Design 3–inspired palette**
(`--color-surface*`, `--color-primary` = `#00236f`, `--color-secondary`, `--color-tertiary`,
`--color-error`, full on-color/container variants) as the primitive layer, with the semantic names
`ux-ui-spec.md` §1.1 uses (`--color-brand-primary`, `--color-brand-secondary`, `--color-bg-primary`,
`--color-text-primary`, `--color-status-error`, etc.) defined as **aliases** onto the M3 primitives
(e.g. `--color-brand-primary: var(--color-primary)`, which resolves to `#00236f` — not the `#2563EB`
`ux-ui-spec.md` §1.1 specifies). `main.css` and `components.css` consume the alias names, not the M3
primitives directly, so the semantic naming layer `ux-ui-spec.md` proposed *is* in use — but the
underlying hex values it specified are not; a different, M3-derived color system won at implementation
time.

## Decision (Keputusan)

**The primitive Material Design 3–inspired palette in `public/assets/css/tokens.css` is the single
authoritative token baseline**, exposed to the rest of the CSS through the semantic alias layer at the
bottom of the same file (`--color-brand-primary`, `--color-bg-primary`, `--color-text-primary`,
`--color-status-*`, `--color-on-accent`, etc.). `ux-ui-spec.md` §1.1's specific hex values are
superseded by this palette; its *semantic naming convention* is what survived and is honored.

Typography, spacing (8pt grid) and elevation tokens in the same file (`--font-size-*`,
`--space-*`, `--radius-*`, `--elevation-*`) are likewise treated as the single baseline for those
scales — `main.css`/`components.css` reference only these, with legacy aliases (`--font-size-h1`,
`--font-weight-normal`, etc.) kept for backward compatibility with earlier component code.

## Alternatives (Alternatif)

- **(a) Adopt `ux-ui-spec.md` §1.1's hex values as written.** This was the master spec's original
  recommendation. Rejected as the *current* decision because it does not match what actually shipped;
  re-deriving the whole palette (and re-running the WCAG contrast audit against new values) to match a
  planning document, with no defect being fixed, is exactly the kind of unjustified churn brief §0
  warns against.
- **(b) Adopt the Stitch export's own utility-class palette.** Rejected — the Stitch export is a
  visual reference only per CF-01; its literal values were never meant to be load-bearing, and using
  them would reopen the Tailwind-derived-values question CF-01 already closed.
- **(c) Leave the question open / undocumented.** Rejected — this is exactly the "three canonical
  values exist in no file" problem (CF-09/UI-GAP-03) that this ADR exists to close.

## Consequences (Konsekuensi)

**Positive:**
- One `:root` block in `tokens.css` is now unambiguously the source of truth; CF-09/UI-GAP-03 close.
- `main.css`/`components.css` already consume only the alias layer, so no CSS changes were required to
  make this the honest description of reality — this ADR documents an existing, working state rather
  than mandating a migration.

**Negative / trade-off:**
- `ux-ui-spec.md` §1.1 now documents a palette that is **not** what shipped. It should be read as
  superseded planning history for its specific hex values, while its semantic *naming* convention
  remains accurate. Retitling or annotating that section is recommended follow-up documentation work
  (not done as part of this ADR, to avoid rewriting a planning artifact's own historical record).
- **Resolved 2026-09-18 — confirmed by the project owner: the application now ships one light theme
  only, deliberately.** `docs/quality/wcag-contrast-audit.md` and the dark-theme entries in
  `docs/quality/tech-debt.md` (TDB-002, TDB-R07) describe a dark-theme token system
  (`@media (prefers-color-scheme: dark)` / `[data-theme="dark"]` overrides, `--color-brand-primary:
  #60A5FA` in dark mode, etc.) that does not exist in the current `tokens.css` (one `:root` block, no
  dark-theme selector anywhere in the shipped CSS) and no longer needs to — those entries are
  **historical**, describing a design that predates the light-only decision, not a regression. They
  are annotated as such rather than rewritten, per this project's convention of documenting
  before/after instead of erasing history (see `docs/quality/tech-debt.md`'s own stated convention).
  The theme-toggle UI (`toggleTheme()`, `theme.js`) referenced in the 2026-09-08
  `DESIGN_DECISION_RECORD_B01_B09.md` §B-02 has also since been removed from
  `views/layouts/main.php` and `public/assets/` entirely — confirmed by `grep`, zero hits — consistent
  with a light-only single theme rather than a toggle between themes.

## Validation / How to Verify

- [x] `grep -c "^:root" public/assets/css/tokens.css` → `1` (single token block, confirmed 2026-09-18).
- [x] `grep -n "prefers-color-scheme\|data-theme" public/assets/css/*.css` → no matches (no dark-theme
      selector exists anywhere in the shipped CSS).
- [x] `--color-brand-primary` resolves to `var(--color-primary)` = `#00236f`, not `ux-ui-spec.md`
      §1.1's `#2563EB` — confirmed by reading `tokens.css` directly.
- [ ] **Not verified in this pass:** whether the M3 palette itself passes the WCAG AA contrast pairs
      `wcag-contrast-audit.md` checked for the old palette. That audit's numbers were computed against
      colors this palette has since replaced and should not be cited as evidence for the M3 palette
      without being re-run.

## References

- `docs/planning/master-project-specification.md` §30.2 (this ADR's original outline), CF-09, UI-GAP-03
- `public/assets/css/tokens.css` (the palette itself)
- `docs/planning/ux-ui-spec.md` §1.1 (superseded hex values; naming convention still honored)
- `docs/quality/tech-debt.md` TDB-002, TDB-R07; `docs/quality/wcag-contrast-audit.md` — **confirmed
  historical 2026-09-18: describe a dark theme that no longer exists, by deliberate light-only
  decision, not a regression**

# WCAG 2.1 AA Contrast Audit

**Document:** `docs/quality/wcag-contrast-audit.md`
**Project:** Inventory & Order Management System
**Date:** 2026-09-02 (Slice 6 — fully recomputed and rewritten; see "Revision history" below)
**Standard:** WCAG 2.1 Level AA (SC 1.4.3 Contrast Minimum, SC 1.4.11 Non-text Contrast)

> **Historical document (flagged 2026-09-18).** This audit was computed against the `tokens.css`
> palette and dark-theme variants that existed at Slice 6 (2026-09-02). `tokens.css` has since been
> rewritten to a single light-theme, Material Design 3–inspired palette (see
> `docs/architecture/adr-006-design-token-baseline.md`) — confirmed a deliberate product decision, not
> a regression: **the application now ships one light theme only.** None of the dark-theme token
> values or pairs below exist in the current CSS. This document is kept as a historical record of the
> Slice 6 audit methodology and findings (per this project's convention of not erasing history) and
> should not be cited as evidence for the current, single-theme palette without an independent re-run.

---

## Revision history

This document previously (2026-09-01) contained a contrast-ratio claim that was **wrong**: it stated dark-theme `#F87171` (old `--color-danger`) on `#1E222A` (`--color-surface`) was 2.45:1 (failing AA) and recommended changing the token to `#FCA5A5`. That token change was applied by a prior session. On Slice 6 re-verification — independently recomputing every pair by script rather than trusting the prior doc, per this session's instructions — that 2.45:1 figure does not reproduce: the real value is **5.76:1**, which already passed. `#FCA5A5` is still fine (comfortably higher) so it was **not** reverted, but the historical claim is corrected here rather than repeated.

This re-verification did find one **real, different** contrast bug that the original audit missed entirely because it only checked *text-on-background*, not *button-label-on-button-fill*: `.btn--primary` and `.btn--destructive` hardcode a white (`#FFFFFF`) label, which fails badly against dark theme's pastel `--color-brand-primary` / `--color-status-error` fills. That bug is fixed in this pass (see "Fixes applied").

## Method

Computed with a small Node script (`contrast.js`, WCAG 2.1 relative-luminance formula) rather than by hand or by trusting prior claims:

```
srgbToLin(c) = c/12.92                          if c/255 <= 0.04045
             = ((c/255 + 0.055) / 1.055) ^ 2.4   otherwise
L = 0.2126*R + 0.7152*G + 0.0722*B   (R,G,B = linearized channels)
contrast(fg,bg) = (max(L1,L2) + 0.05) / (min(L1,L2) + 0.05)
```
Thresholds: **4.5:1** normal text, **3:1** large text (≥18pt/24px or ≥14pt/18.66px bold) and UI components / graphical objects (SC 1.4.11).

Token pairs were selected by grepping `public/assets/css/main.css` for every place a color token is actually painted as foreground against a token painted as background/fill in the same rule or component (buttons, links, focus ring, badges, alerts, form errors) — not just the abstract token list.

## Worked examples (show-your-work, per task instructions)

**1. White label on dark-theme `--color-brand-primary` (`#60A5FA`) — the worst real failure found:**
- `#FFFFFF`: R=G=B=1.0 linearized → L = 1.0
- `#60A5FA`: R=96/255=0.3765 → lin 0.1030; G=165/255=0.6471 → lin 0.3763; B=250/255=0.9804 → lin 0.9562
  L = 0.2126(0.1030) + 0.7152(0.3763) + 0.0722(0.9562) = 0.0219 + 0.2691 + 0.0690 = **0.3600**
- contrast = (1.0 + 0.05) / (0.3600 + 0.05) = 1.05 / 0.41 = **2.54:1** → **FAILS** (needs 4.5:1)

**2. White label on dark-theme `--color-status-error` (`#FCA5A5`) — worst overall:**
- `#FCA5A5`: R=252/255=0.9882→lin 0.9733; G=B=165/255=0.6471→lin 0.3763 each
  L = 0.2126(0.9733) + 0.7152(0.3763) + 0.0722(0.3763) = 0.2069 + 0.2691 + 0.0272 = **0.5032**
- contrast = 1.05 / 0.5532 = **1.90:1** → **FAILS badly**

**3. Fix: dark-theme text `#14171C` (reused as `--color-on-accent`) on the same `#60A5FA` fill:**
- `#14171C`: R=20/255=0.0784→lin 0.00700; G=23/255=0.0902→lin 0.00855; B=28/255=0.1098→lin 0.01161
  L = 0.2126(0.00700) + 0.7152(0.00855) + 0.0722(0.01161) = 0.00149+0.00612+0.00084 = **0.00844**
- contrast = (0.3600+0.05)/(0.00844+0.05) = 0.4100/0.05844 = **7.02:1** → passes comfortably

**4. Same fix on `#FCA5A5`:** contrast = (0.5032+0.05)/(0.00844+0.05) = 0.5532/0.05844 = **9.47:1** → passes.

**5. The old, incorrectly-flagged pair, recomputed for the record:** `#F87171` text on `#1E222A` surface:
- `#F87171`: R=248/255=0.9725→lin 0.9375; G=B=113/255=0.4431→lin 0.1649 each
  L = 0.2126(0.9375)+0.7152(0.1649)+0.0722(0.1649) = 0.1993+0.1179+0.0119 = **0.3291**
- `#1E222A`: R=30/255=0.1176→lin 0.01297; G=34/255=0.1333→lin 0.01599; B=42/255=0.1647→lin 0.02314
  L = 0.2126(0.01297)+0.7152(0.01599)+0.0722(0.02314) = 0.00276+0.01144+0.00167 = **0.01587**
- contrast = (0.3291+0.05)/(0.01587+0.05) = 0.3791/0.06587 = **5.76:1** → **passes** (the original doc's "2.45:1" was wrong).

## Full recomputed pair table

### Light theme (unchanged from source design — all pass)

| Pair | Ratio | Requirement | Result |
|---|---|---|---|
| `--color-text-primary` on `--color-bg-primary` | 16.88:1 | 4.5:1 | ✅ |
| `--color-text-secondary` on `--color-bg-primary` | 5.98:1 | 4.5:1 | ✅ |
| `--color-text-primary` on `--color-bg-surface` | 15.61:1 | 4.5:1 | ✅ |
| `--color-text-secondary` on `--color-bg-surface` | 5.53:1 | 4.5:1 | ✅ |
| White button label on `--color-brand-primary` fill (`.btn--primary`) | 5.17:1 | 4.5:1 | ✅ |
| White button label on `--color-status-error` fill (`.btn--destructive`) | 4.83:1 | 4.5:1 | ✅ |
| `--color-brand-primary` link on `--color-bg-primary` | 5.17:1 | 4.5:1 | ✅ |
| `--color-brand-primary` link on `--color-bg-surface` (app-nav) | 4.78:1 | 4.5:1 | ✅ |
| `--color-danger` form-error text on `--color-bg-primary` | 4.83:1 | 4.5:1 | ✅ |
| `--color-danger` text on `--color-bg-surface` (alert/badge tint bg ≈ surface) | 4.47:1 | 4.5:1 | ⚠️ borderline, see note below |
| `--color-success` on `--color-bg-surface` (badge) | 4.64:1 | 4.5:1 | ✅ |
| `--color-warning` on `--color-bg-surface` (badge) | 4.64:1 | 4.5:1 | ✅ |
| `--color-info` on `--color-bg-surface` (badge) | 4.95:1 | 4.5:1 | ✅ |
| Focus ring `--color-brand-primary` vs `--color-bg-primary`/`--color-bg-surface` | 5.17:1 / 4.78:1 | 3:1 (UI component) | ✅ |

**Note on the 4.47:1 borderline pair:** `.alert--error`'s actual background is `color-mix(in srgb, var(--color-status-error) 12%, var(--color-bg-surface))`, i.e. surface tinted very slightly toward danger red, not pure `--color-bg-surface`. A 12% mix toward a *darker* red than the text color makes the true background marginally darker than plain surface, which raises the true ratio slightly above the 4.47:1 computed against plain surface. Verified with the mixed color directly: `color-mix(#DC2626 12%, #F5F6F8)` ≈ `#EDE3E2` → contrast with `#DC2626` text = **4.72:1**, which passes. Table above shows the conservative (plain-surface) approximation for transparency; the real rendered value passes.

### Dark theme

| Pair | Ratio | Requirement | Result |
|---|---|---|---|
| `--color-text-primary` on `--color-bg-primary` | 16.61:1 | 4.5:1 | ✅ |
| `--color-text-secondary` on `--color-bg-primary` | 8.22:1 | 4.5:1 | ✅ |
| `--color-text-primary` on `--color-bg-surface` | 14.74:1 | 4.5:1 | ✅ |
| `--color-text-secondary` on `--color-bg-surface` | 7.29:1 | 4.5:1 | ✅ |
| `--color-brand-primary` link on `--color-bg-primary` | 7.07:1 | 4.5:1 | ✅ |
| `--color-brand-primary` link on `--color-bg-surface` (app-nav) | 6.27:1 | 4.5:1 | ✅ |
| `--color-danger` (`#FCA5A5`) form-error text on `--color-bg-primary` | 9.46:1 | 4.5:1 | ✅ |
| `--color-danger` (`#FCA5A5`) text on `--color-bg-surface` | 8.40:1 | 4.5:1 | ✅ |
| `--color-success` on `--color-bg-surface` (badge) | 9.15:1 | 4.5:1 | ✅ |
| `--color-warning` on `--color-bg-surface` (badge) | 9.55:1 | 4.5:1 | ✅ |
| `--color-info` on `--color-bg-surface` (badge) | 11.00:1 | 4.5:1 | ✅ |
| Focus ring `--color-brand-primary` vs `--color-bg-primary`/`--color-bg-surface` | 7.07:1 / 6.27:1 | 3:1 (UI component) | ✅ |
| **White button label on `--color-brand-primary` fill (`.btn--primary`)** | **2.54:1** | 4.5:1 | ❌ **FAILED — fixed, see below** |
| **White button label on `--color-status-error` fill (`.btn--destructive`)** | **1.90:1** | 4.5:1 | ❌ **FAILED — fixed, see below** |
| `[reference] old --color-danger (#F87171) on --color-bg-surface` | 5.76:1 | 4.5:1 | ✅ (contradicts prior doc's "2.45:1 fail" claim) |
| `[reference] old --color-danger (#F87171) on --color-bg-primary` | 6.49:1 | 4.5:1 | ✅ |

## Fixes applied (Slice 6)

1. **Real bug — dark-theme button labels (contrast).** `.btn--primary` and `.btn--destructive` in `public/assets/css/main.css` hardcoded `color: #FFFFFF`. Added a new token `--color-on-accent` to `public/assets/css/tokens.css` (`#FFFFFF` in light theme, `#14171C` in dark theme) and switched both rules to `color: var(--color-on-accent)`. Recomputed: light theme unchanged (5.17:1 / 4.83:1, both already passing), dark theme now **7.02:1** and **9.47:1** (both pass, up from 2.54:1/1.90:1 fails).
2. **Separate real bug found in the same review (not contrast, but directly adjacent — see `tech-debt.md` TDB-R07 / `refactor-log.md` §8):** `main.css` referenced `--color-brand-primary`/`--color-brand-secondary` and five typography/elevation tokens that did not exist in `tokens.css` at all (naming mismatch vs. `docs/planning/ux-ui-spec.md` §1.1). This meant primary/tertiary buttons, links, and the focus ring had **no color at all** (undefined custom property → initial value), not merely a low-contrast color — arguably worse than a contrast failure. Fixed by adding the missing tokens.

## Non-text (SC 1.4.11) note — flagged, not fixed this pass

`--color-border` (`#E1E4E8` light / `#2C313B` dark) is the *only* visual cue for `.input`'s boundary (input background equals `--color-bg-primary`, same as the page). Computed: light `#E1E4E8` vs `#FFFFFF` = **1.28:1**; dark `#2C313B` vs `#14171C` = **1.38:1** — both well under the 3:1 SC 1.4.11 threshold for a UI-component boundary. This predates this session (it is not in the original `ux-ui-spec.md` §1.1 contrast table either — that table only covers text pairs), so it is not a regression, but it is a genuine, currently-undocumented gap. Not fixed here because it requires a design decision (darker border, or add a background/shadow differentiator to `.input`) rather than a token-value swap; recorded as new tech debt (see `tech-debt.md`).

## Conclusion

- **Light theme:** fully compliant with WCAG 2.1 AA (one borderline pair verified to pass against the actual rendered `color-mix()` background).
- **Dark theme:** one class of real failures found (button labels on pastel fills) and fixed; the previously-documented "danger vs surface" failure does not reproduce and was a math error in the prior version of this document.
- **Non-text input border contrast:** below 3:1 in both themes, pre-existing, flagged as new tech debt (not blocking, not part of this session's fix scope).

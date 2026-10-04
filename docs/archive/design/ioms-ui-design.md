# IOMS UI Design Reference

**Source:** Google Stitch export `stitch_remix_of_remix_of_enterprise_ioms_design_system_fix`
**Design system name (DESIGN.md):** `Operational Enterprise IOMS`
**Spec label used in-screen:** "Design System Spec v2.4" / "IOMS Enterprise Design Tokens v2.4.0"
**System-states revision label:** `REV 4.2.0`

This document describes the **actual current Stitch design as generated**. Every value here was read
from the exported `code.html` / `DESIGN.md` / `screen.png` files. Nothing has been redesigned,
extended, renamed, or invented. Where the export is internally inconsistent (it is, in several
places), the inconsistency is recorded rather than resolved.

**Token authority note (added 2026-09-08 per `docs/design/DESIGN_DECISION_RECORD_B01_B09.md` §B-01):**
this document describes the Stitch export as a visual/layout reference. Its token values (color,
typography, radius, spacing) are **not canonical**. The canonical token source is
`docs/planning/ux-ui-spec.md` §1. Where a Stitch-derived value described anywhere below differs from
that source, `ux-ui-spec.md` §1 governs.

---

## 0. Export inventory (what physically exists)

31 generated folders, each with `code.html` and/or `screen.png`.

| Folder | Files | screen.png (px) | Kind |
|---|---|---|---|
| `operational_enterprise_ioms` | `DESIGN.md` only | — | Design-token + style manifest |
| `design_system_component_foundation` | code + png | 553×1600 | Design-system documentation screen |
| `authenticated_application_shell` | code + png | 780×1600 | App-shell blueprint screen |
| `system_states_inventory_order_management` | code + png | 514×1600 | 16-state catalogue screen |
| `ioms_enterprise_logo` | code (SVG) + png | 1024×1024 | Logo asset |
| `professional_corporate_headshot_photo_of_an_operations_manager_business_casual` | png only | 1024×1024 | Avatar asset |
| `login_inventory_order_management` | code + png | 1920×1027 | Screen: Login |
| `inventory_order_management_system_flow` | code only | — | **Byte-identical duplicate of the Login file** |
| `admin_dashboard_inventory_order_management` | code + png | 1552×1600 | Screen: Dashboard (Admin) |
| `sales_dashboard_inventory_order_management` | code + png | 1600×1532 | Screen: Dashboard (Sales) |
| `warehouse_staff_dashboard_inventory_order_management` | code + png | 1519×1600 | Screen: Dashboard (Warehouse) |
| `products_list_inventory_order_management` | code + png | 1562×1600 | Screen: Products list |
| `product_detail_inventory_order_management` | code + png | 1600×1580 | Screen: Product detail |
| `product_create_edit_inventory_order_management` | code + png | 1600×1375 | Screen: Product create/edit |
| `categories_inventory_order_management` | code + png | 1600×1280 | Screen: Categories |
| `warehouses_inventory_order_management` | code + png | 1600×1280 | Screen: Warehouses |
| `warehouse_stock_detail_inventory_order_management` | code + png | 1299×1600 | Screen: Warehouse stock detail |
| `suppliers_inventory_order_management` | code + png | 1920×1726 | Screen: Suppliers |
| `customers_inventory_order_management` | code + png | 1466×1600 | Screen: Customers |
| `users_inventory_order_management` | code + png | 1600×1280 | Screen: Users |
| `user_create_edit_inventory_order_management` | code + png | 1600×1280 | Screen: User create/edit |
| `purchase_orders_inventory_order_management` | code + png | 1109×1600 | Screen: Purchase Orders list |
| `purchase_order_detail_inventory_order_management` | code + png | 1443×1600 | Screen: PO detail |
| `purchase_order_create_edit_inventory_order_management` | code + png | 1920×1911 | Screen: PO create/edit |
| `goods_receipt_inventory_order_management` | code + png | 1324×1600 | Screen: Goods Receipt |
| `sales_orders_inventory_order_management` | code + png | 1600×1505 | Screen: Sales Orders list |
| `sales_order_detail_inventory_order_management` | code + png | 1249×1600 | Screen: SO detail |
| `sales_order_create_edit_inventory_order_management` | code + png | 1598×1600 | Screen: SO create/edit |
| `goods_issue_inventory_order_management` | code + png | 976×1600 | Screen: Goods Issue |
| `stock_ledger_inventory_order_management` | code + png | 1288×1600 | Screen: Stock Ledger |
| `reports_inventory_order_management` | code + png | 1600×1280 | Screen: Reports (tabular) |
| `reports_with_data_visualization_inventory_order_management` | code + png | 1123×1600 | Screen: Reports (with charts) |
| `my_profile_inventory_order_management` | code + png | 1600×1349 | Screen: My Profile |

**Only two files carry a `<title>` tag** — `login_inventory_order_management` and its duplicate
`inventory_order_management_system_flow`, both `Login - Inventory & Order Management System`.
Every other screen has **no `<title>`**.

### Two distinct generation lineages

The export contains **two different HTML dialects** and they do not share a token vocabulary:

1. **Login lineage** (`login_*`, `inventory_order_management_system_flow`) — hand-named Tailwind
   `theme.extend.colors` (`brand`, `surface`, `neutral`, `semantic`), literal hex utilities
   (`bg-[#1e3a8a]`), a `<style>` block with `.focus-ring`, and `Inter:wght@400;500;600;700` +
   `JetBrains+Mono:wght@400;500`.
2. **App lineage** (all 26 remaining screens + shell + foundation + states) — Material-3 style
   semantic token names (`primary`, `surface-container-lowest`, `on-error-container`, …), a
   `@layer base` reset, `darkMode: "class"`, and `JetBrains+Mono:wght@500` only.

`DESIGN.md` prose describes a **third** palette (slate/emerald/amber/crimson literal hex) which the
app lineage only uses sporadically, via arbitrary values and Tailwind default palette classes.
All three vocabularies are documented below in §3.

---

## 1. Screen inventory

### 1.1 Application screens (18 real product screens)

| # | Screen | Folder | Route referenced in markup |
|---|---|---|---|
| 1 | Login | `login_inventory_order_management` | `/login` (`data-path="login"` on Logout) |
| 2 | Dashboard — Admin | `admin_dashboard_inventory_order_management` | `dashboard` |
| 3 | Dashboard — Sales | `sales_dashboard_inventory_order_management` | `dashboard` |
| 4 | Dashboard — Warehouse Staff | `warehouse_staff_dashboard_inventory_order_management` | `dashboard` |
| 5 | Products (list) | `products_list_inventory_order_management` | `products` / `#products` |
| 6 | Product Detail | `product_detail_inventory_order_management` | — (breadcrumb `Products / Product Detail / SKU-IV-501`) |
| 7 | Product Create/Edit | `product_create_edit_inventory_order_management` | — |
| 8 | Categories | `categories_inventory_order_management` | `categories` / `#categories` |
| 9 | Warehouses | `warehouses_inventory_order_management` | `warehouses` / `#warehouses` |
| 10 | Warehouse Stock Detail | `warehouse_stock_detail_inventory_order_management` | — |
| 11 | Suppliers | `suppliers_inventory_order_management` | `suppliers` / `#suppliers` |
| 12 | Customers | `customers_inventory_order_management` | `customers` / `#customers` |
| 13 | Users | `users_inventory_order_management` | `users` / `#users` |
| 14 | User Create/Edit | `user_create_edit_inventory_order_management` | `/admin/users` (in an `alert()` string) |
| 15 | Purchase Orders (list) | `purchase_orders_inventory_order_management` | `purchase-orders` / `#purchase-orders` |
| 16 | Purchase Order Detail | `purchase_order_detail_inventory_order_management` | — |
| 17 | Purchase Order Create/Edit | `purchase_order_create_edit_inventory_order_management` | — |
| 18 | Goods Receipt | `goods_receipt_inventory_order_management` | — |
| 19 | Sales Orders (list) | `sales_orders_inventory_order_management` | `sales-orders` / `#sales-orders` |
| 20 | Sales Order Detail | `sales_order_detail_inventory_order_management` | — |
| 21 | Sales Order Create/Edit | `sales_order_create_edit_inventory_order_management` | — |
| 22 | Goods Issue | `goods_issue_inventory_order_management` | — |
| 23 | Stock Ledger | `stock_ledger_inventory_order_management` | `stock-ledger` / `#stock-ledger` |
| 24 | Reports (tabular) | `reports_inventory_order_management` | `reports` / `#reports` |
| 25 | Reports (with data visualization) | `reports_with_data_visualization_inventory_order_management` | `reports` |
| 26 | My Profile | `my_profile_inventory_order_management` | `my-profile` / `#profile` / `/profile` |

### 1.2 Documentation / specimen screens (not product routes)

| Screen | Folder | Purpose in the export |
|---|---|---|
| Design System & UI Component Specification | `design_system_component_foundation` | Token/component/state catalogue, 9 numbered sections |
| Authenticated Shell Blueprint & Reference Frame | `authenticated_application_shell` | Shell modes (Expanded / Collapsed Rail / Mobile Drawer 360px), role matrix, header patterns A/B |
| Common States & System State Architecture | `system_states_inventory_order_management` | 16 canonical states, 5 anatomies, screen→state map, 360px simulator |

### 1.3 Notes on the two Reports screens

Two independent Reports screens exist and they are **not** variants of one another:

- `reports_inventory_order_management` — two report types via tabs ("Stock Movement" / "Order
  Status"), IDR/Indonesian data, **no charts at all**.
- `reports_with_data_visualization_inventory_order_management` — one report type selector with three
  options, USD data, and a **three-tab visual analytics block** including an inline SVG dual-axis chart.

Both must be treated as present in the design. Neither supersedes the other in the export.

**Canonical status (added 2026-09-08 per `docs/design/DESIGN_DECISION_RECORD_B01_B09.md` §B-03/B-04,
cross-referencing `docs/planning/phase1-baseline.md` DEC-013):** the tabular Reports screen
(`reports_inventory_order_management`) is canonical for `REPORT-01`. The data-visualization variant is
**not adopted** as a required Phase 1 screen — any chart/visualization remains bonus/non-canonical only
(brief §4.4). The canonical report UI follows the approved report types and IDR currency; custom
column selection (the `Columns (9)` control on the visualization variant) remains out of `REPORT-01`
scope. No new requirement is introduced by this note.

---

## 2. Layout structure

### 2.1 Authenticated shell (applies to all 26 app-lineage screens, byte-identical markup)

```html
<body class="bg-surface font-body-sm text-body-sm text-on-surface antialiased">
  <aside class="fixed left-0 top-0 h-screen w-64 bg-surface-container-lowest z-50 flex flex-col border-r border-outline-variant/40"> … </aside>
  <div class="pl-64 min-h-screen flex flex-col">
    <header class="fixed top-0 left-64 right-0 h-14 bg-surface-container-lowest border-b border-outline-variant/40 z-40 flex items-center justify-between px-6"> … </header>
    <main class="w-full pt-14 bg-surface flex-1"> … </main>
  </div>
</body>
```

Fixed dimensions in the shell:

| Element | Value |
|---|---|
| Sidebar width | `w-64` = **256px** |
| Sidebar brand block height | `h-14` = **56px** |
| Top header height | `h-14` = **56px** |
| Content left offset | `pl-64` = **256px** |
| Content top offset | `pt-14` = **56px** |
| Sidebar z-index | `z-50` |
| Header z-index | `z-40` |
| Sticky sub-header / QA bars | `sticky top-14 z-30` |
| Modal overlays | `z-50` |
| Toasts | `z-50` |

The blueprint screen documents a **collapsed rail of 64px** (`w-16`) and an expanded rail of
`w-64`; `DESIGN.md` states the same ("64px collapsed, 240px expanded" — note the prose says 240px
while every generated file uses 256px / `w-64`). The rail is only implemented on the blueprint
screen; the 26 product screens ship the expanded 256px sidebar only.

### 2.2 Sidebar navigation (exact structure and order)

Brand block: logo `<img alt="IOMS Enterprise Logo" class="h-8 w-auto object-contain">`, then
`IOMS Core` (`font-headline-sm … font-bold tracking-tight truncate leading-tight`) over
`Inventory & Orders` (`font-label-xs … uppercase tracking-wider font-semibold`).

Scroll body: `flex-1 overflow-y-auto px-3 py-3 space-y-4`.

| Group label | Items (`data-path` → Material icon) |
|---|---|
| *(ungrouped)* | Dashboard → `dashboard` |
| `Master Data` | Products → `inventory_2`; Categories → `category`; Warehouses → `warehouse`; Suppliers → `local_shipping`; Customers → `group` |
| `Procurement` | Purchase Orders → `receipt_long` |
| `Sales` | Sales Orders → `point_of_sale` |
| `Inventory` | Stock Ledger → `format_list_numbered` |
| `Reports` | Reports → `analytics` |
| `Administration` | Users → `manage_accounts` |
| `Account` (in a bottom `p-3 border-t border-outline-variant/40 shrink-0` block) | My Profile → `person`; Logout → `logout` |

Group label style: `px-2.5 pb-1 pt-2 font-label-xs text-label-xs font-semibold text-outline uppercase tracking-wider`.

Link states:

- Idle: `flex items-center gap-2.5 px-2.5 py-1.5 rounded text-on-surface-variant font-label-md text-label-md hover:bg-surface-container hover:text-on-surface transition-colors`
- Active: `bg-primary-container text-on-primary font-semibold` plus `aria-current="page"`
  (declared once as `data-active-classes="bg-primary-container text-on-primary font-semibold"` on every `<nav>`)
- Logout: `text-error … hover:bg-error-container hover:text-on-error-container`
- Nav icons: `<span class="material-symbols-outlined text-[18px]">`

In the static export the **Dashboard link is marked active on every screen**; individual screens
re-point the active class at their own `data-path` from JS on load (Warehouses, Purchase Orders,
Sales Orders, Stock Ledger, My Profile do this explicitly).

### 2.3 Top header (exact content)

Left: breadcrumb `IOMS` `/` `Operations` (`text-on-surface-variant` / `text-outline-variant` /
`text-on-surface font-headline-sm text-headline-sm font-semibold`).
Right: `Sarah Jenkins` (`font-label-md … font-semibold leading-tight`), role pill `Admin`
(`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-secondary-container text-on-secondary-fixed border border-secondary-fixed-dim/60`),
avatar `<img alt="Profile" class="w-8 h-8 rounded-full object-cover border border-outline-variant/60">`.

Three screens mutate this header at runtime instead of shipping a role-specific variant:

| Screen | Header rewritten to |
|---|---|
| Sales dashboard | `Budi Santoso` + `Sales Rep` pill (`bg-surface-container-highest text-primary`), avatar swapped to an `images.unsplash.com` photo, breadcrumb → `IOMS / Sales / Dashboard`, sidebar `<nav>` innerHTML fully replaced by a 4-group menu (Dashboard; Master Catalog → Products; Commercial → Sales Orders; Analytics → Reports) |
| Warehouse dashboard | `Aris Setiawan` + `Warehouse Staff` pill (`bg-primary-container text-on-primary`), avatar replaced by an initials div `AS` (`bg-surface-container-high text-primary`), breadcrumb → `IOMS / Warehouse / Dashboard`, sidebar links outside allow-list `['dashboard','products','purchase-orders','sales-orders','stock-ledger','reports','my-profile','login']` hidden via `style.display='none'` |
| Warehouses | Header title span rewritten to `Master Data / Warehouses` |

### 2.4 Page content container widths (as generated, per screen)

| Screen | Content wrapper |
|---|---|
| Design system foundation | `p-6 space-y-10 max-w-[1600px] mx-auto w-full` |
| Authenticated shell | `p-6 flex flex-col gap-6` (frame `min-h-[760px]`) |
| System states | `p-6 space-y-8` |
| Admin dashboard | `p-6 space-y-6` |
| Sales dashboard | `p-6 space-y-6 max-w-7xl mx-auto w-full` |
| Warehouse dashboard | `p-6 space-y-6` |
| Products list | header `px-6 pt-5 pb-4`, toolbar `px-6 py-3`, body `<main class="p-6">` |
| Categories | `px-6 py-6 max-w-7xl w-full mx-auto space-y-6` |
| Warehouses | `w-full px-6 py-5 max-w-7xl mx-auto space-y-5` |
| Warehouse stock detail | `p-4 lg:p-6 space-y-5` |
| Suppliers | `px-4 sm:px-6 py-5 max-w-7xl w-full mx-auto space-y-5` |
| Customers | header `px-6 py-4`, body sections stacked |
| Users | `px-6` sections, toolbar + table card |
| User create/edit | `px-8 pb-12 w-full max-w-5xl` |
| Purchase Orders list | `p-6 md:p-8` |
| PO detail | `w-full px-4 sm:px-6 lg:px-8 py-5 max-w-[1600px] mx-auto space-y-5` |
| PO create/edit | `w-full max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6` |
| Goods Receipt | `px-4 sm:px-6 lg:px-8 py-6 max-w-7xl mx-auto w-full space-y-6` |
| Sales Orders list | `p-6` sections |
| SO detail | `w-full px-4 sm:px-6 py-4 sm:py-6 space-y-6 max-w-7xl mx-auto` |
| SO create/edit | `px-4 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto mt-6 space-y-6` (+ `pb-16` on parent) |
| Goods Issue | `px-4 sm:px-6 py-5 max-w-[1600px] w-full mx-auto space-y-5` |
| Stock Ledger | sections stacked, `px-6` |
| Reports (tabular) | `p-4 md:p-6 space-y-5` |
| Reports (visualization) | `p-6 space-y-6 w-full max-w-[1720px] mx-auto` |
| My Profile | `w-full max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6` |

### 2.5 Split / master-detail layouts actually present

| Screen | Grid | Ratio |
|---|---|---|
| Admin dashboard | `grid grid-cols-1 xl:grid-cols-12 gap-6 items-start` → `xl:col-span-7` (Low Stock) + `xl:col-span-5` (Pending Orders) | 7 / 5 |
| Sales dashboard | `grid grid-cols-1 lg:grid-cols-12 gap-6 items-start` → `lg:col-span-4` (Requires Attention) + `lg:col-span-8` (Recent Orders) | 4 / 8 |
| Product detail | `grid grid-cols-1 lg:grid-cols-12 gap-5 w-full items-start` → `lg:col-span-7` + `lg:col-span-5`; source comment: "60% Left / 40% Right Bento Structure" | 7 / 5 |
| Product create/edit | `grid grid-cols-1 lg:grid-cols-12 gap-6 items-start` → `lg:col-span-7` (primary data) + `lg:col-span-5` (reorder/media) | 7 / 5 |
| SO create/edit (valuation block) | `grid grid-cols-1 lg:grid-cols-12 gap-6` → `lg:col-span-7` (rules) + `lg:col-span-5` (summary) | 7 / 5 |
| Goods Issue (footer protocol) | `grid grid-cols-1 lg:grid-cols-3 gap-4` → stock preview 1 col + protocol `lg:col-span-2` | 1 / 2 |
| System states explorer | `grid grid-cols-1 xl:grid-cols-12 gap-6` → `xl:col-span-8` (preview stage, `min-h-[460px]`) + `xl:col-span-4` (spec sidebar) | 8 / 4 |
| Reports (tabular), PO create/edit summary | `grid grid-cols-1 lg:grid-cols-3` with `lg:col-span-2` disclaimer | 2 / 1 |

`DESIGN.md` prose claims master-detail is "40% order list on left, 60% inspection on right". No
generated screen implements a 40/60 split; the generated splits are 7/5, 4/8, 8/4 and 1/2 as listed.

### 2.6 Login screen layout (only non-shell screen)

```html
<body class="bg-[#f8fafc] text-[#0f172a] font-sans min-h-screen flex flex-col justify-between selection:bg-blue-100 selection:text-blue-900 antialiased">
```

- Top banner `<header class="w-full bg-white border-b border-[#e2e8f0] px-4 py-2.5 sm:px-6 shadow-sm">` with an inner `max-w-7xl mx-auto flex flex-col sm:flex-row … text-xs`, carrying the badge `IOMS AUTH GATEWAY v2.4` and the "Preview State:" switcher.
- `<main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">` → card column `w-full max-w-[420px] mx-auto`.
- Branding: 48px logo tile `inline-flex items-center justify-center w-12 h-12 rounded-lg bg-[#1e3a8a] shadow-sm mb-3.5` containing a 32px `<img class="w-8 h-8 object-contain">`; H1 `Inventory & Order Management` (`text-xl sm:text-2xl font-semibold tracking-tight`); subtitle `Sign in to manage inventory and orders`.
- Card: `bg-white border border-[#e2e8f0] rounded-md shadow-sm p-6 sm:p-7`.
- Footer `<footer class="w-full bg-white border-t border-[#e2e8f0] px-4 py-3 sm:px-6 text-center text-xs text-[#64748b]">` — `Operational Enterprise IOMS © 2025` and `Role Access Matrix: Admin • Sales • Warehouse Staff`.

---

## 3. Colors

### 3.1 App-lineage semantic tokens (`tailwind.config` — identical in all 26 app screens, and the source of truth for the shell)

| Token | Hex | Token | Hex |
|---|---|---|---|
| `surface` | `#f8f9ff` | `on-surface` | `#0b1c30` |
| `surface-dim` | `#cbdbf5` | `on-surface-variant` | `#444651` |
| `surface-bright` | `#f8f9ff` | `inverse-surface` | `#213145` |
| `surface-container-lowest` | `#ffffff` | `inverse-on-surface` | `#eaf1ff` |
| `surface-container-low` | `#eff4ff` | `outline` | `#757682` |
| `surface-container` | `#e5eeff` | `outline-variant` | `#c5c5d3` |
| `surface-container-high` | `#dce9ff` | `surface-tint` | `#4059aa` |
| `surface-container-highest` | `#d3e4fe` | `surface-variant` | `#d3e4fe` |
| `primary` | `#00236f` | `on-primary` | `#ffffff` |
| `primary-container` | `#1e3a8a` | `on-primary-container` | `#90a8ff` |
| `inverse-primary` | `#b6c4ff` | `primary-fixed` | `#dce1ff` |
| `primary-fixed-dim` | `#b6c4ff` | `on-primary-fixed` | `#00164e` |
| `on-primary-fixed-variant` | `#264191` | | |
| `secondary` | `#565e74` | `on-secondary` | `#ffffff` |
| `secondary-container` | `#dae2fd` | `on-secondary-container` | `#5c647a` |
| `secondary-fixed` | `#dae2fd` | `secondary-fixed-dim` | `#bec6e0` |
| `on-secondary-fixed` | `#131b2e` | `on-secondary-fixed-variant` | `#3f465c` |
| `tertiary` | `#002d48` | `on-tertiary` | `#ffffff` |
| `tertiary-container` | `#004469` | `on-tertiary-container` | `#56b3f9` |
| `tertiary-fixed` | `#cce5ff` | `tertiary-fixed-dim` | `#93ccff` |
| `on-tertiary-fixed` | `#001d31` | `on-tertiary-fixed-variant` | `#004b73` |
| `error` | `#ba1a1a` | `on-error` | `#ffffff` |
| `error-container` | `#ffdad6` | `on-error-container` | `#93000a` |
| `background` | `#f8f9ff` | `on-background` | `#0b1c30` |

`darkMode: "class"` is declared in the config but **no screen contains a single `dark:` utility** —
there is no dark theme in this export.

### 3.2 Login-lineage named colors (`login_*` only)

```js
brand:   { DEFAULT:'#1e3a8a', dark:'#00236f', hover:'#2563eb', light:'#eff4ff', subtle:'#dbeafe' }
surface: { page:'#f8fafc', card:'#ffffff', border:'#e2e8f0', input:'#ffffff' }
neutral: { text:'#0f172a', secondary:'#475569', muted:'#64748b', border:'#cbd5e1' }
semantic:{ success:'#059669', 'success-bg':'#ecfdf5', 'success-border':'#a7f3d0',
           warning:'#d97706', 'warning-bg':'#fffbeb', 'warning-border':'#fde68a',
           danger:'#dc2626',  'danger-bg':'#fef2f2',  'danger-border':'#fecaca',
           info:'#0284c7',    'info-bg':'#f0f9ff',    'info-border':'#bae6fd' }
```

### 3.3 `DESIGN.md` prose palette (used by the design-system foundation screen and by literal-hex utilities elsewhere)

Brand / accent swatches, with the contrast ratios the foundation screen prints next to each:

| Swatch label | Hex | Printed ratio |
|---|---|---|
| Primary Base | `#00236f` | 7.8:1 |
| Primary Container | `#1e3a8a` | 8.1:1 |
| Action Hover | `#2563eb` | 4.6:1 |
| Primary Fixed | `#dce1ff` | 14.2:1 |
| Selected Row / Tint | `#eff6ff` | 18.1:1 |
| On Surface / Text | `#0b1c30` | 16.9:1 |

Semantic scale (base / tint / border / text-on-tint, exactly as rendered):

| Semantic | Base | Tint surface | Border | Text on tint | Foundation note |
|---|---|---|---|---|---|
| Success | `#059669` | `#ecfdf5` | `#a7f3d0` | `#065f46` | "Approved, Completed, Received, Optimal Stock. Pass ratio > 5.2:1." |
| Warning | `#d97706` | `#fffbeb` | `#fde68a` | `#92400e` | "Low Stock Warning, Pending Approval, Partially Received. WCAG pass." |
| Danger / Error | `#dc2626` | `#fef2f2` | `#fecaca` | `#991b1b` | "Cancelled, Out-of-stock Critical, Destructive Action, Validation Errors." |
| Info | `#0284c7` | `#f0f9ff` | `#bae6fd` | `#0369a1` | "Draft, Processing, In-Transit, System Alerts. Neutral blue baseline." |

Neutral slate scale printed as a 10-swatch strip (`grid grid-cols-5 md:grid-cols-10 gap-2`), labelled
`Slate 50 → 900`:

`50 #f8fafc` · `100 #f1f5f9` · `200 #e2e8f0` · `300 #cbd5e1` · `400 #94a3b8` · `500 #64748b` ·
`600 #475569` · `700 #334155` · `800 #1e293b` · `900 #0f172a`

`DESIGN.md` additionally assigns roles: canvas/shell `#f1f5f9`; data surface `#ffffff`; subtle
surface / table header `#f8fafc`; control & input borders `#cbd5e1`; table dividers `#e2e8f0`;
text primary `#0f172a`; text secondary `#475569`; text muted `#64748b`.

### 3.4 Contrast ratios printed on the system-states screen

| Token | Printed ratio |
|---|---|
| `primary (#00236f)` | 12.8:1 • PASS AAA |
| `error (#ba1a1a)` | 5.4:1 • PASS AA |
| `secondary (#565e74)` | 6.2:1 • PASS AA |
| `surface-container-high` | "Background Separation" (no ratio) |

The state-explorer sidebar prints `WCAG 2.1 Contrast: AA Compliant (4.8:1)`; the foundation screen
header prints `WCAG AA Certified (≥4.5:1)`; the shell blueprint asserts a 4.5:1 floor for all body
copy and interactive components.

### 3.5 Tailwind-default palette classes used directly (not tokens)

Several screens bypass the token set and use stock Tailwind classes. These are the ones actually present:

- **Emerald** — `emerald-50`, `emerald-100`, `emerald-200`, `emerald-300`, `emerald-400`,
  `emerald-500`, `emerald-600`, `emerald-700`, `emerald-800`, `emerald-900`
  (active/Ready/Normal/Sufficient badges, success pips, positive quantities)
- **Amber** — `amber-50`, `amber-100`, `amber-200`, `amber-300`, `amber-400`, `amber-500`,
  `amber-600`, `amber-700`, `amber-800`, `amber-900` (low stock, near capacity, stale, slow moving)
- **Red / Rose** — `red-100`, `red-300`, `red-600`, `red-700`, `red-800`, `rose-50`, `rose-200`,
  `rose-800`, `rose-900` (insufficient stock, shortage, PO create/edit validation banner)
- **Sky** — `sky-50`, `sky-100`, `sky-200`, `sky-300`, `sky-600`, `sky-700`, `sky-800`, `sky-900`
  (Ordered / In-Transit / info banners)
- **Slate** — `slate-50`, `slate-100`, `slate-200`, `slate-300`, `slate-500`, `slate-600`,
  `slate-700`, `slate-900`, `slate-950/50` (ledger, PO create/edit dark QA ribbon, neutral badges)
- **Indigo / Teal / Blue** — `indigo-50/200/800`, `teal-50/700/800`, `blue-100/300/600/700/900`
  (Users role badges, Goods Issue fulfilled badge)
- **Arbitrary hex utilities** — `bg-[#0ea5e9]`, `text-[#0284c7]`, `bg-[#ecfdf5]`, `text-[#065f46]`,
  `border-[#a7f3d0]`, `bg-[#fffbeb]`, `text-[#92400e]`, `border-[#fde68a]`, `bg-[#fef2f2]`,
  `text-[#991b1b]`, `border-[#fecaca]`, `bg-[#f0f9ff]`, `text-[#0369a1]`, `border-[#bae6fd]`,
  `bg-[#f1f5f9]`, `text-[#475569]`, `border-[#cbd5e1]`, `bg-[#f0fdf4]`, `text-[#15803d]`,
  `border-[#bbf7d0]`, `bg-[#dc2626]`, `bg-[#fffbeb]/20`

### 3.6 Alpha-modified token usage (recurring exact strings)

`border-outline-variant/40` (shell borders, card borders) · `border-outline-variant/30`
(inner dividers) · `border-outline-variant/60` (controls, avatars) · `border-outline-variant/80`
(inputs on the foundation screen) · `divide-outline-variant/30` · `divide-outline-variant/20` ·
`bg-inverse-surface/40` (modal scrim) · `bg-on-surface/40` (modal scrim, alternate) ·
`bg-slate-900/60`, `bg-slate-950/50` (ledger / warehouse-detail scrims) ·
`bg-error-container/40`, `/60`, `/20` · `border-error/30`, `/40`, `/20` · `bg-primary-container/80`
(disabled loading button) · `bg-primary-fixed/70`, `/10`, `/5` · `hover:bg-primary/90` ·
`bg-surface-container-low/40`, `/50`, `/60`, `/70`, `/30`.

---

## 4. Typography

### 4.1 Fonts loaded

App lineage:
```html
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
```
(Material Symbols Outlined is requested twice, with two different axis specs.)

Login lineage:
```html
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```
with `fontFamily: { sans: ['Inter','system-ui','-apple-system','sans-serif'], mono: ['JetBrains Mono','monospace'] }`
and `body { font-feature-settings: 'cv02','cv03','cv04','cv11'; -webkit-font-smoothing: antialiased; }`.

### 4.2 Type scale (`fontSize` tokens — identical in `DESIGN.md` and every app-lineage config)

| Token | Family | Size | Line height | Weight | Letter spacing |
|---|---|---|---|---|---|
| `headline-xl` | Inter | 24px | 32px | 600 | −0.02em |
| `headline-lg` | Inter | 20px | 28px | 600 | −0.015em |
| `headline-md` | Inter | 16px | 24px | 600 | −0.01em |
| `headline-sm` | Inter | 14px | 20px | 600 | −0.005em |
| `body-md` | Inter | 14px | 20px | 400 | — |
| `body-sm` | Inter | 13px | 18px | 400 | — |
| `body-xs` | Inter | 12px | 16px | 400 | — |
| `label-md` | Inter | 13px | 18px | 500 | — |
| `label-sm` | Inter | 12px | 16px | 500 | — |
| `label-xs` | Inter | 11px | 14px | 600 | +0.04em |
| `mono-data-md` | JetBrains Mono | 13px | 18px | 500 | — |
| `mono-data-sm` | JetBrains Mono | 12px | 16px | 500 | — |

Each token exists as **both** a `fontFamily` and a `fontSize` key, so the generated markup always
pairs them: `class="font-body-sm text-body-sm"`, `class="font-headline-xl text-headline-xl"`,
`class="font-mono-data-sm text-mono-data-sm"`. Body default is `font-body-sm text-body-sm` on `<body>`.

### 4.3 Type usage rules as generated

- **Page H1** — `font-headline-xl text-headline-xl text-on-surface` + `font-bold tracking-tight`
  (most screens) or `font-semibold tracking-tight` (Products list, Sales dashboard, Categories).
  Both variants exist in the export.
- **Section H2** — `font-headline-lg text-headline-lg … font-bold` or `font-semibold`.
- **Card / panel heading** — `font-headline-md text-headline-md` or `font-headline-sm text-headline-sm … font-semibold/font-bold`.
- **Table column headers** — `font-label-xs text-label-xs uppercase tracking-wider` + `font-semibold`,
  colored `text-outline`, `text-on-surface-variant`, or `text-secondary` depending on screen.
- **Monospace is mandated for**: SKU codes, PO/SO/LED reference numbers, quantities, prices,
  valuations, timestamps in metadata rows, facility codes, trace IDs, checksums, version strings,
  page-number buttons, and percentage readouts. Class pair: `font-mono-data-sm text-mono-data-sm`
  or `font-mono-data-md text-mono-data-md`.
- **Off-scale sizes actually used** (arbitrary values, not tokens): `text-[10px]` (role pill in the
  shell header, mobile status bar, archived pill, code tags), `text-[11px]` (badge text on most
  status pills, security notes), `text-[12px]`, `text-[13px]` (status toggle buttons, sticky footer
  buttons on Product create/edit), `text-xs` (login lineage throughout), `text-xl sm:text-2xl`
  (login H1), `text-[20px]` / `text-[22px]` / `text-[40px]` (Product detail price and stock figures),
  `text-4xl` (`filter_alt_off` empty icon on Stock Ledger), `text-[36px]` / `text-[32px]` /
  `text-[28px]` / `text-[24px]` (empty-state icons).
- **Restraint rule from `DESIGN.md`**: "Display sizes above 24px are eliminated." The generated
  screens break this in three places — Product detail (`text-[40px]` aggregate stock,
  `text-[22px]` reorder point, `text-[20px]` prices) — recorded here as-is.
- **All-caps** is used only for column headers (`label-xs`, +0.04em) and compact status badges.

### 4.4 Icon typography

Material Symbols Outlined, always as `<span class="material-symbols-outlined text-[N]px">name</span>`.
Sizes present: `text-[12px]`, `text-[13px]`, `text-[14px]`, `text-[16px]`, `text-[17px]`,
`text-[18px]` (default for nav and inline actions), `text-[20px]`, `text-[22px]`, `text-[24px]`,
`text-[28px]`, `text-[32px]`, `text-[36px]`, plus `text-4xl`.
Filled variants are produced with inline `style="font-variation-settings: 'FILL' 1;"` (shell active
dashboard icon, admin-dashboard warning icon, Products-list toast check, Goods-Receipt
`check_circle` and `task_alt`).

The **login screen uses no icon font at all** — every icon there is a hand-written inline `<svg>`
(20×20 or 24×24 viewBox, `fill="currentColor"` or `stroke-width="2"`).

---

## 5. Spacing

### 5.1 Spacing tokens (`theme.extend.spacing`, identical in `DESIGN.md` and every app config)

| Token | Value | px |
|---|---|---|
| `space-2xs` | 0.125rem | 2 |
| `space-xs` | 0.25rem | 4 |
| `space-sm` | 0.5rem | 8 |
| `space-md` | 0.75rem | 12 |
| `space-lg` | 1rem | 16 |
| `space-xl` | 1.25rem | 20 |
| `space-2xl` | 1.5rem | 24 |
| `space-3xl` | 2rem | 32 |
| `table-row-h-compact` | 2rem | 32 |
| `table-row-h-regular` | 2.5rem | 40 |
| `input-h-compact` | 1.875rem | 30 |
| `input-h-regular` | 2.25rem | 36 |

Only `input-h-regular` is referenced by a generated utility (`h-input-h-regular`, on the
User create/edit form). The other spacing tokens exist in config but the markup uses the standard
Tailwind numeric scale throughout.

### 5.2 Spacing scale documented on the foundation screen

The 8-point grid strip renders six rows with inline `style="width: Npx"` bars:

| Printed px | Label |
|---|---|
| 4px | `space-xs (micro pads)` |
| 8px | `space-sm (button gaps)` |
| 12px | `space-md (card padding)` |
| 16px | `space-lg (standard gutter)` |
| 24px | `space-2xl (section gap)` |
| 32px | `space-3xl (view container)` |

Printed rule: *"Multiples of 8px required for outer section gutters; 4px reserved exclusively for
tight internal component alignments."* `DESIGN.md` states the same, adding that 2px sub-increments
are reserved for internal component padding, borders, and high-density tabular alignment.

### 5.3 Spacing values actually used

| Purpose | Classes present |
|---|---|
| Page gutter | `p-6` (24px) dominant; `p-4 lg:p-6`, `px-4 sm:px-6 lg:px-8`, `px-6 py-5`, `px-8` |
| Section vertical rhythm | `space-y-4`, `space-y-5`, `space-y-6` (most common), `space-y-8`, `space-y-10` (foundation), `gap-4`, `gap-5`, `gap-6` |
| Card padding | `p-3`, `p-3.5`, `p-4` (most common), `p-5`, `p-5 sm:p-6`, `p-6`, `p-8` (empty states), `p-12` (blank/403/404 states) |
| Toolbar padding | `p-2.5`, `p-3`, `p-3.5`, `p-4`, `px-4 py-2`, `px-4 py-2.5`, `px-6 py-3` |
| Table cell padding | `px-3` / `py-2`, `px-3.5 py-2.5`, `px-4 py-2`, `px-4 py-2.5`, `py-2.5 px-4`, `py-3 px-4`, `py-3.5 px-4`, `pl-4 pr-3` (first col), `pl-3 pr-4` (last col) |
| Inline gaps | `gap-1`, `gap-1.5` (icon+label, most common), `gap-2`, `gap-2.5` (nav item), `gap-3`, `gap-4`, `gap-6` |
| Badge padding | `px-1.5 py-0.5`, `px-2 py-0.5` (most common), `px-2.5 py-0.5`, `px-2.5 py-1`, `px-2 py-1` |
| Button padding | `px-2 py-1`, `px-2.5 py-1`, `px-3 py-1.5`, `px-3.5 py-1.5`, `px-3` / `px-3.5` / `px-4` / `px-6` with fixed `h-` |
| Micro offsets | `mt-0.5`, `mb-1`, `pt-0.5`, `pt-1`, `pt-2`, `pb-1`, `pb-2` |
| Negative bleed (sticky footers) | `-mx-4 sm:-mx-6 lg:-mx-8` (Product create/edit), `-mx-4 sm:-mx-6 lg:-mx-8` (SO create/edit) |

### 5.4 Fixed control heights

| Control | Height |
|---|---|
| Button, default | `h-8` = 32px (`px-3`, 13px label) — the documented default |
| Button, compact | `h-7` = 28px (`px-2.5`, 12px label) — documented compact |
| Button, comfortable | `h-9` = 36px (PO list "Create Purchase Order", Warehouses search, login controls) |
| Button, mobile / loading-stable | `h-10` = 40px (ACTION_LOADING spec, mobile dialog primary) |
| Button, mobile tap target | `h-11` = 44px (360px simulator, Goods Receipt mobile ± buttons `h-11 w-11`) |
| Icon-only button | `h-8 w-8` (32×32), `p-1`, `p-0.5` |
| Text input / select, default | `h-8` = 32px |
| Text input, comfortable | `h-9` = 36px |
| Pagination button | `h-7` (`h-7 w-7` for page numbers, `h-7 px-2` for prev/next), `h-6` for the rows-per-page select |
| Table header row | `h-8` = 32px |
| Table body row | `h-9` = 36px (foundation, reports, ledger `h-9`), also `h-10`, `h-11`, `h-12` on dashboards/users |
| Checkbox | `w-4 h-4` = 16px |
| Status pip | `w-1.5 h-1.5` = 6px (in badges), `w-2 h-2` = 8px (standalone), `w-3 h-3` = 12px (foundation swatch) |
| Avatar | `w-8 h-8` (header), `w-14 h-14` (profile hero, `rounded` not `rounded-full`) |
| Progress bar | `h-1`, `h-1.5`, `h-2`, `h-2.5`, `h-4` (stacked master bar) |

---

## 6. Borders

### 6.1 Border strategy as generated

`DESIGN.md` mandates 1px solid borders and background value shifts instead of shadows. The
generated markup uses **three different border strategies across the export**:

1. **Explicit token borders** (Products list, Suppliers, Customers, Users, Warehouses, Categories,
   Stock Ledger, Goods Issue, Goods Receipt, Reports, Dashboards-warehouse):
   `border border-outline-variant/60`, `border border-outline-variant/40`, `border border-outline`,
   `border border-outline-variant/50`, `border border-outline-variant`.
2. **Shadow-only cards, no border** (Authenticated shell, Sales dashboard, SO detail,
   Reports-with-visualization, System states, My Profile):
   `bg-surface-container-lowest rounded shadow-sm` with no `border` utility at all.
3. **Literal-hex borders** (Login lineage, foundation-screen swatches):
   `border border-[#e2e8f0]`, `border border-[#cbd5e1]`, `border border-[#fecaca]`, etc.

All three are present in the export; a future implementation should expect to reconcile them.

### 6.2 Border widths present

| Width | Where |
|---|---|
| `border` (1px) | Default everywhere |
| `border-2` (2px) | Focus state on the foundation-screen "Unit Cost" input: `border-2 border-primary` |
| `border-t`, `border-b`, `border-r`, `border-l` (1px, single edge) | Header `border-b`, sidebar `border-r`, footers `border-t`, table `border-b border-outline-variant/60` |
| `border-l-4` | Warehouse-stock-detail governance callout: `border-l-4 border-primary`; PO-detail audit banner `border-l-4 border-l-primary-container` |
| `divide-y` / `divide-x` | Table bodies: `divide-y divide-outline-variant/30`, `divide-y divide-surface-container`, `divide-y divide-outline-variant/20`, `divide-y divide-surface-container-low`, `divide-y divide-outline-variant/40` |
| `h-[1px]` | Collapsed-rail dividers on the shell blueprint: `w-8 h-[1px] bg-surface-container` |
| `border-none` | Warehouses native `<dialog>` elements |

`DESIGN.md` additionally specifies: 1.5px checkbox border (`1.5px solid #64748b`, radius 2px);
zero-radius edges on internal table cells and segmented mid-sections; and the design-system rule
"Every container, fieldset, table cell header, and control edge utilizes crisp boundaries."

---

## 7. Radii

### 7.1 `borderRadius` tokens — the two configs disagree

**App-lineage `tailwind.config` (all 26 app screens):**

| Class | Value |
|---|---|
| `rounded` (DEFAULT) | `0.125rem` = **2px** |
| `rounded-lg` | `0.25rem` = **4px** |
| `rounded-xl` | `0.5rem` = **8px** |
| `rounded-full` | `0.75rem` = **12px** |

**`DESIGN.md` front-matter `rounded` block:**

| Key | Value |
|---|---|
| `sm` | 0.125rem = 2px |
| `DEFAULT` | 0.25rem = 4px |
| `md` | 0.375rem = 6px |
| `lg` | 0.5rem = 8px |
| `xl` | 0.75rem = 12px |
| `full` | 9999px |

Consequence, and this is important for implementation: in the generated app screens
**`rounded-full` is 12px, not a pill**, and `rounded` is 2px, not 4px. So every
`rounded-full` status badge and `w-1.5 h-1.5 rounded-full` pip in the app lineage renders as a
small-radius rectangle, not a circle — while `w-8 h-8 rounded-full` avatars render as 12px-radius
squares. The login lineage uses stock Tailwind radii, where `rounded-full` is a true pill.

### 7.2 Radius classes actually used

| Class | Effective (app lineage) | Usage |
|---|---|---|
| `rounded` | 2px | Buttons, inputs, selects, badges, nav links, most cards, toasts, table wrappers |
| `rounded-sm` | Tailwind default 2px (not overridden) | Foundation chart legend squares `w-3 h-3 rounded-sm` |
| `rounded-md` | Tailwind default 6px (not overridden) | Login card, Suppliers modal panel, Categories modal panel |
| `rounded-lg` | 4px | Panel cards on shell/system-states/reports-viz, KPI tiles, some tables |
| `rounded-xl` | 8px | System-states section cards, Goods-Issue/SO-detail modals, PO-list KPI tiles, empty-state cards, Product-create/edit section cards |
| `rounded-2xl` | Tailwind default 16px (not overridden) | The 360px device frame on the system-states simulator |
| `rounded-full` | 12px | Status badge pills, pips, avatars, empty-state icon circles, progress-bar tracks and fills |
| `rounded-r` | Tailwind default | Warehouse-stock-detail governance callout (right corners only) |

`DESIGN.md` prose rules, verbatim: default radius 4px for form inputs / buttons / segmented
controls / badges; card and panel radius 6px or 8px maximum; sharp 0px on internal table cells,
segmented mid-sections, and connected input addons; *"Full roundedness / pill shapes are
prohibited, except for micro indicator pips (`w-2 h-2 rounded-full`)."*

---

## 8. Shadows and elevation

### 8.1 `DESIGN.md` depth hierarchy (4 levels)

| Level | Surface | Border | Shadow |
|---|---|---|---|
| 0 — App shell / canvas | `#f1f5f9` | — | none |
| 1 — Data cards, tables, toolbars | `#ffffff` | 1px `#cbd5e1` | **zero box-shadow** |
| 2 — Dropdowns, autocomplete, popovers | `#ffffff` | 1px `#cbd5e1` | `0 4px 6px -1px rgba(15,23,42,0.08), 0 2px 4px -2px rgba(15,23,42,0.05)` |
| 3 — Modals, confirmation dialogs, slide-overs | `#ffffff` | 1px `#94a3b8` | `0 20px 25px -5px rgba(15,23,42,0.12), 0 8px 10px -6px rgba(15,23,42,0.08)`; backdrop `rgba(15,23,42,0.4)` |

### 8.2 Shadow classes actually used

| Class | Where in the export |
|---|---|
| `shadow-xs` | Products-list header buttons, Warehouses cards/toolbar, Suppliers/Warehouses toast rows |
| `shadow-sm` | The overwhelming default: cards, KPI tiles, toolbars, buttons, sticky sub-headers, login card and banner |
| `shadow-inner` | System-states preview stage; Product-create/edit form inputs (`shadow-inner`) |
| `shadow-md` | Foundation toast, Product-create/edit submit button, 360px mobile dialog card |
| `shadow-lg` | Shell user dropdown, My Profile toast, Warehouses/Suppliers toasts |
| `shadow-xl` | Categories modal, Suppliers form modal, System-states CONFIRMATION card, SO-detail modals, Customers modal, 360px device frame |
| `shadow-2xl` | Shell mobile drawer panel, Warehouses deactivate modal |
| Backdrop scrims | `bg-inverse-surface/40`, `bg-inverse-surface/40 backdrop-blur-sm`, `bg-inverse-surface/40 backdrop-blur-xs`, `bg-inverse-surface/60 backdrop-blur-sm`, `bg-on-surface/40 backdrop-blur-[1px]`, `bg-on-surface/40 backdrop-blur-sm`, `bg-slate-900/60 backdrop-blur-xs`, `bg-slate-950/50 backdrop-blur-sm`, `bg-error-container` (no scrim, inline) |

Level 1 in `DESIGN.md` says "zero box-shadow", but the generated cards almost universally carry
`shadow-sm`. Recorded as an internal inconsistency; the generated markup is `shadow-sm`.

### 8.3 Focus rings

| Pattern | Where |
|---|---|
| `focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary` | Foundation inputs, warehouse-stock-detail search, My Profile inputs |
| `focus:outline-hidden focus:border-primary-container focus:ring-1 focus:ring-primary-container` | Products list search, Suppliers search |
| `focus:outline-none focus:ring-2 focus:ring-primary` | Reports filter controls, User create/edit inputs |
| `focus:outline-none focus:ring-2 focus:ring-primary-container` | Shell avatar trigger |
| `focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1` | Suppliers "Add Supplier" |
| `focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb]` | Login inputs |
| `focus:outline-none focus:ring-2 focus:ring-[#2563eb] focus:ring-offset-1` | Login submit |
| `.focus-ring:focus-within, .focus-ring:focus { outline: 2px solid #2563eb; outline-offset: 1px; }` | Login `<style>` block (declared; class not applied to any element) |
| `focus:bg-surface-container-lowest` / `focus:bg-surface-container-low` | Toolbar search inputs (background-shift focus instead of a ring) |

Foundation screen prints: **"Focus Ring: 2px solid primary, 0px offset"**. `DESIGN.md` prints
`2px solid #1e3a8a` with `0px` ring-offset for inputs and `2px solid #ffffff` + `2px solid #2563eb`
for primary buttons.

---

## 9. Component inventory

Every component below exists in the export. Classes are quoted from the generated markup.

### 9.1 Buttons

| Variant | Exact classes (representative) |
|---|---|
| Primary (`primary`) | `h-8 px-3 rounded bg-primary text-on-primary font-label-md text-label-md font-semibold hover:bg-primary/90 active:bg-primary transition-colors flex items-center gap-1.5 shadow-sm` |
| Primary (`primary-container`) | `h-8 px-3.5 rounded bg-primary-container text-on-primary font-label-md text-label-md font-semibold hover:bg-primary transition-colors flex items-center gap-1.5 shadow-sm` |
| Secondary / default | `h-8 px-3 rounded bg-surface-container-lowest text-on-surface border border-outline-variant/60 font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center gap-1.5 shadow-sm` |
| Tonal / neutral | `h-8 px-3 rounded bg-surface-container-low hover:bg-surface-container text-on-surface-variant hover:text-on-surface font-label-md text-label-md flex items-center gap-1 transition-colors border border-outline-variant/40` |
| Destructive | `h-8 px-3 rounded bg-error text-on-error font-label-md text-label-md font-semibold hover:bg-error/90 transition-colors flex items-center gap-1.5 shadow-sm` |
| Destructive secondary | `h-8 px-3 rounded bg-surface-container-lowest text-error border border-error/40 font-label-md text-label-md hover:bg-error-container/40 transition-colors flex items-center gap-1.5 shadow-sm` |
| Disabled | `h-8 px-3 rounded bg-surface-container text-outline font-label-md text-label-md cursor-not-allowed border border-outline-variant/30 flex items-center gap-1` + `disabled` |
| Loading (icon spin) | `h-8 px-3 rounded bg-primary text-on-primary … flex items-center gap-2 shadow-sm` with `<span class="material-symbols-outlined text-[16px] animate-spin">refresh</span>` |
| Loading (SVG spinner, height-locked) | `h-10 px-4 bg-primary-container/80 text-on-primary font-label-md text-label-md rounded flex items-center gap-2.5 cursor-not-allowed` + `disabled` + inline `<svg class="animate-spin h-4 w-4">` |
| Icon-only | `h-8 w-8 rounded bg-surface-container-lowest border border-outline-variant/60 hover:bg-surface-container-low flex items-center justify-center text-on-surface shadow-sm` + `title` |
| Row action, text | `text-primary hover:underline px-1 py-0.5` / `text-error hover:underline px-1 py-0.5` |
| Row action, icon | `p-1 hover:bg-surface-container rounded text-on-surface` + `title` |
| Link-style | `text-on-surface-variant hover:text-primary font-label-xs text-label-xs underline underline-offset-4 px-2 py-1 transition-colors` |
| Mobile full-width | `w-full h-11 bg-primary-container text-on-primary font-label-sm text-label-sm rounded font-semibold flex items-center justify-center gap-1.5 shadow-sm` |

Login-lineage primary submit:
`w-full h-9 px-4 rounded bg-[#1e3a8a] hover:bg-[#2563eb] active:bg-[#00236f] text-white font-medium text-xs tracking-wide transition-colors flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#2563eb] focus:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed shadow-xs`

`DESIGN.md` button spec: Primary bg `#1e3a8a` / text `#ffffff` / border `1px solid #172554`,
hover `#1e40af`, active `#1e3a8a`. Secondary bg `#ffffff` / text `#0f172a` / border `1px solid #cbd5e1`,
hover `#f8fafc` with border `#94a3b8`, active `#f1f5f9`. Destructive bg `#dc2626` / border `#b91c1c`,
hover `#b91c1c`. Destructive-secondary bg `#ffffff` / text `#dc2626` / border `1px solid #fca5a5`,
hover `#fef2f2`.

### 9.2 Form controls

| Control | Exact classes |
|---|---|
| Text input (default) | `w-full h-8 px-2.5 rounded bg-surface-container-lowest border border-outline-variant/80 text-on-surface font-mono-data-sm text-mono-data-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-sm` |
| Text input (soft, no border) | `w-full h-9 px-3 text-body-sm font-body-sm rounded bg-surface-container-lowest text-on-surface shadow-inner focus:outline-none focus:bg-surface-container-low transition-all` |
| Input, focus specimen | `border-2 border-primary` (foundation "Unit Cost", 2px) |
| Input, error | `border border-error text-error … focus:ring-1 focus:ring-error` (+ `bg-error-container/20` or `bg-error-container/30` on some screens; login uses `bg-[#fef2f2]/30`) |
| Input, disabled / readonly | `bg-surface-container text-outline font-mono-data-sm text-mono-data-sm border border-outline-variant/40 cursor-not-allowed` + `disabled`; readonly variant `bg-surface-container-low text-on-surface cursor-not-allowed` + `readonly` + a `Read-Only` / `READONLY` pill |
| Prefix addon | `<span class="absolute left-2.5 top-1/2 -translate-y-1/2 font-mono-data-sm text-mono-data-sm text-outline">$</span>` with input `pl-6` (or `Rp` + `pl-9`) |
| Suffix addon | unit badge absolutely positioned right, input `pr-12` (`PCS`), or `pcs` label after the field |
| Select | `w-full h-8 px-2.5 rounded bg-surface-container-lowest border border-outline-variant/60 font-body-sm text-body-sm text-on-surface focus:outline-none focus:border-primary`; several screens add `appearance-none pr-8` + an absolutely-positioned `expand_more` / `calendar_month` / `warehouse` / `category` / `arrow_drop_down` icon |
| Textarea | `rows="2"` or `rows="3"`, same surface classes; Categories textarea has `maxlength="255"` with a live `0 / 255` counter |
| Date input | `type="date"` with `font-mono-data-sm`; defaults `2024-10-01` / `2024-10-24` / `2024-10-25` |
| Number input | `type="number"` with `min`, `max`, `step` (steps present: `1`, `50`, `100`, `500`, `1000`), `text-right`, `font-mono-data-sm` |
| Checkbox | `w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary accent-primary` |
| Radio | `text-primary focus:ring-primary accent-primary` with `name` groups; also rendered as selectable cards: `relative flex items-center gap-2.5 p-2.5 rounded bg-surface hover:bg-surface-container-low cursor-pointer transition-colors` |
| Password field | `type="password"` + `pr-9` + eye toggle button, icon `visibility` ⇄ `visibility_off` (User create/edit); login uses `pr-10` and inline SVG eye / eye-slash |
| Search input | `w-full h-8 pl-8 pr-3 rounded bg-surface-container-low border border-outline-variant/60 font-body-sm text-body-sm text-on-surface placeholder:text-outline focus:bg-surface-container-lowest focus:outline-none focus:border-primary` + absolute `search` icon at `left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-outline` |
| Label | `block font-label-sm text-label-sm text-on-surface font-medium` (or `font-semibold`), required marker `<span class="text-error font-bold">*</span>` (variants: `<span class="text-error">*</span>`, and login `<span class="text-[#dc2626]" aria-hidden="true">*</span>`) |
| Helper text | `block font-body-xs text-body-xs text-on-surface-variant` |
| Inline field error | `flex items-center gap-1 text-error font-body-xs text-body-xs` + `<span class="material-symbols-outlined text-[14px]">error</span>` |

Documented dimensions: input default height 32px; `DESIGN.md` adds "Floating labels are prohibited"
and "Labels always visible, positioned above input (`label-sm`, `#334155`)".

### 9.3 Segmented controls / tabs

| Instance | Container | Active class | Inactive class |
|---|---|---|---|
| Reports report-type tabs | `bg-surface-container p-1 rounded-lg w-fit` | `flex items-center gap-2 px-3.5 py-1.5 rounded font-label-md text-label-md bg-surface-container-lowest text-primary font-semibold shadow-sm transition-all` | `… text-on-surface-variant hover:text-on-surface font-medium transition-all` |
| Reports-viz visualization tabs | `bg-surface-container p-1 rounded flex items-center gap-1` `role="tablist"` | `px-2.5 py-1 rounded font-label-xs text-label-xs font-semibold bg-surface-container-lowest text-primary shadow-sm` | `px-2.5 py-1 rounded font-label-xs text-label-xs font-medium text-on-surface-variant hover:text-on-surface` |
| Product-create/edit status toggle | `grid grid-cols-2 gap-2 bg-surface-container-low p-1 rounded-lg` | `flex items-center justify-center gap-1.5 py-1.5 text-[12px] font-semibold rounded bg-surface-container-lowest text-primary shadow-sm transition-all` | `text-outline hover:text-on-surface transition-all` |
| Suppliers status pills | `flex items-center gap-1` | `bg-surface-container-lowest text-on-surface shadow-xs` | `text-on-surface-variant hover:text-on-surface` |
| Customers filter pills | inline | `bg-primary-container text-on-primary font-semibold` | `bg-surface-container-low text-on-surface font-medium hover:bg-surface-container` |
| Admin-dashboard order tabs | inline | `.order-tab` active styling set in JS | — |
| System-states state pills | `flex items-center gap-1.5 min-w-max p-1 bg-surface-container-low rounded-lg` | `bg-primary-container text-on-primary shadow-sm` | `text-on-surface-variant hover:bg-surface-container` |

### 9.4 Data table (canonical, from the foundation screen)

```html
<div class="bg-surface-container-lowest rounded border border-outline-variant/40 shadow-sm overflow-hidden flex flex-col">
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse">
      <thead>
        <tr class="h-8 bg-surface-container-low border-b border-outline-variant/60 font-label-xs text-label-xs text-outline uppercase tracking-wider">
          <th class="w-10 px-3 text-center"><input type="checkbox" class="rounded border-outline-variant text-primary accent-primary"></th>
          <th class="px-3 font-semibold cursor-pointer hover:text-on-surface" onclick="sortTable(1)">
            <div class="flex items-center gap-1"><span>SKU Code</span>
              <span class="material-symbols-outlined text-[14px]">unfold_more</span></div>
          </th>
          …
          <th class="px-3 font-semibold text-right">On Hand</th>
          <th class="px-3 font-semibold text-center">Stock Health</th>
          <th class="w-24 px-3 text-right font-semibold">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant/30 font-body-sm text-body-sm">
        <tr class="h-9 hover:bg-surface-container-low transition-colors group"> … </tr>
```

Canonical table facts from the export:

- Header row height `h-8` (32px), header background `bg-surface-container-low`, bottom border
  `border-b border-outline-variant/60`, header text `font-label-xs text-label-xs … uppercase tracking-wider font-semibold`.
- Body row height `h-9` (36px) on the foundation/reports/ledger tables; `h-10`, `h-11`, `h-12`
  variants exist on dashboards and Users. Row hover `hover:bg-surface-container-low` (variants
  `/40`, `/50`, `/60`, `/70`, and `hover:bg-slate-50/80` on the ledger).
- Row-level flag tint: `bg-[#fffbeb]/20` (low-stock rows on the foundation table),
  `bg-amber-50/20` (warehouse stock detail), `bg-primary-fixed/10` (own row, Users),
  `opacity-85` / `opacity-90` (archived rows, Warehouses / Suppliers / Customers).
- **Alignment rules as generated:** text left; quantities, prices, dates right (`text-right`);
  status badges centred (`text-center`) on most screens but left-aligned on Products list and
  Warehouses; action column right (`text-right`) except the ledger which centres it.
- Row action reveal: `<div class="inline-flex items-center justify-end gap-1 opacity-80 group-hover:opacity-100">`.
- Sort affordance: only the foundation table is sortable — `cursor-pointer hover:text-on-surface`
  plus `unfold_more` at `text-[14px]`, wired to `sortTable(columnIndex)`. **No product screen ships
  a sortable header**; Purchase Orders and Sales Orders instead expose a "Sort" `<select>`
  ("Order Date: Newest First" / "Order Date: Oldest First"), and Reports-viz shows a static
  "Sort: Valuation (High-Low)" button.
- Column-width control uses either percentage widths (`w-[28%]`, `w-[12%]`, `w-[16%]`, `w-[6%]`,
  `w-[11%]`, `w-[9%]`, `w-[10%]`, `w-[32%]`, `w-[26%]`, `w-[7%]`), fixed widths (`w-36`, `w-56`,
  `w-40`, `w-48`, `w-28`, `w-24`, `w-12`, `w-72`, `w-80`, `w-10`, `w-1/4`, `w-7/12`, `w-1/6`), or
  `min-w-[…]` on the cells.
- Horizontal overflow uses `overflow-x-auto` on a wrapper, with `min-w-[…]` on the table:
  `min-w-[960px]` (Products), `min-w-[900px]` (Purchase Orders), `min-w-[1020px]` (Sales Orders),
  `min-w-[280px]` / `min-w-[160px]` / `min-w-[120px]` / `min-w-[180px]` per-cell (SO detail).
- **`DESIGN.md` describes frozen left identifier columns and sticky right action columns. No
  generated screen implements sticky/frozen columns** — there is not a single `sticky left-0` or
  `sticky right-0` on a table cell in the export.

`DESIGN.md` table spec: header 32px `#f8fafc` with `1px solid #cbd5e1` bottom border, text
`label-xs` uppercase `#475569`; body rows 36px regular / 28px compact, `1px solid #e2e8f0` bottom
border, hover `#f1f5f9`, selected `#eff6ff` with a `2px solid #1e3a8a` left accent. The **selected-row
state is documented but never rendered** in any generated screen.

### 9.5 Pagination

Two shapes exist.

**Full footer (foundation screen)** —
`px-4 py-2.5 bg-surface-container-low border-t border-outline-variant/40 flex flex-col sm:flex-row items-center justify-between gap-3`:
summary `Showing <strong>1</strong> to <strong>5</strong> of <strong>148</strong> entries`;
rows-per-page `<select class="h-6 px-1 rounded bg-surface-container-lowest border border-outline-variant/60 font-mono-data-sm text-mono-data-sm text-on-surface">` with options `10` (selected) / `25` / `50` / `100`;
`Prev` button `h-7 px-2 rounded border border-outline-variant/60 bg-surface-container-lowest text-outline disabled:opacity-40 disabled:cursor-not-allowed hover:bg-surface-container flex items-center` + `chevron_left` + `disabled`;
page buttons `h-7 w-7 rounded bg-primary text-on-primary font-mono-data-sm text-mono-data-sm font-semibold flex items-center justify-center` (active) / `bg-surface-container-lowest text-on-surface border border-outline-variant/60 … hover:bg-surface-container` (inactive); ellipsis `<span class="px-1 text-outline font-mono">...</span>`; `Next` + `chevron_right`.

**Compact footer (product screens)** — summary text + Previous / numbered / Next, without a
rows-per-page select. Rows-per-page selects appear only on: the foundation screen, Users
(`10 / 25 (selected) / 50`), and Warehouse stock detail (static text "Rows per page: 10").

Exact summary strings shipped per screen:

| Screen | Summary text | Pages shown |
|---|---|---|
| Foundation | `Showing 1 to 5 of 148 entries` | 1 · 2 · 3 · … · 15 |
| Products list | `Showing 1 to 10 of 32 products` | 1 · 2 · 3 · 4 |
| Suppliers | `Showing 1-6 of 6 suppliers across all regions` | 1 (Prev/Next both disabled) |
| Customers | `Displaying 1–7 of 7 customer accounts` | 1 (Prev/Next both disabled) |
| Users | `Showing 1 to 6 of 6 entries` | 1 (Prev/Next both disabled) |
| Purchase Orders | `Showing 1–10 of 25 records` | 1 · 2 · 3 |
| Sales Orders | `Showing 1 to 10 of 25 entries` | 1 · 2 · 3 |
| Stock Ledger | `Showing 1 to 10 of 48 entries` | 1 · 2 · 3 · 4 · 5 |
| Reports (Stock Movement) | `Showing 1 to 10 of 48 entries` | 1 · 2 · 3 · 4 · 5 |
| Reports (Order Status) | `Showing 1 to 6 of 28 entries` | 1 · 2 |
| Reports-viz | `Showing 1–5 of 124 line items` + `Page 1 of 25` | 1 · 2 · 3 · … · 25 |
| Warehouse stock detail | `Showing 1 to 10 of 28 items` | 1 · 2 · 3 |
| Sales dashboard | `Showing 6 of 32 total registered sales transactions` | `1 / 6` |
| Categories, Warehouses | — (no pagination; governance notice footer instead) | — |
| Shell blueprint | `Displaying 1-4 of 24 entries` | — (refresh icon only) |

### 9.6 Status badges — complete inventory

All badges share the shape `inline-flex items-center gap-1 / gap-1.5 px-2 py-0.5 rounded / rounded-full`
plus `text-[11px] font-semibold` or `font-label-xs text-label-xs font-semibold`, with optional
`uppercase tracking-wider`, and either a `w-1.5 h-1.5 rounded-full` pip or a Material icon.

**Purchase Order statuses (foundation screen — literal hex, canonical set):**

| Label (verbatim) | Surface | Border | Text | Pip |
|---|---|---|---|---|
| `Draft` | `#f1f5f9` | `#cbd5e1` | `#475569` | `#64748b` |
| `Ordered` | `#f0f9ff` | `#bae6fd` | `#0369a1` | `#0284c7` |
| `PartiallyReceived` | `#fffbeb` | `#fde68a` | `#92400e` | `#d97706` |
| `Received` | `#ecfdf5` | `#a7f3d0` | `#065f46` | `#059669` |
| `Cancelled` | `#fef2f2` | `#fecaca` | `#991b1b` | `#dc2626` |

**Sales Order statuses (foundation screen):**

| Label | Surface | Border | Text | Indicator |
|---|---|---|---|---|
| `Draft` | `#f1f5f9` | `#cbd5e1` | `#475569` | pip `#64748b` |
| `PendingApproval` | `#fffbeb` | `#fde68a` | `#92400e` | pip `#d97706` |
| `Approved` | `#ecfdf5` | `#a7f3d0` | `#065f46` | pip `#059669` |
| `Fulfilled` | `#f0fdf4` | `#bbf7d0` | `#15803d` | icon `check` at `text-[13px]` |
| `Cancelled` | `#fef2f2` | `#fecaca` | `#991b1b` | pip `#dc2626` |

**Stock health (foundation screen):**

| Label | Style |
|---|---|
| `Normal Stock` / `Normal` | `bg-[#ecfdf5] text-[#065f46] border border-[#a7f3d0]` + pip `bg-[#059669]` |
| `Low Stock Warning` / `Low Stock` | `bg-[#fffbeb] text-[#92400e] border border-[#fde68a]` + pip `bg-[#d97706] animate-pulse` |
| `Critical Out (0)` | `bg-[#dc2626] text-white` + icon `warning` at `text-[13px]` (solid fill, no border) |

**Per-screen badge implementations (these differ from the foundation set — all recorded as-is):**

| Screen | Status | Classes |
|---|---|---|
| Products list | `Low Stock (-N)` | `bg-error-container text-on-error-container border border-error/20` + pip `bg-error` |
| Products list | `Normal` | `bg-surface-container-high text-primary border border-primary/20` + pip `bg-primary` |
| Warehouses | `Active` | `bg-surface-container text-on-primary-container` + pip `bg-primary-container` |
| Warehouses | `Inactive` | `bg-surface-container-highest text-outline` + pip `bg-outline` |
| Suppliers / Customers | `ACTIVE` | `bg-emerald-50 text-emerald-800 border border-emerald-200` + pip `bg-emerald-600` |
| Suppliers | `INACTIVE` | `bg-surface-container-high text-secondary border border-outline-variant` + pip `bg-outline` |
| Customers | `INACTIVE` | `bg-surface-container text-outline border border-outline-variant/60` + pip `bg-outline` |
| Customers | `Archived` (inline name pill) | `px-1.5 py-0.2 rounded bg-surface-container text-outline text-[10px] font-bold uppercase` |
| Users | role `Admin` | `bg-indigo-50 text-indigo-800 border border-indigo-200` |
| Users | role `Warehouse Staff` | `bg-teal-50 text-teal-800 border border-teal-200` |
| Users | role `Sales` (active) | `bg-sky-50 text-sky-800 border border-sky-200` |
| Users | role `Sales` (inactive user) | `bg-surface-container text-outline border border-outline-variant` |
| Users | `Active` | `bg-emerald-50 text-emerald-800 border border-emerald-200` + pip `bg-emerald-600` |
| Users | `Inactive` | `bg-surface-container-high text-outline border border-outline-variant` + pip `bg-outline` |
| Users | `YOU` tag | `bg-surface-container-high text-primary` |
| PO list | `Draft` | `bg-surface-container-low text-outline` + pip `bg-outline` |
| PO list | `Ordered` | `bg-surface-container-high text-primary` + pip `bg-primary` |
| PO list | `Partially Received` | `bg-secondary-container text-on-secondary-container` + pip `bg-secondary` |
| PO list | `Received` | `bg-surface-container text-tertiary` + icon `done_all` |
| PO list | `Cancelled` | `bg-error-container text-on-error-container` + icon `cancel` |
| PO detail | `Draft` | `bg-surface-container text-on-surface-variant` + pip `bg-outline` |
| PO detail | `Ordered` | `bg-surface-container-high text-primary` + pip `bg-primary animate-pulse` |
| PO detail | `PartiallyReceived` | `bg-secondary-container text-on-secondary-fixed` + pip `bg-tertiary` |
| PO detail | `Received (Completed)` | `bg-primary-fixed text-on-primary-fixed` + icon `verified` |
| PO detail | `Cancelled` | `bg-error-container text-on-error-container` + pip `bg-error` |
| SO list | `Draft` | `bg-surface-container text-on-surface-variant border border-outline-variant/60` + pip `bg-outline` |
| SO list | `Pending Approval` | `bg-secondary-container text-on-secondary-fixed border border-secondary-fixed-dim` + pip `bg-secondary` |
| SO list | `Approved` | `bg-surface-container-high text-primary border border-surface-tint/30` + pip `bg-primary` |
| SO list | `Fulfilled` | `bg-primary-fixed/70 text-on-primary-fixed border border-primary-fixed-dim` + pip `bg-primary-container` |
| SO list | `Cancelled` | `bg-error-container text-error border border-error/30` + pip `bg-error` |
| SO detail | `Draft` | `bg-surface-container text-secondary` + pip `bg-secondary` |
| SO detail | `Pending Approval` | `bg-secondary-container text-on-secondary-container` + pip `bg-secondary` |
| SO detail | `Approved` / `Fulfilled` | `bg-surface-container-high text-primary` + pip `bg-primary` |
| SO detail | `Cancelled` | `bg-error-container text-on-error-container` + pip `bg-error` |
| Goods Issue | `Approved` / `Ready` / `Sufficient` | `bg-emerald-100 text-emerald-800 border border-emerald-300` + pip `bg-emerald-600` |
| Goods Issue | `Insufficient Stock` | `bg-red-100 text-red-800 border border-red-300` + pip `bg-red-600` |
| Goods Issue | `Fulfilled` | `bg-blue-100 text-blue-900 border border-blue-300` + pip `bg-blue-600` |
| Goods Issue | `Fulfilled / Closed` | `bg-slate-100 text-slate-700 border border-slate-300` |
| Goods Receipt | `Partial (+N)` | `bg-secondary-container text-on-secondary-fixed` |
| Goods Receipt | `Complete (+N)` | `bg-surface-container text-on-surface` |
| Goods Receipt | `None` | `bg-surface-container text-on-surface-variant` |
| Warehouse dashboard | `APPROVED` | `bg-emerald-50 text-emerald-800 border border-emerald-300` + icon `check_circle` |
| Warehouse dashboard | `ORDERED` | `bg-sky-50 text-sky-800 border border-sky-300` + icon `pending` |
| Warehouse dashboard | `PARTIALLY RECEIVED (300/500)` | `bg-amber-50 text-amber-800 border border-amber-300` + icon `hourglass_bottom` |
| Warehouse dashboard | `LOW STOCK` | `bg-amber-50 text-amber-900 border border-amber-300` + pip `bg-amber-600` |
| Admin dashboard | `Ordered` | `bg-sky-100 text-sky-900` + dot `bg-sky-600` |
| Admin dashboard | `PendingApproval` | `bg-amber-100 text-amber-900` + icon `schedule` |
| Admin dashboard | `Approved` | `bg-emerald-100 text-emerald-900` + icon `check` (FILL 1) |
| Admin dashboard | `Draft` | `bg-surface-container text-on-surface-variant` |
| Admin dashboard | `Low Stock` | `bg-amber-100 text-amber-900` + icon `warning` (FILL 1) |
| Sales dashboard | `Draft` | `bg-surface-container text-on-surface-variant` + dot `bg-secondary` |
| Sales dashboard | `Pending` | `bg-surface-container-high text-tertiary-container` + dot `bg-tertiary-container` |
| Sales dashboard | `Approved` | `bg-surface-container-highest text-primary font-semibold` + dot `bg-primary` |
| Sales dashboard | `Fulfilled` | `bg-surface-container-low text-on-secondary-fixed-variant` + icon `done_all` |
| Sales dashboard | `Cancelled` | `bg-error-container text-on-error-container` + icon `close` |
| Stock Ledger | `Receipt` | `bg-emerald-50 text-emerald-800 border border-emerald-200` + pip `bg-emerald-600` |
| Stock Ledger | `Issue` | `bg-amber-50 text-amber-800 border border-amber-200` + pip `bg-amber-600` |
| Stock Ledger | `Adjustment` | `bg-slate-100 text-slate-700 border border-slate-200` + pip `bg-slate-500` |
| Reports (tabular) | `Receipt` | `bg-secondary-container text-on-secondary-fixed` + primary dot |
| Reports (tabular) | `Issue` | `bg-error-container text-on-error-container` + error dot |
| Reports (tabular) | `Adjustment` | `bg-surface-container text-secondary` + secondary dot |
| Reports (tabular) | type `PO` | `bg-primary text-on-primary` |
| Reports (tabular) | type `SO` | `bg-tertiary-container text-on-tertiary-container` |
| Reports (tabular) | `Received` / `Fulfilled` | `bg-secondary-container text-on-secondary-fixed` |
| Reports (tabular) | `PendingApproval` | `bg-surface-container-high text-primary` |
| Reports (tabular) | `Draft` | `bg-surface-container text-secondary` |
| Reports-viz | `In Stock` / `Optimal` | `bg-emerald-50 text-emerald-700` + pip `bg-emerald-600` |
| Reports-viz | `High Velocity` | `bg-secondary-container text-on-secondary-fixed` |
| Reports-viz | `Slow Moving` | `bg-amber-50 text-amber-700` |
| Product detail | `Active` | `bg-emerald-50 text-emerald-800 border border-emerald-200` + pip `bg-emerald-600` |
| Product detail | `Inactive` | `bg-slate-100 text-slate-700 border border-slate-300` + pip `bg-slate-500` |
| Product detail | `Out of Stock / Zero` | `bg-error-container text-on-error-container border border-error/30` + pip `bg-error` |
| Warehouse stock detail | `Active Facility` | `bg-emerald-50 text-emerald-800 border border-emerald-200` + `bg-emerald-500 animate-pulse` dot |
| System states | `Optimal` | `bg-surface-container text-on-surface` + pip `bg-primary` |
| System states | `Reorder` | `bg-surface-container-high text-on-surface-variant` + pip `bg-secondary` |
| System states | `Stockout` | `bg-error-container text-on-error-container` + pip `bg-error` |
| Shell blueprint | `In Transit` | `bg-surface-container-high text-primary` |
| Shell blueprint | `Processing` | `bg-surface-container-highest text-on-surface` |
| Shell blueprint | `Complete` | `bg-secondary-container text-on-secondary-fixed` |
| Shell / role pills | `Admin` | `bg-secondary-container text-on-secondary-fixed` |
| Shell / role pills | `SALES` | `bg-surface-container-high text-primary` |
| Shell / role pills | `WAREHOUSE` | `bg-surface-container-highest text-on-surface` |

### 9.7 KPI / metric cards

Two card anatomies exist.

**Bordered (Products/Warehouses/PO/Warehouse-dashboard lineage):**
`bg-surface-container-lowest p-4 rounded border border-outline-variant/60 shadow-sm flex flex-col justify-between` —
label `font-label-xs text-label-xs uppercase tracking-wider text-on-surface-variant font-semibold`,
value `font-headline-xl text-headline-xl font-bold` (often `font-mono-data-md` too), trailing icon
`material-symbols-outlined text-[18px]`, footer line `font-body-xs text-body-xs`.

**Shadow-only (shell / Sales dashboard / reports-viz lineage):**
`p-4 rounded bg-surface-container-lowest shadow-sm flex flex-col gap-2` — with an optional
1px progress track `w-full bg-surface-container-high h-1 rounded overflow-hidden` and fill
`bg-primary-container h-full w-3/4` (widths seen: `w-1/4`, `w-3/4`, `w-4/5`, `w-full`).

`DESIGN.md` KPI spec: surface `#ffffff`, border `1px solid #cbd5e1`, padding `12px 16px`; contents =
category label (`label-xs`, `#64748b`) + primary metric (`headline-lg`, tabular mono, `#0f172a`) +
secondary delta coloured semantically; *"No decorative line graphs or gradient fills."*

### 9.8 Toasts

Two shapes.

**Fixed-position card (foundation, Categories, Suppliers, Warehouses, My Profile, Product detail, Warehouse stock detail, Users, Customers, PO detail, SO create/edit):**
`fixed bottom-6 right-6 z-50 … bg-surface-container-lowest text-on-surface shadow-md rounded border border-outline-variant/60 px-4 py-3 flex items-center gap-3`
with icon `check_circle` at `text-[20px]`, message `font-body-sm text-body-sm font-medium`.
Entry/exit is a translate+opacity transition: `transform translate-y-20 opacity-0 pointer-events-none transition-all duration-200` ⇄ `translate-y-0 opacity-100`.
Positions used: `bottom-6 right-6`, `bottom-5 right-5`, `top-16 right-6`, `top-5 right-6`.

**Inverse-surface toast (Warehouses, SO detail):**
`pointer-events-auto bg-inverse-surface text-inverse-on-surface px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[300px] border border-outline-variant/30 animate-in slide-in-from-bottom-5 duration-200`,
icon `check_circle` in `text-tertiary-fixed-dim`.

Auto-dismiss timers actually coded: **2800ms** (foundation), **3000ms** (warehouse stock detail),
**3500ms** (Users), **3800ms** (Warehouses), **4000ms** (Categories, Suppliers, Customers, PO list,
Sales Orders, SO create/edit), **4200ms** (PO detail), **4500ms** (Product detail, PO create/edit).

Type variants (Users, SO create/edit, PO create/edit): success `check_circle` (emerald-400 /
`bg-emerald-900`), warning `warning` (amber-400 / `bg-amber-900`), info `info` (sky-400 /
`bg-slate-900`), error `cancel` / `error` (`bg-rose-900`).

### 9.9 Modals and dialogs

Three implementation techniques are present:

1. **`<div>` overlay + `hidden` toggle** — the majority:
   `fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm`
   with panel `bg-surface-container-lowest rounded-xl shadow-xl max-w-md/max-w-lg w-full overflow-hidden flex flex-col`.
2. **Native `<dialog>` with `showModal()` / `close()`** — Warehouses (3 dialogs), Goods Receipt
   (`<dialog id="modal-confirm">` with `backdrop:bg-on-background/50`), SO detail (3 dialogs with
   `backdrop:bg-inverse-surface/40`).
3. **Entry animation utilities** — `animate-in fade-in zoom-in-95 duration-150` (Categories,
   Suppliers), `animate-in fade-in zoom-in-95` (Users), `animate-in slide-in-from-bottom-5 duration-200` (toasts).

Panel max-widths present: `max-w-md`, `max-w-lg`, `max-w-xl`, `max-w-2xl`, plus `max-h-[921px]`
(Customers form modal).

Complete modal inventory:

| Screen | Modal id | Heading (verbatim) |
|---|---|---|
| Categories | `categoryModal` | `Add Category` / `Edit Category` |
| Warehouses | `modal-add-warehouse` | `Add Warehouse` |
| Warehouses | `modal-edit-warehouse` | `Edit Warehouse` |
| Warehouses | `modal-deactivate` | `Deactivate warehouse?` |
| Suppliers | `supplier-form-modal` | `Add Supplier` / `Edit Supplier` |
| Suppliers | `deactivate-modal` | `Deactivate supplier?` |
| Customers | `customerFormModal` | `Add Customer` / `Edit Customer` |
| Customers | `customerDetailModal` | `Customer Detail` |
| Customers | `deactivateModal` | `Deactivate customer?` |
| Users | `deactivateModal` | `Deactivate user?` |
| Users | `activateModal` | `Activate user?` |
| Users | `viewUserModal` | `User Details` |
| User create/edit | `modal-unsaved-changes` | `Discard Unsaved Changes?` |
| My Profile | `unsaved-modal` | `Discard changes?` |
| Product detail | `deactivate-modal-backdrop` | `Deactivate this product?` |
| Product create/edit | `unsaved-modal` | `Discard unsaved changes?` |
| Warehouse stock detail | `modal-edit-wh` | `Edit Facility Metadata` |
| PO detail | `modal-order-purchase` | `Transmit Purchase Order` |
| PO detail | `modal-cancel-purchase` | `Cancel Purchase Order?` |
| PO create/edit | `confirm-modal` | `Place this purchase order?` |
| Goods Receipt | `modal-confirm` | `Confirm Goods Receipt?` |
| SO list | `action-modal` | `Order Action` (5 dynamic variants — see §12.5) |
| SO detail | `modal-submit` | `Submit sales order for approval?` |
| SO detail | `modal-approve` | `Approve sales order?` |
| SO detail | `modal-cancel` | `Cancel sales order?` |
| SO create/edit | `submit-modal` | `Submit Sales Order for Approval?` |
| SO create/edit | `cancel-modal` | `Cancel Sales Order?` |
| SO create/edit | `product-picker-modal` | `Select Catalog SKU` |
| Goods Issue | `confirm-modal` | `Confirm Goods Issue?` |
| Sales dashboard | `order-inspect-modal` | `Sales Order: {soNumber}` |
| System states | (preview panel 15) | `Decommission Warehouse` |

Standard footer button order across all of them: **destructive/neutral cancel on the left, primary
confirm on the right**, gap `gap-2` / `gap-2.5` / `gap-3`.

### 9.10 Alerts and banners

Inline alert anatomy (foundation screen, 4 variants, `grid grid-cols-1 md:grid-cols-2 gap-3`):

```html
<div class="p-3 rounded bg-[#ecfdf5] border border-[#a7f3d0] flex items-start gap-2.5">
  <span class="material-symbols-outlined text-[#059669] text-[20px] shrink-0">check_circle</span>
  <div class="space-y-0.5 min-w-0">
    <div class="font-label-md text-label-md font-semibold text-[#065f46]">Goods receipt completed</div>
    <p class="font-body-xs text-body-xs text-[#065f46]">…</p>
  </div>
</div>
```

| Variant | Surface / border | Icon | Icon color |
|---|---|---|---|
| Success | `#ecfdf5` / `#a7f3d0`, text `#065f46` | `check_circle` | `#059669` |
| Warning | `#fffbeb` / `#fde68a`, text `#92400e` | `warning` | `#d97706` |
| Danger | `#fef2f2` / `#fecaca`, text `#991b1b` | `error` | `#dc2626` |
| Info | `#f0f9ff` / `#bae6fd`, text `#0369a1` | `info` | `#0284c7` |

Full-bleed banner variants used on product screens (`w-full … px-6 py-3 flex items-center justify-between`):
`bg-error-container text-on-error-container` (Warehouses save error, PO detail sync anomaly,
Goods Issue op error), `bg-secondary-container text-on-secondary-fixed` (Warehouses read-only strip,
Categories read-only, Reports-viz stale banner, User self-lockout, SO create/edit pending notice),
`bg-surface-container-high` (system-states STALE_DATA strip), `bg-amber-50 border-b border-amber-200`
(PO create/edit locked banner), `bg-amber-50 border border-amber-300 text-amber-900` (Goods Issue
stale), `bg-sky-50 border border-sky-300 text-sky-900` (Goods Issue status changed),
`bg-emerald-50 border border-emerald-300 text-emerald-900` (Goods Issue success),
`bg-red-50 border border-red-300 text-red-900` (Goods Issue insufficient stock),
`bg-rose-50 border-rose-200 text-rose-800` (PO create/edit validation),
`bg-surface-container-low border-l-4 border-primary` / `border-l-4 border-l-primary-container`
(governance callouts).

Dismiss control on dismissible banners: `<span class="material-symbols-outlined text-[16px]">close</span>`
in a `text-outline hover:text-on-surface p-0.5` button.

### 9.11 Filter / search toolbar

Canonical pattern (foundation screen, "Standard Enterprise Filter Toolbar Pattern"):
`bg-surface-container-lowest rounded border border-outline-variant/40 shadow-sm p-3 space-y-2`
→ `flex flex-wrap items-center gap-2.5` containing:

1. Search input `relative flex-1 min-w-[220px]`, placeholder `Search by SKU, Product Name or Barcode...`
2. Category select `w-40 shrink-0` — `All Categories` / `Industrial` / `Electronics` / `Fasteners` / `Hydraulics`
3. Status select `w-36 shrink-0` — `All Statuses` / `Normal` / `Low Stock`
4. Warehouse select `w-40 shrink-0` — `All Warehouses` / `WH-A (Chicago)` / `WH-B (Dallas)` / `WH-C (Rotterdam)`
5. Clear-filters button with icon `filter_alt_off`, label `Clear Filters`

Per-screen toolbar contents (verbatim placeholders and options) are listed in §12.

### 9.12 Empty-state card

Canonical (foundation screen):
```html
<div class="bg-surface-container-lowest p-8 rounded border border-outline-variant/40 shadow-sm flex flex-col items-center justify-center text-center space-y-3">
  <div class="w-12 h-12 rounded-full bg-surface-container-low border border-outline-variant/60 flex items-center justify-center text-outline">
    <span class="material-symbols-outlined text-[24px]">search_off</span>
  </div>
  <div class="space-y-1 max-w-sm">
    <div class="font-headline-sm text-headline-sm text-on-surface font-semibold">No products found</div>
    <p class="font-body-xs text-body-xs text-on-surface-variant">…</p>
  </div>
  <button class="h-8 px-3.5 rounded bg-surface-container-low hover:bg-surface-container text-on-surface font-label-md text-label-md font-semibold border border-outline-variant/60 transition-colors shadow-sm">Reset Filters</button>
</div>
```
Icon-circle sizes across the export: `w-12 h-12` (24px icon), `w-14 h-14` (32px icon),
`w-16 h-16` (36px icon). Card padding: `p-8` typical, `p-12` for full-page blank/403/404 states.

### 9.13 Skeleton loader

`animate-pulse` on a wrapper with grey blocks. Two block palettes are used:
`bg-surface-container-high` / `bg-surface-container-highest` / `bg-surface-container` /
`bg-surface-container-low` (token screens) and `bg-slate-100` / `bg-slate-200` / `bg-slate-200/70`
(Stock Ledger). Header bars are `h-3`–`h-8`; row bars are `h-7`, `h-10`, `h-12`, `h-16`.
The foundation screen prints the timing contract:
**"Standard 800ms fade-in transition prevents layout cumulative shift (CLS)."**
The system-states LOADING spec prints **"CLS < 0.01"**.

### 9.14 Progress indicators

| Kind | Markup |
|---|---|
| Thin KPI bar | `w-full bg-surface-container-high h-1 rounded overflow-hidden` + `bg-primary-container h-full w-3/4` |
| Capacity bar | `w-full bg-surface-container rounded-full h-1.5 overflow-hidden` + inner `style="width: NN%"`, fill `bg-primary` or `bg-amber-600` |
| PO fulfilment bar | `w-full bg-surface-container h-1.5 rounded-full overflow-hidden` + `bg-primary h-full rounded-full` (or `bg-outline-variant` at 0%) |
| Two-segment intake bar (Goods Receipt) | `bg-primary` (previously verified) + `bg-surface-tint` (current intake) side by side |
| Product-detail buffer bar | track `w-full h-2.5 rounded-full bg-surface-container overflow-hidden flex`, fill `h-full bg-amber-600 transition-all duration-500 rounded-full` at `width: 30%` |
| Stacked category master bar (Reports-viz) | `w-full h-4 rounded-full overflow-hidden flex bg-surface-container` with 4 inline-width segments |
| ACTION_LOADING bar | `w-full bg-surface-container-high rounded-full h-2 overflow-hidden` + `bg-primary h-2 rounded-full w-2/3` |
| Spinners | `<span class="material-symbols-outlined animate-spin">refresh</span>`, `…>progress_activity</span>`, `…>sync</span>`, or an inline `<svg class="animate-spin h-4 w-4">` with `circle opacity-25` + `path opacity-75` |

### 9.15 Other components present

- **Breadcrumb** — `flex items-center gap-1.5 / gap-2 font-label-xs text-label-xs`, separators are
  either a literal `/` in `text-outline-variant` or `<span class="material-symbols-outlined text-[12px]">chevron_right</span>`; current page `text-on-surface font-semibold` or `text-primary font-semibold`.
- **Workflow stepper** (SO detail only) — 4 nodes (`1. Draft`, `2. Approval`, `3. Approved`,
  `4. Fulfilled`) with a `#step-progress-bar` whose width is set to `12%` / `33%` / `66%` / `100%`
  (and `0%` when Cancelled) and a hint label `Step 2 of 4: Authorization`.
- **Audit log timeline** (SO detail) — timestamp column `w-28`, a dot, then two text lines per entry.
- **User dropdown menu** (shell blueprint) — `absolute right-0 mt-2 w-48 bg-surface-container-lowest rounded shadow-lg py-1 z-30`, items `My Profile` (`person`) and `Logout` (`logout`, error-coloured), closed by an outside-click `window` listener.
- **Mobile off-canvas drawer** (shell blueprint) — overlay `fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm flex justify-start`, panel `w-[320px] max-w-[85vw] h-full bg-surface-container-lowest shadow-2xl flex flex-col`.
- **Collapsed rail specimen** (shell blueprint) — `w-16` column, 7px brand tile `w-7 h-7 rounded bg-primary-container text-on-primary` reading `IO`, items `w-10 h-10 rounded` with 20px icons and `title` tooltips, dividers `w-8 h-[1px] bg-surface-container`.
- **360px device frame** (system states) — `w-[360px] bg-surface-container-lowest rounded-2xl shadow-xl overflow-hidden flex flex-col`, mock status bar `h-6 bg-primary-container … text-[10px]` showing `09:41`, `wifi`, `battery_full`.
- **Avatar initials tile** — `w-14 h-14 rounded bg-primary-container text-on-primary` (My Profile hero, `SJ`); `w-7 h-7 rounded bg-primary-container text-on-primary … font-mono-data-sm font-bold` (`IO` brand tile); table avatars `SJ` / `BS` / `RP` / `MI` / `HS` / `AF` with per-row backgrounds (`bg-primary-container`, `bg-slate-600`, `bg-blue-700`, `bg-teal-700`, `bg-outline-variant`, `bg-indigo-800`).
- **Governance / policy notice** — recurring footer block: `p-4 bg-surface-container-low text-on-surface-variant flex items-start gap-3` with icon `info` / `policy` / `shield` / `verified_user` / `balance` / `security`, a bold heading and one paragraph. Present on Warehouses, Suppliers, Customers, Categories, PO list, SO list, Stock Ledger, Goods Issue, Goods Receipt, Warehouse stock detail, Product detail, Product create/edit.
- **QA / simulator harness bars** — present on **every app screen**. See §13.

---

## 10. Responsive behavior

### 10.1 Breakpoint strategy actually used

Only Tailwind's default breakpoints appear: `sm:` (640px), `md:` (768px), `lg:` (1024px),
`xl:` (1280px). **No `2xl:` prefix appears anywhere in the export.** No custom breakpoints are
configured. No container queries are used (the `?plugins=forms,container-queries` CDN query string
appears on the Goods Issue screen only, and no container-query utility is actually used there).

### 10.2 Documented adaptation rules (foundation screen §9, verbatim)

- **Desktop (1280px+)** — "High-density tables, multi-column master-detail splits (40/60), and multi-select action ribbons."
- **Tablet (768px – 1024px)** — "Left sidebar collapses to 64px icon-rail; table columns collapse with secondary data nested under expandable rows."
- **Mobile (360px Handheld)** — "Tables transform into structured stacked cards with key badge and quantity prominently right-aligned; action bars pin to viewport bottom."

### 10.3 What the generated screens actually implement

| Rule | Implemented? | Evidence |
|---|---|---|
| Sidebar collapses to 64px rail below `lg` | **No** — only demonstrated on the shell blueprint via a JS mode switcher (`w-64` ⇄ `w-16`). The 26 product screens keep `pl-64` at every viewport, with no responsive override. | `authenticated_application_shell` `setShellMode()` |
| Mobile hamburger | **Only on the shell blueprint**: `<button class="lg:hidden flex items-center justify-center w-8 h-8 rounded bg-surface-container text-on-surface" onclick="toggleMobileSidebar()">` with icon `menu`. Absent from all product screens. | shell only |
| Tables → stacked cards | **Only on 4 screens**: Users, Stock Ledger, PO create/edit, Goods Receipt, SO create/edit (5 total). Everywhere else the table just scrolls horizontally. | see §10.5 |
| Expandable rows on tablet | **Not implemented anywhere.** | — |
| Bottom-pinned mobile action bars | Implemented as `sticky bottom-0` footers on Product create/edit, PO create/edit, SO create/edit (at all viewports, not mobile-only). | — |
| Frozen/sticky table columns | **Not implemented anywhere.** | — |

### 10.4 Recurring responsive class patterns

| Pattern | Meaning | Where |
|---|---|---|
| `flex flex-col md:flex-row md:items-center justify-between gap-4` | Page header stacks below `md`, becomes a row above | Most page headers |
| `flex flex-col sm:flex-row sm:items-center justify-between gap-3/gap-4` | Toolbar / footer stacks below `sm` | Toolbars, pagination footers, sticky footers |
| `flex flex-col lg:flex-row lg:items-center justify-between gap-3/gap-4` | Wide toolbars stack until `lg` | Users toolbar, PO list toolbar, SO list toolbar, Reports-viz viz header, system-states header |
| `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4` | KPI strip: 1 → 2 → 4 | Shell, PO list, SO list, Reports-viz, Warehouse stock detail |
| `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4` | KPI strip: 1 → 2 → 4 at `md`/`lg` | Warehouse dashboard |
| `grid grid-cols-2 md:grid-cols-4 gap-3` | KPI strip: 2 → 4 (never 1-up) | Stock Ledger, Reports tabular KPIs, Goods Receipt |
| `grid grid-cols-2 md:grid-cols-5 gap-3` | 5-up KPI strip with `col-span-2 md:col-span-1` on the 5th | Sales dashboard |
| `grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4` | 5-up metrics | Goods Issue |
| `grid grid-cols-1 md:grid-cols-3 gap-4` | 3-up cards | Categories KPIs, Warehouses KPIs, Suppliers KPIs, Customers KPIs, PO detail metadata, SO detail metadata, Goods Receipt info cards, Goods Issue summary, Reports-viz warehouse tab |
| `grid grid-cols-1 md:grid-cols-2 gap-4/gap-5` | Form field pairs, alert pairs | Most forms, foundation alert grid |
| `grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end` with `md:col-span-2/3` | 12-col filter workbench | Stock Ledger filter bar |
| `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4` | 5-field filter row | Reports (Order Status form) |
| `grid grid-cols-2 sm:grid-cols-3` | Spec badge grid with `col-span-2 sm:col-span-1` on the last | Product detail spec badges |
| `grid grid-cols-5 md:grid-cols-10 gap-2` | Neutral swatch strip | Foundation |
| `hidden sm:flex` / `hidden sm:inline` | Hide secondary chrome on mobile | Sales-dashboard live-sync chip, Suppliers "Reset" label, Warehouses "Reload" (`hidden sm:inline-flex`), Warehouse-stock-detail "Export CSV" label |
| `hidden md:inline` | Hide QA subtitle | Suppliers, Reports, My Profile |
| `hidden xl:inline` / `hidden xl:flex` | Hide filter labels / live-sync indicator above `xl` only | Warehouse stock detail (`Status:` / `Category:` labels), Product detail |
| `hidden md:block` + `md:hidden` | Desktop table / mobile card swap | Users, Stock Ledger, PO create/edit, Goods Receipt |
| `hidden sm:block` + `sm:hidden` | Desktop table / mobile card swap at `sm` | SO create/edit |
| `p-4 md:p-6` / `p-4 lg:p-6` / `p-6 md:p-8` / `p-6 lg:p-10` / `p-5 sm:p-6` | Padding step-up | Reports, Warehouse stock detail, PO list, 404 views, Product create/edit |
| `px-4 sm:px-6 lg:px-8` + `-mx-4 sm:-mx-6 lg:-mx-8` | Gutter + sticky-footer bleed | Product create/edit, SO create/edit, Goods Receipt, PO detail |
| `flex-col-reverse sm:flex-row` | Reverse button order on mobile | SO create/edit sticky footer |
| `sm:order-1` / `sm:order-2` | Reorder form actions | My Profile |
| `w-full sm:w-auto` | Full-width buttons on mobile | My Profile, SO detail goods-issue callout |
| `max-w-[85vw]` | Mobile drawer cap | Shell blueprint |
| `overflow-x-auto` on a chip row + `min-w-max` | Horizontally scrolling pill rows | System-states state selector, warehouse-dashboard QA bar |

### 10.5 Mobile card markup where it exists

| Screen | Container | Card contents |
|---|---|---|
| Users | `md:hidden` list `#userMobileList` | 6 static cards, `p-4 space-y-3`, avatar circle + name + role/status pills + `Created:` date + a `grid grid-cols-3 gap-2` action row with `min-h-[44px]` tap targets (View / Edit / Deactivate, or Protected / Activate) |
| Stock Ledger | `md:hidden divide-y divide-outline-variant/30 p-2 space-y-2` `#ledger-mobile-cards` | JS-rendered cards, `p-3 bg-surface rounded border border-outline-variant/40 space-y-2`: product name, SKU + category, type badge, quantity, reference, warehouse + timestamp, performer, `View Details` button |
| PO create/edit | `block md:hidden` `#mobile-line-items-container` | JS-generated per-line cards from `syncMobileCards()`; remove button omitted when `currentMode === 'locked'` |
| Goods Receipt | `block md:hidden` `#receipt-mobile-cards` | Static duplicate of both line items with `mob-`-prefixed ids, `h-11 w-11` ± buttons, `Max (600)` / `Max (200)` text buttons |
| SO create/edit | `sm:hidden` `#items-mobile-container` | 3 cards mirroring the table with `.mobile-qty` / `.mobile-price` inputs and `.mobile-subtotal` text |

### 10.6 Desktop behavior (as generated)

- Sidebar is permanently visible at 256px; content is offset with `pl-64` and never reflows the sidebar away.
- Header is fixed full-width right of the sidebar (`left-64 right-0`), 56px tall, with page content offset by `pt-14`.
- Content is either fluid or capped at one of: `max-w-5xl` (My Profile, User create/edit),
  `max-w-7xl` (Categories, Warehouses, Suppliers, Sales dashboard, PO create/edit, Goods Receipt,
  SO detail, SO create/edit), `max-w-[1600px]` (foundation, PO detail, Goods Issue),
  `max-w-[1720px]` (Reports-viz), or uncapped (`p-6` only — dashboards, Products list, Reports,
  Stock Ledger, Users, Customers).
- Tables are the primary desktop surface, at 32px headers and 36–48px rows, with horizontal scroll
  rather than column hiding.
- Master-detail splits activate at `lg:` or `xl:` (see §2.5) and collapse to a single column below.
- Dashboards use `xl:grid-cols-12` (Admin) or `lg:grid-cols-12` (Sales), so the Admin dashboard
  stays single-column until 1280px.

### 10.7 360px mobile behavior (as generated)

The system-states screen contains the only explicit 360px specification and the only 360px preview.

**Documented targets and rules (verbatim from the export):**

- Frame: `w-[360px]`, target devices `Zebra TC57 / Honeywell CT40 (360x640)`.
- Footer readout inside the frame: `Viewport: 360px • Max-Dialog: 328px`.
- Adaptive alert copy: *"Full-width stacked button layout with minimum 44px tap targets for gloved warehouse personnel."*
- Mobile primary button: `w-full h-11` (44px) with a 16px icon; secondary `w-full h-10` (40px);
  tertiary/dismiss `w-full h-9` (36px). Buttons stack with `flex flex-col gap-2`.
- Mobile dialog card: `bg-surface-container-lowest rounded-xl p-4 shadow-md space-y-3`, capped at
  328px by the 360px frame minus `p-4` padding on each side.
- Mobile screen header inside the frame: `px-4 py-2.5 bg-surface-container-lowest shadow-sm flex items-center justify-between` with a `menu` icon at `text-[20px]`, title `IOMS Warehouse` (`font-headline-sm … font-bold`), and a context pill `BIN 402` (`px-2 py-0.5 bg-primary-container text-on-primary rounded text-[10px] font-bold`).
- Mobile content body: `p-4 space-y-4 min-h-[400px] flex flex-col justify-center`.

**Shell blueprint mobile drawer rules (verbatim):**

- Trigger: "strictly via top-bar hamburger on responsive viewports (<1024px)".
- Panel width `w-[320px] max-w-[85vw]`, full height, `shadow-2xl`.
- "44px Minimum Touch Bounds"; "Slide-in animation: 200ms cubic-bezier transition."; "Backdrop scrim: 40% surface elevation."; "Auto-dismiss on route change / selection."
- Drawer nav groups in the blueprint: `Navigation` (Dashboard) · `Master Data` (Products, Categories) · `Fulfillment & Orders` (Purchase Orders, Sales Orders, Stock Ledger) · footer (My Profile, Logout). Note this is a **reduced** menu compared with the desktop sidebar — Warehouses, Suppliers, Customers, Reports and Users are absent from the drawer specimen.
- Drawer link padding: active `px-3 py-2.5`, inactive `px-3 py-2`, gap `gap-3`, icons `text-[20px]`.

**Practical 360px consequences of the generated product screens:** because no product screen carries
a responsive sidebar rule, at 360px the `pl-64` offset leaves 104px of content width. Any 360px
implementation therefore has to introduce the drawer behavior that only the blueprint demonstrates.
This is recorded as a gap in the export, not as a requirement to invent.

---

## 11. Interaction patterns

### 11.1 Transitions and animations actually used

| Class | Usage |
|---|---|
| `transition-colors` | Default on virtually every button, nav link, table row, badge toggle |
| `transition-all` | KPI card hover, sidebar width change, toast, submit buttons, filter inputs |
| `transition-opacity` | Header primary buttons (`hover:opacity-90`, `hover:opacity-95`) |
| `transition-transform duration-300` | Product-detail image (`group-hover:scale-105`) |
| `duration-150` | Modal entry (`animate-in fade-in zoom-in-95 duration-150`) |
| `duration-200` | Sidebar width (`transition-all duration-200 ease-in-out`), toast slide, inverse toast |
| `duration-300` | Categories toast (`transition-all duration-300 ease-out`) |
| `duration-500` | Product-detail buffer bar fill |
| `animate-pulse` | All skeletons; low-stock warning pips; "Fetching records..." label; querying dots |
| `animate-spin` | All spinners (icon and SVG) |
| `animate-ping` | System-states LOADING query dot |
| `animate-in fade-in zoom-in-95` | Modal entry (Categories, Suppliers, Users) |
| `animate-in slide-in-from-bottom-5 duration-200` | Warehouses toast |
| `backdrop-blur-sm` / `backdrop-blur-xs` / `backdrop-blur-[1px]` / `backdrop-blur-[2px]` / `backdrop-blur-none` | Modal scrims (all five variants appear) |

### 11.2 Hover patterns

- Rows: `hover:bg-surface-container-low` (+ alpha variants).
- Row actions revealed by `group` / `opacity-80 group-hover:opacity-100`.
- Row name highlight: `group-hover:text-primary transition-colors` (Categories).
- Primary buttons: `hover:bg-primary`, `hover:bg-primary-container`, `hover:bg-primary/90`, `hover:opacity-90`.
- Secondary buttons: `hover:bg-surface-container-low`, `hover:bg-surface-container`, `hover:bg-surface-container-high`.
- Destructive: `hover:bg-error-container/40`, `hover:bg-red-700`, `hover:bg-on-error-container`, `hover:opacity-90`.
- Text actions: `hover:underline`, `underline underline-offset-4`.
- Sortable header: `hover:text-on-surface`.
- Tooltips are `title` attributes only (`title="View Details"`, `title="Edit Record"`,
  `title="Fill all 600"`, `title="Toggle Sidebar Width"`, `title="Self-deactivation protected by system security protocol."`, …), plus one CSS-driven tooltip: the Reports Export-CSV button uses
  `group` + `group-hover:block` on `#export-tooltip` (`bg-inverse-surface text-inverse-on-surface`).

### 11.3 Disabled patterns

`disabled` attribute plus one of: `disabled:opacity-50 disabled:cursor-not-allowed`,
`disabled:opacity-60 disabled:cursor-not-allowed`, `disabled:opacity-40 disabled:cursor-not-allowed`,
`cursor-not-allowed opacity-60`, `bg-surface-container text-outline cursor-not-allowed border border-outline-variant/30`,
`bg-surface-container-low text-outline cursor-not-allowed`, `opacity-75 cursor-not-allowed bg-surface-container-low`.

### 11.4 Filtering and search behavior (client-side, per screen)

All filtering is client-side DOM show/hide over static rows or over a small in-file JS array.
Two mechanisms appear:

1. **Row-text substring match** — `row.textContent.toLowerCase().includes(query)` then
   `row.style.display = '' | 'none'`; used by the foundation table, Warehouses, admin dashboard
   low-stock filter, Reports-viz table, PO list.
2. **Typed data filter + re-render** — a JS array is filtered on named fields and the `<tbody>`
   is rebuilt; used by Categories (`categories[]`), Sales Orders (`RAW_ORDERS[]`), Stock Ledger
   (`ledgerData[]`), Warehouse stock detail (`WAREHOUSE_A_ITEMS[]`), Users (hard-coded 6-user array),
   Customers / Suppliers (`data-*` attributes on rows).

Result-count labels are updated on every filter pass (exact strings in §12). When a filter yields
zero rows, the screen swaps to its NO_RESULTS view rather than showing an empty table body.

### 11.5 Sorting behavior

Only the foundation screen sorts: `sortTable(columnIndex)` toggles a module-level `sortDirection`
boolean, sorts `Array.from(tbody.querySelectorAll('tr'))` by `localeCompare` on the cell text, and
re-appends. Product screens expose sort as a `<select>` whose change handler re-renders (Sales
Orders reverses `RAW_ORDERS` when `oldest` is chosen; PO list's sort select has a change handler
that does not reorder rows).

### 11.6 Selection behavior

Only the foundation table has selection: a header `#selectAllCheckbox` with
`toggleSelectAll(this)` setting every `.row-checkbox`, and a toast confirming
`Selected all visible line items` / `Cleared table row selections`. The documented indeterminate
state (`DESIGN.md`: `#1e3a8a` background with a white dash for bulk batch selection) is **not
rendered anywhere**. No product screen ships row checkboxes.

### 11.7 Quantity stepper and recalculation logic

**Goods Receipt** — per line: `-` / `+` buttons calling `adjustQty(inputId, delta)` with
`delta = ±50`, a `Max` button calling `fillMax(n)` (sets input to `remaining`), plus
`quickFillAllRemaining()` and `clearAllInputs()` toolbar buttons. `recalculateMetrics()` computes:
`totalOrdered = Σ ordered`, `totalPrev = Σ prev`, `totalRemaining = Σ remaining`,
`currentBatch = Σ max(0, input)`, `postTotal = totalPrev + currentBatch`,
`postPending = max(0, totalOrdered − postTotal)`; progress segment widths are
`prevPct` and `currentPct` as percentages of `totalOrdered`, `toFixed(1)`; per-item impact badge is
`Complete` when input covers remaining, `Partial (+N)` when partial, `None` at zero. Desktop and
mobile inputs are mirrored on every change.

**Goods Issue** — full-fulfilment rule: `validateLineItems()` requires each issue qty to be
**exactly equal** to the ordered qty (`ORDERED_QTYS`), not merely ≤ available. Failing lines show
`Must equal {orderedQty} pcs` and the inline alert
`Issue quantity must equal ordered quantity (25 pcs). Full fulfillment only.`
`updateTotals()` recomputes `preview-total-qty` and `preview-impact`
(`-{total} Units from Warehouse A ProductStock`) and per-row
`Remaining After Issue = AVAIL_STOCK[n] − qN`. The main button stays disabled in scenarios 2, 7, 8,
9 and 10 regardless of quantity validity.

**PO create/edit** — `recalculateTotals()` renumbers rows, computes each `subtotal = qty × price`,
sums `totalQty` and `grandTotal`, formats with `'Rp ' + toLocaleString('id-ID')`, and updates the
line-count badge, summary, modal total, and the mobile card mirror. `handleProductSelect()` writes
the selected option's `data-price` into the price input. `addNewLineItem()` appends a row using an
unused SKU from `PRODUCT_CATALOG` with default qty 10.

**SO create/edit** — `calculateTotals()` iterates `.item-row`, renumbers, computes
`qty*price` via `parseFloat(...)||0`, writes `Rp {locale}` per row, updates
`val-item-count` / `val-unit-count` / `val-grand-total` / `line-counter-chip` / `modal-submit-val`,
and toggles `err-items-empty` when the count reaches zero. `addNewProductLine()` performs a
**duplicate-SKU check** — an existing SKU has its qty incremented by 1 with a toast instead of
adding a second row (helper text: *"Duplicate SKUs are automatically consolidated into order quantity."*).

### 11.8 Simulated async timings (exact `setTimeout` values in the export)

| Screen | Delay | Transition |
|---|---|---|
| Login | 1200ms | loading → auth-error |
| Foundation | 800ms | token download toast chain |
| Foundation | 1200ms | sync button loading → success |
| Sales dashboard | 700ms | refresh icon spin |
| Reports (tabular) | 450ms | loading → generated |
| Reports-viz | 700ms | loading → DATA |
| Products list / Suppliers / Customers | 1600ms | skeleton → normal |
| Warehouses | 1400ms | skeleton → table |
| Customers | 800ms | sync → toast |
| Product create/edit | 1200ms | loading → success |
| PO create/edit | 1000ms | saving → draft |
| PO create/edit | 1200ms | saving → locked |
| Goods Receipt | 1200ms | processing → success |
| Goods Issue | 1200ms | processing → success |
| SO create/edit | 1500ms | saving draft |
| SO create/edit | 600ms | submit → pending |
| SO create/edit | 400ms | cancel → cancelled |
| SO detail | 1200ms | goods issue → Fulfilled |
| My Profile | 1000ms | save → toast |
| User create/edit | 1000ms | submit → alert + edit mode |
| Users | 3500ms | toast auto-hide |

### 11.9 Keyboard behavior present

- `Escape` closes modals: Categories (`window` `keydown` listener), Suppliers (closes both modals).
- Native `<dialog>` screens (Warehouses, Goods Receipt, SO detail) inherit `Escape` from the
  platform.
- Password toggles are `<button type="button">` with `aria-label="Show password"` ⇄ `Hide password`
  (login) or `aria-label="Toggle password visibility"` (User create/edit).
- No custom focus trap, no arrow-key table navigation, no keyboard shortcut layer exists in the export.

### 11.10 ARIA present

`aria-current="page"` (active nav link) · `aria-label` on `<aside aria-label="Scenario Simulator">`,
`aria-label="Simulation Bar"`, `aria-label="Breadcrumb"` / `"Breadcrumbs"`,
`aria-label="Interactive QA State Switcher"`, `aria-label="Report Filter Configuration"`,
`aria-label="Key Operational Summary Metrics"`, `aria-label="Visual Analytics & Trends"`,
`aria-label="Generated Stock Valuation Data Table"`, `aria-label="Logistics Metrics"`,
`aria-label="Sales Order Items"`, `aria-label="Testing and Simulator Controls"` ·
`aria-describedby` (login email/password) · `aria-hidden="true"` (login asterisk, decorative
chevrons) · `role="img"` + `aria-label` on the Reports-viz SVG · `role="tablist"` (Reports-viz viz
tabs) · `role="group"` (sales-dashboard button group) · `role="dialog"` + `aria-modal="true"`
(Stock Ledger detail modal) · `role="status"` / `role="alert"` / `aria-busy` / `aria-live` /
`aria-invalid` are **documented in the system-states registry (§13.2) but not applied** to the
generated markup outside the two cases above.

`th scope="col"` is present on Products list, Suppliers, Customers and Warehouses tables only.

---

## 12. Screen-by-screen detail

Each entry records the header copy, controls, data columns, seed values, and the states that screen
actually ships. Copy is verbatim.

### 12.1 Login

- **Title:** `Login - Inventory & Order Management System` (the only `<title>` in the export)
- **H1:** `Inventory & Order Management` · **Subtitle:** `Sign in to manage inventory and orders`
- **Top banner:** badge `IOMS AUTH GATEWAY v2.4` · `•` · `Internal Operations Environment`
- **Preview-state switcher** (label `Preview State:`): `Default` (active), `Field Validation`, `Auth Failed`, `Inactive Account`, `Signing In...` → `setState('default'|'validation'|'auth-error'|'inactive'|'loading')`
- **Fields:**
  - `Email *` — `type="email"`, `id/name="email"`, `autocomplete="email"`, placeholder `name@company.com`, default value `s.jenkins@enterprise-ioms.com`, `required`, `aria-describedby="email-error email-helper"`, classes `w-full h-9 px-3 text-xs bg-white text-[#0f172a] border border-[#cbd5e1] rounded focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb] outline-none transition-colors placeholder:text-[#94a3b8]`; error `Please enter a valid work email address`
  - `Password *` — `type="password"`, `autocomplete="current-password"`, placeholder `Enter password`, default `••••••••••••`, `required`, `pl-3 pr-10`; visibility toggle button `absolute right-0 top-0 h-9 px-2.5 text-[#64748b] hover:text-[#0f172a] focus:text-[#1e3a8a]` with inline eye / eye-slash SVGs; error `Password is required`
- **Submit:** `Sign In` → `Signing in...` with an inline `w-3.5 h-3.5 animate-spin` SVG
- **Alerts:**
  - Auth failed — `bg-[#fef2f2] border border-[#fecaca] text-[#dc2626]`, title `Authentication failed` (`text-[#991b1b]`), body `Invalid email or password. Please verify your credentials and try again.`
  - Inactive account — `bg-[#fffbeb] border border-[#fde68a] text-[#92400e]`, title `Unable to sign in`, body `This account is currently unable to sign in. Please contact your system administrator for assistance.`
- **Access note:** `Authorized access only. Accounts are provisioned and managed by your system administrator.`
- **Security footer:** `Enterprise Security Standard 256-bit TLS Encrypted` · `Session inactivity timeout: 30 minutes`
- **Form logic:** `handleFormSubmit` → if `!email || !email.includes('@') || !password` then `validation`; else `loading`, then after 1200ms `auth-error`
- **Seed emails used by states:** `operations.lead@enterprise-ioms.com` (auth-error), `clerk.inactive@enterprise-ioms.com` (inactive), `invalid-email-format` (validation), `s.jenkins@enterprise-ioms.com` (loading)

### 12.2 Dashboards

#### Admin / Operations Dashboard

- **H1:** `Operations Dashboard` + pill `LIVE SYNCED` (pulsing `bg-emerald-600` dot)
- **Subtitle:** `Real-time valuation, low-stock alerts, and pending procurement & fulfillment workflows.`
- **Date chip:** `Today: 24 Oct 2024 | Q4 Operational Period` (icon `calendar_today`)
- **Actions:** `Export Summary` (`file_download`), `Refresh Data` (`autorenew`)
- **KPI cards (4):**
  | Label | Value | Delta / sub |
  |---|---|---|
  | Inventory Value | `Rp 4.820.500.000` | `+1.8%` (`text-emerald-700`, `trending_up`) · `vs last month (3 warehouses)` |
  | Below Reorder Point | `8 SKUs` (`text-error`) | badge `Action Needed` · `Requires PO generation for replenishment` |
  | Pending Purchase Orders | `5 Orders` | `2 Draft` · `3 Ordered (Awaiting)` |
  | Pending Sales Orders | `7 Orders` | `4 Approval Pending` · `3 Ready to Ship` |
- **Low Stock Products table** (`xl:col-span-7`) — columns `Product Name & SKU` · `Warehouse` · `Current` (right) · `Threshold` (right) · `Deficit` (right) · `Status` (center) · `Action` (right); badge `8 Items` (`bg-error text-on-error`); rows:
  `Industrial Valve 50mm / SKU-IV-501 / Warehouse A (West Wing) / 12 / 40 / -28`;
  `Hydraulic Coupling 40mm / SKU-HC-402 / Warehouse B (East Wing) / 5 / 25 / -20`;
  `Reinforced Gasket Type-C / SKU-RG-109 / Warehouse A / 18 / 50 / -32`;
  `Stainless Steel Flange 2" / SKU-SF-202 / Warehouse C (Central) / 8 / 30 / -22`.
  Footer: `Replenishment suggestion: Minimum batch order of 110 combined units recommended.` + link `Create Purchase Order` (`add_circle`)
- **Pending Orders table** (`xl:col-span-5`) — tabs `All (12)` / `PO (5)` / `SO (7)`, counter `12`; columns `Order Ref & Type` · `Counterparty` · `Amount` (right) · `Status` (center) · `Action` (right); rows:
  `PO-2024-0891 / Apex Precision Machinery Ltd / Rp 142.500.000 / Ordered`;
  `SO-2024-1142 / Pacific Logistics Corp / Rp 88.200.000 / PendingApproval`;
  `SO-2024-1140 / Metro Infrastructures Ltd / Rp 315.000.000 / Approved`;
  `PO-2024-0888 / Titan Fasteners & Alloys / Rp 45.800.000 / Draft`;
  `SO-2024-1139 / Nexus Industrial Supplies / Rp 112.400.000 / PendingApproval`.
  Footer links `Manage PO Pipeline` / `Manage Sales Orders` (both `arrow_forward`)
- **Warehouse Network Stock Distribution table** — columns `Warehouse Name` · `Facility Code` · `Tracked Items` (right) · `Stock Valuation` (right) · `Low Stock SKUs` (center) · `Operational Status & Capacity` · `Action` (right); rows `h-12`:
  `Warehouse A (West Wing Hub) / Banten Industrial Cluster, Sector 4 / WH-JKT-01 / 1,420 SKUs / Rp 2.140.000.000 / 4 SKUs / Active (Optimal) 64%`;
  `Warehouse B (East Logistics Hub) / Rungkut Industrial Estate, Surabaya / WH-SBY-02 / 980 SKUs / Rp 1.620.500.000 / 3 SKUs / Active (Optimal) 52%`;
  `Warehouse C (Central Transit Depot) / Gedebage Freight Terminal, Bandung / WH-BDG-03 / 450 SKUs / Rp 1.060.000.000 / 1 SKU / Near Capacity 88%` (`bg-amber-600` bar, `text-amber-900 font-bold`).
  Footer bar: `Aggregate Network Total: 2,850 Tracked SKUs Across All Locations` · `Consolidated Valuation: Rp 4.820.500.000`
- **States shipped:** `normal`, `zero-stock`, `zero-orders`, `loading`, `error`. Error banner: `Unable to synchronize dashboard telemetry` / `Communication timed out while requesting live stock and order tallies from host service. Local replica cached at 09:41 AM.` Low-stock empty: `Optimal Inventory Balance` (`check_circle`, emerald) + `Audit Reorder Rules`. Orders empty: `Queue Clear` (`assignment_turned_in`).
- **Charts:** none — capacity bars only (CSS divs with inline `width: NN%`).

#### Sales Dashboard

- **H1:** `Dashboard` + badge `Representative View` · **Subtitle:** `Overview of your commercial sales orders, pipeline state, and pending authorization requirements.`
- **Actions:** live-sync chip `Personal Workspace • Live Synced` (`hidden sm:flex`), `Refresh` (`autorenew`, `title="Sync latest status from server"`), `Create Sales Order` (`add`)
- **KPI cards (5, `grid-cols-2 md:grid-cols-5`):** `My Draft Orders` 3 Active / `Pending submission for review`; `Pending Approval` 4 Orders / `Awaiting Admin sign-off`; `Approved` 6 Allocated / `Queued for warehouse pick`; `Fulfilled` 18 Completed / `Dispatched in period`; `Cancelled` 1 Voided / `Rejected / credit voided` (`col-span-2 md:col-span-1`)
- **Requires Attention queue** (`lg:col-span-4`) — count badge `7`; draft items get `Discard` + `Complete Draft` (`submitDraftForApproval`), pending items get `View Order`; info callout: `Sales accounts are strictly read-only during operational authorization. Only authorized managers or operations admins may issue approval tokens.`
- **My Recent Orders table** (`lg:col-span-8`) — columns `SO Number` · `Customer Name` · `Order Date` · `Items` · `Total Amount` (right) · `Status` (center) · `Actions` (right); toolbar search placeholder `Filter SO / Client...`, status select `All My Statuses` / `Draft` / `PendingApproval` / `Approved` / `Fulfilled` / `Cancelled`; rows:
  `SO-2024-1148 / PT Sumber Makmur Indah / Today, 09:15 / 4 SKUs / Rp 48.500.000 / Draft`;
  `SO-2024-1142 / Pacific Logistics Corp / 25 Oct 2024 / 8 SKUs / Rp 88.200.000 / Pending`;
  `SO-2024-1138 / PT Cahaya Mandiri Sentosa / 23 Oct 2024 / 12 SKUs / Rp 142.000.000 / Approved`;
  `SO-2024-1132 / Karya Abadi Manufaktur / 21 Oct 2024 / 2 SKUs / Rp 19.450.000 / Fulfilled`;
  `SO-2024-1129 / Surya Utama Distribusi / 19 Oct 2024 / 15 SKUs / Rp 264.800.000 / Fulfilled`;
  `SO-2024-1120 / Apex Precision Tools / 15 Oct 2024 / 1 SKU / Rp 12.000.000 / Cancelled`.
  Footer `Showing 6 of 32 total registered sales transactions` + `Previous` (disabled) · `1 / 6` · `Next`
- **Modal** `order-inspect-modal`: title `Sales Order: {soNumber}`, subtitle `Assigned Representative: Budi Santoso (Self-managed)`, action button label `Submit For Admin Approval` when Draft else `Export Manifest (PDF)` (initial static label `Print Order Summary`)
- **States shipped:** `normal`, `empty-all`, `empty-queue`, `skeleton`, `error`. Error: `Unable to load dashboard data` / `We encountered a temporary network disruption. Your draft orders are safely preserved on your device.` Empty-all: `No Sales Orders in Your Account` + `Draft First Order`. Queue empty: `All caught up!` (`task_alt`) / `No active draft orders or unacknowledged submissions require your immediate intervention.` Filter no-results: `No matching sales orders` (`filter_list_off`).
- **Charts:** none.

#### Warehouse Staff Dashboard

- **H1:** `Dashboard` + chip `Facility: WH-JKT-01 (West Wing Hub) • Live Synced` · **Subtitle:** `Overview of warehouse inventory and fulfillment operations`
- **Actions:** `Refresh Queue` (`sync`, `onclick="location.reload()"`), `+ New Purchase Order` (`add`, no handler)
- **KPI cards (4):** `Goods Receipts Pending` 5 Orders / `Queue Breakdown: 3 Ordered, 2 Partial`; `Goods Issues Pending` 8 Orders / `Ready for Fulfillment: Pick & Pack Queued`; `Low Stock Products` 6 SKUs (`text-error`) + badge `ACTION NEEDED` / `Threshold Deficit: Below reorder minimum`; `Warehouse Capacity & SKUs` 1,420 SKUs / `Occupancy: 64%` · `Active Optimal`
- **Goods Issue Queue** — header `Goods Issue Queue (8 Approved Sales Orders awaiting fulfillment)`; search placeholder `Filter SO or customer...`; select `All Statuses (Approved)` / `Priority Express` / `Standard Freight` (both non-functional); columns `SO Number` · `Customer Name` · `Source Warehouse` · `Order Date` · `Items & SKU Count` (right) · `Status` (center) · `Action` (right); rows `SO-2024-1142 / Pacific Logistics Corp / 25 Oct 2024 / 8 SKUs / 120 units`, `SO-2024-1138 / PT Cahaya Mandiri Sentosa / 23 Oct 2024 / 12 SKUs / 450 units`, `SO-2024-1135 / PT Duta Sarana Teknik / 22 Oct 2024 / 4 SKUs / 85 units`, `SO-2024-1131 / PT Sumber Makmur Indah / 22 Oct 2024 / 16 SKUs / 610 units` (all `APPROVED`, all Warehouse A); footer `Showing 4 of 8 pending goods issues` + `View all 8 sales orders →`
- **Goods Receipt Queue** — header `Goods Receipt Queue (5 Purchase Orders awaiting receiving)` + label `Inbound Bay Inspection Priority`; columns `PO Number` · `Supplier Name` · `Destination Warehouse` · `Order Date` · `Receiving Status` (center) · `Expected Delivery` · `Action` (right); rows `PO-2024-0891 / Apex Precision Machinery Ltd / 24 Oct 2024 / ORDERED / Today, 14:00 WIB`, `PO-2024-0890 / Surabaya Heavy Industries / 23 Oct 2024 / PARTIALLY RECEIVED (300/500) / Today, 16:30 WIB`, `PO-2024-0888 / Titan Fasteners & Alloys / 21 Oct 2024 / ORDERED / Tomorrow, 09:00 WIB`, `PO-2024-0884 / PT Nusantara Steel / 19 Oct 2024 / PARTIALLY RECEIVED (150/200) / 28 Oct 2024`
- **Low Stock Products** — header `Low Stock Products (6 SKUs below configured threshold in Warehouse A)` + `Warehouse staff visibility for stock replenishment coordination.` + badge `Automated Reorder Rule Active`; columns `Product Name & SKU` · `Storage Bin / Location` · `Warehouse` · `Current Stock` (right) · `Reorder Point` (right) · `Deficit` (right) · `Status` (center) · `Action` (right); rows `SKU-IV-501 Bin R-14-A 12/40/-28`, `SKU-HC-402 Bin B-08-C 5/25/-20`, `SKU-RG-109 Bin A-02-D 18/50/-32`, `SKU-HB-112 Bin C-22-B 24/60/-36`
- **States shipped:** `normal`, `empty-receipts`, `empty-issues`, `empty-low-stock`, `skeleton`, `connection-error`. Copy: `No goods receipts pending` / `There are no purchase orders currently waiting for receiving at Warehouse A.`; `No goods issues pending` / `There are no approved sales orders currently waiting for fulfillment or picking.`; `No low-stock products` / `All tracked products are currently above their reorder point in Warehouse A.`; `Unable to load dashboard data` / `Network timeout communicating with warehouse service endpoint. Please verify connection and retry.` (`wifi_off`)
- **Charts:** none — no progress bars either.

### 12.3 Master data screens

#### Products (list)

- **Breadcrumb:** `IOMS / Master Data / Products` · **H1:** `Products` + role badge `Admin Access` (`shield_person`)
- **Subtitle:** `Central catalog for SKUs, pricing definitions, reorder thresholds, and aggregated multi-facility stock.`
- **Actions:** `Export CSV` (`file_download`), `Add Product` (`add`, `#btn-add-product`)
- **Toolbar:** search `#search-input` placeholder `Search by product name or SKU...`; `Category:` select `#filter-category` — `all`=All Categories, `mech`=Mechanical & Valves, `hyd`=Hydraulics & Pneumatics, `fast`=Fasteners & Hardware, `elec`=Electrical Components, `seal`=Seals & Gaskets; `Stock Status:` select `#filter-stock` — `all`=All, `low`=Low Stock (< Reorder), `normal`=Normal; `Clear Filters (2)` button (hidden by default); meta `Showing 1–10 of 32 products`
- **Columns:** `Product` (28%) · `SKU` (12%) · `Category` (16%) · `Unit` (6%) · `Selling Price` (11%, right) · `Total Stock` (9%, right) · `Reorder Pt` (9%, right) · `Stock Status` (10%) · `Actions` (11%, right). Table `min-w-[960px]`.
- **Rows (10, verbatim):**
  | Product / spec line | SKU | Category | Unit | Selling Price | Stock | Reorder | Status |
  |---|---|---|---|---|---|---|---|
  | Industrial Gate Valve 50mm / Brand: PrecisionFlow • Cast Steel WCB | SKU-IV-501 | Mechanical & Valves | Pcs | Rp 450.000 | 12 | 40 | Low Stock (-28) |
  | Hydraulic Coupling 40mm / Brand: AeroQuip • Quick disconnect | SKU-HC-402 | Hydraulics & Pneumatics | Pcs | Rp 280.000 | 5 | 25 | Low Stock (-20) |
  | Reinforced Gasket Type-C / Brand: Flexitallic • High-temp graphite | SKU-RG-109 | Seals & Gaskets | Pcs | Rp 65.000 | 18 | 50 | Low Stock (-32) |
  | High-Tensile Bolt M12x50 / Grade 8.8 • Zinc yellow passivated | SKU-HB-112 | Fasteners & Hardware | Box | Rp 120.000 | 24 | 60 | Low Stock (-36) |
  | Stainless Steel Flange 2" / ANSI Class 150 • SS316 slip-on | SKU-SF-202 | Mechanical & Valves | Pcs | Rp 310.000 | 8 | 30 | Low Stock (-22) |
  | Heavy-Duty Ball Bearing 6205 / Brand: SKF • Deep groove rubber sealed | SKU-BB-625 | Mechanical & Valves | Pcs | Rp 95.000 | 145 | 50 | Normal |
  | Industrial Pressure Gauge 0-10 Bar / Brand: WIKA • Glycerin filled 1/4" NPT | SKU-PG-301 | Hydraulics & Pneumatics | Pcs | Rp 380.000 | 62 | 20 | Normal |
  | Hex Nut Zinc Plated M12 / DIN 934 • Standard metric coarse pitch | SKU-HN-115 | Fasteners & Hardware | Box | Rp 45.000 | 210 | 80 | Normal |
  | Nitrile O-Ring Seal Kit 200pcs / NBR 70 Shore • Metric assortment pack | SKU-OR-210 | Seals & Gaskets | Set | Rp 175.000 | 88 | 30 | Normal |
  | Pneumatic Air Hose 10mm (50m) / Polyurethane • Working pressure 10 Bar Blue | SKU-AH-510 | Hydraulics & Pneumatics | Roll | Rp 520.000 | 34 | 15 | Normal |
- **Row actions:** `View` · `|` · `Edit` (`.action-admin`) · `|` · `Deactivate` (`.action-admin`, `text-error`)
- **States shipped:** `admin`, `readonly` (hides Add + `.action-admin`, role text → `Warehouse / Sales (Read-Only)`), `filtered`, `empty-search`, `empty-catalog`, `skeleton`, `error`, `success`. Copy — empty-search: `No products found` / `No catalog items matched your active search query or filter parameters. Try revising terms or clearing active filters.` + `Clear All Filters`; empty-catalog: `Product catalog is empty` / `No master products have been registered in IOMS yet. Define your organization's first item with SKU, unit of measure, and initial stock baseline.` + `Create First Product`; error: `Unable to load product records` / `An error occurred while connecting to the core inventory service (503 Service Unavailable). Retry the transaction or consult system logs.` + `Retry Connection`; toast: `Product SKU-IV-501 (Industrial Gate Valve 50mm) was successfully updated.`

#### Categories

- **Breadcrumb:** `Master Data / Categories` · **H1:** `Categories`
- **Subtitle:** `Manage master product category taxonomy referenced across catalog items, ledger filters, and purchase orders.`
- **Action:** `Add Category` (`add`)
- **KPI cards (3):** `Active Categories` 4 / `Used across 1,842 catalog SKUs` (`category`); `Unclassified SKUs` 0 / `100% catalog coverage` (`verified`); `Last Taxonomy Update` `Today, 09:42 EST` / `by S. Jenkins (Admin)` (`history`)
- **Toolbar:** search placeholder `Search categories by name or description...` (`oninput="handleSearch(this.value)"`) + clear button; label `Showing 4 active categories` (becomes `Found N of M categories`)
- **Columns:** `Category Name` (w-1/4) · `Operational Scope & Description` (w-7/12) · `Actions` (w-1/6, right; retitled `Status / Access` in read-only mode). Rows are JS-rendered, `h-10 group`, name cell prefixed by a `folder` icon.
- **Seed data (4):** `Mechanical & Valves` — `Industrial flow control valves, high-pressure actuators, ANSI flanges, and piping schedule assemblies for fluid handling.`; `Hydraulics & Pneumatics` — `High-pressure hydraulic fittings, reinforced hoses, heavy pneumatic cylinders, and fluid power couplings.`; `Seals & Gaskets` — `Viton O-rings, spiral wound metallic gaskets, graphite sheet packing, and high-temp flanged sealing rings.`; `Fasteners & Hardware` — `Grade 8.8 / 10.9 metric and imperial hex bolts, structural nuts, threaded rods, spring washers, and anchor hardware.`
- **Missing-description fallback:** italic `No operational description provided.`
- **Modal fields:** `Category Name *` (helper `Max 60 chars`, placeholder `e.g., Electrical & Sensors`, helper text `Used as the primary taxonomy identifier in all SKU selectors and stock journals.`, error `Category name is required`); `Description (Optional)` (`maxlength="255"`, `rows="3"`, counter `0 / 255`, placeholder `Provide a clear operational description of items categorized here (e.g., pressure thresholds, material specs, standard packaging)...`, helper `Helps receiving clerks and purchasing agents classify items accurately.`). Duplicate alert: `Duplicate Category Name` / `A category with this name already exists in the master taxonomy. Category names must be unique across the enterprise.` Footer note `* Mandatory fields`.
- **Table footer notice:** `Master records are strictly audited. Category deletions are disabled to maintain historical ledger integrity.` · `IOMS-SYS-TAXONOMY-V2`
- **Read-only strip:** `Read-Only Mode: Sales & Warehouse Operator View` / `Category creation and modification permissions are restricted to System Administrators.` + `Switch back to Admin`
- **States shipped:** `admin-default`, `readonly`, `empty-search` (query `Pneumatics-X9`), `empty-catalog`, `loading`. Empty-search: `No categories found` / `No category names or descriptions match your query "…".`; empty-catalog: `No categories yet` / `Create master categories to organize physical stock, route purchase order requisitions, and organize catalog SKUs.`
- **No error state view** on this screen (only a validation-preview and a toast).

#### Warehouses

- **Breadcrumb:** `Master Data / Warehouses` · **H1:** `Warehouses`
- **Subtitle:** `Manage warehouse locations used for inventory operations across the logistics network.`
- **Actions:** `Add Warehouse` (`add`, `.admin-only`), read-only indicator `Read-Only Mode`
- **KPI cards (3):** `Active Warehouses` 3 facilities online / `Hub A (West), Hub B (East), Depot C (Transit)`; `Inactive Facilities` 1 archived / `Depot D - North Harbor Storage (Former)`; `Logistics Coverage` `3 Major Metros` / `Greater Jakarta, Surabaya, & Bandung`
- **Toolbar:** search placeholder `Search by warehouse name or location...`; counter `Showing 3 active warehouses (1 inactive)`; `Reload` (`hidden sm:inline-flex`)
- **Columns:** `Warehouse Name` · `Location / City` · `Status` · `Actions` (right)
- **Rows (4):** `Warehouse A - West Wing Hub / WH-JKT-01 • Primary Fulfillment / Jakarta Barat, DKI Jakarta / Active`; `Warehouse B - East Logistics Hub / WH-SBY-02 • Regional Distribution / Surabaya, Jawa Timur / Active`; `Warehouse C - Central Transit Depot / WH-BDG-03 • Transit / Staging / Bandung, Jawa Barat / Active`; `Warehouse D - North Harbor Storage (Former)` (`line-through`) `/ WH-TJP-04 • Decommissioned Facility / Tanjung Priok, Jakarta Utara / Inactive`
- **Row actions:** `View` · `|` · `Edit` · `|` · `Deactivate` (active rows) or `Activate` (inactive row)
- **Modals:** Add (`Warehouse Name *` placeholder `e.g. Warehouse E - South Fulfillment Center`, error `Warehouse name is required.`; `Location / City *` placeholder `e.g. Semarang, Jawa Tengah`, error `Location is required.`; `Initial Status *` radios Active (default) / Inactive) · Edit (prefilled Warehouse A, duplicate error `A warehouse with this name already exists.`) · Deactivate (`Deactivate warehouse?` / `{name} will no longer be available for active operational use, new purchase order deliveries, or sales order fulfillment.` + note `Historical transaction records and stock ledgers will be permanently preserved for compliance audits.`)
- **Governance footer:** `Physical Inventory Governance` / `Warehouse master records define operational facilities and spatial logistics boundaries. Physical stock balances, quantity adjustments, and bin-to-bin transfers are recorded strictly via verified Purchase Order Goods Receipts and Sales Order Goods Issues within the Stock Ledger.`
- **States shipped:** table, read-only, add modal, edit modal, deactivate modal, validation errors, empty-search, empty-catalog, loading skeleton, toast created, toast deactivated, error banner. Error banner copy: `Unable to save warehouse. Please review the information and try again.` Empty-search: `No warehouses found` / `No warehouse facilities matched your search query. Check for typos or reset your filter.` Empty-catalog: `No warehouses yet` / `Create a warehouse location to start configuring bin allocations, inbound PO receipts, and customer shipment fulfillments.`
- **Toast messages:** `Warehouse created successfully`, `Warehouse updated successfully`, `Warehouse deactivated successfully`, `Warehouse "{name}" activated for order fulfillment`, `Viewing inventory breakdown for Warehouse X`, `Viewing historical records for Warehouse X`, `Master supplier catalog reset` (Suppliers).

#### Suppliers

- **Breadcrumb:** `Master Data / Suppliers` · **H1:** `Suppliers` · **Subtitle:** `Manage supplier master data used for purchase orders across procurement operations.`
- **Action:** `Add Supplier` (`add`, `.admin-only`)
- **KPI cards (3):** `Active Suppliers` 5 `Vendors Registered` / `Raw materials, valves, hydraulic fittings & fasteners`; `Procurement Integration` `PO Enabled` + `Live` pill / `Vendors active for inbound purchase order drafting`; `Inactive / Archived` 1 `Vendor Decommissioned` / `Preserved for historical goods receipt & audit ledger`
- **Toolbar:** search placeholder `Search suppliers by name, email, or contact person...`; `Reset`; counter `Showing 5 active suppliers (1 inactive archived)`; status pills `All (6)` / `Active (5)` / `Archived (1)`
- **Columns:** `Supplier Name` (w-72) · `Contact Details` · `Primary Operational Address` (w-80) · `Status` (w-28, center) · `Actions` (w-44, right)
- **Rows (6):**
  | Name / code / segment icon | Contact | Address | Status |
  |---|---|---|---|
  | PT Sumber Jaya Teknik · SUP-00101 • Machining & Steel (`precision_manufacturing`) | Budi Santoso, +62 812-3456-7890, sales@sumberjaya.co.id | Kawasan Industri Jababeka Blok B No. 12, Cikarang, Bekasi, Jawa Barat 17530 | ACTIVE |
  | CV Indo Valve Mandiri · SUP-00102 • Industrial Valves & Controls (`valve`) | Rina Hartono, +62 21-558-9012, procurement@indovalve.com | Jl. Raya Daan Mogot Km. 14 No. 8, Cengkareng, Jakarta Barat 11730 | ACTIVE |
  | PT Baja Pratama Indonesia · SUP-00103 • Structural Steel & Rebar (`hardware`) | Hendra Wijaya, +62 811-9876-5432, orders@bajapratama.id | Kawasan Industri Modern Cikande Kav. 18, Serang, Banten 42186 | ACTIVE |
  | PT Hidrolik Global Utama · SUP-00104 • Hydraulic Cylinders & Hoses (`tune`) | Ahmad Syahril, +62 31-891-2345, ahmad.s@hidrolikglobal.co.id | Kawasan Rungkut Industri III No. 45, Surabaya, Jawa Timur 60293 | ACTIVE |
  | PT Sentosa Fastenerindo · SUP-00105 • High-Tensile Bolts & Rivets (`build_circle`) | Dewi Kusuma, +62 21-899-4411, dewi@sentosafasteners.com | Kawasan Industri MM2100 Blok LL-3, Cikarang Barat, Bekasi 17520 | ACTIVE |
  | PT Mitra Logam Abadi · SUP-00089 • Foundry & Ingot Castings (Archived) (`domain_disabled`) | Bambang Sutedjo, +62 21-7788-9900, b.sutedjo@mitralogam.com | Jl. Industri Raya IV No. 2, Jatake, Pasir Jaya, Kota Tangerang 15135 | INACTIVE |
- **Form fields:** `Supplier Legal Name *` (placeholder `e.g. PT Baja Pratama Indonesia`, error `Supplier name is required.`); `Key Contact, Phone & Email *` (placeholder `e.g. Hendra Wijaya • +62 811-9876-5432 • orders@bajapratama.id`, helper `Primary point of contact for PO approvals and dispatch notices.`, error `Contact information is required.`); `Operational Facility Address *` (`rows="3"`, placeholder `Street, industrial park, building number, city, and postal code...`, error `Address is required.`); `Operational Status` radios `Active (PO Allowed)` (default) / `Archived (Inactive)`
- **Deactivate modal:** `Deactivate supplier?` / `{name} will no longer be available for active purchase orders or procurement fulfillment.` + `Historical transactions, purchase logs, and inbound goods receipts will remain permanently preserved for compliance audits.`
- **Governance notice:** `Procurement Master Data Governance` / `Supplier master data is strictly referenced by Purchase Order Goods Receipts, Quality Control audits, and accounts payable workflows. Inactive suppliers cannot receive new Purchase Orders, but historical procurement records and stock batches are permanently preserved for audit compliance.`
- **States shipped:** normal, read-only, add/edit modal, validation errors, deactivate modal, empty-search, empty-catalog, loading, toast. Empty-search: `No suppliers found` / `No active or archived vendors match your search query. Check for typos or reset applied filters.` Empty-catalog: `No suppliers onboarded yet` / `Supplier master data is required before drafting Purchase Orders and receiving inventory into warehouse bins. Register your first industrial supplier.` + staff note `Read-only account: Contact Sarah Jenkins (Admin) to register new vendor accounts.`

#### Customers

- **Breadcrumb:** `IOMS / Master Data / Customers` · **H1:** `Customers` + badge `B2B Master Record`
- **Actions:** `Sync` (`sync`), `Add Customer` (`add_circle`), read-only badge `Read-Only View` (`visibility`)
- **KPI cards (3):** `ACTIVE CUSTOMERS` 6 + `Active Accounts` / `Commercial B2B buyers across manufacturing, infrastructure, and wholesale`; `SALES INTEGRATION` `SO Enabled` + `Live` / `Active for outbound sales order fulfillment & delivery scheduling`; `INACTIVE / ARCHIVED` 1 + `Archived Account` / `Preserved for historical sales orders and ledger audit trails`
- **Toolbar:** search placeholder `Search customers by name, contact person, or phone...`; pills `All (7)` / `Active (6)` / `Archived (1)`; counter `Showing 6 active customers (1 inactive archived)`; `Reset`
- **Columns:** `CUSTOMER NAME` (32%) · `CONTACT DETAILS` (26%) · `PRIMARY OPERATIONAL ADDRESS` (28%) · `STATUS` (7%, center) · `ACTIONS` (7%, right)
- **Rows (7):**
  | Name · code • segment | Contact | Address (+ sub-label) | Status |
  |---|---|---|---|
  | PT Mega Konstruksi Prima · CUST-00201 • Heavy Infrastructure | Ir. Bambang Prakoso, +62 21-5234-8890, procurement@megakonstruksi.co.id | Jl. Gatot Subroto Kav. 52, Jakarta Selatan 12950 (HQ & Engineering Site Depot) | ACTIVE |
  | CV Mandiri Cipta Perkasa · CUST-00202 • Mechanical Contractor | Siti Rahmawati, +62 22-7312-4401, sales@mandiricipta.com | Jl. Soekarno Hatta No. 418, Bandung 40266 (Central Workshop & Warehouse) | ACTIVE |
  | PT Surya Logam Abadi · CUST-00203 • Industrial Pipeline Systems | Kevin Tanuwidjaja, +62 31-8451-9923, k.tanu@suryalogam.co.id | Kawasan Industri Rungkut Blok F-12, Surabaya 60293 (East Java Staging Facility) | ACTIVE |
  | PT Barito Fabrikasi Teknik · CUST-00204 • Structural Engineering | Denny Hermawan, +62 541-7892-110, contact@baritofab.com | Jl. Yos Sudarso No. 88, Samarinda 75115 (Kalimantan Heavy Yards) | ACTIVE |
  | PT Nusantara Valve & Fitting · CUST-00205 • Fluid Handling Supplies | Maya Indriyani, +62 21-8983-4412, m.indriyani@nusantaravalve.co.id | Kawasan Industri MM2100 Blok C-3, Cikarang Barat 17530 (Logistics Dock 4) | ACTIVE |
  | CV Karya Bersama Mandiri · CUST-00206 • General Hardware Distributor | Agus Setiawan, +62 24-3511-7788, purchasing@karyabersama.co.id | Jl. Pandanaran No. 64, Semarang 50134 (Central Java Wholesale Hub) | ACTIVE |
  | PT Duta Sarana Baja (`line-through`) · CUST-00188 • Former Structural Client | Hendra Gunawan, +62 21-6623-9900, h.gunawan@dutasaranabaja.com | Jl. Kapuk Raya No. 15, Jakarta Utara (Historical Billing Entity) | INACTIVE |
- **Form fields:** `Customer Name *` (placeholder `e.g. PT Mandiri Solusi Teknik`); `Contact Details *` (placeholder `Contact Person, Phone Number (+62...), Email`, helper `Include operational coordinator name, direct phone, and e-invoice email.`); `Primary Operational Address *` (`rows="3"`, placeholder `Full street address, district, city, postal code for delivery logistics`); `Customer Lifecycle Status` radios `Active (Eligible for Sales Orders)` / `Inactive (Archived)`. Validation banner: `Please fix the following validation errors:` with items `Customer name is required`, `Contact details (Person, phone, or email) are required`, `Primary operational address is required`.
- **Detail modal:** `Customer Detail` — `Customer Account`, code, contact person / phone / email, `Primary Shipping / Operational Address`, `Lifecycle Status` badge, note `Referenced by Sales Order fulfillment. Read-only master record.`
- **Deactivate modal:** `Deactivate customer?` / `{name} will no longer be selectable for new Sales Orders or dispatch notes. Historical ledger records and past sales orders are permanently preserved.`
- **Governance banner:** `Enterprise Sales Order Master Data Governance Notice` / `Customer master data is referenced by Sales Orders, dispatch notes, and warehouse Goods Issues (SO). Inactive customers cannot be selected for new Sales Orders, but historical sales invoices and goods issue records remain permanently preserved for financial ledger auditing.`
- **States shipped:** normal, read-only, add/edit modal, detail modal, deactivate modal, validation errors, empty-search, empty-catalog, loading skeleton, toast. Empty-search: `No customers found` / `No master records match your search keyword. Adjust your filters or query term.` Empty-catalog: `No customers registered yet` / `Master customer records must be configured before Sales Orders and logistics dispatches can be created.`

#### Users

- **Breadcrumb:** `Administration` `chevron_right` `Users` · **H1:** `Users` + pill `Live Directory`
- **Subtitle:** `Manage enterprise application accounts, security credentials, and access provisioning.`
- **RBAC notice:** `RBAC Security Notice: Active administration restricted to authorized session leads. Self-deactivation is systematically enforced & locked.` + session pill `Session: SJ-9042`
- **Action:** `+ Add User` (`person_add`)
- **Toolbar:** search placeholder `Search name or email...`; `Role` select — `All Roles` / `Admin` / `Sales` / `Warehouse Staff`; `Status` select — `All Statuses` / `Active` / `Inactive`; `Clear Filters` (`restart_alt`); count `Showing 1–6 of 6 users`; export button (`download`, `title="Export Table (CSV)"`)
- **Columns:** `User` · `Email` · `Role` · `Status` · `Created` (right) · `Actions` (right); rows `h-11`
- **Rows (6):**
  | Initials / Name / sub-label | Email | Role | Status | Created | Last activity (modal only) |
  |---|---|---|---|---|---|
  | SJ · Sarah Jenkins + `YOU` · Active Session Lead | sarah.jenkins@ioms-enterprise.com | Admin | Active | 12 Jan 2024 | Today, 08:31 UTC |
  | BS · Bambang Sugianto · Central Warehouse Yard | b.sugianto@ioms-warehouse.com | Warehouse Staff | Active | 18 Feb 2024 | 14 Oct 2024, 11:20 UTC |
  | RP · Riko Pratama · B2B Regional Dispatch | riko.pratama@ioms-sales.com | Sales | Active | 05 Mar 2024 | 19 Sep 2024, 09:14 UTC |
  | MI · Maya Indrawati · Fulfillment Hub 2 | m.indrawati@ioms-warehouse.com | Warehouse Staff | Active | 22 Apr 2024 | 02 Nov 2024, 14:02 UTC |
  | HS · Hendrik Setiawan · Account Suspended | hendrik.s@ioms-sales.com | Sales | Inactive | 14 Jun 2024 | 15 Jul 2024, 16:45 UTC |
  | AF · Ahmad Fauzi · Co-Administrator | ahmad.fauzi@ioms-enterprise.com | Admin | Active | 01 Aug 2024 | Yesterday, 19:10 UTC |
- **Self-protection:** Sarah's row has `Deactivate` **disabled** (`text-outline/50 cursor-not-allowed bg-surface-container/50`) with tooltip `Self-deactivation protected by system security protocol.`
- **Modals:** `Deactivate user?` / `Deactivating {name} will immediately invalidate all active sessions and prevent the user from signing in.`; `Activate user?` / `Activating {name} will reinstate authorization and allow the user to sign in again.`; `User Details` (read-only 2×2 grid — `Assigned Role`, `Account Status`, `Creation Date`, `Last Activity Recorded` + callout `Authentication credentials and token hashes are encrypted in vault storage and never presented in administrative previews.`)
- **Pagination:** `Rows per page:` `10` / `25` (selected) / `50`; `Showing 1 to 6 of 6 entries`
- **States shipped:** `normal`, `self-protect`, `modal-deactivate`, `modal-activate`, `modal-view`, `loading`, `empty`, `forbidden`. Empty: `No Users Found` / `No enterprise accounts match your active search terms or filtered role/status combination.` + `Clear All Filter Criteria`. Forbidden: badge `HTTP 403: Forbidden`, heading `Administrative Privileges Required`, body `Your security role does not have permission to view or manage the enterprise user directory. This event has been dispatched to security compliance.` + `Return to Admin Session`
- **Mobile:** `md:hidden` card list with a `grid grid-cols-3 gap-2` action row at `min-h-[44px]`

#### User Create/Edit

- **Breadcrumb:** `Administration` → `Users` → dynamic (`Create User` / `Edit User` / `User Detail Not Found` / `Restricted Access`) · pill `Admin Governance Active` (`shield_person`)
- **H1:** `Create User` / `Edit User` · **Subtitle:** `Create an application user account.`
- **Sections:** 1. `Account Information` (`badge`) · 2. `Access & Governance` (`admin_panel_settings`) · 3. `Security Credentials` (`key`, badge `Required for Provisioning`, **create mode only**) or `Password Administration Policy` notice (`lock_clock`, **edit mode only**)
- **Fields:**
  | Field | Type | Placeholder / options | Error |
  |---|---|---|---|
  | `Full Name *` | text, `required` | `Enter full name` | `Full name is required.` |
  | `Email Address *` | email, `required` | `Enter email address` | `Valid enterprise email address is required.` |
  | `Role *` | select, `required` | `Select system role` (disabled/selected), `Admin`, `Sales`, `Warehouse Staff` | `System role assignment is required.` |
  | `Account Status` | radio cards | `Active` (checked) — `Granted login & operational access.` / `Inactive` — `Revoke all active system access.` | `You cannot deactivate your currently authenticated Administrator account.` |
  | `Password *` | password, `pr-9`, eye toggle | `Enter secure initial password`; helper `Minimum 8 characters with at least one number and special character.` | `Password does not meet complexity requirements.` |
  | `Confirm Password *` | password, eye toggle | `Re-enter password` | `Passwords do not match.` |
- **Edit-mode password notice:** `Password Administration Policy` / `For regulatory and compliance security, user authentication credentials cannot be viewed or altered directly within this management view. Authorized reset challenges or multi-factor token rotations must be executed through dedicated cryptographic credential workflows.` (password fields are **removed**, not disabled)
- **Self-lockout banner:** `Self-Account Governance Protection` / `You are currently editing your own administrator profile (Sarah Jenkins). Self-demotion and self-deactivation are strictly prevented to protect against administrative lockout.` Guards: role select reverts any non-Admin choice with an `alert()`; the Inactive radio is `preventDefault()`-ed and Active re-checked.
- **Duplicate email banner:** `Account Conflict Detected` / `An account with email riko.pratama@ioms-sales.com already exists in the system directory. Please provide a distinct corporate address.`
- **Footer:** `Cancel` · primary `Create User` (`person_add`) / `Save Changes` (`save`) / loading `Creating User Record...` (`sync` spinning)
- **Unsaved modal:** `Discard Unsaved Changes?` / `You have modified values in this user record. Leaving this form will permanently discard your changes.` — `Keep Editing` / `Discard & Exit`
- **404 view:** `User Record Not Found` / `The requested user ID does not exist in this tenant partition or has been permanently expunged from directory synchronization.` — `Return to Create Form` / `View All Users`
- **403 view:** `403 - Administrative Privilege Required` / `Access control policy restricted. Managing application user credentials and role delegations requires an active elevated System Administrator session.` — `Switch to Authenticated Admin Session`
- **Scenarios shipped (9):** Create Mode · Edit: Riko (Sales) · Edit: Admin (Self-Lockout) · Validation Errors · Duplicate Email · Submitting / Loading (seed `Tania Chen`) · Unsaved Changes · 404 Not Found · 403 Forbidden

### 12.4 Product detail / create-edit / warehouse stock detail

#### Product Detail

- **Breadcrumb:** `Products` / `Product Detail` / `SKU-IV-501` (mono, `text-primary-container`) · **H1:** `Product Detail` + `Active` badge + SKU pill `SKU: SKU-IV-501`
- **Subtitle:** `View product master data, pricing definitions, and aggregated multi-warehouse stock positions.`
- **Actions:** `Edit` (`edit`) · `Deactivate` (`block`, error-outline) · read-only pill `Read-Only Mode (Sales & Logistics)` (`visibility`)
- **Left column (`lg:col-span-7`):**
  - Identity card — 140×140 image frame (`w-36 h-36`, overlay label `140×140`); category badge `Mechanical & Valves` + `Standard Inventory`; H2 `Industrial Gate Valve 50mm`; description `PrecisionFlow • Cast Steel WCB • ANSI Class 150. Heavy duty flanged flow isolation valve engineered for petroleum, high-pressure steam, and utility water mains.`; spec badges `UoM (Unit)` → `pcs`, `Size / Rating` → `50mm / Class 150`, `Flange Facing` → `Raised Face (RF)`; metadata `Created: 12 Jan 2024, 09:30` (`calendar_today`), `Last Updated: 24 Oct 2024, 14:15 WIB` (`update`)
  - Pricing card (`payments`, tag `Standard IDR Tier`) — `Purchase Price` `Rp 320.000` / helper `Unit Acquisition Cost (Standard PO Valuation)`; `Selling Price` `Rp 450.000` (`text-primary-container`) / helper `Standard Commercial List Price (Excl. VAT)`
  - Replenishment card (`notification_important`, amber) — `Safety Reorder Point` `40 pcs`, description `Threshold trigger for automated operational replenishment warnings when aggregated or warehouse stock drops below this value.`
- **Right column (`lg:col-span-5`):**
  - Network Stock Position (`inventory`, `3 Facilities`) — `Aggregate Stock On Hand` + badge `Low Stock Warning`; value `12` `pcs`, delta `-28 pcs below target`; `Buffer Consumption: 30%` / `Target Reorder: 40 pcs`; facility rows `Warehouse A - West Wing Hub (Jakarta Barat • Bin R-14-A) 5 pcs Critical`, `Warehouse B - East Logistics Hub (Surabaya • Bin B-08-C) 7 pcs Low`, `Warehouse C - Central Transit Depot (Bandung • Bin C-12-D) 0 pcs Depleted`; policy note `Direct physical ledger edits are prohibited. Stock quantities update strictly via verified Purchase Order receipts or Sales Order picking passes.`
  - Quick-links card — `Stock Ledger Transactions` + `View Audit Log` (`arrow_forward`)
- **Stock by Warehouse table** — subtitle `Physical stock distribution across authorized logistics facilities. Stock adjustments occur exclusively via Goods Receipt (PO) or Goods Issue (SO).`, tag `Ledger Mode: Real-time`; columns `Warehouse Facility` · `Location / City` · `Bin / Rack Reference` · `Quantity On Hand` (right) · `Reorder Threshold` (right) · `Operational Status` (center) · `Last Physical Movement` (right); rows as above with movements `24 Oct 2024, 11:20` / `22 Oct 2024, 16:45` / `19 Oct 2024, 08:12`; `tfoot h-11`: `Total Network Stock (3 Facilities Registered)` · `12 pcs` · `40 pcs ref` · `Ledger Balanced • No Open Discrepancies`
- **Deactivate modal:** `Deactivate this product?` / `The product Industrial Gate Valve 50mm (SKU-IV-501) will no longer be available for new sales orders or purchase orders, but existing historical records and order line items will be preserved.` + info `12 physical units remain distributed across 2 warehouses.`
- **404 view:** label `Error 404 • Resource Not Found`, heading `Product Not Found`, body `The product record with SKU identifier SKU-IV-501 does not exist, was purged from the catalog database, or you have insufficient warehouse permission.` — `Back to Products Catalog` / `Inquire with Admin`
- **States shipped:** `admin`, `readonly`, `inactive` (Deactivate becomes `Reactivate`/`check_circle`), `nostock`, deactivate modal, `skeleton`, `404`, success toast (`Product updated successfully` / `Inventory ledger and catalog sync complete.` / `Product SKU-IV-501 has been set to Inactive.`)

#### Product Create/Edit

- **Breadcrumb:** `Products` / dynamic action / SKU · **H1:** `Edit Product` (default) or `Create Product` · status chip `Active`
- **Subtitle:** `Update product information and operational reorder settings`
- **Context card:** `Security Scope` → `Sarah Jenkins (Admin)` (`verified_user`) · `Stock Ledger` → `Master-Data Locked` (`lock`)
- **Fields:**
  | Field | Type | Detail |
  |---|---|---|
  | `SKU Code *` | text, `readonly` in edit | value `SKU-IV-501`, lock pill `Read-Only`, helper `SKU cannot be altered after creation to preserve transaction ledger integrity.`; in create mode editable with placeholder `e.g. SKU-ME-102` and helper `A unique identifier for this product.`; error `This SKU is already in use by another product.` |
  | `Product Name *` | text, `required` | value `Industrial Gate Valve 50mm`; error `Product name is required.` |
  | `Category *` | select | `valves`=Mechanical & Valves (selected), `hydraulics`=Hydraulics & Pneumatics, `seals`=Seals & Gaskets, `fasteners`=Fasteners & Hardware |
  | `Base Unit of Measure *` | select | `pcs (Pieces)` (selected), `box (Box / Carton)`, `kg (Kilograms)`, `set (Complete Set)`, `roll (Continuous Roll)`, `unit (Standard Unit)` |
  | `Purchase Price *` | number `min=0 step=500` | `Rp` prefix, value `320000`, helper `Unit acquisition cost for valuation.`; error `Enter a valid non-negative number.` |
  | `Selling Price *` | number `min=0 step=500` | `Rp` prefix, value `450000`, helper `Standard commercial catalog list price.` |
  | `Reorder Point *` | number `min=0` | value `40` (create default `10`), unit badge `PCS`, helper `Products below this aggregate quantity trigger Low Stock alerts in procurement logs.` |
  | `Product Lifecycle Status` | segmented `Active` / `Inactive` | helper `Inactive items cannot be selected for new Sales Orders or Purchase Orders.` |
- **Computed display:** `Gross Margin Calculation:` → `Rp 130,000` + badge `+28.89%`
- **Immutability notice:** `Immutable Stock Architecture` / `Physical inventory quantities are managed exclusively via verified Purchase Order Goods Receipts and Sales Order Goods Issues. Stock quantities cannot be manually edited in product master data.`
- **Image section:** existing preview — filename `industrial-gate-valve-50mm.png`, meta `142 KB • 800 × 800 px`, buttons `Replace` (`change_circle`) / `Remove` (`delete`); dropzone — `Click to upload or drag & drop`, `PNG, JPG, or WEBP (Max 5MB)`; error `File size exceeds 5MB limit. Please select a smaller file.`; helper `Supported formats: PNG, JPG, WEBP up to 5MB. 1:1 aspect ratio recommended.`
- **Sticky footer:** `Last modified: Oct 24, 2024 at 14:18 PM by S. Jenkins` (`history`) · `Cancel` · `Save Changes` / `Create Product` (`save`) → loading `Saving changes...`
- **Banners:** validation `Unable to save product` / `Please review the highlighted fields below and resolve the validation constraints before continuing.`; success `Product updated successfully` / `Changes committed to catalog database. Redirecting to Product Detail in 2s...`
- **Unsaved modal:** `Discard unsaved changes?` / `You have unsaved changes in this form. Leaving this page will discard all edits made to the product specifications.` + note `Catalog version: Draft revisions will not be committed to audit history.` — `Continue Editing` / `Discard Changes` (`delete_forever`)
- **Scenarios shipped (6):** `1. Edit Mode (Active SKU-IV-501)` · `2. Create Mode (Blank)` · `3. Validation Errors` · `4. Saving State` · `5. Unsaved Changes Modal` · `6. Success State`

#### Warehouse Stock Detail

- **Breadcrumb:** `Master Data` / `Warehouses` / dynamic (`Warehouse Detail`) + `Back to Warehouses`
- **H1:** `Warehouse A (West Wing Hub)` + code pill `WH-JKT-01` + status pill `Active Facility`
- **Address:** `Kawasan Industri Daan Mogot, Blok B4 No. 12, Jakarta Barat, 11840` · `Zone A1 • 4,800 m² Capacity`
- **Actions:** `Edit Warehouse Metadata` (`edit_note`) · `Stock Ledger Logs` (`history`)
- **Governance disclaimer:** `Physical Stock Governed by Immutable Ledger` / `Quantities stored at this facility update strictly via validated Goods Receipts (PO) and Goods Issues (SO). Direct or inline stock value editing is prohibited by system audit policy.` + pill `Read-Only Stock` (`lock`)
- **KPI cards (4):** `Warehouse Profile` → `Warehouse A` / `Jakarta Barat • Hub Utama` / `Rack Fill Rate: 74.2% Utilized`; `Products Stored` → `28` SKUs / `Across 4 active catalog divisions` / `Fast Movers: 12 High Turnover`; `Total On-Hand` → `1,420` PCS / `Aggregate local inventory balance` / `Valuation (Est.): IDR 482.50M`; `Low Stock Alert` → `3` SKUs Depleted / `Below local reorder thresholds` / `Create PO →`
- **Facility metadata card:** `Warehouse Facility Specifications` / `Administrative metadata and physical plant allocation parameters.` / `Last Audit: 14 Oct 2024`; 3 columns — `Facility Custodian / Manager` → `BS` avatar + `Bambang Sugianto` + `(+62 812-9901-2291)`; `Dedicated Storage Types` → `Heavy Machining, High-Rack Racks A1-C8, Climate Controlled Area D`; `Operating Hours & Inbound Gate` → `Mon–Sat 07:30 – 19:00 WIB • Dock 1 & Dock 2 Active`
- **Toolbar:** search placeholder `Search products in this warehouse by name or SKU...`; `All Stock Statuses` / `Low Stock Only` / `Normal Stock`; `All Categories` / `Mechanical & Valves` / `Hydraulics & Pneumatics` / `Seals & Gaskets` / `Fasteners & Hardware`; `Clear` (`restart_alt`); counter `Showing 1–10 of 28 products`; `Export CSV`
- **Columns:** `Product Name & Description` · `SKU Code` · `Category` · `Stock (WH-A)` (right) · `Reorder Pt` (right) · `Status` (center) · `Action` (right)
- **`WAREHOUSE_A_ITEMS` (10):** SKU-IV-501 5/40 (Low, -35) · SKU-HC-402 2/25 (Low, -23) · SKU-RG-109 6/50 (Low, -44) · SKU-HB-112 120/60 (Normal) · SKU-SF-202 45/30 (Normal) · SKU-BB-625 80/50 (Normal) · SKU-PG-301 32/20 (Normal) · SKU-HN-115 350/80 (Normal) · SKU-OR-210 55/30 (Normal) · SKU-AH-510 18/15 (Normal)
- **Inactive-facility banner:** `Warehouse Operationally Inactive` / `This storage site is decommissioned or paused for regular dispatches. No physical Goods Receipts or Shipments can be allocated to this facility code until reactivated by a Logistics Director.` (inactive scenario retitles to `Warehouse D (North Harbor Storage)` / `WH-JKT-04`)
- **Metadata modal:** `Edit Facility Metadata` — `Warehouse Facility Code` (disabled, `WH-JKT-01`, helper `Unique immutable facility identifier.`), `Warehouse Name *`, `Address & Location *` (`rows="2"`), `Lead Custodian`, `Operational Status` (`Active` / `Under Maintenance` / `Decommissioned / Inactive`); notice `Stock Safety Notice` / `Product inventory counts cannot be adjusted here. Use official Goods Receipts or Physical Stock Count sessions to rectify ledger numbers.`
- **404 view:** label `Error 404 • Facility Not Found`, heading `Warehouse Record Does Not Exist`, body `The requested facility ID "WH-UNKNOWN-99" was archived, decommissioned, or does not exist in the Master Data registry.`
- **Empty states:** filter — `No Products Match Current Criteria` / `No inventory items in Warehouse A correspond to the active keyword or category combinations.`; zero stock — `No Stock Recorded for this Facility` / `There are currently 0 SKU line items physically stationed at this warehouse facility. Stock balances initialize automatically when a Goods Receipt (PO) is confirmed into this warehouse code.` + `Receive PO to This Facility` / `View Sample Stock`
- **Scenarios shipped (7):** `Active Facility (Default)` · `Low Stock Only` · `Inactive Facility` · `Zero Stock Records` · `No Filter Matches` · `Loading Skeleton` · `Facility 404`

### 12.5 Procurement screens

#### Purchase Orders (list)

- **Breadcrumb:** `Procurement / Purchase Orders` · **H1:** `Purchase Orders`
- **Subtitle:** `Manage purchasing workflows, supplier delivery verification, and physical warehouse intake.`
- **Action:** `Create Purchase Order` (`add_circle`, `h-9 px-4 rounded-xl`)
- **KPI tiles (4, `rounded-xl`):** `Open POs` `18 Active Orders` / `Allocated to 12 suppliers` (`pending_actions`); `Awaiting Receipt` `7 Dock Ready` / `Arrival priority: high` (`inventory`); `Partially Received` `4 Inbound Logs` / `Backorder dispatch pending` (`fact_check`); `Fulfilled / Closed` `42 Archived POs` / `Recorded in Stock Ledger` (`verified`)
- **Toolbar:** search placeholder `Search by PO number or supplier...`; status select `All Statuses` / `Draft` / `Ordered` / `Partially Received` / `Received` / `Cancelled`; sort select `Order Date: Newest First` / `Order Date: Oldest First`; `Clear Filters`; counter `Showing 1–10 of 25 purchase orders`
- **Columns:** `PO Number` · `Supplier` · `Destination Facility` · `Order Date` · `Status` · `Items / Inbound Progress` · `Operational Actions`. Table `min-w-[900px]`.
- **Rows (10):**
  | PO | Supplier (spec line) | Facility | Date | Status | Progress | Actions |
  |---|---|---|---|---|---|---|
  | PO-2024-0018 | PT Sumber Jaya Teknik (Industrial Valves & Sealings • Cikarang) | Warehouse A (West Wing Hub) • WH-JKT-01 | 18 Oct 2024 | Partially Received | 60 / 100 (60%) | Receive Goods · View |
  | PO-2024-0017 | CV Indo Valve Mandiri (Pneumatic Actuators • Surabaya) | Warehouse B (East Logistics) • WH-SBY-02 | 17 Oct 2024 | Ordered | 0 / 80 received · Awaiting Dock (0%) | Receive Goods · View |
  | PO-2024-0016 | PT Baja Pratama Indonesia (Carbon Steel Flanges • Cilegon) | Warehouse A • WH-JKT-01 | 15 Oct 2024 | Received | 150 / 150 received (100%) | View |
  | PO-2024-0015 | PT Hidrolik Global Utama (Hydraulic Fittings • Tangerang) | Warehouse C (Transit) • WH-BDG-03 | 14 Oct 2024 | Draft | 4 line items (Unsubmitted) | Edit / View |
  | PO-2024-0014 | PT Sentosa Fastenerindo (Hex Bolts Grade 8.8 • Bekasi) | Warehouse A • WH-JKT-01 | 12 Oct 2024 | Partially Received | 1,200 / 2,000 (60%) | Receive Goods · View |
  | PO-2024-0013 (`line-through`) | CV Indo Valve Mandiri (Ball Valves Stainless 316 • Surabaya) | Warehouse B • WH-SBY-02 | 11 Oct 2024 | Cancelled | 0 / 45 received (Voided) | View |
  | PO-2024-0012 | PT Sumber Jaya Teknik (Flange Gaskets Spiral • Cikarang) | Warehouse A • WH-JKT-01 | 09 Oct 2024 | Ordered | 0 / 300 received · In Transit (0%) | Receive Goods · View |
  | PO-2024-0011 | PT Baja Pratama Indonesia (Structural Seamless Pipe • Cilegon) | Warehouse A • WH-JKT-01 | 08 Oct 2024 | Received | 40 / 40 received (100%) | View |
  | PO-2024-0010 | PT Hidrolik Global Utama (Cylinder Seals PTFE • Tangerang) | Warehouse B • WH-SBY-02 | 05 Oct 2024 | Partially Received | 25 / 50 (50%) | Receive Goods · View |
  | PO-2024-0009 | PT Sentosa Fastenerindo (Stainless Washers M12 • Bekasi) | Warehouse C • WH-BDG-03 | 01 Oct 2024 | Received | 5,000 / 5,000 (100%) | View |
- **Governance banner:** `Procurement & Inbound Stock Ledger Integration` / `Purchase Orders establish inbound physical inventory commitments from certified suppliers. Validated Goods Receipts (GR) automatically update ProductStock balances and record immutable entries in the global Stock Ledger for facility audit integrity.`
- **Toast:** `Purchase Order Created` / `PO-2024-0019 has been initialized and awaiting supplier dispatch.`
- **States shipped:** roles `Admin View` / `Warehouse Staff` / `Sales View`; states `Normal (10 Records)` / `Skeleton` / `Empty` / `No Matches` / `Error State` / toast. Empty: `No purchase orders yet` / `Create a purchase order to start tracking incoming inventory across designated warehouse hubs.` (Sales role → `No purchase orders available. There are currently no purchase orders to display.`). No-match: `No matching purchase orders` / `No purchase orders found matching your search or filters. Try adjusting keywords or clearing active filters.` Error: `Unable to load purchase orders` / `A temporary network glitch or inventory service timeout occurred. Please try again.`
- **Role gating:** Sales hides `#createPoContainer`, hides `.action-receive`, and switches `.action-draft-edit` text from `Edit / View` to `View`.

#### Purchase Order Detail

- **Breadcrumb:** `IOMS / Procurement / Purchase Orders / PO-2024-0014` · **H1:** `PO-2024-0014` (`font-mono-data-md`)
- **Subtitle:** `Standard Domestic Reorder • Fasteners & Structural Bolts • Created Oct 12, 2024` · storage chip `Storage Node: WH-JKT-01`
- **Fulfillment pill:** `1,200 / 2,000 Pcs (60%)`
- **Header actions (status/role gated):** `Edit Order` (`edit`, Draft+Admin) · `Order Purchase` (`send`, Draft) · `Receive Goods` (`move_to_inbox`, Ordered/PartiallyReceived) · `Cancel Order` (`block`, Draft/Ordered only — source comment: **"STRICTLY HIDDEN in PartiallyReceived, Received, Cancelled"**) · print icon button (`print`) · role pill `Sales Role • Read Only` (`lock`) which suppresses all four actions
- **Governance banner:** `Enterprise Stock Governance Protocol:` + link `Ledger Specs` (`open_in_new`)
- **Metadata cards (3):**
  - `Supplier Information` (`SUP-00105`) — `PT Sentosa Fastenerindo` / `Tier-1 Industrial Fasteners & Heavy Hardware` / `Dewi Kusuma (Procurement Liaison)` / `+62 21-899-4411` / `dewi@sentosafasteners.com` / `Kawasan Industri MM2100 Blok LL-4, Cikarang Barat, Jawa Barat 17530` / footer `Payment Terms: Net 30 Days (Direct Invoice)`
  - `Destination Facility` (`WH-JKT-01`) — `Warehouse A (West Wing Hub)` / `Bulk Pallet & High-Rack Storage Operations` / `Kawasan Pergudangan Daan Mogot Blok B4 No. 12, Kalideres, Jakarta Barat 11840` / `Assigned Dock: Gate 1 & Gate 2 (Inbound)` / `Receiving Zone: Bay-Alpha Heavy Pallets` / footer `Facility Supervisor: Hendra Wijaya (Shift A)`
  - `Audit & Schedule` (`Doc Ver 2.1`) — `Order Date 12 Oct 2024`, `Target ETA 16 Oct 2024`, `Created By Sarah Jenkins (Admin)`, `Approval Officer B. Santoso (Procurement VP)`, `Tracking Reference SF-EXP-992014`, footer link `View Stock Ledger Entries` + `2 receipts`
- **Receiving progress:** `Inbound Intake & Fulfillment Progress` (`fact_check`), alert `Inbound intake active: 800 units pending dock intake...`, two-segment bar 60% `bg-primary-container` / 40% `bg-surface-container-high`; tiles `Total Ordered 2,000`, `Total Received 1,200` / `60.0% Verified at Dock`, `Remaining to Receive 800` / `Pending Shipment Batch #2`
- **Line items (`2 Items`, `Currency: IDR (Indonesian Rupiah)`):** columns `Product Description` · `SKU Identifier` · `Ordered Qty` · `Received Qty` · `Remaining Qty` · `Unit Price` · `Line Subtotal`
  - `Hex Bolt M12 x 50mm High-Tensile Steel` (tag `Grade 8.8 Galvanized`, `Packaging: Box of 100 pcs`) / SKU-HB-112 / 1,500 pcs / 900 pcs / 600 pcs / Rp 8,500 / Rp 12,750,000
  - `Hex Nut M12 Zinc Plated (Matching DIN 934)` (`Standard Class 8`, `Packaging: Polybag 250 pcs`) / SKU-HN-115 / 500 pcs / 300 pcs / 200 pcs / Rp 3,200 / Rp 1,600,000
  - Footer: `Subtotal strictly governed by Unit Price × Ordered Qty. Tax & logistics calculated separately.` · `Total Quantity 2,000 pcs (Rec: 1,200 | Rem: 800)` · `Total Purchase Value IDR 14,350,000`
- **Goods Receipt entry banner:** `Warehouse Receiving Session` + `Launch Dock Intake` (`add_task`)
- **Modals:** `Transmit Purchase Order` (subtitle `PO-2024-0014 • PT Sentosa Fastenerindo`, body references `IDR 14,350,000`, 3 state-transition bullets, confirm `Transmit & Place Order`) · `Cancel Purchase Order?` (field `Cancellation Reason *`, placeholder `e.g., Supplier lead time unacceptable, duplicate order...`, buttons `Abort` / `Void & Cancel PO`)
- **404 view:** `Purchase Order Not Found` (references `PO-2024-0014`) — `Back to Purchase Orders` / `Reset View`
- **Error banner:** `Ledger Sync Anomaly (ERR-SYNC-409)` + `Retry Sync`
- **QA controls:** status select (PartiallyReceived default / Draft / Ordered / Received / Cancelled) · role select (Admin / Sales) · view state select (normal / skeleton / error-banner / not-found / modal-order / modal-cancel / toast-success) · `Reset`

#### Purchase Order Create/Edit

- **Breadcrumb:** `Procurement / Purchase Orders / {dynamic}` · **H1:** `Edit Purchase Order PO-2024-0019` · status badge `Draft`
- **Quick actions:** `Reset` (`refresh`), `Simulate Errors` (`bug_report`, rose)
- **Section A — `Purchase Order Information`** (`grid grid-cols-1 md:grid-cols-2 gap-5`, note `Required Fields *`):
  | Field | Options / value |
  |---|---|
  | `Certified Supplier *` | `Select an active certified vendor...` · SUP-00101 PT Sumber Jaya Teknik (Machining & Steel) **selected** · SUP-00102 CV Indo Valve Mandiri · SUP-00103 PT Baja Pratama Indonesia · SUP-00104 PT Hidrolik Global Utama · SUP-00105 PT Sentosa Fastenerindo. Helper `Only active certified suppliers are eligible for operational procurement.` Error `Supplier is required.` |
  | `Destination Warehouse *` | WH-JKT-01 Warehouse A **selected** · WH-SBY-02 Warehouse B · WH-BDG-03 Warehouse C. Helper `Inbound goods receipt will record physical intake into this facility ledger.` Error `Destination warehouse is required.` |
  | `Order Date *` | `type="date"` value `2024-10-24`. Helper `Effective commercial agreement binding date.` |
  | `PO Reference Number` | readonly `PO-2024-0019` with overlay `READONLY`. Helper `System identifier assigned per ERP procurement sequencing.` |
- **Section B — `Procurement Items`** (badge `3 Items`, subtitle `Configure SKU identifiers, planned intake quantities, and contracted rates.`, `Add Product` / `add_circle`):
  columns `#` · `Product Description & SKU` · `Intake Qty` · `Unit Price (IDR)` · `Line Subtotal` · `Action`
  | # | SKU / name / category | Qty | Price | Subtotal |
  |---|---|---|---|---|
  | 1 | SKU-IV-501 Industrial Gate Valve 50mm · Cat: Mechanical & Valves | 40 (step 1) | 320000 (step 500) | Rp 12,800,000 |
  | 2 | SKU-HB-112 High-Tensile Bolt M12x50 · Cat: Fasteners & Hardware | 500 (step 1) | 14500 (step 100) | Rp 7,250,000 |
  | 3 | SKU-RG-109 Reinforced Gasket Type-C · Cat: Seals & Gaskets | 80 | 45000 (step 1000) | Rp 3,600,000 |
  `PRODUCT_CATALOG` also contains SKU-PP-882 (750000) and SKU-FL-304 (185000).
  Empty state: `No Procurement Items Added` (`shopping_bag`) + `Add First Product`
- **Section C — summary:** disclaimer `Inbound Valuation & Ledger Integrity Protocol` + mono footer `LEDGER SYNC: INBOUND_PLANNED • VALUATION METHOD: WEIGHTED_FIFO`; `Total Line Items: 3 Line Items`, `Total Intake Quantity: 620 Units`, `Total Valuation Rp 23,650,000` / `IDR (EXCL. TAX)`
- **Sticky footer:** `Created by S. Jenkins (Admin) • Draft rev 1` (`history_edu`) · `Cancel` · `Save Draft` (`save`) · `Order Purchase` (`send`)
- **Confirm modal:** `Place this purchase order?` / `The purchase order PO-2024-0019 will move from Draft to Ordered. Certified supplier PT Sumber Jaya Teknik will be dispatched commitment records and Warehouse A will await dock receipt.` + `Valuation Commitment: Rp 23,650,000` — `Cancel` / `Confirm & Place Order` (`arrow_forward`)
- **Locked banner:** `This Purchase Order is Locked (Status: Ordered)` + `View PO Ledger` / `Go to Goods Receipt` (`warehouse`)
- **Validation banner:** `Validation Error: Unable to complete operation.` with the list `Supplier certification status must be selected.` · `Inbound destination warehouse facility is mandatory.` · `Line items must contain valid positive quantities and unit prices.`
- **Empty-catalog banner:** `Master Data Deficiency Detected` / `No certified suppliers or active master product SKUs were returned from the operational catalog. Contact procurement master data admins before proceeding.`
- **Scenarios shipped (8 buttons / 6 modes):** `1. Create Blank` · `2. Edit Draft (SO-2024-0025)` · `3. Pending Approval` · `4. Approved` · `5. Validation Errors` · `6. Submit Modal` · `7. Cancel Modal` · `8. Loading State` · `9. Empty Catalogs` · `10. Test Toast` (modes: `create` / `draft` / `locked` / `errors` / `saving` / `emptyCatalogs`)
- **Loading labels:** `Saving...` / `Processing...` with `progress_activity animate-spin`

#### Goods Receipt

- **Breadcrumb:** `IOMS / Procurement / Purchase Orders / PO-2024-0014 / Goods Receipt` + `Back to PO-2024-0014`
- **H1:** `Receive Goods` · header status `Partially Received` · warehouse chip `WH-JKT-01` · role pill `Authorized Role: Admin & Receiving Lead`
- **Subtitle:** `Record physical inventory verified at the destination dock against purchase order lines. Line entries will reflect live dock counts and increment warehouse balances upon validation.`
- **Info cards (3):** `Source Order` — PO-2024-0014, `Order Date 12 Oct 2024`, `Payment Terms Net 30 Days`, `Expected Delivery 18 Oct 2024 (On-Time)`; `Certified Supplier` — SUP-00105, `PT Sentosa Fastenerindo`, `Dewi Kusuma (Logistics Dispatch)`, `+62 21-899-4411`; `Destination Facility` — WH-JKT-01, `Warehouse A (West Wing Hub)`, `Receiving Zone: Bay-Alpha Heavy Pallets / Gate 1 & 2`, `Kawasan Industri Pulogadung, Jakarta Timur`
- **Available-stock / governance callout:** `Enterprise Stock Governance & Immutable Audit Trail` (`balance`)
- **Progress card:** `Fulfillment Progress` / `Overall Order Intake Status` + `60.0% Complete`; batch counter `600 Pcs Selected` (`add_box`); two segments `bg-primary` 60% (`Previously Verified (1,200 pcs)`) + `bg-surface-tint` 30% (`Current Receipt Intake (600 pcs)`); legend `Previously Verified (1,200 Pcs)` / `This Intake (600 Pcs)` / `Remaining Balance (200 Pcs)`; right label `Total 2,000 Pcs`
- **KPI tiles (4):** `Total Ordered 2,000 Pcs` / `2 Discrete SKUs`; `Previously Received 1,200 Pcs` / `Verified at Gate 1`; `Remaining Intake 800 Pcs` / `Eligible for receiving`; `Pending Post-Intake 200 Pcs` / `Requires Intake #3`
- **Line items:** helper `Remaining After Issue = ...` n/a here; toolbar `Receive All Remaining` (`done_all`) / `Clear All` (`clear`); columns `#` · `Product & Specification` · `SKU` · `Ordered` · `Prev. Received` · `Remaining` · `Receive Qty` · `Impact Preview`
  - Row 1: `Hex Bolt M12 x 50mm Grade 8.8` / `High Tensile Carbon Steel • DIN 933 Compliant` / SKU-HB-112 / 1,500 / 900 / 600 / input `400` (`min=0 max=600`, ± 50, `Max` button `title="Fill all 600"`) / `Partial (+400)`
  - Row 2: `Hex Nut M12 Zinc Plated` / `Electro-galvanized Finish • Class 8 Metric` / SKU-HN-115 / 500 / 300 / 200 / input `200` (`max=200`) / `Complete (+200)`
- **Stock impact summary:** `Target Destination Warehouse A (West Wing)` / `WH-JKT-01 • Zone Alpha`; `Products Receiving 2 Line Items` / `Both active in Catalog`; `Units in This Session 600 Pcs` / `400 Hex Bolt + 200 Hex Nut`; `Post-Receipt PO Status Partially Received (90%)` / `1,800 / 2,000 Pcs Received`
- **Footer:** note `Action will increment inventory immediately. Only authorized warehouse roles can commit intake transactions.` (`shield`) · `Cancel` · `Confirm Receipt` (`verified`)
- **Confirm modal:** `Confirm Goods Receipt?` / `You are about to record 600 units received against PO-2024-0014.` + breakdown `Hex Bolt M12 x 50mm Grade 8.8 (WH-JKT-01 • Bin A-12) +400 Pcs`, `Hex Nut M12 Zinc Plated (WH-JKT-01 • Bin A-14) +200 Pcs` + note `StockLedger transaction will be permanently created. This action cannot be reversed without standard dock return documentation.` — `Back / Review` / `Confirm & Update Stock`
- **Zero-input alert:** `Enter a quantity to receive for at least one item before proceeding.`
- **Validation messages:** `Receive quantity cannot exceed remaining quantity (600)` · `Receive quantity cannot be negative`
- **Banners:** success `Goods Receipt Completed Successfully` / `PO-2024-0014 has been recorded on the dock. Physical inventory stock balances updated in Warehouse A and transaction recorded in Stock Ledger #SL-88421.` + `View Purchase Order` / `Inspect Stock Ledger`; stale `Purchase Order Revision Detected` / `...altered in another session (Intake #2 recorded by Dock Team B). Refresh the purchase order...` + `Refresh PO Quantities`; ineligible `Goods Receipt Unavailable` / `...locked and no longer eligible for receiving (Status: Received / Closed). Line items have fulfilled 100% of allocation.`; op error `Unable to Process Goods Receipt` / `Database warehouse lock timed out or a field validation failed. Verified intake quantities have not been committed. Please review entries and try again.` + `Retry Transaction`
- **Empty state:** `Nothing to Receive` / `All ordered quantities (2,000 pcs) have already been fully verified and received at Warehouse A dock.` + `Back to PO-2024-0014`
- **Scenarios shipped (11):** `1. Partially Received` · `2. Fresh Intake (Ordered)` · `3. Full Receipt (100%)` · `4. Validation Errors` · `5. Confirm Modal` · `6. Processing` · `7. Success State` · `8. Stale Concurrency` · `9. Closed/Ineligible` · `10. All Received` · `11. Op Error`

### 12.6 Sales screens

#### Sales Orders (list)

- **Breadcrumb:** `Sales / Sales Orders` · **H1:** `Sales Orders` + role badge `Sales Rep Scope`
- **Subtitle:** `Manage customer orders, approval governance, and warehouse fulfillment commitments.`
- **Actions:** `Export CSV` (`file_download`) · `Create Sales Order` (`add`, hidden for non-Sales roles)
- **KPI tiles (4):** `Open Orders` 14 `Active Rep Orders` / `Committed across active accounts`; `Pending Approval` 5 `Requires Admin` / `Credit & margin compliance check`; `Dispatch Ready` 6 `Stock Reserved` / `Ready for Warehouse Goods Issue`; `Fulfilled / Closed` 38 `This Month` / `Ledger deducted & archived`
- **Toolbar:** search placeholder `Search by SO number or customer...`; status select `All Statuses` / `Draft` / `Pending Approval` / `Approved` / `Fulfilled` / `Cancelled`; date sort `Order Date: Newest First` / `Order Date: Oldest First`; `Clear Filters` (`clear_all`); counter `Showing 1–10 of 25 sales orders`
- **Columns (with fixed widths):** `SO Number` (w-36) · `Customer` (w-56) · `Created By` (w-40) · `Source Warehouse` (w-48) · `Order Date` (w-28) · `Status` (w-36) · `Items / Qty` (w-36, right) · `Total Value` (w-32, right) · `Actions` (w-44, right). Table `min-w-[1020px]`; `<tbody>` is empty in markup and rendered from `RAW_ORDERS`.
- **`RAW_ORDERS` (10):**
  | SO | Customer (tier) | Created by | Warehouse | Date | Status | Items | Value |
  |---|---|---|---|---|---|---|---|
  | SO-2024-0025 | PT Megatama Konstruksi (Tier 1 Enterprise • Infrastructure) | You (Riko Pratama) | Warehouse A (West Wing) • WH-JKT-01 | 24 Oct 2024 | Draft | 4 items (180 units) | Rp 48.600.000 |
  | SO-2024-0024 | CV Mandiri Perkasa (Standard • Building Supplies) | You (Riko Pratama) | Warehouse B (East Logistics) • WH-SBY-02 | 24 Oct 2024 | Pending Approval | 2 items (45 units) | Rp 12.450.000 |
  | SO-2024-0023 | PT Surya Semesta (Tier 1 Enterprise • Commercial) | Maya Sandria (Sales) | Warehouse A • WH-JKT-01 | 23 Oct 2024 | Pending Approval | 7 items (320 units) | Rp 94.200.000 |
  | SO-2024-0022 | PT Global Mitra Teknik (Key Account • Heavy Equipment) | You (Riko Pratama) | Warehouse C (Transit) • WH-BDG-03 | 23 Oct 2024 | Approved | 3 items (90 units) | Rp 26.800.000 |
  | SO-2024-0021 | PT Jaya Abadi Sentosa (Wholesale • Hardware Supply) | Bambang Kusumo (Sales) | Warehouse A • WH-JKT-01 | 22 Oct 2024 | Approved | 5 items (210 units) | Rp 63.500.000 |
  | SO-2024-0020 | CV Sumber Rejeki (Standard • Distribution Hub) | You (Riko Pratama) | Warehouse B • WH-SBY-02 | 22 Oct 2024 | Fulfilled | 1 item (50 units) | Rp 9.100.000 |
  | SO-2024-0019 | PT Nusantara Citra Steel (Industrial • Fabrication) | Maya Sandria (Sales) | Warehouse A • WH-JKT-01 | 21 Oct 2024 | Fulfilled | 8 items (410 units) | Rp 112.750.000 |
  | SO-2024-0018 | PT Prima Logam Utama (Commercial • Industrial Piping) | You (Riko Pratama) | Warehouse C (Transit) • WH-BDG-03 | 20 Oct 2024 | Cancelled | 2 items (30 units) | Rp 7.800.000 |
  | SO-2024-0017 | CV Bakti Karya Mandiri (Contractor • Residential) | Bambang Kusumo (Sales) | Warehouse B • WH-SBY-02 | 20 Oct 2024 | Approved | 3 items (65 units) | Rp 18.300.000 |
  | SO-2024-0016 | PT Selaras Bangun Megah (Key Account • Urban Development) | You (Riko Pratama) | Warehouse A • WH-JKT-01 | 19 Oct 2024 | Fulfilled | 6 items (240 units) | Rp 71.900.000 |
- **Role-gated row actions (segregation of duties, exactly as generated):**
  | Role | Draft | Pending Approval | Approved | Other |
  |---|---|---|---|---|
  | Sales | `Submit` + `Edit` + view icon | italic `Awaiting Admin` + `View` (**no Approve button**) | `View` | `View` |
  | Admin | `View Draft` | `Approve` (`check`) + `Cancel` (`title="Reject / Cancel"`) + view icon | `View` + `Cancel` (`title="Void Order"`) | `View` |
  | Warehouse | `Inspect` | `Inspect` | `Goods Issue` (`output`) + view icon | `Inspect` |
- **`Created By` cell:** Sales role shows a `YOU` avatar badge + `You (Riko Pratama)` for own orders; Admin/Warehouse always show `{name}` + `{role}` stacked.
- **Modal `action-modal` — 5 dynamic variants:**
  | Action | Title | Body | Confirm |
  |---|---|---|---|
  | submit | `Submit for Credit & Compliance Approval` | `Are you sure you want to submit this draft order for executive margin review? Once submitted, edits are locked.` | `Confirm Submission` |
  | approve | `Approve Sales Order & Commit Stock` | `Confirm credit compliance and authorize hard stock allocation for this customer order. Warehouse dispatch queue will be notified immediately.` | `Authorize Approval` |
  | goods-issue | `Execute Warehouse Goods Issue (Dock Out)` | `Confirm outbound physical dispatch. This operational action irrevocably decrements stock counts in the Ledger.` | `Confirm Goods Out` |
  | cancel | `Void / Cancel Sales Order` | `Are you sure you want to cancel this order? Any active soft or hard inventory allocations will be released back to available stock.` | `Void Order` |
  | view | `Order Specification Details` | `Showing current configuration, pricing matrix, and source facility allocation log.` | `Close Inspector` |
  Modal info box shows `Order`, `Customer`, `Stock Facility` in mono.
- **Governance banner:** `Sales Order & Stock Allocation Protocol` / `Approved Sales Orders place a hard stock reservation against the assigned Source Warehouse. Physical inventory is deducted from ProductStock only upon validated Goods Issue, recording an immutable audit entry in the Stock Ledger.`
- **States shipped:** personas `Sales View (Own Orders)` / `Admin View (Global Orders)` / `Warehouse Staff View`; data states `Normal (10)` / `Skeleton` / `Empty State` / `No Results` / `Error State` / `Toast`. Empty: `No sales orders yet` / `Get started by creating your first sales order to lock warehouse stock allocation.` (non-Sales: `No sales orders available` / `There are currently no active customer sales orders in the enterprise database.`). No-results: `No orders match filter criteria` / `We couldn't find any sales orders matching your search or active filters. Try broadening your terms.` Error: `Unable to synchronize order records` / `The ERP order engine returned a transient network timeout (Status 504). Master data cache might be unaligned.` + `Retry Connection` / `Diagnose Gateway`. Toast: `Order Updated` / `Sales order submitted for approval`.
- **Sales role filter:** `renderTable()` restricts the Sales persona to rows where `isYou === true`.

#### Sales Order Detail

- **Breadcrumb:** `Sales / Sales Orders / SO-2024-0025` + `Back to Sales Orders`
- **H1:** `SO-2024-0025` · status badge default `Pending Approval` · chip `IDR Currency`
- **Subtitle:** `Sales order details, inventory reservation validation, and warehouse fulfillment allocation.`
- **Segregation-of-duties, verbatim:** in `PendingApproval` + Sales role the actions area renders a locked pill `Self-Approval Restricted` (`lock`) and the governance banner reads
  *"As the order creator, you cannot self-approve customer sales orders. This order has been placed in the Administrative review queue to verify customer credit standing and inventory thresholds."*
- **Workflow stepper:** `Workflow Progression` / `Governance lifecycle: Sales Creation > Admin Approval > Warehouse Goods Issue` / hint `Step 2 of 4: Authorization`; steps `1. Draft` (`Sales Configured`) · `2. Approval` (`Review In Progress`) · `3. Approved` (`Stock Hard-Reserved`) · `4. Fulfilled` (`Goods Issued & Closed`); bar widths 12% / 33% / 66% / 100%, 0% when Cancelled
- **Cards (3):**
  - `Customer Entity` (`CUST-8842-ID`) — `PT Megatama Konstruksi` / `Tier 1 Enterprise • Infrastructure & Heavy Piping` / `Ir. Budi Santoso (Procurement Director)` / `+62 21-555-0192` / `budi.santoso@megatamakonstruksi.co.id` / `Jl. Jend. Sudirman Kav. 52-53, SCBD, Jakarta Selatan`; footer `Credit Verification` ↔ `Authorized Tier`
  - `Source Facility` (`WH-JKT-01`) — `Warehouse A (West Wing Hub)` / `Kawasan Industri Pulo Gadung, Jakarta Timur` / `Dispatch Staging: Bay 3 • Dock 4` / `Freight Category: Heavy Freight / Palletized` / `Inventory Readiness: 100% Allocated (3/3)`; footer `Allocation Buffer` ↔ `No Backorders Needed`
  - `Audit & Chain of Custody` (`IDR Account`) — `Order Creation Date: 24 Oct 2024`, `Created By: You (Riko Pratama - USR-SLS-04)`, `Authorizing Officer: Awaiting Authorization`, `Target Dispatch Date: 28 Oct 2024`, `Channel: Direct Contract`; footer `Enterprise Version` ↔ `v1.0.4 • Locked Hash`
- **Goods Issue callout (Approved only):** `Order Approved • Ready for Warehouse Goods Issue` / `Physical inventory is locked at Bay 3. Warehouse personnel may initiate picking, packing, and dispatch ledger recording.` + `Launch Goods Issue Flow` (`output`)
- **Line items:** columns `#` · `Product Description & SKU` · `WH Available` · `Order Qty` · `Unit Price (IDR)` · `Subtotal (IDR)`
  | # | Product | SKU • Category | WH Available | Qty | Unit Price | Subtotal |
  |---|---|---|---|---|---|---|
  | 01 | Industrial Gate Valve 50mm (`valve`) | SKU-IV-501 • Mechanical & Valves | 140 pcs / `Ample Reserve` | 25 pcs | Rp 450.000 | Rp 11.250.000 |
  | 02 | High-Tensile Hex Bolt M12×50 (`build`) | SKU-HB-112 • Fasteners | 1,200 pcs / `Ample Reserve` | 300 pcs | Rp 12.500 | Rp 3.750.000 |
  | 03 | Reinforced Gasket Type-C (`radio_button_unchecked`) | SKU-RG-109 • Seals & Gaskets | 450 pcs / `Ample Reserve` | 50 pcs | Rp 65.000 | Rp 3.250.000 |
  Totals panel: note `Strict B2B Valuation Model` / `Order lines are calculated strictly as Quantity × Selling Price per contractual catalog. Applicable tax and delivery adjustments are reconciled during post-dispatch billing.`; `Distinct SKUs 3 Items` · `Total Quantity 375 Units` · `Total Order Value IDR 18.250.000` (`bg-primary-container text-on-primary`)
- **Audit log (5 entries, 2 hidden by status):** `24 Oct, 09:14` `Order initialized in Draft state by Sales Rep Riko Pratama (USR-SLS-04).` / `Line items validated against Warehouse A active stock ledger.`; `24 Oct, 10:30` `Order submitted for administrative credit and margin approval.` / `System locked draft configuration; hard reservation tags placed on 3 items.`; `24 Oct, 11:02` `Approved by Administrator Sarah Jenkins (USR-ADM-01).` / `Fulfillment authorization token dispatched to Warehouse A dispatch dock.`; `24 Oct, 14:45` `Goods Issue completed. Outbound delivery slip issued.` / `Order closed in terminal Fulfilled state.`; `24 Oct, 11:30` `Order cancelled. Stock reservations rolled back.` / `Record set to terminal read-only archive status.`
- **Modals:** `Submit sales order for approval?` / `This sales order will be transmitted to an authorized Administrator for review. Once submitted, you will no longer be able to modify line items or quantities.` (info `Customer: PT Megatama Konstruksi (IDR 18.250.000)`; `Keep as Draft` / `Submit for Approval`) · `Approve sales order?` / `Approving SO-2024-0025 commits warehouse stock reservations at Warehouse A and marks this order as fulfillment-ready for warehouse staff.` (info `3 Items Allocated:` ↔ `Rp 18.250.000`) · `Cancel sales order?` / `This action is irrevocable. All reserved physical stock for SO-2024-0025 will be released back into general warehouse availability.` (`Keep Order` / `Cancel Sales Order`)
- **Header action matrix (status × role), as generated:**
  | Status | Sales | Admin | Warehouse |
  |---|---|---|---|
  | Draft | `Cancel Order` · `Edit SO` · `Submit for Approval` | `Cancel Order` | italic `Read-only in draft status` |
  | PendingApproval | `Cancel Order` + `Self-Approval Restricted` pill | `Cancel Order` · `Approve Sales Order` | italic `Awaiting administrative approval` |
  | Approved | `Cancel Order` + `Approved • WH Action Required` pill | same as Sales | `Goods Issue` |
  | Fulfilled / Cancelled | `Print Manifest` (`window.print()`) + `Terminal Record` pill | same | same |
- **Conflict banner:** `The sales order has changed on the server. Refresh the record to inspect current state before continuing.` + `Refresh Order`
- **Skeleton:** toggles `animate-pulse bg-surface-container` on all `td, h1, h2, h3, p`
- **QA controls:** status select (Draft / PendingApproval default / Approved / Fulfilled / Cancelled) · role select (sales default / admin / warehouse)

#### Sales Order Create/Edit

- **Breadcrumb:** `Sales / Sales Orders / Edit SO-2024-0025` · **H1:** `Edit Sales Order SO-2024-0025` · status badge `Draft`
- **Subtitle:** `Update line quantities and customer requirements prior to administrative review.`
- **Originator chip:** avatar `RP` + `Order Originator` / `Riko Pratama (You)`
- **Fields:**
  | Field | Options / value |
  |---|---|
  | `Customer Entity *` | `PT Megatama Konstruksi • Tier 1 Enterprise (Jakarta)` (CUST-001, selected) · `CV Mandiri Perkasa • General Contractor (Surabaya)` (CUST-002) · `PT Global Mitra Teknik • Industrial Maintenance (Bandung)` (CUST-003) · `PT Adhi Graha Engineering (Semarang)` (CUST-004). Meta box `Contact: Ir. Budi Santoso (Procurement Dir)` / `ID: CUST-8842-ID`. Error `Customer entity is required to proceed.` |
  | `Fulfillment Warehouse *` | `Warehouse A (West Wing Hub) - WH-JKT-01` (selected) · `Warehouse B (East Logistics) - WH-SBY-02` · `Warehouse C (Transit Staging) - WH-TRN-03`. Error `Designated warehouse is mandatory.` |
  | `Order Date *` | `type="date"` value `2024-10-25`, `font-mono-data-sm`. Error `Valid ISO date is required.` |
- **Governance callout:** `Inventory Hard Reservation: Order entry checks live balance at Warehouse A. Stock quantities are reserved upon Admin approval and deducted strictly at Goods Issue dispatch.`
- **Line items (`hidden sm:block` table / `sm:hidden` cards):** columns `#` · `Product Description & SKU` · `Available in WH` · `Order Qty *` · `Unit Price (IDR) *` · `Line Subtotal (IDR)` · `Action`
  | # | Product / SKU / category | Available | Qty | Price | Subtotal |
  |---|---|---|---|---|---|
  | 1 | Industrial Gate Valve 50mm / SKU-IV-501 / Mechanical & Valves | 140 pcs (dot `bg-emerald-600`) | 25 (`min=1`) | 450000 (`step=500`) | Rp 11.250.000 |
  | 2 | High-Tensile Hex Bolt M12×50 / SKU-HB-112 / Fasteners | 1,200 pcs | 300 | 12500 (`step=50`) | Rp 3.750.000 |
  | 3 | Reinforced Gasket Type-C / SKU-RG-109 / Seals & Gaskets | 450 pcs | 50 | 65000 (`step=1000`) | Rp 3.250.000 |
  Row errors: `Must be > 0` (qty), `Invalid price` (price). Global: `Add at least one product before submitting this sales order.`
  Add control: `+ Add Product Line` (`add`) + helper `Duplicate SKUs are automatically consolidated into order quantity.`
  Empty placeholder: `No product items added yet. Click "+ Add Product Line" below to insert items.`
- **Valuation summary:** `Total Distinct Items 3 Line Items` · `Total Cumulative Units 375 Units` · `Total Order Value IDR 18.250.000` / `Sum of all item lines`
- **Rules panel:** `Order Approval & Allocation Protocol` (`gavel`) — `Two-Step Validation: ...` · `Separation of Powers: Sales reps are restricted from self-approving orders or initiating warehouse dispatch notes.` · `Strict Pricing Model: ...`
- **Sticky footer:** `Cancel Sales Order` (`close`, error outline) · `Save Changes` (`save`) · `Submit for Approval` (`send`)
- **Product picker modal:** `Select Catalog SKU` (`category`), search placeholder `Type SKU code or product description...`; items `Pressure Flange DIN 2501 PN16` (SKU-FL-220, Piping & Flanges, Rp 220.000, 80 in WH) and `Stainless Steel Ball Valve 1/2-in` (SKU-SS-904, Valves, Rp 185.000, 35 in WH)
- **Submit modal:** `Submit Sales Order for Approval?` / `This sales order will be routed immediately to an Administrator for inventory verification and pricing endorsement. Once submitted, it will be locked from direct editing.` + `Order Reference: SO-2024-0025` / `Total Order Value: IDR 18.250.000` — `Continue Editing` / `Submit for Approval`
- **Cancel modal:** `Cancel Sales Order?` / `Are you sure you want to cancel this sales order? The draft will be permanently cancelled and will not progress to administrative approval or warehouse fulfillment.` — `Keep Order` / `Yes, Cancel Order`
- **Notices:** pending — `Awaiting Administrative Approval` / `This sales order is in queue for review by Sarah Jenkins (Admin). All line items and parameters are strictly read-only.` + `LOCKED`; approved — `Sales Order Approved by Administration` / `Stock hard reservations are committed. Fulfillment handed off to Warehouse A Dispatch queue for Goods Issue.` + `DISPATCH READY`; empty catalogs — `Master Data Warning: One or more operational catalogs (Customers, Inventory Warehouses, or SKUs) could not be retrieved from the central ledger. Order creation might be constrained.`
- **Empty-catalog select text:** `No active customers found` / `No active warehouses found`
- **Scenarios shipped (10):** `1. Create Blank` · `2. Edit Draft (SO-2024-0025)` (default) · `3. Pending Approval` · `4. Approved` · `5. Validation Errors` · `6. Submit Modal` · `7. Cancel Modal` · `8. Loading State` · `9. Empty Catalogs` · `10. Test Toast`
- **Validation demo values:** first row qty `0`, first row price `-100`, both with `border-error`; toast `Please correct the highlighted validation errors.`
- **Loading label:** `Saving Draft...` with an inline SVG spinner (1500ms), then toast `Sales order draft saved successfully.`

#### Goods Issue

- **Breadcrumb:** `Sales / Sales Orders / SO-2024-0025 / Goods Issue` + `← Back to SO-2024-0025`
- **H1:** `Goods Issue` · status badge `Approved` · SO chip `SO-2024-0025` · subtitle `Release inventory against this approved sales order`
- **Role box:** `AUTHORIZED ROLE:` / `Warehouse Staff`
- **Available-stock rule callout (verbatim):** `Available Stock Rule` / *"Strictly defined as `ProductStock.quantity` for the specific Product in the Source Warehouse (Warehouse A). Does not combine other warehouses, pending orders, or reserved stock. Displayed stock represents current database state, re-validated atomically by the backend upon submission."*
- **Cards (3):** `Source Sales Order` — `SO Number SO-2024-0025`, `Order Date 24 Oct 2024`, `Created By Riko Pratama (Sales Rep)`, footer `Status: Approved`; `Customer & Consignee` — `PT Megatama Konstruksi` / `Tier 1 Enterprise • Infrastructure & Piping` / `Destination: Jl. Jend. Sudirman Kav. 52-53, Jakarta Selatan` / footer `Contact Liaison: Ir. Budi Santoso (Director)`; `Source Warehouse` (with a `lock` icon `title="Explicitly non-editable, locked to SO source facility"`) — `Warehouse: Warehouse A (West Wing Hub)` / `WH-JKT-01` / `Facility Access: Locked to SO Facility` / footer `Inventory Check: Sufficient (3/3 SKUs)`
- **Metrics (5):** `Distinct SKUs 3 Items` / `Mechanical, Fasteners, Seals`; `Total Ordered 375 Units` / `Sum of sales line items`; `Total WH-A Stock 1,790 Units` / `Contextual WH-A sum`; `Shortage 0 Units` / `All items sufficient`; `Fulfillment Policy Full Fulfillment Enforced` / `Issue Qty must equal Ordered Qty for all lines`
- **Line items:** helper `Remaining After Issue = Available Stock - Issue Qty`; reset `Reset to Ordered`; columns `#` · `Product & Specification` · `SKU` · `Ordered Qty` · `Available Stock (Source WH)` · `Issue Qty` · `Remaining After Issue` · `Availability Status`
  | # | Product / category | SKU | Ordered | Available | Issue | Remaining | Status |
  |---|---|---|---|---|---|---|---|
  | 01 | Industrial Gate Valve 50mm / Mechanical & Valves | SKU-IV-501 | 25 pcs | 140 pcs | 25 | 115 pcs | Ready |
  | 02 | High-Tensile Hex Bolt M12×50 / Fasteners | SKU-HB-112 | 300 pcs | 1,200 pcs | 300 | 900 pcs | Ready |
  | 03 | Reinforced Gasket Type-C / Seals & Gaskets | SKU-RG-109 | 50 pcs | 450 pcs | 50 | 400 pcs | Ready |
  Insufficient scenario overrides row 3 to `20 pcs` available, `-30 pcs (Shortage)` remaining, `Insufficient Stock` badge, readiness `Shortage (2/3 SKUs Ready)`, shortage metric `30 Units`, WH stock `1,360 Units`.
- **Inline validation:** `Issue quantity must equal ordered quantity (25 pcs). Full fulfillment only.` · per-row `Must equal {orderedQty} pcs`
- **Stock impact preview:** `Source Warehouse: Warehouse A (WH-JKT-01)` · `Products to Issue: 3 Line Items` · `Total Quantity: 375 Units` · `Inventory Impact: -375 Units from WH-A ProductStock`
- **Atomic protocol callout (verbatim):** *"Processing Goods Issue decrements `ProductStock` in Warehouse A, inserts immutable `ISSUE` entry in `StockLedger`, and updates Sales Order SO-2024-0025 to Fulfilled. All operations commit atomically; if any constraint fails, the entire transaction rolls back."*
- **Footer:** note `Reversible only via authorized Return Goods Authorization (RGA) workflow.` (`lock_clock`) · `Cancel & Return` · `Confirm Goods Issue` (`local_shipping`)
- **Confirm modal:** `Confirm Goods Issue?` / `Reference: SO-2024-0025 • Warehouse A (WH-JKT-01)` / `Confirm goods issue? The selected quantities (375 units) will be deducted from Warehouse A stock and sales order SO-2024-0025 will be marked as Fulfilled.` + `Atomic Transaction Commit:` breakdown (`Warehouse A (WH-JKT-01): -375 Units (Live Stock)`, `Sales Order Status: SO-2024-0025 → Fulfilled`, `Stock Ledger: Immutable ISSUE entry generated`) — `Cancel` / `Confirm Goods Issue`
- **Banners:** insufficient — `Insufficient stock: This sales order cannot be fulfilled with the current inventory.` / `Shortage: 30 pcs for SKU-RG-109 (Reinforced Gasket Type-C) in Warehouse A. Available stock is only 20 pcs, but 50 pcs are required for full fulfillment.`; stale — `Stock availability has changed` / `The available inventory changed before this goods issue could be completed. Review current quantities and try again.`; status changed — `Sales order status has changed` / `Refresh the order to review its current status before continuing.`; ineligible — `Goods issue is no longer available` / `This sales order is already Fulfilled or Cancelled and is no longer eligible for fulfillment.`; op error — `Unable to complete goods issue` / `The goods issue could not be processed. Please review the order and try again.`; success — `Goods issue completed successfully` / `Sales order SO-2024-0025 has been fulfilled. Resulting status: Fulfilled.` + `Back to SO Detail` / `Sales Orders`
- **Empty state:** `Nothing to issue` / `There are no remaining quantities available for this sales order.` + `Return to Sales Order`
- **Processing state:** button text `Processing Goods Issue... - Locking ProductStock & Committing...`, icon `progress_activity animate-spin`, all qty inputs disabled
- **Scenarios shipped (11):** `1. Ready to Fulfill (Default)` · `2. Insufficient Stock` · `3. Issue Qty Validation` · `4. Confirm Modal` · `5. Processing State` · `6. Success State` · `7. Stale Stock Conflict` · `8. Order Status Changed` · `9. Ineligible / Closed` · `10. Nothing to Issue` · `11. Op Error`
- **Button-disable rule:** in scenarios 2, 7, 8, 9 and 10 the confirm button stays disabled regardless of quantity validity.

### 12.7 Stock Ledger

- **Breadcrumb:** `Inventory / Stock Ledger` · **H1:** `Stock Ledger` + badge `System Read-Only` (`lock`)
- **Subtitle:** `Review recorded inventory movements across products and facilities.`
- **Session widget:** pulsing `bg-emerald-500` dot · `Operating Session` · `Sarah Jenkins (ADMIN)`
- **Audit policy banner:** `Immutable Audit Ledger` + tag `WORM Compliance` / `Inventory movement records are recorded automatically by backend transactions (Goods Receipt, Goods Issue, Cycle Verification). Direct ledger modification, deletion, or manual adjustments are strictly prohibited by protocol.` · `Ledger Version: v2.8.4-RELEASE`
- **KPI tiles (4, `grid-cols-2 md:grid-cols-4`):** `Total Movements 48` / `Across all 3 facilities` (`swap_vert`); `Receipts (Inbound) 26` / `From Purchase Orders` (`south_west`, emerald); `Issues (Outbound) 18` / `Sales Order fulfillments` (`north_east`, amber); `Adjustments 4` / `Cycle count variances` (`balance`, slate)
- **Filter workbench (`grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end`):**
  | Label | Control | Options / default | Span |
  |---|---|---|---|
  | `Search Ledger` | text | placeholder `Search product, SKU, or reference...` | `md:col-span-3` |
  | `Facility / Warehouse` | select | `All Warehouses` · `Warehouse A (WH-JKT-01)` · `Warehouse B (WH-SBY-02)` · `Warehouse C (WH-BDG-03)` | `md:col-span-2` |
  | `Movement Type` | select | `All Types` · `Receipt (Inbound)` · `Issue (Outbound)` · `Adjustment` | `md:col-span-2` |
  | `Date Range Window` | 2 × date | `2024-10-01` → `2024-10-24` | `md:col-span-3` |
  | `Performed By` | select + reset | `All Users` · `Sarah Jenkins (Admin)` · `Bambang Sugianto (Staff)` · `Riko Pratama (Sales)` | `md:col-span-2` |
- **Date validation:** `Invalid Date Filter: "From Date" cannot be later than "To Date". Please rectify dates to resume ledger filtering.`
- **Result summary:** `Showing 1–10 of 48 movement records` + legend chips `Inbound: +2,150 pcs` (emerald dot) · `Outbound: -407 pcs` (amber dot) · `Net Delta: +1,743 pcs` (slate dot)
- **Columns:** `Timestamp` · `Product & Specification` · `SKU` · `Facility / Warehouse` · `Movement Type` (center) · `Quantity` (right) · `Reference` · `Performed By` · `Action` (center, w-24)
- **`ledgerData` (10):**
  | Ledger ID | Timestamp | Product (category) | SKU | Warehouse | Type | Qty | Reference | Performed by |
  |---|---|---|---|---|---|---|---|---|
  | LED-2024-00941 | 24 Oct 2024, 14:15 | Industrial Gate Valve 50mm (Mechanical & Valves) | SKU-IV-501 | Warehouse A (WH-JKT-01) | Issue | -25 pcs | SO-2024-0025 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00940 | 24 Oct 2024, 14:15 | High-Tensile Hex Bolt M12×50 (Fasteners & Hardware) | SKU-HB-112 | Warehouse A (WH-JKT-01) | Issue | -300 pcs | SO-2024-0025 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00939 | 24 Oct 2024, 14:15 | Reinforced Gasket Type-C (Seals & Gaskets) | SKU-RG-109 | Warehouse A (WH-JKT-01) | Issue | -50 pcs | SO-2024-0025 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00938 | 22 Oct 2024, 11:30 | Hydraulic Coupling 40mm (Fluid Power) | SKU-HC-402 | Warehouse B (WH-SBY-02) | Receipt | +80 pcs | PO-2024-0017 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00937 | 20 Oct 2024, 09:45 | Stainless Steel Flange 2" (Piping & Flanges) | SKU-SF-202 | Warehouse A (WH-JKT-01) | Adjustment | +5 pcs | ADJ-2024-0003 | Sarah Jenkins (Admin) |
  | LED-2024-00936 | 18 Oct 2024, 16:20 | Industrial Gate Valve 50mm (Mechanical & Valves) | SKU-IV-501 | Warehouse A (WH-JKT-01) | Receipt | +60 pcs | PO-2024-0018 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00935 | 17 Oct 2024, 13:10 | Heavy-Duty Ball Bearing 6205 (Bearings & Power) | SKU-BB-625 | Warehouse C (WH-BDG-03) | Receipt | +150 pcs | PO-2024-0016 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00934 | 15 Oct 2024, 10:05 | Industrial Pressure Gauge 0-10 Bar (Instrumentation) | SKU-PG-301 | Warehouse A (WH-JKT-01) | Issue | -12 pcs | SO-2024-0022 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00933 | 12 Oct 2024, 15:40 | Hex Nut Zinc Plated M12 (Fasteners & Hardware) | SKU-HN-115 | Warehouse A (WH-JKT-01) | Receipt | +1,200 pcs | PO-2024-0014 | Bambang Sugianto (Warehouse Staff) |
  | LED-2024-00932 | 10 Oct 2024, 08:30 | Pneumatic Air Hose 10mm (Fluid Power) | SKU-AH-510 | Warehouse B (WH-SBY-02) | Issue | -20 pcs | SO-2024-0020 | Bambang Sugianto (Warehouse Staff) |
- **Detail modal (read-only):** `Stock Ledger Record Details` / `Entry ID: LED-2024-00941` (becomes `Ledger ID: {id}`); notice `This record is cryptographically committed to the audit trail. No edits, rollbacks, or deletion actions are permitted.`; blocks `Product Specification` (name / category / SKU), `Movement Classification` (badge e.g. `● Issue (Dispatched)`, qty `-25 pcs`, note `Decremented from source Bin: A-14-RACK-02`), lineage grid (`Warehouse Facility`, `Origin Transaction` e.g. `SO-2024-0025 (Sales Order)`, `System Timestamp`, `Authorized Operator`, `Ledger Checksum SHA256: 8f92b7c4...90e3a1`), audit note `This automated entry was triggered by physical fulfillment in Warehouse A. To correct an erroneous transaction, dispatch a new compensating Goods Receipt/Issue document per SOP-WH-88.`; footer `Record State: VERIFIED_COMMITTED` + `Close View` (the only action — strictly read-only)
- **States shipped:** `normal`, `Receipts` filter, `Issues` filter, detail modal, `Date Error`, `Skeleton`, `Empty`, `No Match`, `Error`, `403 Block`. Empty: `No stock movements recorded yet` / `Inventory movements are generated synchronously upon completing incoming Purchase Order receipts or outgoing Sales Order dispatches.` No-match: `No matching movement entries found` / `Try relaxing your search terms, warehouse filters, or movement type criteria.` Error: `Unable to load stock movements` / `A transient communication failure occurred while connecting to the primary inventory ledger replica. Please verify your connection or retry.` 403: `403 — Access Denied to Stock Ledger` / `Your current credential scope does not possess permission `inventory.ledger.read`. Contact Sarah Jenkins (Lead System Administrator) to request audit privilege.` + `Switch to Administrator View`
- **Mobile:** `md:hidden` card list `#ledger-mobile-cards` (product, SKU+category, type badge, quantity, reference, warehouse+timestamp, performer, `View Details`)
- **Role switcher is cosmetic** — `setRole()` updates only `#active-user-display`; it does not filter data.

### 12.8 My Profile

- **Breadcrumb:** `Account / My Profile` · **H1:** `My Profile` + badge `Canonical Route: /profile`
- **Subtitle:** `View and update your personal identity credentials and verify assigned operational privileges.`
- **Session indicator:** pulsing `bg-primary` dot · `Active Session:` · `ID: SES-89240`
- **Account summary card:** initials tile `SJ` (`w-14 h-14 rounded bg-primary-container text-on-primary`); `Sarah Jenkins` + badge `Verified Enterprise Identity` (`verified`); email `sarah.jenkins@ioms-enterprise.com` (mono); role pill `Admin`; status pill `Active`; scope notice `Self-service profile boundary. Name and corporate email are editable. Account permissions and organizational assignments are strictly controlled by centralized system administrators.`
- **Personal Information form** (`person`, tag `Editable Fields`):
  | Field | Detail |
  |---|---|
  | `Full Name *` (tag `Legal / Operational`) | placeholder `Enter your full name`, value `Sarah Jenkins`; helper `Your name as it appears on ledger audits, purchase authorizations, and fulfillment stamps.`; error `Full name is required.` |
  | `Email Address *` (tag `Work Corporate`) | `type="email"`, placeholder `name@company.com`, value `sarah.jenkins@ioms-enterprise.com`, `font-mono-data-sm`; helper `Used for enterprise authentication, shift handovers, and automated order notifications.`; error `Enter a valid enterprise email address.` |
- **Actions:** `Cancel` · `Save Changes` (`save`, **disabled until dirty**); helper `Changes are validated atomically against the enterprise account registry.` (`verified_user`)
- **Role & Access Governance card** (read-only, `admin_panel_settings`, tag `Strict Read-Only` / `lock`): body `System role assignments, operational security clearance, and access lifecycles are strictly controlled via centralized administrator governance and cannot be modified via self-service.`; sub-cards `Assigned Security Role` → badge `Admin` + `ROLE-01` + `Full administrative authority across enterprise inventory catalog, procurement orders, sales ledgers, warehouse routing, and user provisioning.`; `Account State` → badge `Active` + `STATUS-ACT` + `Valid authentication token, active API routing, and authorized signature permissions on order processing systems.` (lock tooltips: `Locked by corporate policy` / `Self-service deactivation is disabled`)
- **Duplicate email banner:** `Conflict: Email Address Unavailable` / `An enterprise account with the email sarah.jenkins@ioms-enterprise.com already exists in the identity registry. Enter an alternate address or contact a system administrator.`
- **Load error card:** `Unable to load your profile` / `The operational directory gateway did not respond within the SLA threshold. Please re-authenticate or retry query.` + `Retry Gateway Connection`
- **Unsaved modal:** `Discard changes?` / `Profile modifications detected` / `You have unsaved changes to your full name or email address. If you navigate away or cancel now, your edits will be discarded.` — `Stay on Page` / `Discard Changes`
- **Toast:** `Profile Updated` / `Your personal account details were updated successfully.`
- **Role contexts in `profileStore`:** `clean` (Sarah Jenkins / Admin / ROLE-01), `staff` (Bambang / Warehouse), `sales` (Riko / Sales)
- **Scenarios shipped (11):** `Normal / Clean` · `Dirty (Modified)` · `Role: Warehouse (Bambang)` · `Role: Sales (Riko)` · `Validation Errors` · `Email Conflict` · `Saving State` · `Save Success` · `Unsaved Modal` · `Skeleton Loading` · `Load Error`

### 12.9 Reports (tabular)

- **Breadcrumb:** `Reports / {Stock Movement | Order Status}` · **H1:** `Reports`
- **Subtitle:** `Generate operational reports from inventory and order data.`
- **Export:** `Export CSV` (`download`), **disabled by default** with tooltip `Generate a report before exporting.`
- **Report-type tabs:** `Stock Movement` (`move_to_inbox`) · `Order Status` (`swap_horiz`). Switching tabs resets to the ungenerated state (source comment: *"switching tabs resets to ungenerated state until Generate Report is pressed"*).
- **Stock Movement filters (`sm:grid-cols-2 lg:grid-cols-4`):** `From Date *` (`2024-10-01`) · `To Date *` (`2024-10-24`) · `Facility / Warehouse` (`All Warehouses` / `Warehouse A (WH-JKT-01)` / `Warehouse B (WH-SBY-02)` / `Warehouse C (WH-BDG-03)`) · `Movement Type` (`All Types` / `Receipt (Inflow)` / `Issue (Outflow)` / `Adjustment`)
- **Order Status filters (`sm:grid-cols-2 lg:grid-cols-5`):** `Order Type` (`All Orders` / `Purchase Order (PO)` / `Sales Order (SO)`) · `Status` (`All Statuses` / `Draft` / `Pending Approval` / `Approved` / `Ordered` / `Fulfilled` / `Cancelled`) · `From *` (`2024-10-01`) · `To *` (`2024-10-24`) · `Order # or Party` (placeholder `Search PO, SO, vendor...`)
- **Action bar:** `Generate Report` (`play_circle`) · `Clear Filters` · note `Max report window: 90 days. Query runs against active stock ledgers.`
- **Stale banner:** `Filters changed. Generate the report again to apply them.` + `Regenerate`
- **Stock Movement result:** criteria bar `Generated for 01 Oct 2024 – 24 Oct 2024` • `All Warehouses` • `All Movement Types` • `48 Records Found` • `Execution: 42ms`; KPI cards `Total Movements 48` (`100% Total`), `Receipts (Inflow) 26` (`+2,150 pcs`), `Issues (Outflow) 18` (`-407 pcs`), `Adjustments 4` (`+5 pcs net`)
  Columns `Timestamp` · `Product & Specification` · `SKU` · `Warehouse` · `Movement Type` · `Quantity` (right) · `Reference` · `Performed By`
  | Timestamp | Product | SKU | Warehouse | Type | Qty | Ref | By |
  |---|---|---|---|---|---|---|---|
  | 24 Oct 2024 16:42 | Industrial Steel Plate (12mm x 4x8) | SKU-STL-0089 | Warehouse A (WH-JKT-01) | Receipt | +120 pcs | PO-2024-0018 | Sarah Jenkins |
  | 24 Oct 2024 14:15 | Hydraulic Valve Unit HV-40 | SKU-VLV-1002 | Warehouse B (WH-SBY-02) | Issue | -15 pcs | SO-2024-0025 | Budi Santoso |
  | 23 Oct 2024 11:30 | High-Temp Lubricant Drum 200L | SKU-LUB-9941 | Warehouse A (WH-JKT-01) | Adjustment | +2 pcs | ADJ-2024-0003 | Sarah Jenkins |
  | 22 Oct 2024 09:20 | Copper Core Wiring 500m Coil | SKU-ELC-5420 | Warehouse C (WH-BDG-03) | Receipt | +400 pcs | PO-2024-0017 | Sarah Jenkins |
  | 21 Oct 2024 17:05 | Pneumatic Actuator PA-100 | SKU-ACT-3029 | Warehouse A (WH-JKT-01) | Issue | -32 pcs | SO-2024-0024 | Budi Santoso |
  | 20 Oct 2024 13:40 | Precision Ball Bearings 6205-2RS | SKU-BRG-4412 | Warehouse B (WH-SBY-02) | Receipt | +1,200 pcs | PO-2024-0016 | Dewi Lestari |
  | 19 Oct 2024 15:55 | Hex Bolt M16 x 80 Grade 8.8 | SKU-FST-8821 | Warehouse A (WH-JKT-01) | Issue | -250 pcs | SO-2024-0023 | Sarah Jenkins |
  | 18 Oct 2024 10:10 | Industrial Filter Cartridge 5 Micron | SKU-FLT-0911 | Warehouse C (WH-BDG-03) | Receipt | +300 pcs | PO-2024-0015 | Sarah Jenkins |
  | 17 Oct 2024 14:02 | Safety Relief Valve 150 PSI | SKU-VLV-3004 | Warehouse B (WH-SBY-02) | Adjustment | +3 pcs | ADJ-2024-0002 | Dewi Lestari |
  | 16 Oct 2024 08:45 | Electric Motor 3-Phase 15kW | SKU-MTR-7718 | Warehouse A (WH-JKT-01) | Issue | -10 pcs | SO-2024-0022 | Sarah Jenkins |
- **Order Status result:** criteria bar `Generated for 01 Oct 2024 – 24 Oct 2024` • `All Order Types` • `28 Orders Found` • `Execution: 38ms`; KPI cards `Total Orders 28` (`Active Ledger`), `Purchase Orders (PO) 12` (`9 Received`), `Sales Orders (SO) 16` (`11 Fulfilled`), `Pending / In-Review 5` (`Action Req.`, error-tinted)
  Columns `Order Type` · `Order Number` · `Related Party (Vendor / Customer)` · `Facility` · `Order Date` · `Status` · `Actions` (right)
  | Type | Number | Party | Facility | Date | Status |
  |---|---|---|---|---|---|
  | PO | PO-2024-0018 | PT Krakatau Baja Industrial | Warehouse A (WH-JKT-01) | 24 Oct 2024 | Received |
  | SO | SO-2024-0025 | PT Megatama Konstruksi Prima | Warehouse B (WH-SBY-02) | 24 Oct 2024 | Fulfilled |
  | SO | SO-2024-0024 | CV Sinar Mandiri Abadi | Warehouse A (WH-JKT-01) | 21 Oct 2024 | Fulfilled |
  | PO | PO-2024-0017 | PT Petrokimia Nusantara | Warehouse C (WH-BDG-03) | 22 Oct 2024 | PendingApproval |
  | PO | PO-2024-0016 | SKF Bearing Supply Pte | Warehouse B (WH-SBY-02) | 20 Oct 2024 | Received |
  | SO | SO-2024-0021 | PT Wijaya Karya Logistik | Warehouse A (WH-JKT-01) | 15 Oct 2024 | Draft |
  Row action: `View Order`
- **States shipped (8):** `1. Initial` · `2. Stock Movement` · `3. Filter Changed` · `4. Order Status` · `5. No Data` · `6. Loading` · `7. Error State` · `8. 403 Forbidden`
  - Initial: `Generate a report` / `Choose a report type and date range above to view aggregated operational metrics and audit ledger items.` + `Run Default Report`
  - Empty: `No data for this period` / `No stock movements or order records match the selected warehouse and date filters. Try broadening your criteria.` + `Clear Filters`
  - Error: `Unable to generate the report` / `An internal database timeout occurred while compiling stock movement aggregations. Please try again.` + `Retry Query`
  - Forbidden: `403 - Unauthorized Access` / `Your role (Operations Auditor) does not have authorization to view financial-level order reconciliations. Please contact your system administrator.` + `Return to Default View`
  - Loading: reuses the initial container, disables `Generate Report`, label → `Generating report...`, icon → `progress_activity animate-spin`
- **Charts:** **none** — all metrics are text KPI tiles. The stale state additionally blurs/dims the stock table card.

---

## 13. Report charts and data visualizations

Charts exist on exactly **one** screen: `reports_with_data_visualization_inventory_order_management`.
No charting library is loaded anywhere in the export — there is no Chart.js, D3, ECharts, canvas
element, or `<svg>` generated by script. The only chart is a **hand-authored inline SVG**; every
other "visualization" is a CSS `div` with an inline percentage width.

### 13.1 Reports (with data visualization) — screen frame

- **Breadcrumb:** `Reports` `chevron_right` `Operational Reports` · **H1:** `Reports`
- **Subtitle:** `Generate operational reports, analyze historical inventory movements, and export data audits.`
- **Actions:** `Export CSV` (with a lifecycle status pip — see §13.6) · `Generate Report` (`play_arrow`, spinner `progress_activity`)
- **Query panel — `Report Query Parameters`** (`tune`), note `Default: Snapshot Last 30 Days`, `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end`:
  | Field | Options |
  |---|---|
  | `Report Type *` (icon `expand_more`) | `STOCK_VALUATION` = **Stock Valuation & Turnover Summary** (selected) · `SALES_FULFILLMENT` = Sales & Order Fulfillment Performance · `INVENTORY_MOVEMENTS` = Inventory Movements & Audit Ledger |
  | `Date Range *` (icon `calendar_month`) | `LAST_30_DAYS` = **Last 30 Days (Oct 01 - Oct 31, 2024)** (selected) · `THIS_QUARTER` = This Quarter (Q4 2024) · `YEAR_TO_DATE` = Year to Date (YTD 2024) · `PREVIOUS_MONTH` = Previous Month (Sep 2024) |
  | `Warehouse Location` (icon `warehouse`) | `ALL` = **All Warehouses (National)** (selected) · `JKT_CENTRAL` = Jakarta Central Fulfillment Hub · `SBY_WEST` = Surabaya West Depot · `MDN_NORTH` = Medan North Regional |
  | `Product Category` (icon `category`) | `ALL` = **All Categories** (selected) · `FINISHED_GOODS` · `RAW_MATERIALS` · `ELECTRONICS` = Electronics & Components · `PACKAGING` = Packaging Materials |
  Panel actions: `Reset Filters` (`restart_alt`) · `Apply & Generate` (`analytics`)
- **Summary metric tiles (4):**
  | Label | Value | Footer |
  |---|---|---|
  | `Total Stock Valuation` (`account_balance_wallet`) | `$1,482,950.00` | `+4.2%` (`text-emerald-600`, `trending_up`) `vs previous 30-day period` |
  | `Active SKU Catalog` (`inventory_2`) | `1,248 Items` | `98.4%` `actively cycling stock items` |
  | `Inbound / Outbound` (`swap_horiz`) | `18,420 In / 14,890 Out` | `+3,530 Net` `units safety inventory surge` |
  | `Turnover Velocity` (`speed`) | `4.8x` + badge `Healthy` | `Benchmark Target: >4.0x / yr` |

### 13.2 Visualization block header

- H2 `Report Visual Analytics & Trends` (`insights`)
- Subtitle `Visual breakdown for Stock Valuation & Turnover (Oct 01 - Oct 31, 2024)`
- Insight pill (`bolt`): `Fastest Turnover: Raw Materials (6.1x) • Highest Capital: Finished Goods ($682K)`
- Three view modes (`role="tablist"`): `Trend Overview (6-Month)` (active) · `Category Distribution` · `Warehouse Allocation`
- Aggregation note: `Aggregation: Monthly Rolling Ledger`

### 13.3 Chart 1 — Trend Overview (inline SVG, dual-axis grouped bars + line)

Container: `relative w-full h-72 bg-surface-container-low/50 rounded-lg p-4 overflow-hidden`
SVG: `viewBox="0 0 900 240"`, `class="w-full h-full"`, `fill="none"`, `preserveAspectRatio="none"`,
`role="img"`, `aria-label="Stock valuation and movements over last 6 months"`.

**Legend (4 series):**

| Swatch | Series label |
|---|---|
| `w-3 h-3 rounded-sm bg-primary` (`#00236f`) | `Valuation ($K)` |
| `w-3 h-3 rounded-sm bg-[#0ea5e9]` | `Inbound Volume (Units)` |
| `w-3 h-3 rounded-sm bg-secondary-container` (`#dae2fd`) | `Outbound Volume (Units)` |
| `w-4 h-0.5 bg-error` (`#ba1a1a`) | `Target Turnover SLA` |

**Gridlines** (`x1="50" x2="880"`):

| y | Stroke | Dash |
|---|---|---|
| 20 | `#cbd5e1` | `3 3` |
| 65 | `#cbd5e1` | `3 3` |
| 110 | `#cbd5e1` | `3 3` |
| 155 | `#cbd5e1` | `3 3` |
| 200 (baseline) | `#94a3b8` | solid |

**Left axis labels** — `fill="#64748b"`, `font-family="JetBrains Mono"`, `font-size="10"`,
`text-anchor="end"`, `x="40"`:

| Label | y |
|---|---|
| `$1,600K` | 24 |
| `$1,200K` | 69 |
| `$800K` | 114 |
| `$400K` | 159 |
| `$0` | 204 |

**Bars** — three per month; valuation bar `width="18"`, the two volume bars `width="14"`, all `rx="1"`:

| Month | Valuation `#00236f` (x, y, h) | Inbound `#0ea5e9` (x, y, h) | Outbound `#dae2fd` (x, y, h) | X-label (x=…, y=218) |
|---|---|---|---|---|
| May 2024 | 95, 65, 135 | 116, 85, 115 | 133, 100, 100 | `May 2024` @ 122 |
| Jun 2024 | 235, 55, 145 | 256, 75, 125 | 273, 90, 110 | `Jun 2024` @ 262 |
| Jul 2024 | 375, 50, 150 | 396, 68, 132 | 413, 82, 118 | `Jul 2024` @ 402 |
| Aug 2024 | 515, 44, 156 | 536, 60, 140 | 553, 70, 130 | `Aug 2024` @ 542 |
| Sep 2024 | 655, 38, 162 | 676, 52, 148 | 693, 65, 135 | `Sep 2024` @ 682 |
| Oct 2024 | 795, 33, 167 | 816, 45, 155 | 833, 60, 140 | `Oct 2024` @ 822 |

X-axis label style: `fill="#475569"`, `font-family="Inter"`, `font-size="11"`, `font-weight="500"`,
`text-anchor="middle"`. **The active month (Oct 2024) is emphasised**: `fill="#00236f"`,
`font-weight="700"`.

**Target Turnover SLA line:**
```
<path d="M 122 75 L 262 70 L 402 62 L 542 58 L 682 50 L 822 42"
      fill="none" stroke="#ba1a1a" stroke-linecap="round" stroke-width="2"/>
```
**Data points:** `<circle r="3.5" fill="#ba1a1a" stroke="#ffffff" stroke-width="1.5">` at
(122,75) (262,70) (402,62) (542,58) (682,50) — and an **emphasised final point**
`r="4.5"` with `stroke-width="2"` at (822,42).

Notes for implementation: the chart is static geometry with no scale function, no tooltip, no hover
state, no axis on the right despite being described as dual-axis, and `preserveAspectRatio="none"`
means it stretches non-uniformly with its container.

### 13.4 Chart 2 — Category Distribution (CSS stacked bar + legend cards)

- Heading `Valuation Share & Velocity by Inventory Category` · right label `Total: $1,482,950.00 Across 4 Categories`
- Stacked master bar: `w-full h-4 rounded-full overflow-hidden flex bg-surface-container` with four
  segments, each carrying a `title` attribute:

| Segment | Fill | Width | `title` |
|---|---|---|---|
| 1 | `bg-primary` (`#00236f`) | `46%` | `Finished Goods: 46%` |
| 2 | `bg-[#0ea5e9]` | `28%` | `Raw Materials: 28%` |
| 3 | `bg-surface-tint` (`#4059aa`) | `18%` | `Electronics: 18%` |
| 4 | `bg-secondary` (`#565e74`) | `8%` | `Packaging: 8%` |

- Legend cards (`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2`), each
  `p-3 rounded bg-surface-container-low flex flex-col justify-between space-y-2`:

| Category | Pip | Share | Valuation | SKUs | Velocity |
|---|---|---|---|---|---|
| `Finished Goods` | `bg-primary` | `46%` (`text-primary`) | `$682,157.00` | `420 SKUs` | `4.4x / yr` |
| `Raw Materials` | `bg-[#0ea5e9]` | `28%` (`text-[#0284c7]`) | `$415,226.00` | `340 SKUs` | `6.1x (Leader)` (`text-emerald-600`) |
| `Electronics & Parts` | `bg-surface-tint` | `18%` (`text-surface-tint`) | `$266,931.00` | `288 SKUs` | `4.8x / yr` |
| `Packaging` | `bg-secondary` | `8%` (`text-secondary`) | `$118,636.00` | `200 SKUs` | `7.8x / yr` (`text-emerald-600`) |

Card row divider: `pt-1 border-t border-surface-container-high` above the `Velocity:` line.

### 13.5 Chart 3 — Warehouse Allocation (CSS progress cards, hidden by default)

- Heading `Geographic Capital Allocation` · right label `3 Regional Nodes`
- `grid grid-cols-1 md:grid-cols-3 gap-4`, each card `p-4 rounded-lg bg-surface-container-low space-y-2`
  with a track `w-full bg-surface-container-high h-2 rounded-full overflow-hidden`:

| Node | Tag | Value | Bar fill / width | Caption |
|---|---|---|---|---|
| `Jakarta Central Hub` | `Primary` (`bg-primary-container text-on-primary`) | `$860,110.00` | `bg-primary` / `58%` | `58% total capital • 680 SKUs active` |
| `Surabaya West Depot` | `Secondary` (`bg-surface-container-highest text-on-surface`) | `$444,885.00` | `bg-surface-tint` / `30%` | `30% total capital • 390 SKUs active` |
| `Medan North Regional` | `Spoke` (`bg-surface-container-highest text-on-surface`) | `$177,955.00` | `bg-secondary` / `12%` | `12% total capital • 178 SKUs active` |

### 13.6 Reports-viz data table

- Toolbar: search `Filter report results...` (`onkeyup="filterTableRows()"`); count
  `Showing 5 of 124 records`; buttons `Columns (9)` (`view_column`) and
  `Sort: Valuation (High-Low)` (`filter_list`) — both static, no handler.
- Columns: `SKU Code` · `Product Name` · `Category` · `Warehouse Hub` · `Current Stock` (right) ·
  `Unit Cost` (right) · `Total Valuation` (right) · `30-Day Turnover` (right) · `Status` (center)

| SKU | Product | Category | Hub | Stock | Unit Cost | Valuation | Turnover | Status |
|---|---|---|---|---|---|---|---|---|
| SKU-8841 | Industrial Servo Motor V4 | Finished Goods | Jakarta Central | 450 units | $120.00 | $54,000.00 | 5.2x | In Stock |
| SKU-7729 | Microcontroller Core Board | Electronics & Parts | Surabaya West | 1,200 units | $35.00 | $42,000.00 | 4.8x | Optimal |
| SKU-3301 | Aluminum Casing Alloy 6061 | Raw Materials | Jakarta Central | 3,800 units | $18.50 | $70,300.00 | 6.4x (`text-emerald-600`) | High Velocity |
| SKU-1142 | High-Torque Stepper Motor | Finished Goods | Medan North | 180 units | $85.00 | $15,300.00 | 2.1x (`text-amber-600`) | Slow Moving |
| SKU-9012 | Polymer Insulator Seal 40mm | Packaging Materials | Surabaya West | 8,500 units | $2.40 | $20,400.00 | 7.8x (`text-emerald-600`) | Optimal |

- Footer: `Showing 1–5 of 124 line items` · `Page 1 of 25` · `Previous` (disabled) · `1` · `2` · `3` · `...` · `25` · `Next`

### 13.7 Reports-viz state machine (exact)

Five states via `setAppState(state)`; the Export-CSV button has a **status pip whose colour and
label encode the state** — this is the only lifecycle-coded button in the export:

| State | Visible container | Export button | Pip colour | Export label |
|---|---|---|---|---|
| `DATA` | `#state-data` | enabled | `bg-emerald-600` | `Export CSV` |
| `STALE` | `#state-data` + `#banner-stale-data` | **disabled** | `bg-amber-500` | `Export CSV (Stale)` |
| `BLANK` | `#state-blank` | disabled | `bg-outline` | `Export CSV` |
| `LOADING` | `#state-loading` | disabled | `bg-outline` | `Export CSV` |
| `EMPTY` | `#state-empty` | disabled | `bg-error` | `Export CSV (0)` |

- `onFilterChange()` moves `DATA` → `STALE` on any filter change.
- `triggerGenerateSequence()` → `LOADING`, spinner shown, label `Generating...`, then after **700ms** → `DATA`.
- `handleExportClick()` returns early unless the state is `DATA`; otherwise
  `alert('Generating export for "Stock Valuation & Turnover Summary" (124 records). Download will start shortly.')`.
- **Stale banner:** `Report Parameters Outdated (Stale Data)` / `Filter criteria have been modified post-generation. Visualizations and tabular ledgers below reflect the prior query snapshot.` + `Re-generate Report` (`sync`)
- **Blank state:** `No Report Generated Yet` (`query_stats` in a `w-16 h-16` circle) / `Configure the report criteria above, choose date intervals and warehouse scopes, then click "Generate Report" to compile real-time ledger metrics and charts.` + `Run Default Report`
- **Loading state:** header `Compiling Multi-Warehouse Ledger Datasets...` / `Aggregating 1,248 SKUs, FIFO inventory balances, and cost centers across 3 distribution facilities.` / `Processing... 42%`; then a 4-tile KPI skeleton and a chart skeleton (`h-5 w-64` title bar + `h-56 w-full` plot block)
- **Empty state:** `Zero Records Match Your Parameters` (`folder_off`) / `No inventory transactions or turnover ledgers matched the selected filter configuration for this timeframe.` + `Reset to Default Filters`

---

## 14. Empty / loading / error / success states

This section consolidates the state model. **Nothing here is new** — the 16-state registry is
verbatim from `system_states_inventory_order_management`, and every per-screen instance was listed in §12.

### 14.1 The 16 canonical states (verbatim from the export)

| # | State | Category / Tier | Trigger / Lifecycle | Canonical Anatomy | Allowed Next States |
|---|---|---|---|---|---|
| 01 | `LOADING` | Asynchronous | Initial screen query dispatch or cache invalidation fetch | Standalone Skeleton / Layout Frame | DATA, EMPTY, NETWORK_ERROR, SERVER_ERROR |
| 02 | `DATA` | Normal Content | Data successfully returned with ≥1 records or valid record view | Tabular Data / Card Grids / Forms | ACTION_LOADING, STALE_DATA, CONFIRMATION |
| 03 | `EMPTY` | Boundary Content | Dataset has exactly 0 total records in tenant domain | Standalone Icon + Action | ACTION_LOADING, DATA |
| 04 | `NO_RESULTS` | Boundary Content | Active search filters return 0 records from populated dataset | Standalone Clear Filter View | LOADING, DATA |
| 05 | `VALIDATION_ERROR` | User Correction | Form control values breach schema constraints or requiredness | Field-level Message & Error Border | ACTION_LOADING, DATA |
| 06 | `FORBIDDEN` | Access Security | HTTP 403 response; user RBAC role lacks privilege key | Standalone Center Guard | SESSION_EXPIRED, DATA |
| 07 | `NOT_FOUND` | Access Security | HTTP 404 response; record UUID / route non-existent | Standalone Center Guard | DATA |
| 08 | `SESSION_EXPIRED` | Access Security | JWT / Refresh token revocation or warehouse terminal timeout | Standalone Re-login Card | LOADING, DATA |
| 09 | `SERVER_ERROR` | Infrastructure | HTTP 500, 502, 503 response from core backend microservice | Standalone Trace View | LOADING, ACTION_LOADING |
| 10 | `NETWORK_ERROR` | Infrastructure | Client navigator offline, socket disconnect, or gateway drop | Standalone Reconnect Diagnostic | LOADING, DATA |
| 11 | `MUTATION_SUCCESS` | Operational Status | Successful POST, PUT, or DELETE transaction acknowledgement | Inline Toast / Contextual Banner | DATA |
| 12 | `MUTATION_ERROR` | Operational Status | Server rejected state modification due to lock or rule breach | Inline Banner with Retry Action | ACTION_LOADING, DATA |
| 13 | `BUSINESS_ERROR` | Domain Rule | Negative inventory attempt, credit breach, or workflow locking | Inline Conflict Banner / Action Option | ACTION_LOADING, DATA |
| 14 | `STALE_DATA` | Cache State | Report filter mutated post-generation or concurrent edit conflict | Inline Warning Strip + Re-run Prompt | LOADING, DATA |
| 15 | `CONFIRMATION` | Interactive Modal | User initiates destructive or irreversible transactional operation | Dialog (Cancel + Confirm) | ACTION_LOADING, DATA |
| 16 | `ACTION_LOADING` | Interactive Transition | Button clicked, awaiting asynchronous mutation commitment | Control Spinner (Fixed Height 40px) | MUTATION_SUCCESS, MUTATION_ERROR |

Header rule printed with the matrix: *"Strict definition table. No aliases permitted. Refresh is
categorized strictly as an action trigger, while empty create forms are normal DATA instances."*
Counters printed in the ribbon: `CANONICAL STATES 16 Registered` · `ANATOMY MODELS 5 Canonical` ·
`COMPLIANCE WCAG 2.1 AA`.

### 14.2 State metadata registry (from the explorer's JS `stateMetadata`)

| State | ID | Category | Anatomy | ARIA contract |
|---|---|---|---|---|
| LOADING | `01_LOADING` | ASYNCHRONOUS | Structural Skeleton Canvas | `aria-busy="true"` / `aria-live="polite"` / `role="status"` |
| DATA | `02_DATA` | NORMAL CONTENT | Tabular Data / Card Grids | `aria-busy="false"` / `role="table" / "region"` |
| EMPTY | `03_EMPTY` | BOUNDARY CONTENT | Standalone Icon + Action | `role="status"` / `aria-label="No data available"` |
| NO_RESULTS | `04_NO_RESULTS` | BOUNDARY CONTENT | Standalone Clear Filter View | `role="status"` / `aria-live="polite"` |
| VALIDATION_ERROR | `05_VALIDATION_ERROR` | USER CORRECTION | Field-level Message & Error Border | `aria-invalid="true"` / `aria-describedby="[field]-error"` |
| FORBIDDEN | `06_FORBIDDEN` | ACCESS SECURITY | Standalone Center Guard | `role="alert"` / `aria-atomic="true"` |
| NOT_FOUND | `07_NOT_FOUND` | ACCESS SECURITY | Standalone Center Guard | `role="alert"` / `aria-atomic="true"` |
| SESSION_EXPIRED | `08_SESSION_EXPIRED` | ACCESS SECURITY | Standalone Re-login Card | `role="alertdialog"` / `aria-modal="true"` |
| SERVER_ERROR | `09_SERVER_ERROR` | INFRASTRUCTURE | Standalone Trace View | `role="alert"` / `aria-live="assertive"` |
| NETWORK_ERROR | `10_NETWORK_ERROR` | INFRASTRUCTURE | Standalone Reconnect Diagnostic | `role="alert"` / `aria-live="assertive"` |
| MUTATION_SUCCESS | `11_MUTATION_SUCCESS` | OPERATIONAL STATUS | Inline Toast / Contextual Banner | `role="status"` / `aria-live="polite"` |
| MUTATION_ERROR | `12_MUTATION_ERROR` | OPERATIONAL STATUS | Inline Banner with Retry Action | `role="alert"` / `aria-live="assertive"` |
| BUSINESS_ERROR | `13_BUSINESS_ERROR` | DOMAIN RULE | Inline Conflict Banner / Action Option | `role="alert"` / `aria-live="assertive"` |
| STALE_DATA | `14_STALE_DATA` | CACHE STATE | Inline Warning Strip + Re-run Prompt | `role="status"` / `aria-live="polite"` |
| CONFIRMATION | `15_CONFIRMATION` | INTERACTIVE MODAL | Dialog (Cancel + Confirm) | `role="dialog"` / `aria-modal="true"` |
| ACTION_LOADING | `16_ACTION_LOADING` | INTERACTIVE TRANSITION | Control Spinner (Fixed Height 40px) | `aria-busy="true"` / `aria-disabled="true"` |

Extra trigger prose from the registry (differs slightly from the matrix, both are in the export):
LOADING — *"Preserves structural bounding boxes to avoid layout shifting (CLS < 0.01)."* ·
DATA — *"All cells formatted with semantic monospace values."* ·
EMPTY — *"Directs user to the initial creation or CSV onboarding pathway."* ·
SESSION_EXPIRED — *"30-minute terminal idle timeout or security token revocation during shift handover."* ·
ACTION_LOADING — *"Button disables and presents stable 40px spinner."*

### 14.3 The 5 canonical anatomies (verbatim)

| # | Anatomy | Composition |
|---|---|---|
| 1 | Standalone Center Anatomy | `Icon (32px) → Title (24px/32px/600) → Description (14px/20px) → Primary/Secondary Actions` |
| 2 | Inline Feedback Anatomy | `Status Icon (20px) → Contextual Message → Inline Action Button → Dismiss Trigger` |
| 3 | Field-Level Validation Anatomy | `Label (label-sm) → Input Control (32px/40px) → Validation Message + Alert Icon (body-xs)` |
| 4 | Dialog / Modal Confirmation Anatomy | `Header & Status Icon → Critical Warning Text → Action Pair (Cancel + Primary/Destructive Confirm)` |
| 5 | Action Loading Anatomy | `Height-Locked Control (40px) → Animated Spinner → Progress / Active Verb Label` |

### 14.4 Screen → permitted state map (verbatim)

| IOMS Screen / Subsystem | Permitted Canonical States | Domain Enforcements & Boundary Guardrails |
|---|---|---|
| Login (`/login`) | LOADING · VALIDATION_ERROR · NETWORK_ERROR · SERVER_ERROR · SESSION_EXPIRED | `No EMPTY state allowed. Password inputs strictly sanitized on validation errors.` |
| Dashboards (`/dashboard`) | LOADING · DATA · FORBIDDEN · NETWORK_ERROR · SERVER_ERROR · EMPTY (widget) | `Empty state is scoped inside individual widgets (e.g. "No pending PO approvals").` |
| Master Data Lists (Products, Warehouses, Suppliers, Customers) | LOADING · DATA · EMPTY · NO_RESULTS · FORBIDDEN · NETWORK_ERROR · MUTATION_SUCCESS | `Strict differentiation between EMPTY (zero master records) and NO_RESULTS (search query yields zero).` |
| Orders & Movements (PO, SO, Goods Receipt, Issues) | **ALL 16 CANONICAL STATES** · BUSINESS_ERROR · CONFIRMATION | `Requires BUSINESS_ERROR for stock shortfalls and CONFIRMATION dialogs for dispatch commitments.` |
| Stock Ledger (`/stock-ledger`) | LOADING · DATA · NO_RESULTS · FORBIDDEN · SERVER_ERROR | `Immutable append-only ledger. No mutation or deletion states permitted.` |
| Reports (`/reports`) | STALE_DATA · LOADING · DATA · NO_RESULTS | `Lifecycle rule: Pre-generation is normal DATA; parameter modifications trigger STALE_DATA instantly.` |

### 14.5 Reference specimens for each state (from the explorer's preview stage)

| State | Specimen content (verbatim) |
|---|---|
| LOADING | Skeleton header + 5-column skeleton table with 3 rows; footer `Resolving query: GET /api/v2/orders/stock-allocations` (with `animate-ping` dot) and `Expected payload: 14.8 KB` |
| DATA | `Live Warehouse Stock` + `3 Active Items` + `Receive Pallet` button; table `SKU / Code` · `Description` · `Physical On-Hand` (right) · `Allocated` (right) · `Status` (center) — `SKU-99201 Industrial High-Temp Valve 40mm 1,450 ea / 320 ea / Optimal`, `SKU-88412 Standard Nitrile O-Ring Kit (Box 50) 240 ea / 210 ea / Reorder`, `SKU-33019 Pneumatic Actuator Assembly 18 ea / 18 ea / Stockout` |
| EMPTY | `inventory_2` · `No Products Cataloged` / `This tenant inventory register does not have any items defined yet. Create your initial SKU or import existing inventory records from CSV.` · `Create Product` / `Import CSV` |
| NO_RESULTS | `search_off` · `No Matching Records Found` / `No purchase orders or stock transfers match query "PO-2024-X99Z" within selected warehouse scope.` · `Clear All Filters` / `Modify Search` |
| VALIDATION_ERROR | `Product Definition Entry` + badge `2 Validation Errors`; `SKU Code *` value `SKU/494-INVALID#` → `SKU may only contain alphanumeric characters, hyphens, and underscores.`; `Reorder Threshold Quantity *` value `-25` → `Reorder threshold must be an integer value greater than or equal to 0.`; footer `Cancel` / `Save Product` |
| FORBIDDEN | `lock` · `Access Forbidden (403)` / `Your current enterprise role [Warehouse_Staff] lacks the privilege stock:ledger:write required to adjust inventory balances.` · `Request Permission Elevation` / `Return to Dashboard` |
| NOT_FOUND | `travel_explore` · `Record Not Located (404)` / `The requested document identifier #PO-88192-2023 was either purged, archived, or never existed in this domain.` · `View Purchase Orders List` / `Check Archive` |
| SESSION_EXPIRED | `timer_off` · `Authentication Session Expired` / `Your secure shift token expired due to 30 minutes of terminal inactivity. Please re-authenticate to preserve pending transactional forms.` · `Log In Again` (`login`) |
| SERVER_ERROR | `cloud_off` · `Internal Server Fault (500)` / `The upstream inventory ledger service encountered an unhandled transactional deadlock. No records were corrupted.` + `Trace ID: trc_99a80bf143c088f7` · `Retry Transaction` / `Contact Systems SRE` |
| NETWORK_ERROR | `wifi_off` · `Network Link Disconnected` / `Unable to contact API gateway. Please verify your handheld scanner Wi-Fi connection or warehouse mesh access point.` · `Test Connection` (`autorenew`) / `Work Offline` |
| MUTATION_SUCCESS | `check_circle` · `Stock Count Reconciled` + `Just now` / `Cycle count batch #CC-2024-09 has been committed to the primary stock ledger. 42 line items balanced successfully.` + dismiss `close` |
| MUTATION_ERROR | `error` on `bg-error` circle · `Failed to Create Purchase Order` + `Err Code: PO_POST_FAIL` / `Vendor account #VND-9920 has been placed on credit hold by corporate treasury. PO dispatch rejected.` · `Retry Mutation` / `View Vendor Ledger` |
| BUSINESS_ERROR | `warning` · `Allocation Conflict: Negative Inventory Rule Triggered` / `Cannot fulfill Sales Order #SO-7049: requested 500 units of SKU-4401 in Warehouse East, but unallocated physical available balance is only 320 units.` · `Authorize Partial Dispatch (320)` / `Initiate Inter-Warehouse Transfer` |
| STALE_DATA | `sync_problem` · `Filter criteria changed. This inventory valuation report reflects previous query parameters.` · `Re-generate Report`; below it a greyed sheet (`opacity-60`) labelled `SKU Valuation Aggregate` / `Generated 14m ago` |
| CONFIRMATION | `delete_forever` on `bg-error-container` circle · `Decommission Warehouse` + `Irreversible Destructive Action` / `Are you certain you wish to archive and decommission Warehouse Bay 04 (Austin Depot)? All 12 assigned bin locations will be deregistered and bin transfers halted.` · `Keep Active (Cancel)` / `Confirm Decommission` |
| ACTION_LOADING | `Batch Dispatch Processing` + `Step 2 of 3` / `Allocating physical bin inventory and generating manifest bills of lading...`; bar `w-2/3`, labels `Manifest 18 / 27` and `66%`; button `Processing Commit...` at `h-10`, disabled, with an inline SVG spinner |

### 14.6 Per-screen state coverage (which of the 16 each screen actually ships)

| Screen | States shipped |
|---|---|
| Login | DATA (default) · VALIDATION_ERROR · MUTATION_ERROR (`Auth Failed`) · BUSINESS_ERROR (`Inactive Account`) · ACTION_LOADING (`Signing In...`) |
| Admin dashboard | DATA · EMPTY (×2 widget-scoped) · LOADING · SERVER_ERROR |
| Sales dashboard | DATA · EMPTY (all) · EMPTY (queue) · NO_RESULTS (filter) · LOADING · NETWORK_ERROR |
| Warehouse dashboard | DATA · EMPTY (×3 widget-scoped) · LOADING · NETWORK_ERROR |
| Products list | DATA · DATA (read-only) · DATA (filtered) · NO_RESULTS · EMPTY · LOADING · SERVER_ERROR (503) · MUTATION_SUCCESS |
| Categories | DATA · DATA (read-only) · NO_RESULTS · EMPTY · LOADING · VALIDATION_ERROR · MUTATION_SUCCESS |
| Warehouses | DATA · DATA (read-only) · CONFIRMATION (×3 modals) · VALIDATION_ERROR · NO_RESULTS · EMPTY · LOADING · MUTATION_SUCCESS (×2) · MUTATION_ERROR |
| Suppliers | DATA · DATA (read-only) · CONFIRMATION · VALIDATION_ERROR · NO_RESULTS · EMPTY · LOADING · MUTATION_SUCCESS |
| Customers | DATA · DATA (read-only) · CONFIRMATION · VALIDATION_ERROR · NO_RESULTS · EMPTY · LOADING · MUTATION_SUCCESS |
| Users | DATA · CONFIRMATION (×2) · DATA (detail modal) · LOADING · NO_RESULTS · FORBIDDEN (403) · MUTATION_SUCCESS |
| User create/edit | DATA (create) · DATA (edit) · BUSINESS_ERROR (self-lockout) · VALIDATION_ERROR · MUTATION_ERROR (duplicate email) · ACTION_LOADING · CONFIRMATION (unsaved) · NOT_FOUND · FORBIDDEN |
| Product detail | DATA · DATA (read-only) · DATA (inactive) · CONFIRMATION · EMPTY (no stock) · LOADING · NOT_FOUND · MUTATION_SUCCESS |
| Product create/edit | DATA (edit) · DATA (create) · VALIDATION_ERROR · ACTION_LOADING · CONFIRMATION (unsaved) · MUTATION_SUCCESS |
| Warehouse stock detail | DATA · DATA (low-stock filter) · DATA (inactive facility) · EMPTY · NO_RESULTS · LOADING · NOT_FOUND · MUTATION_SUCCESS |
| PO list | DATA (×3 roles) · LOADING · EMPTY · NO_RESULTS · NETWORK_ERROR · MUTATION_SUCCESS |
| PO detail | DATA (×5 statuses × 2 roles) · LOADING · MUTATION_ERROR (sync anomaly) · NOT_FOUND · CONFIRMATION (×2) · MUTATION_SUCCESS |
| PO create/edit | DATA (create) · DATA (draft) · DATA (locked) · VALIDATION_ERROR · ACTION_LOADING · EMPTY (catalogs) · CONFIRMATION · MUTATION_SUCCESS |
| Goods Receipt | DATA (×3 fulfilment shapes) · VALIDATION_ERROR · CONFIRMATION · ACTION_LOADING · MUTATION_SUCCESS · STALE_DATA · FORBIDDEN-like (ineligible) · EMPTY · MUTATION_ERROR |
| SO list | DATA (×3 personas) · LOADING · EMPTY · NO_RESULTS · SERVER_ERROR (504) · CONFIRMATION (×5) · MUTATION_SUCCESS |
| SO detail | DATA (×5 statuses × 3 roles) · CONFIRMATION (×3) · STALE_DATA (conflict) · LOADING (skeleton) · MUTATION_SUCCESS |
| SO create/edit | DATA (create) · DATA (draft) · DATA (pending) · DATA (approved) · VALIDATION_ERROR · CONFIRMATION (×3) · ACTION_LOADING · EMPTY (catalogs) · MUTATION_SUCCESS |
| Goods Issue | DATA · BUSINESS_ERROR (insufficient) · VALIDATION_ERROR · CONFIRMATION · ACTION_LOADING · MUTATION_SUCCESS · STALE_DATA · STALE_DATA (status changed) · ineligible · EMPTY · MUTATION_ERROR |
| Stock Ledger | DATA · DATA (filtered ×2) · DATA (detail modal) · VALIDATION_ERROR (date range) · LOADING · EMPTY · NO_RESULTS · SERVER_ERROR · FORBIDDEN |
| Reports (tabular) | DATA (pre-generation `Initial`) · DATA (×2 report types) · STALE_DATA · EMPTY · LOADING · SERVER_ERROR · FORBIDDEN |
| Reports-viz | DATA · DATA (blank) · LOADING · EMPTY · STALE_DATA |
| My Profile | DATA (clean) · DATA (dirty) · DATA (×2 other roles) · VALIDATION_ERROR · MUTATION_ERROR (email conflict) · ACTION_LOADING · MUTATION_SUCCESS · CONFIRMATION (unsaved) · LOADING · SERVER_ERROR |

**No screen ships a SESSION_EXPIRED view.** It exists only as a specimen in the state explorer and
as a permitted state on `/login` in the screen map.

---

## 15. Shell blueprint and design-system foundation screens

### 15.1 Authenticated Application Shell blueprint

- **H1:** `Authenticated Shell Blueprint & Reference Frame` + badge `Global System v1.0`
- **Shell Mode switcher:** `Expanded (Default)` · `Collapsed Rail` · `Mobile Drawer (360px)` → `setShellMode('expanded'|'collapsed'|'mobile')`
- **Active Role select:** `Role: Admin (Full Access)` (default) · `Role: Sales Representative` · `Role: Warehouse Specialist` → `setShellRole()`
- **Header pattern switcher:** `Pattern A (Dashboard)` · `Pattern B (Catalog)` → `setHeaderPattern('A'|'B')`
- **Principle chips:** `Persistent Standard Navigation` · `Zero Scope Creep Guaranteed` · `8px Grid Alignment System` · `No Consumer Notifications`
- **Simulated top bar:** `IOMS Operational Core / {crumb} / Live System Frame`; user block `Alex Morgan` + `ADMIN` badge + `Facility Central DC-1`; avatar with `expand_more` and a dropdown (`My Profile` / `Logout`)
- **Header Pattern A (overview):** breadcrumb `Enterprise Core › Dashboard`; H2 `Dashboard`; subtitle `Overview of real-time inventory levels, fulfillment pipelines, and active procurement.`; actions `Filter Period` (`tune`) · `Export Summary` (`file_download`)
- **Header Pattern B (catalog):** breadcrumb `Enterprise Core › Master Data › Products`; H2 `Products`; subtitle `Manage master SKU registry, default warehouse bin routing, and unit thresholds.`; actions `Bulk CSV` (`upload`) · `Add Product` (`add`)
- **Content slot demonstrators:** A — 4 KPI cards (`Active Open POs 42 / +6 today`, `Pending Fulfillment 118 / 94.2% SLA`, `Critical Stock Items 03 / Requires PO`, `Stock Ledger Shifts 1,894 / Last 24h`); B — filter toolbar (`Search orders, SKU code, or supplier...`, `All Warehouses` / `Main DC Seattle` / `Secondary Reno Hub`, `Status: Active` / `Status: In-Transit` / `Status: Draft`, `Displaying 1-4 of 24 entries`, refresh icon); C — `Active Operational Pipeline` table (`AUTO-REFRESH: 30s`) with columns `Reference ID` · `Entity / Destination` · `Category` · `Units` (right) · `Valuation` (right) · `Status` (center) · `Action` (right) and rows `PO-2024-9102 / Apex Precision Machinery Ltd / Procurement / 3,500 / $48,220.00 / In Transit`, `SO-2024-4190 / Pacific Logistics Warehouse Hub / Fulfillment / 820 / $14,100.50 / Processing`, `LED-2024-0012 / Seattle Hub Bin R-14 to A-02 / Internal Transfer / 150 / $2,250.00 / Complete`; D — empty state (`No matching transactions found` / `There are no operational records matching the filtered date range and criteria for this warehouse.` + `Clear All Filters` / `Create New Record`) and a shimmer specimen (`Shell Shimmer Skeleton Specimen` / `Querying Ledger`)
- **Inline feedback specimens:** `Goods Receipt Completed Successfully` / `PO-2024-8841 received at Warehouse West Wing. 1,420 units recorded into active inventory.`; `Reorder Threshold Reached (3 SKUs)` / `Hydraulic Coupling 40mm and 2 other master items below safety stock reserves.`
- **Role-based navigation matrix (verbatim — this is the authoritative role table in the export):**

| Navigation Item | Section | Admin | Sales Representative | Warehouse Staff | Behavior When Prohibited |
|---|---|---|---|---|---|
| Dashboard | Navigation | ✓ | ✓ | ✓ | `N/A (Universal entry)` |
| Products | Master Data | ✓ | ✓ (View Catalog) | ✓ (Bin details) | `N/A (Universal entry)` |
| Categories, Warehouses, Suppliers, Customers | Master Data | ✓ Full CRUD | — Hidden | — Hidden | `Omitted from DOM; 403 route guard` |
| Purchase Orders | Procurement | ✓ | — Hidden | ✓ (Inbound Receipt) | `Omitted for Sales` |
| Sales Orders | Sales | ✓ | ✓ Full Creation | ✓ (Pick/Pack Only) | `Scoped action triggers` |
| Stock Ledger | Inventory | ✓ | — Hidden | ✓ Reconciliation | `Omitted for Sales` |
| Reports | Reports | ✓ Complete Suite | ✓ (Sales metrics) | ✓ (Turnover) | `Scoped parameters` |
| Users | Administration | ✓ Manage Users | — Hidden | — Hidden | `Strict 403 Forbidden Access` |
| My Profile & Logout | Account | ✓ | ✓ | ✓ | `N/A (Universal identity)` |

Preamble: *"The application shell strictly validates user claims upon authentication. Navigation
groups and endpoints render deterministically based on operational clearance."*

- **Role→persona names in `setShellRole()`:** admin `Alex Morgan`, sales `Elena Rostova`, warehouse `Marcus Cole`. Sales hides `[data-role-req="admin"]`, `#sec-admin`, `#sec-procurement`, `#sec-inventory`; Warehouse hides `[data-role-req="admin"]` and `#sec-admin` only.
- **Collapsed rail rules (verbatim):** `1. Fixed width lock: Exactly 64px width.` · `2. Labels completely hidden from DOM flow (display: none).` · `3. Section headers converted to 1px visual dividers.` · `4. Native SVG & Material icon glyphs remain 20px.` Description: *"Optimized for maximum horizontal data canvas space on standard 1080p display terminals. Section labels collapse into subtle geometric dividers while high-contrast tooltips provide semantic names on pointer hover."*

### 15.2 Design System & UI Component Specification screen

Header: badge `Design System Spec v2.4` · `Production Grade • Enterprise Core` ·
H1 `Design System & UI Component Specification` ·
subtitle `Global visual language, semantic tokens, component patterns, and operational UI standards for the high-velocity Inventory & Order Management System.` ·
actions `Copy Tokens (JSON)` (`content_copy`) · `Export Design Tokens` (`download`).

Nine numbered sections, exactly as generated:

| # | Section heading | Right-hand label |
|---|---|---|
| 01 | `System Overview & Product Context` | `Operational Baseline` |
| 02 | `Color Palette & Semantic Tokens` | `WCAG AA Certified (≥4.5:1)` |
| 03 | `Typography Scale & 8-Point Spacing Grid` | `Inter Display & JetBrains Mono` |
| 04 | `Button System & Action Hierarchy` | `32px Regular / 28px Compact` |
| 05 | `Form Controls & Standard Filter Toolbar` | `High Density Inputs` |
| 06 | `Enterprise Data Table & Pagination System` | `36px Regular Row • Frozen Columns Ready` |
| 07 | `Unified Business Status Badges` | `Dual Coding: Tint + Border + Pill Indicator` |
| 08 | `Inline Alerts, Empty States & Skeletons` | `Operational Feedback` |
| 09 | `Responsive Principles & Accessibility Rules` | `Enterprise Compliance` |

**Section 01 persona cards (4):**

| Card label | Title | Description | Footer tag |
|---|---|---|---|
| `Access Model` (`verified_user`) | `Zero Public Sign-up` | `Closed enterprise intranet application. Accounts provisioned exclusively via Superadmin with granular role-based privileges.` | `Strict Auth Gateway` |
| `Primary Persona` (`admin_panel_settings`) | `System Admin` | `Full control over master data catalogs, users, financial thresholds, audit trail integrity, and warehouse routing configs.` | `Tier 1 Clearance` |
| `Sales Persona` (`point_of_sale`) | `Sales Representative` | `Drafts SOs, manages customer credit line checks, reserves multi-depot stock, tracks backorders, and manages invoice dispatch.` | `Customer & SO Focus` |
| `Operations Persona` (`forklift`) | `Warehouse Clerk` | `Executes PO receipt, bin inspections, pallet putaway, pick-pack-ship verification, batch tracking, and real-time ledger updates.` | `High Density Workflows` |

**Section 03 type-hierarchy matrix specimens:**

| Row label | Spec line | Specimen text |
|---|---|---|
| `H1 (headline-xl)` | `24px / 32px • Semibold` | `Central Warehouse Logistics Dashboard` |
| `H2 (headline-lg)` | `20px / 28px • Semibold` | `Purchase Order Line Reconciliation & Receipt` |
| `H3 (headline-md)` | `16px / 24px • Semibold` | `SKU Stock Allocation by Bin Location` |
| `Body (body-sm)` | `13px / 18px • Regular` | `Standard UI instructions, notes, and general ledger metadata descriptions.` |
| `Mono Numeric` | `13px / 18px • Tabular` | `PO-2024-8891 | 14,850 PCS @ $42.50 USD | QTY: 9,200` |
| `Label (label-xs)` | `11px / 14px • +4% Track` | `REORDER POINT THRESHOLD • MANDATORY AUDIT TRAIL` |

**Section 04 button groups:** `Primary Actions` (`Save Order` / `Receive Goods`) · `Secondary / Default`
(`Back` / `Edit Items`) · `Destructive Actions` (`Cancel Order` / `Deactivate`) ·
`States & Micro-Pills` (`Disabled` / `Syncing...` / icon-only `Print Sheet`). Printed density line:
`Default Height: 32px (h-8) • px-3 • 13px label` · `Compact Height: 28px (h-7) • px-2.5 • 12px label` ·
`Focus Ring: 2px solid primary, 0px offset` · `Click buttons to test micro-toast signals`.

**Section 05 input specimens:** `Product SKU *` value `SKU-8921-X`, placeholder `e.g. SKU-1000`,
helper `Permanent master inventory code`; `Unit Cost (USD) *` value `245.00` with `$` prefix, helper
`Active field focus state (2px solid)`; `Safety Reorder Level *` value `-15`, error
`Must be a positive integer`; `System Assigned Ledger ID` disabled value `SYS-LDG-99042-ALPHA`,
helper `Read-only immutable database key`. Checkboxes `Auto-notify warehouse lead` (checked) /
`Allow partial backorders`; radios `Standard Delivery` (checked) / `Cross-Dock Immediate`; footnote
`Form Control Height: 32px Standard Baseline`.

**Section 06 demo table rows:**

| SKU | Product | Category | Depot | On Hand | Reorder Pt | Health |
|---|---|---|---|---|---|---|
| SKU-99201-IND | Hydraulic Valve Assembly 3/4" NPT | Hydraulics | WH-A (Chicago) | 1,420 | 350 | Normal |
| SKU-44018-ELC | Solid State Relay 24VDC DIN Rail | Electronics | WH-B (Dallas) | 48 (`text-[#b45309]`) | 150 | Low Stock |
| SKU-11200-FAS | High Tensile Hex Bolt M12x50 Gr 8.8 | Fasteners | WH-A (Chicago) | 18,500 | 5,000 | Normal |
| SKU-77309-IND | Direct Acting Solenoid Valve 110VAC | Industrial | WH-C (Rotterdam) | 810 | 200 | Normal |
| SKU-23004-ELC | Step-Down Power Converter 48V to 12V | Electronics | WH-B (Dallas) | 12 (`text-[#b45309]`) | 50 | Low Stock |

**Section 08 alert copy:** success `Goods receipt completed` / `All 420 line items for PO-99042 successfully recorded into Bin WH-A-04. Stock ledger updated in real time.`;
warning `Low stock threshold reached in Warehouse B` / `Inventory for Solid State Relays is below safety stock of 150 units. Automated replenishment PO recommended.`;
danger `Validation error: Insufficient stock` / `Requested quantity (500) exceeds current unallocated inventory (340) in selected depot. Adjust line or split order.`;
info `Draft saved automatically` / `Your local draft version was cached at 14:32:01. Session expires after 30 minutes of warehouse inactivity.`

**Section 09 accessibility rules (verbatim):**
`Contrast Ratios: 100% of body copy and interactive UI components maintain a minimum 4.5:1 contrast against surface backgrounds.` ·
`Explicit Labels: Form inputs always utilize explicit persistent labels with visible red asterisks (*) for mandatory fields.` ·
`Dual Coding: Information is never communicated by color alone. Every badge pairs a hex hue with clear text strings and status icons.`

**Token export payload (from `triggerCopyTokens()`):**
```json
{
  "system": "IOMS Enterprise Design Tokens",
  "version": "2.4.0",
  "colors": { "primary": "#00236f", "primaryContainer": "#1e3a8a", "primaryHover": "#2563eb",
              "success": "#059669", "warning": "#d97706", "error": "#dc2626", "info": "#0284c7",
              "background": "#f8f9ff", "surface": "#ffffff", "border": "#cbd5e1" },
  "spacing": { "xs": "4px", "sm": "8px", "md": "12px", "lg": "16px", "xl": "24px", "xxl": "32px" },
  "typography": { "fontFamily": "Inter, JetBrains Mono, sans-serif",
                  "h1": "24px", "h2": "20px", "body": "13px", "caption": "11px" }
}
```
Download toast: `Tokens bundle downloaded: ioms-tokens-v2.4.json`

### 15.3 QA / simulator harness bars (present on every app screen)

Every app-lineage screen ships a developer harness above the page content. These are **part of the
generated design** and are documented so an implementer knows to strip them, not to reproduce them.

| Screen | Harness container | Label |
|---|---|---|
| Products list | `<aside aria-label="Scenario Simulator" class="bg-surface-container-high px-4 py-2 border-b border-outline-variant/50 …">` | `Interactive State Preview:` (`rule_settings`) |
| Categories | `bg-surface-container-high px-4 py-2` | `QA Prototype Controller` / `Switch operational view states:` |
| Warehouses | `<aside aria-label="Interactive QA State Switcher" class="bg-surface-container-highest px-4 py-2.5 …">` | `QA` badge + `Simulator Controls` |
| Suppliers | `bg-surface-container-high` | role buttons + view states |
| Customers | `bg-surface-container-high px-4 py-2 border-b border-outline-variant/30` | `QA Controller` (`tune`) / `Customer Master Data Simulation:` |
| Users | `bg-surface-container-highest px-4 py-2 border-b border-outline-variant/50 z-30` | `QA Scenario Simulator:` (`developer_board`) |
| User create/edit | `bg-surface-container-low px-6 py-2.5` | `QA` badge + `Scenario Simulator:` |
| Admin dashboard | `bg-surface-container-high px-6 py-2` | state switcher `#stateSwitcher` (`developer_mode`) |
| Sales dashboard | `<section aria-label="Testing and Simulator Controls" class="bg-surface-container-high px-4 py-2 …">` | 5 state buttons (`tune`) |
| Warehouse dashboard | `bg-surface-container-lowest border-b border-outline-variant/40 px-6 py-2` | `QA Scenario Bar` (`bug_report`) |
| Product detail | sticky `top-14` | 8 state buttons + `Live Sync: SKU-IV-501` (`hidden xl:flex`) |
| Product create/edit | `#qa-toolbar` | 6 numbered scenario buttons |
| Warehouse stock detail | `#qa-switcher` | `Simulate Facility State` (7 buttons) |
| PO list | — | role buttons + 5 state buttons + toast (`notifications_active`) |
| PO detail | sticky `top-14 z-30 bg-surface-container-lowest px-4 py-2.5 shadow-sm` | 3 selects + `Reset` |
| PO create/edit | `bg-slate-900 text-slate-200 px-4 py-2 border-b border-slate-700` | 8 scenario buttons (the only dark harness) |
| Goods Receipt | `<aside aria-label="Simulation Bar" class="… sticky top-14 z-30">` | `QA Scenario Simulator` + `11 Operational Cases` |
| SO list | — | 3 persona buttons + 6 data-state buttons (`play_arrow`) |
| SO detail | — | `Simulator Control` (2 selects) |
| SO create/edit | sticky `top-14 z-30` | 10 numbered mode pills |
| Goods Issue | — | `QA State Simulator (Interactive Test Rig)` (`bug_report`), 11 buttons |
| Stock Ledger | `bg-slate-900 text-slate-200` | `QA Sandbox Simulator` — 3 role buttons + 10 state buttons |
| Reports | `<aside aria-label="Simulator Test Rig" class="bg-surface-container-high px-4 py-3 …">` | `Report State Simulator (QA Rig)` (`science`) + `Active: …` readout |
| Reports-viz | `<aside aria-label="Simulation Bar" class="bg-surface-container-highest px-4 py-2 …">` | `QA Harness: State Simulation` (`biotech`) / `| Select lifecycle view:` |
| My Profile | `bg-surface-container-low px-4 py-2.5` | `Interactive QA State Simulator` + `/profile` tag |
| System states | `#state-selector-tabs` | 16 numbered state pills |
| Shell blueprint | `bg-surface-container-low p-4` | `Shell Mode:` / `Active Role:` / `Header:` |
| Login | top `<header>` | `Preview State:` (5 buttons) |

Active-pill styling is consistent across harnesses: `bg-primary-container text-on-primary font-semibold`
(or `bg-primary text-on-primary` on a few), inactive `bg-surface-container-lowest text-on-surface hover:bg-surface-container`.

---

## 16. Asset usage

### 16.1 Image assets in the export

| Asset | Where | Usage |
|---|---|---|
| `ioms_enterprise_logo/code.html` | — | Inline SVG logo source, 312 bytes (see below) |
| `ioms_enterprise_logo/screen.png` | 1024×1024 | Rendered logo raster |
| `professional_corporate_headshot_photo_of_an_operations_manager_business_casual/screen.png` | 1024×1024 | Avatar source (no `code.html`) |
| `*/screen.png` (29 files) | see §0 | Stitch render of each screen |

### 16.2 The logo (complete source, `ioms_enterprise_logo/code.html`)

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none">
  <rect width="32" height="32" rx="6" fill="#1e3a8a"/>
  <path d="M8 12L16 7L24 12L16 17L8 12Z" fill="#93c5fd"/>
  <path d="M8 14.5L16 19.5V25L8 20V14.5Z" fill="#60a5fa"/>
  <path d="M16 19.5L24 14.5V20L16 25V19.5Z" fill="#3b82f6"/>
</svg>
```

A 32×32 rounded square (`rx="6"`) on `#1e3a8a`, with three isometric box faces in
`#93c5fd` (top), `#60a5fa` (left) and `#3b82f6` (right) — a stylised open carton.
**This SVG is never referenced by any screen.** Screens use a hosted raster instead (next section).

### 16.3 Remote image references (exact usage)

All screens load images from `lh3.googleusercontent.com`; five distinct assets exist.

| Asset id prefix | Path | Used by | Markup |
|---|---|---|---|
| `AEtjO1WoIpPeHD0_AH4Sgml…` | `/aida/` | **All 27 code screens** (26 app + login) | Sidebar/login logo — `<img alt="IOMS Enterprise Logo" class="h-8 w-auto object-contain">`; login uses `class="w-8 h-8 object-contain"` inside a 48px `bg-[#1e3a8a]` tile |
| `AB6AXuAC2XvkS-QJM4p0eKCU7…` | `/aida-public/` | All 26 app screens | Header avatar — `<img alt="Profile" class="w-8 h-8 rounded-full object-cover border border-outline-variant/60">` |
| `AB6AXuAeXlEWxekGhhM4sonyK…` | `/aida-public/` | Shell blueprint only | Simulated top-bar avatar — `<img class="w-8 h-8 rounded-full object-cover shadow-sm" data-alt="Professional corporate headshot portrait of operations director Alex Morgan in a crisp navy blue shirt, clean white background, enterprise lighting, authoritative expression">` |
| `AB6AXuAD347x6uHt2vIJe0sqT…` | `/aida-public/` | Product detail only | Product photo — `<img class="w-full h-full object-contain mix-blend-multiply transition-transform duration-300 group-hover:scale-105" data-alt="Industrial gate valve heavy-duty 50mm manufactured in cast steel WCB, ANSI class 150 flanged connection, technical studio lighting on neutral light grey backdrop, precision engineering component, crisp focus on metal threads and blue painted handwheel.">` |
| `AB6AXuAP-jD9J2gV3RWnZstEU…` | `/aida-public/` | Product create/edit only | Product photo — `<img class="w-full h-full object-cover" data-alt="An industrial heavy-duty cast iron gate valve with vibrant blue anti-corrosive coating, polished brass stem, and circular control handwheel centered cleanly on an engineering workshop surface under diffuse clinical overhead lighting.">` |

One additional external host appears: the **Sales dashboard** JS swaps the header avatar `src` to
`https://images.unsplash.com/photo-1534528741775-…` with `alt="Budi Santoso Avatar"`.

### 16.4 `alt` / `data-alt` conventions

- Chrome images carry real `alt` text: `IOMS Enterprise Logo`, `Profile`.
- Content images carry **`data-alt`, not `alt`** — Stitch's generation-prompt attribute. Three exist,
  quoted in full in §16.3. An implementation must convert these to real `alt` text.
- No `<picture>`, `srcset`, `loading="lazy"`, `width`/`height` attributes, or image placeholders
  appear anywhere in the export.

### 16.5 Avatar strategies (three coexist)

1. **Hosted raster** — `<img class="w-8 h-8 rounded-full object-cover border border-outline-variant/60">` (shell header on all app screens).
2. **Initials tile** — a `<div>` with 1–2 letters: `SJ`, `BS`, `RP`, `MI`, `HS`, `AF`, `AS`, `IO`.
   Sizes/shapes: `w-8 h-8 rounded-full` (Users table, Warehouse dashboard), `w-14 h-14 rounded`
   (My Profile hero), `w-7 h-7 rounded` (brand tile), plus per-row background colors listed in §9.15.
3. **Unsplash raster** (Sales dashboard, JS-injected).

### 16.6 Icon assets

Material Symbols Outlined is the only icon system in the app lineage. Complete set observed across
the export (alphabetical):

`account_balance`, `account_balance_wallet`, `account_tree`, `add`, `add_box`, `add_circle`,
`add_task`, `admin_panel_settings`, `analytics`, `archive`, `architecture`, `arrow_back`,
`arrow_drop_down`, `arrow_forward`, `assignment`, `assignment_ind`, `assignment_turned_in`,
`autorenew`, `badge`, `balance`, `battery_full`, `biotech`, `block`, `bolt`, `bug_report`,
`build`, `build_circle`, `calendar_month`, `calendar_today`, `call`, `cancel`, `category`,
`change_circle`, `check`, `check_box`, `check_circle`, `chevron_left`, `chevron_right`,
`clear`, `clear_all`, `close`, `cloud_off`, `cloud_upload`, `content_copy`, `corporate_fare`,
`dashboard`, `delete`, `delete_forever`, `design_services`, `developer_board`, `developer_mode`,
`devices`, `domain`, `domain_add`, `domain_disabled`, `done`, `done_all`, `download`,
`edit`, `edit_note`, `edit_square`, `error`, `error_outline`, `expand_more`, `fact_check`,
`file_download`, `filter_alt_off`, `filter_list`, `filter_list_off`, `find_in_page`, `fmd_good`,
`folder`, `folder_off`, `folder_open`, `forklift`, `format_list_bulleted`, `format_list_numbered`,
`gavel`, `gpp_bad`, `group`, `group_add`, `hardware`, `help_outline`, `history`, `history_edu`,
`home_work`, `hourglass_bottom`, `hourglass_empty`, `hourglass_top`, `how_to_reg`, `info`,
`insert_chart`, `insights`, `inventory`, `inventory_2`, `key`, `left_panel_close`,
`left_panel_open`, `local_shipping`, `location_on`, `lock`, `lock_clock`, `login`, `logout`,
`mail`, `manage_accounts`, `manage_search`, `map`, `menu`, `move_to_inbox`, `north_east`,
`notification_important`, `notifications_active`, `notifications_off`, `open_in_new`, `outbox`,
`output`, `package_2`, `palette`, `pause_circle`, `payments`, `pending`, `pending_actions`,
`person`, `person_add`, `person_off`, `person_search`, `phone_android`, `photo_camera`,
`play_arrow`, `play_circle`, `point_of_sale`, `policy`, `precision_manufacturing`, `print`,
`progress_activity`, `qr_code_scanner`, `query_stats`, `radio_button_unchecked`, `receipt`,
`receipt_long`, `refresh`, `remove`, `remove_done`, `replay`, `restart_alt`, `rule_settings`,
`save`, `save_as`, `schedule`, `science`, `search`, `search_off`, `security`, `sell`,
`shield`, `shield_lock`, `shield_person`, `shopping_bag`, `south_west`, `speed`,
`stay_current_portrait`, `swap_horiz`, `swap_vert`, `sync`, `sync_alt`, `sync_problem`,
`table_chart`, `task_alt`, `timer_off`, `touch_app`, `travel_explore`, `tune`, `unfold_more`,
`update`, `valve`, `verified`, `verified_user`, `view_column`, `view_quilt`, `view_sidebar`,
`visibility`, `visibility_off`, `warehouse`, `warning`, `wifi`, `wifi_off`, `zoom_in`

The **login screen uses zero icon-font glyphs** — 8 hand-written inline SVGs instead (error circle-X,
warning triangle, info circle ×2, eye, eye-slash, shield-check, spinner).

### 16.7 Third-party runtime dependencies

| Resource | Loaded by |
|---|---|
| `https://cdn.tailwindcss.com` | 29 screens |
| `https://cdn.tailwindcss.com?plugins=forms,container-queries` | Goods Issue only (the plugin utilities are never used) |
| `https://fonts.googleapis.com` / `https://fonts.gstatic.com` | preconnect on all screens |
| Inter + JetBrains Mono | all screens (weights differ by lineage, §4.1) |
| Material Symbols Outlined (two stylesheet requests) | app lineage only |

No bundler, no build step, no local CSS/JS files, no favicon, no manifest.

---

## 17. Component relationships

### 17.1 Composition hierarchy

```
AuthenticatedShell
├─ Sidebar (w-64, fixed, z-50)
│  ├─ BrandBlock (h-14)
│  ├─ NavGroup × 7  ──▶ NavItem (icon 18px + label, active = primary-container)
│  └─ AccountBlock (border-t) ──▶ NavItem (My Profile) + NavItem--error (Logout)
├─ TopHeader (h-14, fixed, z-40)
│  ├─ Breadcrumb
│  └─ UserBlock ──▶ Name + RolePill + Avatar  [blueprint adds UserDropdown]
└─ Main (pt-14)
   ├─ QAHarnessBar                       (all app screens; strip in production)
   ├─ GlobalBanner*                      (0..n: read-only / locked / stale / error / success)
   ├─ PageHeader
   │  ├─ Breadcrumb
   │  ├─ Title + StatusBadge? + RolePill?
   │  ├─ Subtitle
   │  └─ ActionGroup ──▶ Button (secondary…primary, right-most = primary)
   ├─ KpiStrip?      ──▶ KpiCard × 3|4|5  (± ProgressBar)
   ├─ FilterToolbar? ──▶ SearchInput + Select* + ClearButton + ResultCounter
   ├─ ContentSurface (exactly one of)
   │  ├─ DataTable ──▶ TableHeaderRow → TableRow → StatusBadge + RowActionGroup
   │  │              └─ MobileCardList (5 screens only)
   │  ├─ DetailPanelGrid ──▶ DetailCard × 3 (+ LineItemTable + TotalsPanel)
   │  ├─ FormSections ──▶ FormSection → FormField (Label + Control + Helper + Error)
   │  ├─ VisualAnalytics ──▶ VizTabs → TrendSVG | CategoryStackedBar | WarehouseProgressCards
   │  ├─ EmptyState | NoResultsState | ErrorState | ForbiddenState | NotFoundState
   │  └─ SkeletonLoader
   ├─ PaginationFooter?   (paired with DataTable)
   ├─ GovernanceNotice?   (terminal block on data-mutating screens)
   └─ StickyActionFooter? (form screens only)

Overlays (z-50, outside Main flow)
├─ Modal ──▶ ModalHeader + ModalBody + ModalFooter(Cancel, Confirm)
├─ Toast
└─ MobileDrawer (blueprint only)
```

### 17.2 Pairing rules observed in the export

- **DataTable always ships with a PaginationFooter**, except Categories and Warehouses, which
  substitute a GovernanceNotice footer instead.
- **FilterToolbar always ships with a ResultCounter**, and its zero-result path always swaps the
  ContentSurface for a NoResultsState — never an empty table body.
- **EMPTY vs NO_RESULTS are always two distinct components** with different icons
  (`inventory_2` / `domain_add` / `folder_off` / `warehouse` / `group_add` / `person_search` for EMPTY;
  `search_off` / `manage_search` / `filter_alt_off` / `filter_list_off` for NO_RESULTS) and different
  primary actions (create vs clear-filters). This mirrors the screen-map guardrail in §14.4.
- **Every destructive row action opens a CONFIRMATION modal.** No destructive action is immediate.
- **Every mutation resolves to a Toast** (MUTATION_SUCCESS), never a full-page success screen —
  except Product create/edit and Goods Receipt, which use an inline success **banner**.
- **StickyActionFooter appears only on create/edit forms** (Product, PO, SO) and always carries an
  audit/meta line on the left and the action pair on the right.
- **GovernanceNotice appears only on screens that touch stock or master data**, always as the last
  block before the page ends, always icon + bold heading + one paragraph.
- **LineItemTable is always followed by a TotalsPanel or `tfoot` totals row** (PO detail, PO
  create/edit, SO detail, SO create/edit, Goods Receipt, Goods Issue, Product detail).
- **StatusBadge is always dual-coded** — colour plus a text label, plus a pip or icon. No badge in
  the export relies on colour alone.
- **RolePill in the header and the sidebar's visible group set are always mutated together** — role
  changes drive both the pill and nav visibility from the same handler.

### 17.3 Cross-screen navigation relationships referenced in markup

```
Login ──▶ Dashboard (role-dependent: Admin | Sales | Warehouse)

Products ──▶ Product Detail ──▶ Product Create/Edit
Products ──▶ Product Create/Edit
Product Detail ──▶ Stock Ledger  ("View Audit Log")
Warehouses ──▶ Warehouse Stock Detail ──▶ Stock Ledger ("Stock Ledger Logs")
Warehouse Stock Detail ──▶ Purchase Order create ("Create PO →", "Receive PO to This Facility")

Purchase Orders ──▶ PO Detail ──▶ Goods Receipt ──▶ PO Detail | Stock Ledger
Purchase Orders ──▶ PO Create/Edit ──▶ PO Detail
PO Create/Edit (locked) ──▶ Goods Receipt ("Go to Goods Receipt")

Sales Orders ──▶ SO Detail ──▶ Goods Issue ──▶ SO Detail | Sales Orders
Sales Orders ──▶ SO Create/Edit ──▶ SO Detail
SO Detail ("Launch Goods Issue Flow") ──▶ Goods Issue

Users ──▶ User Create/Edit
Any screen ──▶ My Profile | Login (logout)
Admin dashboard ──▶ Purchase Orders | Sales Orders | Stock Ledger (footer links)
Warehouse dashboard ──▶ Sales Orders | Purchase Orders (queue links)
Reports / Reports-viz: terminal (export only)
```

### 17.4 Domain relationships asserted by the copy

These are stated in the generated governance notices and callouts, and they are consistent across
screens:

- `ProductStock` is mutated **only** by validated Goods Receipt (PO) and Goods Issue (SO); no screen
  offers manual stock editing, and four screens say so explicitly.
- `StockLedger` is **append-only / immutable** ("WORM Compliance", `VERIFIED_COMMITTED`,
  `SHA256` checksum, "no edits, rollbacks, or deletion actions are permitted"); corrections are made
  by "a new compensating Goods Receipt/Issue document per SOP-WH-88".
- **Approved Sales Orders place a hard stock reservation**; deduction happens at Goods Issue.
- **Goods Issue is all-or-nothing per order** — full-fulfilment only, enforced in the UI by requiring
  issue qty to equal ordered qty on every line.
- **Goods Receipt is incremental** — partial receipts are first-class (`PartiallyReceived`), with a
  per-line `Prev. Received` / `Remaining` model.
- **Goods Issue commits atomically**: decrement `ProductStock` → insert `ISSUE` in `StockLedger` →
  set the SO to `Fulfilled`, with full rollback on any constraint failure.
- **Segregation of duties:** a Sales rep cannot approve their own order (`Self-Approval Restricted`),
  and an Admin cannot deactivate their own account (`Self-deactivation protected by system security protocol.`),
  nor demote their own role (self-lockout guard).
- **Master data is deactivated, never deleted** — every deactivate dialog states that historical
  records are preserved; Categories goes further and disables deletion outright.
- **Cancel is state-gated on POs**: hidden once a PO reaches `PartiallyReceived`, `Received` or `Cancelled`.

---

## 18. Implementation-relevant details

### 18.1 Element ids and hooks that carry meaning

The export's JS uses stable ids. These are worth preserving as component/DOM contracts because the
screen copy and state machines are keyed to them.

| Concern | Representative ids |
|---|---|
| State containers | `state-normal-content`, `state-skeleton-view`, `state-empty-all-view`, `state-view-empty`, `state-view-no-results`, `state-view-error`, `state-view-skeleton`, `view-table`, `view-empty-search`, `view-empty-catalog`, `view-loading-skeleton`, `view-error`, `view-404`, `view-403`, `view-main`, `view-skeleton`, `view-not-found`, `state-data`, `state-blank`, `state-loading`, `state-empty`, `qa-view-normal`, `qa-view-skeleton`, `qa-view-connection-error`, `ledger-empty`, `ledger-no-match`, `ledger-skeleton`, `ledger-safe-error`, `ledger-403`, `skeletonState`, `emptySearchState`, `emptyCatalogState`, `forbiddenView`, `emptyView` |
| Banners | `banner-stale-data`, `banner-insufficient`, `banner-stale`, `banner-status-changed`, `banner-ineligible`, `banner-error`, `banner-success`, `banner-inactive-facility`, `errorStateBanner`, `system-error-banner`, `duplicate-error-banner`, `duplicate-email-banner`, `self-lockout-banner`, `readOnlyBanner`, `validation-banner`, `success-banner`, `form-error-banner`, `catalog-empty-banner`, `locked-banner`, `filter-changed-alert`, `date-range-alert`, `inline-validation-alert`, `all-zero-error-alert`, `conflict-banner`, `operation-error-alert`, `notice-pending`, `notice-approved` |
| Toasts | `toastNotification`, `toastMessage`, `toastIcon`, `toast-banner`, `toast-container`, `app-toast`, `toast-text`, `system-toast`, `systemToast`, `success-toast`, `live-toast`, `toast-success` |
| Tables / bodies | `specLiveTable`, `tableBodyRows`, `table-body`, `categoryTableBody`, `table-rows-container`, `supplier-table-body`, `customerTableBody`, `userTableBody`, `userMobileList`, `poTableBody`, `orders-tbody`, `ledger-table-body`, `ledger-mobile-cards`, `stock-data-table`, `stock-tbody`, `line-items-body`, `items-table-body`, `items-mobile-container`, `mobile-line-items-container`, `receipt-table-body`, `receipt-mobile-cards`, `report-ledger-table`, `lowStockTable`, `pendingOrdersTable`, `sales-orders-table` |
| Filters | `search-input`, `searchInput`, `supplier-search-input`, `customerSearchInput`, `warehouse-search`, `filter-search-input`, `order-search-input`, `demoSearchInput`, `table-search-input`, `filter-category`, `filter-stock`, `filter-status`, `filter-warehouse`, `filter-type`, `filter-performer`, `filter-report-type`, `filter-date-range`, `roleFilter`, `statusFilter`, `status-filter`, `status-filter-select`, `sortFilter`, `date-sort`, `date-from`, `date-to`, `stock-from-date`, `stock-to-date`, `order-from-date`, `order-to-date`, `order-type-select`, `order-status-select` |
| Counters | `recordCount`, `results-count`, `results-count-label`, `results-count-text`, `resultsCount`, `resultCountLabel`, `result-counter`, `meta-count`, `liveCustomerCount`, `active-counter-label`, `table-record-count`, `pagination-summary`, `paginationSummary`, `visible-orders-count`, `line-count-badge`, `line-counter-chip` |
| Quantity / totals | `qty-input-1`, `qty-input-2`, `mob-qty-1`, `mob-qty-2`, `input-qty-1..3`, `mob-qty-1..3`, `summary-total-ordered`, `summary-wh-stock`, `summary-shortage`, `summary-session-units`, `summary-post-status-pill`, `preview-total-qty`, `preview-impact`, `val-item-count`, `val-unit-count`, `val-grand-total`, `summary-line-count`, `summary-total-qty`, `summary-grand-total`, `kpi-total-stock`, `kpi-progress-bar`, `table-total-sum`, `progress-bar-fill`, `progress-bar-previously`, `progress-bar-current`, `progress-percentage-label`, `current-batch-units-counter` |
| Badges / status | `status-badge`, `header-status-badge`, `so-status-badge`, `badge-product-status`, `pos-status-badge`, `facility-status-pill`, `status-chip`, `role-badge`, `role-text`, `readonly-role-pill`, `role-badge-indicator`, `user-role-badge`, `header-po-status-badge`, `card-so-status`, `card-stock-readiness`, `active-category-badge`, `spec-state-id`, `spec-anatomy-badge`, `spec-trigger-desc`, `spec-transitions` |
| Modals | listed in §9.9 |

### 18.2 Data seeds an implementation will encounter

- **SKUs (product master):** SKU-IV-501, SKU-HC-402, SKU-RG-109, SKU-HB-112, SKU-SF-202, SKU-BB-625,
  SKU-PG-301, SKU-HN-115, SKU-OR-210, SKU-AH-510 (the canonical 10) plus SKU-PP-882, SKU-FL-304,
  SKU-FL-220, SKU-SS-904 (PO/SO pickers), and a disjoint Reports/foundation set
  (SKU-STL-0089, SKU-VLV-1002, SKU-LUB-9941, SKU-ELC-5420, SKU-ACT-3029, SKU-BRG-4412, SKU-FST-8821,
  SKU-FLT-0911, SKU-VLV-3004, SKU-MTR-7718, SKU-8841, SKU-7729, SKU-3301, SKU-1142, SKU-9012,
  SKU-99201-IND, SKU-44018-ELC, SKU-11200-FAS, SKU-77309-IND, SKU-23004-ELC, SKU-99201, SKU-88412,
  SKU-33019, SKU-8921-X, SKU-4401, SKU-9902).
- **Warehouses:** `WH-JKT-01` Warehouse A (West Wing Hub), Jakarta Barat · `WH-SBY-02` Warehouse B
  (East Logistics Hub), Surabaya · `WH-BDG-03` Warehouse C (Central Transit Depot), Bandung ·
  `WH-TJP-04` / `WH-JKT-04` Warehouse D (North Harbor Storage, decommissioned) ·
  plus `WH-TRN-03` in the SO create/edit select, and the Reports-viz set
  (Jakarta Central Fulfillment Hub, Surabaya West Depot, Medan North Regional), and the
  foundation/shell sets (WH-A Chicago / WH-B Dallas / WH-C Rotterdam; Main DC Seattle / Secondary Reno Hub).
- **Suppliers:** SUP-00101…SUP-00105 active, SUP-00089 archived.
- **Customers:** CUST-00201…CUST-00206 active, CUST-00188 archived; SO screens use `CUST-8842-ID`
  and the create/edit select uses CUST-001…CUST-004.
- **Documents:** PO-2024-0009…PO-2024-0019 (list/detail), PO-2024-0884/0888/0890/0891 (warehouse
  dashboard), SO-2024-0016…SO-2024-0025 (list/detail), SO-2024-1120…SO-2024-1148 (dashboards),
  LED-2024-00932…LED-2024-00941, ADJ-2024-0002/0003, SL-88421.
- **Users:** Sarah Jenkins (Admin, `sarah.jenkins@ioms-enterprise.com`, session `SJ-9042` / `SES-89240`),
  Bambang Sugianto (Warehouse), Riko Pratama (Sales, `USR-SLS-04`), Maya Indrawati (Warehouse),
  Hendrik Setiawan (Sales, inactive), Ahmad Fauzi (Admin), plus per-screen personas
  Budi Santoso (Sales dashboard), Aris Setiawan (Warehouse dashboard),
  Alex Morgan / Elena Rostova / Marcus Cole (shell blueprint), Maya Sandria / Bambang Kusumo (SO list),
  Dewi Lestari (Reports).
- **Currency:** the app screens use **IDR** formatted `Rp 4.820.500.000` (Indonesian dot grouping,
  produced by `toLocaleString('id-ID')`); the Reports-viz screen and the foundation screen use
  **USD** formatted `$1,482,950.00`. Both exist in the export.
- **Dates:** display format `24 Oct 2024`, with times as `14:15` or `Today, 14:00 WIB`; `<input type="date">` defaults are ISO (`2024-10-01`, `2024-10-24`, `2024-10-25`).

### 18.3 Things the export declares but does not implement

Recorded so an implementer does not mistake absence for oversight, and does not treat these as
requirements to invent:

| Declared in | Not present in generated markup |
|---|---|
| `DESIGN.md` + foundation §06 | Frozen left / sticky right table columns |
| `DESIGN.md` table spec | Selected-row state (`#eff6ff` + 2px left accent) |
| `DESIGN.md` checkbox spec | Indeterminate checkbox state |
| `DESIGN.md` layout model | 40/60 master-detail split; 240px expanded sidebar |
| `DESIGN.md` elevation L1 | Zero-shadow data cards (generated cards use `shadow-sm`) |
| `DESIGN.md` shapes | Pill-shape prohibition (badges use `rounded-full`, which the app config redefines to 12px) |
| Foundation §09 / shell blueprint | Sidebar auto-collapse below `lg`, tablet expandable rows, mobile hamburger on product screens |
| System-states registry | `role`/`aria-live`/`aria-busy`/`aria-invalid` attributes on the real screens |
| Login `<style>` | `.focus-ring` class (declared, never applied) |
| Goods Issue CDN query | Tailwind `forms` and `container-queries` plugin utilities |
| App `tailwind.config` | `darkMode: "class"` — no `dark:` variant anywhere |
| App `tailwind.config` spacing | All `space-*` and `table-row-h-*` tokens except `input-h-regular` |
| Logo folder | The inline SVG logo (screens use a hosted raster instead) |

### 18.4 Known internal inconsistencies (do not silently "fix" — decide explicitly)

1. **Three colour vocabularies** coexist (§3.1 / §3.2 / §3.3) plus raw Tailwind palette classes (§3.5).
2. **Radii conflict**: app config `rounded-full: 0.75rem` vs `DESIGN.md` `full: 9999px`; app
   `rounded: 0.125rem` vs `DESIGN.md` `DEFAULT: 0.25rem`.
3. **Border strategy** differs per screen: token borders / shadow-only / literal hex (§6.1).
4. **Currency** is IDR on 24 screens and USD on 2.
5. **Two Reports screens** with different report-type vocabularies and different data.
6. **Status label casing** varies: `PartiallyReceived` vs `Partially Received`; `PendingApproval`
   vs `Pending Approval`. Both spellings ship, sometimes on the same concept in different screens.
7. **Sidebar active item** is hard-coded to Dashboard in static HTML and corrected by JS on only
   some screens.
8. **H1 weight** is `font-bold` on most screens and `font-semibold` on Products list, Sales
   dashboard and Categories.
9. **Table row heights** range `h-9`/`h-10`/`h-11`/`h-12` across screens for the same table role.
10. **`inventory_order_management_system_flow` is a byte-identical duplicate** of the login screen —
    it is not a separate flow diagram despite the folder name.
11. **Status-badge palettes differ per screen** for the same status (see the 60-row table in §9.6):
    e.g. `Approved` is emerald-100 on the admin dashboard, `surface-container-high/text-primary`
    on the SO list, and `surface-container-highest/text-primary` on the Sales dashboard.

### 18.5 Fidelity checklist for implementation

- Reproduce the shell at exactly 256px / 56px / 56px offsets with the z-index ladder 50 / 40 / 30 / 50.
- Keep the 12 typography tokens as a paired `font-*` + `text-*` system; monospace is mandatory for
  the field list in §4.3.
- Keep control heights at 32px default / 28px compact / 36px comfortable / 40px height-locked
  loading / 44px mobile.
- Keep table headers at 32px with `label-xs` uppercase +0.04em, and body rows at 36px on data tables.
- Keep every badge dual-coded (colour + text + pip/icon).
- Keep EMPTY and NO_RESULTS as separate components with different icons and different primary actions.
- Keep every destructive action behind a CONFIRMATION modal, and every mutation resolving to a toast
  or inline success banner.
- Keep the governance notices — they encode the ProductStock / StockLedger invariants the UI is built around.
- Keep the segregation-of-duties gates: self-approval blocked, self-deactivation blocked,
  self-demotion blocked, PO cancel state-gated.
- Strip every QA harness bar (§15.3) and every `setScenario` / `setAppState` / `setViewState`
  simulator function; they are specimen scaffolding, not product behavior.
- Convert `data-alt` to real `alt`, and replace the hosted `lh3.googleusercontent.com` rasters with
  local assets (the logo SVG in §16.2 is available).
- Decide, once and explicitly, on each of the eleven inconsistencies in §18.4 before building.

---

## 19. Provenance

| Item | Value |
|---|---|
| Source archive | `stitch_remix_of_remix_of_enterprise_ioms_design_system_fix.zip` |
| Folders | 31 (29 with `screen.png`, 28 with `code.html`, 1 `DESIGN.md`) |
| Total generated HTML | ~1.83 MB across 28 files |
| Design manifest | `operational_enterprise_ioms/DESIGN.md` (12,225 bytes, 251 lines) |
| Documented on | 2026-09-07 |
| Method | Every `code.html`, `DESIGN.md`, and PNG dimension in the archive was read and cross-referenced; all values, class strings and copy above are quoted from those files. |

**Scope statement.** This document is a description of the existing Stitch output only. It proposes
no redesign, adds no screens, routes, modes, states, or lifecycle steps, and invents no
requirements. Where the export is silent, ambiguous, or self-contradictory, that fact is recorded
in §18.3 and §18.4 rather than resolved.

---

## 20. Appendix — 51→16 State Mapping (B-07)

*Added 2026-09-08 per `docs/design/DESIGN_DECISION_RECORD_B01_B09.md` §B-07. This is a historical
traceability mapping from the 51 ad-hoc local state keys `docs/design/design-audit.md` finding S-01
found across the 30 product screens, onto the 16 canonical states defined in §14.1/§14.2 above. It
introduces no new state and authorizes none of these 51 keys to remain as current canonical
identifiers — future implementation and future Stitch regeneration must use the 16 canonical ids
only (§14.1).*

| Original local key | Canonical state | Note |
|---|---|---|
| `404` | `07_NOT_FOUND` | |
| `BLANK` | `02_DATA` | Reports-viz pre-generation key; anatomy visually resembles EMPTY (icon+action) but §14.4 classifies pre-generation as DATA. This is the key S-01 flagged as needing an explicit decision — resolved here as DATA, per the registry's own classification. |
| `DATA` | `02_DATA` | Already canonical. |
| `EMPTY` | `04_NO_RESULTS` | Reports-viz's filter-zero key. Reclassified from EMPTY to NO_RESULTS per B-07/S-02 — the copy is filter-scoped, matching the NO_RESULTS trigger, not the EMPTY trigger. |
| `LOADING` | `01_LOADING` | Already canonical. |
| `STALE` | `14_STALE_DATA` | |
| `active` | `02_DATA` | Role/tab-scoped content variant. |
| `admin` | `02_DATA` | Role-scoped dashboard content variant. |
| `admin-default` | `02_DATA` | Role-scoped dashboard content variant. |
| `auth-error` | `12_MUTATION_ERROR` | Login rejection, per design-audit.md's own MUTATION_ERROR grouping. |
| `clean` | `02_DATA` | Form pristine-state content variant. |
| `conflict` | `12_MUTATION_ERROR` | |
| `create` | `02_DATA` | Create-mode form content variant. |
| `default` | `02_DATA` | |
| `dirty` | `02_DATA` | Form edited-state content variant (unsaved changes), not an error state. |
| `edit` | `02_DATA` | Edit-mode form content variant. |
| `empty` | `03_EMPTY` (master-data/list screens) / `04_NO_RESULTS` (tabular Reports screen) | Same literal key used with two different triggers on different screens. On Products/Warehouses/Suppliers/Customers/Users lists it correctly means a zero-total dataset (EMPTY). On the tabular Reports screen it is used for filter-zero and is reclassified to NO_RESULTS per B-07/S-02 — no third state introduced. |
| `empty-catalog` | `03_EMPTY` | Products list, zero total records. |
| `empty-search` | `04_NO_RESULTS` | |
| `empty-stock` | `03_EMPTY` | Warehouse Stock Detail, zero stock records for the warehouse — dataset-level, not filter-driven. |
| `error` | `09_SERVER_ERROR` (most screens) / `10_NETWORK_ERROR` (2 dashboard screens) | Same literal key used for two different triggers, per design-audit.md's own dual listing under both the SERVER_ERROR and NETWORK_ERROR rows. |
| `filter-changed` | `14_STALE_DATA` | |
| `forbidden` | `06_FORBIDDEN` | |
| `freshIntake` | `02_DATA` | Goods Receipt line-item content variant. |
| `fullReceipt` | `02_DATA` | Goods Receipt line-item content variant. |
| `inactive` | `13_BUSINESS_ERROR` | Login inactive-account rejection, per design-audit.md's own BUSINESS_ERROR grouping. |
| `ineligible` | `13_BUSINESS_ERROR` | The second key S-01 flagged as needing an explicit decision (as generated, neither a clean FORBIDDEN nor BUSINESS_ERROR fit). Resolved here as BUSINESS_ERROR, consistent with that state's "workflow locking" trigger clause (§14.1 row 13). |
| `initial` | `02_DATA` | Tabular Reports pre-generation state, paired with `BLANK`; §14.4 classifies pre-generation as DATA. |
| `loading` | `01_LOADING` | |
| `low-stock` | `02_DATA` | Dashboard widget content variant (a data slice, not an error/empty state). |
| `modal` | `15_CONFIRMATION` | |
| `no-results` | `04_NO_RESULTS` | |
| `nomatch` | `04_NO_RESULTS` | |
| `normal` | `02_DATA` | |
| `nostock` | `03_EMPTY` | Zero-stock dataset variant, distinct screen usage from `empty-stock`. |
| `not-found` | `07_NOT_FOUND` | |
| `operationError` | `12_MUTATION_ERROR` | |
| `order-generated` | `02_DATA` | Tabular Reports generated-result content variant. |
| `partiallyReceived` | `02_DATA` | Goods Receipt line-item content variant. |
| `processing` | `01_LOADING` (6-screen majority usage) / `16_ACTION_LOADING` (in-flight mutation button state) | Same literal key used for two different triggers, per design-audit.md's own dual listing under both the LOADING and ACTION_LOADING rows. |
| `readonly` | `02_DATA` | View-only permission variant of normal content, not an access-denial state. |
| `sales` | `02_DATA` | Role-scoped dashboard content variant. |
| `saving` | `16_ACTION_LOADING` | |
| `skeleton` | `01_LOADING` | |
| `staff` | `02_DATA` | Role-scoped dashboard content variant. |
| `staleData` | `14_STALE_DATA` | |
| `stock-generated` | `02_DATA` | Tabular Reports generated-result content variant. |
| `success` | `11_MUTATION_SUCCESS` | |
| `toast` | `11_MUTATION_SUCCESS` | |
| `validation` | `05_VALIDATION_ERROR` | |
| `validationErrors` | `05_VALIDATION_ERROR` | |

All 51 keys from `docs/design/design-audit.md`'s S-01 list are accounted for above, each mapped to
exactly one primary canonical state (four keys — `empty`, `error`, `processing`, and the pair
`BLANK`/`ineligible` individually — carry an explanatory note documenting a context-dependent or
previously-undecided nuance already present in the audit evidence; none of these notes introduces a
17th state, an alias, or new behavior). This appendix is a historical traceability artifact for the
original Stitch export; it does not authorize any of these 51 keys to persist as state identifiers in
a regenerated Stitch export or in implementation — both must use the 16 canonical ids exclusively.


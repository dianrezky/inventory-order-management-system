# Screenshot Evidence Index

Generated: 2026-10-04T14:39:49.933Z
App: http://localhost:8090

| # | Folder | Sub-folders | Coverage |
|---|---|---|---|
| 01 | `01-auth/` | `login/ error/ success/` | Login page, wrong-password error, successful login → dashboard |
| 02 | `02-dashboard/` | `admin/ sales/ warehouse/` | Per-role dashboards (Admin, Sales, Warehouse, Sales-special) |
| 03 | `03-products/` | `list/ create/ view/ edit/ deactivate/` | Full CRUD + search + warehouse readonly view |
| 04 | `04-categories/` | `list/ create/ view/ edit/ deactivate/ delete/ export/ roles/ workflow/` | Full CRUD + hard-delete + CSV export; per-role list views; duplicate-name validation |
| 05 | `05-warehouses/` | `list/ create/ view/ edit/ roles/ workflow/` | Full CRUD (no deactivate); per-role list and detail views |
| 06 | `06-suppliers/` | `list/ create/ view/ edit/ deactivate/ roles/ workflow/` | Full CRUD + deactivate/reactivate; per-role list views |
| 07 | `07-customers/` | `list/ create/ view/ edit/ deactivate/ roles/ workflow/` | Full CRUD + deactivate/reactivate; per-role list and detail views |
| 08 | `08-purchase-orders/` | `list/ create/ detail/ workflow/ roles/` | List, create, draft→submit, cancel, receive goods; warehouse role |
| 09 | `09-sales-orders/` | `list/ create/ detail/ workflow/ sod/ roles/` | List (3 roles), create, submit, approve, reject, issue goods; SOD constraint |
| 10 | `10-stock-ledger/` | `list/ filter/ roles/ workflow/` | All entries + filters (warehouse, product, type); mobile 360px; per-role views |
| 11 | `11-reports/` | `(root)` | Reports page, date filter, export buttons, per-role report dashboards |
| 12 | `12-users-rbac/` | `list/ create/ view/ edit/ deactivate/ rbac-403/ workflow/` | Full CRUD + 5 × role-403 guard screens; admin CRUD run |
| 13 | `13-notifications/` | `(root)` | Notification list, mark-all-read |
| 14 | `14-profile/` | `admin/ sales/` | Admin profile edit/save; Sales profile view |

PHPUnit run output (2026-10-04) sits directly in this folder: `phpunit-unit-*` and `phpunit-integration-*` as `.png` (screenshot), `.txt` (saved output) and `.html` (the readable log view the screenshot was captured from).

## Folder structure
```
docs/testing/screenshots/
├── 01-auth/                  login page, error, success
├── 02-dashboard/
│   ├── admin/
│   ├── sales/
│   └── warehouse/
├── 03-products/
│   ├── list/                 all-items, with-search-bar, warehouse-readonly
│   ├── create/               form-empty, form-filled, result
│   ├── view/                 detail
│   ├── edit/                 form-empty, form-filled, saved
│   └── deactivate/           deactivated, reactivated
├── 04-categories/
│   ├── list/ create/ view/ edit/ deactivate/
│   ├── delete/               delete-button, deleted-result
│   ├── export/               export-button
│   ├── roles/                admin-list, sales-list, warehouse-list
│   └── workflow/             admin-crud-final
├── 05-07  (warehouses/suppliers/customers)
│   ├── list/ create/ view/ edit/ deactivate/
│   ├── roles/                per-role list (and detail for warehouses, customers)
│   └── workflow/             admin CRUD run
├── 08-purchase-orders/
│   ├── list/ create/ detail/
│   ├── workflow/             submit, cancel, receive-goods
│   └── roles/                warehouse-list, warehouse-detail
├── 09-sales-orders/
│   ├── list/ create/ detail/
│   ├── workflow/             submit, pending-approval, approve, reject, issue-goods
│   ├── sod/                  sales-no-approve-button
│   └── roles/                warehouse-no-create
├── 10-stock-ledger/
│   ├── list/                 all-entries, mobile-360
│   ├── filter/
│   ├── roles/                admin-list, warehouse-list
│   └── workflow/             admin-final
├── 11-reports/               (flat)
├── 12-users-rbac/
│   ├── list/ create/ view/ edit/ deactivate/
│   ├── rbac-403/             per-role 403 screens
│   └── workflow/             admin-crud
├── 13-notifications/         (flat)
└── 14-profile/
    ├── admin/
    └── sales/
```

## Regenerate
```bash
cd tests/playwright
node screenshot-evidence.mjs
```
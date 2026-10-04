# Screenshot Evidence Index

Generated: 2026-10-04T14:39:49.933Z
App: http://localhost:8090

| # | Folder | Sub-folders | Coverage |
|---|---|---|---|
| 01 | `01-auth/` | `login/ error/ success/` | Login page, wrong-password error, successful login → dashboard |
| 02 | `02-dashboard/` | `admin/ sales/ warehouse/` | Per-role dashboards (Admin, Sales, Warehouse, Sales-special) |
| 03 | `03-products/` | `list/ create/ view/ edit/ deactivate/` | Full CRUD + search + warehouse readonly view |
| 04 | `04-categories/` | `list/ create/ view/ edit/ deactivate/ delete/ export/` | Full CRUD + hard-delete + CSV export |
| 05 | `05-warehouses/` | `list/ create/ view/ edit/` | Full CRUD (no deactivate) |
| 06 | `06-suppliers/` | `list/ create/ view/ edit/ deactivate/` | Full CRUD + deactivate/reactivate |
| 07 | `07-customers/` | `list/ create/ view/ edit/ deactivate/` | Full CRUD + deactivate/reactivate |
| 08 | `08-purchase-orders/` | `list/ create/ detail/ workflow/ roles/` | List, create, draft→submit, cancel, receive goods; warehouse role |
| 09 | `09-sales-orders/` | `list/ create/ detail/ workflow/ sod/ roles/` | List (3 roles), create, submit, approve, reject, issue goods; SOD constraint |
| 10 | `10-stock-ledger/` | `list/ filter/` | All entries + filters (warehouse, product, type) |
| 11 | `11-reports/` | `(root)` | Reports page, date filter, export buttons |
| 12 | `12-users-rbac/` | `list/ create/ view/ edit/ deactivate/ rbac-403/` | Full CRUD + 5 × role-403 guard screens |
| 13 | `13-notifications/` | `(root)` | Notification list, mark-all-read |
| 14 | `14-profile/` | `admin/ sales/` | Admin profile edit/save; Sales profile view |

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
│   └── export/               export-button
├── 05-07  (warehouses/suppliers/customers)
│   └── list/ create/ view/ edit/ deactivate/
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
│   ├── list/
│   └── filter/
├── 11-reports/               (flat)
├── 12-users-rbac/
│   ├── list/ create/ view/ edit/ deactivate/
│   └── rbac-403/             per-role 403 screens
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
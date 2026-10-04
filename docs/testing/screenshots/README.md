# Screenshot Evidence Index

Generated: 2026-10-04T14:23:33.279Z
App: http://localhost:8090

| # | Folder | Coverage |
|---|---|---|
| 01 | 01-auth | Login page, wrong password, successful login |
| 02 | 02-dashboard | Admin / Sales / Warehouse / Sales-special dashboards |
| 03 | 03-products | List, search, create (empty→filled→result), view, edit, deactivate/activate, warehouse read-only |
| 04 | 04-categories | List, create, view, edit, deactivate/activate, hard delete, CSV export |
| 05 | 05-warehouses | List, create, view (with stock), edit, deactivate/activate |
| 06 | 06-suppliers | List, create, view, edit, deactivate/activate |
| 07 | 07-customers | List, create, view, edit, deactivate/activate |
| 08 | 08-purchase-orders | List, create form, detail (draft), submit, cancel, receive-goods form, warehouse role |
| 09 | 09-sales-orders | List (3 roles), create, draft+submit, approve, reject, issue goods, SOD constraint |
| 10 | 10-stock-ledger | All entries, filtered by warehouse / product / type |
| 11 | 11-reports | Reports page, date filter, export buttons |
| 12 | 12-users-rbac | List, create, view, edit, deactivate/activate; 403 guards per role |
| 13 | 13-notifications | Notification list, mark-all-read, after mark-all-read |
| 14 | 14-profile | Admin profile, edit, saved; Sales profile |

## Regenerate
```bash
cd tests/playwright
node screenshot-evidence.mjs
```
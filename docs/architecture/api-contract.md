# API Contract — Product Availability

**Verified source date:** 2026-10-04. This describes the implemented endpoint, replacing the initial Stage 4 proposal. Controller smoke checks use fake repositories; an HTTP/container run against this checkout remains pending.

## Request

`GET /api/products/{sku}/availability`

Authentication uses the configured application session cookie (`SESSION_NAME`, default `iom_session`), with the same authenticated-user checks as the HTML application. The request has no body. SKU is a route string, not an obfuscated numeric id. No separate SKU-format rejection returning 400 is implemented; a lookup with no matching product returns 404.

## Responses

JSON responses use `Content-Type: application/json; charset=utf-8`. Stock is read live from the repository. Only active warehouses appear in the success payload; an existing product with no stock returns 200 with zero stock. Inactive products can still be looked up.

```json
{
  "sku": "SKU-A",
  "product_id": 3,
  "name": "Alpha",
  "unit": "pcs",
  "available": true,
  "total_stock": 10,
  "availability": [
    {
      "warehouse_id": 1,
      "warehouse_code": "WH-1",
      "warehouse_name": "Main",
      "quantity": 10
    }
  ]
}
```

| Status | Meaning | Body |
|---|---|---|
| 200 | Existing product; live stock lookup succeeded | Success object above |
| 401 | No authenticated user | `{"error":"unauthorized"}` |
| 404 | Product lookup succeeded but found no product | `{"error":"not_found","sku":"MISSING"}` |
| 500 | Product/stock dependency failed or an exception occurred | `{"error":"internal_error"}` |

| Success field | Type | Meaning |
|---|---|---|
| `sku`, `name`, `unit` | string | Product identity and unit |
| `product_id` | integer | Product database id |
| `available` | boolean | Whether active-warehouse stock sum is greater than zero |
| `total_stock` | integer | Active-warehouse stock sum |
| `availability` | array | Per active warehouse stock |
| `availability[].warehouse_id` | integer | Warehouse id |
| `availability[].warehouse_code`, `availability[].warehouse_name` | string | Warehouse identity |
| `availability[].quantity` | integer | Current stock quantity |

`total_quantity` and `warehouses` were names in the initial design and are not the implemented keys. Existing JavaScript consumers retain `available`, `total_stock`, and `unit` compatibility. Failed stock retrieval must not become a successful zero-stock response. The service retains array/null success/not-found behavior and returns a failed `Result` for dependency errors, which the controller translates to 500.

## Trace and verification

- [Route](../../config/routes.php) → [ProductApiController](../../app/Controller/ProductApiController.php) → [ProductService](../../app/Service/ProductService.php) → product and stock repository interfaces.
- [Regression scenarios](../../tests/Support/ReferenceBoundaryScenarios.php) check 401/404/500/200 controller responses, zero-stock success, and live-stock payload keys.
- [Testing instructions](../testing/README.md) explain the distinction between smoke evidence and full integration evidence.

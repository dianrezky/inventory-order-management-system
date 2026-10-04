# API Contract — Product Availability Endpoint

- **Status:** Initial — to be implemented in Stage 6
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related:** PRD §7 API-01, BR-016, BR-017

---

## Overview

Brief §2.6 API-01 mewajibkan minimal satu endpoint JSON terpisah dari HTML pages. Endpoint ini mendemonstrasikan kemampuan aplikasi untuk berkomunikasi via API (frontend JS Fetch atau integrasi eksternal masa depan).

**Endpoint:** `GET /api/products/{sku}/availability`

---

## Request Specification

| Aspek | Nilai |
|-------|-------|
| Method | `GET` |
| Path | `/api/products/{sku}/availability` |
| Auth | Session-based (cookie `PHPSESSID`) |
| Content-Type (req) | (tidak ada body) |
| Path Parameter | `sku` (string, required) |

### Path Parameter: `sku`

| Validation | Error Code |
|------------|-----------|
| Pattern `^[A-Z0-9-]{3,50}$` | `400 bad_request` (jika format invalid) |
| Wajib ada di DB | `404 not_found` |

---

## Response Specification

### 200 OK — Product Ditemukan

**Headers:**
```
Content-Type: application/json; charset=utf-8
Cache-Control: no-store
```

**Body:**
```json
{
  "sku": "PROD-001",
  "name": "Buku Tulis 80 Lembar",
  "total_quantity": 125,
  "warehouses": [
    {
      "warehouse_code": "WH-JKT",
      "warehouse_name": "Gudang Jakarta",
      "quantity": 100
    },
    {
      "warehouse_code": "WH-BDG",
      "warehouse_name": "Gudang Bandung",
      "quantity": 25
    }
  ]
}
```

**Field contract:**

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `sku` | string | no | Echo of input SKU |
| `name` | string | no | Nama produk (fallback ke as-is kalau I18N-02 tidak aktif) |
| `total_quantity` | integer | no | Sum dari semua warehouses |
| `warehouses` | array | no | List per warehouse |
| `warehouses[].warehouse_code` | string | no | |
| `warehouses[].warehouse_name` | string | no | |
| `warehouses[].quantity` | integer | no | Non-negative |

**Note:** Hanya `is_active=1` warehouse yang muncul. Produk `is_active=0` tetap return 200 (info masih valid), tapi di aplikasi normal UI tidak akan request ini untuk produk nonaktif.

### 401 Unauthorized

```json
{
  "error": "unauthenticated",
  "message": "Authentication required"
}
```

Trigger: tidak ada session aktif / session expired.

### 404 Not Found

```json
{
  "error": "not_found",
  "message": "Product with SKU 'PROD-XXX' not found"
}
```

Trigger: SKU tidak ada di DB.

### 403 Forbidden

Tidak berlaku untuk endpoint ini (semua role authenticated boleh cek availability). Jika di kemudian hari perlu restrict, endpoint akan di-prefix dengan role check.

### 500 Internal Server Error (rare)

```json
{
  "error": "internal_error",
  "message": "An unexpected error occurred"
}
```

Trigger: DB down, unexpected exception. Detail error di-log ke file, tidak di-expose.

---

## Examples

### Example 1 — Happy Path

**Request:**
```http
GET /api/products/PROD-001/availability HTTP/1.1
Host: localhost
Cookie: PHPSESSID=abc123
Accept: application/json
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8

{"sku":"PROD-001","name":"Buku Tulis 80 Lembar","total_quantity":125,"warehouses":[{"warehouse_code":"WH-JKT","warehouse_name":"Gudang Jakarta","quantity":100},{"warehouse_code":"WH-BDG","warehouse_name":"Gudang Bandung","quantity":25}]}
```

### Example 2 — Not Found

**Request:**
```http
GET /api/products/PROD-XXX/availability HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Response:**
```http
HTTP/1.1 404 Not Found
Content-Type: application/json; charset=utf-8

{"error":"not_found","message":"Product with SKU 'PROD-XXX' not found"}
```

### Example 3 — Unauthenticated

**Request:**
```http
GET /api/products/PROD-001/availability HTTP/1.1
(no session cookie)
```

**Response:**
```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json; charset=utf-8

{"error":"unauthenticated","message":"Authentication required"}
```

### Example 4 — Bad Request (SKU format invalid)

**Request:**
```http
GET /api/products/abc@123/availability HTTP/1.1
```

**Response:**
```http
HTTP/1.1 400 Bad Request
Content-Type: application/json; charset=utf-8

{"error":"bad_request","message":"Invalid SKU format"}
```

---

## Implementation Notes

### Routing

Di `public/index.php` (front controller):

```php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API
if (preg_match('#^/api/products/([A-Z0-9-]+)/availability$#', $path, $m)) {
    (new ProductApiController($container))->getAvailability($m[1]);
    return;
}

// ... HTML routes ...
```

### Controller

```php
final class ProductApiController extends BaseController
{
    public function __construct(private Container $container) {}

    public function getAvailability(string $sku): void
    {
        // 1. Auth check
        $user = $this->container->getAuthService()->currentUser();
        if (!$user) {
            $this->json(['error' => 'unauthenticated', 'message' => 'Authentication required'], 401);
            return;
        }

        // 2. Validate SKU format
        if (!preg_match('/^[A-Z0-9-]{3,50}$/', $sku)) {
            $this->json(['error' => 'bad_request', 'message' => 'Invalid SKU format'], 400);
            return;
        }

        // 3. Lookup
        $product = $this->container->getProductRepository()->findBySku($sku);
        if (!$product) {
            $this->json(['error' => 'not_found', 'message' => "Product with SKU '$sku' not found"], 404);
            return;
        }

        // 4. Get stocks
        $stocks = $this->container->getProductStockRepository()->findByProductId($product->id);

        // 5. Build response (filter active warehouses only)
        $total = 0;
        $warehouses = [];
        foreach ($stocks as $stock) {
            $wh = $this->container->getWarehouseRepository()->findById($stock->warehouseId);
            if (!$wh || !$wh->isActive) continue;
            $warehouses[] = [
                'warehouse_code' => $wh->code,
                'warehouse_name' => $wh->name,
                'quantity' => $stock->quantity,
            ];
            $total += $stock->quantity;
        }

        $this->json([
            'sku' => $product->sku,
            'name' => $product->name,
            'total_quantity' => $total,
            'warehouses' => $warehouses,
        ], 200);
    }
}
```

### Routing for HTML endpoints (not in API path)

Sisanya tetap return HTML. Untuk 404 API path → return JSON juga:

```php
// Trailing / catch-all untuk API yang tidak ada
if (str_starts_with($path, '/api/')) {
    $this->json(['error' => 'not_found', 'message' => 'Endpoint not found'], 404);
    return;
}

// HTML 404
http_response_code(404);
include __DIR__ . '/../views/errors/404.php';
```

### Security

1. **SQL injection:** Parameterized queries di seluruh repository (BR-016).
2. **Auth check server-side:** `currentUser()` di Controller, bukan di UI (BR-017).
3. **No PII leak:** response tidak expose created_by, password, dsb.
4. **Rate limiting:** Di luar scope MVP. Untuk v2.0.

### Testing

| Test | Expected |
|------|----------|
| Authenticated + valid SKU + product exists | 200 + body sesuai |
| Authenticated + valid SKU + product not exist | 404 |
| Unauthenticated + valid SKU | 401 |
| SKU dengan format invalid | 400 |
| DB down | 500 + log ke error_log |

---

## Future API Endpoints (Catatan v2.0)

Untuk saat ini **cukup 1 endpoint**. Kalau di v2.0 perlu lebih:

- `GET /api/products` (paginated list)
- `GET /api/sales-orders/{id}` (Sales scope)
- `POST /api/sales-orders` (create)
- `POST /api/sales-orders/{id}/approve` (admin)
- `GET /api/stock-ledger?from=&to=`

Tapi untuk **Stage 6 implementasi MVP, cukup endpoint availability di atas.** Justifikasi: Brief §2.6 API-01 secara eksplisit hanya minta "minimal satu endpoint".

---

## References
- `docs/planning/prd.md` §7 API-01
- `docs/architecture/class-diagram-initial.md` (ProductApiController)
- RFC 7231 (HTTP semantics)
- RFC 8259 (JSON)